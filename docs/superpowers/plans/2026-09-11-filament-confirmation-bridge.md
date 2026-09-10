# Filament Confirmation Bridge Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement T-504 as a Filament-only approval bridge that presents an exact T-401 challenge, approves only that locked challenge, and requires explicit caller retry through the normal ActionBus path before business execution.

**Architecture:** Keep confirmation/idempotency core unchanged. Add a narrow Filament bridge plus explicit page trait, let `FilamentActionGateway` optionally present a typed confirmation halt, and prove that the later retry re-resolves trusted record/selection/filter/actor/tenant state before receipt consumption.

**Tech Stack:** PHP 8.3/8.4, Illuminate 12/13, Livewire 4.4, Filament 5.x, PHPUnit 11, Orchestra Testbench 10/11, existing SQLite/MySQL test infrastructure.

**Spec:** `docs/superpowers/specs/2026-09-10-filament-confirmation-bridge-design.md`

## Global Constraints

- Base: `main@66f1d5db7e7902b6d7f09306be021119a6d96086`.
- Branch: `feat/filament-confirmation-bridge`.
- D-051 is ACCEPTED: approval never executes or redispatches the business action.
- `spec/0.1/**`, `packages/laravel/src/Confirmation/**`, `packages/laravel/src/Idempotency/**`, `packages/laravel/src/Livewire/**`, and `packages/browser-runtime/src/**` are production-read-only for T-504.
- Filament stays dev/optional: `filament/filament:^5.0` remains in `require-dev` only.
- `SurfaceRelayServiceProvider` gains no eager Filament reference and no hidden confirmation-store construction.
- Only exact `ActionPipelineStage::Confirmation` + `confirmation_required` + typed `ConfirmationChallenge` is presentable.
- No challenge may be reconstructed from halt details, metadata, requests, routes, query state, action arguments, or browser payloads.
- Presentation state is server-authored Livewire `#[Locked]` state.
- The internal Filament approval action accepts no caller challenge token as authority.
- Raw challenge/receipt values, token hashes, scope fingerprints, business input, trusted identities, selection/filter values never enter logs, audit, notifications, events, or exceptions.
- `ConfirmationService::class` must be explicitly bound; no fallback confirmation service/store may be created.
- One page may hold one SurfaceRelay confirmation presentation. Different-challenge overwrite fails closed. Same exact challenge may remount only when no unrelated action is mounted.
- T-504 does not claim proof-of-human, approver identity, supervisor/delegated approval, WebAuthn, or modal persistence across navigation/refresh.
- Early retry before approval is normal T-401 retry behavior, not an approval-status polling API.

---

## File Map

**Production**
- Create `packages/laravel/src/Filament/Confirmation/InvalidFilamentConfirmationBridge.php`.
- Create `packages/laravel/src/Filament/Confirmation/FilamentConfirmationBridge.php`.
- Create `packages/laravel/src/Filament/Confirmation/InteractsWithSurfaceRelayConfirmation.php`.
- Modify `packages/laravel/src/Filament/Invocation/FilamentActionGateway.php`.

**Test support / fixtures**
- Create `packages/laravel/tests/Support/FilamentConfirmationMemoryStore.php`.
- Create `packages/laravel/tests/Support/FilamentConfirmationMutableClock.php`.
- Create `packages/laravel/tests/Support/FilamentConfirmationSequenceTokenGenerator.php`.
- Create `packages/laravel/tests/Fixtures/Filament/TestConfirmationPage.php`.
- Create `packages/laravel/tests/Fixtures/Filament/TestConfirmationRecordPage.php`.
- Create `packages/laravel/tests/Fixtures/Filament/TestConfirmationTablePage.php`.
- Create `packages/laravel/tests/Fixtures/views/filament-test-confirmation-page.blade.php`.

**Tests**
- Create `packages/laravel/tests/Unit/FilamentConfirmationBridgeTest.php`.
- Create `packages/laravel/tests/Integration/FilamentConfirmationModalIntegrationTest.php`.
- Create `packages/laravel/tests/Integration/FilamentConfirmationApprovalIntegrationTest.php`.
- Create `packages/laravel/tests/Integration/FilamentConfirmationGatewayIntegrationTest.php`.
- Create `packages/laravel/tests/Integration/FilamentConfirmationTrustControlsIntegrationTest.php`.
- Modify `packages/laravel/tests/Unit/FilamentDependencyPolicyTest.php`.

