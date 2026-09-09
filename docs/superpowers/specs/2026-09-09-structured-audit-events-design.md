# T-404 Structured Audit Events Design

## Status

- Task: `T-404 — Structured audit events`
- Milestone: `M4 — Production Trust Controls`
- Base: `main@a3112dea3f965e27db8e6904650c97f89d2261fe`
- Branch: `feat/structured-audit-events`
- Design state: approved in chat; this document is the implementation contract for the Laravel reference runtime.
- Decision: `D-047 — ACCEPTED`

## Goal

Persist one structured, append-only, payload-minimized audit record for every ActionBus dispatch that reaches the existing finalization boundary.

T-404 turns the existing `ActionPipelineAuditor` finalizer port into a production trust control without turning SurfaceRelay into an event-sourcing, SIEM, analytics, or business-payload archive.

The audit record must answer:

1. which exact Action/version was finalized;
2. which surface/correlation ID the finalized invocation belonged to;
3. which declared trust semantics applied;
4. whether the pipeline completed or halted;
5. where and why an explicit halt occurred;
6. which trusted context requirements were present and which trusted provider supplied each one;
7. whether trusted human-confirmation authority was present at finalization;
8. when SurfaceRelay persisted the record.

It must not persist invocation/business payloads or bearer capabilities.

## Existing Runtime Boundary

`ActionBus` already has an exactly-once finalizer for outcomes that are represented as `ActionPipelineOutcome`:

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

`ActionPipelineAuditor::record()` is invoked exactly once when the dispatch reaches either:

- `ActionPipelineOutcome::completed(...)`; or
- an explicit `ActionPipelineOutcome::halted(...)` path.

T-403 already guarantees that raw sensitive output is removed before a failed output-policy outcome reaches this finalizer.

A PHP exception thrown before `ActionBus::finalize()` is currently outside this final-outcome contract. T-404 does not silently broaden that semantic into a universal exception-event system.

## Non-goals

T-404 does not implement:

- stage-by-stage audit/event streaming;
- event sourcing or state reconstruction;
- a generic domain-event bus;
- business payload history;
- raw input/output persistence;
- exception-message or stack-trace persistence;
- SIEM/OpenTelemetry/export pipelines;
- audit signing, hash chains, transparency logs, or non-repudiation;
- custom encryption/KMS;
- automatic retention/cleanup policy;
- audit querying/reporting APIs;
- a UI for audit records;
- cross-service distributed tracing;
- changes to frozen `spec/0.1/**` wire schemas;
- changing ActionResult public taxonomy;
- converting every arbitrary thrown PHP exception into a durable audit event.

These may be layered later only when product requirements justify them.

## Approaches Considered

### 1. Stage-by-stage event stream

Rejected for T-404.

Recording every stage transition would require event ordering semantics, duplicate handling, exception-path semantics, retention, replay interpretation and substantially more persistence volume. That is a different subsystem and would expand M4 beyond its trust-control objective.

### 2. Persist `ActionCall` and `ActionPipelineOutcome` snapshots directly

Rejected.

Those runtime objects contain or reference raw input/output, caller metadata, idempotency candidates, confirmation receipts, binding references and trusted context values. Serializing them would make sensitive-data leakage the default rather than the exception.

### 3. Explicit allowlist projection into one final audit event

Accepted.

A dedicated factory projects only approved fields from the final call/outcome into a narrow immutable `AuditEvent`, then an append-only store persists it.

This keeps persistence semantics explicit, testable and independent from future additions to `ActionCall`, `InvocationContext`, `ActionPipelineState` or `ActionDefinition`.

## D-047

**Structured audit is an append-only, payload-minimized final-dispatch record. Raw invocation/business payloads and bearer capabilities are never audit persistence data.**

Consequences:

- the audit event is built from an explicit allowlist;
- adding a field to a runtime object never automatically adds it to persisted audit data;
- audit persistence records final execution/control evidence, not business-object history;
- persistence failure is a trust-control failure and is not swallowed as best effort.

## Audit Event Model

The implementation introduces an immutable audit event under a dedicated Laravel runtime audit namespace.

Conceptually:

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
  haltedAt             // stage value | null
  haltCode             // extensible machine code | null

  humanConfirmationPresent
  trustedContextManifest[]
