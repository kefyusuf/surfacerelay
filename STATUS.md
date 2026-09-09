# Project Status

> Current repository state for implementation and review.

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `main`
- **Stage:** M0 DONE; M1 DONE; M1.1 DONE/REVIEWED; M2 DONE/REVIEWED; M3 DONE/REVIEWED; **M4 IN PROGRESS**
- **Last completed/reviewed/merged task:** `T-402 — Idempotency store`
- **Pull request:** `#2 — merged`
- **Final reviewed code checkpoint:** `3a7ab03f821fad4ddee02b2d3ecb9ede0943dbdc`
- **Review-passed branch checkpoint:** `ff6bc3c07567b4e0e84fd4f57cd0006236658c7d`
- **Merge commit:** `b4a43ff2526f680656762671a1eee67b4135ee03`
- **Merged-main workflow:** `34335064423` — **all 7 jobs green**
- **PHP evidence:** **412 tests / 1900 assertions** across PHP 8.3/8.4 and Illuminate 12/13, with real MySQL 8.4 migration round-trip coverage
- **Browser isolation evidence:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract evidence:** `python scripts/validate.py` green; frozen `spec/0.1` unchanged; fixture/scenario baseline **52 + 12**
- **External automated review:** CodeRabbit — one Major timestamp-precision finding, reproduced on real MySQL and fixed TDD-first
- **Open review threads:** **0**
- **Decision:** `D-045 — ACCEPTED`
- **Next task:** `T-403 — Output policy/redaction` — **not started**

## T-402 — Implemented trust boundary

```text
caller invocation
        │
        ├── input validation
        ├── current invocation authorization
        ▼
idempotency preflight
        │
        ├── policy bypass → continue normally
        ├── invalid/missing required key → rejected
        ├── conflict / in_progress / indeterminate → fail closed
        ├── exact active completed
        │       ↓
        │   stored pre-policy executor output
        │       ↓
        │   skip confirmation + execution
        │       ↓
        │   current output policy → audit → success
        │
        └── fresh intent
                ↓
          confirmation if required
                ↓
          atomic in_progress claim
                ↓
          application executor
                ↓
          persist completed replay output
                ↓
          output policy → audit → success
```

### Locked invariants

1. Idempotency state is retry/deduplication state, never authorization authority.
2. Runtime keys are exact 1..240 Unicode-character strings and are not normalized before hashing.
3. Raw caller keys are never persisted. The database primary key is a SHA-256 digest over exact action ID/version, trusted authority partition, and raw retry key.
4. Authority partitioning uses present tenant/actor identities; when both are absent it falls back to browser-session identity, otherwise an explicit global partition.
5. Intent fingerprinting binds exact action ID/version, validated input and present actor/tenant/current-record/current-selection/browser-session identities. Correlation ID, generic metadata, raw key, confirmation receipt, runtime `HumanConfirmation`, surface, and binding ID are excluded.
6. Current validation and authorization always run before idempotency preflight; completed replay cannot bypass a new authorization denial.
7. Fresh execution ownership is claimed only after required confirmation and immediately before application execution.
8. Exact completed replay uses stored **pre-output-policy** executor output, skips confirmation/execution, reruns current output policy and audit, and preserves the retry's current correlation ID.
9. Replay never manufactures `human_confirmation` authority and consumes no second confirmation receipt.
10. Atomic claim races permit only one claimant to enter application execution for one active key/intent. Conflict/in-progress/indeterminate races fail closed; a completed race replays.
11. Executor exceptions after claim are conservatively best-effort marked `indeterminate`; SurfaceRelay never assumes an exception proves no side effect occurred.
12. Replay output must be deterministic JSON/canonical data. Unreplayable successful output becomes `indeterminate`, never a reopened key.
13. Public success is impossible until completed replay payload persistence succeeds. Completion persistence failure leaves the key closed as `in_progress`.
14. `DatabaseIdempotencyStore` uses hashed primary-key insert and short row-lock claim transitions only; no DB transaction or row lock spans application executor code.
15. Default retention is 86,400 seconds; active means strict `now < expiresAt`. Equality ends the bounded guarantee and allows a new claim.
16. Public idempotency rejections expose only static codes/messages; raw key, key hash, intent fingerprint, and replay payload are not emitted.
17. Idempotency persistence timestamps are explicitly pinned to second precision (`precision: 0`) to match the strict UTC `Y-m-d H:i:s` hydrator contract even if the host application configures Laravel fractional time precision.
18. T-402 is bounded deduplication, not a distributed transaction and not proof of globally exactly-once external effects.
19. `spec/0.1` remains unchanged.

