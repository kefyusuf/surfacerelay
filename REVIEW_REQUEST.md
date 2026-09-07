# External Review Record

## Review status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Scope:** `T-401 — Confirmation challenge/receipt`
- **Original base:** `main@5eb33c203fb40fc2ff744f2f4a57cbce4c704b80`
- **Pull request:** `#1 — merged`
- **Final code checkpoint:** `f94340947b00f06707451590f1bef9fcc980479d`
- **Review-passed checkpoint:** `b169ad81d9f603e18b0cf47c7097a575c83d806c`
- **Merged-main checkpoint:** `a6c92a8b75b17196f7667c22b4b323fe25d3427f`
- **Merged-main workflow:** `34137414074` — **all 7 jobs success**
- **External automated reviewer:** CodeRabbit full review run `e1364e16-d11d-4fe9-85d5-5f6675a2d99f`
- **Review result:** **PASSED WITH ONE TRIVIAL FINDING, FIXED AND REVERIFIED**
- **Merge result:** **PASSED / MERGED TO MAIN**
- **M4 status:** IN PROGRESS; T-402..T-404 remain TODO

## Reviewed trust boundary

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

The production confirmation store persists only scalar/cache-safe records keyed by SHA-256 token hash. Raw bearer tokens are not persisted. A Laravel cache store must implement both `Store` and `LockProvider`; every confirmation-record mutation is protected by a per-token lock with a bounded two-second acquisition wait and no unlocked fallback.

## External review finding

CodeRabbit found no blocker/security defect and reported one **trivial Stability & Availability** issue in `CacheConfirmationStore::withTokenLock()`:

- the original implementation used a single non-blocking `Lock::get()` attempt;
- short contention on the same token could therefore surface `ConfirmationStoreUnavailable` immediately;
- Laravel 12/13 support bounded `Lock::block()` acquisition, with timeout represented as a lock acquisition failure.

The finding was verified against the current Laravel cache lock contracts before changing code. It was then fixed test-first:

```text
Review RED:   37832f73c51b085e6711dc5059c6c0466cc88f82 / 34135694223
              four PHP matrix jobs failed only because lock.get was still used

Review GREEN: f94340947b00f06707451590f1bef9fcc980479d / 34135906728
              all 7 jobs success; 344 tests / 1300 assertions
```

The final behavior is:

```text
per-token lock TTL:  10s
bounded wait:         2s
wait timeout/error:   ConfirmationStoreUnavailable
unlocked fallback:    forbidden
```

This hardening changes availability under brief contention only; confirmation authority, scope, expiry, replay and single-use semantics remain unchanged.

## Review conclusions

1. **Authority boundary:** action input, metadata, `confirmed=true`, pending challenge IDs and prebuilt HumanConfirmation cannot satisfy the gate.
2. **Exact scope:** action ID/version, validated input, surface, binding, actor, tenant, record, selection and browser session are bound; correlation/idempotency/metadata are excluded intentionally.
3. **Stable context representation:** arbitrary objects fail closed unless a trusted resolver supplies a non-secret confirmation scope key; Laravel `Authenticatable` identity derives a stable actor key without credentials/tokens.
4. **State machine:** pending → approved → consumed/expired is server-side, approval cannot rewrite scope, and the same opaque token is used across challenge/receipt states.
5. **Replay/expiry:** expiry equality fails closed, successful consumption is single-use, and downstream execution failure does not restore a receipt.
6. **Mismatch behavior:** wrong-scope attempts cannot execute and do not consume the legitimate exact-scope receipt.
7. **Cache atomicity:** every authority mutation is under the exact per-token lock; acquisition waits are bounded and timeout/store failures fail closed.
8. **Secret handling:** raw receipt, token hash, scope hash and trusted actor/tenant values do not leak through halt details, result metadata, provenance or exceptions.
9. **Result semantics:** `confirmation_required` is produced only from a typed real `ConfirmationChallenge`, never fabricated from generic halt details.
10. **Scope discipline:** no T-402 idempotency, T-403 redaction, T-404 audit persistence, T-504 UI, rollback semantics, or `spec/0.1` change was introduced.

## Verification evidence

```text
Implementation GREEN:       7db7d54fd1af9387c3cb408ec472c949168ed569 / 34130941909 — 7/7 green
Review-prep checkpoint:      d2f76f3c3172a2800e929ee0288d64c305bdb7e7 / 34132981487 — 7/7 green
Initial PR validation:       d2f76f3c3172a2800e929ee0288d64c305bdb7e7 / 34133666646 — 7/7 green
CodeRabbit full review:      e1364e16-d11d-4fe9-85d5-5f6675a2d99f
Review-finding RED:          37832f73c51b085e6711dc5059c6c0466cc88f82 / 34135694223
Review-finding GREEN:        f94340947b00f06707451590f1bef9fcc980479d / 34135906728 — 7/7 green
Review-passed docs:          b169ad81d9f603e18b0cf47c7097a575c83d806c / 34137102693 — 7/7 green
Merged main:                 a6c92a8b75b17196f7667c22b4b323fe25d3427f / 34137414074 — 7/7 green
PHP:                         344 tests / 1300 assertions
Browser:                     TypeScript typecheck + 103/103 Vitest tests
Contract:                    python scripts/validate.py green; frozen spec/0.1 unchanged
Open review threads:          0
```

## Decision status

- D-014 ACCEPTED — consequential is a categorical confirmation gate.
- D-015 ACCEPTED — receipt authority is opaque, scoped, expiring and runtime-issued.
- D-044 ACCEPTED — confirmation grants are single-use exact-scope bearer capabilities and only successful server-side consumption materializes HumanConfirmation.

## Explicit non-claims

T-401 does **not** claim idempotent/exactly-once business execution, output redaction, structured audit persistence, a generic approval HTTP endpoint, browser/Filament confirmation UX, rollback, compensation, or post-dispatch cancellation. Those remain T-402/T-403/T-404/T-504 or separate concerns.

## Review outcome

**T-401 external automated review passed, the only finding was fixed TDD-first, PR #1 was merged to `main`, and the exact merged commit was independently revalidated with all 7 CI jobs green.**
