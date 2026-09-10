# T-502 Filament Current-Selection Trusted Context Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add bounded, caller-unforgeable Filament `current_selection` trusted context that snapshots the exact effective selected Eloquent records reported by Filament's public table contract and binds the existing confirmation, idempotency, audit, and ActionBus path.

**Architecture:** Reuse the T-501 exact-Page production gateway. Extract one shared typed Eloquent record-identity primitive so `current_record` and `current_selection` use identical identity rules without changing the existing T-501 scope hash. Add `FilamentCurrentSelectionResolver` to read only `HasTable::getSelectedTableRecords()`, enforce a trusted maximum, canonicalize the effective selection as an unordered identity set, and feed the existing `FilamentTrustedContextComposer → FilamentInvocationContextFactory → FilamentActionGateway → ActionBus` path.

**Tech Stack:** PHP 8.3/8.4, Laravel/Illuminate 12/13, Livewire 4.4+, Filament 5.x, Orchestra Testbench 10/11, PHPUnit 11, SQLite focused integration tests, MySQL 8.4 CI service.

**Spec:** `docs/superpowers/specs/2026-09-10-filament-current-selection-context-design.md`

## Global Constraints

- Implementation branch: `feat/filament-current-selection-context`, forked from `main@ae77cbf26cbefcca478d070765386628343bec52`.
- `spec/0.1/**` remains unchanged.
- Do not add a `filament` RuntimeBinding driver, alternate gateway, agent-only endpoint, route/request discovery, or ActionBus bypass.
- Exact active `Filament\Resources\Pages\Page` remains the UI authority source.
- `current_selection` comes only from the public `Filament\Tables\Contracts\HasTable::getSelectedTableRecords()` effective selection; raw Livewire selection properties are not read by production code.
- Empty authority means the **effective public selected-record result is empty**, not that `$selectedTableRecords` happens to be empty.
- Selection is an unordered exact identity set. Click order, table display order, query order, filter descriptor, search, sort, pagination, grouping, and tab state are excluded from T-502 identity.
- `ResolvedTrustedValue::value` is a bounded `list<Model>` containing the exact Eloquent instances returned by Filament in deterministic identity order.
- Selection membership/identity is frozen for one invocation; Eloquent object attributes remain normal mutable application state.
- Default trusted maximum is exactly `500`; constructor overrides must be positive integers and are trusted adapter configuration, never per-invocation caller data.
- Over-limit selection fails closed after observing at most `max + 1` records; never silently truncate.
- Non-Eloquent selected values, unsaved/invalid-key models, duplicate exact identities, and duplicate-enabled `BelongsToMany` row semantics fail closed for non-empty selection authority.
- Tenant remains a separate trusted dimension; never fold tenant identity into the record or selection scope hash.
- T-501 `current_record` scope hash behavior must remain byte-for-byte compatible.
- T-503 active filters, T-504 confirmation UI bridge, and T-505 order-operations demo remain out of scope.
- Existing PHP 8.3/8.4 × Illuminate 12/13 × Testbench 10/11 + Filament 5 + Livewire 4.4 matrix remains intact; no row may be removed to make T-502 pass.

---

## File Structure

### New production files

- `packages/laravel/src/Filament/Context/FilamentRecordIdentity.php` — shared exact persisted Eloquent identity value/encoding.
- `packages/laravel/src/Filament/Context/InvalidFilamentRecordIdentity.php` — internal static-safe identity extraction failure.
- `packages/laravel/src/Filament/Context/FilamentCurrentSelectionResolver.php` — exact table Page → bounded effective selection `ResolvedTrustedValue|null`.
- `packages/laravel/src/Filament/Context/InvalidFilamentCurrentSelection.php` — static-safe selection configuration/resolution/identity failures.

### Modified production files

