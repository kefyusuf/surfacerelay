# T-501 Filament Record Context Binding Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a Filament 5 trusted-context adapter that supplies the exact active record page's Eloquent model as `current_record` without introducing a second execution driver or any caller-controlled record resolution path.

**Architecture:** Filament remains a UI/trusted-context adapter on top of the existing Livewire RuntimeBinding execution path. `FilamentRecordContextResolver` accepts one exact trusted `Filament\Resources\Pages\Page`, reads only its public `getRecord()` capability, returns the exact Eloquent model plus a domain-separated hashed confirmation scope key, and never re-queries by route/input ID. `FilamentTrustedContextComposer` delegates existing actor/tenant resolution to `TrustedContextComposer` and appends only the resolved `current_record` entry.

**Tech Stack:** PHP 8.3/8.4, Laravel/Illuminate 12/13, Livewire 4.4+, Filament 5.x, Orchestra Testbench 10/11, PHPUnit 11, MySQL 8.4 CI service.

**Spec:** `docs/superpowers/specs/2026-09-10-filament-record-context-binding-design.md`

## Global Constraints

- Base implementation branch: `feat/filament-record-context-binding`, forked from `main@66afcc22704bfe3b317f2894b7737cf18d248e34`.
- `spec/0.1/**` remains unchanged.
- Do not introduce a `filament` RuntimeBinding driver; execution remains on the existing exact `livewire` binding/driver path.
- `current_record` authority comes only from the exact trusted active Filament resource-page instance supplied to the adapter.
- Never resolve/reload a record from action input, `InvocationContext::metadata`, route/query/request data, binding ID, confirmation receipt, or idempotency key.
- `ResolvedTrustedValue::value` is the exact Eloquent `Model` instance returned by the page.
- `confirmationScopeKey` is SHA-256 over domain-separated canonical `{modelClass,keyName,keyValue}` identity only; no model attributes, tenant identity, route values, timestamps, or user identity participate.
- Tenant and current-record identity remain independent trusted dimensions. Never fold tenant identity into the record scope key.
- Filament remains optional for production consumers: `filament/filament:^5.0` belongs in `require-dev`, not `require`.
- Production service-provider boot must not eagerly resolve or register Filament classes when Filament is absent.
- Inconsistent record-aware Filament state fails closed with static-safe adapter exceptions and no chained underlying exception.
- T-502 selection, T-503 filters, T-504 confirmation UI bridge, and T-505 order-operations demo remain out of scope.

---

## File Structure

### New production files

- `packages/laravel/src/Filament/Context/FilamentRecordContextResolver.php` — exact page → `ResolvedTrustedValue|null`; owns record identity derivation.
- `packages/laravel/src/Filament/Context/FilamentTrustedContextComposer.php` — actor/tenant composer + Filament record resolver → canonical trusted-context entries.
- `packages/laravel/src/Filament/Context/InvalidFilamentRecordContext.php` — static-safe fail-closed adapter exception factory.

### New test/support files

- `packages/laravel/tests/Unit/FilamentRecordContextResolverTest.php` — record capability, identity, failure secrecy, no-requery behavior.
- `packages/laravel/tests/Unit/FilamentTrustedContextComposerTest.php` — actor/tenant/current-record composition and metadata isolation.
- `packages/laravel/tests/Integration/FilamentRecordTrustControlsIntegrationTest.php` — real existing confirmation/idempotency/audit semantics using Filament record context.
- `packages/laravel/tests/Integration/FilamentRecordPageIntegrationTest.php` — real Filament 5 resource page + `InteractsWithRecord` compatibility proof.
- `packages/laravel/tests/Fixtures/Filament/TestRecord.php` — minimal Eloquent record model for Filament/Testbench integration.
- `packages/laravel/tests/Fixtures/Filament/TestRecordResource.php` — minimal Filament Resource.
- `packages/laravel/tests/Fixtures/Filament/TestRecordPage.php` — minimal record-aware Resource Page using Filament's public `InteractsWithRecord` concern.
- `packages/laravel/tests/Fixtures/Filament/NonRecordPage.php` — real Filament Resource Page without record capability.

### Modified files

- `packages/laravel/composer.json` — add Filament 5 as dev-only dependency.
- `.github/workflows/validate.yml` — make `intl` and Filament 5 test dependency explicit in the PHP matrix setup.
- `STATUS.md` — move current milestone to M5/T-501 implementation/review state only after implementation is green.
- `REVIEW_REQUEST.md` — record T-501 review scope/evidence only after implementation is green.
- `TASKS.md` — update only the T-501 operational marker at review-prep; do not mark M5 complete.

---

### Task 1: Lock Filament 5 as an Optional Test Dependency Across the Existing Matrix

