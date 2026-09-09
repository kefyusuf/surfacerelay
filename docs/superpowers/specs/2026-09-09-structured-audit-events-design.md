# T-404 Structured Audit Events Design

## Status

- Task: `T-404 — Structured audit events`
- Milestone: `M4 — Production Trust Controls`
- Base: `main@a3112dea3f965e27db8e6904650c97f89d2261fe`
- Branch: `feat/structured-audit-events`
- Design state: approved in chat and self-reviewed
- Decision: `D-047 — ACCEPTED`

## Goal

Persist one structured, append-only, payload-minimized audit record for every `ActionBus` dispatch that reaches the existing finalization boundary.

T-404 is a production trust control, not an event-sourcing, analytics, SIEM, or business-payload archive. A record must identify the exact Action/version, invocation labels, declared trust semantics, final completed/halted state, explicit halt location/code, trusted-context provenance classes, trusted confirmation presence, and server finalization time without persisting invocation/business payloads or bearer capabilities.

## Existing Boundary

The current canonical flow ends with exactly one auditor call for outcomes represented as `ActionPipelineOutcome`:

```text
exact action resolution
→ trusted context gate
→ input_validation
→ authorization
→ idempotency
→ confirmation
→ execution
→ output_policy
→ ActionPipelineAuditor::record(call, outcome)
```

The finalizer is reached for completed outcomes and explicit halted outcomes. T-403 already removes raw sensitive output before a failed output-policy outcome reaches it.

A PHP exception that escapes before `ActionBus::finalize()` currently has no `ActionPipelineOutcome` and is outside this contract. T-404 does not redefine arbitrary exceptions as audit events.

## D-047

**Structured audit is an append-only, payload-minimized final-dispatch record. Only explicitly allowlisted action/outcome/provenance facts are persisted; raw invocation/business payloads, trusted identity values, and bearer capabilities are never audit persistence data. Audit-store failure propagates rather than being downgraded to best-effort logging.**

Adding fields to `ActionCall`, `InvocationContext`, `ActionPipelineState`, or `ActionDefinition` must never implicitly add persisted audit fields.

## Non-goals

T-404 does not implement:

- stage-by-stage event streaming;
- event sourcing or state reconstruction;
- a generic domain-event bus;
- business payload history;
- raw input/output persistence;
- exception text or stack-trace persistence;
- SIEM/OpenTelemetry/export pipelines;
- audit signing/hash chains/non-repudiation;
- custom encryption/KMS;
- retention cleanup;
- audit query/reporting APIs or UI;
- automatic audit retry/outbox;
- changes to `spec/0.1/**`, ActionResult taxonomy, WebMCP or browser contracts;
- durable records for every arbitrary PHP `Throwable`.

## Approaches

### Stage-by-stage event stream — rejected

This would require ordering, duplicate, exception, retention and replay semantics and would turn T-404 into a separate event subsystem.

### Persist whole runtime snapshots — rejected

`ActionCall`/`ActionPipelineOutcome` transitively expose raw input/output, metadata, idempotency candidates, confirmation receipts, binding references and trusted context values. Snapshot serialization makes leakage the default.

### Explicit allowlist projection — accepted

A dedicated factory projects only approved facts into an immutable `AuditEvent`; an append-only store persists it.

## Event Model

```text
AuditEvent
  eventId
  recordedAt
  correlationId
  surface

  actionId
  actionVersion
  actionScope
  actionEffect
  actionRisk
  idempotencyPolicy
  outputSensitivity
  outputContentTrust

  outcomeKind          // completed | halted
  haltedAt             // pipeline stage | null
  haltCode             // machine code | null

  humanConfirmationPresent
  trustedContextManifest[]
```

### Event ID

`eventId` is server-generated with `random_bytes(16)` and encoded as exactly 32 lowercase hexadecimal characters. It is opaque and contains no time, user, tenant, action, payload or authority meaning. The database primary key enforces uniqueness.

### Timestamp

