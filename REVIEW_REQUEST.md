# External Review / Merge Record

## Current status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Scope:** `T-503 — Active-filter context`
- **Feature branch:** `feat/filament-active-filter-context`
- **Pull request:** `#7` — **CLOSED / MERGED**
- **Original base / merge-base:** `main@00cf05d48b4c02e0eeaa0d8413683d39db4e0f65`
- **Final feature head:** `a46ab15c88b32c4785279f1dc96f1c62b6ded0f6`
- **Merge commit:** `7fe9db4f1e87257b83120396cc290b7253424ad4`
- **Implementation verification:** `34483725864` — **7/7 green**
- **Final feature-head push validation:** `34491356472` — **7/7 green**
- **Final PR merge-ref validation:** `34491366747` — **7/7 green**
- **Post-merge main validation:** `34492632652` — **7/7 green**
- **PHP:** **551 tests / 2788 assertions** across PHP 8.3/8.4 × Illuminate 12/13 with MySQL 8.4 service coverage
- **Browser:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract:** `python scripts/validate.py` green; frozen `spec/0.1/**` unchanged
- **Filament compatibility:** Filament **5.8.1** / Livewire **4.4.4**
- **Decision:** `D-050` — **ACCEPTED**
- **CodeRabbit full review:** `427bcd38-3e18-46e4-b393-dbd6ca99dabb`
- **External review result:** **PASSED after documentation hardening**
- **Production correctness/security findings:** **0**
- **Actionable review findings:** **2 documentation/tracking findings, both fixed and confirmed**
- **Unresolved review threads:** **0**
- **Merge method:** merge commit with expected-head guard pinned to the exact final feature head

## Final production path

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
                    ├── mandatory confirmation scope binding
                    ├── mandatory idempotency intent binding
                    └── audit manifest: extension/provider only
                    │
                    ▼
 existing InvocationContext → ActionCall → ActionBus → Livewire RuntimeBinding
```

## Reviewed acceptance boundary

1. `active_filters` is not added to frozen `ContextRequirement` or `spec/0.1`.
2. Adapter-specific trusted authority uses a namespaced `TrustedContextExtension` channel physically distinct from generic metadata and core trusted context requirements.
3. T-503 uses exact extension key `filament/active_filters` and provider `filament.active_filters`.
4. Exposure is explicit trusted server-side wiring through `FilamentContextExposure`; action input, generic metadata, request/query/route data, WebMCP arguments, binding ID, confirmation receipt, and idempotency key cannot enable or alter it.
5. Authority comes only from the exact active Filament `HasTable` page via public `getTable()->getFilters()` and public `getTableFilterState()`.
6. Deferred/pending `getTableFilterFormState()`, `tableFilters`, and `tableDeferredFilters` are not authority sources.
7. Explicit exposure with zero configured filters is a present empty snapshot; no exposure means no trusted extension.
8. Snapshot canonicalization is deterministic and type-preserving; unrepresentable values fail closed with static adapter errors and no sensitive exception chaining.
9. **Every present trusted runtime extension always binds confirmation scope and idempotency intent**; zero-extension hash documents remain structurally unchanged for compatibility.
10. A confirmation receipt issued for filter state A cannot authorize applied filter state B, and a scope mismatch does not consume an otherwise-valid A receipt.
11. Reuse of the same idempotency key after applied filter state changes conflicts rather than replaying the prior intent.
12. Audit persists only `{extension, provider}` for trusted extensions; raw filter values, canonical bytes, scope keys, and sensitive exception material are excluded.
13. `current_selection` remains a separate trusted dimension and does not absorb active-filter state.
14. No Filament RuntimeBinding driver, alternate business endpoint, browser-runtime change, Livewire production change, or `spec/0.1` change is introduced.

## External review findings and closure

### Major — optional-sounding extension binding wording — FIXED / CONFIRMED

CodeRabbit identified that D-050 and the design spec used “by default” for confirmation/idempotency binding even though production behavior and the acceptance contract make binding mandatory for every present trusted extension.

- `4e0ed4838a17ea64573623323ff233b7f5f0a583` — D-050 changed to unconditional **always binds** wording.
- `83e7b180cd434b0dff38b5096be4e41d73a83039` — design spec aligned with the same mandatory invariant.
- CodeRabbit confirmed the corrected wording matches the implemented hasher behavior.
- Review thread `PRRT_kwDOUPoqPc6hHHdr` is resolved and outdated.

No production code change was required because the hashers already enforced the stronger invariant.

### Minor — stale T-503 tracking documents — FIXED / CONFIRMED

CodeRabbit identified that `STATUS.md` still described pre-implementation state and the retained implementation plan could be mistaken for current progress.

- `d219672690ee7257376eace19018ccda0bc5179a` — implementation plan labeled as retained prospective/historical execution plan.
- `68c6c94cfc6250f3135c946f080d73cc08cdcf51` — `STATUS.md` aligned with implemented files, verification evidence, known limitations, and next task.
- CodeRabbit confirmed the tracking ambiguity is resolved.
- Review thread `PRRT_kwDOUPoqPc6hHHd1` is resolved and outdated.

## Verification and merge evidence

```text
Design / D-050 checkpoint:       be17945dac6cf2d075bd72075ee26839286b8709
Implementation review head:      18b218b3c45578821028b85111c44d19da8b28b9
Implementation validation:       34483725864 — 7/7 green
Review-preparation head:          d3e94df9fc3c541fb3df408525318ae7c3fc23eb
Push validation:                  34487043650 — 7/7 green
PR initial validation:            34487266417 — 7/7 green
CodeRabbit full review:           427bcd38-3e18-46e4-b393-dbd6ca99dabb — 2 doc/tracking findings, 0 production issues
Review-hardening checkpoint:      68c6c94cfc6250f3135c946f080d73cc08cdcf51
Review-hardening validation:      34489567321 — 7/7 green
Final feature head:               a46ab15c88b32c4785279f1dc96f1c62b6ded0f6
Final feature-head push CI:       34491356472 — 7/7 green
Final PR merge-ref CI:            34491366747 — 7/7 green
Merge commit:                     7fe9db4f1e87257b83120396cc290b7253424ad4
Post-merge main validation:       34492632652 — 7/7 green
PHP:                              551 tests / 2788 assertions
Browser:                          TypeScript typecheck + 103/103 Vitest
Contract:                         python scripts/validate.py green
Unresolved review threads:        0
```

The merge commit has parents `00cf05d48b4c02e0eeaa0d8413683d39db4e0f65` and exact feature head `a46ab15c88b32c4785279f1dc96f1c62b6ded0f6`. Post-merge validation checked out `main@7fe9db4f1e87257b83120396cc290b7253424ad4` directly and passed all seven jobs.

## Known non-T-503 observation

The browser job's `npm ci` reports two moderate dependency advisories. T-503 did not modify `packages/browser-runtime/**` or its lockfile, so those advisories were not introduced by this change. They remain separate dependency-maintenance scope.

## Deferred work

- `T-504 — Confirmation bridge`
- `T-505 — Multi-tenant order operations demo`
- General Filament page-state rebinding across refresh/pagination/navigation remains a separate lifecycle/portability question.

## Merge closure

T-503 is **DONE / REVIEWED / MERGED / MAIN REVALIDATED**. No further T-503 gate remains. The next repository task is `T-504 — Confirmation bridge`, which must begin with its own design/architecture review before implementation.