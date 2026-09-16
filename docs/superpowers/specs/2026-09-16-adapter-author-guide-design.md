# T-702 — Adapter Author Guide Design

**Status:** APPROVED DESIGN / IMPLEMENTATION NOT STARTED  
**Task:** T-702  
**Milestone:** M7 — Conformance / Ecosystem Bridges  
**Branch:** `feat/t-702-adapter-author-guide`  
**Approved:** 2026-09-16  

## 1. Purpose

T-702 turns SurfaceRelay's already-accepted architecture, trust boundaries, runtime-binding rules, projection separation, and executable conformance model into an author-facing guide for maintainers who need to add a new integration without redefining core semantics.

The guide is intended to answer, from one coherent entry point:

- what kind of adapter is being authored;
- which SurfaceRelay layer owns each responsibility;
- which existing contracts are normative;
- how trusted runtime context differs from caller input;
- how profiles and capabilities are claimed truthfully;
- how an adapter connects to executable conformance without controlling scenario selection or verdicts;
- which unsupported or ambiguous conditions must fail closed;
- when an author may claim verified conformance.

T-702 is documentation and integration guidance. It does not introduce a new adapter SDK, runtime abstraction, protocol, capability, error enum, or certification program.

## 2. Problem

SurfaceRelay now has enough implemented and reviewed behavior to support third-party integration work, but the knowledge is distributed across canonical contracts, decision records, runtime code, conformance fixtures, implementation specs, and task-specific evidence.

A maintainer can currently infer the correct architecture, but must reconstruct it from multiple places. That creates avoidable risks:

- collapsing Action Definition, Runtime Binding, and projection responsibilities;
- treating caller-provided identifiers as trusted authority;
- using framework discovery as invocation authorization;
- silently retargeting stale runtime bindings;
- advertising capabilities that are not actually implemented;
- letting a harness self-select scenarios or self-report PASS/NOT_APPLICABLE;
- copying examples as if they were new normative contracts;
- widening core behavior while attempting to add an adapter.

T-702 provides a bounded author path that prevents those failure modes without creating a second specification.

## 3. Architectural constraints inherited from accepted decisions

The guide must preserve, not reinterpret, accepted project decisions. In particular:

- D-002: Action Definition and Runtime Binding are separate contracts.
- D-004: core semantics are protocol-neutral; protocol annotations are projections.
- D-005: browser protocol changes remain isolated behind an adapter.
- D-006: SurfaceRelay does not implement an MCP transport stack from scratch.
- D-007: caller input cannot manufacture actor, tenant, selection, confirmation, or binding authority.
- D-010: action exposure is explicit; there is no expose-all convention.
- D-011: discovery permission does not imply invocation permission.
- D-012: OpenAPI is a secondary/import adapter, not the core product.
- D-016: binding drivers are explicit registry entries; unknown drivers fail closed.
- D-022 through D-025: bindings identify exact issued instances/targets and stale bindings do not silently retarget.
- D-027 through D-032: trusted context, authorization, result semantics, and output dimensions remain explicit and separated.
- D-035 through D-043: browser driver resolution, invocation mapping, registration lifetime, exact-target execution, and cancellation boundaries remain explicit and fail closed.
- D-048 through D-052: framework-specific trusted UI context augments the existing execution path and never becomes caller authority.
- D-053 through D-058: HTMX and Livewire keep materially different driver-owned semantics while satisfying shared tested behavior.
- D-059 through D-061: conformance uses profile + capability; the runner owns canonical scenario selection, applicability, and verdicts; harnesses emit bounded raw observations only; target wiring remains outside canonical scenario semantics.

D-026 remains PROPOSED. The guide must not promote its provisional binding failure-code names into a global normative error enum.

## 4. Design principle: the guide is not a shadow specification

T-702 introduces the following authority rule:

```text
canonical contracts / accepted decisions / executable conformance semantics
                              │
                              ▼
                    Adapter Author Guide
                              │
                              ▼
                     examples / recipes
```

The guide may:

