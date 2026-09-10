# T-503 Filament Active-Filter Trusted Context Implementation Plan

> **Planning artifact status:** This is the prospective TDD execution plan that was used for T-503; its checkboxes are retained as the original plan and are **not** the authoritative progress tracker. The implementation and boundary regression are complete at `18b218b3c45578821028b85111c44d19da8b28b9`; authoritative verification/review status is maintained in `STATUS.md` and `REVIEW_REQUEST.md`. PR #7 external review is the current gate; merge remains separate.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add explicitly exposed Filament applied-filter state as namespaced trusted runtime authority without changing frozen `spec/0.1`, while binding that authority to confirmation/idempotency and keeping raw filter state out of audit persistence.

**Architecture:** Introduce `TrustedContextExtension` beside core `TrustedContextEntry` and non-authoritative metadata. Filament may explicitly expose `filament/active_filters`; its resolver snapshots only public query-facing applied filter state and derives a stable scope key. Confirmation/idempotency include extensions only when present, preserving all existing no-extension hashes byte-for-byte; audit stores only extension key/provider.

**Tech Stack:** PHP 8.3/8.4, Illuminate 12/13, Testbench 10/11, Filament 5.x, Livewire 4.x, PHPUnit 11, GitHub Actions, existing TypeScript/Vitest browser regression suite, Python contract validator.

**Spec:** `docs/superpowers/specs/2026-09-10-filament-active-filter-context-design.md`

## Global Constraints

- Work only on `T-503 — Active-filter context`; base `main@00cf05d48b4c02e0eeaa0d8413683d39db4e0f65`, branch `feat/filament-active-filter-context`.
- `spec/0.1/**`, `ContextRequirement`, `packages/browser-runtime/src/**`, and `packages/laravel/src/Livewire/**` remain unchanged.
- No Filament RuntimeBinding driver or alternate business endpoint.
- Exposure is typed trusted server-side adapter wiring only; input, metadata, request/query/route data, WebMCP args, binding ID, confirmation receipt, and idempotency key cannot enable or alter it.
- Authority uses only the exact active `HasTable` page, `Table::getFilters()` normal visibility semantics, and public `getTableFilterState()` applied state. `getTableFilterFormState()` and raw `tableFilters`/`tableDeferredFilters` are forbidden authority sources.
- Explicit exposure + no visible configured filters produces a present empty snapshot. No exposure means no trusted extension.
- Every present trusted extension binds confirmation scope and idempotency intent. With zero extensions, their hash documents are structurally unchanged.
- Audit persists extension key/provider only; raw state, canonical bytes, and scope keys are forbidden.
- T-502 `current_selection` remains independent and unchanged.

---

### Task 1: Generic Trusted Runtime Extensions

**Files:**
- Create: `packages/laravel/src/Runtime/Context/TrustedContextExtension.php`
- Create: `packages/laravel/src/Runtime/Context/DuplicateTrustedContextExtension.php`
- Create: `packages/laravel/src/Runtime/Context/TrustedContextExtensionNotAvailable.php`
- Modify: `packages/laravel/src/Runtime/InvocationContext.php`
- Create: `packages/laravel/tests/Unit/TrustedContextExtensionTest.php`
- Modify: `packages/laravel/tests/Unit/InvocationContextTest.php`

**Interfaces:**
- `TrustedContextExtension::__construct(string $key, mixed $value, ContextProvenance $provenance, ?string $scopeKey = null)`.
- Append `array $trustedExtensions = []` to `InvocationContext::__construct(...)` after existing `$metadata`.
- Add `hasTrustedExtension()`, `getTrustedExtension()`, `requireTrustedExtension()`, `allTrustedExtensions()`, `withTrustedExtension()`.

- [ ] **Step 1: RED — value and context contract**

Write tests requiring the missing API. Prove valid key/value preservation:

```php
$entry = new TrustedContextExtension(
    'filament/active_filters',
    [],
    new ContextProvenance('filament.active_filters'),
    'scope-A',
);
$context = new InvocationContext(
    surface: 'filament',
    correlationId: 'corr-1',
    metadata: ['filament/active_filters' => ['spoofed' => true]],
    trustedExtensions: [$entry],
);
self::assertTrue($context->hasTrustedExtension('filament/active_filters'));
self::assertSame([], $context->requireTrustedExtension('filament/active_filters')->value);
```

Reject `active_filters`, `/active_filters`, `Filament/active_filters`, `filament/active filters`, null values and empty scope keys. Preserve `[]`, `false`, `0`, `0.0`, `''`. Reject duplicate exact keys. Prove exact-byte deterministic ordering. Prove metadata never becomes a trusted extension. Prove `withTrustedEntry()` preserves extensions and `withTrustedExtension()` preserves core entries.

- [ ] **Step 2: Verify RED**

```bash
cd packages/laravel
vendor/bin/phpunit tests/Unit/TrustedContextExtensionTest.php tests/Unit/InvocationContextTest.php
```

Expected: missing type/API failure.

- [ ] **Step 3: GREEN — minimal primitive**

Use exact namespaced grammar:

```php
private const string KEY_PATTERN = '/^[a-z0-9.-]+\/[a-zA-Z0-9._-]+$/';
```

`InvocationContext` maintains a private exact-key map plus `ksort(..., SORT_STRING)` order. Reject non-extension items and duplicates. Rebuild methods preserve both trusted collections.

- [ ] **Step 4: Verify GREEN**

```bash
vendor/bin/phpunit tests/Unit/TrustedContextExtensionTest.php tests/Unit/InvocationContextTest.php
vendor/bin/phpunit
```

- [ ] **Step 5: Commit**

```bash
git commit -am "feat(runtime): add trusted context extensions"
```

Include newly created files in the commit.

---

### Task 2: Confirmation and Idempotency Intent Binding

**Files:**
- Modify: `packages/laravel/src/Runtime/Scope/RuntimeScopeCanonicalizer.php`
- Modify: `packages/laravel/src/Confirmation/ConfirmationScopeHasher.php`
- Modify: `packages/laravel/src/Idempotency/IdempotencyIntentHasher.php`
- Modify: `packages/laravel/tests/Unit/RuntimeScopeCanonicalizerTest.php`
- Modify: `packages/laravel/tests/Unit/ConfirmationScopeFingerprintRegressionTest.php`
- Modify: `packages/laravel/tests/Unit/IdempotencyHashingTest.php`

**Interfaces:**
- Add `RuntimeScopeCanonicalizer::trustedExtensionIdentity(TrustedContextExtension $entry, string $path): array` with the same scope-key/value identity rule as core trusted entries.
- Only non-empty extension collections add `extensions` to the confirmation/idempotency canonical document.
- Legacy no-extension confirmation hash remains exactly `0057701f23d96b62d1a763c8dad01ae9a0412bf1783e72201d82949df0826e5a`.

- [ ] **Step 1: RED — extension changes intent, metadata does not**

Add states with extension scope keys `filters-A` and `filters-B`. Assert A != B for both hashers, identical A == A, and metadata imitation has no effect. Add unrepresentable extension value without scope key and require static-safe `UnrepresentableConfirmationScope` / `UnrepresentableIdempotencyScope`.

- [ ] **Step 2: Verify RED**

```bash
vendor/bin/phpunit tests/Unit/RuntimeScopeCanonicalizerTest.php tests/Unit/ConfirmationScopeFingerprintRegressionTest.php tests/Unit/IdempotencyHashingTest.php
```

- [ ] **Step 3: GREEN — conditional canonical extension map**

```php
$extensions = [];
foreach ($state->context->allTrustedExtensions() as $entry) {
    $extensions[$entry->key] = $this->canonicalizer->trustedExtensionIdentity(
        $entry,
        'extensions.' . $entry->key,
    );
}
if ($extensions !== []) {
    $scope['extensions'] = $extensions; // $document for idempotency
}
```

Never emit `extensions => []`.

- [ ] **Step 4: Verify GREEN + legacy hash**

