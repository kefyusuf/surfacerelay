<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Idempotency;

use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;
use SurfaceRelay\Laravel\Runtime\Scope\RuntimeScopeCanonicalizer;
use SurfaceRelay\Laravel\Runtime\Scope\UnrepresentableRuntimeScope;

/** Derives the hashed lookup namespace for one caller idempotency key. */
final class IdempotencyKeyHasher
{
    private const string DOMAIN = "surfacerelay.idempotency.key.v1\n";

    private RuntimeScopeCanonicalizer $canonicalizer;

    public function __construct(?RuntimeScopeCanonicalizer $canonicalizer = null)
    {
        $this->canonicalizer = $canonicalizer ?? new RuntimeScopeCanonicalizer();
    }

    public function hash(ActionPipelineState $state, string $rawKey): string
    {
        try {
            $partition = [];
            $tenant = $state->context->get(ContextRequirement::Tenant);
            $actor = $state->context->get(ContextRequirement::AuthenticatedActor);

            if ($tenant !== null) {
                $partition['tenant'] = $this->canonicalizer->trustedIdentity(
                    $tenant,
                    'partition.tenant',
                );
            }
            if ($actor !== null) {
                $partition['actor'] = $this->canonicalizer->trustedIdentity(
                    $actor,
                    'partition.actor',
                );
            }

            if ($tenant === null && $actor === null) {
                $session = $state->context->get(ContextRequirement::BrowserSession);
                if ($session !== null) {
                    $partition['browser_session'] = $this->canonicalizer->trustedIdentity(
                        $session,
                        'partition.browser_session',
                    );
                } else {
                    $partition['global'] = true;
                }
            }

            $document = [
                'action' => [
                    'id' => $state->definition->id,
                    'version' => $state->definition->version,
                ],
                'partition' => $partition,
                'key' => $rawKey,
            ];

            return hash(
                'sha256',
                self::DOMAIN . $this->canonicalizer->encode($document, 'key'),
            );
        } catch (UnrepresentableRuntimeScope $e) {
            throw UnrepresentableIdempotencyScope::at($e->path);
        }
    }
}
