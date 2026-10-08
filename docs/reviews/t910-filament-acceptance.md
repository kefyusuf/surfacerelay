# T-910 Filament Selection Driver Acceptance

The owner approved the D-075 package design before implementation. The adapter is
opt-in and unreleased: published alpha.1/alpha.2 lack its exports. Core schemas,
server authorization/confirmation/idempotency and normal Livewire result modes are
unchanged. The private executor is not exported from the package root.

## Executable boundaries

`FilamentBrowserDriverOptions` and `FilamentSelectionRuntime` are the adapter's
TypeScript API schema; public typecheck fixtures cover the required coordinator and
explicit exposure list. No protocol-neutral JSON schema change is needed. Public
driver/runtime tests cover adapter behavior; existing shared Livewire/HTMX
conformance remains green. This is not a new WebMCP/W3C conformance claim.

| Boundary | Evidence |
| --- | --- |
| Exact registration and owned plain data | Unknown/clone/mutation, undefined extra key, nonfinite number, getter and custom serialization negatives; no getter invocation or JSON normalization. |
| Preflight before selection | Expiry, target, method, input, abort, missing interception and stale/non-callable wire: zero sync/dispatch. |
| Shared exclusion | Two drivers/one coordinator; selection-free contenders; independent components; release on failure/settlement. |
| Exact-action capture failure | Public rejection can precede underlying settlement; occupancy remains until actual call finishes. |
| DOM/Alpine | Exact prevalidated wire, one owned table, full state snapshot, callable capabilities, safe failure/partial-write recovery. |
| Projection compatibility | Public registry/lifecycle tests preserve passthrough and envelope output; business rejection stays nested. |
| Artifact | Actual tarball installed in a clean root import/typecheck/bundle/smoke/deep-import consumer; new exports independently imported. |

Observed RED before implementation: missing driver; clone/mutation/conflicting
registration called through instead of rejecting; lossy JSON normalization allowed
undefined target keys and null-to-NaN expiry. Corresponding GREEN controls pass.
Runtime tests also grew through individual RED/GREEN selection slices. Historical
generator RED failed the `// Filament` marker lookup after live-client migration;
the isolated historical client fixes it without future exports in old registries.

## Docker and native acceptance

Docker browser suite: 508 tests (39 Filament driver, 22 selection runtime), plus
typecheck/build/conformance build. Canonical validator: 22 HTMX and 41 WebMCP result
fixtures. Historical generator checks: T-907 4, T-908 6; new candidate generator 7.
Actual mounted local-artifact consumer: 23 signed HTTP and 7 response-loss controls.
The pinned consumer uses Laravel 13.35.0, Livewire 4.4.7, Filament 5.10.0 and the
published Laravel alpha.1 package; its browser is a private local candidate.

The separately migrated live example passed all 16 Playwright tests in 22.9 seconds,
one worker/no retries: PHP 8.4.26, Node 22.23.3, Playwright 1.63.0, Chromium
153.0.8010.12, Laravel 13.35.0, Livewire 4.4.7, Filament 5.10.1. Full Chromium ran
under task-local Xvfb after the headless-shell download failed certificate
validation. TLS stayed enabled. Relevant 183 source files matched the host.

Real Chrome native calls used the generated consumer through loopback 4187:

| Case | Observation / SQL |
| --- | --- |
| Empty selection / invalid input | Nested rejection; initial ledger remains empty. |
| Record 101 approval then selection 102 | Fresh confirmation required; 101 does not execute. |
| Fresh approval/execution/replay for 102 | Nested success; one effect for only 102. |
| Table-free current-record 101 with post-commit response loss | `execution_failed/unknown`; SQL effects increase from one to two and fault is consumed. |
| Explicit retry after inspecting SQL | Original success; still two effects. |
| Old list binding after edit mounts | Safe unknown surface failure; still two effects. |

See [sanitized native/SQL observations](t910-native-acceptance.json) and
[initial tarball manifest/clean consumer](t910-candidate-proof.json). The latter
records the base revision and explicitly labels a working-tree candidate, not an
exact committed revision or npm release. Host Chrome version was not separately
recorded; the native calls themselves were observed directly.

Final compatibility review preserved the original direct-driver stale messages.
The resulting final tarball SHA-256 is
`205a800918152f1375ab0cb8d971cbb5fefc49fa952611a2e924cb9ce4e73c45`.
Its [manifest, source hashes and repeated clean consumer](t910-final-candidate-proof.json)
cover all 45 shipped files; the mounted installation matched each byte.
[Fresh final native calls](t910-final-native-acceptance.json) repeated selected-102
UI approval/execution/replay (ledger two to three), table-free current-record-101
post-commit response loss (three to four), SQL-inspected retry and stale-list failure
(still four). The final runtime's Docker 508/typecheck/conformance checks passed.

Independent reviewers found no remaining blocker after reproducing and closing
the lossy-JSON snapshot issue. They independently exercised mutation and pending
call controls; they did not repeat the mounted HTTP/native suites.

## Bounds

Ordinary UI/Livewire work outside the shared helper is outside component exclusion.
Separate coordinators do not coordinate. Deferred writes are not transactional;
partial local state is possible on failure, but the failed helper does not dispatch.
No automatic retry, rollback, production/payment, performance or general native
interoperability guarantee. Receipts, challenge/correlation identifiers, signed
snapshots and credentials are omitted from retained observations.
