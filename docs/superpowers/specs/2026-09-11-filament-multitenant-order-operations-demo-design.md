# T-505 — Filament Multi-Tenant Order Operations Demo Design

## Status

Approved design for M5 / T-505.

- Task: `T-505 — Multi-tenant order operations demo`
- Milestone: `M5 — Filament Vertical`
- Base: `main@b5da05b4a975ff8b2779960ea94e0c786a9c01db`
- Branch: `feat/filament-order-operations-demo`
- Verified predecessor: T-504 is DONE / REVIEWED / MERGED / main-revalidated
- Target vertical: Filament 5.x on the existing Laravel 12/13 + Livewire 4 runtime
- Protocol impact: none; `spec/0.1/**` remains frozen
- Browser-runtime impact: none expected
- RuntimeBinding impact: none; Filament continues to use the existing Livewire binding/execution model
- Production-runtime impact: none expected; T-505 is an executable reference vertical and integration proof unless implementation uncovers a concrete missing primitive

## Objective

T-505 proves that the T-501 through T-504 Filament trust controls compose correctly in one realistic multi-tenant order workflow without allowing caller-controlled input, metadata, browser arguments, or UI state to become record/selection/tenant/confirmation authority.

The demo must exercise:

- trusted tenant resolution;
- trusted `current_record` on an order record page;
- trusted `current_selection` on an order table page;
- explicitly exposed `filament/active_filters` applied-state context;
- ordinary authorization before mutation;
- consequential confirmation through the T-504 approval-only bridge;
- server-side idempotency for externally consequential retry;
- structured audit without raw business/trusted-context payload persistence;
- the existing Livewire binding + ActionBus execution path for both human and agent invocation.

The goal is not to add another framework adapter or another business execution endpoint. The goal is to prove that the existing contracts are sufficient for a realistic tenant-isolated vertical.

## Locked decision — D-052

T-505 is an executable Filament multi-tenant order reference vertical, not a new protocol or execution contract. Authoritative order targets come exclusively from trusted tenant/current-record/current-selection state; applied filters remain an independent trusted runtime extension. Every mutating order operation must re-authorize target membership against the trusted tenant before application execution. Caller input may express business intent, but it may never identify the authoritative tenant or target records.

D-052 is recorded as `PROPOSED` during the design gate and is promoted to `ACCEPTED` only when the executable vertical and negative proofs are implemented and verified.

## Why this is a reference vertical rather than a standalone demo application

A separate Laravel application would introduce substantial non-contract surface:

- application bootstrapping and framework configuration;
- duplicated package wiring;
- separate migrations and fixtures;
- UI/layout maintenance unrelated to SurfaceRelay semantics;
- dependency/version drift from the package test matrix.

T-505 therefore uses a production-shaped executable reference vertical inside the existing Laravel/Testbench + Filament test environment. It may add dedicated order-domain fixtures and a short `examples/filament-orders/README.md`, but it must not create a second application runtime whose maintenance obscures the trust-boundary proof.

This keeps the vertical executable in CI across the existing PHP/Illuminate matrix while remaining understandable as a real order-operations example.

## Architecture

```text
trusted authenticated actor
trusted active tenant
          │
          ▼
exact active Filament Page
          │
          ├── record page ─────► current_record
          │
          ├── table page ──────► current_selection
          │
          └── explicit exposure ► filament/active_filters
                                   (applied state only)
          │
          ▼
FilamentActionGateway
          │
          ▼
existing ActionCall / ActionBus
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
order application operation
```

There is no Filament-specific execution driver and no direct browser-to-order endpoint.

## Reference domain

The executable fixture models a deliberately small order domain.

Conceptually:

```text
Tenant A
  Order 101 — paid
  Order 102 — paid
  Order 103 — pending

Tenant B
  Order 201 — paid
  Order 202 — pending
```

The exact fixture values are test data, not protocol data. Tests must use at least two tenants and multiple order states so that cross-tenant, record, selection, and filter drift can be proved negatively.

