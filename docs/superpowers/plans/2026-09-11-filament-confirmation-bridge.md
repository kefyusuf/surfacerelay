# Filament Confirmation Bridge Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement T-504 as a Filament-only, approval-only confirmation bridge that presents an exact runtime-issued T-401 challenge, approves only that locked challenge, and requires the original caller to retry through the normal ActionBus path before any business execution can occur.

**Architecture:** Keep T-401/T-402 confirmation and idempotency core unchanged. Add a narrow Filament presentation adapter plus an explicit page opt-in trait, wire the existing `FilamentActionGateway` to optionally present typed confirmation halts, and prove that approval never redispatches business work while the later explicit retry re-resolves current record, current selection, active filters, actor, tenant, binding, surface, and input before receipt consumption.

**Tech Stack:** PHP 8.3/8.4, Laravel/Illuminate 12/13, Livewire 4.4, Filament 5.x (matrix currently resolves 5.8.1), PHPUnit 11, Orchestra Testbench 10/11, SQLite for focused adapter tests, existing MySQL 8.4 CI matrix.

**Spec:** `docs/superpowers/specs/2026-09-10-filament-confirmation-bridge-design.md`

## Global Constraints

- Base is `main@66f1d5db7e7902b6d7f09306be021119a6d96086`; implementation branch is `feat/filament-confirmation-bridge`.
- D-051 is accepted: Filament confirmation is approval-only + explicit retry; approval never executes or redispatches the business action.
- `spec/0.1/**` remains frozen.
- `packages/laravel/src/Confirmation/**` remains production-read-only unless an unavoidable contradiction is proven and architecture review is reopened.
- `packages/laravel/src/Livewire/**` and `packages/browser-runtime/src/**` remain production-read-only.
- Filament remains optional/dev-only: `filament/filament:^5.0` stays in `require-dev`, never `require`.
- `SurfaceRelayServiceProvider` must not gain eager `Filament\\...` references or hidden confirmation-store construction.
- The bridge may consume only a real typed `ConfirmationChallenge` from an exact `ActionPipelineStage::Confirmation` / `confirmation_required` halt.
- The bridge never reconstructs a challenge from `details`, metadata, requests, route/query state, action arguments, or browser payloads.
- The three page presentation fields are server-authored Livewire `#[Locked]` properties; browser mutation must fail.
- The internal Filament action accepts no browser challenge ID argument as authority.
- Raw challenge/receipt tokens, token hashes, scope fingerprints, business input, trusted identities, selected records, and active-filter values must not enter logs/audit/notifications/exceptions.
- `ConfirmationService::class` must be explicitly bound by the host runtime; the trait must not construct an in-memory/unlocked fallback.
- Approval null/expired/already-non-pending outcomes remain generic and non-oracular.
- One page hosts at most one outstanding SurfaceRelay confirmation presentation; different-challenge overwrite fails closed.
- An unrelated mounted Filament action must never be force-unmounted, replaced, or silently nested by the bridge.
- A same-challenge re-presentation is idempotent; if its modal was lost and no unrelated action is mounted, it may be safely remounted from the existing locked state.
- T-504 does not claim proof-of-human, `approvedBy`, supervisor/delegated approval, WebAuthn, or cross-user approval semantics.
- Early caller retry before approval is not a status-poll API; it may produce a new challenge under normal T-401 semantics and must not be treated as approval progress.

---

## File Map

### Production

- Create `packages/laravel/src/Filament/Confirmation/InvalidFilamentConfirmationBridge.php` — static-safe adapter failures only.
- Create `packages/laravel/src/Filament/Confirmation/FilamentConfirmationBridge.php` — recognizes typed confirmation halts and delegates presentation to an explicitly opted-in page.
- Create `packages/laravel/src/Filament/Confirmation/InteractsWithSurfaceRelayConfirmation.php` — owns locked presentation state, reserved Filament Action, approve/cancel handlers, safe service resolution, and public modal lifecycle only.
- Modify `packages/laravel/src/Filament/Invocation/FilamentActionGateway.php` — optional final constructor dependency and post-dispatch presentation hook; returned `ActionPipelineOutcome` remains unchanged.

### Test fixtures

- Create `packages/laravel/tests/Fixtures/Filament/TestConfirmationPage.php` — non-record Filament resource page using the confirmation trait.
- Create `packages/laravel/tests/Fixtures/Filament/TestConfirmationRecordPage.php` — record-aware page using the confirmation trait for fresh-record scope tests.
- Create `packages/laravel/tests/Fixtures/Filament/TestConfirmationTablePage.php` — table page using the confirmation trait for selection/filter scope tests.
- Create `packages/laravel/tests/Fixtures/views/filament-test-confirmation-page.blade.php` — minimal component view rendering Filament action modals.

### Tests

