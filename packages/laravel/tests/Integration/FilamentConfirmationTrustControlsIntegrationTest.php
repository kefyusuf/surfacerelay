<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Integration;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\Schema\Blueprint;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase;
use SurfaceRelay\Laravel\Confirmation\ConfirmationScopeHasher;
use SurfaceRelay\Laravel\Confirmation\ConfirmationService;
use SurfaceRelay\Laravel\Confirmation\ConfirmationStage;
use SurfaceRelay\Laravel\Contracts\ActionAuthorizer;
use SurfaceRelay\Laravel\Contracts\ActionExecutor;
use SurfaceRelay\Laravel\Contracts\AuthenticatedActorResolver;
use SurfaceRelay\Laravel\Contracts\TenantResolver;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Filament\Confirmation\FilamentConfirmationBridge;
use SurfaceRelay\Laravel\Filament\Context\FilamentContextExposure;
use SurfaceRelay\Laravel\Filament\Context\FilamentInvocationContextFactory;
use SurfaceRelay\Laravel\Filament\Invocation\FilamentActionGateway;
use SurfaceRelay\Laravel\Idempotency\IdempotencyIntentHasher;
use SurfaceRelay\Laravel\Idempotency\IdempotencyKeyHasher;
use SurfaceRelay\Laravel\Idempotency\IdempotencyKeyValidator;
use SurfaceRelay\Laravel\Idempotency\IdempotencyReplayCodec;
use SurfaceRelay\Laravel\Idempotency\IdempotencyService;
use SurfaceRelay\Laravel\Idempotency\IdempotencyStage;
use SurfaceRelay\Laravel\OutputPolicy\OutputPolicyStage;
use SurfaceRelay\Laravel\Registry\InMemoryActionRegistry;
use SurfaceRelay\Laravel\Result\CoreActionErrorCode;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\ResolvedTrustedValue;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextComposer;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionBus;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionCall;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionExecutionStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineAuditor;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;
use SurfaceRelay\Laravel\Runtime\Pipeline\AuthorizationStage;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestConfirmationPage;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestConfirmationRecordPage;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestConfirmationTablePage;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestRecord;
use SurfaceRelay\Laravel\Tests\Support\FilamentConfirmationIdempotencyClock;
use SurfaceRelay\Laravel\Tests\Support\FilamentConfirmationIdempotencyStore;
use SurfaceRelay\Laravel\Tests\Support\FilamentConfirmationMemoryStore;
use SurfaceRelay\Laravel\Tests\Support\FilamentConfirmationMutableClock;
use SurfaceRelay\Laravel\Tests\Support\FilamentConfirmationSequenceTokenGenerator;
use SurfaceRelay\Laravel\Validation\InMemoryActionValidationRules;
use SurfaceRelay\Laravel\Validation\LaravelInputValidationStage;

