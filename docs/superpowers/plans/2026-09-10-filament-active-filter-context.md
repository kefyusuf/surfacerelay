# T-503 Filament Active-Filter Trusted Context Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add explicitly exposed Filament applied-filter state as namespaced trusted runtime authority without changing frozen `spec/0.1`, while binding that authority to confirmation/idempotency and keeping raw filter state out of audit persistence.

**Architecture:** Introduce a generic `TrustedContextExtension` collection beside core `TrustedContextEntry` values and non-authoritative metadata. Filament opts into `filament/active_filters` through trusted adapter wiring, resolves only the exact active table page's public applied filter state, and derives a stable scope key. Confirmation/idempotency include trusted extensions only when present so legacy no-extension fingerprints remain byte-for-byte stable; audit records only extension key/provider.

**Tech Stack:** PHP 8.3/8.4, Laravel/Illuminate 12/13, Orchestra Testbench 10/11, Filament 5.x, Livewire 4.x, PHPUnit 11, existing `RuntimeScopeCanonicalizer`, GitHub Actions matrix, browser Vitest/TypeScript regression suite, Python contract validator.

**Spec:** `docs/superpowers/specs/2026-09-10-filament-active-filter-context-design.md`

## Global Constraints

- Work only on `T-503 — Active-filter context`.
- Base is `main@00cf05d48b4c02e0eeaa0d8413683d39db4e0f65`; feature branch is `feat/filament-active-filter-context`.
- `spec/0.1/**` and `ContextRequirement` remain unchanged.
- `packages/browser-runtime/src/**` and `packages/laravel/src/Livewire/**` remain unchanged.
- Filament remains a trusted-context/UI adapter over the existing Livewire execution binding; no Filament RuntimeBinding driver or alternate business endpoint.
- Active-filter exposure is explicit trusted server-side wiring only; caller input, metadata, request/query/route state, WebMCP arguments, binding ID, confirmation receipt and idempotency key cannot enable or alter it.
- Filament authority comes only from the exact active `HasTable` page, `Table::getFilters()` using normal visibility semantics, and `getTableFilterState()` applied state. `getTableFilterFormState()` and `tableDeferredFilters` are not authority.
- `filament/active_filters` is present with an empty snapshot when exposure is explicit but no configured/applied filters exist; no exposure means the extension is absent.
- Every present trusted extension participates in confirmation scope and idempotency intent. When no extensions exist, the hash document must remain unchanged for byte-for-byte legacy compatibility.
- Audit may persist extension key/provider only, never filter values, canonical bytes or scope keys.
- T-502 `current_selection` remains independent and unchanged.

---

### Task 1: Add the Generic Trusted Runtime Extension Primitive

**Files:**
- Create: `packages/laravel/src/Runtime/Context/TrustedContextExtension.php`
- Create: `packages/laravel/src/Runtime/Context/DuplicateTrustedContextExtension.php`
- Create: `packages/laravel/src/Runtime/Context/TrustedContextExtensionNotAvailable.php`
- Modify: `packages/laravel/src/Runtime/InvocationContext.php`
- Create: `packages/laravel/tests/Unit/TrustedContextExtensionTest.php`
- Modify: `packages/laravel/tests/Unit/InvocationContextTest.php`

**Interfaces:**
- Produces: `TrustedContextExtension::__construct(string $key, mixed $value, ContextProvenance $provenance, ?string $scopeKey = null)`.
- Produces: `InvocationContext::__construct(..., array $metadata = [], array $trustedExtensions = [])` with the new argument appended at the end.
- Produces: `hasTrustedExtension(string $key): bool`, `getTrustedExtension(string $key): ?TrustedContextExtension`, `requireTrustedExtension(string $key): TrustedContextExtension`, `allTrustedExtensions(): array`, `withTrustedExtension(TrustedContextExtension $entry): self`.
- Preserves: all existing `ContextRequirement` APIs and constructor call sites.

- [ ] **Step 1: Write failing value-object and InvocationContext tests**

Add tests that require the missing type/API and prove namespaced key validation, null rejection, falsy values, duplicate rejection, exact-byte key ordering, metadata non-promotion, and cross-preservation through both `withTrustedEntry()` and `withTrustedExtension()`.