- `packages/laravel/src/Filament/Context/FilamentRecordContextResolver.php` — delegate record identity extraction to shared primitive while preserving T-501 output exactly.
- `packages/laravel/src/Filament/Context/FilamentTrustedContextComposer.php` — optionally append `current_selection` after `current_record`.
- `packages/laravel/src/Filament/Context/FilamentInvocationContextFactory.php` — construct selection resolver for the exact Page with trusted max configuration.
- `packages/laravel/src/Filament/Invocation/FilamentActionGateway.php` — accept trusted constructor-level max selection configuration and preserve the existing dispatch API.

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
- Review prep only: `STATUS.md`, `REVIEW_REQUEST.md`, and the single T-502 marker in `TASKS.md`.

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
- Produces: `FilamentRecordIdentity::fromModel(Model): FilamentRecordIdentity`, `payload(): array{modelClass:string,keyName:string,keyValue:int|string}`, and `encode(RuntimeScopeCanonicalizer,string): string`.
- Preserves: `FilamentRecordContextResolver::resolve(): ?ResolvedTrustedValue` and domain `"surfacerelay.filament.current_record.v1\n"`.

- [ ] **Step 1: Pin the existing T-501 scope hash before refactoring**

Add to `FilamentRecordContextResolverTest.php` using `SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestRecord` with persisted integer ID `41`:

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

- [ ] **Step 2: Run the compatibility test on the pre-refactor implementation**

```bash
cd packages/laravel
vendor/bin/phpunit tests/Unit/FilamentRecordContextResolverTest.php --filter=byte_compatible
```

Expected: PASS. If the literal does not match the current implementation, stop and correct the test from the actual current canonical encoding before refactoring; do not change production code to satisfy a guessed literal.

- [ ] **Step 3: Write RED shared-identity tests**

Create `FilamentRecordIdentityTest.php` and require:

```php
$identity = FilamentRecordIdentity::fromModel($record);
self::assertSame([
    'modelClass' => TestRecord::class,
    'keyName' => 'id',
    'keyValue' => 41,
], $identity->payload());
self::assertSame(
    '{"keyName":"id","keyValue":41,"modelClass":"SurfaceRelay\\\\Laravel\\\\Tests\\\\Fixtures\\\\Filament\\\\TestRecord"}',
    $identity->encode(new RuntimeScopeCanonicalizer(), 'test.identity'),
);
```

Also cover integer `0`, string `'41'`, unsaved model, null/empty/array/object key, empty key name, and a model whose `getKey()` throws `RuntimeException('SECRET-IDENTITY')`. All invalid cases must throw only `InvalidFilamentRecordIdentity` with static text and `getPrevious() === null`.

- [ ] **Step 4: Run RED**

```bash
vendor/bin/phpunit tests/Unit/FilamentRecordIdentityTest.php
```

Expected: FAIL because `FilamentRecordIdentity` does not exist.

- [ ] **Step 5: Implement the minimal shared primitive**

Create `InvalidFilamentRecordIdentity.php`:

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

Create `FilamentRecordIdentity.php`:

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

- [ ] **Step 6: Refactor current-record resolution only through the shared primitive**

Keep the existing current-record domain constant unchanged. Replace direct key extraction/canonicalization with:

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

Do not alter `getRecord()` handling, provenance, value object identity, or hash domain.

- [ ] **Step 7: Run focused GREEN including byte-compatibility**

```bash
vendor/bin/phpunit \
  tests/Unit/FilamentRecordIdentityTest.php \
  tests/Unit/FilamentRecordContextResolverTest.php \
  tests/Unit/FilamentRecordIdentityFailureTest.php
```

Expected: PASS and the pinned `5436c402...` hash remains exact.

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
- Create: `packages/laravel/tests/Unit/FilamentCurrentSelectionResolverTest.php`

