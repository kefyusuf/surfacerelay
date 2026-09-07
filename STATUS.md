# Project Status

> Current repository state for implementation and review.

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Base:** `main@5eb33c203fb40fc2ff744f2f4a57cbce4c704b80`
- **Working branch:** `feat/confirmation-challenge-receipt`
- **Stage:** M0 DONE; M1 DONE; M1.1 DONE/REVIEWED; M2 DONE/REVIEWED; M3 DONE/REVIEWED; **M4 IN PROGRESS**
- **Current task:** `T-401 — Confirmation challenge/receipt` — **DONE / READY FOR EXTERNAL REVIEW**
- **Implementation verification checkpoint:** `7db7d54fd1af9387c3cb408ec472c949168ed569`
- **Implementation CI:** workflow `34130941909` — **all 7 jobs green**
- **PHP evidence:** **344 tests / 1299 assertions** across PHP 8.3/8.4 and Illuminate 12/13 matrix
- **Browser isolation evidence:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract evidence:** `python scripts/validate.py` green; `spec/0.1` is frozen and unchanged; fixture/scenario baseline remains **52 + 12**
- **Diff evidence:** implementation checkpoint is based exactly on `main@5eb33c20...`, 0 commits behind; no `spec/0.1`, browser production, T-402, T-403 or T-404 implementation changes
- **Next task:** `T-402 — Idempotency store` — **not started**
- **Merge status:** T-401 is **not merged**; external review is still required

## T-401 — Implemented trust boundary

```text
caller invocation candidate
        │
        ├── input validation
        ├── invocation authorization
        ▼
confirmation stage
        │
        ├── no confirmation required → continue
        │
        ├── no valid receipt
        │       ↓
        │   issue opaque pending challenge
        │       ↓
        │   confirmation_required
        │
        └── exact approved receipt
                ├── deterministic exact-scope fingerprint
                ├── strict expiry check
                ├── atomic single-use consume
                ↓
        trusted HumanConfirmation
                ↓
        idempotency → execution → output policy
```

### Locked invariants

1. Confirmation is required for `risk=consequential` even if the definition omitted `human_confirmation`; explicit `human_confirmation` also requires it.
2. Caller input, metadata, `confirmed=true`, a pending challenge, a forged receipt, or a pre-materialized HumanConfirmation entry never grants authority.
3. The receipt scope binds exact action ID/version, validated input, surface, binding and present trusted actor/tenant/current-record/current-selection/browser-session context.
4. Correlation ID, idempotency key, generic metadata and HumanConfirmation are not scope dimensions.
5. Raw bearer tokens are never persisted; the store is keyed by SHA-256 token hash.
6. Challenges are pending authority requests, not receipts. Trusted approval changes the same server-side token record from pending to approved without allowing scope rewrite.
7. Default challenge TTL is 300s and approved-receipt TTL is 120s; validity requires `now < expiresAt`.
8. Exact-scope successful consumption is atomic and single-use. Receipt replay fails. Downstream failure does not restore a consumed receipt.
9. Scope mismatch does not grant authority and does not spend an otherwise-valid approved receipt.
10. `CacheConfirmationStore` requires a shared lock-capable Laravel cache (`Store` + `LockProvider`) and never degrades to unlocked mutation.
11. Only successful receipt consumption creates a runtime-owned `VerifiedConfirmation` trusted entry; provenance contains no receipt/token/scope secret.
12. `ActionResultNormalizer` maps only a typed real ConfirmationChallenge. Generic halt details cannot fabricate a challenge.
13. T-402 idempotency remains separate; T-401 single-use receipt semantics are not a duplicate-side-effect solution.

## TDD / verification evidence

```text
Design:                    13437eec846bc2410125f7e7648d4a3034c8abfe
Plan:                      76a92dd65131d58c3833f9415ff03984910bf8ac / 34121419395
Invocation RED:            e0e6b5fee4752d0655a95fa72dfd7c9f0b1fd7d8 / 34121703510
Invocation GREEN:          d39d9985109f50c2501bd058420d1be6cb3b6ac6 / 34122361725
Scope RED:                 9bd56e1ea7c93d2acdf4f353e663b7ff21e0e5ab / 34122730746
Scope GREEN:               02faf1e105691acbd19221662972cc1d3c347ffa / 34123455314
State-machine RED:         d21f8a26b7e0bebbebe4aeb105449718eff4f29b / 34125236616
State-machine GREEN:       cc7eb1a0bb6a0ea2d715826cbed7db1ed5d062f5 / 34125961974
Pipeline checkpoint:       c10f06c16384c5d53f28ea38fa0f64903a4a37ab / 34127948471
Full integration RED:      de78135f5df344aae4f2ab8bdf846130ed008c82 / 34130583026
Implementation GREEN:      7db7d54fd1af9387c3cb408ec472c949168ed569 / 34130941909 — 7/7 green
```

The full integration RED intentionally exposed one remaining missing behavior: the public normalizer did not map the typed `confirmation_required` halt. The final GREEN adds only that narrow mapping; the suite then passes at 344 tests / 1299 assertions.

## Decisions

- D-014 ACCEPTED — consequential risk is a categorical confirmation gate.
- D-015 ACCEPTED — confirmation authority is opaque, scoped, expiring and runtime-issued.
- D-044 ACCEPTED — grants are single-use exact-scope bearer capabilities; only successful server-side consumption materializes `human_confirmation`.

## Known boundaries / non-claims

- T-401 does not provide an HTTP approval endpoint, browser modal, or Filament UI; T-504 owns the first concrete human-facing bridge.
- T-401 does not implement idempotency/deduplication; T-402 remains next.
- T-401 does not implement output redaction (T-403) or structured audit persistence (T-404).
- T-401 does not claim rollback, reversal, cancellation-after-dispatch or exactly-once business side effects.
- Shared cross-runtime conformance execution remains T-604/T-701; the Laravel package tests currently prove the T-401 trust scenarios for the reference runtime only.

## Next boundary

**Stop after T-401 review preparation. Do not begin T-402 automatically.**
