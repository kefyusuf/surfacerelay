# External Review Record

## Review status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Scope:** `T-402 — Idempotency store`
- **Exact base / merge-base:** `main@b94ed83497e213c155ae0276264e954b9acd3ac3`
- **Candidate branch:** `feat/idempotency-store`
- **Pull request:** `#2 — feat(laravel): add bounded idempotency replay controls`
- **Final reviewed code checkpoint:** `3a7ab03f821fad4ddee02b2d3ecb9ede0943dbdc`
- **Review finding RED:** `58fb0abb304398931d215dda5d079e5269ac4748` / `34333385345`
- **Review finding GREEN:** `3a7ab03f821fad4ddee02b2d3ecb9ede0943dbdc` / `34333528637` — **all 7 jobs success**
- **PHP evidence:** **412 tests / 1900 assertions** across PHP 8.3/8.4 and Illuminate 12/13, including real MySQL 8.4 round-trip coverage
- **Browser isolation:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract:** `python scripts/validate.py` green; frozen `spec/0.1` unchanged; **52 fixture entries + 12 scenarios** unchanged
- **Decision:** `D-045 — ACCEPTED`
- **External automated reviewer:** CodeRabbit full PR review
- **External review result:** **PASSED WITH ONE MAJOR FINDING, FIXED TDD-FIRST AND REVERIFIED**
- **Open review threads:** **0**
- **Merge status:** **MERGE READY / NOT MERGED AT THIS CHECKPOINT**
- **M4 status:** IN PROGRESS; T-403/T-404 remain TODO

## Reviewed trust boundary

T-402 adds bounded server-side retry/deduplication to the Laravel reference runtime without changing `spec/0.1` wire shapes:

```text
validated + currently authorized invocation
        ↓
idempotency preflight
        ├── safe refusal → rejected
        ├── exact completed → replay stored pre-policy executor output
        │                       ↓
        │                 output policy + audit
        │
        └── fresh → confirmation if required
                        ↓
                 atomic in_progress claim
                        ↓
                  application executor
                        ↓
               durable completed payload
                        ↓
                 output policy + audit
```

The production database adapter persists only hashed lookup/fingerprint state and deterministic replay payload. The raw caller idempotency key is never stored. No database transaction or row lock spans application executor code.

## Locked implemented semantics

1. `none`, `recommended_key`, and `required_key` are enforced server-side; required missing/invalid keys reject before confirmation/execution.
2. Runtime keys are exact 1..240 Unicode-character strings with no trimming, case folding, or normalization.
3. Raw caller keys are never persisted. A SHA-256 lookup hash binds exact action ID/version, trusted authority partition, and the raw retry key.
4. Authority partitioning uses present tenant/actor identities; if both are absent it falls back to browser-session identity, otherwise an explicit global partition.
5. A separate intent fingerprint binds exact action ID/version, validated input, and present actor/tenant/current-record/current-selection/browser-session identities.
6. Correlation ID, generic metadata, the raw key, confirmation receipt, runtime `HumanConfirmation`, surface, and `bindingId` do not alter the intent fingerprint.
7. Validation and current authorization always precede idempotency replay. Fresh ownership is claimed only after required confirmation and immediately before application execution.
8. Exact completed replay skips confirmation/execution but reruns current output policy and audit and uses the retry's current correlation ID.
9. One atomic fresh claimant reaches application execution; conflict, active `in_progress`, and active `indeterminate` reuse fail closed.
10. Executor uncertainty is never treated as proof of no side effect. Executor failure after claim is best-effort marked `indeterminate`.
11. Replay payload is deterministic JSON/canonical data, never PHP serialization. Unreplayable successful output becomes `indeterminate`.
12. Public success is impossible until the completed replay payload persists; completion-store failure leaves the active claim closed as `in_progress`.
13. `DatabaseIdempotencyStore` uses hashed primary-key insert, a short row-lock transaction only for existing/expired claim resolution, and exact conditional state transitions. Executor code runs outside DB locks/transactions.
14. Default retention is 86,400 seconds with strict `now < expiresAt`; equality ends the bounded guarantee and allows a fresh claim.
15. Public idempotency refusals expose only static codes/messages; raw keys, hashes, intent fingerprints, and replay payloads do not appear in public result details.
16. T-401 confirmation receipt single-use semantics remain separate from T-402 deduplication. Lost-response replay consumes no second receipt and executes no second side effect.
17. This is bounded deduplication, not a distributed transaction and not a globally exactly-once external-side-effect guarantee.
18. Idempotency timestamps are explicitly schema-pinned to second precision (`precision: 0`) so persistence matches the strict UTC `Y-m-d H:i:s` hydrator contract even when an application sets Laravel global time precision to a fractional value.

