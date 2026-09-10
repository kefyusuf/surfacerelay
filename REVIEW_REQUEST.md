# External Review Record

## Final status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Scope:** `T-502 — Current-selection trusted context`
- **Branch:** `feat/filament-current-selection-context`
- **Pull request:** `#6` — **OPEN / MERGE PENDING**
- **Exact base / merge-base:** `main@ae77cbf26cbefcca478d070765386628343bec52`
- **Final reviewed code head:** `433147274ea57fb9ae1452295bbdad143a19a507`
- **Final code workflow:** `34468866367` — **7/7 green**
- **PHP:** **509 tests / 2629 assertions** across PHP 8.3/8.4 × Illuminate 12/13 with MySQL 8.4 service coverage
- **Browser:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract:** `python scripts/validate.py` green; frozen `spec/0.1/**` unchanged
- **Filament compatibility:** Filament **5.8.1** / Livewire **4.4.4**
- **Decisions:** `D-019`, `D-048`, `D-049` — **ACCEPTED**
- **External review:** **PASSED after TDD hardening**
- **Remaining actionable findings:** **0**
- **Unresolved review threads:** **0**
- **Final result:** **DONE / REVIEWED; MERGE PENDING**

## Review objective

Challenge effective-selection authority, raw Livewire-state dependence, select-all/deselection semantics, bounded materialization, duplicate/pivot ambiguity, T-501 identity compatibility, caller spoofing, confirmation/idempotency selection binding, audit leakage, and accidental second execution paths.

## Final production path

```text
exact trusted active Filament table Page
        │
        ▼
public getSelectedTableRecords(true, maxSelectionRecords + 1)
        │
        ▼
FilamentCurrentSelectionResolver
        │
        ├── exact persisted Eloquent record identities
        ├── max+1 decision window
        ├── duplicate / ambiguity / limit fail-closed gates
        └── canonical unordered identity-set scope key
        │
        ▼
FilamentTrustedContextComposer
        │
        ▼
FilamentInvocationContextFactory
        │
        ▼
FilamentActionGateway
        │
        ▼
normal ActionCall → existing ActionBus → existing Livewire RuntimeBinding
```

No Filament RuntimeBinding driver, raw selection-property authority, route/request selection discovery, replacement model query, service-provider ambient hook, or alternate business-action endpoint was introduced.

## Final implemented semantics

1. `current_selection` is derived only from the exact trusted active Filament table page through public `getSelectedTableRecords()`.
2. Empty effective selection is absence; caller input and invocation metadata cannot manufacture or replace authority.
3. Selection is an unordered canonical set of exact persisted Eloquent identities.
4. Shared `FilamentRecordIdentity` preserves T-501 current-record identity byte-for-byte; `TestRecord#41` remains `5436c4020c370bb2f495f0954332bd892a3e5f89950db287c808f292e32bfa96`.
5. A/B and B/A share selection authority; A/B and A/C do not.
6. Tenant and selection remain independent trusted dimensions composed by existing confirmation/idempotency controls.
7. Wrong-selection receipt mismatch cannot authorize the wrong set and does not consume the valid receipt.
8. Query-backed resolution requests the exact `maxSelectionRecords + 1` sentinel window and rejects rather than truncates.
9. Filament select-all/deselection and record-selectability semantics are inherited from the public effective-selection result.
10. Duplicate identities, non-Eloquent/unsaved values, duplicate-enabled `BelongsToMany` semantics and framework failures fail closed with static, non-chained errors.
11. Composer order is `authenticated_actor → tenant → current_record → current_selection` when all exist.
12. T-404 audit persists only `current_selection` requirement/provider provenance; selected IDs, model classes, attributes, counts and scope keys are excluded.
13. `FilamentActionGateway::dispatch(...)` is unchanged and existing Livewire execution remains the sole action path.
14. Active filters remain T-503 scope.
15. `spec/0.1/**`, `packages/browser-runtime/src/**` and `packages/laravel/src/Livewire/**` are unchanged.

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
Review-prep checkpoint:         d0432f0cc94c7d1e14960135aedc329e60f9c5dc / 34465695646 — 7/7 green
Review finding RED:             1b9b3fdb43d05d46ff73d8f1e08cd4c0fdaef6c1 / 34468667385 — 509 / 2629, exactly 1 failure; 200 hydrated vs required 151
Review finding GREEN:           433147274ea57fb9ae1452295bbdad143a19a507 / 34468866367 — 7/7 green; 509 / 2629
Browser:                        TypeScript typecheck + 103/103 Vitest tests
Contract:                       green; frozen spec/0.1 unchanged
```

## External review finding and closure

### Finding — `chunkSize=100` did not cap total Filament materialization — FIXED / CONFIRMED

CodeRabbit full review run `7eadba8f-6972-444e-8e32-b5e66e776a0d` correctly identified that Filament's `lazyById($chunkSize)` treats the supplied chunk size as a per-query batch size, not a total materialization ceiling. The original `min(100, max + 1)` therefore allowed a selection with `max=150` to hydrate all 200 test records before SurfaceRelay observed record 151.

The RED regression at `1b9b3fdb...` measured the actual Eloquent retrieval count and failed at **200 vs required 151**. The minimal production fix at `43314727...` keeps the public Filament API and supplies the exact trusted sentinel `maxSelectionRecords + 1`. The same integration test now passes with exactly **151 hydrated records**.

CodeRabbit incremental verification comment `5617635978` confirmed:

- the resolver now passes `maxSelectionRecords + 1` to public `getSelectedTableRecords()`;
- the real `max=150` test rejects after exactly 151 hydrated records;
- no alternate query/execution path or raw Livewire state read was added;
- **no remaining actionable T-502 correctness/security findings**;
- **no unresolved review threads**;
- the prior materialization-bound finding is addressed.

Generic docstring/style metrics remain non-blocking because repository CI does not require them.

## Scope audit

Base remains `main@ae77cbf26cbefcca478d070765386628343bec52`. The reviewed production change remains limited to the Filament context/invocation vertical. Frozen protocol, browser-runtime production and Livewire production are unchanged, and no second RuntimeBinding driver exists.

## Deferred work

- `T-503 — Active-filter context`
- `T-504 — Confirmation bridge`
- `T-505 — Multi-tenant order operations demo`

## Final result

**PASSED / MERGE PENDING.** T-502 is complete/reviewed. PR #6 merge requires a separate explicit user gate.