final class FilamentConfirmationTrustControlsIntegrationTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LivewireServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', '0123456789abcdef0123456789abcdef');
        $app['config']->set('view.paths', [dirname(__DIR__) . '/Fixtures/views']);
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['db']->connection()->getSchemaBuilder()->create(
            'filament_test_records',
            static function (Blueprint $table): void {
                $table->integer('id')->primary();
                $table->string('name');
            },
        );
    }

    public function test_explicit_retry_executes_once_and_lost_response_retry_replays_without_second_confirmation(): void
    {
        $harness = $this->harness();
        $page = $this->plainPage();

        $first = $harness->dispatch($page, key: 'refund-K');
        $challenge = $this->assertConfirmationRequired($first);
        self::assertSame(0, $harness->executor->calls);
        self::assertSame($challenge->challengeId, $page->surfaceRelayConfirmationChallengeId);

        $page->callMountedAction();
        self::assertSame(0, $harness->executor->calls, 'Human approval must not execute business code.');

        $second = $harness->dispatch(
            $page,
            key: 'refund-K',
            receipt: $challenge->challengeId,
            correlationId: 'corr-happy-2',
        );
        self::assertTrue($second->completed);
        self::assertSame(1, $harness->executor->calls);

        $replay = $harness->dispatch(
            $page,
            key: 'refund-K',
            receipt: null,
            correlationId: 'corr-happy-replay',
        );
        self::assertTrue($replay->completed, 'Completed idempotency retry must replay without a second receipt.');
        self::assertSame(1, $harness->executor->calls, 'Lost-response retry must not duplicate the side effect.');

        $spent = $harness->dispatch(
            $page,
            key: 'refund-K-fresh',
            receipt: $challenge->challengeId,
            correlationId: 'corr-happy-spent',
        );
        $this->assertConfirmationRequired($spent);
        self::assertSame(1, $harness->executor->calls, 'A consumed receipt must never execute a fresh attempt.');
    }

    public function test_record_drift_rejects_old_receipt_without_spending_it(): void
    {
        $recordA = TestRecord::query()->create(['id' => 1, 'name' => 'record-A']);
        $recordB = TestRecord::query()->create(['id' => 2, 'name' => 'record-B']);
        $harness = $this->harness();
        $page = $this->recordPage($recordA);

        $first = $harness->dispatch($page, key: 'record-K');
        $challenge = $this->assertConfirmationRequired($first);
        $page->callMountedAction();

        $page->record = $recordB;
        $wrong = $harness->dispatch(
            $page,
            key: 'record-K',
            receipt: $challenge->challengeId,
            correlationId: 'corr-record-B',
        );
        $this->assertConfirmationRequired($wrong);
        self::assertSame(0, $harness->executor->calls);
        $this->cancelPresentedChallenge($page);

        $page->record = $recordA;
        $exact = $harness->dispatch(
            $page,
            key: 'record-K',
            receipt: $challenge->challengeId,
            correlationId: 'corr-record-A-exact',
        );
        self::assertTrue($exact->completed);
        self::assertSame(1, $harness->executor->calls);
    }

    public function test_selection_drift_rejects_old_receipt_without_spending_it(): void
    {
        foreach ([10, 11, 15] as $id) {
            TestRecord::query()->create(['id' => $id, 'name' => 'record-' . $id]);
        }

        $harness = $this->harness();
        $page = $this->tablePage();
        $page->selectedTableRecords = [10, 11];

        $first = $harness->dispatch($page, key: 'selection-K');
        $challenge = $this->assertConfirmationRequired($first);
        $page->callMountedAction();

        $page->selectedTableRecords = [15];
        $wrong = $harness->dispatch(
            $page,
            key: 'selection-K',
            receipt: $challenge->challengeId,
            correlationId: 'corr-selection-B',
        );
        $this->assertConfirmationRequired($wrong);
        self::assertSame(0, $harness->executor->calls);
        $this->cancelPresentedChallenge($page);

        $page->selectedTableRecords = [10, 11];
        $exact = $harness->dispatch(
            $page,
            key: 'selection-K',
            receipt: $challenge->challengeId,
            correlationId: 'corr-selection-A-exact',
        );
        self::assertTrue($exact->completed);
        self::assertSame(1, $harness->executor->calls);
    }

    public function test_only_applied_filter_drift_changes_confirmation_scope(): void
    {
        TestRecord::query()->create(['id' => 21, 'name' => 'record-21']);
        $harness = $this->harness();
        $page = $this->tablePage();
        $exposure = FilamentContextExposure::activeFilters();

        $this->applyFilters($page, status: true, priority: false);
        $scopeA = $harness->scopeFingerprint($page, $exposure);
        $first = $harness->dispatch($page, key: 'filter-K', exposure: $exposure);
        $challenge = $this->assertConfirmationRequired($first);
        $page->callMountedAction();

        $page->getTableFiltersForm()->fill([
            'status' => ['isActive' => false],
            'priority' => ['isActive' => true],
        ]);
        self::assertSame(
            $scopeA,
            $harness->scopeFingerprint($page, $exposure),
            'Deferred form edits must not change trusted applied-filter authority.',
        );

        $page->applyTableFilters();
        self::assertNotSame($scopeA, $harness->scopeFingerprint($page, $exposure));
        $wrong = $harness->dispatch(
            $page,
            key: 'filter-K',
            receipt: $challenge->challengeId,
            correlationId: 'corr-filter-B',
            exposure: $exposure,
        );
        $this->assertConfirmationRequired($wrong);
        self::assertSame(0, $harness->executor->calls);
        $this->cancelPresentedChallenge($page);

        $this->applyFilters($page, status: true, priority: false);
        self::assertSame($scopeA, $harness->scopeFingerprint($page, $exposure));
        $exact = $harness->dispatch(
            $page,
            key: 'filter-K',
            receipt: $challenge->challengeId,
            correlationId: 'corr-filter-A-exact',
            exposure: $exposure,
        );
        self::assertTrue($exact->completed);
        self::assertSame(1, $harness->executor->calls);
    }

    public function test_actor_tenant_binding_surface_and_validated_input_mismatches_do_not_spend_old_receipt(): void
    {
        $cases = [
            'actor' => static function (FilamentConfirmationE2EHarness $harness, array &$call): void {
                $harness->actor->value = 'actor-B';
            },
            'tenant' => static function (FilamentConfirmationE2EHarness $harness, array &$call): void {
                $harness->tenant->value = 'tenant-B';
            },
            'binding' => static function (FilamentConfirmationE2EHarness $harness, array &$call): void {
                $call['bindingId'] = 'binding-B';
            },
            'surface' => static function (FilamentConfirmationE2EHarness $harness, array &$call): void {
                $call['surface'] = 'filament-other';
            },
            'validated input' => static function (FilamentConfirmationE2EHarness $harness, array &$call): void {
                $call['input'] = ['amount' => 101, 'ignored' => 'different-unvalidated-value'];
            },
        ];

        foreach ($cases as $name => $mutate) {
            $harness = $this->harness();
            $page = $this->plainPage();
            $key = 'dimension-' . str_replace(' ', '-', $name);

            $first = $harness->dispatch($page, key: $key, input: ['amount' => 100, 'ignored' => 'first']);
            $challenge = $this->assertConfirmationRequired($first);
            $page->callMountedAction();

            $wrongCall = [
                'input' => ['amount' => 100, 'ignored' => 'second'],
                'surface' => 'filament',
                'bindingId' => 'binding-A',
            ];
            $mutate($harness, $wrongCall);

            $wrong = $harness->dispatch(
                $page,
                key: $key,
                receipt: $challenge->challengeId,
                correlationId: 'corr-wrong-' . str_replace(' ', '-', $name),
                input: $wrongCall['input'],
                surface: $wrongCall['surface'],
                bindingId: $wrongCall['bindingId'],
            );
            $this->assertConfirmationRequired($wrong);
            self::assertSame(0, $harness->executor->calls, $name);
            $this->cancelPresentedChallenge($page);

            $harness->actor->value = 'actor-A';
            $harness->tenant->value = 'tenant-A';
            $exact = $harness->dispatch(
                $page,
                key: $key,
                receipt: $challenge->challengeId,
                correlationId: 'corr-exact-' . str_replace(' ', '-', $name),
                input: ['amount' => 100, 'ignored' => 'third'],
                surface: 'filament',
                bindingId: 'binding-A',
            );
            self::assertTrue($exact->completed, $name);
            self::assertSame(1, $harness->executor->calls, $name);
        }
    }

    private function harness(): FilamentConfirmationE2EHarness
    {
        return FilamentConfirmationE2EHarness::boot($this->app);
    }

    private function plainPage(): TestConfirmationPage
    {
        $page = new TestConfirmationPage();
        $page->bootedInteractsWithActions();

        return $page;
    }

    private function recordPage(TestRecord $record): TestConfirmationRecordPage
    {
        $page = new TestConfirmationRecordPage();
        $page->record = $record;
        $page->bootedInteractsWithActions();

        return $page;
    }

    private function tablePage(): TestConfirmationTablePage
    {
        $page = new TestConfirmationTablePage();
        $page->bootedInteractsWithTable();
        $page->bootedInteractsWithActions();

        return $page;
    }

    private function applyFilters(TestConfirmationTablePage $page, bool $status, bool $priority): void
    {
        $page->getTableFiltersForm()->fill([
            'status' => ['isActive' => $status],
            'priority' => ['isActive' => $priority],
        ]);
        $page->applyTableFilters();
    }

    private function cancelPresentedChallenge(TestConfirmationPage|TestConfirmationRecordPage|TestConfirmationTablePage $page): void
    {
        $cancel = $page->getMountedAction()?->getModalCancelAction();
        self::assertNotNull($cancel);
        $cancel->call();
        $page->unmountAction();
    }

    private function assertConfirmationRequired(ActionPipelineOutcome $outcome): \SurfaceRelay\Laravel\Result\ConfirmationChallenge
    {
        self::assertFalse($outcome->completed);
        self::assertSame(CoreActionErrorCode::CONFIRMATION_REQUIRED, $outcome->halt?->code);
        self::assertNotNull($outcome->halt?->confirmation);

        return $outcome->halt->confirmation;
    }
}