- Create `packages/laravel/tests/Unit/FilamentConfirmationBridgeTest.php` — typed outcome recognition, malformed halt, opt-in requirement, static-safe exceptions.
- Create `packages/laravel/tests/Integration/FilamentConfirmationModalIntegrationTest.php` — real Filament/Livewire modal lifecycle, locked state, remount/conflict, direct-mount and browser-tamper resistance.
- Create `packages/laravel/tests/Integration/FilamentConfirmationApprovalIntegrationTest.php` — exact locked-token approval, cancel, null result, service/store failure, zero business execution.
- Create `packages/laravel/tests/Integration/FilamentConfirmationGatewayIntegrationTest.php` — optional gateway hook and unchanged outcome/backward compatibility.
- Create `packages/laravel/tests/Integration/FilamentConfirmationTrustControlsIntegrationTest.php` — explicit retry, single-use receipt, idempotency, current-record/current-selection/active-filter drift.
- Modify `packages/laravel/tests/Unit/FilamentDependencyPolicyTest.php` — prevent core/provider/browser/Livewire coupling and forbidden authority sources.

### Tracking / review

- Modify `TASKS.md`, `STATUS.md`, `REVIEW_REQUEST.md` only after implementation verification is green.

---

### Task 1: Typed confirmation-presentation boundary

**Files:**
- Create: `packages/laravel/src/Filament/Confirmation/InvalidFilamentConfirmationBridge.php`
- Create: `packages/laravel/src/Filament/Confirmation/FilamentConfirmationBridge.php`
- Create: `packages/laravel/src/Filament/Confirmation/InteractsWithSurfaceRelayConfirmation.php`
- Create: `packages/laravel/tests/Unit/FilamentConfirmationBridgeTest.php`

**Interfaces:**
- Consumes: `ActionPipelineOutcome`, `ActionPipelineStage::Confirmation`, `CoreActionErrorCode::CONFIRMATION_REQUIRED`, typed `ConfirmationChallenge`, `Filament\\Resources\\Pages\\Page`.
- Produces: `FilamentConfirmationBridge::presentIfRequired(Page $page, ActionPipelineOutcome $outcome): void`.
- Produces trait presentation entry point: `InteractsWithSurfaceRelayConfirmation::presentSurfaceRelayConfirmation(ConfirmationChallenge $challenge): void`.
- Produces static-safe exception factories: `invalidOutcome()`, `hostUnavailable()`, `presentationConflict()`, `presentationFailed()`, `serviceUnavailable()`.

- [ ] **Step 1: Write RED tests for no-op and typed-halt recognition**

Create `FilamentConfirmationBridgeTest.php` with helpers that build an `ActionPipelineState` from a low-risk `ActionDefinition` and `new InvocationContext('filament', 'corr-bridge')`. Add these exact behaviors:

```php
public function test_completed_and_non_confirmation_outcomes_are_no_op(): void
{
    $page = new NonRecordPage();
    $bridge = new FilamentConfirmationBridge();
    $state = $this->state();

    $bridge->presentIfRequired($page, ActionPipelineOutcome::completed($state));
    $bridge->presentIfRequired($page, ActionPipelineOutcome::halted(
        $state,
        ActionPipelineStage::Authorization,
        new ActionPipelineHalt(CoreActionErrorCode::AUTHORIZATION_DENIED),
    ));

    self::assertTrue(true);
}

public function test_confirmation_halt_without_typed_challenge_fails_closed(): void
{
    $this->expectException(InvalidFilamentConfirmationBridge::class);
    $this->expectExceptionMessage('Filament confirmation outcome is invalid.');

    (new FilamentConfirmationBridge())->presentIfRequired(
        new NonRecordPage(),
        ActionPipelineOutcome::halted(
            $this->state(),
            ActionPipelineStage::Confirmation,
            new ActionPipelineHalt(CoreActionErrorCode::CONFIRMATION_REQUIRED),
        ),
    );
}

public function test_real_confirmation_requires_explicit_page_opt_in(): void
{
    $challenge = new ConfirmationChallenge(str_repeat('A', 43), 'Approve refund', '2026-09-11T01:00:00Z');

    try {
        (new FilamentConfirmationBridge())->presentIfRequired(
            new NonRecordPage(),
            ActionPipelineOutcome::halted(
                $this->state(),
                ActionPipelineStage::Confirmation,
                new ActionPipelineHalt(
                    CoreActionErrorCode::CONFIRMATION_REQUIRED,
                    confirmation: $challenge,
                ),
            ),
        );
        self::fail('Expected an opted-out page to reject confirmation presentation.');
    } catch (InvalidFilamentConfirmationBridge $e) {
        self::assertSame('Filament confirmation bridge is unavailable for this page.', $e->getMessage());
        self::assertNull($e->getPrevious());
    }
}
```

Use a local test-only page class that uses `InteractsWithSurfaceRelayConfirmation` for the positive delegation test, and override `presentSurfaceRelayConfirmation()` in that test fixture to capture the exact object identity without invoking Filament UI yet.

- [ ] **Step 2: Run the focused test and verify RED**

Run:

```bash
cd packages/laravel
composer test -- --filter FilamentConfirmationBridgeTest
```

Expected: FAIL because `FilamentConfirmationBridge`, `InteractsWithSurfaceRelayConfirmation`, and `InvalidFilamentConfirmationBridge` do not yet exist.

- [ ] **Step 3: Implement the minimal static-safe exception and bridge classifier**

`InvalidFilamentConfirmationBridge.php` must use fixed messages and no previous exception:

