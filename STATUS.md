# Project Status

> Current repository state for implementation, review, and merge readiness.

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/filament-order-operations-demo`
- **Stage:** M0 DONE; M1 DONE; M1.1 DONE/REVIEWED; M2 DONE/REVIEWED; M3 DONE/REVIEWED; M4 DONE/REVIEWED/MERGED; **M5 IN PROGRESS pending T-505 merge**
- **Last merged/revalidated task:** `T-504 — Confirmation bridge`
- **Current task:** `T-505 — Multi-tenant order operations demo`
- **Pull request:** `#9` — **OPEN / EXTERNAL REVIEW HARDENING**
- **Base / merge-base:** `main@b5da05b4a975ff8b2779960ea94e0c786a9c01db`
- **Current verified code head:** `991a108c6f1226860f671029c509b4c9edb09a97`
- **Decision:** `D-052` — **ACCEPTED**
- **Production runtime changes:** **NONE**
- **Frozen contract changes:** **NONE**
- **PHP baseline:** **595 tests / 3164 assertions** across PHP 8.3/8.4 × Illuminate 12/13 with MySQL 8.4
- **Browser baseline:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract / lint / Composer validation:** green
- **Filament / Livewire:** **5.8.1 / 4.4.4**

## T-505 outcome

T-505 is an executable Filament multi-tenant order reference vertical. It composes the existing T-501 through T-504 trusted-context controls with the M4 authorization, idempotency, confirmation, output-policy, and structured-audit pipeline.

