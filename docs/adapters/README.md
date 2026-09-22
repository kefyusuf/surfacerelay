# Adapter Authoring

SurfaceRelay adapter documentation explains how to extend existing integration boundaries without redefining the core model.

> **Source of truth:** canonical contracts, accepted decisions, and executable conformance semantics govern. These guides are explanatory and non-normative.

## Choose your integration boundary

| You are adding… | Start with |
|---|---|
| external-definition import/translation | [Author Guide — Definition / import](author-guide.md#11-definition--import-adapter) |
| runtime/framework execution binding | [Author Guide — Runtime / binding](author-guide.md#12-runtime--binding-adapter), then [Security](security.md) |
| external protocol/tool projection | [Author Guide — Surface / projection](author-guide.md#13-surface--projection-adapter), then [Security](security.md) |
| executable profile/capability evidence | [Conformance and Evidence](conformance.md) |
| architecture/security review | [Security](security.md) + [responsibility matrix](author-guide.md#2-responsibility-matrix) |

These are responsibility categories for authors. They do not define a universal `Adapter` interface, plugin loader, or common adapter lifecycle.

## Recommended reading order

1. Read this entry point and classify the integration role.
2. Read [author-guide.md](author-guide.md) for the workflow and ownership matrix.
3. Read [security.md](security.md) before wiring trusted/runtime behavior.
4. Read [conformance.md](conformance.md) before claiming a profile/capability or publishing compatibility evidence.
5. Follow the linked canonical source for exact contract semantics.

## Boundary summary

Keep these rules visible while authoring:

```text
translate; do not redefine core semantics
consume trusted context; do not manufacture it
resolve exact issued targets; do not silently retarget
project explicitly exposed actions; discovery is not invocation authority
advertise only real capabilities
let the runner select/apply/evaluate mandatory conformance scenarios
publish only revision-bounded evidence claims
open a new gate for new semantics
```

## Canonical reference map

| Source | Owns |
|---|---|
| [Action Definition schema](../../spec/0.1/action-definition.schema.json) | Protocol-neutral Action identity, semantics, input/output shape, scope/effect/risk/idempotency/output/context vocabulary. |
| [Runtime Binding schema](../../spec/0.1/runtime-binding.schema.json) | Exact Action reference, binding identity, driver, lifecycle, expiry, and driver-owned target container. |
| [Invocation schema](../../spec/0.1/invocation.schema.json) | Caller invocation envelope and non-authoritative client metadata boundary. |
| [Action Result schema](../../spec/0.1/action-result.schema.json) | Normalized result structure. |
| [Conformance scenarios](../../spec/0.1/fixtures/conformance-scenarios.json) | Canonical scenario semantics used by the current repo-local conformance system. |
| [Decision Register](../DECISION-REGISTER.md) | Status authority for `ACCEPTED`, `PROPOSED`, and `SUPERSEDED` architecture decisions. |
| [Glossary](../GLOSSARY.md) | Locked/current project vocabulary and semantic explanations. |
| [Architecture](../ARCHITECTURE.md) | Layer boundaries: Action Definition, Runtime Binding, Trusted Runtime, Surface Projection. |
| [Conformance overview](../../CONFORMANCE.md) | Current executable profile/scenario/target scope and runner-owned verdict model. |
| [Repo-local conformance protocol](../../conformance/README.md) | Current target manifests, one-process protocol, raw observations, and local execution commands. |

The [Decision Register](../DECISION-REGISTER.md) is the status authority for architecture decisions. During T-702 implementation:

```text
D-026: PROPOSED
D-062: PROPOSED
```

Do not promote either through author-guide prose.

## What each guide owns

### [author-guide.md](author-guide.md)

End-to-end author workflow, role taxonomy, responsibility matrix, runtime/binding guidance, projection guidance, import guidance, Livewire/HTMX reference contrasts, and scope-stop conditions.

### [security.md](security.md)

Explicit exposure, discovery-vs-invocation, trusted context, exact-target/no-retarget behavior, fail-closed rules, cancellation boundaries, dangerous patterns, and a security review checklist.

### [conformance.md](conformance.md)

Current T-701 `profile + capability` model, runner/harness authority, process protocol, bounded raw observations, evidence recording, compatibility wording, and version dimensions.

## Current scope limits

These guides do **not** introduce:

- a new adapter SDK;
- a universal `Adapter` interface;
- plugin discovery/loading;
- a new RuntimeBinding or BindingDriver abstraction;
- a new profile, capability, or canonical conformance scenario;
- a global error enum;
- public certification, badges, signing, or compatibility registry infrastructure;
- changes to the separately implemented Laravel MCP projection contract (T-703);
- changes to the separately implemented OpenAPI importer mapping contract (T-704).

T-703 and T-704 now exist as separate reviewed integrations; this guide does not become authority for their package-specific semantics. If an integration cannot be described accurately without one of the changes above, stop and open a separate task/decision gate.
