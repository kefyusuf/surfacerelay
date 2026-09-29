<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use SurfaceRelay\Laravel\Contracts\ActionExecutor;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\OutputPolicy\OutputPolicyStage;
use SurfaceRelay\Laravel\Registry\InMemoryActionRegistry;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionBus;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionCall;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionExecutionStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineAuditor;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineDecision;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStageHandler;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;
use SurfaceRelay\Laravel\SurfaceRelayServiceProvider;

if ($argc !== 2 || !is_string($argv[1]) || $argv[1] === '') {
    fwrite(STDERR, "usage: php smoke.php <consumer-root>\n");
    exit(64);
}

$consumerRoot = realpath($argv[1]);
if ($consumerRoot === false || !is_dir($consumerRoot)) {
    fwrite(STDERR, "clean consumer root does not exist\n");
    exit(65);
}

$autoload = $consumerRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "clean consumer vendor/autoload.php is missing\n");
    exit(66);
}

require $autoload;

final class T802SmokePassThroughStage implements ActionPipelineStageHandler
{
    public function __construct(private readonly ActionPipelineStage $ownedStage)
    {
    }

    public function stage(): ActionPipelineStage
    {
        return $this->ownedStage;
    }

    public function process(ActionPipelineState $state): ActionPipelineDecision
    {
        return ActionPipelineDecision::continueWith($state);
    }
}

final class T802SmokeExecutor implements ActionExecutor
{
    public function execute(
        ActionDefinition $definition,
        array $input,
        InvocationContext $context,
    ): mixed {
        if ($definition->id !== 'consumer.health.read' || $definition->version !== 1) {
            throw new RuntimeException('unexpected smoke Action identity');
        }
        if ($input !== []) {
            throw new RuntimeException('smoke Action input must stay empty');
        }

        return ['status' => 'ok'];
    }
}

final class T802SmokeAuditor implements ActionPipelineAuditor
{
    public int $calls = 0;

    public function record(ActionCall $call, ActionPipelineOutcome $outcome): void
    {
        $this->calls++;
    }
}

$app = new Application($consumerRoot);
$app->register(SurfaceRelayServiceProvider::class);

$definition = new ActionDefinition(
    id: 'consumer.health.read',
    version: 1,
    title: 'Consumer health read',
    description: 'Returns one side-effect-free clean-consumer smoke value.',
    inputSchema: [
        'type' => 'object',
        'properties' => new stdClass(),
        'additionalProperties' => false,
    ],
    scope: ActionScope::Portable,
    effect: ActionEffect::Read,
    risk: ActionRisk::Low,
    idempotency: IdempotencyPolicy::None,
    outputSensitivity: OutputSensitivity::Normal,
    outputContentTrust: OutputContentTrust::TrustedApplicationData,
    contextRequirements: [],
    outputSchema: [
        'type' => 'object',
        'required' => ['status'],
        'properties' => [
            'status' => ['const' => 'ok'],
        ],
        'additionalProperties' => false,
    ],
);

$registry = new InMemoryActionRegistry();
$registry->register($definition);

$auditor = new T802SmokeAuditor();
$bus = new ActionBus(
    registry: $registry,
    auditor: $auditor,
    handlers: [
        new T802SmokePassThroughStage(ActionPipelineStage::InputValidation),
        new T802SmokePassThroughStage(ActionPipelineStage::Authorization),
        new T802SmokePassThroughStage(ActionPipelineStage::Idempotency),
        new T802SmokePassThroughStage(ActionPipelineStage::Confirmation),
        new ActionExecutionStage(new T802SmokeExecutor()),
        new OutputPolicyStage(),
    ],
);

$outcome = $bus->dispatch(new ActionCall(
    actionId: $definition->id,
    actionVersion: $definition->version,
    input: [],
    context: new InvocationContext(
        surface: 'consumer-smoke',
        correlationId: 't802-clean-consumer-smoke',
    ),
));

if (!$outcome->completed) {
    throw new RuntimeException('clean-consumer ActionBus smoke did not complete');
}
if (!$outcome->state->hasOutput || $outcome->state->output !== ['status' => 'ok']) {
    throw new RuntimeException('clean-consumer ActionBus smoke returned unexpected output');
}
if ($auditor->calls !== 1) {
    throw new RuntimeException('clean-consumer ActionBus smoke must audit exactly once');
}

fwrite(STDOUT, "SurfaceRelay Laravel clean-consumer smoke: PASS\n");
