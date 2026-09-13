# T-604 — Binding Driver Conformance Design

## Status

- Task: `T-604 — Shared conformance against Livewire + HTMX`
- Milestone: `M6 — HTMX Portability Proof`
- Branch: `feat/binding-driver-conformance`
- Base: `main@0e06f140035473ac6f29dfec1a87dd0a78fd068d`
- Design state: **APPROVED IN CHAT / WRITTEN SPEC UNDER REVIEW**
- Implementation state: **NOT STARTED**
- Existing Livewire driver decisions: `D-039`, `D-040`, `D-041`, `D-042`, `D-043` — **ACCEPTED**
- Existing HTMX decisions: `D-053`, `D-054`, `D-055`, `D-056`, `D-057` — **ACCEPTED**
- Proposed shared-conformance decision: `D-058`
- Portability decision: `D-020` — remains **PROPOSED** until the T-604 verification gate is complete

T-604 is the final M6 portability gate. T-601 proved an explicit HTMX binding descriptor, T-602 proved its browser driver, and T-603 proved that driver against a real non-Laravel HTMX application. T-604 now asks a narrower question: **which browser binding-driver semantics are genuinely shared by the already-implemented Livewire and HTMX paths?**

The answer must come from one shared executable matrix, not from two independent test files that happen to contain similar assertions.

This task does **not** define a new public browser-driver abstraction, normalize driver-owned targets, or implement the future repository-wide conformance runner from T-701.

Implementation must not begin until this written design is reviewed and explicitly authorized.

## Objective

Prove that the production `LivewireBrowserDriver` and `HtmxBrowserDriver` satisfy the same observable fail-closed contract where their semantics actually overlap, while keeping framework-specific behavior local to each driver.

The shared proof must establish all of the following:

1. the same conformance cases execute against both production drivers;
2. common semantics are asserted through observable outcomes and dispatch counts, not by forcing identical internal implementations;
3. invalid, expired, unmappable, stale, or already-cancelled executions fail before unintended framework dispatch;
4. an equivalent replacement target never inherits an old binding identity;
5. driver-specific target shapes, lifecycle values, runtime APIs, cancellation mechanisms, and success-result behavior remain driver-owned;
6. T-604 remains a browser-runtime portability proof and does not become T-701 early;
7. `D-020` is promoted only after the complete T-604 implementation and verification gate succeeds.

## Proposed decision — D-058: shared binding-driver conformance is behavioral, not structural

Record during this design gate as `PROPOSED`:

> Livewire and HTMX establish shared browser binding-driver conformance by passing one executable test matrix over the behavior that is genuinely common to both production `BindingDriver` implementations: fail-closed target validation, expiry classification, Action-input mappability, exact-target stale/no-retarget behavior, and cancellation before framework dispatch. The shared matrix asserts observable error codes, dispatch/no-dispatch effects, and exact-target identity guarantees; it does not require identical driver-owned target shapes, lifecycle values, runtime APIs, framework-specific error codes, cancellation mechanisms, or successful return values. The matrix and its adapter harness are test-only T-604 infrastructure, not a new public production abstraction and not the general T-701 conformance runner.

D-058 remains `PROPOSED` through implementation. It becomes eligible for `ACCEPTED` only when both production drivers pass the complete shared matrix and the regression suites remain green.

`D-020` remains separately `PROPOSED` during design and implementation. A successful T-604 verification gate provides the evidence required for a later explicit promotion or rejection of that portability decision; D-058 does not auto-promote D-020.

---

# Part 1 — Shared semantic matrix

## 1.1 Conformance boundary

The shared unit is the existing production contract:

```ts
interface BindingDriver {
  execute(
    binding: RuntimeBinding,
    input: Record<string, unknown>,
    context: DriverExecutionContext,
  ): Promise<unknown>;
}
```

T-604 does not add methods to `BindingDriver`, does not add a base driver class, and does not introduce a common production error superclass merely to make tests easier.

