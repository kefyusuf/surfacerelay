# External Review Handoff

## Review target

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Base:** `main` at `11e7348cbee6f69fa8e502308f6db262bf78e271`
- **Head branch:** `feat/livewire-runtime-binding`
- **Scope:** T-201 only — generic RuntimeBinding model + Livewire component descriptor.
- **M2 status:** IN PROGRESS.
- **Next task:** T-202 has **not** started.

## T-201 architecture

```text
ActionDefinition
      │
      ▼
RuntimeBinding
├── bindingId
├── exact action id + version
├── driver
├── lifecycle
├── target
├── expiresAt
└── extensions
      ▲
      │
LivewireBindingTarget
├── componentId
└── method

LivewireRuntimeBinding::forComponent(...)
    → driver=livewire
    → lifecycle=component
```

Production files added:

```text
packages/laravel/src/Binding/BindingLifecycle.php
packages/laravel/src/Binding/InvalidRuntimeBinding.php
packages/laravel/src/Binding/RuntimeBinding.php
packages/laravel/src/Livewire/LivewireBindingTarget.php
packages/laravel/src/Livewire/LivewireRuntimeBinding.php
```

Tests added:

```text
packages/laravel/tests/Unit/RuntimeBindingTest.php
packages/laravel/tests/Unit/LivewireRuntimeBindingTest.php
```

Design / plan:

```text
docs/design/livewire-runtime-binding.md
docs/plans/2026-09-06-livewire-runtime-binding.md
```

## Review invariants

Please verify these boundaries directly in code:

1. `ActionDefinition` contains no Livewire-specific fields.
2. `RuntimeBinding` serializes only exact `ActionDefinition.id + version`; no version negotiation/fallback exists.
3. `bindingId` is explicit input to the descriptor and is not treated as authorization.
4. Driver grammar remains open/extensible and matches the frozen contract.
5. `BindingLifecycle` values exactly match `page/component/session/persistent`.
6. Generic `target` must be a non-empty string-keyed object.
7. `expiresAt` is null or valid RFC3339 and is preserved verbatim.
8. Extension keys use the frozen `namespace/key` grammar.
9. `LivewireBindingTarget` stores only `componentId + method` and does not store component class/name as a fallback locator.
10. `LivewireRuntimeBinding::forComponent()` fixes `driver=livewire` and `lifecycle=component`; callers cannot override either.
11. No `livewire/livewire` dependency was added.
12. No component lookup, method reflection, discovery, lifecycle producer, registry/store, invocation execution, or browser runtime behavior exists in T-201.
13. `spec/0.1` is unchanged.

## TDD evidence

RED:

```text
commit: 66041e403e423b010dc28efd84b5761fd37b2772
PHP: 219 tests / 476 assertions / 30 failures
```

The failures were deliberate existence failures for the not-yet-implemented T-201 classes. Pre-existing contract, browser, and lint jobs remained green.

Implementation commits:

```text
28364e137b0b052eeaa0ea878739963e387ba7ed
feat(laravel): add generic runtime binding model

1e572894e8f0338465fa58593130d01d736fa68b
feat(livewire): add component runtime binding descriptor
```

GREEN:

```text
contract: 52 fixture manifest entries + 12 conformance scenarios
PHP:      219 tests / 569 assertions
browser:  TypeScript typecheck + 3 Vitest tests
CI:       contract + PHP 8.3/8.4 × Illuminate 12/13 + php-lint + browser
```

All jobs passed on the implementation commit.

## Deliberate non-goals

T-201 does not implement:

- binding ID issuance/generation;
- binding registry/storage/revocation;
- lifecycle invalidation/stale lookup;
- explicit Livewire action exposure/discovery;
- `Livewire\Component` integration;
- component existence lookup;
- method reflection;
- method execution;
- ActionBus binding resolution;
- browser execution / `Livewire.find()` / `$wire.$call()`;
- current record/selection projection.

These remain assigned to later tasks.

## Decision status

Relevant accepted decisions:

- D-016 extensible binding drivers.
- D-022 exact issued binding identity; no silent retarget.
- D-023 lifecycle semantics.
- D-024 cumulative binding validity.
- D-025 stale bindings fail closed.

Still PROPOSED:

- D-026 binding failure codes until resolution behavior exists.

## Explicit statement

**T-201 is implemented and awaiting external review. T-202 has NOT started.**
