# Implementation Task Board

Status values: `TODO`, `IN_PROGRESS`, `BLOCKED`, `DONE`.

A task is DONE only after required verification passes. SurfaceRelay implements one task at a time; do not begin the next task automatically.

> Historical per-task evidence through T-505 is preserved in `docs/archive/TASKS-through-T505-preclosure.md`. Design specs, implementation plans, `STATUS.md`, `REVIEW_REQUEST.md`, PR discussions, and Git history contain detailed evidence for later tasks.

## M0 — Contract Foundation — DONE

- T-001 through T-005 — DONE.

## M1 — Laravel Kernel — DONE

- T-101 through T-110 — DONE.

## M1.1 — Hardening — DONE / REVIEWED

- Reviewed baseline: `11e7348cbee6f69fa8e502308f6db262bf78e271`.

## M2 — Livewire Binding — DONE / REVIEWED

- T-201 through T-204 — DONE / REVIEWED.

## M3 — Browser Runtime / WebMCP — DONE / REVIEWED

- T-301 through T-305 — DONE / REVIEWED / MERGED as applicable.

## M4 — Production Trust Controls — DONE / REVIEWED / MERGED

- T-401 through T-404 — DONE / REVIEWED.

## M5 — Filament Vertical — DONE / REVIEWED / MERGED / MAIN REVALIDATED

- T-501 through T-505 — DONE / REVIEWED / MERGED / MAIN REVALIDATED.

T-505 final closure:

```text
Final feature head:       85570928b5e20277d94d2a95ec30028779966112
Merge commit:             7b95a82423012bf2824e55ba052ce78106f52e9a
Post-merge main CI:       34620944364 — 7/7 green
Final closure main:       5b22eef928d2fb1f8fac8ab13507e2f22661d3df
Final closure CI:         34621807162 — 7/7 green
PHP:                      595 tests / 3164 assertions
Browser baseline:         TypeScript typecheck + 103/103 Vitest
```

**M5 is closed.**

## M6 — HTMX Portability Proof — DONE / REVIEWED / MERGED / MAIN REVALIDATED

### T-601 — Explicit HTMX binding descriptor — DONE / REVIEWED / MERGED / MAIN REVALIDATED

```text
Decision:                    D-053 — ACCEPTED
Final feature head:          e4cbf0b3831863f4e0a7c89a0700726246b6f4b9
Merge commit:                96581ff9d12dba5487b4831c3bc146081945decb
Post-merge CI:               34657967629 — 7/7 green
Browser on main:             174/174 + typecheck
```

### T-602 — HTMX browser driver — DONE / REVIEWED / MERGED / MAIN REVALIDATED

```text
Pull request:                      #11
Decisions:                         D-054 / D-055 / D-056 — ACCEPTED
Final feature head:                ebad0f04a3b540a5a1536350038d0919ff600ebd
Merge commit:                      ee9af986f22cba45b05c59059c11e68ac46111fd
Final closure main:                83df122d84f6881a4a3541bd5c72e1108e5d1e48
Final closure CI:                  34702679885 — 7/7 green
Browser on final closure main:     17 files / 297/297 + typecheck
CodeRabbit:                        2 actionable / 2 resolved / 0 unresolved
```

### T-603 — Non-Laravel HTMX fixture app — DONE / REVIEWED / MERGED / MAIN REVALIDATED

```text
Pull request:                    #12 — test(htmx): add real non-Laravel fixture proof
Decision:                        D-057 — ACCEPTED
Final feature/review head:       076108554d6995fea65108ca07c9b64d3994d459
Merge commit:                    e98c919b90f9f19b58ae56b88e391f1abbb179e7
Post-merge main validate:        34758253025 — 7/7 green
Post-merge main fixture:         34758253003 — 8/8 Playwright
Post-merge browser:              17 files / 297/297 + typecheck
CodeRabbit threads:              4/4 actionable resolved / 0 unresolved
```

T-603 proved the HTMX adapter against a real non-Laravel Node application, real `htmx.org@2.0.10`, real Chromium DOM/network execution and the existing `prep_list.add_item@1` ActionDefinition.

### T-604 — Shared conformance against Livewire + HTMX — DONE / REVIEWED / MERGED / MAIN REVALIDATED

Design / plan:

```text
docs/superpowers/specs/2026-09-13-binding-driver-conformance-design.md
docs/superpowers/plans/2026-09-14-binding-driver-conformance.md
```

Verified implementation boundary:

- one shared executable 11-case matrix runs unchanged against production `LivewireBrowserDriver` and `HtmxBrowserDriver` through thin test-only adapters;
- shared assertions cover fail-closed target validation, expiry, Action-input mappability, exact-target stale/no-retarget behavior, dispatch counts and already-aborted no-dispatch cancellation;
- equivalent replacement identities receive zero dispatch from an old binding;
- framework-specific target shapes, lifecycles, runtime APIs, local errors, cancellation mechanisms and successful return values remain driver-owned.

TDD / executable evidence:

```text
Livewire RED head:              ae82c3d3bec6918a45932c3b11278b64b9ffe9d4
Livewire RED validate:          34793105308 — browser tests failed after typecheck passed
Livewire GREEN head:            bbb594f91ce2983adf0652fdad12d0fc05ce7509
Livewire GREEN validate:        34793156495 — browser job green
HTMX RED head:                  7b04d5e04c6d97e51039c680ee78cba374e78413
HTMX RED validate:              34793193056 — browser tests failed after typecheck passed
Implementation head:            9d49c22ac2127c7ade6e235fae488f87490feb43
Implementation validate:        34793229849 — 7/7 green
Shared matrix:                  22/22 — 11 Livewire + 11 HTMX
Full browser-runtime suite:     19 files / 319/319 + typecheck
Fresh T-603 regression:         job 103821380728 — 8/8 real Chromium
```

External review / closure evidence:

```text
Pull request:                    #13 — test(conformance): add shared Livewire/HTMX binding-driver matrix
Initial reviewed head:           e71248adb7afbce4c286b4be2504344eb3553da2
CodeRabbit risk:                 LOW
Initial actionable findings:     2 minor documentation consistency findings
Design-state fix:                26324a20a640940d138c5954351133276fb4f4fb
Needs-decision fix:              d8396e36b5e335f89f719f425597cd19ac30a7f3
CodeRabbit threads:              2/2 confirmed addressed and resolved / 0 unresolved
Review-fix validate:             34803374097 — 7/7 green
Decision-promotion head:         bfcbde90ba2892f2e192a1df8d5c7c84310b23c0
Decision-promotion PR validate:  34806672450 — 7/7 green
Decisions:                       D-058 / D-020 — ACCEPTED
Merge commit:                    2b25b5ccfbbdc9bf8a9e757f93f2cc59fe9ea080
Post-merge main validate:        34806790431 — 7/7 green
Post-merge browser:              19 files / 319/319 + typecheck
```

**T-604 is closed. M6 is closed.**

## M7 — Conformance / Ecosystem Bridges — DONE / CLOSED / MERGED / MAIN_REVALIDATED

### T-701 — Executable conformance runner — DONE / REVIEWED / MERGED / MAIN REVALIDATED

Design / plan:

```text
docs/superpowers/specs/2026-09-14-executable-conformance-runner-design.md
docs/superpowers/plans/2026-09-15-executable-conformance-runner.md
```

Implementation sequence:

```text
Task 1 — pure model/evaluator:                    8351954b95b94902ca91f3d0d8fe78c0d6675a49
Task 2 — subprocess runner/protocol tests:        ecc14a0a9ff571e9c0de20fba7e3fc2f43387a13
Task 3 — Vitest-free shared target support:       706a476b5178e9dcbfb9af942446f810cdcc470c
Task 4 — Livewire process harness:                9563299aec93508b7275a44efe82325f4a69fa26
Task 5 — HTMX process harness:                    7ef086d7547837331ca5662ebcbce49ce7b1f44a
Task 6 — canonical scenarios/manifests:           65e978393b55ffa2f908cb1518f9b768c9e3fbc3
Task 7 — CI + implemented conformance docs:       2c15515a77d2dd1d79ea970a2811ca0c40191ceb
Review-prep tracking:                              041dc9126f63225de0338bdb557446906da78c62
External-review correctness fixes:                 89a968785a09032cbf3b71f7f60c3f14b76c10ab
Concise review handoff:                            b7d2c4aee175da80f0c97d103f915f751c6b0fc7
Review-closure tracking:                           3777fce830ae2d4a1bcc64925af24563f611ea3e
Decision promotion:                                1efe11714e9d3abcd0e8a12fcb90912b23522ad4
Final feature/tracking head:                       5da153c3c0a0f6c38d9b5f01b26899efa24ea949
```

Verified review + decision evidence:

```text
Pull request:                         #14 — feat(conformance): add executable browser conformance runner
External-review RED:                  #794 / 35049387890 — expected failure on new review regressions
Review-fix validate:                  #796 / 35049689721 — SUCCESS
Final reviewed PR-head validate:      #798 / 35049891609 — SUCCESS, 7 jobs total
Review-closure push validate:         #799 / 35050355079 — 7/7 SUCCESS
Decision-promotion PR validate:       #802 / 35058068033 — 7/7 SUCCESS
Tracking-closure push validate:       #803 / 35058313792 — 7/7 SUCCESS
Python runtime:                       CPython 3.12.14
Conformance model + runner tests:     47/47 PASS
Browser runtime:                      20 files / 328/328 Vitest + typecheck
Harness build:                        PASS
Structural validation:                PASS
Canonical runtime matrix:             7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR
```

CodeRabbit external review:

- four actionable threads were opened;
- three valid findings were addressed: closed-v1 scenario/target completeness, repo-root subprocess cwd, and review-handoff concision;
- the HTMX replacement-identity suggestion was not applied because D-053 requires replacement targets to receive a new opaque source identity; CodeRabbit independently rechecked and confirmed that original finding invalid;
- all four threads were individually rechecked and resolved by CodeRabbit: **4/4 resolved / 0 unresolved**;
- no second full CodeRabbit sweep is claimed; closure evidence is thread-level recheck plus fresh CI.

Implemented v1 boundary remains:

- exactly one profile: `runtime-binding/driver`;
- exact target set: `browser/livewire`, `browser/htmx`;
- exact executable runtime scenario set: exact execution, expiry, component stale and no-silent-retarget;
- Livewire applies to all four; HTMX applies to three and receives runner-owned `NOT_APPLICABLE` for component stale;
- Python runner owns canonical selection/applicability/verdicts and enforces the closed v1 target/scenario membership;
- harnesses emit bounded raw observations only;
- one target/scenario pair uses one fresh subprocess with a fixed 10-second timeout;
- manifest commands execute from repository `ROOT`, independent of caller cwd;
- `recommendedCode` / raw `errorCode` remain advisory;
- every claimed profile requires a mandatory positive control;
- `scripts/validate.py` validates structure/configuration and never executes harnesses;
- no semantic production changes exist under `packages/browser-runtime/src/**`;
- no dependency expansion, Laravel production change, T-603 fixture behavior change, standalone-spec extraction, or T-702/T-703/T-704 implementation appeared.

Decision state after promotion:

- `D-059` — **ACCEPTED**;
- `D-060` — **ACCEPTED**;
- `D-061` — **ACCEPTED**;
- `D-026` — **PROPOSED** independently; failure-code recommendations remain advisory.

Merge / post-merge main evidence:

```text
Merge commit:                        50c8c482165a115b8b3b8cb740f123ecf4203041
Post-merge main validate:            #809 / 35080877519 — 7/7 SUCCESS
Post-merge contract validation:      PASS
Post-merge browser runtime:          20 files / 328/328 Vitest + typecheck
Post-merge Python conformance tests: 47/47 PASS on CPython 3.12.14
Post-merge harness build:            PASS
Post-merge canonical matrix:         7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR
```

**T-701 is closed.**

### T-702 — Adapter author guide — DONE / REVIEWED / MERGED / MAIN REVALIDATED

Design / plan:

```text
docs/superpowers/specs/2026-09-16-adapter-author-guide-design.md
docs/superpowers/plans/2026-09-16-adapter-author-guide.md
```

Implemented documentation:

```text
docs/adapters/README.md
docs/adapters/author-guide.md
docs/adapters/conformance.md
docs/adapters/security.md
```

