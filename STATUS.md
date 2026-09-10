# Project Status

> Current repository state for implementation and review.

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `main`
- **Stage:** M0 DONE; M1 DONE; M1.1 DONE/REVIEWED; M2 DONE/REVIEWED; M3 DONE/REVIEWED; M4 DONE/REVIEWED/MERGED; **M5 IN PROGRESS**
- **Last merged/revalidated task:** `T-501 — Record context binding`
- **T-501 status:** **DONE / REVIEWED / MERGED / MAIN REVALIDATED**
- **Feature final head:** `7c3b96b289540366acc1b5c916b479b8963db775`
- **Merge commit:** `f9cc5aabc22d93edd2ebae122cc1aaf90bbda9e6`
- **Merged-main workflow:** `34443059898` — **7/7 green**
- **PHP:** **482 tests / 2533 assertions** across PHP 8.3/8.4 × Illuminate 12/13 with MySQL 8.4 service coverage
- **Browser isolation:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract:** `python scripts/validate.py` green; frozen `spec/0.1/**` unchanged
- **Filament compatibility:** Filament **5.8.1** + Livewire **4.4.4** verified in the existing matrix
- **Decisions:** `D-019 — ACCEPTED`; `D-048 — ACCEPTED`
- **Pull request:** `#5` — **MERGED**
- **External review:** **PASSED after TDD hardening**
- **Unresolved inline review threads:** **0**
- **Next task:** `T-502 — Current-selection trusted context` — **NOT STARTED**

## T-501 final production boundary

```text
exact trusted active Filament Page
        │
        ▼
FilamentActionGateway
        │
        ▼
FilamentInvocationContextFactory
        │
        ├── existing TrustedContextComposer      → actor / tenant
        └── FilamentRecordContextResolver        → current_record
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

Filament remains a trusted-context/UI adapter. T-501 does **not** introduce a `filament` RuntimeBinding driver, an alternate business-action endpoint, route/request record discovery, record re-resolution, or a replacement database query.

## Locked T-501 invariants

1. `current_record` comes only from the exact trusted active `Filament\Resources\Pages\Page` supplied to the Filament-side gateway/factory.
2. Record capability requires a physical public/callable `getRecord()` method; Livewire magic `__call` alone is not authority.
3. Action input, invocation metadata, route/query/request data, binding ID, confirmation receipt and idempotency key cannot select or manufacture record authority.
4. SurfaceRelay never calls Filament `resolveRecord()`, route model binding, a model reload, or a replacement-record query.
5. `ResolvedTrustedValue::value` is the exact Eloquent model object returned by Filament.
6. Stable record identity is a domain-separated SHA-256 over canonical `{modelClass,keyName,keyValue}` only; model attributes are excluded.
7. Integer/string primary-key identities remain distinct; integer `0` is valid; null/empty/unsupported identities fail closed.
8. `getRecord()`, `getKeyName()` and `getKey()` failures are translated to static, non-chained adapter errors.
9. Tenant identity is independent from `current_record`; existing confirmation/idempotency controls bind both dimensions when present.
10. `FilamentActionGateway` is the production Filament invocation boundary and feeds the normal existing `ActionBus` path.
11. Record-A confirmation cannot authorize record B; wrong-scope mismatch does not spend the valid record-A receipt.
12. T-404 audit persists only the `current_record` requirement/provider manifest and excludes raw record identity/material.
13. Real Filament 5 `InteractsWithRecord` integration preserves the exact model and performs **0 SurfaceRelay database queries** during record resolution.
14. Filament remains dev-only for the reference integration surface; `SurfaceRelayServiceProvider` remains Filament-neutral.
15. `spec/0.1/**`, `packages/browser-runtime/**` and `packages/laravel/src/Livewire/**` are unchanged by T-501.

## TDD / verification evidence

```text
Plan baseline:                 fe2840ff470fff5c2a3acdb2f3e2be7fc86bac29 / 34433195788 — 7/7 green
Dependency-policy RED:         e478d9fce9720d87c32fa7356989a89845e44ff7 / 34434052061 — 454 / 2420, 1 expected failure
Dependency/matrix GREEN:       1f170db35f606f4f5058ca06a1365ccc6c3ef547 / 34434175823 — 7/7 green
Record-resolver RED:           d09964efc5ebada88930e14db0b3148411a19f9c / 34434272335 — missing resolver only
Record-resolver GREEN:         b516c5469294ec7912608f6a05f55c1540c58597 / 34435117503 — 7/7 green
Composer RED:                  3a92572add17515299796a4292756de8029e342d / 34435241640 — 472 / 2473, 3 expected errors
Composer GREEN:                3340cf28eb57fb4fc3021ef5303da921733f184a / 34435394181 — 7/7 green
Trust-control RED:             c1a02ca00089d22e0ee3872237527bce881685ae / 34435523351 — 473 / 2485, 1 expected failure
Trust-control GREEN:           081eed99fdd4cf29ade3f7b002d8bb1c8eed38c3 / 34435672411 — 7/7 green
Real Filament checkpoint:      e8e113647383f8fe294eeb046eb5c8dd152adb16 / 34435810811 — 7/7 green
Review finding #1 RED:         f7918f04f4b2a18fb38fa24b5a1f66d63aa9388f / 34437159969 — raw identity exception escaped
Review finding #1 GREEN:       e311201abc44ac61bddedcd22cbaf8d4802e26c7 / 34437278072 — 7/7 green
Factory-composition RED:       b961700be7ed97cda4bb7ca423c17068700027b9 / 34437453767 — 2 expected missing-factory errors
Factory-composition GREEN:     031dfe4218bdaa776f40cfca073ad7ad7bc98d8b / 34437591532 — 7/7 green
Gateway first RED attempt:     1e3e11392d62b3d9e4c400a8017d3a3d40600d6a — INVALID HARNESS EVIDENCE
Gateway valid RED:             513fa5189b907c1022a93e8da7d8df4136959952 / 34438127154 — 482 / 2527, exactly 2 missing-gateway errors
Review-hardening GREEN:        88967b90711d3080ad2e47ad8e47fa5a23280579 / 34438214057 — 7/7 green
Final feature head:            7c3b96b289540366acc1b5c916b479b8963db775 / 34438780003 — 7/7 green
Merge commit:                  f9cc5aabc22d93edd2ebae122cc1aaf90bbda9e6
Merged-main validation:        34443059898 — 7/7 green
PHP:                           482 tests / 2533 assertions
Browser:                       TypeScript typecheck + 103/103 Vitest tests
Contract:                      green; frozen spec/0.1 unchanged
```

## External review closure

CodeRabbit full review run `5931182a-b621-47c3-b361-8843f450f039` found two actionable issues. Both were reproduced and fixed TDD-first.

1. Eloquent `getKeyName()` / `getKey()` failures now map to static, non-chained `invalidRecordIdentity()` errors.
2. Trusted Filament record context is connected to a real production invocation. The first hardening step added `FilamentInvocationContextFactory`; CodeRabbit correctly found that it still lacked a production caller. The final hardening added `FilamentActionGateway`.

After the gateway GREEN checkpoint, CodeRabbit directly re-checked the remaining blocker and replied in comment `5613323657`: **“Yes. This closes the specific production-wiring blocker.”**

Generic CodeRabbit docstring coverage remains a nonfunctional/non-blocking style warning and is outside T-501 scope.

## Next boundary

**T-501 is fully closed on `main`. M5 remains IN PROGRESS. T-502, T-503, T-504 and T-505 have not started.**
