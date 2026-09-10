# T-502 Filament Current-Selection Trusted Context Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add bounded, caller-unforgeable Filament `current_selection` trusted context that snapshots the exact effective selected Eloquent records reported by Filament's public table contract and binds the existing confirmation, idempotency, audit, and ActionBus path.

**Architecture:** Reuse the T-501 exact-Page production gateway. Extract one shared typed Eloquent record-identity primitive so `current_record` and `current_selection` use identical identity rules without changing the existing T-501 scope hash. `FilamentCurrentSelectionResolver` reads only `HasTable::getSelectedTableRecords()`, enforces a trusted maximum, canonicalizes the effective selection as an unordered identity set, and feeds the existing `FilamentTrustedContextComposer → FilamentInvocationContextFactory → FilamentActionGateway → ActionBus` path.

**Tech Stack:** PHP 8.3/8.4, Laravel/Illuminate 12/13, Livewire 4.4+, Filament 5.x, Orchestra Testbench 10/11, PHPUnit 11, SQLite focused tests, MySQL 8.4 CI service.

**Spec:** `docs/superpowers/specs/2026-09-10-filament-current-selection-context-design.md`

## Global Constraints

- Implementation branch: `feat/filament-current-selection-context`, forked from `main@ae77cbf26cbefcca478d070765386628343bec52`.
- `spec/0.1/**` remains unchanged.
- Do not add a `filament` RuntimeBinding driver, alternate gateway, agent-only endpoint, route/request discovery, or ActionBus bypass.
- Exact active `Filament\Resources\Pages\Page` remains the UI authority source.
- `current_selection` comes only from public `Filament\Tables\Contracts\HasTable::getSelectedTableRecords()` effective selection; raw Livewire selection properties are never read by production SurfaceRelay code.
- Empty authority means the **effective public selected-record result is empty**, not that `$selectedTableRecords` happens to be empty.
- Selection is an unordered exact identity set. Click order, display order, query order, filter descriptor, search, sort, pagination, grouping, and tab state are excluded from T-502 identity.
- `ResolvedTrustedValue::value` is a bounded `list<Model>` containing exact Filament-resolved Eloquent instances in deterministic identity order.
- Selection membership/identity is frozen for one invocation; Eloquent object attributes remain normal mutable application state.
- Default trusted maximum is exactly `500`; constructor overrides must be positive integers and are trusted adapter configuration, never per-invocation caller data.
- Over-limit selection fails closed after observing at most `max + 1` effective records; never silently truncate.
- Non-Eloquent selected values, unsaved/invalid-key models, duplicate exact identities, and duplicate-enabled `BelongsToMany` row semantics fail closed for non-empty authority.
- Tenant remains a separate trusted dimension; never fold tenant identity into record or selection scope hashes.
- T-501 `current_record` scope hash behavior must remain byte-for-byte compatible.
- T-503 active filters, T-504 confirmation UI bridge, and T-505 order-operations demo remain out of scope.
- Existing PHP 8.3/8.4 × Illuminate 12/13 × Testbench 10/11 + Filament 5 + Livewire 4.4 matrix remains intact.

---

## File Structure

### New production files

- `packages/laravel/src/Filament/Context/FilamentRecordIdentity.php` — shared exact persisted Eloquent identity and canonical encoding.
- `packages/laravel/src/Filament/Context/InvalidFilamentRecordIdentity.php` — internal static-safe identity extraction failure.
- `packages/laravel/src/Filament/Context/FilamentCurrentSelectionResolver.php` — exact table Page → bounded effective selection `ResolvedTrustedValue|null`.
- `packages/laravel/src/Filament/Context/InvalidFilamentCurrentSelection.php` — static-safe selection configuration/resolution failures.

### Modified production files

- `packages/laravel/src/Filament/Context/FilamentRecordContextResolver.php` — delegate identity extraction while preserving T-501 output exactly.
- `packages/laravel/src/Filament/Context/FilamentTrustedContextComposer.php` — append optional `current_selection` after `current_record`.
- `packages/laravel/src/Filament/Context/FilamentInvocationContextFactory.php` — construct selection resolver for the exact Page with trusted max configuration.
- `packages/laravel/src/Filament/Invocation/FilamentActionGateway.php` — accept constructor-level trusted max selection configuration; keep dispatch inputs unchanged.

