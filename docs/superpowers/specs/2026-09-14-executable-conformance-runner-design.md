# T-701 — Executable Conformance Runner Design

**Status:** APPROVED DESIGN / IMPLEMENTATION NOT STARTED  
**Task:** T-701  
**Milestone:** M7 — Conformance / Ecosystem Bridges  
**Branch:** `feat/executable-conformance-runner`  
**Approved:** 2026-09-14  

## 1. Purpose

T-701 closes the gap between SurfaceRelay's canonical conformance scenario registry and executable runtime evidence.

Today:

- `scripts/validate.py` executes schema fixtures and validates conformance-registry integrity;
- `spec/0.1/fixtures/conformance-scenarios.json` records runtime semantics that are still largely `documented`;
- T-604 proves a shared subset of browser `BindingDriver` behavior against production `LivewireBrowserDriver` and `HtmxBrowserDriver`, but that suite is package-local and does not execute canonical scenario IDs from the registry.

T-701 introduces a thin, repo-local runner that selects canonical runtime scenarios, executes target-specific harnesses through a language-neutral process protocol, evaluates raw observations centrally, and reports `PASS`, `FAIL`, `ERROR`, or `NOT_APPLICABLE`.

T-701 is deliberately **not** a public SDK, standalone specification, plugin system, or complete cross-runtime certification product.

## 2. Architectural constraints inherited from prior decisions

The design preserves the following established boundaries:

- `ActionDefinition` remains protocol-neutral and contains no runtime-target fields.
- `RuntimeBinding` remains separate from `ActionDefinition`.
- Binding-driver identifiers remain extensible and fail closed when unsupported.
- Exact target identity is authoritative; stale bindings must not rediscover or silently retarget to replacement targets.
- T-604's accepted common `BindingDriver` overlap remains behavioral rather than structural.
- Framework-specific target shapes, lifecycles, runtime APIs, local errors, cancellation mechanisms, and successful return values remain driver-owned.
- D-009/D-020 establish the two-materially-different-bindings threshold, but do not authorize standalone public-spec extraction.
- D-026 remains `PROPOSED`; T-701 must not silently turn provisional recommended binding failure codes into a global normative enum.

## 3. Scope

### 3.1 Executable conformance profile

T-701 v1 claims exactly one production conformance profile:

```text
runtime-binding/driver
```

A profile is an atomic conformance claim boundary. It names the production behavior under test, not a framework.

Reference targets for v1:

```text
browser/livewire
browser/htmx
```

Both targets exercise their production `BindingDriver.execute()` implementation.

### 3.2 Capability model

Capabilities express conditional applicability inside a profile. They do not select the scenario set directly and they are not evidence of correctness.

Initial capability:

```text
lifecycle.component
```

`browser/livewire` declares `lifecycle.component`; `browser/htmx` does not.

A capability declaration creates additional test obligations. The adapter cannot use a capability declaration to skip a mandatory scenario.

### 3.3 Out of scope in v1

The following are intentionally not claimed by T-701 v1:

- `runtime-binding/binding-lookup`;
- `runtime-binding/driver-registry`;
- `runtime-binding/action-availability`;
- Laravel/PHP conformance targets;
- trust/confirmation/idempotency conformance;
- output-policy or projection conformance;
- real-browser orchestration;
- public conformance SDK/package;
- standalone spec extraction;
- remote targets;
- generic plugin discovery;
- generic assertion DSL;
- parallel/sharded execution;
- retries/watch mode;
- global exact binding-error-code standardization.

`BIND-ID-UNKNOWN`, `BIND-DRIVER-UNKNOWN`, and `BIND-ACTION-VERSION-UNAVAILABLE` therefore remain documented runtime scenarios during T-701 v1.

## 4. Why T-701 v1 excludes `driver-registry`

An earlier design candidate included a `browser/driver-registry` target. It is excluded from v1 because the accepted raw observation vocabulary can prove `BindingDriver.execute()` behavior directly, but cannot prove "the exact registered implementation object was resolved" without adding a semantic/self-reported boolean such as `resolvedExactImplementation=true`.

Adding such a field would weaken the rule that harnesses report raw observations rather than interpretations. The v1 design therefore keeps the protocol smaller and leaves driver-registry conformance for a later gate with a purpose-built observation model.

## 5. Canonical scenario ownership

### 5.1 Semantic source of truth

Canonical semantic scenario definitions remain in:

```text
spec/0.1/fixtures/conformance-scenarios.json
```

