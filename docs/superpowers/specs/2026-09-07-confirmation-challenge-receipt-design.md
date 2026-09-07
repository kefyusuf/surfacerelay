# T-401 Confirmation Challenge / Receipt Design

## Status

- Task: `T-401 — Confirmation challenge/receipt`
- Milestone: `M4 — Production Trust Controls`
- Base: `main@5eb33c203fb40fc2ff744f2f4a57cbce4c704b80`
- Branch: `feat/confirmation-challenge-receipt`
- Design state: approved in chat; self-reviewed and hardened before implementation.

## Goal

Implement real server-side confirmation authority for SurfaceRelay consequential / human-confirmation actions without changing the frozen `spec/0.1` wire shapes.

The runtime must issue an opaque confirmation challenge, allow a trusted human-facing bridge to approve that exact challenge, and accept the resulting receipt exactly once only when it matches the exact invocation intent and trusted runtime scope for which approval was granted.

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

A confirmation receipt is a **single-use opaque bearer capability** whose authority exists only in server-side state.

One 32-byte random token is created when the challenge is issued and encoded with unpadded base64url. The public `challengeId` is that token while its server-side record is `pending`. After trusted human approval the same opaque token becomes the `confirmationReceipt` while the server-side record is `approved`.

This state-transition model is deliberate:

```text
random opaque token
      │
      ├─ pending  → public meaning: challengeId; no execution authority
      │
      ├─ approved → public meaning: confirmationReceipt; single-use authority
      │
      └─ consumed / expired → no authority
```

The token is never self-describing and never contains actor IDs, tenant IDs, action IDs, input, binding data, expiry, signatures, or other claims. Raw tokens are never stored server-side; storage uses `sha256(token)` as the lookup key.

Using one stateful token avoids a two-record challenge→receipt handoff that could otherwise require an atomic multi-key transaction to prevent crash-induced duplicate receipts. Approval changes one locked record from `pending` to `approved`; consumption changes/deletes that same locked record. A crash cannot manufacture a second independent receipt from one approval.

The receipt grants authority only when all of the following are true:

1. its hashed record exists in the configured SurfaceRelay confirmation store;
2. the record is `approved`;
3. it has not expired;
4. it has not already been consumed;
5. its stored scope fingerprint exactly matches the current invocation scope fingerprint;
6. consumption succeeds atomically, so concurrent replay cannot execute twice.

Unknown, pending, malformed, expired, consumed, or scope-mismatched tokens never produce `human_confirmation` trusted authority.

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
- `human_confirmation` is never satisfied by action input, metadata, or mere presence of a prebuilt `TrustedContextEntry`;
- if a confirmation-required invocation reaches the confirmation stage with a pre-existing `HumanConfirmation` trusted entry, that is a runtime configuration violation and fails closed;
- real `human_confirmation` authority is materialized only by the confirmation stage after successful atomic receipt consumption.

The generic context gate remains fail-closed for actor, tenant, record, selection, and browser-session requirements.

## Invocation Data Boundary

`confirmationReceipt` is a caller-supplied **candidate**, not trusted context.

Runtime wiring must carry it separately from trusted authority. T-401 may extend the internal invocation request/context model with dedicated non-authoritative fields:

```text
bindingId: ?string
confirmationReceipt: ?string
```

These fields must not be stored as `TrustedContextEntry` values and must not be recovered from generic metadata keys.

`bindingId` remains a reference, not authorization proof. T-401 does not validate binding existence/lifecycle; it binds confirmation to the exact binding reference already selected/validated by the invoking runtime/adapter. `null` remains a distinct scope value for headless/non-binding invocations.

`correlationId` and `idempotencyKey` do not participate in confirmation scope. They identify diagnostics/retries, not the business intent being approved.

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
- finite floats encoded deterministically with JSON and `JSON_PRESERVE_ZERO_FRACTION`;
- strings;
- lists whose elements are canonical values;
- associative arrays with string keys sorted lexicographically and canonical values.

Unsupported values, including arbitrary objects/resources/closures and non-finite floats, fail closed instead of being guessed.

Trusted context may carry an optional stable **confirmation scope key** supplied by a trusted resolver/adapter. When present, this stable key is used instead of inspecting the domain value. When absent, the value may be used only if it is canonically encodable by the rules above.

The default Laravel authenticated-actor resolver provides a stable scope key derived from the trusted Laravel `Authenticatable` implementation: actor class plus `getAuthIdentifierName()` plus scalar/stringable `getAuthIdentifier()`. It never serializes the user object or records credentials/tokens.

Application tenant/current-record/current-selection/browser-session adapters are responsible for producing stable non-secret scope keys whenever their runtime value is not canonically encodable. Failure to derive a deterministic confirmation scope is a runtime configuration/security failure and occurs before challenge issuance or receipt acceptance.

### Fingerprint format

The canonical scope structure is JSON encoded without pretty printing and hashed with SHA-256. The stored authority value is the hash, not the raw canonical scope document.

The hash input begins with this internal domain separator:

```text
surfacerelay.confirmation.scope.v1\n<canonical-json>
```

