# T-401 Confirmation Challenge / Receipt Design

## Status

- Task: `T-401 — Confirmation challenge/receipt`
- Milestone: `M4 — Production Trust Controls`
- Base: `main@5eb33c203fb40fc2ff744f2f4a57cbce4c704b80`
- Branch: `feat/confirmation-challenge-receipt`
- Design state: approved in chat; this document records the implementation contract before code changes.

## Goal

Implement real server-side confirmation authority for SurfaceRelay consequential / human-confirmation actions without changing the frozen `spec/0.1` wire shapes.

The runtime must issue an opaque confirmation challenge, allow a trusted human-facing bridge to exchange that challenge for an opaque receipt, and accept that receipt exactly once only when it matches the exact invocation intent and trusted runtime scope for which approval was granted.

The caller must never be able to manufacture confirmation authority with action input, metadata, `confirmed=true`, a forged token, a stale token, or a token issued for another action, actor, tenant, binding, record, selection, session, or input.

## Existing Contract

T-401 builds on existing frozen behavior rather than inventing a new public protocol:

- `ActionRisk::Consequential` is already defined as a categorical confirmation gate.
- `ContextRequirement::HumanConfirmation` already exists.
- `Invocation.confirmationReceipt` is already `string|null` with a 4096-byte maximum.
- `ActionResult.status=confirmation_required` and `ConfirmationChallenge { challengeId, summary, expiresAt }` already exist.
- `ConfirmationChallenge` is intentionally data-only; issuance, verification, scope binding, expiry, and storage were deferred to T-401.
- D-007 forbids caller-manufactured confirmation authority.
- D-015 requires opaque, scoped, expiring runtime-issued receipts.
- Threat-model T11 requires receipt binding to actor, tenant, action, relevant input/binding, expiry, and replay policy.

`spec/0.1` therefore remains frozen for T-401 unless implementation proves an unavoidable contradiction. No such contradiction is currently known.

## Non-goals

T-401 does not implement:

- T-402 idempotency-key storage or duplicate side-effect handling;
- T-403 output policy/redaction;
- T-404 structured audit events;
- T-504 Filament confirmation UI/bridge;
- a browser modal or generic approval endpoint;
- a JWT/JWS receipt format;
- public receipt claims or receipt introspection;
- a new crypto key-management subsystem;
- cross-framework public confirmation APIs beyond the existing `spec/0.1` fields;
- rollback, cancellation, or reversal semantics;
- application-specific confirmation copy containing dynamic user data.

## Core Security Model

A confirmation receipt is a **single-use opaque bearer capability**.

The only public value is a high-entropy random token. The token is never self-describing and never contains actor IDs, tenant IDs, action IDs, input, binding data, expiry, signatures, or other claims.

Server-side storage owns all receipt semantics. Raw receipt tokens are not stored; only a one-way SHA-256 token hash is stored. A stolen server-side confirmation record must therefore not directly reveal a usable receipt token.

The receipt grants authority only when all of the following are true:

1. it was issued by the configured SurfaceRelay confirmation service;
2. it exists in the server-side store;
3. it has not expired;
4. it has not already been consumed;
5. its stored scope fingerprint exactly matches the current invocation scope fingerprint;
6. consumption succeeds atomically, so concurrent replay cannot execute twice.

Unknown, malformed, expired, already-consumed, or scope-mismatched receipts never produce `human_confirmation` trusted authority.

## Confirmation Requirement Rule

The confirmation stage owns the final runtime gate.

Confirmation is required when either condition is true:

```text
ActionDefinition.risk == consequential
OR
ActionDefinition.contextRequirements contains human_confirmation
```

This deliberately prevents a misconfigured consequential action from becoming executable merely because its definition omitted `human_confirmation`.

The ActionBus's pre-stage required-context gate therefore changes narrowly:

- every declared trusted context requirement except `human_confirmation` is still checked before pipeline stages;
- `human_confirmation` is never accepted merely because a caller or earlier generic composer inserted a value;
- real `human_confirmation` authority is materialized only by the confirmation stage after successful receipt consumption.

The generic context gate remains fail-closed for actor, tenant, record, selection, and browser-session requirements.

## Invocation Data Boundary

`confirmationReceipt` is a caller-supplied **candidate**, not trusted context.

Runtime wiring must carry it separately from trusted authority. T-401 may extend the internal invocation request/context model with dedicated non-authoritative fields such as:

```text
bindingId: ?string
confirmationReceipt: ?string
```

These fields must not be stored as `TrustedContextEntry` values and must not be recoverable from generic metadata keys.

`bindingId` remains a reference, not authorization proof. When an invocation has an exact validated binding reference, that value participates in confirmation scope binding; `null` remains a distinct scope value for headless/non-binding invocations.

`correlationId` and `idempotencyKey` do not participate in the confirmation scope. They identify diagnostics/retries, not the business intent being approved.

## Confirmation Scope

A receipt is bound to the exact approval intent through one deterministic scope fingerprint.

The scope includes:

1. action ID;
2. exact action version;
3. `surface`;
4. exact `bindingId` or explicit `null`;
5. the pipeline input **after input validation/transformation and authorization has succeeded**;
6. each relevant trusted context entry present for:
   - `authenticated_actor`;
   - `tenant`;
   - `current_record`;
   - `current_selection`;
   - `browser_session`.

`human_confirmation` itself is excluded from scope construction to avoid circular authority.

The scope is intentionally stricter than actor+tenant. A receipt issued for one selected record set must not authorize a later selection, and a receipt issued for one runtime binding must not authorize a replacement binding.

### Canonical scope encoding

Scope hashing must not use PHP `serialize()` on arbitrary application objects and must not rely on object identity, memory address, debug output, or non-deterministic property order.

The canonical encoder accepts only deterministic values:

- `null`;
- booleans;
- integers;
- finite floats using deterministic JSON encoding;
- strings;
- lists whose elements are canonical values;
- associative arrays with string keys sorted lexicographically and canonical values.

Unsupported values, including arbitrary objects/resources/closures, fail closed instead of being guessed.

Trusted context may therefore carry an optional stable **confirmation scope key** supplied by a trusted resolver/adapter. When present, this stable key is used instead of inspecting the domain value. When absent, the value may be used only if it is canonically encodable by the rules above.

The default Laravel authenticated-actor resolver should provide a stable scope key derived from trusted Laravel `Authenticatable` identity rather than serializing the user object. Application tenant/current-record/current-selection/browser-session adapters are responsible for producing stable non-secret scope keys whenever their runtime value is not canonically encodable.

Failure to derive a deterministic confirmation scope is a runtime configuration/security failure and must occur before challenge issuance or receipt acceptance.

### Fingerprint format

The canonical scope structure is JSON encoded without pretty printing and hashed with SHA-256. The stored authority value is the hash, not the raw canonical scope document.

A versioned internal domain separator must be included, for example:

```text
surfacerelay.confirmation.scope.v1\n<canonical-json>
```

This permits future internal hash evolution without silently reinterpreting old receipts. It is not a public protocol version.

## Challenge Lifecycle

When confirmation is required and no valid receipt is consumed, the confirmation stage returns `confirmation_required` with a real `ConfirmationChallenge`.

A newly issued challenge contains:

- a cryptographically random opaque `challengeId`;
- a static application-authored summary; T-401's default is the exact `ActionDefinition.title` and does not interpolate untrusted input;
- an RFC3339 `expiresAt` generated from a configured short challenge TTL.

The server-side challenge record contains at minimum:

- hashed/opaque challenge lookup identity as appropriate for the selected store;
- exact scope fingerprint;
- issuance timestamp;
- expiry timestamp;
- challenge state required to guarantee one approval exchange.

A challenge is not authority and cannot be supplied as a receipt.

## Human Approval Exchange

T-401 exposes a server-side confirmation service for a **trusted human-facing bridge** to approve a challenge.

That service is not an ActionDefinition, WebMCP tool, browser driver method, or generic public HTTP endpoint. T-504 will provide the Filament bridge later.

Approval exchange rules:

1. challenge must exist;
2. challenge must be unexpired;
3. challenge may be exchanged at most once;
4. the bridge must execute in trusted application/runtime code, not from generic caller input;
5. successful exchange deletes/consumes the challenge atomically and returns one new random opaque receipt token;
6. only the SHA-256 hash of that receipt token is stored with the challenge's original scope fingerprint and receipt expiry.

The bridge does not get an API that can override the stored scope. Approval means "approve the exact scope recorded by this challenge", not "approve this challenge ID for new caller-supplied parameters".

