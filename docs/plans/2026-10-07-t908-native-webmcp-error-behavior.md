# T-908 — Native WebMCP Error Behavior

Status: Owner-approved opt-in scope; implementation underway, acceptance pending.
Baseline: T-907 is merged and locally accepted; alpha.1 registry packages remain unchanged.

Actual Phase 0 native evidence invalidated the preferred sanitized rejection:
all eight rejection forms produced empty errorText in Chrome 154 / official
client 1.10.1. Baseline 32/32 and canonical/22 HTMX fixtures pass. The delivery
sequence below remains Proposed beyond Phase 0. See
[observations and representation options](../reviews/t908-native-characterization.md)
for baseline observations.

## Accepted Phase 1 correction

The owner approved Option A, superseding the original sanitized-rejection
preference below. D-079 is Accepted. The optional constructor result mode is
`envelope`; default/passthrough stays compatible. Every resolved value is nested
in `kind: 'surfacerelay.webmcp.execution.v1'`, status `returned`, with explicit
undefined versus value output. Any driver rejection becomes a fixed safe
`execution_failed/unknown` surface arm without inspecting the rejected value.
Only the callback's pre-abort branch returns `cancelled/not_dispatched`; driver
cancellation after initiation retains natural completion or unknown failure.
Public types, adapter schema/fixtures and migration guide accompany this change.
Real Chrome additionally confirmed resolved undefined is stringified by the
old native channel, null remains null, and envelope-shaped business output is
ordinary output; explicit nesting avoids these interpretation collisions.

The original proposal below remains design context. Its unselected native
rejection preference and undecided gate text do not override this accepted
correction. Delivery acceptance beyond implementation remains to be verified.

## Problem Statement

An agent can execute real page-bound SurfaceRelay tools, but some framework or
transport rejections reach native Chrome as `Error` with an empty `errorText`.
The caller cannot distinguish a lost/stale page from a failure whose business
outcome is unknown. Blind retries of consequential operations can be unsafe.

T-907 demonstrated this difference directly. A server Action Result carrying
`authorization_denied` remained readable as structured output. An old list/edit
page rejected by HTTP 409, or an old tenant-scoped edit page rejected by HTTP 404,
produced a native error without useful text. The SQL ledger remained unchanged
in those specific tests. That observation does not mean every 404/409 or network
failure proves no effect, or that HTTP status alone establishes the domain cause.

The registration lifecycle currently passes driver promise rejections through.
The Livewire driver intentionally preserves the original `$call` rejection.
Existing tests also require exact cancellation reasons and registration rollback.
These are compatibility boundaries, not incidental implementation details.

## Solution

Make the native execution failure boundary readable and safe for the agent while
preserving trusted server results, exact binding authority, and dispatch semantics.
Prefer a bounded change in the WebMCP projection execution wrapper. Keep direct
BindingDriver consumers and server Action Result behavior compatible.

The first implementation step must characterize the actual installed Chrome and
official MCP connection: compare plain-object, primitive, standard Error and
AbortError rejection, plus successful structured server output. A sanitized Error
is the preferred presentation only if the native tool exposes its safe text.
If the browser/client discards that text, stop at the representation decision
gate; do not claim that unit tests solve the native problem.

There must be no fabricated core `failed`/`rejected` result, fabricated server
correlation ID, automatic retry, hidden remount, rollback claim or new permission
authority. Unknown outcome must remain unknown. Recovery text is advisory and
must not issue an application action or confer permission to repeat a write.

## User Stories

1. As an agent, I want a readable native failure instead of an empty error.
2. As an agent, I want a trusted server denial to remain a server denial.
3. As an agent, I want `confirmation_required` to remain distinct from completion.
4. As an agent, I want a successful business payload to remain unchanged.
5. As an agent, I want a stale page to fail without finding a replacement target.
6. As an agent, I want unknown transport failures to prevent automatic write retries.
7. As an agent, I want authentication recovery to require the real user session.
8. As a user, I want approval alone to create no business effect.
9. As a user, I want selection changes to require the appropriate fresh approval.
10. As a user, I want revoked permissions to remain authoritative at invocation.
11. As a tenant owner, I want old tenant/page authority to remain unusable.
12. As a developer, I want direct driver results/rejections to retain their contract.
13. As a developer, I want registration failures to retain rollback behavior.
14. As a developer, I want cancellation to preserve the existing dispatch frontier.
15. As an operator, I want post-dispatch uncertainty to be explicit.
16. As a security reviewer, I want no raw response, receipt, identity or stack leak.
17. As a security reviewer, I want hostile error objects to be handled safely.
18. As a package consumer, I want the new runtime tested as a built artifact.
19. As a maintainer, I want one documented failure boundary, not another protocol stack.
20. As a maintainer, I want a reproducible Chrome/SQL regression and scoped cleanup.