**Files:**
- Modify: `packages/laravel/composer.json`
- Modify: `.github/workflows/validate.yml`
- Create: `packages/laravel/tests/Unit/FilamentDependencyPolicyTest.php`

**Interfaces:**
- Consumes: current package Composer metadata and existing four-way PHP/Illuminate/Testbench matrix.
- Produces: Filament 5 classes available to tests while production package metadata remains Filament-optional.

- [ ] **Step 1: Write the failing dependency-policy test**

Create `packages/laravel/tests/Unit/FilamentDependencyPolicyTest.php`:

```php
<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use JsonException;
use PHPUnit\Framework\TestCase;

final class FilamentDependencyPolicyTest extends TestCase
{
    /** @throws JsonException */
    public function test_filament_is_dev_only_and_pinned_to_major_five(): void
    {
        $composer = json_decode(
            file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertArrayNotHasKey('filament/filament', $composer['require'] ?? []);
        self::assertSame('^5.0', $composer['require-dev']['filament/filament'] ?? null);
    }
}
```

- [ ] **Step 2: Run the focused test and verify RED**

Run:

```bash
cd packages/laravel
vendor/bin/phpunit tests/Unit/FilamentDependencyPolicyTest.php
```

Expected: FAIL because `require-dev.filament/filament` is absent.

- [ ] **Step 3: Add the dev-only Filament dependency**

Update `packages/laravel/composer.json` `require-dev` to include:

```json
"filament/filament": "^5.0"
```

Do not add Filament to `require`, `extra.laravel.providers`, or `SurfaceRelayServiceProvider`.

- [ ] **Step 4: Make the CI extension/dependency setup explicit**

In `.github/workflows/validate.yml`, change setup-php extensions from:

```yaml
extensions: pdo_mysql
```

to:

```yaml
extensions: pdo_mysql, intl
```

Change the dev dependency constraint step to:

```yaml
- name: Constrain Testbench, Livewire and Filament integration dependencies
  working-directory: packages/laravel
  run: >-
    composer require --dev --no-interaction --no-progress --no-update
    "orchestra/testbench:${{ matrix.testbench-version }}"
    "livewire/livewire:^4.4"
    "filament/filament:^5.0"
```

This keeps all four existing PHP/Illuminate matrix combinations authoritative. Do not remove or split a matrix row to make Filament resolve.

- [ ] **Step 5: Resolve dependencies and run the focused test**

Run:

```bash
cd packages/laravel
composer update --no-interaction --no-progress --prefer-dist
vendor/bin/phpunit tests/Unit/FilamentDependencyPolicyTest.php
composer validate --strict
```

Expected: dependency resolution succeeds and the focused test PASSes.

If Composer reports Filament 5 cannot resolve against any existing matrix combination, stop implementation and return to the design gate; do not silently narrow Laravel support.

- [ ] **Step 6: Commit the dependency boundary**

```bash
git add packages/laravel/composer.json .github/workflows/validate.yml packages/laravel/tests/Unit/FilamentDependencyPolicyTest.php
git commit -m "test(filament): lock optional Filament 5 matrix"
```

---

### Task 2: Resolve the Exact Active Filament Record and Derive Stable Identity

**Files:**
- Create: `packages/laravel/src/Filament/Context/InvalidFilamentRecordContext.php`
- Create: `packages/laravel/src/Filament/Context/FilamentRecordContextResolver.php`
- Create: `packages/laravel/tests/Unit/FilamentRecordContextResolverTest.php`

**Interfaces:**
- Consumes: `Filament\Resources\Pages\Page`, `Illuminate\Database\Eloquent\Model`, `RuntimeScopeCanonicalizer`, `ResolvedTrustedValue`, `ContextProvenance`.
- Produces: `FilamentRecordContextResolver::resolve(): ?ResolvedTrustedValue` with provider `filament.current_record` and a stable 64-character lowercase SHA-256 `confirmationScopeKey`.

- [ ] **Step 1: Write RED tests for record capability and exact-object preservation**

Create test-local page fixtures in `FilamentRecordContextResolverTest.php`:

```php
final class ResolverNonRecordPage extends \Filament\Resources\Pages\Page
{
    protected string $view = 'resolver-non-record';
}

final class ResolverRecordPage extends \Filament\Resources\Pages\Page
{
    protected string $view = 'resolver-record';

    public function __construct(private readonly mixed $resolvedRecord) {}

    public function getRecord(): mixed
    {
        return $this->resolvedRecord;
    }
}
```

Use a small Eloquent model fixture:

```php
final class ResolverRecord extends \Illuminate\Database\Eloquent\Model
{
    protected $guarded = [];
    public $timestamps = false;
}
```