### New/modified tests and fixtures

- Create: `packages/laravel/tests/Unit/FilamentRecordIdentityTest.php`
- Modify: `packages/laravel/tests/Unit/FilamentRecordContextResolverTest.php`
- Create: `packages/laravel/tests/Unit/FilamentCurrentSelectionResolverTest.php`
- Modify: `packages/laravel/tests/Unit/FilamentTrustedContextComposerTest.php`
- Create: `packages/laravel/tests/Fixtures/Filament/TestTableResource.php`
- Create: `packages/laravel/tests/Fixtures/Filament/TestTablePage.php`
- Create: `packages/laravel/tests/Integration/FilamentCurrentSelectionPageIntegrationTest.php`
- Create: `packages/laravel/tests/Integration/FilamentCurrentSelectionTrustControlsIntegrationTest.php`
- Modify: `packages/laravel/tests/Integration/FilamentActionGatewayIntegrationTest.php`
- Review prep only: `STATUS.md`, `REVIEW_REQUEST.md`, and exactly the T-502 marker in `TASKS.md`.

---

### Task 1: Extract Shared Exact Record Identity Without Changing T-501 Scope

**Files:**
- Create: `packages/laravel/src/Filament/Context/FilamentRecordIdentity.php`
- Create: `packages/laravel/src/Filament/Context/InvalidFilamentRecordIdentity.php`
- Modify: `packages/laravel/src/Filament/Context/FilamentRecordContextResolver.php`
- Create: `packages/laravel/tests/Unit/FilamentRecordIdentityTest.php`
- Modify: `packages/laravel/tests/Unit/FilamentRecordContextResolverTest.php`

**Interfaces:**
- Consumes: `Illuminate\Database\Eloquent\Model`, `RuntimeScopeCanonicalizer`.
- Produces: `FilamentRecordIdentity::fromModel(Model): FilamentRecordIdentity`, `payload(): array{modelClass:string,keyName:string,keyValue:int|string}`, `encode(RuntimeScopeCanonicalizer,string): string`.
- Preserves: `FilamentRecordContextResolver::resolve(): ?ResolvedTrustedValue` and domain `"surfacerelay.filament.current_record.v1\n"`.

- [ ] **Step 1: Pin current T-501 scope behavior before refactoring**

Add a regression test for persisted `TestRecord` ID `41`:

```php
public function test_current_record_scope_hash_is_byte_compatible_across_identity_refactor(): void
{
    $record = new TestRecord();
    $record->setRawAttributes(['id' => 41, 'name' => 'ignored']);
    $record->exists = true;

    $page = new TestRecordPage();
    $page->record = $record;

    $resolved = (new FilamentRecordContextResolver($page))->resolve();

    self::assertNotNull($resolved);
    self::assertSame(
        '5436c4020c370bb2f495f0954332bd892a3e5f89950db287c808f292e32bfa96',
        $resolved->confirmationScopeKey,
    );
}
```

- [ ] **Step 2: Verify the pin against pre-refactor production**

```bash
cd packages/laravel
vendor/bin/phpunit tests/Unit/FilamentRecordContextResolverTest.php --filter=byte_compatible
```

Expected: PASS. A mismatch means the regression literal is wrong; inspect the current canonical encoding before touching production code.

- [ ] **Step 3: Write RED shared-identity tests**

Create `FilamentRecordIdentityTest.php` and require exact payload semantics:

```php
$identity = FilamentRecordIdentity::fromModel($record);

self::assertSame([
    'modelClass' => TestRecord::class,
    'keyName' => 'id',
    'keyValue' => 41,
], $identity->payload());

self::assertSame(
    (new RuntimeScopeCanonicalizer())->encode($identity->payload(), 'test.identity'),
    $identity->encode(new RuntimeScopeCanonicalizer(), 'test.identity'),
);
```

Also cover integer `0`, string `'41'`, unsaved model, null/empty/array/object key, empty key name, and a model whose `getKey()` throws `RuntimeException('SECRET-IDENTITY')`. Invalid cases must expose only static `InvalidFilamentRecordIdentity` text and `getPrevious() === null`.