- explain existing contracts;
- connect concepts that currently live in separate documents;
- provide author workflows and checklists;
- show correct, incorrect, and dangerous integration examples;
- point authors to canonical sources and executable verification.

The guide must not:

- create new protocol requirements;
- define a new capability vocabulary;
- redefine RuntimeBinding or ActionDefinition semantics;
- create an alternate scenario registry;
- override the conformance runner's applicability or verdict rules;
- turn illustrative examples into normative contracts;
- silently promote a proposed decision.

If guide prose or an example conflicts with a canonical contract, accepted decision, or executable canonical conformance semantics, the canonical artifact wins.

This authority boundary is recorded as D-062 in PROPOSED state during T-702 design.

## 5. Target audience

The primary audience is a maintainer implementing one of the following integration boundaries:

1. a runtime/binding adapter for a host framework or execution environment;
2. a projection adapter that exposes SurfaceRelay actions through another protocol or tool surface;
3. an import/definition adapter that converts an external description into SurfaceRelay-neutral action definitions.

Secondary readers include reviewers who need to verify that a proposed adapter has not widened trust or bypassed conformance boundaries.

The guide is not an end-user product tutorial and does not teach the host framework itself.

## 6. Author-facing adapter taxonomy

T-702 documents three responsibility categories. These are author-facing categories, not new core interfaces.

### 6.1 Definition / import adapter

Conceptual flow:

```text
external description
        │
        ▼
import adapter
        │
        ▼
SurfaceRelay-neutral Action Definition
```

Typical future example: an optional OpenAPI importer.

Responsibilities:

- translate supported external definition data into existing Action Definition semantics;
- reject unsupported or ambiguous mappings;
- preserve protocol neutrality in the resulting Action Definition.

Must not:

- manufacture trusted actor/tenant/UI authority;
- embed runtime target authority into Action Definition;
- make imported metadata implicitly trusted;
- redefine core Action semantics.

### 6.2 Runtime / binding adapter

Conceptual flow:

```text
Action + RuntimeBinding
          │
          ▼
framework/runtime BindingDriver
          │
          ▼
exact host target
```

Livewire and HTMX are current concrete reference examples.

Responsibilities:

- validate the driver-owned target shape;
- map validated Action input to the host's supported invocation mechanism;
- resolve and execute the exact issued target;
- honor lifecycle, expiry, cancellation, and no-retarget invariants already defined by the contracts;
- expose only host capabilities that are truly available.

Must not:

- rediscover a similar replacement target when the exact target is stale;
- invent runtime authority from caller payload or generic metadata;
- bypass the normal authorization/confirmation pipeline;
- reinterpret unsupported host behavior as success.

### 6.3 Surface / projection adapter

Conceptual flow:

```text
SurfaceRelay Action
        │
        ▼
projection adapter
        │
        ▼
external protocol/tool representation
```

Existing WebMCP annotation projection is an architectural precedent. T-703 will later address Laravel MCP projection separately.

Responsibilities:

- project existing neutral semantics into supported protocol fields;
- keep transport/protocol details outside core action semantics;
- preserve explicit exposure rules.

Must not:

- treat discovery as authorization;
- create a second business execution path;
- infer authority or risk semantics not present in the canonical model;
- bypass ActionBus/runtime invocation controls.

## 7. Taxonomy does not create a new plugin architecture

The three categories in section 6 exist only to help an author locate responsibilities.

T-702 does not introduce:

- `Adapter` as a new universal public interface;
- a plugin loader;
- dynamic package discovery;
- a common adapter lifecycle abstraction;
- an adapter registry spanning import, binding, and projection roles.

If later implementation work demonstrates a need for a new shared abstraction, that requires its own task and decision gate.

## 8. Responsibility matrix

The guide must include a concise matrix equivalent to the following:

| Concern | Canonical/core/runner authority | Adapter/harness responsibility |
|---|---|---|
| Action semantics | canonical contracts | translate without redefining |
| Runtime target shape | binding contract + driver-owned contract | validate exact host-specific shape |
| Caller business input | validation pipeline | map only validated input |
| Trusted actor/tenant/UI context | trusted runtime services/context | consume; never manufacture from caller input |
| Discovery/exposure | explicit exposure rules | project only explicitly exposed actions |
| Invocation authorization | normal invocation pipeline | must not bypass |
| Scenario selection | conformance runner | none |
| Capability advertisement | runner validates/uses claim | declare only real capabilities |
| Applicability | conformance runner | none |
| PASS/FAIL verdict | conformance runner | none |
| Raw runtime observation | runner consumes | harness captures bounded facts |
| Unsupported ambiguity | fail-closed contract | report/reject explicitly |

The exact wording may evolve during documentation implementation, but the ownership boundary must not change.

## 9. Minimal author workflow

The main guide must lead an author through this sequence:

```text
1. Identify the integration boundary.
2. Classify the role: import, runtime/binding, projection, or a package containing clearly separated roles.
3. Read the canonical contracts and accepted decisions relevant to that role.
4. Identify the existing profile, if any, that the adapter intends to claim.
5. Determine the capabilities the implementation actually provides.
6. Implement host/protocol translation without widening authority.
7. Preserve exact-target, trusted-context, exposure, authorization, and fail-closed rules.
8. Normalize only what the existing contracts require; keep host-owned semantics host-owned.
9. Wire a conformance harness when an executable profile exists.
10. Run every runner-selected applicable mandatory scenario.
11. Fix FAIL/ERROR results instead of suppressing scenarios or changing applicability locally.
12. Publish only claims supported by the relevant executable evidence.
```

The workflow is instructional. It does not create a new release or certification process.

## 10. Trust boundary guidance is mandatory, not an appendix

Security/trust behavior is part of adapter correctness and must be prominent in the guide.

### 10.1 Explicit exposure

Default behavior remains not exposed. An adapter must not scan arbitrary application behavior and expose everything by convention.

### 10.2 Discovery is not invocation authorization

An external protocol being able to discover or describe an action does not grant invocation authority. Invocation must still traverse the existing trusted runtime and authorization path.

### 10.3 Caller input is not trusted runtime context

Caller-provided fields such as tenant IDs, actor IDs, record IDs, selections, confirmation flags/tokens outside their defined receipt semantics, or host-target identifiers cannot become trusted context merely because an adapter can parse them.

Trusted context must come from the established runtime authority source for that context type.

### 10.4 Adapter must not widen authority

An adapter translates between SurfaceRelay and a host/protocol. Translation must not add authorization shortcuts, implicit trust, fallback identities, replacement target selection, or hidden execution paths.

### 10.5 Fail closed

Unknown, missing, stale, ambiguous, unsupported, or contradictory authority/target/mapping state must be rejected rather than guessed.

This section must reference canonical decisions instead of inventing adapter-local security rules.

## 11. Runtime/binding author guidance

For runtime/binding authors, the guide must explain the following invariants:

- a RuntimeBinding references an exact Action identity/version and one exact issued binding instance;
- the binding driver identifier resolves only through explicit registry support;
- the driver-owned target is validated by that driver;
- execution must resolve the exact target, not a similar target discovered later;
- lifecycle and expiry checks remain cumulative validity conditions;
- stale exact targets do not silently retarget;
- Action input mapping must be deterministic and bounded by the driver/host contract;
- unsupported mapping or target states fail closed;
- framework dispatch boundaries and cancellation guarantees must be stated precisely rather than overstated.

The guide may use Livewire and HTMX as contrasting examples, but neither framework becomes the universal adapter shape.

## 12. Projection author guidance

For projection authors, the guide must explain:

- projection is representation, not new core semantics;
- external tool identity must derive deterministically from existing Action identity rules when such a projection is defined;
- protocol annotations may project existing effect/risk/content-trust semantics only according to their accepted mapping;
- protocol discovery remains separate from invocation permission;
- projection must not create agent-only business methods or bypass the canonical invocation path;
- unsupported protocol fields should not be populated by speculative inference.