Verified implementation gate:

```text
Integration:                    main fast-forward from feat/t-702-adapter-author-guide
Scope/design:                   APPROVED
Implementation:                 DONE / REVIEWED / MERGED / MAIN REVALIDATED
Verified docs/navigation head:  04e69ecb8a4d752732f2ab85eaf0f14798944e68
Implementation validate:        #821 / 35150202622 — 7/7 SUCCESS
Fast-forward main head:             ffd97c9a6608e37a4042d899e079ab751ecc5272
Post-merge main validate:           #825 / 35299100221 — 7/7 SUCCESS
Contract / scripts/validate.py: PASS
PHP matrix:                     4/4 PASS
PHP lint:                       PASS
Browser:                        20 files / 328/328 Vitest + typecheck
Python conformance:             47/47 PASS on CPython 3.12.14
Harness build:                  PASS
Canonical runtime matrix:       7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR
Decision D-062:                 ACCEPTED
Decision D-026:                 PROPOSED
Production changes:             NONE
Canonical spec changes:         NONE
Conformance semantic changes:   NONE
T-703/T-704 work at T-702 closure: NOT STARTED (historical snapshot)
```

Implemented boundary:

- the Adapter Author Guide is explicitly subordinate to canonical contracts, accepted decisions, and executable conformance semantics; it does not create a second specification;
- `docs/adapters/README.md` provides the author entry point, role picker, canonical reference map, and non-normative authority warning;
- `docs/adapters/author-guide.md` documents definition/import, runtime/binding, and surface/projection roles, the core/runner responsibility matrix, the 12-step author workflow, exact-target/runtime invariants, and portable-invariant vs Livewire/HTMX reference techniques;
- `docs/adapters/security.md` documents explicit exposure, discovery-vs-invocation, caller-input vs trusted-context authority, exact-target/no-retarget, fail-closed behavior, bounded cancellation, and review checks;
- `docs/adapters/conformance.md` carries D-059 through D-061 forward without widening them: truthful profile/capability claims, runner-owned selection/applicability/verdicts, bounded raw harness observations, repo-local process semantics, revision-bounded evidence, and bounded compatibility wording;
- D-026 remains PROPOSED and no new global error enum is introduced;
- D-062 is ACCEPTED only as the reviewed guide-authority boundary; it does not create or authorize new adapter semantics;
- Historical T-702 boundary: T-703 Laravel MCP projection and T-704 OpenAPI import remained outside T-702 and had not started at that point;
- `main..feat/t-702-adapter-author-guide` contains no semantic changes under `packages/browser-runtime/src/**`, `packages/laravel/src/**`, `spec/0.1/**`, `conformance/targets/**`, `scripts/conformance_model.py`, or `scripts/run_conformance.py`.

External review closure:

```text
Review-only PR:                 #15 — CLOSED WITHOUT MERGE
Review range:                    7d96160286a7a8a618635fa54f1bf40a3bcc2baa..618f4698f775ea5057caed0e3c7e4bcbf47f83b7
CodeRabbit actionable findings:  1 Minor
Review fix:                      d5c67a5ee403cdd6cc5805ac072dc05f6139d309
Review-fix validate:             #830 / 35299933662 — 7/7 SUCCESS
CodeRabbit threads:              1/1 confirmed addressed / 0 unresolved
Reviewed fix on main:            d5c67a5ee403cdd6cc5805ac072dc05f6139d309
Post-review main validate:       #832 / 35300067389 — 7/7 SUCCESS
```

The only actionable review finding was a documentation-plan preflight gap: `git status --short` printed dirty state without enforcing it. The plan now uses `test -z "$(git status --short)"`, matching the documented clean-working-tree gate. No adapter/runtime/spec/conformance semantics changed.

Decision promotion / closure evidence:

```text
Decision:                        D-062 — ACCEPTED
Decision-promotion head:         f37955979bf0c45878b449b5dd7c608046d94fb4
Decision-promotion validate:     #834 / 35302680228 — 7/7 SUCCESS
Contract / PHP / browser matrix: PASS
```

**Historical T-702 closure note:** T-702 was closed; T-703/T-704 did not begin automatically from that gate.

### T-703 — Laravel MCP projection using a maintained MCP implementation — DONE / REVIEWED / MERGED / MAIN REVALIDATED

Design:

```text
docs/superpowers/specs/2026-09-18-laravel-mcp-projection-design.md
```

Approved scope/design boundary:

- integration uses maintained official `laravel/mcp`; SurfaceRelay does not implement MCP protocol/transports;
- MCP integration is isolated in optional `packages/laravel-mcp`; `packages/laravel` remains MCP-independent;
- v1 projects MCP Tools only;
- exposure is an explicit exact-`id + version` MCP allow-list; `ActionRegistry::all()` is not exposure authority;
- only `portable` and `headless` actions are eligible; `page_scoped` and `browser_local` fail closed;
- MCP arguments remain untrusted Action input;
- actor/tenant authority remains existing trusted Laravel runtime state;
- confirmation receipt and idempotency key may enter only as namespaced non-authoritative metadata candidates and remain subject to existing server verification;
- invocation converges on the existing ActionBus / ActionResultNormalizer path;
- T-701 conformance scope is not broadened;
- MCP Resources, Prompts, Apps/client support and T-704 are out of scope.

Decision state:

- D-063 — ACCEPTED;
- D-064 — ACCEPTED;
- D-026 remains PROPOSED independently.

Implementation plan: `docs/superpowers/plans/2026-09-18-laravel-mcp-projection.md` — **APPROVED**.  
Implementation: **DONE / REVIEWED / MERGED / MAIN REVALIDATED**.

Task 1 evidence:

```text
Task:                           optional Laravel MCP bridge scaffold + architecture/CI guard
Implementation head:            407a8a9c7c8a2d1d14db9252e600cf4284cb3175
Validate:                        #839 / 35324030173 — 11/11 SUCCESS
Laravel MCP compatibility:       4/4 matrix jobs SUCCESS
Bridge architecture test:        1 test / 157 assertions on every bridge matrix job
Composer validate --strict:      PASS on all 4 bridge jobs
Existing validation baseline:    7/7 prior jobs remain SUCCESS
Production MCP behavior:         NOT STARTED
Task 2 exposure registry:        COMPLETE

Task 2 evidence:

```text
Task:                           explicit exact-identity MCP exposure registry
RED contract head:              d7881d9f4eed15edc45ca1a1c4260a7134c78864
RED validate:                   #841 / 35327036473 — expected FAILURE
RED proof:                      8 exposure tests ERROR — McpActionExposureRegistry class not found
GREEN implementation head:      d73811fb3f8a6c24ea47eb04ef3c3acf771dd3e5
GREEN validate:                 #842 / 35327114009 — 11/11 SUCCESS
Laravel MCP bridge suite:       9 tests / 171 assertions
Laravel MCP compatibility:      4/4 matrix jobs SUCCESS
Existing validation baseline:   7/7 prior jobs remain SUCCESS
Task 3 projection/tool work:     COMPLETE

Task 3 evidence:

```text
Task:                           exact MCP tool identity + discovery projection
RED contract head:              624392b0ba03d652100a4c7dbf7a41952afc7ed1
RED validate:                   #845 / 35328979749 — expected FAILURE
RED proof:                      9 new projection/tool tests ERROR — projector/tool classes not found
GREEN implementation head:      5892bb4361a1f829c5b2ebae9185b1d11ae4781a
GREEN validate:                 #846 / 35329092370 — 11/11 SUCCESS
Laravel MCP bridge suite:       18 tests / 190 assertions
Laravel MCP compatibility:      4/4 matrix jobs SUCCESS
Existing validation baseline:   7/7 prior jobs remain SUCCESS
Task 4 invocation work:          COMPLETE

Task 4 evidence:

```text
Task:                           bounded MCP metadata parsing + ActionBus gateway
Initial RED contract head:      59a496aeb80e061bfd84b5c00a6d35cdd044f7ce
RED test-import fix head:       b664afbf2009b303eb77d7c3effad5dee6b18ecd
RED validate:                   #849 / 35361993178 — expected FAILURE
RED proof:                      15 errors — McpInvocationMetadata / McpActionGateway classes not found
GREEN implementation head:      92034ab0752619b5da7216d3b3cb23452b50409a
GREEN validate:                 #850 / 35362116668 — 11/11 SUCCESS
Laravel MCP bridge suite:       28 tests / 236 assertions
Laravel MCP compatibility:      4/4 matrix jobs SUCCESS
Existing validation baseline:   7/7 prior jobs remain SUCCESS
Task 5 server/provider wiring:   COMPLETE

Task 5 evidence:

```text
Task:                           dynamic Laravel MCP server + service-provider wiring
Initial RED contract head:      4056ecb110100d0048fee6112afa091ade23a782
RED support-load fix head:      d9d3aa927ff9730940faf6cc156bb545d7cd5b46
RED validate:                   #853 / 35379467242 — expected FAILURE
RED proof:                      4 missing SurfaceRelayMcpServer errors + 1 exposure-registry singleton failure
GREEN implementation head:      d859d822d49f497713411b780ab434ebffc27d0b
GREEN validate:                 #854 / 35379570033 — 11/11 SUCCESS
Laravel MCP bridge suite:       33 tests / 254 assertions
Laravel MCP compatibility:      4/4 matrix jobs SUCCESS
Existing validation baseline:   7/7 prior jobs remain SUCCESS
Task 6 trust/output/audit work:  COMPLETE

Task 6 evidence:

```text
Task:                           trust + authorization + output-policy + audit convergence evidence
Initial RED contract head:      8e5dacee06c95f384563253a57513767638a1836
RED enum-case fix head:         62990a8d034c906b7bfa69292809ccfb8b526adf
RED validate:                   #857 / 35390303007 — expected FAILURE
RED proof:                      6 missing Task 6 evidence-helper errors
GREEN evidence head:            e3320c56fb7ec2ad1e5e687bfbfbed13d6be222a
GREEN validate:                 #858 / 35390396145 — 11/11 SUCCESS
Laravel MCP bridge suite:       39 tests / 307 assertions
Laravel MCP compatibility:      4/4 matrix jobs SUCCESS
Existing validation baseline:   7/7 prior jobs remain SUCCESS
Production bridge/core changes: NONE
Task 7 confirmation/idempotency: COMPLETE

Task 7 evidence:

```text
Task:                           confirmation + idempotency authority evidence
RED contract head:              1778bf9fa4d16c852ddcc03ec30aa51c34ba7561
RED validate:                   #860 / 35398293750 — expected FAILURE
RED proof:                      8 missing authority-runtime helper errors
GREEN evidence head:            8a3b099175ee85182d54be57c323ecd04b97cc3a
GREEN validate:                 #861 / 35398392055 — 11/11 SUCCESS
Laravel MCP bridge suite:       47 tests / 337 assertions
Laravel MCP compatibility:      4/4 matrix jobs SUCCESS
Existing validation baseline:   7/7 prior jobs remain SUCCESS
Production bridge/core changes: NONE
Task 8 docs/review handoff:      COMPLETE / EXTERNALLY REVIEWED
```

Confirmation evidence uses the real production `ConfirmationService`, `ConfirmationStage`, `ConfirmationScopeHasher`, and `ActionExecutionStage`. A consequential call without a receipt returns `confirmation_required`; caller `confirmed=true` does not bypass; an unapproved token candidate grants no authority; a server-approved exact-scope receipt allows one execution; and the consumed receipt cannot be reused.

Idempotency evidence uses the real production `IdempotencyStage`, `IdempotencyService`, `IdempotencyKeyValidator`, `IdempotencyKeyHasher`, `IdempotencyIntentHasher`, `IdempotencyReplayCodec`, and `ActionExecutionStage`. A required key missing from namespaced MCP metadata is rejected; a business-input `idempotencyKey` does not satisfy policy; a namespaced key permits one fresh execution; an exact retry replays without a second executor call; and the same key with different input is rejected as `idempotency_conflict`.
```

Task 6 uses the real production `ActionBus`, `ActionResultNormalizer`, `TrustedContextComposer`, `OutputPolicyStage`, and `StructuredActionPipelineAuditor`. Only unrelated pipeline stages use deterministic test pass-through handlers.

Evidence proves caller actor/tenant/current-record/current-selection input cannot become trusted runtime authority; invocation authorization denial stops before execution; sensitive output fails closed without a redactor; an explicit trusted redactor releases only its safe transformed value; and structured audit emits minimized MCP evidence without raw input, confirmation receipt, idempotency key, trusted actor/tenant values, or sensitive raw output.
```

