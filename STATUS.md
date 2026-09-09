# Project Status

> Current repository state for implementation and review.

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Base:** `main@b94ed83497e213c155ae0276264e954b9acd3ac3`
- **Working branch:** `feat/idempotency-store`
- **Stage:** M0 DONE; M1 DONE; M1.1 DONE/REVIEWED; M2 DONE/REVIEWED; M3 DONE/REVIEWED; **M4 IN PROGRESS**
- **Last completed/reviewed/merged task:** `T-401 — Confirmation challenge/receipt`
- **Current task:** `T-402 — Idempotency store` — **DONE / REVIEW PENDING / NOT MERGED**
- **T-402 final code checkpoint:** `d7dca5d5f4670ab6b0c9684f68c2e85dfed30cd2`
- **T-402 code workflow:** `34329096188` — **all 7 jobs green**
- **PHP evidence:** **411 tests / 1893 assertions** across PHP 8.3/8.4 and Illuminate 12/13 matrix
- **Browser isolation evidence:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract evidence:** `python scripts/validate.py` green; `spec/0.1` is frozen and unchanged; fixture/scenario baseline remains **52 + 12**
- **Branch relation at code checkpoint:** ahead-only from exact base; no `main` drift observed
- **Decision:** `D-045 — ACCEPTED`
- **Next gate:** exact-head documentation CI + final compare, then external review/PR as a separate explicit operation
- **Next implementation task:** `T-403 — Output policy/redaction` — **must not begin before T-402 review/merge closure**

## T-402 — Implemented trust boundary

```text
caller invocation
        │
        ├── input validation
        ├── invocation authorization
        ▼
idempotency preflight
        │
        ├── policy=none / recommended without key
        │       ↓
        │   bypass deduplication
        │
        ├── invalid/missing required key
        │       ↓
        │   rejected
        │
        ├── active same-key different intent
        │       ↓
        │   idempotency_conflict
        │
        ├── active in_progress / indeterminate
        │       ↓
        │   fail closed
        │
        ├── exact active completed
        │       ↓
        │   stored raw executor output
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
          persist completed raw output
                ↓
          output policy → audit → success
```

### Locked invariants

1. Idempotency is server-side retry/deduplication state, never authorization authority.
2. Runtime keys are 1..240 Unicode characters and are not normalized before hashing.
3. Raw caller idempotency keys are never persisted. The database primary key is a SHA-256 digest namespaced by exact action ID/version plus trusted authority partition and the raw token.
4. The authority partition uses present tenant/actor identities; when both are absent it falls back to browser-session identity, otherwise an explicit global partition.
5. The intent fingerprint binds exact action ID/version, validated input and present actor/tenant/current-record/current-selection/browser-session identities. Correlation ID, generic metadata, raw key, confirmation receipt, runtime HumanConfirmation, surface and binding ID are excluded.
6. Current validation and authorization always run before idempotency preflight. A completed replay therefore cannot bypass a new authorization denial.
7. Idempotency preflight happens before confirmation, but a fresh execution claim happens only after successful confirmation and immediately before application execution.
8. A completed exact retry replays stored **pre-output-policy** executor output. It skips confirmation/execution but reruns current output policy and audit and keeps the retry's current correlation ID.
9. A replay does not manufacture `human_confirmation`; pre-materialized caller/runtime confirmation authority remains invalid.
10. Atomic claim races allow exactly one claimant into application execution for one active key/intent. A losing completed race replays; conflict/in-progress/indeterminate races fail closed.
11. Executor exceptions do not prove the side effect did not happen. After claim, such failures best-effort transition to `indeterminate`, and the original exception remains primary.
12. Successful executor output must be deterministically JSON/canonical replayable. Unreplayable output becomes `indeterminate` rather than reopening execution.
13. Public success is impossible until the completed replay payload has been durably persisted. Completion-persistence failure leaves the active claim closed as `in_progress`.
14. `DatabaseIdempotencyStore` uses hashed primary-key insert/short row-lock claim transitions and conditional `in_progress` state updates. No DB transaction/row lock spans application executor code.
15. Default retention is 86,400 seconds; active means strict `now < expiresAt`. Equality ends the bounded guarantee and permits a new claim.
16. Public idempotency rejections expose only static codes/messages. Raw key, key hash, intent fingerprint and replay payload are never emitted through `ActionResultNormalizer` details.
17. T-402 is bounded deduplication, not a distributed transaction and not a proof of globally exactly-once external business effects.
18. `spec/0.1` remains unchanged.

## TDD / verification evidence

```text
Pre-Task-5 checkpoint:       d2b30474a2f4a1ce07bc0aa6041ca0b44ef17232 / 34203602150 — 7/7 green
Execution ownership RED:     159740749aa73da201ffdbb496fffc6ec650f60d / 34326974127
Failure-boundary RED:        ebc98af93d25e31ff85b6742e8614a6b1a0d72d5 / 34327261695
Task-5 GREEN:                1f370b487bc5e05618a3b057b4ab44cd97791555 / 34327446638 — 7/7 green
Replay orchestration RED:    88c1922f0d2ccf9aec22fd22d55989bd286b1502 / 34327691831
Result mapping RED:          b7c72ccb8db9452809e6003a11e7226062b9ab24 / 34327726665
Task-6 GREEN:                144142c88b591c96838f6b5834a4dcfa29e437d0 / 34327917164 — 7/7 green
Integration harness RED:     87d6e4764f0f017f086620dbe62e836a50353a1a / 34328505015 — test-harness-only errors
Integration GREEN:           f1ee38285330c5d49af661e4bd0d9bdb10fe88b3 / 34328897541 — 7/7 green
Livewire real-stage proof:    d7dca5d5f4670ab6b0c9684f68c2e85dfed30cd2 / 34329096188 — 7/7 green
Final code PHP:              411 tests / 1893 assertions
Final code browser:          TypeScript typecheck + 103/103 Vitest tests
Final code contract:         frozen spec/0.1 validator green; 52 fixture entries + 12 scenarios unchanged
```

## Decisions

- D-014 ACCEPTED — consequential risk is a categorical confirmation gate.
- D-015 ACCEPTED — confirmation authority is opaque, scoped, expiring and runtime-issued.
- D-044 ACCEPTED — confirmation grants are single-use exact-scope bearer capabilities.
- **D-045 ACCEPTED — server-side idempotency is bounded, partitioned exact-intent deduplication with post-confirmation atomic claim and pre-output-policy completed replay.**

## Known boundaries / non-claims

- T-402 does not implement output policy/redaction itself; T-403 owns that behavior. Replay deliberately feeds stored raw executor output back through the current output-policy stage.
- T-402 does not implement structured audit persistence; T-404 remains separate. Existing audit finalization still runs once per invocation outcome.
- T-402 does not provide distributed transactions, compensation, rollback, or proof of exactly-once behavior in external systems.
- Retention is bounded. Once `expiresAt` is reached, the prior deduplication guarantee intentionally ends and a fresh claim is allowed.
- Shared cross-runtime conformance execution remains T-604/T-701; the current package tests prove Laravel reference-runtime behavior only.

## Next boundary

**T-402 implementation is DONE and repository code verification is green. It is not REVIEWED and not merged. Finish exact-head documentation validation and final compare, then open external review/PR as a separate gate. Do not begin T-403 automatically.**