Write tests proving:

```php
public function test_non_record_filament_page_resolves_to_absence(): void
{
    self::assertNull((new FilamentRecordContextResolver(new ResolverNonRecordPage()))->resolve());
}

public function test_record_page_returns_exact_model_instance_and_minimal_provenance(): void
{
    $record = new ResolverRecord();
    $record->setRawAttributes(['id' => 41, 'name' => 'secret-name']);
    $record->exists = true;

    $resolved = (new FilamentRecordContextResolver(new ResolverRecordPage($record)))->resolve();

    self::assertNotNull($resolved);
    self::assertSame($record, $resolved->value);
    self::assertSame('filament.current_record', $resolved->provenance->provider);
    self::assertNull($resolved->provenance->reference);
    self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $resolved->confirmationScopeKey);
}
```

- [ ] **Step 2: Run the focused test and verify RED**

Run:

```bash
cd packages/laravel
vendor/bin/phpunit tests/Unit/FilamentRecordContextResolverTest.php
```

Expected: FAIL because `FilamentRecordContextResolver` does not exist.

- [ ] **Step 3: Add the static-safe failure type**

Create `InvalidFilamentRecordContext.php`:

```php
<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Filament\Context;

final class InvalidFilamentRecordContext extends \RuntimeException
{
    public static function recordResolutionFailed(): self
    {
        return new self('Filament current record resolution failed.');
    }

    public static function invalidRecordState(): self
    {
        return new self('Filament current record state is invalid.');
    }

    public static function invalidRecordIdentity(): self
    {
        return new self('Filament current record identity is invalid.');
    }
}
```

Messages must remain static. Never pass a previous exception into these constructors.

- [ ] **Step 4: Implement minimal exact-page resolution**

Create `FilamentRecordContextResolver.php`:

```php
<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Filament\Context;

use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Model;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\ResolvedTrustedValue;
use SurfaceRelay\Laravel\Runtime\Scope\RuntimeScopeCanonicalizer;
use SurfaceRelay\Laravel\Runtime\Scope\UnrepresentableRuntimeScope;

final class FilamentRecordContextResolver
{
    private const string IDENTITY_DOMAIN = "surfacerelay.filament.current_record.v1\n";

    private readonly RuntimeScopeCanonicalizer $canonicalizer;

    public function __construct(
        private readonly Page $page,
        ?RuntimeScopeCanonicalizer $canonicalizer = null,
    ) {
        $this->canonicalizer = $canonicalizer ?? new RuntimeScopeCanonicalizer();
    }

    public function resolve(): ?ResolvedTrustedValue
    {
        if (! is_callable([$this->page, 'getRecord'])) {
            return null;
        }

        try {
            $record = $this->page->getRecord();
        } catch (\Throwable) {
            throw InvalidFilamentRecordContext::recordResolutionFailed();
        }

        if (! $record instanceof Model || ! $record->exists) {
            throw InvalidFilamentRecordContext::invalidRecordState();
        }

        $keyName = $record->getKeyName();
        $keyValue = $record->getKey();

        if (
            ! is_string($keyName)
            || $keyName === ''
            || (! is_int($keyValue) && ! is_string($keyValue))
            || $keyValue === ''
        ) {
            throw InvalidFilamentRecordContext::invalidRecordIdentity();
        }

        try {
            $identity = $this->canonicalizer->encode([
                'modelClass' => $record::class,
                'keyName' => $keyName,
                'keyValue' => $keyValue,
            ], 'filament.current_record.identity');
        } catch (UnrepresentableRuntimeScope) {
            throw InvalidFilamentRecordContext::invalidRecordIdentity();
        }

        return new ResolvedTrustedValue(
            value: $record,
            provenance: new ContextProvenance('filament.current_record'),
            confirmationScopeKey: hash('sha256', self::IDENTITY_DOMAIN . $identity),
        );
    }
}
```

Do not use route model binding, `resolveRecord()`, `request()`, `Route`, the service container, reflection, protected `$record`, or a database query.

- [ ] **Step 5: Run the focused tests and verify GREEN**

Run:

```bash
cd packages/laravel
vendor/bin/phpunit tests/Unit/FilamentRecordContextResolverTest.php
```

Expected: PASS for absence and exact-object/provenance tests.

- [ ] **Step 6: Add RED adversarial identity/failure tests**

Extend the same test file to prove:

```php
public function test_identity_is_stable_for_same_typed_record_identity(): void
{
    $a = $this->persistedRecord(123, ['name' => 'alpha']);
    $b = $this->persistedRecord(123, ['name' => 'beta']);

    self::assertSame(
        $this->resolve($a)->confirmationScopeKey,
        $this->resolve($b)->confirmationScopeKey,
    );
}

public function test_identity_changes_for_different_record_key(): void
{
    self::assertNotSame(
        $this->resolve($this->persistedRecord(123))->confirmationScopeKey,
        $this->resolve($this->persistedRecord(124))->confirmationScopeKey,
    );
}

public function test_integer_and_string_keys_are_distinct(): void
{
    self::assertNotSame(
        $this->resolve($this->persistedRecord(123))->confirmationScopeKey,
        $this->resolve($this->persistedRecord('123'))->confirmationScopeKey,
    );
}
```

Add a second model class with the same key and assert a different hash. Assert attribute mutations do not change the hash. Assert key `0` is accepted.

Add failures for:

- `getRecord()` returns null;
- `getRecord()` returns non-Model;
- `getRecord()` throws `new RuntimeException('SECRET-RECORD-ERROR')`;
- `$record->exists === false`;
- `getKey() === null`;
- `getKey() === ''`;
- unsupported array/object key;
- empty key name.

For the throwing case:

```php
try {
    $resolver->resolve();
    self::fail('Expected InvalidFilamentRecordContext.');
} catch (InvalidFilamentRecordContext $e) {
    self::assertSame('Filament current record resolution failed.', $e->getMessage());
    self::assertNull($e->getPrevious());
    self::assertStringNotContainsString('SECRET-RECORD-ERROR', $e->getMessage());
}
```

- [ ] **Step 7: Run identity/failure tests and fix only production implementation defects**

Run:

```bash
cd packages/laravel
vendor/bin/phpunit tests/Unit/FilamentRecordContextResolverTest.php
```

Expected: all tests PASS without loosening the assertions.

- [ ] **Step 8: Commit exact record resolution**

```bash
git add packages/laravel/src/Filament/Context/InvalidFilamentRecordContext.php \
        packages/laravel/src/Filament/Context/FilamentRecordContextResolver.php \
        packages/laravel/tests/Unit/FilamentRecordContextResolverTest.php
git commit -m "feat(filament): resolve trusted current record"
```

---

### Task 3: Compose Current Record with Existing Actor and Tenant Trusted Context

**Files:**
- Create: `packages/laravel/src/Filament/Context/FilamentTrustedContextComposer.php`
- Create: `packages/laravel/tests/Unit/FilamentTrustedContextComposerTest.php`

**Interfaces:**
- Consumes: `TrustedContextComposer::resolve(): array`, `FilamentRecordContextResolver::resolve(): ?ResolvedTrustedValue`.
- Produces: `FilamentTrustedContextComposer::resolve(): list<TrustedContextEntry>` preserving existing actor/tenant semantics and adding `ContextRequirement::CurrentRecord` only when present.

- [ ] **Step 1: Write RED composition tests**

Create `FilamentTrustedContextComposerTest.php` with actor/tenant resolver fakes implementing the existing zero-argument contracts:

```php
final class StaticActorResolver implements AuthenticatedActorResolver
{
    public function __construct(private readonly ?ResolvedTrustedValue $value) {}
    public function resolve(): ?ResolvedTrustedValue { return $this->value; }
}

final class StaticTenantResolver implements TenantResolver
{
    public function __construct(private readonly ?ResolvedTrustedValue $value) {}
    public function resolve(): ?ResolvedTrustedValue { return $this->value; }
}
```

Build the base composer:

```php
$base = new TrustedContextComposer(
    new StaticActorResolver(new ResolvedTrustedValue(
        value: 'actor-1',
        provenance: new ContextProvenance('test.actor'),
        confirmationScopeKey: 'actor-scope',
    )),
    new StaticTenantResolver(new ResolvedTrustedValue(
        value: 'tenant-1',
        provenance: new ContextProvenance('test.tenant'),
        confirmationScopeKey: 'tenant-scope',
    )),
);
```

Assert the new composer produces requirements in exact order:

```php
self::assertSame(
    [
        ContextRequirement::AuthenticatedActor,
        ContextRequirement::Tenant,
        ContextRequirement::CurrentRecord,
    ],
    array_map(static fn (TrustedContextEntry $entry) => $entry->requirement, $entries),
);
```

Assert the record entry holds the exact model instance, provider `filament.current_record`, and resolver-produced scope key.

Add a non-record page case and assert output is exactly the two base entries with no synthetic current-record placeholder.

- [ ] **Step 2: Run the focused test and verify RED**

```bash
cd packages/laravel
vendor/bin/phpunit tests/Unit/FilamentTrustedContextComposerTest.php
```

Expected: FAIL because `FilamentTrustedContextComposer` does not exist.

- [ ] **Step 3: Implement the narrow wrapper composer**

Create:

```php
<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Filament\Context;

use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextComposer;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;

final readonly class FilamentTrustedContextComposer
{
    public function __construct(
        private TrustedContextComposer $baseComposer,
        private FilamentRecordContextResolver $recordResolver,
    ) {}

    /** @return list<TrustedContextEntry> */
    public function resolve(): array
    {
        $entries = $this->baseComposer->resolve();
        $record = $this->recordResolver->resolve();

        if ($record === null) {
            return $entries;
        }

        $entries[] = new TrustedContextEntry(
            requirement: ContextRequirement::CurrentRecord,
            value: $record->value,
            provenance: $record->provenance,
            confirmationScopeKey: $record->confirmationScopeKey,
        );

        return $entries;
    }
}
```

Do not turn `TrustedContextComposer` into a plugin registry and do not add current-selection support.

- [ ] **Step 4: Prove InvocationContext canonical ordering and metadata isolation**

Add tests that create:

```php
$context = new InvocationContext(
    surface: 'filament',
    correlationId: 'corr-filament-record',
    trustedContext: $composer->resolve(),
    metadata: [
        'current_record' => 'ATTACKER-RECORD',
        'recordId' => 999,
    ],
);
```

Assert:

```php
self::assertSame($record, $context->require(ContextRequirement::CurrentRecord)->value);
self::assertSame(
    [
        ContextRequirement::AuthenticatedActor,
        ContextRequirement::Tenant,
        ContextRequirement::CurrentRecord,
    ],
    array_map(static fn (TrustedContextEntry $entry) => $entry->requirement, $context->allTrusted()),
);
self::assertNotSame(
    $context->metadata['current_record'],
    $context->require(ContextRequirement::CurrentRecord)->value,
);
```

- [ ] **Step 5: Run focused tests and verify GREEN**

```bash
cd packages/laravel
vendor/bin/phpunit tests/Unit/FilamentTrustedContextComposerTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit trusted-context composition**

```bash
git add packages/laravel/src/Filament/Context/FilamentTrustedContextComposer.php \
        packages/laravel/tests/Unit/FilamentTrustedContextComposerTest.php
git commit -m "feat(filament): compose current record context"
```

---

### Task 4: Prove Existing Confirmation, Idempotency and Audit Controls Bind the Filament Record Correctly

**Files:**
- Create: `packages/laravel/tests/Integration/FilamentRecordTrustControlsIntegrationTest.php`
- No production changes expected.

**Interfaces:**
- Consumes: `FilamentRecordContextResolver`, `FilamentTrustedContextComposer`, `InvocationContext`, `ActionPipelineState`, `ConfirmationScopeHasher`, `ConfirmationService`, `IdempotencyIntentHasher`, `AuditEventFactory`.
- Produces: regression evidence that changing records changes confirmation/idempotency identity while T-404 persists only requirement/provider.

- [ ] **Step 1: Write RED integration harness guard**

Start the test file with a guard test so the new harness is visibly absent before implementation:

```php
public function test_filament_record_trust_control_harness_is_implemented(): void
{
    self::assertTrue(
        method_exists($this, 'stateForRecord'),
        'T-501 trust-control harness must construct pipeline state from Filament current_record context.',
    );
}
```

Commit/run this RED before filling the helper.

- [ ] **Step 2: Run the integration test and verify RED**

```bash
cd packages/laravel
vendor/bin/phpunit tests/Integration/FilamentRecordTrustControlsIntegrationTest.php
```

Expected: exactly one failure from the missing `stateForRecord` harness guard.

- [ ] **Step 3: Build a real pipeline-state helper from Filament context**

Implement helper shape:

```php
private function stateForRecord(Model $record): ActionPipelineState
{
    $page = new TrustControlRecordPage($record);
    $recordEntry = (new FilamentRecordContextResolver($page))->resolve();
    self::assertNotNull($recordEntry);

    $context = new InvocationContext(
        surface: 'filament',
        correlationId: 'corr-' . (string) $record->getKey(),
        trustedContext: [
            new TrustedContextEntry(
                ContextRequirement::Tenant,
                'tenant-a',
                new ContextProvenance('test.tenant'),
                'tenant-a-scope',
            ),
            new TrustedContextEntry(
                ContextRequirement::CurrentRecord,
                $recordEntry->value,
                $recordEntry->provenance,
                $recordEntry->confirmationScopeKey,
            ),
        ],
    );

    return new ActionPipelineState(
        definition: $this->definition(),
        input: ['reason' => 'customer-request'],
        context: $context,
        bindingId: 'filament-livewire-binding-1',
    );
}
```

The `definition()` fixture must use exact stable ActionDefinition fields and include `current_record` in `contextRequirements`; reuse the repository's existing ActionDefinition constructor pattern rather than adding test-only production APIs.

- [ ] **Step 4: Prove confirmation and idempotency fingerprints vary only with intended record identity**

Add:

```php
public function test_confirmation_and_idempotency_bind_exact_record_identity(): void
{
    $recordA = $this->persistedRecord(10);
    $recordB = $this->persistedRecord(11);
    $recordASecondInstance = $this->persistedRecord(10);

    $confirmation = new ConfirmationScopeHasher();
    $idempotency = new IdempotencyIntentHasher();

    self::assertNotSame(
        $confirmation->fingerprint($this->stateForRecord($recordA)),
        $confirmation->fingerprint($this->stateForRecord($recordB)),
    );
    self::assertSame(
        $confirmation->fingerprint($this->stateForRecord($recordA)),
        $confirmation->fingerprint($this->stateForRecord($recordASecondInstance)),
    );

    self::assertNotSame(
        $idempotency->fingerprint($this->stateForRecord($recordA)),
        $idempotency->fingerprint($this->stateForRecord($recordB)),
    );
    self::assertSame(
        $idempotency->fingerprint($this->stateForRecord($recordA)),
        $idempotency->fingerprint($this->stateForRecord($recordASecondInstance)),
    );
}
```

- [ ] **Step 5: Prove a receipt issued for record A cannot authorize record B**

Use the existing `ConfirmationService` contract, not a synthetic boolean:

```php
$scopeA = (new ConfirmationScopeHasher())->fingerprint($this->stateForRecord($recordA));
$scopeB = (new ConfirmationScopeHasher())->fingerprint($this->stateForRecord($recordB));

