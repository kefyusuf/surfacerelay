# Project Status

> Current repository state for implementation and review.

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/browser-driver-registry`
- **Base reviewed main checkpoint:** `42a1c643c60ccd9ad445d5b9f21b83ee8490de05`
- **T-301 implementation head:** `1c1ff62ac70f779e90866bd169abc7599b7632bf`
- **Stage:** M0 DONE; M1 DONE; M1.1 DONE/REVIEWED; M2 DONE/REVIEWED; **M3 IN PROGRESS**
- **Current task:** `T-301 — DriverRegistry` — implementation DONE, pending final review
- **Next task:** `T-302 — WebMCP semantic projection` — **not started**
- **Contract version:** `0.1-draft`
- **Spec status:** `spec/0.1` remains frozen and unchanged by T-301.
- **Contract baseline:** **52 fixture manifest entries + 12 conformance scenarios**.
- **PHP baseline:** **266 tests / 783 assertions**.
- **Browser baseline:** TypeScript typecheck + **18 Vitest tests**.
- **CI:** contract, PHP 8.3/8.4 × Illuminate 12/13 × Testbench 10/11 × Livewire 4.4, php-lint and browser are green on the T-301 implementation head.

## M2 — Livewire Binding — DONE / REVIEWED

T-201 through T-204 remain reviewed and merged. The Livewire vertical proves explicit exposure, exact mounted RuntimeBindings and a single shared ActionBus/application mutation path for human and binding-derived invocation.

## M3 — Browser Runtime / WebMCP — IN PROGRESS

### T-301 — DriverRegistry

The existing browser-runtime registry has been completed and hardened rather than redesigned.

```text
RuntimeBinding.driver
        │ exact contract-valid identifier
        ▼
DriverRegistry.requireDriver(name)
        │
        ├── explicitly registered ──→ exact BindingDriver object
        └── unknown/invalid ────────→ fail closed
```

Reviewed implementation boundaries:

1. `register(name, driver)` and `requireDriver(name)` remain the complete public registry API.
2. Driver identifiers use the frozen RuntimeBinding driver grammar and are not trimmed, lowercased, aliased or defaulted.
3. Duplicate registration fails loudly and does not replace the original driver.
4. Contract-valid but unsupported names fail closed.
5. Runtime non-string names are rejected before regex coercion or unsupported-driver lookup.
6. Multiple explicit drivers coexist independently.
7. Registration/lookup never calls `BindingDriver.execute()`.
8. Registry performs no binding discovery, lifecycle validation, stale resolution, target lookup, authorization, action-version resolution, WebMCP registration or driver execution.
9. `spec/0.1` is unchanged.

### Runtime hardening finding

JavaScript `RegExp.test()` coerces non-string values. Before T-301 hardening, a value such as `null` could be tested as the string `"null"`, pass the identifier regex and reach the registry despite the contract requiring a string.

The registry now checks `typeof name === 'string'` before applying the frozen grammar. This preserves the TypeScript API while making the runtime boundary fail closed for JavaScript/untyped callers.

## TDD / verification evidence

```text
RED test commit:  5e442a7ae70a59ef2d8b7f5c9bdd3dcc4134d91b
GREEN fix commit: 1c1ff62ac70f779e90866bd169abc7599b7632bf
RED workflow:     34050492466
GREEN workflow:   34050557047
```

RED browser evidence:

```text
18 tests total
16 passed
2 deliberate failures
```

Both failures were the non-string runtime-name cases.

GREEN evidence:

```text
browser:  TypeScript typecheck + 18/18 Vitest tests
PHP:      266 tests / 783 assertions
contract: 52 fixture manifest entries + 12 conformance scenarios
CI:       all jobs green
```

## Decisions

- D-016 remains ACCEPTED — drivers are extensible identifiers and unknown drivers fail closed.
- D-022..D-025 remain ACCEPTED.
- D-026 remains PROPOSED until real binding-resolution failure behavior exists.
- D-033 and D-034 remain ACCEPTED.
- D-035 ACCEPTED — browser DriverRegistry maps exact contract-valid driver names only to explicitly registered browser BindingDrivers; registration does not confer authority, validate lifecycle or permit alias/fallback behavior.

## Next task

`T-302 — WebMCP semantic projection`

**Status: TODO / not started. Do not begin until T-301 review is closed.**
