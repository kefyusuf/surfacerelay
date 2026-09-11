# External Review Request — T-505 Multi-Tenant Order Operations Demo

## Current status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Scope:** `T-505 — Multi-tenant order operations demo`
- **Feature branch:** `feat/filament-order-operations-demo`
- **Pull request:** **NOT CREATED YET**
- **Base / merge-base:** `main@b5da05b4a975ff8b2779960ea94e0c786a9c01db`
- **Verified implementation head:** `08126177cd223c3beadd2150ffeb0bbb431d4c4d`
- **Decision:** `D-052` — **ACCEPTED after executable verification**
- **PHP:** **593 tests / 3157 assertions** across PHP 8.3/8.4 × Illuminate 12/13 with MySQL 8.4
- **Browser:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract:** `python scripts/validate.py` green; frozen `spec/0.1/**` unchanged
- **PHP lint / Composer validation:** green
- **Production runtime changes:** **NONE**
- **Review state:** **READY FOR EXTERNAL REVIEW — NOT MERGED**

## Review thesis

T-505 is an executable reference vertical, not a new runtime feature. Review should verify that the fixture honestly composes the existing SurfaceRelay trust boundaries and does not create a second business/authorization path.

The two application operations are:

```text
orders.hold_current
orders.refund_selected
```

Both are dispatched through the existing:

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

## Primary security assertions to review

### 1. Current-record target authority

`orders.hold_current` receives only `reason` as Action input. The authoritative target is the exact trusted `current_record` resolved from the active record-aware Filament page.

The cross-tenant negative test deliberately places a persisted Tenant B order onto the trusted record-aware page while actor/tenant authority remains Tenant A. Laravel Gate must deny before executor mutation even though normal resource scoping has effectively been bypassed by the fixture.

### 2. Current-selection target authority

`orders.refund_selected` receives only `reason` as Action input. The target set is exact trusted `current_selection` materialized through the existing T-502 resolver.

Caller metadata containing fake `orderIds`, `tenantId`, or filter state must not change target authority.

### 3. Applied filters are an independent authority dimension

Refund dispatch explicitly opts into `FilamentContextExposure::activeFilters()`.

The trusted extension `filament/active_filters` must reflect the exact applied Filament filter state. It must remain independent from selection identity and must not be replaced by generic metadata.

### 4. Tenant scoping is defense-in-depth, not the authorization boundary

`OrderResource::getEloquentQuery()` normally scopes to the trusted host tenant.

A test-only fixture switch deliberately simulates an unscoped host resource query and selects orders from Tenant A and Tenant B together. The complete invocation must still be denied atomically by Laravel Gate before confirmation/execution. The code must not filter the unauthorized record out and continue.

### 5. Confirmation is approval-only

The refund action is consequential and requires human confirmation. Approval calls only the existing `ConfirmationService::approveChallenge()` and causes zero order executor calls.

The requesting caller explicitly retries the normal gateway invocation. Selection, tenant, or applied-filter drift must reject the old scope without consuming the valid exact-scope receipt. Restoring exact trusted state must allow that same receipt to complete.

### 6. Idempotency binds current authority and intent

Exact completed retry must replay without a second refund execution.

Changed validated input, selected-set identity, or applied-filter authority under the same key must not replay the completed output. Tenant changes use the existing tenant/actor key partition rather than sharing a cross-tenant idempotency record.

### 7. Human/agent convergence

`EditOrder::holdCurrent(reason)` and `ListOrders::refundSelected(reason)` are the same explicitly exposed page methods used by the existing Livewire binding producer.

Review that:

- binding `driver` remains `livewire`;
- exact action ID/version and exact page method are produced;
- binding target contains no order or tenant IDs;
- page methods contain no Eloquent mutation/query shortcut, `ActionBus`, or `ActionCall` path;
- `OrderDemoPageActions` is a thin gateway adapter only.

### 8. Durable audit secrecy

The reference vertical uses the existing T-404 migration and `DatabaseAuditEventStore`.