Task 5 adds `SurfaceRelayMcpServer`, which materializes only the existing explicit MCP exposure registry through `McpToolProjector`. Registered-but-unexposed Actions remain absent; projected tools are deterministic; MCP `tools/call` reaches the existing gateway/ActionBus path; rejected and confirmation-required results remain structured ActionResult envelopes.

`SurfaceRelayMcpServiceProvider` now registers only one bridge-local `McpActionExposureRegistry` singleton backed by the host-provided `ActionRegistry`. It does not register routes, transports, authentication/OAuth policy, Actions, or implicit exposures.
```

Task 4 extracts only `io.surfacerelay/confirmationReceipt` and `io.surfacerelay/idempotencyKey` from MCP metadata; unknown metadata is ignored and never copied into trusted context or generic invocation metadata. Confirmation candidates are bounded to 4096 characters; idempotency candidates must be non-empty strings up to 240 characters.

`McpActionGateway` generates a server-side correlation ID, resolves trusted actor/tenant values only through `TrustedContextComposer`, sets `surface=mcp`, carries idempotency/confirmation candidates into their existing invocation fields, sets `bindingId=null`, dispatches the existing `ActionBus`, and normalizes through `ActionResultNormalizer`.

Security evidence proves caller `actor`, `tenant_id`, `current_record`, `current_selection`, and `confirmed=true` remain ordinary input and cannot manufacture trusted authority.

Task 4 ruling:

- because Task 3 deliberately deferred gateway creation, Task 4 extends `McpToolProjector` to receive one `McpActionGateway` and inject it into every projected `SurfaceRelayActionTool`. This keeps invocation dependency explicit and avoids container/service-locator fallback.
```

Task 3 projects exact tool names as `<action-id>.v<version>`, rejects projected names outside the MCP 128-character grammar, copies the canonical Action input schema directly, maps only `effect=read -> readOnlyHint=true`, emits no MCP output schema, and rejects duplicate/non-exposure projector input.

Implementation rulings:

- `McpActionGateway` remains owned by Task 4; Task 3 does not introduce a fake or empty invocation gateway merely to satisfy a future constructor shape. `SurfaceRelayActionTool` is discovery-only until Task 4 adds invocation.
- canonical `ActionDefinition` ID validation already excludes characters outside the MCP tool-name character set, so Task 3 does not fabricate an invalid ActionDefinition to test impossible invalid characters. The projector still retains the full MCP name-pattern guard as defense in depth.
```

Task 2 implements the explicit allow-list, Task 3 adds discovery projection, Task 4 routes invocation through the existing trusted ActionBus pipeline, Task 5 provides dynamic server/provider wiring, Task 6 proves trust/authorization/output-policy/audit convergence, and Task 7 proves confirmation/idempotency authority remains server-owned without changing production semantics.
```

Task 1 added only the optional package scaffold, empty provider, package test bootstrap, dependency-boundary guard, CI matrix, and lint coverage. No action exposure, tool projection, MCP invocation gateway, server wiring, or trusted-authority behavior has been implemented yet.

Design-head validation: `#837 / 35319627802` — **7/7 SUCCESS**. Task 1 implementation validation: `#839 / 35324030173` — **11/11 SUCCESS**.

Task 8 documentation + full-verification evidence:

```text
Documentation / review-prep head:   ca03e02abfb53c1b260e05f02014bb11c2787d58
Validate:                            #863 / 35400607926 — 11/11 SUCCESS
Laravel MCP compatibility:           4/4 matrix jobs SUCCESS
Laravel MCP bridge suite:            47 tests / 337 assertions
Base Laravel compatibility:          4/4 matrix jobs SUCCESS
Base Laravel suite:                  595 tests / 3164 assertions
PHP lint:                            PASS
Contract / scripts/validate.py:      PASS
Browser typecheck:                   PASS
Browser Vitest:                      20 files / 328/328 PASS
Python conformance:                  47/47 PASS on CPython 3.12.14
Harness build:                       PASS
Canonical runtime matrix:            7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR
Forbidden diff audit:                EMPTY
```

Task 8 created `packages/laravel-mcp/README.md`, linked the optional bridge conservatively from the root README, and corrected the stale `SurfaceRelayActionTool` class documentation without changing behavior. The bridge remains explicitly subordinate to the maintained `laravel/mcp` implementation; the host owns route/authentication/OAuth/network policy; MCP arguments remain untrusted business input; namespaced confirmation/idempotency metadata remains non-authoritative until existing server verification.

Self-review passed for scope alignment, invariant/decision consistency, dependency direction, unnecessary-complexity avoidance, brownfield safety, and verification evidence. The `main...ca03e02` audit contains no changes under `packages/laravel/src/**`, `spec/0.1/**`, `conformance/targets/**`, `scripts/conformance_model.py`, or `scripts/run_conformance.py`.

External review closure evidence:

```text
Pull request:                       #16 — feat(mcp): add optional Laravel MCP projection
Initial review handoff head:        fb71d595053c827b740eb3cd05e8e410a76672ce
Initial handoff push validate:      #864 / 35400789828 — 11/11 SUCCESS
Initial handoff PR validate:        #865 / 35400893305 — 11/11 SUCCESS
CodeRabbit actionable findings:     3 Minor
Review-fix head:                    2616213489228d5b47daeaafed108fa5c326e457
Review-fix push validate:           #866 / 35406029527 — 11/11 SUCCESS
Review-fix PR validate:             #867 / 35406031980 — 11/11 SUCCESS
CodeRabbit threads:                 3/3 confirmed addressed + resolved / 0 unresolved
Incremental review range:           fb71d595053c827b740eb3cd05e8e410a76672ce..2616213489228d5b47daeaafed108fa5c326e457
Incremental review files:           3
Incremental review result:          SUCCESS / review finished / 0 new actionable findings
```

The three reviewed fixes were bounded to design-status accuracy, a case-insensitive architecture-test guard for the base-Laravel MCP dependency boundary, and a concise `REVIEW_REQUEST.md`. No production runtime behavior, canonical schema, trusted-authority rule, or T-701 conformance semantics changed.

Decision-promotion result:

```text
Promotion basis:                    reviewed T-703 implementation at 2616213489228d5b47daeaafed108fa5c326e457
Review-closure tracking head:       8a96615e705de87b607a09844b5350940b8428ab
D-063:                              ACCEPTED
D-064:                              ACCEPTED
D-026:                              PROPOSED — unchanged
Promotion scope:                    reviewed Laravel MCP bridge boundary only
Production/runtime semantic change: NONE
Canonical spec change:              NONE
T-701 conformance change:           NONE
T-704 work at T-703 closure:        NOT STARTED (historical snapshot)
```

D-063 is accepted because the reviewed implementation uses maintained `laravel/mcp` only inside the optional bridge and preserves base-Laravel MCP independence. D-064 is accepted because the reviewed implementation exactly enforces explicit exact-identity exposure, portable/headless eligibility, untrusted MCP arguments, server-owned trusted context and confirmation/idempotency authority, and ActionBus/normalized-ActionResult convergence.

Final merge / main revalidation evidence:

```text
Final feature head:                  9c6fe28296801158ca86810fc04ed48a3099707c
Decision-promotion push validate:    #870 / 35407058010 — 11/11 SUCCESS
Decision-promotion PR validate:      #871 / 35407059787 — 11/11 SUCCESS
Pull request:                        #16 — MERGED
Merge commit:                        99551c4f796c25c560821930c8b4ffc2443aadef
Post-merge main validate:            #872 / 35407443826 — 11/11 SUCCESS
Laravel MCP compatibility:           4/4 matrix jobs SUCCESS
Laravel MCP bridge suite:            47 tests / 337 assertions
Base Laravel compatibility:          4/4 matrix jobs SUCCESS
Base Laravel suite:                  595 tests / 3164 assertions
Browser:                             20 files / 328/328 PASS + typecheck
Python conformance:                  47/47 PASS
Canonical runtime matrix:            7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR
Contract / lint:                     PASS
```

Historical T-703 closure snapshot: T-703 was **DONE / REVIEWED / MERGED / MAIN REVALIDATED**; D-063 and D-064 were ACCEPTED and D-026 remained PROPOSED. T-704 was **NOT STARTED** at that point and required its separate scope/design gate. The current T-704 state is recorded below.

### T-704 — Optional OpenAPI importer as a secondary adapter — DONE / REVIEWED / MERGED / MAIN REVALIDATED

Approved design:

```text
docs/superpowers/specs/2026-09-19-openapi-importer-design.md
```

Approved scope/design boundary:

- D-012 remains authoritative: OpenAPI is secondary/import-only and does not define SurfaceRelay architecture;
- importer output begins as an import candidate + diagnostics, not an executable Action Definition;
- no RuntimeBinding, HTTP executor, authorization, trusted-context source, MCP/WebMCP exposure, or ActionBus path is created by import;
- OpenAPI transport metadata must not silently determine SurfaceRelay risk/authority semantics;
- final Action `id + version` is explicitly resolved; `operationId`/method/path remain provenance only;
- v1 ingestion is bounded to one caller-supplied OpenAPI 3.1.x/3.2.x root document, same-document fragment refs only, and no secondary filesystem/network retrieval;
- local references must be cycle-safe/resource-bounded; Path Item ref/sibling ambiguity, unsupported schema dialects, additionalOperations, and other unsupported source constructs fail closed into diagnostics;
- callbacks/webhooks do not become independent imported actions in v1;
- canonical spec, T-701 conformance, T-703 MCP projection, and runtime binding behavior remain unchanged.

Accepted decisions:

- D-065 — candidate-only secondary importer boundary;
- D-066 — no silent SurfaceRelay semantic inference from HTTP/OpenAPI metadata;
- D-067 — bounded OpenAPI 3.1/3.2 local ingestion and reference safety;
- D-068 — OpenAPI source identity/provenance is separate from exact Action identity.

Design approval evidence:

```text
Design approval head:          814d610e40ddbccee051885895070f9d08c7a76c
Validate:                      #876 / 35408438835 — 11/11 SUCCESS
Changed paths:                 STATUS.md, TASKS.md, docs/DECISION-REGISTER.md, design spec only
Runtime/package changes:       NONE
Canonical spec changes:        NONE
T-701 conformance changes:     NONE
T-703 MCP changes:             NONE
```

Implementation plan: `docs/superpowers/plans/2026-09-19-openapi-importer.md` — **APPROVED**.  
Implementation: **DONE / REVIEWED / MERGED / MAIN REVALIDATED**.

Plan approval evidence:

```text
Plan approval head:            9c03048ffa6e9b8aaa2892df24e8d41cc0793e2d
Validate:                      #880 / 35495182605 — 11/11 SUCCESS
Changed paths:                 plan/design/STATUS/TASKS only
Implementation package:       NOT CREATED
CI definition change:          NONE
Canonical spec change:         NONE
Runtime/conformance change:    NONE
D-065..D-068:                  PROPOSED
D-026:                         PROPOSED
```

Task 1 implementation evidence:

```text
Branch:                         feat/t-704-openapi-importer
RED head:                       d09d6cd3034a1a897cfebba185a054b1cf8c4a8b
RED validate:                   #883 / 35496056306 — expected FAILURE
RED proof:                      existing 11 jobs SUCCESS; openapi-importer FAIL
Importer RED detail:            npm ci PASS; typecheck PASS; Vitest 1 failed / 2 passed
RED reason:                     minimal production entry point src/index.ts absent
GREEN head:                     185e9c2e0455831b4e896fb0176b39555742010a
GREEN validate:                 #884 / 35496138107 — 12/12 SUCCESS
Importer GREEN:                 1 test file / 3 tests PASS + typecheck + npm ci
Production dependency:          yaml ^2.9.1 only
Resolved lock examples:         yaml 2.9.1; TypeScript 5.9.3; Vitest 3.2.7
Forbidden implementation diff:  EMPTY
```

Task 1 created only the optional framework-neutral package scaffold, lockfile, TypeScript config, minimal entry point, architecture guard, and one CI job. It created no parser, reference resolver, candidate model, materializer, HTTP execution path, RuntimeBinding, trusted authority source, or exposure behavior.

