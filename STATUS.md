# Project Status

> Current repository state for implementation and review.

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/cancellation-propagation`
- **Reviewed main baseline before T-305:** `a61fec3085b920f158e4c61fb74420ace706fca1`
- **T-305 implementation head:** `6cfd7d11862d51dcd6c1e2c18254b290c661e2ec`
- **T-305 reviewed checkpoint:** `0a883ffd819144298e864b1a6e5beea87cb1b984`
- **Stage:** M0 DONE; M1 DONE; M1.1 DONE/REVIEWED; M2 DONE/REVIEWED; **M3 DONE / REVIEWED**
- **Last completed/reviewed task:** `T-305 — Cancellation propagation`
- **Next task:** `T-401 — Confirmation challenge/receipt` — **not started**
- **Contract version:** `0.1-draft`
- **Spec status:** `spec/0.1` remains frozen and unchanged by T-305.
- **Contract baseline:** **52 fixture manifest entries + 12 conformance scenarios**.
- **PHP baseline:** **283 tests / 815 assertions**.
- **Browser baseline:** TypeScript typecheck + **103/103 Vitest tests**.
- **Implementation CI:** workflow `34104686684` on `6cfd7d11862d51dcd6c1e2c18254b290c661e2ec`, all 7 jobs green.
- **Review CI:** workflow `34112709513` on exact reviewed checkpoint `0a883ffd819144298e864b1a6e5beea87cb1b984`, all 7 jobs green.
- **Observed Livewire integration version:** `v4.4.3`.

## M3 — Browser Runtime / WebMCP — DONE / REVIEWED

### T-301 — DriverRegistry — DONE / REVIEWED

Exact explicit driver registration/lookup with fail-closed invalid/unknown handling. D-035 ACCEPTED.

### T-302 — WebMCP semantic projection — DONE / REVIEWED

Independent deterministic projection of supported WebMCP hints. D-036 ACCEPTED.

### T-303 — Async registration lifecycle — DONE / REVIEWED

Deterministic versioned tool projection, whole-snapshot preflight, sequential registration, AbortController-backed registration leases and partial-failure cleanup. D-037/D-038 ACCEPTED.

### T-304 — Livewire browser driver — DONE / REVIEWED

Exact `Livewire.find(componentId)` resolution, server-issued object→positional call plan, documented `$wire.$call()` invocation, strict expiry/input validation and no stale-target retargeting. D-039/D-040/D-041 ACCEPTED.

### T-305 — Cancellation propagation — DONE / REVIEWED

Execution model:

```text
WebMCP execute(required signal)
        │
        ├── already aborted
        │      ↓
        │   exact signal.reason
        │   no invocation-time driver lookup
        │
        ▼
DriverRegistry → LivewireBrowserDriver
        │
        ├── exact target/expiry/input validation
        ├── exact Livewire.find(componentId)
        ├── documented component-scoped intercept(method)
        └── exact $wire.$call(method, ...params)
                 │
                 ├── abort before onSend → exact action.cancel() once
                 └── onSend → dispatch frontier
                            └── later abort does not cancel broader framework work
```

Reviewed invariants:

1. WebMCP execution `signal` is required by TypeScript.
2. Already-aborted WebMCP execution fails before invocation-time `requireDriver()` and preserves exact `signal.reason`.
3. Registration-time driver preflight remains unchanged and separate from invocation cancellation.
4. Direct-driver already-aborted signals fail before Livewire lookup/call.
5. Cancellation-aware Livewire execution requires documented component-scoped `intercept(method, callback)` before `$call()`.
6. Only the exact action handle created by the immediate exact `$call()` is cancellation authority.
7. Pre-`onSend` caller abort invokes exact `action.cancel()` once and surfaces the caller abort reason rather than Livewire's internal cancellation error.
8. A synchronous prior-interceptor abort race is handled after exact action capture.
9. `onSend` is the hard dispatch frontier.
10. Post-`onSend` abort does not call `action.cancel()`, `message.cancel()`, or `request.cancel()` and makes no rollback/reversal claim.
11. Post-dispatch direct-driver success/failure remains the natural exact Livewire result/error.
12. No `#[Async]`, `#[Isolate]`, private Livewire request API or synthetic rollback result is introduced.
13. The execution-local interceptor is logically one-shot and physically unsubscribed after the active interceptor iteration.
14. Unrelated trailing interceptors still run and later same-method invocations are not captured.
15. AbortSignal listeners are removed on natural success, natural failure and pre-dispatch cancellation.
16. Registration lifetime and per-execution cancellation remain separate authorities.
17. `livewire_cancellation_unavailable` is a focused compatibility error only; D-026 remains PROPOSED as the complete generic binding failure vocabulary.
18. `spec/0.1`, Laravel production code, M4 trust controls and other framework drivers are unchanged.

## TDD / verification evidence

```text
Design:                       2d044bdde2fdf8f5b084ce5cebf888aa2c293319
Design hardening:             961262931676d1102555cd31c6e5dafa3ad19b30
Implementation plan:          e33b74439055dfabab40ceb99850404d420b314b

WebMCP type RED:              b270a2b26d45ba8826128c616f97d3897e857ac9 / 34098620894
Task-1 GREEN:                 cf884ed85f57f8dfeb21cf68411fd820e9979ceb / 34099618870 — 7/7 green
Interceptor type RED:         f8811fd3ef46a0a2616524d7499b23de926e43f6 / 34102239036
Interceptor port:             fb00a2123b449ba25e240cf6253856fcbcdc72e5
Cancellation RED:             ba7240eee58caee2b1132802d01c4a6ef13d245d / 34102948252
Cancellation implementation:  fffff7b15a31d61fc1cb212598505eba044b86b2
Implementation GREEN:         35430366e8d01d17a5d936ac55050848face2bbc / 34103990010 — 7/7 green
Cleanup/isolation hardening:  6cfd7d11862d51dcd6c1e2c18254b290c661e2ec / 34104686684 — 7/7 green
Review checkpoint:            0a883ffd819144298e864b1a6e5beea87cb1b984 / 34112709513 — 7/7 green
```

Final reviewed evidence:

```text
browser:  TypeScript typecheck + 103/103 Vitest tests
PHP:      283 tests / 815 assertions
contract: 52 fixture manifest entries + 12 conformance scenarios
CI:       all 7 jobs green on exact reviewed checkpoint
Livewire: v4.4.3 observed in matrix
```

## Decisions

- D-042 ACCEPTED — strong no-dispatch guarantee exists only before the framework dispatch frontier; WebMCP signal is required and post-frontier abort is not rollback.
- D-043 ACCEPTED — Livewire cancellation is exact action-level `action.cancel()` before `onSend` only; no broad message/request cancellation or private-API workaround.
- D-026 remains PROPOSED as a complete generic binding failure vocabulary.

## Next boundary

M3 is complete/reviewed. **M4/T-401 has not started and must not begin automatically.**
