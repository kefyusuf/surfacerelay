# External Review Record

## Review status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Scope:** `T-404 — Structured audit events`
- **Branch:** `feat/structured-audit-events`
- **Pull request:** `#4`
- **Base / merge-base:** `main@a3112dea3f965e27db8e6904650c97f89d2261fe`
- **Original production/test checkpoint:** `79df19c3a95f1a95b03e4b5c6578d8195903d22a`
- **Original code workflow:** `34384669898` — **7/7 green**
- **Pre-finding external-review head:** `52aaee0e5e446311fb4554c67789ebefbbf116fb`
- **Pre-finding review-head workflow:** `34385980442` — **7/7 green**
- **CodeRabbit full-review run:** `cad69fe7-6838-42a2-8fca-496f9f592309`
- **First-review result:** 2 actionable findings + 1 trivial maintainability nitpick
- **Review-hardening code checkpoint:** `403927a0f2fdb60170f24c3d02756cb41e87965e`
- **Review-hardening workflow:** `34401520520` — **7/7 green**
- **PHP evidence after hardening:** **453 tests / 2418 assertions** across PHP 8.3/8.4 × Illuminate 12/13 with MySQL 8.4 service coverage
- **Browser isolation:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract:** `python scripts/validate.py` green; frozen `spec/0.1/**` unchanged
- **Decision:** `D-047 — ACCEPTED`
- **Incremental external review:** pending for the post-review hardening delta
- **Merge:** not requested; explicit permission remains required

## Review objective

Challenge the durable audit boundary for confidentiality leaks, accidental business-payload history, bearer-capability persistence, authority expansion, replay confusion, unsafe database error exposure, package-installation gaps, or claims broader than the runtime actually guarantees.

The intended model remains narrow: one append-only, payload-minimized audit event for each `ActionBus` dispatch that reaches the existing completed/explicit-halt finalization boundary.

## Implemented semantics

1. `ActionPipelineAuditor::record(ActionCall, ActionPipelineOutcome)` remains source-compatible.
2. `StructuredActionPipelineAuditor` creates one semantic event and calls `AuditEventStore::append()` once per finalizer invocation.
3. `AuditEventFactory` receives only `ActionPipelineOutcome`, not `ActionCall`, and projects an explicit allowlist from final state.
4. Persisted semantic facts are limited to event ID/time, correlation/surface labels, exact action/version and declared trust enums, completed/halted state, halt stage/code, confirmation-authority presence, and canonical `{requirement, provider}` trusted-context manifest.
5. Raw/validated input, every form of output, generic metadata, idempotency keys/hashes/fingerprints/replay payloads, binding IDs, confirmation receipts/challenges, trusted context values/references/scope keys, halt details, exception diagnostics, schemas and extensions are excluded.
6. `humanConfirmationPresent` is a boolean derived only from final trusted context presence. Receipt/challenge capability material is never persisted.
7. Trusted context persists only canonical requirement + provider pairs; actor/tenant/record/selection/session values are not audit data.
8. `DatabaseAuditEventStore` performs one insert; duplicate event IDs fail instead of replacing/upserting/ignoring prior evidence.
9. Correlation and halt-code hashes are database equality-index helpers only and do not appear in `AuditEvent` or confer authority.
10. JSON and database failures map to static, non-chained audit domain exceptions so SQL/table/row/driver diagnostics are not exposed.
11. Audit persistence failure propagates. SurfaceRelay does not return the original finalized outcome as if the trust control succeeded, but it does not claim an already-run side effect was rolled back.
12. An exact T-402 completed replay creates a new final audit event, reruns current T-403 output policy, does not execute application code twice and does not manufacture/replay human-confirmation authority.
13. T-403 raw output and even successfully released/redacted output are both excluded from T-404 persistence.
14. T-404 does not convert arbitrary PHP `Throwable` paths into audit events; only the existing completed/explicit-halt finalization boundary is guaranteed.
15. The Laravel package now auto-discovers `SurfaceRelayServiceProvider`; that provider loads the shipped migration path so the audit table migration participates in normal Laravel package installation/migration flow.
16. `spec/0.1/**` is unchanged.

