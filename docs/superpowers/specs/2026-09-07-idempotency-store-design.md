# T-402 Idempotency Store Design

## Status

- Task: `T-402 — Idempotency store`
- Milestone: `M4 — Production Trust Controls`
- Base: `main@b94ed83497e213c155ae0276264e954b9acd3ac3`
- Branch: `feat/idempotency-store`
- Design state: approved in chat; this written version includes the self-review security corrections described below.

## Goal

Implement bounded server-side idempotency for the Laravel reference runtime so caller/network retries cannot repeat a protected write or external side effect inside the configured retention window.

The runtime must:

1. enforce the existing `none`, `recommended_key`, and `required_key` policies;
2. treat caller-provided idempotency keys as untrusted retry identifiers rather than authority;
3. bind each usable key to the exact validated invocation intent within a trusted authority partition;
4. atomically claim one fresh execution before application code runs;
5. prevent concurrent or ambiguous reuse from executing again;
6. persist a successful replayable execution result;
7. replay an exact completed result without executing application code again;
8. rerun output policy on replay;
9. fail closed when the prior execution outcome is in progress, conflicting, or indeterminate;
10. preserve T-401 single-use confirmation semantics while allowing safe completed-result replay after a lost response.

This is a bounded deduplication guarantee, not a claim of global or permanent exactly-once business execution.

## Existing Contract

T-402 builds on already-frozen v0.1 semantics:

- `ActionDefinition.idempotency` is one of:
  - `none`;
  - `recommended_key`;
  - `required_key`.
- `Invocation.idempotencyKey` is `string|null` with `maxLength: 240`.
- The glossary already states that `required_key` rejects before execution when no valid key exists.
- Conformance Trust scenario 15 requires a required idempotency key to prevent duplicate side effects.
- Threat-model T12 requires action-declared idempotency policy plus a server idempotency key/store; a duplicate may return the prior outcome or a safe rejection.
- `InvocationContext.idempotencyKey` is non-authoritative invocation metadata.
- The canonical runtime flow currently contains an idempotency stage placeholder.
- T-401 explicitly states that confirmation receipt single-use behavior does not replace duplicate-side-effect protection and leaves room for a safe prior-result replay path.

`spec/0.1` remains frozen for T-402. Empty-string keys remain structurally schema-valid because v0.1 has no `minLength`; T-402 treats them as runtime-invalid when the action policy evaluates the supplied key.

## Non-goals

T-402 does not implement:

- T-403 output redaction or a new output-policy model;
- T-404 structured audit persistence;
- T-504 human confirmation UI;
- permanent exactly-once execution;
- distributed transactions with application databases or third-party APIs;
- automatic rollback/reversal after application execution;
- automatic retry of an indeterminate execution;
- an application-specific idempotency key generator;
- browser-specific idempotency behavior;
- a generic workflow/job engine;
- a cache-only production idempotency authority store;
- an encryption/key-management subsystem for replay payloads;
- changes to `spec/0.1` schemas or wire shapes.

## Approaches Considered

### 1. Hold a distributed lock through application execution

Rejected.

It prevents a second caller from entering the executor while the lock is valid, but arbitrary application code or external API calls would run while a distributed lock is held. Long-running execution, process failure, lock TTL expiry, and backend availability make this an unsafe ownership model.

### 2. Two-phase idempotency state machine with completed-result replay

Accepted.

The store performs only short atomic state transitions. Application code runs outside a database lock/transaction. One invocation atomically claims `in_progress`; a successful wire-safe output becomes `completed`; an exception or uncertain completion becomes/remains non-retryable (`indeterminate` or `in_progress`). Exact completed retries replay the stored execution output.

### 3. Store a result only after execution with no pre-execution claim

Rejected.

Two concurrent first attempts could both execute the side effect before either result is persisted. That does not satisfy T12 or the T-402 acceptance boundary.

## Core State Model

The server record state is:

```text
(no record / expired)
        |
        | atomic claim after validation + authorization + confirmation
        v
   in_progress
      |     \
      |      \
      |       \ executor / completion uncertainty
      |        v
      |   indeterminate
      |
      | successful replayable output persisted atomically
      v
   completed
```