```bash
vendor/bin/phpunit tests/Unit/RuntimeScopeCanonicalizerTest.php tests/Unit/ConfirmationScopeFingerprintRegressionTest.php tests/Unit/IdempotencyHashingTest.php
vendor/bin/phpunit
```

- [ ] **Step 5: Commit**

```bash
git commit -am "feat(trust): bind trusted extensions to invocation intent"
```

---

### Task 3: Filament Applied-Filter Resolver

**Files:**
- Create: `packages/laravel/src/Filament/Context/FilamentContextExposure.php`
- Create: `packages/laravel/src/Filament/Context/FilamentActiveFilterContextResolver.php`
- Create: `packages/laravel/src/Filament/Context/InvalidFilamentActiveFilterContext.php`
- Modify: `packages/laravel/tests/Fixtures/Filament/TestTableResource.php`
- Create: `packages/laravel/tests/Fixtures/Filament/EmptyFilterTablePage.php`
- Create: `packages/laravel/tests/Fixtures/Filament/EmptyFilterTableResource.php`
- Create: `packages/laravel/tests/Unit/FilamentActiveFilterContextResolverTest.php`

**Interfaces:**
- `FilamentContextExposure::activeFilters(): self`.
- `FilamentContextExposure::includesActiveFilters(): bool`.
- `FilamentActiveFilterContextResolver::EXTENSION_KEY = 'filament/active_filters'`.
- `FilamentActiveFilterContextResolver::__construct(Page $page, ?RuntimeScopeCanonicalizer $canonicalizer = null)`.
- `FilamentActiveFilterContextResolver::resolve(): ResolvedTrustedValue`.
- Provider is `filament.active_filters`, reference null.

- [ ] **Step 1: RED — realistic applied-state contract**

Add a normal Filament filter to `TestTableResource`; use dedicated empty-filter resource/page fixtures for the empty-map case. Require:

```php
$resolved = (new FilamentActiveFilterContextResolver($page))->resolve();
self::assertSame($page->getTableFilterState('status'), $resolved->value['status']);
self::assertSame('filament.active_filters', $resolved->provenance->provider);
self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $resolved->confirmationScopeKey);
```

Explicit resolver use on `NonRecordPage` must throw static-safe `InvalidFilamentActiveFilterContext`, not return absence.

- [ ] **Step 2: Verify RED**

```bash
vendor/bin/phpunit tests/Unit/FilamentActiveFilterContextResolverTest.php
```

- [ ] **Step 3: GREEN — public query-facing reads only**

```php
$filters = $this->page->getTable()->getFilters();
$snapshot = [];
foreach (array_keys($filters) as $filterName) {
    $snapshot[$filterName] = $this->page->getTableFilterState($filterName);
}
ksort($snapshot, SORT_STRING);
$canonical = $this->canonicalizer->encode($snapshot, 'filament.active_filters');
$scopeKey = hash('sha256', "surfacerelay.filament.active-filters.v1\n" . $canonical);
```

Do not call `getTableFilterFormState()` and do not read raw filter properties. Convert framework failures and canonicalization failures to static-safe non-chained adapter errors.

- [ ] **Step 4: Harden canonical behavior**

Prove nested associative key-order stability, list-order significance, int/string distinction, nested false/zero/empty-string/null/empty-array preservation, unrepresentable state failure, no sensitive exception chaining, and empty-filter resource => present `[]` value.

```bash
vendor/bin/phpunit tests/Unit/FilamentActiveFilterContextResolverTest.php
vendor/bin/phpunit tests/Unit/FilamentCurrentSelectionResolverTest.php tests/Unit/FilamentRecordContextResolverTest.php
```

- [ ] **Step 5: Commit**

```bash
git add packages/laravel/src/Filament/Context packages/laravel/tests/Fixtures/Filament packages/laravel/tests/Unit/FilamentActiveFilterContextResolverTest.php
git commit -m "feat(filament): resolve applied filter context"
```

---

### Task 4: Explicit Exposure Through Existing Filament Gateway