```php
final class InvalidFilamentConfirmationBridge extends \RuntimeException
{
    public static function invalidOutcome(): self
    {
        return new self('Filament confirmation outcome is invalid.');
    }

    public static function hostUnavailable(): self
    {
        return new self('Filament confirmation bridge is unavailable for this page.');
    }

    public static function presentationConflict(): self
    {
        return new self('Filament confirmation presentation conflicts with active page state.');
    }

    public static function presentationFailed(): self
    {
        return new self('Filament confirmation presentation failed.');
    }

    public static function serviceUnavailable(): self
    {
        return new self('Filament confirmation service is unavailable.');
    }
}
```

`FilamentConfirmationBridge.php` must classify only the exact typed halt shape:

```php
public function presentIfRequired(Page $page, ActionPipelineOutcome $outcome): void
{
    if ($outcome->completed) {
        return;
    }

    $halt = $outcome->halt;
    $looksLikeConfirmation = $outcome->haltedAt === ActionPipelineStage::Confirmation
        || $halt?->code === CoreActionErrorCode::CONFIRMATION_REQUIRED
        || $halt?->confirmation !== null;

    if (!$looksLikeConfirmation) {
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

Create the trait with only the three locked fields and a temporary `presentSurfaceRelayConfirmation()` method that stores the exact fields; do not add the Filament Action yet:

```php
#[Locked]
public ?string $surfaceRelayConfirmationChallengeId = null;

#[Locked]
public ?string $surfaceRelayConfirmationSummary = null;

#[Locked]
public ?string $surfaceRelayConfirmationExpiresAt = null;

public function presentSurfaceRelayConfirmation(ConfirmationChallenge $challenge): void
{
    $this->surfaceRelayConfirmationChallengeId = $challenge->challengeId;
    $this->surfaceRelayConfirmationSummary = $challenge->summary;
    $this->surfaceRelayConfirmationExpiresAt = $challenge->expiresAt;
}
```

- [ ] **Step 4: Run focused tests and verify GREEN**

```bash
cd packages/laravel
composer test -- --filter FilamentConfirmationBridgeTest
```

Expected: PASS.

- [ ] **Step 5: Run PHP lint on the new production files**

```bash
php -l src/Filament/Confirmation/InvalidFilamentConfirmationBridge.php
php -l src/Filament/Confirmation/FilamentConfirmationBridge.php
php -l src/Filament/Confirmation/InteractsWithSurfaceRelayConfirmation.php
```

Expected: all report no syntax errors.

- [ ] **Step 6: Commit Task 1**

```bash
git add packages/laravel/src/Filament/Confirmation packages/laravel/tests/Unit/FilamentConfirmationBridgeTest.php
git commit -m "feat(filament): add typed confirmation presentation boundary"
```

---

### Task 2: Real Filament modal lifecycle, locked state, and presentation concurrency

**Files:**
- Modify: `packages/laravel/src/Filament/Confirmation/InteractsWithSurfaceRelayConfirmation.php`
- Create: `packages/laravel/tests/Fixtures/Filament/TestConfirmationPage.php`
- Create: `packages/laravel/tests/Fixtures/views/filament-test-confirmation-page.blade.php`
- Create: `packages/laravel/tests/Integration/FilamentConfirmationModalIntegrationTest.php`

**Interfaces:**
- Consumes: `Filament\\Actions\\Action`, public `mountAction()`, public `getMountedAction()`, public `unmountAction()`, Livewire `#[Locked]`.
- Produces reserved action name `surfacerelay_confirmation`.
- Produces public trait methods `presentSurfaceRelayConfirmation(ConfirmationChallenge $challenge): void` and `clearSurfaceRelayConfirmation(): void`.
- Does not consume/produce any business-action execution callback.

- [ ] **Step 1: Create the real page/view fixture and RED lifecycle tests**

`TestConfirmationPage.php`:

```php
final class TestConfirmationPage extends Page
{
    use InteractsWithSurfaceRelayConfirmation;

    protected static string $resource = TestRecordResource::class;
    protected string $view = 'filament-test-confirmation-page';
}
```

`filament-test-confirmation-page.blade.php`:

```blade
<div>
    <x-filament-actions::modals />
</div>
```

In `FilamentConfirmationModalIntegrationTest`, configure the test view path with:

```php
protected function defineEnvironment($app): void
{
    $app['view']->addLocation(dirname(__DIR__) . '/Fixtures/views');
}
```

Add tests that assert:

```php
public function test_present_mounts_reserved_modal_with_exact_locked_state(): void
{
    $page = new TestConfirmationPage();
    $challenge = $this->challenge('A', 'Approve refund');

    $page->presentSurfaceRelayConfirmation($challenge);

    self::assertSame($challenge->challengeId, $page->surfaceRelayConfirmationChallengeId);
    self::assertSame($challenge->summary, $page->surfaceRelayConfirmationSummary);
    self::assertSame($challenge->expiresAt, $page->surfaceRelayConfirmationExpiresAt);
    self::assertSame('surfacerelay_confirmation', $page->getMountedAction()?->getName());
}

public function test_same_challenge_is_idempotent_and_can_remount_after_modal_loss(): void
{
    $page = new TestConfirmationPage();
    $challenge = $this->challenge('A', 'Approve refund');

    $page->presentSurfaceRelayConfirmation($challenge);
    $page->unmountAction();
    $page->presentSurfaceRelayConfirmation($challenge);

    self::assertSame('surfacerelay_confirmation', $page->getMountedAction()?->getName());
    self::assertSame($challenge->challengeId, $page->surfaceRelayConfirmationChallengeId);
}

public function test_different_challenge_cannot_overwrite_existing_presentation(): void
{
    $page = new TestConfirmationPage();
    $page->presentSurfaceRelayConfirmation($this->challenge('A', 'Approve A'));

    $this->expectException(InvalidFilamentConfirmationBridge::class);
    $this->expectExceptionMessage('Filament confirmation presentation conflicts with active page state.');

    $page->presentSurfaceRelayConfirmation($this->challenge('B', 'Approve B'));
}
```

