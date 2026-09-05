<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Authorization;

use SurfaceRelay\Laravel\Runtime\InvocationContext;

/**
 * Explicit per-action Laravel Gate configuration. The ability is application
 * configuration — never derived from ActionDefinition.id — and is passed to
 * Laravel Gate unchanged (string or UnitEnum; empty strings rejected, no
 * silent normalization).
 *
 * The optional argument resolver is trusted application code receiving the
 * validated pipeline input and the trusted InvocationContext; it never sees
 * raw pre-validation caller input and gets no hidden request/global access.
 * Without a resolver, Gate arguments are exactly [] — actor, tenant, records,
 * selection, context, and definition are never injected automatically
 * (the actor is already applied through Gate::forUser()).
 */
final readonly class LaravelAuthorizationRule
{
    /** @var (\Closure(array<string, mixed>, InvocationContext): mixed)|null */
    private ?\Closure $argumentsResolver;

    /**
     * @param (\Closure(array<string, mixed>, InvocationContext): mixed)|null $arguments
     */
    public function __construct(
        public readonly \UnitEnum|string $ability,
        ?callable $arguments = null,
    ) {
        if (is_string($this->ability) && $this->ability === '') {
            throw new \InvalidArgumentException(
                'Laravel authorization ability must be a non-empty string or a UnitEnum.'
            );
        }
        $this->argumentsResolver = $arguments === null ? null : \Closure::fromCallable($arguments);
    }

    /**
     * A non-array resolver result is wrapped as a single Gate argument;
     * arrays are passed through unchanged.
     *
     * @param array<string, mixed> $input validated pipeline input
     *
     * @return list<mixed>|array<mixed>
     */
    public function resolveArguments(array $input, InvocationContext $context): array
    {
        if ($this->argumentsResolver === null) {
            return [];
        }
        $arguments = ($this->argumentsResolver)($input, $context);

        return is_array($arguments) ? $arguments : [$arguments];
    }
}