- [ ] **Step 4: Run RED**

```bash
vendor/bin/phpunit tests/Unit/FilamentRecordIdentityTest.php
```

Expected: FAIL because `FilamentRecordIdentity` does not exist.

- [ ] **Step 5: Implement the shared primitive**

`InvalidFilamentRecordIdentity.php`:

```php
<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Filament\Context;

final class InvalidFilamentRecordIdentity extends \RuntimeException
{
    public static function invalid(): self
    {
        return new self('Filament record identity is invalid.');
    }
}
```

`FilamentRecordIdentity.php`:

```php
<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Filament\Context;

use Illuminate\Database\Eloquent\Model;
use SurfaceRelay\Laravel\Runtime\Scope\RuntimeScopeCanonicalizer;
use SurfaceRelay\Laravel\Runtime\Scope\UnrepresentableRuntimeScope;

final readonly class FilamentRecordIdentity
{
    private function __construct(
        public string $modelClass,
        public string $keyName,
        public int|string $keyValue,
    ) {}

    public static function fromModel(Model $record): self
    {
        if (! $record->exists) {
            throw InvalidFilamentRecordIdentity::invalid();
        }

        try {
            $keyName = $record->getKeyName();
            $keyValue = $record->getKey();
        } catch (\Throwable) {
            throw InvalidFilamentRecordIdentity::invalid();
        }

        if (
            ! is_string($keyName)
            || $keyName === ''
            || (! is_int($keyValue) && ! is_string($keyValue))
            || $keyValue === ''
        ) {
            throw InvalidFilamentRecordIdentity::invalid();
        }

        return new self($record::class, $keyName, $keyValue);
    }

    /** @return array{modelClass:string,keyName:string,keyValue:int|string} */
    public function payload(): array
    {
        return [
            'modelClass' => $this->modelClass,
            'keyName' => $this->keyName,
            'keyValue' => $this->keyValue,
        ];
    }

    public function encode(RuntimeScopeCanonicalizer $canonicalizer, string $path): string
    {
        try {
            return $canonicalizer->encode($this->payload(), $path);
        } catch (UnrepresentableRuntimeScope) {
            throw InvalidFilamentRecordIdentity::invalid();
        }
    }
}
```

- [ ] **Step 6: Refactor current-record resolver through the shared primitive**

Keep the existing domain constant and replace only direct key extraction/canonicalization:

```php
try {
    $identity = FilamentRecordIdentity::fromModel($record);
    $encodedIdentity = $identity->encode(
        $this->canonicalizer,
        'filament.current_record.identity',
    );
} catch (InvalidFilamentRecordIdentity) {
    throw InvalidFilamentRecordContext::invalidRecordIdentity();
}
```

Do not alter `getRecord()` handling, exact model value, provenance, or domain string.

- [ ] **Step 7: Run focused GREEN**

```bash
vendor/bin/phpunit \
  tests/Unit/FilamentRecordIdentityTest.php \
  tests/Unit/FilamentRecordContextResolverTest.php \
  tests/Unit/FilamentRecordIdentityFailureTest.php
```

Expected: PASS and the pinned T-501 hash remains exact.

- [ ] **Step 8: Commit**

```bash
git add packages/laravel/src/Filament/Context packages/laravel/tests/Unit/FilamentRecordIdentityTest.php packages/laravel/tests/Unit/FilamentRecordContextResolverTest.php
git commit -m "refactor(filament): share exact record identity"
```

---

### Task 2: Resolve a Bounded Effective Filament Selection

**Files:**
- Create: `packages/laravel/src/Filament/Context/InvalidFilamentCurrentSelection.php`
- Create: `packages/laravel/src/Filament/Context/FilamentCurrentSelectionResolver.php`
- Create: `packages/laravel/tests/Fixtures/Filament/TestTableResource.php`
- Create: `packages/laravel/tests/Fixtures/Filament/TestTablePage.php`
- Create: `packages/laravel/tests/Unit/FilamentCurrentSelectionResolverTest.php`

