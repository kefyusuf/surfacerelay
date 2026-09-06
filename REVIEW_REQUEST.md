# External Review Record

## Review status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Reviewed scope:** `T-301 — browser DriverRegistry`
- **Reviewed/merged checkpoint:** `df55267a72a93ed7a3017c810469fd5c0ff1b6f4`
- **Result:** **PASSED**
- **M3 status:** IN PROGRESS.
- **Next task:** T-302 has **not** started.

## Reviewed scope

T-301 completed and hardened the pre-existing browser `DriverRegistry`; it did not introduce a new subsystem or redesign the public API.

Production file:

```text
packages/browser-runtime/src/driver-registry.ts
```

New test file:

```text
packages/browser-runtime/tests/driver-registry.test.ts
```

No design/implementation-plan artifact was added because this was approved as a bounded completion of an existing flow.

## Reviewed architecture

```text
RuntimeBinding.driver
        │ exact identifier
        ▼
DriverRegistry
        │
        ├── register(name, BindingDriver)
        └── requireDriver(name)
                │
                ├── exact registered driver
                └── unknown/invalid → fail closed
```

The registry is only a browser execution-adapter lookup. It is not binding authority or lifecycle state.

## Accepted invariants

1. Only exact explicit registration creates support for a driver identifier.
2. Registry names follow the frozen RuntimeBinding driver grammar.
3. Driver identifiers are never trimmed, lowercased, aliased or defaulted.
4. Duplicate registration fails loudly and leaves the original registration intact.
5. Multiple distinct registrations coexist independently.
6. A contract-valid but unregistered name fails closed with no fallback.
7. Invalid names fail before lookup.
8. Runtime non-string names fail before JavaScript regex coercion can treat them as strings.
9. Registration and lookup do not call `BindingDriver.execute()`.
10. Registry does not discover bindings, validate lifecycle/expiry/revocation, resolve stale targets, authorize invocations, resolve action versions, inspect driver-owned targets, register WebMCP tools or execute bindings.
11. `spec/0.1` is unchanged.
12. T-302/T-303/T-304/T-305 remain unimplemented.

## Runtime hardening finding

The original validator relied on the TypeScript type before `RegExp.test()`. JavaScript regex APIs coerce non-string values; `null`, for example, is tested as `"null"`, which matches the identifier grammar and could reach the registry as a non-string Map key from untyped JavaScript.

The final implementation requires:

```text
typeof name === "string"
AND
frozen driver grammar matches
```

This is the only production behavior change in T-301.

## TDD evidence

RED:

```text
commit:   5e442a7ae70a59ef2d8b7f5c9bdd3dcc4134d91b
workflow: 34050492466
browser:  18 tests total / 16 passed / 2 deliberate failures
```

The two failures proved:

- non-string registration could pass without error;
- non-string lookup reached unsupported-driver resolution instead of failing as an invalid contract value.

GREEN:

```text
commit:   1c1ff62ac70f779e90866bd169abc7599b7632bf
workflow: 34050557047
```

Review / merge:

```text
review checkpoint: df55267a72a93ed7a3017c810469fd5c0ff1b6f4
feature review run: 34050778061 — success
merged main run:    34050849851 — success
```

Final evidence:

```text
browser:  TypeScript typecheck + 18/18 Vitest tests
PHP:      266 tests / 783 assertions
contract: 52 fixture manifest entries + 12 conformance scenarios
CI:       all jobs green on feature checkpoint and merged main
```

## Decision status

- D-016 remains ACCEPTED — driver identifiers are extensible and unsupported drivers fail closed.
- D-035 ACCEPTED — browser DriverRegistry maps exact contract-valid identifiers only to explicitly registered BindingDrivers; registration/lookup do not confer authority, validate lifecycle or permit aliases/defaults/fallback.
- D-026 remains PROPOSED; T-301 does not define normalized browser binding-resolution failure semantics.

## Explicit statement

**T-301 REVIEW PASSED and is merged to `main`. T-302 is TODO and has NOT started.**
