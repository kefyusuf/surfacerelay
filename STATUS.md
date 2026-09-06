# Project Status

> Current repository state for implementation and review.

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/webmcp-registration-lifecycle`
- **Stage:** M0 DONE; M1 DONE; M1.1 DONE/REVIEWED; M2 DONE/REVIEWED; **M3 IN PROGRESS**
- **Last reviewed task:** `T-302 — WebMCP semantic projection`
- **Current task:** `T-303 — Async registration lifecycle` — implementation complete, pending final review
- **Next task:** `T-304 — Livewire browser driver` — **not started**
- **Contract version:** `0.1-draft`
- **Spec status:** `spec/0.1` remains frozen and unchanged by T-303.
- **Contract baseline:** **52 fixture manifest entries + 12 conformance scenarios**.
- **PHP baseline:** **266 tests / 783 assertions**.
- **Browser baseline:** TypeScript typecheck + **49/49 Vitest tests**.
- **Implementation CI:** exact head `86e91f72ef90c6f9f888ba23e87de2d946923cf5`, workflow `34063883296`, all 7 jobs green.

## M3 — Browser Runtime / WebMCP — IN PROGRESS

### T-301 — DriverRegistry — DONE / REVIEWED

Exact explicit driver registration/lookup with fail-closed invalid/unknown handling. D-035 remains ACCEPTED.

### T-302 — WebMCP semantic projection — DONE / REVIEWED

Independent deterministic projection of `readOnlyHint`, `untrustedContentHint`, and `consequentialHint`. D-036 remains ACCEPTED.

### T-303 — Async registration lifecycle — IMPLEMENTED / PENDING REVIEW

T-303 adds three focused browser-runtime boundaries:

```text
webmcp-types.ts
        ↓
narrow async WebMCP registration compatibility port

webmcp-tool-projection.ts
        ↓
ActionDefinition + exact RuntimeBinding
        → <action-id>.v<version>
        → stable metadata + T-302 annotations

webmcp-registration-lifecycle.ts
        ↓
full-snapshot preflight
        → deterministic sequential registerTool()
        → one AbortController-backed lease
        → abort-on-partial-failure cleanup
```

Reviewed implementation invariants awaiting final external-style checkpoint:

1. Binding action identity must exactly match ActionDefinition `id + version`.
2. WebMCP tool identity is deterministic `<action-id>.v<version>` and must fit the 1–128 character WebMCP name contract.
3. Same action/version on two bindings is ambiguous and fails before any browser registration.
4. Driver support is preflighted through exact T-301 `DriverRegistry`; preflight never executes the driver.
5. Registration order is deterministic ASCII lexical order and strictly sequential.
6. One snapshot generation uses one registration `AbortSignal`.
7. Lease disposal is idempotent and aborts that registration generation.
8. Empty snapshots return a disposable lease and make no browser calls.
9. Partial registration failure aborts the shared generation and rethrows the exact original browser error object.
10. Tool execution resolves the exact captured binding driver and forwards the exact per-execution cancellation signal.
11. Registration lifetime signal and per-execution cancellation signal are separate authorities.
12. No `exposedTo`, `getTools()`, `executeTool()`, automatic reconciliation, stale-resolution, Livewire browser execution, or M4 trust controls were introduced.

## TDD / verification evidence

```text
Boundary RED (corrected): 7fcf21c1c90b701b473b3e569d2430bf00b3b8fe
Boundary RED run:         34063633148 — TS2307 missing webmcp-types module
Boundary GREEN:           1f44a7b7d9715727c8b202f9be52a57599345913
Boundary GREEN run:       34063660958

Projection RED:           7c89f5f586ff227def203a57261bf8f8be4febff
Projection RED run:       34063697219 — new suite missing projection module
Projection GREEN:         e604c3f7943497723822903970f8d037ea438390
Boundary fixture fix:     ca8821487656deff188e009b9189e988a8d43ab0
Projection GREEN run:     34063779909

Lifecycle RED:            6e0f2464a1b523b848975673ac8afa78e512686c
Lifecycle RED run:        34063844315 — existing 38 tests pass; lifecycle module missing
Lifecycle GREEN:          86e91f72ef90c6f9f888ba23e87de2d946923cf5
Lifecycle GREEN run:      34063883296 — all 7 jobs green
```

The first boundary test commit used only type imports, which Vitest erased at runtime; it therefore did not produce the intended RED. A dedicated `.typecheck.ts` fixture was added before production code, and the corrected RED was verified by TypeScript compiler failure.

The projection implementation's first run exposed a test arithmetic mistake in the exact-128-character fixture; production validation was correct. Only the test fixture was corrected.

Final implementation evidence:

```text
browser:  TypeScript typecheck + 49/49 Vitest tests
PHP:      266 tests / 783 assertions
contract: 52 fixture manifest entries + 12 conformance scenarios
CI:       all 7 jobs green on exact implementation head
```

## Decisions

- D-035 remains ACCEPTED — exact browser driver registry boundary.
- D-036 remains ACCEPTED — independent WebMCP semantic projection.
- D-037 ACCEPTED — one snapshot generation is owned by one registration lease/controller; partial failure aborts the generation; registration and execution signals remain separate.
- D-038 ACCEPTED — WebMCP tool identity is exact `<action-id>.v<version>` with loud failure for invalid/too-long/duplicate projected identity and no binding/target-derived fallback naming.
- D-026 remains PROPOSED; T-303 does not implement stale/expired/revoked binding resolution or final browser failure codes.

## Next task boundary

`T-304 — Livewire browser driver`

**Status: TODO / not started.** T-304 requires a separate design gate for exact Livewire component lookup, `$wire.$call()` execution, and stale/unknown target failure semantics.
