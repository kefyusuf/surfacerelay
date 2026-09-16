# Adapter Security and Trust Boundaries

> This guide explains existing SurfaceRelay contracts and accepted decisions. It is not an independent specification. If this document conflicts with `spec/0.1/**`, an accepted decision, or executable conformance semantics, the canonical artifact governs.

Security and trust behavior is part of adapter correctness, not an optional hardening layer.

Canonical references:

- [Decision Register](../DECISION-REGISTER.md)
- [Threat Model](../THREAT-MODEL.md)
- [Runtime Binding schema](../../spec/0.1/runtime-binding.schema.json)
- [Invocation schema](../../spec/0.1/invocation.schema.json)
- [Glossary](../GLOSSARY.md)

## 1. Explicit exposure

SurfaceRelay exposure is explicit. An adapter must not scan arbitrary application behavior and expose everything by convention.

This boundary is owned by D-010. Projection/import/runtime code may translate already-authorized SurfaceRelay concepts into a host representation, but it does not gain authority to make unrelated controllers, component methods, routes, model operations, or framework events agent-visible.

A useful review question is:

```text
Would this action exist on the external surface if the application never explicitly exposed it?
```

If the answer is yes, the adapter is widening exposure beyond the established contract.

## 2. Discovery is not invocation authorization

Being able to discover, list, describe, or project an action does not grant authority to invoke it.

D-011 keeps discovery permission separate from invocation authorization. Invocation still traverses the normal trusted path: binding/runtime checks, trusted-context resolution, input validation, authorization, confirmation when required, idempotency, execution, output handling, and audit.

A projection must not create a second, weaker business execution route merely because a protocol can discover a tool.

```text
discovery allowed
    ≠
invocation authorized
```

## 3. Caller input is not trusted runtime context

Caller-provided values are ordinary untrusted input unless an existing contract says otherwise. Parsing a value does not make it authoritative.

D-007 and D-027 through D-029 require actor/tenant/runtime authority to come from trusted runtime sources. D-048 through D-052 apply the same principle to Filament-specific current record, selection, filters, confirmation, and tenant-scoped behavior.

The following values must not become trusted context merely because an adapter can read them:

- `tenant_id` or equivalent tenant/account identifiers;
- actor/user identifiers, role names, or permission claims;
- record/resource IDs presented as the current record;
- selected row/resource IDs presented as the current selection;
- `confirmed=true`, arbitrary confirmation flags, or caller-provided challenge data outside the defined confirmation receipt flow;
- host/runtime target identifiers supplied as ordinary action input;
- generic client metadata.

The [Invocation schema](../../spec/0.1/invocation.schema.json) explicitly classifies `clientMeta` as non-authoritative metadata. The [Action Definition schema](../../spec/0.1/action-definition.schema.json) similarly keeps trusted context out of `inputSchema`.

The correct direction of authority is:

```text
caller input ------------------------------┐
client metadata ---------------------------┘ untrusted

session/auth middleware ──→ actor ---------┐
tenant resolver ──────────→ tenant         │ trusted
binding producer ─────────→ record/selection
confirmation store ───────→ receipt -------┘ runtime authority
```

Adapters consume trusted runtime authority; they do not manufacture it.

## 4. Exact runtime targets must not silently retarget

A RuntimeBinding identifies one exact issued binding instance, exact Action identity/version, driver, lifecycle, and driver-owned target.

D-016 and D-022 through D-025 require:

- driver resolution only through explicit support/registration;
- exact binding identity and exact Action version;
- cumulative validity checks across existence, revocation, lifecycle, expiry, action availability, and driver support;
- stale targets to fail closed;
- no rediscovery or substitution of a similar replacement target.

The [Runtime Binding schema](../../spec/0.1/runtime-binding.schema.json) deliberately leaves target shape to the named driver. That flexibility does not permit fallback targeting.

Current reference implementations prove the invariant differently:

- D-039: Livewire resolves the exact issued component identity and refuses name/DOM/class/position replacement fallback.
- D-053 through D-055: HTMX pins an exact rendered source identity and declarative request contract; replacement sources do not inherit an old binding.

A future adapter may use a different host mechanism, but it must preserve the same portable invariant: **the exact issued target does not silently become another target.**

## 5. Unknown or ambiguous authority fails closed

Unknown, missing, stale, ambiguous, unsupported, or contradictory authority/target/mapping state must be rejected instead of guessed.

Examples already established by accepted decisions include:

- unknown driver: reject; do not fall back to another registered driver (D-016, D-035);
- stale/missing exact runtime target: reject; do not rediscover a replacement (D-025, D-039, D-054);
- ambiguous Action-input mapping: reject; do not infer parameter order or silently coerce host behavior (D-040, D-053, D-056);
- missing or duplicate trusted context: reject according to the runtime contract rather than falling back to caller payload/metadata (D-027);
- unsupported framework surface: reject instead of using private/undocumented execution paths to manufacture compatibility (D-041, D-043, D-054);
- cancellation after the supported dispatch frontier: do not claim rollback, reversal, or server cancellation that the host cannot prove (D-042, D-055).