This permits future internal hash evolution without silently reinterpreting old receipts. It is not a public protocol version.

## Challenge Lifecycle

When confirmation is required and no valid approved receipt is consumed, the confirmation stage returns `confirmation_required` with a real `ConfirmationChallenge`.

A newly issued challenge contains:

- a 32-byte cryptographically random opaque token encoded as unpadded base64url in `challengeId`;
- a static application-authored summary; T-401's default is the exact `ActionDefinition.title` and does not interpolate untrusted input;
- an RFC3339 UTC `expiresAt` generated from the challenge TTL.

Default challenge TTL is **300 seconds (5 minutes)**. It is configurable as a positive integer number of seconds; zero and negative values are invalid configuration.

The server stores one pending record keyed by `sha256(challengeId)` containing at minimum:

- state `pending`;
- exact scope fingerprint;
- issued timestamp;
- challenge expiry timestamp;
- static summary required to reproduce the same challenge if needed.

A pending challenge token is not execution authority. Supplying it through `confirmationReceipt` before approval does not satisfy confirmation.

## Human Approval Exchange

T-401 exposes a server-side confirmation service for a **trusted human-facing bridge** to approve a challenge.

That service is not an ActionDefinition, WebMCP tool, browser driver method, or generic public HTTP endpoint. T-504 will provide the Filament bridge later.

Approval rules:

1. hash the supplied challenge token and acquire the record's atomic lock;
2. record must exist and be `pending`;
3. `now < challengeExpiresAt` must hold; equality is expired;
4. approval cannot supply or override action/input/actor/tenant/binding/scope fields;
5. transition that exact record to `approved` under the lock;
6. set receipt expiry to `now + 120 seconds`;
7. return the same opaque token as the receipt value.

Default approved-receipt TTL is **120 seconds (2 minutes)**. It is configurable as a positive integer number of seconds; zero and negative values are invalid configuration.

Because approval mutates one locked record and does not create a second bearer token, retrying an already-approved challenge cannot mint additional capabilities. The service may return an explicit already-approved/invalid outcome to trusted bridge code, but it must never reset expiry or widen scope on repeat approval.

The bridge does not get an API that can rewrite stored scope. Approval means "approve the exact scope recorded by this challenge".

T-401 deliberately does not expose a machine-callable `approve=true` path. Human-presence UX belongs to the concrete bridge (T-504), but the core service API is designed so that bridge cannot rewrite approval scope.

## Receipt Lifecycle

The approved token is presented on the next invocation as `confirmationReceipt`.

Consumption semantics are atomic and destructive:

```text
consume(tokenHash, expectedScopeHash, now)
  -> consumed | not_valid
```

The store must guarantee that exactly one concurrent consumer can obtain `consumed` for one approved token.

A scope mismatch does **not** consume a still-valid approved receipt. This avoids turning an accidental wrong-scope attempt into a denial-of-service against the legitimate invocation. The mismatch nevertheless grants no authority and produces the same external confirmation-required behavior as other invalid candidates.

An exact-scope successful consumption deletes or irreversibly marks the record consumed before downstream idempotency/execution proceeds. If a later stage or executor fails, the receipt remains spent. Confirmation is approval for one execution attempt, not a reusable capability until success.

This single-use policy is separate from T-402 idempotency. A network/client retry after receipt consumption must obtain fresh confirmation unless T-402 later proves a safe prior-result replay path.

## Store Contract

The core confirmation logic depends on a focused `ConfirmationStore` contract rather than a facade.

The contract must support atomic operations for:

- storing one expiring pending record by token hash;
- atomically approving one exact pending record;
- atomically consuming one exact-scope approved record.

A Laravel cache-backed adapter is the reference production implementation. It is constructed with an `Illuminate\Contracts\Cache\Store`; construction fails unless that store also implements `Illuminate\Contracts\Cache\LockProvider`. This keeps the package on its existing `illuminate/contracts` dependency while requiring documented lock semantics at runtime.

Every approve/consume mutation is performed while holding a per-token distributed lock. The adapter never degrades to an unlocked get/delete sequence.

Multi-node deployments must use one shared lock-capable cache backend. Per-process or independent node caches are not sufficient production confirmation authority stores. An in-memory fake may exist only under tests; it is not the production default.

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

if context already contains HumanConfirmation:
    fail closed as runtime configuration violation

build exact confirmation scope from current validated/authorized state

if candidate receipt exists:
    hash candidate
    atomically consume only if record is approved, unexpired, and scope matches
    if consumed:
        add runtime-verified HumanConfirmation trusted entry
        continue