`completed` and `indeterminate` records are immutable except expiry/removal by retention cleanup. `in_progress` is also never automatically reclaimed while its record is still within the retention window.

A process crash may leave `in_progress`. That is intentionally fail-closed: the runtime cannot know whether the external/application side effect happened before the crash.

## Retention and Expiry

Default idempotency retention is **24 hours** (`86400` seconds), configurable as a positive integer.

Validity is strict:

```text
record is active only when now < expiresAt
```

At `now == expiresAt`, the record is expired and no longer participates in deduplication.

Replay does not extend retention. Reads do not refresh TTL. The retention window begins with the successful fresh claim.

After expiry, the same scoped caller key may be claimed again. This is why T-402 guarantees bounded deduplication, not permanent exactly-once execution.

## Key Policy Evaluation

### `none`

The idempotency subsystem is bypassed completely. A caller-supplied key is ignored and does not cause validation, lookup, claim, or replay.

### `recommended_key`

- `null` key: proceed without deduplication;
- non-null key: key must be runtime-valid and full idempotency semantics apply.

### `required_key`

- `null` key: reject before confirmation/execution with `idempotency_key_required`;
- non-null key: key must be runtime-valid and full idempotency semantics apply.

### Runtime-valid key

A key used by idempotency must be an exact string of **1–240 Unicode characters** as measured consistently with current runtime string-length rules.

No trimming, case folding, normalization, prefix stripping, or fallback is performed. `"x"` and `" x "` are distinct. An empty string is invalid.

A non-null invalid key fails closed with `idempotency_key_invalid`; it is never treated as if the caller omitted the key, because that could silently disable deduplication.

## Trusted Authority Partition

### Self-review correction

The approved chat draft contained a contradiction: it placed actor/tenant in the lookup namespace while also saying that a different actor/tenant using the same raw caller key must conflict. Both cannot be true.

The written design resolves this in favor of tenant/user isolation and denial-of-service resistance:

> A raw caller idempotency key is unique only inside the exact action version and trusted authority partition. Different trusted authority partitions may safely reuse the same raw key without colliding.

This prevents one actor/tenant from reserving a predictable caller key and blocking an unrelated actor/tenant.

The same correction applies to action identity: the same caller key may be reused independently for another action ID/version. Cross-action global key collision would create needless caller-controlled denial-of-service coupling.

### Partition dimensions

The lookup partition is derived from trusted context only:

1. exact action ID;
2. exact action version;
3. trusted tenant identity when present;
4. trusted authenticated actor identity when present;
5. if neither tenant nor authenticated actor exists, trusted browser-session identity when present;
6. otherwise an explicit internal `global` partition for that exact action version.

Current record and current selection are deliberately not partition dimensions. They are invocation intent and must cause conflict when the same scoped key is reused for another target/selection.

When actor and/or tenant authority exists, browser session is not used as the lookup partition. This lets an authenticated retry find the same scoped key after a browser-session change; because browser session remains an intent dimension, such a change fails closed as `idempotency_conflict` instead of silently permitting a second business execution.

### Lookup hash

The server never persists the raw idempotency key.

The lookup key is a SHA-256 hash over canonical data with an internal domain separator:

```text
surfacerelay.idempotency.key.v1\n<canonical-json>
```

Canonical content contains exact action identity, the trusted authority partition, and the caller key.

The raw key exists only transiently in request/runtime memory and hash input.

## Invocation Intent Fingerprint

Within one lookup partition, one caller key may identify only one validated invocation intent.

The intent fingerprint includes:

1. exact action ID;
2. exact action version;
3. pipeline input after input validation/transformation and authorization;
4. present trusted authenticated actor;
5. present trusted tenant;
6. present trusted current record;
7. present trusted current selection;
8. present trusted browser session.

The intent deliberately excludes:

- `correlationId`;
- generic metadata;
- the idempotency key itself;
- `confirmationReceipt`;
- runtime-materialized `HumanConfirmation`;
- `surface`;
- `bindingId`.

`surface` and `bindingId` are excluded because idempotency protects one business execution across transport/UI retry boundaries. A page rerender or newly issued binding must not itself create permission to repeat a completed side effect.

The intent fingerprint uses:

```text
surfacerelay.idempotency.intent.v1\n<canonical-json>
```

followed by SHA-256.

## Canonical Trusted Scope Encoding

T-402 reuses T-401's deterministic value rules rather than inventing a second object-serialization model.

A shared internal canonicalizer accepts only:

- `null`;
- booleans;
- integers;
- finite floats;
- strings;
- lists of canonical values;
- string-key maps whose values are canonical.

Map keys are sorted lexicographically for fingerprints. Arbitrary objects, resources, closures, non-finite floats, or non-string associative keys fail closed.

Trusted context may provide a stable non-secret scope identity key for values that are not canonically representable.

### Compatibility with T-401

T-401 currently names this field `confirmationScopeKey`. T-402 generalizes its use internally without performing a source-level rename in this task. The common canonicalizer reads the existing trusted key as the stable identity source for both confirmation and idempotency. This avoids a needless PHP API break while preserving the already-tested T-401 behavior.

`ConfirmationScopeHasher` must continue producing the same fingerprint for every previously representable input/context. Tests will pin this regression explicitly.

A future breaking/naming cleanup may rename the property to a more general term, but T-402 does not require it.

## Pipeline Order

T-402 changes the internal canonical order to:

```text
input_validation
→ authorization
→ idempotency
→ confirmation
→ execution
→ output_policy
```

This intentionally supersedes the T-401-era internal order where confirmation preceded the idempotency placeholder.

Authorization still runs before any idempotency replay so a previously authorized result does not become current invocation authority.

Idempotency preflight runs before confirmation only to determine whether a safe completed-result replay already exists. A fresh execution is not claimed before confirmation.

## Idempotency Preflight

`IdempotencyStage` performs policy/key validation, scope hashing, and a read-only/atomic-safe preflight.

Its result is an internal execution plan:

```text
Bypass
FreshAttempt
Replay(output)
```

or a fail-closed halt.

Behavior:

```text
policy none
    -> Bypass

recommended_key + null
    -> Bypass

required_key + null
    -> halt idempotency_key_required

non-null invalid key
    -> halt idempotency_key_invalid

valid scoped key + no active record
    -> FreshAttempt

active record + different intent fingerprint
    -> halt idempotency_conflict

active in_progress + same intent
    -> halt idempotency_in_progress

active indeterminate + same intent
    -> halt idempotency_indeterminate

active completed + same intent
    -> Replay(stored execution output)
```

The preflight read is not the fresh-execution claim. A concurrent state change between preflight and execution is rechecked atomically at claim time.

## Completed Replay Path

For `Replay(output)`, ActionBus explicitly skips confirmation and execution and proceeds to output policy with the stored execution output.

This is a narrow orchestration rule, not logic embedded in `ConfirmationStage`.

Replay invariants:

- input validation has already succeeded;
- invocation authorization has already succeeded for the current actor/context;
- exact scoped key and intent fingerprint match an active completed record;
- no new application side effect is executed;
- no confirmation receipt is consumed;
- no new `HumanConfirmation` entry is materialized;
- output policy runs again on the replayed pre-policy output;
- the current invocation correlation ID is used in the eventual `ActionResult`;
- retention is not extended.

A completed record is not confirmation authority. For a confirmation-required action, a pre-materialized `HumanConfirmation` trusted entry remains the same T-401 runtime configuration violation even on a completed replay path. T-402 must enforce that invariant before replay skips `ConfirmationStage`, preferably through a narrow shared/kernel guard rather than by teaching idempotency code to trust or manufacture confirmation state.

This is the safe T-402 path anticipated by the T-401 design for a lost-response retry.

## Fresh Execution Claim

A `FreshAttempt` reaches confirmation normally.

After confirmation has passed, the execution stage asks `IdempotencyService` to atomically claim the scoped key before calling the application executor.

The claim operation may return:

```text
claimed
completed(output)   // another invocation completed after our preflight
conflict
in_progress
indeterminate
```

Only `claimed` permits the executor to run.

If claim returns `completed(output)` because of a race, the executor is skipped and that output proceeds to output policy. For a consequential invocation, the current confirmation receipt may already have been consumed immediately before this race is discovered. That is safe: no second business side effect occurs, and T-401's single-use receipt contract remains intact.

