# External Review Record

## Final status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Scope:** `T-501 — Filament record context binding`
- **Pull request:** `#5` — **MERGED**
- **Feature head:** `7c3b96b289540366acc1b5c916b479b8963db775`
- **Merge commit:** `f9cc5aabc22d93edd2ebae122cc1aaf90bbda9e6`
- **Merged-main workflow:** `34443059898` — **7/7 green**
- **PHP:** **482 tests / 2533 assertions** across PHP 8.3/8.4 × Illuminate 12/13 with MySQL 8.4 service coverage
- **Browser:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract:** `python scripts/validate.py` green; frozen `spec/0.1/**` unchanged
- **Filament compatibility:** Filament **5.8.1** / Livewire **4.4.4** verified in the current matrix
- **Decisions:** `D-019 — ACCEPTED`; `D-048 — ACCEPTED`
- **External review result:** **PASSED after TDD hardening**
- **Unresolved inline review threads:** **0**
- **Final result:** **DONE / REVIEWED / MERGED / MAIN REVALIDATED**

## Review objective

Challenge the Filament trusted-context boundary for caller-controlled record selection, silent record re-resolution/retargeting, unstable record identity, tenant/record authority conflation, unsafe framework exceptions, private Filament API dependence, confirmation/idempotency scope mismatch, audit leakage, and accidental creation of a second execution path.

## Final production path

```text
exact trusted active Filament Page
        │
        ▼
FilamentActionGateway
        │
        ▼
FilamentInvocationContextFactory
        │
        ├── TrustedContextComposer          → actor / tenant
        └── FilamentRecordContextResolver   → current_record
        │
        ▼
InvocationContext
        │
        ▼
normal ActionCall
        │
        ▼
existing ActionBus
        │
        ▼
existing Livewire RuntimeBinding execution
```

No Filament RuntimeBinding driver, route/request record discovery, model re-query, service-provider ambient hook, or alternate business-action endpoint was introduced.

## Final implemented semantics

1. The adapter accepts the exact trusted active `Filament\Resources\Pages\Page` instance.
2. Record capability requires a physical public/callable `getRecord()` method; Livewire magic `__call` cannot manufacture authority.
3. Non-record pages produce trusted-context absence and cannot satisfy `current_record` requirements.
4. Record-aware pages reuse the exact Eloquent model already owned by Filament; SurfaceRelay performs no replacement lookup/reload.
5. Caller input, metadata, route/query/request values, binding ID, confirmation receipt and idempotency key do not select the record.
6. Stable identity is a domain-separated SHA-256 over canonical `{modelClass,keyName,keyValue}` only.
7. Integer/string key identities remain distinct; integer zero is valid; invalid identities fail closed.
8. `getRecord()`, `getKeyName()` and `getKey()` exceptions become static, non-chained adapter failures.
9. Tenant and current-record identities remain independent trusted dimensions.
10. `FilamentActionGateway` is the real production invocation boundary and feeds the existing `ActionBus` / Livewire execution path.
11. Confirmation/idempotency identity changes across different records and remains stable across distinct model instances with equivalent typed identity.
12. A record-A receipt cannot authorize record B; wrong-scope mismatch does not spend the valid record-A receipt.
13. T-404 audit stores only trusted-context `{requirement,provider}` facts and excludes raw record identity/material.
14. Real Filament 5 `InteractsWithRecord` integration proves exact-model preservation and zero SurfaceRelay database queries during record resolution.
15. Filament remains dev-only for the reference integration surface and `SurfaceRelayServiceProvider` remains Filament-neutral.
16. Existing Livewire production, browser-runtime production and frozen `spec/0.1/**` are unchanged.

## TDD / verification evidence

