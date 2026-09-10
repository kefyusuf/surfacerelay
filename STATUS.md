# Project Status

> Current repository state for implementation and review.

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/filament-current-selection-context`
- **Stage:** M0 DONE; M1 DONE; M1.1 DONE/REVIEWED; M2 DONE/REVIEWED; M3 DONE/REVIEWED; M4 DONE/REVIEWED/MERGED; **M5 IN PROGRESS**
- **Last merged/revalidated task:** `T-501 — Record context binding`
- **Current task:** `T-502 — Current-selection trusted context`
- **T-502 status:** **DONE / REVIEWED; MERGE PENDING**
- **Exact base / merge-base:** `main@ae77cbf26cbefcca478d070765386628343bec52`
- **Final reviewed code head:** `433147274ea57fb9ae1452295bbdad143a19a507`
- **Final code workflow:** `34468866367` — **7/7 green**
- **PHP:** **509 tests / 2629 assertions** across PHP 8.3/8.4 × Illuminate 12/13 with MySQL 8.4 service coverage
- **Browser isolation:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract:** `python scripts/validate.py` green; frozen `spec/0.1/**` unchanged
- **Filament compatibility:** Filament **5.8.1** + Livewire **4.4.4** verified in the matrix
- **Decisions:** `D-019`, `D-048`, `D-049` — **ACCEPTED**
- **Pull request:** `#6` — **OPEN / MERGE PENDING**
- **External review:** **PASSED after TDD hardening**
- **Unresolved review threads:** **0**
- **Next task:** `T-503 — Active-filter context` — **NOT STARTED**

## T-502 production boundary

```text
exact trusted active Filament table Page
        │
        ▼
public getSelectedTableRecords(true, maxSelectionRecords + 1)
        │
        ▼
FilamentCurrentSelectionResolver
        │
        ├── exact persisted Eloquent identity validation
        ├── max+1 bounded decision window
        ├── duplicate / ambiguity / limit fail-closed gates
        └── canonical unordered identity-set scope key
        │
        ▼
FilamentTrustedContextComposer
        │
        ├── authenticated_actor
        ├── tenant
        ├── current_record
        └── current_selection
        │
        ▼
FilamentInvocationContextFactory
        │
        ▼
FilamentActionGateway
        │
        ▼
existing ActionCall → ActionBus → existing Livewire RuntimeBinding execution
```

Filament remains a trusted-context/UI adapter. T-502 does **not** add a Filament RuntimeBinding driver, alternate business-action endpoint, caller-supplied selection IDs, route/request selection discovery, raw Livewire selection-property reads, reflection, or a second execution path.

## Locked T-502 invariants

1. `current_selection` comes only from the exact trusted active Filament table page.
2. Production uses Filament's public effective-selection API only; raw Livewire selection properties are not authority.
3. Empty effective selection is absence; caller input/metadata cannot manufacture selection authority.
4. Selection is an unordered canonical set of exact persisted Eloquent identities.
5. Shared `FilamentRecordIdentity` preserves the T-501 `current_record` scope hash byte-for-byte; `TestRecord#41` remains `5436c4020c370bb2f495f0954332bd892a3e5f89950db287c808f292e32bfa96`.
6. Selection scope and tenant scope remain independent trusted dimensions.
7. A/B and B/A produce the same selection identity; A/B and A/C differ.
8. Wrong-selection confirmation mismatch does not consume a valid receipt.
9. Query-backed materialization is bounded to the exact `max + 1` decision window; over-limit selections fail rather than truncate.
10. Filament select-all/deselection and selectable-record semantics come from the public effective-selection contract.
11. Duplicate identities, non-Eloquent/unsaved values, duplicate-enabled `BelongsToMany` rows and framework failures fail closed with static, non-chained errors.
12. T-404 audit stores only `{requirement, provider}` for `current_selection`; selected record material and scope keys are excluded.
13. `FilamentActionGateway::dispatch(...)` remains unchanged and existing Livewire execution remains the only action path.
14. Active filters remain T-503 scope.
15. `spec/0.1/**`, `packages/browser-runtime/src/**` and `packages/laravel/src/Livewire/**` remain unchanged.

## TDD / verification evidence

```text
Plan baseline:                  a115a432dba24b7df30884b0f3fef2ed990e119f / 34450997871 — 7/7 green
T-501 hash compatibility:      d0cf2f4c6715e93d72769eeea428a056a9d06911 / 34457594608 — 7/7 green
Shared identity RED:            6097e312728c5f476875f7b075648080ff214673 / 34457789241 — expected missing class
Shared identity GREEN:          0592f60ddf67dbb70d230c3c9f1263e2c7c9ae1a / 34458099131 — 7/7 green
Selection resolver RED:         2ca035b03fd72cfd41fdbd38f54472ec0c63766f / 34458424330 — expected missing resolver
Effective-selection RED:        d870708786b1e72732a9d57631f266b56a7b64d5 / 34458959903 — 2 expected failures
Effective-selection GREEN:      1e51a9572387e6a63970d35d9e412d4e62053cba / 34459178353 — 7/7 green
Resolver adversarial GREEN:     c4d5662e975d8e531df0c4ae6f1771b1da80d5ff / 34459545721 — 7/7 green
Gateway wiring RED:             0f41550c826eb4e532da67348e272312906e6672 / 34459901925 — expected production-wiring failure
Gateway wiring GREEN:           5365460a36c8ddcccfb6f02c5996d41aca1d99a7 / 34460166003 — 7/7 green
Real Filament semantics:        0a33b3f58098b7a9790ef638f37cc93898ec5f30 / 34460407313 — 7/7 green
Trust-controls checkpoint:      c859239785ccff3660e4f55741af2526989488e7 / 34460761976 — 7/7 green
Duplicate-row hardening:        c678d6f05feb0c1e44d0ec548f28329e54dbc4e4 / 34461173145 — 7/7 green
Composer-order hardening:       a89ef13ccdc4bca45eb3f2d34dea7b4cf78b06f6 / 34465053733 — 7/7 green
Review-prep head:               d0432f0cc94c7d1e14960135aedc329e60f9c5dc / 34465695646 — 7/7 green
Review finding RED:             1b9b3fdb43d05d46ff73d8f1e08cd4c0fdaef6c1 / 34468667385 — 509 / 2629, exactly 1 failure; 200 hydrated vs required 151
Review finding GREEN:           433147274ea57fb9ae1452295bbdad143a19a507 / 34468866367 — 7/7 green; 509 / 2629
Browser:                        TypeScript typecheck + 103/103 Vitest tests
Contract:                       green; frozen spec/0.1 unchanged
```

## External review closure

CodeRabbit full review run `7eadba8f-6972-444e-8e32-b5e66e776a0d` found one actionable correctness issue: the original `min(100, max + 1)` value limited each Filament `lazyById()` batch rather than total materialization. With `max=150`, the regression proved 200 records were hydrated before the limit was observed.

The fix passes the exact trusted sentinel size `maxSelectionRecords + 1` to Filament's public `getSelectedTableRecords()` API. The same real Filament integration now proves exactly 151 hydrated records for `max=150`. CodeRabbit incremental verification confirmed: **no remaining actionable T-502 correctness/security findings**, **0 unresolved review threads**, and the prior materialization finding is addressed.

## Next boundary

**T-502 is DONE / REVIEWED. PR #6 merge is the next explicit gate. T-503 has not started.**