T-401 deliberately does not expose a machine-callable `approve=true` path. Human-presence UX belongs to the concrete bridge (T-504), but the core service API is designed so the bridge cannot rewrite the approval scope.

## Receipt Lifecycle

Receipt tokens use `random_bytes()` with enough entropy to make online guessing infeasible and are encoded as URL-safe text that fits well below the existing 4096-byte invocation limit.

Receipt storage records at minimum:

- SHA-256 token hash;
- exact confirmation scope fingerprint;
- issued timestamp;
- expiry timestamp.

Consumption semantics are atomic and destructive:

```text
consume(tokenHash, expectedScopeHash, now)
  -> consumed | not_valid
```

The store must guarantee that exactly one concurrent consumer can obtain `consumed` for one receipt.

A scope mismatch does **not** consume a still-valid receipt. This avoids turning an accidental wrong-scope attempt into a denial-of-service against the legitimate invocation. The mismatch nevertheless grants no authority and returns the same external confirmation-required behavior as other invalid receipts.

An exact-scope successful consumption removes/marks the receipt consumed before downstream execution proceeds. If later stages fail, the receipt is still spent. Confirmation is approval for one execution attempt, not a reusable capability until success.

This single-use policy is separate from T-402 idempotency. A network/client retry after receipt consumption must obtain fresh confirmation unless T-402 later proves a safe prior-result replay path.

## Store Contract

The core confirmation logic depends on a focused `ConfirmationStore` contract rather than a concrete cache facade.

The contract must support atomic operations for:

- storing an expiring challenge;
- atomically taking/exchanging one unexpired challenge;
- storing an expiring receipt record;
- atomically consuming one exact-scope unexpired receipt.

A Laravel cache-backed adapter is the reference production implementation. It uses an application-provided cache repository/store and distributed atomic locks for challenge exchange / receipt consumption. Laravel's documented atomic-lock requirement means multi-node deployments must use a supported shared cache backend; per-process or independent node caches are not sufficient production authority stores.

The adapter must fail closed when the configured cache store cannot provide the required lock semantics rather than silently degrading to a non-atomic get/delete sequence.

An in-memory fake may exist only under tests; it is not the production default.

## Pipeline Integration

The canonical pipeline order remains:

```text
input_validation
→ authorization
→ confirmation
→ idempotency
→ execution
→ output_policy
```

Confirmation intentionally runs after authorization so unauthorized callers do not receive valid challenges for operations they cannot invoke.

Detailed confirmation-stage behavior:

```text
if action does not require confirmation:
    continue unchanged

build exact confirmation scope from current validated state

if candidate receipt exists:
    hash candidate
    atomically consume only if receipt is unexpired and scope matches
    if consumed:
        add runtime-verified HumanConfirmation trusted entry
        continue

issue a fresh challenge for current exact scope
halt at confirmation stage with the real ConfirmationChallenge
```

A receipt is never verified before input validation or authorization.

The stage may create a new immutable `InvocationContext` / `ActionPipelineState` containing the verified `HumanConfirmation` entry. Existing trusted entries remain exact and unchanged.

The provenance of this entry is runtime-generated, for example provider `surfacerelay.confirmation`; it contains no raw receipt token.

## Result Normalization

The internal confirmation halt carries the real `ConfirmationChallenge` object through a narrow typed path.

`ActionResultNormalizer` maps this known halt to:

```text
ActionResult::confirmationRequired(correlationId, challenge)
```

It must not reconstruct a fake challenge from arbitrary halt details and must not expose receipt hashes, scope hashes, actor identifiers, tenant identifiers, input fingerprints, provenance secrets, or cache keys in public result metadata.

Unknown/malformed confirmation halts continue to fail loudly as programming/configuration errors.

## External Failure Behavior

Caller-visible behavior intentionally avoids becoming a confirmation-token oracle.

The following conditions all grant no authority and result in a fresh `confirmation_required` challenge after authorization succeeds:

- missing receipt;
- syntactically non-empty but unknown receipt;
- expired receipt;
- already-consumed/replayed receipt;
- receipt issued for another action/version;
- input mismatch;
- actor mismatch;
- tenant mismatch;
- binding mismatch;
- current-record mismatch;
- current-selection mismatch;
- browser-session mismatch.