---

### Task 1: Typed presentation boundary

**Files:** bridge, static-safe exception, trait skeleton, unit test.

**Produces:**
```php
FilamentConfirmationBridge::presentIfRequired(Page $page, ActionPipelineOutcome $outcome): void
InteractsWithSurfaceRelayConfirmation::presentSurfaceRelayConfirmation(ConfirmationChallenge $challenge): void
```

- [ ] **Step 1: Write RED tests** for completed/non-confirmation no-op, malformed confirmation halt failure, exact typed challenge delegation, and explicit trait opt-in.

Use `new InvocationContext('filament', 'corr-bridge')` inside a small test helper that creates `ActionPipelineState`. The malformed case must be:

```php
ActionPipelineOutcome::halted(
    $state,
    ActionPipelineStage::Confirmation,
    new ActionPipelineHalt(CoreActionErrorCode::CONFIRMATION_REQUIRED),
)
```

and must throw `InvalidFilamentConfirmationBridge` with exactly `Filament confirmation outcome is invalid.` and no previous exception.

- [ ] **Step 2: Verify RED**
```bash
cd packages/laravel
composer test -- --filter FilamentConfirmationBridgeTest
```
Expected: missing T-504 production types.

- [ ] **Step 3: Add static-safe exception**
```php
final class InvalidFilamentConfirmationBridge extends \RuntimeException
{
    public static function invalidOutcome(): self { return new self('Filament confirmation outcome is invalid.'); }
    public static function hostUnavailable(): self { return new self('Filament confirmation bridge is unavailable for this page.'); }
    public static function presentationConflict(): self { return new self('Filament confirmation presentation conflicts with active page state.'); }
    public static function presentationFailed(): self { return new self('Filament confirmation presentation failed.'); }
    public static function serviceUnavailable(): self { return new self('Filament confirmation service is unavailable.'); }
}
```

- [ ] **Step 4: Implement bridge classifier**
```php
public function presentIfRequired(Page $page, ActionPipelineOutcome $outcome): void
{
    if ($outcome->completed) {
        return;
    }

    $halt = $outcome->halt;
    $mentionsConfirmation = $outcome->haltedAt === ActionPipelineStage::Confirmation
        || $halt?->code === CoreActionErrorCode::CONFIRMATION_REQUIRED
        || $halt?->confirmation !== null;

    if (!$mentionsConfirmation) {
        return;
    }

    if (
        $outcome->haltedAt !== ActionPipelineStage::Confirmation
        || $halt === null
        || $halt->code !== CoreActionErrorCode::CONFIRMATION_REQUIRED
        || !$halt->confirmation instanceof ConfirmationChallenge
    ) {
        throw InvalidFilamentConfirmationBridge::invalidOutcome();
    }

    if (!in_array(InteractsWithSurfaceRelayConfirmation::class, class_uses_recursive($page), true)) {
        throw InvalidFilamentConfirmationBridge::hostUnavailable();
    }

    $page->presentSurfaceRelayConfirmation($halt->confirmation);
}
```

- [ ] **Step 5: Add trait skeleton with locked fields**
```php
#[Locked] public ?string $surfaceRelayConfirmationChallengeId = null;
#[Locked] public ?string $surfaceRelayConfirmationSummary = null;
#[Locked] public ?string $surfaceRelayConfirmationExpiresAt = null;
```
The initial presentation method copies the exact three fields only.

- [ ] **Step 6: Verify GREEN and lint**
```bash
composer test -- --filter FilamentConfirmationBridgeTest
php -l src/Filament/Confirmation/InvalidFilamentConfirmationBridge.php
php -l src/Filament/Confirmation/FilamentConfirmationBridge.php
php -l src/Filament/Confirmation/InteractsWithSurfaceRelayConfirmation.php
```
Expected: all green.

- [ ] **Step 7: Commit**
```bash
git add packages/laravel/src/Filament/Confirmation packages/laravel/tests/Unit/FilamentConfirmationBridgeTest.php
git commit -m "feat(filament): add typed confirmation presentation boundary"
```

---

### Task 2: Real Filament modal + locked lifecycle

**Files:** trait, `TestConfirmationPage`, Blade fixture, modal integration test.

**Produces reserved action:** `surfacerelay_confirmation`.