**Interfaces:**
- Consumes: exact `Filament\Resources\Pages\Page`, `Filament\Tables\Contracts\HasTable`, public `getSelectedTableRecords(true, ?int $chunkSize)`, public `getTable()`, `Table::getRelationship()`, `Table::allowsDuplicates()`, shared `FilamentRecordIdentity`.
- Produces: `FilamentCurrentSelectionResolver::resolve(): ?ResolvedTrustedValue` with provider `filament.current_selection`, exact canonical `list<Model>`, and 64-char selection scope key.
- Configuration: `FilamentCurrentSelectionResolver::DEFAULT_MAX_SELECTION_RECORDS = 500`.

- [ ] **Step 1: Write RED configuration/absence tests**

Require:

```php
self::assertSame(500, FilamentCurrentSelectionResolver::DEFAULT_MAX_SELECTION_RECORDS);
self::assertNull((new FilamentCurrentSelectionResolver(new NonRecordPage()))->resolve());
```

Constructing with `maxSelectionRecords: 0` or `-1` must throw `InvalidFilamentCurrentSelection::invalidConfiguration()` with no previous exception.

- [ ] **Step 2: Write RED selection-set tests using a table-capable test page**

The focused unit fixture may subclass a real Filament resource `Page` and implement `HasTable` only through a small test trait/class already supplied by Filament. Do **not** create a fake by hand-implementing every `HasTable` method. If unit setup would require duplicating the full Filament contract, move these cases to the real fixture created in Task 4 and keep only constructor/non-table tests here.

Required assertions for the resolver behavior are:

```php
self::assertSame([$recordA, $recordB], $resolved->value); // canonical identity order
self::assertSame('filament.current_selection', $resolved->provenance->provider);
self::assertNull($resolved->provenance->reference);
self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $resolved->confirmationScopeKey);
```

The same `{A,B}` set returned as `[B,A]` must produce the same scope key and same canonical output ordering; `{A,C}` must differ.

- [ ] **Step 3: Run RED**

```bash
cd packages/laravel
vendor/bin/phpunit tests/Unit/FilamentCurrentSelectionResolverTest.php
```

Expected: FAIL because the resolver/failure types do not exist.

- [ ] **Step 4: Add static-safe failure type**

Create `InvalidFilamentCurrentSelection.php` with exactly these factories/messages:

```php
public static function invalidConfiguration(): self
// "Filament current selection configuration is invalid."

public static function resolutionFailed(): self
// "Filament current selection resolution failed."

public static function limitExceeded(): self
// "Filament current selection exceeds the configured limit."

public static function unsupportedValue(): self
// "Filament current selection contains an unsupported value."

public static function invalidRecordIdentity(): self
// "Filament current selection record identity is invalid."

public static function duplicateIdentity(): self
// "Filament current selection contains a duplicate record identity."

public static function ambiguousDuplicateRows(): self
// "Filament current selection uses ambiguous duplicate-row semantics."
```

Every factory returns a fresh exception without chaining an underlying exception.

- [ ] **Step 5: Implement bounded public-contract selection resolution**

Create `FilamentCurrentSelectionResolver.php` with these constants and constructor:

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

Core algorithm:

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

$recordsByIdentity = [];
$observed = 0;

foreach ($selected as $record) {
    $observed++;
    if ($observed > $this->maxSelectionRecords) {
        throw InvalidFilamentCurrentSelection::limitExceeded();
    }

    if (! $record instanceof Model) {
        throw InvalidFilamentCurrentSelection::unsupportedValue();
    }

    try {
        $identity = FilamentRecordIdentity::fromModel($record);
        $encoded = $identity->encode(
            $this->canonicalizer,
            'filament.current_selection.record',
        );
    } catch (InvalidFilamentRecordIdentity) {
        throw InvalidFilamentCurrentSelection::invalidRecordIdentity();
    }

    if (array_key_exists($encoded, $recordsByIdentity)) {
        throw InvalidFilamentCurrentSelection::duplicateIdentity();
    }

    $recordsByIdentity[$encoded] = $record;
}

