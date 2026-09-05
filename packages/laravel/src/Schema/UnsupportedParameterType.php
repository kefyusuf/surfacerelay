<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Schema;

/**
 * Raised for input signatures outside the scalar compiler's scope. Names the
 * offending parameter and the reason; unsupported parameters are never
 * silently mapped to an empty or object schema and never skipped.
 */
final class UnsupportedParameterType extends \InvalidArgumentException
{
    private function __construct(
        public readonly string $parameterName,
        public readonly string $reason,
    ) {
        parent::__construct(sprintf('Parameter "%s": %s.', $parameterName, $reason));
    }

    public static function untyped(string $parameterName): self
    {
        return new self($parameterName, 'untyped parameters are not supported; an explicit scalar type is required');
    }

    public static function variadic(string $parameterName): self
    {
        return new self($parameterName, 'variadic parameters are not supported by the scalar input schema compiler');
    }

    public static function union(string $parameterName): self
    {
        return new self(
            $parameterName,
            'non-nullable union types are not supported; only nullable scalar types (T string|null / ?T) are allowed',
        );
    }

    public static function intersection(string $parameterName): self
    {
        return new self($parameterName, 'intersection types are not supported');
    }

    public static function unitEnum(string $parameterName, string $class): self
    {
        return new self($parameterName, sprintf(
            'unit (non-backed) enum %s is not supported; a backed enum with an explicit '
            . 'scalar serialization contract is required',
            $class,
        ));
    }

    public static function classType(string $parameterName, string $class): self
    {
        return new self($parameterName, sprintf('class/DTO parameters (%s) are not supported', $class));
    }

    public static function unsupportedBuiltin(string $parameterName, string $type): self
    {
        return new self($parameterName, sprintf('built-in type "%s" is not a supported scalar', $type));
    }
}
