<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Confirmation\ConfirmationScopeHasher;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;

final class ConfirmationScopeFingerprintRegressionTest extends TestCase
{
    public function test_t401_standard_scope_fingerprint_is_stable_before_shared_canonicalizer_refactor(): void
    {
        $state = new ActionPipelineState(
            definition: new ActionDefinition(
                id: 'orders.refund.commit',
                version: 1,
                title: 'Commit refund',
                description: 'Commits an approved refund.',
                inputSchema: ['type' => 'object'],
                scope: ActionScope::PageScoped,
                effect: ActionEffect::ExternalSideEffect,
                risk: ActionRisk::Consequential,
                idempotency: IdempotencyPolicy::RequiredKey,
                outputSensitivity: OutputSensitivity::Sensitive,
                outputContentTrust: OutputContentTrust::TrustedApplicationData,
                contextRequirements: [
                    ContextRequirement::AuthenticatedActor,
                    ContextRequirement::Tenant,
                    ContextRequirement::CurrentRecord,
                    ContextRequirement::CurrentSelection,
                    ContextRequirement::BrowserSession,
                    ContextRequirement::HumanConfirmation,
                ],
            ),
            input: ['amount' => 100],
            context: new InvocationContext(
                surface: 'webmcp',
                correlationId: 'corr-1',
                trustedContext: [
                    $this->entry(ContextRequirement::AuthenticatedActor, 'actor-1'),
                    $this->entry(ContextRequirement::Tenant, 'tenant-1'),
                    $this->entry(ContextRequirement::CurrentRecord, 'record-1'),
                    $this->entry(ContextRequirement::CurrentSelection, ['order-1']),
                    $this->entry(ContextRequirement::BrowserSession, 'session-1'),
                ],
                idempotencyKey: 'idem-1',
                metadata: ['locale' => 'tr-TR'],
            ),
            bindingId: 'binding-1',
        );

        self::assertSame(
            '0057701f23d96b62d1a763c8dad01ae9a0412bf1783e72201d82949df0826e5a',
            (new ConfirmationScopeHasher())->fingerprint($state),
        );
    }

    private function entry(ContextRequirement $requirement, mixed $value): TrustedContextEntry
    {
        return new TrustedContextEntry(
            $requirement,
            $value,
            new ContextProvenance('test.' . $requirement->value),
        );
    }
}
