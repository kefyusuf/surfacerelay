<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Attributes\InputDescription;
use SurfaceRelay\Laravel\Schema\PhpInputSchemaCompiler;
use SurfaceRelay\Laravel\Schema\UnsupportedParameterType;

final class PhpInputSchemaCompilerTest extends TestCase
{
    private PhpInputSchemaCompiler $compiler;

    protected function setUp(): void
    {
        $this->compiler = new PhpInputSchemaCompiler();
    }

    // ------------------------------------------------------------------
    // Successful compilations (exact-output assertions)
    // ------------------------------------------------------------------

    public function test_empty_signature_produces_base_schema_without_required(): void
    {
        $schema = $this->compiler->compile(
            new \ReflectionFunction(function (): void {}),
        );

        self::assertSame([
            'type' => 'object',
            'properties' => [],
            'additionalProperties' => false,
        ], $schema);
    }

    public function test_maps_all_scalar_types(): void
    {
        $schema = $this->compiler->compile(new \ReflectionFunction(
            function (string $name, int $count, float $ratio, bool $active): void {},
        ));

        self::assertSame([
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'count' => ['type' => 'integer'],
                'ratio' => ['type' => 'number'],
                'active' => ['type' => 'boolean'],
            ],
            'required' => ['name', 'count', 'ratio', 'active'],
            'additionalProperties' => false,
        ], $schema);
    }

    public function test_compiles_reference_example_with_mixed_required_and_optional(): void
    {
        $schema = $this->compiler->compile(new \ReflectionFunction(
            function (string $query, int $limit = 10, ?bool $exact = null): void {},
        ));

        self::assertSame([
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string'],
                'limit' => ['type' => 'integer'],
                'exact' => ['type' => ['boolean', 'null']],
            ],
            'required' => ['query'],
            'additionalProperties' => false,
        ], $schema);
    }

    public function test_required_nullable_parameter(): void
    {
        $schema = $this->compiler->compile(new \ReflectionFunction(
            function (?string $value): void {},
        ));

        self::assertSame([
            'type' => 'object',
            'properties' => [
                'value' => ['type' => ['string', 'null']],
            ],
            'required' => ['value'],
            'additionalProperties' => false,
        ], $schema);
    }

    public function test_optional_nullable_parameter_is_not_required(): void
    {
        $schema = $this->compiler->compile(new \ReflectionFunction(
            function (?string $value = null): void {},
        ));

        self::assertSame([
            'type' => 'object',
            'properties' => [
                'value' => ['type' => ['string', 'null']],
            ],
            'additionalProperties' => false,
        ], $schema, 'No `required` key when nothing is required; nullability is independent of requiredness.');
    }

    public function test_optional_non_nullable_parameter_emits_no_default_keyword(): void
    {
        $schema = $this->compiler->compile(new \ReflectionFunction(
            function (int $limit = 10): void {},
        ));

        self::assertSame([
            'type' => 'object',
            'properties' => [
                'limit' => ['type' => 'integer'],
            ],
            'additionalProperties' => false,
        ], $schema, 'No `default` keyword is emitted; defaults affect requiredness only.');
    }

    public function test_explicit_nullable_scalar_union_equals_shorthand(): void
    {
        $schema = $this->compiler->compile(new \ReflectionFunction(
            function (string|null $value): void {},
        ));

        self::assertSame([
            'type' => 'object',
            'properties' => [
                'value' => ['type' => ['string', 'null']],
            ],
            'required' => ['value'],
            'additionalProperties' => false,
        ], $schema, '`string|null` and `?string` are the same nullable-scalar contract.');
    }

    public function test_accepts_reflection_method_and_preserves_declaration_order(): void
    {
        $schema = $this->compiler->compile(
            new \ReflectionMethod(CompilerFixtureService::class, 'mixedSignature'),
        );

        self::assertSame([
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string'],
                'label' => ['type' => 'string'],
                'limit' => ['type' => 'integer'],
                'exact' => ['type' => ['boolean', 'null']],
            ],
            'required' => ['query', 'label'],
            'additionalProperties' => false,
        ], $schema);
    }

    // ------------------------------------------------------------------
    // T-104: backed enums
    // ------------------------------------------------------------------

    public function test_string_backed_enum_compiles_to_type_and_enum(): void
    {
        $schema = $this->compiler->compile(
            new \ReflectionMethod(CompilerFixtureService::class, 'withBackedStringEnum'),
        );

        self::assertSame([
            'type' => 'object',
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'enum' => ['draft', 'published'],
                ],
            ],
            'required' => ['status'],
            'additionalProperties' => false,
        ], $schema);
    }

    public function test_int_backed_enum_compiles_to_type_and_enum(): void
    {
        $schema = $this->compiler->compile(
            new \ReflectionMethod(CompilerFixtureService::class, 'withBackedIntEnum'),
        );

        self::assertSame([
            'type' => 'object',
            'properties' => [
                'priority' => [
                    'type' => 'integer',
                    'enum' => [10, 20, 30],
                ],
            ],
            'required' => ['priority'],
            'additionalProperties' => false,
        ], $schema);
    }

    public function test_required_nullable_backed_string_enum_allows_null_in_both_constraints(): void
    {
        $schema = $this->compiler->compile(
            new \ReflectionMethod(CompilerFixtureService::class, 'withNullableBackedStringEnum'),
        );

        self::assertSame([
            'type' => 'object',
            'properties' => [
                'status' => [
                    'type' => ['string', 'null'],
                    'enum' => ['draft', 'published', null],
                ],
            ],
            'required' => ['status'],
            'additionalProperties' => false,
        ], $schema, 'Null must be allowed by both `type` and `enum`; null is appended last.');
    }

    public function test_required_nullable_int_backed_enum(): void
    {
        $schema = $this->compiler->compile(
            new \ReflectionMethod(CompilerFixtureService::class, 'withNullableIntBackedEnum'),
        );

        self::assertSame([
            'type' => 'object',
            'properties' => [
                'priority' => [
                    'type' => ['integer', 'null'],
                    'enum' => [10, 20, 30, null],
                ],
            ],
            'required' => ['priority'],
            'additionalProperties' => false,
        ], $schema);
    }

    public function test_optional_nullable_backed_enum_is_not_required(): void
    {
        $schema = $this->compiler->compile(
            new \ReflectionMethod(CompilerFixtureService::class, 'withOptionalNullableBackedEnum'),
        );

        self::assertSame([
            'type' => 'object',
            'properties' => [
                'status' => [
                    'type' => ['string', 'null'],
                    'enum' => ['draft', 'published', null],
                ],
            ],
            'additionalProperties' => false,
        ], $schema);
    }

    public function test_optional_backed_enum_with_case_default_has_no_default_keyword(): void
    {
        $schema = $this->compiler->compile(
            new \ReflectionMethod(CompilerFixtureService::class, 'withCaseDefault'),
        );

        self::assertSame([
            'type' => 'object',
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'enum' => ['draft', 'published'],
                ],
            ],
            'additionalProperties' => false,
        ], $schema, 'Case defaults affect requiredness only; no `default` key is emitted.');
    }

    public function test_enum_values_preserve_case_declaration_order(): void
    {
        $schema = $this->compiler->compile(
            new \ReflectionMethod(CompilerFixtureService::class, 'withDeclarationOrderEnum'),
        );

        self::assertSame([30, 10, 20], $schema['properties']['order']['enum'],
            'Backing values are emitted in case declaration order, never sorted.');
    }

    public function test_backed_enum_plus_null_explicit_union_is_supported(): void
    {
        $schema = $this->compiler->compile(
            new \ReflectionMethod(CompilerFixtureService::class, 'withBackedEnumOrNull'),
        );

        self::assertSame(
            ['type' => ['integer', 'null'], 'enum' => [10, 20, 30, null]],
            $schema['properties']['priority'],
        );
    }

    // ------------------------------------------------------------------
    // T-104: explicit descriptions
    // ------------------------------------------------------------------

    public function test_explicit_scalar_description_is_emitted_after_type(): void
    {
        $schema = $this->compiler->compile(
            new \ReflectionMethod(CompilerFixtureService::class, 'withDescription'),
        );

        self::assertSame([
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Text to search for',
                ],
            ],
            'required' => ['query'],
            'additionalProperties' => false,
        ], $schema);
    }

    public function test_enum_description_appears_after_type_and_enum(): void
    {
        $schema = $this->compiler->compile(
            new \ReflectionMethod(CompilerFixtureService::class, 'withEnumDescription'),
        );

        self::assertSame([
            'type' => 'object',
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'enum' => ['draft', 'published'],
                    'description' => 'Desired publication state',
                ],
            ],
            'required' => ['status'],
            'additionalProperties' => false,
        ], $schema);
    }

    public function test_description_is_preserved_verbatim_without_trimming(): void
    {
        $schema = $this->compiler->compile(
            new \ReflectionMethod(CompilerFixtureService::class, 'withVerbatimDescription'),
        );

        self::assertSame(
            '  Keeps  spacing & punctuation — verbatim.  ',
            $schema['properties']['raw']['description'],
        );
    }

    public function test_no_description_key_is_synthesized_without_attribute(): void
    {
        $schema = $this->compiler->compile(new \ReflectionFunction(
            function (string $query): void {},
        ));

        self::assertArrayNotHasKey('description', $schema['properties']['query']);
    }

    // ------------------------------------------------------------------
    // Unsupported signatures (fail loudly, name the parameter)
    // ------------------------------------------------------------------

    public function test_rejects_untyped_parameter(): void
    {
        try {
            $this->compiler->compile(new \ReflectionFunction(
                function ($value): void {},
            ));
            self::fail('Expected UnsupportedParameterType.');
        } catch (UnsupportedParameterType $exception) {
            self::assertSame('value', $exception->parameterName);
            self::assertStringContainsString('untyped', $exception->getMessage());
        }
    }

    public function test_rejects_array_parameter(): void
    {
        try {
            $this->compiler->compile(new \ReflectionFunction(
                function (array $values): void {},
            ));
            self::fail('Expected UnsupportedParameterType.');
        } catch (UnsupportedParameterType $exception) {
            self::assertSame('values', $exception->parameterName);
            self::assertStringContainsString('not a supported scalar', $exception->getMessage());
        }
    }

    public function test_rejects_class_dto_parameter(): void
    {
        try {
            $this->compiler->compile(
                new \ReflectionMethod(CompilerFixtureService::class, 'withDto'),
            );
            self::fail('Expected UnsupportedParameterType.');
        } catch (UnsupportedParameterType $exception) {
            self::assertSame('dto', $exception->parameterName);
            self::assertStringContainsString('CompilerFixtureDto', $exception->getMessage());
        }
    }

    public function test_rejects_unit_enum_parameter(): void
    {
        try {
            $this->compiler->compile(
                new \ReflectionMethod(CompilerFixtureService::class, 'withUnitEnum'),
            );
            self::fail('Expected UnsupportedParameterType.');
        } catch (UnsupportedParameterType $exception) {
            self::assertSame('state', $exception->parameterName);
            self::assertStringContainsString('backed enum', $exception->getMessage());
            self::assertStringContainsString(CompilerFixtureUnitStatus::class, $exception->getMessage());
        }
    }

    public function test_rejects_mixed_union_involving_enum(): void
    {
        try {
            $this->compiler->compile(
                new \ReflectionMethod(CompilerFixtureService::class, 'withEnumUnion'),
            );
            self::fail('Expected UnsupportedParameterType.');
        } catch (UnsupportedParameterType $exception) {
            self::assertSame('value', $exception->parameterName);
            self::assertStringContainsString('union', $exception->getMessage());
        }
    }

    public function test_rejects_non_nullable_union(): void
    {
        try {
            $this->compiler->compile(
                new \ReflectionMethod(CompilerFixtureService::class, 'withUnion'),
            );
            self::fail('Expected UnsupportedParameterType.');
        } catch (UnsupportedParameterType $exception) {
            self::assertSame('value', $exception->parameterName);
            self::assertStringContainsString('union', $exception->getMessage());
        }
    }

    public function test_rejects_intersection_type(): void
    {
        try {
            $this->compiler->compile(
                new \ReflectionMethod(CompilerFixtureService::class, 'withIntersection'),
            );
            self::fail('Expected UnsupportedParameterType.');
        } catch (UnsupportedParameterType $exception) {
            self::assertSame('subject', $exception->parameterName);
            self::assertStringContainsString('intersection', $exception->getMessage());
        }
    }

    public function test_rejects_variadic_parameter(): void
    {
        try {
            $this->compiler->compile(
                new \ReflectionMethod(CompilerFixtureService::class, 'withVariadic'),
            );
            self::fail('Expected UnsupportedParameterType.');
        } catch (UnsupportedParameterType $exception) {
            self::assertSame('values', $exception->parameterName);
            self::assertStringContainsString('variadic', $exception->getMessage());
        }
    }
}

