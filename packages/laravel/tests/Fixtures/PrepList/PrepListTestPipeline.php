<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\PrepList;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Foundation\Application;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Idempotency\IdempotencyClock;
use SurfaceRelay\Laravel\Idempotency\IdempotencyIntentHasher;
use SurfaceRelay\Laravel\Idempotency\IdempotencyKeyHasher;
use SurfaceRelay\Laravel\Idempotency\IdempotencyKeyValidator;
use SurfaceRelay\Laravel\Idempotency\IdempotencyRecord;
use SurfaceRelay\Laravel\Idempotency\IdempotencyRecordState;
use SurfaceRelay\Laravel\Idempotency\IdempotencyReplayCodec;
use SurfaceRelay\Laravel\Idempotency\IdempotencyService;
use SurfaceRelay\Laravel\Idempotency\IdempotencyStage;
use SurfaceRelay\Laravel\Idempotency\IdempotencyStore;
use SurfaceRelay\Laravel\Idempotency\IdempotencyStoreClaimResult;
use SurfaceRelay\Laravel\Registry\InMemoryActionRegistry;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionBus;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionCall;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionExecutionStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineAuditor;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineDecision;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStageHandler;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;
use SurfaceRelay\Laravel\Runtime\Pipeline\AuthorizationStage;
use SurfaceRelay\Laravel\Validation\InMemoryActionValidationRules;
use SurfaceRelay\Laravel\Validation\LaravelInputValidationStage;

final readonly class PrepListTestPipeline
{
    public function __construct(
        public ActionDefinition $definition,
        public InMemoryActionRegistry $registry,
        public PrepListStore $store,
        public AddPrepListItem $addItem,
        public PrepListAuthorizer $authorizer,
        public PrepListActionGateway $gateway,
    ) {}

    public static function boot(Application $app): self
    {
        $definition = self::definition();
        $registry = new InMemoryActionRegistry();
        $registry->register($definition);

        $rules = new InMemoryActionValidationRules();
        $rules->register($definition, [
            'name' => ['required', 'string', 'min:1'],
        ]);

        $store = new PrepListStore();
        $addItem = new AddPrepListItem($store);
        $authorizer = new PrepListAuthorizer();
        $executor = new PrepListActionExecutor($addItem);
        $contextFactory = new PrepListInvocationContextFactory();
        $idempotencyService = new IdempotencyService(
            new PrepListIdempotencyStore(),
            new PrepListIdempotencyClock(),
            new IdempotencyReplayCodec(),
        );

        $handlers = [
            new LaravelInputValidationStage($app->make(ValidationFactory::class), $rules),
            new AuthorizationStage($authorizer),
            new IdempotencyStage(
                new IdempotencyKeyValidator(),
                new IdempotencyKeyHasher(),
                new IdempotencyIntentHasher(),
                $idempotencyService,
            ),
            self::passThrough(ActionPipelineStage::Confirmation),
            new ActionExecutionStage($executor, $idempotencyService),
            self::passThrough(ActionPipelineStage::OutputPolicy),
        ];

        $auditor = new class implements ActionPipelineAuditor {
            public int $calls = 0;

            public function record(ActionCall $call, ActionPipelineOutcome $outcome): void
            {
                ++$this->calls;
            }
        };

        $bus = new ActionBus($registry, $auditor, $handlers);
        $gateway = new PrepListActionGateway($bus, $contextFactory);

        // Livewire's boot() lifecycle hook resolves this exact gateway from
        // the Testbench application on every component request/hydration.
        $app->instance(PrepListActionGateway::class, $gateway);

        return new self($definition, $registry, $store, $addItem, $authorizer, $gateway);
    }

    private static function passThrough(ActionPipelineStage $stage): ActionPipelineStageHandler
    {
        return new class($stage) implements ActionPipelineStageHandler {
            public function __construct(
                private readonly ActionPipelineStage $pipelineStage,
            ) {}

            public function stage(): ActionPipelineStage
            {
                return $this->pipelineStage;
            }

            public function process(ActionPipelineState $state): ActionPipelineDecision
            {
                return ActionPipelineDecision::continueWith($state);
            }
        };
    }

    private static function definition(): ActionDefinition
    {
        return new ActionDefinition(
            id: 'prep_list.add_item',
            version: 1,
            title: 'Add preparation item',
            description: 'Adds an item to the shared preparation list.',
            inputSchema: [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'name' => ['type' => 'string', 'minLength' => 1],
                ],
                'required' => ['name'],
            ],
            outputSchema: [
                'type' => 'object',
                'properties' => [
                    'itemId' => ['type' => 'string'],
                ],
                'required' => ['itemId'],
            ],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::ReversibleWrite,
            risk: ActionRisk::Low,
            idempotency: IdempotencyPolicy::RecommendedKey,
            outputSensitivity: OutputSensitivity::Normal,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [ContextRequirement::BrowserSession],
        );
    }
}

final class PrepListIdempotencyClock implements IdempotencyClock
{
    public function now(): int
    {
        return time();
    }
}

final class PrepListIdempotencyStore implements IdempotencyStore
{
    /** @var array<string, IdempotencyRecord> */
    private array $records = [];

    public function find(string $keyHash): ?IdempotencyRecord
    {
        return $this->records[$keyHash] ?? null;
    }

    public function claim(IdempotencyRecord $fresh, int $now): IdempotencyStoreClaimResult
    {
        $existing = $this->records[$fresh->keyHash] ?? null;
        if ($existing !== null && $existing->isActiveAt($now)) {
            return IdempotencyStoreClaimResult::existing($existing);
        }

        $this->records[$fresh->keyHash] = $fresh;
        return IdempotencyStoreClaimResult::claimed($fresh);
    }

    public function complete(string $keyHash, string $intentFingerprint, string $outputPayload): void
    {
        $record = $this->records[$keyHash] ?? throw new \RuntimeException('Missing PrepList idempotency claim.');
        if (!hash_equals($record->intentFingerprint, $intentFingerprint)) {
            throw new \RuntimeException('PrepList idempotency intent mismatch.');
        }

        $this->records[$keyHash] = new IdempotencyRecord(
            $record->keyHash,
            $record->intentFingerprint,
            IdempotencyRecordState::Completed,
            $outputPayload,
            $record->createdAt,
            $record->expiresAt,
        );
    }

    public function markIndeterminate(string $keyHash, string $intentFingerprint): void
    {
        $record = $this->records[$keyHash] ?? throw new \RuntimeException('Missing PrepList idempotency claim.');
        if (!hash_equals($record->intentFingerprint, $intentFingerprint)) {
            throw new \RuntimeException('PrepList idempotency intent mismatch.');
        }

        $this->records[$keyHash] = new IdempotencyRecord(
            $record->keyHash,
            $record->intentFingerprint,
            IdempotencyRecordState::Indeterminate,
            null,
            $record->createdAt,
            $record->expiresAt,
        );
    }
}
