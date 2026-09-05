<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Authorization;

use SurfaceRelay\Laravel\Definition\ActionDefinition;

/**
 * Explicit in-memory authorization rules provider keyed by exact
 * ActionDefinition identity (id + version). array_key_exists presence
 * semantics; no version fallback or inheritance.
 */
final class InMemoryActionAuthorizationRules implements ActionAuthorizationRulesProvider
{
    /** @var array<string, LaravelAuthorizationRule> keyed by "{id}@{version}" */
    private array $rules = [];

    public function register(ActionDefinition $definition, LaravelAuthorizationRule $rule): void
    {
        $key = $this->key($definition);
        if (array_key_exists($key, $this->rules)) {
            throw new DuplicateAuthorizationRule($definition->id, $definition->version);
        }
        $this->rules[$key] = $rule;
    }

    public function ruleFor(ActionDefinition $definition): LaravelAuthorizationRule
    {
        $key = $this->key($definition);
        if (!array_key_exists($key, $this->rules)) {
            throw new AuthorizationRuleNotConfigured($definition->id, $definition->version);
        }
        return $this->rules[$key];
    }

    private function key(ActionDefinition $definition): string
    {
        return $definition->id . '@' . $definition->version;
    }
}
