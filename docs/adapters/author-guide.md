# Adapter Author Guide

> This guide explains how to integrate with existing SurfaceRelay contracts. It is not a second specification, adapter SDK, or plugin API. Canonical contracts, accepted decisions, and executable conformance semantics take precedence over this prose and all examples.

This guide is for maintainers implementing an integration boundary. It does not teach the host framework itself.

Start with:

- [Adapter authoring entry point](README.md)
- [Security and trust boundaries](security.md)
- [Conformance and evidence](conformance.md)
- [Architecture](../ARCHITECTURE.md)
- [Decision Register](../DECISION-REGISTER.md)
- [Action Definition schema](../../spec/0.1/action-definition.schema.json)
- [Runtime Binding schema](../../spec/0.1/runtime-binding.schema.json)

## 1. First decide which boundary you are adding

SurfaceRelay separates stable application semantics from runtime execution and external presentation. An adapter should extend one of those boundaries without collapsing them together.

The three author-facing categories below are organizational guidance. They do **not** create a universal `Adapter` interface, plugin loader, lifecycle abstraction, or cross-role registry.

### 1.1 Definition / import adapter

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

Use this role when translating an external description into existing SurfaceRelay Action Definition semantics.

Responsibilities:

- translate only semantics representable by the existing Action Definition contract;
- preserve protocol neutrality in the resulting definition;
- reject unsupported or ambiguous mappings that would change meaning;
- keep imported metadata non-authoritative unless an existing canonical field explicitly owns that meaning.

Must not:

- manufacture actor, tenant, UI, confirmation, or binding authority;
- place runtime target identity into Action Definition;
- make imported metadata implicitly trusted;
- invent a new Action semantic because the external format happens to contain it.

T-704 is expected to address optional OpenAPI importing later. **T-702 does not define OpenAPI mapping rules and does not implement T-704.**

### 1.2 Runtime / binding adapter

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

Use this role when SurfaceRelay needs to execute an already-defined Action through a host framework/runtime target.

Responsibilities:

- validate the driver-owned target shape;
- map validated Action input deterministically into the host invocation mechanism;
- resolve and execute the exact issued target;
- preserve lifecycle, expiry, revocation, exact Action-version, and no-retarget semantics;
- state the host's real cancellation/dispatch boundary precisely;
- advertise only host capabilities that are actually implemented.

Must not:

- rediscover a similar replacement target when an exact target is stale;
- turn ordinary caller input into runtime authority;
- bypass the existing trusted invocation pipeline;
- reinterpret unsupported host behavior as success;
- generalize Livewire- or HTMX-specific mechanics into a universal target model.

### 1.3 Surface / projection adapter

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

Use this role when presenting existing SurfaceRelay semantics through another protocol or tool surface.

Responsibilities:

- project existing protocol-neutral Action semantics into supported external fields;
- preserve explicit exposure rules;
- derive external identity only through an accepted deterministic mapping where one exists;
- keep transport/protocol compatibility logic outside the core Action semantics.

Must not:

- treat discovery as invocation authorization;
- create a second business execution path;
- infer new risk, trust, authorization, or lifecycle semantics from the protocol;
- populate unsupported protocol fields through speculative inference.

Current WebMCP projection code is an architectural precedent; see the [browser-runtime source](../../packages/browser-runtime/src/) and accepted decisions D-036 through D-038. T-703 will address Laravel MCP projection separately. **T-702 does not implement T-703.**

## 2. Responsibility matrix

Use this matrix during design/review. The ownership boundary must not move merely because a host API makes another approach convenient.

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

## 3. Minimal author workflow

Follow this sequence before claiming an adapter is ready:

1. **Identify the integration boundary.** Decide what host/protocol behavior SurfaceRelay needs to connect to.
2. **Classify the role.** Import, runtime/binding, projection, or a package containing clearly separated roles.
3. **Read the canonical contracts and accepted decisions** relevant to that role.
4. **Identify the existing conformance profile, if any,** that the adapter intends to claim.
5. **Determine the capabilities the implementation actually provides.** Do not declare aspirational capability support.
6. **Implement host/protocol translation without widening authority.** Follow [security.md](security.md).
7. **Preserve exact-target, trusted-context, exposure, authorization, and fail-closed rules.** Follow [security.md](security.md).
8. **Normalize only what existing contracts require.** Keep host-owned semantics host-owned rather than inventing a universal host model.
9. **Wire a conformance harness when an executable profile exists.** Follow [conformance.md](conformance.md).
10. **Run every runner-selected applicable mandatory scenario.** Do not choose a private passing subset.
11. **Fix `FAIL`/`ERROR` results** instead of suppressing scenarios or changing applicability locally.
12. **Publish only claims supported by the actual executable evidence.** Bound claims to profile, target, capabilities, and revisions.

This is an author workflow, not a new release/certification process.

## 4. Read canonical sources before copying implementation patterns

An adapter author should distinguish two kinds of source material:

### Canonical/authoritative semantics

- [`spec/0.1/action-definition.schema.json`](../../spec/0.1/action-definition.schema.json)
- [`spec/0.1/runtime-binding.schema.json`](../../spec/0.1/runtime-binding.schema.json)
- [`spec/0.1/invocation.schema.json`](../../spec/0.1/invocation.schema.json)
- [`spec/0.1/action-result.schema.json`](../../spec/0.1/action-result.schema.json)
- [accepted decisions](../DECISION-REGISTER.md)
- current executable conformance semantics in [`spec/0.1/fixtures/conformance-scenarios.json`](../../spec/0.1/fixtures/conformance-scenarios.json)

### Reference implementation techniques

- current Laravel reference runtime;
- current Livewire binding/driver;
- current HTMX binding/driver;
- current WebMCP projection;
- current repo-local conformance harnesses.

Reference code demonstrates one valid technique. It does not automatically become a new cross-framework contract.

## 5. Runtime/binding author invariants

A runtime/binding adapter must preserve all of these portable invariants.

### 5.1 Exact Action identity/version

A RuntimeBinding references one exact Action `id + version`. The binding does not float to a newer or similar Action version.

### 5.2 Exact issued binding instance

`bindingId` identifies one exact issued binding instance. It is a reference used by the runtime; it is not proof of authorization by itself.

### 5.3 Explicit driver support

A binding's driver identifier resolves only through explicit supported/registered driver behavior. Unknown drivers fail closed; there is no alias/default/fallback driver selection.

### 5.4 Driver-owned target validation

The core RuntimeBinding contract deliberately leaves `target` shape to the named driver. The driver must validate that host-specific target shape before execution.

Do not move host target structure into Action Definition merely to make an adapter easier to implement.

### 5.5 Cumulative validity

Lifecycle, expiry, action availability, driver support, revocation, and exact target validity are cumulative conditions. Passing one condition does not waive another.

Conceptually:

```text
valid =
    binding exists
    AND not revoked
    AND lifecycle authority is still valid
    AND expiresAt has not passed
    AND referenced action id/version is available
    AND driver is supported
```

### 5.6 No silent retargeting

Execution resolves the exact issued target. If that target is gone, stale, contradictory, or otherwise invalid, the adapter fails closed.

Forbidden:

```text
exact target missing
→ search by component class / selector / record / route similarity
→ invoke replacement
```

Required:

```text
exact target missing
→ reject
→ replacement target, if legitimate, receives a newly issued binding
```

### 5.7 Deterministic bounded input mapping

Validated Action input may be translated into host invocation parameters only through a deterministic mapping owned by the driver/host contract.

Do not use incidental JSON property order, generic form state, caller-provided parameter names, or host fallback coercions as invocation authority.

### 5.8 Precise cancellation guarantees

State only what the host can prove.

A strong SurfaceRelay no-dispatch guarantee exists only before framework dispatch begins. After the host's dispatch frontier, cancellation does not automatically prove that server/network work stopped, rolled back, or reversed.

## 6. Portable invariant vs reference technique

> **Reference techniques are illustrative/non-normative.** Future adapters may satisfy the portable invariant differently.

