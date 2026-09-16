# T-701 Executable Conformance Runner Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a repo-local, language-neutral executable conformance runner that selects canonical `runtime-binding/driver` scenarios, executes fresh Livewire/HTMX harness processes, evaluates raw observations centrally, and proves the v1 matrix as `7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR`.

**Architecture:** Canonical runtime scenario semantics stay in `spec/0.1/fixtures/conformance-scenarios.json`; repo-local target manifests under `conformance/targets/` declare profile/capability claims and argv only. A Python stdlib runner owns selection and verdicts, while Vitest-free TypeScript harness support invokes the production browser drivers and returns raw observations over one-request/one-response JSON subprocesses.

**Tech Stack:** Python 3.12 stdlib (`dataclasses`, `json`, `subprocess`, `unittest`), TypeScript 5.9, Node 22, Vitest 3.2, GitHub Actions, existing SurfaceRelay browser-runtime production drivers.

**Spec:** `docs/superpowers/specs/2026-09-14-executable-conformance-runner-design.md`

## Global Constraints

- T-701 v1 claims exactly one profile: `runtime-binding/driver`.
- Reference targets are exactly `browser/livewire` and `browser/htmx`.
- Initial capability vocabulary is exactly `lifecycle.component`; Livewire declares it and HTMX does not.
- Canonical scenario selection and `PASS`/`FAIL` authority belong only to the Python runner.
- Harnesses emit raw observations only; they do not emit `passed`, `conformant`, `failClosed`, `retargetPrevented`, or `NOT_APPLICABLE`.
- One target/scenario pair executes in one fresh subprocess.
- Request is one JSON document on stdin; response is exactly one JSON protocol document on stdout containing the raw observation; stderr is diagnostics only.
- `targetId` may be echoed in the process envelope only as routing/integrity metadata. It is not scenario semantics, target authority, or a normalized runtime target.
- Production timeout is fixed at 10 seconds per subprocess.
- Runner exit codes are `0` for all-applicable PASS, `1` for at least one FAIL with no ERROR, and `2` for at least one ERROR.
- `recommendedCode` is advisory; D-026 remains `PROPOSED` and code mismatch alone does not fail a scenario.
- `scripts/validate.py` validates structure only; it must not execute runtime harnesses.
- No public SDK/package, standalone spec extraction, Laravel/PHP target, driver-registry profile, binding-lookup profile, remote target, plugin discovery, generic assertion DSL, parallelism, retry/watch mode, or real-browser orchestration is added.
- No production files under `packages/browser-runtime/src/**` should require semantic changes. If implementation proves otherwise, stop and reopen the design gate instead of broadening scope silently.
- No new Python dependency or npm dependency is required. Node stdio access in TypeScript must use a minimal locally typed `globalThis.process` structural adapter rather than adding `@types/node` only for the harness.
- D-059, D-060, and D-061 remain `PROPOSED` throughout implementation and review; promotion is a later explicit decision gate.
- T-702, T-703, and T-704 do not start automatically.

---

## File Structure

### New root conformance/tooling files

- `scripts/conformance_model.py` — pure registry/target validation, selection, expectation evaluation, and status aggregation.
- `scripts/run_conformance.py` — CLI, target discovery, subprocess protocol, timeout handling, rendering, and process exit code.
- `scripts/tests/test_conformance_model.py` — pure model/evaluator tests.
- `scripts/tests/test_run_conformance.py` — subprocess protocol, timeout, filtering, and rendering tests.
- `scripts/tests/fixtures/fake_conformance_harness.py` — deterministic child process used only by Python runner tests.
- `conformance/README.md` — repo-local process protocol and target-manifest documentation; explicitly non-public/non-standard.
- `conformance/targets/browser-livewire.json` — Livewire target profile/capability/argv declaration.
- `conformance/targets/browser-htmx.json` — HTMX target profile/capability/argv declaration.

### New browser conformance files

- `packages/browser-runtime/conformance/protocol.ts` — request/response/observation TypeScript types and validation helpers used by process harnesses.
- `packages/browser-runtime/conformance/node-stdio.ts` — minimal locally typed Node stdin/stdout adapter without `@types/node`.
- `packages/browser-runtime/conformance/run-binding-driver-scenario.ts` — common scenario setup/execution-to-observation logic for the four T-701 scenarios.
- `packages/browser-runtime/conformance/support/binding-driver-target.ts` — Vitest-free shared target contract and deterministic clock constant.
- `packages/browser-runtime/conformance/support/livewire-target.ts` — controlled Livewire runtime target builder around production `LivewireBrowserDriver`.
- `packages/browser-runtime/conformance/support/htmx-target.ts` — controlled HTMX runtime target builder around production `HtmxBrowserDriver`.
- `packages/browser-runtime/conformance/livewire-harness.ts` — Livewire process entrypoint.
- `packages/browser-runtime/conformance/htmx-harness.ts` — HTMX process entrypoint.
- `packages/browser-runtime/tsconfig.conformance.json` — emits temporary JS under `.tmp/conformance/` without changing normal `tsconfig.json`.
- `packages/browser-runtime/tests/binding-driver-conformance-observation.test.ts` — direct RED/GREEN coverage proving the shared scenario executor emits raw observations rather than verdicts.

### Existing files modified during implementation

