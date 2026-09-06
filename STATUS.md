# Project Status

> Current repository state for implementation and review.

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `main`
- **Reviewed T-301 checkpoint:** `df55267a72a93ed7a3017c810469fd5c0ff1b6f4`
- **Stage:** M0 DONE; M1 DONE; M1.1 DONE/REVIEWED; M2 DONE/REVIEWED; **M3 IN PROGRESS**
- **Last completed/reviewed task:** `T-301 — DriverRegistry`
- **Next task:** `T-302 — WebMCP semantic projection` — **not started**
- **Contract version:** `0.1-draft`
- **Spec status:** `spec/0.1` remains frozen and unchanged by T-301.
- **Contract baseline:** **52 fixture manifest entries + 12 conformance scenarios**.
- **PHP baseline:** **266 tests / 783 assertions**.
- **Browser baseline:** TypeScript typecheck + **18 Vitest tests**.
- **CI:** feature review checkpoint and merged `main` checkpoint are green across contract, four PHP matrix cells, php-lint and browser.

## M2 — Livewire Binding — DONE / REVIEWED

T-201 through T-204 remain reviewed and merged. The Livewire vertical proves explicit exposure, exact mounted RuntimeBindings and a single shared ActionBus/application mutation path for human and binding-derived invocation.

## M3 — Browser Runtime / WebMCP — IN PROGRESS

### T-301 — DriverRegistry — DONE / REVIEWED

T-301 completed and hardened the existing browser DriverRegistry without redesigning its public API.

```text
RuntimeBinding.driver
        │ exact contract-valid identifier
        ▼
DriverRegistry.requireDriver(name)
        │
        ├── explicitly registered ──→ exact BindingDriver object
        └── unknown/invalid ────────→ fail closed
```

Reviewed invariants:

1. `register(name, driver)` and `requireDriver(name)` remain the complete registry API.
2. Driver names use the frozen RuntimeBinding driver grammar.
3. Names are not trimmed, lowercased, aliased, defaulted or silently substituted.
4. Duplicate registration fails loudly and preserves the original driver.
5. Contract-valid but unsupported names fail closed.
6. Runtime non-string names fail before JavaScript regex coercion or unsupported-driver lookup.
7. Multiple explicit drivers coexist independently.
8. Registration and lookup never execute the driver.
9. Registry owns no discovery, binding lifecycle validation, stale resolution, target lookup, authorization, action-version resolution or WebMCP registration.
10. `spec/0.1` is unchanged.

### Runtime hardening finding

JavaScript `RegExp.test()` coerces non-string inputs. Before hardening, a runtime value such as `null` could be tested as the string `"null"`, satisfy the grammar and reach the registry despite the contract requiring a string.

The final implementation requires both:

```text
typeof name === "string"
AND
frozen driver grammar matches
```

No broader production behavior was introduced.

## TDD / verification evidence

```text
RED test commit:      5e442a7ae70a59ef2d8b7f5c9bdd3dcc4134d91b
GREEN implementation: 1c1ff62ac70f779e90866bd169abc7599b7632bf
Review checkpoint:    df55267a72a93ed7a3017c810469fd5c0ff1b6f4
RED workflow:         34050492466
GREEN workflow:       34050557047
Feature review run:   34050778061
Merged main run:      34050849851
```

RED browser evidence:

```text
18 tests total
16 passed
2 deliberate failures
```

Final evidence:

```text
browser:  TypeScript typecheck + 18/18 Vitest tests
PHP:      266 tests / 783 assertions
contract: 52 fixture manifest entries + 12 conformance scenarios
CI:       all jobs green on feature checkpoint and merged main
```

## Decisions

- D-016 remains ACCEPTED — drivers are extensible identifiers and unknown drivers fail closed.
- D-022..D-025 remain ACCEPTED.
- D-026 remains PROPOSED until real binding-resolution failure behavior exists.
- D-033 and D-034 remain ACCEPTED.
- D-035 ACCEPTED — browser DriverRegistry maps exact contract-valid driver names only to explicitly registered browser BindingDrivers; registration/lookup do not confer authority, validate lifecycle or permit alias/default/fallback behavior.

## Next task

`T-302 — WebMCP semantic projection`

**Status: TODO / not started.**