`recordedAt` is produced by an injected `AuditClock` as a UTC `DateTimeImmutable`. `SystemAuditClock` uses current UTC wall-clock time. Persistence writes explicit UTC `Y-m-d H:i:s.u` into a `DATETIME(6)` column so storage does not depend on the database session timezone.

`IdempotencyClock` is not reused: it is deliberately idempotency-specific and second-based, while audit has independent finalization-time and microsecond-precision semantics.

### Invocation labels

Persist exact final `correlationId` and `surface` as diagnostic labels. They remain non-authoritative.

They have no existing runtime max length, so the database stores them as `LONGTEXT` rather than introducing a new invocation-validation limit. Equality-query helper hashes are stored separately for correlation ID only.

Generic `InvocationContext::metadata` is never persisted.

### Action identity and declared trust semantics

Persist from the exact resolved final `ActionDefinition`:

- `id`, `version`;
- `scope`, `effect`, `risk`;
- `idempotency`;
- `outputSensitivity`;
- `outputContentTrust`.

Do not persist title, description, schemas, extensions or other application-defined containers.

### Outcome

`outcomeKind` is exactly `completed` or `halted`.

For completed events:

- `haltedAt = null`;
- `haltCode = null`.

For halted events:

- `haltCode` is the exact machine-readable internal halt code;
- `haltedAt` is the canonical pipeline-stage value when a stage halted;
- `haltedAt = null` is valid for the built-in pre-stage trusted-context gate.

Halt `details`, confirmation challenge content, public messages and exception diagnostics are not persisted.

### Confirmation evidence

Persist only:

```text
humanConfirmationPresent: bool
```

It is derived from final trusted context presence of `ContextRequirement::HumanConfirmation`. It is evidence only and never reusable authority.

Do not persist receipt, challenge token/ID/payload, confirmation scope key, receipt hash, or `VerifiedConfirmation` value.

### Trusted context manifest

Persist one entry per final trusted context entry, in the canonical order already provided by `InvocationContext::allTrusted()`:

```json
{"requirement":"authenticated_actor","provider":"laravel.auth"}
```

Only `requirement` and `ContextProvenance::provider` are allowed.

Never persist:

- trusted context value;
- `ContextProvenance::reference`;
- `confirmationScopeKey`;
- canonicalized identity/scope value;
- actor, tenant, record, selection or browser-session identifiers.

## Explicit Forbidden Persistence Set

Adversarial tests must prove that persisted columns/JSON contain none of these sources:

- raw or validated action input;
- normal output;
- sensitive raw or released output;
- generic metadata;
- raw idempotency key, lookup hash, intent fingerprint or replay payload;
- binding ID;
- confirmation receipt/challenge material;
- trusted context values;
- provenance reference;
- confirmation/scope key;
- halt details;
- exception text/stack trace;
- ActionDefinition schemas/extensions.

## Components

```text
ActionBus final outcome
        ↓
StructuredActionPipelineAuditor
        ↓
AuditEventFactory ── AuditClock
        ↓
AuditEventStore::append(AuditEvent)
        ↓
DatabaseAuditEventStore
        ↓
surfacerelay_audit_events
```

### `StructuredActionPipelineAuditor`

Implements the existing `ActionPipelineAuditor` signature unchanged. It builds exactly one event and appends it exactly once per finalizer call. It never mutates the outcome and never suppresses factory/store failure.

### `AuditEventFactory`

Owns the semantic allowlist projection. It reads only the explicit event fields defined above and never serializes whole runtime objects. Database-only index helper hashes are deliberately not part of `AuditEvent`.

### `AuditClock`

```php
public function now(): DateTimeImmutable;
```

The factory rejects a non-UTC clock result as an internal configuration violation rather than silently interpreting an ambiguous timestamp.

### `AuditEventStore`

```php
public function append(AuditEvent $event): void;
```

No update, delete, upsert or query operation belongs to the T-404 core write contract.

### `DatabaseAuditEventStore`

Uses `Illuminate\Database\ConnectionInterface`, mirroring the narrow database boundary used by T-402.

Each `append()` is one insert. There is no upsert/update fallback. Duplicate IDs fail.

