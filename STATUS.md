# Project Status

> Current repository state for implementation and review.

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `main`
- **Reviewed M2 implementation checkpoint:** `068347ac6d1bba645ab1c311daf918f87298b2e8`
- **Stage:** M0 DONE; M1 DONE; M1.1 DONE/REVIEWED; **M2 DONE/REVIEWED**; M3 TODO
- **Last completed/reviewed task:** `T-204 — Prep List shared ActionBus E2E`
- **Next task:** `T-301 — DriverRegistry` — **not started**
- **Contract version:** `0.1-draft`
- **Spec status:** `spec/0.1` remains frozen and unchanged by M2/T-204.
- **Contract baseline:** **52 fixture manifest entries + 12 conformance scenarios**.
- **PHP baseline:** **266 tests / 783 assertions**.
- **Integration matrix:** PHP 8.3/8.4 × Illuminate 12/13 × Testbench 10/11 × Livewire 4.4 — all green on both feature review checkpoint and merged `main` checkpoint.
- **Observed Livewire:** `v4.4.3` in the reviewed matrix run.
- **Browser baseline:** TypeScript typecheck + **3 Vitest tests**.

## Current objective

M2 Livewire Binding is reviewed, merged, and closed. M3/T-301 is the next task but has not started; its DriverRegistry boundary must be designed before implementation.

## M2 — Livewire Binding — DONE / REVIEWED

### T-201 — RuntimeBinding descriptor

Delivered generic immutable RuntimeBinding modeling plus exact Livewire component target data. Livewire descriptors fix `driver=livewire` and `lifecycle=component`, preserve exact action identity, and do not silently retarget or fallback.

### T-202 — Explicit action exposure

Delivered method-level `#[ExposeAction(id, version)]` allow-list declarations and exact registry-backed exposure resolution. Public framework methods are not automatically SurfaceRelay exposures; exposure remains separate from discovery/invocation authorization.

### T-203 — Mounted binding producer

Delivered trusted component identity resolution, fresh opaque binding IDs, deterministic exposure-to-binding production, exact target retention, and D-033 lifecycle separation. PHP request teardown is not treated as browser component unmount.

### T-204 — Shared ActionBus E2E

T-204 proves the Livewire vertical's central application-path claim:

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

The binding-derived test is intentionally not the M3 browser driver. It uses a real Livewire component instance, T-203 binding production, and then invokes exactly the produced `target.method` through the real Livewire testing API.

### T-204 production additions

Only protocol-neutral runtime pieces were added under `src/`:

```text
packages/laravel/src/Contracts/ActionExecutor.php
packages/laravel/src/Runtime/Pipeline/ActionExecutionStage.php
```

`ActionExecutionStage` passes the exact resolved definition, current pipeline input and trusted context to the injected executor, records the executor result as real output including `null`, and lets application exceptions propagate.

No agent-only endpoint, controller, transport, surface-specific executor, or duplicate business path exists.

### Real Livewire integration evidence

Livewire and Testbench are development-only dependencies:

```text
livewire/livewire ^4.4
orchestra/testbench ^10|^11
```

`livewire/livewire` is absent from production Composer `require`.

The reviewed CI matrix is:

```text
PHP 8.3 × Illuminate 12 × Testbench 10 × Livewire 4.4
PHP 8.3 × Illuminate 13 × Testbench 11 × Livewire 4.4
PHP 8.4 × Illuminate 12 × Testbench 10 × Livewire 4.4
PHP 8.4 × Illuminate 13 × Testbench 11 × Livewire 4.4
```

All four cells passed on the exact feature review checkpoint and again after fast-forward to `main`.

### Prep List proof

All application/reference code remains test-only under `packages/laravel/tests/Fixtures/PrepList/`.

The integration suite proves:

1. real human Livewire method invocation traverses shared ActionBus and mutates once;
2. T-203 binding production from the real mounted instance returns exact action/component/method identity;
3. calling exactly the binding target method through the real Livewire harness traverses the same ActionBus and same `AddPrepListItem` mutation;
4. fresh human and binding-derived runs produce equivalent state;
5. real validation runs before authorization/execution;
6. real authorization receives validated input and trusted BrowserSession context;
7. BrowserSession authority is never part of action input;
8. invalid input halts before authorization and mutation;
9. Livewire component dependencies use real `boot()` lifecycle injection rather than component-owned constructor/service-locator wiring.

Confirmation, idempotency, output policy and audit implementations remain M4 work. T-204 uses explicitly test-only placeholders for those stages and makes no production-safety claim about them.

## TDD / verification evidence

```text
Execution RED:   868eb1f3dc89e47023af95217bd44279b7a80994
Execution GREEN: 7267a43d6ede657d52cffc0d8a96f047f6c885af
E2E RED:         75022ae6594dfcabfd33bec89825d51459d0b8fa
Prep fixture:    f1eca5290d4ddbbd4b36990feddf76e20cc76f1c
Testbench key:   e7e6a9809d647070aff78105285ac08da0b4a03b
Review/merge:    068347ac6d1bba645ab1c311daf918f87298b2e8
```

RED evidence:

```text
ActionExecutionStage: 260 tests / 713 assertions / 4 deliberate failures
Prep List E2E:        266 tests / 735 assertions / 6 deliberate failures
```

Final evidence:

```text
PHP:      266 tests / 783 assertions
contract: 52 fixture manifest entries + 12 conformance scenarios
browser:  TypeScript typecheck + 3 Vitest tests
CI:       all PHP/Illuminate/Testbench/Livewire cells + contract + php-lint + browser GREEN
```

## Decisions

- D-022..D-025 remain ACCEPTED.
- D-026 remains PROPOSED until actual binding-resolution failure behavior exists.
- D-033 ACCEPTED — PHP request teardown is not browser component-lifecycle authority.
- D-034 ACCEPTED — human Livewire interaction and binding-derived invocation converge at the same explicit component method and shared ActionBus/application action; the future browser driver selects that existing target rather than creating an agent-only business endpoint.

## Next task

`M3 / T-301 — DriverRegistry`

**Status: TODO / not started.**