- [ ] **Step 1: Create fixture**
```php
final class TestConfirmationPage extends Page
{
    use InteractsWithSurfaceRelayConfirmation;

    protected static string $resource = TestRecordResource::class;
    protected string $view = 'filament-test-confirmation-page';

    public function unrelatedAction(): Action
    {
        return Action::make('unrelated')->requiresConfirmation()->action(static fn (): null => null);
    }

    public function seedConfirmationForTest(string $id, string $summary, ?string $expiresAt): void
    {
        $this->presentSurfaceRelayConfirmation(new ConfirmationChallenge($id, $summary, $expiresAt));
    }
}
```

Blade fixture:
```blade
<div>
    <x-filament-actions::modals />
</div>
```

For Livewire tests set:
```php
$app['config']->set('view.paths', [dirname(__DIR__) . '/Fixtures/views']);
```

- [ ] **Step 2: Write RED tests** proving: exact state + reserved mounted action; same exact challenge is idempotent; same challenge remounts after `unmountAction()`; different challenge conflicts; unrelated mounted action remains mounted; direct mount with no locked challenge does not approve; `#[Locked]` rejects browser mutation of ID, summary, and expiry via Livewire hydration.

The selection of mounted action must use only public `getMountedAction()?->getName()`. Production/tests must not inspect raw `$mountedActions` for authority.

- [ ] **Step 3: Verify RED**
```bash
composer test -- --filter FilamentConfirmationModalIntegrationTest
```
Expected: modal/concurrency behavior absent.

- [ ] **Step 4: Implement Filament Action**
```php
private const string CONFIRMATION_ACTION = 'surfacerelay_confirmation';

public function surfaceRelayConfirmationAction(): Action
{
    return Action::make(self::CONFIRMATION_ACTION)
        ->requiresConfirmation()
        ->modalHeading('Confirm action')
        ->modalDescription(fn (): string => $this->surfaceRelayConfirmationDescription())
        ->modalSubmitActionLabel('Approve')
        ->modalCancelActionLabel('Cancel')
        ->closeModalByClickingAway(false)
        ->closeModalByEscaping(false)
        ->modalCloseButton(false)
        ->disabled(fn (): bool => $this->surfaceRelayConfirmationChallengeId === null)
        ->action(fn (): void => $this->approveSurfaceRelayConfirmation())
        ->modalCancelAction(fn (Action $action): Action => $action
            ->action(fn (): void => $this->clearSurfaceRelayConfirmation())
            ->close());
}
```

Description helper returns summary alone when expiry is null, otherwise `summary . ' Expires at ' . expiresAt`.

- [ ] **Step 5: Implement presentation state machine**

Absent state is valid only when ID, summary, expiry are all null. Present state requires non-null ID+summary; expiry may be null. Any incoherent state throws `presentationFailed()`.

Before writing state, inspect public `getMountedAction()`. If an unrelated action is mounted, throw `presentationConflict()`. For stored same exact challenge: reserved action mounted => no-op; no mounted action => remount; unrelated action => conflict. Any different stored ID/summary/expiry => conflict.

After `mountAction(self::CONFIRMATION_ACTION)`, require `getMountedAction()?->getName() === self::CONFIRMATION_ACTION`; otherwise clear the just-written state and throw `presentationFailed()`.

- [ ] **Step 6: Implement clearing**
```php
public function clearSurfaceRelayConfirmation(): void
{
    $this->surfaceRelayConfirmationChallengeId = null;
    $this->surfaceRelayConfirmationSummary = null;
    $this->surfaceRelayConfirmationExpiresAt = null;
}
```
No core confirmation call occurs here.

- [ ] **Step 7: Verify GREEN and commit**
```bash
composer test -- --filter FilamentConfirmationModalIntegrationTest
git add packages/laravel/src/Filament/Confirmation/InteractsWithSurfaceRelayConfirmation.php packages/laravel/tests/Fixtures/Filament/TestConfirmationPage.php packages/laravel/tests/Fixtures/views/filament-test-confirmation-page.blade.php packages/laravel/tests/Integration/FilamentConfirmationModalIntegrationTest.php
git commit -m "feat(filament): add locked confirmation modal lifecycle"
```

---

### Task 3: Exact approval authority

**Files:** trait, three test-support classes, approval integration test.

- [ ] **Step 1: Add deterministic test support**

`FilamentConfirmationMemoryStore` implements existing `ConfirmationStore` exactly: `createPending()` rejects duplicate hash; `approvePending()` requires pending and `now < challengeExpiresAt`, then writes Approved with supplied receipt expiry; `consumeApproved()` requires Approved, exact scope, non-null receipt expiry, `now < receiptExpiresAt`, then deletes the record. Add test-only `recordForToken(string $token): ?ConfirmationRecord` that hashes the token and returns the current record without mutation.