Task 1 self-review passed for scope alignment, D-012/D-065 consistency, dependency direction/capability isolation, unnecessary-complexity avoidance, brownfield safety, and exact verification evidence.

Task 2 implementation evidence:

```text
Task:                           bounded JSON/YAML source parsing
RED head:                       e72e03052a289d99931a3fa52b85c5c1d8f6b97c
RED validate:                   #886 / 35496515711 — expected FAILURE
RED proof:                      existing 11 jobs SUCCESS; openapi-importer FAIL
Importer RED detail:            npm ci PASS; typecheck PASS; Task 2 parser tests 12/12 expected FAIL
Task 1 architecture regression: PASS
Initial GREEN head:             9fcbf94a1a1ee5af29d4d36c289e0c8b634f3f30
Initial GREEN validate:         #887 — typecheck FAIL (YAML generic narrowing)
First narrowing fix:            3db875b3cb5eca245f7b70bd178e163d276c6658
Fix validate:                   #888 — typecheck FAIL (sequence generic narrowing)
Final GREEN head:               19f2628511b65617a6c21ef2e0235e2af56530ed
Final GREEN validate:           #889 / 35496725520 — 12/12 SUCCESS
Importer GREEN:                 2 test files / 15 tests PASS + typecheck + npm ci
Source byte budget:             2 MiB
Document depth budget:          64
Document node budget:           50,000
JSON duplicate-key policy:      fail closed, including escaped-equivalent keys
YAML aliases/tags/duplicates:   fail closed
YAML multi-document input:      fail closed
Safe object representation:     null-prototype maps for JSON and YAML
Filesystem/network capability:  NONE
Production dependency:          yaml ^2.9.1 only
Forbidden implementation diff:  EMPTY
```

Task 2 added only bounded source parsing and importer-local diagnostics/types. It does not parse OpenAPI version semantics, resolve `$ref`, select operations, infer SurfaceRelay semantics, create candidates/materialization, or add runtime/exposure behavior.

Task 2 self-review passed for untrusted-input handling, alias/tag attack-surface containment, duplicate-key ambiguity, prototype safety, resource budgets, deterministic fail-closed diagnostics, no-I/O capability, dependency direction, brownfield safety, and exact verification evidence.

Task 3 implementation evidence:

```text
Task:                           OpenAPI version family + same-document JSON Pointer references
Initial RED head:               c7a817dd330d523f915996ac81a77c47bdef06ee
Initial RED validate:           #891 — typecheck FAIL (ref-budget constants absent)
Corrected RED head:             bdc3329b35189ede1a12c66a7a116f7625ea4bfe
Corrected RED validate:         #892 / 35497077001 — expected FAILURE
Corrected RED proof:            existing 11 jobs SUCCESS; openapi-importer FAIL
Importer RED detail:            npm ci PASS; typecheck PASS; prior 15 tests PASS; Task 3 tests 32/32 expected FAIL
GREEN head:                     4deab64fd7aa8ddc4b71660836e0d3bf06132ad1
GREEN validate:                 #893 / 35497163796 — 12/12 SUCCESS
Importer GREEN:                 3 test files / 47 tests PASS + typecheck + npm ci
Accepted OpenAPI families:      3.1.x and 3.2.x semantic patch triplets
Fragment decoding:              percent-decode exactly once, then strict JSON Pointer ~1/~0
External/file/network refs:     fail closed
Named anchors:                  fail closed
Missing/invalid pointer:        fail closed
Array pointer syntax:           exact index syntax; leading-zero indexes rejected
Reference hop budget:           32
Cycle detection:                active traversal branch
Unique-target budget:           4,096 shared across forked sibling traversals
Resolver copy behavior:         NONE — returns exact referenced value
Filesystem/network capability:  NONE
Production dependency:          yaml ^2.9.1 only
Forbidden implementation diff:  EMPTY
```

Task 3 added only source-version classification, same-document JSON Pointer parsing/lookup, importer-local ref diagnostics, and traversal budgets. It does not recursively dereference schemas, select OpenAPI operations, fetch any resource, create candidates, infer SurfaceRelay semantics, materialize Action Definitions, or add runtime/exposure behavior.

Task 3 self-review passed for D-067 alignment, no-I/O capability, strict one-pass URI-fragment/JSON-Pointer decoding, fail-closed external/anchor behavior, exact lookup/no-copy semantics, cycle/hop/unique-target bounding, dependency direction, brownfield safety, and exact verification evidence.

Task 4 implementation evidence:

```text
Task:                           root paths operation selection + provenance + parameter/security evidence
RED head:                       f1b58b9feeed38509f2620d59affbbd22acace1a
RED validate:                   #895 / 35503293113 — expected FAILURE
RED proof:                      existing 11 jobs SUCCESS; openapi-importer FAIL
Importer RED detail:            npm ci PASS; typecheck PASS; prior 47 tests PASS; Task 4 tests 18/18 expected FAIL
Initial implementation head:    97bbffd8e3c7a61f12bfd142dec1f5992c74dc65
Initial implementation validate:#896 / 35503411754 — typecheck FAIL
Type-guard fix head:            1c34d3b7bb5d921cde523e1caa23b68a4bf00c18
Type-guard fix validate:        #897 / 35503457399 — 12/12 SUCCESS
Importer at #897:               4 test files / 65 tests PASS
Final GREEN head:               cd5205d2b8918e30a77290c2d64616471aa89fb6
Final GREEN validate:           #898 / 35503516563 — 12/12 SUCCESS
Importer final GREEN:           4 test files / 67 tests PASS + typecheck + npm ci
OAS 3.1 fixed operations:       get/put/post/delete/options/head/patch/trace
OAS 3.2 fixed addition:         query
Callbacks/webhooks:             not promoted to root candidates
additionalOperations:           unsupported diagnostic
Path Item ref+sibling:          fail closed
Path Item ref-only:             same-document resolution supported
Operation budget:               1,000 encountered fixed-operation entries
Operation identity/provenance:  exact; no operationId/path/method normalization
Source prose budget:            8,192 Unicode characters; omit, never truncate
Effective parameter identity:   exact (name,in); operation overrides path level
Input flattening:               NONE
Security inheritance:           source evidence only
security []:                    explicit inherited-security removal evidence
security [{}]:                  anonymous-alternative evidence
Security OR/AND structure:      preserved as evidence; no authority granted
Request/response evidence:      preserved for Task 5; no schema materialization yet
Filesystem/network capability:  NONE
Production dependency:          yaml ^2.9.1 only
Forbidden implementation diff:  EMPTY
```

Task 4 added only bounded operation selection, exact source provenance, request/response structural evidence, effective parameter evidence, and effective security evidence. It does not infer SurfaceRelay effect/risk/idempotency/context requirements, flatten HTTP parameters into Action input, materialize schemas, create RuntimeBindings, execute HTTP, authorize callers, or expose tools.

Task 4 self-review passed for D-066/D-068 alignment, exact source identity, no semantic inference, parameter override correctness, security evidence without authority, Path Item ambiguity fail-closed behavior, operation/text resource bounds, no-I/O capability, dependency direction, brownfield safety, and exact verification evidence.

Task 5 implementation evidence:

```text
Task:                           conservative schema subset + safe input/output suggestions
RED head:                       dae76466437c5e6cf720bcb3b1cedb3f5fbaa32f
RED validate:                   #900 / 35533887718 — expected FAILURE
RED proof:                      existing 11 jobs SUCCESS; openapi-importer FAIL
Importer RED detail:            npm ci PASS; typecheck PASS; prior 67 tests PASS; Task 5 tests 52/52 expected FAIL
Initial GREEN head:             683adf0a0fbc3047bd5c69b821888bfdf916acf4
Initial GREEN validate:         #901 / 35534003405 — corrective FAILURE
Initial GREEN detail:           Task 5 52/52 PASS; one Task 4 test exposed plan-scope overreach from selector auto-enrichment
Scope-correction head:          bba9857219afadc17a9dcc72c2cbacc5420d27a4
Scope-correction validate:      #902 / 35534039877 — SUCCESS
Importer at scope correction:   5 test files / 119 tests PASS
Final GREEN head:               bca7488a080e32cdc07bed5ea9dfbebc71f97fd1
Final GREEN validate:           #903 / 35534181870 — 12/12 SUCCESS
Importer final GREEN:           5 test files / 122 tests PASS + typecheck + npm ci
Document jsonSchemaDialect:     fail closed when explicitly present
Schema-local $schema:           fail closed
Supported schema model:         whitelist-only, semantics-preserving copy
Unsupported/custom keywords:    schema_keyword_unsupported
Schema annotations:             not copied; unsupported rather than silently dropped
Schema $ref+sibling:            fail closed
Acyclic local schema $ref:      inlined without retaining source $ref
Cyclic local schema $ref:       fail closed
Schema fragment budget:         5,000 nodes including supported scalar/array keyword values
Invalid approved-keyword shapes:fail closed; no coercion
Input suggestion:               no params + no body => exact empty object schema
JSON-body suggestion:           exactly one application/json schema only
Ambiguous transport input:      unresolved + ambiguous_input_mapping
Output suggestion:              one explicit 2xx only
No-content success:             suggestedOutputSchema = null
Ambiguous/wildcard output:      unresolved + ambiguous_success_output
Prior blocking diagnostics:     prevent automatic schema suggestions
Operation selector integration: NONE in Task 5; deferred to Task 6 candidate-builder
Filesystem/network capability:  NONE
Production dependency:          yaml ^2.9.1 only
Forbidden implementation diff:  EMPTY
```

Task 5 added only conservative schema copying and reusable input/output suggestion primitives plus schema fixtures/diagnostics/limits. It does not automatically enrich selected operations, build import reports, infer SurfaceRelay policy semantics, materialize Action Definitions, create RuntimeBindings, execute HTTP, authorize callers, or expose tools.

Task 5 self-review passed for semantics-preserving whitelist handling, annotation non-leakage, custom-dialect and unsupported-keyword fail-closed behavior, local-ref cycle/sibling safety, 5,000-node resource accounting, ambiguity-safe input/output selection, prior-blocking evidence handling, no-I/O capability, plan file/scope alignment, dependency direction, brownfield safety, and exact verification evidence.

Task 6 implementation evidence:

```text
Task:                           deterministic import report + candidate-builder integration
RED head:                       5d132bb1ac019e39e2270eacc5acd0bb253db4c9
RED validate:                   #905 / 35534734087 — expected FAILURE
RED proof:                      existing 11 jobs SUCCESS; openapi-importer FAIL
Importer RED detail:            npm ci PASS; typecheck PASS; prior 122 tests PASS; Task 6 tests 10/10 expected FAIL
GREEN head:                     cd6b4e68e544f9c310f93af72b9db42623aad180
GREEN validate:                 #906 / 35534812616 — 12/12 SUCCESS
Final implementation head:      e9f1c761a378fe7978fab6c13627a2c9bc7ed8fd
Final implementation validate:  #907 / 35534896771 — 12/12 SUCCESS
Importer final GREEN:           6 test files / 132 tests PASS + typecheck + npm ci
Pipeline:                       parse -> version family -> operation selection -> schema suggestions -> report
Candidate order:                pathTemplate -> httpMethod -> operationPointer
Report diagnostics order:       sourcePointer -> code -> insertion sequence
Duplicate diagnostics:          preserved
Report diagnostic cap:          500 final entries
Overflow behavior:              499 ordinary + diagnostic_limit_reached terminal entry
Candidate diagnostics:          retained locally and aggregated into report with operation provenance
OpenAPI version on report:      preserved when source version string exists
Unsupported version:            stops before operation selection
Task 5 enrichment integration:  performed only by candidate-builder
Public package API:             importOpenApi + OpenApiImportReport
Filesystem/network capability:  NONE
RuntimeBinding/ActionBus:       NONE
SurfaceRelay policy inference:  NONE
Production dependency:          yaml ^2.9.1 only
Forbidden implementation diff:  EMPTY
```

Task 6 integrated the previously isolated parser, version classifier, operation evidence, and safe schema-suggestion primitives into one deterministic import-report pipeline. It does not materialize canonical Action Definitions, synthesize Action identity/version, create RuntimeBindings, execute HTTP, authorize callers, infer trusted context/effect/risk/idempotency, or expose MCP/WebMCP tools.