T-701 may extend runtime scenario entries with generic conformance metadata such as:

- `profile`;
- `requiresCapabilities`;
- structured `expectation`;
- `status=executable` when the executable conditions below are satisfied.

This file contains portable semantic truth. It must not contain repository process wiring.

### 5.2 Execution wiring

Target-specific execution wiring lives outside `spec/0.1`, under repo-local infrastructure such as:

```text
conformance/targets/browser-livewire.json
conformance/targets/browser-htmx.json
```

Target manifests may contain:

- target ID;
- protocol version;
- profile claims;
- capability claims;
- executable argv.

They do not define scenario semantics.

This preserves the separation:

```text
portable semantic contract != repository execution wiring
```

## 6. Runtime scenario status

The registry continues to use `kind=schema|runtime` and `status=executable|documented`.

Execution engine is determined by `kind`:

```text
kind=schema, status=executable
→ scripts/validate.py / fixture manifest

kind=runtime, status=executable
→ T-701 runner
```

A runtime scenario may be promoted to `executable` only when:

1. it has a structured expectation understood by the runner;
2. it has a profile;
3. any capability requirements are explicit;
4. at least one real production-boundary target can execute it through the T-701 process protocol.

`executable` does not mean every target is applicable. Capability-gated scenarios may be `NOT_APPLICABLE` for individual targets.

## 7. Positive-control invariant

A failure-only profile is unsound because an implementation that rejects everything could appear conformant.

Therefore:

> Every claimed profile must contain at least one mandatory positive executable scenario.

T-701 v1 adds the canonical positive scenario:

```text
BIND-EXACT-TARGET-EXECUTES
```

Normative expectation:

```json
{
  "termination": "returned",
  "frameworkDispatchCount": 1,
  "replacementDispatchCount": 0
}
```

This proves that a valid exact binding can execute exactly once, and prevents an always-rejecting implementation from satisfying the profile.

## 8. T-701 v1 executable matrix

The initial canonical matrix is:

| Scenario | Profile | Capability requirement | Livewire | HTMX |
|---|---|---|---:|---:|
| `BIND-EXACT-TARGET-EXECUTES` | `runtime-binding/driver` | none | executable | executable |
| `BIND-EXPIRED-NOT-EXECUTABLE` | `runtime-binding/driver` | none | executable | executable |
| `BIND-COMPONENT-STALE` | `runtime-binding/driver` | `lifecycle.component` | executable | N/A |
| `BIND-NO-SILENT-RETARGET` | `runtime-binding/driver` | none | executable | executable |

Expected full-run result:

```text
7 PASS
0 FAIL
0 ERROR
1 NOT_APPLICABLE
```

T-604 contains additional executable browser-driver cases (malformed target, malformed expiry, unknown/missing input, pre-aborted signal, and related exact local error assertions). They remain T-604 regression coverage and are not automatically promoted into new canonical T-701 scenario IDs.

## 9. Profile/capability selection rules

A target declares profiles and capabilities. It does not enumerate or choose scenarios.

The runner computes the exact execution set from:

```text
canonical scenario registry
+
target profile claims
+
target capabilities
=
exact applicable execution set
```

Rules:

1. A mandatory profile scenario cannot be skipped by the target.
2. Capability-gated scenarios are selected only when the target declares every required capability.
3. `NOT_APPLICABLE` is produced by the runner before process execution; the harness cannot return it.
4. If the runner selects a mandatory scenario but the harness cannot execute it, the result is `ERROR`, not `NOT_APPLICABLE`.
5. A target cannot provide an ad-hoc scenario allowlist that overrides canonical selection.

## 10. Runner/harness process protocol

### 10.1 Process model

T-701 v1 uses one fresh subprocess per target/scenario pair:

```text
one target × one scenario = one subprocess
```

This prevents test-order dependencies and scenario state leakage, gives exact crash attribution, and keeps cleanup simple.

Persistent workers/NDJSON sessions are not part of v1.

### 10.2 Request

The runner writes exactly one request JSON document to stdin. Example:

```json
{
  "protocolVersion": "0.1",
  "requestId": "run-42",
  "scenarioId": "BIND-NO-SILENT-RETARGET",
  "profile": "runtime-binding/driver",
  "now": "2026-09-14T00:00:00.000Z"
}
```

The request does **not** include expected results. A harness must not receive the answer to the scenario it is executing.

### 10.3 Observation

The harness writes exactly one observation JSON document to stdout.