`FilamentConfirmationMutableClock`:
```php
final class FilamentConfirmationMutableClock implements ConfirmationClock
{
    public function __construct(public int $timestamp = 1_800_000_000) {}
    public function now(): int { return $this->timestamp; }
    public function advance(int $seconds): void { $this->timestamp += $seconds; }
}
```

`FilamentConfirmationSequenceTokenGenerator` stores a list of exact 43-character tokens and `generate()` shifts one, throwing `RuntimeException('No confirmation test token available.')` if exhausted.

- [ ] **Step 2: Write RED approval tests**

Issue challenge A and B from one real `ConfirmationService`; bind that exact service:
```php
$this->app->instance(ConfirmationService::class, $service);
```
Present A, then call the mounted action while supplying browser action arguments containing B. Assert A becomes Approved, B remains Pending, and all three UI fields clear. This proves action arguments cannot select approval authority.

Also test: Cancel clears UI state while core challenge stays Pending; missing `ConfirmationService::class` binding fails with `serviceUnavailable()`; `approveChallenge()` returning null clears stale UI state without token-state detail; a store exception propagates and never looks like approval success.

- [ ] **Step 3: Verify RED**
```bash
composer test -- --filter FilamentConfirmationApprovalIntegrationTest
```

- [ ] **Step 4: Implement explicit service resolution**
```php
protected function resolveSurfaceRelayConfirmationService(): ConfirmationService
{
    if (!app()->bound(ConfirmationService::class)) {
        throw InvalidFilamentConfirmationBridge::serviceUnavailable();
    }

    $service = app(ConfirmationService::class);
    if (!$service instanceof ConfirmationService) {
        throw InvalidFilamentConfirmationBridge::serviceUnavailable();
    }

    return $service;
}
```

- [ ] **Step 5: Implement approval**
```php
private function approveSurfaceRelayConfirmation(): void
{
    $challengeId = $this->surfaceRelayConfirmationChallengeId;
    if ($challengeId === null || $this->surfaceRelayConfirmationSummary === null) {
        throw InvalidFilamentConfirmationBridge::presentationFailed();
    }

    $approved = $this->resolveSurfaceRelayConfirmationService()->approveChallenge($challengeId);

    $this->clearSurfaceRelayConfirmation();

    if ($approved !== null && $approved !== $challengeId) {
        throw InvalidFilamentConfirmationBridge::presentationFailed();
    }
}
```
No notification/event/log contains challenge or returned receipt.

- [ ] **Step 6: Verify GREEN and commit**
```bash
composer test -- --filter 'FilamentConfirmation(Bridge|Modal|Approval)'
git add packages/laravel/src/Filament/Confirmation/InteractsWithSurfaceRelayConfirmation.php packages/laravel/tests/Support/FilamentConfirmationMemoryStore.php packages/laravel/tests/Support/FilamentConfirmationMutableClock.php packages/laravel/tests/Support/FilamentConfirmationSequenceTokenGenerator.php packages/laravel/tests/Integration/FilamentConfirmationApprovalIntegrationTest.php
git commit -m "feat(filament): approve exact locked confirmation challenge"
```

---

### Task 4: Optional `FilamentActionGateway` hook

**Files:** gateway + gateway integration test.

- [ ] **Step 1: Write RED tests** proving bridge absent preserves current behavior, bridge present receives the exact typed halt/challenge, normal completed actions mount no modal, and returned `ActionPipelineOutcome` remains the original outcome.

- [ ] **Step 2: Verify RED**
```bash
composer test -- --filter FilamentConfirmationGatewayIntegrationTest
```

- [ ] **Step 3: Add final optional constructor parameter**
```php
public function __construct(
    private ActionBus $bus,
    TrustedContextComposer $baseComposer,
    int $maxSelectionRecords = FilamentCurrentSelectionResolver::DEFAULT_MAX_SELECTION_RECORDS,
    private ?FilamentConfirmationBridge $confirmationBridge = null,
) {
    $this->contextFactory = new FilamentInvocationContextFactory(
        $baseComposer,
        maxSelectionRecords: $maxSelectionRecords,
    );
}
```

