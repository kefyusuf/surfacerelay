# External Review / Merge Record — T-505 Multi-Tenant Order Operations Demo

## Final status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Scope:** `T-505 — Multi-tenant order operations demo`
- **Feature branch:** `feat/filament-order-operations-demo`
- **Pull request:** `#9` — **MERGED**
- **Original base / merge-base:** `main@b5da05b4a975ff8b2779960ea94e0c786a9c01db`
- **Final feature head:** `85570928b5e20277d94d2a95ec30028779966112`
- **Merge commit:** `7b95a82423012bf2824e55ba052ce78106f52e9a`
- **Post-merge main validation:** `34620944364` — **7/7 green**
- **Decision:** `D-052` — **ACCEPTED**
- **PHP:** **595 tests / 3164 assertions** across PHP 8.3/8.4 × Illuminate 12/13 with MySQL 8.4
- **Browser:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract / lint / Composer:** green
- **CodeRabbit review:** `96cfc4ce-fc5c-47a7-a0e7-b0cf9c2c87d6`
- **Review result:** **PASSED after TDD-first hardening and evidence-based finding triage**
- **Actionable review threads:** **5 total / 0 unresolved**
- **Merge state:** **MERGED / MAIN REVALIDATED**
- **Next boundary:** `M6 / T-601` — **NOT STARTED**

## Final reviewed behavior

T-505 demonstrates a multi-tenant Filament order vertical without adding a Filament-specific execution path.

```text
exact Filament Page
      │
      ├── trusted current_record
      ├── trusted current_selection
      └── trusted filament/active_filters
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
```

The central trust claim is that UI query scoping and caller-supplied IDs are never mutation authority. Exact trusted targets are separately authorized against trusted tenant state before execution.

## Review findings and closure

### Major — null authentication identifier could reach refund confirmation — FIXED / CONFIRMED

The original fixture actor resolver wrapped a `GenericUser` with a null authentication identifier as trusted `authenticated_actor`. A RED regression reproduced the problem: the refund reached `confirmation_required`.

The fix aligns the fixture with the production `AuthenticatedActorResolver` contract:

- null auth identifier resolves to `null`;
- required `authenticated_actor` context is therefore absent;
- invocation halts as `required_context_missing` before confirmation;
- refund Gate independently rejects a null identifier as defense-in-depth.

CodeRabbit confirmed the fix and the thread is resolved.

### Major — fixed convenience idempotency key allegedly conflicts across initial intents — NOT REPRODUCED / WITHDRAWN

The concern was tested before changing the adapter. A dedicated hardening test invoked two independent selections/reasons through the exposed page adapter using the same convenience key. Both reached `confirmation_required` successfully on the original implementation.

This matches the runtime ordering: `IdempotencyStage` preflights the key, but `ActionExecutionStage` creates a fresh claim only after confirmation immediately before execution. The exposed page adapter is intentionally initial-invocation-only and therefore does not claim the key.

Exact claimed/completed-key conflict and replay behavior remains covered through the normal gateway envelope tests. CodeRabbit verified this reasoning, withdrew the finding, and resolved the thread.

### Major — add confirmation receipt to exposed refund method — CONTRACTUALLY INCORRECT / WITHDRAWN

The written T-505 plan explicitly requires:

```text
ListOrders::refundSelected(string $reason)
```

with `reason` as the only business input. `confirmationReceipt` and `idempotencyKey` are invocation-envelope candidates owned by `FilamentActionGateway`, not Action input or Livewire call-plan authority.

Adding a bearer receipt to the exposed method would violate the D-040/D-051 separation. Exact challenge → approval → explicit retry → one execution → completed replay is already proven through `FilamentActionGateway`, including selection/tenant/filter drift and non-spending mismatch behavior.

CodeRabbit rechecked the written plan and tests, withdrew the finding, and resolved the thread.

### Minor — stale PR metadata — FIXED / CONFIRMED

`REVIEW_REQUEST.md` was updated to identify PR #9 and its live review status before merge. CodeRabbit confirmed and resolved the thread.

### Minor — incomplete changed-file inventory — FIXED / CONFIRMED

`STATUS.md` was updated to include the complete implementation/tracking surface, including `docs/DECISION-REGISTER.md`, `TASKS.md`, `STATUS.md`, and `REVIEW_REQUEST.md`. CodeRabbit confirmed and resolved the thread.

## Verified trust boundary

1. `orders.hold_current` is targeted by trusted `current_record`, never caller order ID.
2. `orders.refund_selected` is targeted by trusted `current_selection`; applied filters are independent `filament/active_filters` authority.
3. Caller metadata cannot replace tenant, record, selection, filters, confirmation, binding, or target authority.
4. Tenant-scoped Filament resource queries are defense-in-depth; independent Laravel Gate authorization protects the mutation boundary even under a deliberately unscoped fixture query.
5. Mixed-tenant selections are denied atomically before confirmation/execution.
6. Anonymous/null-ID actor state cannot manufacture `authenticated_actor` authority.
7. Confirmation approval performs no refund; the requesting caller must retry through the normal gateway with freshly resolved trusted state.
8. Wrong-scope selection/tenant/filter attempts do not spend an otherwise valid receipt.
9. Required-key idempotency prevents duplicate confirmed refund execution and rejects changed intent on completed-key reuse.
10. Exposed Livewire methods contain business inputs only; envelope capabilities are not promoted into the call plan.
11. Human and agent paths converge on the same page methods, gateway, ActionBus, and executor.
12. Structured audit persists allowlisted provenance/action/outcome facts without raw trusted/business/capability marker values.
13. No production Laravel source, browser-runtime production source, or frozen wire spec was changed.

## Final change surface

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

No T-505 implementation change occurred under:

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
Review-hardening RED:           081b43627552bbcb1470f88a980e3d7b34bdaa86 / 34619817207 — null actor reproduced; fixed-key conflict did not reproduce
Review-hardening GREEN:         991a108c6f1226860f671029c509b4c9edb09a97 / 34620155445 — 7/7 green
Review-hardening PR CI:         34620158752 — 7/7 green
Final feature head:             85570928b5e20277d94d2a95ec30028779966112
Final feature push CI:          34620681591 — 7/7 green
Final PR CI:                    34620685078 — 7/7 green
Merge commit:                   7b95a82423012bf2824e55ba052ce78106f52e9a
Post-merge main CI:             34620944364 — 7/7 green
PHP:                            595 tests / 3164 assertions
Browser:                        TypeScript typecheck + 103/103 Vitest
Contract / lint / Composer:     green
Unresolved review threads:      0
```

## Deliberate limitation

The minimal Testbench fixture does not configure a full Filament panel container. The shared execution seam is proven by directly booting the exact Filament page method while production Livewire binding generation for that same method is tested independently. This avoids turning T-505 into panel/bootstrap infrastructure work.

## Merge closure

T-505 is **DONE / REVIEWED / MERGED / MAIN REVALIDATED**. PR #9 merged using a normal merge commit with expected-head protection pinned to exact final feature head `85570928b5e20277d94d2a95ec30028779966112`. The resulting merge commit is `7b95a82423012bf2824e55ba052ce78106f52e9a`; post-merge main validation `34620944364` passed all seven jobs.

**M5 is complete. T-601 has not started.**