The shared matrix is about **observable semantics at this boundary**.

## 1.2 Required shared cases

The implementation plan must encode the following cases once and run each case against both adapters.

| Case | Shared expectation | Livewire realization | HTMX realization |
|---|---|---|---|
| valid execution | exact intended target dispatches once | exact component + method | exact source + method/path |
| foreign/invalid binding target | `binding_target_invalid`; zero dispatch | wrong driver/lifecycle/shape | wrong driver/lifecycle/shape |
| invalid expiry value | `binding_target_invalid`; zero dispatch | shared expiry classifier | shared expiry classifier |
| expired binding | `binding_expired`; zero dispatch | shared expiry classifier | shared expiry classifier |
| expiry equality | expired; zero dispatch | same boundary instant | same boundary instant |
| unknown input key | `binding_input_unmappable`; zero dispatch | positional call-plan mapper | named HTMX mapper |
| missing required input | `binding_input_unmappable`; zero dispatch | positional call-plan mapper | named HTMX mapper |
| stale exact target | `binding_stale`; zero business dispatch | component missing/mismatched | source missing/duplicated/mismatched |
| equivalent replacement target | old binding remains `binding_stale`; replacement receives zero dispatch | replacement component identity | replacement source identity |
| signal already aborted | reject with the signal reason; zero framework dispatch | before Livewire call | before HTMX ajax |

The matrix may include additional shared cases only when both drivers already have the same externally meaningful contract. It must not weaken or rewrite an existing driver-specific guarantee just to increase the size of the shared table.

## 1.3 Exact target means semantic identity, not equal target JSON

Livewire and HTMX deliberately have different target contracts:

```text
Livewire
  driver: livewire
  lifecycle: component
  target: componentId + method + inputOrder + requiredCount

HTMX
  driver: htmx
  lifecycle: page
  target: sourceId + method + path + inputNames + requiredInputNames
```

T-604 must preserve that difference.

Shared conformance means:

```text
issued exact target identity
        ↓
current runtime still owns that exact identity?
        ├── yes -> eligible to dispatch
        └── no  -> binding_stale, no retarget
```

It does **not** mean inventing a normalized target such as `{ targetId, operation, inputs }` and rewriting both drivers around it.

## 1.4 Shared error surface

The current driver error taxonomies intersect on:

```text
binding_stale
binding_expired
binding_target_invalid
binding_input_unmappable
```

These codes are the shared error assertions for T-604.

Framework-local codes remain local, including examples such as:

```text
Livewire:
  livewire_runtime_unavailable
  livewire_method_unsupported
  livewire_cancellation_unavailable

HTMX:
  htmx_runtime_unavailable
  htmx_runtime_unsupported
  htmx_source_unsupported
  htmx_source_busy
```

T-604 must not rename, merge, or promote those framework-specific codes into a generic closed enum.

## 1.5 Cancellation boundary

The only cancellation behavior required in the shared matrix is the guarantee already common to both drivers:

```text
caller signal already aborted
        ↓
no framework dispatch begins
        ↓
reject with the caller's abort reason
```

Post-start cancellation remains framework-specific:

- Livewire may capture the exact action and cancel before its `onSend` frontier;
- HTMX has no equivalent SurfaceRelay-owned post-`htmx.ajax()` cancellation guarantee and treats the call into the host HTMX runtime as its dispatch frontier.

Existing driver-specific cancellation suites remain authoritative for those differences.

## 1.6 Success-result boundary

The shared matrix may assert **that the exact intended framework dispatch occurred once**, but it must not require identical successful return values.

Livewire may surface the resolved `$wire.$call()` result. The reference HTMX path is defined by host request/swap behavior and may resolve without a business result object. T-604 must not manufacture a normalized result envelope.

---

# Part 2 — Test-only adapter harness

## 2.1 Architecture

