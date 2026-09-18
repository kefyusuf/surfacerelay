<?php

declare(strict_types=1);

namespace SurfaceRelay\LaravelMcp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Contracts\ActionRegistry;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Registry\ActionDefinitionNotFound;
use SurfaceRelay\Laravel\Registry\InMemoryActionRegistry;
use SurfaceRelay\LaravelMcp\Exposure\InvalidMcpActionExposure;
use SurfaceRelay\LaravelMcp\Exposure\McpActionExposure;
use SurfaceRelay\LaravelMcp\Exposure\McpActionExposureRegistry;

final class McpActionExposureRegistryTest extends TestCase
{
    public function test_exact_registered_portable_action_can_be_exposed(): void
    {
        $actions = new InMemoryActionRegistry();
        $definition = $this->definition('orders.list', 1, ActionScope::Portable);
        $actions->register($definition);

        $exposures = new McpActionExposureRegistry($actions);
        $exposures->expose('orders.list', 1);

        self::assertEquals(
            [new McpActionExposure($definition)],
            $exposures->all(),
        );
    }

    public function test_exact_registered_headless_action_can_be_exposed(): void
    {
        $actions = new InMemoryActionRegistry();
        $definition = $this->definition('orders.export', 2, ActionScope::Headless);
        $actions->register($definition);

        $exposures = new McpActionExposureRegistry($actions);
        $exposures->expose('orders.export', 2);

        self::assertSame($definition, $exposures->all()[0]->definition);
    }

    public function test_unknown_identity_fails_closed_without_version_fallback(): void
    {
        $actions = new InMemoryActionRegistry();
        $actions->register($this->definition('orders.list', 2, ActionScope::Portable));

        $exposures = new McpActionExposureRegistry($actions);

        try {
            $exposures->expose('orders.list', 1);
            self::fail('Expected exact missing Action identity to fail closed.');
        } catch (ActionDefinitionNotFound $exception) {
            self::assertSame('orders.list', $exception->id);
            self::assertSame(1, $exception->version);
        }

        self::assertSame([], $exposures->all());
    }

    public function test_page_scoped_action_is_rejected(): void
    {
        $actions = new InMemoryActionRegistry();
        $actions->register($this->definition('orders.page_action', 1, ActionScope::PageScoped));

        $exposures = new McpActionExposureRegistry($actions);

        $this->expectException(InvalidMcpActionExposure::class);
        $this->expectExceptionMessage('page_scoped');
        $exposures->expose('orders.page_action', 1);
    }

    public function test_browser_local_action_is_rejected(): void
    {
        $actions = new InMemoryActionRegistry();
        $actions->register($this->definition('orders.browser_action', 1, ActionScope::BrowserLocal));

        $exposures = new McpActionExposureRegistry($actions);

        $this->expectException(InvalidMcpActionExposure::class);
        $this->expectExceptionMessage('browser_local');
        $exposures->expose('orders.browser_action', 1);
    }

    public function test_duplicate_exact_exposure_is_rejected(): void
    {
        $actions = new InMemoryActionRegistry();
        $actions->register($this->definition('orders.list', 1, ActionScope::Portable));

        $exposures = new McpActionExposureRegistry($actions);
        $exposures->expose('orders.list', 1);

        $this->expectException(InvalidMcpActionExposure::class);
        $this->expectExceptionMessage('already exposed');
        $exposures->expose('orders.list', 1);
    }

    public function test_all_is_deterministically_sorted_by_id_then_version(): void
    {
        $actions = new InMemoryActionRegistry();
        foreach ([
            $this->definition('zeta.action', 2, ActionScope::Headless),
            $this->definition('alpha.action', 2, ActionScope::Portable),
            $this->definition('zeta.action', 1, ActionScope::Portable),
            $this->definition('alpha.action', 1, ActionScope::Headless),
        ] as $definition) {
            $actions->register($definition);
        }

        $exposures = new McpActionExposureRegistry($actions);
        $exposures->expose('zeta.action', 2);
        $exposures->expose('alpha.action', 2);
        $exposures->expose('zeta.action', 1);
        $exposures->expose('alpha.action', 1);

        self::assertSame(
            [
                ['alpha.action', 1],
                ['alpha.action', 2],
                ['zeta.action', 1],
                ['zeta.action', 2],
            ],
            array_map(
                static fn (McpActionExposure $exposure): array => [
                    $exposure->definition->id,
                    $exposure->definition->version,
                ],
                $exposures->all(),
            ),
        );
    }

    public function test_exposure_never_calls_action_registry_all(): void
    {
        $definition = $this->definition('orders.list', 1, ActionScope::Portable);
        $actions = new NoEnumerationActionRegistry([$definition]);

        $exposures = new McpActionExposureRegistry($actions);
        $exposures->expose('orders.list', 1);

        self::assertSame($definition, $exposures->all()[0]->definition);
        self::assertFalse($actions->allCalled);
    }

    private function definition(string $id, int $version, ActionScope $scope): ActionDefinition
    {
        return new ActionDefinition(
            id: $id,
            version: $version,
            title: 'MCP exposure action',
            description: 'Exercises explicit MCP action exposure.',
            inputSchema: [
                'type' => 'object',
                'properties' => (object) [],
                'additionalProperties' => false,
            ],
            scope: $scope,
            effect: ActionEffect::Read,
            risk: ActionRisk::Low,
            idempotency: IdempotencyPolicy::None,
            outputSensitivity: OutputSensitivity::Normal,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [],
        );
    }
}

/**
 * Test registry that proves exposure uses exact get() rather than registry-wide enumeration.
 */
final class NoEnumerationActionRegistry implements ActionRegistry
{
    /** @var array<string, ActionDefinition> */
    private array $definitions = [];

    public bool $allCalled = false;

    /** @param list<ActionDefinition> $definitions */
    public function __construct(array $definitions)
    {
        foreach ($definitions as $definition) {
            $this->register($definition);
        }
    }

    public function register(ActionDefinition $definition): void
    {
        $this->definitions[$definition->id.'@'.$definition->version] = $definition;
    }

    public function has(string $id, int $version): bool
    {
        return isset($this->definitions[$id.'@'.$version]);
    }

    public function get(string $id, int $version): ActionDefinition
    {
        return $this->definitions[$id.'@'.$version]
            ?? throw new ActionDefinitionNotFound($id, $version);
    }

    public function all(): array
    {
        $this->allCalled = true;
        throw new \RuntimeException('ActionRegistry::all() must not be MCP exposure authority.');
    }
}
