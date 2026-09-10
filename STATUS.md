# Project Status

> Current repository state for implementation and review.

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/filament-current-selection-context`
- **Stage:** M0 DONE; M1 DONE; M1.1 DONE/REVIEWED; M2 DONE/REVIEWED; M3 DONE/REVIEWED; M4 DONE/REVIEWED/MERGED; **M5 IN PROGRESS**
- **Last merged/revalidated task:** `T-501 — Record context binding`
- **Current task:** `T-502 — Current-selection trusted context`
- **T-502 implementation status:** **DONE / REVIEW PENDING**
- **Exact base / merge-base:** `main@ae77cbf26cbefcca478d070765386628343bec52`
- **Implementation verification checkpoint:** `a89ef13ccdc4bca45eb3f2d34dea7b4cf78b06f6`
- **Exact-head workflow:** `34465053733` — **7/7 green**
- **PHP:** **508 tests / 2626 assertions** across PHP 8.3/8.4 × Illuminate 12/13 with MySQL 8.4 service coverage
- **Browser isolation:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract:** `python scripts/validate.py` green; frozen `spec/0.1/**` unchanged
- **Filament compatibility:** Filament **5.8.1** + Livewire **4.4.4** verified in the current matrix
- **Decisions:** `D-019 — ACCEPTED`; `D-048 — ACCEPTED`; `D-049 — ACCEPTED`
- **Branch relation:** **28 ahead / 0 behind** at the implementation checkpoint
- **Pull request:** not opened yet at this checkpoint
- **External review:** **PENDING**
- **Task-board marker:** intentionally unchanged until external review closes

## T-502 production boundary

```text
exact trusted active Filament table Page
        │
        ▼
FilamentCurrentSelectionResolver
        │
        ├── public Filament getSelectedTableRecords(true, bounded chunk)
        ├── exact persisted Eloquent identity validation
        ├── duplicate / ambiguity / limit fail-closed gates
        └── canonical unordered identity-set scope key
        │
        ▼
FilamentTrustedContextComposer
        │
        ├── actor
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

1. `current_selection` is resolved only from the exact trusted active Filament table page supplied by trusted adapter code.
2. Production selection resolution uses Filament's public `getSelectedTableRecords()` contract; it does not read `$selectedTableRecords`, `$deselectedTableRecords`, `$isTrackingDeselectedTableRecords`, request/route/query values, `Livewire\invade()`, or reflection.
3. Empty effective selection is trusted-context absence; caller input or metadata cannot manufacture it.
4. The snapshot is an unordered set of exact persisted Eloquent record identities; canonical ordering affects identity/hash stability, not business ordering semantics.
5. Stable record identity reuses the shared T-501 canonical `{modelClass,keyName,keyValue}` primitive.
6. T-501 current-record compatibility is byte-pinned: record `TestRecord#41` retains scope hash `5436c4020c370bb2f495f0954332bd892a3e5f89950db287c808f292e32bfa96` after the identity refactor.
7. Selection scope uses its own domain and does not silently absorb tenant identity; generic confirmation/idempotency scope still binds tenant independently.
8. Equivalent A/B vs B/A effective sets produce the same selection scope, confirmation scope and idempotency intent; A/B vs A/C differ.
9. A receipt issued for A/B cannot authorize A/C, and wrong-selection mismatch does not spend the valid A/B receipt.
10. Selection materialization is bounded: SurfaceRelay requests a first decision window no larger than `max + 1` and fails rather than truncating when the configured ceiling is exceeded.
11. Real Filament select-all with raw selected IDs empty still resolves the effective set after deselections; selectability filtering is inherited from Filament's public effective-selection result.
12. Duplicate effective identities, non-Eloquent values, invalid/unsaved identities and duplicate-enabled `BelongsToMany` row semantics fail closed with static non-chained adapter errors.
13. `FilamentTrustedContextComposer` preserves canonical order `authenticated_actor → tenant → current_record → current_selection` when all four exist.
14. T-404 audit persists only `{requirement: current_selection, provider: filament.current_selection}` for this context and excludes selected IDs, model class, business attributes, selection count, scope key and caller spoof values.
15. `FilamentActionGateway::dispatch(...)` remains unchanged; the selection ceiling is trusted constructor configuration only.
16. Active filters remain a separate T-503 trusted-context concern.
17. `spec/0.1/**`, `packages/browser-runtime/src/**` and `packages/laravel/src/Livewire/**` are unchanged by T-502.

## TDD / verification evidence

```text
Plan baseline:                 a115a432dba24b7df30884b0f3fef2ed990e119f / 34450997871 — 7/7 green
T-501 hash compatibility:     d0cf2f4c6715e93d72769eeea428a056a9d06911 / 34457594608 — 7/7 green
Shared identity RED:           6097e312728c5f476875f7b075648080ff214673 / 34457789241 — 484 / 2535, exactly 1 missing-class error
Shared identity GREEN:         0592f60ddf67dbb70d230c3c9f1263e2c7c9ae1a / 34458099131 — 7/7 green
Selection resolver RED:        2ca035b03fd72cfd41fdbd38f54472ec0c63766f / 34458424330 — 485 / 2537, exactly 1 missing-resolver error
Resolver shell GREEN:          015a96e29723128460c9c4d9bab2d3a2be30e67e / 34458717345 — 7/7 green
Effective-selection RED:       d870708786b1e72732a9d57631f266b56a7b64d5 / 34458959903 — 490 / 2546, exactly 2 expected non-empty failures
Effective-selection GREEN:     1e51a9572387e6a63970d35d9e412d4e62053cba / 34459178353 — 7/7 green
Resolver adversarial GREEN:    c4d5662e975d8e531df0c4ae6f1771b1da80d5ff / 34459545721 — 7/7 green
Gateway wiring RED:            0f41550c826eb4e532da67348e272312906e6672 / 34459901925 — 497 / 2572, exactly 1 production-wiring failure
Gateway wiring GREEN:          5365460a36c8ddcccfb6f02c5996d41aca1d99a7 / 34460166003 — 7/7 green
Real Filament semantics:       0a33b3f58098b7a9790ef638f37cc93898ec5f30 / 34460407313 — 7/7 green
Trust-controls checkpoint:     c859239785ccff3660e4f55741af2526989488e7 / 34460761976 — 7/7 green
Duplicate-row hardening:       c678d6f05feb0c1e44d0ec548f28329e54dbc4e4 / 34461173145 — 7/7 green
Composer-order hardening:      a89ef13ccdc4bca45eb3f2d34dea7b4cf78b06f6 / 34465053733 — 7/7 green
PHP:                           508 tests / 2626 assertions
Browser:                       TypeScript typecheck + 103/103 Vitest tests
Contract:                      green; frozen spec/0.1 unchanged
```

## Scope audit

Exact compare `ae77cbf26cbefcca478d070765386628343bec52...a89ef13ccdc4bca45eb3f2d34dea7b4cf78b06f6` is **28 commits ahead / 0 behind** with 20 changed files.

No changed path exists under:

- `spec/0.1/**`;
- `packages/browser-runtime/src/**`;
- `packages/laravel/src/Livewire/**`.

Production changes are restricted to Filament context/invocation code. No new RuntimeBinding driver exists.

## Next boundary

**T-502 implementation is complete and verified. The next action is to create the review-prep PR, request external CodeRabbit review, freeze the review head, and handle only actionable findings. `TASKS.md` remains unchanged until review closure. Merge remains a separate explicit user gate.**
