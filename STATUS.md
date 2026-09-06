# Project Status

> Current repository state for implementation and review.

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `main`
- **Reviewed main baseline:** `40dce9f460c1c6e83dc9a8b8548abe0c49ae4f77`
- **Stage:** M0 DONE; M1 DONE; M1.1 DONE/REVIEWED; **M2 IN PROGRESS**
- **Last completed/reviewed task:** `T-202 — Explicit Livewire action exposure API`
- **Next task:** `T-203 — Livewire binding lifecycle producer` — **not started**
- **Contract version:** `0.1-draft`
- **Spec status:** `spec/0.1` remains frozen and unchanged by T-202.
- **Contract baseline:** **52 fixture manifest entries + 12 conformance scenarios**.
- **PHP baseline:** **242 tests / 667 assertions** across PHP 8.3/8.4 × Illuminate 12/13 CI.
- **Browser baseline:** TypeScript typecheck + **3 Vitest tests**.
- **CI:** contract, four PHP matrix jobs, PHP lint, and browser checks are green on the reviewed T-202 main baseline.

## Current objective

T-202 is reviewed, merged, and closed. T-203 is the next task but has not started; its design boundary must be reviewed before implementation.

## T-202 — Explicit Livewire action exposure

Implemented:

```text
packages/laravel/src/Livewire/
├── Attributes/
│   └── ExposeAction.php
└── Exposure/
    ├── InvalidLivewireActionExposure.php
    ├── LivewireActionExposure.php
    └── LivewireActionExposureReader.php
```

### Exposure declaration

A method is a SurfaceRelay exposure candidate only when it explicitly carries:

```php
#[ExposeAction(id: 'action.id', version: 1)]
```

An ordinary public method is ignored. Public framework callability is not treated as SurfaceRelay exposure authority.

### Reader invariants

`LivewireActionExposureReader`:

- inspects explicit attributes but never invokes component methods;
- accepts only concrete-class public instance declarations;
- does not automatically inherit a parent-only exposure annotation;
- rejects annotated protected/private/static methods as configuration errors;
- rejects duplicate `ExposeAction` declarations on one method;
- resolves exact registered definitions with `ActionRegistry::get(id, version)`;
- performs no latest-version, fuzzy, same-ID, or method-name fallback;
- rejects one exact action identity mapped to multiple methods;
- preserves different versions of one action as distinct identities;
- returns deterministic ordering by action ID, version, then method;
- reuses the exact registered `ActionDefinition` object.

Exposure remains strictly weaker than discovery authorization and invocation authorization.

### Deliberately not implemented

- no `livewire/livewire` dependency;
- no mounted component ID lookup;
- no RuntimeBinding issuance;
- no binding ID generation;
- no binding registry/revocation/stale tracking;
- no discovery authorization policy;
- no invocation authorization change;
- no component method execution;
- no ActionBus integration;
- no browser-runtime execution;
- no automatic Action Definition creation from reflected methods.

These remain later-task concerns, beginning with T-203.

## TDD / verification evidence

RED:

```text
35850ce5c2bb8bdb78dda7bf63791f46d7cbf4ef
PHP: 242 tests / 592 assertions / 15 expected failures
```

Implementation/review commits:

```text
4a79e6302ea0f138b28bd6a8646fc19d88274cfa  exposure vocabulary
471769c7eda6767e6bf19b08d3cdd828e2c8053d  exposure reader
2fb1fffe9a5bb966b1c4629c676bf30d8ac22b8f  exact registry lookup refactor
40dce9f460c1c6e83dc9a8b8548abe0c49ae4f77  reviewed T-202 checkpoint merged to main
```

Final observed PHP result:

```text
OK (242 tests, 667 assertions)
```

All PHP/Illuminate matrix combinations, contract validation, PHP lint, and browser checks passed on the merged main checkpoint.

## Security / architecture invariants carried forward

1. Explicit exposure is an allow-list declaration, never public-method auto-exposure.
2. Exposure is not discovery authorization and not invocation authorization.
3. Exact Action Definition `id + version` is required; no version fallback exists.
4. Parent-class exposure metadata does not silently propagate to child components.
5. Reader reflection never invokes component code.
6. `ActionDefinition` remains free of Livewire-specific target/method identity.
7. T-202 issues no RuntimeBinding and creates no trusted runtime authority.
8. Frozen `spec/0.1` is unchanged.

## Decisions

Existing accepted decisions remain unchanged. D-026 remains PROPOSED until binding-resolution failure behavior exists.

## Next task

`T-203 — Implement Livewire binding lifecycle producer`

**Status: TODO / not started.**
