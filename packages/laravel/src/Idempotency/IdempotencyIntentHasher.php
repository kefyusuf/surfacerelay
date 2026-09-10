<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Idempotency;

use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;
use SurfaceRelay\Laravel\Runtime\Scope\RuntimeScopeCanonicalizer;
use SurfaceRelay\Laravel\Runtime\Scope\UnrepresentableRuntimeScope;

/** Derives one exact validated invocation intent fingerprint. */
final class IdempotencyIntentHasher
{
    private const string DOMAIN = "surfacerelay.idempotency.intent.v1\n";

    private RuntimeScopeCanonicalizer $canonicalizer;

    public function __construct(?RuntimeScopeCanonicalizer $canonicalizer = null)
    {
        $this->canonicalizer = $canonicalizer ?? new RuntimeScopeCanonicalizer();
    }

    public function fingerprint(ActionPipelineState $state): string
    {
        try {
            $context = [];
            foreach ([
                ContextRequirement::AuthenticatedActor,
                ContextRequirement::Tenant,
                ContextRequirement::CurrentRecord,
                ContextRequirement::CurrentSelection,
                ContextRequirement::BrowserSession,
            ] as $requirement) {
                $entry = $state->context->get($requirement);
                if ($entry !== null) {
                    $context[$requirement->value] = $this->canonicalizer->trustedIdentity(
                        $entry,
                        'context.' . $requirement->value,
                    );
                }
            }

            $document = [
                'action' => [
                    'id' => $state->definition->id,
                    'version' => $state->definition->version,
                ],
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
                $document['extensions'] = $extensions;
            }

            return hash(
                'sha256',
                self::DOMAIN . $this->canonicalizer->encode($document, 'intent'),
            );
        } catch (UnrepresentableRuntimeScope $e) {
            throw UnrepresentableIdempotencyScope::at($e->path);
        }
    }
}