## Explicit forbidden persistence set

Adversarial durable-row tests place unique markers in all reachable sensitive sources and assert none survive into any scalar/JSON persisted value:

- raw and validated action input;
- normal output and T-403 released output;
- generic metadata;
- raw idempotency key;
- idempotency lookup hash, intent fingerprint and replay payload;
- binding ID;
- confirmation receipt and challenge ID/summary;
- authenticated actor, tenant, current-record, current-selection and browser-session values;
- context provenance references;
- confirmation/stable scope keys;
- halt details;
- ActionDefinition input/output schemas and extensions.

The audit table likewise contains no corresponding business/authority columns.

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
PHP after hardening:           453 tests / 2418 assertions
Browser:                       TypeScript typecheck + 103/103 Vitest tests
Contract:                      green; frozen spec/0.1 unchanged
```

## External review findings

### 1. Package migration installation path — FIXED

The first review correctly identified that the migration existed but was only exercised by directly requiring its file. There was no package service provider or Composer Laravel auto-discovery metadata, so consumers could enable `DatabaseAuditEventStore` without the package exposing its audit migration through the normal Laravel installation path.

TDD closure:

```text
RED:   4115df2f64aa4c6eae9abbd33f0d9fe418500a28 / 34401342660
       453 tests / 2415 assertions, exactly one expected failure:
       SurfaceRelayServiceProvider must exist so Laravel can install package migrations.

GREEN: 403927a0f2fdb60170f24c3d02756cb41e87965e / 34401520520
       7/7 CI green; 453 tests / 2418 assertions.
```

The minimal fix adds `SurfaceRelayServiceProvider::boot()` with `loadMigrationsFrom(...)`, registers the provider in `composer.json` Laravel auto-discovery metadata, and verifies provider metadata + migration execution through Orchestra Testbench. The migration schema and ActionBus flow are unchanged.

### 2. Review evidence drift — FIXED

The first review found stale final-review SHA/workflow values in `STATUS.md` and this document. The records are now expressed as stable evidence checkpoints rather than attempting to make a document contain the SHA of the commit that contains itself:

- `52aaee0e… / 34385980442` is the exact head/workflow that the first full CodeRabbit review covered;
- `403927a0… / 34401520520` is the exact post-finding production/test hardening checkpoint and workflow;
- later review-closure documentation commits are documentation-only and do not redefine the code checkpoint.

### Trivial maintainability suggestion — DEFERRED

CodeRabbit also suggested extracting duplicated integration-test serializers/confirmation scaffolding into shared test support. That change does not fix T-404 behavior, security, migration availability, or a failing test. It would widen a trust-control PR with test-support refactoring and create unnecessary review churn, so it is intentionally deferred.

## Review focus for incremental pass

Please prioritize the delta after `52aaee0e…`:

1. Does `SurfaceRelayServiceProvider::loadMigrationsFrom(...)` resolve the correct package migration directory on Laravel 12 and 13?
2. Does Composer Laravel auto-discovery metadata register exactly that provider without affecting runtime contracts?
3. Does the Testbench coverage actually prove the package migration can be loaded and executed through Laravel migration flow?
4. Did the review fix alter the audit schema, ActionBus semantics, payload minimization, frozen wire contracts or T-402/T-403 boundaries? It should not.
5. Are review records now internally consistent without self-referential SHA chasing?

## Non-claims

- Append-only is a SurfaceRelay API/persistence-write contract, not a tamper-proof/WORM guarantee against database administrators.
- T-404 is not a business history store, identity ledger, SIEM or distributed-tracing system.
- It does not add automatic audit retry/outbox behavior for ambiguous database commits.
- It does not durably record every arbitrary PHP exception.

## Review gate

**The first full external review produced two actionable findings. Both have been addressed; the production/test hardening checkpoint `403927a0…` is 7/7 green with 453 tests / 2418 assertions. Incremental external review of the hardening delta is the current gate. Merge remains a separate explicit decision and is not requested by this document.**