Also add an unrelated mounted action to the fixture:

```php
public function unrelatedAction(): Action
{
    return Action::make('unrelated')->requiresConfirmation()->action(static fn (): null => null);
}
```

and prove presentation fails without unmounting it.

- [ ] **Step 2: Add a real Livewire hydration RED test for `#[Locked]` tampering**

Use Livewire's test harness against `TestConfirmationPage` after server-side presentation. Attempt:

```php
$component->set('surfaceRelayConfirmationChallengeId', str_repeat('B', 43));
```

Expected: Livewire throws `CannotUpdateLockedPropertyException` and the server-side property remains A. Repeat for summary and expiry so none of the three presentation fields are browser-mutable.

- [ ] **Step 3: Run modal tests and verify RED**

```bash
cd packages/laravel
composer test -- --filter FilamentConfirmationModalIntegrationTest
```

Expected: FAIL because the trait does not yet define/register/mount the reserved Filament Action or enforce presentation concurrency.

- [ ] **Step 4: Implement the reserved Filament Action and public lifecycle only**

In the trait define:

```php
private const string SURFACERELAY_CONFIRMATION_ACTION = 'surfacerelay_confirmation';

public function surfaceRelayConfirmationAction(): Action
{
    return Action::make(self::SURFACERELAY_CONFIRMATION_ACTION)
        ->requiresConfirmation()
        ->modalHeading('Confirm action')
        ->modalDescription(fn (): string => $this->surfaceRelayConfirmationSummary
            ?? 'Confirmation is unavailable.')
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

Add a helper that reads only public Filament action state:

```php
private function mountedSurfaceRelayActionName(): ?string
{
    try {
        return $this->getMountedAction()?->getName();
    } catch (ActionNotResolvableException) {
        throw InvalidFilamentConfirmationBridge::presentationFailed();
    }
}
```

Implement `presentSurfaceRelayConfirmation()` with this order:

1. Validate existing locked state coherence (`challengeId === null` iff `summary === null`; expiry may be null).
2. Inspect the public mounted action before mutating locked state.
3. If an unrelated action is mounted, throw `presentationConflict()`.
4. If no presentation exists, set exact challenge fields and call `mountAction(self::SURFACERELAY_CONFIRMATION_ACTION)`.
5. If the same exact `challengeId + summary + expiresAt` is already stored and the reserved action is mounted, no-op.
6. If the same exact presentation is stored but no action is mounted, remount it.
7. If any stored field differs, throw `presentationConflict()`.
8. After mounting, verify `getMountedAction()?->getName()` is the reserved name; otherwise clear the just-written state and throw `presentationFailed()`.

Do not read or write Filament's raw `$mountedActions` property.

- [ ] **Step 5: Implement state clearing without touching core confirmation state**

```php
public function clearSurfaceRelayConfirmation(): void
{
    $this->surfaceRelayConfirmationChallengeId = null;
    $this->surfaceRelayConfirmationSummary = null;
    $this->surfaceRelayConfirmationExpiresAt = null;
}
```

Do not call `ConfirmationService` from this method.

- [ ] **Step 6: Run focused modal/locking tests and verify GREEN**

```bash
cd packages/laravel
composer test -- --filter FilamentConfirmationModalIntegrationTest
```

Expected: PASS, including real locked-property tamper rejection and unrelated-action preservation.

- [ ] **Step 7: Commit Task 2**

```bash
git add packages/laravel/src/Filament/Confirmation/InteractsWithSurfaceRelayConfirmation.php packages/laravel/tests/Fixtures/Filament/TestConfirmationPage.php packages/laravel/tests/Fixtures/views/filament-test-confirmation-page.blade.php packages/laravel/tests/Integration/FilamentConfirmationModalIntegrationTest.php
git commit -m "feat(filament): add locked confirmation modal lifecycle"
```

---

### Task 3: Approval and cancel authority semantics

**Files:**
- Modify: `packages/laravel/src/Filament/Confirmation/InteractsWithSurfaceRelayConfirmation.php`
- Create: `packages/laravel/tests/Integration/FilamentConfirmationApprovalIntegrationTest.php`

**Interfaces:**
- Consumes explicit Laravel binding `ConfirmationService::class`.
- Consumes `ConfirmationService::approveChallenge(string $challengeId): ?string` exactly once per Approve click.
- Produces no receipt-return API and no business dispatch.

- [ ] **Step 1: Write an in-test deterministic confirmation harness**

Create test-local implementations of the existing interfaces:

```php
final class FilamentConfirmationTestClock implements ConfirmationClock
{
    public function __construct(public int $now = 1_800_000_000) {}
    public function now(): int { return $this->now; }
}