**Interfaces:**
- Consumes: exact `Page`, `HasTable::getSelectedTableRecords(true, ?int)`, public `getTable()`, `Table::getRelationship()`, `Table::allowsDuplicates()`, shared `FilamentRecordIdentity`.
- Produces: `FilamentCurrentSelectionResolver::resolve(): ?ResolvedTrustedValue` with provider `filament.current_selection`, canonical `list<Model>`, and 64-char SHA-256 scope key.
- Configuration: `DEFAULT_MAX_SELECTION_RECORDS = 500`, internal max chunk size `100`.

- [ ] **Step 1: Create the real table fixture used by focused and integration tests**

`TestTableResource.php`:

```php
<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\Filament;

use Filament\Resources\Resource;
use Filament\Tables\Table;

final class TestTableResource extends Resource
{
    protected static ?string $model = TestRecord::class;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([])
            ->checkIfRecordIsSelectableUsing(
                static fn (TestRecord $record): bool => $record->name !== 'blocked',
            );
    }

    public static function getPages(): array
    {
        return [
            'index' => TestTablePage::route('/'),
        ];
    }
}
```

`TestTablePage.php` uses Filament's real resource-list lifecycle:

```php
<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\Filament;

use Filament\Resources\Pages\ListRecords;

class TestTablePage extends ListRecords
{
    protected static string $resource = TestTableResource::class;
}
```

For direct Testbench construction, initialize table state only through the public lifecycle hook:

```php
$page = new TestTablePage();
$page->bootedInteractsWithTable();
```

Do not assign protected `$table` through reflection. `ListRecords::makeTable()` invokes the resource's public `configureTable($table)` path, so `TestTableResource::table()` is part of the real Filament lifecycle.

- [ ] **Step 2: Write RED configuration and absence tests**

Use a Testbench test case with SQLite `filament_test_records(id,name)` schema. Require:

```php
self::assertSame(500, FilamentCurrentSelectionResolver::DEFAULT_MAX_SELECTION_RECORDS);
self::assertNull((new FilamentCurrentSelectionResolver(new NonRecordPage()))->resolve());
```

For a booted `TestTablePage` with no effective selection, require `resolve() === null`. Constructing with max `0` or `-1` must throw static `invalidConfiguration()`.

- [ ] **Step 3: Write RED set/identity/boundary tests**

Persist A/B/C. Use fresh page instances per case to avoid Filament's selected-record cache. In test setup it is acceptable to simulate browser-owned Filament state by assigning the framework's public properties; assertions must compare SurfaceRelay to public `getSelectedTableRecords()` behavior.

Require:

- effective A/B → exact model instances returned in deterministic identity order;
- source order B/A → same scope key and same canonical output order;
- A/C → different scope key;
- exactly max records succeeds;
- max+1 fails closed;
- duplicate effective identity fails;
- non-Eloquent returned value fails;
- unsaved/invalid-key record fails;
- `getSelectedTableRecords()` throwing `RuntimeException('SECRET-SELECTION')` is sanitized.

For duplicate/non-Eloquent/throwing cases use test-local subclasses of `TestTablePage` overriding only public `getSelectedTableRecords()`; they still inherit the complete real `HasTable` contract from `ListRecords`.

- [ ] **Step 4: Run RED**

```bash
cd packages/laravel
vendor/bin/phpunit tests/Unit/FilamentCurrentSelectionResolverTest.php
```

Expected: FAIL because resolver/failure types do not exist.

- [ ] **Step 5: Add static-safe selection failure type**

`InvalidFilamentCurrentSelection.php` exposes exactly:

```php
invalidConfiguration() // "Filament current selection configuration is invalid."
resolutionFailed()     // "Filament current selection resolution failed."
limitExceeded()        // "Filament current selection exceeds the configured limit."
unsupportedValue()     // "Filament current selection contains an unsupported value."
invalidRecordIdentity()// "Filament current selection record identity is invalid."
duplicateIdentity()    // "Filament current selection contains a duplicate record identity."
ambiguousDuplicateRows() // "Filament current selection uses ambiguous duplicate-row semantics."
```

All return unchained exceptions.

