# T-504 Filament Confirmation Bridge Design

## Status

- Task: `T-504 — Confirmation bridge`
- Milestone: `M5 — Filament Vertical`
- Base: `main@66f1d5db7e7902b6d7f09306be021119a6d96086`
- Branch: `feat/filament-confirmation-bridge`
- Predecessors: T-401 confirmation, T-402 idempotency, T-501 current-record, T-502 current-selection, and T-503 active-filter context are DONE / REVIEWED / MERGED / main-revalidated
- Design state: approved in chat; written-spec review required before implementation planning
- Protocol impact: none; `spec/0.1/**` remains frozen
- Execution-driver impact: none; the existing ActionBus + Livewire RuntimeBinding path remains the only business-action execution path

## Goal

Provide the first trusted human-facing approval bridge for SurfaceRelay consequential actions inside a Filament page without weakening T-401's single-use confirmation capability model.

When an invocation halts with a real typed `ConfirmationChallenge`, an explicitly configured Filament bridge may present that exact challenge in a Filament modal. A human-facing Filament action may approve only that exact pending challenge. Approval never executes, redispatches, reconstructs, or mutates the business invocation. The original requesting caller must explicitly retry the original action with the original `challengeId` supplied as `confirmationReceipt`, causing validation, authorization, idempotency preflight, and trusted Filament context to be freshly resolved before the receipt can be consumed.

The bridge must not create a machine-callable approval endpoint, accept caller-supplied challenge IDs, infer authority from modal state, or turn Filament into a second RuntimeBinding driver.

## Chosen Model: Approval-Only Modal + Explicit Retry

T-504 adopts an approval-only, explicit-retry model.

```text
requesting caller
      │
      │ normal SurfaceRelay invocation
      ▼
FilamentActionGateway
      │
      ▼
ActionBus
      │
      ▼
ConfirmationStage
      │
      └── confirmation_required + typed ConfirmationChallenge
                  │
                  ▼
       FilamentConfirmationBridge
                  │
                  ▼
       exact active Filament Page
                  │
                  ▼
      SurfaceRelay confirmation modal
          ┌─────────────────┐
          │ Cancel          │
          │ Approve         │
          └───────┬─────────┘
                  │
                  │ server-side human-facing action
                  ▼
       ConfirmationService::approveChallenge()
                  │
                  │ pending → approved
                  │ no business execution
                  ▼
             modal closes
                  │
                  ▼
requesting caller explicitly retries
confirmationReceipt = original challengeId
                  │
                  ▼
fresh trusted context resolution
                  │
                  ▼
ConfirmationStage::consumeReceipt()
            ┌─────┴─────┐
            │ exact     │ changed/stale
            │ scope     │ scope
            ▼           ▼
        execute      new challenge
```

This preserves the central T-401 invariant: approval changes only one server-side confirmation record from `pending` to `approved`. Execution authority is materialized only later when the normal ConfirmationStage atomically consumes the exact-scope approved receipt.

### Rejected alternative: modal approval + automatic redispatch

The bridge will not automatically redispatch the business action after approval. Automatic redispatch would require the bridge to retain or reconstruct action ID/version, validated input, binding ID, idempotency key, surface, and trusted context across requests. That would create a second invocation owner, duplicate stale-state logic, and weaken the guarantee that retry uses freshly resolved trusted Filament state.

### Rejected alternative: generic approval endpoint or SurfaceRelay action

T-504 will not expose approval as an ActionDefinition, WebMCP tool, generic HTTP endpoint, browser-driver method, or arbitrary Livewire method that accepts a challenge token. T-401 intentionally reserves approval for trusted human-facing bridge code. A caller that knows a challenge ID must not gain a direct machine approval primitive.

## Existing Core Contract

T-504 consumes T-401 exactly as implemented:

- `ConfirmationChallenge` is the typed public data object containing `challengeId`, static `summary`, and optional `expiresAt`.
- `ConfirmationService::approveChallenge(string $challengeId): ?string` approves only the exact server-side pending record addressed by the opaque token and returns the same token on success.
- the token is not execution authority while pending;
- after approval, the same opaque token is the candidate receipt;
- approval cannot supply or rewrite scope, action, actor, tenant, record, selection, active filters, binding, surface, or validated input;
- only `ConfirmationStage::consumeReceipt()` can materialize trusted `human_confirmation` authority;
- receipt consumption remains single-use and exact-scope;
- scope mismatch does not spend an otherwise-valid receipt;
- T-402 idempotency replay semantics remain unchanged.

