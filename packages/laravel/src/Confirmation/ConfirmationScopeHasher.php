<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Confirmation;

use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;

/**
 * Derives one deterministic SHA-256 fingerprint for the exact confirmation
 * intent and trusted runtime scope. Diagnostics/retry metadata and
 * HumanConfirmation itself are deliberately excluded.
 */
final class ConfirmationScopeHasher
{
    private const string DOMAIN = "surfacerelay.confirmation.scope.v1\n";

    /** @var list<ContextRequirement> */
    private const array RELEVANT_CONTEXT = [
        ContextRequirement::AuthenticatedActor,
        ContextRequirement::Tenant,
        ContextRequirement::CurrentRecord,
        ContextRequirement::CurrentSelection,
        ContextRequirement::BrowserSession,
    ];

    public function fingerprint(ActionPipelineState $state): string
    {
        $context = [];
        foreach (self::RELEVANT_CONTEXT as $requirement) {
            $entry = $state->context->get($requirement);
            if ($entry === null) {
                continue;
            }

            $context[$requirement->value] = $this->contextValue(
                $entry,
                'context.' . $requirement->value,
            );
        }

        $scope = $this->canonicalize([
            'action' => [
                'id' => $state->definition->id,
                'version' => $state->definition->version,
            ],
            'surface' => $state->context->surface,
            'bindingId' => $state->bindingId,
            'input' => $state->input,
            'context' => $context,
        ], 'scope');

        try {
            $json = json_encode(
                $scope,
                JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_PRESERVE_ZERO_FRACTION,
            );
        } catch (\JsonException) {
            throw UnrepresentableConfirmationScope::at('scope');
        }

        return hash('sha256', self::DOMAIN . $json);
    }

    /** @return array{scopeKey: string}|array{value: mixed} */
    private function contextValue(TrustedContextEntry $entry, string $path): array
    {
        if ($entry->confirmationScopeKey !== null) {
            return ['scopeKey' => $entry->confirmationScopeKey];
        }

        return ['value' => $this->canonicalize($entry->value, $path)];
    }

    private function canonicalize(mixed $value, string $path): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_string($value)) {
            return $value;
        }

        if (is_float($value)) {
            if (!is_finite($value)) {
                throw UnrepresentableConfirmationScope::at($path);
            }
            return $value;
        }

        if (!is_array($value)) {
            throw UnrepresentableConfirmationScope::at($path);
        }

        if (array_is_list($value)) {
            $canonical = [];
            foreach ($value as $index => $entry) {
                $canonical[] = $this->canonicalize($entry, $path . '[' . $index . ']');
            }
            return $canonical;
        }

        foreach (array_keys($value) as $key) {
            if (!is_string($key)) {
                throw UnrepresentableConfirmationScope::at($path);
            }
        }

        ksort($value, SORT_STRING);
        $canonical = [];
        foreach ($value as $key => $entry) {
            $canonical[$key] = $this->canonicalize($entry, $path . '.' . $key);
        }

        return $canonical;
    }
}