final class FilamentConfirmationTestTokenGenerator implements ConfirmationTokenGenerator
{
    /** @param list<string> $tokens */
    public function __construct(private array $tokens) {}
    public function generate(): string { return array_shift($this->tokens) ?? throw new \RuntimeException('No test token.'); }
}
```

Use a test-local `ConfirmationStore` implementation with the same state transition checks already used by `ConfirmationPipelineIntegrationTest`: unique pending create; pending+unexpired approve; exact-scope+approved+unexpired destructive consume.

Bind the exact service instance used to issue the challenge:

```php
$this->app->instance(ConfirmationService::class, $service);
```

- [ ] **Step 2: Write RED tests proving approval uses only locked A even when browser arguments contain B**

Issue two real challenges A and B from the same `ConfirmationService`. Present A. Then invoke the reserved action with browser/action arguments containing B. The action closure must accept no challenge argument and read only locked A.

After approval assert:

```php
self::assertTrue($service->consumeReceipt($challengeA->challengeId, $scopeA));
self::assertFalse($service->consumeReceipt($challengeB->challengeId, $scopeB));
self::assertNull($page->surfaceRelayConfirmationChallengeId);
self::assertNull($page->surfaceRelayConfirmationSummary);
self::assertNull($page->surfaceRelayConfirmationExpiresAt);
```

Then prove B is still pending by calling `approveChallenge($challengeB->challengeId)` and expecting B.

- [ ] **Step 3: Write RED tests for direct mount, cancel, null approval, and service failure**

Required cases:

```text
direct mount/call with no server-authored locked challenge -> no approval
Cancel -> locked UI state clears, core challenge remains pending and approvable
approveChallenge() returns null -> state clears, no execution, no token-state detail leaks
ConfirmationService binding missing -> InvalidFilamentConfirmationBridge::serviceUnavailable()
ConfirmationStore throws during approve -> failure propagates; no apparent success and no business execution
```

For the missing binding case remove any explicit binding and require the trait to check `$app->bound(ConfirmationService::class)` before resolution so Laravel cannot silently auto-construct a differently configured authority.

- [ ] **Step 4: Run approval tests and verify RED**

```bash
cd packages/laravel
composer test -- --filter FilamentConfirmationApprovalIntegrationTest
```

Expected: FAIL until the approval handler and explicit service resolver are implemented.

- [ ] **Step 5: Implement explicit service resolution and approval handler**

Add:

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

Implement approval without returning the receipt to the UI:

```php
private function approveSurfaceRelayConfirmation(): void
{
    $challengeId = $this->surfaceRelayConfirmationChallengeId;
    if ($challengeId === null || $this->surfaceRelayConfirmationSummary === null) {
        throw InvalidFilamentConfirmationBridge::presentationFailed();
    }

    $service = $this->resolveSurfaceRelayConfirmationService();
    $approvedReceipt = $service->approveChallenge($challengeId);

    $this->clearSurfaceRelayConfirmation();

    if ($approvedReceipt === null) {
        return;
    }

    if ($approvedReceipt !== $challengeId) {
        throw InvalidFilamentConfirmationBridge::presentationFailed();
    }
}
```

Do not include `$approvedReceipt`, `$challengeId`, hash/scope data, or dynamic token state in a notification/event/log. Static success/failure notices may be added only if they carry no secret values; the minimal implementation may omit notifications entirely.

- [ ] **Step 6: Run approval and Task 1/2 regression tests**

```bash
cd packages/laravel
composer test -- --filter 'FilamentConfirmation(Bridge|Modal|Approval)'
```

Expected: PASS.

- [ ] **Step 7: Commit Task 3**

```bash
git add packages/laravel/src/Filament/Confirmation/InteractsWithSurfaceRelayConfirmation.php packages/laravel/tests/Integration/FilamentConfirmationApprovalIntegrationTest.php
git commit -m "feat(filament): approve exact locked confirmation challenge"
```

---

### Task 4: Optional gateway presentation hook

**Files:**
- Modify: `packages/laravel/src/Filament/Invocation/FilamentActionGateway.php`
- Create: `packages/laravel/tests/Integration/FilamentConfirmationGatewayIntegrationTest.php`
- Reuse: `packages/laravel/tests/Fixtures/Filament/TestConfirmationPage.php`

**Interfaces:**
- Consumes: `?FilamentConfirmationBridge` as the final constructor parameter.
- Produces: unchanged `dispatch(...): ActionPipelineOutcome` signature and unchanged return object identity/value.
- Existing call sites that omit the bridge remain valid.

- [ ] **Step 1: Write RED gateway compatibility tests**

Use an ActionBus with deterministic passthrough stages and one test stage that halts at `ActionPipelineStage::Confirmation` with a real challenge. Prove:

```php
public function test_gateway_without_bridge_returns_confirmation_outcome_without_touching_page_ui(): void
{
    $gateway = $this->gateway(bridge: null);
    $page = new TestConfirmationPage();

    $outcome = $gateway->dispatch(
        page: $page,
        actionId: 'orders.refund',
        actionVersion: 1,
        input: ['amount' => 100],
        surface: 'filament',
        correlationId: 'corr-no-bridge',
    );

    self::assertFalse($outcome->completed);
    self::assertSame(CoreActionErrorCode::CONFIRMATION_REQUIRED, $outcome->halt?->code);
    self::assertNull($page->surfaceRelayConfirmationChallengeId);
}