T-702 may reference existing WebMCP projection behavior. It does not implement T-703 MCP support.

## 13. Import/definition author guidance

For import authors, the guide must explain:

- imported external descriptions are input to translation, not trusted runtime authority;
- only semantics representable by existing Action Definition contracts may be produced;
- ambiguous or lossy mappings that would change meaning must be rejected or explicitly left unsupported;
- runtime binding information must not be smuggled into Action Definition;
- host/protocol-specific metadata must remain secondary and non-authoritative unless an existing canonical field explicitly owns it.

T-702 does not define the OpenAPI mapping rules planned for T-704.

## 14. Profiles, capabilities, and truthful claims

The guide must carry T-701's accepted model forward exactly:

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

Rules for authors:

1. A target declares only capabilities it actually provides.
2. Capability declarations may increase obligations; they are not escape hatches.
3. The target/harness cannot enumerate a private subset of mandatory profile scenarios.
4. The harness cannot return `NOT_APPLICABLE` to suppress an applicable scenario.
5. If the runner selects an applicable mandatory scenario and the harness cannot execute it, that is an infrastructure/error condition, not local N/A.
6. A profile claim must include its mandatory positive control.

T-702 does not add a new profile or capability.

## 15. Conformance harness boundary

The guide must describe the T-701 boundary in author terms:

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

Harness responsibilities:

- prepare the minimum controlled environment needed by the target;
- invoke the production boundary selected by the scenario;
- capture only bounded raw observations defined by the current process protocol;
- write the required protocol response and diagnostics in the established channels.

Harnesses must not:

- choose scenarios;
- receive expected answers merely to self-validate;
- emit `passed`, `conformant`, `failClosed`, or equivalent interpretive booleans;
- self-report runner verdicts;
- turn missing support into `NOT_APPLICABLE` after the runner selected the scenario.

## 16. Verified conformance claims

The guide may describe a verified claim in the following conceptual form:

```text
adapter/target
  └── claimed profile
       ├── declared capabilities
       ├── runner-selected applicable scenarios
       └── executable results
```

Authors must not use vague wording such as `fully SurfaceRelay compatible` when the evidence only covers a bounded profile.

The preferred wording is bounded, for example:

```text
Conforms to SurfaceRelay profile `runtime-binding/driver`
for target `<target-id>` under the capabilities declared by that target,
verified by the repository conformance runner at the recorded revision.
```

This is documentation guidance, not a formal certification trademark, badge program, signing system, or compatibility registry.

## 17. Evidence and revision scope

Conformance evidence is meaningful only for the exact code/configuration revision that produced it.

The guide must teach authors to record at least:

- repository/adapter revision;
- claimed profile;
- declared capabilities;
- runner version/revision used;
- scenario result summary.

A later material adapter change requires fresh evidence before repeating the same verified claim.

T-702 does not introduce cryptographic attestation, remote certification, or a new evidence store.

## 18. Failure/error guidance

T-702 must explain categories of adapter failure behavior without defining a new global error enum.

Authors need to distinguish conceptually between:

- unsupported adapter/host behavior;
- invalid or stale binding/target state;
- unavailable capability;
- invalid trusted-context preconditions;
- host/runtime execution failure;
- cancellation/timeout at a defined boundary;
- protocol/normalization failure in a harness or projection.

Where existing contracts define exact error/result semantics, the guide references them. Where vocabulary remains extensible or provisional, the guide must not freeze new names.

D-026 therefore remains independent and PROPOSED.

## 19. Correct / incorrect / dangerous examples policy

Examples are valuable because many adapter failures are responsibility-boundary failures rather than syntax errors.

The guide should use three labeled example forms where useful:

### Correct

```text
host capability is not implemented
→ do not advertise the capability
→ allow the runner to compute applicability
```

### Incorrect

```text
host capability is not implemented
→ advertise it anyway
→ skip failing mandatory scenarios in the harness
```

### Dangerous

```text
caller sends tenant_id
→ adapter copies tenant_id into trusted runtime context
```

