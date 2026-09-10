# External Review Request

## Review status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Scope:** `T-501 — Filament record context binding`
- **Branch:** `feat/filament-record-context-binding`
- **Base / merge-base:** `main@66afcc22704bfe3b317f2894b7737cf18d248e34`
- **Code checkpoint:** `e8e113647383f8fe294eeb046eb5c8dd152adb16`
- **Checkpoint workflow:** `34435810811` — **7/7 green**
- **PHP:** **479 tests / 2524 assertions** across PHP 8.3/8.4 × Illuminate 12/13 with MySQL 8.4 service coverage
- **Browser isolation:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract:** `python scripts/validate.py` green; frozen `spec/0.1/**` unchanged
- **Filament compatibility:** Filament 5.8.1 / Livewire 4.4.4 verified on the current matrix
- **Decisions:** `D-019 — ACCEPTED`; `D-048 — ACCEPTED`
- **External review result:** PENDING
- **Merge:** not requested; explicit permission remains required

## Review objective

Challenge the Filament trusted-context boundary for caller-controlled record selection, silent record re-resolution/retargeting, unstable record identity, tenant/record authority conflation, unsafe Filament private-API dependence, confirmation/idempotency scope mismatch, audit leakage, or accidental creation of a second execution driver/path.

T-501 is intentionally narrow: Filament contributes `current_record` trusted context to the existing Livewire RuntimeBinding execution path. It does not introduce a Filament driver, Filament-specific business endpoint, current selection, filters, confirmation UI bridge, or the order-operations demo.

## Implemented semantics

1. `FilamentRecordContextResolver` accepts one exact trusted active `Filament\Resources\Pages\Page` instance.
2. A page is record-capable only when a physical public/callable `getRecord()` method exists; Livewire magic `__call` alone is not authority.
3. Legitimate non-record resource pages resolve to trusted-context absence.
4. Record-aware pages return the exact Eloquent `Model` instance already owned by Filament; SurfaceRelay performs no replacement lookup or reload.
5. Caller input, invocation metadata, route/query/request values, binding ID, confirmation receipt and idempotency key are not inputs to record resolution.
6. Stable record identity is `SHA-256("surfacerelay.filament.current_record.v1\n" + canonical({modelClass,keyName,keyValue}))`.
7. Model attributes, tenant, actor, route values and timestamps do not participate in the record identity key.
8. Integer/string key types remain distinct; integer zero is valid. Null, empty and unsupported key states fail closed.
9. `ResolvedTrustedValue::value` is the exact model object; provenance is only `provider=filament.current_record`, `reference=null`.
10. `FilamentTrustedContextComposer` delegates the existing actor/tenant composer and appends typed `current_record` authority without modifying core `TrustedContextComposer`.
11. Tenant and record identity remain independent trusted dimensions. Existing confirmation/idempotency scope includes both when present.
12. Record-A confirmation scope cannot consume/authorize a record-B receipt candidate; wrong-scope consumption does not spend the exact original receipt.
13. Existing idempotency intent fingerprint changes across different records and remains stable across distinct model instances with the same typed record identity.
14. T-404 audit persists only trusted-context `{requirement,provider}` facts; record key, model attributes, tenant value and record scope key do not appear in the audited semantic projection.
15. A real Filament 5 `InteractsWithRecord` page fixture proves exact-model preservation and zero SurfaceRelay DB queries during resolution.
16. Filament is dev-only in Composer metadata; the base package/provider remains Filament-neutral.
17. Existing Livewire execution binding and browser driver remain unchanged.

## TDD / verification evidence

```text
Plan-head baseline:           fe2840ff470fff5c2a3acdb2f3e2be7fc86bac29 / 34433195788 — 7/7 green
Dependency-policy RED:        e478d9fce9720d87c32fa7356989a89845e44ff7 / 34434052061 — 454 tests / 2420 assertions, 1 expected failure
Dependency/matrix GREEN:      1f170db35f606f4f5058ca06a1365ccc6c3ef547 / 34434175823 — 7/7 green
Record-resolver RED:          d09964efc5ebada88930e14db0b3148411a19f9c / 34434272335 — missing resolver only
Record-resolver GREEN:        b516c5469294ec7912608f6a05f55c1540c58597 / 34435117503 — 7/7 green
Composer RED:                 3a92572add17515299796a4292756de8029e342d / 34435241640 — 472 tests / 2473 assertions, 3 expected missing-class errors
Composer GREEN:               3340cf28eb57fb4fc3021ef5303da921733f184a / 34435394181 — 7/7 green
Trust-control harness RED:    c1a02ca00089d22e0ee3872237527bce881685ae / 34435523351 — 473 tests / 2485 assertions, 1 expected failure
Trust-control GREEN:          081eed99fdd4cf29ade3f7b002d8bb1c8eed38c3 / 34435672411 — 7/7 green
Real Filament/code checkpoint:e8e113647383f8fe294eeb046eb5c8dd152adb16 / 34435810811 — 7/7 green
PHP:                          479 tests / 2524 assertions
Browser:                      TypeScript typecheck + 103/103 Vitest tests
Contract:                     green; frozen spec/0.1 unchanged
```

## Review-sensitive failure cases

The resolver must fail closed without sensitive exception chaining for:

- record-capable page returning null;
- non-Model result;
- unsaved model;
- null or empty primary-key identity;
- unsupported key types;
- empty key name;
- `getRecord()` throwing an exception containing sensitive text.

A normal Filament resource page with no physical `getRecord()` method is a legitimate absence, not an invariant error.

## Scope audit

Base-to-code-checkpoint comparison is ahead-only and contains no changes under:

- `spec/0.1/**`;
- `packages/browser-runtime/**`;
- `packages/laravel/src/Livewire/**`.

Production additions are limited to:

```text
packages/laravel/src/Filament/Context/FilamentRecordContextResolver.php
packages/laravel/src/Filament/Context/FilamentTrustedContextComposer.php
packages/laravel/src/Filament/Context/InvalidFilamentRecordContext.php
```

plus Composer/CI dependency metadata. No new Filament RuntimeBinding target/driver exists.

## Known non-claims / deferred work

- T-501 does not resolve `current_selection` (T-502).
- T-501 does not expose active table filters (T-503).
- T-501 does not implement the Filament confirmation UI bridge (T-504).
- T-501 does not implement the multi-tenant order-operations vertical (T-505).
- The record scope hash is an identity aid, not authorization proof.
- Filament page construction/discovery remains trusted adapter responsibility; T-501 does not globally discover pages from routes/requests.

## Requested review

Please focus on authority boundaries, fail-closed behavior, exact record identity, Filament public-API compatibility, trust-control binding and absence of alternate execution/re-resolution paths. Generic unrelated refactors should not be folded into this task unless they reveal a correctness or security defect.