### Order identity

An order is represented by a persisted Eloquent model with a stable primary key and a tenant key. SurfaceRelay does not invent an alternate order identity format.

The trusted `current_record` and `current_selection` values therefore contain the exact Eloquent model instances resolved through the supported Filament page contracts already implemented by T-501/T-502.

### Tenant query scoping

The Filament resource/table should be scoped to the active tenant as an ordinary host-application defense-in-depth measure.

However, **Filament query scoping is not sufficient authorization for T-505**.

Every mutating operation must also validate that the trusted current record or every trusted selected record belongs to the trusted tenant before application execution. This prevents an incorrectly composed page/query from becoming cross-tenant mutation authority.

The two layers are deliberately separate:

```text
Filament resource/query scope
        = what the UI normally exposes

Action authorization tenant-membership check
        = what the mutation is allowed to affect
```

A failure in the first layer must not silently weaken the second.

## Operation 1 — `orders.hold_current`

### Purpose

Demonstrate a record-page reversible order mutation using trusted tenant + current record authority.

### Definition

Conceptually:

```text
id:          orders.hold_current
version:     1
scope:       page_scoped
effect:      reversible_write
risk:        moderate
idempotency: none
```

Required trusted context:

```text
authenticated_actor
tenant
current_record
```

### Input

Input carries business intent only, for example:

```json
{
  "reason": "manual-review"
}
```

The input schema must not accept target-authority fields such as:

```text
orderId
recordId
tenantId
currentRecord
record
```

### Authorization

Before mutation the application policy verifies at minimum:

1. an exact authenticated actor is present;
2. an exact trusted tenant is present;
3. an exact trusted current order record is present;
4. the current record belongs to the trusted tenant;
5. the actor is permitted to perform the hold operation for that tenant/order.

Caller metadata containing order or tenant-shaped keys grants no authority.

### Expected result

Only the exact active current order may transition to the demo's held state. No record re-resolution from action input, route/query parameters, binding metadata, or browser arguments is permitted.

## Operation 2 — `orders.refund_selected`

### Purpose

Demonstrate the complete consequential bulk-operation trust boundary:

- trusted tenant;
- exact trusted Filament selection;
- independently bound applied filter context;
- explicit human approval;
- exact retry;
- idempotent external side-effect semantics.

### Definition

Conceptually:

```text
id:          orders.refund_selected
version:     1
scope:       page_scoped
effect:      external_side_effect
risk:        consequential
idempotency: required_key
```

Required core trusted context:

```text
authenticated_actor
tenant
current_selection
human_confirmation
```

The invocation also explicitly requests:

```text
filament/active_filters
```

through trusted server-side `FilamentContextExposure::activeFilters()` wiring.

### Input

Business intent only, for example:

```json
{
  "reason": "customer-request"
}
```

The action must not accept authoritative target/context fields such as:

```text
orderId
orderIds
ids
recordIds
selection
selected
selectedTableRecords
tenantId
filters
activeFilters
tableFilters
confirmed
confirmationChallengeId
```

### Selection authority

The exact selected orders come only from T-502 `current_selection`, resolved from the exact active Filament table page through Filament's supported selection contract.

Caller input, generic metadata, raw Livewire selection state, request/query/route parameters, DOM state, or table identifiers cannot manufacture or alter this selection.

### Filter authority

The operation opts into `filament/active_filters` so the exact applied Filament filter state participates independently in invocation identity.

The filter extension:

- is not a substitute for selection;
- is not reconstructed from input or metadata;
- uses applied state, not deferred/pending form state;
- automatically binds confirmation scope and idempotency intent under D-050;
- is represented in structured audit only by extension key/provider, not raw filter state.

### Tenant-membership authorization

Before refund execution, authorization verifies that **every exact selected order** belongs to the trusted tenant.

A mixed-tenant selection fails closed as a whole. T-505 must not partially refund the matching subset while silently dropping unauthorized records.

