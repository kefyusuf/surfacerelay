<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Contracts\ActionExecutor;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionExecutionStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;

final class ActionExecutionStageTest extends TestCase
{
    public function test_execution_types_exist(): void
    {
        self::assertTrue(interface_exists(ActionExecutor::class), 'ActionExecutor must exist.');
        self::assertTrue(class_exists(ActionExecutionStage::class), 'ActionExecutionStage must exist.');
    }

    public function test_stage_delegates_exact_definition_current_input_and_context_once(): void
    {
        $this->assertTypesExist();
        $definition = $this->definition();
        $context = new InvocationContext('test', 'corr-1');
        $calls = [];

        $executor = new class($calls) implements ActionExecutor {
            /** @param array<int, mixed> $calls */
            public function __construct(private array &$calls) {}

            public function execute(ActionDefinition $definition, array $input, InvocationContext $context): mixed
            {
                $this->calls[] = [$definition, $input, $context];
                return ['ok' => true];
            }
        };

        $stage = new ActionExecutionStage($executor);
        $state = new ActionPipelineState($definition, ['name' => 'validated'], $context);
        $decision = $stage->process($state);

        self::assertSame(ActionPipelineStage::Execution, $stage->stage());
        self::assertTrue($decision->continue);
        self::assertCount(1, $calls);
        self::assertSame($definition, $calls[0][0]);
        self::assertSame(['name' => 'validated'], $calls[0][1]);
        self::assertSame($context, $calls[0][2]);
        self::assertTrue($decision->state->hasOutput);
        self::assertSame(['ok' => true], $decision->state->output);
    }

    public function test_null_executor_result_still_marks_output_present(): void
    {
        $this->assertTypesExist();
        $executor = new class implements ActionExecutor {
            public function execute(ActionDefinition $definition, array $input, InvocationContext $context): mixed
            {
                return null;
            }
        };

        $decision = (new ActionExecutionStage($executor))->process(
            new ActionPipelineState($this->definition(), [], new InvocationContext('test', 'corr-null')),
        );

        self::assertTrue($decision->continue);
        self::assertTrue($decision->state->hasOutput);
        self::assertNull($decision->state->output);
    }

    public function test_executor_exception_propagates_unchanged(): void
    {
        $this->assertTypesExist();
        $expected = new \RuntimeException('application failed');
        $executor = new class($expected) implements ActionExecutor {
            public function __construct(private readonly \RuntimeException $exception) {}

            public function execute(ActionDefinition $definition, array $input, InvocationContext $context): mixed
            {
                throw $this->exception;
            }
        };

        try {
            (new ActionExecutionStage($executor))->process(
                new ActionPipelineState($this->definition(), [], new InvocationContext('test', 'corr-error')),
            );
            self::fail('Expected executor exception to propagate.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }
    }

    private function assertTypesExist(): void
    {
        self::assertTrue(interface_exists(ActionExecutor::class), 'ActionExecutor must exist before this test can proceed.');
        self::assertTrue(class_exists(ActionExecutionStage::class), 'ActionExecutionStage must exist before this test can proceed.');
    }

    private function definition(): ActionDefinition
    {
        return new ActionDefinition(
            id: 'prep_list.add_item',
            version: 1,
            title: 'Add preparation item',
            description: 'Adds an item through the shared execution stage.',
            inputSchema: ['type' => 'object'],
            outputSchema: ['type' => 'object'],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::ReversibleWrite,
            risk: ActionRisk::Low,
            idempotency: IdempotencyPolicy::RecommendedKey,
            outputSensitivity: OutputSensitivity::Normal,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [],
        );
    }
}