```

### Event ID

`eventId` is a server-generated opaque identifier independent from correlation ID.

The reference implementation uses 128 bits from `random_bytes(16)`, encoded as 32 lowercase hexadecimal characters. The identifier carries no time, action, user, tenant or payload meaning.

The database enforces uniqueness.

### Timestamp

`recordedAt` is generated by trusted server runtime in UTC.

The persisted column uses microsecond precision (`timestamp(6)`/equivalent supported by the existing Laravel/MySQL test matrix). Tests pin UTC handling and round-trip precision.

The timestamp describes audit persistence/finalization time, not application-business-event time.

### Invocation labels

Persist:

- `correlationId`;
- `surface`.

They remain diagnostic labels, not authorization evidence.

Do not persist generic `InvocationContext::metadata`.

### Action identity and declared trust semantics

Persist exact final resolved definition values:

- action ID;
- action version;
- scope;
- effect;
- risk;
- idempotency policy;
- output sensitivity;
- output content trust.

Do not persist title, description, input/output schemas or extensions. They add storage volume and may become application-defined data containers without improving the T-404 trust boundary.

### Outcome

`outcomeKind` is exactly one of:

- `completed`;
- `halted`.

For `completed`:

- `haltedAt = null`;
- `haltCode = null`.

For `halted`:

- `haltCode` is the exact internal machine-readable halt code;
- `haltedAt` is the canonical pipeline stage value when a stage halted;
- `haltedAt = null` remains valid for the built-in pre-stage trusted-context gate.

No halt `details`, confirmation challenge content, public message, exception text or arbitrary diagnostic payload is persisted.

### Confirmation evidence

Persist only:

```text
humanConfirmationPresent: bool
```

It is derived from final trusted context presence of `ContextRequirement::HumanConfirmation`.

Do not persist:

- caller confirmation receipt;
- challenge ID/token;
- confirmation scope key;
- `VerifiedConfirmation` object/data;
- receipt hashes or capability material.

This boolean is evidence that trusted runtime authority was present at finalization; it is not reusable confirmation authority.

### Trusted context manifest

For each trusted context entry present in final pipeline state, persist only:

```text
{
  requirement: <canonical ContextRequirement value>,
  provider: <ContextProvenance provider>
}
```

The manifest follows canonical `ContextRequirement::cases()` ordering already guaranteed by `InvocationContext::allTrusted()`.

Do not persist:

- trusted context values;
- `ContextProvenance::reference`;
- `confirmationScopeKey`;
- canonicalized scope/identity values;
- actor/tenant/record/selection/session identifiers.

Provider strings are already explicit trusted-runtime provenance labels. The manifest therefore proves which authority classes were present and which resolver/provider supplied them without turning the audit table into a copy of application identity data.

## Explicit Forbidden Persistence Set

The T-404 reference auditor must never persist any of the following:

- `ActionCall::input` or final validated input;
- `ActionPipelineState::output`, even after T-403 redaction/release;
- generic invocation metadata;
- raw idempotency key;
- idempotency lookup hash;
- idempotency intent fingerprint;
- idempotency replay payload;
- binding ID;
- confirmation receipt;
- confirmation challenge token/identifier/payload;
- trusted context values;
- trusted context provenance reference;
- trusted stable confirmation/scope key;
- halt details;
- exception message/stack trace;
- ActionDefinition schemas/extensions.

Tests use adversarial marker strings in each reachable forbidden source and assert that no persisted column/JSON document contains them.

## Components

```text
ActionBus final outcome
        |
        v
StructuredActionPipelineAuditor
        |
        v
AuditEventFactory
   |            |
   |            +--> AuditClock
   |
   +--> allowlist projection
        |
        v
AuditEventStore::append(AuditEvent)
        |
        v
DatabaseAuditEventStore
        |
        v
