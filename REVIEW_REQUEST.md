# External Review Request

## Review status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Scope:** `T-404 — Structured audit events`
- **Branch:** `feat/structured-audit-events`
- **Pull request:** `#4`
- **Base / merge-base:** `main@a3112dea3f965e27db8e6904650c97f89d2261fe`
- **Exact production/test-code checkpoint:** `79df19c3a95f1a95b03e4b5c6578d8195903d22a`
- **Exact code workflow:** `34384669898` — **7/7 green**
- **Review-prep checkpoint before this record:** `4092616cce0a325a5130f3455bbb3c4bc16c0682`
- **Review-prep workflow:** `34385271078` — **7/7 green**
- **PHP evidence:** **452 tests / 2414 assertions** across PHP 8.3/8.4 × Illuminate 12/13 with MySQL 8.4 service coverage
- **Browser isolation:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract:** `python scripts/validate.py` green; frozen `spec/0.1/**` unchanged
- **Decision:** `D-047 — ACCEPTED`
- **Code-checkpoint diff:** **18 ahead / 0 behind**, 22 changed files
- **External review result:** pending
- **Merge:** not requested; explicit permission remains required

## Review objective

Challenge the new durable audit boundary for confidentiality leaks, accidental business-payload history, bearer-capability persistence, authority expansion, replay confusion, unsafe database error exposure, or claims broader than the runtime actually guarantees.

The intended model is deliberately narrow: one append-only, payload-minimized audit event for each `ActionBus` dispatch that reaches the existing completed/explicit-halt finalization boundary.

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
15. `spec/0.1/**` is unchanged.

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
Plan-head baseline:          777442e4c65ed0f8ba10ac13d76a6fccc2926dcc / 34380200139 — 7/7 green
Task-1 semantic RED:         12252f6cb5eeaf087b587dc0590a3939df13a6a5 / 34381899289
Task-1 GREEN:                2d51abb731335e409ff2984b784096625fab6ad3 / 34382322354 — 7/7 green
Task-2 auditor/store RED:    ab638b8dba7344803dde0f0f203426bc1db8956e / 34382547037
Task-2 GREEN:                6b1cf5f49f49f0097d2de503e2e5cec61e0c8901 / 34382775139 — 7/7 green
Task-3 durable-store RED:    b5132fa9c769428db353f1e94e1fd0c2572db0cb / 34383113630
Task-3 GREEN:                23c978e5d576a19410cda4669e2ab1e43bc7254c / 34383330077 — 7/7 green
Task-4 pipeline RED:         08a4a14797078584f4deed95231612966024cd28 / 34383610213
Task-4 GREEN:                da37400c14e558438b84ea9a140eb06fd50e1f69 / 34383939887 — 7/7 green
Task-5 replay/secrecy RED:   7a9adb89bd7ab8cc525a19bf061e37886bb36527 / 34384242487
Task-5 GREEN/code checkpoint:79df19c3a95f1a95b03e4b5c6578d8195903d22a / 34384669898 — 7/7 green
Review-prep checkpoint:      4092616cce0a325a5130f3455bbb3c4bc16c0682 / 34385271078 — 7/7 green
PHP:                         452 tests / 2414 assertions
Browser:                     TypeScript typecheck + 103/103 Vitest tests
Contract:                    green; frozen spec/0.1 unchanged
```

## Review focus

Please prioritize:

1. **Leak paths:** can any raw/business payload, identity, capability or diagnostic value enter the semantic event or durable row indirectly?
2. **Factory boundary:** does excluding `ActionCall` from `AuditEventFactory` sufficiently prevent caller-only candidates becoming audit data by convenience?
3. **Trusted provenance minimization:** is `{requirement, provider}` the correct maximum retained trusted-context detail for this task?
4. **Append-only persistence:** can duplicate IDs, query errors or JSON failures overwrite/partially replace prior evidence or leak diagnostics?
5. **Post-execution failure semantics:** does propagating audit-store failure avoid false success without implying rollback?
6. **T-402 replay:** does replay correctly create a separate audit event while preserving one business execution and no replayed confirmation authority?
7. **T-403 interaction:** is there any route by which raw or released output becomes audit data despite the allowlist?
8. **Exception boundary honesty:** does the implementation avoid implying that arbitrary PHP exceptions are durably audited when they bypass `ActionBus::finalize()`?
9. **Scope restraint:** no event sourcing, telemetry exporter, query API/UI, retention, signing/hash-chain, KMS or audit retry/outbox should have slipped into T-404.

## Non-claims

- Append-only is a SurfaceRelay API/persistence-write contract, not a tamper-proof/WORM guarantee against database administrators.
- T-404 is not a business history store, identity ledger, SIEM or distributed-tracing system.
- It does not add automatic audit retry/outbox behavior for ambiguous database commits.
- It does not durably record every arbitrary PHP exception.

## Review gate

**Implementation and internal verification are complete at the exact code checkpoint above. Review-prep was revalidated on the exact PR head before this documentation record. External review is the current gate. Merge remains a separate explicit decision and is not requested by this document.**
