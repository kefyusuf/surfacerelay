<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Schema;

use SurfaceRelay\Laravel\Attributes\InputDescription;

/**
 * Compiles a PHP callable's scalar/backed-enum parameter signature into a
 * deterministic JSON Schema (Draft 2020-12) object suitable for an Action
 * Definition `inputSchema`.
 *
 * Scope (T-103 + T-104): string/int/float/bool parameters, string- and
 * int-backed enums (case values emitted in declaration order), nullable forms
 * (`?T`, equivalently the explicit union `T|null`), and explicit parameter
 * descriptions via the `InputDescription` attribute (preserved verbatim).
 * Everything else — untyped, variadic, unions that are not one nullable
 * value type, intersections, classes/DTOs, unit (non-backed) enums — fails
 * loudly with UnsupportedParameterType.
 *
 * Requiredness is independent of nullability: a parameter is required when it
 * has no default value, regardless of whether it may be null. Default values
 * influence requiredness only; no `default` keyword is emitted. No
 * descriptions are inferred from docblocks, attributes other than
 * InputDescription, or parameter names; no description key is emitted when
 * the attribute is absent.
 *
 * Nullable enums satisfy both JSON Schema constraints at once: the `type`
 * array allows null AND `null` is appended (last) to the `enum` values.
 *
 * `additionalProperties: false` is intentional compiler output: the PHP
 * signature is the declared set of accepted input properties, and unknown
 * input fields must not be inferred as valid.
 */
final class PhpInputSchemaCompiler
{
    private const SCALAR_TYPE_MAP = [
        'string' => 'string',
        'int' => 'integer',
        'integer' => 'integer',
        'float' => 'number',
        'double' => 'number',
        'bool' => 'boolean',
        'boolean' => 'boolean',
    ];

    /**
     * @return array<string, mixed>
     */
    public function compile(\ReflectionFunctionAbstract $function): array
    {
        $properties = [];
        $required = [];
        foreach ($function->getParameters() as $parameter) {
            [$name, $property, $isRequired] = $this->compileParameter($parameter);
            $properties[$name] = $property;
            if ($isRequired) {
                $required[] = $name;
            }
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
        ];
        if ($required !== []) {
            $schema['required'] = $required;
        }
        $schema['additionalProperties'] = false;

        return $schema;
    }

    /**
     * @return array{0: string, 1: array<string, mixed>, 2: bool}
     */
    private function compileParameter(\ReflectionParameter $parameter): array
    {
        $name = $parameter->getName();
        if ($parameter->isVariadic()) {
            throw UnsupportedParameterType::variadic($name);
        }

        $type = $parameter->getType();
        if ($type === null) {
            throw UnsupportedParameterType::untyped($name);
        }
        if ($type instanceof \ReflectionIntersectionType) {
            throw UnsupportedParameterType::intersection($name);
        }

        $resolved = $this->resolveType($name, $type);

        $property = ['type' => $resolved['nullable'] ? [$resolved['json_type'], 'null'] : $resolved['json_type']];
        if (isset($resolved['enum_values'])) {
            $values = $resolved['enum_values'];
            if ($resolved['nullable']) {
                $values[] = null;
            }
            $property['enum'] = $values;
        }
        $description = $this->resolveDescription($parameter);
        if ($description !== null) {
            $property['description'] = $description;
        }

        return [$name, $property, !$parameter->isDefaultValueAvailable()];
    }

    /**
     * @return array{json_type: string, nullable: bool, enum_values?: list<int|string>}
     */
    private function resolveType(string $name, \ReflectionType $type): array
    {
        if ($type instanceof \ReflectionUnionType) {
            // The explicit nullable-scalar form `T|null` is the same contract
            // as `?T`; both are accepted for exactly one value type + null.
            // Any other union shape is rejected.
            $members = $type->getTypes();
            $nonNull = array_values(array_filter(
                $members,
                static fn (\ReflectionType $member): bool => !$member instanceof \ReflectionNamedType
                    || $member->getName() !== 'null',
            ));
            if (count($members) !== 2 || count($nonNull) !== 1 || !$nonNull[0] instanceof \ReflectionNamedType) {
                throw UnsupportedParameterType::union($name);
            }
            $resolved = $this->resolveNamedType($name, $nonNull[0]);
            $resolved['nullable'] = true;
            return $resolved;
        }

        \assert($type instanceof \ReflectionNamedType);
        return $this->resolveNamedType($name, $type);
    }

    /**
     * @return array{json_type: string, nullable: bool, enum_values?: list<int|string>}
     */
    private function resolveNamedType(string $name, \ReflectionNamedType $type): array
    {
        if (!$type->isBuiltin()) {
            $class = $type->getName();
            if (!enum_exists($class)) {
                throw UnsupportedParameterType::classType($name, $class);
            }
            $resolved = $this->resolveEnum($name, $class);
            $resolved['nullable'] = $type->allowsNull();
            return $resolved;
        }

        $mapped = self::SCALAR_TYPE_MAP[$type->getName()] ?? null;
        if ($mapped === null) {
            throw UnsupportedParameterType::unsupportedBuiltin($name, $type->getName());
        }
        return ['json_type' => $mapped, 'nullable' => $type->allowsNull()];
    }

    /**
     * @return array{json_type: string, nullable: bool, enum_values: list<int|string>}
     */
    private function resolveEnum(string $name, string $class): array
    {
        $enum = new \ReflectionEnum($class);
        if (!$enum->isBacked()) {
            throw UnsupportedParameterType::unitEnum($name, $class);
        }
        $backingType = self::SCALAR_TYPE_MAP[$enum->getBackingType()->getName()] ?? null;
        if ($backingType === null) {
            throw UnsupportedParameterType::unsupportedBuiltin($name, $enum->getBackingType()->getName());
        }
        return [
            'json_type' => $backingType,
            'nullable' => false,
            'enum_values' => array_map(
                static fn (\ReflectionEnumBackedCase $case): int|string => $case->getBackingValue(),
                $enum->getCases(),
            ),
        ];
    }

    private function resolveDescription(\ReflectionParameter $parameter): ?string
    {
        $attributes = $parameter->getAttributes(InputDescription::class);
        if ($attributes === []) {
            return null;
        }
        return $attributes[0]->newInstance()->description;
    }
}
