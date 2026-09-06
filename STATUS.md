# Project Status

> Current repository state for implementation and review.

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/livewire-binding-producer`
- **Main baseline:** `f5cb2c757d4de77d3cf256190f5c6af7794a8828`
- **Stage:** M0 DONE; M1 DONE; M1.1 DONE/REVIEWED; **M2 IN PROGRESS**
- **Last completed task:** `T-203 — Livewire mounted binding producer`
- **Next task:** `T-204 — End-to-end Prep List through shared ActionBus` — **not started**
- **Contract version:** `0.1-draft`
- **Spec status:** `spec/0.1` remains frozen and unchanged by T-203.
- **Contract baseline:** **52 fixture manifest entries + 12 conformance scenarios**.
- **PHP baseline:** **256 tests / 709 assertions** across PHP 8.3/8.4 × Illuminate 12/13 CI.
- **Browser baseline:** TypeScript typecheck + **3 Vitest tests**.
- **CI:** contract, four PHP matrix jobs, PHP lint, and browser checks are green on the T-203 implementation head.

## Current objective

External-style review of T-203 before beginning T-204.

## T-203 — Livewire mounted binding producer

Implemented:

```text
packages/laravel/src/Binding/
├── BindingIdGenerator.php
└── RandomBindingIdGenerator.php

packages/laravel/src/Livewire/
├── Identity/
│   ├── LivewireComponentIdentityResolver.php
│   └── MethodLivewireComponentIdentityResolver.php
└── Binding/
    ├── InvalidLivewireBindingProduction.php
    └── LivewireBindingProducer.php
```

### Issuance boundary

`LivewireBindingProducer` accepts only a trusted component object. It does not accept caller-provided `componentId` or `bindingId` values.

The producer:

1. resolves the exact component ID through the injected trusted `LivewireComponentIdentityResolver`;
2. reads exact T-202 exposures;
3. requests a fresh opaque binding ID for every exposure;
4. rejects duplicate generated IDs within the batch;
5. reuses the exact exposed `ActionDefinition` and method;
6. creates component-scoped `driver=livewire` Runtime Bindings through the reviewed T-201 factory;
7. preserves deterministic exposure ordering independent of random binding IDs.

Default produced bindings have `expiresAt=null` and no extensions.

### Component identity

The reference `MethodLivewireComponentIdentityResolver` requires an explicit callable `getId()` method and a non-empty string result. There is no class/name fallback and no caller metadata/input fallback.

### Fresh binding identity

`RandomBindingIdGenerator` uses native `random_bytes()` and opaque encoding. IDs are not derived from component ID, action ID, method name, or component name.

Each production call is a new issuance operation. Repeated production for the same component/action yields a fresh `bindingId` while preserving the same exact target. A replacement component gets its own exact component ID and new binding IDs; old immutable bindings are never retargeted.

### Livewire lifecycle authority

T-203 records D-033:

> Livewire PHP request teardown is not browser component-lifecycle authority.

Livewire reconstructs PHP component objects across requests while preserving snapshot identity, and its server-side destroy hook is request teardown rather than browser unmount. T-203 therefore does not revoke bindings from server `destroy`.

Actual browser component cleanup/stale-target resolution remains assigned to M3 client lifecycle signals such as component-scoped cleanup and exact component lookup.

### Deliberately not implemented

- no `livewire/livewire` dependency;
- no server destroy-hook invalidation;
- no browser `Livewire.find()` resolution;
- no `component.init` cleanup registration;
- no binding registry/store/revocation persistence;
- no stale-target ActionResult normalization;
- no D-026 finalization;
- no WebMCP registration;
- no binding execution or ActionBus integration;
- no T-204 application flow.

## TDD / verification evidence

RED:

```text
f1c4a1b1f1d6950c28bef14fa39f4723c9466a61
PHP: 256 tests / 681 assertions / 14 expected failures
```

Implementation:

```text
44e8cf779f79c7ac365f9d8067ee26d9584cb8fd  fresh BindingIdGenerator
2ef35c990437434ba0d38ddecf943d64e0ddda1b  trusted component identity
0280a6c369aac27f510bee874006af5fe9b20e40  mounted binding producer
6bccbc87c6449df6b6669f23c75d83b3e8c66220  test-fixture correction
```

Final observed PHP result:

```text
OK (256 tests, 709 assertions)
```

All four PHP/Illuminate matrix combinations, contract validation, PHP lint, and browser checks passed.

## Security / architecture invariants carried forward

1. Caller input cannot manufacture component target or binding identity.
2. Binding IDs are generated inside the trusted runtime boundary and remain references, not authorization proof.
3. Exact T-202 `ActionDefinition` objects and method mappings are reused without fallback.
4. Repeated issuance uses fresh IDs; replacement never silently retargets old descriptors.
5. Producer invokes no exposed component action method; only the trusted identity accessor is called.
6. PHP request teardown is not used as component lifecycle authority.
7. Frozen `spec/0.1` is unchanged.
8. D-026 remains PROPOSED until actual binding-resolution failure behavior exists.

## Decisions

- D-022..D-025 remain ACCEPTED.
- D-026 remains PROPOSED.
- D-033 ACCEPTED — Livewire PHP request teardown is not browser component-lifecycle authority; server issuance and browser lifecycle cleanup remain separate responsibilities.

## Next task

`T-204 — End-to-end Prep List through shared ActionBus`

**Do not begin T-204 until T-203 external review completes.**
