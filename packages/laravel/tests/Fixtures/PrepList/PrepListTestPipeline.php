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

        $handlers = [
            new LaravelInputValidationStage($app->make(ValidationFactory::class), $rules),
            new AuthorizationStage($authorizer),
            self::passThrough(ActionPipelineStage::Confirmation),
            self::passThrough(ActionPipelineStage::Idempotency),
            new ActionExecutionStage($executor),
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
