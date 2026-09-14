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
- Request is one JSON document on stdin; response is exactly one JSON observation document on stdout; stderr is diagnostics only.
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
- Produces: `ConformanceConfigError`, `SelectedCase`, `validate_conformance_config(registry, targets) -> list[str]`, `select_cases(registry, target) -> list[SelectedCase]`, `validate_observation(observation) -> list[str]`, `evaluate_observation(expectation, observation) -> list[str]`, `aggregate_exit_code(statuses) -> int`.
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

Run:

```bash
python -m unittest scripts.tests.test_conformance_model -v
```

Expected: import failure because `scripts/conformance_model.py` does not exist.

- [ ] **Step 3: Implement the minimal pure model**

Create `scripts/conformance_model.py` around these exact v1 primitives:

```python
from dataclasses import dataclass
from typing import Any, Mapping, Sequence

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

`validate_conformance_config()` must derive known profiles from executable runtime scenarios and known capabilities from `requiresCapabilities`, reject target claims outside those sets, and require at least one mandatory `_is_positive_control()` per claimed profile.

`select_cases()` must return deterministic scenario-ID order and mark capability-missing cases `applicable=False` rather than deleting them.

`evaluate_observation()` must compare only fields present in the structured expectation; advisory `errorCode` is not part of the normative comparison.

`aggregate_exit_code()` must implement:

```python
if ERROR in statuses:
    return 2
if FAIL in statuses:
    return 1
return 0
```

- [ ] **Step 4: Run model tests and make them GREEN**

Run:

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
- Produces: `run_harness(command, request, timeout_seconds=10.0)`, `execute_case(...)`, deterministic text/JSON rendering, `main(argv=None) -> int`.
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

Production CLI must still use 10 seconds.

- [ ] **Step 3: Run runner tests and verify RED**

Run:

```bash
python -m unittest scripts.tests.test_run_conformance -v
```

Expected: import failure because `scripts/run_conformance.py` does not exist.

- [ ] **Step 4: Implement strict one-request/one-response subprocess handling**

Create `scripts/run_conformance.py` with a small dataclass for execution results and strict response-envelope checks. The implementation must:

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

Then require `returncode == 0`, parse `completed.stdout.strip()` with one `json.loads()` call, verify exact echoed `protocolVersion`, `requestId`, `scenarioId`, `targetId`, and `profile`, validate the `observation` through Task 1, and preserve `stderr` only as diagnostics.

No exception message from the child becomes a normative conformance field.

- [ ] **Step 5: Implement deterministic target discovery/filtering and CLI rendering**

`main()` must discover JSON files under `conformance/targets/` once they exist, sort by `targetId`, use `select_cases()`, sort cases by scenario ID, avoid spawning non-applicable cases, and support:

```text
--target browser/livewire
--scenario BIND-NO-SILENT-RETARGET
--json
```

Unknown filters are configuration errors and produce process exit `2`, not an empty successful run.

Default text rendering format must be stable:

```text
PASS browser/htmx BIND-EXACT-TARGET-EXECUTES
N/A  browser/htmx BIND-COMPONENT-STALE
Summary: 1 PASS, 0 FAIL, 0 ERROR, 1 NOT_APPLICABLE
```

- [ ] **Step 6: Run the runner tests and make them GREEN**

Run:

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
- Consumed by: Task 4 and Task 5 process harnesses.

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

Create `conformance/support/livewire-target.ts` by moving the controlled runtime/binding logic out of the current Vitest adapter. Replace `vi.fn()` with explicit counters/functions; for example the `$call` implementation increments a local integer and returns `{ ok: true }`.

The builder signature is:

```ts
export function createLivewireConformanceTarget(
  now: Date = CONFORMANCE_NOW,
): BindingDriverConformanceTarget
```

Before changing the old adapter, temporarily import the builder in a focused new typecheck assertion or adapter and run the shared tests expecting failure until the adapter points at the new builder.

- [ ] **Step 3: GREEN-refactor the Livewire T-604 adapter to one thin wrapper**

The final adapter should be structurally equivalent to:

```ts
import { createLivewireConformanceTarget } from '../../conformance/support/livewire-target.js';
import type { BindingDriverConformanceAdapter } from './binding-driver-conformance-suite.js';

