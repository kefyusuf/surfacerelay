# Glossary

This glossary locks the v0.1 semantic vocabulary (task T-001). Every enum value has exactly one meaning and at least one example. The same value sets appear in `spec/0.1/action-definition.schema.json`.

## Action

A coherent application capability with explicit input/output semantics and an execution handler.

## Action Definition

Protocol-neutral metadata describing what an action means. It does not identify a framework/runtime target.

## Runtime Binding

A descriptor connecting one Action Definition (`id` + exact `version`) to a concrete runtime target, issued by the runtime. Bindings have identity, a driver, a lifecycle, optional expiry, and can be revoked or become stale. A binding is a reference to execution authority — it is never proof of authorization by itself.

## Binding ID

Identifies one exact issued runtime binding instance. Invariants (D-022):

- It references exactly one Action Definition `id + version`.
- It must not silently retarget to another action, action version, runtime target, or authority context.
- If the executable target instance changes materially, a new binding ID is issued.
- Reusing an old binding ID to point at a replacement component/target is forbidden.
- Unknown binding IDs fail closed.

`ActionReference.id` (`action.id`) uses the canonical Action ID grammar defined by the Action Definition contract; it is not a separate identifier namespace (T-004). Action Definition, Runtime Binding ActionReference, and Invocation ActionReference all use the same canonical Action Identity (`id + version`) (T-005).

## Binding Driver

An extensible identifier naming how the runtime executes the binding (D-016). Examples: `livewire`, `htmx`, `liveview`, `http`, or namespaced custom drivers. None of these are core enum values. A runtime may execute a binding only if the named driver is explicitly registered/supported; unknown drivers fail closed; no fallback to another driver is permitted. The binding's `target` is driver-owned data whose shape the core contract does not enumerate.

## Binding lifecycle

Which runtime authority a binding's validity is tied to (D-023). Values locked for v0.1:

- `page` — validity is tied to one runtime-defined page/surface instance. When that instance ends, the binding becomes stale. This is not necessarily a browser `document`: SPA/server-driven frameworks may navigate logically without a full document reload. *Example: a tool exposed only on the current orders page.*
- `component` — validity is tied to one specific component instance. It becomes stale when that instance is unmounted, destroyed, replaced, or otherwise ceases to be the authority that issued the binding. A normal rerender of the same instance does not necessarily invalidate it. A replacement component must not inherit the old binding merely because it shares component class, method name, DOM position, or business record.
- `session` — validity is tied to a trusted runtime session authority. Session identity comes from trusted runtime state, never ordinary caller input. It becomes stale when the session expires, is revoked, logs out, rotates in a way that invalidates prior bindings, or otherwise ceases to be authoritative. Not equated with any specific cookie implementation.
- `persistent` — validity is not intrinsically tied to page, component, or session lifetime. This does **not** mean permanent, globally authorized, anonymous, never expiring, immune to revocation, or safe to cache forever. Persistent bindings remain subject to explicit revocation, `expiresAt`, exact action version availability, driver availability, validation, authorization, tenancy, confirmation, idempotency, and other runtime policy.

The runtime/adapter that issued the binding is responsible for detecting when its lifecycle authority has ended.

## Binding expiry (`expiresAt`)

An independent hard upper time bound on binding validity (D-024). Lifecycle and expiry are cumulative, not alternatives: whichever invalidates first wins. `expiresAt: null` or absence means no explicit contract-level time deadline — it does not disable lifecycle invalidation or revocation. Expired bindings fail closed. Timestamp comparisons should be deterministic and UTC-oriented.

Conceptual validity formula (D-024):

```text
valid =
    binding exists
    AND not revoked
    AND lifecycle authority is still valid
    AND expiresAt has not passed
    AND referenced action id/version is available
    AND driver is supported
```

## Stale Binding

A binding whose previously issued execution authority is no longer valid (D-025) — because it is unknown or revoked, its lifecycle authority ended, `expiresAt` passed, the exact referenced action version is unavailable or no longer matches, or the runtime target instance no longer exists. Stale bindings fail closed; the runtime must not silently rediscover or substitute a "similar" replacement target.