The database adapter derives the domain-separated `correlation_hash` and nullable `halt_code_hash` equality-index helper values from the semantic event immediately before insert. Those hashes are persistence/index artifacts only and never become audit-event fields or runtime authority.

Raw `QueryException`/driver diagnostics are not propagated directly. The adapter converts database failures to a static `AuditStoreUnavailable` runtime exception without SQL/row diagnostics or a chained database exception, matching the existing T-402 secrecy pattern. The auditor itself does not catch that domain failure.

## Database Schema

Table: `surfacerelay_audit_events`.

```text
event_id                       CHAR(32) PRIMARY KEY
recorded_at                    DATETIME(6) NOT NULL
correlation_id                 LONGTEXT NOT NULL
correlation_hash               CHAR(64) NOT NULL
surface                        LONGTEXT NOT NULL
action_id                      VARCHAR(160) NOT NULL
action_version                 INTEGER UNSIGNED NOT NULL
action_scope                   VARCHAR(32) NOT NULL
action_effect                  VARCHAR(32) NOT NULL
action_risk                    VARCHAR(32) NOT NULL
idempotency_policy             VARCHAR(32) NOT NULL
output_sensitivity             VARCHAR(32) NOT NULL
output_content_trust           VARCHAR(64) NOT NULL
outcome_kind                   VARCHAR(16) NOT NULL
halted_at                      VARCHAR(32) NULL
halt_code                      LONGTEXT NULL
halt_code_hash                 CHAR(64) NULL
human_confirmation_present     BOOLEAN NOT NULL
trusted_context_manifest       JSON NOT NULL
```

`correlation_hash` is an equality-index helper only:

```text
SHA-256("surfacerelay.audit.correlation.v1\n" + correlationId)
```

`halt_code_hash` is null for completed events and otherwise:

```text
SHA-256("surfacerelay.audit.halt-code.v1\n" + haltCode)
```

These hashes do not confer authority and are not idempotency/identity fingerprints. They avoid adding length limits merely to make open-length diagnostic strings indexable.

Indexes beyond the primary key:

- `recorded_at`;
- `correlation_hash`;
- `(action_id, action_version, recorded_at)`;
- `(outcome_kind, halt_code_hash, recorded_at)`.

`trusted_context_manifest` is JSON containing only a list of `{requirement, provider}` string pairs. Encoding failure is a runtime failure; no PHP serialization or silent entry dropping is allowed.

No ORM model is introduced solely for insert-only persistence.

## Append-only Meaning

SurfaceRelay exposes only insertion of new audit events. It does not expose prior-event mutation or deletion APIs.

This is an application-level contract, not a claim that a database administrator, retention job or backup operator can never alter rows. WORM storage, regulatory retention and tamper evidence remain deployment/future concerns.

## Failure Semantics

Audit persistence is a trust control, not best-effort logging.

If audit construction/persistence fails:

- the failure propagates;
- `ActionBus` does not return the original finalized outcome as if audit succeeded;
- SurfaceRelay does not claim the application side effect was rolled back;
- T-402 durable idempotency state remains authoritative and unchanged;
- a business/external side effect may already have occurred.

T-404 adds no automatic internal retry. A database commit may be ambiguous; blind retry can create duplicate evidence. Retry/outbox semantics need a separate design.

## Finalization / Throwable Boundary

T-404 guarantees:

> Every dispatch that reaches the existing completed/explicit-halt finalization boundary attempts exactly one structured audit append.

It does **not** guarantee durable audit for every PHP `Throwable`. Exceptions that escape before `ActionBus::finalize()` remain outside this event model.

Broadening this would require a safe exception taxonomy, execution-occurrence semantics and different orchestration. That work is deliberately excluded rather than partially implemented.

## Idempotency Replay

A completed replay is a new invocation/finalization, so it creates a new audit event with a new event ID/time and the current correlation ID/surface.

The replay event records no replay payload, key/hash/fingerprint or prior audit ID. It may correctly have `humanConfirmationPresent=false` because exact completed replay skips confirmation. Thus two finalized invocation attempts may have two audit events while T-402 still guarantees one protected application execution.