Replace direct return with:
```php
$outcome = $this->bus->dispatch(new ActionCall(
    actionId: $actionId,
    actionVersion: $actionVersion,
    input: $input,
    context: $context,
    bindingId: $bindingId,
    confirmationReceipt: $confirmationReceipt,
));

$this->confirmationBridge?->presentIfRequired($page, $outcome);

return $outcome;
```

- [ ] **Step 4: Verify regressions and commit**
```bash
composer test -- --filter 'Filament(ActionGateway|CurrentSelectionGateway|ActiveFilterGateway|ConfirmationGateway)'
git add packages/laravel/src/Filament/Invocation/FilamentActionGateway.php packages/laravel/tests/Integration/FilamentConfirmationGatewayIntegrationTest.php
git commit -m "feat(filament): present confirmation halts through optional bridge"
```

---

### Task 5: Explicit retry + fresh trusted scope + idempotency

**Files:** record/table confirmation fixtures + trust-control integration test. Production edits permitted only in T-504 Filament files if a new test exposes a bridge-owned defect.

- [ ] **Step 1: Create fixtures**

`TestConfirmationRecordPage` extends `Filament\Resources\Pages\Page`, uses `InteractsWithRecord` + `InteractsWithSurfaceRelayConfirmation`, references `TestRecordResource`, and uses the same minimal confirmation view.

`TestConfirmationTablePage` extends existing non-final `TestTablePage` and uses `InteractsWithSurfaceRelayConfirmation`.

- [ ] **Step 2: Build one real consequential ActionBus harness** using existing validation, authorization, idempotency, confirmation, execution, output-policy stage order. Action definition:
```php
new ActionDefinition(
    id: 'orders.refund',
    version: 1,
    title: 'Refund order',
    description: 'Exercise the T-504 confirmation bridge.',
    inputSchema: [
        'type' => 'object',
        'properties' => ['amount' => ['type' => 'integer']],
        'required' => ['amount'],
        'additionalProperties' => false,
    ],
    scope: ActionScope::PageScoped,
    effect: ActionEffect::ExternalSideEffect,
    risk: ActionRisk::Consequential,
    idempotency: IdempotencyPolicy::RequiredKey,
    outputSensitivity: OutputSensitivity::Normal,
    outputContentTrust: OutputContentTrust::TrustedApplicationData,
    contextRequirements: [],
);
```
Executor increments a counter.

- [ ] **Step 3: Write happy-path RED/behavior test**
```text
call 1, key K -> confirmation_required, modal presented, executor=0
call mounted Approve -> core record Approved, executor=0
call 2, same intent/key K, confirmationReceipt=call1.challengeId -> completed, executor=1
lost-response exact retry with K -> T-402 replay, executor remains 1
fresh attempt with a different key and consumed old receipt -> confirmation_required, executor remains 1
```

- [ ] **Step 4: Write fresh current-record test**: challenge on record A, approve, switch exact page record to B, old receipt must not execute; restore A, old receipt remains usable if unexpired because wrong-scope attempt did not consume it.

- [ ] **Step 5: Write fresh selection test** using the same real Filament state proven by T-502:
```php
$page->selectedTableRecords = [10, 11];
```
After approval change to `[15]`; retry fails confirmation with executor 0. Restore `[10, 11]`; original receipt may execute once.

- [ ] **Step 6: Write fresh filter test** using `FilamentContextExposure::activeFilters()`. Apply filter A, approve, change only deferred form state and prove applied A remains authoritative; after `applyTableFilters()` changes to B, old receipt fails; restore applied A and prove old receipt remains usable if still valid.

- [ ] **Step 7: Add one-at-a-time actor, tenant, binding, surface, and validated-input mismatch cases**. Every wrong scope yields confirmation-required and zero new execution, then exact original scope consumes the old receipt once.

- [ ] **Step 8: Verify**
```bash
composer test -- --filter FilamentConfirmationTrustControlsIntegrationTest
composer test -- --filter 'Filament(Confirmation|Record|CurrentSelection|ActiveFilter)'
```
If a failure points into confirmation/idempotency core semantics rather than Filament bridge state, stop and reopen architecture; do not patch core under T-504.

- [ ] **Step 9: Commit**
```bash
git add packages/laravel/tests/Fixtures/Filament/TestConfirmationRecordPage.php packages/laravel/tests/Fixtures/Filament/TestConfirmationTablePage.php packages/laravel/tests/Integration/FilamentConfirmationTrustControlsIntegrationTest.php packages/laravel/src/Filament
git commit -m "test(filament): prove confirmation retry trust boundaries"
```

