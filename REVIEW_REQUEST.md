# External Review Request

## Review status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Scope:** `T-502 — Current-selection trusted context`
- **Branch:** `feat/filament-current-selection-context`
- **Exact base / merge-base:** `main@ae77cbf26cbefcca478d070765386628343bec52`
- **Implementation verification checkpoint:** `a89ef13ccdc4bca45eb3f2d34dea7b4cf78b06f6`
- **Implementation workflow:** `34465053733` — **7/7 green**
- **PHP:** **508 tests / 2626 assertions** across PHP 8.3/8.4 × Illuminate 12/13 with MySQL 8.4 service coverage
- **Browser:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract:** `python scripts/validate.py` green; frozen `spec/0.1/**` unchanged
- **Filament compatibility:** Filament **5.8.1** / Livewire **4.4.4** verified in the matrix
- **Decisions:** `D-019 — ACCEPTED`; `D-048 — ACCEPTED`; `D-049 — ACCEPTED`
- **External review:** **PENDING**
- **Task-board marker:** intentionally unchanged until external review closes
- **Current result:** **IMPLEMENTATION PASSED / REVIEW PENDING / MERGE NOT REQUESTED**

## Review objective

Challenge the implementation for:

- effective-selection authority accidentally derived from caller-controlled or raw Livewire state;
- incorrect select-all / deselection semantics;
- unbounded or silently truncated selection materialization;
- duplicate identity and duplicate-enabled relationship/pivot ambiguity;
- T-501 `current_record` identity/hash compatibility regressions;
- caller input/metadata manufacturing or replacing `current_selection`;
- incorrect confirmation/idempotency binding across selection changes or tenant changes;
- valid confirmation receipt consumption on a wrong-selection mismatch;
- selected-record identity/material leaking into structured audit;
- accidental introduction of a Filament RuntimeBinding driver or second execution path.

## Production path

```text
exact trusted active Filament table Page
        │
        ▼
FilamentCurrentSelectionResolver
        │
        ├── public getSelectedTableRecords(true, bounded chunk)
        ├── persisted Eloquent identity validation
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
normal ActionCall
        │
        ▼
existing ActionBus
        │
        ▼
existing Livewire RuntimeBinding execution
```

There is no Filament RuntimeBinding driver, route/request selection discovery, raw selection-property production dependency, service-provider ambient hook, model re-query for authority, or alternate business-action endpoint.

## Implemented semantics

1. `current_selection` comes only from the exact trusted active Filament table page supplied to trusted adapter code.
2. Production resolution depends only on Filament's public `getSelectedTableRecords()` effective-selection result. It does not inspect raw selected/deselected/tracking properties.
3. Empty effective selection is absence and therefore cannot satisfy a required `current_selection` context.
4. Caller action input and non-authoritative invocation metadata cannot manufacture or replace selection authority.
5. The trusted snapshot is an unordered set of exact persisted Eloquent record identities.
6. Identity reuses the shared canonical T-501 `{modelClass,keyName,keyValue}` representation; attributes are excluded.
7. T-501 byte compatibility is pinned: `TestRecord#41` continues to produce `5436c4020c370bb2f495f0954332bd892a3e5f89950db287c808f292e32bfa96` for `current_record`.
8. A selection scope has its own domain and is independent from tenant identity; generic confirmation/idempotency scope composes both dimensions when present.
9. Equivalent A/B and B/A sets have the same trusted selection scope, confirmation fingerprint and idempotency intent; A/B and A/C differ.
10. Same A/B under different tenants preserves the selection's own scope while changing the overall confirmation/idempotency scope.
11. A receipt approved for A/B cannot authorize A/C, and wrong-selection mismatch does not consume the valid A/B receipt.
12. The resolver requests a bounded first decision window no greater than `max + 1` and rejects over-limit selections instead of truncating.
13. Real Filament select-all with raw selected IDs empty and explicit deselections resolves the public effective set correctly.
14. Filament's record-selectability filtering is applied before SurfaceRelay derives authority.
15. Duplicate effective identity, non-Eloquent value, invalid/unsaved identity, framework selection failure and duplicate-enabled `BelongsToMany` semantics fail closed with static non-chained errors.
16. Composer order remains `authenticated_actor → tenant → current_record → current_selection` when all four are available.
17. `FilamentActionGateway::dispatch(...)` remains unchanged; maximum selection size is trusted constructor configuration only.
18. T-404 structured audit stores only `current_selection` requirement/provider provenance and excludes selected IDs, model classes, business attributes, selection count, selection scope key, caller spoof values and UI query/filter state.
19. Active filters remain outside this task and are deferred to T-503.
20. Existing Livewire execution remains the only business-action execution path.

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

## Concrete review scenarios already covered

- explicit A/B selection through real Filament and production gateway;
- effective empty selection with caller input/metadata spoof values;
- A/B versus B/A canonical identity stability;
- A/B versus A/C scope separation;
- tenant A versus tenant B composition separation;
- select-all tracking with raw selected IDs empty and one deselected exception;
- record selectability filtering before authority derivation;
- exact selection ceiling and `max + 1` fail-closed behavior;
- bounded query-backed select-all decision window;
- duplicate effective record identity;
- duplicate-enabled `BelongsToMany` ambiguity;
- non-Eloquent and unsaved selected values;
- static/non-chained framework exception translation;
- wrong-selection confirmation mismatch without valid-receipt consumption;
- audit minimization for completed/halted selection-aware invocations;
- canonical trusted-context composer ordering;
- byte-compatible T-501 current-record scope hash after shared identity refactor.

## Scope audit

Implementation checkpoint compare:

`ae77cbf26cbefcca478d070765386628343bec52...a89ef13ccdc4bca45eb3f2d34dea7b4cf78b06f6`

- **28 commits ahead / 0 behind**;
- 20 changed files;
- no `spec/0.1/**` change;
- no `packages/browser-runtime/src/**` production change;
- no `packages/laravel/src/Livewire/**` change;
- no new RuntimeBinding driver.

Production changes are restricted to Filament context/invocation code. Test fixtures and integration tests exercise real Filament 5 public contracts.

## Review instructions

Please review the exact pull-request head and challenge the authority boundary rather than generic style. Any actionable correctness/security finding will be reproduced TDD-first and fixed minimally before the thread is considered closed. Generic docstring/style coverage is non-blocking unless repository CI itself requires it.

`TASKS.md` remains unchanged while review is pending. After external review closes, the T-502 marker alone will move to `DONE / REVIEWED` and receive a new exact-head CI checkpoint.

## Current result

**IMPLEMENTATION PASSED / EXTERNAL REVIEW PENDING / MERGE NOT REQUESTED.**