export const livewireConformanceAdapter: BindingDriverConformanceAdapter = {
  name: 'livewire',
  createHarness: createLivewireConformanceTarget,
};
```

- [ ] **Step 4: Repeat RED/GREEN extraction for HTMX**

Create `conformance/support/htmx-target.ts` using the current `ConformanceSource`, controlled source array, public runtime shape, and explicit `ajaxDispatches` array instead of `vi.fn()`.

Signature:

```ts
export function createHtmxConformanceTarget(
  now: Date = CONFORMANCE_NOW,
): BindingDriverConformanceTarget
```

The final T-604 HTMX adapter must contain no `vi` import and only wrap this builder.

- [ ] **Step 5: Move shared harness type/clock ownership out of the Vitest suite**

Modify `binding-driver-conformance-suite.ts` to import:

```ts
import {
  CONFORMANCE_NOW,
  type BindingDriverConformanceTarget,
} from '../../conformance/support/binding-driver-target.js';
```

Keep `BindingDriverConformanceAdapter` test-local, but make `createHarness(): BindingDriverConformanceTarget`.

Do not alter any of the 11 test semantics or expected codes.

- [ ] **Step 6: Run the complete browser baseline**

Run:

```bash
cd packages/browser-runtime
npm run typecheck
npm test
```

Expected: existing T-604 remains `22/22` shared cases and the full browser suite remains green. Record the exact current total; do not hardcode an old count if unrelated baseline tests changed.

- [ ] **Step 7: Review source diff to prove production files are untouched**

Run from repository root:

```bash
git diff --name-only HEAD~1..HEAD -- packages/browser-runtime/src
```

When run after staging/commit boundaries, expected output for this task is empty.

- [ ] **Step 8: Commit the shared-target extraction**

```bash
git add packages/browser-runtime/conformance/support packages/browser-runtime/tests/support
git commit -m "refactor(conformance): share browser target harnesses"
```

---

### Task 4: TypeScript Process Protocol and Livewire Harness

**Files:**
- Create: `packages/browser-runtime/conformance/protocol.ts`
- Create: `packages/browser-runtime/conformance/node-stdio.ts`
- Create: `packages/browser-runtime/conformance/run-binding-driver-scenario.ts`
- Create: `packages/browser-runtime/conformance/livewire-harness.ts`
- Create: `packages/browser-runtime/tsconfig.conformance.json`
- Modify: `packages/browser-runtime/package.json`

**Interfaces:**
- Consumes: Task 3 `BindingDriverConformanceTarget` and Livewire target builder.
- Produces: `ConformanceRequest`, `ConformanceObservation`, `ConformanceResponse`, `observeBindingDriverScenario(target, request)`, compiled Livewire process harness.
- No new dependency is added to `package.json`.

- [ ] **Step 1: Add protocol types and strict local request validation**

Create `conformance/protocol.ts` with these exact public shapes:

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

Provide `parseConformanceRequest(value: unknown): ConformanceRequest` that fails loudly for missing/wrong fields. It does not receive or parse expected results.

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

This avoids adding `@types/node` solely for process globals.

- [ ] **Step 3: RED-test the common scenario executor via a temporary focused Vitest test or existing target builders**

Add a focused test file only if needed for the RED/GREEN cycle, asserting that:

```text
BIND-EXACT-TARGET-EXECUTES → returned, dispatch 1, replacement 0
BIND-EXPIRED-NOT-EXECUTABLE → threw, dispatch 0
BIND-NO-SILENT-RETARGET → threw, original 0, replacement 0
```

The executor must not compare these values with expectations; it only records them.

- [ ] **Step 4: Implement `observeBindingDriverScenario()`**

Create `conformance/run-binding-driver-scenario.ts` with a scenario switch limited to the four canonical v1 IDs:

```ts
export async function observeBindingDriverScenario(
  target: BindingDriverConformanceTarget,
  request: ConformanceRequest,
): Promise<ConformanceObservation>
```

Setup rules:

- `BIND-EXACT-TARGET-EXECUTES`: execute the unmodified exact binding.
- `BIND-EXPIRED-NOT-EXECUTABLE`: clone `binding` with `expiresAt` exactly equal to one millisecond before `request.now`.
- `BIND-COMPONENT-STALE`: call `target.makeTargetStale()` before execution.
- `BIND-NO-SILENT-RETARGET`: call `target.replaceTargetWithEquivalentIdentity()` before execution.

Catch the production exception only to classify `termination='threw'` and, when `typeof error === 'object'` and it has a string `code`, copy that raw code into `errorCode`. Do not infer stale/expired semantics from exception class/message.

- [ ] **Step 5: Add Livewire process entrypoint**

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

Top-level infrastructure exceptions must write diagnostics to stderr, set a non-zero exit code, and emit no fake observation.

- [ ] **Step 6: Add dedicated emit config and package build script**

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

Add exactly one script to `package.json`:

```json
"conformance:build": "tsc -p tsconfig.conformance.json"
```

Do not add devDependencies.

- [ ] **Step 7: Build and manually smoke the Livewire harness**

Run:

```bash
cd packages/browser-runtime
npm run conformance:build
printf '%s' '{"protocolVersion":"0.1","requestId":"smoke-livewire","scenarioId":"BIND-EXACT-TARGET-EXECUTES","targetId":"browser/livewire","profile":"runtime-binding/driver","now":"2026-09-14T00:00:00.000Z"}' \
  | node .tmp/conformance/conformance/livewire-harness.js