// ----------------------------------------------------------------------
// Fixtures (test-file scope only)
// ----------------------------------------------------------------------

final class CompilerFixtureDto
{
}

enum CompilerFixtureStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}

enum CompilerFixtureUnitStatus
{
    case Draft;
    case Published;
}

enum CompilerFixturePriority: int
{
    case Low = 10;
    case Medium = 20;
    case High = 30;
}

enum CompilerFixtureOrderTest: int
{
    case Third = 30;
    case First = 10;
    case Second = 20;
}

enum CompilerFixtureContractStatus: string
{
    case Open = 'open';
}

interface CompilerFixtureContract
{
}

final class CompilerFixtureImpl implements CompilerFixtureContract
{
}

final class CompilerFixtureService
{
    public function mixedSignature(string $query, string $label, int $limit = 10, ?bool $exact = null): void
    {
    }

    public function withDto(CompilerFixtureDto $dto): void
    {
    }

    public function withBackedStringEnum(CompilerFixtureStatus $status): void
    {
    }

    public function withBackedIntEnum(CompilerFixturePriority $priority): void
    {
    }

    public function withNullableBackedStringEnum(?CompilerFixtureStatus $status): void
    {
    }

    public function withNullableIntBackedEnum(?CompilerFixturePriority $priority): void
    {
    }

    public function withOptionalNullableBackedEnum(?CompilerFixtureStatus $status = null): void
    {
    }

    public function withCaseDefault(CompilerFixtureStatus $status = CompilerFixtureStatus::Draft): void
    {
    }

    public function withDeclarationOrderEnum(CompilerFixtureOrderTest $order): void
    {
    }

    public function withBackedEnumOrNull(CompilerFixturePriority|null $priority): void
    {
    }

    public function withDescription(#[InputDescription('Text to search for')] string $query): void
    {
    }

    public function withEnumDescription(#[InputDescription('Desired publication state')] CompilerFixtureStatus $status): void
    {
    }

    public function withVerbatimDescription(
        #[InputDescription('  Keeps  spacing & punctuation — verbatim.  ')] string $raw,
    ): void
    {
    }

    public function withUnitEnum(CompilerFixtureUnitStatus $state): void
    {
    }

    public function withEnumUnion(CompilerFixtureStatus|string $value): void
    {
    }

    public function withUnion(string|int $value): void
    {
    }

    public function withIntersection(CompilerFixtureContract&CompilerFixtureImpl $subject): void
    {
    }

    public function withVariadic(string ...$values): void
    {
    }
}