Every example section must be clearly illustrative/non-normative and point back to the owning canonical rule.

## 20. Examples must not become a universal host shape

Livewire and HTMX are intentionally materially different. The guide must avoid teaching their current implementation details as mandatory structure for all future adapters.

Examples should therefore separate:

```text
portable invariant
from
reference implementation technique
```

For example:

```text
portable invariant:
  exact issued target must not silently retarget

Livewire technique:
  exact component identity via supported Livewire API

HTMX technique:
  exact rendered source identity + declarative request contract
```

Future adapters may satisfy the invariant differently while remaining conformant.

## 21. Versioning and compatibility guidance

The guide must distinguish these concepts:

- SurfaceRelay contract/spec revision;
- adapter/package version;
- claimed conformance profile;
- conformance runner/repository revision;
- host framework/protocol version support.

These are not interchangeable.

T-702 may provide a checklist for documenting supported combinations, but does not introduce version negotiation, compatibility solving, or a release matrix service.

## 22. Proposed documentation information architecture

Implementation of T-702 should prefer small navigable author documents rather than one oversized page.

Proposed output shape:

```text
docs/adapters/
├── README.md
├── author-guide.md
├── conformance.md
└── security.md
```

Responsibilities:

- `docs/adapters/README.md` — entry point, role taxonomy, navigation, canonical-source warning;
- `docs/adapters/author-guide.md` — end-to-end author workflow, responsibility boundaries, runtime/projection/import guidance;
- `docs/adapters/conformance.md` — profile/capability claims, harness boundary, evidence, verified-claim wording;
- `docs/adapters/security.md` — exposure, discovery-vs-invocation, trusted context, authority widening, fail-closed checklist.

This is the preferred design target, not implementation completed by this design commit.

If implementation reveals that fewer files produce a clearer guide without losing these semantic sections, file count may be adjusted without changing the approved scope.

## 23. Canonical references expected from the guide

The implementation should link readers to the relevant canonical/current sources rather than duplicate them wholesale, including as appropriate:

- `spec/0.1/**` contracts and conformance fixtures;
- `CONFORMANCE.md`;
- `docs/DECISION-REGISTER.md`;
- `docs/GLOSSARY.md`;
- current RuntimeBinding/Action Definition documentation;
- relevant browser-runtime/Laravel reference docs when used as examples;
- T-701 executable conformance design and implemented runner instructions.

The guide should prefer stable repository-relative links.

## 24. Documentation validation requirements

T-702 implementation must be reviewable as a documentation-only task unless a separate gate explicitly expands scope.

At minimum, verification should prove:

- every repository-relative link added by the guide resolves;
- referenced profile/scenario/capability names exactly match canonical sources;
- no guide statement contradicts D-059/D-060/D-061 runner authority;
- no guide statement promotes D-026;
- no guide statement claims T-703 or T-704 implementation exists;
- no production code, canonical runtime semantics, or conformance verdict logic changes as a side effect of writing the guide.

Existing repository validation may be run to prove the documentation change did not break structural checks, but T-702 does not require new production tests merely to create prose.

## 25. Acceptance criteria

T-702 implementation is eligible for review only when all of the following are true:

- [ ] The author guide clearly locates adapters within existing SurfaceRelay architecture.
- [ ] Definition/import, runtime/binding, and projection roles are distinguished without creating new core interfaces.
- [ ] Core/runner authority versus adapter/harness responsibility is explicit.
- [ ] The guide states that it is explanatory and non-authoritative relative to canonical contracts/accepted decisions/executable conformance semantics.
- [ ] Profile and capability semantics match D-059 through D-061 exactly.
- [ ] Scenario selection, applicability, and PASS/FAIL authority remain runner-owned.
- [ ] Harnesses are limited to bounded raw observations.
- [ ] Truthful capability advertisement is required.
- [ ] Explicit exposure is documented.
- [ ] Discovery permission is separated from invocation authorization.
- [ ] Caller input is separated from trusted runtime context.
- [ ] Authority widening, silent retargeting, and ambiguous fallback behavior are explicitly prohibited.
- [ ] Fail-closed behavior is explained using existing contracts.
- [ ] A conformance author workflow is documented from profile selection through evidence recording.
- [ ] Verified claims are bounded to an exact profile/target/capability/revision rather than vague global compatibility.
- [ ] Correct / incorrect / dangerous examples are clearly non-normative.
- [ ] Livewire/HTMX examples distinguish portable invariants from framework-specific techniques.
- [ ] Versioning concepts are separated without introducing version negotiation.
- [ ] Canonical links are valid and repository-relative where practical.
- [ ] D-026 remains PROPOSED and no new error enum is created.
- [ ] T-703 and T-704 remain unimplemented.
- [ ] No production code or canonical conformance semantics are changed by T-702 without a separate explicit gate.