```

Expected stdout is exactly one JSON document whose observation contains:

```json
{"termination":"returned","frameworkDispatchCount":1,"replacementDispatchCount":0}
```

- [ ] **Step 8: Re-run browser typecheck/tests and commit**

```bash
npm run typecheck
npm test
cd ../..
git add packages/browser-runtime/conformance packages/browser-runtime/tsconfig.conformance.json packages/browser-runtime/package.json
git commit -m "feat(conformance): add Livewire process harness"
```

---

### Task 5: HTMX Process Harness on the Same Protocol and Scenario Executor

**Files:**
- Create: `packages/browser-runtime/conformance/htmx-harness.ts`
- Reuse: `packages/browser-runtime/conformance/protocol.ts`
- Reuse: `packages/browser-runtime/conformance/node-stdio.ts`
- Reuse: `packages/browser-runtime/conformance/run-binding-driver-scenario.ts`
- Reuse: `packages/browser-runtime/conformance/support/htmx-target.ts`

**Interfaces:**
- Produces: process-compatible `browser/htmx` harness with no separate scenario semantics.
- Preserves: HTMX has no `lifecycle.component` capability claim in T-701; the Python runner will never send `BIND-COMPONENT-STALE` in the canonical run.

- [ ] **Step 1: Add the HTMX entrypoint with the same response envelope**

Create `conformance/htmx-harness.ts` mirroring the Livewire entrypoint except:

```ts
if (request.targetId !== 'browser/htmx') throw new Error('Unexpected targetId.');
const target = createHtmxConformanceTarget(now);
```

Do not add HTMX-specific result fields.

- [ ] **Step 2: Build and smoke positive exact execution**

Run:

```bash
cd packages/browser-runtime
npm run conformance:build
printf '%s' '{"protocolVersion":"0.1","requestId":"smoke-htmx","scenarioId":"BIND-EXACT-TARGET-EXECUTES","targetId":"browser/htmx","profile":"runtime-binding/driver","now":"2026-09-14T00:00:00.000Z"}' \
  | node .tmp/conformance/conformance/htmx-harness.js
```

Expected observation: returned, framework dispatch `1`, replacement dispatch `0`.

- [ ] **Step 3: Smoke no-silent-retarget negative proof**

Run the same command with `scenarioId=BIND-NO-SILENT-RETARGET`.

Expected observation includes:

```json
{
  "termination": "threw",
  "frameworkDispatchCount": 0,
  "replacementDispatchCount": 0
}
```

The raw `errorCode` may be present; it remains advisory to the Python runner.

- [ ] **Step 4: Run the full browser regression suite**

```bash
npm run typecheck
npm test
```

Expected: all browser tests PASS; T-604 remains unchanged semantically.

- [ ] **Step 5: Commit the HTMX process harness**

```bash
cd ../..
git add packages/browser-runtime/conformance/htmx-harness.ts
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

Extend `test_conformance_model.py` with cases proving:

- executable runtime scenario without `profile` fails;
- executable runtime scenario without structured `expectation` fails;
- unknown expectation key fails;
- target unknown profile/capability fails;
- duplicate target IDs fail;
- claimed profile with only negative controls fails;
- a mandatory positive control makes the same profile valid.

Run:

```bash
python -m unittest scripts.tests.test_conformance_model -v
```

Expected: new tests FAIL until Task 1 model is tightened as required.

- [ ] **Step 2: Promote exactly the approved scenario set**

Edit `spec/0.1/fixtures/conformance-scenarios.json`:

1. Add `BIND-EXACT-TARGET-EXECUTES` as `kind=runtime`, `status=executable`, profile `runtime-binding/driver`, no required capabilities, expectation returned/dispatch-1/replacement-0.
2. Promote `BIND-EXPIRED-NOT-EXECUTABLE` to executable with the same profile and expectation threw/dispatch-0; keep `recommendedCode=binding_expired` advisory.
3. Promote `BIND-COMPONENT-STALE` to executable with `requiresCapabilities=["lifecycle.component"]` and expectation threw/dispatch-0; keep `recommendedCode=binding_stale` advisory.
4. Promote `BIND-NO-SILENT-RETARGET` to executable with no required capabilities and expectation threw/dispatch-0/replacement-0; keep `recommendedCode=binding_stale` advisory.
5. Leave `BIND-ID-UNKNOWN`, `BIND-DRIVER-UNKNOWN`, and `BIND-ACTION-VERSION-UNAVAILABLE` documented.

Do not create canonical IDs for T-604 malformed target/input/cancellation cases in this task.

- [ ] **Step 3: Add the exact target manifests**

Create `conformance/targets/browser-livewire.json`:

```json
{
  "targetId": "browser/livewire",
  "protocolVersion": "0.1",
  "profiles": ["runtime-binding/driver"],
  "capabilities": ["lifecycle.component"],
  "command": [
    "node",
    "packages/browser-runtime/.tmp/conformance/conformance/livewire-harness.js"
  ]
}
```

Create `conformance/targets/browser-htmx.json` with the same profile, empty capabilities, and HTMX harness path.

- [ ] **Step 4: Integrate structural conformance validation into `scripts/validate.py`**

Import `validate_conformance_config` from `conformance_model` and replace the old runtime-only `fail_closed + recommendedCode` assumption with model-based validation while preserving schema scenario fixture checks.

Load all target manifests from `conformance/targets/*.json`, then:

```python
config_errors = validate_conformance_config({"scenarios": scenarios}, targets)
for error in config_errors:
    print(f"FAIL conformance config: {error}")
    failures += 1
```

Do not invoke `run_conformance.py` from `validate.py`.

- [ ] **Step 5: Document the repo-local protocol boundary**

Create `conformance/README.md` describing:

- repo-local experimental status;
- canonical semantic registry location;
- target manifest fields;
- one-request/one-observation protocol;
- PASS/FAIL authority belonging to the runner;
- `recommendedCode` advisory status;
- current profile/capability vocabulary;
- exact v1 target set;
- explicit statement that this is not a public certification SDK/spec.

- [ ] **Step 6: Run structural validation and Python tests**

```bash
python -m unittest discover -s scripts/tests -p 'test_conformance_*.py' -v
python scripts/validate.py
```

Expected: all runner/model tests PASS and starter validation PASS.

- [ ] **Step 7: Build browser harnesses and run the real canonical matrix**

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

Verify that HTMX `BIND-COMPONENT-STALE` is reported N/A and that no child process is spawned for that case using the runner test instrumentation, not harness self-reporting.

- [ ] **Step 8: Verify advisory code mismatch cannot flip PASS**

Use a Python unit fixture observation with a missing or different `errorCode` but correct normative fields and prove `evaluate_observation()` returns no normative mismatch. The renderer may show advisory mismatch separately.

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
- Test: complete existing workflow plus local commands.

**Interfaces:**
- Consumes: complete T-701 runner/harness/config from Tasks 1–6.
- Produces: T-701 as a required part of the existing seven-job `validate` workflow; no new job.

- [ ] **Step 1: Update `CONFORMANCE.md` to match implemented state**

Replace future-T-701 wording only where now implemented. State precisely:

- schema scenarios remain executable via `scripts/validate.py`/fixture manifest;
- four `runtime-binding/driver` scenarios are executable through T-701;
- Livewire applies to all four;
- HTMX applies to the three non-component-specific scenarios and reports component stale N/A;
- T-604 still owns its broader 11-case package regression set;
- T-603 remains separate real-browser evidence;
- documented binding lookup/driver-registry/action-availability scenarios remain not executable through T-701 v1.

Do not claim full Trust/Output/Projection conformance.

- [ ] **Step 2: Extend the existing browser CI job without adding a job**

After `Run browser-runtime tests`, add:

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