T-504 should not modify `ConfirmationService`, `ConfirmationStore`, confirmation scope hashing, idempotency hashing, or frozen protocol schemas. If implementation discovers an unavoidable core-contract contradiction, stop and return to architecture review rather than widening T-401 silently.

## Typed Outcome Recognition

`FilamentConfirmationBridge` receives the exact `ActionPipelineOutcome` returned by `FilamentActionGateway` after ActionBus dispatch.

The bridge recognizes a presentable confirmation only when all of the following hold:

```text
outcome.completed == false
outcome.haltedAt == ActionPipelineStage::Confirmation
outcome.halt.code == CoreActionErrorCode::CONFIRMATION_REQUIRED
outcome.halt.confirmation instanceof ConfirmationChallenge
```

A non-confirmation outcome is a no-op for the bridge.

A confirmation-coded halt that lacks the typed `ConfirmationChallenge`, has the wrong stage, or is otherwise structurally inconsistent is a runtime programming/configuration failure and fails closed. The bridge never fabricates a challenge from `halt.details`, caller metadata, ActionResult arrays, exceptions, or arbitrary payloads.

## Filament Adapter Components

### `FilamentConfirmationBridge`

New adapter component under `packages/laravel/src/Filament/Confirmation/`.

Conceptual API:

```php
final readonly class FilamentConfirmationBridge
{
    public function presentIfRequired(
        Page $page,
        ActionPipelineOutcome $outcome,
    ): void;
}
```

Responsibilities:

1. no-op for completed/non-confirmation outcomes;
2. validate the exact typed confirmation halt shape;
3. require the target page to opt into `InteractsWithSurfaceRelayConfirmation`;
4. reject conflicting existing SurfaceRelay confirmation presentation state;
5. copy the exact challenge ID/summary/expiry into server-authored Livewire-locked page properties;
6. mount only the reserved SurfaceRelay Filament confirmation action through Filament's supported public Action API;
7. never call `approveChallenge()` itself during presentation;
8. never execute or redispatch the business action;
9. never return or persist a receipt;
10. never read Filament private properties or use reflection/private request internals.

The bridge may use Filament's public `mountAction()` / mounted-action accessors to operate the UI lifecycle. Direct reads/writes of Filament's raw `$mountedActions` property are forbidden in SurfaceRelay production code.

### `InteractsWithSurfaceRelayConfirmation`

An explicit opt-in trait for Filament resource pages.

A host page uses:

```php
use InteractsWithSurfaceRelayConfirmation;
```

The trait owns only the SurfaceRelay approval presentation state and internal Filament Action definition. It does not own application business actions, ActionBus composition, record/selection/filter context, authorization policies, or RuntimeBindings.

The trait defines three nullable scalar public properties that must be protected by Livewire `#[Locked]`:

```text
surfaceRelayConfirmationChallengeId : ?string
surfaceRelayConfirmationSummary     : ?string
surfaceRelayConfirmationExpiresAt   : ?string
```

These are presentation state, not confirmation authority. They are public only because Livewire must persist them across component requests. `#[Locked]` prevents client-side mutation while allowing trusted server-side PHP assignment.

The challenge ID is already public in the T-401 `confirmation_required` result while pending, but it becomes a bearer capability after approval. The trait must therefore never log it, include it in notifications, audit metadata, exceptions, or arbitrary browser events.

## Internal Filament Action

The trait provides one reserved internal Filament Action, conceptually named:

```text
surfacerelay_confirmation
```

It is registered through Filament's supported Action/trait discovery lifecycle and is mounted programmatically only after a real typed T-401 challenge is presented.

The modal is intentionally narrow:

```text
heading:      Confirm action
summary:      exact server-issued ConfirmationChallenge.summary
expires:      optional display-only ConfirmationChallenge.expiresAt
approve:      Approve
cancel:       Cancel
```

The summary is the static application-authored T-401 summary. T-504 does not interpolate raw invocation input, record fields, selected values, filters, SQL, exception text, or arbitrary untrusted application content into the approval copy.

The modal must disable ambiguous client-only dismissal paths where supported by public Filament APIs:

- close by clicking outside: disabled;
- close by escape: disabled;
- top-right close control: disabled.

The only intended server-recorded UI decisions are Approve and Cancel.

## Approval Action

The Approve control accepts no action arguments and no challenge token from the browser.

Its server-side behavior is:

```text
read locked challengeId from current component state
        │
        ├── missing/invalid presentation state → fail closed / no approval
        │
        ▼
resolve explicitly configured ConfirmationService
        │
        ▼
approveChallenge(locked challengeId)
        │
        ├── success → clear presentation state; static success notice; close modal
        │
        └── null    → clear stale presentation state; generic retry-required notice; close modal
```