- `packages/browser-runtime/tests/support/binding-driver-conformance-suite.ts` — import shared target contract/clock instead of owning duplicated definitions.
- `packages/browser-runtime/tests/support/livewire-conformance-adapter.ts` — become a thin Vitest wrapper around `createLivewireConformanceTarget()`.
- `packages/browser-runtime/tests/support/htmx-conformance-adapter.ts` — become a thin Vitest wrapper around `createHtmxConformanceTarget()`.
- `packages/browser-runtime/package.json` — add only the `conformance:build` script; no dependency addition.
- `spec/0.1/fixtures/conformance-scenarios.json` — add the positive control and promote the three approved shared runtime scenarios with structured T-701 metadata.
- `scripts/validate.py` — delegate conformance registry/target structural integrity checks to the pure model; continue schema/fixture validation unchanged.
- `CONFORMANCE.md` — describe the implemented T-701 v1 runtime profile and retain clear distinction from T-604 and T-603 evidence.
- `.github/workflows/validate.yml` — extend the existing `browser` job with Python setup, harness compile, runner unit tests, and canonical conformance execution; do not add an eighth job.
- `TASKS.md`, `STATUS.md`, `REVIEW_REQUEST.md` — implementation/review evidence only after verification.

---

### Task 1: Pure Conformance Model — Selection, Validation, Evaluation, Aggregation

**Files:**
- Create: `scripts/conformance_model.py`
- Create: `scripts/tests/test_conformance_model.py`

**Interfaces:**
- Produces: `SelectedCase`, `validate_conformance_config(registry, targets) -> list[str]`, `select_cases(registry, target) -> list[SelectedCase]`, `validate_observation(observation) -> list[str]`, `evaluate_observation(expectation, observation) -> list[str]`, `aggregate_exit_code(statuses) -> int`.
- Consumes later: Task 2 runner and Task 6 `scripts/validate.py` integration.

- [ ] **Step 1: Write RED tests for profile/capability selection and positive-control integrity**

Create `scripts/tests/test_conformance_model.py` with in-memory fixtures that prove:

```python
import unittest

from scripts.conformance_model import (
    aggregate_exit_code,
    evaluate_observation,
    select_cases,
    validate_conformance_config,
)


class ConformanceModelTest(unittest.TestCase):
    def setUp(self):
        self.registry = {
            "scenarios": [
                {
                    "id": "BIND-EXACT-TARGET-EXECUTES",
                    "kind": "runtime",
                    "status": "executable",
                    "profile": "runtime-binding/driver",
                    "requiresCapabilities": [],
                    "expectation": {
                        "termination": "returned",
                        "frameworkDispatchCount": 1,
                        "replacementDispatchCount": 0,
                    },
                },
                {
                    "id": "BIND-COMPONENT-STALE",
                    "kind": "runtime",
                    "status": "executable",
                    "profile": "runtime-binding/driver",
                    "requiresCapabilities": ["lifecycle.component"],
                    "expectation": {
                        "termination": "threw",
                        "frameworkDispatchCount": 0,
                    },
                },
            ]
        }
        self.livewire = {
            "targetId": "browser/livewire",
            "protocolVersion": "0.1",
            "profiles": ["runtime-binding/driver"],
            "capabilities": ["lifecycle.component"],
            "command": ["node", "livewire.js"],
        }
        self.htmx = {
            **self.livewire,
            "targetId": "browser/htmx",
            "capabilities": [],
            "command": ["node", "htmx.js"],
        }

    def test_capability_gated_case_is_applicable_only_to_declaring_target(self):
        livewire = {case.scenario_id: case for case in select_cases(self.registry, self.livewire)}
        htmx = {case.scenario_id: case for case in select_cases(self.registry, self.htmx)}
        self.assertTrue(livewire["BIND-COMPONENT-STALE"].applicable)
        self.assertFalse(htmx["BIND-COMPONENT-STALE"].applicable)

    def test_claimed_profile_requires_mandatory_positive_control(self):
        registry = {
            "scenarios": [
                {
                    "id": "NEGATIVE-ONLY",
                    "kind": "runtime",
                    "status": "executable",
                    "profile": "runtime-binding/driver",
                    "requiresCapabilities": [],
                    "expectation": {"termination": "threw", "frameworkDispatchCount": 0},
                }
            ]
        }
        errors = validate_conformance_config(registry, [self.livewire])
        self.assertTrue(any("positive" in error.lower() for error in errors))
```

Also cover duplicate scenario IDs, unknown profile claims, unknown capabilities, malformed argv, executable runtime scenario missing `profile`, missing `expectation`, and invalid expectation keys.

- [ ] **Step 2: Run the model tests and verify RED**

```bash
python -m unittest scripts.tests.test_conformance_model -v
```

Expected: import failure because `scripts/conformance_model.py` does not exist.

- [ ] **Step 3: Implement the minimal pure model**

Create `scripts/conformance_model.py` around these exact v1 primitives:

```python
from dataclasses import dataclass
from typing import Any, Mapping

PROTOCOL_VERSION = "0.1"
PASS = "PASS"
FAIL = "FAIL"
ERROR = "ERROR"
NOT_APPLICABLE = "NOT_APPLICABLE"
ALLOWED_EXPECTATION_KEYS = {
    "termination",
    "frameworkDispatchCount",
    "replacementDispatchCount",
}
ALLOWED_TERMINATIONS = {"returned", "threw"}


@dataclass(frozen=True)
class SelectedCase:
    target_id: str
    scenario_id: str
    profile: str
    applicable: bool
    expectation: Mapping[str, Any]
    recommended_code: str | None


def _is_positive_control(scenario: Mapping[str, Any]) -> bool:
    expectation = scenario.get("expectation", {})
    return (
        scenario.get("requiresCapabilities", []) == []
        and expectation.get("termination") == "returned"
        and isinstance(expectation.get("frameworkDispatchCount"), int)
        and expectation["frameworkDispatchCount"] > 0
    )
```

`validate_conformance_config()` derives known profiles from executable runtime scenarios and known capabilities from `requiresCapabilities`, rejects target claims outside those sets, and requires at least one mandatory `_is_positive_control()` per claimed profile.

