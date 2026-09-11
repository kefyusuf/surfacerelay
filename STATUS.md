# Project Status

> Current repository state for implementation and review.

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/filament-order-operations-demo`
- **Stage:** M0 DONE; M1 DONE; M1.1 DONE/REVIEWED; M2 DONE/REVIEWED; M3 DONE/REVIEWED; M4 DONE/REVIEWED/MERGED; **M5 IN PROGRESS**
- **Last merged/revalidated task:** `T-504 — Confirmation bridge`
- **Current task:** `T-505 — Multi-tenant order operations demo`
- **T-505 status:** **DESIGN APPROVED / IMPLEMENTATION NOT STARTED**
- **Base:** `main@b5da05b4a975ff8b2779960ea94e0c786a9c01db`
- **Design spec:** `docs/superpowers/specs/2026-09-11-filament-multitenant-order-operations-demo-design.md`
- **Decision:** `D-052` — **PROPOSED pending executable verification**
- **Design spec commit:** `502b3916c124086089f2eb560a49f064cb00c65f`
- **Decision-register commit:** `3e978003e36a1bf1b2723fc80df9144f89e6ed31`
- **Implementation plan:** **NOT STARTED; requires post-spec review gate**
- **Production code changes:** **NONE for T-505 so far**
- **Previous T-504 merge commit:** `e42ca3ae1e8e41cbdd2ba6383e1f9d58af833115`
- **Previous T-504 post-merge main validation:** `34573128162` — **7/7 green**
- **Verified predecessor PHP baseline:** **578 tests / 3029 assertions** across PHP 8.3/8.4 × Illuminate 12/13 with MySQL 8.4 service coverage
- **Verified predecessor browser baseline:** TypeScript typecheck + **103/103 Vitest tests**
- **Verified predecessor contract baseline:** `python scripts/validate.py` green; frozen `spec/0.1/**` unchanged
- **Filament compatibility:** Filament **5.8.1** + Livewire **4.4.4**

## T-505 design gate

T-505 is approved as an executable Filament multi-tenant order reference vertical, not a new protocol/runtime mechanism.

The planned proof combines the existing T-501 through T-504 trust controls in one order workflow:

```text
trusted actor + trusted tenant
        │
        ▼
exact active Filament Page
        │
        ├── record page ─────► current_record
        ├── table page ──────► current_selection
        └── explicit exposure ► filament/active_filters
        │
        ▼
FilamentActionGateway
        │
        ▼
existing ActionBus
        │
        ├── authorization
        ├── idempotency
        ├── confirmation
        ├── execution
        ├── output policy
        └── structured audit
        │
        ▼
order operation
```

### Locked design boundaries

1. `orders.hold_current` proves trusted tenant + exact `current_record` mutation semantics.
2. `orders.refund_selected` proves trusted tenant + exact `current_selection` + explicitly exposed applied-filter context + confirmation + idempotency semantics.
3. Action input carries business intent only; tenant/order/selection/filter/confirmation identifiers are never authoritative input.
4. Filament tenant query scoping is defense-in-depth, not sufficient mutation authorization.
5. Every mutating operation separately verifies that the exact trusted current record or every exact selected order belongs to the trusted tenant before application execution.
6. Mixed/cross-tenant selection fails closed as a whole; there is no authorized-subset partial execution.
7. Applied filters remain the existing independent `filament/active_filters` trusted runtime extension; they are not folded into selection identity or promoted into `spec/0.1` vocabulary.
8. T-504 remains approval-only: modal approval never executes/redispatches the order action; the requesting caller retries normally.
9. Retry freshly resolves actor, tenant, record/selection, and applied filters before confirmation receipt consumption.
10. Tenant, selection, or applied-filter drift after approval invalidates the old scope and performs no side effect.
11. `orders.refund_selected` uses existing required-key idempotency; exact lost-response retry executes the external side effect once while changed intent conflicts.
12. Structured audit may persist only existing allowlisted action/outcome/provenance facts, never raw tenant/order-selection/filter/token/business-input values.
13. Human and agent invocation converge on the same explicitly exposed Livewire/Filament page method and the same ActionBus/application operation.
14. No agent-only endpoint, Filament RuntimeBinding driver, browser-runtime production change, or `spec/0.1` change is planned.
15. Production source under `packages/laravel/src/**` is not expected to change. If a missing runtime primitive is discovered, T-505 must stop and reopen the design gate before adding it.
16. D-052 remains `PROPOSED` until executable implementation and negative proofs pass; only then may it become `ACCEPTED`.

### Expected implementation surface after the next gate

```text
packages/laravel/tests/Fixtures/Filament/OrderDemo/**
packages/laravel/tests/Integration/FilamentMultiTenantOrderOperationsDemoTest.php
examples/filament-orders/README.md
docs/superpowers/plans/<T-505 implementation plan>.md
TASKS.md
STATUS.md
REVIEW_REQUEST.md
```

No implementation-plan or production-code work has started at this checkpoint.

## T-504 outcome

T-504 adds an approval-only Filament bridge for real T-401 confirmation challenges while preserving the existing ActionBus and Livewire RuntimeBinding execution path.

```text
normal SurfaceRelay invocation
        │
        ▼
ActionBus → confirmation_required + typed ConfirmationChallenge
        │
        ▼
optional FilamentConfirmationBridge
        │
        ▼
explicitly opted-in Filament Page
        │
        ▼
server-authored Livewire #[Locked] presentation state
        │
        ▼
Filament modal: Cancel / Approve
        │
        ├── Cancel  → clear disposable UI state only
        │
        └── Approve → ConfirmationService::approveChallenge(exact locked challenge)
                         │
                         └── NO business execution / NO redispatch
        │
        ▼
requesting caller explicitly retries original invocation
        │
        ▼
fresh actor / tenant / record / selection / filters / input / binding / surface resolution
        │
        ▼
ConfirmationStage consumes the exact-scope receipt once
```

## Locked T-504 invariants

1. Only an exact Confirmation-stage `confirmation_required` halt carrying a real typed `ConfirmationChallenge` is presentable.
2. Pages opt in explicitly with `InteractsWithSurfaceRelayConfirmation`.
3. Challenge ID, summary, and expiry are server-authored Livewire `#[Locked]` state; browser arguments cannot substitute approval authority.
4. One page hosts at most one outstanding SurfaceRelay confirmation presentation; conflicting challenge or unrelated mounted Action state fails closed.
5. Approve calls only the explicitly bound `ConfirmationService::approveChallenge()` for the locked challenge and never dispatches business execution.
6. Approval success emits a fixed success notice; null/non-pending approval emits a fixed generic retry-required notice. Neither exposes token/state detail.
7. Confirmation-store/infrastructure exceptions are translated at the Filament adapter boundary to fixed, non-chained `InvalidFilamentConfirmationBridge::approvalFailed()`; raw store/framework exception detail is not exposed to Livewire.
8. Infrastructure failure retains the locked presentation state and does not masquerade as approval success.
9. The host must wire the Filament resolver to the same authoritative confirmation service/store/configuration used by `ConfirmationStage`; a distinct-store mismatch is fail-closed and cannot cross-approve a stage-issued challenge.
10. Cancel clears only UI presentation state and leaves the core challenge pending/non-authoritative.
11. The original caller explicitly retries through the normal gateway/ActionBus path; retry freshly resolves actor, tenant, record, selection, applied filters, input, binding, and surface.
12. Wrong-scope attempts do not spend an otherwise-valid exact-scope receipt; exact-scope receipt consumption remains single-use.
13. Exact same idempotency key/intent retry executes once; completed lost-response retry replays without a duplicate side effect or second confirmation.
14. Static dependency policy forbids `ActionBus`, `ActionCall`, `FilamentActionGateway::dispatch`, and instance `->dispatch(` shortcuts in confirmation adapter source.
15. `spec/0.1/**`, Confirmation core, Idempotency core, Livewire production, and browser-runtime production remain unchanged.
16. Filament remains optional/dev-only and `SurfaceRelayServiceProvider` remains Filament-free.
17. T-504 is a trusted human-facing UI decision boundary, not proof-of-human, `approvedBy`, supervisor, or delegated approval semantics.

## Production change surface

```text
packages/laravel/src/Filament/Confirmation/
  FilamentConfirmationBridge.php
  InteractsWithSurfaceRelayConfirmation.php
  InvalidFilamentConfirmationBridge.php

packages/laravel/src/Filament/Invocation/
  FilamentActionGateway.php   # optional bridge hook only
```

No production changes were made under:

```text
spec/0.1/**
packages/browser-runtime/src/**
packages/laravel/src/Confirmation/**
packages/laravel/src/Idempotency/**
packages/laravel/src/Livewire/**
```

## TDD / verification and external-review evidence

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
Review hardening RED:           2dd660c7d43fef331f54f29fe1b8938449e8a7bd / 34571740035 — expected 1 error + 3 failures; 578 / 3020
Review hardening GREEN:         e0153e6de755963e8d7804cf83c60dd88eec3cd2 / 34571892139 — 7/7 green; 578 / 3029
Review hardening PR CI:         34571895353 — 7/7 green
Final feature head:             121f5c52b043dccfb5f9f24403aef50799c10518
Final feature-head push CI:     34572529686 — 7/7 green
Final PR CI:                    34572530074 — 7/7 green
Merge commit:                   e42ca3ae1e8e41cbdd2ba6383e1f9d58af833115
Post-merge main CI:             34573128162 — 7/7 green
PHP:                            578 tests / 3029 assertions
Browser:                        TypeScript typecheck + 103/103 Vitest
Contract / lint:                green
Open review threads:            0
```

CodeRabbit confirmed all four findings as addressed. The Major authority-wiring finding was closed with explicit existing same-authority configuration semantics plus a distinct-store integration test proving mismatched stores cannot cross-approve. The Major security finding was reproduced as raw store exception leakage and fixed by static non-chained adapter translation. The two Minor findings added fixed approval-result notifications and the missing instance-dispatch source guard.

The merge commit has parents `66f1d5db7e7902b6d7f09306be021119a6d96086` and exact final feature head `121f5c52b043dccfb5f9f24403aef50799c10518`. Post-merge validation checked out `main@e42ca3ae1e8e41cbdd2ba6383e1f9d58af833115` directly and passed all seven jobs.

## Known limitations / deliberate exclusions

- T-504 does not persist or recover approval modals across full navigation, browser refresh, component replacement, arbitrary pagination restoration, or multiple tabs.
- Premature caller retry is not an approval-status API and may produce a fresh challenge under existing T-401 semantics.
- T-504 does not add durable standalone approval-decision audit records; successful receipt consumption remains visible through the existing invocation/audit path.
- Strong proof-of-human, WebAuthn, step-up authentication, delegated/supervisor approval, and independent approver identity are separate future contracts.
- The browser workflow still reports two moderate npm dependency advisories during `npm ci`; T-504 does not modify browser-runtime source or lockfile, so that remains separate dependency-maintenance scope.

## Previous merged boundary

T-503 remains **DONE / REVIEWED / MERGED / MAIN REVALIDATED** on merge commit `7fe9db4f1e87257b83120396cc290b7253424ad4`, with post-merge validation `34492632652` green.

## Current boundary

T-504 is **DONE / REVIEWED / MERGED / MAIN REVALIDATED**. T-505 has passed its design gate only. Stop before implementation-plan/code work until the written T-505 design spec is reviewed as the next explicit gate.