Task 6 self-review passed for deterministic candidate/report ordering, diagnostic ownership and duplicate preservation, 500-entry final-report cap, early stop on parse/version failure, no hidden execution/exposure path, importer-local diagnostics, no-I/O capability, dependency direction, brownfield safety, and exact verification evidence.

Task 7 implementation evidence:

```text
Task:                           explicit SurfaceRelay resolution + canonical Action Definition materialization
Initial RED head:               e215133ab38d3d5644f1664a41c0bb3ce0d9fb72
Initial RED validate:           #909 — typecheck FAIL in diagnostic/AJV test-contract typing
Corrected RED head:             479312aed8f4e0ac93f9fb53046bfd64ab7394d2
Corrected RED validate:         #910 / 35537328082 — expected FAILURE
Corrected RED proof:            existing 11 jobs SUCCESS; openapi-importer FAIL
Importer RED detail:            npm ci PASS; typecheck PASS; prior 132 tests PASS; Task 7 tests 39/39 expected FAIL
GREEN head:                     58d18dc201be5e50ea7e20d1747d906a3ec5a412
GREEN validate:                 #911 / 35537472977 — 12/12 SUCCESS
Importer GREEN:                 7 test files / 171 tests PASS + typecheck + npm ci
Required explicit semantics:    id/version/title/description/scope/effect/risk/idempotency/output policy/context/input+output schema choice
Action id:                      exact canonical lowercase dot grammar; max 160 bytes; no normalization
Action version:                 positive integer only
Title/description:              1..120 / 1..2000 Unicode code points; no trimming/fallback
Context requirements:           canonical enum only; unique; deterministic canonical order
OpenAPI semantic fallback:      NONE
Candidate suggestion use:       requires present non-blocked suggestion
Explicit schema copy:           safe deep copy; arbitrary caller-authored JSON Schema keywords allowed
Schema safety budgets:          64 depth / 5,000 nodes
Candidate schema copy:          deep copied; no shared mutable references
Canonical verification:         AJV Draft 2020-12 against repo canonical Action Definition schema
Provenance in ActionDefinition: NONE
Returned provenance:            exact OpenApiSourceProvenance only, separately copied
RuntimeBinding/executor:         NONE
Duplicate batch id+version:     fail closed with duplicate_action_identity
Filesystem/network capability:  NONE
Production dependency:          yaml ^2.9.1 only
Forbidden implementation diff:  EMPTY
```

Task 7 added only explicit resolution types, canonical Action Definition materialization, batch identity collision checking, public materializer exports, and test-only canonical AJV validation. It does not infer semantics from OpenAPI, create RuntimeBindings, execute HTTP, authorize callers, register Action definitions automatically, or expose MCP/WebMCP tools.

Task 7 self-review passed for D-065/D-066/D-068 alignment, exact Action identity/version handling, explicit semantic completion, trusted-context non-inference, schema deep-copy/resource safety, canonical-schema alignment, provenance separation, duplicate identity rejection, no-I/O capability, dependency direction, brownfield safety, and exact verification evidence.

Task 8 handoff preparation evidence:

```text
Implementation code head:       58d18dc201be5e50ea7e20d1747d906a3ec5a412
Implementation validate:        #911 / 35537472977 — 12/12 SUCCESS
Pre-handoff tracking head:      106cba54fe85909362cab3c1ec5f3d1db1036011
Pre-handoff validate:           #912 / 35537581965 — 12/12 SUCCESS
OpenAPI importer:               7 files / 171 tests PASS + typecheck + npm ci
Browser runtime:                20 files / 328 tests PASS + typecheck
Python conformance tests:       47/47 PASS
Runtime conformance matrix:     7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR
Laravel matrix:                 4/4 SUCCESS; 595 tests / 3164 assertions
Laravel MCP matrix:             4/4 SUCCESS; 47 tests / 337 assertions
Contract validation:            PASS
PHP lint:                       PASS
Forbidden path audit:           PASS against main merge-base 7e26d61a...
Production dependency:          yaml ^2.9.1 only
Generic OpenAPI dereferencer:   NONE
Filesystem/network retrieval:   NONE
Internal SurfaceRelay deps:     NONE
D-065..D-068:                   PROPOSED
D-026:                          PROPOSED
```

Task 8 documentation/review handoff evidence:

```text
Documentation handoff head:     201b030b91c8642387514510ea120cd9282122f2
Documentation handoff validate: #913 / 35538173232 — 12/12 SUCCESS
Changed paths:                  package README / root README / REVIEW_REQUEST / STATUS / TASKS only
Implementation/spec changes:    NONE
Conformance/runtime changes:    NONE
CI definition changes:          NONE
Review state:                    EXTERNAL REVIEW PENDING
D-065..D-068:                    PROPOSED
D-026:                           PROPOSED
Merge:                           NOT PERFORMED
T-704 closure:                   NOT PERFORMED
```

External review round-one evidence:

```text
Pull request:                     #17 — OPEN / NOT MERGED
Reviewed head:                    f004463185876b3a40d8fd71a4519e81b1f7c3f7
CodeRabbit findings:              4 actionable + 1 nitpick
Review-fix RED head:              ed591be06b8455a1e0e1e1f5047af0db576b1d7a
Review-fix RED push/PR:           #930 / #931 — expected FAILURE
RED importer evidence:            177 prior PASS / 7 new regression tests FAIL
Review-fix code head:             e88cd9e34e515aa63df0a7ab87c8d8ec4495edf2
Review-fix push/PR:               #932 / #933 — 12/12 SUCCESS each
Importer after fixes:             7 files / 184 tests PASS + typecheck + npm ci
Fixed:                            chained Parameter refs
Fixed:                            JSON-null ref target fail-closed across Path Item / request-response / parameter / schema callers
Fixed:                            cross-dimension blocking ambiguity suppresses both schema suggestions
Docs fix:                         stale pre-implementation snapshots marked historical; concise review handoff restored
D-065..D-068:                     PROPOSED
D-026:                            PROPOSED
Merge:                            NOT PERFORMED
T-704 closure:                    NOT PERFORMED
```

External review closure evidence:

```text
Review PR:                        #17 — OPEN / NOT MERGED
Round-one reviewed head:          f004463185876b3a40d8fd71a4519e81b1f7c3f7
Round-one findings:               4 actionable + 1 nitpick
Actionable closure:               4/4 CodeRabbit-confirmed addressed + resolved
Review-fix RED:                   ed591be06b8455a1e0e1e1f5047af0db576b1d7a
Review-fix code head:             e88cd9e34e515aa63df0a7ab87c8d8ec4495edf2
Review-fix code CI:               push #932 + PR #933 — 12/12 SUCCESS each
Review-fix importer:              7 files / 184 tests PASS + typecheck + npm ci
Re-review/docs head:              b83632c790b893f993630c452c47a41444832918
Re-review/docs CI:                push #934 + PR #935 — 12/12 SUCCESS each
CodeRabbit incremental re-review: SUCCESS / Review completed
New inline findings:              0
Unresolved review threads:        0
D-065..D-068:                     PROPOSED
D-026:                            PROPOSED
Merge:                            NOT PERFORMED
T-704 closure:                    NOT PERFORMED
```

T-704 external review is **CLOSED** with no remaining actionable findings.

Decision promotion evidence:

```text
D-065:                            ACCEPTED
D-066:                            ACCEPTED
D-067:                            ACCEPTED
D-068:                            ACCEPTED
Decision-promotion head:         f014c7253a91d78e3b03b7761d1fc5d4ce83ba5a
Decision-promotion push CI:      #938 / 35625605195 — 12/12 SUCCESS
Decision-promotion PR CI:        #939 / 35625610665 — 12/12 SUCCESS
Decision-promotion diff:         decision/tracking docs only
D-026:                            PROPOSED
Review PR:                        #17 — OPEN / NOT MERGED
Merge:                            NOT PERFORMED
T-704 closure:                    NOT PERFORMED
```

D-065 through D-068 are accepted only for the externally reviewed T-704 implementation boundary. D-026 remains independently PROPOSED.

The next gate is **merge + post-merge main revalidation planning only**. Do not merge, close T-704, or begin later work automatically.

Final closure evidence:

```text
Review PR:                       #17 — MERGED
Decision state:                  D-065 / D-066 / D-067 / D-068 ACCEPTED
D-026:                           PROPOSED
Merged reviewed head:            7207be2044f24b63b87485afe82e9f1c6bfc36dd
Merge commit:                    7890907c408c1e529c955c8d37520ac8d83ffe1f
Post-merge main validate:        #942 / 35665487192 — 12/12 SUCCESS
Post-merge main head:            7890907c408c1e529c955c8d37520ac8d83ffe1f
Final state:                     DONE / REVIEWED / MERGED / MAIN REVALIDATED
```

T-704 is closed. No later task begins automatically; any further work requires a separate explicit scope/design gate.

### M7 closure / state reconciliation — DONE / REVIEWED / MERGED / MAIN REVALIDATED

Closure scope is documentation-only:

- reconcile current-facing state after T-704 closure;
- verify the M7 roadmap outcome against T-701 through T-704;
- keep explicitly historical design/plan snapshots intact;
- update live adapter/package docs that still describe T-703/T-704 as future work;
- preserve D-026 as PROPOSED;
- make no production, canonical-spec, conformance-semantic, dependency, publication, or release-contract change;
- do not create T-705 or begin a later milestone.

```text
main head:                       b2de658f9feaee18ac60cb0c2786b45c3d95e342
main Validate:                   #943 / 35666645607 — 12/12 SUCCESS
reconciliation head:             8575364732f2a8c574fcf0eb30af7eb5bd028a9c
reconciliation Validate:         #945 / 35668629999 — 12/12 SUCCESS
review PR:                       #18 — MERGED
review findings:                 2 Minor — both addressed / CodeRabbit-confirmed
review-fix head:                 186c67f3840a63f5b8fd6c803ae4be9e7632c586
review-fix push/PR:              #948 / #949 — 12/12 SUCCESS each
review-closure head:             6ff7ad666e9dcaffaa1f68937c66d2e864e336fb
review-closure push/PR:          #950 / #951 — 12/12 SUCCESS each
review threads:                  0 unresolved
merge commit:                    893e39582cd80e07f1a455d1cb5a1d7c9d1ca35c
post-merge main Validate:        #952 / 35680926172 — 12/12 SUCCESS
T-701..T-704:                    DONE / REVIEWED / MERGED / MAIN REVALIDATED
D-059..D-068 except D-026:       ACCEPTED
D-026:                           PROPOSED
production/spec semantic change: NONE
next milestone implementation:   NOT STARTED
```

## Pre-reassessment boundary

At M7 closure, M6 was **DONE / REVIEWED / MERGED / MAIN REVALIDATED**; T-701 through T-704 were **DONE / REVIEWED / MERGED / MAIN REVALIDATED**; `D-059` through `D-068` were **ACCEPTED** except `D-026`, which remained **PROPOSED**. M7 was **DONE / CLOSED / EXTERNALLY REVIEWED / MERGED / MAIN REVALIDATED** on `main`, with no T-705 or later milestone implementation started. The next required action at that point was this product/scope reassessment gate.

## Post-M7 product/scope reassessment — DONE / RECOMMENDATION READY

This is not a numbered implementation task and does not open M8.

Evidence-based finding:

- M0 through M7 already provide substantial capability, trust controls, portability proof, conformance, and ecosystem bridges;
- the repository still exposes development/source-tree workflows rather than a clean downstream-consumer installation contract;
- the TypeScript packages remain 0.0.0-dev + private:true and have no reviewed distribution/build/export contract;
- surfacerelay/laravel-mcp still requires surfacerelay/laravel:dev-main through a local Composer path repository;
- the root README has development commands but no supported external installation/getting-started path;
- there is no GitHub release, release workflow, changelog, root vulnerability-reporting policy, or clean consumer-project smoke evidence;
- roadmap release labels 0.1.0-alpha through 0.4.0-beta were never published, so release numbering must be reassessed independently rather than inferred mechanically from completed milestones.

Recommended next milestone candidate:

```text
M8 — Consumer & Release Readiness
Status: PROPOSED ONLY / NOT OPENED / NOT STARTED
```

