# External Review Record

## Review status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Review scope:** `T-301 — browser DriverRegistry`
- **Base reviewed main checkpoint:** `42a1c643c60ccd9ad445d5b9f21b83ee8490de05`
- **Implementation checkpoint:** `1c1ff62ac70f779e90866bd169abc7599b7632bf`
- **Result:** **PENDING FINAL REVIEW**
- **M3 status:** IN PROGRESS.
- **Next task:** T-302 has **not** started.

## Scope

T-301 completes and hardens the pre-existing browser `DriverRegistry`; it does not introduce a new subsystem or redesign its public API.

Production file:

```text
packages/browser-runtime/src/driver-registry.ts
```

New test file:

```text
packages/browser-runtime/tests/driver-registry.test.ts
```

No design/implementation-plan artifact was added because this was approved as a bounded completion of an existing flow.

## Expected architecture

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

## Review invariants

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
12. T-302/T-303/T-304/T-305 are not implemented by this task.

## Runtime hardening finding

The original `assertValidDriverName(name: string)` relied only on TypeScript typing before `RegExp.test()`. At runtime, JavaScript regex APIs coerce non-string inputs; for example `null` is tested as `"null"`, which matches the driver grammar and could be inserted as a non-string Map key by untyped JavaScript callers.

T-301 adds the minimal runtime guard:

```text
typeof name === "string"
AND
frozen driver grammar matches
```

No other production behavior was expanded.

## TDD evidence

RED commit:

```text
5e442a7ae70a59ef2d8b7f5c9bdd3dcc4134d91b
workflow 34050492466
```

Browser result:

```text
18 tests total
16 passed
2 deliberate failures
```

The two failures prove:

- non-string registration could pass without error;
- non-string lookup reached `Unsupported binding driver` instead of being rejected as an invalid contract value.

GREEN commit:

```text
1c1ff62ac70f779e90866bd169abc7599b7632bf
workflow 34050557047
```

Final implementation evidence:

```text
browser:  TypeScript typecheck + 18/18 Vitest tests
PHP:      266 tests / 783 assertions
contract: 52 fixture manifest entries + 12 conformance scenarios
CI:       contract + four PHP matrix cells + php-lint + browser — all green
```

## Decision status

- D-016 remains ACCEPTED — driver identifiers are extensible and unsupported drivers fail closed.
- D-035 ACCEPTED — browser DriverRegistry maps exact contract-valid identifiers only to explicitly registered BindingDrivers; registration/lookup do not confer authority, validate lifecycle or permit aliases/fallback.
- D-026 remains PROPOSED; T-301 does not define the eventual normalized browser binding-resolution error model.

## Explicit statement

**T-301 implementation is complete and green. Final external-style review is requested before merge. T-302 is TODO and has NOT started.**
