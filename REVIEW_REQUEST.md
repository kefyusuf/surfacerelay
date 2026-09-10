# External Review / Merge Record

## Current status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Scope:** `T-503 — Active-filter context`
- **Feature branch:** `feat/filament-active-filter-context`
- **Original base / merge-base:** `main@00cf05d48b4c02e0eeaa0d8413683d39db4e0f65`
- **Implementation review head:** `18b218b3c45578821028b85111c44d19da8b28b9`
- **Implementation verification workflow:** `34483725864` — **7/7 green**
- **PHP:** **551 tests / 2788 assertions** across PHP 8.3/8.4 × Illuminate 12/13 with MySQL 8.4 service coverage
- **Browser:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract:** `python scripts/validate.py` green; frozen `spec/0.1/**` unchanged
- **Filament compatibility:** Filament **5.8.1** / Livewire **4.4.4**
- **Decision:** `D-050` — **ACCEPTED**
- **Pull request:** pending creation after fresh review-preparation-head validation
- **External review:** pending
- **Merge:** separate explicit gate; not requested

## Review objective

Review T-503 specifically for authority confusion, caller-to-trusted promotion, stale/deferred Filament state, canonicalization ambiguity, confirmation/idempotency scope drift, audit leakage, and accidental protocol/framework coupling.

The implementation must preserve the existing Action execution path. Filament remains a trusted-context/UI adapter, not a RuntimeBinding driver.

## Intended production path

```text
exact trusted active Filament Page
        │
        ├── no explicit active-filter exposure
        │       └── no trusted extension; prior behavior unchanged
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
 TrustedContextExtension: filament/active_filters
                    │
                    ├── confirmation scope binding
                    ├── idempotency intent binding
                    └── audit manifest: extension/provider only
                    │
                    ▼
 existing InvocationContext → ActionCall → ActionBus → Livewire RuntimeBinding
```

## Acceptance boundary under review

1. `active_filters` is not added to frozen `ContextRequirement` or `spec/0.1`.
2. Adapter-specific trusted authority uses a namespaced `TrustedContextExtension` channel physically distinct from generic metadata and core trusted context requirements.
3. T-503 uses exact extension key `filament/active_filters` and provider `filament.active_filters`.
4. Exposure is explicit trusted server-side wiring through `FilamentContextExposure`; action input, generic metadata, request/query/route data, WebMCP arguments, binding ID, confirmation receipt, and idempotency key cannot enable or alter it.
5. Authority comes only from the exact active Filament `HasTable` page via public `getTable()->getFilters()` and public `getTableFilterState()`.
6. Deferred/pending `getTableFilterFormState()`, `tableFilters`, and `tableDeferredFilters` are not authority sources.
7. Explicit exposure with zero configured filters is a present empty snapshot; no exposure means no trusted extension.
8. Snapshot canonicalization is deterministic and type-preserving; unrepresentable values fail closed with static adapter errors and no sensitive exception chaining.
9. Present trusted extensions bind confirmation scope and idempotency intent; zero-extension hash documents remain structurally unchanged for compatibility.
10. A confirmation receipt issued for filter state A cannot authorize applied filter state B, and a scope mismatch does not consume an otherwise-valid A receipt.
11. Reuse of the same idempotency key after applied filter state changes conflicts rather than replaying the prior intent.
12. Audit persists only `{extension, provider}` for trusted extensions; raw filter values, canonical bytes, scope keys, and sensitive exception material are excluded.
13. `current_selection` remains a separate trusted dimension and does not absorb active-filter state.
14. No Filament RuntimeBinding driver, alternate business endpoint, browser-runtime change, Livewire production change, or `spec/0.1` change is introduced.

## Review evidence already available

```text
Design / D-050 checkpoint:        be17945dac6cf2d075bd72075ee26839286b8709
Implementation review head:       18b218b3c45578821028b85111c44d19da8b28b9
Implementation validation:        34483725864 — 7/7 green
PHP:                               551 tests / 2788 assertions
Browser:                           TypeScript typecheck + 103/103 Vitest
Contract:                          python scripts/validate.py green
Merge-base:                        00cf05d48b4c02e0eeaa0d8413683d39db4e0f65
Compare state:                     ahead-only, 34 commits
```

The review-preparation documentation commit intentionally follows the implementation checkpoint. Its exact head must be independently validated before a pull request is opened; the final closure head must be revalidated again after any review findings or documentation updates.

## Known non-T-503 observation

The browser job's `npm ci` currently reports two moderate dependency advisories. T-503 does not modify `packages/browser-runtime/**` or its lockfile, so those advisories are not introduced by this change. They are not being silently fixed inside T-503 because that would mix dependency-maintenance scope into a trusted-context task.

## Deferred work

- `T-504 — Confirmation bridge`
- `T-505 — Multi-tenant order operations demo`
- General Filament page-state rebinding across refresh/pagination/navigation remains a separate open portability/lifecycle question.

## Merge gate

Do not merge on self-review alone. Required next gates are:

1. fresh CI on the review-preparation head;
2. pull-request external review and resolution of any actionable correctness/security finding;
3. final exact-head CI and review-thread check;
4. explicit user authorization for merge.