## Implementation Decisions

### Three separate result channels

| Channel | Authority | Planned behavior |
| --- | --- | --- |
| Successfully resolved driver value | Application/driver output policy | Pass through exactly; do not reinterpret keys or invent a business status |
| Trusted server Action Result | Server invocation pipeline | Preserve `succeeded`, `rejected`, `failed`, `confirmation_required`, error code and correlation supplied by the server |
| Rejected browser/framework execution | Surface/runtime failure | Provide safe surface failure presentation; never masquerade as a server Action Result |

The Chrome client's outer `Completed` means the callback returned a value. It
does not imply the nested application status is `succeeded`. An HTTP failure
before a readable server result and a core authorization denial are different
observations. The plan must not flatten those distinctions.

### Preferred seam and allowed changes

- Primary seam: invoke the actual registered WebMCP tool callback through the
  existing registration compatibility port, with an existing DriverRegistry.
- Prefer a small private execution-failure normalizer in browser projection.
  Do not create a generic error framework, telemetry system or recovery engine.
- Normalize invocation rejections only. Snapshot preflight, duplicate identity,
  registration rollback and registration lease cleanup retain their behavior.
- Existing direct driver return/rejection identity remains unchanged unless
  a concrete incompatibility requires a separately reviewed scope correction.
- Direct driver and WebMCP callback cancellation need separate tests. The native
  cancellation presentation is an explicit decision gate: preserve cancellation
  meaning and dispatch behavior; document any callback reason-identity change.
  Do not silently weaken the existing exact-reason tests.
- No HTTP-status/domain mapping based on raw exception shape, rendered HTML,
  console scraping, error-message regex or arbitrary caller-controlled `code`.
- If safe outcome metadata needs driver support, propose the smallest explicit
  internal provenance mechanism. Do not use properties forged on an arbitrary
  rejected object as authority, or infer phase solely from an error code.
  Publicly exported runtime error constructors, `instanceof`, a copied prototype
  or a mutable `code` are not throw-site provenance. An error built with the real
  exported class after dispatch still needs the conservative unknown branch
  unless independent private provenance proves a narrower classification.

### Failure and recovery policy

| Observation | What is known | Agent guidance | Dispatch/outcome rule |
| --- | --- | --- | --- |
| Local missing/expired exact binding before invocation | Exact local preflight failed | Refresh the page and rediscover; re-evaluate intended action | No automatic retarget or dispatch |
| Local invalid input/target | Local validation failed | Correct input or integration; do not guess authority | No dispatch when proven by the preflight path |
| Unsupported/unavailable runtime | Compatibility boundary failed | Stop and repair configuration | Claim no dispatch only with exact path evidence |
| Trusted server `authorization_denied` | Server rejected authorization | Stop; require legitimate permission/session recovery | Preserve server result, no auto retry |
| Trusted server `confirmation_required` | Server requires approval | Obtain real visible approval; caller boolean is insufficient | Approval alone is not execution |
| Trusted server `idempotency_conflict` | Key is bound to another intent | Stop; do not substitute a key or silently change intent | Preserve server result |
| HTTP 401/403/404/409 without authoritative typed result | Transport failed; domain cause not established | Safe generic failure; re-authenticate/rediscover only if independently supported | No speculative domain classification or automatic write retry |
| Network disconnect, timeout, HTTP 5xx, unreadable response after initiation | Server effect may have happened | Verify application evidence using an already authorized mechanism before deciding | Unknown outcome; no rollback/no-effect claim |
| Caller cancellation before dispatch | Only known if exact no-dispatch frontier is proven | Report cancellation, do not start work | No dispatch |
| Caller cancellation after dispatch | Existing operation may still finish | Preserve natural result where the driver currently does so | No broad abort or rollback claim |
| Unclassified/hostile rejected value | No safe provenance | Fixed generic failure with outcome uncertainty | Never stringify or retry |

Livewire currently tracks `onSend` internally for cancellation. A failure such
as unavailable interception can occur both before initiation and after `$call`
has been initiated. A shared error code is not enough to claim no dispatch.
For HTMX, deferred confirmation and post-frontier request failures also retain
the existing unknown-outcome semantics. T-908 must preserve those safeguards.

### Safe presentation policy

