# External Review Record

## Review status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Reviewed task:** `T-203 — Livewire mounted binding producer`
- **Reviewed/merged implementation checkpoint:** `bd69e135cbabb1b3828c51c9cef2293737e8a5be`
- **Result:** **PASSED**
- **M2 status:** IN PROGRESS.
- **Next task:** T-204 has **not** started.

## Reviewed architecture

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
                 exact componentId + method
```

Production files reviewed:

```text
packages/laravel/src/Binding/BindingIdGenerator.php
packages/laravel/src/Binding/RandomBindingIdGenerator.php
packages/laravel/src/Livewire/Identity/LivewireComponentIdentityResolver.php
packages/laravel/src/Livewire/Identity/MethodLivewireComponentIdentityResolver.php
packages/laravel/src/Livewire/Binding/InvalidLivewireBindingProduction.php
packages/laravel/src/Livewire/Binding/LivewireBindingProducer.php
```

Test reviewed:

```text
packages/laravel/tests/Unit/LivewireBindingProducerTest.php
```

Design / plan:

```text
docs/design/livewire-binding-producer.md
docs/plans/2026-09-06-livewire-binding-producer.md
```

## Accepted review invariants

1. Caller input does not supply component target identity or binding identity at the producer boundary.
2. The reference resolver requires a real callable `getId()` and a non-empty string result; no metadata, component-name, or class-name fallback exists.
3. Exact T-202 exposure declarations remain the only action/method source.
4. Exact registered `ActionDefinition` objects are reused; there is no action-version fallback or method guessing.
5. Every exposure requests a fresh opaque ID through `BindingIdGenerator`.
6. The default generator uses native 128-bit random bytes and does not derive IDs from action/component/method identity.
7. Duplicate generated IDs inside one issuance batch fail loudly.
8. Repeated issuance for the same component creates new binding IDs while preserving the exact target.
9. Replacement components produce new exact targets; old immutable bindings are never retargeted.
10. Produced order follows deterministic exposure order, not random ID ordering.
11. Produced bindings are fixed to `driver=livewire` and `lifecycle=component` with null expiry and empty extensions by default.
12. Producer invokes no exposed application action method; only the trusted identity accessor is called.
13. No server `destroy` lifecycle invalidation was introduced.
14. No `livewire/livewire` dependency, browser registry, stale-target resolver, WebMCP registration, binding execution, or ActionBus integration was introduced.
15. `spec/0.1` is unchanged.
16. D-033 correctly separates PHP request teardown from browser component-lifecycle authority.

## Lifecycle review finding

Current Livewire behavior re-creates PHP component objects from snapshots on subsequent requests while preserving the component ID. The server-side request teardown/destroy point is therefore not equivalent to browser component unmount.

D-033 is accepted: server binding production owns exact fresh issuance; browser component cleanup and stale-target resolution belong to the client lifecycle. Server teardown must not revoke or retarget component bindings.

## TDD evidence

RED:

```text
f1c4a1b1f1d6950c28bef14fa39f4723c9466a61
PHP: 256 tests / 681 assertions / 14 deliberate T-203 failures
```

Implementation/review:

```text
44e8cf779f79c7ac365f9d8067ee26d9584cb8fd  fresh binding ID generation
2ef35c990437434ba0d38ddecf943d64e0ddda1b  trusted component identity
0280a6c369aac27f510bee874006af5fe9b20e40  mounted binding producer
6bccbc87c6449df6b6669f23c75d83b3e8c66220  test fixture correction
bd69e135cbabb1b3828c51c9cef2293737e8a5be  reviewed checkpoint merged to main
```

Final evidence:

```text
contract: 52 fixture manifest entries + 12 conformance scenarios
PHP:      256 tests / 709 assertions
browser:  TypeScript typecheck + 3 Vitest tests
CI:       contract + PHP 8.3/8.4 × Illuminate 12/13 + php-lint + browser
```

The exact feature review checkpoint passed all jobs, and the same checkpoint passed all jobs again after fast-forward to `main`.

## Review nuance carried forward

Cross-batch/global binding-ID uniqueness is not implemented with a stateful registry in T-203. The producer relies on the `BindingIdGenerator` freshness contract and the default 128-bit random generator, while rejecting duplicates inside one issuance batch. Existence/revocation persistence and definitive stale resolution remain later runtime responsibilities.

This does not authorize ID reuse: D-022 remains normative and implementations of `BindingIdGenerator` are required to produce fresh identifiers.

## Decision status

- D-022..D-025 remain ACCEPTED.
- D-026 remains PROPOSED until real binding-resolution failure behavior exists.
- D-033 ACCEPTED — PHP request teardown is not browser component-lifecycle authority; server issuance and browser lifecycle cleanup/stale resolution are separate responsibilities.

## Explicit statement

**T-203 REVIEW PASSED and is merged to `main`. T-204 is TODO and has not started.**