$service = $this->confirmationService();
$challenge = $service->issueChallenge($scopeA, 'Approve refund');
$receipt = $service->approveChallenge($challenge->challengeId);
self::assertNotNull($receipt);

self::assertFalse($service->consumeReceipt($receipt, $scopeB));
self::assertTrue($service->consumeReceipt($receipt, $scopeA));
```

The test-local `confirmationService()` must use the same `ConfirmationStore` interface and deterministic clock/token fixtures already used by existing confirmation integration tests. Copy the minimal fixture into this file rather than changing production confirmation code.

- [ ] **Step 6: Prove tenant stays an independent trusted dimension**

Build two states with identical record identity but different tenant trusted entries and assert both confirmation and idempotency fingerprints differ. Also assert the resolver's `confirmationScopeKey` itself remains identical across the two tenants.

This prevents accidentally moving tenant authority into `FilamentRecordContextResolver`.

- [ ] **Step 7: Prove T-404 audit minimization**

Create a completed `ActionPipelineOutcome` from the Filament-record state and use `AuditEventFactory` directly. Assert the trusted-context manifest contains:

```php
self::assertContains(
    ['requirement' => 'current_record', 'provider' => 'filament.current_record'],
    array_map(
        static fn ($entry) => [
            'requirement' => $entry->requirement,
            'provider' => $entry->provider,
        ],
        $event->trustedContextManifest,
    ),
);
```

Then encode the semantic event/test projection and assert it does **not** contain:

- raw record key marker;
- model attribute marker;
- resolver `confirmationScopeKey`;
- page class;
- resource/route data.

If `AuditEventFactory` public construction requires a clock, use a fixed UTC `AuditClock` test double exactly as existing audit tests do. Do not modify T-404 production behavior.

- [ ] **Step 8: Run focused trust-control integration and verify GREEN**

```bash
cd packages/laravel
vendor/bin/phpunit tests/Integration/FilamentRecordTrustControlsIntegrationTest.php
```

Expected: PASS with no production changes outside the Filament adapter.

- [ ] **Step 9: Commit trust-control proof**

```bash
git add packages/laravel/tests/Integration/FilamentRecordTrustControlsIntegrationTest.php
git commit -m "test(filament): bind record context to trust controls"
```

---

### Task 5: Prove Compatibility Against a Real Filament 5 Record Resource Page

**Files:**
- Create: `packages/laravel/tests/Fixtures/Filament/TestRecord.php`
- Create: `packages/laravel/tests/Fixtures/Filament/TestRecordResource.php`
- Create: `packages/laravel/tests/Fixtures/Filament/TestRecordPage.php`
- Create: `packages/laravel/tests/Fixtures/Filament/NonRecordPage.php`
- Create: `packages/laravel/tests/Integration/FilamentRecordPageIntegrationTest.php`

**Interfaces:**
- Consumes: real Filament 5 `Filament\Resources\Resource`, `Filament\Resources\Pages\Page`, `Filament\Resources\Pages\Concerns\InteractsWithRecord`, Eloquent/Testbench container.
- Produces: compatibility proof that the adapter consumes Filament's real public `getRecord(): Model` contract and returns absence for a real non-record resource page.

- [ ] **Step 1: Add minimal real Filament fixture model/resource/pages**

`TestRecord.php`:

```php
<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\Filament;

