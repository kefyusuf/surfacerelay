<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Definition\InvalidActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;

final class ActionDefinitionTest extends TestCase
{
    // ------------------------------------------------------------------
    // Successful construction (three materially different v0.1 shapes)
    // ------------------------------------------------------------------

    public function test_constructs_portable_read_definition(): void
    {
        $definition = new ActionDefinition(
            id: 'kb.articles.search',
            version: 1,
            title: 'Search knowledge base articles',
            description: 'Searches published knowledge base articles by query.',
            inputSchema: [
                'type' => 'object',
                'properties' => ['query' => ['type' => 'string', 'minLength' => 1]],
                'required' => ['query'],
            ],
            scope: ActionScope::Portable,
            effect: ActionEffect::Read,
            risk: ActionRisk::Low,
            idempotency: IdempotencyPolicy::None,
            outputSensitivity: OutputSensitivity::Normal,
            outputContentTrust: OutputContentTrust::ContainsUntrustedContent,
            contextRequirements: [ContextRequirement::AuthenticatedActor],
            outputSchema: ['type' => 'object'],
        );

        self::assertSame('kb.articles.search', $definition->id);
        self::assertSame(1, $definition->version);
        self::assertSame(ActionScope::Portable, $definition->scope);
        self::assertSame(ActionEffect::Read, $definition->effect);
        self::assertSame(ActionRisk::Low, $definition->risk);
        self::assertSame(IdempotencyPolicy::None, $definition->idempotency);
        self::assertSame(OutputSensitivity::Normal, $definition->outputSensitivity);
        self::assertSame(OutputContentTrust::ContainsUntrustedContent, $definition->outputContentTrust);
        self::assertSame([ContextRequirement::AuthenticatedActor], $definition->contextRequirements);
        self::assertSame(['type' => 'object'], $definition->outputSchema);
        self::assertSame([], $definition->extensions);
    }

    public function test_constructs_page_scoped_trusted_write_definition(): void
    {
        $definition = new ActionDefinition(
            id: 'inventory.stock.adjust',
            version: 1,
            title: 'Adjust stock level',
            description: 'Adjusts the stock level of the runtime-resolved order line.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::ReversibleWrite,
            risk: ActionRisk::Moderate,
            idempotency: IdempotencyPolicy::RecommendedKey,
            outputSensitivity: OutputSensitivity::Normal,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [
                ContextRequirement::Tenant,
                ContextRequirement::AuthenticatedActor,
                ContextRequirement::CurrentRecord,
            ],
        );

        self::assertSame(ActionScope::PageScoped, $definition->scope);
        self::assertSame(ActionEffect::ReversibleWrite, $definition->effect);
        self::assertSame(
            [
                ContextRequirement::AuthenticatedActor,
                ContextRequirement::Tenant,
                ContextRequirement::CurrentRecord,
            ],
            $definition->contextRequirements,
            'Context requirements are canonicalized to enum-declaration order; caller order is irrelevant.',
        );
        self::assertNull($definition->outputSchema);
    }

    public function test_constructs_consequential_external_side_effect_definition(): void
    {
        $definition = new ActionDefinition(
            id: 'payroll.batches.payout',
            version: 1,
            title: 'Trigger payroll batch payout',
            description: 'Submits an approved payroll batch to the external payment provider.',
            inputSchema: ['type' => 'object', 'required' => ['batchId']],
            scope: ActionScope::Headless,
            effect: ActionEffect::ExternalSideEffect,
            risk: ActionRisk::Consequential,
            idempotency: IdempotencyPolicy::RequiredKey,
            outputSensitivity: OutputSensitivity::Sensitive,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [ContextRequirement::HumanConfirmation],
        );

        self::assertSame(ActionRisk::Consequential, $definition->risk);
        self::assertSame(ActionEffect::ExternalSideEffect, $definition->effect);
        self::assertSame(IdempotencyPolicy::RequiredKey, $definition->idempotency);
        self::assertSame([ContextRequirement::HumanConfirmation], $definition->contextRequirements);
    }

    public function test_declared_context_requirements_are_not_derived_from_risk(): void
    {
        $definition = new ActionDefinition(
            id: 'payroll.batches.payout',
            version: 1,
            title: 'Trigger payroll batch payout',
            description: 'Consequential risk does not mutate declared context requirements.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::Headless,
            effect: ActionEffect::ExternalSideEffect,
            risk: ActionRisk::Consequential,
            idempotency: IdempotencyPolicy::RequiredKey,
            outputSensitivity: OutputSensitivity::Sensitive,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [],
        );

        self::assertSame([], $definition->contextRequirements);
    }