Do not install Python requirements in the browser job because the runner tests and runtime runner are stdlib-only. The separate `contract` job continues to install `requirements-dev.txt` for `jsonschema` and run `scripts/validate.py`.

- [ ] **Step 3: Run the complete local verification set**

From repo root:

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

Expected: no failures/errors; full canonical run is `7 PASS / 1 NOT_APPLICABLE`; filtered runs include only requested target/scenario while preserving deterministic order.

- [ ] **Step 4: Verify no dependency drift**

Run:

```bash
git diff -- packages/browser-runtime/package-lock.json requirements-dev.txt
```

Expected: empty. If non-empty solely because an unnecessary type/test dependency was introduced, remove it before proceeding.

- [ ] **Step 5: Verify production browser source was not changed**

Run:

```bash
git diff 05b020c94279c295068895a8163a892549327d3f...HEAD -- packages/browser-runtime/src
```

Expected: empty. Any required semantic production change reopens the design gate.

- [ ] **Step 6: Commit CI/docs integration**

```bash
git add .github/workflows/validate.yml CONFORMANCE.md
git commit -m "ci(conformance): run canonical browser matrix"
```

- [ ] **Step 7: Verify the resulting GitHub Actions run**

Wait only for the current push-triggered run already created by the commit; inspect it rather than promising future/background work. Required evidence before the implementation can be described as verified:

```text
7 workflow jobs total
7/7 success
browser job includes typecheck + Vitest + runner unit tests + harness build + canonical runner
contract job includes scripts/validate.py success
```

Record the exact workflow run ID and exact implementation head SHA.

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

Run:

```bash
git diff --stat 05b020c94279c295068895a8163a892549327d3f...HEAD
git diff --name-only 05b020c94279c295068895a8163a892549327d3f...HEAD
```

Check line-by-line that changes are limited to the planned runner, conformance metadata/manifests/docs, browser conformance test infrastructure, package script, and workflow. Confirm no Laravel production source, T-603 fixture behavior, standalone spec extraction, T-702/T-703/T-704 implementation, or dependency expansion appeared.

- [ ] **Step 2: Re-run fresh implementation verification on the exact review-prep head before claiming completion**

Run:

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

- [ ] **Step 3: Update `TASKS.md` with implementation evidence but keep review/merge state honest**

Record:

- design and plan paths;
- implementation head SHA;
- Python runner test result;
- browser test/typecheck result;
- canonical matrix `7 PASS / 1 N/A`;
- validation workflow run ID/result;
- D-059/D-060/D-061 still `PROPOSED`;
- T-701 state as implementation-complete/verification-complete but **not** reviewed/merged unless those later gates actually occur.

- [ ] **Step 4: Update `STATUS.md` with exact active boundary**

State that the next gate is external review of T-701, not T-702. Preserve all existing M6 historical evidence.

Under `Needs decision`, keep:

```text
D-059 — PROPOSED
D-060 — PROPOSED
D-061 — PROPOSED
D-026 — PROPOSED and unchanged by T-701
```

- [ ] **Step 5: Replace `REVIEW_REQUEST.md` with a T-701 review handoff**

Include:

- scope and non-goals;
- exact implementation head;
- exact changed-file list summary;
- TDD/verification evidence;
- canonical `7 PASS / 1 N/A` matrix;
- explicit review questions around runner authority, process protocol, capability applicability, advisory error-code behavior, no-retarget evidence, and scope containment;
- statement that decision promotion and merge require later explicit gates.

- [ ] **Step 6: Confirm proposed decisions were not prematurely promoted**

Run:

```bash
grep -E '\| D-0(26|59|60|61) \|' docs/DECISION-REGISTER.md
```

Expected: D-026, D-059, D-060, D-061 all show `PROPOSED` at review handoff.

- [ ] **Step 7: Commit review-prep tracking**

```bash
git add TASKS.md STATUS.md REVIEW_REQUEST.md
git commit -m "docs(conformance): prepare T-701 external review"
```

- [ ] **Step 8: Run/inspect fresh CI for the exact review-prep commit and stop**

Required before reporting the gate ready:

```text
validate workflow: 7/7 green on exact review-prep SHA
canonical runner: 7 PASS / 1 N/A
D-059/D-060/D-061: still PROPOSED
```

Stop here. Do not create/merge a PR, accept architecture decisions, or begin T-702 without the next explicit user gate.

---

## Final Plan Verification Checklist

Before implementation is called review-ready, the executor must be able to point to evidence for every item below:

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