Application execution never runs while a database row lock or transaction is deliberately held open.

## Execution Completion

When the executor returns successfully:

1. validate/encode the returned value as a replayable wire-safe execution output;
2. atomically transition the exact expected `in_progress` record to `completed` with that output;
3. only after successful completion persistence may the pipeline continue to output policy.

A successful application side effect followed by an idempotency completion-store failure must never be reported as a normal succeeded result. The runtime fails closed because it cannot safely promise retry behavior.

The existing public output remains produced by the output-policy stage and normalizer; the idempotency store does not persist `ActionResult`, old correlation IDs, confirmation objects, or public result metadata.

## Replay Payload

The store persists the **pre-output-policy executor output**, not the public `ActionResult`.

Reason:

- a replay must rerun the current output policy;
- T-403 must not be bypassed later by replaying a stale already-projected/redacted result;
- the retry must receive its own correlation ID;
- confirmation/audit metadata must not be cached as business output.

Replayable output is restricted to JSON/wire-safe data:

- `null`;
- boolean;
- integer;
- finite float;
- string;
- lists of replayable values;
- string-key maps of replayable values.

Arbitrary PHP objects, resources, closures, non-finite floats, and unsupported map shapes are not serialized with PHP `serialize()` and do not become replay payloads.

The implementation should isolate replay encoding/decoding behind a narrow internal codec so persisted representation cannot accidentally become domain-object serialization. The codec may use a tagged JSON representation when necessary to preserve list/map shape deterministically.

If application execution succeeds but its returned output cannot be encoded safely, the execution is treated as **indeterminate for retry purposes**. The runtime must block same-key automatic re-execution rather than silently dropping the record and risking a duplicate side effect.

### Sensitive output at rest

A pre-policy replay payload is internal server-side application data and may be sensitive. T-402 therefore:

- never logs replay payloads;
- never exposes them in halt/error details or metadata;
- keeps the default retention bounded;
- stores them in the application's durable database under the same operational access-control/backups/encryption-at-rest regime as other application data.

T-402 does not add a new encryption/key-management subsystem. Deployments requiring stronger field-level encryption can provide an alternate `IdempotencyStore`/codec. T-403 still runs on every replay before public projection.

## Executor Failure and Indeterminate State

If the application executor throws after a successful claim:

1. SurfaceRelay attempts to atomically mark the same expected record `indeterminate`;
2. the original application exception remains the primary execution failure when the transition succeeds;
3. if marking indeterminate itself fails, the runtime must not delete/release the idempotency record; a remaining `in_progress` record is conservative and still blocks automatic re-execution.

The runtime never assumes an exception means the side effect did not happen. This is essential for external APIs and writes that may throw after committing/sending.

The same principle applies to process crash: a stranded `in_progress` record blocks reuse until retention expiry.

## Store Contract

Core logic depends on a focused `IdempotencyStore` contract rather than facades.

The contract supports at minimum:

- preflight lookup by hashed scoped key at a supplied deterministic time;
- atomic fresh claim with exact intent fingerprint and expiry;
- atomic completed transition from exact expected `in_progress` state;
- atomic indeterminate transition from exact expected `in_progress` state.

Store operations return narrow typed outcomes rather than leaking database exceptions/records into pipeline logic.

Corrupt records, impossible transitions, or storage unavailability fail closed as runtime exceptions.

## Database Reference Adapter

The reference production adapter is durable database-backed, not cache-only.

T-402 adds `illuminate/database ^12.0|^13.0` to the Laravel package requirements.

Default table:

```text
surfacerelay_idempotency_records
--------------------------------
key_hash             char(64) primary/unique
intent_fingerprint   char(64) not null
state                varchar not null
output_payload       text nullable
created_at           timestamp not null
expires_at           timestamp not null
```

The exact table name may be injected/overridden by runtime configuration. Raw caller keys are never columns.

The package currently has no general service provider/autodiscovery layer. T-402 must not introduce a broad package-bootstrap subsystem solely for this table. It should provide a narrowly usable migration/schema artifact and documented setup path that tests exercise directly; full package configuration/bootstrap can be introduced later when another task justifies it.

### Atomicity

