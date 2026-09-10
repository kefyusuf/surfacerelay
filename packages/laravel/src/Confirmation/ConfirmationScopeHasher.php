<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Confirmation;

use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;
use SurfaceRelay\Laravel\Runtime\Scope\RuntimeScopeCanonicalizer;
use SurfaceRelay\Laravel\Runtime\Scope\UnrepresentableRuntimeScope;

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

    private RuntimeScopeCanonicalizer $canonicalizer;

    public function __construct(?RuntimeScopeCanonicalizer $canonicalizer = null)
    {
        $this->canonicalizer = $canonicalizer ?? new RuntimeScopeCanonicalizer();
    }

    public function fingerprint(ActionPipelineState $state): string
    {
        try {
            $context = [];
            foreach (self::RELEVANT_CONTEXT as $requirement) {
                $entry = $state->context->get($requirement);
                if ($entry === null) {
                    continue;
                }

                $context[$requirement->value] = $this->canonicalizer->trustedIdentity(
                    $entry,
                    'context.' . $requirement->value,
                );
            }

            $scope = [
                'action' => [
                    'id' => $state->definition->id,
                    'version' => $state->definition->version,
                ],
                'surface' => $state->context->surface,
                'bindingId' => $state->bindingId,
                'input' => $state->input,
                'context' => $context,
            ];

            $extensions = [];
            foreach ($state->context->allTrustedExtensions() as $entry) {
                $extensions[$entry->key] = $this->canonicalizer->trustedExtensionIdentity(
                    $entry,
                    'extensions.' . $entry->key,
                );
            }
            if ($extensions !== []) {
                $scope['extensions'] = $extensions;
            }

            return hash(
                'sha256',
                self::DOMAIN . $this->canonicalizer->encode($scope, 'scope'),
            );
        } catch (UnrepresentableRuntimeScope $e) {
            throw UnrepresentableConfirmationScope::at($e->path);
        }
    }
}