Use one test-only shared suite with thin driver-specific adapters:

```text
shared binding-driver conformance cases
                |
        +-------+-------+
        |               |
Livewire test adapter   HTMX test adapter
        |               |
production              production
LivewireBrowserDriver   HtmxBrowserDriver
        |               |
Livewire fake runtime   HTMX fake runtime
```

The production drivers are the systems under test. The adapters only create valid driver-owned bindings, configure runtime state, expose dispatch counters/evidence, and create driver-specific stale/replacement situations required by the common cases.

## 2.2 Proposed test-only shape

The implementation plan may refine names, but the conceptual boundary should stay equivalent to:

```ts
interface BindingDriverConformanceAdapter {
  readonly name: 'livewire' | 'htmx';

  createHarness(now: Date): {
    driver: BindingDriver;
    validBinding: RuntimeBinding;
    validInput: Record<string, unknown>;

    makeTargetStale(): void;
    replaceTargetWithEquivalentIdentity(): void;

    frameworkDispatchCount(): number;
    replacementDispatchCount(): number;
  };

  invalidBinding(base: RuntimeBinding): RuntimeBinding;
  inputWithUnknownKey(): Record<string, unknown>;
  inputMissingRequiredKey(): Record<string, unknown>;
}
```

This is design pseudocode, not a requirement to expose this exact TypeScript API.

The implementation should prefer the smallest helper surface that allows one matrix to execute the cases in Part 1 without hiding driver behavior.

## 2.3 Harness rules

The shared harness must:

- instantiate the actual `LivewireBrowserDriver` and `HtmxBrowserDriver`;
- use deterministic clocks for expiry cases;
- make dispatch evidence observable without duplicating driver logic;
- let each adapter construct its own valid driver-owned binding and runtime state;
- keep replacement identity distinct even when operation semantics are equivalent;
- assert zero dispatch for all fail-closed cases;
- avoid calling private driver helpers directly as the proof mechanism.

The shared harness must not:

- copy production parsing or input-mapping algorithms;
- recreate the driver under test in a fake implementation;
- normalize Livewire and HTMX targets into a new production shape;
- alter production runtime ports solely for test convenience unless a separate design decision explicitly justifies it;
- depend on the T-603 Node/Chromium fixture for every case;
- treat two independent driver-specific tests as shared conformance.

## 2.4 Existing tests remain

T-604 supplements rather than replaces the existing suites.

Driver-specific tests continue to own details such as:

### Livewire-only

- `$wire.$id` verification details;
- reserved `$wire` method collisions;
- exact positional `inputOrder` / `requiredCount` behavior;
- component-scoped `intercept()` behavior;
- `onSend` dispatch frontier;
- cancellation-compatibility failures.

### HTMX-only

- exact physical `hx-*` request declaration checks;
- same-origin path validation;
- unsupported inherited HTMX mechanisms;
- `hx-params` / validation restrictions;
- source busy behavior;
- HTMX 2.x runtime support;
- deterministic JSON-data override encoding;
- HTMX runtime rejection/swap semantics.

The shared matrix proves portability of the overlap. The existing suites prove correctness of the non-overlap.

## 2.5 No T-701 runner in T-604

T-604 may use ordinary Vitest helpers/parameterized tests inside `packages/browser-runtime/tests/**`.

It must not introduce:

- a repository-wide conformance CLI;
- a plugin/adapter discovery system;
- a JSON scenario execution engine;
- cross-language process orchestration;
- a public conformance SDK;
- promotion of all `kind=runtime` entries in `spec/0.1/fixtures/conformance-scenarios.json` to executable status.

Those concerns belong to `T-701 — Executable conformance runner`.

T-604 evidence may later become an input to T-701, but T-604 must remain small enough to prove M6 without prematurely designing M7.

---

# Part 3 — Evidence, scope, and decision gate

## 3.1 Expected implementation boundary