public function test_configured_bridge_presents_same_typed_challenge_and_returns_original_outcome(): void
{
    $gateway = $this->gateway(new FilamentConfirmationBridge());
    $page = new TestConfirmationPage();

    $outcome = $gateway->dispatch(/* exact same envelope */);

    self::assertSame($outcome->halt?->confirmation?->challengeId, $page->surfaceRelayConfirmationChallengeId);
    self::assertSame('surfacerelay_confirmation', $page->getMountedAction()?->getName());
}
```

Also prove a normal completed low-risk action with a configured bridge does not mount UI.

- [ ] **Step 2: Run gateway tests and verify RED**

```bash
cd packages/laravel
composer test -- --filter FilamentConfirmationGatewayIntegrationTest
```

Expected: FAIL because `FilamentActionGateway` does not yet accept/call the bridge.

- [ ] **Step 3: Implement the minimal optional final constructor dependency**

Change only the constructor and final dispatch return:

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

Then:

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

No normalization, redispatch, receipt insertion, or context mutation belongs here.

- [ ] **Step 4: Run T-501/T-502/T-503 gateway regressions plus new gateway tests**

```bash
cd packages/laravel
composer test -- --filter 'Filament(Record|CurrentSelection|ActiveFilter|Confirmation).*Gateway'
```

Expected: PASS.

- [ ] **Step 5: Commit Task 4**

```bash
git add packages/laravel/src/Filament/Invocation/FilamentActionGateway.php packages/laravel/tests/Integration/FilamentConfirmationGatewayIntegrationTest.php
git commit -m "feat(filament): present confirmation halts through optional bridge"
```

---

### Task 5: End-to-end explicit retry, single-use, idempotency, and fresh Filament scope

**Files:**
- Create: `packages/laravel/tests/Fixtures/Filament/TestConfirmationRecordPage.php`
- Create: `packages/laravel/tests/Fixtures/Filament/TestConfirmationTablePage.php`
- Create: `packages/laravel/tests/Integration/FilamentConfirmationTrustControlsIntegrationTest.php`
- Modify only if a real bug is exposed: files already introduced by Tasks 1-4.

**Interfaces:**
- Consumes real `ConfirmationStage`, `ConfirmationScopeHasher`, existing idempotency stages/service, `FilamentInvocationContextFactory`, and T-501/T-502/T-503 resolvers.
- Produces no new production API unless a failing integration test proves the existing adapter boundary cannot express the approved design.

- [ ] **Step 1: Build one real consequential ActionBus harness**

Follow `ConfirmationPipelineIntegrationTest` and existing Filament trust-control tests. The harness must include the real canonical stage order:

```text
input_validation
→ authorization
→ idempotency
→ confirmation
→ execution
→ output_policy
```

Use one consequential page-scoped action such as:

```php
new ActionDefinition(
    id: 'orders.refund',
    version: 1,
    title: 'Refund order',
    description: 'Test T-504 confirmation bridge.',
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

The executor increments a public call counter and returns a small deterministic payload.

- [ ] **Step 2: Write RED end-to-end happy-path and replay tests**

Exact sequence:

```text
call 1 with idempotency key K -> confirmation_required + modal presented + executor calls 0
human-facing modal Approve -> ConfirmationService record pending→approved + executor calls 0
call 2 with same business intent, same K and confirmationReceipt=call1.challengeId -> completed + executor calls 1
call 3 reusing consumed receipt on a non-replayable/fresh attempt -> no second execution; normal confirmation semantics apply
```

For the T-402 completed-replay case, keep the same idempotency key and exact intent and prove a lost-response retry replays the stored result without requiring a second application side effect. The test must follow the existing T-402 rule rather than weakening confirmation consumption.

- [ ] **Step 3: Write RED fresh current-record scope test**

Create `TestConfirmationRecordPage` by composing the existing record-aware fixture with `InteractsWithSurfaceRelayConfirmation`. First call on record A, approve A, then change the exact active page record to B through the same public/test fixture mechanism used by T-501. Retry with A receipt must halt at confirmation and executor remain 0. Restore exact record A and prove the still-valid A receipt can execute once because wrong-scope consumption did not spend it.

- [ ] **Step 4: Write RED fresh selection scope test**

Create `TestConfirmationTablePage` using the existing table resource plus `InteractsWithSurfaceRelayConfirmation`.

Use Filament's public effective-selection APIs/fixture state from T-502:

```text
selection [10, 11] -> challenge A -> approve A
change effective selection to [15]
retry with A receipt -> confirmation_required; executor 0
restore [10, 11]
retry with same A receipt -> execute exactly once
```

Never read raw Livewire selection properties as authority in production code.

- [ ] **Step 5: Write RED fresh active-filter scope test**

Expose `FilamentContextExposure::activeFilters()` on both calls. Use real applied Filament filter APIs from T-503:

```text
apply status=true -> challenge A -> approve A
change only deferred/pending filter form state -> A receipt remains exact for currently applied A
apply status=false -> A receipt no longer matches and grants no authority
restore applied status=true -> A receipt remains usable if not expired/consumed
```

The test must distinguish pending deferred filter edits from applied state.

- [ ] **Step 6: Write RED cross-dimension tests for actor/tenant/binding/surface/input**

Use deterministic trusted resolvers and the existing gateway arguments to mutate each dimension one at a time after approval. Each wrong scope must produce a new confirmation requirement and zero execution; then the original exact scope must still be able to consume the old receipt once.

Do not add any T-504-specific scope hashing logic. If one of these tests fails because the core scope already behaves differently, stop and review the contradiction rather than patching the bridge.

- [ ] **Step 7: Run the trust-control integration test and verify RED/diagnose only bridge-owned failures**

```bash
cd packages/laravel
composer test -- --filter FilamentConfirmationTrustControlsIntegrationTest
```

Expected before any necessary Task-5 adapter hardening: at least one new integration assertion should fail if the Tasks 1-4 bridge lifecycle is incomplete. Any failure in core confirmation/idempotency hashing must trigger architecture review instead of a silent core edit.

- [ ] **Step 8: Apply only minimal bridge/trait/gateway fixes required by those tests**

Permitted production edit set:

```text
src/Filament/Confirmation/FilamentConfirmationBridge.php
src/Filament/Confirmation/InteractsWithSurfaceRelayConfirmation.php
src/Filament/Confirmation/InvalidFilamentConfirmationBridge.php
src/Filament/Invocation/FilamentActionGateway.php
```

Forbidden without architecture re-open:

```text
src/Confirmation/**
src/Idempotency/**
src/Livewire/**
../../browser-runtime/src/**
../../spec/0.1/**
```

- [ ] **Step 9: Run all Filament confirmation tests and existing T-501/T-503 trust controls**

```bash
cd packages/laravel
composer test -- --filter 'Filament(Confirmation|Record|CurrentSelection|ActiveFilter)'
```

Expected: PASS.

- [ ] **Step 10: Commit Task 5**

```bash
git add packages/laravel/tests/Fixtures/Filament/TestConfirmationRecordPage.php packages/laravel/tests/Fixtures/Filament/TestConfirmationTablePage.php packages/laravel/tests/Integration/FilamentConfirmationTrustControlsIntegrationTest.php packages/laravel/src/Filament
git commit -m "test(filament): prove confirmation retry trust boundaries"
```

---

### Task 6: Dependency/secret boundary, full verification, tracking, and review handoff

**Files:**
- Modify: `packages/laravel/tests/Unit/FilamentDependencyPolicyTest.php`
- Modify: `TASKS.md`
- Modify: `STATUS.md`
- Modify: `REVIEW_REQUEST.md`
- Review only: `docs/DECISION-REGISTER.md`, `docs/superpowers/specs/2026-09-10-filament-confirmation-bridge-design.md`

**Interfaces:**
- No new runtime interface. This task freezes implementation evidence and catches accidental scope expansion.

- [ ] **Step 1: Add dependency/source-boundary tests**

Extend `FilamentDependencyPolicyTest` with explicit source assertions:

```php
public function test_t504_keeps_confirmation_core_and_base_provider_filament_free(): void
{
    $root = dirname(__DIR__, 2);

    foreach ([
        'src/Confirmation/ConfirmationService.php',
        'src/Confirmation/ConfirmationStage.php',
        'src/Confirmation/ConfirmationScopeHasher.php',
        'src/SurfaceRelayServiceProvider.php',
    ] as $relativePath) {
        $source = file_get_contents($root . '/' . $relativePath);
        self::assertIsString($source, $relativePath);
        self::assertStringNotContainsString('Filament\\\\', $source, $relativePath);
    }
}

public function test_t504_bridge_does_not_use_raw_filament_action_state_or_caller_authority_sources(): void
{
    $root = dirname(__DIR__, 2);
    $bridge = file_get_contents($root . '/src/Filament/Confirmation/FilamentConfirmationBridge.php');
    $trait = file_get_contents($root . '/src/Filament/Confirmation/InteractsWithSurfaceRelayConfirmation.php');
    self::assertIsString($bridge);
    self::assertIsString($trait);

    foreach ([
        '->mountedActions',
        '$mountedActions',
        'Reflection',
        'request(',
        'Route::',
        'query(',
        'metadata',
    ] as $forbidden) {
        self::assertStringNotContainsString($forbidden, $bridge . "\n" . $trait, $forbidden);
    }
}
```

Add assertions that `composer.json` still has no production Filament dependency and `SurfaceRelayServiceProvider.php` still contains no `Filament\\` reference.

- [ ] **Step 2: Add a source-boundary assertion against automatic business redispatch**

Read the new bridge and trait source and assert they do not reference:

```text
FilamentActionGateway::dispatch
ActionBus
ActionCall
RuntimeBinding
confirmationReceipt =
```

The bridge may type-reference `ActionPipelineOutcome`; it must not own a second execution path.

- [ ] **Step 3: Run focused dependency tests**

```bash
cd packages/laravel
composer test -- --filter FilamentDependencyPolicyTest
```

Expected: PASS.

- [ ] **Step 4: Run the full PHP test suite locally**

```bash
cd packages/laravel
composer validate --strict
composer test
```

Expected: 0 failures/errors. Record the exact test/assertion count in `STATUS.md` and `REVIEW_REQUEST.md`; do not pre-write a guessed count.

- [ ] **Step 5: Run repository contract and browser isolation verification**

From repository root:

```bash
python scripts/validate.py
cd packages/browser-runtime
npm ci
npm run typecheck
npm test
```

Expected: contract validator green, browser typecheck green, current browser test suite green. Do not modify browser-runtime production or lockfile to address unrelated advisories inside T-504.

- [ ] **Step 6: Review the full branch diff against the exact base**

```bash
git diff --stat 66f1d5db7e7902b6d7f09306be021119a6d96086...HEAD
git diff --name-only 66f1d5db7e7902b6d7f09306be021119a6d96086...HEAD
```

Required production diff boundary:

```text
packages/laravel/src/Filament/Confirmation/**
packages/laravel/src/Filament/Invocation/FilamentActionGateway.php
```

plus tests/docs/tracking only. If `spec/0.1/**`, `packages/browser-runtime/src/**`, `packages/laravel/src/Confirmation/**`, or `packages/laravel/src/Livewire/**` appears, stop and review scope before completion.

- [ ] **Step 7: Update authoritative tracking only after green verification**

`TASKS.md` must change T-504 from TODO to DONE only after all required tests are green and must record:

```text
Outcome: approval-only Filament modal bridge; explicit retry; no business redispatch
D-051: ACCEPTED
Exact implementation/review head
PHP tests/assertions
Browser tests
Contract validation
Known limits: no proof-of-human, no approver identity, no modal persistence, no generic approval endpoint
Next task: T-505 — Multi-tenant order operations demo — NOT STARTED
```

`STATUS.md` must name the branch/head, changed production files, verification evidence, known limitations, and review state.

`REVIEW_REQUEST.md` must focus reviewers on:

```text
caller challenge substitution
Livewire locked-property tampering
same/different challenge presentation races
unrelated mounted Filament actions
automatic redispatch / second execution path
receipt/token leakage
service binding/fallback behavior
fresh record/selection/filter scope after approval
idempotency interaction
optional Filament dependency boundary
```

- [ ] **Step 8: Run final validator after tracking edits**

```bash
python scripts/validate.py
```

Expected: PASS.

- [ ] **Step 9: Commit review-preparation state**

```bash
git add TASKS.md STATUS.md REVIEW_REQUEST.md packages/laravel/tests/Unit/FilamentDependencyPolicyTest.php
git commit -m "docs(review): prepare T-504 confirmation bridge review"
```

- [ ] **Step 10: Push exact head and require the full GitHub Actions matrix**

Push `feat/filament-confirmation-bridge`. Required CI result before review-ready claim:

```text
php-lint                                green
contract                                green
browser                                 green
PHP 8.3 / Illuminate 12 / Testbench 10 green
PHP 8.3 / Illuminate 13 / Testbench 11 green
PHP 8.4 / Illuminate 12 / Testbench 10 green
PHP 8.4 / Illuminate 13 / Testbench 11 green
```

For at least one PHP matrix job, inspect the decoded log and record the exact PHPUnit test/assertion count. Inspect browser logs and record the exact Vitest count.

- [ ] **Step 11: Open the review PR only after exact-head CI is green**

PR title:

```text
feat(filament): add approval-only confirmation bridge
```

PR summary must state that approval is not execution, the original caller performs explicit retry, all trusted context is freshly resolved on retry, no core confirmation/protocol/browser/Livewire production code changed, and merge remains a separate explicit user gate.

- [ ] **Step 12: Stop at external review gate**

Do not merge the PR and do not start T-505. T-504 reaches DONE/REVIEWED only after external review findings are verified/fixed and exact-head CI is green again. Merge remains an explicit separate user decision.

---

## Plan Self-Review Checklist

Before implementation starts, verify this document against the approved spec:

- [x] Every T-504 production edit is Filament-only except the existing gateway hook.
- [x] No task requires changes to T-401 confirmation core, T-402 idempotency core, Livewire production, browser production, or frozen `spec/0.1`.
- [x] Approval never redispatches or executes business code.
- [x] Explicit retry re-enters the normal ActionBus path.
- [x] Locked challenge/summary/expiry tamper resistance has a real Livewire test.
- [x] Direct mount/action arguments cannot select another challenge.
- [x] Same-challenge remount and different-challenge conflict are covered.
- [x] Unrelated mounted Filament action preservation is covered.
- [x] Explicit `ConfirmationService::class` binding is required; no fallback store is constructed.
- [x] Approval null/store failure cannot appear as success or disclose token state.
- [x] Cancel clears UI state without revoking/approving core state.
- [x] Fresh record, selection, applied-filter, actor, tenant, binding, surface, and input scope are covered after approval.
- [x] Same-key idempotency behavior is exercised without creating a second side effect.
- [x] Receipt/challenge secrets are excluded from notifications/events/logging/audit.
- [x] Human-presence and approver-identity non-guarantees remain explicit.
- [x] Final full matrix, diff-boundary review, tracking update, PR review gate, and no-auto-merge rule are explicit.