Initial raw vocabulary:

```text
termination:
  returned | threw

errorCode:
  optional string

frameworkDispatchCount:
  integer

replacementDispatchCount:
  optional integer
```

Harnesses must not report interpretive fields such as:

```text
passed
conformant
failClosed
retargetPrevented
expectedErrorObserved
```

The harness reports what happened. The runner decides what it means.

### 10.4 Diagnostics

`stderr` is diagnostic-only and may contain human-readable framework/bootstrap information.

`stdout` is protocol-only. Any non-protocol stdout content is an infrastructure `ERROR`.

### 10.5 Harness process exit

Harness exit code semantics:

```text
0        valid protocol observation produced
non-zero harness/protocol infrastructure failure
```

An expected production exception is represented as:

```text
process exit = 0
observation.termination = threw
```

It is not represented by a failing child-process exit code.

## 11. Runner verdict authority

Only the runner computes conformance status.

Results:

- `PASS` — a valid observation matches the canonical expectation;
- `FAIL` — the harness ran successfully but the production observation does not match the canonical expectation;
- `ERROR` — the harness/protocol could not be trusted or completed;
- `NOT_APPLICABLE` — the runner determined before spawning that the target lacks a scenario-required capability.

Examples of `ERROR`:

- harness executable missing;
- child process non-zero exit;
- timeout;
- empty stdout;
- invalid JSON;
- more than one stdout JSON document / extra protocol-breaking stdout;
- protocol-version mismatch;
- request-ID mismatch;
- scenario-ID mismatch;
- missing required observation field;
- invalid `termination` value;
- child-process crash.

## 12. Structured expectations

The runner uses a deliberately small expectation vocabulary that maps directly to the observation vocabulary.

Examples:

Expired binding:

```json
{
  "termination": "threw",
  "frameworkDispatchCount": 0
}
```

No silent retarget:

```json
{
  "termination": "threw",
  "frameworkDispatchCount": 0,
  "replacementDispatchCount": 0
}
```

Positive exact execution:

```json
{
  "termination": "returned",
  "frameworkDispatchCount": 1,
  "replacementDispatchCount": 0
}
```

T-701 v1 does not add a generic assertion language (`jsonPath`, arbitrary regex, scripting expressions, generic predicates, etc.).

## 13. Error-code handling and D-026

`recommendedCode` remains advisory metadata in T-701 v1.

The runner may report:

```text
recommendedCode = binding_expired
observedCode    = binding_expired
codeMatch       = true
```

or a mismatch, but a mismatch does not automatically fail the scenario solely because of D-026's provisional vocabulary.

This avoids silently promoting D-026 from `PROPOSED` to a global normative exact-code contract.

Existing T-604 browser-driver tests may continue to assert accepted driver-specific exact error behavior independently.

## 14. Deterministic time

Runtime expiry scenarios must not depend on wall-clock time, local timezone, or CI scheduling.

The runner supplies a deterministic `now` value in the request. Harnesses adapt that value into the production target's existing clock injection boundary.

T-604's deterministic conformance-clock approach is reused conceptually; no global clock abstraction is added to production solely for T-701.

## 15. Runner implementation boundary

### 15.1 Language

The repo-local orchestrator is Python 3 stdlib:

```text
scripts/run_conformance.py
```

Python is chosen because root contract tooling already uses Python and because an orchestrator outside the browser/PHP runtimes avoids making either reference implementation the semantic authority.

The runner must not import:

- `LivewireBrowserDriver`;
- `HtmxBrowserDriver`;
- Laravel `ActionBus`;
- framework-specific runtime packages.

It only knows JSON, target manifests, subprocesses, timeouts, selection, comparison, aggregation, and reporting.

### 15.2 Internal split

Recommended structure:

```text
scripts/
├── conformance_model.py
├── run_conformance.py
└── tests/
    └── test_conformance_runner.py
```

`conformance_model.py` owns pure deterministic logic:

- registry validation helpers;
- target validation helpers;
- profile/capability selection;
- expectation comparison;
- result aggregation.

`run_conformance.py` owns side effects:

- CLI parsing;
- subprocess execution;
- timeout handling;
- stdin/stdout protocol;
- text/JSON rendering;
- process exit code.

No new Python dependency is required; stdlib `unittest` is sufficient for the runner tests.

## 16. Target manifest command safety

Target commands are argv arrays, not shell expressions.

Example:

```json
{
  "command": [
    "node",
    ".tmp/conformance/livewire-harness.js"
  ]
}
```