surfacerelay_audit_events
```

### `StructuredActionPipelineAuditor`

Implements the existing `ActionPipelineAuditor` interface.

Responsibilities:

1. build one event from the provided call/outcome;
2. append it once;
3. return nothing;
4. never modify the pipeline outcome;
5. never catch/suppress persistence failure.

It does not know SQL/table structure.

### `AuditEventFactory`

Owns the persistence allowlist projection.

It reads:

- invocation labels from the call/final state context;
- resolved exact definition from final state;
- completed/halted metadata from outcome;
- trusted context requirement/provider manifest from final state.

It must never serialize whole runtime objects.

### `AuditClock`

A narrow injectable clock returns a UTC `DateTimeImmutable` for deterministic tests.

A default system implementation produces current UTC time. T-404 does not reuse `IdempotencyClock`: that interface is intentionally subsystem-specific and second-based, while audit persistence requires an independent wall-clock timestamp with microsecond-capable representation.

### `AuditEventStore`

Core contract:

```php
public function append(AuditEvent $event): void;
```

No update/delete/query method is part of the core write contract.

### `DatabaseAuditEventStore`

Laravel database implementation using the same `illuminate/database` dependency and MySQL 8.4 integration matrix already used by T-402.

One `append()` call performs one insert. Duplicate `eventId` or database failure propagates as a persistence/runtime failure.

No upsert, overwrite, retry-as-update or delete-on-conflict behavior is allowed.

## Database Schema

New table:

```text
surfacerelay_audit_events
```

Columns:

```text
event_id                       char(32) primary key
recorded_at                    timestamp(6)
correlation_id                 varchar(...)
surface                        varchar(...)
action_id                      varchar(160)
action_version                 unsigned integer
action_scope                   varchar(...)
action_effect                  varchar(...)
action_risk                    varchar(...)
idempotency_policy             varchar(...)
output_sensitivity             varchar(...)
output_content_trust           varchar(...)
outcome_kind                   varchar(...)
halted_at                      varchar(...) nullable
halt_code                      varchar(...) nullable
human_confirmation_present     boolean
trusted_context_manifest       json
```

The exact varchar lengths should reuse/enforce known runtime limits where they exist and use conservative bounded lengths for open machine-code/provider vocabularies.

Required indexes beyond the primary key:

- `(recorded_at)`;
- `(correlation_id)`;
- `(action_id, action_version, recorded_at)`;
- `(outcome_kind, halt_code, recorded_at)`.

No input/output/business identity indexes exist because those values are not stored.

### Manifest encoding

`trusted_context_manifest` is JSON containing only a list of `{requirement, provider}` string pairs.

Encoding failure is a runtime failure; the implementation must not fall back to PHP serialization or silently drop entries.

Hydration/query APIs are not required by T-404, so the reference write store does not introduce a general ORM model merely to insert rows.

## Append-only Semantics

Append-only means SurfaceRelay's T-404 write API has exactly one operation: insert a new event.

The package does not expose mutation or deletion methods for prior events.

This is an application-level contract, not a claim that a database administrator can never alter/delete rows. Database/WORM policy, regulatory retention and tamper-evident storage are deployment concerns outside T-404.

## Failure Semantics

Audit persistence is a production trust control, not optional logging.

If `ActionPipelineAuditor::record()` fails:

- the exception propagates;
- SurfaceRelay must not return the original outcome as an ordinary successful finalized result;
- the runtime must not pretend application execution was rolled back;
- T-402 idempotency state remains whatever authoritative state was already persisted;
- a completed external/business side effect may already have occurred.

This follows the existing post-execution trust model: failure after execution changes what SurfaceRelay may safely claim, not what has already happened in the application/external system.

T-404 does not add an automatic second audit retry inside `ActionBus`; blind retry could create duplicate records when database commit outcome is ambiguous. Operational retry/outbox strategies require a separate explicit design.

## Finalization and Exception Boundary

T-404 guarantees:

> Every ActionBus dispatch that reaches the existing completed/explicit-halt finalization boundary attempts exactly one structured audit append.

It does not guarantee:

> Every PHP Throwable thrown anywhere during action resolution/stages is durably audited.

Today, executor/stage/configuration exceptions that escape before `ActionBus::finalize()` do not produce `ActionPipelineOutcome` and therefore do not reach the auditor.

Changing that would require defining an exception taxonomy, safe exception projection, execution-occurrence semantics and likely `try/finally` orchestration changes. That is deliberately excluded from T-404 rather than being implemented implicitly.

A future task may introduce an independent runtime-failure telemetry/audit boundary without changing the meaning of T-404 final-dispatch events.

## Replay Semantics

A completed idempotency replay is a new invocation/finalization and therefore produces a new audit event.

The replay event:

- has a new `eventId` and `recordedAt`;
- uses the current invocation correlation ID/surface;
- records exact action/version and final outcome;
- may show no human-confirmation authority because completed replay skips confirmation by design;
- does not expose the replay payload, lookup hash, intent fingerprint or prior audit record ID.

This correctly records two finalized invocation attempts while T-402 still guarantees one protected application execution.

## T-403 Interaction

T-404 never persists output, so it cannot become an alternate disclosure path around T-403.

For an `output_policy_failed` halt:

- final state is already output-free by D-046;
- event records `outcomeKind=halted`, `haltedAt=output_policy`, `haltCode=output_policy_failed`;
- no raw or redacted output is stored.

Even for successful sensitive release, the released value is not persisted by T-404.

## Compatibility / Frozen Contracts

T-404 changes only the Laravel reference runtime persistence layer and internal audit implementation.

It does not modify:

- `spec/0.1/**`;
- ActionDefinition wire shape;
- Invocation wire shape;
- ActionResult wire shape/taxonomy;
- WebMCP annotations;
- browser runtime contracts.

`ActionPipelineAuditor` keeps its existing method signature so existing test doubles/custom auditors remain source-compatible.

The production structured auditor is a new implementation of that port rather than a signature-breaking replacement.

## Testing Strategy

### Unit tests

Prove `AuditEventFactory`:

- projects exact action identity and declarative trust enums;
- maps completed/halted correctly;
- preserves `haltedAt=null` for pre-stage context failure;
- records halt code but never halt details;
- derives `humanConfirmationPresent` only from trusted final context;
- emits trusted-context manifest in canonical requirement order;
- includes provider but excludes value/reference/scope key;
- generates opaque 32-character lowercase hex event IDs;
- uses UTC clock output.

### Adversarial secrecy tests

Place unique secret markers in:

- raw/validated input;
- normal output;
- sensitive released output;
- generic metadata;
- idempotency key;
- binding ID;
- confirmation receipt/challenge fields where reachable;
- trusted actor/tenant/record/selection/session values;
- provenance reference;
- confirmation scope key;
- halt details.

Assert persisted row/manifest contains none of them.

### Auditor tests

Prove:

- one finalizer call appends exactly one event;
- completed and explicit halt each append once;
- store exception propagates unchanged;
- auditor does not mutate/replace the outcome.

Existing ActionBus tests continue proving finalizer exactly-once orchestration.

### Integration tests

With real MySQL 8.4 across PHP 8.3/8.4 × Illuminate 12/13:

- migration up/down round-trip;
- completed append;
- halt append;
- output-policy failure append without output leak;
- consequential confirmed fresh execution records confirmation presence;
- exact completed idempotency replay creates a second audit event but does not re-execute application code;
- duplicate `event_id` fails rather than updates;
- JSON manifest round-trips only allowlisted fields;
- timestamp microseconds/UTC round-trip;
- required indexes/table shape exist where practical to assert portably.

### Full regression

Run:

- PHP unit/integration suite;
- PHP lint;
- browser TypeScript typecheck and Vitest suite;
- `python scripts/validate.py`;
- frozen `spec/0.1/**` diff check.

## Acceptance Criteria

T-404 is complete when:

1. an immutable allowlisted `AuditEvent` model exists;
2. production `ActionPipelineAuditor` projects and appends one event for each existing finalization path;
3. an append-only `AuditEventStore` contract exists;
4. a MySQL/Laravel database store and migration persist the event;
5. raw input/output/business payloads and bearer/identity material are absent from persistence;
6. completed/halted/halt-stage/halt-code semantics are preserved;
7. trusted context persistence is limited to requirement + provider manifest;
8. confirmation persistence is limited to a boolean authority-presence fact;
9. idempotency replay generates a separate invocation audit event without storing replay data;
10. output-policy failure cannot leak raw output through audit;
11. audit-store failure propagates and is never downgraded to best-effort logging;
12. append collision does not mutate an existing event;
13. `ActionPipelineAuditor` signature and frozen `spec/0.1/**` remain unchanged;
14. the full CI matrix is green.

## Self-review Corrections Built Into This Spec

The chat-approved direction said “append-only structured final audit event.” This written design tightens several points to prevent accidental scope or secrecy expansion:

1. **No sanitized output either.** Persisting T-403's released value would still turn audit into a second business-data store and create retention/confidentiality obligations.
2. **No trusted identity values.** Actor/tenant/etc. values are omitted; only requirement + provider provenance class is stored.
3. **No provenance reference.** A provider reference can itself contain application/user identifiers, so only provider name is allowed.
4. **No automatic exception auditing.** The current finalizer contract does not see arbitrary thrown exceptions; T-404 states that boundary rather than manufacturing an incomplete exception-event model.
5. **No blind audit retry.** Ambiguous database commit + retry can duplicate evidence; retry/outbox needs a separate contract.
6. **No update/delete core API.** Append-only is enforced structurally at the SurfaceRelay store interface.
7. **No ActionDefinition extensions/schemas.** They are open/application-defined data containers and are unnecessary for trust evidence.
8. **No reuse of `IdempotencyClock`.** Audit has independent timestamp semantics and precision requirements; cross-subsystem reuse would create misleading coupling.

## Next Step

After user review of this written spec, create the detailed implementation plan. No production code should be written before that review gate.