Representative RED assertions:

```php
$extension = new TrustedContextExtension(
    'filament/active_filters',
    [],
    new ContextProvenance('filament.active_filters'),
    'scope-1',
);

$context = new InvocationContext(
    surface: 'filament',
    correlationId: 'corr-1',
    metadata: ['filament/active_filters' => ['status' => ['value' => 'spoofed']]],
    trustedExtensions: [$extension],
);

self::assertTrue($context->hasTrustedExtension('filament/active_filters'));
self::assertSame([], $context->requireTrustedExtension('filament/active_filters')->value);
self::assertSame([$extension], $context->allTrustedExtensions());
```

Use invalid keys such as `active_filters`, `/active_filters`, `Filament/active_filters`, and `filament/active filters`; reject null values and empty scope keys. Construct two entries with the same exact key and require `DuplicateTrustedContextExtension`.

- [ ] **Step 2: Run the focused tests and verify RED**

Run:

```bash
cd packages/laravel
vendor/bin/phpunit tests/Unit/TrustedContextExtensionTest.php tests/Unit/InvocationContextTest.php
```

Expected: failure because `TrustedContextExtension` / trusted-extension InvocationContext APIs do not exist. Existing core tests must not be weakened.

- [ ] **Step 3: Implement the minimal generic primitive**

`TrustedContextExtension` validates the same namespaced grammar used by existing contract extension keys:

```php
private const string KEY_PATTERN = '/^[a-z0-9.-]+\/[a-zA-Z0-9._-]+$/';
```

It rejects null value and empty `scopeKey`, while preserving `[]`, `false`, `0`, `0.0`, and `''` as present values.

`InvocationContext` stores extensions in a private exact-key map and an exact-byte sorted list (`ksort(..., SORT_STRING)`). Reject non-extension items and duplicate keys. Append the optional constructor parameter after `metadata` so existing positional/named calls remain valid. `withTrustedEntry()` must pass `$this->trustedExtensionOrder`; `withTrustedExtension()` rebuilds with both collections.

- [ ] **Step 4: Re-run focused tests and full PHP suite**

Run:

```bash
cd packages/laravel
vendor/bin/phpunit tests/Unit/TrustedContextExtensionTest.php tests/Unit/InvocationContextTest.php
vendor/bin/phpunit
```

Expected: GREEN; existing core ordering/metadata-spoofing behavior unchanged.

- [ ] **Step 5: Commit**

```bash
git add packages/laravel/src/Runtime/Context packages/laravel/src/Runtime/InvocationContext.php packages/laravel/tests/Unit/TrustedContextExtensionTest.php packages/laravel/tests/Unit/InvocationContextTest.php
git commit -m "feat(runtime): add trusted context extensions"
```

---

### Task 2: Bind Trusted Extensions into Confirmation and Idempotency Without Legacy Hash Drift

**Files:**
- Modify: `packages/laravel/src/Runtime/Scope/RuntimeScopeCanonicalizer.php`
- Modify: `packages/laravel/src/Confirmation/ConfirmationScopeHasher.php`
- Modify: `packages/laravel/src/Idempotency/IdempotencyIntentHasher.php`
- Modify: `packages/laravel/tests/Unit/RuntimeScopeCanonicalizerTest.php`
- Modify: `packages/laravel/tests/Unit/ConfirmationScopeFingerprintRegressionTest.php`
- Modify: `packages/laravel/tests/Unit/IdempotencyHashingTest.php`

**Interfaces:**
- Produces: `RuntimeScopeCanonicalizer::trustedExtensionIdentity(TrustedContextExtension $entry, string $path): array` using the same scope-key/value rules as core trusted entries.
- Confirmation and idempotency documents gain an `extensions` map only when `InvocationContext::allTrustedExtensions()` is non-empty.
- Existing no-extension confirmation baseline remains exactly `0057701f23d96b62d1a763c8dad01ae9a0412bf1783e72201d82949df0826e5a`.

- [ ] **Step 1: Write RED hash-binding tests**

Extend the regression tests to construct `TrustedContextExtension('filament/active_filters', ..., scopeKey: 'filters-A')` and prove:

```php
self::assertSame($legacyHash, (new ConfirmationScopeHasher())->fingerprint($legacyState));
self::assertNotSame(
    (new ConfirmationScopeHasher())->fingerprint($stateWithFiltersA),
    (new ConfirmationScopeHasher())->fingerprint($stateWithFiltersB),
);
self::assertSame(
    (new ConfirmationScopeHasher())->fingerprint($stateWithFiltersA),
    (new ConfirmationScopeHasher())->fingerprint($stateWithSameFiltersDifferentMetadata),
);
```

For idempotency intent, identical trusted extensions produce identical intent; changed extension scope changes intent; metadata imitation does not. Add an unrepresentable extension value without scope key and require static-safe `UnrepresentableConfirmationScope` / `UnrepresentableIdempotencyScope` behavior.

- [ ] **Step 2: Run focused tests and verify RED**

```bash
cd packages/laravel
vendor/bin/phpunit tests/Unit/RuntimeScopeCanonicalizerTest.php tests/Unit/ConfirmationScopeFingerprintRegressionTest.php tests/Unit/IdempotencyHashingTest.php
```

Expected: extension state does not yet affect fingerprints / extension identity API missing.

- [ ] **Step 3: Implement conditional extension hashing**

Add a shared identity helper in the canonicalizer so both core and extension entries use the same semantics. In each hasher:

```php
$extensions = [];
foreach ($state->context->allTrustedExtensions() as $entry) {
    $extensions[$entry->key] = $this->canonicalizer->trustedExtensionIdentity(
        $entry,
        'extensions.' . $entry->key,
    );
}

if ($extensions !== []) {
    $scope['extensions'] = $extensions; // or $document for idempotency
}
```

Do not add `extensions => []` for the empty case; that would change every existing hash.

- [ ] **Step 4: Re-run focused and full tests**

```bash
cd packages/laravel
vendor/bin/phpunit tests/Unit/RuntimeScopeCanonicalizerTest.php tests/Unit/ConfirmationScopeFingerprintRegressionTest.php tests/Unit/IdempotencyHashingTest.php
vendor/bin/phpunit
```

Expected: GREEN and exact legacy confirmation fingerprint unchanged.

- [ ] **Step 5: Commit**

```bash
git add packages/laravel/src/Runtime/Scope packages/laravel/src/Confirmation/ConfirmationScopeHasher.php packages/laravel/src/Idempotency/IdempotencyIntentHasher.php packages/laravel/tests/Unit/RuntimeScopeCanonicalizerTest.php packages/laravel/tests/Unit/ConfirmationScopeFingerprintRegressionTest.php packages/laravel/tests/Unit/IdempotencyHashingTest.php
git commit -m "feat(trust): bind trusted extensions to invocation intent"
```

---

### Task 3: Resolve Exact Applied Filament Filter State

**Files:**
- Create: `packages/laravel/src/Filament/Context/FilamentContextExposure.php`
- Create: `packages/laravel/src/Filament/Context/FilamentActiveFilterContextResolver.php`
- Create: `packages/laravel/src/Filament/Context/InvalidFilamentActiveFilterContext.php`
- Modify: `packages/laravel/tests/Fixtures/Filament/TestTableResource.php`
- Create: `packages/laravel/tests/Unit/FilamentActiveFilterContextResolverTest.php`

**Interfaces:**
- Produces: `FilamentContextExposure::activeFilters(): self`; exposure is a trusted adapter value, not invocation payload.
- Produces: `FilamentActiveFilterContextResolver::EXTENSION_KEY = 'filament/active_filters'`.
- Produces: `FilamentActiveFilterContextResolver::__construct(Page $page, ?RuntimeScopeCanonicalizer $canonicalizer = null)` and `resolve(): ResolvedTrustedValue`.
- Resolver provider is exactly `filament.active_filters`; reference is null.

- [ ] **Step 1: Add realistic test filters and RED resolver tests**

Add Filament table filters to `TestTableResource`, including at least a normal selectable/status filter and a filter whose state has nested values. Tests must prove the resolver reads the exact public applied state and returns a present empty snapshot when the visible configured filter set is empty in a dedicated fixture/table configuration.

Representative assertions:

```php
$resolved = (new FilamentActiveFilterContextResolver($page))->resolve();
self::assertSame('filament.active_filters', $resolved->provenance->provider);
self::assertNull($resolved->provenance->reference);
self::assertSame($page->getTableFilterState('status'), $resolved->value['status']);
self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $resolved->confirmationScopeKey);
```

Explicit resolution on `NonRecordPage` must fail closed with `InvalidFilamentActiveFilterContext` rather than return absence, because exposure requested filter authority and the exact page cannot supply it.

- [ ] **Step 2: Run resolver tests and verify RED**

```bash
cd packages/laravel
vendor/bin/phpunit tests/Unit/FilamentActiveFilterContextResolverTest.php
```

Expected: missing resolver/exposure classes.

- [ ] **Step 3: Implement resolver using only public applied-state APIs**

Require `Page instanceof Filament\Tables\Contracts\HasTable`; then use:

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

Do not call `getTableFilterFormState()`, read `tableFilters`/`tableDeferredFilters`, indicators, request state or SQL. Catch framework read failures and convert them to static-safe, non-chained adapter exceptions. Convert canonicalization failure to `unrepresentable_active_filter_state` without leaking state.

- [ ] **Step 4: Prove canonicalization and failure behavior**

Add tests for associative key-order stability, nested list-order significance, int/string distinction, nested false/zero/empty-string/null/empty-array preservation, unrepresentable custom state failure and no exception chaining.

Run:

```bash
cd packages/laravel
vendor/bin/phpunit tests/Unit/FilamentActiveFilterContextResolverTest.php
vendor/bin/phpunit tests/Unit/FilamentCurrentSelectionResolverTest.php tests/Unit/FilamentRecordContextResolverTest.php
```

Expected: GREEN; T-501/T-502 resolvers unchanged.

- [ ] **Step 5: Commit**

```bash
git add packages/laravel/src/Filament/Context packages/laravel/tests/Fixtures/Filament/TestTableResource.php packages/laravel/tests/Unit/FilamentActiveFilterContextResolverTest.php
git commit -m "feat(filament): resolve applied filter context"
```

---

### Task 4: Wire Explicit Exposure Through the Existing Filament Invocation Path and Prove Deferred Semantics

**Files:**
- Modify: `packages/laravel/src/Filament/Context/FilamentInvocationContextFactory.php`
- Modify: `packages/laravel/src/Filament/Invocation/FilamentActionGateway.php`
- Create: `packages/laravel/tests/Integration/FilamentActiveFilterPageIntegrationTest.php`
- Create: `packages/laravel/tests/Integration/FilamentActiveFilterGatewayIntegrationTest.php`

**Interfaces:**
- `FilamentInvocationContextFactory::forPage(..., array $metadata = [], ?FilamentContextExposure $contextExposure = null): InvocationContext` appends the optional exposure argument.
- `FilamentActionGateway::dispatch(..., array $metadata = [], ?FilamentContextExposure $contextExposure = null): ActionPipelineOutcome` appends the optional exposure argument.
- No-exposure calls must produce no trusted extensions and preserve T-502 behavior.

- [ ] **Step 1: Write RED factory/gateway tests**

Prove default no-exposure produces `allTrustedExtensions() === []`; explicit `FilamentContextExposure::activeFilters()` adds exactly `filament/active_filters`; metadata/input imitation does not create or alter the extension; explicit exposure on a non-table page fails before `ActionBus` application execution.

- [ ] **Step 2: Run integration tests and verify RED**

```bash
cd packages/laravel
vendor/bin/phpunit tests/Integration/FilamentActiveFilterGatewayIntegrationTest.php
```

Expected: factory/gateway do not yet accept exposure and no extension is produced.

- [ ] **Step 3: Implement minimal wiring**

Factory first resolves existing core context exactly as T-502 does. Only when `$contextExposure?->includesActiveFilters()` is true, resolve `FilamentActiveFilterContextResolver`, create one `TrustedContextExtension`, and add it through `InvocationContext::withTrustedExtension()` or constructor wiring. Gateway forwards only the typed exposure object.

- [ ] **Step 4: Prove real Filament deferred versus applied state**

Use the real `TestTablePage` lifecycle. With Filament deferred filters enabled:

```text
apply state A
→ resolve SurfaceRelay snapshot A
→ change pending filter form state to B without apply
→ SurfaceRelay still resolves A
→ call Filament applyTableFilters()
→ next SurfaceRelay invocation resolves B
```

Also configure a live-filter variant (`deferFilters(false)`) and prove changes appear as applied state immediately. Assert SurfaceRelay snapshot equals `getTableFilterState()` rather than `getTableFilterFormState()`.

Run:

```bash
cd packages/laravel
vendor/bin/phpunit tests/Integration/FilamentActiveFilterPageIntegrationTest.php tests/Integration/FilamentActiveFilterGatewayIntegrationTest.php
vendor/bin/phpunit tests/Integration/FilamentCurrentSelectionPageIntegrationTest.php tests/Integration/FilamentCurrentSelectionGatewayIntegrationTest.php
```

Expected: GREEN.

- [ ] **Step 5: Commit**

```bash
git add packages/laravel/src/Filament packages/laravel/tests/Integration/FilamentActiveFilterPageIntegrationTest.php packages/laravel/tests/Integration/FilamentActiveFilterGatewayIntegrationTest.php
git commit -m "feat(filament): expose trusted active filter context"
```

---

### Task 5: Integrate Filter Authority with Confirmation, Idempotency and Payload-Minimized Audit

**Files:**
- Modify: `packages/laravel/src/Audit/AuditTrustedContextEntry.php`
- Modify: `packages/laravel/src/Audit/AuditEventFactory.php`
- Modify: `packages/laravel/src/Audit/DatabaseAuditEventStore.php`
- Modify: `packages/laravel/tests/Unit/AuditEventFactoryTest.php`
- Modify: `packages/laravel/tests/Unit/DatabaseAuditEventStoreTest.php`
- Create: `packages/laravel/tests/Integration/FilamentActiveFilterTrustControlsIntegrationTest.php`

**Interfaces:**
- Evolve the audit manifest value so each item contains either one core `ContextRequirement` or one trusted extension key, plus provider.
- Preserve existing serialized core shape exactly: `{"requirement":"current_selection","provider":"filament.current_selection"}`.
- New extension shape: `{"extension":"filament/active_filters","provider":"filament.active_filters"}`.

- [ ] **Step 1: Write RED audit manifest tests**

Construct an outcome with a trusted filter extension containing sentinel secrets such as `SECRET_FILTER_VALUE` and `SECRET_SCOPE_KEY`. Require event/store output to contain only the fixed extension key/provider and not either secret.

- [ ] **Step 2: Run audit tests and verify RED**

```bash
cd packages/laravel
vendor/bin/phpunit tests/Unit/AuditEventFactoryTest.php tests/Unit/DatabaseAuditEventStoreTest.php
```

Expected: current manifest type can represent only core `ContextRequirement` entries.

- [ ] **Step 3: Implement disjoint audit manifest shape**

Evolve `AuditTrustedContextEntry` with named constructors (or equivalent invariant-safe constructor) so exactly one of requirement/extension is set. `AuditEventFactory` appends core entries in existing canonical order followed by trusted extensions in their exact-key order. `DatabaseAuditEventStore` serializes exactly one discriminator field plus provider.

Do not persist extension value, scopeKey, filter name list, canonical bytes or raw page/table state.

- [ ] **Step 4: Add trust-control integration RED/GREEN cases**

Use the existing confirmation/idempotency test stores and Filament page lifecycle to prove:

1. filter snapshot A creates scope/intent A;
2. pending deferred state B before Apply leaves scope/intent A;
3. applied B changes scope/intent;
4. an approved receipt for A cannot authorize B and the mismatch does not spend the otherwise-valid A receipt;
5. reusing the same idempotency key across A then applied B conflicts rather than replaying A output;
6. metadata/input imitation cannot reproduce extension authority;
7. audit persistence contains `filament/active_filters` / `filament.active_filters` only and no raw filter material.

Run:

```bash
cd packages/laravel
vendor/bin/phpunit tests/Integration/FilamentActiveFilterTrustControlsIntegrationTest.php
vendor/bin/phpunit tests/Integration/FilamentCurrentSelectionTrustControlsIntegrationTest.php tests/Integration/StructuredAuditPersistenceSecrecyIntegrationTest.php
```

