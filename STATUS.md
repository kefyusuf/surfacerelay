# Project Status

> Current repository state for implementation and review.

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `main`
- **Reviewed T-303 checkpoint:** `961a1715c889cd52b814537646a50e049f1ef9d7`
- **Stage:** M0 DONE; M1 DONE; M1.1 DONE/REVIEWED; M2 DONE/REVIEWED; **M3 IN PROGRESS**
- **Last completed/reviewed task:** `T-303 — Async registration lifecycle`
- **Next task:** `T-304 — Livewire browser driver` — **not started**
- **Contract version:** `0.1-draft`
- **Spec status:** `spec/0.1` remains frozen and unchanged by T-303.
- **Contract baseline:** **52 fixture manifest entries + 12 conformance scenarios**.
- **PHP baseline:** **266 tests / 783 assertions**.
- **Browser baseline:** TypeScript typecheck + **49/49 Vitest tests**.
- **CI:** feature review workflow `34064090886` and merged-main workflow `34064515055` are green across all 7 jobs.

## M3 — Browser Runtime / WebMCP — IN PROGRESS

### T-301 — DriverRegistry — DONE / REVIEWED

Exact explicit driver registration/lookup with fail-closed invalid/unknown handling. D-035 remains ACCEPTED.

### T-302 — WebMCP semantic projection — DONE / REVIEWED

Independent deterministic projection of the supported WebMCP hints. D-036 remains ACCEPTED.

### T-303 — Async registration lifecycle — DONE / REVIEWED

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

Reviewed invariants:

1. Binding action identity exactly matches ActionDefinition `id + version`.
2. WebMCP tool identity is deterministic `<action-id>.v<version>` and must fit the 1–128 character WebMCP name contract.
3. Same action/version on two bindings is ambiguous and fails before browser registration.
4. Driver support is preflighted through exact T-301 `DriverRegistry`; preflight never executes the driver.
5. Registration order is deterministic ASCII lexical order and strictly sequential.
6. One snapshot generation uses one registration `AbortSignal`.
7. Lease disposal is idempotent and aborts that generation.
8. Empty snapshots return a disposable lease and make no browser calls.
9. Partial registration failure aborts the generation and rethrows the exact original browser error object.
10. Tool execution preserves the exact captured RuntimeBinding/input and forwards the per-execution cancellation signal.
11. Registration lifetime signal and execution cancellation signal are separate authorities.
12. No `exposedTo`, automatic reconciliation, stale/expiry/revocation resolution, Livewire browser execution, or M4 trust controls were introduced.

## TDD / verification evidence

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

Review checkpoint:      961a1715c889cd52b814537646a50e049f1ef9d7
Feature review run:     34064090886 — all 7 jobs success
Merged main run:        34064515055 — all 7 jobs success
```

## Decisions

- D-035 remains ACCEPTED — exact browser driver registry boundary.
- D-036 remains ACCEPTED — independent WebMCP semantic projection.
- D-037 ACCEPTED — one snapshot generation is owned by one registration lease/controller; partial failure aborts the generation; registration and execution signals remain separate.
- D-038 ACCEPTED — WebMCP tool identity is exact `<action-id>.v<version>` with loud failure for invalid/too-long/duplicate projected identity and no binding/target-derived fallback naming.
- D-026 remains PROPOSED; T-303 does not implement stale/expired/revoked binding resolution or final browser failure codes.

## Next task

`T-304 — Livewire browser driver`

**Status: TODO / not started.** A separate design gate is required for exact component lookup, `$wire.$call()` execution, stale/unknown target failures, and no-retarget semantics.