Fail-closed behavior is not an adapter-local policy vocabulary. It follows the owning canonical contract or accepted decision.

## 6. Preserve the normal trusted execution path

Adapters translate between SurfaceRelay and a host/protocol. Translation must not bypass existing invocation controls.

For a runtime/projection path, the architecture remains conceptually:

```text
resolve action
→ resolve/verify binding
→ resolve trusted context
→ check context requirements
→ validate input
→ authorize invocation
→ enforce runtime policies
→ require/verify confirmation when needed
→ enforce idempotency when configured
→ execute application action
→ validate/redact/annotate output
→ audit
→ normalized result
```

An adapter must not create an agent-only business method, alternate mutation endpoint, hidden authorization shortcut, fallback identity, or separate confirmation authority.

## 7. Cancellation claims must match the real host boundary

SurfaceRelay does not treat cancellation as automatic rollback.

D-042 establishes a strong no-dispatch guarantee only before framework dispatch begins. Host-specific adapters must state the actual dispatch frontier precisely.

Current reference examples:

- Livewire: the documented `onSend` hook marks the dispatch frontier; cancellation after it does not prove server work stopped or rolled back.
- HTMX: strong no-dispatch applies only before the `htmx.ajax()` invocation frontier; post-frontier cancellation does not imply network/server cancellation or reversal.

Do not describe a weaker host guarantee as stronger than it is.

## 8. Adapter-specific trusted extensions remain trusted-runtime state

D-050 allows adapter-specific trusted UI authority that has not earned frozen core `ContextRequirement` vocabulary to use a separate namespaced trusted-runtime-extension channel.

That is not a generic metadata escape hatch. A trusted extension:

- is produced by trusted server/runtime wiring;
- is not populated from ordinary caller payload or generic metadata;
- participates in authority-sensitive scope where the accepted decision requires it;
- remains adapter-specific rather than silently becoming new core vocabulary.

If a new adapter needs a new trusted authority source, stop the adapter task and open a separate design/decision gate.

## 9. Illustrative boundary examples

> **Illustrative examples — non-normative.** The owning canonical contract/decision, not the example syntax, defines the requirement.

### Correct

```text
host capability is not implemented
→ do not advertise the capability
→ let the runner compute applicability
```

Why: truthful capability advertisement preserves runner-owned applicability under D-059 through D-061.

### Incorrect

```text
host capability is not implemented
→ advertise it anyway
→ suppress the mandatory scenario in the harness
```

Why: the harness does not own applicability or scenario suppression.

### Dangerous

```text
caller sends tenant_id
→ adapter copies tenant_id into trusted runtime context
```

Why: caller input cannot manufacture tenant authority (D-007, D-027, D-028).

### Dangerous

```text
issued target is stale/missing
→ adapter searches for a similar current target
→ invocation continues against the replacement
```

Required behavior:

```text
issued target is stale/missing
→ reject/fail closed
→ do not rediscover or substitute a replacement target
```

Why: exact issued targets do not silently retarget (D-022, D-025).

## 10. Failure vocabulary boundary

T-702 does not create a global adapter failure enum.

Authors should distinguish failure categories conceptually — unsupported host behavior, stale/invalid targets, missing capabilities, invalid trusted-context preconditions, host execution failure, cancellation/timeout, and protocol/normalization failure — while using exact error/result semantics only where an existing canonical artifact defines them.

D-026 remains **PROPOSED**. Its provisional names must not be promoted by adapter documentation into a frozen global enum. D-030 also keeps `ActionError.code` extensible rather than closed.

## 11. Security review checklist

Before requesting review for an adapter, verify:

- [ ] Is exposure explicit rather than convention-based/expose-all?
- [ ] Does discovery remain separate from invocation authorization?
- [ ] Does trusted actor/tenant/UI context come only from established trusted runtime sources?
- [ ] Are caller payload and generic metadata prevented from manufacturing authority?
- [ ] Does the runtime/binding path resolve the exact issued target only?
- [ ] Are unknown driver, stale target, unsupported mapping, missing authority, and ambiguous state rejected rather than guessed?
- [ ] Does the adapter preserve the normal validation/authorization/confirmation/execution path?
- [ ] Are cancellation guarantees stated only up to the actual supported dispatch frontier?
- [ ] Are framework/protocol-specific mechanics clearly separated from portable SurfaceRelay invariants?
- [ ] Did documentation avoid inventing a new global error vocabulary or promoting D-026?

## 12. Stop and open a new gate

Do not extend this guide or an adapter implementation ad hoc if correct implementation appears to require:

- a new trusted-context authority source;
- a new Action Definition or RuntimeBinding field;
- a universal adapter/plugin lifecycle abstraction;
- new authorization/confirmation semantics;
- a new global failure-code enum;
- changed Livewire/HTMX production semantics.

Those are architecture changes, not documentation details. They require a separate task/decision gate.
