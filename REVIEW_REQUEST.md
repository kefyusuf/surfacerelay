# External Review Handoff

## Review target

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Base:** `main` at `2f474f2f2362cbef2969724d0aa445d232c78d30`
- **Head branch:** `feat/livewire-action-exposure`
- **Scope:** T-202 only — explicit method-level Livewire action exposure declaration and exact registry-backed exposure resolution.
- **M2 status:** IN PROGRESS.
- **Next task:** T-203 has **not** started.

## T-202 architecture

```text
concrete component
      │
      ▼
method #[ExposeAction(id, version)]
      │
      ▼
LivewireActionExposureReader
      │ exact ActionRegistry::get(id, version)
      ▼
LivewireActionExposure
├── exact ActionDefinition object
└── concrete method name
      │
      ▼
T-203 (not implemented)
```

Production files added:

```text
packages/laravel/src/Livewire/Attributes/ExposeAction.php
packages/laravel/src/Livewire/Exposure/InvalidLivewireActionExposure.php
packages/laravel/src/Livewire/Exposure/LivewireActionExposure.php
packages/laravel/src/Livewire/Exposure/LivewireActionExposureReader.php
```

Tests added:

```text
packages/laravel/tests/Unit/LivewireActionExposureReaderTest.php
```

Design / plan:

```text
docs/design/livewire-action-exposure.md
docs/plans/2026-09-06-livewire-action-exposure.md
```

## Review invariants

Please verify directly in code:

1. Only explicit `#[ExposeAction]` methods become exposure candidates.
2. Unannotated public methods are ignored; there is no expose-all-public-methods path.
3. Exposure is not discovery authorization and not invocation authorization.
4. The reader never invokes reflected component methods.
5. Only an annotation declared by the concrete inspected class is accepted; parent-only exposure does not propagate automatically.
6. Annotated protected/private/static concrete methods fail loudly as configuration errors.
7. Duplicate `ExposeAction` attributes on one method fail through `InvalidLivewireActionExposure` before attribute instantiation.
8. Exact `ActionRegistry::get(id, version)` resolution is used; no latest/same-ID/fuzzy fallback exists.
9. Different versions of one action remain distinct exact identities.
10. One exact action identity cannot map to multiple methods on the same component.
11. Results are deterministic by action ID, version, method.
12. The exact registered `ActionDefinition` object is reused; no second definition is constructed or mutated.
13. No RuntimeBinding, component ID, binding ID, lifecycle, stale state, or execution behavior is introduced.
14. No `livewire/livewire` Composer dependency is introduced.
15. `spec/0.1` is unchanged.

## TDD evidence

RED:

```text
35850ce5c2bb8bdb78dda7bf63791f46d7cbf4ef
test(livewire): define T-202 explicit exposure behavior

PHP: 242 tests / 592 assertions / 15 failures
```

The 15 failures were deliberate existence failures for the new T-202 classes. Pre-existing contract and browser checks remained green.

Implementation:

```text
4a79e6302ea0f138b28bd6a8646fc19d88274cfa
feat(livewire): add explicit exposure vocabulary

471769c7eda6767e6bf19b08d3cdd828e2c8053d
feat(livewire): resolve explicit action exposures

2fb1fffe9a5bb966b1c4629c676bf30d8ac22b8f
refactor(livewire): resolve exposure with exact registry lookup
```

Final evidence:

```text
contract: 52 fixture manifest entries + 12 conformance scenarios
PHP:      242 tests / 667 assertions
browser:  TypeScript typecheck + 3 Vitest tests
CI:       contract + PHP 8.3/8.4 × Illuminate 12/13 + php-lint + browser
```

All jobs passed on the exact-lookup implementation head.

## Deliberate non-goals

T-202 does not implement:

- RuntimeBinding issuance;
- binding ID generation;
- mounted component ID resolution;
- binding registry/storage/revocation;
- lifecycle invalidation/stale detection;
- discovery authorization;
- invocation authorization changes;
- Livewire method execution;
- ActionBus binding integration;
- browser `Livewire.find()` / `$wire.$call()` execution;
- current record/selection projection;
- method-signature → ActionDefinition compilation.

## Decision status

No decision-register changes were required by T-202. D-026 remains PROPOSED until binding-resolution failure behavior exists.

## Explicit statement

**T-202 is implemented and final CI is green. T-203 has NOT started.**
