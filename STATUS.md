# Project Status

> Current repository state for implementation and review.

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `main`
- **Stage:** M0 DONE; M1 DONE; M1.1 DONE/REVIEWED; M2 DONE/REVIEWED; M3 DONE/REVIEWED; M4 DONE/REVIEWED/MERGED; **M5 IN PROGRESS**
- **Last merged/revalidated task:** `T-503 — Active-filter context`
- **Current task:** none
- **T-502 status:** **DONE / REVIEWED / MERGED / MAIN REVALIDATED**
- **T-503 status:** **DONE / REVIEWED / MERGED / MAIN REVALIDATED**
- **T-503 original base:** `main@00cf05d48b4c02e0eeaa0d8413683d39db4e0f65`
- **T-503 design/decision checkpoint:** `be17945dac6cf2d075bd72075ee26839286b8709`
- **T-503 implementation review head:** `18b218b3c45578821028b85111c44d19da8b28b9`
- **T-503 final feature head:** `a46ab15c88b32c4785279f1dc96f1c62b6ded0f6`
- **T-503 merge commit:** `7fe9db4f1e87257b83120396cc290b7253424ad4`
- **T-503 design spec:** `docs/superpowers/specs/2026-09-10-filament-active-filter-context-design.md`
- **T-503 implementation plan:** `docs/superpowers/plans/2026-09-10-filament-active-filter-context.md`
- **PHP verified:** **551 tests / 2788 assertions** across PHP 8.3/8.4 × Illuminate 12/13 with MySQL 8.4 service coverage
- **Browser isolation verified:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract verified:** `python scripts/validate.py` green; frozen `spec/0.1/**` unchanged
- **Filament compatibility:** Filament **5.8.1** + Livewire **4.4.4** verified in the T-503 matrix
- **Decisions:** `D-019`, `D-048`, `D-049`, `D-050` — **ACCEPTED**
- **T-502 pull request:** `#6` — **CLOSED / MERGED**
- **T-503 pull request:** `#7` — **CLOSED / MERGED**
- **CodeRabbit review:** run `427bcd38-3e18-46e4-b393-dbd6ca99dabb` — **2 documentation/tracking findings fixed; production-code findings: 0**
- **Unresolved PR review threads:** **0**
- **Post-merge main validation:** `34492632652` — **7/7 green**
- **Next task:** `T-504 — Confirmation bridge` — **NOT STARTED**

## T-503 changed files / implementation surface

Production/runtime changes are intentionally confined to the Laravel trusted-context path:

```text
Runtime/Context:
  TrustedContextExtension
  DuplicateTrustedContextExtension
  TrustedContextExtensionNotAvailable
Runtime:
  InvocationContext
Runtime/Scope:
  RuntimeScopeCanonicalizer
Confirmation:
  ConfirmationScopeHasher
Idempotency:
  IdempotencyIntentHasher
Filament/Context:
  FilamentContextExposure
  FilamentActiveFilterContextResolver
  InvalidFilamentActiveFilterContext
  FilamentInvocationContextFactory
Filament/Invocation:
  FilamentActionGateway
Audit:
  AuditTrustedContextEntry
  AuditEventFactory
  DatabaseAuditEventStore
```

Tests/fixtures added or hardened cover generic trusted extensions, exact Filament applied/deferred state semantics, gateway spoofing, confirmation/idempotency interaction, audit secrecy, empty-filter exposure, and dependency/boundary policy. `spec/0.1/**`, `packages/browser-runtime/src/**`, and `packages/laravel/src/Livewire/**` remain outside the production diff.

## T-503 verification / review evidence

```text
Design / D-050 checkpoint:      be17945dac6cf2d075bd72075ee26839286b8709
Implementation review head:     18b218b3c45578821028b85111c44d19da8b28b9
Implementation validation:      34483725864 — 7/7 green; 551 tests / 2788 assertions
Review-preparation head:         d3e94df9fc3c541fb3df408525318ae7c3fc23eb
Push validation:                 34487043650 — 7/7 green; 551 / 2788; browser 103/103; contract green
PR #7 initial validation:        34487266417 — 7/7 green
CodeRabbit full review:          427bcd38-3e18-46e4-b393-dbd6ca99dabb — 2 documentation/tracking findings; 0 production issues
CodeRabbit Major:                optional-sounding D-050/spec binding wording — FIXED / CONFIRMED
CodeRabbit Minor:                stale tracking-plan/status wording — FIXED / CONFIRMED
Review-hardening checkpoint:     68c6c94cfc6250f3135c946f080d73cc08cdcf51
Review-hardening validation:     34489567321 — 7/7 green; 551 / 2788; browser 103/103; contract green
Open review threads:             0
Final feature head:              a46ab15c88b32c4785279f1dc96f1c62b6ded0f6
Final feature-head push CI:      34491356472 — 7/7 green; 551 / 2788; browser 103/103; contract green
Final PR merge-ref CI:           34491366747 — 7/7 green; 551 / 2788; browser 103/103; contract green
Merge commit:                    7fe9db4f1e87257b83120396cc290b7253424ad4
Post-merge main validation:      34492632652 — 7/7 green; 551 / 2788; browser 103/103; contract green
```

## T-503 known limitations / deliberate exclusions

- General page-state rebinding across Filament refresh/pagination/navigation remains an open lifecycle/portability question; T-503 snapshots the exact page state once per invocation and resolves again on the next invocation.
- Global/column search, sort, tabs, grouping, pagination, indicators, filtered record IDs, arbitrary SQL and saved-filter presets are not trusted-context inputs in T-503.
- T-504 confirmation UI/bridge and T-505 multi-tenant order demo are not implemented here.
- The browser workflow currently reports two moderate npm dependency advisories during `npm ci`; T-503 does not change browser-runtime source or lockfile, so dependency maintenance remains separate scope.