```text
trusted actor + trusted tenant
        │
        ▼
exact active Filament Page
        │
        ├── current_record
        ├── current_selection
        └── filament/active_filters
        │
        ▼
FilamentActionGateway
        │
        ▼
existing ActionBus
        │
        ├── validation
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

The vertical introduces no second RuntimeBinding driver, no agent-only order API, and no new protocol/runtime primitive.

## Locked T-505 invariants

1. `orders.hold_current` accepts business intent only; the authoritative target is trusted `current_record`.
2. `orders.refund_selected` accepts business intent only; targets are trusted `current_selection`; applied filters remain the independent `filament/active_filters` trusted extension.
3. Caller input/metadata cannot manufacture tenant, current record, selection, filters, confirmation, binding, or target IDs.
4. Filament tenant query scoping is defense-in-depth rather than mutation authorization.
5. Every mutation re-authorizes exact trusted targets against the trusted tenant before execution.
6. Mixed-tenant selection fails atomically; unauthorized records are never silently removed.
7. A null authentication identifier is treated as absent trusted actor context; the refund Gate also carries the same null-ID rejection as defense-in-depth.
8. Consequential refunds remain approval-only at the UI decision boundary; approval alone produces zero order side effects.
9. Retry freshly resolves tenant, selection, and applied-filter authority before receipt consumption.
10. Selection, tenant, or applied-filter drift does not spend an otherwise-valid exact-scope receipt.
11. Required-key idempotency executes a confirmed refund once and replays an exact completed retry without a second executor call.
12. Changed validated input, selection, or applied-filter intent cannot replay a completed result under the same key; tenant changes use the existing authority partition.
13. Human and agent paths converge on the same explicitly exposed Filament/Livewire page methods and gateway/ActionBus/application executor.
14. `ListOrders::refundSelected(string $reason)` is intentionally an **initial-invocation-only** convenience seam. It exposes only business input; `confirmationReceipt` and `idempotencyKey` remain invocation-envelope candidates and are not Livewire call-plan authority.
15. Exact approved receipt retry and idempotency replay/conflict semantics are proven at the production `FilamentActionGateway` seam in the Task-3 integration tests, not by adding envelope fields to the exposed page method.
16. Livewire binding production remains `driver=livewire`; no `filament` RuntimeBinding driver exists.
17. Structured audit uses the existing D-047 migration and `DatabaseAuditEventStore` and excludes raw tenant/record/filter/business/idempotency/receipt marker material.
18. Demo correlation IDs are internal sequence values and are not target/business/idempotency-derived.
19. `packages/laravel/src/**`, `packages/browser-runtime/src/**`, and `spec/0.1/**` remain unchanged.

## Implementation / tracking surface

```text
packages/laravel/tests/Fixtures/Filament/OrderDemo/**
packages/laravel/tests/Fixtures/views/filament-order-demo-page.blade.php
packages/laravel/tests/Support/FilamentOrderDemoHarness.php
packages/laravel/tests/Integration/FilamentMultiTenantOrderOperationsDemoTest.php
packages/laravel/tests/Integration/FilamentOrderDemoLivewireBindingTest.php
packages/laravel/tests/Integration/FilamentOrderDemoReviewHardeningTest.php
examples/filament-orders/README.md
docs/superpowers/specs/2026-09-11-filament-multitenant-order-operations-demo-design.md
docs/superpowers/plans/2026-09-11-filament-multitenant-order-operations-demo.md
docs/DECISION-REGISTER.md
TASKS.md
STATUS.md
REVIEW_REQUEST.md
```

No T-505 change is permitted under:

```text
packages/laravel/src/**
packages/browser-runtime/src/**
spec/0.1/**
```

## Verification evidence

```text
Design spec checkpoint:         502b3916c124086089f2eb560a49f064cb00c65f
Implementation plan:            1f3444e8560fc20eb04797a6762a3c8cad663f4f
Task 1 GREEN:                   43f4db10ce5e559be6b6e6e3b4fe8dc52763a13c / 34611831744 — 7/7 green
Task 2 authority hardening:     cf82140cd17c17e942bd5477e7380c2c7979b71a / 34612972729 — 7/7 green
Task 3 final GREEN:             31e88742d3db568df90e56b1487710bfca4485d6 / 34613849084 — 7/7 green
Task 4 final GREEN:             3ded2dd0d0575e39d84ead814a93a7ca635b75a8 / 34614610479 — 7/7 green
Task 5 RED:                     f385159dd9cb27c7e6f26a3936e9b2a3b51726b2 / 34615660318
Initial verified implementation:08126177cd223c3beadd2150ffeb0bbb431d4c4d / 34615887644 — 7/7 green
Initial review-prep head:       0a74a07266dca54a87b975ac9771746fc2946aab / 34616818751 — 7/7 green
Initial PR #9 CI:               34618387763 — 7/7 green
CodeRabbit review:              96cfc4ce-fc5c-47a7-a0e7-b0cf9c2c87d6 — 3 Major + 2 Minor
Review-hardening RED:           081b43627552bbcb1470f88a980e3d7b34bdaa86 / 34619817207 — expected null-actor failure; idempotency hypothesis did not reproduce
Review-hardening GREEN:         991a108c6f1226860f671029c509b4c9edb09a97 / 34620155445 — 7/7 green
Review-hardening PR CI:         34620158752 — 7/7 green
PHP:                            595 tests / 3164 assertions
Browser:                        TypeScript typecheck + 103/103 Vitest
Contract / lint / Composer:     green
```

## External review rulings

### Major — null-ID actor could reach refund confirmation — VALID / FIXED

The original fixture resolver wrapped a `GenericUser` with `getAuthIdentifier() === null` as trusted `authenticated_actor`. That contradicted the production `AuthenticatedActorResolver` contract, where anonymous/absent callers resolve to `null`.

Hardening now returns `null` from `OrderDemoActorResolver` for a null identifier, causing the action to fail with `required_context_missing` before confirmation. The refund Gate also explicitly rejects null IDs as defense-in-depth. The regression is executable in `FilamentOrderDemoReviewHardeningTest`.

### Major — fixed convenience idempotency key allegedly conflicts across independent initial intents — NOT REPRODUCED

A RED reproduction test was added before modifying the adapter. Both independent, unconfirmed exposed refund intents reached `confirmation_required` successfully. Under the existing pipeline, a fresh idempotency claim is not created until after confirmation immediately before execution. The exposed adapter is initial-invocation-only, so this claimed conflict is not reachable there.

Completed-key replay/conflict behavior remains covered by the Task-3 gateway tests where idempotency belongs to the invocation envelope.

### Major — add confirmation receipt to exposed page method — REJECTED BY LOCKED CONTRACT

The suggestion would promote `confirmationReceipt` into the Livewire method/call-plan surface. T-505 intentionally exposes only business input (`reason`). Confirmation receipt and idempotency key are invocation-envelope candidates handled by `FilamentActionGateway` and are covered by the explicit retry integration tests. Adding them to `refundSelected()` would weaken D-040/D-051 rather than fix the reference boundary.

### Minors — stale PR metadata and incomplete changed-file inventory — FIXED

PR #9 and the complete tracking/documentation surface are now recorded here and in `REVIEW_REQUEST.md`.

## Known limitations / deliberate exclusions

- This is an executable reference fixture, not a standalone Laravel/Filament application.
- The Testbench convergence proof does not boot a full Filament panel/browser UI; binding production and the exact shared page-method seam are tested separately.
- The exposed consequential page method demonstrates the initial invocation only; exact approved retry remains a gateway-level envelope proof by design.
- T-505 does not add proof-of-human, delegated/supervisor approval, or independent approver identity semantics.
- T-505 does not change frozen wire contracts or browser-runtime production code.

## Current boundary

T-505 implementation, security hardening, and external-review triage are complete on the feature branch. PR #9 remains **open and unmerged** until the final documentation head passes push/PR CI and review threads are closed. M5 remains in progress until merge and post-merge `main` revalidation complete.