Expected: GREEN and T-502/T-404 regressions unchanged.

- [ ] **Step 5: Commit**

```bash
git add packages/laravel/src/Audit packages/laravel/tests/Unit/AuditEventFactoryTest.php packages/laravel/tests/Unit/DatabaseAuditEventStoreTest.php packages/laravel/tests/Integration/FilamentActiveFilterTrustControlsIntegrationTest.php
git commit -m "feat(audit): record trusted extension provenance"
```

---

### Task 6: Boundary Regression, Full Verification, and Review Handoff

**Files:**
- Modify: `packages/laravel/tests/Unit/FilamentDependencyPolicyTest.php`
- Modify: `TASKS.md`
- Modify: `STATUS.md`
- Modify: `REVIEW_REQUEST.md`
- Modify: `docs/OPEN-QUESTIONS.md`
- Design/decision docs already created in this branch remain part of the final diff.

**Interfaces:**
- No new protocol schema or browser/runtime driver API.
- Final documentation records exact commit/run evidence rather than aspirational behavior.

- [ ] **Step 1: Add dependency/boundary regression assertions**

Prove Filament remains dev-only, `ContextRequirement` contains no `active_filters`, no `spec/0.1/**` file changes, and no Filament import enters Action Definition/browser/Livewire production code. Assert the new trusted-extension primitive itself has no Filament dependency.

- [ ] **Step 2: Run focused boundary tests**

```bash
cd packages/laravel
vendor/bin/phpunit tests/Unit/FilamentDependencyPolicyTest.php
```

Expected: GREEN after production work; no dependency drift.

- [ ] **Step 3: Run full PHP verification**

```bash
cd packages/laravel
composer validate --strict
vendor/bin/phpunit
find src tests -name '*.php' -print0 | xargs -0 -n1 php -l
```

Expected: all tests/lint GREEN.

- [ ] **Step 4: Run contract and browser regressions**

From repository root:

```bash
python scripts/validate.py
cd packages/browser-runtime
npm ci
npm run typecheck
npm test
```

Expected: contract validator GREEN and existing TypeScript/Vitest suite GREEN without browser production changes.

- [ ] **Step 5: Verify the GitHub Actions matrix on the exact head**

Require all seven repository jobs GREEN:

```text
php-lint
contract
browser
php-tests PHP 8.3 / Illuminate 12 / Testbench 10
php-tests PHP 8.3 / Illuminate 13 / Testbench 11
php-tests PHP 8.4 / Illuminate 12 / Testbench 10
php-tests PHP 8.4 / Illuminate 13 / Testbench 11
```

Record exact workflow run ID and exact head SHA.

- [ ] **Step 6: Review the full diff for architecture/security drift**

Verify explicitly:

```text
spec/0.1/** unchanged
ContextRequirement unchanged
packages/browser-runtime/src/** unchanged
packages/laravel/src/Livewire/** unchanged
no raw tableFilters/tableDeferredFilters authority read
no getTableFilterFormState() authority read
no request/query/route filter fallback
no Filament RuntimeBinding driver
no filter values/scope keys in audit persistence
no-extension confirmation/idempotency regression hashes unchanged
```

- [ ] **Step 7: Update task/review documentation only after verification**

Mark T-503 `DONE / REVIEWED` only if required verification passes. Update `STATUS.md` with changed files, exact test count/assertions, workflow run/head, known limitations and T-504 as next task. Update `REVIEW_REQUEST.md` with the D-050 boundary, applied-vs-deferred evidence, trust-control evidence and prohibited-path checklist. Resolve the Filament active-filter open question in `docs/OPEN-QUESTIONS.md` while leaving page-rebinding questions that T-503 does not answer.

- [ ] **Step 8: Commit review-prep documentation**

```bash
git add TASKS.md STATUS.md REVIEW_REQUEST.md docs/OPEN-QUESTIONS.md packages/laravel/tests/Unit/FilamentDependencyPolicyTest.php
git commit -m "docs(review): prepare T-503 active filter handoff"
```

Do not merge, tag, publish, or begin T-504 without a separate explicit user gate.