**Files:**
- Modify: `packages/laravel/src/Filament/Context/FilamentInvocationContextFactory.php`
- Modify: `packages/laravel/src/Filament/Invocation/FilamentActionGateway.php`
- Create: `packages/laravel/tests/Integration/FilamentActiveFilterPageIntegrationTest.php`
- Create: `packages/laravel/tests/Integration/FilamentActiveFilterGatewayIntegrationTest.php`

**Interfaces:**
- Append `?FilamentContextExposure $contextExposure = null` after `$metadata` in `FilamentInvocationContextFactory::forPage(...)`.
- Append the same optional final parameter to `FilamentActionGateway::dispatch(...)`.
- Default/no exposure produces zero trusted extensions and preserves T-502 behavior.

- [ ] **Step 1: RED — production wiring**

Prove no exposure => `allTrustedExtensions() === []`; active-filter exposure => exactly one `filament/active_filters`; input/metadata imitation cannot add/change it; explicit exposure on non-table page fails before ActionBus application execution.

- [ ] **Step 2: Verify RED**

```bash
vendor/bin/phpunit tests/Integration/FilamentActiveFilterGatewayIntegrationTest.php
```

- [ ] **Step 3: GREEN — minimal typed exposure wiring**

Factory first builds existing T-501/T-502 core context unchanged. Only when `$contextExposure?->includesActiveFilters()` is true does it resolve the filter snapshot and attach a `TrustedContextExtension`. Gateway only forwards the typed exposure object.

- [ ] **Step 4: Real Filament deferred/live semantics**

With default deferred filters prove:

```text
apply A → SurfaceRelay A
edit pending B without Apply → SurfaceRelay still A
applyTableFilters() → next SurfaceRelay invocation B
```

With `deferFilters(false)`, prove live updates appear through `getTableFilterState()`. Always assert SurfaceRelay matches query-facing `getTableFilterState()`, not form-state `getTableFilterFormState()`.

```bash
vendor/bin/phpunit tests/Integration/FilamentActiveFilterPageIntegrationTest.php tests/Integration/FilamentActiveFilterGatewayIntegrationTest.php
vendor/bin/phpunit tests/Integration/FilamentCurrentSelectionPageIntegrationTest.php tests/Integration/FilamentCurrentSelectionGatewayIntegrationTest.php
```

- [ ] **Step 5: Commit**

```bash
git add packages/laravel/src/Filament packages/laravel/tests/Integration/FilamentActiveFilterPageIntegrationTest.php packages/laravel/tests/Integration/FilamentActiveFilterGatewayIntegrationTest.php
git commit -m "feat(filament): expose trusted active filter context"
```

---

### Task 5: Audit and End-to-End Trust Controls

**Files:**
- Modify: `packages/laravel/src/Audit/AuditTrustedContextEntry.php`
- Modify: `packages/laravel/src/Audit/AuditEventFactory.php`
- Modify: `packages/laravel/src/Audit/DatabaseAuditEventStore.php`
- Modify: `packages/laravel/tests/Unit/AuditEventFactoryTest.php`
- Modify: `packages/laravel/tests/Unit/DatabaseAuditEventStoreTest.php`
- Create: `packages/laravel/tests/Integration/FilamentActiveFilterTrustControlsIntegrationTest.php`

**Interfaces:**
- Audit manifest item contains exactly one of core `requirement` or trusted `extension`, plus provider.
- Existing core JSON remains exactly `{ "requirement": "...", "provider": "..." }` semantically and field-wise.
- New filter item is `{ "extension": "filament/active_filters", "provider": "filament.active_filters" }`.

- [ ] **Step 1: RED — audit secrecy and manifest type**

Construct a filter extension containing `SECRET_FILTER_VALUE` and `SECRET_SCOPE_KEY`; require only extension key/provider in event/store output and neither secret anywhere persisted.

```bash
vendor/bin/phpunit tests/Unit/AuditEventFactoryTest.php tests/Unit/DatabaseAuditEventStoreTest.php
```

Expected: current audit manifest cannot represent trusted extensions.

- [ ] **Step 2: GREEN — disjoint audit manifest**