    public function test_preserves_supplied_schemas_and_extensions_verbatim(): void
    {
        $inputSchema = [
            'type' => 'object',
            'properties' => ['name' => ['type' => 'string', 'minLength' => 1]],
            'required' => ['name'],
        ];
        $definition = new ActionDefinition(
            id: 'prep_list.add_item',
            version: 1,
            title: 'Add preparation item',
            description: 'Adds an item to the shared preparation list.',
            inputSchema: $inputSchema,
            scope: ActionScope::PageScoped,
            effect: ActionEffect::ReversibleWrite,
            risk: ActionRisk::Low,
            idempotency: IdempotencyPolicy::RecommendedKey,
            outputSensitivity: OutputSensitivity::Normal,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [ContextRequirement::BrowserSession],
            extensions: [
                'io.surfacerelay.example/cache-hint' => ['ttl' => 30],
            ],
        );

        self::assertSame($inputSchema, $definition->inputSchema, 'Schemas are preserved verbatim; no compilation or additionalProperties injection.');
        self::assertSame(
            ['io.surfacerelay.example/cache-hint' => ['ttl' => 30]],
            $definition->extensions,
        );
    }

    // ------------------------------------------------------------------
    // ID grammar
    // ------------------------------------------------------------------

    public static function invalidIdProvider(): \Generator
    {
        yield 'no namespace separator' => ['orders'];
        yield 'uppercase segment' => ['Orders.Refund'];
        yield 'empty segment' => ['orders..refund'];
        yield 'hyphen instead of separator' => ['orders-refund'];
        yield 'leading digit' => ['1orders.refund'];
        yield 'trailing dot' => ['orders.refund.'];
        yield 'underscores only in separator position' => ['orders_refund'];
    }

