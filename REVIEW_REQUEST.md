# External Review Record

## Review status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Reviewed scope:** `T-303 — WebMCP async registration lifecycle`
- **Reviewed/merged checkpoint:** `961a1715c889cd52b814537646a50e049f1ef9d7`
- **Result:** **PASSED**
- **M3 status:** IN PROGRESS
- **Next task:** T-304 has **not** started.

## Reviewed architecture

```text
readonly BoundActionTool[]
        ↓
full-snapshot preflight
        ↓
exact/versioned WebMCP projection
        ↓
deterministic ASCII-name ordering
        ↓
one AbortController per generation
        ↓
sequential registerTool(..., { signal })
        ↓
WebMcpRegistrationLease.dispose()
```

Production files:

```text
packages/browser-runtime/src/webmcp-types.ts
packages/browser-runtime/src/webmcp-tool-projection.ts
packages/browser-runtime/src/webmcp-registration-lifecycle.ts
```

## Accepted invariants

1. `WebMcpModelContext` is a narrow async registration compatibility port.
2. A browser-visible candidate is one exact ActionDefinition + RuntimeBinding pair.
3. Definition and binding `id + version` match exactly; no negotiation/fallback occurs.
4. WebMCP tool name is `<action-id>.v<version>` and satisfies the 1–128 ASCII `[A-Za-z0-9_.-]` contract.
5. Tool identity never uses `bindingId`, component ID, target data, truncation or hash fallback.
6. Duplicate projected names fail before registration; same action/version on multiple bindings is treated as ambiguous.
7. Driver support is preflighted through exact T-301 registry lookup without execution.
8. T-302 annotations are reused without reinterpretation.
9. Registrations are deterministic ASCII-name sorted and strictly sequential.
10. Every registration in one generation receives the same registration `AbortSignal`.
11. Lease disposal is idempotent and aborts that generation.
12. Empty snapshot registration is valid and makes zero browser calls.
13. Partial registration failure aborts the generation and rethrows the exact original browser error object.
14. Tool execution captures the exact RuntimeBinding and resolves the exact driver name.
15. Input and per-execution cancellation signal are forwarded unchanged.
16. Execution cancellation is separate from registration lifetime authority.
17. Registration does not confer authorization or prove binding freshness/expiry/revocation.
18. No `exposedTo`, cross-origin policy API, automatic reconciliation, stale resolution, Livewire browser execution, D-026 finalization, or M4 trust controls were introduced.
19. `spec/0.1` remains unchanged.
20. D-037 and D-038 record the implemented lifecycle and browser-tool-identity boundaries.

## TDD evidence

```text
Boundary corrected RED: 7fcf21c1c90b701b473b3e569d2430bf00b3b8fe
Boundary RED run:       34063633148
Boundary GREEN:         1f44a7b7d9715727c8b202f9be52a57599345913
Boundary GREEN run:     34063660958

Projection RED:         7c89f5f586ff227def203a57261bf8f8be4febff
Projection RED run:     34063697219
Projection impl:        e604c3f7943497723822903970f8d037ea438390
Projection fixture fix: ca8821487656deff188e009b9189e988a8d43ab0
Projection GREEN run:   34063779909

Lifecycle RED:          6e0f2464a1b523b848975673ac8afa78e512686c
Lifecycle RED run:      34063844315
Lifecycle GREEN:        86e91f72ef90c6f9f888ba23e87de2d946923cf5
Lifecycle GREEN run:    34063883296
```

The first boundary test used type-only imports and therefore did not produce the intended RED; no production code was added from that attempt. A dedicated `.typecheck.ts` fixture produced the corrected TypeScript RED before implementation.

The projection implementation's first run exposed a test arithmetic mistake in the claimed 128-character boundary fixture; production validation was correct and only the test fixture was corrected.

## Review and merge evidence

```text
review checkpoint: 961a1715c889cd52b814537646a50e049f1ef9d7
feature review run: 34064090886 — all 7 jobs success
merged main run:    34064515055 — all 7 jobs success
browser:            TypeScript typecheck + 49/49 Vitest tests
PHP:                266 tests / 783 assertions
contract:           52 fixture manifest entries + 12 conformance scenarios
```

## Decision status

- D-035 remains ACCEPTED — exact DriverRegistry behavior.
- D-036 remains ACCEPTED — independent WebMCP annotation mapping.
- D-037 ACCEPTED — one snapshot generation is owned by one registration lease/controller; partial failures abort the generation while preserving the original error; registration and execution signals are separate.
- D-038 ACCEPTED — browser tool identity is exact `<action-id>.v<version>` with loud failure for invalid/too-long/duplicate projected names and no binding/target-derived fallback naming.
- D-026 remains PROPOSED because T-303 does not resolve stale/expired/revoked RuntimeBindings or define final browser invocation failure codes.

## Explicit boundary

T-303 does **not** implement `Livewire.find()`, `$wire.$call()`, stale/expired/revoked binding resolution, final D-026 browser error codes, automatic component/router/DOM observation, incremental reconciliation, cross-origin exposure policy, or M4 production controls.

## Explicit statement

**T-303 REVIEW PASSED and is merged to `main`. T-304 is TODO and has NOT started.**
