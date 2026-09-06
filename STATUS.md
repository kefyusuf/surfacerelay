# Project Status

> Current repository state for implementation and external review.

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/livewire-action-exposure`
- **Main baseline:** `2f474f2f2362cbef2969724d0aa445d232c78d30`
- **Stage:** M0 DONE; M1 DONE; M1.1 DONE/REVIEWED; **M2 IN PROGRESS**
- **Last completed task:** `T-202 — Explicit Livewire action exposure API`
- **Next task:** `T-203 — Livewire binding lifecycle producer` — **not started**
- **Contract version:** `0.1-draft`
- **Spec status:** `spec/0.1` remains frozen and unchanged by T-202.
- **Contract baseline:** **52 fixture manifest entries + 12 conformance scenarios**.
- **PHP baseline:** **242 tests / 667 assertions** across PHP 8.3/8.4 × Illuminate 12/13 CI.
- **Browser baseline:** TypeScript typecheck + **3 Vitest tests**.
- **CI jobs:** contract, four PHP matrix jobs, PHP lint, browser typecheck/tests — all green for final T-202 implementation head.

## Current objective

External-style review of T-202 before beginning T-203.

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

An ordinary public method is ignored. Public Livewire callability is not treated as SurfaceRelay exposure authority.

### Reader invariants

`LivewireActionExposureReader`:

- inspects attributes but never invokes component methods;
- accepts only concrete-class public instance declarations;
- does not automatically inherit a parent-only exposure annotation;
- rejects annotated protected/private/static methods as configuration errors;
- rejects duplicate `ExposeAction` declarations on one method;
- resolves the exact registered Action Definition with `ActionRegistry::get(id, version)`;
- does not perform latest-version/fuzzy/method-name fallback;
- rejects the same exact action identity mapped to more than one method;
- preserves different versions of the same action as separate exact identities;
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
test(livewire): define T-202 explicit exposure behavior

PHP: 242 tests / 592 assertions / 15 expected failures
```

All 15 failures were the new T-202 tests proving the exposure classes did not yet exist. Contract and browser checks remained green.

Implementation:

```text
4a79e6302ea0f138b28bd6a8646fc19d88274cfa
feat(livewire): add explicit exposure vocabulary

471769c7eda6767e6bf19b08d3cdd828e2c8053d
feat(livewire): resolve explicit action exposures

2fb1fffe9a5bb966b1c4629c676bf30d8ac22b8f
refactor(livewire): resolve exposure with exact registry lookup
```

Final observed PHP result:

```text
OK (242 tests, 667 assertions)
```

All PHP/Illuminate matrix combinations, contract validation, PHP lint, and browser checks passed.

## Security / architecture invariants carried forward

1. Explicit exposure is an allow-list declaration, not reflection-based public-method exposure.
2. Exposure is not discovery authorization and not invocation authorization.
3. Exact Action Definition `id + version` is required; no version fallback exists.
4. Parent-class exposure metadata does not silently propagate to child components.
5. Reader reflection never invokes component code.
6. ActionDefinition remains free of Livewire-specific target/method identity.
7. T-202 issues no RuntimeBinding and creates no trusted runtime authority.
8. Frozen `spec/0.1` is unchanged.

## Decisions

Existing accepted decisions remain unchanged. D-026 remains PROPOSED until binding-resolution failure behavior exists.

## Next task

`T-203 — Implement Livewire binding lifecycle producer`

**Do not begin T-203 until T-202 review completes.**
