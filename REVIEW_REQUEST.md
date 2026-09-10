# External Review Record

## Review status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Scope:** `T-501 — Filament record context binding`
- **Branch:** `feat/filament-record-context-binding`
- **Pull request:** `#5`
- **Base / merge-base:** `main@66afcc22704bfe3b317f2894b7737cf18d248e34`
- **Original implementation checkpoint:** `e8e113647383f8fe294eeb046eb5c8dd152adb16` / `34435810811` — **7/7 green**
- **Initial review-prep head:** `16821431198b454bd611e65f44da03254611ab3f` / `34436214862` — **7/7 green**
- **CodeRabbit full-review run:** `5931182a-b621-47c3-b361-8843f450f039`
- **Review-hardening production checkpoint:** `88967b90711d3080ad2e47ad8e47fa5a23280579` / `34438214057` — **7/7 green**
- **PHP after hardening:** **482 tests / 2533 assertions** across PHP 8.3/8.4 × Illuminate 12/13 with MySQL 8.4 service coverage
- **Browser isolation:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract:** `python scripts/validate.py` green; frozen `spec/0.1/**` unchanged
- **Filament compatibility:** Filament 5.8.1 / Livewire 4.4.4 verified across the current matrix
- **Decisions:** `D-019 — ACCEPTED`; `D-048 — ACCEPTED`
- **External review result:** **PASSED after TDD hardening**
- **Unresolved inline review threads:** **0**
- **Merge:** not requested; explicit permission remains required

## Review objective

Challenge the Filament trusted-context boundary for caller-controlled record selection, silent record re-resolution/retargeting, unstable record identity, tenant/record authority conflation, unsafe framework exceptions, private Filament API dependence, confirmation/idempotency scope mismatch, audit leakage, and accidental creation of a second execution path.

The final implementation remains narrow: exact active Filament page context is composed into the existing `InvocationContext`, a normal `ActionCall` is created, and the existing `ActionBus` / Livewire RuntimeBinding path remains authoritative.

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
        ├── TrustedContextComposer      → actor / tenant
        └── FilamentRecordContextResolver → current_record
        │
        ▼
InvocationContext
        │
        ▼
ActionCall
        │
        ▼
existing ActionBus
        │
        ▼
existing Livewire RuntimeBinding execution
```

No Filament RuntimeBinding driver, route/request record discovery, model re-query, service-provider ambient hook, or alternate business-action endpoint is introduced.

## Final implemented semantics

1. `FilamentRecordContextResolver` accepts one exact trusted `Filament\Resources\Pages\Page` instance.
2. Record capability requires a physical public/callable `getRecord()` method; Livewire magic `__call` cannot manufacture it.
3. Non-record pages produce trusted-context absence and therefore cannot satisfy an action requiring `current_record`.
4. Record-aware pages return the exact Eloquent model already owned by Filament; SurfaceRelay performs no replacement lookup/reload.
5. Caller input, invocation metadata, routes, query/request values, binding ID, receipt and idempotency key do not select the record.
6. Stable record identity is a domain-separated SHA-256 over canonical `{modelClass,keyName,keyValue}` only.
7. Model attributes, tenant, actor, routes and timestamps do not participate in record identity.
8. Integer/string key identities remain distinct; integer zero is valid; invalid identities fail closed.
9. `getRecord()`, `getKeyName()` and `getKey()` exceptions become static non-chained adapter failures.
10. Provenance is only `provider=filament.current_record`, `reference=null`.
11. `FilamentTrustedContextComposer` preserves existing actor/tenant composition and appends typed `current_record` authority.
12. `FilamentInvocationContextFactory` converts exact page context into the existing `InvocationContext` type.
13. `FilamentActionGateway` is the production invocation boundary and creates the normal existing `ActionCall` before dispatching `ActionBus`.
14. Different records change confirmation/idempotency identity; equivalent typed record identity is stable across distinct Eloquent instances.
15. Tenant and current-record identities remain independent trusted dimensions.
16. Record-A receipt cannot authorize record B; wrong-scope mismatch does not spend the valid record-A receipt.
17. T-404 audit persists only `{requirement,provider}` trusted-context facts and not record key/attributes/tenant/scope-key material.
18. Real Filament 5 `InteractsWithRecord` integration proves exact model preservation and zero SurfaceRelay record re-query.
19. Filament remains dev-only in Composer metadata and the base service provider remains Filament-neutral.
20. Existing Livewire production and browser-runtime production remain unchanged.

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
Review-prep head:               16821431198b454bd611e65f44da03254611ab3f / 34436214862 — 7/7 green
Review finding #1 RED:          f7918f04f4b2a18fb38fa24b5a1f66d63aa9388f / 34437159969 — 480 / 2524, 1 unsafe identity-access error
Review finding #1 GREEN:        e311201abc44ac61bddedcd22cbaf8d4802e26c7 / 34437278072 — 7/7 green
Factory-composition RED:        b961700be7ed97cda4bb7ca423c17068700027b9 / 34437453767 — 482 / 2527, 2 expected missing-factory errors
Factory-composition GREEN:      031dfe4218bdaa776f40cfca073ad7ad7bc98d8b / 34437591532 — 7/7 green
Gateway first RED attempt:      1e3e11392d62b3d9e4c400a8017d3a3d40600d6a — INVALID HARNESS EVIDENCE; filename/class mismatch prevented intended tests from executing
Gateway valid RED:              513fa5189b907c1022a93e8da7d8df4136959952 / 34438127154 — 482 / 2527, exactly 2 missing-gateway errors
Review-hardening GREEN:         88967b90711d3080ad2e47ad8e47fa5a23280579 / 34438214057 — 7/7 green
PHP after hardening:            482 tests / 2533 assertions
Browser:                        TypeScript typecheck + 103/103 Vitest tests
Contract:                       green; frozen spec/0.1 unchanged
```