use Illuminate\Database\Eloquent\Model;

final class TestRecord extends Model
{
    protected $table = 'filament_test_records';
    protected $guarded = [];
    public $timestamps = false;
}
```

`TestRecordResource.php`:

```php
<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\Filament;

use Filament\Resources\Resource;

final class TestRecordResource extends Resource
{
    protected static ?string $model = TestRecord::class;

    public static function getPages(): array
    {
        return [
            'record' => TestRecordPage::route('/{record}'),
            'index' => NonRecordPage::route('/'),
        ];
    }
}
```

`TestRecordPage.php`:

```php
<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\Filament;

use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

final class TestRecordPage extends Page
{
    use InteractsWithRecord;

    protected static string $resource = TestRecordResource::class;
    protected string $view = 'filament-test-record-page';
}
```

`NonRecordPage.php`:

```php
<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\Filament;

use Filament\Resources\Pages\Page;

final class NonRecordPage extends Page
{
    protected static string $resource = TestRecordResource::class;
    protected string $view = 'filament-test-non-record-page';
}
```

- [ ] **Step 2: Write RED real-Filament integration test**

Create `FilamentRecordPageIntegrationTest.php` extending `Orchestra\Testbench\TestCase`.

In `defineEnvironment()` configure SQLite `:memory:`. In the test create the table:

```php
$this->app['db']->connection()->getSchemaBuilder()->create(
    'filament_test_records',
    static function (Blueprint $table): void {
        $table->integer('id')->primary();
        $table->string('name');
    },
);
```

Insert/load a real model:

```php
$record = TestRecord::query()->create(['id' => 77, 'name' => 'Visible only in app']);
```

Instantiate the real Filament page and set its public Filament `record` property to the loaded model, matching Filament's `InteractsWithRecord` public contract:

```php
$page = new TestRecordPage();
$page->record = $record;

self::assertSame($record, $page->getRecord());

$resolved = (new FilamentRecordContextResolver($page))->resolve();
self::assertNotNull($resolved);
self::assertSame($record, $resolved->value);
```

Also instantiate `new NonRecordPage()` and assert resolver returns null.

- [ ] **Step 3: Run real-Filament integration test**

```bash
cd packages/laravel
vendor/bin/phpunit tests/Integration/FilamentRecordPageIntegrationTest.php
```

Expected: PASS against installed Filament 5. If Filament's actual public page API differs, stop and revise the design; do not add reflection or private-state fallback.

- [ ] **Step 4: Add a no-requery assertion**

Attach a database query listener/counter after loading the model, then call the resolver and assert it emits zero additional queries:

```php
$queries = 0;
$this->app['db']->listen(static function () use (&$queries): void {
    $queries++;
});

$resolved = (new FilamentRecordContextResolver($page))->resolve();

self::assertNotNull($resolved);
self::assertSame(0, $queries, 'SurfaceRelay must not re-query the active Filament record.');
```

If framework boot emits unrelated queries, scope the listener immediately around resolver invocation and reset the counter before the call.

- [ ] **Step 5: Run focused integration and relevant unit tests**

```bash
cd packages/laravel
vendor/bin/phpunit \
  tests/Unit/FilamentRecordContextResolverTest.php \
  tests/Unit/FilamentTrustedContextComposerTest.php \
  tests/Integration/FilamentRecordTrustControlsIntegrationTest.php \
  tests/Integration/FilamentRecordPageIntegrationTest.php
```

Expected: all PASS.

- [ ] **Step 6: Commit real Filament compatibility proof**

```bash
git add packages/laravel/tests/Fixtures/Filament \
        packages/laravel/tests/Integration/FilamentRecordPageIntegrationTest.php
git commit -m "test(filament): prove real record page context"
```

---

### Task 6: Full Regression, Scope Audit and Review Preparation

**Files:**
- Modify: `STATUS.md`
- Modify: `REVIEW_REQUEST.md`
- Modify: `TASKS.md` only at the T-501 marker
- No additional production changes unless a failing test demonstrates a T-501 defect.

**Interfaces:**
- Consumes: completed T-501 adapter and all repository test suites.
- Produces: reviewable exact-head evidence for T-501 while M5 remains in progress and T-502..T-505 remain untouched.

- [ ] **Step 1: Run focused PHP suite once more**

```bash
cd packages/laravel
vendor/bin/phpunit \
  tests/Unit/FilamentDependencyPolicyTest.php \
  tests/Unit/FilamentRecordContextResolverTest.php \
  tests/Unit/FilamentTrustedContextComposerTest.php \
  tests/Integration/FilamentRecordTrustControlsIntegrationTest.php \
  tests/Integration/FilamentRecordPageIntegrationTest.php