final class FilamentConfirmationE2EHarness
{
    public readonly ActionDefinition $definition;
    public readonly FilamentConfirmationMemoryStore $confirmationStore;
    public readonly ConfirmationService $confirmationService;
    public readonly FilamentConfirmationIdempotencyStore $idempotencyStore;
    public readonly IdempotencyService $idempotencyService;
    public readonly MutableConfirmationActorResolver $actor;
    public readonly MutableConfirmationTenantResolver $tenant;
    public readonly CountingConfirmationExecutor $executor;
    public readonly FilamentActionGateway $gateway;
    private readonly TrustedContextComposer $composer;

    private function __construct(\Illuminate\Foundation\Application $app)
    {
        $this->definition = new ActionDefinition(
            id: 'orders.refund',
            version: 1,
            title: 'Refund order',
            description: 'Exercise the T-504 confirmation bridge.',
            inputSchema: [
                'type' => 'object',
                'properties' => ['amount' => ['type' => 'integer']],
                'required' => ['amount'],
                'additionalProperties' => false,
            ],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::ExternalSideEffect,
            risk: ActionRisk::Consequential,
            idempotency: IdempotencyPolicy::RequiredKey,
            outputSensitivity: OutputSensitivity::Normal,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [],
        );

        $registry = new InMemoryActionRegistry();
        $registry->register($this->definition);
        $rules = new InMemoryActionValidationRules();
        $rules->register($this->definition, ['amount' => ['required', 'integer']]);

        $this->confirmationStore = new FilamentConfirmationMemoryStore();
        $this->confirmationService = new ConfirmationService(
            $this->confirmationStore,
            new FilamentConfirmationMutableClock(),
            new FilamentConfirmationSequenceTokenGenerator(array_map(
                static fn (string $seed): string => str_repeat($seed, 43),
                range('A', 'Z'),
            )),
        );
        $app->instance(ConfirmationService::class, $this->confirmationService);

        $this->idempotencyStore = new FilamentConfirmationIdempotencyStore();
        $this->idempotencyService = new IdempotencyService(
            $this->idempotencyStore,
            new FilamentConfirmationIdempotencyClock(),
            new IdempotencyReplayCodec(),
        );
        $this->actor = new MutableConfirmationActorResolver('actor-A');
        $this->tenant = new MutableConfirmationTenantResolver('tenant-A');
        $this->composer = new TrustedContextComposer($this->actor, $this->tenant);
        $this->executor = new CountingConfirmationExecutor();

        $bus = new ActionBus(
            registry: $registry,
            auditor: new NoopConfirmationE2EAuditor(),
            handlers: [
                new LaravelInputValidationStage($app->make(ValidationFactory::class), $rules),
                new AuthorizationStage(new AllowConfirmationAuthorizer()),
                new IdempotencyStage(
                    new IdempotencyKeyValidator(),
                    new IdempotencyKeyHasher(),
                    new IdempotencyIntentHasher(),
                    $this->idempotencyService,
                ),
                new ConfirmationStage($this->confirmationService, new ConfirmationScopeHasher()),
                new ActionExecutionStage($this->executor, $this->idempotencyService),
                new OutputPolicyStage(),
            ],
        );

        $this->gateway = new FilamentActionGateway(
            bus: $bus,
            baseComposer: $this->composer,
            confirmationBridge: new FilamentConfirmationBridge(),
        );
    }