The audit test injects marker strings into tenant authority, order status, refund reason, applied filters, raw idempotency key, and the exact confirmation receipt. Directly persisted rows must not contain any of those marker bytes.

The provider/provenance manifest should still expose allowlisted facts such as:

```text
order_demo.actor
order_demo.tenant
filament.current_record
filament.current_selection
filament.active_filters
surfacerelay.confirmation
```

A confirmed retry should persist `human_confirmation_present=true` without storing the bearer receipt.

The harness correlation ID is intentionally an internal sequence value rather than a target/business/idempotency-derived string because correlation IDs are part of D-047's durable allowlist.

## Change surface

Expected T-505 implementation/review files:

```text
packages/laravel/tests/Fixtures/Filament/OrderDemo/**
packages/laravel/tests/Fixtures/views/filament-order-demo-page.blade.php
packages/laravel/tests/Support/FilamentOrderDemoHarness.php
packages/laravel/tests/Integration/FilamentMultiTenantOrderOperationsDemoTest.php
packages/laravel/tests/Integration/FilamentOrderDemoLivewireBindingTest.php
examples/filament-orders/README.md
docs/superpowers/specs/2026-09-11-filament-multitenant-order-operations-demo-design.md
docs/superpowers/plans/2026-09-11-filament-multitenant-order-operations-demo.md
docs/DECISION-REGISTER.md
TASKS.md
STATUS.md
REVIEW_REQUEST.md
```

Explicitly unexpected for T-505:

```text
packages/laravel/src/**
packages/browser-runtime/src/**
spec/0.1/**
```

Any T-505 diff under those production/frozen-contract paths should be treated as scope drift requiring a renewed design decision.

## Verification evidence

```text
Design spec checkpoint:         502b3916c124086089f2eb560a49f064cb00c65f
D-052 proposed checkpoint:      3e978003e36a1bf1b2723fc80df9144f89e6ed31
Implementation plan:            1f3444e8560fc20eb04797a6762a3c8cad663f4f
Task 1 RED:                     a384433079b01dba2419979bbce335aa54263ba9 / 34611447869
Task 1 GREEN:                   43f4db10ce5e559be6b6e6e3b4fe8dc52763a13c / 34611831744 — 7/7 green
Task 2 RED:                     effd98dcf9e998baf4700e3f4068d29140fa8d90 / 34612166801
Task 2 API-fix GREEN:           d28958b14ccd077bcf1cbd77cadd74d33111f3d2 / 34612717833 — 7/7 green
Task 2 authority hardening:     cf82140cd17c17e942bd5477e7380c2c7979b71a / 34612972729 — 7/7 green
Task 3 RED:                     ed603e69cace125be528bdd505a9c5ce62fd8745 / 34613229629
Task 3 final GREEN:             31e88742d3db568df90e56b1487710bfca4485d6 / 34613849084 — 7/7 green
Task 4 final GREEN:             3ded2dd0d0575e39d84ead814a93a7ca635b75a8 / 34614610479 — 7/7 green
Task 5 RED:                     f385159dd9cb27c7e6f26a3936e9b2a3b51726b2 / 34615660318
Verified implementation head:  08126177cd223c3beadd2150ffeb0bbb431d4c4d / 34615887644 — 7/7 green
PHP:                            593 tests / 3157 assertions
Browser:                        TypeScript typecheck + 103/103 Vitest
Contract:                       validator green; frozen spec unchanged
```

## Review ruling to keep visible

The first Task-4 attempt tried to render the Filament page through `Livewire::test()` and failed because the minimal Testbench fixture has no configured Filament panel container (`Target class [filament] does not exist`).

The accepted fixture boundary executes the exact booted page method directly and independently verifies production Livewire binding generation for that same method.

This avoids expanding T-505 into panel/bootstrap infrastructure. The trade-off is explicit: a render/lifecycle-specific bug that appears only in a fully configured Filament panel is not covered by this minimal reference fixture.

## Merge gate

This request is for external code review only. T-505 is **not merged**. Merge should remain a separate explicit action after review findings, if any, are resolved and the exact final feature head is revalidated.