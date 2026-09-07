# External Review Request

## Review status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Scope:** `T-401 — Confirmation challenge/receipt`
- **Base:** `main@5eb33c203fb40fc2ff744f2f4a57cbce4c704b80`
- **Candidate branch:** `feat/confirmation-challenge-receipt`
- **Implementation checkpoint:** `7db7d54fd1af9387c3cb408ec472c949168ed569`
- **Implementation workflow:** `34130941909` — all 7 jobs success
- **Result:** **READY FOR EXTERNAL REVIEW / NOT MERGED**
- **M4 status:** IN PROGRESS; T-402..T-404 remain TODO

## What changed

T-401 implements real Laravel runtime confirmation authority without changing `spec/0.1` wire shapes:

```text
validated + authorized consequential invocation
        ↓
exact confirmation scope fingerprint
        ↓
opaque pending challenge
        ↓ trusted bridge approval
same opaque token becomes approved receipt
        ↓ next exact invocation
atomic single-use consume
        ↓
runtime-owned HumanConfirmation trusted entry
        ↓
execution
```

The production confirmation store persists only scalar/cache-safe records keyed by SHA-256 token hash. Raw bearer tokens are not persisted. A Laravel cache store must implement both `Store` and `LockProvider`; approve/consume operations are lock-protected and have no unlocked fallback.

## Review focus

1. **Authority boundary:** confirm action input, metadata, `confirmed=true`, pending challenge IDs and prebuilt HumanConfirmation cannot satisfy the gate.
2. **Exact scope:** confirm action ID/version, validated input, surface, binding, actor, tenant, record, selection and browser session are bound; correlation/idempotency/metadata are excluded intentionally.
3. **Stable context representation:** confirm arbitrary objects fail closed unless a trusted resolver supplies a non-secret confirmation scope key; Laravel `Authenticatable` identity derives a stable actor key without credentials/tokens.
4. **State machine:** confirm pending → approved → consumed/expired is server-side, approval cannot rewrite scope, and the same opaque token is used across challenge/receipt states.
5. **Replay/expiry:** confirm strict equality expiry, single-use receipt semantics, and receipt remains spent when downstream execution fails.
6. **Mismatch behavior:** confirm wrong-scope attempt cannot execute and does not consume the legitimate exact-scope receipt.
7. **Cache atomicity:** confirm every authority mutation is under per-token lock and lock/store failures fail closed.
8. **Secret handling:** confirm raw receipt, token hash, scope hash and trusted actor/tenant values do not leak through halt details, result metadata, provenance or exceptions.
9. **Result semantics:** confirm `confirmation_required` is produced only from a typed real `ConfirmationChallenge`, never fabricated from generic halt details.
10. **Scope discipline:** confirm there is no T-402 idempotency, T-403 redaction, T-404 audit persistence, T-504 UI, or `spec/0.1` change.

## Verification evidence

```text
Implementation checkpoint: 7db7d54fd1af9387c3cb408ec472c949168ed569
Workflow:                  34130941909 — 7/7 jobs success
PHP:                       344 tests / 1299 assertions
Browser:                   TypeScript typecheck + 103/103 Vitest tests
Contract:                  python scripts/validate.py green; frozen spec/0.1 unchanged
Base comparison:           main@5eb33c20... → checkpoint, 17 commits ahead / 0 behind
```

Key TDD checkpoints:

```text
Invocation RED/GREEN:      e0e6b5fee4752d0655a95fa72dfd7c9f0b1fd7d8 → d39d9985109f50c2501bd058420d1be6cb3b6ac6
Scope RED/GREEN:           9bd56e1ea7c93d2acdf4f353e663b7ff21e0e5ab → 02faf1e105691acbd19221662972cc1d3c347ffa
State-machine RED/GREEN:   d21f8a26b7e0bebbebe4aeb105449718eff4f29b → cc7eb1a0bb6a0ea2d715826cbed7db1ed5d062f5
Full integration RED:      de78135f5df344aae4f2ab8bdf846130ed008c82 / run 34130583026
Final implementation GREEN:7db7d54fd1af9387c3cb408ec472c949168ed569 / run 34130941909
```

The full integration RED had exactly one remaining production gap: `confirmation_required` typed halt normalization. After adding the narrow typed mapping, the complete feature branch returned to 7/7 green.

## Decision status

- D-014 ACCEPTED — consequential is a categorical confirmation gate.
- D-015 ACCEPTED — receipt authority is opaque, scoped, expiring and runtime-issued.
- D-044 ACCEPTED — confirmation grants are single-use exact-scope bearer capabilities and only successful server-side consumption materializes HumanConfirmation.

## Explicit non-claims

T-401 does **not** claim idempotent/exactly-once business execution, output redaction, structured audit persistence, a generic approval HTTP endpoint, browser/Filament confirmation UX, rollback, compensation, or post-dispatch cancellation. Those remain T-402/T-403/T-404/T-504 or separate concerns.

## Requested reviewer outcome

Review the feature branch against `main@5eb33c203fb40fc2ff744f2f4a57cbce4c704b80`. A successful review may mark T-401 **REVIEWED** and permit a separate explicit merge step. This document does not self-approve or merge the task.