Candidate outcome:

> A clean downstream project can install the intended SurfaceRelay release-candidate artifacts without monorepo path/dev-main coupling, follow a minimal documented setup, exercise the supported happy path, and reproduce bounded compatibility evidence.

Safety boundary:

- release readiness is not registry publication;
- no npm/Packagist publish;
- no GitHub tag/release;
- no package version bump;
- no T-705/T-801;
- no new adapter/capability;
- no canonical contract/conformance semantic change;
- no D-026 promotion;
- no public compatibility promise before clean-consumer evidence.

The first M8 scope/design gate, if approved separately, must resolve:

1. which packages are intended to be public in the first release candidate;
2. whether package versions move together or independently;
3. how PHP package constraints replace dev-main/path coupling without premature registry publication;
4. the TypeScript build/export/package-content contract;
5. the clean-consumer fixture/matrix and minimum end-to-end proof;
6. installation/getting-started, changelog, vulnerability-reporting, compatibility, and release-checklist boundaries;
7. the later explicit go/no-go gate for registry publication and tags.

## Pre-M8-design boundary

At reassessment closure, M7 remained **DONE / CLOSED / EXTERNALLY REVIEWED / MERGED / MAIN REVALIDATED** on `main`; M8 was only a proposed milestone candidate; no T-705/T-801 existed; and no publication/tag/release or implementation action was authorized. The next explicit gate at that point was this M8 scope/design gate.

## M8 — Consumer & Release Readiness — DESIGN_APPROVED / IMPLEMENTATION_IN_PROGRESS

Outcome candidate:

> A clean downstream project can install the intended SurfaceRelay release-candidate artifacts without monorepo path/dev-main coupling, follow a minimal documented setup, exercise the supported happy path, and reproduce bounded compatibility evidence.

Design source:

`docs/superpowers/specs/2026-09-22-consumer-release-readiness-design.md`

Proposed decisions: D-069 through D-073.

### T-801 — Release-candidate artifact contract — DONE / REVIEWED / MERGED / MAIN_REVALIDATED

Implementation plan: `docs/superpowers/plans/2026-09-22-release-candidate-artifact-contract.md`

Shared artifact version input, exact clean source-revision binding, `.tmp/release-candidate` staging containment, deterministic content manifests, SHA-256 archive/content evidence, no-publication guardrails, and a dedicated CI contract job. Real Composer/npm package building remains T-802/T-803.

### T-802 — Laravel artifact + clean consumer proof — DONE / REVIEWED / MERGED / MAIN_REVALIDATED

Implementation plan: `docs/superpowers/plans/2026-09-27-laravel-release-candidate-clean-consumer.md` — **APPROVED**.

Planned scope: Composer ZIP for `surfacerelay/laravel`, exact staged prerelease version without source-manifest mutation, PHP 8.3/8.4 × Laravel 12/13 artifact-repository install matrix, one PHP 8.4 + Laravel 13 production ActionBus smoke, and explicit no-path/dev-main/source-link evidence.

Step 1 baseline: `main@12de01ae0539a4862adc1acda1cb36b7f4a00fd5`, Validate #1004 (`36279906163`) **13/13 SUCCESS**. Feature branch: `feat/t-802-laravel-artifact-clean-consumer`. No implementation/test/CI/package changes were made in Step 1.

Step 2 RED head: `eb727e294420f2b44efa663ed4c806ca9d6e953a`. The approved artifact-content test file contains 10 contract tests. Focused execution is **10 tests / 10 ERROR** because `scripts.laravel_release_candidate` does not exist yet. Existing Validate #1007 (`36299355134`) remains **13/13 SUCCESS**; no builder, consumer, CI matrix, package production, spec, or conformance implementation changed.

Step 3 GREEN head: `331490b4942e257749d1e104f20365b5d4583cc7`. `scripts/laravel_release_candidate.py` is the only Step 3 implementation file. The focused artifact suite is **10/10 PASS** in isolated scratch execution, while Validate #1009 (`36315361027`) is **13/13 SUCCESS** with contract validation and publication guard green. No `packages/laravel/**`, `spec/**`, `conformance/**`, consumer, Composer-matrix, smoke, or dedicated-CI implementation changed.

Step 4 RED: 7 new consumer/isolation test methods were added at `dc341335f4db17ed04ab776fbda6d684643a6dbe`. A temporary discovery bridge made them authoritative in existing CI; after correcting one unrelated Step-3 test-scope lifetime bug at `ad834fde68175700b6533c39c6d832f6cdfdc065`, Validate #1013 (`36317673759`) produced the isolated expected result: **12 SUCCESS / 1 FAILURE**, only `release-contract` failing. That job ran 66 tests and reported **11 expected error records** from the 7 new methods/subtests; the existing 10 T-802 artifact tests passed. All RED errors are missing Step-5 consumer tooling seams, not product regressions.

Step 5 GREEN is complete. Initial consumer manifest/isolation/archive seams at `9aad5add6299b70528fcb4e4bf0d59347b9dd605` produced 66/66 PASS in Validate #1016. Self-review then enforced the full Step-5 plan wording by adding bounded workspace generation + Composer-installed metadata verification: micro-RED `a27c25c83976521cde6e2ffee916e969f136f2be` yielded Validate #1017 (**12 SUCCESS / 1 expected FAILURE**, 69 tests / 3 expected errors), and final implementation `80f0fef6e4c4c2e41a3d338438d9bf989f4a2962` yielded Validate #1018 **13/13 SUCCESS**, `release-contract` **69/69 PASS**, publication guard PASS. No Composer process/network resolution, smoke, CI matrix, package production, spec, or conformance implementation occurred.

Step 6 RED fixture head: `7c97ec9448646ab32af3e2a42bdc6635077e954a`. The permanent `scripts/fixtures/laravel-clean-consumer/smoke.php` depends only on clean-consumer `vendor/autoload.php`, minimal Laravel Application/provider registration, and the production ActionBus path. A temporary target-only PHPUnit bridge produced authoritative Validate #1023 (`36333900561`): **12 SUCCESS / 1 expected FAILURE**. Only PHP 8.4 + Illuminate 13 failed, with **596 tests / 3171 assertions / 1 failure** because the smoke exited `66` with `clean consumer vendor/autoload.php is missing`. This proves the fixture does not fall back to repository source/runtime before the artifact-installed consumer exists. The bridge is removed after evidence capture.

Step 7 GREEN is verified at `e9d205528556d2c8a7413d0bbaa6de172800d08b`. Authoritative Validate #1030 (`36335013953`) is **13/13 SUCCESS**.

Step 8 dedicated CI matrix is verified. Initial matrix head `9fb95c0e3a16b8e56a7410e708663b1625aa90e5` exposed a CI-ordering issue: Python tooling tests created untracked cache files before the checkout-clean assertion. Fix head `0e531439b59e849d046434fd932fbe5374c4f586` moved the clean check before tooling while preserving exact `git archive HEAD` artifact materialization. Authoritative Validate #1033 (`36359056975`) completed **17/17 SUCCESS**: the original 13 jobs stayed green and all four `laravel-release-consumer` legs passed. PHP 8.4 + Laravel 13 additionally emitted `SurfaceRelay Laravel clean-consumer smoke: PASS`; the other three smoke steps were skipped by design. No registry credentials, path/dev-main/source-link coupling, publication, package production, spec, or conformance changes were introduced.

Step 9 whole-task verification is complete on `86ccdc67ff268014d8948112ac07ad7bee410f87`. Exact-head Validate #1034 (`36359223941`) completed **17/17 SUCCESS**. The focused T-802 tooling suite ran **20/20 PASS** in each of the four consumer jobs; `release-contract` discovery ran **49/49 PASS** with publication guard PASS; canonical `python scripts/validate.py` passed. All four consumer legs locked/installed `surfacerelay/laravel (0.0.0-alpha1)` from the artifact repository and verified installed metadata/autoload; PHP 8.4 + Laravel 13 additionally emitted `SurfaceRelay Laravel clean-consumer smoke: PASS`. The PHP 8.4 + Illuminate 13 job completed **596 tests / 3180 assertions**, after building the Laravel ZIP from a clean `git archive HEAD` snapshot, installing it into an isolated Laravel 13 consumer through a Composer `artifact` repository, verifying exact installed metadata, and emitting `SurfaceRelay Laravel clean-consumer smoke: PASS`. Generic package-neutral prerelease identifiers tested during probing were not valid Composer require constraints, so the authoritative proof used non-public `0.0.0-alpha1`; T-801 SemVer behavior remains unchanged. The temporary GREEN bridge is removed after evidence capture.

### T-803 — Browser runtime public API + artifact + clean consumer proof — DONE / REVIEWED / MERGED / MAIN_REVALIDATED

Implementation plan: `docs/superpowers/plans/2026-09-29-browser-runtime-release-candidate-clean-consumer.md` — **APPROVED**.

Planned scope: curated root-only ESM API, ES2022 JavaScript + declarations, root-only exports map, npm-pack release-candidate tarball, isolated package-root typecheck/Vite bundle/side-effect-free smoke, and explicit no-source/deep-import/workspace/symlink evidence.

Step 1 baseline: `main@5de05ea2498bea186aa6d8d11e1726f6c1c56539`, Validate #1051 (`36562318964`) **17/17 SUCCESS**. Feature branch: `feat/t-803-browser-runtime-artifact-clean-consumer`. No implementation/test/CI/package/build changes were made in Step 1.

Step 2 RED head: `e5cfbb73b12d5fdd0d10307ec8455f3e776ca4aa`. Two approved root-API contract tests are present. Validate #1055 (`36582905214`) completed **16 SUCCESS / 1 expected FAILURE**; only `browser` failed, with the authoritative typecheck reporting only `TS2307` because `../src/index.js` does not exist. No root facade, build metadata, artifact tooling, consumer fixture, CI wiring, or existing runtime-source change exists.

Step 3 GREEN head: `85ffb7925a61747ba0c099b4cd27c646cb45233f`. The only implementation file is `packages/browser-runtime/src/index.ts`. Validate #1057 (`36589996508`) completed **17/17 SUCCESS**; browser typecheck passed, the runtime snapshot passed inside **21 test files / 329 tests**, canonical browser conformance reported **7 PASS / 0 FAIL / 0 ERROR / 1 NOT_APPLICABLE**, and HTMX fixture run #26 (`36589996536`) succeeded. No existing runtime implementation file, package metadata/build config, artifact tooling, clean consumer, or dedicated T-803 CI changed.

Step 4 RED head: `bfc8c522e1c7dd9e7e3ceee57a67ac3dfb7f52da`. The new `distribution-contract.test.ts` contains 4 tests. Validate #1059 (`36592466741`) completed **16 SUCCESS / 1 expected FAILURE**; only `browser` failed. Browser typecheck passed, then Vitest reported **22 files / 330 PASS / 3 expected FAIL**. The three failures are exactly the missing Step-5 distribution seams: package root metadata/build script, `tsconfig.build.json`, and executable build emit. Source version/private/type invariants already pass. No package/build implementation or later T-803 work exists.

Step 5 GREEN head: `71af1a28f792723f9c49be911c10130f78d937c1`. Only `packages/browser-runtime/package.json` and new `tsconfig.build.json` changed. Validate #1061 (`36609079739`) completed **17/17 SUCCESS**. Browser typecheck passed; `distribution-contract.test.ts` is **4/4 PASS**; the browser suite is **22 files / 333 tests PASS**; canonical browser conformance remains **7 PASS / 0 FAIL / 0 ERROR / 1 NOT_APPLICABLE**. Source version remains `0.0.0-dev`, `private: true` is retained, `package-lock.json` and existing runtime sources are unchanged, and no `dist/**` output is committed.

Step 6 RED head: `fa8491ba774d68c7e2f3d64c69ed4ddb9588e651` with permanent contract test file `scripts/tests/test_browser_release_candidate.py`. A temporary test-only discovery bridge exposed it to the existing release-contract glob. Validate #1064 (`36611234115`) completed **16 SUCCESS / 1 expected FAILURE**; only `release-contract` failed. That job ran **60 tests / 11 expected ERROR**: all pre-existing 49 tests passed and all 11 new browser-artifact tests failed only because `scripts.browser_release_candidate` does not exist. No builder, npm-pack staging, clean consumer, workflow change, or publication behavior exists.