- [ ] **Step 6: Implement bounded public-contract resolver**

Core constructor/constants:

```php
private const string IDENTITY_DOMAIN = "surfacerelay.filament.current_selection.v1\n";
private const int MAX_CHUNK_SIZE = 100;
public const int DEFAULT_MAX_SELECTION_RECORDS = 500;

public function __construct(
    private readonly Page $page,
    private readonly int $maxSelectionRecords = self::DEFAULT_MAX_SELECTION_RECORDS,
    ?RuntimeScopeCanonicalizer $canonicalizer = null,
) {
    if ($maxSelectionRecords < 1) {
        throw InvalidFilamentCurrentSelection::invalidConfiguration();
    }

    $this->canonicalizer = $canonicalizer ?? new RuntimeScopeCanonicalizer();
}
```

Resolution starts only from the public table contract:

```php
if (! $this->page instanceof HasTable) {
    return null;
}

$chunkSize = min(self::MAX_CHUNK_SIZE, $this->maxSelectionRecords + 1);

try {
    $selected = $this->page->getSelectedTableRecords(true, $chunkSize);
} catch (\Throwable) {
    throw InvalidFilamentCurrentSelection::resolutionFailed();
}
```

Iterate no more than `max + 1`, validate each value as persisted Eloquent, derive encoded shared identity, reject duplicate encoded identity, and store `encodedIdentity => exactModel`.

After iteration:

```php
if ($recordsByIdentity === []) {
    return null;
}
```

Before issuing non-empty authority, reject ambiguous duplicate-enabled relationship rows through public APIs only:

```php
try {
    $table = $this->page->getTable();
    if ($table->getRelationship() instanceof BelongsToMany && $table->allowsDuplicates()) {
        throw InvalidFilamentCurrentSelection::ambiguousDuplicateRows();
    }
} catch (InvalidFilamentCurrentSelection $e) {
    throw $e;
} catch (\Throwable) {
    throw InvalidFilamentCurrentSelection::resolutionFailed();
}
```

Canonical return:

```php
ksort($recordsByIdentity, SORT_STRING);
$identitySet = array_keys($recordsByIdentity);

try {
    $encodedSet = $this->canonicalizer->encode(
        $identitySet,
        'filament.current_selection.identity_set',
    );
} catch (UnrepresentableRuntimeScope) {
    throw InvalidFilamentCurrentSelection::invalidRecordIdentity();
}

return new ResolvedTrustedValue(
    value: array_values($recordsByIdentity),
    provenance: new ContextProvenance('filament.current_selection'),
    confirmationScopeKey: hash('sha256', self::IDENTITY_DOMAIN . $encodedSet),
);
```

Production file must contain no references to `$selectedTableRecords`, `$deselectedTableRecords`, `$isTrackingDeselectedTableRecords`, `request()`, route selection IDs, `Livewire\invade()`, or reflection.

- [ ] **Step 7: Run focused GREEN**

```bash
vendor/bin/phpunit tests/Unit/FilamentCurrentSelectionResolverTest.php
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add packages/laravel/src/Filament/Context/FilamentCurrentSelectionResolver.php packages/laravel/src/Filament/Context/InvalidFilamentCurrentSelection.php packages/laravel/tests/Fixtures/Filament/TestTableResource.php packages/laravel/tests/Fixtures/Filament/TestTablePage.php packages/laravel/tests/Unit/FilamentCurrentSelectionResolverTest.php
git commit -m "feat(filament): resolve bounded current selection"
```

---

### Task 3: Wire Current Selection Through Existing Filament Invocation

**Files:**
- Modify: `packages/laravel/src/Filament/Context/FilamentTrustedContextComposer.php`
- Modify: `packages/laravel/src/Filament/Context/FilamentInvocationContextFactory.php`
- Modify: `packages/laravel/src/Filament/Invocation/FilamentActionGateway.php`
- Modify: `packages/laravel/tests/Unit/FilamentTrustedContextComposerTest.php`
- Modify: `packages/laravel/tests/Integration/FilamentActionGatewayIntegrationTest.php`