`select_cases()` returns deterministic scenario-ID order and marks capability-missing cases `applicable=False` rather than deleting them.

`validate_observation()` requires `termination` and non-negative integer `frameworkDispatchCount`, permits optional non-negative integer `replacementDispatchCount`, and permits optional string `errorCode`; unknown observation fields are rejected so harness output stays bounded.

`evaluate_observation()` compares only fields present in the structured expectation; advisory `errorCode` is not part of the normative comparison.

`aggregate_exit_code()` implements:

```python
if ERROR in statuses:
    return 2
if FAIL in statuses:
    return 1
return 0
```

- [ ] **Step 4: Run model tests and make them GREEN**

```bash
python -m unittest scripts.tests.test_conformance_model -v
```

Expected: all model tests PASS.

- [ ] **Step 5: Commit the pure model slice**

```bash
git add scripts/conformance_model.py scripts/tests/test_conformance_model.py
git commit -m "test(conformance): define runner selection model"
```

---

### Task 2: Subprocess Protocol Runner, Timeout, CLI, and Fake Harness Tests

**Files:**
- Create: `scripts/run_conformance.py`
- Create: `scripts/tests/test_run_conformance.py`
- Create: `scripts/tests/fixtures/fake_conformance_harness.py`

**Interfaces:**
- Consumes: Task 1 model primitives.
- Produces: `HarnessRun`, `run_harness(command, request, timeout_seconds=10.0) -> HarnessRun`, `execute_case(...)`, deterministic text/JSON rendering, `main(argv=None) -> int`.
- Later tasks provide real target manifests and browser harness commands; Task 2 proves the protocol independently with the fake child.

- [ ] **Step 1: Write the fake harness fixture with deterministic modes**

Create `scripts/tests/fixtures/fake_conformance_harness.py`:

```python
#!/usr/bin/env python3
import json
import sys
import time

mode = sys.argv[1]
request = json.loads(sys.stdin.read())

if mode == "hang":
    time.sleep(5)
    raise SystemExit(0)
if mode == "nonzero":
    raise SystemExit(7)
if mode == "invalid-json":
    print("not-json")
    raise SystemExit(0)
if mode == "extra-stdout":
    print("diagnostic that must not be on stdout")

response = {
    "protocolVersion": "0.1",
    "requestId": request["requestId"],
    "scenarioId": request["scenarioId"],
    "targetId": request["targetId"],
    "profile": request["profile"],
    "observation": {
        "termination": "returned",
        "frameworkDispatchCount": 1,
        "replacementDispatchCount": 0,
    },
}

if mode == "wrong-request":
    response["requestId"] = "wrong"
elif mode == "wrong-scenario":
    response["scenarioId"] = "WRONG"
elif mode == "wrong-version":
    response["protocolVersion"] = "9.9"

print(json.dumps(response, separators=(",", ":")))
```

`extra-stdout` intentionally produces two lines so strict whole-stdout JSON parsing fails.

- [ ] **Step 2: Write RED runner tests for protocol failures and timeout**

Create `scripts/tests/test_run_conformance.py` with `sys.executable` + fake fixture commands. Include tests named:

```text
test_valid_child_returns_observation
test_nonzero_child_is_error
test_invalid_json_is_error
test_extra_stdout_is_error
test_wrong_protocol_version_is_error
test_wrong_request_id_is_error
test_wrong_scenario_id_is_error
test_timeout_is_error
test_error_status_has_exit_code_precedence
test_target_filter_rejects_unknown_target
test_scenario_filter_rejects_unknown_scenario
test_json_output_is_deterministically_sorted
```

Use a short injected timeout only in the timeout test:

```python
result = run_harness(
    [sys.executable, str(FAKE), "hang"],
    request,
    timeout_seconds=0.05,
)
self.assertEqual("ERROR", result.status)
```

Production CLI still passes 10 seconds.

- [ ] **Step 3: Run runner tests and verify RED**

```bash
python -m unittest scripts.tests.test_run_conformance -v
```

Expected: import failure because `scripts/run_conformance.py` does not exist.

- [ ] **Step 4: Implement strict one-request/one-response subprocess handling**

Create `scripts/run_conformance.py` with:

```python
@dataclass(frozen=True)
class HarnessRun:
    status: str
    observation: dict[str, object] | None
    diagnostics: str
    infrastructure_error: str | None
```

`run_harness()` executes:

```python
completed = subprocess.run(
    command,
    input=json.dumps(request, separators=(",", ":")),
    text=True,
    capture_output=True,
    timeout=timeout_seconds,
    shell=False,
    check=False,
)
```

Require `returncode == 0`, parse `completed.stdout.strip()` with one `json.loads()` call, verify exact echoed `protocolVersion`, `requestId`, `scenarioId`, `targetId`, and `profile`, validate `observation` through Task 1, and preserve `stderr` as diagnostics only.

The runner creates deterministic request IDs as:

```python
request_id = f"{target_id}::{scenario_id}"
```

No exception message from the child becomes a normative conformance field.

- [ ] **Step 5: Implement deterministic target discovery/filtering and CLI rendering**

`main()` discovers JSON files under `conformance/targets/`, sorts by `targetId`, calls `select_cases()`, sorts cases by scenario ID, avoids spawning non-applicable cases, and supports:

```text
--target browser/livewire
--scenario BIND-NO-SILENT-RETARGET
--json
```

Unknown filters are configuration `ERROR` and produce process exit `2`, not an empty successful run.

Default text rendering is stable:

```text
PASS browser/htmx BIND-EXACT-TARGET-EXECUTES
N/A  browser/htmx BIND-COMPONENT-STALE
Summary: 1 PASS, 0 FAIL, 0 ERROR, 1 NOT_APPLICABLE
```