Step 7 GREEN head: `279f4b8758303496e50d5e1d306cdd75f2813e6b`. The only implementation file is `scripts/browser_release_candidate.py`. A temporary GREEN discovery bridge made the permanent Step-6 suite authoritative in Validate #1067 (`36631327207`), which completed **17/17 SUCCESS**. `release-contract` ran **60/60 PASS**: 49 existing tests plus **11/11 browser artifact tests**, with publication guard PASS. The builder stages the reviewed allowlist, retains source/private invariants, uses `npm pack --json --ignore-scripts`, validates tar safety/exact file list, and reuses T-801 manifest/evidence hashes. No workflow, clean consumer, runtime source, package/build metadata, or publication behavior changed.

Step 8 RED head: `303e1ca0d65df4eff45b2ac41542f4eeee1638ab` with seven new clean-consumer/isolation test methods added to `scripts/tests/test_browser_release_candidate.py`. A temporary test-only discovery bridge exposed them to the existing release-contract glob. Validate #1070 (`36640215454`) completed **16 SUCCESS / 1 expected FAILURE**; only `release-contract` failed. That job ran **67 tests / 12 expected ERROR**: the pre-existing 60 tests passed and every new error is an AttributeError for one of six intentionally missing Step-9 seams. No permanent `main.ts`/`smoke.mjs`, consumer generator/verifier, Vite bundle/smoke wiring, workflow change, or later-step implementation exists.

Step 9 tooling GREEN head: `47bab19c3fe42d883665e237161a0d030c698c6a`. Only `scripts/browser_release_candidate.py` changed. A temporary GREEN discovery bridge made the full contract authoritative in Validate #1073 (`36649005470`), which completed **17/17 SUCCESS**; `release-contract` is **67/67 PASS** with publication guard PASS and all seven new consumer/isolation methods green. The six proven tooling seams are implemented.

Step 9 execution proof is complete at `ded8716d7ae70e058e34499328ce5aad71012374`. Permanent `scripts/fixtures/browser-clean-consumer/main.ts` and `smoke.mjs` plus one integration contract were added after the isolation RED boundary. Validate #1077 (`36705973818`) completed **17/17 SUCCESS** and `release-contract` is **68/68 PASS**. The integration test materializes `git archive HEAD`, runs the real browser-runtime build, creates the real npm tarball, installs pinned TypeScript/Vite plus the exact tarball into a fresh isolated consumer without saving SurfaceRelay to package.json, verifies physical/identity isolation, proves package-root import + TypeScript typecheck + Vite production bundle + DriverRegistry smoke, and confirms `@surfacerelay/browser-runtime/dist/driver-registry.js` is rejected by the exports map. Step 9 is now complete; no workflow change exists yet.

Step 10 dedicated CI is complete at `66991da7101a773fcce99a17b99dce608080f956`. Only `.github/workflows/validate.yml` changed. Validate #1079 (`36842264896`) completed **18/18 SUCCESS**: the pre-existing 17 jobs stayed green and the new single non-matrix `browser-release-consumer` job passed. That job uses checkout with `persist-credentials: false`, proves a clean exact checkout before tooling, uses Node 22 + Python 3.12, materializes `git archive HEAD`, runs browser-runtime `npm ci` + `npm run build` inside the snapshot, builds the non-public `0.0.0-alpha1` npm tarball, runs the browser release-candidate tooling tests, and executes the existing isolated install/package-root import/typecheck/Vite/smoke/deep-import-rejection proof. No npm token/auth, publish, tag, release, public SemVer, runtime-source, spec, conformance, or decision change was introduced.

Step 11 whole-task verification is complete at `0d311d8acb8dc80a82ee62afeca0ffc4ee7ebffa`. Exact-head Validate #1081 (`36842525045`) completed **18/18 SUCCESS**. The required verification surfaces are all green at that revision: browser-runtime typecheck/tests; exact-snapshot ESM/declaration build; focused `scripts.tests.test_browser_release_candidate`; release-candidate discovery; publication guardrails; canonical `scripts/validate.py`; and the dedicated isolated candidate install/package-root import/typecheck/Vite/smoke/deep-import-rejection proof. The installed-package verifier also rejects symlink/source resolution before execution. Step 11 adds no implementation and does not perform the Step-12 forbidden-diff/semantic-mutation audit.

Step 12 forbidden-diff/semantic-mutation audit is complete against baseline `5de05ea2498bea186aa6d8d11e1726f6c1c56539` and final implementation head `66991da7101a773fcce99a17b99dce608080f956`. The implementation diff has 14 bounded T-803 files and **0 forbidden paths**. All 16 pre-existing browser-runtime source files are blob-identical to baseline; the only new source module is `src/index.ts`. `package-lock.json` and `docs/DECISION-REGISTER.md` are unchanged; Laravel, Laravel-MCP, OpenAPI-importer, `spec/**`, and `conformance/**` are absent from the diff. Source package version/private state remains `0.0.0-dev` / `true`. No publication command or registry-auth workflow wiring was introduced; clean-consumer tooling strips NPM credential variables. Current tag refs and GitHub Releases are empty. No corrective implementation is required.

