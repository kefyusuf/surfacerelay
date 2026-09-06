# Project Status

> Current repository state for implementation and external review.

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/prep-list-e2e`
- **Main baseline:** `44eb738bae54c8066e04ccc015edfea160de58f2`
- **Stage:** M0 DONE; M1 DONE; M1.1 DONE/REVIEWED; **M2 IMPLEMENTATION COMPLETE / REVIEW PENDING**
- **Last completed task:** `T-204 — Prep List shared ActionBus E2E`
- **Next task:** `T-301 — DriverRegistry` — **not started**
- **Contract version:** `0.1-draft`
- **Spec status:** `spec/0.1` remains frozen and unchanged by T-204.
- **Contract baseline:** **52 fixture manifest entries + 12 conformance scenarios**.
- **PHP baseline:** **266 tests / 783 assertions**.
- **Integration matrix:** PHP 8.3/8.4 × Illuminate 12/13 × Testbench 10/11 with Livewire 4.4 — all green on T-204 implementation head.
- **Observed Livewire:** `v4.4.3` in the current matrix run.
- **Browser baseline:** TypeScript typecheck + **3 Vitest tests**.

## Current objective

External-style review of T-204 and the complete M2 Livewire vertical before beginning M3/T-301.

## T-204 — Prep List shared ActionBus E2E

T-204 proves the first complete Livewire reference path without adding an agent-only business endpoint.

```text
Human Livewire call ───────────────┐
                                   ▼
                            PrepListComponent::addItem
                                   │
Binding-derived invocation ────────┘
                                   │
                                   ▼
                              ActionBus
                                   │
                                   ▼
                         ActionExecutionStage
                                   │
                                   ▼
                         PrepListActionExecutor
                                   │
                                   ▼
                           AddPrepListItem
                                   │
                                   ▼
                             PrepListStore
```

The binding-derived test does not claim the M3 browser driver exists. It obtains an actual Livewire component instance, produces a T-203 RuntimeBinding, verifies the exact component ID/action/method target, and asks the real Livewire test harness to call precisely that binding target method.

### Production runtime additions

Only two protocol-neutral production files were required:

```text
packages/laravel/src/Contracts/ActionExecutor.php
packages/laravel/src/Runtime/Pipeline/ActionExecutionStage.php
```

`ActionExecutionStage`:

- is exactly the canonical `execution` stage;
- delegates once to `ActionExecutor`;
- passes the exact resolved `ActionDefinition` object;
- passes current pipeline input after earlier transformations/validation;
- passes the exact trusted `InvocationContext`;
- records executor return value through immutable pipeline state;
- treats `null` as a real execution output;
- propagates application exceptions unchanged.

No generic executor fallback registry, surface-specific executor, or agent-only execution path was added.

### Real Livewire/Testbench integration

Livewire is now a **development-only** dependency of the Laravel package:

```text
livewire/livewire ^4.4
orchestra/testbench ^10|^11
```

It is not present in production Composer `require`.

CI explicitly tests:

```text
PHP 8.3 × Illuminate 12 × Testbench 10 × Livewire 4.4
PHP 8.3 × Illuminate 13 × Testbench 11 × Livewire 4.4
PHP 8.4 × Illuminate 12 × Testbench 10 × Livewire 4.4
PHP 8.4 × Illuminate 13 × Testbench 11 × Livewire 4.4
```

The reusable library no longer commits `packages/laravel/composer.lock`; every CI cell resolves its supported framework generation independently.

### Prep List reference fixture

All Prep List application/reference code remains test-only under:

```text
packages/laravel/tests/Fixtures/PrepList/
```

The fixture includes:

- `PrepListStore` — deterministic in-memory state;
- `AddPrepListItem` — the single business mutation;
- `PrepListActionExecutor` — exact `prep_list.add_item@1` executor;
- `PrepListAuthorizer` — explicit fixture authorization port;
- `PrepListInvocationContextFactory` — trusted BrowserSession authority;
- `PrepListActionGateway` — exact ActionCall → shared ActionBus;
- `PrepListComponent` — real Livewire component with explicit `#[ExposeAction]`;
- `PrepListTestPipeline` — integration wiring.

The real component uses Livewire lifecycle injection:

```text
boot(PrepListActionGateway)
```

and contains no direct store/business mutation.

### Security and pipeline proof

The E2E suite proves:

1. Human `Livewire::test(...)->call('addItem', 'passport')` reaches the shared ActionBus and mutates state exactly once.
2. A T-203 binding generated from the real component points to the exact real component ID and `addItem` method.
3. Calling exactly the binding target method through the real Livewire test harness reaches the same ActionBus and same `AddPrepListItem` service.
4. Fresh human and binding-derived runs produce equivalent state.
5. The real validation stage passes only validated `name` downstream.
6. The real authorization stage is traversed after validation.
7. `browser_session` exists only as trusted `InvocationContext`; it is absent from action input.
8. Invalid empty input halts at input validation before authorization and execution; business mutation count remains zero.
9. Component wiring uses `boot()` lifecycle DI, not constructor/service-locator application wiring.

Confirmation, idempotency, output-policy and audit completion are **not** claimed by T-204. Their T-204 handlers/auditor are explicit test-only placeholders because production implementations belong to M4.

## TDD / verification evidence

### Execution stage RED → GREEN

```text
RED:   868eb1f3dc89e47023af95217bd44279b7a80994
GREEN: 7267a43d6ede657d52cffc0d8a96f047f6c885af
```

RED observed:

```text
260 tests / 713 assertions / 4 deliberate failures
```

Failures were only missing `ActionExecutor` / `ActionExecutionStage` behavior.

### Prep List E2E RED → GREEN

```text
RED:      75022ae6594dfcabfd33bec89825d51459d0b8fa
Fixture:  f1eca5290d4ddbbd4b36990feddf76e20cc76f1c
Harness:  e7e6a9809d647070aff78105285ac08da0b4a03b
```

RED observed:

```text
266 tests / 735 assertions / 6 deliberate failures
```

All failures were absent Prep List fixture/wiring. After fixture implementation the remaining integration error was Testbench's missing application encryption key; a fixed test-only key was added to the integration environment.

Final observed result:

```text
OK (266 tests, 783 assertions)
```

All four PHP/Illuminate/Testbench/Livewire cells, contract validation, PHP lint, and browser checks passed on the exact T-204 implementation head.

## Decisions

- D-022..D-025 remain ACCEPTED.
- D-026 remains PROPOSED until real binding-resolution failure behavior exists.
- D-033 remains ACCEPTED — server request teardown is not browser component-lifecycle authority.
- D-034 ACCEPTED — human Livewire interaction and binding-derived invocation converge on the same explicit component method and shared ActionBus/application mutation; no agent-only business endpoint is introduced.

## M2 completion state

```text
T-201 DONE / REVIEWED
T-202 DONE / REVIEWED
T-203 DONE / REVIEWED
T-204 DONE / REVIEW PENDING
```

M2 implementation is complete. M2 is not marked reviewed until the T-204 feature branch passes final external-style review and merged-main verification.

## Next task

`M3 / T-301 — DriverRegistry`

**Status: not started. Do not begin until T-204/M2 review passes.**