## 26. Explicit non-goals

T-702 does not implement or define:

- a new adapter SDK;
- a universal `Adapter` interface;
- adapter scaffolding/generator CLI;
- plugin discovery or loading;
- a new RuntimeBinding or BindingDriver abstraction;
- a new Action Definition field;
- a new profile or capability;
- new canonical conformance scenarios;
- changes to T-701 runner selection/applicability/verdict logic;
- a new global failure-code enum;
- promotion of D-026;
- Laravel MCP projection (T-703);
- OpenAPI import mapping (T-704);
- adapter marketplace/registry;
- compatibility badge/certification authority;
- remote conformance execution;
- signing/attestation infrastructure;
- changes to Livewire/HTMX production semantics;
- standalone public-spec extraction.

## 27. Expected implementation boundary

The later T-702 implementation gate is expected to touch documentation only, primarily under `docs/adapters/**`, plus bounded navigation/tracking updates if needed.

It should not require semantic changes under:

```text
packages/browser-runtime/src/**
packages/laravel/src/**
spec/0.1/**
conformance/targets/**
scripts/conformance_model.py
scripts/run_conformance.py
```

If implementation appears to require changes in those paths, work stops and a new scope/decision gate is required before proceeding.

## 28. Decision record introduced by this design

### D-062 — Adapter Author Guide authority boundary — PROPOSED

> The Adapter Author Guide is an author-facing explanation of existing canonical SurfaceRelay contracts, accepted decisions, security/trust invariants, and executable conformance semantics. It is not an independent specification or contract source. Guide prose, examples, and recipes may explain but may not override or silently extend canonical behavior; where they conflict, the canonical artifact governs. New semantics discovered while authoring the guide require a separate task/decision gate rather than being introduced through documentation.

D-062 remains PROPOSED during design and documentation implementation. Promotion, if appropriate, requires later review/decision evidence and is not automatic.

## 29. Design risks and mitigations

### Risk: duplicated specification prose drifts

Mitigation: link to canonical sources, summarize ownership rules, and avoid copying full contract definitions.

### Risk: examples accidentally become normative

Mitigation: label examples as illustrative/non-normative and state the owning invariant separately.

### Risk: guide overfits Livewire/HTMX

Mitigation: teach portable invariants first and framework techniques second.

### Risk: adapter authors bypass runner authority

Mitigation: dedicate a conformance section to target claims, capability truthfulness, runner-owned applicability, and raw-observation-only harnesses.

### Risk: documentation work smuggles architecture changes

Mitigation: D-062 + explicit implementation path boundary; any new semantic need stops T-702 and opens a separate gate.

## 30. Approval and next gate

This design is approved as the T-702 scope/design contract.

Current state after recording this design:

```text
T-701: DONE / REVIEWED / MERGED / MAIN REVALIDATED
T-702: DESIGN APPROVED / IMPLEMENTATION NOT STARTED
D-062: PROPOSED
T-703: TODO / NOT STARTED
T-704: TODO / NOT STARTED
```

No Adapter Author Guide implementation starts from this design commit automatically.

The next explicit user gate may authorize a T-702 implementation plan and/or documentation implementation. Until then, only this design, the proposed D-062 record, and task/status tracking are in scope.
