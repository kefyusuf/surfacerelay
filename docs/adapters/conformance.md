# Adapter Conformance and Evidence

> SurfaceRelay conformance is currently a repository-local project contract. It is not a public certification program, compatibility badge, remote target protocol, or standalone adapter SDK.

Canonical runtime scenario semantics live in [`../../spec/0.1/fixtures/conformance-scenarios.json`](../../spec/0.1/fixtures/conformance-scenarios.json). The runner owns scenario selection, applicability, and verdicts; a harness emits bounded raw observations only.

Primary references:

- [SurfaceRelay conformance overview](../../CONFORMANCE.md)
- [Repo-local executable conformance protocol](../../conformance/README.md)
- [Decision Register](../DECISION-REGISTER.md)
- [Canonical conformance scenarios](../../spec/0.1/fixtures/conformance-scenarios.json)

## 1. Authority model

T-701 established the current repo-local executable conformance boundary under D-059, D-060, and D-061.

The author-facing model is:

```text
profile
= atomic conformance claim boundary

capability
= declared functionality/environment property used for applicability

scenario applicability
= runner decision

PASS / FAIL / ERROR / NOT_APPLICABLE
= runner-owned result semantics
```

The target and harness do not own the mandatory scenario set, applicability, or verdict.

## 2. Current implemented v1 facts

The implemented and accepted T-701 v1 claim is intentionally narrow:

```text
profile: runtime-binding/driver

reference target: browser/livewire
  capabilities:
    - lifecycle.component

reference target: browser/htmx
  additional capabilities:
    - none
```

These names describe the current repository-local implementation. T-702 does not add another profile, capability, target, or canonical scenario.

The canonical executable runtime scenarios currently in this profile are documented in [CONFORMANCE.md](../../CONFORMANCE.md). The two reference targets produce a current canonical matrix of:

```text
7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR
```

The HTMX `NOT_APPLICABLE` result for the component-lifecycle scenario is computed by the runner because HTMX does not claim `lifecycle.component`. The HTMX harness does not self-report that result.

## 3. Capability declarations are obligations, not escape hatches

An adapter target must declare only functionality it actually provides.

Rules for authors:

1. A target declares only capabilities it actually implements.
2. Declaring a capability may add mandatory applicable scenarios.
3. A capability declaration does not prove correctness by itself.
4. A target or harness cannot enumerate a private subset of mandatory profile scenarios.
5. A harness cannot return `NOT_APPLICABLE` to suppress an applicable scenario.
6. If the runner selected an applicable mandatory scenario and the harness cannot produce a trustworthy observation, that is an infrastructure/protocol `ERROR`, not local N/A.
7. Every executable claimed profile requires its mandatory positive control.

This keeps applicability and verdict authority centralized instead of allowing each adapter to redefine what passing means.

## 4. Runner/harness responsibility boundary

```text
adapter / production runtime
            │
            ▼
        thin harness
            │
      raw observations
            │
            ▼
          runner
            │
 applicability + verdict
```

### Harness responsibilities

A harness may:

- prepare the minimum controlled environment needed by the target;
- invoke the production boundary selected by the scenario;
- capture only the bounded raw facts defined by the current process protocol;
- emit exactly the required protocol response;
- use stderr for diagnostics.

A harness must not:

- choose scenarios;
- receive expected answers merely to self-validate;
- skip a runner-selected mandatory scenario;
- emit `NOT_APPLICABLE` after selection;
- emit `passed`, `conformant`, `failClosed`, `retargetPrevented`, or equivalent interpretation booleans;
- self-report `PASS`, `FAIL`, `ERROR`, or any other runner verdict.

## 5. Current repo-local process protocol

D-060 fixes the current T-701 execution boundary to one scenario per fresh process:

```text
one target/scenario pair = one fresh subprocess
stdin  = one JSON request document
stdout = exactly one JSON protocol response
stderr = diagnostics only
timeout = 10 seconds
```

The manifest command is an argv array executed by the repo-local Python runner with `shell=False` from repository root. The Python orchestrator does not import the runtime implementation.

This process protocol is current repository infrastructure. T-702 does not promote it into a stable public remote-target SDK/protocol.

## 6. Bounded raw observation vocabulary

The current process protocol uses raw facts rather than semantic conclusions:

```text
termination: returned | threw
errorCode?: string
frameworkDispatchCount: non-negative integer
replacementDispatchCount?: non-negative integer
```

The runner compares those facts with the canonical scenario expectation.

`recommendedCode` remains advisory. Under the current T-701 model, a different or absent raw `errorCode` does not independently fail a scenario when the structured normative expectation matches.

This preserves the D-026 boundary: provisional binding failure-code names are not promoted into a global normative enum.

## 7. Scenario selection and applicability

The runner derives its work from:

1. canonical executable runtime scenarios;
2. profiles claimed by a target;
3. capabilities claimed by that target.

A target manifest cannot define an alternate canonical scenario registry or allowlist.

Conceptually:

```text
canonical scenarios
      +
target profile claims
      +
target capability claims
      ↓
runner applicability decision
      ↓
applicable scenario → spawn harness
missing required capability → runner-owned NOT_APPLICABLE
```

The harness only sees work after applicability has been determined.

## 8. Verified evidence is revision-bounded

Conformance evidence is meaningful for the code/configuration revision that produced it.

Record at least:

```text
repository/adapter revision
claimed profile
declared capabilities
runner/repository revision
scenario result summary
host framework/protocol version support
```

A material adapter change requires fresh evidence before repeating the same verified claim.

Examples of material changes include:

- target resolution logic;
- lifecycle/expiry handling;
- input mapping;
- cancellation frontier behavior;
- capability support;
- host framework version changes that alter adapter behavior;
- harness wiring changes that affect the production boundary being exercised.

T-702 does not introduce cryptographic attestation, signing, a remote evidence store, or a certification authority.

## 9. Bounded compatibility wording

Avoid vague claims such as:

```text
fully SurfaceRelay compatible
```

when executable evidence covers only a bounded profile/target/capability set.

Preferred form:

```text
Conforms to SurfaceRelay profile `runtime-binding/driver`
for target `<target-id>` under the capabilities declared by that target,
verified by the repository conformance runner at the recorded revision.
```

A good claim should let a reviewer answer:

- Which profile?
- Which target?
- Which capabilities?
- Which repository/adapter revision?
- Which runner revision?
- What was the result summary?

If any of those are intentionally omitted, do not imply broader evidence than was actually produced.

## 10. Version dimensions are independent

Do not collapse these into one generic “compatible version” value:

| Dimension | Meaning |
|---|---|
| SurfaceRelay contract/spec revision | Canonical schema/semantic revision the adapter is built against. |
| Adapter/package version | Version of the adapter implementation itself. |
| Claimed conformance profile | Atomic behavior claim under test, currently `runtime-binding/driver` in T-701 v1. |
| Conformance runner/repository revision | Exact executable evaluator/scenario wiring revision that produced evidence. |
| Host framework/protocol version support | Framework or protocol versions the adapter actually supports. |

T-702 introduces no version negotiation, compatibility solver, release-matrix service, badge system, registry, or automatic support inference.

## 11. Failure/error categories without a new enum

Authors should distinguish failure behavior conceptually:

```text
unsupported adapter/host behavior
invalid or stale binding/target state
unavailable capability
invalid trusted-context preconditions
host/runtime execution failure
cancellation/timeout at a defined boundary
protocol/normalization failure in a harness or projection
```

Where an existing contract defines exact result/error semantics, use that contract.

Where vocabulary is extensible or provisional, do not invent or freeze a new global name through adapter documentation.

**D-026 remains PROPOSED.** D-030 keeps `ActionError.code` extensible rather than a closed global enum.

## 12. Current repository-local reference verification

For the current repository reference targets, build the browser harnesses first:

```bash
cd packages/browser-runtime
npm run conformance:build
cd ../..
```

Then run the canonical matrix:

```bash
python scripts/run_conformance.py
```

Current expected summary:

```text
7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR
```

These commands describe the current repo-local reference verification path. They are not a promise of a stable external harness CLI or remote execution API.

## 13. Evidence review checklist

Before publishing an adapter conformance claim, verify:

- [ ] The claimed profile already exists in canonical/current conformance sources.
- [ ] The target declares only capabilities it truly implements.
- [ ] The target/harness does not carry a private mandatory-scenario allowlist.
- [ ] The runner, not the harness, determined applicability.
- [ ] The harness emitted raw observations only.
- [ ] Every runner-selected applicable mandatory scenario executed successfully enough to produce a trustworthy observation.
- [ ] `PASS`/`FAIL`/`ERROR`/`NOT_APPLICABLE` came from the runner.
- [ ] The claim includes the adapter/repository revision and runner/repository revision.
- [ ] The claim is bounded to the actual profile/target/capabilities tested.
- [ ] Host framework/protocol version support is documented separately from the SurfaceRelay profile claim.
- [ ] The wording does not imply public certification, a compatibility badge, or global compatibility.
- [ ] No D-026 provisional error name has been promoted into a new normative global enum.

## 14. Stop and open a new gate

Do not extend T-702 documentation to create:

- a new profile or capability;
- a new canonical conformance scenario;
- changed applicability logic;
- changed runner verdict semantics;
- harness-owned pass/fail/N/A logic;
- a remote target protocol;
- certification, badges, signing, or compatibility registry infrastructure.

Those are conformance architecture changes and require a separate task/decision gate.