`approveChallenge()` returning `null` is deliberately not turned into token-state introspection. Expired, unknown, already-approved, or otherwise non-pending state is not distinguished to the browser. The UI may say only that the confirmation is no longer approvable and the requesting operation must be retried.

If the configured confirmation authority throws because its store/locking/configuration is unavailable, the failure propagates fail-closed. T-504 must not downgrade store failure into apparent approval.

Approval success does not expose the returned token because the requesting caller already owns the original challenge ID and the browser modal does not need a second receipt-delivery channel.

Most importantly, approval never calls `FilamentActionGateway::dispatch()`, ActionBus, a RuntimeBinding, application mutation code, or a second execution callback.

## Confirmation Authority Wiring

T-504 does not make the base `SurfaceRelayServiceProvider` auto-create confirmation authority or eagerly register Filament services.

The host Laravel application must explicitly make the same configured confirmation authority used by `ConfirmationStage` resolvable under `ConfirmationService::class` for the Filament approval action. In normal composition this is the same service instance or an equivalent instance backed by the same authoritative `ConfirmationStore` and configuration.

The trait may obtain the service only through a protected server-side resolver method / explicit Laravel container resolution. No browser argument selects a store, service, cache connection, tenant, or approval backend.

A missing or invalid binding is a configuration failure. T-504 never constructs an in-memory or unlocked fallback confirmation store.

The base package must remain installable without Filament. `filament/filament:^5.0` remains an optional/dev integration dependency, and `SurfaceRelayServiceProvider` must remain free of eager `Filament\...` references.

## Gateway Integration

`FilamentActionGateway` remains the only Filament-side entry into the existing ActionBus.

It may receive an optional `FilamentConfirmationBridge` as the final constructor dependency to preserve existing call sites:

```php
public function __construct(
    ActionBus $bus,
    TrustedContextComposer $baseComposer,
    int $maxSelectionRecords = ...,
    ?FilamentConfirmationBridge $confirmationBridge = null,
)
```

Dispatch remains:

```text
resolve exact trusted Filament context
        │
        ▼
ActionBus::dispatch(ActionCall)
        │
        ▼
obtain ActionPipelineOutcome
        │
        ├── configured bridge + real confirmation halt
        │       └── present approval modal only
        │
        └── otherwise no bridge behavior
        │
        ▼
return the original ActionPipelineOutcome unchanged
```

No bridge configured means T-501/T-502/T-503 behavior is unchanged.

The bridge must never normalize and rewrite the ActionPipelineOutcome. The original caller still receives the canonical confirmation-required result through the existing result-normalization path and therefore retains the exact challenge ID needed for an eventual explicit retry.

## Fresh-State Retry Guarantee

The reason T-504 forbids automatic redispatch is to preserve fresh trusted authority resolution.

After human approval the requesting caller must retry the original SurfaceRelay invocation with:

```text
actionId               = original action
actionVersion          = original version
input                   = original intended input
bindingId               = original binding reference
surface                 = original surface
idempotencyKey          = original retry key when applicable
confirmationReceipt     = original challengeId
```

The retry goes through the normal gateway and ActionBus again.

Therefore current record, current selection, active filters, actor, tenant, browser session, validated input, binding, and surface are not trusted because the modal remembers them. They are re-derived and re-hashed by the existing runtime.

Examples:

- approval for selection `[10, 11]` cannot execute against later selection `[15]`;
- approval for `filament/active_filters = status:pending` cannot execute after applied filters change to `status:cancelled`;
- approval for one current record cannot execute after navigation to another record;
- approval for one actor/tenant/binding/surface cannot execute under another exact scope.

A scope mismatch returns/produces the normal T-401 confirmation-required behavior and does not spend the still-valid old receipt. T-504 adds no special stale-state bypass.

## Presentation Concurrency

One Filament Page may host at most one outstanding SurfaceRelay confirmation presentation at a time.

Rules:

```text
no SurfaceRelay presentation + incoming A
    → store A in locked state and mount modal

existing A + incoming A
    → idempotent; do not replace locked state

existing A + incoming B
    → fail closed with presentation conflict
```

The bridge must not silently overwrite A with B while the human is reading A.

The bridge should also avoid replacing or hijacking an unrelated mounted Filament application action. If public Filament mounted-action inspection shows another non-SurfaceRelay action is active, presentation fails closed rather than force-unmounting, nesting unexpectedly, or replacing application UI state.

Presentation conflict is not business-action failure: the business action has already halted at confirmation before execution. The adapter failure remains static-safe and contains no challenge IDs or business payloads.

