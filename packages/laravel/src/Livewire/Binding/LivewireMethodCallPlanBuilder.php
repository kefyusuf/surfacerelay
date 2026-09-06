<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Livewire\Binding;

use ReflectionNamedType;
use ReflectionObject;
use ReflectionProperty;
use SurfaceRelay\Laravel\Livewire\Exposure\LivewireActionExposure;

/** Builds a deterministic object-input to positional-call plan for one exposure. */
final class LivewireMethodCallPlanBuilder
{
    public function forExposure(
        object $component,
        LivewireActionExposure $exposure,
    ): LivewireMethodCallPlan {
        $reflection = new ReflectionObject($component);
        $methodName = $exposure->method;

        if (!$reflection->hasMethod($methodName)) {
            throw InvalidLivewireBindingProduction::methodSignature($methodName, 'method does not exist on the concrete component');
        }

        if (LivewireWireReservedNames::contains($methodName)) {
            throw InvalidLivewireBindingProduction::reservedMethod($methodName);
        }

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if (!$property->isStatic() && $property->getName() === $methodName) {
                throw InvalidLivewireBindingProduction::publicPropertyCollision($methodName);
            }
        }

        $method = $reflection->getMethod($methodName);
        if (!$method->isPublic() || $method->isStatic()) {
            throw InvalidLivewireBindingProduction::methodSignature($methodName, 'method must be public and non-static');
        }

        $inputOrder = [];
        $requiredNames = [];
        $optionalSeen = false;

        foreach ($method->getParameters() as $parameter) {
            $name = $parameter->getName();

            if ($parameter->isVariadic()) {
                throw InvalidLivewireBindingProduction::methodSignature($methodName, sprintf('parameter "%s" is variadic', $name));
            }
            if ($parameter->isPassedByReference()) {
                throw InvalidLivewireBindingProduction::methodSignature($methodName, sprintf('parameter "%s" is passed by reference', $name));
            }

            $type = $parameter->getType();
            if ($type !== null) {
                if (!$type instanceof ReflectionNamedType) {
                    throw InvalidLivewireBindingProduction::methodSignature($methodName, sprintf('parameter "%s" uses an unsupported union/intersection type', $name));
                }
                if (!$type->isBuiltin()) {
                    throw InvalidLivewireBindingProduction::methodSignature($methodName, sprintf('parameter "%s" looks like a method-level dependency', $name));
                }
            }

            $required = !$parameter->isOptional() && !$parameter->isDefaultValueAvailable();
            if ($required && $optionalSeen) {
                throw InvalidLivewireBindingProduction::methodSignature($methodName, 'required parameters must form a positional prefix');
            }
            if (!$required) {
                $optionalSeen = true;
            }

            $inputOrder[] = $name;
            if ($required) {
                $requiredNames[] = $name;
            }
        }

        $schema = $exposure->definition->inputSchema;
        foreach (['oneOf', 'anyOf', 'allOf'] as $compositionKeyword) {
            if (array_key_exists($compositionKeyword, $schema)) {
                throw InvalidLivewireBindingProduction::inputSchema($methodName, sprintf('top-level %s is not supported for deterministic positional mapping', $compositionKeyword));
            }
        }

        if (array_key_exists('type', $schema) && $schema['type'] !== 'object') {
            throw InvalidLivewireBindingProduction::inputSchema($methodName, 'top-level type must be object');
        }
        if (!array_key_exists('properties', $schema) || !is_array($schema['properties'])) {
            throw InvalidLivewireBindingProduction::inputSchema($methodName, 'explicit top-level properties are required');
        }

        $propertyNames = array_keys($schema['properties']);
        foreach ($propertyNames as $propertyName) {
            if (!is_string($propertyName) || $propertyName === '') {
                throw InvalidLivewireBindingProduction::inputSchema($methodName, 'property names must be non-empty strings');
            }
        }

        $schemaRequired = $schema['required'] ?? [];
        if (!is_array($schemaRequired) || !array_is_list($schemaRequired)) {
            throw InvalidLivewireBindingProduction::inputSchema($methodName, 'required must be a list of property names');
        }
        if (count($schemaRequired) !== count(array_unique($schemaRequired, SORT_REGULAR))) {
            throw InvalidLivewireBindingProduction::inputSchema($methodName, 'required property names must be unique');
        }
        foreach ($schemaRequired as $requiredName) {
            if (!is_string($requiredName) || $requiredName === '') {
                throw InvalidLivewireBindingProduction::inputSchema($methodName, 'required entries must be non-empty strings');
            }
        }

        $sortedParameters = $inputOrder;
        $sortedProperties = $propertyNames;
        sort($sortedParameters, SORT_STRING);
        sort($sortedProperties, SORT_STRING);
        if ($sortedParameters !== $sortedProperties) {
            throw InvalidLivewireBindingProduction::inputSchema($methodName, 'schema properties must exactly match exposed PHP method parameters');
        }

        $sortedPhpRequired = $requiredNames;
        $sortedSchemaRequired = $schemaRequired;
        sort($sortedPhpRequired, SORT_STRING);
        sort($sortedSchemaRequired, SORT_STRING);
        if ($sortedPhpRequired !== $sortedSchemaRequired) {
            throw InvalidLivewireBindingProduction::inputSchema($methodName, 'schema required properties must exactly match PHP parameters without defaults');
        }

        if ($exposure->definition->outputSchema !== null) {
            $returnType = $method->getReturnType();
            if ($returnType instanceof ReflectionNamedType && in_array($returnType->getName(), ['void', 'never'], true)) {
                throw InvalidLivewireBindingProduction::outputIncompatible($methodName, $returnType->getName());
            }
        }

        return new LivewireMethodCallPlan($inputOrder, count($requiredNames));
    }
}
