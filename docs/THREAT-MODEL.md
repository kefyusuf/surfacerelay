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

**Mitigation:** output content-trust classification (`contains_untrusted_content` is independent of sensitivity, D-032); WebMCP untrusted-content hint projection; structured results; redaction; agent-side caution is helpful but server authorization remains mandatory.

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

### T11 — Confirmation spoofing / receipt replay

Caller sends `confirmed=true`, supplies a challenge before human approval, forges a receipt, replays a consumed receipt, or reuses approval for a different action/input/runtime context.

**Mitigation (T-401 implemented in the Laravel reference runtime):** confirmation authority is a server-side `pending → approved → consumed/expired` state machine addressed by an opaque 32-byte random bearer token. Only SHA-256 token hashes are stored. Approval cannot rewrite the stored scope. Receipts are short-lived and single-use, and are bound to exact action ID/version, validated input, surface/binding, and relevant trusted actor/tenant/current-record/current-selection/browser-session context. Scope mismatch grants no authority and does not spend an otherwise-valid receipt; successful exact-scope consumption spends it before execution. The production cache adapter requires a shared lock-capable Laravel cache store and fails closed rather than performing an unlocked mutation. Caller input, metadata, `confirmed=true`, pending challenge IDs, and pre-materialized `human_confirmation` entries never grant authority.

### T12 — Replay / double execution

Network retries or agent retries repeat a write/external side effect.

**Mitigation (T-402 implemented in the Laravel reference runtime):** idempotency is enforced server-side after current validation/authorization and before confirmation. `required_key` rejects missing keys; runtime keys are 1..240 Unicode characters and are not normalized. Raw keys are never persisted: SHA-256 lookup hashes bind the exact action ID/version to a trusted authority partition (tenant/actor when present; otherwise browser session; otherwise explicit global) and the caller token. A separate SHA-256 intent fingerprint binds validated input plus present actor/tenant/current-record/current-selection/browser-session identities.

An exact active `completed` retry reuses persisted deterministic pre-output-policy executor output, skips confirmation and application execution, and still reruns current validation, authorization, output policy and audit. A fresh attempt is only atomically claimed as `in_progress` after any required confirmation succeeds and immediately before application code executes. Same-key different-intent conflicts, active `in_progress`, and `indeterminate` states fail closed before confirmation/execution. Executor failure after claim is conservatively marked `indeterminate`; unreplayable successful output also becomes `indeterminate`; failure to persist successful completion leaves `in_progress` closed rather than reporting success. The database adapter uses a primary hashed key, unique-insert/short row-lock claim transitions, and never holds a database transaction across executor code. The default retention is 86,400 seconds and validity is strict `now < expiresAt`; at equality the bounded deduplication guarantee ends and a fresh claim is allowed.

T-402 does not turn SurfaceRelay into a distributed transaction and does not prove globally exactly-once business effects. External systems may still require their own idempotency/transaction semantics. T-401 receipt single-use remains a separate confirmation-authority protection and is not a substitute for T-402 deduplication.

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