## Cancel Semantics

Cancel is a server-side UI decision that clears the trait's locked presentation state and closes the modal.

Cancel does not:

- approve the challenge;
- consume the challenge;
- revoke the challenge;
- execute or redispatch the business action;
- change confirmation TTL;
- create a receipt;
- create a new challenge.

T-401 has no reject/revoke state transition. The unapproved pending challenge simply remains non-authoritative until its normal challenge TTL expires. Adding revocation is outside T-504.

## Navigation, Refresh, and Lost UI State

The server-side `ConfirmationStore` is the authority. Filament modal/presentation state is disposable UI state.

A full page navigation, component replacement, browser refresh, or other lifecycle event may lose the displayed modal/presentation state. Losing UI state never approves or consumes a challenge. The requesting caller may later retry and receive a new challenge if necessary.

T-504 does not attempt to persist/recover approval modals across arbitrary Filament navigation, pagination, browser restoration, or multiple tabs. General page-state rebinding remains a separate portability/lifecycle concern.

## Human-Presence Boundary

T-504 provides a trusted human-facing Filament approval surface, not cryptographic proof that a biological human physically clicked a button.

The package can guarantee:

- the approval primitive is not a SurfaceRelay ActionDefinition/WebMCP tool/generic approval endpoint;
- the challenge presented to the approval action was written by trusted server-side bridge code into Livewire-locked component state;
- browser arguments cannot substitute another challenge ID;
- the server-side approval call operates on that locked exact challenge record;
- business execution still requires later exact-scope receipt consumption.

The package cannot guarantee that browser automation, accessibility tooling, remote-control software, or a compromised authenticated browser session did not activate the same UI control. Strong physical-human, second-person, step-up-authentication, WebAuthn, or out-of-band approval would require a separate future contract and is not claimed by T-504.

T-504 also does not introduce supervisor/delegated approval semantics. The host Filament application's existing authentication/access boundary decides who can reach the page. Cross-user delegated approval requires separate explicit policy rather than being inferred from possession of a challenge ID.

## Caller Spoofing Boundaries

The following must never approve a challenge:

- action input such as `confirmed=true`;
- generic InvocationContext metadata;
- WebMCP arguments;
- query/route/request values;
- Filament action arguments containing a challenge ID;
- arbitrary Livewire property updates;
- direct construction of `ConfirmationChallenge`;
- direct mounting of the internal Filament action when no server-authored locked challenge is present;
- a different challenge ID supplied by the caller;
- a stale/expired/already-non-pending presentation;
- a prebuilt `human_confirmation` trusted entry.

Livewire `#[Locked]` is part of the T-504 client-tampering boundary. Attempts to mutate locked confirmation properties from the browser must fail before the approval action executes.

## Security / Error Behavior

New adapter failures should be static-safe, non-chained where underlying framework/store messages could leak sensitive state, and must never include raw challenge/receipt tokens, token hashes, scope fingerprints, business input, trusted identity values, filter values, or record identifiers.

Representative internal categories:

```text
filament_confirmation_host_unavailable
filament_confirmation_presentation_conflict
filament_confirmation_presentation_failed
filament_confirmation_service_unavailable
```

These are adapter/runtime failure categories, not new frozen ActionError protocol requirements.

Ordinary `ConfirmationService::approveChallenge()` returning `null` is handled as generic non-approval UI state, not an exception/token oracle.

## Audit / Logging Boundary

T-504 does not add a second standalone approval-event audit subsystem.

Existing T-404 final dispatch audit remains authoritative for actual SurfaceRelay invocations. A later successful retry that consumes confirmation carries the existing runtime-owned `human_confirmation` provenance into the invocation/audit path according to current policy.

The Filament bridge itself must not persist:

- raw challenge IDs / receipts;
- token hashes;
- confirmation scope hashes;
- locked modal state;
- business input;
- trusted actor/tenant/record/selection/filter values.

A future requirement for durable standalone approval-decision auditing must be designed explicitly rather than overloading ActionBus audit records.

## Compatibility / Boundaries

T-504 must preserve all of the following:

1. frozen `spec/0.1/**` schemas and fixtures;
2. T-401 single-use opaque capability semantics;
3. T-402 idempotency ordering and replay semantics;
4. T-501 exact `current_record` authority;
5. T-502 bounded exact `current_selection` authority;
6. T-503 exact applied `filament/active_filters` trusted extension authority;
7. ActionDefinition protocol neutrality;
8. current WebMCP projection semantics;
9. existing browser runtime source behavior;
10. existing Livewire RuntimeBinding driver behavior;
11. no Filament RuntimeBinding driver;
12. no agent-only business endpoint;
13. no generic confirmation approval endpoint/tool;
14. package installability when Filament is absent;
15. no eager Filament registration in the base `SurfaceRelayServiceProvider`.