The demo must include a negative proof in which a selected record from another tenant reaches the trusted selection fixture/harness and is still refused before application execution.

This negative proof intentionally demonstrates that resource/query scoping is defense-in-depth rather than the sole authorization boundary.

## Confirmation flow

T-505 uses T-504 unchanged.

```text
original refund_selected invocation
        │
        ▼
validation + authorization + idempotency preflight
        │
        ▼
confirmation_required + exact ConfirmationChallenge
        │
        ▼
Filament confirmation modal
        │
        ├── Cancel  → presentation state only
        │
        └── Approve → approve exact challenge only
                         NO refund
                         NO redispatch
        │
        ▼
requesting caller retries original operation
        │
        ▼
fresh actor / tenant / selection / applied filters / input / binding / surface
        │
        ▼
exact receipt consumed once
        │
        ▼
refund execution
```

Approval is never itself execution authority outside the exact retry scope.

## Confirmation drift proofs

A receipt approved for state A must not authorize state B.

Required drift cases include at least:

### Selection drift

```text
challenge: tenant A + selection [101, 102]
approve
retry:     tenant A + selection [101, 103]
→ no execution
```

### Tenant drift

```text
challenge: tenant A + selection [101, 102]
approve
retry:     tenant B + any selection
→ no execution
```

### Applied-filter drift

```text
challenge: status=paid
approve
retry:     status=pending
→ no execution
```

### Exact retry

```text
challenge: tenant A + selection [101, 102] + status=paid
approve
retry:     exact same trusted authority + intent
→ receipt consumed; execution permitted
```

Wrong-scope attempts must not spend an otherwise-valid exact-scope receipt, preserving existing T-401/T-503 semantics.

## Idempotency

`orders.refund_selected` uses `required_key`.

The vertical must prove:

1. the first exact authorized+confirmed attempt executes the external-side-effect fixture once;
2. a lost-response/exact retry with the same active idempotency key and same trusted intent reuses completed output without a second refund execution;
3. the same caller idempotency key with changed tenant, selection, applied filters, or validated business input conflicts rather than replaying stale output;
4. raw idempotency keys are never persisted in audit/demo projection;
5. confirmation authority is not re-created by idempotency replay.

T-505 must reuse D-045; it must not create an order-specific idempotency store or alternate deduplication mechanism.

## Human / agent convergence

The Filament page methods used by the demo are explicitly exposed through the existing Livewire `#[ExposeAction(id, version)]` mechanism.

Conceptually:

```text
human Filament interaction ─┐
                            ├─ exact exposed Livewire page method
agent binding invocation ───┘
                                      │
                                      ▼
                             FilamentActionGateway
                                      │
                                      ▼
                                  ActionBus
                                      │
                                      ▼
                           same application operation
```

The exposed method is the convergence point. T-505 must not introduce:

- an agent-only controller/route;
- a direct API mutation endpoint solely for the demo;
- a Filament RuntimeBinding driver;
- an ActionBus bypass;
- a duplicate order mutation implementation.

Exposure remains an allow-list reference only and is not invocation authorization.

## Caller-spoofing boundary

The following must be unable to create or replace tenant/target/filter/confirmation authority:

- action input fields with tenant/record/selection/filter-shaped names;
- `InvocationContext::metadata`;
- route/query/request values;
- Livewire public properties supplied directly by the caller;
- WebMCP arguments;
- `bindingId`;
- confirmation challenge ID supplied in business input;
- `confirmed=true` or equivalent booleans;
- idempotency key contents.

The executable vertical must include negative tests for representative spoofing attempts.

## Authorization and failure atomicity

Mutating order operations are all-or-nothing at the SurfaceRelay authorization boundary.

Representative refusal cases:

- missing trusted tenant;
- missing required current record/current selection;
- current record belongs to another tenant;
- one or more selected records belong to another tenant;
- actor lacks operation permission;
- consequential execution lacks valid confirmation receipt;
- trusted scope changed after approval;
- required idempotency key missing/invalid/conflicting;
- explicitly exposed active-filter context cannot be resolved safely.