- [ ] **Step 6: Run runner tests and make them GREEN**

```bash
python -m unittest scripts.tests.test_run_conformance -v
python -m unittest discover -s scripts/tests -p 'test_conformance_*.py' -v
```

Expected: all Python conformance-runner tests PASS.

- [ ] **Step 7: Commit the process-runner slice**

```bash
git add scripts/run_conformance.py scripts/tests/test_run_conformance.py scripts/tests/fixtures/fake_conformance_harness.py
git commit -m "feat(conformance): add process runner protocol"
```

---

### Task 3: Extract Vitest-Free Shared Browser Target Support Without Changing T-604 Semantics

**Files:**
- Create: `packages/browser-runtime/conformance/support/binding-driver-target.ts`
- Create: `packages/browser-runtime/conformance/support/livewire-target.ts`
- Create: `packages/browser-runtime/conformance/support/htmx-target.ts`
- Modify: `packages/browser-runtime/tests/support/binding-driver-conformance-suite.ts`
- Modify: `packages/browser-runtime/tests/support/livewire-conformance-adapter.ts`
- Modify: `packages/browser-runtime/tests/support/htmx-conformance-adapter.ts`
- Test: existing `packages/browser-runtime/tests/binding-driver-conformance.livewire.test.ts`
- Test: existing `packages/browser-runtime/tests/binding-driver-conformance.htmx.test.ts`
- Test: existing `packages/browser-runtime/tests/binding-driver-conformance.typecheck.ts`

**Interfaces:**
- Produces: `CONFORMANCE_NOW`, `BindingDriverConformanceTarget`, `createLivewireConformanceTarget(now?)`, `createHtmxConformanceTarget(now?)`.
- Preserves: existing T-604 `BindingDriverConformanceAdapter` and unchanged 11-case shared suite behavior.
- Consumed by: Tasks 4–5 process harnesses.

- [ ] **Step 1: Add the Vitest-free target contract**

Create `packages/browser-runtime/conformance/support/binding-driver-target.ts`:

```ts
import type { BindingDriver, RuntimeBinding } from '../../src/types.js';

export const CONFORMANCE_NOW = new Date('2026-09-14T00:00:00.000Z');

export interface BindingDriverConformanceTarget {
  readonly driver: BindingDriver;
  readonly binding: RuntimeBinding;
  readonly validInput: Record<string, unknown>;
  readonly invalidTargetBinding: RuntimeBinding;
  readonly unknownInput: Record<string, unknown>;
  readonly missingRequiredInput: Record<string, unknown>;
  makeTargetStale(): void;
  replaceTargetWithEquivalentIdentity(): void;
  frameworkDispatchCount(): number;
  replacementDispatchCount(): number;
}
```

- [ ] **Step 2: RED-refactor Livewire target support**

Create `conformance/support/livewire-target.ts` by moving the controlled runtime/binding logic out of the current Vitest adapter. Replace `vi.fn()` with explicit counters/functions. The builder signature is:

```ts
export function createLivewireConformanceTarget(
  now: Date = CONFORMANCE_NOW,
): BindingDriverConformanceTarget
```

Before replacing the adapter, import the builder into `binding-driver-conformance.livewire.test.ts` through a temporary direct construction assertion and run that focused test. Expected RED is a missing builder/module until the file is created; remove the temporary assertion after the adapter is switched.

- [ ] **Step 3: GREEN-refactor the Livewire T-604 adapter to one thin wrapper**

Final adapter:

```ts
import { createLivewireConformanceTarget } from '../../conformance/support/livewire-target.js';
import type { BindingDriverConformanceAdapter } from './binding-driver-conformance-suite.js';

export const livewireConformanceAdapter: BindingDriverConformanceAdapter = {
  name: 'livewire',
  createHarness: createLivewireConformanceTarget,
};
```

- [ ] **Step 4: Repeat RED/GREEN extraction for HTMX**

Create `conformance/support/htmx-target.ts` using the current `ConformanceSource`, controlled source array, public runtime shape, and an explicit `ajaxDispatches` array instead of `vi.fn()`.

Signature:

```ts
export function createHtmxConformanceTarget(
  now: Date = CONFORMANCE_NOW,
): BindingDriverConformanceTarget
```

The final T-604 HTMX adapter contains no `vi` import and only wraps this builder.

- [ ] **Step 5: Move shared target type/clock ownership out of the Vitest suite**

Modify `binding-driver-conformance-suite.ts` to import:

```ts
import {
  CONFORMANCE_NOW,
  type BindingDriverConformanceTarget,
} from '../../conformance/support/binding-driver-target.js';
```

Keep `BindingDriverConformanceAdapter` test-local, with `createHarness(): BindingDriverConformanceTarget`. Do not alter any of the 11 test semantics or expected codes.

- [ ] **Step 6: Run the complete browser baseline**

```bash
cd packages/browser-runtime
npm run typecheck
npm test
```

Expected: existing T-604 remains `22/22` shared cases and the full browser suite is green. Record the exact current total rather than copying an older count.

- [ ] **Step 7: Review source diff to prove production files are untouched**

From repository root:

```bash
git diff --name-only -- packages/browser-runtime/src
```

Expected output: empty.

- [ ] **Step 8: Commit the shared-target extraction**

```bash
git add packages/browser-runtime/conformance/support packages/browser-runtime/tests/support packages/browser-runtime/tests/binding-driver-conformance.livewire.test.ts
git commit -m "refactor(conformance): share browser target harnesses"
```

---

### Task 4: TypeScript Process Protocol, Shared Observation Executor, and Livewire Harness

