# External Review Record

## Review status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Reviewed scope:** `T-302 — WebMCP semantic projection`
- **Reviewed/merged checkpoint:** `b8904aaf5d2d8d4f551c213c7ff1103aabb9c8d0`
- **Result:** **PASSED**
- **M3 status:** IN PROGRESS
- **Next task:** T-303 has **not** started.

## Reviewed production scope

```text
packages/browser-runtime/src/webmcp-projection.ts
```

The runtime mapping logic remained unchanged. T-302 strengthened the public return type so its three booleans are required, matching the values that `projectAnnotations()` always emits.

Test/typecheck coverage:

```text
packages/browser-runtime/tests/webmcp-projection.test.ts
packages/browser-runtime/tests/webmcp-projection.typecheck.ts
packages/browser-runtime/tsconfig.json
```

## Accepted invariants

1. `effect=read` alone controls `readOnlyHint`.
2. `outputContentTrust=contains_untrusted_content` alone controls `untrustedContentHint`.
3. `risk=consequential` alone controls `consequentialHint`.
4. Destructive/external effects do not imply consequential risk.
5. Read-only and consequential hints may both be true.
6. Output sensitivity does not affect the untrusted-content hint.
7. Sensitive/trusted and sensitive/untrusted outputs remain distinct.
8. Projection does not synthesize sensitivity, destructive, idempotent, open-world or unrelated hints.
9. Projection leaves the ActionDefinition unchanged.
10. Projection does not register tools, resolve/execute bindings, choose drivers or authorize actions.
11. `WebMcpAnnotations` now guarantees the exact deterministic three-boolean projection shape.
12. Browser typecheck includes an explicit compile-time fixture guarding that return contract.
13. `spec/0.1` is unchanged.
14. D-036 records the independent projection boundary.

## TDD evidence

RED:

```text
commit:   8ef99e86331b1a6d81f4755ab7cd65294b639075
workflow: 34052956365
TS2322: WebMcpAnnotations was not assignable to an exact three-boolean shape because fields were optional.
```

GREEN:

```text
commit:   13253898b568bd0a52b0a2dc8d3d9f0be113483f
workflow: 34053052459
browser:  typecheck + 30/30 Vitest tests
```

Review and merge:

```text
review checkpoint: b8904aaf5d2d8d4f551c213c7ff1103aabb9c8d0
feature review run: 34053277732 — all 7 jobs success
merged main run:    34053337527 — all 7 jobs success
```

Final evidence:

```text
browser:  TypeScript typecheck + 30/30 Vitest tests
PHP:      266 tests / 783 assertions
contract: 52 fixture manifest entries + 12 conformance scenarios
CI:       all jobs green on feature review checkpoint and merged main
```

## Decision status

- D-014 remains ACCEPTED — consequential risk is orthogonal to effect/destructiveness.
- D-032 remains ACCEPTED — sensitivity and content trust are independent.
- D-036 ACCEPTED — WebMCP projection maps the three supported SurfaceRelay projection hints independently, returns a deterministic three-boolean shape and does not infer unrelated semantics.

## Explicit boundary

T-302 does **not** call the WebMCP registration API, manage registration cleanup/AbortController state, execute RuntimeBindings or implement the Livewire browser driver. Those remain T-303/T-304/T-305.

## Explicit statement

**T-302 REVIEW PASSED and is merged to `main`. T-303 is TODO and has NOT started.**