Step 13 handoff is **READY** on 2026-10-02. Pre-handoff head `fd9973d2e9b58755fe8e9088f8e07cf652a09151` has live-verified Validate [#1085 / 36934575893](https://github.com/kefyusuf/surfacerelay/actions/runs/36934575893) **18/18 SUCCESS**, browser **333 tests PASS**, focused tooling **19/19 PASS**, and isolated root import/typecheck/Vite/smoke/deep-import rejection PASS. Updated `STATUS.md`, `TASKS.md`, and `REVIEW_REQUEST.md` only; no implementation changes. Local Windows subprocess tests cannot launch bare npm (browser 332 PASS / 1 ERROR; Python 8 PASS / 11 ERROR); direct npm.cmd build/typecheck and canonical/publication checks pass. Ubuntu CI is the authoritative consumer evidence. T-803 is **DONE / REVIEW HANDOFF**, external review pending. Next gate: **T-803 external review/finding disposition only**, not T-804 or merge.

### T-803 external-review finding — ACCEPTED / RED_NOT_STARTED

PR #21 is OPEN / non-draft / NOT MERGED. CodeRabbit reviewed `34d8cf0dc7e294af6566d24bdeeb4ce9395720bc` and posted [one actionable Minor](https://github.com/kefyusuf/surfacerelay/pull/21#discussion_r4163289925): bare npm subprocess calls fail on Windows. The finding is verified against current code and matches the documented local ENOENT / WinError 2 failures. Reviewed-head Validate [36971286670](https://github.com/kefyusuf/surfacerelay/actions/runs/36971286670) is **18/18 SUCCESS** on Ubuntu.

Bounded amendment: portable npm launch in `scripts/browser_release_candidate.py`, its focused tests, and `packages/browser-runtime/tests/distribution-contract.test.ts`. Prove RED first, cover missing npm with a negative test, preserve argument boundaries and credential stripping, then verify Windows browser/tooling checks and exact-head CI. No runtime/public API/metadata/dependency/CI matrix/spec/conformance/decision change is included. The review thread remains open; no RED or GREEN implementation exists in this disposition.

Next gate: **focused RED tests for this Minor only**. T-804, thread resolution, and merge do not begin automatically.

### T-804 — Release-facing documentation and compatibility policy — IN_PROGRESS / STEP_2_VERIFIED

Getting started, CHANGELOG, SECURITY, versioning/compatibility policy, release checklist; no claim that publication already exists.

Prepared plan: `docs/superpowers/plans/2026-10-02-release-facing-documentation-compatibility.md`, baseline `e7d61ef2f044990d12d2aa4cca44a62c57e9e290` with live-verified main Validate **18/18 SUCCESS**. Plan/tracking changes only. Acceptance separates local artifact consumption from registry publication, tested compatibility from broader manifest constraints, and fixture-only proof from production setup. Private reporting is currently disabled and remains a publication blocker. Canonical validation and publication guard pass. Next gate: **T-804 implementation-plan approval only**; implementation and T-805 have not started.

Plan approved on 2026-10-02 from user continuation at the approval gate. Approved plan revision `1bafe57b50c2870e3afea1aa3c0fc6a6cfce6141` has Validate **18/18 SUCCESS**. Approved scope is the plan's documentation paths, evidence limits, acceptance and verification only. PR #23 remains open and no automated external-review completion is claimed. Next gate: **T-804 implementation baseline and feature branch only**; consumer guides and T-805 have not started. No merge/publication/decision promotion is authorized.

Step 1 baseline/feature branch is READY: clean `543b60e4988e7ac8ed5a89c861e87affa79b5bc3`, live-verified Validate 37023733891 **18/18 SUCCESS**. Branch `docs/t-804-release-facing-documentation`, stacked on the still-open plan branch/PR #23. Docker canonical validation and publication guard pass. Tracking/plan edits only; no guide or implementation content. Next gate: **Step 2 consumer guides with existing artifact verification**. Plan merge/main integration require explicit authorization; T-805 remains not started.

Step 2 consumer guides verified on 2026-10-03: `docs/consumers/laravel.md` and `docs/consumers/browser-runtime.md`. Their exact shell blocks pass in Docker using clean source revision `a15567c42d8f50ba8d060dcfbf25d76e371f8e11`: local Composer ZIP identity/autoload/ActionBus smoke (PHP 8.4.26, Composer 2.10.3, Laravel 13.34.0) and npm tarball root import/typecheck/Vite/DriverRegistry/deep-import rejection (Node 22.23.3). Python 3.12.15 tooling suites pass 25/25 browser + 20/20 Laravel; browser 333 tests, typecheck/build, canonical validator, publication guard and relative links pass. No runtime/package/CI/contract change. Fixture-only security and real-browser evidence limits are explicit. T-804 remains IN_PROGRESS; next gate is **Step 3 Unreleased changelog and SECURITY**. Exact new-head CI remains pending commit/push; no merge, release or publication occurred.

### T-805 — Integrated release-readiness verification + external-review handoff — NOT_STARTED

One revision/version input, both candidate artifacts, full repository regression, clean-consumer evidence, hashes, external review, publication-go/no-go handoff.

Explicitly outside M8 first candidate set:

- `surfacerelay/laravel-mcp` publication;
- `@surfacerelay/openapi-importer` publication;
- new adapter/capability work;
- v0.2 canonical contract changes;
- D-026 promotion;
- npm/Packagist publication;
- tags/GitHub Releases;
- first public version selection.

## Current boundary

M8 design remains **APPROVED**. T-801, T-802, and T-803 are **DONE / REVIEWED / MERGED / MAIN REVALIDATED**. PR #21 merged as `5ae4562a0b1676858f2c001c29b47eb6a45ef44b`; post-merge main Validate [36985896210](https://github.com/kefyusuf/surfacerelay/actions/runs/36985896210) passed **18/18** jobs. External review is closed with 1 thread / 0 unresolved and the npm finding CodeRabbit-confirmed addressed. D-026 and D-069 through D-073 remain **PROPOSED**; T-804/T-805 remain **NOT STARTED**. No tag, release, registry publication, public version selection, or decision promotion occurred.

T-803 implementation, review, merge, and post-merge verification are complete. T-804 plan is approved and Step 1 baseline/feature branch is ready; next gate: **T-804 Step 2 consumer guides**. Do not start T-805 or merge the open plan/implementation PRs automatically.
### T-801 external-review amendment — workflow YAML parsing boundary — DESIGN_LOCKED / FIX_NOT_STARTED

External review of PR #19 identified one unresolved Major: the current handwritten workflow folding logic can miss valid YAML `run` scalar forms.

Locked boundary:

- parse workflow YAML before command scanning;
- replace the handwritten folded-scalar regex/folder rather than broadening it;
- use a bounded PyYAML 6.x development dependency with string-preserving, duplicate-key-rejecting loading;
- scan publication commands from parsed step `run` strings only;
- scan workflow credential identifiers from parsed string keys/values so comments are excluded;
- fail closed on YAML parse errors, duplicate keys, structurally invalid workflow sections, or non-string `run` values;
- preserve the existing non-workflow scanners and CI publication-authority restrictions;
- do not treat this amendment as a general shell parser or a new public SurfaceRelay contract.

Next gate: **focused RED tests for this finding only**. Parser implementation, thread resolution, merge, D-069..D-073 promotion, and T-802 remain out of scope until a later explicit continuation.
### T-801 external-review finding — RED_PROVEN / GREEN_NOT_STARTED

RED evidence is complete at `3702b9786fe3a171571acd3988021ba7083cf232`.

Validate #992 (`36250401675`) completed with **12 SUCCESS / 1 FAILURE**. Only `release-contract` failed. The T-801 suite ran 49 tests with six expected failures covering the accepted YAML parsing gap: multi-line plain scalar, `>2`, duplicate keys, malformed YAML, non-string `run`, and comment false positives. Existing `>`, `>-`, and `>+` cases stayed green.

No production scanner, dependency, CI setup, package, spec, conformance, decision, publication, or T-802 implementation changed in the RED gate.

Next gate: **GREEN parser implementation for this Major only**. The review thread remains unresolved until exact-head verification proves the fix.
### T-801 external-review finding — GREEN_VERIFIED / REVIEW_THREAD_OPEN

GREEN implementation is complete at `c50b9f12cd809107f4d95f553716d9a96df3f038`.

Validate #996 (`36266106312`) completed **13/13 SUCCESS**. The dedicated `release-contract` suite is **49/49 PASS**, the repository publication guard passes, and the normal contract/browser/OpenAPI/PHP/Laravel matrices are all green.

The fix is limited to:

- `.github/workflows/validate.yml` — install the bounded release-contract Python dependencies;
- `requirements-dev.txt` — add `PyYAML>=6.0.3,<7`;
- `scripts/check_release_guardrails.py` — strict parsed-workflow scanning, duplicate-key rejection, parsed credential scanning, no handwritten YAML folding;
- `scripts/tests/test_release_candidate_guardrails.py` — align the non-string `run` fixture with BaseLoader semantics.

The Major review thread remains unresolved intentionally. Next gate: **disposition the verified finding and resolve/re-check that thread only**. T-802 remains NOT STARTED.

### T-801 external-review closure — DONE / REVIEW_CLOSED

```text
PR:                              #19 — OPEN / mergeable / NOT MERGED
Final implementation-code head: c50b9f12cd809107f4d95f553716d9a96df3f038
Final reviewed pre-closure head: f1257054f6d828d5e25fa68cdc23bf334188192e
Reviewed-head Validate:          #998 / 36266212552 — 13/13 SUCCESS
Release-contract:                49/49 PASS
Publication guard:               PASS
Reviewer re-check:               Major YAML parsing finding confirmed addressed
Review threads:                  5 total / 0 unresolved
D-069..D-073:                   PROPOSED / unchanged
T-802..T-805:                   NOT STARTED
Merge/tag/release/publication:  NOT AUTHORIZED
```

Next gate: **T-801 merge decision only**. Completing review does not start T-802 automatically.

### T-801 post-merge closure — DONE / REVIEWED / MERGED / MAIN_REVALIDATED

```text
PR:                              #19 — MERGED
Merge commit:                    84f80858308d35ed532eec3928a7fdf3186ffb34
Post-merge Validate:             #1001 / 36273220054 — 13/13 SUCCESS
Release-contract:                SUCCESS
Review threads:                  5 total / 0 unresolved
D-069..D-073:                   PROPOSED / unchanged
T-802..T-805:                   NOT STARTED
Tag/release/publication:        NONE
```

Next gate: **T-802 implementation-plan preparation only**.


T-802 Step 9 is **WHOLE-TASK VERIFIED**. Next explicit gate: **T-802 Step 10 — forbidden-diff / source-mutation audit only**. Step 11 review handoff and T-803 must not start automatically.


T-802 Step 10 forbidden-diff/source-mutation audit is **VERIFIED** on `bfb6053258a8fe23c9e6a0bc0529fbdfdf85911a`: baseline comparison shows exactly 7 allowed net changed files, **0 forbidden** and **0 unexpected** paths. `packages/laravel/src/**`, `packages/laravel/database/**`, source `packages/laravel/composer.json`, `spec/**`, `conformance/**`, browser-runtime, Laravel-MCP, and OpenAPI-importer remain unchanged. Repository tags/releases are empty; changed executable surfaces contain no publication commands or registry credential wiring. Next explicit gate: **T-802 Step 11 — tracking / external-review handoff only**.


T-802 Step 11 is **DONE / REVIEW HANDOFF**. External-review material is prepared in `REVIEW_REQUEST.md`, PR #20 is open, and the review cycle completed with both inline threads resolved.

### T-802 external-review closure — DONE / REVIEW_CLOSED

```text
PR:                              #20 — OPEN / non-draft / mergeable / NOT MERGED
Final reviewed pre-closure head: 951ecd07dfe18063042bf0ad1d06aa6723f952e5
Reviewed-head Validate:          #1045 / 36487729081 — 17/17 SUCCESS
Review threads:                  2 total / 0 unresolved
Latest incremental finding:      CodeRabbit-confirmed addressed
Full reviewed branch scope:      8 bounded T-802 files
D-026 / D-069..D-073:            PROPOSED / unchanged
T-803..T-805:                    NOT STARTED
Merge/tag/release/publication:    NOT AUTHORIZED
```

Next explicit gate: **T-802 merge decision only**. Review closure does not start T-803, promote decisions, create tags/releases, publish packages, or select a public SemVer automatically.


### T-802 post-merge closure — DONE / REVIEWED / MERGED / MAIN_REVALIDATED

```text
PR:                              #20 — MERGED
Merge commit:                    f7d86c76ccc15dc21b63cf868ad75d559607f730
Post-merge Validate:             #1048 / 36541971021 — 17/17 SUCCESS
Review threads:                  2 total / 0 unresolved
D-026 / D-069..D-073:            PROPOSED / unchanged
T-803..T-805:                    NOT STARTED
Tag/release/publication:         NONE
```

Next gate: **T-803 implementation-plan preparation only**.


## T-803 npm launch finding — RED PROVEN / GREEN NOT STARTED

Five focused `BrowserNpmLaunchContractTest` methods now cover npm path resolution for pack/consumer commands, missing-npm fail-closed behavior for both paths, and a Windows npm.cmd fixture executed via Node's npm CLI with literal arguments and no shell. Arguments include spaces and shell metacharacters. These are process-launch tests; the mocked Windows layout does not claim Windows end-to-end support.

Local focused command: `python -B -m unittest scripts.tests.test_browser_release_candidate.BrowserNpmLaunchContractTest` — **5 tests / 5 expected assertion failures / 0 errors**. Existing production behavior launches bare npm and does not check availability first. No corrective implementation changed. Canonical validation, publication guard, and diff checks pass. The previously verified baseline has 19 browser tooling tests passing in Linux CI; the expected new CI result is 19 PASS / 5 expected FAIL in browser-release-consumer, with the other 17 Validate jobs green.

Docker CLI was not found on PATH or in standard Docker Desktop executable locations. No Docker run or installation is claimed. Linux CI is used for independent RED evidence; Docker remains preferred when an available engine/host is provided.

Changed files: focused Python tests plus STATUS, TASKS, and REVIEW_REQUEST only. No production/browser runtime, API, dependency, workflow, schema, conformance, or decision edits. The external-review finding remains open.

Next explicit gate: **GREEN npm launch implementation for this Minor only**, after the expected Linux CI failures are verified. Do not resolve the review thread, merge, or start T-804 automatically.


Linux RED evidence verified at `d4a19c57927e05c1847c47be8a0032771c13895f`: [Validate / 36975704645](https://github.com/kefyusuf/surfacerelay/actions/runs/36975704645) finished **17 SUCCESS / 1 expected FAILURE**. Only `browser-release-consumer` failed. Its job log proves **24 tests / 19 PASS / 5 expected assertion failures / 0 errors**; the isolated real-artifact consumer journey remains green. All five failures are the new npm-launch contracts. RED is independently proven on Linux; next gate remains GREEN implementation only.

## T-803 npm launch finding — GREEN VERIFIED LOCALLY / CI PENDING

The bounded fix resolves npm from the host PATH. POSIX launches the resolved executable; Windows .cmd/.bat shims launch the adjacent npm-cli.js through resolved Node, without a shell. Missing npm, Node, or CLI fails before launching a process. Pack and all four consumer npm commands share this launch path. The integration fixture uses the same launcher; the browser emission test uses the npm-provided CLI path with process.execPath.

Verification on 2026-10-02: Docker Node 22.23.3 / Python 3.12.15, read-only repository mount and temporary filesystem: 25/25 Python artifact/consumer tests PASS, 333/333 browser tests PASS, typecheck/build PASS, canonical validation/publication guard PASS. Windows: 25/25 Python tests including real pack/install/isolated consumer PASS with project-local npm cache and approved process access; 333/333 browser tests and typecheck PASS. The previous Windows bare-npm launch limitation is addressed for these tested paths. This is tooling evidence, not a new supported browser-runtime platform claim.

The five RED tests are GREEN. One additional negative method covers missing Windows Node/CLI prerequisites. Existing archive/consumer isolation, argv boundaries and consumer credential stripping are preserved. No runtime source/API/metadata/dependency/workflow/spec/conformance/decision change. The Dockerfile remains ignored under .tmp; no permanent container infrastructure was added. Docker is available at the user-local Docker Desktop installation, correcting the earlier narrow lookup result.

Changed files: scripts/browser_release_candidate.py, scripts/tests/test_browser_release_candidate.py, packages/browser-runtime/tests/distribution-contract.test.ts, STATUS.md, TASKS.md, REVIEW_REQUEST.md. Exact-head CI remains to be verified after commit. The review thread remains open. Next gate after CI verification: external-review re-check/disposition only; no automatic merge or T-804.

GREEN exact-head evidence: `d70da239434beee09635696c8a1b1489afd0a00a`, [Validate / 36977031979](https://github.com/kefyusuf/surfacerelay/actions/runs/36977031979) — **18/18 SUCCESS**. The browser-release-consumer log proves 25 tests PASS plus clean-consumer smoke and deep-import rejection PASS. Docker and Windows checks above are also green. The npm finding is fixed and verified, but its review thread remains open pending external re-check. Next gate: external-review re-check/disposition only; no merge or T-804.

## T-803 external-review closure — DONE / REVIEW CLOSED

Date: 2026-10-02. PR #21 remains OPEN / non-draft / NOT MERGED. Reviewed pre-closure head: `fa0bafc197dfc5ede0b96bc9fbbabeafe0839fd7`. [Validate / 36977314801](https://github.com/kefyusuf/surfacerelay/actions/runs/36977314801) passed **18/18** jobs at that exact revision.

[CodeRabbit re-check](https://github.com/kefyusuf/surfacerelay/pull/21#discussion_r4164121881) confirms the original Windows npm launch finding is addressed and no issue remains in its covered launch paths. The bot inspected the changed paths and confirmed CI; it did not rerun tests or independently reproduce Windows execution. Docker/Windows execution evidence is recorded above. The single review thread is resolved: **1 total / 0 unresolved**, verified via GitHub GraphQL after the reply.

This closure changes STATUS, TASKS, and REVIEW_REQUEST only. No implementation changes. D-026 / D-069..D-073 remain PROPOSED; T-804/T-805 remain NOT STARTED. No merge, tag, release, publication, or public version selection occurred.

Next explicit gate: **T-803 merge decision only**. External-review closure does not authorize merge or begin T-804 automatically.

## T-803 post-merge closure — DONE / REVIEWED / MERGED / MAIN REVALIDATED

The user explicitly authorized merge on 2026-10-02. [PR #21](https://github.com/kefyusuf/surfacerelay/pull/21) is MERGED as `5ae4562a0b1676858f2c001c29b47eb6a45ef44b`. Post-merge main [Validate / 36985896210](https://github.com/kefyusuf/surfacerelay/actions/runs/36985896210) passed **18/18** jobs at that exact merge commit. Local main canonical validation and publication guard pass; checkout was clean before this tracking update.

Review is closed: 1 thread / 0 unresolved. This post-merge tracking update changes STATUS, TASKS, and REVIEW_REQUEST only. No implementation changes. D-026 / D-069..D-073 remain PROPOSED. T-804/T-805 remain NOT STARTED; no tag/release/publication/version selection occurred.

Next gate: **T-804 implementation-plan preparation only**, on explicit continuation; do not start its implementation automatically.