**Interfaces:**
- Keep old composer construction valid by making selection resolver optional.
- Emit `authenticated_actor → tenant → current_record → current_selection` when all are present.
- Keep `FilamentActionGateway::dispatch(...)` parameters unchanged; no caller selection IDs or limits enter dispatch.

- [ ] **Step 1: Write RED composer tests**

Require record-only compatibility and new ordering:

```php
self::assertSame([
    ContextRequirement::AuthenticatedActor,
    ContextRequirement::Tenant,
    ContextRequirement::CurrentRecord,
    ContextRequirement::CurrentSelection,
], array_map(
    static fn (TrustedContextEntry $entry): ContextRequirement => $entry->requirement,
    $composer->resolve(),
));
```

Also prove selection may exist without current record and old two-argument composer construction still produces T-501 behavior.

- [ ] **Step 2: Run composer RED**

```bash
vendor/bin/phpunit tests/Unit/FilamentTrustedContextComposerTest.php
```

Expected: new selection assertions FAIL.

- [ ] **Step 3: Extend composer**

Constructor:

```php
public function __construct(
    private TrustedContextComposer $baseComposer,
    private FilamentRecordContextResolver $recordResolver,
    private ?FilamentCurrentSelectionResolver $selectionResolver = null,
) {}
```

After record resolution, append a `TrustedContextEntry(ContextRequirement::CurrentSelection, ...)` only when selection resolver returns non-null.

- [ ] **Step 4: Write RED gateway E2E**

Call production `FilamentActionGateway::dispatch(...)` against a booted `TestTablePage`. Executor must receive exact selected models from:

```php
$call->context->require(ContextRequirement::CurrentSelection)
```

Provide fake caller input/metadata `[999]` and prove they do not replace the trusted selection.

- [ ] **Step 5: Extend factory/gateway with constructor-only trusted limit**

Factory constructor:

```php
public function __construct(
    private TrustedContextComposer $baseComposer,
    private int $maxSelectionRecords = FilamentCurrentSelectionResolver::DEFAULT_MAX_SELECTION_RECORDS,
) {
    if ($maxSelectionRecords < 1) {
        throw InvalidFilamentCurrentSelection::invalidConfiguration();
    }
}
```

`forPage()` constructs both exact-page resolvers and passes them to one `FilamentTrustedContextComposer`.

Gateway constructor:

```php
public function __construct(
    private ActionBus $bus,
    TrustedContextComposer $baseComposer,
    int $maxSelectionRecords = FilamentCurrentSelectionResolver::DEFAULT_MAX_SELECTION_RECORDS,
) {
    $this->contextFactory = new FilamentInvocationContextFactory(
        $baseComposer,
        $maxSelectionRecords,
    );
}
```

Do not change `dispatch()` signature.

- [ ] **Step 6: Run focused GREEN**

```bash
vendor/bin/phpunit \
  tests/Unit/FilamentTrustedContextComposerTest.php \
  tests/Integration/FilamentActionGatewayIntegrationTest.php
```

Expected: PASS, including all pre-existing T-501 gateway cases.

- [ ] **Step 7: Commit**

```bash
git add packages/laravel/src/Filament/Context/FilamentTrustedContextComposer.php packages/laravel/src/Filament/Context/FilamentInvocationContextFactory.php packages/laravel/src/Filament/Invocation/FilamentActionGateway.php packages/laravel/tests/Unit/FilamentTrustedContextComposerTest.php packages/laravel/tests/Integration/FilamentActionGatewayIntegrationTest.php
git commit -m "feat(filament): wire trusted current selection"
```

---

### Task 4: Prove Real Filament 5 Effective Selection Semantics

**Files:**
- Create: `packages/laravel/tests/Integration/FilamentCurrentSelectionPageIntegrationTest.php`
- Reuse: `TestTableResource`, `TestTablePage`, `TestRecord`.

**Interfaces:**
- Uses real `ListRecords`, `HasTable`, `InteractsWithTable`, resource query/configuration, and public `getSelectedTableRecords()`.
- Test code may mutate Filament public selection properties to simulate browser state; production adapter may not read them directly.

- [ ] **Step 1: Build fresh booted page helper**

In the Testbench integration test, create SQLite schema and helper:

```php
private function page(): TestTablePage
{
    $page = new TestTablePage();
    $page->bootedInteractsWithTable();

    return $page;
}
```

Use a fresh page per test because Filament caches selected records.

- [ ] **Step 2: Prove explicit selection matches Filament public result**

Persist A/B/C, simulate A/B framework state, then:

```php
$filamentRecords = $page->getSelectedTableRecords(true, 100)->values()->all();
$resolved = (new FilamentCurrentSelectionResolver($page))->resolve();

self::assertNotNull($resolved);
self::assertEqualsCanonicalizing($filamentRecords, $resolved->value);
```

The assertion source is Filament's public result, not raw IDs.

- [ ] **Step 3: Prove raw-empty select-all is still non-empty authority**

On a fresh page:

```php
$page->isTrackingDeselectedTableRecords = true;
$page->selectedTableRecords = [];
$page->deselectedTableRecords = [(string) $recordB->getKey()];
```

Assert raw selected IDs are empty, while public effective selection and SurfaceRelay both resolve A/C.

- [ ] **Step 4: Prove selectability filtering**

Persist one `name='blocked'` record and include it in framework selection state. Assert Filament public selected records exclude it and SurfaceRelay resolves the same effective set.

- [ ] **Step 5: Prove bounded lazy decision window**

Persist at least five selectable rows, simulate select-all, create resolver with max `2`, and attach a DB listener. `getSelectedTableRecords(true, 3)` uses Filament's lazy query path for query-backed tables; resolver must throw on the third effective record and must not require a full unbounded selection materialization by SurfaceRelay.

Assert the resolver outcome and query evidence are compatible with a first decision window of at most `3` rows. Do not assert zero queries.

- [ ] **Step 6: Run real Filament GREEN**

```bash
vendor/bin/phpunit tests/Integration/FilamentCurrentSelectionPageIntegrationTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add packages/laravel/tests/Integration/FilamentCurrentSelectionPageIntegrationTest.php
git commit -m "test(filament): prove effective table selection"
```

---

### Task 5: Bind Selection to Required Context, Confirmation, Idempotency, and Audit

**Files:**
- Create: `packages/laravel/tests/Integration/FilamentCurrentSelectionTrustControlsIntegrationTest.php`
- Reuse generic trust-control production classes unchanged unless a focused failing test proves a generic defect.

**Interfaces:**
- Consumes: `FilamentActionGateway`, `ContextRequirement::CurrentSelection`, D-044 confirmation, D-045 idempotency, D-047 audit.
- Produces: evidence that existing generic controls bind the new selection scope correctly.

- [ ] **Step 1: Write required-context and production-path tests**

ActionDefinition requires `current_selection`.

- Effective empty selection: halt at required context, executor calls `0`.
- Non-empty A/B: executor receives exact selected models through production gateway.
- Caller input/metadata `[999]` cannot satisfy empty selection or replace A/B.

- [ ] **Step 2: Prove order-independent trust identity**

For effective A/B vs B/A, assert equal selection scope key, confirmation scope hash, and idempotency intent fingerprint.

For A/B vs A/C, assert different values.

For same A/B under tenant A vs tenant B, selection's own scope key stays equal while overall confirmation/idempotency scope differs.

- [ ] **Step 3: Prove wrong-selection receipt isolation without spending valid receipt**

Issue and approve a receipt for A/B. Attempt it against current A/C and assert mismatch before execution. Retry the same receipt against A/B and prove success, demonstrating the wrong-selection mismatch did not consume the A/B receipt.

Use public confirmation service/store APIs only.

- [ ] **Step 4: Prove audit minimization**

Completed and halted invocations may persist only:

```php
[
    'requirement' => 'current_selection',
    'provider' => 'filament.current_selection',
]
```

Audit representation must not contain selected IDs, model class, business attributes, selection count, selection scope key, filter state, route/query state, or caller spoof values.

- [ ] **Step 5: Run focused GREEN**

```bash
vendor/bin/phpunit tests/Integration/FilamentCurrentSelectionTrustControlsIntegrationTest.php
```

Expected: PASS without generic confirmation/idempotency/audit production changes.