if ($recordsByIdentity === []) {
    return null;
}
```

Before issuing non-empty authority, reject duplicate-enabled `BelongsToMany` semantics using public APIs only:

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

Canonicalize and return:

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

Production code must not read `$selectedTableRecords`, `$deselectedTableRecords`, `$isTrackingDeselectedTableRecords`, route/query/request state, or caller IDs.

- [ ] **Step 6: Add adversarial/boundary tests**

Cover exact effective empty selection → `null`, exactly max succeeds, max+1 fails, duplicate identity fails, non-Eloquent fails, unsaved/invalid key fails, selection resolver throwing `RuntimeException('SECRET-SELECTION')` maps to static `resolutionFailed()`, and underlying messages never appear.

For the max test, use a small trusted max (for example `2`) and verify the third observed record fails; do not create 501 fixtures for a unit test.

- [ ] **Step 7: Run focused GREEN**

```bash
vendor/bin/phpunit tests/Unit/FilamentCurrentSelectionResolverTest.php
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add packages/laravel/src/Filament/Context/FilamentCurrentSelectionResolver.php packages/laravel/src/Filament/Context/InvalidFilamentCurrentSelection.php packages/laravel/tests/Unit/FilamentCurrentSelectionResolverTest.php
git commit -m "feat(filament): resolve bounded current selection"
```

---

### Task 3: Wire Current Selection Through the Existing Filament Invocation Path

**Files:**
- Modify: `packages/laravel/src/Filament/Context/FilamentTrustedContextComposer.php`
- Modify: `packages/laravel/src/Filament/Context/FilamentInvocationContextFactory.php`
- Modify: `packages/laravel/src/Filament/Invocation/FilamentActionGateway.php`
- Modify: `packages/laravel/tests/Unit/FilamentTrustedContextComposerTest.php`
- Modify: `packages/laravel/tests/Integration/FilamentActionGatewayIntegrationTest.php`

**Interfaces:**
- Preserves old composer construction: the new selection resolver parameter is optional so T-501 direct consumers remain source-compatible.
- Produces canonical trusted order `authenticated_actor → tenant → current_record → current_selection` when present.
- Preserves `FilamentActionGateway::dispatch(...)` invocation parameters; selection IDs/limits are never dispatch arguments.

- [ ] **Step 1: Write RED composer tests**

Add tests that construct a `ResolvedTrustedValue` selection and require:

```php
$requirements = array_map(
    static fn (TrustedContextEntry $entry): ContextRequirement => $entry->requirement,
    $composer->resolve(),
);

self::assertSame([
    ContextRequirement::AuthenticatedActor,
    ContextRequirement::Tenant,
    ContextRequirement::CurrentRecord,
    ContextRequirement::CurrentSelection,
], $requirements);
```

Also verify record-only behavior remains unchanged when no selection resolver is supplied and table-only selection may exist without `current_record`.

- [ ] **Step 2: Run RED**

```bash
vendor/bin/phpunit tests/Unit/FilamentTrustedContextComposerTest.php
```

Expected: new current-selection tests FAIL.

- [ ] **Step 3: Extend the composer with an optional selection resolver**

Constructor:

```php
public function __construct(
    private TrustedContextComposer $baseComposer,
    private FilamentRecordContextResolver $recordResolver,
    private ?FilamentCurrentSelectionResolver $selectionResolver = null,
) {}
```

After current-record handling, resolve selection only when configured and append:

```php
$selection = $this->selectionResolver?->resolve();
if ($selection !== null) {
    $entries[] = new TrustedContextEntry(
        requirement: ContextRequirement::CurrentSelection,
        value: $selection->value,
        provenance: $selection->provenance,
        confirmationScopeKey: $selection->confirmationScopeKey,
    );
}
```

- [ ] **Step 4: Write RED factory/gateway E2E tests**

Require the exact Page selection to reach an executor through the existing production chain. The test must call `FilamentActionGateway::dispatch(...)`; it must not instantiate `InvocationContext` manually.

Assert executor sees:

```php
$selection = $call->context->require(ContextRequirement::CurrentSelection);
self::assertSame([$recordA, $recordB], $selection);
```

Use caller input/metadata containing fake selection IDs such as `[999]` and assert they do not replace the trusted selection.

- [ ] **Step 5: Extend factory and gateway with trusted constructor configuration only**

`FilamentInvocationContextFactory` constructor:

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

In `forPage()` construct both resolvers from the exact same Page:

```php
new FilamentRecordContextResolver($page),
new FilamentCurrentSelectionResolver($page, $this->maxSelectionRecords),
```

`FilamentActionGateway` constructor becomes:

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

Do not add `selectedIds`, `selection`, or `maxSelectionRecords` to `dispatch()`.

- [ ] **Step 6: Run focused GREEN**

```bash
vendor/bin/phpunit \
  tests/Unit/FilamentTrustedContextComposerTest.php \
  tests/Integration/FilamentActionGatewayIntegrationTest.php