Runner execution uses `shell=False` semantics.

Target manifests do not contain pipeline expressions, `bash -c`, `cmd.exe /c`, PowerShell expressions, or shell interpolation.

## 17. Runner is not a build system

The runner assumes target harnesses are execution-ready.

It does not run:

- `npm install`;
- `composer install`;
- package-manager resolution;
- `tsc` compilation;
- bundling;
- framework build/bootstrap preparation beyond executing the harness command.

CI/developer workflow prepares the harness first and then invokes the runner.

## 18. Browser harness architecture

The browser package's normal TypeScript config remains `noEmit`. T-701 uses a dedicated compile config such as:

```text
packages/browser-runtime/tsconfig.conformance.json
```

Compiled output goes to the already-ignored repo-local area:

```text
.tmp/conformance/
```

Proposed harness layout:

```text
packages/browser-runtime/conformance/
├── protocol.ts
├── livewire-harness.ts
├── htmx-harness.ts
└── support/
    ├── livewire-target.ts
    └── htmx-target.ts
```

Harness code is Vitest-free. Counters are ordinary state, not framework mocks.

## 19. T-604 test-infrastructure reuse

T-701 must not create an independent second behavioral model of Livewire and HTMX.

Where practical, reusable Vitest-free target setup is extracted so both T-604 and T-701 use the same controlled runtime model:

```text
          reusable Vitest-free target setup
                     │
            ┌────────┴────────┐
            │                 │
      T-604 Vitest        T-701 process
       adapters             harnesses
```

The T-604 adapters remain thin test wrappers. Production files under `packages/browser-runtime/src/**` do not need to change for T-701 v1.

## 20. Registry and target structural validation

`scripts/validate.py` remains the structural validator. It does not execute runtime harnesses.

Additional integrity requirements for T-701 include:

- `kind=runtime + status=executable` requires `profile` and structured `expectation`;
- `requiresCapabilities` may reference only known capability vocabulary;
- target manifests may claim only known profiles/capabilities;
- every profile actually claimed by a target must have at least one mandatory positive executable scenario;
- duplicate target IDs/profile inconsistencies fail validation.

Responsibility remains:

```text
scripts/validate.py
→ schemas / fixtures / registry / target-manifest structure

scripts/run_conformance.py
→ runtime selection / execution / observation / verdict
```

## 21. CLI and reporting

Minimal v1 CLI:

```bash
python scripts/run_conformance.py
python scripts/run_conformance.py --target browser/livewire
python scripts/run_conformance.py --scenario BIND-NO-SILENT-RETARGET
python scripts/run_conformance.py --json
```

Target and scenario filters may be combined.

Default output is deterministic human-readable text. Optional `--json` emits machine-readable results; T-701 does not define a separately versioned artifact/report standard.

Execution order is deterministic:

```text
targetId ascending
then scenarioId ascending
```

Filesystem enumeration or JSON insertion order is not execution authority.

## 22. Timeout and runner exit codes

Each executed target/scenario subprocess has a fixed 10-second timeout in v1.

Timeout is `ERROR`.

The fixed timeout is intentionally not target-configurable in v1; no adapter-controlled escape hatch is introduced before evidence shows it is needed.

Runner process exit codes:

```text
0 → all applicable scenarios PASS
1 → at least one FAIL and no ERROR
2 → at least one ERROR
```

`ERROR` has precedence over `FAIL`. `NOT_APPLICABLE` does not cause a non-zero exit.

## 23. Verification and TDD strategy

Implementation must proceed test-first in bounded slices.

### Slice A — model selection

RED first for:

- profile/capability selection;
- mandatory scenario selection;
- capability-gated applicability;
- positive-control invariant.

GREEN result: deterministic scenario selection independent of target preference.

### Slice B — expectation evaluator

RED first for returned/threw and dispatch-count mismatches.

GREEN result: only the runner decides `PASS`/`FAIL`.

### Slice C — advisory error code

RED first for absent/mismatched `recommendedCode` observations.

GREEN result: advisory mismatch is reported without silently making D-026 normative.

### Slice D — protocol failures

RED first for:

- invalid JSON;
- extra stdout;
- wrong protocol version;
- wrong request ID;
- wrong scenario ID;
- missing observation field;
- non-zero child exit.

GREEN result: all classify as `ERROR`.

### Slice E — timeout

RED first with a hanging fake harness.

GREEN result: fixed 10-second timeout becomes `ERROR`.