The adapter uses database transaction/unique-key semantics for state mutation.

Key requirements:

- first fresh claim has one unique winner for `key_hash`;
- a competing claim reads the winning record and returns completed/conflict/in-progress/indeterminate instead of executing;
- expired rows may be replaced only through an atomic path that cannot permit two claim winners;
- completed/indeterminate rows are never overwritten while active;
- completion updates only the exact expected `in_progress + intent_fingerprint` record;
- no transaction/row lock is held across application executor work.

Driver-specific duplicate-key handling must not silently ignore unrelated database errors.

## Internal Components

Expected focused boundaries:

```text
IdempotencyKeyValidator
RuntimeScopeCanonicalizer
IdempotencyIntentHasher
IdempotencyRecord
IdempotencyRecordState
IdempotencyStore
DatabaseIdempotencyStore
IdempotencyReplayCodec
IdempotencyService
IdempotencyExecutionPlan
IdempotencyStage
ActionExecutionStage integration
ActionBus replay orchestration
```

Names may vary slightly during implementation if existing package conventions require it, but responsibilities must remain isolated.

## Public Error Semantics

T-402 adds implemented extensible core error codes:

```text
idempotency_key_required
idempotency_key_invalid
idempotency_conflict
idempotency_in_progress
idempotency_indeterminate
```

These are deliberate pre-success refusals for the current invocation and normalize to `ActionResult.status = rejected`.

Public messages are static and public details are omitted. No raw key, lookup hash, intent fingerprint, trusted actor/tenant identity, stored payload, table key, or database diagnostic is projected into `ActionResult`.

Store unavailability, corrupt persisted records, impossible transitions, completion persistence failure, and invalid runtime idempotency configuration remain runtime/programming failures rather than being disguised as ordinary caller rejection.

## Audit Boundary

T-402 does not implement T-404 structured audit persistence.

The existing auditor contract continues to receive the current `ActionCall` as it already does; T-402 does not claim that arbitrary application auditor implementations cannot inspect `InvocationContext.idempotencyKey`.

T-402 does guarantee that its own generated halt details, public result metadata/errors, trusted-context provenance, store-record diagnostics, and exception messages do not add raw keys, hashes, fingerprints, or replay payloads. T-404 will define minimized structured persistence later.

## T-401 Interaction

The critical combined-control scenario is:

```text
1. caller invokes consequential action with idempotency key
2. input validation succeeds
3. authorization succeeds
4. idempotency preflight finds no completed record
5. confirmation challenge/approval succeeds
6. confirmation receipt is consumed once
7. idempotency claim succeeds
8. application side effect executes once
9. successful pre-policy output is persisted as completed
10. network/client loses the response
11. caller retries same action/key/validated intent
12. validation succeeds again
13. authorization succeeds again
14. idempotency preflight finds exact completed record
15. confirmation + execution are skipped for replay
16. output policy runs again
17. current correlation ID is returned
18. executor total call count remains 1
```

This does not weaken T-401:

- a fresh consequential execution still requires confirmation;
- confirmation authority is never reconstructed from idempotency state;
- replay does not create `HumanConfirmation`;
- a consumed receipt remains consumed;
- a pre-materialized `HumanConfirmation` remains a configuration violation even on replay;
- a conflicting/non-completed idempotency record never bypasses confirmation into execution.

## Decision Update

Implementation should add and accept D-045 with this meaning:

> Idempotency is a bounded server-side execution deduplication guarantee. A caller key never identifies business intent or authority by itself: the runtime hashes it inside exact action identity and a trusted authority partition, binds reuse to the validated invocation intent, atomically claims before execution, persists only a replayable successful pre-output-policy execution result, and replays an exact completed result without re-executing the action. Conflicting, concurrent, or indeterminate reuse fails closed.

The decision should explicitly state that different trusted authority partitions and different action versions may reuse the same raw caller key independently; this prevents caller-controlled cross-tenant/action key squatting.

## Security and Functional Test Matrix

At minimum executable tests must prove:

1. `none` bypasses idempotency even when a key is present;
2. `recommended_key` with `null` proceeds without deduplication;
3. `recommended_key` with valid key performs full deduplication;
4. `required_key` with `null` rejects before confirmation/execution;
5. empty or over-240 non-null key rejects before execution;
6. invalid key is never silently treated as absent;
7. validated/transformed input, not raw caller input, participates in the intent fingerprint;
8. same action/version + same authority partition + same key + different validated input conflicts;
9. same scoped key with changed current record conflicts;
10. same scoped key with changed current selection conflicts;
11. same scoped key with changed browser session conflicts when actor/tenant partition remains the same;
12. same raw key under a different authenticated actor partition does not collide;
13. same raw key under a different tenant partition does not collide;
14. same raw key for another action ID/version does not collide;
15. different surface with otherwise exact business intent replays;
16. different binding ID with otherwise exact business intent replays;
17. exact completed replay never calls executor again;
18. replay reruns authorization;
19. replay does not require/consume a new confirmation receipt;
20. replay reruns output policy;
21. replay uses the current correlation ID;
22. replay does not extend expiry;
23. two competing fresh claims produce only one execution winner;
24. race where another invocation completes after preflight returns completed output without a second execution;
25. active `in_progress` blocks a second execution;
26. executor exception makes the same key non-retryable (`indeterminate` or conservatively stranded `in_progress`);
27. completion-persistence failure never returns normal success and does not reopen execution;
28. unsupported/unpersistable successful output does not reopen execution;
29. `now == expiresAt` is expired;
30. expiry permits a new bounded-window claim;
31. corrupt record/store unavailability fails closed before unsafe execution;
32. raw idempotency key is never persisted in the reference store;
33. SurfaceRelay-generated public errors/meta/provenance/exceptions do not expose raw key/hash/fingerprint/replay payload;
34. T-401 confirmation scope fingerprints remain regression-identical after canonicalizer reuse;
35. full ActionBus integration proves consequential success + lost-response retry executes exactly once and replays safely;
36. full integration proves conflict/in-progress/indeterminate cannot bypass into confirmation/execution;
37. completed replay for a confirmation-required action still fails closed on pre-materialized `HumanConfirmation` configuration;
38. `spec/0.1` remains byte-for-byte unchanged;
39. browser production code remains untouched unless a test fixture needs only non-production adaptation;
40. T-403 output-redaction implementation is not introduced;
41. T-404 structured audit persistence is not introduced.

## Documentation Updates at Completion

T-402 implementation/review completion should update together:

- `docs/DECISION-REGISTER.md` with accepted D-045;
- `docs/GLOSSARY.md` with exact implemented idempotency/replay semantics;
- `docs/THREAT-MODEL.md` T12 with implemented state/store behavior;
- `CONFORMANCE.md` Trust scenario 15 reference-runtime evidence;
- `TASKS.md` T-402 status/evidence;
- `STATUS.md` authoritative checkpoint;
- `REVIEW_REQUEST.md` before external review.

Historical T-401 design artifacts remain historical; current architecture/docs should describe the T-402 pipeline order that supersedes the placeholder-era ordering.

## Compatibility and Scope Guard

T-402 must preserve:

- frozen `spec/0.1` wire contracts;
- protocol-neutral Action Definition semantics;
- T-401 confirmation capability behavior for fresh execution and configuration-invalid pre-materialized authority;
- T-305 cancellation claims/frontier;
- existing Livewire/browser driver behavior;
- current output-policy stage ownership;
- existing extensible ActionError code model;
- existing audit finalization exactly once per ActionBus dispatch;
- PHP 8.3/8.4 and Illuminate 12/13 support.

T-402 must not drift into T-403 or T-404.

## Completion Standard

T-402 is not DONE merely because duplicate unit calls appear deduplicated. Completion requires:

1. exact key-policy enforcement;
2. deterministic trusted partition + intent hashing;
3. atomic durable fresh claim;
4. safe completed replay;
5. conservative indeterminate behavior;
6. consequential-action lost-response integration proof;
7. negative bypass/conflict/concurrency tests;
8. database adapter tests;
9. full PHP matrix green;
10. browser/typecheck isolation green;
11. `python scripts/validate.py` green;
12. diff/scope review with no T-403/T-404 implementation leakage;
13. documentation/decision/evidence updates;
14. external review before merge, following the same explicit merge gate used for T-401.