Production changes should remain confined to `packages/laravel/src/Filament/**` plus the minimal optional `FilamentActionGateway` bridge hook. `packages/laravel/src/Confirmation/**`, `packages/laravel/src/Livewire/**`, `packages/browser-runtime/src/**`, and `spec/0.1/**` are expected to remain unchanged.

## Expected Files

Likely production files:

```text
packages/laravel/src/Filament/Confirmation/FilamentConfirmationBridge.php
packages/laravel/src/Filament/Confirmation/InteractsWithSurfaceRelayConfirmation.php
packages/laravel/src/Filament/Confirmation/InvalidFilamentConfirmationBridge.php
packages/laravel/src/Filament/Invocation/FilamentActionGateway.php
```

Likely tests/fixtures:

```text
packages/laravel/tests/Fixtures/Filament/...
packages/laravel/tests/Unit/FilamentConfirmationBridgeTest.php
packages/laravel/tests/Integration/FilamentConfirmationBridgeIntegrationTest.php
packages/laravel/tests/Integration/FilamentConfirmationTrustControlsIntegrationTest.php
packages/laravel/tests/Unit/FilamentDependencyPolicyTest.php
```

Exact file decomposition may be refined in the implementation plan without changing the architectural boundaries above.

## Required TDD / Integration Proofs

At minimum implementation must prove:

1. non-confirmation outcomes do not mutate page state or mount a SurfaceRelay modal;
2. only an exact confirmation-stage `confirmation_required` halt carrying a typed `ConfirmationChallenge` is presentable;
3. malformed confirmation halts fail closed and never fabricate a challenge;
4. a page without explicit confirmation trait opt-in fails closed when a configured bridge must present a challenge;
5. presentation copies the exact challenge fields into Livewire `#[Locked]` state;
6. browser tampering with challenge ID/summary/expiry is rejected and cannot call approval with substituted state;
7. direct mounting/calling the internal approval action without server-authored locked challenge state grants no authority;
8. same-challenge re-presentation is idempotent;
9. different-challenge overwrite while A is pending fails closed;
10. unrelated mounted Filament actions are not force-unmounted/replaced;
11. Approve calls the configured `ConfirmationService` with exactly the locked challenge ID and accepts no browser challenge argument;
12. approval success does not execute application business code;
13. approval success does not expose a raw receipt through notification, metadata, audit, event payload, or exception;
14. approval `null` is handled generically without revealing token state;
15. confirmation-store/configuration failure fails closed and cannot appear as approval success;
16. Cancel clears presentation state but leaves the core pending challenge unapproved/non-authoritative;
17. first consequential invocation executes zero application side effects and presents a real challenge;
18. human approval alone still executes zero application side effects;
19. explicit retry with the original token and unchanged exact scope executes exactly once;
20. replay of the consumed receipt does not execute again;
21. changed current record after approval fails the old receipt;
22. changed current selection after approval fails the old receipt;
23. changed applied active-filter state after approval fails the old receipt;
24. changed actor/tenant/binding/surface/input follows existing exact-scope failure semantics;
25. same idempotency key + exact intent works with the explicit post-approval retry path and does not duplicate side effects;
26. bridge-disabled gateway behavior remains backward-compatible;
27. `spec/0.1/**`, browser-runtime production, Livewire production, and confirmation-core production remain unchanged;
28. Filament remains a dev/optional dependency and the base ServiceProvider contains no Filament references;
29. full PHP/Illuminate matrix, browser typecheck/tests, contract validator, and PHP lint remain green.

## Decision D-051

T-504 accepts the following architecture decision:

> Filament confirmation is an approval-only, explicit-retry bridge. A Filament modal may approve only the exact runtime-issued T-401 challenge held in server-authored Livewire-locked page state. Approval never executes or redispatches the business action. The original opaque challenge token becomes the receipt after server-side approval, and the requesting caller must retry through the normal ActionBus path so all trusted context and invocation intent are freshly resolved and re-bound before receipt consumption.

## Implementation Gate

No production implementation starts until this written design is reviewed and explicitly approved.

After written-spec approval, create the T-504 implementation plan and execute strict RED → GREEN TDD. Any discovery that requires changing T-401 core confirmation semantics, frozen `spec/0.1`, browser runtime, Livewire RuntimeBinding, or adding a generic machine approval API returns to architecture review before code changes.