Forbidden: component `abc` disappears → find another component of the same type → execute the old binding against it.
Correct: old binding → reject/stale; new component → new binding.

Unsupported driver is conceptually distinct from a formerly-valid stale target, but both fail closed.

## Surface Projection

Translation from core action semantics into a consumer surface such as WebMCP, MCP, or a human UI.

## Trusted Context

Runtime-authoritative state not accepted as normal caller input: actor, tenant, current record, current selection, confirmation authority, etc. Caller input can never populate trusted context; only the runtime resolves it from authenticated/request/runtime sources.

## Scope

Where the action meaning/executability depends on context.

Values (locked for v0.1):

- `portable` — the action semantics are not intrinsically dependent on a particular UI or agent surface. Execution still requires a compatible runtime binding and all required trusted context; portable does not mean literally executable everywhere. *Example: "archive invoice by ID" can be projected to any surface that provides a binding and the required context.*
- `page_scoped` — meaning/execution depends on the currently active page/application surface. *Example: "submit the current wizard step" only makes sense while that wizard page is active.*
- `browser_local` — execution is local to browser/UI state and may not have a server action equivalent. *Example: "collapse all table groups" changes only the rendered view.*
- `headless` — designed for execution in environments without a visible human web surface. *Example: "generate month-end report" is intended to run from a scheduler or agent, not a visible page.*

`headless` and `portable` are distinct: `portable` means the semantics are surface-independent; `headless` means "its intended target is a non-visual runtime."

## Effect

What observable state change execution can cause.

Values (locked for v0.1):

- `read` — *Example: fetch an order's line items.*
- `reversible_write` — *Example: update a shipping address; the previous value is retained and restorable.*
- `destructive_write` — *Example: delete a draft; recovery is not guaranteed.*
- `external_side_effect` — *Example: charge a payment via a third-party gateway; the change happens outside this application's transactional reach.*

Effect is not risk. A read can expose sensitive data; a reversible write can still be consequential.

## Risk

Level of user/business consequence requiring stronger review/confirmation.

Values (locked for v0.1):

- `low` — *Example: reorder columns in a saved view.*
- `moderate` — *Example: change a notification preference.*
- `high` — *Example: approve a refund above a threshold; review is expected but the runtime does not mandate an interactive confirmation challenge.*
- `consequential` — the top category: execution requires an opaque, runtime-issued confirmation receipt before it may proceed. `consequential` is a categorical gate, not the next step of the `low/moderate/high` scale; it must not be compared ordinally with the other values.

Risk is orthogonal to effect (D-014): a `read` of sensitive data can be `consequential`, and a `reversible_write` can be `low`.

## Idempotency policy

Values (locked for v0.1):

- `none` — the runtime does not deduplicate; callers must avoid duplicate invocations. *Example: read a dashboard snapshot.*
- `recommended_key` — if the caller supplies an idempotency key, the runtime deduplicates on it; if omitted, the invocation proceeds without deduplication. *Example: retry-safe "send welcome email".*
- `required_key` — an invocation without a valid idempotency key is rejected before execution. *Example: "create subscription charge" refuses to run twice on the same key. Enforcement is server-side (rule 9).*

T-402 implements the Laravel reference-runtime semantics behind these policies:

- An idempotency key is a caller-supplied retry token, not authorization. Runtime lexical validity is 1..240 Unicode characters; the runtime does not normalize the string before hashing.
- The raw key is never persisted. Its lookup key is a SHA-256 digest over exact Action ID/version, a trusted authority partition, and the raw key. The partition uses present tenant/actor identity; if both are absent it falls back to present browser-session identity, otherwise an explicit global partition.
- A separate intent fingerprint binds exact Action ID/version, validated input, and any present trusted actor, tenant, current-record, current-selection, and browser-session identities. Correlation ID, generic metadata, the raw idempotency key, confirmation receipt, runtime `human_confirmation`, surface, and `bindingId` are intentionally excluded.
- Idempotency preflight runs after current validation and authorization but before confirmation. A missing/invalid required key, same-key different-intent conflict, active `in_progress`, or active `indeterminate` record fails closed before confirmation/execution.
- A fresh intent is not claimed during preflight. After any required confirmation succeeds, execution atomically claims an `in_progress` record immediately before application code runs.
- Successful executor output is persisted in deterministic JSON/canonical form **before output policy/redaction** and the record becomes `completed` before the invocation may return success. Output that cannot be represented for deterministic replay becomes `indeterminate` instead of reopening execution.
- An exact active `completed` retry reruns validation and authorization, reuses the stored pre-output-policy executor output, skips confirmation and application execution, then reruns the current output policy and audit path. The retry keeps its own current correlation ID and never manufactures `human_confirmation` authority.
- Executor failure after ownership is claimed becomes `indeterminate` on a best-effort durable transition; failure to persist successful completion leaves the claim closed as `in_progress`. Neither case is automatically re-executed while active.
- The reference retention window defaults to 86,400 seconds. A record is active only while `now < expiresAt`; equality ends the bounded deduplication guarantee and permits a new claim.

These guarantees are retry/deduplication semantics, not a distributed transaction or proof of globally exactly-once business effects. External systems still need their own idempotency/transaction semantics when applicable.

## Output sensitivity

Confidentiality classification of the action's output (D-032). Drives output policy/redaction handling; independent of output content trust — a `sensitive` output may also contain untrusted content, and neither dimension suppresses the other.

Values (locked for v0.1):

- `normal` — *Example: a list of internal product categories.*
- `sensitive` — *Example: payroll amounts; redaction pipeline applies (T-403).*

## Output content trust

Whether downstream agent consumers must treat the action's output as untrusted content (D-032). Drives surface-level untrusted-content handling (e.g. WebMCP `untrustedContentHint`); independent of output sensitivity. *Example of the combined case: a private customer support message written by the customer is `sensitive` AND `contains_untrusted_content`.*

Values (locked for v0.1):

- `trusted_application_data` — *Example: server-computed aggregates with no third-party text.*
- `contains_untrusted_content` — *Example: user-generated ticket bodies that a surface must treat as untrusted.*

## Context requirement

Trusted facts required before invocation. These are declared by the action and resolved exclusively by the runtime from trusted sources — never from caller input.

Values (locked for v0.1):

- `authenticated_actor` — *Example: "my profile" needs the signed-in identity.*
- `tenant` — *Example: multi-tenant "list invoices" resolves the tenant from the session, not from a caller-supplied field.*
- `current_record` — *Example: "duplicate this order" resolves the record from the runtime's authoritative view (e.g. Filament's active record).*
- `current_selection` — *Example: "refund selected rows" uses the table's trusted selection state (D-017).*
- `browser_session` — short-lived, runtime-validated UI session state of the current browser surface. *Example: "resume the draft cart" binds to the session cart, not a caller-supplied cart ID.*
- `human_confirmation` — the action requires a human confirmation flow. Declaring this is necessary but not sufficient for consequential actions: authority comes only from successful server-side verification and atomic consumption of a runtime-issued confirmation receipt (D-015/D-044), never from caller input such as `confirmed: true` or metadata.

## Discovery authorization

Decision whether an action should be visible/discoverable in a given surface context. Discovery permission does not imply invocation permission (D-011).

## Invocation authorization

Decision whether a specific invocation may execute after resolving its input/resources/context.

## Confirmation challenge

A short-lived runtime-issued request for human approval of one exact confirmation scope. Its public `challengeId` is an opaque token while the server-side record is pending; a pending challenge is not execution authority. Trusted bridge code may approve that exact pending challenge, but it cannot rewrite its stored scope. T-504 will provide the first concrete Filament human-facing bridge.

## Confirmation receipt

A short-lived, single-use opaque bearer capability issued by the runtime after trusted human approval. The server stores only the token hash and authoritative state. The receipt is bound to exact action ID/version, validated invocation input, surface/binding reference, and relevant trusted actor/tenant/record/selection/browser-session context. It is valid only before expiry and only once; replay, mismatch, unknown, pending, or expired candidates grant no `human_confirmation` authority. Caller booleans, input, metadata, and merely constructing a `ConfirmationChallenge` never grant authority (D-015/D-044).