```

Expected: PASS.

- [ ] **Step 2: Run the entire Laravel package suite**

```bash
cd packages/laravel
composer test
```

Expected: all tests PASS. Record exact test/assertion counts from fresh output.

- [ ] **Step 3: Run PHP lint and Composer validation**

```bash
find packages/laravel -name '*.php' -not -path '*/vendor/*' -print0 | xargs -0 -n1 php -l
cd packages/laravel && composer validate --strict
```

Expected: no lint/Composer validation errors.

- [ ] **Step 4: Run browser isolation regression**

```bash
cd packages/browser-runtime
npm ci
npm run typecheck
npm test
```

Expected: typecheck PASS and existing Vitest suite PASS. T-501 must not modify browser runtime files.

- [ ] **Step 5: Run contract validator**

```bash
python scripts/validate.py
```

Expected: PASS with frozen protocol fixtures unchanged.

- [ ] **Step 6: Verify frozen protocol and scope diff**

Run:

```bash
git diff --name-only 66afcc22704bfe3b317f2894b7737cf18d248e34...HEAD
```

Required assertions:

- no path under `spec/0.1/**`;
- no path under `packages/browser-runtime/**`;
- no path under `packages/laravel/src/Livewire/**`;
- no Filament RuntimeBinding driver/target class;
- no route/request record resolver;
- production additions limited to `packages/laravel/src/Filament/Context/**` plus dependency/CI metadata.

- [ ] **Step 7: Update operational review records**

Update `STATUS.md` to state:

```text
M5 — IN PROGRESS
T-501 — implementation complete / self-reviewed / external review pending
T-502..T-505 — not started
```

Record:

- branch/base;
- implementation exact head;
- exact CI workflow ID after push;
- PHP test/assertion counts;
- browser result;
- contract validator result;
- D-019/D-048 accepted;
- no `spec/0.1/**` diff;
- no new Filament execution driver.

Update `REVIEW_REQUEST.md` with review focus:

1. caller/request data cannot manufacture `current_record`;
2. exact page public `getRecord()` only;
3. exact model object retained, no re-query/reload;
4. record scope key contains only domain-separated model class/key-name/typed-key identity;
5. tenant stays independent;
6. confirmation receipt record mismatch fails closed;
7. idempotency intent separates records;
8. audit persists provider only, never record/key/hash;
9. Filament remains dev-only/optional to package consumers;
10. existing Livewire execution path remains the only binding path.

In `TASKS.md`, change only:

```text
- T-501 — Record context binding.
```

to:

```text
- T-501 — Record context binding — IMPLEMENTED / REVIEW PENDING.
```

Do not mark M5 complete and do not alter T-502..T-505.

- [ ] **Step 8: Commit review-prep records**

```bash
git add STATUS.md REVIEW_REQUEST.md TASKS.md
git commit -m "docs(review): prepare T-501 record context review"
```

- [ ] **Step 9: Push and require exact-head CI**

Push `feat/filament-record-context-binding` and wait for the repository `validate` workflow on that exact SHA.

Required jobs: 7 total, all success:

- contract;
- php-lint;
- browser;
- PHP 8.3 + Illuminate 12 + Testbench 10;
- PHP 8.3 + Illuminate 13 + Testbench 11;
- PHP 8.4 + Illuminate 12 + Testbench 10;
- PHP 8.4 + Illuminate 13 + Testbench 11.

Do not claim review-ready until all seven are green on the exact branch head.

- [ ] **Step 10: Self-review acceptance checklist before PR**

Confirm all items:

1. exact Filament Page object is injected by trusted adapter code;
2. non-record Page yields absence;
3. record-aware invalid state fails static-safe/unchained;
4. exact Eloquent object retained;
5. no route/request/DB re-resolution;
6. scope key uses domain-separated canonical class/key-name/typed-key only;
7. tenant not embedded in record identity;
8. actor/tenant composition unchanged;
9. metadata/input spoofing ineffective;
10. confirmation record mismatch rejected;
11. idempotency fingerprints separate record identities;
12. audit event contains only current-record requirement/provider, not value/hash;
13. Filament 5 resolves on all existing PHP/Illuminate rows;
14. production package has no hard Filament dependency;
15. no new execution driver/path;
16. frozen `spec/0.1/**` unchanged;
17. T-502..T-505 untouched.

Only after this checklist and exact-head CI are green should a T-501 PR/review gate be opened.
