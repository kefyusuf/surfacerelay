# External Review Record

## Review status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Reviewed task:** `T-202 — Explicit Livewire action exposure API`
- **Reviewed/merged main commit:** `40dce9f460c1c6e83dc9a8b8548abe0c49ae4f77`
- **Result:** **PASSED**
- **M2 status:** IN PROGRESS.
- **Next task:** T-203 has **not** started.

## Reviewed architecture

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

Production files reviewed:

```text
packages/laravel/src/Livewire/Attributes/ExposeAction.php
packages/laravel/src/Livewire/Exposure/InvalidLivewireActionExposure.php
packages/laravel/src/Livewire/Exposure/LivewireActionExposure.php
packages/laravel/src/Livewire/Exposure/LivewireActionExposureReader.php
```

Test reviewed:

```text
packages/laravel/tests/Unit/LivewireActionExposureReaderTest.php
```

Design / plan:

```text
docs/design/livewire-action-exposure.md
docs/plans/2026-09-06-livewire-action-exposure.md
```

## Accepted review invariants

1. Only explicit `#[ExposeAction]` methods become exposure candidates.
2. Unannotated public methods are ignored; there is no expose-all-public-methods path.
3. Exposure is not discovery authorization and not invocation authorization.
4. The reader never invokes reflected component methods.
5. Parent-only exposure metadata does not propagate automatically to a child component.
6. Annotated protected/private/static concrete methods fail loudly as configuration errors.
7. Duplicate attributes and duplicate exact action identity mappings fail loudly.
8. Exact `ActionRegistry::get(id, version)` resolution is used; no latest/same-ID/fuzzy fallback exists.
9. Different versions of one action remain distinct exact identities.
10. Results are deterministic by action ID, version, and method.
11. The exact registered `ActionDefinition` object is reused.
12. No RuntimeBinding, component ID, binding ID, lifecycle, stale state, or execution behavior is introduced by T-202.
13. No `livewire/livewire` Composer dependency is introduced.
14. `spec/0.1` is unchanged.

## TDD evidence

RED:

```text
35850ce5c2bb8bdb78dda7bf63791f46d7cbf4ef
PHP: 242 tests / 592 assertions / 15 deliberate failures
```

Implementation/review:

```text
4a79e6302ea0f138b28bd6a8646fc19d88274cfa  exposure vocabulary
471769c7eda6767e6bf19b08d3cdd828e2c8053d  exposure reader
2fb1fffe9a5bb966b1c4629c676bf30d8ac22b8f  exact registry lookup review refactor
40dce9f460c1c6e83dc9a8b8548abe0c49ae4f77  reviewed checkpoint merged to main
```

Final evidence:

```text
contract: 52 fixture manifest entries + 12 conformance scenarios
PHP:      242 tests / 667 assertions
browser:  TypeScript typecheck + 3 Vitest tests
CI:       contract + PHP 8.3/8.4 × Illuminate 12/13 + php-lint + browser
```

The final feature-tree CI and the merge-to-main CI both passed.

## Deliberate non-goals retained

T-202 did not implement:

- RuntimeBinding issuance;
- binding ID generation;
- mounted component ID resolution;
- binding registry/storage/revocation;
- lifecycle invalidation/stale detection;
- discovery authorization;
- invocation authorization changes;
- Livewire method execution;
- ActionBus binding integration;
- browser execution;
- current record/selection projection;
- method-signature → ActionDefinition compilation.

## Decision status

No decision-register changes were required by T-202. D-026 remains PROPOSED until binding-resolution failure behavior exists.

## Explicit statement

**T-202 REVIEW PASSED and is merged to `main`. T-203 is TODO and has not started.**