## T-503 production boundary

D-050 preserves the frozen core context vocabulary while allowing explicitly exposed adapter-specific trusted UI authority.

```text
exact trusted active Filament Page
        │
        ├── no explicit active-filter exposure
        │       └── no trusted extension; T-502 behavior unchanged
        │
        └── FilamentContextExposure::activeFilters()
                    │
                    ▼
        public getTable()->getFilters()
                    │
                    ▼
        public getTableFilterState(name)
                    │
                    ▼
        canonical applied-filter snapshot
                    │
                    ▼
 trusted extension: filament/active_filters
                    │
                    ├── mandatory confirmation scope binding
                    ├── mandatory idempotency intent binding
                    └── audit manifest: extension/provider only
                    │
                    ▼
 existing InvocationContext → ActionCall → ActionBus → Livewire RuntimeBinding
```

Locked T-503 invariants:

1. `active_filters` is not added to frozen `ContextRequirement` or `spec/0.1`.
2. Adapter-specific trusted authority uses a namespaced trusted-runtime-extension channel physically separate from generic metadata.
3. T-503 uses the exact extension key `filament/active_filters` and provider `filament.active_filters`.
4. Exposure is explicit trusted server-side adapter wiring; action input/metadata/browser arguments cannot enable or alter it.
5. Authority comes only from the exact active `HasTable` page and public `getFilters()` + `getTableFilterState()` APIs.
6. Deferred `getTableFilterFormState()` / `tableDeferredFilters` state is not authority before Apply.
7. Exposed empty filter state is a present empty snapshot, distinct from no exposure.
8. Snapshot state is canonical and type-preserving; unrepresentable custom state fails closed without fallback casting/serialization.
9. Every present trusted extension always binds confirmation scope and idempotency intent; when none exist the hash document remains unchanged for byte-for-byte compatibility.
10. Audit persists only the trusted extension key/provider, never raw filter state or scope material.
11. T-502 `current_selection` remains independent; filter state is not embedded into selection identity.
12. No Filament RuntimeBinding driver, alternate action endpoint, or browser-runtime change is introduced.

## T-503 external review closure

CodeRabbit full review run `427bcd38-3e18-46e4-b393-dbd6ca99dabb` produced two actionable comments, both documentation/tracking issues rather than production-code findings.

The Major finding identified optional-sounding “by default” language for extension security binding. D-050 and the design spec now state the implemented invariant unconditionally: **every present trusted runtime extension always binds confirmation scope and idempotency intent**. CodeRabbit confirmed the wording now matches the existing hasher behavior.

The Minor finding identified stale pre-implementation tracking. The retained implementation plan is now explicitly labeled as a prospective/historical TDD artifact, while `STATUS.md`, `TASKS.md`, and `REVIEW_REQUEST.md` carry authoritative current state. CodeRabbit confirmed the ambiguity is resolved.

Both inline review threads are resolved and outdated; unresolved review-thread count is zero.

## T-503 merge closure

PR #7 was merged with an expected-head guard pinned to `a46ab15c88b32c4785279f1dc96f1c62b6ded0f6` using the repository's established merge-commit method. GitHub created merge commit `7fe9db4f1e87257b83120396cc290b7253424ad4`, whose parents are the original base `00cf05d48b4c02e0eeaa0d8413683d39db4e0f65` and exact feature head. The merge commit became `main` and post-merge push validation `34492632652` completed **7/7 green** with PHP **551 tests / 2788 assertions**, browser typecheck + **103/103 Vitest**, contract validation, and PHP lint all green.

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
14. Active filters are implemented separately by T-503 and do not alter T-502 selection identity.
15. `spec/0.1/**`, `packages/browser-runtime/src/**` and `packages/laravel/src/Livewire/**` remain unchanged.

## T-502 TDD / verification evidence

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
Final pre-merge operational:    d56d0b55b5857bfefa80c043ec6d13e52ea90a4a / 34470021303 — 7/7 green; 509 / 2629
Merge commit:                   0b78afa7d1541b34ef3b82d04c97e99d11cbb694
Post-merge main run A:          34471406523 — 7/7 green
Post-merge main run B:          34471433674 — 7/7 green; 509 / 2629
Browser:                        TypeScript typecheck + 103/103 Vitest tests
Contract:                       green; frozen spec/0.1 unchanged
```

## T-502 external review closure

CodeRabbit full review run `7eadba8f-6972-444e-8e32-b5e66e776a0d` found one actionable correctness issue: the original `min(100, max + 1)` value limited each Filament `lazyById()` batch rather than total materialization. With `max=150`, the regression proved 200 records were hydrated before the limit was observed.

The fix passes the exact trusted sentinel size `maxSelectionRecords + 1` to Filament's public `getSelectedTableRecords()` API. The same real Filament integration proves exactly 151 hydrated records for `max=150`. CodeRabbit incremental verification comment `5617635978` confirmed: **no remaining actionable T-502 correctness/security findings**, **0 unresolved review threads**, and the prior materialization-bound finding is addressed.

## T-502 merge closure

PR #6 was merged with an expected-head guard pinned to `d56d0b55b5857bfefa80c043ec6d13e52ea90a4a` using the repository's established merge-commit method. GitHub created merge commit `0b78afa7d1541b34ef3b82d04c97e99d11cbb694`, whose parents are the original base and exact feature head. The merge commit became `main` and both post-merge push validation runs completed **7/7 green**.

## Next boundary

**T-503 is fully closed on `main`. T-504 — Confirmation bridge is the next task. Begin with design/architecture review; do not encode a new public contract or Filament authority path without an explicit design gate.**