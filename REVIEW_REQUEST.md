# External Review Record

## Review status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Reviewed scope:** `T-303 — WebMCP async registration lifecycle`
- **Implementation head:** `86e91f72ef90c6f9f888ba23e87de2d946923cf5`
- **Result:** **PENDING FINAL CHECKPOINT REVIEW**
- **M3 status:** IN PROGRESS
- **Next task:** T-304 has **not** started.

## Reviewed architecture candidate

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

Focused tests:

```text
packages/browser-runtime/tests/webmcp-types.test.ts
packages/browser-runtime/tests/webmcp-types.typecheck.ts
packages/browser-runtime/tests/webmcp-tool-projection.test.ts
packages/browser-runtime/tests/webmcp-registration-lifecycle.test.ts
```

## Accepted invariants to review

1. `WebMcpModelContext` is a narrow async registration compatibility port; T-303 does not spread ambient `document.modelContext` access across runtime logic.
2. A browser-visible candidate is one exact ActionDefinition + RuntimeBinding pair.
3. Definition and binding `id + version` must match exactly; no negotiation/fallback occurs.
4. WebMCP tool name is `<action-id>.v<version>` and must satisfy the 1–128 ASCII `[A-Za-z0-9_.-]` name contract.
5. Tool identity never uses `bindingId`, component ID, driver target data, truncation or hash fallback.
6. Duplicate projected tool names fail before browser registration. Same action/version on multiple bindings is treated as ambiguous rather than selecting one implicitly.
7. Driver support is preflighted through exact T-301 `DriverRegistry.requireDriver()`; preflight never invokes `BindingDriver.execute()`.
8. Projection reuses T-302 annotations unchanged and uses stable ActionDefinition title/description/inputSchema only.
9. Snapshot input order is not authority. Registrations are sorted by exact ASCII tool name and executed strictly sequentially.
10. Every registration in one successful snapshot receives the same registration `AbortSignal`.
11. A successful batch returns a lease whose repeated `dispose()` is safe and whose first disposal aborts that registration generation.
12. Empty snapshot registration is valid and returns a disposable lease with zero browser registration calls.
13. If a later registration rejects after earlier successes, the generation controller is aborted and the exact original error object is rethrown.
14. The registered tool callback captures the exact RuntimeBinding and resolves the exact binding driver name at invocation time.
15. Caller input is forwarded unchanged to the driver.
16. Per-execution cancellation signal is forwarded to `DriverExecutionContext` and is distinct from the registration lifetime signal.
17. Browser registration does not confer authorization or prove binding freshness/expiry/revocation.
18. T-303 introduces no `exposedTo`, cross-origin policy API, `getTools()`/`executeTool()` orchestration, automatic diff reconciliation, stale resolution, or Livewire browser execution.
19. `spec/0.1` is unchanged.
20. D-037/D-038 record the implemented registration-lifetime and exact browser-tool-identity boundaries.

## TDD evidence

### Compatibility boundary

The first runtime-only test used type-only imports and therefore did not produce the intended RED because Vitest erased them. No production code was added from that attempt. A dedicated typecheck fixture was added and the corrected RED was then observed.

```text
corrected RED commit: 7fcf21c1c90b701b473b3e569d2430bf00b3b8fe
RED workflow:         34063633148
failure:              TS2307 missing ../src/webmcp-types.js
GREEN commit:         1f44a7b7d9715727c8b202f9be52a57599345913
GREEN workflow:       34063660958
```

### Exact WebMCP tool projection

```text
RED commit:           7c89f5f586ff227def203a57261bf8f8be4febff
RED workflow:         34063697219
failure:              new suite missing ../src/webmcp-tool-projection.js
implementation:       e604c3f7943497723822903970f8d037ea438390
fixture-only fix:     ca8821487656deff188e009b9189e988a8d43ab0
GREEN workflow:       34063779909
```

The initial implementation run exposed a test arithmetic error: the claimed 128-character fixture was actually 129 characters. Production validation was correct; only the test fixture was corrected.

### Registration lifecycle

```text
RED commit:           6e0f2464a1b523b848975673ac8afa78e512686c
RED workflow:         34063844315
RED evidence:         existing 38 tests pass; only new lifecycle suite cannot load missing module
GREEN commit:         86e91f72ef90c6f9f888ba23e87de2d946923cf5
GREEN workflow:       34063883296
```

Implementation-head evidence:

```text
browser:  TypeScript typecheck + 49/49 Vitest tests
PHP:      266 tests / 783 assertions
contract: 52 fixture manifest entries + 12 conformance scenarios
CI:       all 7 jobs success on workflow 34063883296
```

## Decision status

- D-035 remains ACCEPTED — exact DriverRegistry behavior.
- D-036 remains ACCEPTED — independent WebMCP annotation mapping.
- D-037 ACCEPTED — one snapshot generation is owned by one registration lease/controller; partial failures abort the generation while preserving the original error; registration and execution signals are separate.
- D-038 ACCEPTED — browser tool identity is exact `<action-id>.v<version>` with loud failure for invalid/too-long/duplicate projected names and no binding/target-derived fallback naming.
- D-026 remains PROPOSED because T-303 does not resolve stale/expired/revoked RuntimeBindings or define final browser invocation failure codes.

## Explicit non-claims

T-303 does **not** implement:

- `Livewire.find()` or `$wire.$call()`;
- stale/expired/revoked binding resolution;
- final D-026 browser error codes;
- automatic component/router/DOM observation;
- incremental tool reconciliation;
- cross-origin `exposedTo` policy;
- confirmation/idempotency/output-redaction/audit production controls.

## Review gate

Before marking T-303 reviewed or merging:

1. compare `main...feat/webmcp-registration-lifecycle` for scope leakage;
2. run fresh CI on the exact documentation/review checkpoint;
3. require all 7 jobs green;
4. only then fast-forward `main` and verify merged-main CI;
5. stop before T-304.
