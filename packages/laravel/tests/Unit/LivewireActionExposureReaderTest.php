<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Livewire\Attributes\ExposeAction;
use SurfaceRelay\Laravel\Livewire\Exposure\InvalidLivewireActionExposure;
use SurfaceRelay\Laravel\Livewire\Exposure\LivewireActionExposure;
use SurfaceRelay\Laravel\Livewire\Exposure\LivewireActionExposureReader;
use SurfaceRelay\Laravel\Registry\InMemoryActionRegistry;

final class LivewireActionExposureReaderTest extends TestCase
{
    public function test_exposure_vocabulary_types_are_readonly(): void
    {
        self::assertTrue(class_exists(ExposeAction::class), 'ExposeAction must exist before this test can proceed.');
        self::assertTrue(class_exists(LivewireActionExposure::class), 'LivewireActionExposure must exist before this test can proceed.');

        self::assertTrue((new ReflectionClass(ExposeAction::class))->isReadOnly());
        self::assertTrue((new ReflectionClass(LivewireActionExposure::class))->isReadOnly());
    }

    public function test_resolved_exposure_reuses_exact_definition_object_and_method_name(): void
    {
        $this->assertTypesExist();
        $definition = $this->definition('demo.exposed', 1);

        $exposure = new LivewireActionExposure($definition, 'perform');

        self::assertSame($definition, $exposure->definition);
        self::assertSame('perform', $exposure->method);
    }

    public function test_annotated_public_method_resolves_exact_registered_definition_and_unannotated_public_method_is_ignored(): void
    {
        $definition = $this->definition('demo.exposed', 1);
        $reader = $this->reader($definition);

        $exposures = $reader->forComponent(new BasicExposureComponent());

        self::assertCount(1, $exposures);
        self::assertSame($definition, $exposures[0]->definition);
        self::assertSame('perform', $exposures[0]->method);
    }

    public function test_component_without_annotations_has_empty_exposure_list(): void
    {
        $reader = $this->reader();

        self::assertSame([], $reader->forComponent(new EmptyExposureComponent()));
    }

    public function test_missing_exact_version_fails_loudly_without_falling_back_to_registered_version(): void
    {
        $reader = $this->reader($this->definition('demo.versioned', 1));

        $this->expectException(InvalidLivewireActionExposure::class);
        $this->expectExceptionMessage('demo.versioned');
        $this->expectExceptionMessage('version 2');
        $reader->forComponent(new MissingVersionExposureComponent());
    }

    public function test_two_versions_of_same_action_remain_distinct(): void
    {
        $v1 = $this->definition('demo.versioned', 1);
        $v2 = $this->definition('demo.versioned', 2);
        $reader = $this->reader($v1, $v2);

        $exposures = $reader->forComponent(new DistinctVersionExposureComponent());

        self::assertCount(2, $exposures);
        self::assertSame($v1, $exposures[0]->definition);
        self::assertSame('performV1', $exposures[0]->method);
        self::assertSame($v2, $exposures[1]->definition);
        self::assertSame('performV2', $exposures[1]->method);
    }

    public function test_duplicate_exact_action_identity_on_two_methods_fails_loudly(): void
    {
        $reader = $this->reader($this->definition('demo.duplicate', 1));

        $this->expectException(InvalidLivewireActionExposure::class);
        $this->expectExceptionMessage('demo.duplicate');
        $reader->forComponent(new DuplicateIdentityExposureComponent());
    }

    public function test_duplicate_expose_action_attributes_on_one_method_fail_through_focused_exception(): void
    {
        $reader = $this->reader($this->definition('demo.duplicate_attribute', 1));

        $this->expectException(InvalidLivewireActionExposure::class);
        $this->expectExceptionMessage('perform');
        $reader->forComponent(new DuplicateAttributeExposureComponent());
    }

    public function test_annotated_protected_method_is_configuration_error(): void
    {
        $reader = $this->reader($this->definition('demo.protected', 1));

        $this->expectException(InvalidLivewireActionExposure::class);
        $this->expectExceptionMessage('protectedAction');
        $reader->forComponent(new ProtectedExposureComponent());
    }

    public function test_annotated_private_method_is_configuration_error(): void
    {
        $reader = $this->reader($this->definition('demo.private', 1));

        $this->expectException(InvalidLivewireActionExposure::class);
        $this->expectExceptionMessage('privateAction');
        $reader->forComponent(new PrivateExposureComponent());
    }

    public function test_annotated_static_method_is_configuration_error(): void
    {
        $reader = $this->reader($this->definition('demo.static', 1));

        $this->expectException(InvalidLivewireActionExposure::class);
        $this->expectExceptionMessage('staticAction');
        $reader->forComponent(new StaticExposureComponent());
    }

    public function test_parent_only_annotation_does_not_auto_expose_on_child_component(): void
    {
        $reader = $this->reader($this->definition('demo.inherited', 1));

        self::assertSame([], $reader->forComponent(new ChildWithoutExplicitExposure()));
    }

    public function test_child_override_with_explicit_annotation_can_expose_intentionally(): void
    {
        $definition = $this->definition('demo.inherited', 1);
        $reader = $this->reader($definition);

        $exposures = $reader->forComponent(new ChildWithExplicitExposure());

        self::assertCount(1, $exposures);
        self::assertSame($definition, $exposures[0]->definition);
        self::assertSame('inheritedAction', $exposures[0]->method);
    }