```text
Plan baseline:                  fe2840ff470fff5c2a3acdb2f3e2be7fc86bac29 / 34433195788 — 7/7 green
Dependency-policy RED:          e478d9fce9720d87c32fa7356989a89845e44ff7 / 34434052061 — 454 / 2420, 1 expected failure
Dependency/matrix GREEN:        1f170db35f606f4f5058ca06a1365ccc6c3ef547 / 34434175823 — 7/7 green
Record-resolver RED:            d09964efc5ebada88930e14db0b3148411a19f9c / 34434272335 — missing resolver only
Record-resolver GREEN:          b516c5469294ec7912608f6a05f55c1540c58597 / 34435117503 — 7/7 green
Composer RED:                   3a92572add17515299796a4292756de8029e342d / 34435241640 — 472 / 2473, 3 expected errors
Composer GREEN:                 3340cf28eb57fb4fc3021ef5303da921733f184a / 34435394181 — 7/7 green
Trust-control RED:              c1a02ca00089d22e0ee3872237527bce881685ae / 34435523351 — 473 / 2485, 1 expected failure
Trust-control GREEN:            081eed99fdd4cf29ade3f7b002d8bb1c8eed38c3 / 34435672411 — 7/7 green
Real Filament checkpoint:       e8e113647383f8fe294eeb046eb5c8dd152adb16 / 34435810811 — 7/7 green
Review finding #1 RED:          f7918f04f4b2a18fb38fa24b5a1f66d63aa9388f / 34437159969 — unsafe identity exception escaped
Review finding #1 GREEN:        e311201abc44ac61bddedcd22cbaf8d4802e26c7 / 34437278072 — 7/7 green
Factory-composition RED:        b961700be7ed97cda4bb7ca423c17068700027b9 / 34437453767 — 2 expected missing-factory errors
Factory-composition GREEN:      031dfe4218bdaa776f40cfca073ad7ad7bc98d8b / 34437591532 — 7/7 green
Gateway first RED attempt:      1e3e11392d62b3d9e4c400a8017d3a3d40600d6a — INVALID HARNESS EVIDENCE
Gateway valid RED:              513fa5189b907c1022a93e8da7d8df4136959952 / 34438127154 — 482 / 2527, exactly 2 missing-gateway errors
Review-hardening GREEN:         88967b90711d3080ad2e47ad8e47fa5a23280579 / 34438214057 — 7/7 green
Final feature head:             7c3b96b289540366acc1b5c916b479b8963db775 / 34438780003 — 7/7 green
Merge commit:                   f9cc5aabc22d93edd2ebae122cc1aaf90bbda9e6
Merged-main validation:         34443059898 — 7/7 green
PHP:                            482 tests / 2533 assertions
Browser:                        TypeScript typecheck + 103/103 Vitest tests
Contract:                       green; frozen spec/0.1 unchanged
```

## External review findings and closure

### Finding 1 — Eloquent identity accessor exceptions could escape — FIXED / CONFIRMED

The review correctly found `getKeyName()` / `getKey()` outside the adapter's safe exception boundary. The failure was reproduced, then both accessors were placed inside a `Throwable` boundary that returns static `invalidRecordIdentity()` without chaining application exceptions. CodeRabbit confirmed the fix and the inline thread is resolved.

### Finding 2 — trusted record context was not wired into production invocation — FIXED / CONFIRMED

The original implementation exposed resolver/composer behavior without a production consumer. A first hardening step added `FilamentInvocationContextFactory`, but CodeRabbit correctly found an incremental blocker: the factory still had no production caller.

A valid second RED required `FilamentActionGateway`. The gateway now owns exact `Page → FilamentInvocationContextFactory → InvocationContext → ActionCall → ActionBus` composition while retaining the existing Livewire execution binding.

CodeRabbit directly re-checked this blocker at the GREEN head and replied in comment `5613323657`: **“Yes. This closes the specific production-wiring blocker.”**

### Non-blocking warning

Generic docstring coverage remains below CodeRabbit's generic threshold. This is a documentation-style metric, not a T-501 correctness/security finding, and was intentionally not expanded into the trust-control change.

## Scope audit

The merged T-501 change contains no production changes under:

- `spec/0.1/**`;
- `packages/browser-runtime/**`;
- `packages/laravel/src/Livewire/**`.

Production additions are restricted to Filament `Context/**`, `Invocation/FilamentActionGateway.php`, plus Composer/CI dependency metadata. No `filament` RuntimeBinding driver exists.

## Deferred work

- `T-502 — Current-selection trusted context`
- `T-503 — Active-filter context`
- `T-504 — Confirmation bridge`
- `T-505 — Multi-tenant order operations demo`

## Final result

**PASSED / MERGED / MAIN REVALIDATED.** T-501 is fully closed. M5 remains in progress; T-502 has not started.
