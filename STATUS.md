# Project Status

> Current repository state for implementation and external review.

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/livewire-runtime-binding`
- **Reviewed main baseline:** `11e7348cbee6f69fa8e502308f6db262bf78e271`
- **Stage:** M0 DONE; M1 DONE; M1.1 DONE/REVIEWED; **M2 IN PROGRESS**
- **Last completed task:** `T-201 — Livewire RuntimeBinding descriptor`
- **Next task:** `T-202 — Explicit Livewire action exposure API` — **not started**
- **Contract version:** `0.1-draft`
- **Spec status:** `spec/0.1` remains frozen; T-201 required no schema change.
- **Contract baseline:** **52 fixture manifest entries + 12 conformance scenarios**.
- **PHP baseline:** **219 tests / 569 assertions** across PHP 8.3/8.4 × Illuminate 12/13 CI.
- **Browser baseline:** TypeScript typecheck + **3 Vitest tests**.
- **CI jobs:** contract, four PHP matrix jobs, PHP lint, browser typecheck/tests — all green for T-201 implementation commit.

## Current objective

External review of T-201 before beginning T-202.

## T-201 — Runtime Binding descriptor

Implemented:

```text
packages/laravel/src/Binding/
├── BindingLifecycle.php
├── InvalidRuntimeBinding.php
└── RuntimeBinding.php

packages/laravel/src/Livewire/
├── LivewireBindingTarget.php
└── LivewireRuntimeBinding.php
```

### Generic RuntimeBinding

`RuntimeBinding` is the protocol-neutral PHP representation of the frozen Runtime Binding contract. It carries:

- explicit `bindingId`;
- exact `ActionDefinition` reference, serialized as `action.id + version`;
- extensible driver string;
- frozen lifecycle enum;
- non-empty driver-owned target object;
- optional RFC3339 expiry;
- namespaced extensions.

It validates construction only. It does not resolve, authorize, discover, execute, refresh, revoke, store, or silently retarget bindings.

### Livewire descriptor

`LivewireBindingTarget` contains only:

```text
componentId
method
```

`LivewireRuntimeBinding::forComponent(...)` always produces:

```text
driver    = livewire
lifecycle = component
```

Neither value is caller-configurable.

The descriptor uses exact mounted-component identity only. No component class/name fallback is stored.

### Deliberately not implemented

- no `livewire/livewire` dependency;
- no component lookup;
- no method reflection;
- no binding ID generation;
- no binding registry/store;
- no stale/lifecycle producer;
- no action discovery/exposure API;
- no Livewire execution;
- no browser-runtime change;
- no ActionDefinition Livewire fields;
- no action-version fallback.

These boundaries remain assigned to T-202/T-203/T-204/T-304.

## T-201 verification

TDD evidence:

1. RED commit: `66041e403e423b010dc28efd84b5761fd37b2772`
   - 219 tests executed;
   - 30 expected T-201 failures because descriptor classes did not exist;
   - pre-existing tests, contract, lint, and browser jobs remained green.
2. Generic model commit: `28364e137b0b052eeaa0ea878739963e387ba7ed`.
3. Livewire descriptor commit: `1e572894e8f0338465fa58593130d01d736fa68b`.
4. Final implementation CI: all jobs green.

Observed PHP result on PHP 8.3 + Illuminate 12:

```text
OK (219 tests, 569 assertions)
```

The other three PHP/Illuminate matrix combinations also passed.

## Security / architecture invariants carried forward

1. Caller input still cannot manufacture trusted actor, tenant, selection, confirmation, or binding authority.
2. A binding ID remains a reference, never authorization by itself.
3. RuntimeBinding action identity is exact `id + version`; there is no latest/fallback behavior.
4. Livewire component target identity is exact instance ID; no replacement lookup exists.
5. `driver=livewire` and `lifecycle=component` are fixed by the Livewire factory.
6. T-201 introduces no discovery/exposure mechanism and cannot expose public methods automatically.
7. Frozen `spec/0.1` is unchanged.

## Decisions

- D-016 ACCEPTED — extensible binding drivers; unsupported drivers fail closed when runtime resolution exists.
- D-017 ACCEPTED — `current_selection` is trusted runtime context.
- D-018 ACCEPTED — Apache-2.0.
- D-021 SUPERSEDED by D-032.
- D-022..D-025 ACCEPTED — binding identity/lifecycle/stale semantics.
- D-026 remains PROPOSED until binding-resolution failure behavior is implemented.
- D-032 ACCEPTED — output sensitivity/content trust are independent.

## Next task

`T-202 — Implement explicit Livewire action exposure API`

**Do not begin T-202 until T-201 external review completes.**