    public static function boot(\Illuminate\Foundation\Application $app): self
    {
        return new self($app);
    }

    /** @param array<string, mixed> $input */
    public function dispatch(
        \Filament\Resources\Pages\Page $page,
        string $key,
        ?string $receipt = null,
        string $correlationId = 'corr-happy-1',
        array $input = ['amount' => 100],
        string $surface = 'filament',
        string $bindingId = 'binding-A',
        ?FilamentContextExposure $exposure = null,
    ): ActionPipelineOutcome {
        return $this->gateway->dispatch(
            page: $page,
            actionId: $this->definition->id,
            actionVersion: $this->definition->version,
            input: $input,
            surface: $surface,
            correlationId: $correlationId,
            bindingId: $bindingId,
            confirmationReceipt: $receipt,
            idempotencyKey: $key,
            contextExposure: $exposure,
        );
    }

    /** @param array<string, mixed> $input */
    public function scopeFingerprint(
        \Filament\Resources\Pages\Page $page,
        ?FilamentContextExposure $exposure,
        array $input = ['amount' => 100],
        string $surface = 'filament',
        string $bindingId = 'binding-A',
    ): string {
        $context = (new FilamentInvocationContextFactory($this->composer))->forPage(
            page: $page,
            surface: $surface,
            correlationId: 'corr-scope-probe',
            idempotencyKey: 'scope-probe-key',
            contextExposure: $exposure,
        );

        return (new ConfirmationScopeHasher())->fingerprint(new ActionPipelineState(
            definition: $this->definition,
            input: ['amount' => $input['amount']],
            context: $context,
            bindingId: $bindingId,
        ));
    }
}

