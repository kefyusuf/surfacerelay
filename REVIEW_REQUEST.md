# External Review / Merge Record — T-504 Filament Confirmation Bridge

## Current status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Scope:** `T-504 — Confirmation bridge`
- **Feature branch:** `feat/filament-confirmation-bridge`
- **Pull request:** `#8` — **OPEN / MERGEABLE / UNMERGED**
- **Base / merge-base:** `main@66f1d5db7e7902b6d7f09306be021119a6d96086`
- **Reviewed code head before tracking closure:** `e0153e6de755963e8d7804cf83c60dd88eec3cd2`
- **PHP:** **578 tests / 3029 assertions** across PHP 8.3/8.4 × Illuminate 12/13 with MySQL 8.4
- **Browser:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract:** `python scripts/validate.py` green; frozen `spec/0.1/**` unchanged
- **Filament / Livewire:** **5.8.1 / 4.4.4**
- **Decision:** `D-051` — **ACCEPTED**
- **CodeRabbit review:** `b6c519df-3a00-4d4b-b4db-994240edffe6`
- **External review result:** **PASSED after TDD-first hardening**
- **Actionable findings:** **2 Major + 2 Minor; all addressed and confirmed**
- **Unresolved review threads:** **0**
- **Merge state:** **NOT AUTHORIZED; separate explicit gate required**
- **Next task:** `T-505 — Multi-tenant order operations demo` — **NOT STARTED**

## Final reviewed behavior

T-504 implements a narrow approval-only Filament bridge for T-401 confirmation challenges.

```text
ActionBus
   │
   └── confirmation_required + typed ConfirmationChallenge
                 │
                 ▼
      FilamentConfirmationBridge
                 │
                 ▼
      opted-in Filament Page
                 │
                 ▼
      Livewire #[Locked] state
                 │
                 ▼
          [Cancel] [Approve]
                 │
        Approve only changes
        pending → approved
                 │
                 ▼
 caller explicitly retries normal action
                 │
                 ▼
 fresh scope resolution + receipt consumption
                 │
                 ▼
       existing business execution path
```

Approve never executes or redispatches the business action. The requesting caller must retry through the existing `FilamentActionGateway → ActionBus` path with the original opaque challenge token as `confirmationReceipt`; trusted context and invocation intent are freshly resolved before receipt consumption.

## Production files

```text
packages/laravel/src/Filament/Confirmation/FilamentConfirmationBridge.php
packages/laravel/src/Filament/Confirmation/InteractsWithSurfaceRelayConfirmation.php
packages/laravel/src/Filament/Confirmation/InvalidFilamentConfirmationBridge.php
packages/laravel/src/Filament/Invocation/FilamentActionGateway.php
```

Production code under these areas remains unchanged:

```text
spec/0.1/**
packages/browser-runtime/src/**
packages/laravel/src/Confirmation/**
packages/laravel/src/Idempotency/**
packages/laravel/src/Livewire/**
```

## External review findings and closure

### Major — split confirmation authority — ADDRESSED / CONFIRMED

`ConfirmationStage` uses its constructor-injected `ConfirmationService`, while the Filament approval trait resolves `ConfirmationService::class` from the container. The design already requires the host to bind the same service instance or an equivalent service backed by the same authoritative `ConfirmationStore` and expiry configuration.

Rather than introducing private store-identity introspection or changing the T-401 core contract, review hardening added an explicit distinct-store integration test. A challenge issued into store A cannot be approved through container-bound store B: store A remains `Pending`, store B has no token record, no approval authority is granted, the modal state is cleared, and only the generic retry-required warning is shown. CodeRabbit verified this fail-closed proof and resolved the thread.

### Major — confirmation-store exception leakage — FIXED / CONFIRMED

The original approval action allowed infrastructure `Throwable` from `ConfirmationService::approveChallenge()` to reach Livewire, potentially carrying chained store/framework details.

The RED review test reproduced the leak as `RuntimeException: confirmation test store unavailable`. Production now catches `Throwable` only around `approveChallenge()` and converts it to fixed non-chained `InvalidFilamentConfirmationBridge::approvalFailed()` with message `Filament confirmation approval failed.`. Tests assert `getPrevious() === null`, raw store text is absent, the core challenge remains `Pending`, and presentation state remains available after infrastructure failure. CodeRabbit confirmed the fix and resolved the thread.

### Minor — missing approval-result notifications — FIXED / CONFIRMED

Approval success now sends the fixed success notice:

```text
Confirmation approved. Retry the original operation.
```

