# Implementation Task Board

Status values: `TODO`, `IN_PROGRESS`, `BLOCKED`, `DONE`.

A task is DONE only after required verification passes. SurfaceRelay implements one task at a time; do not begin the next task automatically.

## M0 — Contract Foundation — DONE

All T-001..T-005 DONE.

## M1 — Laravel Kernel — DONE

All T-101..T-110 DONE.

## M1.1 — Hardening — DONE / REVIEWED

Reviewed baseline: `11e7348cbee6f69fa8e502308f6db262bf78e271`.

## M2 — Livewire Binding — DONE / REVIEWED

T-201 through T-204 DONE / REVIEWED. Reviewed M2 checkpoint: `068347ac6d1bba645ab1c311daf918f87298b2e8`.

---

## M3 — Browser Runtime / WebMCP — DONE / REVIEWED

### T-301 — DriverRegistry — DONE / REVIEWED

Exact explicit driver registration/lookup with fail-closed invalid/unknown handling. Reviewed checkpoint: `df55267a72a93ed7a3017c810469fd5c0ff1b6f4`.

### T-302 — WebMCP semantic projection — DONE / REVIEWED

Independent deterministic projection of supported WebMCP hints. Reviewed checkpoint: `b8904aaf5d2d8d4f551c213c7ff1103aabb9c8d0`.

### T-303 — Async registration lifecycle — DONE / REVIEWED

Whole-snapshot preflight, deterministic tool identity, sequential registration, registration leases and partial-failure cleanup. Reviewed/merged checkpoint: `961a1715c889cd52b814537646a50e049f1ef9d7`.

### T-304 — Livewire browser driver — DONE / REVIEWED

Exact Livewire binding execution through documented `Livewire.find()` + `$wire.$call()`, server-issued positional call plan, strict expiry/input checks and no stale-target retargeting. Reviewed/merged checkpoint: `bd1f20397a6a3f24000abb73ac7eedb6ed48fdb9`.

### T-305 — Cancellation propagation — DONE / REVIEWED

**Outcome:** SurfaceRelay propagates cancellation only as far as it can make a truthful guarantee: exact action-level cancellation before Livewire dispatch, with a hard `onSend` frontier and no rollback/reversal claim after dispatch.

**Acceptance:**

- WebMCP `ToolExecuteCallbackOptions.signal` is required by TypeScript;
- already-aborted WebMCP execution preserves exact abort reason and does not perform invocation-time driver lookup/execution;
- registration-time driver preflight remains separate and unchanged;
- direct Livewire driver execution checks already-aborted signals before runtime lookup;
- cancellation-aware Livewire execution requires documented component-scoped `intercept(method, callback)` support before `$call()`;
- only the exact action created by the immediate exact `$call()` is cancellation authority;
- pre-`onSend` abort invokes exact `action.cancel()` once and surfaces exact caller abort reason;
- synchronous prior-interceptor abort race is handled after action capture;
- `onSend` is the dispatch frontier;
- post-`onSend` abort never invokes action/message/request broad cancellation and does not replace natural success/failure;
- no `#[Async]`, `#[Isolate]`, private request API or synthetic rollback result is introduced;
- interceptor cleanup avoids mutating Livewire's interceptor array during callback iteration;
- unrelated trailing interceptors run and later same-method invocations are not captured;
- execution-local AbortSignal listeners are cleaned on success, failure and pre-dispatch cancellation;
- D-042/D-043 record the cancellation frontier and granular Livewire cancellation boundary;
- `spec/0.1` and Laravel production remain unchanged.

**Verification:**

```text
Design:                      2d044bdde2fdf8f5b084ce5cebf888aa2c293319
Design hardening:            961262931676d1102555cd31c6e5dafa3ad19b30
Plan:                        e33b74439055dfabab40ceb99850404d420b314b
WebMCP RED:                  b270a2b26d45ba8826128c616f97d3897e857ac9 / 34098620894
Task-1 GREEN:                cf884ed85f57f8dfeb21cf68411fd820e9979ceb / 34099618870 — 7/7 green
Interceptor type RED:        f8811fd3ef46a0a2616524d7499b23de926e43f6 / 34102239036
Interceptor port:            fb00a2123b449ba25e240cf6253856fcbcdc72e5
Cancellation RED:            ba7240eee58caee2b1132802d01c4a6ef13d245d / 34102948252
Cancellation implementation: fffff7b15a31d61fc1cb212598505eba044b86b2
Implementation GREEN:        35430366e8d01d17a5d936ac55050848face2bbc / 34103990010 — 7/7 green
Coverage hardening:          6cfd7d11862d51dcd6c1e2c18254b290c661e2ec / 34104686684 — 7/7 green
Review checkpoint:           0a883ffd819144298e864b1a6e5beea87cb1b984 / 34112709513 — 7/7 green
Review-passed checkpoint:    dbb200cec4e194f73aa4cefb064bf0aaf15a7781 / 34113173623 — 7/7 green
Merged-main run:             34113562938 — 7/7 green
Browser:                     TypeScript typecheck + 103/103 Vitest tests
PHP:                         283 tests / 815 assertions
Contract:                    52 fixture entries + 12 conformance scenarios
```

**Review result:** PASSED. Reviewed/merged checkpoint: `dbb200cec4e194f73aa4cefb064bf0aaf15a7781`. M3 is complete/reviewed on `main`.