| Portable invariant | Livewire reference technique | HTMX reference technique |
|---|---|---|
| Exact issued target must not silently retarget | exact component identity through supported Livewire API | exact rendered source identity + declarative request contract |
| Input mapping must be deterministic | server-issued positional call plan | named allowlisted Action-input mapping |
| Pre-dispatch cancellation boundary must be explicit | component-scoped action interception before send frontier | no-dispatch only before `htmx.ajax()` frontier |
| Host-owned lifecycle semantics stay host-owned | component-oriented identity/lifecycle | page-scoped rendered source identity |

The point is the invariant, not the syntax/API shown in a current reference implementation.

## 7. Livewire is a reference, not the universal shape

Current Livewire decisions prove one component-oriented implementation:

- D-039 resolves only exact component identity;
- D-040 uses a server-issued call plan for deterministic positional input mapping;
- D-041 stays on supported Livewire public APIs;
- D-042/D-043 define a precise pre-dispatch cancellation frontier.

A future runtime adapter does not need to look like Livewire. It needs to preserve the portable invariants those decisions protect.

## 8. HTMX is a materially different reference, not a Livewire clone

Current HTMX decisions prove a page/source-oriented implementation:

- D-053 defines an exact rendered source identity and declarative request target;
- D-054 resolves the exact current source and uses supported `htmx.ajax()` behavior;
- D-055 fails closed when the exact source is already busy and bounds cancellation to the pre-`htmx.ajax()` frontier;
- D-056 provides deterministic named Action-input mapping while rejecting ambiguous host mechanisms.

The HTMX shape intentionally differs from Livewire. Cross-adapter portability therefore lives at the shared tested invariant level, not in a shared universal target schema.

## 9. Projection adapter guidance

Projection is representation, not new core semantics.

An external surface adapter should follow these rules:

- project only explicitly exposed Actions;
- preserve canonical Action identity/semantics;
- derive external tool identity deterministically only when an accepted mapping defines it;
- map existing effect/risk/content-trust semantics only through accepted projection rules;
- keep discovery separate from invocation authorization;
- route invocation through the normal trusted execution path;
- avoid agent-only business methods or duplicate mutation endpoints;
- omit unsupported protocol fields rather than inferring new meaning.

Existing WebMCP projection behavior is the current precedent. D-036 maps specific existing semantics into annotations without inferring unrelated meaning. D-038 derives deterministic tool identity from Action identity rather than binding/component identity.

Do not infer that every future protocol must use identical annotation fields.

## 10. Definition/import adapter guidance

Imported descriptions are translation input, not runtime authority.

Rules:

- translate only semantics representable by existing Action Definition fields;
- reject or leave unsupported mappings that would be lossy/ambiguous enough to change meaning;
- keep runtime binding information out of Action Definition;
- keep host/protocol-specific metadata secondary and non-authoritative unless an existing canonical extension/field explicitly owns it;
- do not use imported tenant/user/resource metadata as trusted runtime context;
- do not create new Action semantic categories merely to preserve every field from the source format.

An import adapter may be useful even if it supports only a safe subset. Unsupported is preferable to silently changing meaning.

T-704 remains the future OpenAPI-specific task. T-702 does not predefine its mapping decisions.

## 11. Trust/security is part of correctness

Do not treat security as a final checklist after adapter behavior is finished.

Before wiring host execution or projection, read [security.md](security.md). In particular:

- exposure is explicit;
- discovery is not invocation authorization;
- caller payload/client metadata do not become trusted actor/tenant/UI authority;
- exact targets do not silently retarget;
- unsupported or ambiguous states fail closed;
- cancellation claims stop at the actual host dispatch frontier.

If a proposed adapter cannot satisfy one of those boundaries without new semantics, stop and open a new gate.

## 12. Conformance profile/capability guidance

Before claiming executable compatibility, read [conformance.md](conformance.md).

Current T-701 rules that authors must preserve:

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

Authors must not:

- advertise capabilities merely to make a target look more complete;
- suppress mandatory scenarios inside a harness;
- self-report `NOT_APPLICABLE`;
- emit interpretive `passed`/`conformant` booleans in place of bounded observations;
- publish global compatibility wording when evidence only covers one bounded profile.

## 13. Conformance author workflow

When an executable profile applies:

1. identify the existing profile;
2. declare only the target's real capabilities;
3. wire the thin harness to the production boundary;
4. let the runner select applicable mandatory scenarios;
5. execute every selected scenario;
6. fix `FAIL`/`ERROR` results instead of hiding them;
7. record revision-bounded evidence;
8. publish a claim bounded to the actual profile/target/capabilities/revisions.

The harness captures facts. The runner interprets them.

## 14. Illustrative examples policy

> **Illustrative examples — non-normative.** Each example demonstrates an existing invariant. The linked canonical contract/decision remains the authority.

### Correct — truthful capability claim

```text
host does not implement component lifecycle
→ omit lifecycle.component
→ runner computes scenario applicability
```

### Incorrect — harness-owned applicability

```text
host does not implement component lifecycle
→ advertise lifecycle.component
→ harness skips component scenario
```

The second flow violates D-059 because applicability is runner-owned.

### Dangerous — caller becomes tenant authority

```text
caller input: tenant_id = 42
→ adapter writes tenant 42 into trusted context
```

This violates D-007/D-027/D-028. The runtime must resolve tenant authority from its established trusted source.

### Dangerous — stale target replacement

```text
issued component/source is gone
→ adapter searches for something similar
→ invokes replacement
```

This violates D-022/D-025 and the exact-target rules proved by current Livewire/HTMX implementations.

## 15. Versioning and compatibility

Document these independently:

- SurfaceRelay contract/spec revision;
- adapter/package version;
- claimed conformance profile;
- conformance runner/repository revision;
- supported host framework/protocol versions.

Do not collapse them into a generic “supports SurfaceRelay vX” claim if that hides the actual tested boundary.

T-702 does not create version negotiation, compatibility solving, certification, badges, signing, or a compatibility registry.

## 16. Failure guidance

Distinguish failure categories without inventing a global adapter error enum:

- unsupported adapter/host behavior;
- invalid or stale target/binding state;
- unavailable capability;
- invalid trusted-context precondition;
- host/runtime execution failure;
- cancellation/timeout at the defined host boundary;
- harness/projection protocol or normalization failure.

Where an existing contract defines exact result/error semantics, follow it. Otherwise keep vocabulary local/extensible until a separate accepted decision freezes it.

D-026 remains **PROPOSED**.

## 17. Stop and open a new gate when

Stop T-702 implementation instead of extending this guide if authoring reveals a need for:

- a new canonical Action Definition or RuntimeBinding field;
- a universal `Adapter` interface or plugin lifecycle;
- a new trusted-context authority source;
- a new profile, capability, or canonical conformance scenario;
- changed runner selection/applicability/verdict semantics;
- a new global failure-code enum;
- changed Livewire/HTMX production behavior;
- T-703 Laravel MCP implementation;
- T-704 OpenAPI import mapping;
- standalone public-spec extraction or certification infrastructure.

Those requirements belong in their own task/decision gate.

## 18. Author review checklist

Before requesting review for a new adapter/integration, verify:

- [ ] The integration role is classified as import, runtime/binding, projection, or a package with those roles explicitly separated.
- [ ] The implementation links to and follows existing canonical contracts rather than restating a new contract locally.
- [ ] Action Definition remains protocol-neutral and does not carry runtime target authority.
- [ ] Trusted actor/tenant/UI/confirmation authority comes only from established runtime sources.
- [ ] Exposure is explicit and discovery does not imply invocation permission.
- [ ] Runtime/binding execution resolves the exact issued target with no silent fallback/retargeting.
- [ ] Input mapping is deterministic and bounded by the host/driver contract.
- [ ] Unsupported or ambiguous mapping/authority/target state fails closed.
- [ ] Cancellation guarantees match the actual host dispatch boundary.
- [ ] Capability declarations are truthful.
- [ ] Runner-owned scenario selection, applicability, and verdict semantics are preserved.
- [ ] Harnesses emit bounded raw observations only.
- [ ] Compatibility claims are bounded to the profile, target, capabilities, and exact evidence revisions.
- [ ] Examples are clearly illustrative/non-normative.
- [ ] Livewire/HTMX examples are treated as reference techniques, not a universal host shape.
- [ ] D-026 has not been promoted through prose.
- [ ] T-703/T-704 are not represented as already implemented.
- [ ] No new semantic requirement was smuggled in through documentation.