```

Expected: PASS and all existing T-501 gateway tests remain green.

- [ ] **Step 7: Commit**

```bash
git add packages/laravel/src/Filament/Context/FilamentTrustedContextComposer.php packages/laravel/src/Filament/Context/FilamentInvocationContextFactory.php packages/laravel/src/Filament/Invocation/FilamentActionGateway.php packages/laravel/tests/Unit/FilamentTrustedContextComposerTest.php packages/laravel/tests/Integration/FilamentActionGatewayIntegrationTest.php
git commit -m "feat(filament): wire trusted current selection"
```

---

### Task 4: Prove Real Filament 5 Effective Selection Semantics

**Files:**
- Create: `packages/laravel/tests/Fixtures/Filament/TestTableResource.php`
- Create: `packages/laravel/tests/Fixtures/Filament/TestTablePage.php`
- Create: `packages/laravel/tests/Integration/FilamentCurrentSelectionPageIntegrationTest.php`

**Interfaces:**
- Uses real `Filament\Tables\Contracts\HasTable` + `Filament\Tables\Concerns\InteractsWithTable` public behavior.
- Test setup may mutate public Filament Livewire properties to simulate browser state; production SurfaceRelay code must never inspect them directly.
- Produces proof for explicit selection, select-all/deselection, selectability, and bounded lazy query behavior.

- [ ] **Step 1: Create the real table fixture**

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
        return [];
    }
}
```

`TestTablePage.php`:

```php
<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\Filament;

use Filament\Resources\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;

final class TestTablePage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = TestTableResource::class;
    protected string $view = 'surfacerelay-tests::empty';
}
```

In the Testbench integration case register a trivial test view namespace/file or an in-memory view so the Page can boot if Livewire rendering is used. If direct construction is used, call the public trait boot hook required to initialize the table before resolving selection; do not set the protected `$table` property by reflection.

- [ ] **Step 2: Write explicit-selection integration test**

Create the SQLite table `filament_test_records(id integer primary key, name string)`, persist A/B/C, initialize `TestTablePage`, set Filament's public selection state in the test harness to A/B, then compare:

```php
$filamentRecords = $page->getSelectedTableRecords(true, 100)->values()->all();
$resolved = (new FilamentCurrentSelectionResolver($page))->resolve();

self::assertNotNull($resolved);
self::assertEqualsCanonicalizing($filamentRecords, $resolved->value);
```

The SurfaceRelay assertion is against Filament's public effective result, not against raw ID properties.

- [ ] **Step 3: Add select-all + deselected-exception proof**

Simulate Filament select-all with:

```php
$page->isTrackingDeselectedTableRecords = true;
$page->selectedTableRecords = [];
$page->deselectedTableRecords = [(string) $recordB->getKey()];
```

Assert raw `$selectedTableRecords === []` while `getSelectedTableRecords()` returns A/C and SurfaceRelay resolves non-empty A/C authority. This proves raw empty ID state is not trusted absence.

