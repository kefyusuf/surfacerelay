# Threat Model

## Security objective

An agent may propose input and request an action. It must not be able to manufacture the authority required to perform that action.

## Assets

- authenticated user identity;
- tenant/account boundary;
- current resource/record/selection state;
- authorization decisions;
- confirmation authority;
- idempotency/replay state;
- sensitive application data;
- audit integrity;
- runtime binding identity.

## Trust zones

1. **Caller/agent** — untrusted input and model reasoning.
2. **Browser surface** — partially trusted execution environment; page content may contain attacker-controlled text.
3. **Binding layer** — app/framework-generated runtime references; must be integrity-checked/lifecycle-bound.
4. **Server runtime** — authoritative validation, identity, tenancy, authorization, confirmation, idempotency.
5. **External systems** — may produce untrusted content or irreversible side effects.

## Threats and required mitigations

### T1 — Tool metadata poisoning

Attacker-controlled text becomes tool name/description/parameter description and manipulates agent behavior.

**Mitigation:** static/app-authored metadata by default; sanitize/escape dynamic metadata; never use user content as executable instruction metadata without explicit policy.

### T2 — Indirect prompt injection in tool output

External/UGC content contains instructions aimed at the agent.

**Mitigation:** output trust classification; WebMCP untrusted-content hint projection; structured results; redaction; agent-side caution is helpful but server authorization remains mandatory.

### T3 — Session riding

Agent acts with the user's authenticated browser session.

**Mitigation:** treat that session as real authority but re-run policy per invocation; require stronger confirmation for consequential actions; audit surface/correlation.

### T4 — Confused deputy

Agent requests an action on a resource it should not control by passing IDs/tenant values.

**Mitigation:** resolve tenant/resource scope from trusted context; treat caller IDs as lookup input only and authorize resolved resources.

### T5 — Tenant forgery

Caller supplies `tenant_id` and crosses tenant boundary.

**Mitigation:** tenant resolver ignores caller authority fields; required tenant derives from authenticated session/domain/framework context.

### T6 — Current-selection forgery

Agent passes record IDs claiming they are the user's selected rows.

**Mitigation:** current selection comes from signed/server/framework-managed page context; action input does not substitute for `current_selection` requirement.

### T7 — Binding substitution

Caller changes component/method/selector/driver target to execute another operation.

**Mitigation:** binding ID maps to server/app-issued descriptor; driver target is not accepted as ordinary action input; integrity/lifecycle checks before execution.

### T8 — Stale binding execution

A tool remains visible after component navigation/unmount and invokes a no-longer-valid target.

**Mitigation:** AbortController/browser lifecycle cleanup plus server-side binding validity; stale binding returns explicit error.

### T9 — Duplicate/collision attack

Different actions/bindings map to ambiguous tool names.

**Mitigation:** stable action IDs; deterministic projection namespace; registration collision detection; no silent overwrite.

### T10 — Cross-origin overexposure

Tools become available to unintended frames/origins.

**Mitigation:** secure-context and Permissions Policy awareness; explicit origin exposure; least privilege; never infer trust from embedding alone.

### T11 — Confirmation spoofing

Caller sends `confirmed=true` or replays an old approval.

**Mitigation:** opaque runtime-issued confirmation receipt bound to actor, tenant, action, relevant input/binding, expiry, and single-use/replay policy.

### T12 — Replay / double execution

Network retries or agent retries repeat a write/external side effect.

**Mitigation:** action-declared idempotency policy; server idempotency key/store; duplicate returns prior outcome or safe rejection.

### T13 — Cancellation confusion

Browser cancellation is interpreted as rollback even though server/external effect already happened.

**Mitigation:** propagate cancellation where supported; never claim rollback; audit actual final state; result model distinguishes cancelled request from proven reversed effect.

### T14 — Tool-budget / registration DoS

Page exposes hundreds of low-value tools and causes agent/browser limits or ambiguity.

**Mitigation:** page-scoped curated actions, discovery filtering, bounded registrations, namespaced groups later only if evidence supports them.

### T15 — Sensitive output leakage

Read-only tool returns PII/secrets across agent context.

**Mitigation:** read-only is not “safe”; authorization + output policy/redaction + minimal result schemas.

### T16 — Authorization-by-discovery

Implementation assumes a visible tool is already authorized.

**Mitigation:** discovery check and invocation check are separate; invocation always re-evaluates relevant policy/context.

### T17 — Browser-spec drift

Changing WebMCP semantics cause registration/lifecycle/security behavior to diverge.

**Mitigation:** isolate browser API in projection adapter; feature-detect there; pin compatibility tests; update projection without changing core vocabulary unless semantics truly changed.

### T18 — Driver injection

Manifest names attacker-controlled/unregistered runtime driver.

**Mitigation:** explicit driver registry; unknown drivers fail closed; no dynamic `eval`/module path execution from manifest values.

### T19 — Audit leakage/tampering

Audit stores full sensitive payload or can be confused across retries.

**Mitigation:** correlation IDs, hashes/minimized payloads, explicit retention, append-only/event sink where appropriate, access controls.

## Security test rule

Every task that touches a trust boundary must include a negative test proving the attempted bypass fails.