issue a fresh pending challenge for current exact scope
halt at confirmation stage with the real ConfirmationChallenge
```

A receipt is never verified before input validation or authorization.

The stage creates a new immutable `InvocationContext` / `ActionPipelineState` containing the verified `HumanConfirmation` entry. Existing trusted entries remain exact and unchanged.

The verified entry's value is a narrow runtime-owned marker/value object rather than the raw receipt. Its provenance provider is `surfacerelay.confirmation`; provenance contains no raw token, token hash, or scope hash.

## Result Normalization

The internal confirmation halt carries the real `ConfirmationChallenge` object through a narrow typed path.

`ActionResultNormalizer` maps this known halt to:

```text
ActionResult::confirmationRequired(correlationId, challenge)
```

It must not reconstruct a fake challenge from arbitrary halt details and must not expose token hashes, scope hashes, actor identifiers, tenant identifiers, input fingerprints, provenance secrets, or cache keys in public result metadata.

Unknown/malformed confirmation halts continue to fail loudly as programming/configuration errors.

## External Failure Behavior

Caller-visible behavior intentionally avoids becoming a confirmation-token oracle.

After authorization succeeds, the following conditions all grant no authority and produce `confirmation_required` with a valid challenge for the current exact scope:

- missing receipt;
- unknown token;
- pending/unapproved token;
- expired token;
- already-consumed/replayed token;
- token approved for another action/version;
- input mismatch;
- actor mismatch;
- tenant mismatch;
- binding mismatch;
- current-record mismatch;
- current-selection mismatch;
- browser-session mismatch.

Malformed runtime configuration, pre-materialized `HumanConfirmation`, inability to derive deterministic trusted scope, unavailable required store atomicity, random-source failure, or corrupt stored records are not ordinary caller mistakes. They fail closed as runtime exceptions and must not be converted into a fake approval challenge.

## Expiry and Clock

Clock access is injected behind a tiny runtime clock contract or equivalent deterministic callable so unit tests never depend on wall time.

Defaults are fixed for T-401 and remain constructor/config overrides rather than protocol fields:

```text
challenge TTL = 300 seconds
receipt TTL   = 120 seconds
```

Expiry comparison semantics are exact:

```text
valid only when now < expiresAt
```

At `now == expiresAt`, the challenge/receipt is expired.

Public `ConfirmationChallenge.expiresAt` is UTC RFC3339 with second precision and preserves the existing contract shape.

## Secret Handling

Raw tokens are bearer credentials after approval and are treated as secrets:

- never stored in server-side confirmation records; only SHA-256 lookup hashes are stored;
- never stored in logs/audit metadata;
- never added to `ActionResult.meta`;
- never added to trusted-context provenance/reference;
- never included in exceptions;
- never included in halt details;
- compared through token-hash lookup rather than logging/debugging values.

The token is visible as `challengeId` while pending because that is required by the existing public contract, but pending state grants no execution authority.

## Compatibility

T-401 must preserve:

- `ActionDefinition` protocol neutrality;
- existing WebMCP projection semantics;
- existing RuntimeBinding semantics;
- Livewire driver behavior;
- T-305 cancellation frontier;
- all non-confirmation action behavior;
- `spec/0.1` schemas and wire shapes;
- D-030 extensible ActionError code semantics;
- T-402/T-403/T-404 as separate tasks.

No browser-specific or Filament-specific code belongs in confirmation core.

## Decision Update

T-401 should add an accepted decision, expected as D-044 unless another decision is allocated first:

> Confirmation grants are single-use opaque bearer capabilities represented by a runtime-issued token whose server-side state transitions from pending challenge to approved receipt. Authority is bound to exact action version, validated invocation intent, binding/surface, and relevant trusted runtime context. Only successful atomic server-side receipt consumption may materialize `human_confirmation`; challenge IDs, caller fields, and prebuilt trusted entries are never sufficient authority.

A second decision is warranted only if implementation freezes a general trusted-context scope-key contract for adapters. Avoid additional ADR vocabulary unless the code genuinely exposes that concept.

## Security Tests

At minimum T-401 must prove these negative/positive behaviors with executable tests:

1. consequential action without receipt does not execute and returns a real challenge;
2. action declaring `human_confirmation` without consequential risk follows the same gate;
3. consequential action that accidentally omits `human_confirmation` still requires confirmation;
4. caller input/metadata containing `confirmed=true` grants no authority;
5. prebuilt `HumanConfirmation` trusted context cannot bypass receipt verification;
6. unknown token does not execute;
7. pending/unapproved token does not execute;
8. expired pending challenge cannot be approved;
9. expired approved receipt does not execute;
10. already-consumed receipt cannot execute a second time;
11. two concurrent consumers cannot both consume one receipt;
12. re-approving one challenge cannot mint/reset a second receipt authority;
13. receipt for another action ID fails;
14. receipt for another action version fails;
15. changed validated input fails;
16. changed actor fails;
17. changed tenant fails;
18. changed binding fails;
19. changed current record fails;
20. changed current selection fails;
21. changed browser session fails;
22. unsupported/non-deterministic scope value fails closed;
23. valid exact receipt executes once and executor observes runtime-verified `HumanConfirmation` trusted context;
24. receipt is spent even when a later stage/executor fails;
25. non-confirmation low-risk actions remain unchanged;
26. raw tokens do not appear in stored records, results, halt details, exceptions, or provenance.

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

> After authorization, an invocation that requires human confirmation can proceed only by presenting one unexpired, human-approved, runtime-issued opaque token whose exact stored scope matches the current validated invocation and trusted runtime context, and whose single-use authority is atomically consumed before execution.

Anything broader belongs to a later task.