**Files:**
- Create: `packages/browser-runtime/conformance/protocol.ts`
- Create: `packages/browser-runtime/conformance/node-stdio.ts`
- Create: `packages/browser-runtime/conformance/run-binding-driver-scenario.ts`
- Create: `packages/browser-runtime/conformance/livewire-harness.ts`
- Create: `packages/browser-runtime/tests/binding-driver-conformance-observation.test.ts`
- Create: `packages/browser-runtime/tsconfig.conformance.json`
- Modify: `packages/browser-runtime/package.json`

**Interfaces:**
- Consumes: Task 3 `BindingDriverConformanceTarget` and Livewire target builder.
- Produces: `ConformanceRequest`, `ConformanceObservation`, `ConformanceResponse`, `parseConformanceRequest(value)`, `observeBindingDriverScenario(target, request)`, compiled Livewire process harness.
- No new dependency is added to `package.json`.

- [ ] **Step 1: Add protocol types and strict local request validation**

Create `conformance/protocol.ts`:

```ts
export interface ConformanceRequest {
  protocolVersion: '0.1';
  requestId: string;
  scenarioId: string;
  targetId: string;
  profile: 'runtime-binding/driver';
  now: string;
}

export interface ConformanceObservation {
  termination: 'returned' | 'threw';
  errorCode?: string;
  frameworkDispatchCount: number;
  replacementDispatchCount?: number;
}

export interface ConformanceResponse {
  protocolVersion: '0.1';
  requestId: string;
  scenarioId: string;
  targetId: string;
  profile: 'runtime-binding/driver';
  observation: ConformanceObservation;
}
```

`parseConformanceRequest(value: unknown): ConformanceRequest` fails loudly for missing/wrong fields and never accepts expected results.

- [ ] **Step 2: Add a dependency-free Node stdio adapter**

Create `conformance/node-stdio.ts` using a locally structural cast rather than Node ambient types:

```ts
interface MinimalNodeProcess {
  stdin: {
    setEncoding(encoding: 'utf8'): void;
    on(event: 'data', listener: (chunk: string) => void): void;
    on(event: 'end', listener: () => void): void;
  };
  stdout: { write(chunk: string): unknown };
  stderr: { write(chunk: string): unknown };
  exitCode?: number;
}

const nodeProcess = (globalThis as unknown as { process: MinimalNodeProcess }).process;
```

Expose `readSingleStdinDocument(): Promise<string>`, `writeProtocolJson(value: unknown): void`, `writeDiagnostic(message: string): void`, and `setFailureExitCode(): void`.

- [ ] **Step 3: Write an exact RED observation test file**

Create `packages/browser-runtime/tests/binding-driver-conformance-observation.test.ts` with tests using `createLivewireConformanceTarget()` and the not-yet-created `observeBindingDriverScenario()`:

```ts
it('observes one exact successful dispatch without producing a verdict', async () => {
  const target = createLivewireConformanceTarget();
  const observation = await observeBindingDriverScenario(target, {
    protocolVersion: '0.1',
    requestId: 'livewire::exact',
    scenarioId: 'BIND-EXACT-TARGET-EXECUTES',
    targetId: 'browser/livewire',
    profile: 'runtime-binding/driver',
    now: '2026-09-14T00:00:00.000Z',
  });

  expect(observation).toEqual({
    termination: 'returned',
    frameworkDispatchCount: 1,
    replacementDispatchCount: 0,
  });
  expect(observation).not.toHaveProperty('passed');
});
```

Add expiry and no-retarget tests expecting raw `threw` + zero dispatch fields.

- [ ] **Step 4: Run the focused test and verify RED**

```bash
cd packages/browser-runtime
npx vitest run tests/binding-driver-conformance-observation.test.ts
```

Expected: FAIL because `run-binding-driver-scenario.ts`/export does not exist.

- [ ] **Step 5: Implement `observeBindingDriverScenario()` and make the focused tests GREEN**

Create `conformance/run-binding-driver-scenario.ts` with:

```ts
export async function observeBindingDriverScenario(
  target: BindingDriverConformanceTarget,
  request: ConformanceRequest,
): Promise<ConformanceObservation>
```

Scenario setup is limited to:

- `BIND-EXACT-TARGET-EXECUTES`: execute unmodified exact binding.
- `BIND-EXPIRED-NOT-EXECUTABLE`: clone binding with `expiresAt` one millisecond before `request.now`.
- `BIND-COMPONENT-STALE`: call `target.makeTargetStale()` before execution.
- `BIND-NO-SILENT-RETARGET`: call `target.replaceTargetWithEquivalentIdentity()` before execution.

Catch production exceptions only to classify `termination='threw'` and copy a raw string `error.code` when present. Do not infer stale/expired semantics from class/message.

Run:

```bash
npx vitest run tests/binding-driver-conformance-observation.test.ts
```

Expected: PASS.

- [ ] **Step 6: Add the Livewire process entrypoint**

Create `conformance/livewire-harness.ts`:

```ts
const raw = await readSingleStdinDocument();
const request = parseConformanceRequest(JSON.parse(raw));
if (request.targetId !== 'browser/livewire') throw new Error('Unexpected targetId.');
const now = new Date(request.now);
if (Number.isNaN(now.getTime())) throw new Error('Invalid conformance clock.');
const target = createLivewireConformanceTarget(now);
const observation = await observeBindingDriverScenario(target, request);
writeProtocolJson({
  protocolVersion: '0.1',
  requestId: request.requestId,
  scenarioId: request.scenarioId,
  targetId: request.targetId,
  profile: request.profile,
  observation,
});
```

Top-level infrastructure exceptions write diagnostics to stderr, set non-zero exit code, and emit no fake observation.