---

## M4 — Production Trust Controls — IN PROGRESS

### T-401 — Confirmation challenge/receipt — DONE / READY FOR EXTERNAL REVIEW

**Outcome:** Consequential or explicitly `human_confirmation`-gated actions now require a real server-issued opaque confirmation capability. The Laravel reference runtime issues a pending challenge after validation and authorization, permits trusted bridge code to approve that exact scope, atomically consumes an exact-scope approved receipt once, and only then materializes trusted `HumanConfirmation` before execution.

**Acceptance:**

- confirmation is mandatory when `risk=consequential` OR `human_confirmation` is declared;
- caller `confirmed=true`, action input, client metadata, pending challenge IDs and prebuilt `HumanConfirmation` entries cannot manufacture authority;
- `bindingId` and `confirmationReceipt` are explicit non-authoritative invocation candidates, never metadata fallbacks;
- confirmation scope is deterministic and binds exact action ID/version, validated input, surface, binding reference and relevant actor/tenant/current-record/current-selection/browser-session context;
- correlation ID, idempotency key, generic metadata and `human_confirmation` itself are excluded from scope;
- arbitrary unrepresentable trusted objects fail closed unless their trusted resolver supplies a stable non-secret confirmation scope key;
- challenges use opaque 32-byte random base64url tokens; server records are addressed only by SHA-256 token hash;
- challenge and approved-receipt expiry are strict (`now < expiresAt`), with default TTLs of 300s and 120s respectively;
- approval cannot rewrite scope and returns the same opaque token as the receipt;
- receipt consumption is single-use and occurs before downstream execution; later execution failure does not restore the receipt;
- scope mismatch grants no authority but does not spend an otherwise-valid approved receipt;
- the production cache store requires Laravel `Store` + `LockProvider`, uses per-token locks for approve/consume, and has no unlocked mutation fallback;
- only successful receipt consumption creates `VerifiedConfirmation` with non-secret `surfacerelay.confirmation` provenance;
- real typed `ConfirmationChallenge` halts normalize to `confirmation_required`; generic halt details can never fabricate a challenge;
- full integration tests cover validation→authorization→confirmation→execution ordering, replay, expiry, scope mismatch, secret non-leakage and caller spoofing;
- D-044 records the implemented single-use scoped capability model;
- `spec/0.1` wire schemas remain unchanged;
- T-402 idempotency, T-403 output policy, T-404 structured audit and T-504 human UI remain out of scope.

**TDD / verification evidence:**

```text
Design checkpoint:          13437eec846bc2410125f7e7648d4a3034c8abfe
Implementation plan:        76a92dd65131d58c3833f9415ff03984910bf8ac / 34121419395 — 7/7 green
Task 1 RED:                 e0e6b5fee4752d0655a95fa72dfd7c9f0b1fd7d8 / 34121703510
Task 1 GREEN:               d39d9985109f50c2501bd058420d1be6cb3b6ac6 / 34122361725 — 7/7 green
Task 2 RED:                 9bd56e1ea7c93d2acdf4f353e663b7ff21e0e5ab / 34122730746
Task 2 GREEN:               02faf1e105691acbd19221662972cc1d3c347ffa / 34123455314 — 7/7 green
Task 3 RED:                 d21f8a26b7e0bebbebe4aeb105449718eff4f29b / 34125236616
State-machine GREEN:         cc7eb1a0bb6a0ea2d715826cbed7db1ed5d062f5 / 34125961974 — green
Pipeline gate checkpoint:    c10f06c16384c5d53f28ea38fa0f64903a4a37ab / 34127948471 — green
Full integration RED:        de78135f5df344aae4f2ab8bdf846130ed008c82 / 34130583026 — expected unmapped confirmation halt only
Implementation GREEN:        7db7d54fd1af9387c3cb408ec472c949168ed569 / 34130941909 — 7/7 green
PHP:                         344 tests / 1299 assertions
Browser isolation:           TypeScript typecheck + 103/103 Vitest tests
Contract:                    frozen spec/0.1 validator green; 52 fixture entries + 12 scenarios unchanged
Diff:                        main@5eb33c20... → implementation HEAD, 0 behind; no spec/0.1 change
```

T-401 implementation verification is complete. External review and merge are separate gates.

### Remaining M4 tasks

- T-402 — Idempotency store — TODO / **not started**.
- T-403 — Output policy/redaction — TODO.
- T-404 — Structured audit events — TODO.

## M5 — Filament Vertical — TODO

- T-501 — Record context binding.
- T-502 — Current-selection trusted context.
- T-503 — Active-filter context.
- T-504 — Confirmation bridge.
- T-505 — Multi-tenant order operations demo.

## M6 — HTMX Portability Proof — TODO

- T-601 — Explicit HTMX binding descriptor.
- T-602 — HTMX browser driver.
- T-603 — Non-Laravel HTMX fixture app.
- T-604 — Shared conformance against Livewire + HTMX.

## M7 — Conformance / Ecosystem Bridges — TODO

- T-701 — Executable conformance runner.
- T-702 — Adapter author guide.
- T-703 — Laravel MCP projection using a maintained MCP implementation.
- T-704 — Optional OpenAPI importer as a secondary adapter.