## External review finding

CodeRabbit identified one **Major — Data Integrity & Integration** issue: the migration originally omitted explicit timestamp precision while `DatabaseIdempotencyStore::parseTimestamp()` accepts only second-precision `Y-m-d H:i:s`. Laravel 12/13 allow global time precision to be configured; under precision `6`, MySQL returns values such as `2026-09-09 09:00:00.000000`, which the strict hydrator correctly rejects.

The finding was verified rather than accepted speculatively. A real MySQL 8.4 regression test was added and run across all four PHP/Illuminate matrix combinations with `MySqlBuilder::defaultTimePrecision(6)`.

```text
Review RED:   58fb0abb304398931d215dda5d079e5269ac4748 / 34333385345
              all four PHP matrix jobs failed on the MySQL migration round-trip;
              expected second precision, received `.000000` fractional precision

Review GREEN: 3a7ab03f821fad4ddee02b2d3ecb9ede0943dbdc / 34333528637
              both migration timestamps explicitly use precision: 0;
              all 7 jobs success; 412 tests / 1900 assertions per PHP matrix job
```

The hydrator was not relaxed and no alternate timestamp format was accepted. The persistence schema was aligned to the existing exact runtime representation instead.

## Failure-safety evidence

The test suite covers:

- lost successful response followed by exact consequential retry → executor total calls remain 1;
- same-key changed validated input/record/selection/browser session → `idempotency_conflict` before confirmation/execution;
- different actor/tenant/action ID/action version → independent reuse of the same raw key;
- changed surface or binding alone → completed replay;
- current authorization denial → halts before replay;
- missing required key → halts before confirmation/execution;
- active `in_progress` / `indeterminate` → no re-execution;
- policy `none` → two calls execute twice;
- executor side-effect boundary then throw → same active key cannot execute again;
- unreplayable successful output → `indeterminate`;
- completion persistence failure → no success and retry remains `in_progress`;
- expiry equality → bounded guarantee ends and a new claim can execute after fresh confirmation;
- public normalization discards deliberately injected raw-key/hash/fingerprint details;
- package migration → MySQL 8.4 → claim → raw timestamp read → strict store hydration under global fractional precision.

## Verification evidence

```text
Task-5 GREEN:                1f370b487bc5e05618a3b057b4ab44cd97791555 / 34327446638 — 7/7 green
Task-6 GREEN:                144142c88b591c96838f6b5834a4dcfa29e437d0 / 34327917164 — 7/7 green
Integration GREEN:           f1ee38285330c5d49af661e4bd0d9bdb10fe88b3 / 34328897541 — 7/7 green
Livewire real-stage proof:    d7dca5d5f4670ab6b0c9684f68c2e85dfed30cd2 / 34329096188 — 7/7 green
Initial review head:          a46297f8edc18d3485ff9822ded5e6207c1888c3 / 34331545331 — PR CI green
Review finding RED:          58fb0abb304398931d215dda5d079e5269ac4748 / 34333385345
Review finding GREEN:        3a7ab03f821fad4ddee02b2d3ecb9ede0943dbdc / 34333528637 — 7/7 green
PHP:                         412 tests / 1900 assertions, PHP 8.3/8.4 × Illuminate 12/13 + MySQL 8.4
Browser:                     TypeScript typecheck + 103/103 Vitest tests
Contract:                    frozen spec/0.1 unchanged; 52 fixture entries + 12 scenarios unchanged
Open review threads:         0
```

## Explicit non-claims

T-402 does **not** provide a distributed transaction, external-system compensation, rollback, globally exactly-once effects, T-403 output redaction, T-404 structured audit persistence, T-504 human UI, or shared T-701 cross-runtime conformance execution.

## Review outcome

**T-402 external automated review passed after its only actionable finding was reproduced on real MySQL, fixed TDD-first, and revalidated across the full PHP/Illuminate matrix. PR #2 is merge-ready; merge remains a separate explicit operation.**