- [ ] **Step 7: Add dedicated emit config and package build script**

Create `tsconfig.conformance.json`:

```json
{
  "extends": "./tsconfig.json",
  "compilerOptions": {
    "noEmit": false,
    "outDir": ".tmp/conformance",
    "rootDir": ".",
    "declaration": false,
    "types": []
  },
  "include": [
    "src/**/*.ts",
    "conformance/**/*.ts"
  ]
}
```

Add exactly one package script:

```json
"conformance:build": "tsc -p tsconfig.conformance.json"
```

Do not add devDependencies.

- [ ] **Step 8: Build and manually smoke the Livewire harness**

```bash
npm run conformance:build
printf '%s' '{"protocolVersion":"0.1","requestId":"smoke-livewire","scenarioId":"BIND-EXACT-TARGET-EXECUTES","targetId":"browser/livewire","profile":"runtime-binding/driver","now":"2026-09-14T00:00:00.000Z"}' \
  | node .tmp/conformance/conformance/livewire-harness.js
```

Expected stdout: exactly one JSON document whose observation contains returned/dispatch-1/replacement-0.

- [ ] **Step 9: Run browser regression and commit**

```bash
npm run typecheck
npm test
cd ../..
git add packages/browser-runtime/conformance packages/browser-runtime/tests/binding-driver-conformance-observation.test.ts packages/browser-runtime/tsconfig.conformance.json packages/browser-runtime/package.json
git commit -m "feat(conformance): add Livewire process harness"
```

---

### Task 5: HTMX Process Harness on the Same Protocol and Scenario Executor

**Files:**
- Create: `packages/browser-runtime/conformance/htmx-harness.ts`
- Modify: `packages/browser-runtime/tests/binding-driver-conformance-observation.test.ts`

**Interfaces:**
- Consumes: Task 4 protocol/executor and Task 3 HTMX target builder.
- Produces: process-compatible `browser/htmx` harness with no separate scenario semantics.
- HTMX has no `lifecycle.component` capability claim; the Python runner will not send `BIND-COMPONENT-STALE` in the canonical run.

- [ ] **Step 1: Add RED HTMX observation tests before the process entrypoint**

Extend `binding-driver-conformance-observation.test.ts` with HTMX exact execution and no-retarget cases using the same `observeBindingDriverScenario()` function.

Expected raw no-retarget observation:

```json
{
  "termination": "threw",
  "frameworkDispatchCount": 0,
  "replacementDispatchCount": 0
}
```

Run the focused test and verify RED until `createHtmxConformanceTarget()` and shared executor integration are correct.

- [ ] **Step 2: Make HTMX observation tests GREEN**

```bash
cd packages/browser-runtime
npx vitest run tests/binding-driver-conformance-observation.test.ts
```

Expected: all Livewire + HTMX raw-observation tests PASS.

- [ ] **Step 3: Add the HTMX entrypoint**

Create `conformance/htmx-harness.ts` mirroring Livewire except:

```ts
if (request.targetId !== 'browser/htmx') throw new Error('Unexpected targetId.');
const target = createHtmxConformanceTarget(now);
```

Do not add HTMX-specific result fields.

- [ ] **Step 4: Build and smoke positive exact execution**

```bash
npm run conformance:build
printf '%s' '{"protocolVersion":"0.1","requestId":"smoke-htmx","scenarioId":"BIND-EXACT-TARGET-EXECUTES","targetId":"browser/htmx","profile":"runtime-binding/driver","now":"2026-09-14T00:00:00.000Z"}' \
  | node .tmp/conformance/conformance/htmx-harness.js
```

Expected observation: returned, framework dispatch `1`, replacement dispatch `0`.

- [ ] **Step 5: Smoke no-silent-retarget negative proof**

Run the same command with `scenarioId=BIND-NO-SILENT-RETARGET`.

Expected observation: threw, framework dispatch `0`, replacement dispatch `0`; raw `errorCode` may be present but remains advisory.

- [ ] **Step 6: Run the full browser regression suite and commit**

```bash
npm run typecheck
npm test
cd ../..
git add packages/browser-runtime/conformance/htmx-harness.ts packages/browser-runtime/tests/binding-driver-conformance-observation.test.ts
git commit -m "feat(conformance): add HTMX process harness"
```

---

### Task 6: Canonical Runtime Scenario Promotion, Target Manifests, and Structural Validation

**Files:**
- Modify: `spec/0.1/fixtures/conformance-scenarios.json`
- Create: `conformance/targets/browser-livewire.json`
- Create: `conformance/targets/browser-htmx.json`
- Create: `conformance/README.md`
- Modify: `scripts/validate.py`
- Modify: `scripts/tests/test_conformance_model.py`

**Interfaces:**
- Consumes: Task 1 validation/selection model; Tasks 4–5 compiled harness commands.
- Produces: real canonical v1 config consumed by `scripts/run_conformance.py`.

- [ ] **Step 1: RED-test canonical structural rules in the Python model**

Extend `test_conformance_model.py` to prove:

- executable runtime scenario without `profile` fails;
- executable runtime scenario without structured `expectation` fails;
- unknown expectation key fails;
- target unknown profile/capability fails;
- duplicate target IDs fail;
- claimed profile with only negative controls fails;
- a mandatory positive control makes the same profile valid.

```bash
python -m unittest scripts.tests.test_conformance_model -v
```

Expected: new cases FAIL until model validation is tightened.

- [ ] **Step 2: Promote exactly the approved scenario set**

Edit `spec/0.1/fixtures/conformance-scenarios.json`:

1. Add `BIND-EXACT-TARGET-EXECUTES` as `kind=runtime`, `status=executable`, profile `runtime-binding/driver`, no required capabilities, expectation returned/dispatch-1/replacement-0.
2. Promote `BIND-EXPIRED-NOT-EXECUTABLE` to executable with same profile and expectation threw/dispatch-0; keep `recommendedCode=binding_expired` advisory.
3. Promote `BIND-COMPONENT-STALE` with `requiresCapabilities=["lifecycle.component"]` and expectation threw/dispatch-0; keep `recommendedCode=binding_stale` advisory.
4. Promote `BIND-NO-SILENT-RETARGET` with no required capabilities and expectation threw/dispatch-0/replacement-0; keep `recommendedCode=binding_stale` advisory.
5. Leave `BIND-ID-UNKNOWN`, `BIND-DRIVER-UNKNOWN`, and `BIND-ACTION-VERSION-UNAVAILABLE` documented.

Do not create canonical IDs for T-604 malformed-target/input/cancellation cases.

- [ ] **Step 3: Add exact target manifests**

`conformance/targets/browser-livewire.json`:

```json
{
  "targetId": "browser/livewire",
  "protocolVersion": "0.1",
  "profiles": ["runtime-binding/driver"],
  "capabilities": ["lifecycle.component"],
  "command": ["node", "packages/browser-runtime/.tmp/conformance/conformance/livewire-harness.js"]
}
```

`browser-htmx.json` uses empty capabilities and the HTMX harness path.

- [ ] **Step 4: Integrate structural conformance validation into `scripts/validate.py`**

Import `validate_conformance_config` from `conformance_model`, preserve schema scenario fixture checks, load `conformance/targets/*.json`, and report each configuration error:

```python
config_errors = validate_conformance_config({"scenarios": scenarios}, targets)
for error in config_errors:
    print(f"FAIL conformance config: {error}")
    failures += 1
```

Do not invoke `run_conformance.py` from `validate.py`.

- [ ] **Step 5: Document the repo-local protocol boundary**

Create `conformance/README.md` describing repo-local experimental status, canonical semantic registry, target manifest fields, process protocol, runner verdict authority, advisory `recommendedCode`, current profile/capability vocabulary, exact targets, and explicit non-certification/public-SDK status.

- [ ] **Step 6: Make model/structural tests GREEN**

```bash
python -m unittest discover -s scripts/tests -p 'test_conformance_*.py' -v
python scripts/validate.py
```

Expected: all runner/model tests PASS and starter validation PASS.

- [ ] **Step 7: Build harnesses and run the real canonical matrix**

```bash
cd packages/browser-runtime
npm run conformance:build
cd ../..
python scripts/run_conformance.py
```

Expected exact summary:

```text
7 PASS, 0 FAIL, 0 ERROR, 1 NOT_APPLICABLE
```

HTMX `BIND-COMPONENT-STALE` is runner-owned N/A and its subprocess is not spawned.

- [ ] **Step 8: Prove advisory code mismatch cannot flip PASS**

Add a unit case with correct normative fields and a missing/different `errorCode`; `evaluate_observation()` must report no normative mismatch. Renderer may report advisory mismatch separately.

- [ ] **Step 9: Commit canonical config and validation**

```bash
git add spec/0.1/fixtures/conformance-scenarios.json conformance scripts/validate.py scripts/tests/test_conformance_model.py
git commit -m "feat(conformance): execute canonical binding scenarios"
```

---

### Task 7: CI Integration and Implemented Conformance Documentation

**Files:**
- Modify: `.github/workflows/validate.yml`
- Modify: `CONFORMANCE.md`

**Interfaces:**
- Consumes: Tasks 1–6 complete runner/harness/config.
- Produces: T-701 required inside the existing seven-job workflow; no new job.

- [ ] **Step 1: Update `CONFORMANCE.md` to implemented state**

State precisely that schema scenarios remain executable via `scripts/validate.py`; four `runtime-binding/driver` scenarios are executable through T-701; Livewire applies to all four; HTMX applies to three and reports component stale N/A; T-604 still owns broader 11-case package regression; T-603 remains separate real-browser evidence; binding lookup/driver-registry/action-availability scenarios remain documented.

Do not claim Trust/Output/Projection conformance.

- [ ] **Step 2: Extend the existing browser CI job without adding a job**

After browser tests add:

```yaml
      - uses: actions/setup-python@v5
        with:
          python-version: '3.12'
      - name: Run conformance runner unit tests
        run: python -m unittest discover -s scripts/tests -p 'test_conformance_*.py' -v
      - name: Build browser conformance harnesses
        working-directory: packages/browser-runtime
        run: npm run conformance:build
      - name: Run canonical runtime conformance
        run: python scripts/run_conformance.py
```

No Python requirements install is needed in browser job; contract job still owns `requirements-dev.txt` + `scripts/validate.py`.

- [ ] **Step 3: Run the complete local verification set**

```bash
python -m unittest discover -s scripts/tests -p 'test_conformance_*.py' -v
python scripts/validate.py
cd packages/browser-runtime
npm run typecheck
npm test
npm run conformance:build
cd ../..
python scripts/run_conformance.py
python scripts/run_conformance.py --target browser/livewire
python scripts/run_conformance.py --scenario BIND-NO-SILENT-RETARGET
python scripts/run_conformance.py --json
```

Expected: no failures/errors; full canonical run `7 PASS / 1 NOT_APPLICABLE`; filtered runs include only requested cases in deterministic order.

- [ ] **Step 4: Verify no dependency drift**

```bash
git diff -- packages/browser-runtime/package-lock.json requirements-dev.txt
```

Expected: empty.

- [ ] **Step 5: Verify production browser source was not changed**

```bash
git diff 05b020c94279c295068895a8163a892549327d3f...HEAD -- packages/browser-runtime/src
```