- [ ] **Step 6: Commit**

```bash
git add packages/laravel/tests/Integration/FilamentCurrentSelectionTrustControlsIntegrationTest.php
git commit -m "test(filament): bind selection trust controls"
```

---

### Task 6: Full Regression, Scope Audit, Review Prep, and External Review Gate

**Files:**
- Modify: `STATUS.md`
- Modify: `REVIEW_REQUEST.md`
- Modify: `TASKS.md` only at T-502 marker.

**Interfaces:**
- Produces stable exact-head PR review candidate; does not merge.

- [ ] **Step 1: Run focused Filament suite**

```bash
cd packages/laravel
vendor/bin/phpunit \
  tests/Unit/FilamentRecordIdentityTest.php \
  tests/Unit/FilamentRecordContextResolverTest.php \
  tests/Unit/FilamentCurrentSelectionResolverTest.php \
  tests/Unit/FilamentTrustedContextComposerTest.php \
  tests/Integration/FilamentRecordPageIntegrationTest.php \
  tests/Integration/FilamentActionGatewayIntegrationTest.php \
  tests/Integration/FilamentCurrentSelectionPageIntegrationTest.php \
  tests/Integration/FilamentCurrentSelectionTrustControlsIntegrationTest.php
```

Expected: PASS.

- [ ] **Step 2: Run full Laravel regression**

```bash
vendor/bin/phpunit
composer validate --strict
```

Record fresh test/assertion counts; do not reuse T-501's `482 / 2533` count.

- [ ] **Step 3: Run browser and contract isolation checks**

```bash
cd ../browser-runtime
npm test
npm run typecheck
cd ../..
python scripts/validate.py
```

Expected: browser and contract green.

- [ ] **Step 4: Scope audit exact base → head**

Against `ae77cbf26cbefcca478d070765386628343bec52`, verify:

- no `spec/0.1/**` changes;
- no `packages/browser-runtime/src/**` production changes;
- no `packages/laravel/src/Livewire/**` changes;
- no new RuntimeBinding driver;
- new selection production code contains no raw Filament selection property reads, request/route selection lookup, `Livewire\invade()`, or reflection.

- [ ] **Step 5: Update evidence records only after verification**

`STATUS.md`: implementation state first `DONE / REVIEW PENDING`, then `DONE / REVIEWED; MERGE PENDING` only after external review closes.

`REVIEW_REQUEST.md` records exact base/head, all RED/GREEN checkpoints, fresh test counts, D-049, bounded effective selection, T-501 hash compatibility, trust-control proofs, and external findings/fixes.

After review closure, change exactly the T-502 line in `TASKS.md` to `DONE / REVIEWED`; preserve historical board content.

- [ ] **Step 6: Commit review-prep docs and require exact-head 7-job CI**

```bash
git add STATUS.md REVIEW_REQUEST.md TASKS.md
git commit -m "docs(review): prepare T-502 review record"
```

Push and require all 7 validation jobs green on that exact SHA.

- [ ] **Step 7: Open PR and request CodeRabbit**

PR title:

```text
feat(filament): bind trusted current selection context
```

Review focus:

```text
Challenge effective-selection authority, raw Livewire-state dependence,
select-all/deselection semantics, bounded materialization, duplicate/pivot
ambiguity, T-501 hash compatibility, caller spoofing, confirmation/idempotency
selection binding, audit leakage, and accidental second execution paths.
```

Freeze branch writes while review evaluates the exact head.

- [ ] **Step 8: Handle actionable review findings TDD-first**

For each valid finding: focused RED → minimal fix → focused/full GREEN → exact evidence reply → resolve after technical closure. Generic docstring/style metrics remain non-blocking unless repository CI itself requires them.

- [ ] **Step 9: Final review-head verification**

Before reporting merge-ready require:

- PR open and mergeable;
- exact final head known;
- exact-head workflow 7/7 green;
- fresh PHP test/assertion count recorded;
- browser/contract green;
- 0 unresolved actionable review threads;
- branch 0 behind `main` or explicitly revalidated if main moved;
- `spec/0.1/**` unchanged.

Do **not** merge. PR merge is a separate explicit user gate.
