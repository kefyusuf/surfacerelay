# Project Status

> Current repository state for implementation and review.

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/filament-record-context-binding`
- **Base / merge-base:** `main@66afcc22704bfe3b317f2894b7737cf18d248e34`
- **Stage:** M0 DONE; M1 DONE; M1.1 DONE/REVIEWED; M2 DONE/REVIEWED; M3 DONE/REVIEWED; M4 DONE/REVIEWED/MERGED; **M5 IN PROGRESS**
- **Last merged/revalidated task:** `T-404 — Structured audit events`
- **Current task:** `T-501 — Record context binding` — **IMPLEMENTED / SELF-REVIEWED / EXTERNAL REVIEW PENDING**
- **T-501 code checkpoint:** `e8e113647383f8fe294eeb046eb5c8dd152adb16`
- **Checkpoint workflow:** `34435810811` — **7/7 green**
- **PHP:** **479 tests / 2524 assertions** across PHP 8.3/8.4 × Illuminate 12/13 with MySQL 8.4 service coverage
- **Browser isolation:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract:** `python scripts/validate.py` green; frozen `spec/0.1/**` unchanged
- **Filament compatibility:** Filament **5.8.1** + Livewire **4.4.4** resolves in the existing matrix; real `InteractsWithRecord` page integration is green
- **Decisions:** `D-019 — ACCEPTED`; `D-048 — ACCEPTED`
- **External review:** pending
- **Merge:** not requested

## T-501 implemented boundary

```text
exact trusted active Filament resource page
        │
        │ public getRecord()
        ▼
FilamentRecordContextResolver
        │
        ├── exact Eloquent Model instance
        ├── provider = filament.current_record
        └── domain-separated stable record scope key
                    │
                    ▼
FilamentTrustedContextComposer
        │
        ├── existing authenticated_actor
        ├── existing tenant
        └── current_record
                    │
                    ▼
InvocationContext
        │
        ▼
existing ActionBus + exact Livewire RuntimeBinding execution
```

Filament does **not** introduce a new RuntimeBinding driver or a second business-action execution path. It contributes trusted UI/runtime context to the existing Livewire execution boundary.

## Locked T-501 invariants

1. `current_record` comes only from the exact trusted active `Filament\Resources\Pages\Page` instance supplied to the adapter.
2. SurfaceRelay reads only the public physical `getRecord()` method; Livewire magic `__call` is not treated as record capability.
3. Action input, invocation metadata, route/query/request data, binding ID, confirmation receipt and idempotency key cannot select or manufacture record authority.
4. SurfaceRelay never calls Filament `resolveRecord()`, route model binding, a model reload, or a replacement-record database query.
5. `ResolvedTrustedValue::value` is the exact Eloquent model object returned by Filament.
6. Stable record identity is a domain-separated SHA-256 over canonical `{modelClass,keyName,keyValue}` only; model attributes are excluded.
7. Integer and string primary-key identities remain distinct; integer `0` is valid; null/empty/unsupported keys fail closed.
8. Tenant identity is not folded into the record hash. `tenant` and `current_record` remain independent trusted dimensions and existing confirmation/idempotency algorithms bind both when present.
9. Record-resolution/invariant failures use static-safe non-chained adapter errors; `getRecord()` exception text is not propagated.
10. `FilamentTrustedContextComposer` delegates existing actor/tenant composition and appends only typed `current_record` authority.
11. Confirmation scope and idempotency intent differ across different records and remain stable across distinct Eloquent instances with the same typed record identity.
12. A confirmation receipt issued for record A cannot authorize record B; a wrong-record mismatch does not spend the exact record-A receipt.
13. T-404 audit sees only the `current_record` requirement/provider manifest; raw record key, model attributes, tenant value and record scope key are excluded.
14. Real Filament 5 `InteractsWithRecord` integration preserves the exact active model and performs **0 SurfaceRelay database queries** during resolution.
15. Filament remains a dev-only package dependency for this reference-runtime test surface; `SurfaceRelayServiceProvider` remains Filament-neutral.
16. `spec/0.1/**`, `packages/browser-runtime/**` and `packages/laravel/src/Livewire/**` are unchanged by T-501.

## TDD / verification evidence

```text
Plan-head baseline:          fe2840ff470fff5c2a3acdb2f3e2be7fc86bac29 / 34433195788 — 7/7 green
Dependency-policy RED:       e478d9fce9720d87c32fa7356989a89845e44ff7 / 34434052061 — 454 tests / 2420 assertions, 1 expected failure
Dependency/matrix GREEN:     1f170db35f606f4f5058ca06a1365ccc6c3ef547 / 34434175823 — 7/7 green
Record-resolver RED:         d09964efc5ebada88930e14db0b3148411a19f9c / 34434272335 — missing resolver only
Record resolver GREEN:       b516c5469294ec7912608f6a05f55c1540c58597 / 34435117503 — 7/7 green
Composer RED:                3a92572add17515299796a4292756de8029e342d / 34435241640 — 472 tests / 2473 assertions, 3 expected missing-class errors
Composer GREEN:              3340cf28eb57fb4fc3021ef5303da921733f184a / 34435394181 — 7/7 green
Trust-control harness RED:   c1a02ca00089d22e0ee3872237527bce881685ae / 34435523351 — 473 tests / 2485 assertions, 1 expected failure
Trust-control GREEN:         081eed99fdd4cf29ade3f7b002d8bb1c8eed38c3 / 34435672411 — 7/7 green
Real Filament/code checkpoint:e8e113647383f8fe294eeb046eb5c8dd152adb16 / 34435810811 — 7/7 green
PHP:                         479 tests / 2524 assertions
Browser:                     TypeScript typecheck + 103/103 Vitest tests
Contract:                    green; frozen spec/0.1 unchanged
```

## Self-review findings

- The initial capability check used only `is_callable([$page, 'getRecord'])`; Livewire magic `__call` made non-record pages appear callable. This was reproduced in CI and corrected to require a physical method with `method_exists()` before callable invocation.
- Adversarial key-type tests initially relied on incorrect Eloquent default key casting assumptions. Test fixtures were corrected to preserve the actual key types reaching the resolver; production guards were not relaxed.
- One accidental temporary placeholder file was created while preparing Task 1 and immediately deleted before implementation proceeded; it is absent from the base-to-head diff.
- No route/request record resolver, reflection fallback, private Filament state access, new Filament driver, browser-runtime change, Livewire production change, or frozen protocol change was introduced.

## Next boundary

**T-501 implementation is ready for external review. M5 remains IN PROGRESS. T-502, T-503, T-504 and T-505 have not started. Merge remains a separate explicit user gate.**