    public function test_exposures_are_sorted_deterministically_by_id_version_and_method(): void
    {
        $alpha1 = $this->definition('alpha.action', 1);
        $alpha2 = $this->definition('alpha.action', 2);
        $zeta1 = $this->definition('zeta.action', 1);
        $reader = $this->reader($zeta1, $alpha2, $alpha1);

        $exposures = $reader->forComponent(new UnorderedExposureComponent());

        self::assertSame(
            [
                ['alpha.action', 1, 'alphaV1'],
                ['alpha.action', 2, 'alphaV2'],
                ['zeta.action', 1, 'zetaV1'],
            ],
            array_map(
                static fn (LivewireActionExposure $exposure): array => [
                    $exposure->definition->id,
                    $exposure->definition->version,
                    $exposure->method,
                ],
                $exposures,
            ),
        );
    }

    public function test_reader_never_invokes_component_methods(): void
    {
        $component = new NeverInvokeExposureComponent();
        $reader = $this->reader($this->definition('demo.never_invoke', 1));

        $exposures = $reader->forComponent($component);

        self::assertCount(1, $exposures);
        self::assertSame(0, $component->calls);
    }

    private function assertTypesExist(): void
    {
        self::assertTrue(class_exists(ExposeAction::class), 'ExposeAction must exist before this test can proceed.');
        self::assertTrue(class_exists(LivewireActionExposure::class), 'LivewireActionExposure must exist before this test can proceed.');
        self::assertTrue(class_exists(InvalidLivewireActionExposure::class), 'InvalidLivewireActionExposure must exist before this test can proceed.');
        self::assertTrue(class_exists(LivewireActionExposureReader::class), 'LivewireActionExposureReader must exist before this test can proceed.');
    }

    private function reader(ActionDefinition ...$definitions): LivewireActionExposureReader
    {
        $this->assertTypesExist();
        $registry = new InMemoryActionRegistry();
        foreach ($definitions as $definition) {
            $registry->register($definition);
        }

        return new LivewireActionExposureReader($registry);
    }

    private function definition(string $id, int $version): ActionDefinition
    {
        return new ActionDefinition(
            id: $id,
            version: $version,
            title: 'Test action',
            description: 'Test action used by explicit Livewire exposure tests.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::Portable,
            effect: ActionEffect::Read,
            risk: ActionRisk::Low,
            idempotency: IdempotencyPolicy::None,
            outputSensitivity: OutputSensitivity::Normal,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [],
        );
    }
}

final class BasicExposureComponent
{
    #[ExposeAction(id: 'demo.exposed', version: 1)]
    public function perform(): void {}

    public function helper(): void {}
}

final class EmptyExposureComponent
{
    public function helper(): void {}
}

final class MissingVersionExposureComponent
{
    #[ExposeAction(id: 'demo.versioned', version: 2)]
    public function perform(): void {}
}

final class DistinctVersionExposureComponent
{
    #[ExposeAction(id: 'demo.versioned', version: 2)]
    public function performV2(): void {}

    #[ExposeAction(id: 'demo.versioned', version: 1)]
    public function performV1(): void {}
}

final class DuplicateIdentityExposureComponent
{
    #[ExposeAction(id: 'demo.duplicate', version: 1)]
    public function first(): void {}

    #[ExposeAction(id: 'demo.duplicate', version: 1)]
    public function second(): void {}
}

final class DuplicateAttributeExposureComponent
{
    #[ExposeAction(id: 'demo.duplicate_attribute', version: 1)]
    #[ExposeAction(id: 'demo.duplicate_attribute', version: 1)]
    public function perform(): void {}
}

final class ProtectedExposureComponent
{
    #[ExposeAction(id: 'demo.protected', version: 1)]
    protected function protectedAction(): void {}
}

final class PrivateExposureComponent
{
    #[ExposeAction(id: 'demo.private', version: 1)]
    private function privateAction(): void {}
}

final class StaticExposureComponent
{
    #[ExposeAction(id: 'demo.static', version: 1)]
    public static function staticAction(): void {}
}

class ParentExposureComponent
{
    #[ExposeAction(id: 'demo.inherited', version: 1)]
    public function inheritedAction(): void {}
}

final class ChildWithoutExplicitExposure extends ParentExposureComponent {}

final class ChildWithExplicitExposure extends ParentExposureComponent
{
    #[ExposeAction(id: 'demo.inherited', version: 1)]
    public function inheritedAction(): void {}
}

final class UnorderedExposureComponent
{
    #[ExposeAction(id: 'zeta.action', version: 1)]
    public function zetaV1(): void {}

    #[ExposeAction(id: 'alpha.action', version: 2)]
    public function alphaV2(): void {}

    #[ExposeAction(id: 'alpha.action', version: 1)]
    public function alphaV1(): void {}
}

final class NeverInvokeExposureComponent
{
    public int $calls = 0;

    #[ExposeAction(id: 'demo.never_invoke', version: 1)]
    public function dangerous(): void
    {
        ++$this->calls;
        throw new \RuntimeException('Reader must never invoke component methods.');
    }
}
