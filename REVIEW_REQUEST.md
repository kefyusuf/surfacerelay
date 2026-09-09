# External Review Request

## Review status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Scope:** `T-402 — Idempotency store`
- **Exact base / merge-base:** `main@b94ed83497e213c155ae0276264e954b9acd3ac3`
- **Candidate branch:** `feat/idempotency-store`
- **Final code checkpoint:** `d7dca5d5f4670ab6b0c9684f68c2e85dfed30cd2`
- **Final code workflow:** `34329096188` — **all 7 jobs success**
- **PHP evidence:** **411 tests / 1893 assertions** across PHP 8.3/8.4 and Illuminate 12/13
- **Browser isolation:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract:** `python scripts/validate.py` green; frozen `spec/0.1` unchanged; **52 fixture entries + 12 scenarios** unchanged
- **Decision:** `D-045 — ACCEPTED`
- **External review result:** **PENDING**
- **Pull request:** **NOT OPENED AT THIS CHECKPOINT**
- **Merge status:** **NOT MERGED**
- **M4 status:** IN PROGRESS; T-403/T-404 remain TODO

## Review target

T-402 adds bounded server-side retry/deduplication to the Laravel reference runtime without changing `spec/0.1` wire shapes:

```text
validated + currently authorized invocation
        ↓
idempotency preflight
        ├── safe refusal → rejected
        ├── exact completed → replay raw stored executor output
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

1. **Policy behavior:** `none` bypasses deduplication; `recommended_key` deduplicates only when a key is supplied; `required_key` rejects missing/invalid keys before confirmation/execution.
2. **Key lexical contract:** runtime key length is 1..240 Unicode characters; no normalization is performed.
3. **Raw-key secrecy:** the persisted lookup key is SHA-256 over exact action ID/version + trusted authority partition + raw caller key. The raw key is not a database column.
4. **Authority partition:** present tenant/actor identity scopes the key; if both are absent, present browser-session identity is used; otherwise the partition is explicit global.
5. **Exact intent:** a separate SHA-256 fingerprint includes exact action ID/version, validated input and present actor/tenant/current-record/current-selection/browser-session identities.
6. **Transient exclusions:** correlation ID, generic metadata, raw idempotency key, confirmation receipt, runtime `human_confirmation`, surface and `bindingId` do not alter the intent fingerprint.
7. **Ordering:** validation and authorization run before idempotency; preflight runs before confirmation; fresh ownership is claimed only after confirmation and immediately before application execution.
8. **Completed replay:** exact active completed retry reruns validation/authorization, skips confirmation/execution, reruns current output policy and audit, and uses the retry's current correlation ID without manufacturing confirmation authority.
9. **Race behavior:** one fresh claimant owns execution. A losing completed race replays; same-key conflict, active in-progress and indeterminate reuse fail closed.
10. **Executor uncertainty:** application exception after claim is conservatively best-effort marked `indeterminate`; SurfaceRelay does not assume an exception proves no side effect occurred.
11. **Replay safety:** successful executor output must be deterministic JSON/canonical data. Unrepresentable output becomes `indeterminate` and does not reopen the key.
12. **Durability before success:** completed replay payload must persist before public success. Completion persistence failure leaves the key closed as `in_progress`.
13. **Database atomicity:** `DatabaseIdempotencyStore` uses a hashed primary-key insert and short `lockForUpdate()` transaction only to resolve existing/expired claim state; completion/indeterminate transitions are conditional on exact key + intent + `in_progress`.
14. **Bounded retention:** default retention is 86,400 seconds. Active validity is strict `now < expiresAt`; equality permits a new claim.
15. **Public result secrecy:** five idempotency refusal codes normalize to static `rejected` results with no raw key, key hash, intent fingerprint or replay payload details.
16. **T-401 interaction:** single-use confirmation receipts and T-402 deduplication remain separate protections. Lost-response replay does not require/consume a second confirmation receipt.
17. **Non-claim:** this is bounded deduplication, not a distributed transaction and not proof of globally exactly-once external side effects.

## High-value review focus

Please challenge these boundaries specifically:

- Can the same raw key collide across actions, action versions, tenants, actors, or browser-session fallback partitions in a way that suppresses a legitimate independent invocation?
- Can changing validated input/current record/current selection/browser session replay an older completed result instead of conflicting?
- Can changing only transient surface/binding/correlation metadata incorrectly defeat exact completed replay?
- Can a completed replay bypass a new authorization denial or manufacture/consume confirmation authority?
- Is there any race path where two callers both reach application execution for one active key/intent?
- Can an executor exception, unreplayable output, store transition failure, or claim contention reopen automatic execution unsafely?
- Does the database implementation persist or leak the raw key, use PHP serialization, or hold transaction/row-lock scope across application code?
- Can public results/provenance expose raw keys, hashes, fingerprints or replay payloads?
- Does exact expiry equality behave consistently with the documented bounded guarantee?
- Did any T-403 redaction, T-404 audit persistence, browser production behavior, or `spec/0.1` contract change leak into scope?

## TDD / verification evidence

```text
Pre-Task-5 checkpoint:       d2b30474a2f4a1ce07bc0aa6041ca0b44ef17232 / 34203602150 — 7/7 green
Execution ownership RED:     159740749aa73da201ffdbb496fffc6ec650f60d / 34326974127
Failure-boundary RED:        ebc98af93d25e31ff85b6742e8614a6b1a0d72d5 / 34327261695
Task-5 GREEN:                1f370b487bc5e05618a3b057b4ab44cd97791555 / 34327446638 — 7/7 green
Replay orchestration RED:    88c1922f0d2ccf9aec22fd22d55989bd286b1502 / 34327691831
Result mapping RED:          b7c72ccb8db9452809e6003a11e7226062b9ab24 / 34327726665
Task-6 GREEN:                144142c88b591c96838f6b5834a4dcfa29e437d0 / 34327917164 — 7/7 green
Integration harness RED:     87d6e4764f0f017f086620dbe62e836a50353a1a / 34328505015 — harness-only errors; production remained unchanged
Integration GREEN:           f1ee38285330c5d49af661e4bd0d9bdb10fe88b3 / 34328897541 — 7/7 green
Livewire real-stage proof:    d7dca5d5f4670ab6b0c9684f68c2e85dfed30cd2 / 34329096188 — 7/7 green
PHP:                         411 tests / 1893 assertions
Browser:                     TypeScript typecheck + 103/103 Vitest tests
Contract:                    frozen spec/0.1 unchanged; 52 fixture entries + 12 scenarios unchanged
```

## Failure-safety evidence

The integration/unit suite explicitly covers:

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
- public normalization discards deliberately injected raw-key/hash/fingerprint details.

## Scope / secret review already performed

- exact branch base and merge-base are `b94ed83497e213c155ae0276264e954b9acd3ac3`;
- branch was ahead-only at the final code checkpoint;
- `spec/0.1/**` does not appear in the implementation diff;
- database schema contains `key_hash`, `intent_fingerprint`, state, replay payload and timestamps only — no raw-key field;
- replay encoding uses deterministic canonical JSON, not PHP serialization;
- executor code runs outside the database claim transaction/row-lock scope;
- T-403 output redaction and T-404 structured audit persistence are not implemented by this change;
- browser production runtime behavior is unchanged.

## Explicit non-claims

T-402 does **not** provide a distributed transaction, external-system compensation, rollback, globally exactly-once effects, T-403 output redaction, T-404 audit persistence, T-504 human UI, or shared T-701 cross-runtime conformance execution.

## Review outcome

**T-402 implementation and internal verification are complete. External review is intentionally still pending. PR creation and merge remain separate explicit gates; do not merge automatically.**
