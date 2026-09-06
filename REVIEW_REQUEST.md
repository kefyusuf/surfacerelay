# External Review Record

## Review status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Review scope:** `T-302 — WebMCP semantic projection`
- **Implementation checkpoint:** `13253898b568bd0a52b0a2dc8d3d9f0be113483f`
- **Result:** review pending
- **M3 status:** IN PROGRESS
- **Next task:** T-303 has **not** started.

## Reviewed scope

T-302 completes the pre-existing browser `webmcp-projection.ts` skeleton. It does not add WebMCP registration, browser tool lifecycle, binding execution or driver behavior.

Production file:

```text
packages/browser-runtime/src/webmcp-projection.ts
```

Test/typecheck files:

```text
packages/browser-runtime/tests/webmcp-projection.test.ts
packages/browser-runtime/tests/webmcp-projection.typecheck.ts
packages/browser-runtime/tsconfig.json
```

## Projection contract under review

```text
effect=read
    → readOnlyHint=true

outputContentTrust=contains_untrusted_content
    → untrustedContentHint=true

risk=consequential
    → consequentialHint=true
```

Accepted review targets:

1. The three mappings are independent.
2. Non-read effects never set `readOnlyHint` merely because of risk or other metadata.
3. Destructive/external effects do not imply consequential risk.
4. `risk=consequential` works independently for read or write actions.
5. Output sensitivity never changes `untrustedContentHint`; only output content trust controls it.
6. Sensitive + trusted and sensitive + untrusted combinations remain distinct.
7. No unsupported/unrelated annotations are synthesized from effect, sensitivity or idempotency.
8. Projection is pure and leaves the ActionDefinition untouched.
9. Projection owns no validation, authorization, driver lookup, binding resolution, execution or WebMCP registration.
10. Runtime output already contained all three booleans; T-302 strengthens the public TypeScript return shape so those fields are required rather than `boolean | undefined`.
11. A compile-time fixture now guards the exact return shape.
12. `spec/0.1` is unchanged.
13. D-036 records the orthogonal projection boundary.

## TDD evidence

RED:

```text
commit:   8ef99e86331b1a6d81f4755ab7cd65294b639075
workflow: 34052956365
```

RED failure:

```text
TS2322: WebMcpAnnotations not assignable to exact three-boolean shape
readOnlyHint: boolean | undefined is not assignable to boolean
```

This proved a type/runtime drift: `projectAnnotations()` always emitted all three booleans while the declared interface allowed missing fields.

GREEN:

```text
commit:   13253898b568bd0a52b0a2dc8d3d9f0be113483f
workflow: 34053052459
browser:  typecheck + 30/30 Vitest tests
PHP:      266 tests / 783 assertions
contract: 52 fixture entries + 12 conformance scenarios
CI:       all 7 jobs green
```

## Decision status

- D-014 remains ACCEPTED — consequential risk is orthogonal to effect/destructiveness.
- D-032 remains ACCEPTED — sensitivity and content trust are independent.
- D-036 ACCEPTED — WebMCP projection maps these semantics independently, returns a deterministic three-boolean shape and does not synthesize unrelated hints.

## Explicit boundary

T-302 does **not** call `document.modelContext.registerTool()`, does not manage AbortController cleanup, does not execute RuntimeBindings and does not implement the Livewire browser driver. Those responsibilities remain T-303/T-304/T-305.

## Explicit statement

**T-302 implementation is ready for final review. T-303 is TODO and has NOT started.**