Expected: empty. Any required semantic production change reopens design gate.

- [ ] **Step 6: Commit CI/docs integration**

```bash
git add .github/workflows/validate.yml CONFORMANCE.md
git commit -m "ci(conformance): run canonical browser matrix"
```

- [ ] **Step 7: Inspect the push-triggered GitHub Actions run for the exact implementation head**

Required evidence before calling implementation verified:

```text
7 workflow jobs total
7/7 success
browser: typecheck + Vitest + runner unit tests + harness build + canonical runner
contract: scripts/validate.py success
```

Record exact run ID and exact implementation head SHA.

---

### Task 8: Internal Review Gate, Tracking Evidence, and External-Review Handoff

**Files:**
- Modify: `TASKS.md`
- Modify: `STATUS.md`
- Modify: `REVIEW_REQUEST.md`
- Read/verify: `docs/DECISION-REGISTER.md`
- Read/verify: `docs/GLOSSARY.md`

**Interfaces:**
- Consumes: exact verified implementation head and CI run from Task 7.
- Produces: review-ready evidence only. It does not accept D-059/D-060/D-061, merge, or start T-702.

- [ ] **Step 1: Review the full implementation diff against the approved design base**

```bash
git diff --stat 05b020c94279c295068895a8163a892549327d3f...HEAD
git diff --name-only 05b020c94279c295068895a8163a892549327d3f...HEAD
```

Confirm changes are limited to planned runner, metadata/manifests/docs, browser conformance test infrastructure, package script, and workflow. Confirm no Laravel production source, T-603 fixture behavior, standalone spec extraction, T-702/T-703/T-704 implementation, or dependency expansion appeared.

- [ ] **Step 2: Re-run fresh implementation verification on the exact review-prep head**

```bash
python -m unittest discover -s scripts/tests -p 'test_conformance_*.py' -v
python scripts/validate.py
cd packages/browser-runtime
npm run typecheck
npm test
npm run conformance:build
cd ../..
python scripts/run_conformance.py
```

Required runtime summary:

```text
7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR
```

- [ ] **Step 3: Update `TASKS.md` with implementation evidence while keeping review/merge state honest**

Record design/plan paths, implementation head, Python runner tests, browser tests/typecheck, canonical matrix, validation run, D-059/D-060/D-061 still PROPOSED, and T-701 as implementation/verification complete but not reviewed/merged unless those gates actually occurred.

- [ ] **Step 4: Update `STATUS.md` with exact active boundary**

Next gate is external review of T-701, not T-702. Preserve M6 historical evidence. Under `Needs decision`, keep D-059/D-060/D-061 and D-026 as PROPOSED.

- [ ] **Step 5: Replace `REVIEW_REQUEST.md` with a T-701 review handoff**

Include scope/non-goals, exact implementation head, changed-file summary, TDD/verification evidence, canonical `7 PASS / 1 N/A`, review questions around runner authority/process protocol/capability applicability/advisory codes/no-retarget evidence/scope containment, and statement that decision promotion + merge need later explicit gates.

- [ ] **Step 6: Confirm proposed decisions were not prematurely promoted**

Use a Python one-liner rather than shell-specific grep:

```bash
python - <<'PY'
from pathlib import Path
text = Path('docs/DECISION-REGISTER.md').read_text(encoding='utf-8')
for decision in ('D-026', 'D-059', 'D-060', 'D-061'):
    line = next(line for line in text.splitlines() if f'| {decision} |' in line)
    print(line)
    assert '| PROPOSED |' in line
PY
```

- [ ] **Step 7: Commit review-prep tracking**

```bash
git add TASKS.md STATUS.md REVIEW_REQUEST.md
git commit -m "docs(conformance): prepare T-701 external review"
```

- [ ] **Step 8: Inspect fresh CI for the exact review-prep commit and stop**

Required:

```text
validate workflow: 7/7 green on exact review-prep SHA
canonical runner: 7 PASS / 1 N/A
D-059/D-060/D-061: still PROPOSED
```

Stop here. Do not merge, accept architecture decisions, or begin T-702 without the next explicit user gate.

---

## Final Plan Verification Checklist

Before implementation is called review-ready, evidence must exist for every item:

- [ ] Canonical runner owns scenario selection and verdicts.
- [ ] Harness output contains raw observations only.
- [ ] HTMX component-stale is runner-owned N/A with no subprocess spawn.
- [ ] Mandatory positive exact-execution scenario prevents always-reject conformance.
- [ ] Expiry and stale/no-retarget cases prove zero unintended dispatch.
- [ ] Replacement identity proves zero replacement dispatch.
- [ ] `recommendedCode` mismatch alone is advisory, not normative FAIL.
- [ ] Malformed/extra child stdout, wrong echoed IDs/version, non-zero child exit, and timeout all become ERROR.
- [ ] One scenario uses one fresh subprocess.
- [ ] Production timeout is exactly 10 seconds.
- [ ] Target commands are argv arrays and runner uses `shell=False`.
- [ ] `scripts/validate.py` validates structure but never executes harnesses.
- [ ] Normal browser `tsconfig.json` remains `noEmit`; dedicated conformance config emits under `.tmp/conformance/`.
- [ ] T-604 shared browser tests remain green after support extraction.
- [ ] No dependency was added for Python runner or Node process typing.
- [ ] No semantic changes exist under `packages/browser-runtime/src/**`.
- [ ] Canonical full matrix is exactly `7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR`.
- [ ] Existing GitHub Actions workflow remains seven jobs and is fully green.
- [ ] D-026, D-059, D-060, and D-061 remain `PROPOSED` at external-review handoff.
- [ ] T-702/T-703/T-704 remain unstarted.