### Slice F — aggregation

RED first for mixed PASS/FAIL/ERROR sets.

GREEN result: runner exits `0/1/2` with ERROR precedence.

### Slice G — applicability

RED first proving that HTMX without `lifecycle.component` does not spawn `BIND-COMPONENT-STALE`.

GREEN result: `NOT_APPLICABLE` is runner-owned and no child process runs.

### Slice H — structural validation

RED first for executable runtime scenario missing profile/expectation and for invalid target profile/capability references.

GREEN result: `scripts/validate.py` rejects malformed conformance metadata.

### Slice I — Livewire harness

Executable observations for:

- exact target executes once;
- expiry rejects with zero dispatch;
- missing/stale component rejects with zero dispatch;
- equivalent replacement receives zero dispatch.

### Slice J — HTMX harness

Executable observations for:

- exact target executes once;
- expiry rejects with zero dispatch;
- equivalent replacement receives zero dispatch.

### Slice K — full canonical run

Canonical registry + both target manifests produce:

```text
7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR
```

### Slice L — CLI/reporting

Verify `--target`, `--scenario`, and `--json` with deterministic ordering.

## 24. Security-negative evidence

Runtime binding identity/lifecycle is security-sensitive. T-701 therefore preserves executable negative evidence for at least:

- expired binding → zero framework dispatch;
- stale exact component → zero framework dispatch;
- equivalent replacement target → zero original dispatch and zero replacement dispatch.

No-retarget proof is not satisfied merely by observing an error code. Dispatch counters are part of the normative expectation.

## 25. CI integration

T-701 v1 adds no new GitHub Actions job because all v1 reference targets are browser-runtime targets.

The existing `browser` job becomes conceptually:

```text
npm ci
→ npm run typecheck
→ npm test
→ compile conformance harnesses
→ setup Python 3.12
→ python scripts/run_conformance.py
```

The overall workflow remains seven jobs.

When the first non-browser conformance target is later added, a dedicated conformance job may be reconsidered in that task rather than pre-created now.

T-603 real Chromium proof remains separate regression evidence; T-701 does not absorb Playwright/browser-fixture orchestration.

## 26. Completion evidence required before T-701 may be DONE

At minimum:

```text
Python runner unit tests          PASS
python scripts/validate.py        PASS
browser TypeScript typecheck      PASS
full browser Vitest suite         PASS
conformance harness compilation   PASS
T-701 runtime matrix              7 PASS / 1 N/A
full validate workflow            7/7 green
full diff review                  PASS
external review                   completed
```

Decision promotion is separate from merely obtaining green tests. Proposed architecture decisions are evaluated after executable evidence and external review.

## 27. Proposed architecture decisions

### D-059 — Conformance authority — PROPOSED

Conformance claims use `profile + capability`. Canonical scenario selection and PASS/FAIL authority belong to the runner. Harnesses emit raw observations only and cannot choose, skip, or self-pass mandatory scenarios.

### D-060 — Language-neutral process protocol — PROPOSED

T-701 uses a repo-local one-scenario/one-subprocess JSON protocol: request on stdin, exactly one observation JSON on stdout, diagnostics on stderr, fixed 10-second timeout. A Python stdlib orchestrator imports no runtime implementation.

### D-061 — Semantic/execution separation — PROPOSED

Canonical runtime scenario semantics remain in `spec/0.1/fixtures/conformance-scenarios.json`; target command/profile/capability wiring remains outside the spec. An executable claimed profile requires at least one mandatory positive scenario. T-701 v1 claims only the shared browser `runtime-binding/driver` profile.

These decisions remain `PROPOSED` until implementation evidence and external review justify promotion. D-026 remains `PROPOSED` independently.

## 28. Explicit non-goals at closure

Closing T-701 does not imply any of the following:

- public SurfaceRelay conformance certification;
- public SDK or package release;
- standalone standards repository;
- Laravel/PHP conformance coverage;
- binding lookup or driver-registry conformance;
- complete Trust/Output/Projection conformance;
- generic adapter plugin API;
- real browser conformance execution;
- M7 completion;
- automatic start of T-702, T-703, or T-704.

## 29. Approval and next gate

This design was approved in the T-701 design conversation on 2026-09-14.

After this document is written, self-reviewed, and committed, the next gate is **user review of the written spec**. No implementation plan or implementation work begins until the user explicitly approves the committed design artifact. After that approval, the only next design-process step is the implementation-planning gate.
