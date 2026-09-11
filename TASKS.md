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

### T-401 — Confirmation challenge/receipt — DONE / REVIEWED

**Outcome:** Consequential or explicitly `human_confirmation`-gated actions require a real server-issued opaque confirmation capability. The Laravel reference runtime issues a pending challenge after validation and authorization, permits trusted bridge code to approve that exact scope, atomically consumes an exact-scope approved receipt once, and only then materializes trusted `HumanConfirmation` before execution.

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
- the production cache store requires Laravel `Store` + `LockProvider`, protects every confirmation-record mutation with an exact per-token lock, waits at most 2 seconds for acquisition, fails closed on timeout/store failure, and has no unlocked mutation fallback;
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
Review-prep checkpoint:      d2f76f3c3172a2800e929ee0288d64c305bdb7e7 / 34132981487 — 7/7 green
Initial PR validation:       d2f76f3c3172a2800e929ee0288d64c305bdb7e7 / 34133666646 — 7/7 green
CodeRabbit full review:      e1364e16-d11d-4fe9-85d5-5f6675a2d99fabb — no blocker; one trivial bounded-lock-wait finding
Review finding RED:          37832f73c51b085e6711dc5059c6c0466cc88f82 / 34135694223 — expected block-vs-get failures in all PHP matrices
Review finding GREEN:        f94340947b00f06707451590f1bef9fcc980479d / 34135906728 — 7/7 green
PHP:                         344 tests / 1300 assertions
Browser isolation:           TypeScript typecheck + 103/103 Vitest tests
Contract:                    frozen spec/0.1 validator green; 52 fixture entries + 12 scenarios unchanged
CodeRabbit final head status: success
Open review threads:          0
```

**Review result:** PASSED after the only external automated review finding was verified against Laravel 12/13, fixed TDD-first, and revalidated on the exact code head. Merge remains a separate explicit gate.

### T-402 — Idempotency store — DONE / REVIEWED

**Outcome:** The Laravel reference runtime now enforces bounded server-side idempotency for Action invocations. Exact completed retries reuse durable pre-output-policy executor output without consuming a second confirmation receipt or executing application code twice, while current validation, authorization, output policy and audit still run.

**Acceptance:**

- `none`, `recommended_key`, and `required_key` are enforced server-side without changing `spec/0.1`;
- runtime keys are 1..240 Unicode characters with no normalization; required missing/invalid keys reject before confirmation/execution;
- raw caller keys are never persisted; SHA-256 lookup hashes bind exact action ID/version, raw key, and trusted authority partition (tenant/actor when present, otherwise browser session, otherwise explicit global);
- intent fingerprints bind exact action ID/version, validated input and present actor/tenant/current-record/current-selection/browser-session identities while excluding correlation ID, generic metadata, raw key, confirmation receipt, runtime `HumanConfirmation`, surface and `bindingId`;
- preflight order is validation → authorization → idempotency → confirmation; completed replay still reruns current validation/authorization;
- fresh attempts are claimed atomically as `in_progress` only after any required confirmation and immediately before application execution;
- successful executor output must be deterministically replayable and is persisted before output policy as `completed` before public success;
- exact active completed retry skips confirmation/execution, reruns output policy, keeps the retry's correlation ID and never manufactures confirmation authority;
- same-key different-intent conflict, active `in_progress`, and active `indeterminate` reuse fail closed before confirmation/execution;
- executor failure after claim is best-effort `indeterminate`; unreplayable successful output becomes `indeterminate`; completion-persistence failure remains closed as `in_progress` and never returns success;
- production `DatabaseIdempotencyStore` uses a hashed primary key, unique insert plus short row-lock transaction for claim/expiry replacement, and conditional `in_progress` state transitions; no transaction/row lock spans executor code;
- default retention is 86,400 seconds with strict `now < expiresAt`; equality ends the guarantee and permits a new claim;
- persistence timestamps are explicitly pinned to second precision (`precision: 0`) so the strict UTC hydrator remains valid even when host applications configure Laravel fractional time precision;
- public idempotency refusals normalize to static `rejected` errors with no raw key, key hash, intent fingerprint or replay payload details;
- lost-response integration proves one consequential external-side-effect execution across exact retry after the original successful response is lost;
- actor/tenant/action-version partition isolation, changed-intent conflicts, authorization-before-replay, policy `none`, expiry, race and failure boundaries have negative/integration coverage;
- real MySQL 8.4 migration round-trip is verified across PHP 8.3/8.4 and Illuminate 12/13 with global time precision forced to 6;
- D-045 records the implemented bounded deduplication model;
- T-403 output redaction and T-404 structured audit persistence remain unstarted.

**TDD / verification evidence:**

```text
Pre-Task-5 checkpoint:       d2b30474a2f4a1ce07bc0aa6041ca0b44ef17232 / 34203602150 — 7/7 green
Execution ownership RED:     159740749aa73da201ffdbb496fffc6ec650f60d / 34326974127
Failure-boundary RED:        ebc98af93d25e31ff85b6742e8614a6b1a0d72d5 / 34327261695
Task-5 GREEN:                1f370b487bc5e05618a3b057b4ab44cd97791555 / 34327446638 — 7/7 green
Replay orchestration RED:    88c1922f0d2ccf9aec22fd22d55989bd286b1502 / 34327691831
Result mapping RED:          b7c72ccb8db9452809e6003a11e7226062b9ab24 / 34327726665
Task-6 GREEN:                144142c88b591c96838f6b5834a4dcfa29e437d0 / 34327917164 — 7/7 green
Integration harness RED:     87d6e4764f0f017f086620dbe62e836a50353a1a / 34328505015 — test-harness-only errors
Integration GREEN:           f1ee38285330c5d49af661e4bd0d9bdb10fe88b3 / 34328897541 — 7/7 green
Livewire real-stage proof:    d7dca5d5f4670ab6b0c9684f68c2e85dfed30cd2 / 34329096188 — 7/7 green
Initial PR head:             a46297f8edc18d3485ff9822ded5e6207c1888c3 / 34331545331 — PR CI green
Review finding RED:          58fb0abb304398931d215dda5d079e5269ac4748 / 34333385345
Review finding GREEN:        3a7ab03f821fad4ddee02b2d3ecb9ede0943dbdc / 34333528637 — 7/7 green
PHP:                         412 tests / 1900 assertions across PHP 8.3/8.4 and Illuminate 12/13 + MySQL 8.4
Browser isolation:           TypeScript typecheck + 103/103 Vitest tests
Contract:                    python scripts/validate.py green; frozen spec/0.1 unchanged; 52 fixture entries + 12 scenarios unchanged
Open review threads:         0
Branch/base:                 main@b94ed83497e213c155ae0276264e954b9acd3ac3; ahead-only
```

**Review result:** PASSED. CodeRabbit's only actionable finding was reproduced on real MySQL, fixed TDD-first by pinning both migration timestamps to second precision, and revalidated across the full PHP/Illuminate matrix. PR #2 is merge-ready; merge remains a separate explicit gate.

### T-403 — Output policy/redaction — DONE

**Outcome:** Sensitive action output is now a fail-closed disclosure boundary. Normal output remains an exact pass-through; sensitive output leaves SurfaceRelay only after an explicit trusted redactor release decision. Disclosure failure removes raw output before audit/finalization and normalizes to a static public `failed` result.

**Acceptance:**

- `outputSensitivity=normal` passes execution/replay output through exactly and never invokes the sensitive redactor;
- `outputSensitivity=sensitive` has no implicit raw-output fallback and requires an explicit `OutputRedactionResult::release(...)` decision;
- `release(null)` is a valid successful disclosure and preserves output presence;
- missing redactor, explicit `withhold()`, or redactor exception fails closed with `output_policy_failed`;
- fail-closed state clears `hasOutput` and raw output before `ActionBus` audit/finalization;
- output-policy entry without execution/replay output is an internal pipeline invariant violation;
- the redactor receives exact `ActionDefinition`, raw output, and a restricted `OutputPolicyContext` containing trusted typed runtime entries only, not generic invocation metadata or the full `InvocationContext`;
- public `output_policy_failed` normalization is `failed` with a static message and no propagated halt details, redactor exception text, or raw output;
- output content trust remains independent from sensitivity/redaction per D-032;
- exact completed idempotency replay skips confirmation/execution but re-applies the current sensitive output policy to stored pre-policy executor output; the prior disclosed payload is not replayed;
- audit sees only the post-policy released value, or output-free state on disclosure failure;
- D-046 records the implemented fail-closed sensitive-output disclosure model;
- `spec/0.1/**` remains unchanged;
- T-404 structured audit persistence remains out of scope and unstarted.

**TDD / verification evidence:**

```text
Contract/context RED:        0bb9e430… / 34345104742
Task-1 GREEN:                a7f58d90… / 34345494908 — 7/7 green
Stage RED:                   321b32ef… / 34345640741
Normal pass-through GREEN:   433eafa9… / 34346073018 — 7/7 green
Sensitive release RED:       ff18fa96f9bb36d9d03f7cf148220fafe4cfa056 / 34346351525
Sensitive release GREEN:     d993d32037936fd9132743ccac39b7228a6e87b4 / 34346513087 — 7/7 green
Fail-closed RED:             57315af9e183ac2fd63ec88a59c9aabc2cae2559 / 34346906520
Fail-closed GREEN:           df2667dc2a34fa4316ccccbfb2c23a27bb9ca9ff / 34347231312 — 7/7 green
Result mapping RED:          c0d2cad4ecd7870b9464dcb2933cdf189b35ec0d / 34347679997
Result mapping GREEN:        0399ef03f42001f3c5e913fda5687e74e9ba5a90 / 34347966075 — 7/7 green
Audit sanitization proof:    bb3506076f579fb6ee637c12e6dc68bea3800b07 / 34348300687 — 7/7 green
Implementation checkpoint:   0195875b5f2d5d9646407337ea54df52fe4c8bb3 / 34356958945 — 7/7 green
PHP:                         430 tests / 2034 assertions across PHP 8.3/8.4 and Illuminate 12/13 + MySQL 8.4
Browser isolation:           TypeScript typecheck + 103/103 Vitest tests
Contract:                    python scripts/validate.py green; frozen spec/0.1 unchanged
```

**Review status:** implementation complete and self-reviewed; external PR review is the next gate. Merge remains separate and requires explicit permission.

### Remaining M4 tasks

- T-404 — Structured audit events — DONE / REVIEWED.

## M5 — Filament Vertical — IN PROGRESS

- T-501 — Record context binding — DONE / REVIEWED.
- T-502 — Current-selection trusted context — DONE / REVIEWED.

### T-503 — Active-filter context — DONE / REVIEWED / MERGED / MAIN REVALIDATED

**Outcome:** Explicit trusted Filament adapter wiring can expose the exact applied table-filter snapshot as namespaced runtime authority `filament/active_filters` without changing frozen `ContextRequirement`/`spec/0.1` or introducing a second execution path.

**Acceptance:**

- active-filter authority is a `TrustedContextExtension`, physically separate from caller metadata and frozen core trusted requirements;
- exact key/provider are `filament/active_filters` / `filament.active_filters`;
- exposure is explicit trusted server-side wiring only;
- authority is resolved from the exact active `HasTable` page through public `getTable()->getFilters()` + `getTableFilterState()`;
- deferred form state and raw `tableFilters` / `tableDeferredFilters` are non-authoritative;
- explicit exposure with zero configured filters is a present empty snapshot; no exposure is absence;
- canonicalization is deterministic and type-preserving; unsupported state fails closed with static, non-chained adapter errors;
- every present trusted extension always binds confirmation scope and idempotency intent;
- zero-extension confirmation/idempotency documents retain legacy-compatible shape;
- filter-state changes invalidate prior confirmation scope and idempotency intent while pending deferred edits do not;
- audit persists extension/provider only and never raw filter values, canonical bytes or scope keys;
- caller input/metadata/request/query/route/WebMCP/binding/receipt/idempotency candidates cannot manufacture or replace authority;
- T-502 `current_selection` remains an independent trusted dimension;
- no Filament RuntimeBinding driver, alternate business endpoint, browser-runtime production change, Livewire production change or `spec/0.1` change is introduced.

**Verification / review evidence:**

```text
Design / D-050:                be17945dac6cf2d075bd72075ee26839286b8709
Implementation review head:    18b218b3c45578821028b85111c44d19da8b28b9
Implementation validation:     34483725864 — 7/7 green
Review-preparation validation:  34487043650 — 7/7 green
PR #7 initial validation:       34487266417 — 7/7 green
CodeRabbit full review:         427bcd38-3e18-46e4-b393-dbd6ca99dabb
Review result:                  2 documentation/tracking findings; 0 production correctness/security findings
Review-hardening checkpoint:    68c6c94cfc6250f3135c946f080d73cc08cdcf51
Review-hardening validation:    34489567321 — 7/7 green
Final feature head:             a46ab15c88b32c4785279f1dc96f1c62b6ded0f6
Final feature-head CI:          34491356472 — 7/7 green
Final PR merge-ref CI:          34491366747 — 7/7 green
Merge commit:                   7fe9db4f1e87257b83120396cc290b7253424ad4
Post-merge main CI:             34492632652 — 7/7 green
PHP:                            551 tests / 2788 assertions
Browser:                        TypeScript typecheck + 103/103 Vitest
Contract:                       python scripts/validate.py green; frozen spec/0.1 unchanged
Open review threads:            0
```

**Review result:** PASSED / MERGED / MAIN REVALIDATED. Both CodeRabbit documentation/tracking findings were fixed and confirmed before merge.

### T-504 — Confirmation bridge — IMPLEMENTATION COMPLETE / REVIEW PENDING

**Outcome:** Exact runtime-issued T-401 confirmation challenges can be presented as approval-only Filament modals on explicitly opted-in pages. Human-facing approval mutates only the core pending challenge to approved; business execution remains owned by the original caller's explicit retry through the normal ActionBus path.

**Acceptance:**

- only an exact Confirmation-stage `confirmation_required` halt carrying a real typed `ConfirmationChallenge` is presentable;
- page opt-in is explicit via `InteractsWithSurfaceRelayConfirmation`;
- challenge ID, summary, and expiry are server-authored Livewire `#[Locked]` state;
- browser Action arguments, invocation metadata, request/query/route state, and arbitrary Livewire property updates cannot substitute challenge authority;
- one page hosts at most one pending SurfaceRelay presentation; same-challenge presentation is idempotent/remountable, while different-challenge overwrite and unrelated mounted Filament Action state fail closed;
- the modal disables click-away, Escape, and top-right close paths and exposes intended Approve/Cancel decisions;
- Approve resolves an explicitly configured `ConfirmationService`, approves only the locked challenge ID, and never dispatches ActionBus or business code;
- Cancel clears disposable UI state only and leaves the core challenge pending/non-authoritative;
- missing service binding and store failure fail closed; expired/non-pending approval is generic/non-oracular;
- approval alone produces zero business side effects;
- the original caller explicitly retries with the original token as `confirmationReceipt` through the normal gateway/ActionBus path;
- retry freshly re-resolves trusted runtime authority; current record, current selection, applied active filters, actor, tenant, binding, surface, and validated-input drift reject an old receipt;
- wrong-scope attempts do not spend an otherwise-valid exact-scope receipt;
- exact same idempotency key/intent post-approval retry executes once; a completed lost-response retry replays without a second side effect or second confirmation;
- a consumed receipt cannot authorize a fresh attempt;
- no Confirmation core, Idempotency core, Livewire production, browser-runtime production, or frozen `spec/0.1` change is introduced;
- Filament remains require-dev/optional and the base `SurfaceRelayServiceProvider` remains Filament-free;
- D-051 records the approval-only explicit-retry model and does not claim proof-of-human, `approvedBy`, or delegated approval semantics.

**TDD / verification evidence:**

```text
Design / D-051 checkpoint:      a3e60544308ea5f3072d5fb813e939bfe4924def
Plan checkpoint:                46426cbccc1258664ebe6e3a5177cb841582732e / 34533606536 — 7/7 green
Task 1 RED:                     2df61dfa8c079c9f97b9d90c4c7ec096374910ba / 34559167747
Task 1 GREEN:                   8370402eb8d752eae4c329523e584820c28341c3 / 34559287792 — 7/7 green
Task 2 RED:                     9563b6108066cdde9396b8bfd044e9c2669604a2 / 34559561986
Task 2 final GREEN:             6fbaba5e6215e723d921618072b44931c7ba0264 / 34560122329 — 7/7 green
Task 3 RED:                     1f0f75f316955e03c6aaf61a9ddb3f8c6b062fa2 / 34560370550
Task 3 GREEN:                   f1bea851269cbae932b792d26c2f7731b303aebc / 34560453025 — 7/7 green
Task 4 RED:                     f9822e4e725eb0997633557383c1902adae3c8c5 / 34560647891
Task 4 GREEN:                   babc80bb30426748765fc2dc79c095ab5ae4967b / 34560733159 — 7/7 green
E2E initial proof:              acd4e7fb5129696a6e863e8d1a276ec8a4cfe9ca / 34561151879 — one test-harness-only selection-cache failure
E2E GREEN:                      f83db1a0e54760c2b4bfb97992bdfb93b7610c90 / 34561337612 — 7/7 green
Boundary / review-prep:         e7954abe7a696c7a05ed32a6995fb3b7ae98400c / 34561470349 — 7/7 green
PHP:                            577 tests / 3011 assertions across PHP 8.3/8.4 × Illuminate 12/13 + MySQL 8.4
Browser:                        TypeScript typecheck + 103/103 Vitest
Contract:                       python scripts/validate.py green; frozen spec/0.1 unchanged
Filament / Livewire:            5.8.1 / 4.4.4
```

The initial E2E selection-drift failure was isolated to the direct-object test harness: Filament caches selected records within one Livewire request while the test reused one PHP Page instance across synthetic requests. A fixture-only request-boundary reset fixed the harness; no production T-502/T-504 resolver change was required.

**Review status:** implementation and self-review verification are complete. External PR review is the next gate. Merge remains separate and requires explicit permission.

- T-505 — Multi-tenant order operations demo — TODO / NOT STARTED.

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