# Project Status

> Current repository state for implementation and review.

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/structured-audit-events`
- **Base / merge-base:** `main@a3112dea3f965e27db8e6904650c97f89d2261fe`
- **Stage:** M0 DONE; M1 DONE; M1.1 DONE/REVIEWED; M2 DONE/REVIEWED; M3 DONE/REVIEWED; **M4 IN PROGRESS**
- **Last merged/revalidated task:** `T-403 — Output policy/redaction`
- **Current task:** `T-404 — Structured audit events` — **DONE / REVIEWED; MERGE PENDING**
- **Pull request:** `#4` — open; merge remains a separate explicit gate
- **Original T-404 code checkpoint:** `79df19c3a95f1a95b03e4b5c6578d8195903d22a`
- **Original code workflow:** `34384669898` — **7/7 green**
- **Pre-finding external-review head:** `52aaee0e5e446311fb4554c67789ebefbbf116fb`
- **Pre-finding review-head workflow:** `34385980442` — **7/7 green**
- **CodeRabbit full review:** `cad69fe7-6838-42a2-8fca-496f9f592309` — 2 actionable findings + 1 trivial test-refactor nitpick
- **Review-hardening code checkpoint:** `403927a0f2fdb60170f24c3d02756cb41e87965e`
- **Review-hardening workflow:** `34401520520` — **7/7 green**
- **Review-evidence checkpoint:** `5544d5db98b7441e009a57e523b6cc7ccefb1e45`
- **Review-evidence workflow:** `34401943831` — **7/7 green**
- **PHP evidence after hardening:** **453 tests / 2418 assertions** across PHP 8.3/8.4 × Illuminate 12/13 with MySQL 8.4 service coverage
- **Browser isolation:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract:** `python scripts/validate.py` green; frozen `spec/0.1/**` unchanged
- **Decision:** `D-047 — ACCEPTED`
- **External review result:** **PASSED after review hardening; 0 unresolved review threads**
- **Merge:** not requested

## T-404 — Implemented trust boundary

```text
ActionBus completed / explicit-halt finalization
        │
        ▼
StructuredActionPipelineAuditor
        │
        ▼
AuditEventFactory
  explicit semantic allowlist only
        │
        ▼
AuditEventStore::append(event)
        │
        ▼
DatabaseAuditEventStore
  + persistence-only equality hashes
        │
        ▼
surfacerelay_audit_events
```

### Persisted facts

- opaque server-generated event ID;
- UTC finalization timestamp with microseconds;
- current correlation ID and surface diagnostic labels;
- exact Action ID/version;
- declared scope/effect/risk/idempotency/output sensitivity/output content trust;
- final `completed|halted` state;
- canonical halt stage/code when halted, including `haltedAt=null` for pre-stage context failure;
- trusted human-confirmation **presence boolean only**;
- canonical trusted-context manifest containing only `{requirement, provider}`.

### Explicitly forbidden persistence

T-404 never persists raw/validated input, any output (including T-403 released output), generic metadata, raw/hashed idempotency material, replay payload, binding IDs, confirmation receipts/challenges, trusted context values, provenance references, stable scope keys, halt details, exception diagnostics, schemas or extensions.

## Locked invariants

1. Audit persistence uses an explicit allowlist; adding fields to runtime objects cannot implicitly add audit columns/data.
2. `ActionPipelineAuditor::record(ActionCall, ActionPipelineOutcome)` remains source-compatible.
3. `AuditEventFactory` accepts only the final `ActionPipelineOutcome`; raw caller-only candidates are outside its API.
4. Audit is append-only at the SurfaceRelay write-contract level: one `append(AuditEvent)` operation, no update/upsert/delete fallback.
5. Duplicate event IDs fail; existing evidence is never overwritten.
6. Correlation/halt equality hashes are database index helpers only and are not semantic event fields or authority.
7. Database/JSON failures become static non-chained audit errors; SQL/table/row/driver diagnostics are not exposed.
8. Audit persistence failure propagates; SurfaceRelay does not return the original finalized outcome as though audit succeeded.
9. Audit failure after execution does not imply rollback; application/external side effects may already have occurred.
10. T-402 exact completed replay produces a fresh audit event while application execution remains single and prior confirmation authority is not replayed/manufactured.
11. T-403 cannot be bypassed through audit because neither raw nor released output is persisted.
12. T-404 guarantees audit only for dispatches reaching the existing completed/explicit-halt finalization boundary; arbitrary PHP `Throwable` paths before `ActionBus::finalize()` remain an explicit non-claim.
13. The Laravel package installation path auto-discovers `SurfaceRelayServiceProvider`; the provider loads the shipped audit migration so enabling the database audit store does not depend on consumers manually locating that migration file.
14. Frozen `spec/0.1/**` remains unchanged.

## TDD / verification evidence