No refused case may partially mutate orders or perform the external-side-effect fixture.

Failure messages should remain static-safe and must not expose trusted tenant values, unauthorized order IDs, receipt tokens, scope fingerprints, raw filter state, or underlying framework/store exception text.

## Structured audit

T-505 reuses D-047 unchanged.

Audit may record existing allowlisted facts such as:

- action identity/version;
- effect/risk/idempotency policy;
- outcome kind/halt stage/code;
- whether verified human confirmation was present;
- trusted-context manifest provider names;
- `filament/active_filters` extension key/provider.

Audit must not persist:

- raw tenant identity/value;
- order attributes;
- selected order ID lists;
- filter state/values;
- raw business input such as refund reason;
- confirmation challenge/receipt tokens;
- confirmation/idempotency scope fingerprints;
- raw idempotency key;
- model class names when not already part of an allowlisted stable contract.

The vertical includes an assertion over the resulting audit projection/storage to prove these business/trusted-context values are absent.

## Snapshot freshness

Each invocation resolves its trusted Filament state once.

A later retry is a new invocation and therefore re-resolves:

- authenticated actor;
- tenant;
- current record or selection;
- applied active filters.

The demo does not maintain a long-lived synchronized authorization snapshot between approval and retry.

This is essential to the confirmation-drift proofs: changed UI/runtime authority after approval must naturally produce a different scope rather than silently reusing stale state.

## Test architecture

T-505 should add a focused fixture namespace, conceptually:

```text
packages/laravel/tests/Fixtures/Filament/OrderDemo/
  Order.php
  OrderResource.php
  ListOrders.php
  EditOrder.php
  actor/tenant resolver fixtures as needed
  minimal operation/executor fixtures as needed
```

The exact filenames may follow nearby repository patterns, but the domain-specific fixture boundary should remain isolated from generic T-501–T-504 test fixtures.

Primary integration proof:

```text
packages/laravel/tests/Integration/FilamentMultiTenantOrderOperationsDemoTest.php
```

A short human-facing walkthrough may be added at:

```text
examples/filament-orders/README.md
```

The README must describe the executable fixture/test rather than claiming a separately deployable demo application exists.

## Required scenario set

The implementation plan may split these into multiple tests, but the completed vertical must prove the behavior as a coherent set.

### Current-record operation

- Tenant A + Order 101 active record + permitted actor → hold succeeds.
- Input/metadata tries to identify Order 201 while current record is Order 101 → only trusted Order 101 can be targeted.
- Tenant A trusted context + current Order 201 (Tenant B) → authorization refusal; no mutation.

### Current-selection operation

- Tenant A + exact selected orders 101/102 → selection reaches authorization/executor unchanged.
- Caller-supplied IDs cannot add Order 201 or replace 101/102.
- Mixed-tenant trusted selection is refused atomically; no partial refund.

### Filter binding

- `status=paid` applied state participates in trusted extension scope.
- pending deferred filter edits do not change authority before Apply.
- applied filter change after challenge invalidates the old receipt for that retry.

### Confirmation

- first consequential invocation returns `confirmation_required` and performs no refund.
- modal approval alone performs no refund.
- exact approved retry executes.
- tenant/selection/filter drift retry does not execute.
- wrong-scope attempt does not spend exact valid receipt.

### Idempotency

- exact retry/lost-response path executes external side effect once.
- changed selection/filter/input under the same caller key conflicts rather than replaying old output.

### Audit

- audit records trusted manifest facts but no raw tenant/order-selection/filter/token/business-input material.

### Exposure path

- demo operations are explicitly exposed through existing Livewire exposure/binding semantics.
- no alternate business endpoint or Filament-specific browser execution path exists.

## Expected change surface

T-505 is expected to be test/reference-fixture heavy.

Expected additions/modifications:

```text
packages/laravel/tests/Fixtures/Filament/OrderDemo/**
packages/laravel/tests/Integration/FilamentMultiTenantOrderOperationsDemoTest.php
examples/filament-orders/README.md
docs/superpowers/plans/<T-505 implementation plan>.md
TASKS.md
STATUS.md
REVIEW_REQUEST.md
```

Possible documentation updates after implementation:

```text
docs/DECISION-REGISTER.md   # D-052 PROPOSED → ACCEPTED
README.md                   # only if a conservative reference-vertical note is useful
```

Production source under `packages/laravel/src/**` is **not expected to change**. If implementation reveals a genuine missing runtime primitive, work must stop and the design gate must be reopened before adding that primitive.

## Explicit non-goals

T-505 does not:

- add a `filament` RuntimeBinding driver;
- change `spec/0.1/**`;
- add protocol-neutral `active_filters` vocabulary;
- add search/sort/tab/group/pagination authority;
- add a generic tenant framework or tenancy package integration;
- claim Filament resource scoping is sufficient authorization;
- introduce proof-of-human, WebAuthn, supervisor approval, delegated approval, or approver identity semantics;
- add a second order business endpoint for agents;
- build a separately deployable demo SaaS/application;
- change browser-runtime production behavior;
- change Livewire cancellation semantics;
- broaden T-504 approval into automatic redispatch.

## Acceptance criteria

T-505 is complete only when all of the following are true:

1. A realistic two-tenant Filament order fixture exists in the existing Laravel/Testbench matrix.
2. `orders.hold_current` mutates only the exact trusted current order after tenant-membership authorization.
3. `orders.refund_selected` consumes only the exact trusted current selection after all selected orders pass tenant-membership authorization.
4. Caller input/metadata/browser arguments cannot manufacture tenant, record, selection, filter, or confirmation authority.
5. Mixed/cross-tenant current record or selection fails closed before application mutation/side effect.
6. Applied Filament filter state is explicitly exposed through `filament/active_filters` and remains independent from selection identity.
7. Consequential bulk refund uses the existing T-504 approval-only flow; approval performs no business redispatch.
8. Exact retry freshly resolves trusted actor/tenant/selection/filter state before receipt consumption.
9. Tenant, selection, or applied-filter drift invalidates the approved scope and performs no side effect.
10. Wrong-scope receipt attempts do not spend the exact valid receipt.
11. Required-key idempotency proves exact retry/lost-response deduplication with one external-side-effect execution.
12. Changed intent under the same caller key conflicts rather than replaying stale output.
13. Structured audit contains only existing allowlisted manifest/outcome facts and no raw order-selection/filter/tenant/token/business-input material.
14. Human and agent paths converge on the same explicitly exposed Livewire/Filament page method and same ActionBus/application operation.
15. No alternate agent endpoint, Filament RuntimeBinding driver, browser-runtime production change, or `spec/0.1` change is introduced.
16. The full existing PHP/browser/contract verification matrix remains green.
17. D-052 is promoted to `ACCEPTED` only with the verified implementation.

## Verification gate

Implementation must follow repository TDD discipline and include targeted RED/GREEN checkpoints for the new vertical.

Before T-505 may be marked DONE:

```text
PHP unit/integration suite across supported PHP × Illuminate matrix
MySQL service coverage where existing workflow requires it
Browser TypeScript typecheck + Vitest regression suite
python scripts/validate.py
full diff architecture/security review
```

The task is security-sensitive because it touches tenant resolution, consequential mutation, confirmation, idempotency, and audit; negative tests are mandatory under `AGENTS.md`.

## Design conclusion

T-505 closes M5 by demonstrating composition, not by expanding architecture.

The proof is successful when a real Filament-shaped order workflow can expose record and bulk operations to human and agent callers through the existing Livewire/ActionBus path while the authoritative tenant, record, selection, applied filters, confirmation receipt, and replay identity remain server-derived and fail closed under cross-tenant or stale-scope attempts.