final class MutableConfirmationActorResolver implements AuthenticatedActorResolver
{
    public function __construct(public string $value) {}

    public function resolve(): ?ResolvedTrustedValue
    {
        return new ResolvedTrustedValue(
            $this->value,
            new ContextProvenance('test.authenticated_actor'),
            'actor-scope:' . $this->value,
        );
    }
}

final class MutableConfirmationTenantResolver implements TenantResolver
{
    public function __construct(public string $value) {}

    public function resolve(): ?ResolvedTrustedValue
    {
        return new ResolvedTrustedValue(
            $this->value,
            new ContextProvenance('test.tenant'),
            'tenant-scope:' . $this->value,
        );
    }
}

final class AllowConfirmationAuthorizer implements ActionAuthorizer
{
    public function allows(ActionDefinition $definition, array $input, \SurfaceRelay\Laravel\Runtime\InvocationContext $context): bool
    {
        return true;
    }
}

final class CountingConfirmationExecutor implements ActionExecutor
{
    public int $calls = 0;

    public function execute(ActionDefinition $definition, array $input, \SurfaceRelay\Laravel\Runtime\InvocationContext $context): mixed
    {
        ++$this->calls;

        return ['execution' => $this->calls, 'amount' => $input['amount']];
    }
}

final class NoopConfirmationE2EAuditor implements ActionPipelineAuditor
{
    public function record(ActionCall $call, ActionPipelineOutcome $outcome): void {}
}