Malformed runtime configuration, inability to derive deterministic trusted scope, unavailable required store atomicity, random-source failure, or corrupt stored records are not ordinary caller mistakes. They fail closed as runtime exceptions and must not be converted into a fake approval challenge.

## Expiry and Clock

Clock access is injected behind a tiny runtime clock contract or equivalent deterministic callable so unit tests never depend on wall time.

Default TTLs should be conservative and configurable by constructor/config rather than protocol fields. The implementation plan will choose exact defaults and test boundaries explicitly.

Expiry comparison semantics are exact:

```text
valid only when now < expiresAt
```

At `now == expiresAt`, the challenge/receipt is expired.

Public `ConfirmationChallenge.expiresAt` remains RFC3339 and preserves the existing contract shape.

## Secret Handling

Raw receipt tokens are treated as secrets/bearer credentials:

- never stored in logs/audit metadata;
- never added to `ActionResult.meta`;
- never added to trusted-context provenance/reference;
- never included in exceptions;
- never included in challenge records after hashing where storage can avoid it;
- compared through token-hash lookup rather than logging/debugging values.

Challenge IDs are opaque identifiers but are not execution authority.

## Compatibility

T-401 must preserve:

- `ActionDefinition` protocol neutrality;
- existing WebMCP projection semantics;
- existing RuntimeBinding semantics;
- Livewire driver behavior;
- T-305 cancellation frontier;
- all non-confirmation action behavior;
- `spec/0.1` schema/fixture counts unless an executable runtime-conformance fixture is intentionally added without changing schemas;
- D-030 extensible ActionError code semantics;
- T-402/T-403/T-404 as separate tasks.

No browser-specific or Filament-specific code belongs in confirmation core.

## Decision Update

T-401 should add an accepted decision, expected as D-044 unless another decision is allocated first:

> Confirmation grants are single-use opaque bearer capabilities bound to exact action version, validated invocation intent, binding/surface, and relevant trusted runtime context. Only successful server-side receipt verification/consumption may materialize `human_confirmation` authority; challenge IDs and caller fields are never authority.

A second decision is warranted only if implementation needs to freeze a general trusted-context scope-key contract. Avoid creating additional ADR vocabulary unless the code genuinely exposes that concept to adapters.

## Security Tests

At minimum T-401 must prove these negative/positive behaviors with executable tests:

1. consequential action without receipt does not execute and returns a real challenge;
2. action declaring `human_confirmation` without consequential risk follows the same gate;
3. consequential action that accidentally omits `human_confirmation` still requires confirmation;
4. caller input/metadata containing `confirmed=true` or a fake human-confirmation value grants no authority;
5. unknown receipt does not execute;
6. expired receipt does not execute;
7. already-consumed receipt cannot execute a second time;
8. two concurrent consumers cannot both consume one receipt;
9. receipt for another action ID fails;
10. receipt for another action version fails;
11. changed validated input fails;
12. changed actor fails;
13. changed tenant fails;
14. changed binding fails;
15. changed current record fails;
16. changed current selection fails;
17. changed browser session fails;
18. unsupported/non-deterministic scope value fails closed;
19. valid exact receipt executes once and executor observes runtime-verified `HumanConfirmation` trusted context;
20. receipt is spent even when a later stage/executor fails;
21. non-confirmation low-risk actions remain unchanged;
22. raw receipt tokens do not appear in results, halt details, or provenance.

The implementation must use TDD: each production behavior begins with a failing test whose failure proves the missing behavior.

## Required Verification

Before T-401 can be marked DONE:

- focused confirmation unit/integration tests pass;
- full Laravel PHPUnit suite passes;
- browser TypeScript/Vitest baseline passes unchanged;
- `python scripts/validate.py` passes;
- full diff is reviewed for trust-boundary leaks and unrelated M4 work;
- `TASKS.md`, `STATUS.md`, `docs/DECISION-REGISTER.md`, and `REVIEW_REQUEST.md` are updated with exact evidence only after verification;
- no claim is made that T-402 idempotency or T-504 human UI bridge is implemented.

## Acceptance Boundary

T-401 is complete when SurfaceRelay can truthfully make this statement:

> After authorization, an invocation that requires human confirmation can proceed only by presenting one unexpired, previously human-approved, runtime-issued opaque receipt whose exact stored scope matches the current validated invocation and trusted runtime context, and whose single-use authority is atomically consumed before execution.

Anything broader belongs to a later task.