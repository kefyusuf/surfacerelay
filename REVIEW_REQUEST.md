# External Review Request — T-505 Multi-Tenant Order Operations Demo

## Current status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Scope:** `T-505 — Multi-tenant order operations demo`
- **Feature branch:** `feat/filament-order-operations-demo`
- **Pull request:** `#9` — **OPEN / EXTERNAL REVIEW HARDENING**
- **Base / merge-base:** `main@b5da05b4a975ff8b2779960ea94e0c786a9c01db`
- **Current verified code head:** `991a108c6f1226860f671029c509b4c9edb09a97`
- **Decision:** `D-052` — **ACCEPTED**
- **PHP:** **595 tests / 3164 assertions** across PHP 8.3/8.4 × Illuminate 12/13 with MySQL 8.4
- **Browser:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract / PHP lint / Composer:** green
- **Production runtime changes:** **NONE**
- **CodeRabbit review:** `96cfc4ce-fc5c-47a7-a0e7-b0cf9c2c87d6` — **3 Major + 2 Minor reviewed individually**
- **Merge state:** **NOT MERGED**

## Review thesis

T-505 is an executable reference vertical, not a new runtime feature. It proves that the existing SurfaceRelay trust controls compose correctly for realistic multi-tenant Filament order operations without creating a second business, target-selection, or authorization path.

```text
Filament Page
   ↓
FilamentActionGateway
   ↓
TrustedContextComposer + Filament context resolvers
   ↓
ActionBus
   ↓
validation → authorization → idempotency → confirmation → execution → output policy → audit
```

There is no `filament` RuntimeBinding driver and no agent-only order endpoint.

## Primary security assertions

### Current-record target authority

`orders.hold_current` receives only `reason` as Action input. The authoritative target is the exact trusted `current_record`. A cross-tenant persisted record assigned directly to the page is denied before executor mutation even when normal resource query scoping is bypassed by the fixture.

### Current-selection and applied-filter authority

`orders.refund_selected` receives only `reason` as Action input. The exact target set is trusted `current_selection`. Applied filters are exposed independently as `filament/active_filters`. Caller metadata containing fake `orderIds`, `tenantId`, or filters cannot replace either authority dimension.

### Tenant query scope is defense-in-depth

`OrderResource` normally scopes to the trusted host tenant. A test-only host-query misconfiguration permits a mixed `[101, 201]` selection while trusted tenant authority remains Tenant A. Laravel Gate still rejects the complete invocation atomically before confirmation/execution.

### Authenticated actor absence fails closed

External review found that the original test resolver could wrap a `GenericUser` with `getAuthIdentifier() === null` as trusted actor context. The production `AuthenticatedActorResolver` contract defines anonymous/absent actor as `null`. Review hardening now returns `null` from the fixture resolver for a null identifier, so `authenticated_actor` is absent and the action halts with `required_context_missing`. The refund Gate independently rejects a null identifier as defense-in-depth.

### Confirmation remains an invocation-envelope capability

The refund action is consequential. Approval alone executes no order side effect. The requesting caller explicitly retries through the normal `FilamentActionGateway`, which freshly resolves tenant, selection, filters, actor, binding, surface, and validated input before consuming the receipt.

`ListOrders::refundSelected(string $reason)` is intentionally an **initial-invocation-only convergence seam**. Its Livewire binding contains exactly one business field: `reason`.

`confirmationReceipt` and `idempotencyKey` are **not** page-method parameters and **not** Action input. They are invocation-envelope candidates owned by the normal gateway retry path. This is a locked D-040/D-051 boundary, not missing plumbing.

Exact challenge → approval → explicit retry → one execution → completed replay, including selection/tenant/filter drift and non-spending mismatch behavior, is covered by the Task-3 gateway integration tests.

### Idempotency finding was executable-tested before ruling

CodeRabbit raised a concern that the convenience adapter's constructor-supplied idempotency key could make a second independent refund intent conflict. We added a reproduction test before altering the adapter. Both independent unconfirmed intents reached `confirmation_required` on the original implementation.

This matches core ordering: required-key idempotency is preflighted before confirmation, but a fresh claim is created only after confirmation immediately before execution. The exposed adapter never approves or retries; therefore it never claims the key. The proposed conflict is not reachable on that initial-invocation-only path.

Exact completed-key replay/conflict behavior remains tested via `FilamentOrderDemoHarness::dispatchRefund()` at the gateway envelope seam.

### Durable audit secrecy

The reference vertical uses the existing T-404 migration and `DatabaseAuditEventStore`. Adversarial tenant/record/filter/business/idempotency/receipt marker values are absent from durable audit rows while allowlisted provider/provenance facts remain available. Correlation IDs are internal sequence identifiers rather than secret/target-derived strings.

## CodeRabbit finding rulings

### Major — fixed convenience key causes later intent conflict

**Ruling: NOT REPRODUCED / NO CODE CHANGE.**

The dedicated hardening test passed on the original adapter implementation. Unconfirmed requests do not claim the key. Adding caller-controlled or business-derived idempotency fields to the page method would weaken the invocation-envelope boundary.

### Major — exposed refund method cannot accept approved receipt

**Ruling: INTENTIONAL CONTRACT / PROPOSED FIX REJECTED.**

Adding `confirmationReceipt` to the exposed method would promote a bearer capability into Livewire call-plan input, contrary to the written T-505 plan and D-040/D-051. Exact approved retry already runs through the production gateway in integration tests.

### Major — null authentication identifier accepted by refund Gate

**Ruling: VALID / FIXED TDD-FIRST.**

The RED regression reproduced `confirmation_required` for the null-ID actor. The fixture resolver now returns `null`, matching `AuthenticatedActorResolver`, and the Gate also rejects null IDs. GREEN verification is 7/7.

### Minor — review request said PR was not created

**Ruling: FIXED.** PR #9 is recorded in this document and `STATUS.md`.

### Minor — status inventory omitted tracking files

**Ruling: FIXED.** The complete implementation/tracking surface now includes `docs/DECISION-REGISTER.md`, `TASKS.md`, `STATUS.md`, and `REVIEW_REQUEST.md`.

## Change surface

Expected T-505 files:

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

Unexpected / forbidden without reopening the design gate:

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
CodeRabbit full review:         96cfc4ce-fc5c-47a7-a0e7-b0cf9c2c87d6 — 3 Major + 2 Minor
Review-hardening RED:           081b43627552bbcb1470f88a980e3d7b34bdaa86 / 34619817207 — null actor reproduced; fixed-key conflict did not reproduce
Review-hardening GREEN:         991a108c6f1226860f671029c509b4c9edb09a97 / 34620155445 — 7/7 green
Review-hardening PR CI:         34620158752 — 7/7 green
PHP:                            595 tests / 3164 assertions
Browser:                        TypeScript typecheck + 103/103 Vitest
Contract / lint / Composer:     green
```

## Deliberate fixture limitation

The minimal Testbench fixture does not configure a full Filament panel container. A first Task-4 attempt using `Livewire::test()` failed at panel-container resolution (`Target class [filament] does not exist`). The accepted proof directly boots the exact Filament page method and independently verifies production Livewire binding generation for that same method.

This avoids expanding T-505 into panel/bootstrap infrastructure. A fully configured host may add panel-level render/browser tests without changing the trust boundary.

## Merge gate

PR #9 is **not merged**. Merge is permitted only after the final documentation/tracking head passes push and PR CI, all actionable review findings are either fixed or technically resolved with evidence, and no production/runtime/frozen-contract drift appears.