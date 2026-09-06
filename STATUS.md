# Project Status

> Current repository state for implementation and review.

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `main`
- **Reviewed T-203 implementation checkpoint:** `bd69e135cbabb1b3828c51c9cef2293737e8a5be`
- **Stage:** M0 DONE; M1 DONE; M1.1 DONE/REVIEWED; **M2 IN PROGRESS**
- **Last completed/reviewed task:** `T-203 — Livewire mounted binding producer`
- **Next task:** `T-204 — End-to-end Prep List through shared ActionBus` — **not started**
- **Contract version:** `0.1-draft`
- **Spec status:** `spec/0.1` remains frozen and unchanged by T-203.
- **Contract baseline:** **52 fixture manifest entries + 12 conformance scenarios**.
- **PHP baseline:** **256 tests / 709 assertions** across PHP 8.3/8.4 × Illuminate 12/13 CI.
- **Browser baseline:** TypeScript typecheck + **3 Vitest tests**.
- **CI:** contract, four PHP matrix jobs, PHP lint, and browser checks are green on both the T-203 feature checkpoint and merged `main` checkpoint.

## Current objective

T-203 is reviewed, merged, and closed. T-204 is next but has not started; its end-to-end execution boundary must be designed before implementation.

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

### Reviewed invariants

1. Producer accepts a trusted component object; caller input supplies neither `componentId` nor `bindingId`.
2. Reference identity resolution requires a real callable `getId()` and a non-empty string result; there is no component-name/class fallback.
3. T-202 exact exposures are the only action/method mapping source.
4. Exact registered `ActionDefinition` objects are reused; no version or method fallback exists.
5. Every exposure requests a fresh opaque binding ID; the default generator uses native `random_bytes()` and does not derive identity from action/component/method data.
6. Duplicate generated IDs inside one issuance batch fail loudly.
7. Repeated issuance for the same component produces new binding IDs while retaining the exact target.
8. Replacement components produce new exact targets; old immutable bindings are never retargeted.
9. Output ordering follows deterministic T-202 exposure order rather than random IDs.
10. Produced bindings remain `driver=livewire`, `lifecycle=component`, `expiresAt=null`, with empty extensions by default.
11. Producer never invokes exposed action methods; only the trusted identity accessor is called.
12. Livewire server request teardown is not used as browser component lifecycle authority.
13. No `livewire/livewire` dependency, browser binding registry, stale resolver, WebMCP registration, or ActionBus execution was introduced.
14. `spec/0.1` is unchanged.

### D-033 lifecycle boundary

Livewire re-creates PHP component objects from snapshots on later requests while preserving component identity. Its server-side request teardown is therefore not equivalent to browser component unmount.

D-033 is ACCEPTED: server binding production owns fresh exact issuance; browser registration cleanup and stale-target resolution remain client-lifecycle responsibilities. Server `destroy` is not used to revoke or retarget component bindings.

## TDD / verification evidence

```text
RED:                    f1c4a1b1f1d6950c28bef14fa39f4723c9466a61
Binding ID generation:  44e8cf779f79c7ac365f9d8067ee26d9584cb8fd
Trusted identity:       2ef35c990437434ba0d38ddecf943d64e0ddda1b
Producer:               0280a6c369aac27f510bee874006af5fe9b20e40
Test fixture correction:6bccbc87c6449df6b6669f23c75d83b3e8c66220
Reviewed checkpoint:    bd69e135cbabb1b3828c51c9cef2293737e8a5be
```

RED evidence:

```text
256 tests / 681 assertions / 14 deliberate T-203 failures
```

Final evidence:

```text
PHP:      256 tests / 709 assertions
contract: 52 fixture manifest entries + 12 conformance scenarios
browser:  TypeScript typecheck + 3 Vitest tests
CI:       contract + PHP 8.3/8.4 × Illuminate 12/13 + php-lint + browser
```

All jobs passed on the exact T-203 review checkpoint and again after merge to `main`.

## Decisions

- D-022..D-025 remain ACCEPTED.
- D-026 remains PROPOSED until actual binding-resolution failure behavior exists.
- D-033 ACCEPTED — PHP request teardown is not browser component-lifecycle authority; server issuance and browser cleanup/stale resolution remain separate responsibilities.

## Next task

`T-204 — End-to-end Prep List through shared ActionBus`

**Status: TODO / not started.**