```text
Plan-head baseline:            777442e4c65ed0f8ba10ac13d76a6fccc2926dcc / 34380200139 — 7/7 green
Task-1 semantic RED:           12252f6cb5eeaf087b587dc0590a3939df13a6a5 / 34381899289
Task-1 GREEN:                  2d51abb731335e409ff2984b784096625fab6ad3 / 34382322354 — 7/7 green
Task-2 auditor/store RED:      ab638b8dba7344803dde0f0f203426bc1db8956e / 34382547037
Task-2 GREEN:                  6b1cf5f49f49f0097d2de503e2e5cec61e0c8901 / 34382775139 — 7/7 green
Task-3 durable-store RED:      b5132fa9c769428db353f1e94e1fd0c2572db0cb / 34383113630
Task-3 GREEN:                  23c978e5d576a19410cda4669e2ab1e43bc7254c / 34383330077 — 7/7 green
Task-4 pipeline RED:           08a4a14797078584f4deed95231612966024cd28 / 34383610213
Task-4 GREEN:                  da37400c14e558438b84ea9a140eb06fd50e1f69 / 34383939887 — 7/7 green
Task-5 replay/secrecy RED:     7a9adb89bd7ab8cc525a19bf061e37886bb36527 / 34384242487
Task-5 GREEN/code checkpoint:  79df19c3a95f1a95b03e4b5c6578d8195903d22a / 34384669898 — 7/7 green
Pre-finding review head:       52aaee0e5e446311fb4554c67789ebefbbf116fb / 34385980442 — 7/7 green
CodeRabbit full review:        cad69fe7-6838-42a2-8fca-496f9f592309 — 2 actionable + 1 trivial nitpick
Provider-wiring RED:           4115df2f64aa4c6eae9abbd33f0d9fe418500a28 / 34401342660 — 453 tests / 2415 assertions, 1 expected failure
Provider-wiring GREEN:         403927a0f2fdb60170f24c3d02756cb41e87965e / 34401520520 — 7/7 green
Review-evidence checkpoint:    5544d5db98b7441e009a57e523b6cc7ccefb1e45 / 34401943831 — 7/7 green
PHP after hardening:           453 tests / 2418 assertions
Browser:                       TypeScript typecheck + 103/103 Vitest tests
Contract:                      green; frozen spec/0.1 unchanged
```

## External-review closure

CodeRabbit's first full review was bound to `main@a3112dea… → 52aaee0e…` and produced two actionable findings:

1. **Package migration installation path — fixed and reviewer-confirmed.** RED `4115df2f…` failed solely because `SurfaceRelayServiceProvider` did not exist. GREEN `403927a0…` adds the provider, Composer Laravel auto-discovery metadata, and Testbench migration-flow coverage. CodeRabbit subsequently inspected the provider, metadata, test and commit and marked the thread addressed/resolved.
2. **Review evidence drift — fixed and reviewer-confirmed.** Records now separate the pre-finding reviewed head, stable code-hardening checkpoint, and docs-only evidence checkpoint instead of recursively attempting to embed a document's own future SHA. CodeRabbit acknowledged the checkpoint model and marked the thread addressed/resolved.

The manual post-fix CodeRabbit review command completed without creating any new actionable inline thread. The PR has **0 unresolved review threads**.

CodeRabbit also suggested deduplicating integration-test scaffolding. That is a trivial maintainability refactor, not a T-404 correctness/security fix. It is intentionally deferred to avoid expanding this security-focused PR with unrelated test-support churn.

## Self-review

- Semantic event projection cannot read ActionCall input/binding/receipt because the factory does not receive `ActionCall`.
- Durable secrecy tests place unique markers in raw/validated input, normal/released output, metadata, idempotency plan data, binding/confirmation material, trusted values/references/scope keys, halt details, challenge fields and schemas/extensions; persisted rows contain none of them.
- Real consequential confirmation proves only trusted authority presence is recorded after receipt consumption.
- Real T-403 withhold/release proves audit does not become a secondary output-disclosure channel.
- Consequential required-key replay proves challenge + first success + replay are separate finalizations, but executor count remains one and replay has no manufactured confirmation authority.
- Store failure is observed after one execution/one append attempt with no internal retry or rollback claim.
- Package migration installation is tested through Laravel/Testbench rather than only by directly requiring the migration file.
- No event sourcing, SIEM/export, retention system, query API/UI, signing/hash chain, KMS or generic Throwable audit subsystem was introduced.

## Known boundaries / non-claims

- Append-only is a SurfaceRelay API contract, not a WORM/database-administrator guarantee.
- T-404 adds no automatic audit retry/outbox because ambiguous database commit outcomes need a separate design.
- It does not claim every thrown exception is durably audited; only completed/explicit-halt finalization is covered.
- It does not persist business history or identity values; downstream compliance/reporting systems would require separate explicitly minimized projections.

## Next boundary

**T-404 is DONE / REVIEWED. PR #4 remains open. The next operation is the explicit PR #4 merge gate; merge is not authorized by this status, and no M5 work should start before that gate is resolved.**
