# External Review Handoff

## Review target

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Base:** `main` at `f5cb2c757d4de77d3cf256190f5c6af7794a8828`
- **Head branch:** `feat/livewire-binding-producer`
- **Scope:** T-203 only — fresh binding ID generation, trusted mounted component identity resolution, and exact Livewire RuntimeBinding production from T-202 exposures.
- **M2 status:** IN PROGRESS.
- **Next task:** T-204 has **not** started.

## T-203 architecture

```text
trusted mounted component object
        │
        ├── LivewireComponentIdentityResolver
        │       └── exact getId()
        │
        └── LivewireActionExposureReader
                └── exact T-202 exposures
                         │
                         ▼
                  BindingIdGenerator
                         │ fresh opaque ID
                         ▼
                LivewireBindingProducer
                         │
                         ▼
                 RuntimeBinding
                 driver=livewire
                 lifecycle=component
                 target.componentId=exact ID
                 target.method=explicit exposure
```

Production files added:

```text
packages/laravel/src/Binding/BindingIdGenerator.php
packages/laravel/src/Binding/RandomBindingIdGenerator.php
packages/laravel/src/Livewire/Identity/LivewireComponentIdentityResolver.php
packages/laravel/src/Livewire/Identity/MethodLivewireComponentIdentityResolver.php
packages/laravel/src/Livewire/Binding/InvalidLivewireBindingProduction.php
packages/laravel/src/Livewire/Binding/LivewireBindingProducer.php
```

Test added:

```text
packages/laravel/tests/Unit/LivewireBindingProducerTest.php
```

Design / plan:

```text
docs/design/livewire-binding-producer.md
docs/plans/2026-09-06-livewire-binding-producer.md
```

## Review invariants

Please verify directly in code:

1. Producer accepts a trusted component object, never caller `componentId` or `bindingId` parameters.
2. Reference component identity resolution requires a real callable `getId()` method and a non-empty string result.
3. No component class/name fallback locator exists.
4. T-202 exposure reader remains the only action/method mapping source; no method inference or version fallback exists.
5. Exact registered `ActionDefinition` objects are reused unchanged.
6. Each exposure receives a newly generated opaque binding ID.
7. Random IDs are not derived from action ID, method, component ID, or component name.
8. Duplicate generated IDs within one issuance batch fail loudly.
9. Repeated production for the same component issues fresh IDs.
10. Replacement component IDs produce new exact targets and do not mutate or retarget old bindings.
11. Output order follows deterministic T-202 exposure order, not random binding IDs.
12. Produced bindings remain `driver=livewire`, `lifecycle=component`, `expiresAt=null`, with empty extensions by default.
13. Producer never invokes exposed action methods; only the trusted identity accessor is invoked.
14. No `livewire/livewire` Composer dependency was added.
15. No server-side Livewire `destroy` hook invalidation exists.
16. No browser binding registry, `Livewire.find()`, WebMCP registration, stale-target resolution, or ActionBus execution exists.
17. `spec/0.1` is unchanged.
18. D-033 correctly records that PHP request teardown is not browser component-lifecycle authority.

## Lifecycle review finding

Current Livewire behavior reconstructs PHP component objects from snapshots on subsequent requests while preserving the snapshot component ID. Livewire's internal server `destroy` hook runs at the end of mount/update request processing, so it cannot be treated as browser unmount authority.

T-203 therefore deliberately does **not** revoke component bindings from server request teardown. Browser component cleanup/stale-target resolution remains assigned to M3 client lifecycle signals.

## TDD evidence

RED:

```text
f1c4a1b1f1d6950c28bef14fa39f4723c9466a61
test(livewire): define T-203 binding production behavior

PHP: 256 tests / 681 assertions / 14 deliberate failures
```

All failures were new T-203 existence failures. Contract/browser/lint remained green.

Implementation:

```text
44e8cf779f79c7ac365f9d8067ee26d9584cb8fd
feat(binding): add fresh binding ID generation

2ef35c990437434ba0d38ddecf943d64e0ddda1b
feat(livewire): resolve trusted component identity

0280a6c369aac27f510bee874006af5fe9b20e40
feat(livewire): produce mounted component bindings

6bccbc87c6449df6b6669f23c75d83b3e8c66220
test(livewire): fix T-203 identity resolver fixture
```

Final evidence:

```text
contract: 52 fixture manifest entries + 12 conformance scenarios
PHP:      256 tests / 709 assertions
browser:  TypeScript typecheck + 3 Vitest tests
CI:       contract + PHP 8.3/8.4 × Illuminate 12/13 + php-lint + browser
```

All matrix jobs passed on the implementation head.

## Decision status

- D-022..D-025 remain ACCEPTED.
- D-026 remains PROPOSED until actual binding-resolution failure behavior exists.
- D-033 ACCEPTED — Livewire PHP request teardown is not browser component-lifecycle authority; server binding issuance and browser lifecycle cleanup/stale resolution are separate responsibilities.

## Deliberate non-goals retained

T-203 does not implement:

- browser component lookup;
- component init/cleanup registration;
- binding registry/persistence/revocation store;
- stale-target ActionResult normalization;
- D-026 final error-code behavior;
- WebMCP registration;
- binding execution;
- ActionBus integration;
- Prep List end-to-end flow;
- current record/selection projection.

## Explicit statement

**T-203 implementation is complete and CI is green. T-204 has NOT started. The next action is external-style review of this branch.**