A null/non-pending approval result sends the fixed generic warning:

```text
Confirmation is no longer approvable. Retry the original operation.
```

Neither notice includes the challenge ID, token state, scope material, or business/trusted-context values. Integration tests cover both branches. CodeRabbit confirmed the fix and resolved the thread.

### Minor — incomplete redispatch source guard — FIXED / CONFIRMED

The static confirmation-adapter policy now forbids literal instance dispatch calls `->dispatch(` in addition to `FilamentActionGateway::dispatch`, `ActionBus`, and `ActionCall`, closing the source-guard gap without changing production behavior. CodeRabbit confirmed and resolved the thread.

## Verification evidence

```text
Design / D-051 checkpoint:      a3e60544308ea5f3072d5fb813e939bfe4924def
Plan checkpoint:                46426cbccc1258664ebe6e3a5177cb841582732e / 34533606536 — 7/7 green
Task 1 RED:                     2df61dfa8c079c9f97b9d90c4c7ec096374910ba / 34559167747
Task 1 GREEN:                   8370402eb8d752eae4c329523e584820c28341c3 / 34559287792 — 7/7 green
Task 2 RED:                     9563b6108066cdde9396b8bfd044e9c2669604a2 / 34559561986
Task 2 final GREEN:             6fbaba5e6215e723d921618072b44931c7ba0264 / 34560122329 — 7/7 green
Task 3 RED:                     1f0f75f316955e03c6aaf61a9ddb3f8c6b062fa2 / 34560370550
Task 3 GREEN:                   f1bea851269cbae932b792d26c2f7731b303aebc / 34560453025 — 7/7 green
Task 4 RED:                     f9822e4e725eb0997633557383c1902adae3c8c5 / 34560647891
Task 4 GREEN:                   babc80bb30426748765fc2dc79c095ab5ae4967b / 34560733159 — 7/7 green
E2E initial proof:              acd4e7fb5129696a6e863e8d1a276ec8a4cfe9ca / 34561151879 — harness-only selection-cache failure
E2E GREEN:                      f83db1a0e54760c2b4bfb97992bdfb93b7610c90 / 34561337612 — 7/7 green
Boundary / review-prep:         e7954abe7a696c7a05ed32a6995fb3b7ae98400c / 34561470349 — 7/7 green
Initial feature head:           17c5ac6cb135ab9494dd1eb906752ef24ea59ec8 / 34561985867 — 7/7 green
Initial PR validation:          34562182120 — 7/7 green
CodeRabbit full review:         b6c519df-3a00-4d4b-b4db-994240edffe6 — 2 Major + 2 Minor
Review hardening RED:           2dd660c7d43fef331f54f29fe1b8938449e8a7bd / 34571740035 — 1 expected error + 3 expected failures; 578 / 3020
Review hardening GREEN:         e0153e6de755963e8d7804cf83c60dd88eec3cd2 / 34571892139 — 7/7 green; 578 / 3029
Review hardening PR CI:         34571895353 — 7/7 green
Browser:                        TypeScript typecheck + 103/103 Vitest
Contract / lint:                green
Unresolved review threads:      0
```

## Reviewed trust boundary

1. Only a real typed Confirmation-stage `confirmation_required` halt may be presented.
2. Challenge presentation is server-authored `#[Locked]` state; browser arguments cannot choose another challenge.
3. Approve calls only the locked challenge's `ConfirmationService::approveChallenge()` and never executes business code.
4. Success/null UI notices are fixed and non-secret.
5. Infrastructure approval failures are fixed, non-chained adapter errors; raw store/framework details do not reach Livewire.
6. Host configuration must use the same authoritative confirmation service/store/configuration for stage and Filament approval; mismatches fail closed.
7. Cancel clears only disposable UI state; it does not change core confirmation state.
8. Explicit retry freshly resolves actor, tenant, record, selection, applied filters, input, binding, and surface before exact-scope receipt consumption.
9. Wrong-scope attempts do not spend an otherwise-valid receipt; exact receipt is single-use.
10. Confirmation adapter source contains no ActionBus/ActionCall/gateway/instance redispatch shortcut.
11. Filament remains optional/dev-only and the base service provider stays Filament-free.
12. No new proof-of-human, approver identity, delegation, or standalone approval audit semantics are claimed.

## Merge gate

T-504 external review is **PASSED**. PR #8 is intentionally left open and unmerged. A successful review does not authorize merge. Do not start T-505 until the separate T-504 merge/closure gate is explicitly authorized.