A later implementation plan may add test-only files under `packages/browser-runtime/tests/**` and make the minimum documentation/tracking updates required to record the result.

The preferred implementation shape is one shared suite plus two thin adapters, not production refactoring.

Production changes are **not presumed**. If the shared matrix exposes a real semantic mismatch, implementation must stop and classify it before changing production behavior:

```text
bug in one implementation?
        -> fix under the existing accepted decision

genuine driver-specific difference?
        -> keep out of the shared matrix

missing portable contract decision?
        -> open a new design/decision gate before changing production semantics
```

T-604 must not silently change an accepted Livewire or HTMX decision to make a test pass.

## 3.2 Explicit non-goals

T-604 does not:

- change `ActionDefinition` semantics;
- change the `RuntimeBinding` schema;
- close the extensible driver namespace;
- create a universal target schema;
- move trusted actor/tenant/record/selection authority into browser bindings;
- duplicate Laravel ActionBus/trust controls in the browser runtime;
- add a new Livewire or HTMX business execution path;
- rewrite T-603 as the shared conformance harness;
- standardize framework-specific error codes;
- standardize successful driver return values;
- promise post-dispatch rollback/cancellation;
- implement T-701;
- extract a standalone standard/specification repository.

## 3.3 Frozen contract boundary

The design gate itself requires no changes under:

```text
spec/0.1/**
packages/browser-runtime/src/**
packages/laravel/src/**
examples/htmx-prep-list/**
.github/workflows/**
```

During later implementation, any proposal to change the frozen contract or production behavior requires separate evidence and the repository's normal schema/fixture/conformance/decision process. A shared test is not authority to mutate the contract.

## 3.4 Verification gate for T-604

T-604 may be considered complete only when all of the following are true:

1. every required Part 1 case is declared once in a shared suite;
2. the exact same case set runs against production `LivewireBrowserDriver` and production `HtmxBrowserDriver`;
3. fail-closed cases prove zero unintended framework dispatch;
4. stale replacement cases prove zero dispatch to the equivalent replacement identity;
5. both existing driver-specific suites remain green;
6. Livewire WebMCP integration tests remain green;
7. HTMX WebMCP integration tests remain green;
8. cancellation regression suites remain green;
9. browser-runtime TypeScript typecheck remains green;
10. repository validation remains green;
11. the T-603 real Chromium fixture remains green, preserving the real HTMX proof;
12. changed-file review shows no accidental frozen-spec or unrelated production expansion;
13. external review has no unresolved correctness findings against the T-604 boundary.

Exact commands and CI evidence belong in the later implementation plan/review record, not this design document.

## 3.5 Decision promotion gate

Before verification:

```text
D-058 = PROPOSED
D-020 = PROPOSED
T-604 = design approved, implementation not started/in progress as applicable
```

After successful implementation, verification, and explicit review:

- `D-058` may be promoted to `ACCEPTED` for the tested shared binding-driver behavior;
- `D-020` may be separately promoted to `ACCEPTED` only if the evidence supports the claim that HTMX is a materially different second binding that passes the shared portability scenarios;
- M6 may close only after those decision states are recorded explicitly.

A green test run does not silently change either decision.

## 3.6 Relationship to the architecture promotion rule

The repository architecture says a standalone standard must not be extracted until:

1. Laravel + Livewire implements the contract;
2. a materially different HTMX binding implements it;
3. shared conformance scenarios expose no Livewire-specific assumptions.

T-604 addresses item 3 only for the browser binding-driver portability slice required by M6. It is evidence toward that future promotion rule, not authorization to perform the extraction now.

## Current gate

This document completes the approved **T-604 design-writing gate only**.

At this point:

- no T-604 implementation plan has been written;
- no shared test harness has been created;
- no production code has changed;
- no frozen spec has changed;
- `D-058` is only proposed;
- `D-020` remains proposed;
- implementation requires a separate explicit next step.