Evolve `AuditTrustedContextEntry` with invariant-safe core/extension construction. `AuditEventFactory` emits existing core entries first, then trusted extensions in deterministic key order. Store serializes exactly one discriminator plus provider. Never persist extension values/scope keys.

- [ ] **Step 3: RED/GREEN — trust-control integration**

Prove:

```text
applied A => confirmation/idempotency intent A
pending deferred B before Apply => still A
applied B => new confirmation/idempotency intent
receipt for A cannot authorize B and mismatch does not spend valid A receipt
same idempotency key A→B conflicts rather than replaying A
metadata/input imitation cannot recreate authority
audit contains fixed extension/provider only
```

Run:

```bash
vendor/bin/phpunit tests/Integration/FilamentActiveFilterTrustControlsIntegrationTest.php
vendor/bin/phpunit tests/Integration/FilamentCurrentSelectionTrustControlsIntegrationTest.php tests/Integration/StructuredAuditPersistenceSecrecyIntegrationTest.php
vendor/bin/phpunit
```

- [ ] **Step 4: Commit**

```bash
git add packages/laravel/src/Audit packages/laravel/tests/Unit/AuditEventFactoryTest.php packages/laravel/tests/Unit/DatabaseAuditEventStoreTest.php packages/laravel/tests/Integration/FilamentActiveFilterTrustControlsIntegrationTest.php
git commit -m "feat(audit): record trusted extension provenance"
```

---

### Task 6: Boundary Regression and Review Handoff

**Files:**
- Modify: `packages/laravel/tests/Unit/FilamentDependencyPolicyTest.php`
- Modify: `TASKS.md`
- Modify: `STATUS.md`
- Modify: `REVIEW_REQUEST.md`
- Modify: `docs/OPEN-QUESTIONS.md`

**Interfaces:** no protocol/browser/Livewire production API change.

- [ ] **Step 1: Boundary regression**

Prove Filament stays dev-only, `ContextRequirement` still has no `active_filters`, generic trusted-extension classes have no Filament import, and prohibited production areas are untouched.

```bash
vendor/bin/phpunit tests/Unit/FilamentDependencyPolicyTest.php
```

- [ ] **Step 2: Full PHP verification**

```bash
cd packages/laravel
composer validate --strict
vendor/bin/phpunit
find src tests -name '*.php' -print0 | xargs -0 -n1 php -l
```

- [ ] **Step 3: Contract/browser regression**

```bash
cd ../..
python scripts/validate.py
cd packages/browser-runtime
npm ci
npm run typecheck
npm test
```

- [ ] **Step 4: Exact-head GitHub Actions verification**

Require GREEN: `php-lint`, `contract`, `browser`, plus PHP 8.3/8.4 × Illuminate 12/13 matrices. Record exact head SHA/run ID/test count/assertions.

- [ ] **Step 5: Full-diff security review**

Verify:

```text
spec/0.1/** unchanged
ContextRequirement unchanged
packages/browser-runtime/src/** unchanged
packages/laravel/src/Livewire/** unchanged
no raw tableFilters/tableDeferredFilters authority read
no getTableFilterFormState() authority read in SurfaceRelay production
no request/query/route filter fallback
no Filament RuntimeBinding driver
no raw filter/scope material in audit
legacy no-extension hashes unchanged
```

- [ ] **Step 6: Documentation only after GREEN**

Mark T-503 `DONE / REVIEWED` only after verification. Update `STATUS.md` with exact evidence, `REVIEW_REQUEST.md` with external-review handoff, and resolve only the active-filter portion of `docs/OPEN-QUESTIONS.md`. Set T-504 as next task but do not start it.

- [ ] **Step 7: Commit review-prep docs**

```bash
git add TASKS.md STATUS.md REVIEW_REQUEST.md docs/OPEN-QUESTIONS.md packages/laravel/tests/Unit/FilamentDependencyPolicyTest.php
git commit -m "docs(review): prepare T-503 active filter handoff"
```

Do not merge, tag, publish, or begin T-504 without a separate explicit user gate.