- [ ] **Step 4: Add selectable-record proof**

Persist one record with `name='blocked'`, include it in simulated selected state, and assert Filament's public `getSelectedTableRecords()` excludes it because `checkIfRecordIsSelectableUsing()` rejects it. SurfaceRelay must match the same effective result and must not count the blocked record toward its own post-Filament membership set.

- [ ] **Step 5: Add bounded lazy query proof**

Persist at least five selectable records, simulate select-all, construct resolver with `maxSelectionRecords: 2`, and attach a DB query listener. The resolver must throw `selection_limit_exceeded` after the third effective record. With `chunkSize = min(100, max + 1)`, the first lazy query window must be bounded to at most three rows; assert the observed SQL/query binding or result instrumentation demonstrates no full unbounded materialization by SurfaceRelay.

Do not claim Filament performs zero queries; T-502 explicitly permits Filament's own effective-selection query.

- [ ] **Step 6: Run real Filament GREEN**

```bash
vendor/bin/phpunit tests/Integration/FilamentCurrentSelectionPageIntegrationTest.php
```

Expected: PASS on the local/default dependency set, then exact-head CI must prove all four matrix combinations.

- [ ] **Step 7: Commit**

```bash
git add packages/laravel/tests/Fixtures/Filament/TestTableResource.php packages/laravel/tests/Fixtures/Filament/TestTablePage.php packages/laravel/tests/Integration/FilamentCurrentSelectionPageIntegrationTest.php
git commit -m "test(filament): prove effective table selection"
```

---

### Task 5: Bind Selection to Confirmation, Idempotency, Required Context, and Audit

**Files:**
- Create: `packages/laravel/tests/Integration/FilamentCurrentSelectionTrustControlsIntegrationTest.php`
- Reuse existing confirmation/idempotency/audit production classes without behavior changes unless a failing test proves an actual generic-runtime defect.

**Interfaces:**
- Consumes: `FilamentActionGateway`, `ContextRequirement::CurrentSelection`, D-044 confirmation service/stage, D-045 idempotency hashing/service, D-047 audit projection.
- Produces: evidence that existing generic trust controls bind the new selection scope key correctly.

- [ ] **Step 1: Write required-context/production-path tests**

Use an ActionDefinition requiring `current_selection`.

Case A: effective empty selection → ActionBus halts at required context and executor call count remains zero.

Case B: non-empty A/B selection → executor receives the exact two Eloquent instances through `FilamentActionGateway`.

Include caller payload and metadata such as:

```php
input: ['selectedIds' => [999]],
metadata: ['current_selection' => [999]],
```

and prove they neither satisfy empty selection nor replace trusted A/B.

- [ ] **Step 2: Prove order-independent confirmation/idempotency identity**

Resolve page state A/B and B/A as two effective source orders. Assert the resulting `current_selection` scope key, confirmation scope hash, and idempotency intent fingerprint are equal.

Resolve A/C and assert all relevant scope/fingerprint values differ.

Tenant A vs tenant B with the same A/B selection must produce different overall confirmation/idempotency scope while the selection's own `confirmationScopeKey` remains identical.

- [ ] **Step 3: Prove wrong-selection receipt isolation and non-consumption**

Issue/approve a confirmation for selection A/B. Attempt the receipt against current selection A/C and assert mismatch/rejection before execution. Then retry the same receipt against A/B and prove it still succeeds, showing the wrong-selection attempt did not spend the valid A/B receipt.

Use the existing confirmation store/service APIs; do not inspect or mutate store internals to manufacture success.

- [ ] **Step 4: Prove audit minimization**

Run completed and halted selection-backed invocations through `StructuredActionPipelineAuditor`/real audit path and assert the trusted-context manifest contains only:

```php
[
    'requirement' => 'current_selection',
    'provider' => 'filament.current_selection',
]
```

Scan semantic/persisted audit representation for forbidden markers: raw IDs, model class, model attributes, selection count, selection scope key, active filter state, route/query data.