---

### Task 6: Boundary tests, full verification, tracking, review gate

**Files:** `FilamentDependencyPolicyTest.php`, `TASKS.md`, `STATUS.md`, `REVIEW_REQUEST.md`.

- [ ] **Step 1: Extend dependency/source boundary test**. Assert Filament remains absent from `require`, present as `^5.0` in `require-dev`, and `SurfaceRelayServiceProvider`, `ConfirmationService`, `ConfirmationStage`, `ConfirmationScopeHasher`, `IdempotencyIntentHasher`, and Livewire production files contain no new Filament coupling.

- [ ] **Step 2: Assert T-504 production source forbids authority shortcuts**. In concatenated bridge+trait source, require absence of:
```text
$mountedActions
Reflection
request(
Route::
FilamentActionGateway::dispatch
ActionBus
ActionCall
```
The bridge is allowed to reference `ActionPipelineOutcome`; it is not allowed to own business execution.

- [ ] **Step 3: Run focused boundary tests**
```bash
cd packages/laravel
composer test -- --filter FilamentDependencyPolicyTest
```

- [ ] **Step 4: Run full PHP verification**
```bash
composer validate --strict
composer test
```
Record the actual test/assertion count from output; never pre-write a guessed count.

- [ ] **Step 5: Run repository-wide isolation/contract verification**
```bash
cd ../..
python scripts/validate.py
cd packages/browser-runtime
npm ci
npm run typecheck
npm test
```
Record actual browser test count. Do not alter browser source/lockfile for unrelated dependency advisories.

- [ ] **Step 6: Review exact-base diff**
```bash
git diff --stat 66f1d5db7e7902b6d7f09306be021119a6d96086...HEAD
git diff --name-only 66f1d5db7e7902b6d7f09306be021119a6d96086...HEAD
```
Production paths may be only `packages/laravel/src/Filament/Confirmation/**` and `packages/laravel/src/Filament/Invocation/FilamentActionGateway.php`. Any `spec/0.1/**`, confirmation/idempotency core, Livewire production, or browser production diff blocks completion.

- [ ] **Step 7: Update tracking after green verification**. `TASKS.md` records T-504 outcome/acceptance/evidence and leaves T-505 NOT STARTED. `STATUS.md` records exact head, changed files, actual PHP/browser/contract evidence, known limits. `REVIEW_REQUEST.md` asks reviewers to focus on challenge substitution, locked-property tampering, presentation races, unrelated mounted actions, automatic redispatch, token leakage, service fallback, fresh trusted context, idempotency, and optional dependency boundaries.

- [ ] **Step 8: Validate tracking and commit**
```bash
python scripts/validate.py
git add TASKS.md STATUS.md REVIEW_REQUEST.md packages/laravel/tests/Unit/FilamentDependencyPolicyTest.php
git commit -m "docs(review): prepare T-504 confirmation bridge review"
```

- [ ] **Step 9: Push exact head and require all 7 CI jobs green**
```text
php-lint
contract
browser
PHP 8.3 / Illuminate 12 / Testbench 10
PHP 8.3 / Illuminate 13 / Testbench 11
PHP 8.4 / Illuminate 12 / Testbench 10
PHP 8.4 / Illuminate 13 / Testbench 11
```
Inspect at least one PHP job log for exact test/assertion count and browser log for exact Vitest count.

- [ ] **Step 10: Open review PR** only after exact-head CI is green.

Title:
```text
feat(filament): add approval-only confirmation bridge
```
PR body states: approval is not execution; caller explicitly retries; retry freshly resolves trusted context; no core confirmation/idempotency/protocol/browser/Livewire production code changed; merge remains a separate user gate.

- [ ] **Step 11: Stop**. Do not merge and do not start T-505. External review findings must be verified/fixed and the exact final head revalidated before T-504 can be called DONE/REVIEWED.

---

## Self-Review Result

- Spec coverage: all required modal, authority, retry, concurrency, stale-scope, secrecy, dependency, and non-goal boundaries map to Tasks 1-6.
- Placeholder scan: no `TBD`, implementation placeholders, or omitted test command remains.
- Type consistency: bridge, trait, gateway, `ConfirmationService`, `ConfirmationStore`, clock/token interfaces, and Filament fixture names are consistent across tasks.
- Scope: no planned production edit exists outside the approved Filament boundary.