- Use short English text from a closed application-owned catalog.
- Only recognize audited runtime error types with safe provenance; unrecognized
  errors use the generic branch, even when they mimic a known name/code.
- Do not read or return raw `message`, `stack`, `cause`, response body, headers,
  endpoint URLs, component/binding/tenant/actor IDs, receipts or user input.
- Do not call `String(error)`, `JSON.stringify(error)`, `toString`, custom getters
  or arbitrary enumeration to produce presentation. Detection must also tolerate
  proxies/getters that throw; the normalizer must not become another failure.
- Never attach the original error as the outward cause. Preserve diagnostic
  evidence only through existing authorized, payload-minimized mechanisms.
- Do not add a default console logger or client audit stream containing payloads.
- Do not claim complete error-detail confidentiality in the separate developer
  console/network surface; qualify the agent-visible boundary precisely.
- Exact machine-readable codes/message grammar remain Proposed until native
  characterization and compatibility review. D-026 remains separate.

### Decision gate and migration impact

The preferred approach is a sanitized native rejection, retaining the WebMCP
error channel. It must prove a nonempty, fixed, safe message through the installed
Chrome DevTools MCP client. This is not yet an implemented/public error contract.

If that channel cannot carry safe text, alternatives must be compared explicitly:
an upstream browser/client defect versus a documented projection-only failure
envelope. A resolved envelope can be reported as outer `Completed`, collide with
arbitrary application outputs and change consumer handling; it cannot be adopted
as an invisible fallback. An upstream fix is not proof the pinned client supports it.

Before adopting a new outward contract, record an Accepted decision with its
schema/fixtures/conformance evidence and migration impact. No core Action Result
or Action Definition fields should change. New public adapter types/exports also
require package API and generated type/export verification in the same change.

## Testing Decisions

### Primary regression boundary

Use the registered callback as the highest existing test seam. The real native
browser check is the acceptance oracle for message visibility; a fake browser
port passing is insufficient. Driver-level tests protect existing behavior rather
than implement a second presentation policy. Assertions describe outputs, absence
of leaks, dispatch counts and ledger effects, not private helper calls.

### Required matrix

| ID | Scenario | Required observable assertion |
| --- | --- | --- |
| E01 | Resolve arbitrary business object | Exact value/identity preserved; no envelope collision |
| E02 | Resolve each existing core status | Server error/confirmation/data/correlation remain unchanged |
| E03 | Synchronous driver throw and asynchronous rejection: plain object, string, number, null, undefined | Native failure has safe nonempty presentation; no coercion leak; both initiation forms caught |
| E04 | Reject raw Error with sensitive message/cause | Safe catalog text only; no seeded secret markers |
| E05 | Cyclic object, throwing getter/toString, revoked proxy or throwing getPrototypeOf, through synchronous throw and asynchronous rejection | Generic safe failure, no secondary normalization error or hostile detection side effect |
| E06 | Forged name/code/phase/prototype, mutated code, or a real exported runtime error class constructed after dispatch | Type identity/instanceof is not private throw-site provenance; no stronger authority/outcome classification |
| E07 | Supported typed local failure | Correct safe presentation, exact target remains unchanged |
| E08 | Pre-aborted execution | No driver dispatch; cancellation semantics and intentional compatibility decision covered |
| E09 | Cancellation/unknown failure after initiation | No claim of rollback/no effect; no automatic retry |
| E10 | Partial registration failure | Previously registered generation aborted; original registration error preserved |
| E11 | Real stale Filament list/edit page | Native message nonempty; SQL unchanged for the exercised stale case |
| E12 | Real permission revocation | Server `authorization_denied` passes through; no extra effect |
| E13 | Real tenant switch and expired receipt | Old authority rejected; fresh approval requirement preserved |
| E14 | Lost response after a simulated effect commits | Outcome remains unknown; one effect, no automatic second call |
| E15 | Same exact authorized idempotent replay | Existing one-effect behavior preserved |
| E16 | HTMX failure/deferred-confirmation control | D-074 unknown outcome and D-078 business-output isolation preserved |
| E17 | New artifact installed in clean consumer | Built candidate contains fix; control alpha.1 retains baseline reproduction |
| E18 | Local allowlist/profile/cleanup | Only task-owned local targets/resources; no personal profile or broad network access |

Livewire HTTP requests may contain unrelated framework work. Simulated faults
must be tied to the exact action/request and must not cancel, replay or manipulate
other Livewire actions. At least one request must deliberately apply a simulated
effect before its response is lost, to prevent vacuous zero-effect-only coverage.