- [ ] **Step 5: Run focused trust-control GREEN**

```bash
vendor/bin/phpunit tests/Integration/FilamentCurrentSelectionTrustControlsIntegrationTest.php
```

Expected: PASS without changing generic confirmation/idempotency/audit production behavior.

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
- Modify: `TASKS.md` only at the T-502 operational marker.
- No `spec/0.1/**` change.

**Interfaces:**
- Consumes all T-502 production/test checkpoints.
- Produces a stable exact-head review candidate; does not merge.

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

Record the exact test/assertion count from fresh output; do not reuse T-501's `482 / 2533` count.

- [ ] **Step 3: Run browser and contract isolation checks**

```bash
cd ../browser-runtime
npm test
npm run typecheck
cd ../..
python scripts/validate.py
```

Expected: browser remains at least the current 103 Vitest tests unless unrelated main changes legitimately altered the count; contract validator green.

- [ ] **Step 4: Scope-audit base → head**

Compare against exact base `ae77cbf26cbefcca478d070765386628343bec52` and verify:

- no `spec/0.1/**` files changed;
- no `packages/browser-runtime/src/**` production files changed;
- no `packages/laravel/src/Livewire/**` files changed;
- no new RuntimeBinding driver exists;
- no production references to `$selectedTableRecords`, `$deselectedTableRecords`, `$isTrackingDeselectedTableRecords`, `request()`, route record IDs, `Livewire\invade()`, or reflection exist in the new selection adapter.

- [ ] **Step 5: Update review records from evidence only**

Set `STATUS.md` current task to `T-502 — DONE / REVIEW PENDING` after implementation verification, then to `DONE / REVIEWED; MERGE PENDING` only after external review closure.

`REVIEW_REQUEST.md` must include:

- exact base/head SHAs;
- TDD RED/GREEN checkpoints;
- fresh PHP/browser/contract evidence;
- D-049 semantics;
- selection limit and effective-selection boundary;
- T-501 byte-compatibility proof;
- confirmation/idempotency/audit proofs;
- exact external-review findings/fixes.

Change only the T-502 line in `TASKS.md` to `DONE / REVIEWED` after review closure; preserve all historical task-board evidence.

- [ ] **Step 6: Commit review-prep docs and run exact-head CI**

```bash
git add STATUS.md REVIEW_REQUEST.md TASKS.md
git commit -m "docs(review): prepare T-502 review record"
```

Push and require the repository's full 7-job workflow to be green on this exact head.

- [ ] **Step 7: Open PR and request CodeRabbit review**

PR title:

```text
feat(filament): bind trusted current selection context
```

Review focus:

```text
Challenge effective-selection authority, raw Livewire-state dependence,
select-all/deselection semantics, bounded materialization, duplicate/pivot
ambiguity, T-501 hash compatibility, caller spoofing, selection-scope
confirmation/idempotency binding, audit leakage, and accidental second
execution paths.
```

Trigger CodeRabbit on the stable exact head. Freeze branch writes while the reviewer evaluates that head.

- [ ] **Step 8: Handle actionable review findings TDD-first**

For each technically valid finding:

1. reproduce with a focused failing test;
2. record exact RED evidence;
3. implement the smallest correction;
4. run focused + full relevant GREEN;
5. reply with exact evidence;
6. resolve only after technical closure.

Generic docstring/style metrics are not correctness/security gates unless the repository's own CI requires them.

- [ ] **Step 9: Final review-head verification**

Require before reporting merge-ready:

- PR open and mergeable;
- exact final head known;
- exact-head workflow 7/7 green;
- fresh PHP test/assertion count recorded;
- browser/contract green;
- 0 unresolved actionable review threads;
- branch 0 behind `main` or intentionally rebased/revalidated if main moved;
- `spec/0.1/**` unchanged.

Do **not** merge. PR merge remains a separate explicit user gate.