    #[DataProvider('invalidIdProvider')]
    public function test_rejects_ids_violating_canonical_grammar(string $id): void
    {
        $this->expectException(InvalidActionDefinition::class);
        new ActionDefinition(
            id: $id,
            version: 1,
            title: 'Title',
            description: 'Description',
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

    public function test_rejects_id_over_160_bytes(): void
    {
        $segments = str_repeat('a124.', 32) . 'a'; // 161 chars matching the grammar
        self::assertSame(161, strlen($segments));

        $this->expectException(InvalidActionDefinition::class);
        $this->expectExceptionMessage('maximum length of 160');
        new ActionDefinition(
            id: $segments,
            version: 1,
            title: 'Title',
            description: 'Description',
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

    public static function invalidVersionProvider(): \Generator
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }

    #[DataProvider('invalidVersionProvider')]
    public function test_rejects_version_below_one(int $version): void
    {
        $this->expectException(InvalidActionDefinition::class);
        $this->expectExceptionMessage('version must be >= 1');
        new ActionDefinition(
            id: 'orders.refund.prepare',
            version: $version,
            title: 'Title',
            description: 'Description',
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

    // ------------------------------------------------------------------
    // Text constraints (no trimming, no hidden normalization)
    // ------------------------------------------------------------------

    public function test_rejects_empty_title(): void
    {
        $this->expectException(InvalidActionDefinition::class);
        $this->expectExceptionMessage('title');
        new ActionDefinition(
            id: 'orders.refund.prepare',
            version: 1,
            title: '',
            description: 'Description',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::ReversibleWrite,
            risk: ActionRisk::Moderate,
            idempotency: IdempotencyPolicy::RequiredKey,
            outputSensitivity: OutputSensitivity::Sensitive,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [],
        );
    }

    public function test_rejects_title_over_120_characters(): void
    {
        $this->expectException(InvalidActionDefinition::class);
        $this->expectExceptionMessage('title');
        new ActionDefinition(
            id: 'orders.refund.prepare',
            version: 1,
            title: str_repeat('a', 121),
            description: 'Description',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::ReversibleWrite,
            risk: ActionRisk::Moderate,
            idempotency: IdempotencyPolicy::RequiredKey,
            outputSensitivity: OutputSensitivity::Sensitive,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [],
        );
    }

    public function test_rejects_empty_description(): void
    {
        $this->expectException(InvalidActionDefinition::class);
        $this->expectExceptionMessage('description');
        new ActionDefinition(
            id: 'orders.refund.prepare',
            version: 1,
            title: 'Title',
            description: '',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::ReversibleWrite,
            risk: ActionRisk::Moderate,
            idempotency: IdempotencyPolicy::RequiredKey,
            outputSensitivity: OutputSensitivity::Sensitive,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [],
        );
    }

    public function test_rejects_description_over_2000_characters(): void
    {
        $this->expectException(InvalidActionDefinition::class);
        $this->expectExceptionMessage('description');
        new ActionDefinition(
            id: 'orders.refund.prepare',
            version: 1,
            title: 'Title',
            description: str_repeat('a', 2001),
            inputSchema: ['type' => 'object'],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::ReversibleWrite,
            risk: ActionRisk::Moderate,
            idempotency: IdempotencyPolicy::RequiredKey,
            outputSensitivity: OutputSensitivity::Sensitive,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [],
        );
    }

    // ------------------------------------------------------------------
    // Context requirements
    // ------------------------------------------------------------------

    public function test_rejects_duplicate_context_requirements(): void
    {
        $this->expectException(InvalidActionDefinition::class);
        $this->expectExceptionMessage('duplicate');
        new ActionDefinition(
            id: 'orders.refund.prepare',
            version: 1,
            title: 'Prepare refund',
            description: 'Creates a reviewable refund draft for the current order.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::ReversibleWrite,
            risk: ActionRisk::Moderate,
            idempotency: IdempotencyPolicy::RequiredKey,
            outputSensitivity: OutputSensitivity::Sensitive,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [
                ContextRequirement::Tenant,
                ContextRequirement::Tenant,
            ],
        );
    }

    public function test_rejects_arbitrary_strings_as_context_requirements(): void
    {
        $this->expectException(InvalidActionDefinition::class);
        $this->expectExceptionMessage('ContextRequirement');
        new ActionDefinition(
            id: 'orders.refund.prepare',
            version: 1,
            title: 'Prepare refund',
            description: 'Creates a reviewable refund draft for the current order.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::ReversibleWrite,
            risk: ActionRisk::Moderate,
            idempotency: IdempotencyPolicy::RequiredKey,
            outputSensitivity: OutputSensitivity::Sensitive,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            /** @phpstan-ignore-next-line intentionally invalid argument under test */
            contextRequirements: ['tenant'],
        );
    }

    // ------------------------------------------------------------------
    // Extensions
    // ------------------------------------------------------------------

    public function test_rejects_extension_key_violating_namespace_grammar(): void
    {
        $this->expectException(InvalidActionDefinition::class);
        $this->expectExceptionMessage('Extension key');
        new ActionDefinition(
            id: 'orders.refund.prepare',
            version: 1,
            title: 'Prepare refund',
            description: 'Creates a reviewable refund draft for the current order.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::ReversibleWrite,
            risk: ActionRisk::Moderate,
            idempotency: IdempotencyPolicy::RequiredKey,
            outputSensitivity: OutputSensitivity::Sensitive,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [],
            extensions: ['cache-hint' => ['ttl' => 30]],
        );
    }

    public function test_accepts_valid_namespaced_extension_key(): void
    {
        $definition = new ActionDefinition(
            id: 'orders.refund.prepare',
            version: 1,
            title: 'Prepare refund',
            description: 'Creates a reviewable refund draft for the current order.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::ReversibleWrite,
            risk: ActionRisk::Moderate,
            idempotency: IdempotencyPolicy::RequiredKey,
            outputSensitivity: OutputSensitivity::Sensitive,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [],
            extensions: ['acme.corp/flag.v1' => true],
        );
        self::assertSame(['acme.corp/flag.v1' => true], $definition->extensions);
    }

    // ------------------------------------------------------------------
    // Immutability
    // ------------------------------------------------------------------

    public function test_definition_is_readonly(): void
    {
        $reflection = new \ReflectionClass(ActionDefinition::class);
        self::assertTrue($reflection->isReadOnly(), 'ActionDefinition must be final readonly.');
        self::assertTrue($reflection->isFinal());

        foreach ($reflection->getProperties() as $property) {
            self::assertTrue(
                $property->isReadOnly(),
                sprintf('Property %s must be readonly.', $property->getName()),
            );
        }
    }
}