## Delivery Sequence

### Phase 0 — Baseline and native characterization

- Reconfirm current checkout, package scripts, supported runtimes and local client.
- Run relevant existing browser driver/lifecycle/cancellation tests as baseline.
- Reproduce T-907 empty native error with the smallest local deterministic fixture.
- Compare the native rejection types using fixed, harmless payloads; retain evidence.
- Exit: observable failing case, native Error/AbortError behavior and explicit
  limits recorded. No production implementation or public contract encoded yet.

### Phase 1 — Design gate

- Select the outward presentation from native evidence and outcome-authority rules.
- Resolve cancellation reason compatibility and any needed internal provenance.
- Record decision, glossary impact, fixtures/schema and migration obligations.
- Exit: one reviewable design that can express safe failure without invented certainty.

### Phase 2 — TDD implementation

- Start with E03/E04 at the registered callback boundary: reproduce RED.
- Add the smallest normalizer/wrapper giving GREEN.
- Add E01/E02 success/server pass-through and E05/E06 hostile-value/forgery tests.
- Add cancellation/registration compatibility controls before broad integration.
- Keep modules framework/surface scoped; inspect diff for trust-boundary drift.
- Exit: narrow browser change plus behavior/security tests; direct driver contracts protected.

### Phase 3 — Real Docker/Chrome acceptance

- Generate a disposable consumer inside the repository with an ownership marker.
- Use exact registry alpha.1 Laravel/Filament dependencies and a clearly labeled
  local built browser-runtime candidate containing T-908. Do not claim the npm
  alpha.1 tarball contains unreleased changes. Compare the control and candidate.
- Use a task-specific Compose project, loopback port 4187 and isolated native Chrome.
- Perform real UI login/selection/approval and native discovery/invocation.
- Verify E11–E15 through native tool output and SQL ledger deltas, including
  effect-then-response-loss uncertainty. No real payment/provider call is involved.
- Exit: safe visible native failure, preserved trusted results and no retry-induced duplicate.

### Phase 4 — Verification and review

- Browser runtime: narrow Vitest regressions, full runtime tests, typecheck and build.
- Run existing cancellation and shared-driver conformance controls where applicable.
- Run `python scripts/validate.py` and existing schema/fixture checks.
- Run the package's current clean-consumer artifact/type/export checks if code changes.
- Repeat real Filament HTTP acceptance; expand Laravel tests only if Laravel code changes.
- Independent review checks outcome ambiguity, error secrecy, callback compatibility,
  target/session authority and the complete diff. Native replay by the reviewer
  is reported separately from source/evidence review.
- Exit: required checks pass; environment-blocked evidence is not labeled passed.

### Phase 5 — Cleanup, commit and PR

- Close task-created browser pages. Remove only owned Compose services/network/
  volumes with scoped down; remove only marker-verified demo/fault helpers.
- Preserve existing Docker images/resources, user Composer locks and local settings.
- Update current status/task/review handoff after verified acceptance; retain durable
  baseline/native/redaction/ledger evidence in the repository's review documentation.
- Commit narrowly on a dedicated branch, submit/update one PR, and check exact-head CI.
- Merge and publishing remain separate authorized steps. No new roadmap task starts
  automatically after T-908; alpha.2 is not part of implementation acceptance.

## Out of Scope

In-app browser repair; a new browser connector or custom MCP protocol stack;
WebMCP polyfills; automatic recovery/retry/retarget/remount; production banking
or real 3DS; changes to confirmation/idempotency authority; automatic permission
discovery reconciliation; D-075 Filament helper productization; general latency
benchmarks; global skill/configuration maintenance; new registry publication.

## Further Notes

Planning changes describe Proposed work, not executable conformance. This plan
does not approve a new public failure contract before the Phase 1 decision gate.
Potential implementation areas are the WebMCP registration/execution boundary,
its browser tests, optional private diagnostics and disposable native fault fixture.
Framework drivers change only if actual phase/provenance evidence requires it.
Independent source/plan review found no architectural blocker and requested the
explicit real-class forgery and synchronous/hostile-proxy cases now included in
E03/E05/E06. That review did not perform future native or implementation tests.

Official references: [Chrome imperative API](https://developer.chrome.com/docs/ai/webmcp/imperative-api),
[Chrome DevTools MCP tools](https://github.com/ChromeDevTools/chrome-devtools-mcp/blob/main/docs/tool-reference.md#webmcp).
Upstream main is reference material; the installed released package and actual
native results are authoritative for this task's compatibility proof.