## External review finding and closure

CodeRabbit identified one Major data-integrity issue: migration timestamp columns inherited Laravel's configurable global time precision, while `DatabaseIdempotencyStore` intentionally hydrates only exact second-precision UTC strings.

The issue was reproduced with a real MySQL 8.4 service and `MySqlBuilder::defaultTimePrecision(6)` before production code changed:

```text
RED:   58fb0abb304398931d215dda5d079e5269ac4748 / 34333385345
       expected 2026-09-09 09:00:00
       actual   2026-09-09 09:00:00.000000
       all four PHP/Illuminate matrix jobs failed the new regression

GREEN: 3a7ab03f821fad4ddee02b2d3ecb9ede0943dbdc / 34333528637
       created_at and expires_at explicitly use precision: 0
       all 7 jobs green
       PHP: 412 tests / 1900 assertions per matrix job
```

The hydrator was not weakened. MySQL 8.4 round-trip coverage now runs in every PHP 8.3/8.4 × Illuminate 12/13 CI matrix combination.

## Verification evidence

```text
Task-5 GREEN:                 1f370b487bc5e05618a3b057b4ab44cd97791555 / 34327446638 — 7/7 green
Task-6 GREEN:                 144142c88b591c96838f6b5834a4dcfa29e437d0 / 34327917164 — 7/7 green
Integration GREEN:            f1ee38285330c5d49af661e4bd0d9bdb10fe88b3 / 34328897541 — 7/7 green
Livewire real-stage proof:     d7dca5d5f4670ab6b0c9684f68c2e85dfed30cd2 / 34329096188 — 7/7 green
Initial PR head:              a46297f8edc18d3485ff9822ded5e6207c1888c3 / 34331545331 — PR CI green
Review finding RED:           58fb0abb304398931d215dda5d079e5269ac4748 / 34333385345
Review finding GREEN:         3a7ab03f821fad4ddee02b2d3ecb9ede0943dbdc / 34333528637 — 7/7 green
Review-passed branch head:    ff6bc3c07567b4e0e84fd4f57cd0006236658c7d / 34334459976 — 7/7 green
Merged main:                  b4a43ff2526f680656762671a1eee67b4135ee03 / 34335064423 — 7/7 green
Final PHP:                    412 tests / 1900 assertions, PHP 8.3/8.4 × Illuminate 12/13 + MySQL 8.4
Final browser:                TypeScript typecheck + 103/103 Vitest tests
Final contract:               frozen spec/0.1 validator green; 52 fixture entries + 12 scenarios unchanged
Open review threads:          0
```

## Decisions

- D-014 ACCEPTED — consequential risk is a categorical confirmation gate.
- D-015 ACCEPTED — confirmation authority is opaque, scoped, expiring and runtime-issued.
- D-044 ACCEPTED — confirmation grants are single-use exact-scope bearer capabilities.
- **D-045 ACCEPTED — server-side idempotency is bounded, partitioned exact-intent deduplication with post-confirmation atomic claim and pre-output-policy completed replay.**

## Known boundaries / non-claims

- T-402 does not implement output policy/redaction itself; T-403 owns that behavior. Replay deliberately feeds stored pre-policy executor output back through the current output-policy stage.
- T-402 does not implement structured audit persistence; T-404 remains separate.
- T-402 does not provide distributed transactions, compensation, rollback, or proof of exactly-once behavior in external systems.
- Retention is bounded. At `expiresAt`, the prior deduplication guarantee intentionally ends and a fresh claim is allowed.
- Shared cross-runtime conformance execution remains T-604/T-701; current tests prove Laravel reference-runtime behavior only.

## Next boundary

**T-402 is DONE / REVIEWED / MERGED and independently revalidated on `main`. T-403 remains not started and must not begin automatically.**
