<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Validation;

use SurfaceRelay\Laravel\Definition\ActionDefinition;

/**
 * Explicit in-memory reference rules provider keyed by exact ActionDefinition
 * identity (id + version). Plain PHP; rules are stored and returned as
 * configured — never normalized, merged, or schema-derived.
 *
 * Presence is tracked with array_key_exists semantics: an explicitly
 * registered empty rule set `[]` is valid configuration, distinct from a
 * missing configuration (which throws ValidationRulesNotConfigured).
 */
final class InMemoryActionValidationRules implements ActionValidationRulesProvider
{
    /** @var array<string, array<string, mixed>> keyed by "{id}@{version}" */
    private array $rules = [];

    /**
     * @param array<string, mixed> $rules
     */
    public function register(ActionDefinition $definition, array $rules): void
    {
        $key = $this->key($definition);
        if (array_key_exists($key, $this->rules)) {
            throw new DuplicateValidationRules($definition->id, $definition->version);
        }
        $this->rules[$key] = $rules;
    }

    public function rulesFor(ActionDefinition $definition): array
    {
        $key = $this->key($definition);
        if (!array_key_exists($key, $this->rules)) {
            throw new ValidationRulesNotConfigured($definition->id, $definition->version);
        }
        return $this->rules[$key];
    }

    private function key(ActionDefinition $definition): string
    {
        return $definition->id . '@' . $definition->version;
    }
}