## T-403 Interaction

T-404 never persists output, so audit cannot become a secondary disclosure path.

For `output_policy_failed`:

```text
outcomeKind = halted
haltedAt    = output_policy
haltCode    = output_policy_failed
```

No raw or released output is persisted. The same rule applies to successful sensitive releases.

## Compatibility

T-404 does not change:

- `spec/0.1/**`;
- ActionDefinition/Invocation/ActionResult wire shapes;
- public result taxonomy;
- WebMCP/browser contracts;
- `ActionPipelineAuditor::record(ActionCall, ActionPipelineOutcome)` signature.

Existing custom/test auditors remain source-compatible. The structured auditor is a new implementation of the existing port.

## Testing

### Unit

Prove event factory:

- exact action/version/trust enum projection;
- completed/halted consistency;
- pre-stage `haltedAt=null` support;
- halt code included but details excluded;
- confirmation boolean derived only from trusted final context;
- canonical trusted-context manifest order;
- provider included while value/reference/scope key excluded;
- opaque 32-char lowercase hex event ID;
- UTC clock requirement and microsecond preservation.

Prove database persistence projection separately:

- `correlation_hash` uses the exact domain-separated SHA-256 input;
- completed events persist `halt_code_hash=null`;
- halted events use the exact domain-separated halt-code SHA-256 input.

### Adversarial secrecy

Place unique markers in every forbidden source and assert that no inserted scalar/JSON value contains any marker.

### Auditor

Prove one finalizer call causes one append, completed and halted each append once, and a store/factory failure propagates without outcome mutation.

### MySQL integration

Across PHP 8.3/8.4 × Illuminate 12/13 with MySQL 8.4:

- migration up/down;
- completed append;
- explicit halt append;
- output-policy-failure append with no output leak;
- consequential confirmed fresh execution shows confirmation presence;
- exact completed idempotency replay creates a second event without re-execution;
- duplicate event ID fails instead of mutating;
- manifest contains only allowlisted keys;
- UTC microsecond timestamp round-trip;
- defined indexes/schema exist.

### Full regression

- PHP tests;
- PHP lint;
- browser TypeScript typecheck + Vitest;
- `python scripts/validate.py`;
- `spec/0.1/**` unchanged.

## Acceptance Criteria

T-404 is complete when:

1. immutable allowlisted `AuditEvent` exists;
2. structured auditor implements the existing finalizer port;
3. append-only store contract exists;
4. database store + migration persist records on MySQL 8.4;
5. forbidden payload/authority sources are absent from persistence;
6. completed/halted/stage/code semantics are exact;
7. trusted context is limited to requirement + provider;
8. confirmation persistence is only authority-presence boolean;
9. replay creates a separate audit event without replay/business payload;
10. T-403 output cannot leak through audit;
11. audit failures propagate through static safe domain errors;
12. collision never mutates an existing record;
13. auditor signature and frozen wire contracts remain unchanged;
14. full CI matrix is green.

## Self-review

- **Placeholder scan:** exact schema types/lengths/index strategy are specified; no `TBD`/`TODO`/placeholder lengths remain.
- **Secrecy correction:** even T-403 released output is excluded; audit is not a second business-data store.
- **Identity minimization:** trusted values and provenance references are excluded; requirement + provider is the maximum persisted trusted-context detail.
- **Database diagnostic correction:** raw SQL/driver exceptions are wrapped in static non-chained domain errors.
- **Compatibility correction:** open-length correlation/surface/halt values are stored without adding new runtime validation limits; equality indexes use domain-separated hashes where needed.
- **Responsibility correction:** equality-index hashes are persistence-only and do not pollute the semantic `AuditEvent` contract.
- **Exception honesty:** arbitrary thrown exceptions are explicitly outside the current final-outcome boundary.
- **Retry restraint:** no blind audit retry/outbox semantics are introduced.
- **Scope check:** no event sourcing, telemetry platform, query API, retention system or UI is introduced.

## Next Step

After user review of this written spec, create the detailed T-404 implementation plan. Production code starts only after that review gate.