## External review findings and closure

### Finding 1 — Eloquent identity accessor exceptions could escape — FIXED / CONFIRMED

The full review correctly found that `getKeyName()` / `getKey()` were outside the adapter's safe exception boundary.

- RED `f7918f04… / 34437159969`: custom model `getKey()` leaked `SECRET-IDENTITY-ERROR`.
- GREEN `e311201a… / 34437278072`: both identity accessors are inside a `Throwable` boundary and map to static `invalidRecordIdentity()` with no previous exception.

CodeRabbit explicitly confirmed the fix; the inline thread is resolved.

### Finding 2 — trusted record context was not wired into production invocation — FIXED / CONFIRMED

The first reviewed implementation had resolver/composer tests but no production path consuming the result. A first hardening step introduced `FilamentInvocationContextFactory` and a real ActionBus E2E.

- RED `b961700b… / 34437453767`: production factory missing.
- GREEN `031dfe42… / 34437591532`: exact page context could be composed into `InvocationContext` and used by the existing ActionBus.

CodeRabbit's incremental review correctly found that this still left the factory without a production caller (comment `5613229905`).

### Incremental blocker — factory still had no production caller — FIXED / CONFIRMED

A production `FilamentActionGateway` was then required TDD-first.

The first RED attempt (`1e3e1139…`) was intentionally **not** accepted as RED evidence because PHPUnit did not execute the intended class due to a filename/class mismatch. The harness was fixed first.

- Valid RED `513fa518… / 34438127154`: **482 tests / 2527 assertions**, exactly two errors because `FilamentActionGateway` did not exist.
- GREEN `88967b90… / 34438214057`: **7/7 green**, **482 tests / 2533 assertions**.

The gateway now owns exact `Page → FilamentInvocationContextFactory → InvocationContext → ActionCall → ActionBus` composition while retaining the existing Livewire execution binding.

CodeRabbit directly re-checked only this blocker at the GREEN head and replied in comment `5613323657`: **“Yes. This closes the specific production-wiring blocker.”** It confirmed that the gateway is production source, constructs the factory, supplies the exact Page, creates the normal ActionCall and invokes the existing ActionBus.

### Non-blocking CodeRabbit warning

Generic docstring coverage remains below CodeRabbit's generic 80% threshold. This is a documentation-style metric, not a T-501 correctness/security finding, and is intentionally not expanded into this trust-control PR.

## Scope audit

At production hardening head `88967b90…`, base comparison is ahead-only and contains no files under:

- `spec/0.1/**`;
- `packages/browser-runtime/**`;
- `packages/laravel/src/Livewire/**`.

Production additions are restricted to:

```text
packages/laravel/src/Filament/Context/FilamentRecordContextResolver.php
packages/laravel/src/Filament/Context/FilamentTrustedContextComposer.php
packages/laravel/src/Filament/Context/FilamentInvocationContextFactory.php
packages/laravel/src/Filament/Context/InvalidFilamentRecordContext.php
packages/laravel/src/Filament/Invocation/FilamentActionGateway.php
```

plus Composer/CI dependency metadata. No `filament` RuntimeBinding driver exists.

## Non-claims / deferred work

- T-501 does not resolve `current_selection` — T-502.
- T-501 does not expose active table filters — T-503.
- T-501 does not implement the Filament confirmation UI bridge — T-504.
- T-501 does not implement the multi-tenant order-operations demo — T-505.
- The record scope hash is an identity aid, not authorization proof.
- The gateway requires trusted code to supply the exact active Page; it does not discover pages from ambient routes/requests.

## Final result

**PASSED.** T-501's exact-record resolution, trust-control binding, static-safe failure behavior and production invocation composition were reviewed and hardened. PR #5 remains open; merge is a separate explicit user gate.
