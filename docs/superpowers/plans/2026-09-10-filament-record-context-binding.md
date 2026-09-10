# T-501 Filament Record Context Binding Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a Filament 5 trusted-context adapter that supplies the exact active record page's Eloquent model as `current_record` without introducing a second execution driver or any caller-controlled record resolution path.

**Architecture:** Filament remains a UI/trusted-context adapter on top of the existing Livewire RuntimeBinding execution path. `FilamentRecordContextResolver` accepts one exact trusted `Filament\Resources\Pages\Page`, reads only its public `getRecord()` capability, returns the exact Eloquent model plus a domain-separated hashed confirmation scope key, and never re-queries by route/input ID. `FilamentTrustedContextComposer` delegates actor/tenant resolution to the existing `TrustedContextComposer` and appends only the resolved `current_record` entry.

**Tech Stack:** PHP 8.3/8.4, Laravel/Illuminate 12/13, Livewire 4.4+, Filament 5.x, Orchestra Testbench 10/11, PHPUnit 11, MySQL 8.4 CI service.

**Spec:** `docs/superpowers/specs/2026-09-10-filament-record-context-binding-design.md`

## Global Constraints

- Implementation branch: `feat/filament-record-context-binding`, forked from `main@66afcc22704bfe3b317f2894b7737cf18d248e34`.
- `spec/0.1/**` remains unchanged.
- Do not introduce a `filament` RuntimeBinding driver; execution remains on the existing exact `livewire` binding/driver path.
- `current_record` authority comes only from the exact trusted active Filament resource-page instance supplied to the adapter.
- Never resolve/reload a record from action input, `InvocationContext::metadata`, route/query/request data, binding ID, confirmation receipt, or idempotency key.
- `ResolvedTrustedValue::value` is the exact Eloquent `Model` instance returned by the page.
- `confirmationScopeKey` is SHA-256 over domain-separated canonical `{modelClass,keyName,keyValue}` identity only; no model attributes, tenant identity, route values, timestamps, or user identity participate.
- Tenant and current-record identity remain independent trusted dimensions. Never fold tenant identity into the record scope key.
- Filament remains optional for production consumers: `filament/filament:^5.0` belongs in `require-dev`, not `require`.
- `SurfaceRelayServiceProvider` must not eagerly resolve/register Filament services when Filament is absent.
- Inconsistent record-aware Filament state fails closed with static-safe adapter exceptions and no chained underlying exception.
- T-502 selection, T-503 filters, T-504 confirmation UI bridge, and T-505 order-operations demo remain out of scope.

---

## File Structure

### New production files

- `packages/laravel/src/Filament/Context/FilamentRecordContextResolver.php` — exact page → `ResolvedTrustedValue|null`; owns record identity derivation.
- `packages/laravel/src/Filament/Context/FilamentTrustedContextComposer.php` — actor/tenant composer + Filament record resolver → trusted-context entries.
- `packages/laravel/src/Filament/Context/InvalidFilamentRecordContext.php` — static-safe fail-closed adapter exception factory.

### New test/support files

- `packages/laravel/tests/Unit/FilamentDependencyPolicyTest.php`
- `packages/laravel/tests/Unit/FilamentRecordContextResolverTest.php`
- `packages/laravel/tests/Unit/FilamentTrustedContextComposerTest.php`
- `packages/laravel/tests/Integration/FilamentRecordTrustControlsIntegrationTest.php`
- `packages/laravel/tests/Integration/FilamentRecordPageIntegrationTest.php`
- `packages/laravel/tests/Fixtures/Filament/TestRecord.php`
- `packages/laravel/tests/Fixtures/Filament/TestRecordResource.php`
- `packages/laravel/tests/Fixtures/Filament/TestRecordPage.php`
- `packages/laravel/tests/Fixtures/Filament/NonRecordPage.php`

### Modified files

- `packages/laravel/composer.json`
- `.github/workflows/validate.yml`
- `STATUS.md`
- `REVIEW_REQUEST.md`
- `TASKS.md` only at the T-501 marker during review prep.

---

### Task 1: Lock Filament 5 as an Optional Test Dependency Across the Existing Matrix

**Files:**
- Modify: `packages/laravel/composer.json`
- Modify: `.github/workflows/validate.yml`
- Create: `packages/laravel/tests/Unit/FilamentDependencyPolicyTest.php`

**Interfaces:**
- Consumes: current Composer package metadata and four-way PHP/Illuminate/Testbench CI matrix.
- Produces: Filament 5 available to tests while production consumers remain free of a hard Filament dependency.

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
    public function test_filament_is_dev_only_and_provider_remains_framework_neutral(): void
    {
        $root = dirname(__DIR__, 2);
        $composer = json_decode(
            file_get_contents($root . '/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertArrayNotHasKey('filament/filament', $composer['require'] ?? []);
        self::assertSame('^5.0', $composer['require-dev']['filament/filament'] ?? null);

        $provider = file_get_contents($root . '/src/SurfaceRelayServiceProvider.php');
        self::assertIsString($provider);
        self::assertStringNotContainsString('Filament\\', $provider);
    }
}
```

- [ ] **Step 2: Run focused RED**

```bash
cd packages/laravel
vendor/bin/phpunit tests/Unit/FilamentDependencyPolicyTest.php
```

Expected: FAIL because `require-dev.filament/filament` is absent.

- [ ] **Step 3: Add the dev-only dependency**

Add exactly this entry to `require-dev` in `packages/laravel/composer.json`:

```json
"filament/filament": "^5.0"
```

Do not add Filament to `require`, Laravel provider auto-discovery metadata, or `SurfaceRelayServiceProvider`.

- [ ] **Step 4: Make the CI dependency requirements explicit**

In `.github/workflows/validate.yml`, change:

```yaml
extensions: pdo_mysql
```

to:

```yaml
extensions: pdo_mysql, intl
```

Change the dev-constraint step to:

```yaml
- name: Constrain Testbench, Livewire and Filament integration dependencies
  working-directory: packages/laravel
  run: >-
    composer require --dev --no-interaction --no-progress --no-update
    "orchestra/testbench:${{ matrix.testbench-version }}"
    "livewire/livewire:^4.4"
    "filament/filament:^5.0"
```

Do not remove/split a matrix row to make Filament resolve.

- [ ] **Step 5: Resolve and verify locally**

```bash
cd packages/laravel
composer update --no-interaction --no-progress --prefer-dist
vendor/bin/phpunit tests/Unit/FilamentDependencyPolicyTest.php
composer validate --strict
```

Expected: dependency resolution succeeds and the test PASSes. If a current matrix combination cannot resolve Filament 5, stop and return to the design gate rather than narrowing existing Laravel support.

- [ ] **Step 6: Commit**

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
- Produces: `FilamentRecordContextResolver::resolve(): ?ResolvedTrustedValue` with provider `filament.current_record` and a 64-character lowercase SHA-256 scope key.

- [ ] **Step 1: Write RED exact-object/absence tests without overriding Filament constructors**

Use test-local fixtures:

```php
final class ResolverRecord extends \Illuminate\Database\Eloquent\Model
{
    protected $guarded = [];
    public $timestamps = false;
}

final class ResolverResource extends \Filament\Resources\Resource
{
    protected static ?string $model = ResolverRecord::class;

    public static function getPages(): array
    {
        return [];
    }
}

final class ResolverNonRecordPage extends \Filament\Resources\Pages\Page
{
    protected static string $resource = ResolverResource::class;
    protected string $view = 'resolver-non-record';
}

final class ResolverRecordPage extends \Filament\Resources\Pages\Page
{
    protected static string $resource = ResolverResource::class;
    protected string $view = 'resolver-record';

    public mixed $resolvedRecord = null;

    public function getRecord(): mixed
    {
        return $this->resolvedRecord;
    }
}
```

Tests:

```php
public function test_non_record_page_resolves_to_absence(): void
{
    self::assertNull((new FilamentRecordContextResolver(new ResolverNonRecordPage()))->resolve());
}

public function test_record_page_returns_exact_model_instance_and_minimal_provenance(): void
{
    $record = new ResolverRecord();
    $record->setRawAttributes(['id' => 41, 'name' => 'SECRET-ATTRIBUTE']);
    $record->exists = true;

    $page = new ResolverRecordPage();
    $page->resolvedRecord = $record;

    $resolved = (new FilamentRecordContextResolver($page))->resolve();

    self::assertNotNull($resolved);
    self::assertSame($record, $resolved->value);
    self::assertSame('filament.current_record', $resolved->provenance->provider);
    self::assertNull($resolved->provenance->reference);
    self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $resolved->confirmationScopeKey);
}
```

- [ ] **Step 2: Run focused RED**

```bash
cd packages/laravel
vendor/bin/phpunit tests/Unit/FilamentRecordContextResolverTest.php
```

Expected: FAIL because `FilamentRecordContextResolver` does not exist.

- [ ] **Step 3: Add static-safe adapter failure type**

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

- [ ] **Step 4: Implement exact-page resolution**

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
            $encodedIdentity = $this->canonicalizer->encode([
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
            confirmationScopeKey: hash('sha256', self::IDENTITY_DOMAIN . $encodedIdentity),
        );
    }
}
```

Forbidden implementation dependencies: `request()`, route parameters, `resolveRecord()`, database queries, reflection, protected `$record`, component-name lookup, DOM data, or model reload.

- [ ] **Step 5: Verify initial GREEN**

```bash
cd packages/laravel
vendor/bin/phpunit tests/Unit/FilamentRecordContextResolverTest.php
```

Expected: PASS for the first two tests.

- [ ] **Step 6: Add adversarial identity/failure tests**

Add helpers that set raw attributes and `$record->exists = true`, then prove:

```php
self::assertSame($this->scopeKey($this->record(123, 'alpha')), $this->scopeKey($this->record(123, 'beta')));
self::assertNotSame($this->scopeKey($this->record(123)), $this->scopeKey($this->record(124)));
self::assertNotSame($this->scopeKey($this->record(123)), $this->scopeKey($this->record('123')));
self::assertNotSame($this->scopeKey($this->record(123)), $this->scopeKey($this->otherModel(123)));
self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $this->scopeKey($this->record(0)));
```

Add fail-closed cases for:

- `getRecord()` returns `null`;
- non-Model return;
- unsaved model (`exists=false`);
- null key;
- empty-string key;
- array/object key;
- empty key name;
- `getRecord()` throws `RuntimeException('SECRET-RECORD-ERROR')`.

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

Also assert no exception/provenance text contains the raw record key or `SECRET-ATTRIBUTE`.

- [ ] **Step 7: Run focused GREEN**

```bash
cd packages/laravel
vendor/bin/phpunit tests/Unit/FilamentRecordContextResolverTest.php
```

Expected: all tests PASS without loosening identity/failure assertions.

- [ ] **Step 8: Commit**

```bash
git add packages/laravel/src/Filament/Context/InvalidFilamentRecordContext.php \
        packages/laravel/src/Filament/Context/FilamentRecordContextResolver.php \
        packages/laravel/tests/Unit/FilamentRecordContextResolverTest.php
git commit -m "feat(filament): resolve trusted current record"
```

---

### Task 3: Compose Current Record with Existing Actor and Tenant Context

**Files:**
- Create: `packages/laravel/src/Filament/Context/FilamentTrustedContextComposer.php`
- Create: `packages/laravel/tests/Unit/FilamentTrustedContextComposerTest.php`

**Interfaces:**
- Consumes: `TrustedContextComposer::resolve(): array`, `FilamentRecordContextResolver::resolve(): ?ResolvedTrustedValue`.
- Produces: `FilamentTrustedContextComposer::resolve(): list<TrustedContextEntry>` with actor → tenant → current-record ordering when present.

- [ ] **Step 1: Write RED composition tests**

Use zero-argument resolver fakes:

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

Create the base composer with actor/tenant values and a record page/resolver. Assert:

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

Assert the current-record entry retains the exact model object, provider and scope key. A non-record page must return exactly the base actor/tenant list.

- [ ] **Step 2: Run focused RED**

```bash
cd packages/laravel
vendor/bin/phpunit tests/Unit/FilamentTrustedContextComposerTest.php
```

Expected: FAIL because `FilamentTrustedContextComposer` does not exist.

- [ ] **Step 3: Implement the narrow wrapper**

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

Do not generalize `TrustedContextComposer` into a plugin registry and do not add selection support.

- [ ] **Step 4: Prove InvocationContext ordering and metadata spoof resistance**

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

- [ ] **Step 5: Run focused GREEN**

```bash
cd packages/laravel
vendor/bin/phpunit tests/Unit/FilamentTrustedContextComposerTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add packages/laravel/src/Filament/Context/FilamentTrustedContextComposer.php \
        packages/laravel/tests/Unit/FilamentTrustedContextComposerTest.php
git commit -m "feat(filament): compose current record context"
```

---

### Task 4: Prove Confirmation, Idempotency and Audit Bind the Filament Record Correctly

**Files:**
- Create: `packages/laravel/tests/Integration/FilamentRecordTrustControlsIntegrationTest.php`
- No production modifications expected.

**Interfaces:**
- Consumes: `FilamentRecordContextResolver`, `InvocationContext`, `ActionPipelineState`, `ConfirmationScopeHasher`, `ConfirmationService`, `IdempotencyIntentHasher`, `AuditEventFactory`.
- Produces: proof that record identity participates in existing trust controls without leaking record material into audit.

- [ ] **Step 1: Write a deliberate RED harness guard**

```php
public function test_filament_record_trust_control_harness_is_implemented(): void
{
    self::assertTrue(
        method_exists($this, 'stateForRecord'),
        'T-501 trust-control harness must construct pipeline state from Filament current_record context.',
    );
}
```

Run it before adding the helper and record the expected single failure.

- [ ] **Step 2: Implement exact ActionDefinition fixture**

```php
private function definition(): ActionDefinition
{
    return new ActionDefinition(
        id: 'orders.refund_current',
        version: 1,
        title: 'Refund current order',
        description: 'Refund the exact current Filament order record.',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'reason' => ['type' => 'string'],
            ],
            'required' => ['reason'],
            'additionalProperties' => false,
        ],
        scope: ActionScope::PageScoped,
        effect: ActionEffect::ExternalSideEffect,
        risk: ActionRisk::Consequential,
        idempotency: IdempotencyPolicy::RequiredKey,
        outputSensitivity: OutputSensitivity::Normal,
        outputContentTrust: OutputContentTrust::TrustedApplicationData,
        contextRequirements: [
            ContextRequirement::Tenant,
            ContextRequirement::CurrentRecord,
            ContextRequirement::HumanConfirmation,
        ],
    );
}
```

- [ ] **Step 3: Implement the pipeline-state helper**

For each record, create a trusted Page + resolver result and construct:

```php
private function stateForRecord(Model $record, string $tenant = 'tenant-a'): ActionPipelineState
{
    $page = new TrustControlRecordPage();
    $page->resolvedRecord = $record;

    $recordValue = (new FilamentRecordContextResolver($page))->resolve();
    self::assertNotNull($recordValue);

    return new ActionPipelineState(
        definition: $this->definition(),
        input: ['reason' => 'customer-request'],
        context: new InvocationContext(
            surface: 'filament',
            correlationId: 'corr-filament-record',
            trustedContext: [
                new TrustedContextEntry(
                    ContextRequirement::Tenant,
                    $tenant,
                    new ContextProvenance('test.tenant'),
                    'tenant-scope:' . $tenant,
                ),
                new TrustedContextEntry(
                    ContextRequirement::CurrentRecord,
                    $recordValue->value,
                    $recordValue->provenance,
                    $recordValue->confirmationScopeKey,
                ),
            ],
        ),
        bindingId: 'livewire-binding-record-page',
    );
}
```

The test-local `TrustControlRecordPage` uses the same `Page`/public `getRecord()` shape as Task 2 and no constructor override.

- [ ] **Step 4: Prove confirmation/idempotency identity behavior**

```php
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
```

- [ ] **Step 5: Prove tenant is independent from record identity**

Resolve the same record under tenants A and B. Assert the resolver-generated record scope key is the same, while both full confirmation and idempotency fingerprints differ because the separate tenant entry differs.

- [ ] **Step 6: Implement a minimal faithful in-memory ConfirmationStore test double**

```php
final class MemoryConfirmationStore implements ConfirmationStore
{
    /** @var array<string, ConfirmationRecord> */
    private array $records = [];

    public function createPending(string $tokenHash, ConfirmationRecord $record, int $ttlSeconds): bool
    {
        if (isset($this->records[$tokenHash])) {
            return false;
        }
        $this->records[$tokenHash] = $record;
        return true;
    }

    public function approvePending(string $tokenHash, int $now, int $receiptExpiresAt): bool
    {
        $record = $this->records[$tokenHash] ?? null;
        if (
            $record === null
            || $record->state !== ConfirmationRecordState::Pending
            || $now >= $record->challengeExpiresAt
        ) {
            return false;
        }

        $this->records[$tokenHash] = new ConfirmationRecord(
            ConfirmationRecordState::Approved,
            $record->scopeFingerprint,
            $record->summary,
            $record->issuedAt,
            $record->challengeExpiresAt,
            $receiptExpiresAt,
        );
        return true;
    }

    public function consumeApproved(string $tokenHash, string $expectedScopeFingerprint, int $now): bool
    {
        $record = $this->records[$tokenHash] ?? null;
        if (
            $record === null
            || $record->state !== ConfirmationRecordState::Approved
            || $record->scopeFingerprint !== $expectedScopeFingerprint
            || $record->receiptExpiresAt === null
            || $now >= $record->receiptExpiresAt
        ) {
            return false;
        }

        unset($this->records[$tokenHash]);
        return true;
    }
}
```

Use fixed test doubles:

```php
final class FixedConfirmationClock implements ConfirmationClock
{
    public function now(): int { return 1_800_000_000; }
}

final class FixedConfirmationTokenGenerator implements ConfirmationTokenGenerator
{
    public function generate(): string
    {
        return str_repeat('A', 43);
    }
}
```

- [ ] **Step 7: Prove receipt A cannot authorize record B**

```php
$scopeA = (new ConfirmationScopeHasher())->fingerprint($this->stateForRecord($recordA));
$scopeB = (new ConfirmationScopeHasher())->fingerprint($this->stateForRecord($recordB));

$service = new ConfirmationService(
    new MemoryConfirmationStore(),
    new FixedConfirmationClock(),
    new FixedConfirmationTokenGenerator(),
);

$challenge = $service->issueChallenge($scopeA, 'Approve refund');
$receipt = $service->approveChallenge($challenge->challengeId);
self::assertNotNull($receipt);

self::assertFalse($service->consumeReceipt($receipt, $scopeB));
self::assertTrue($service->consumeReceipt($receipt, $scopeA));
```

- [ ] **Step 8: Prove T-404 audit minimization with exact current types**

Use:

```php
final class FixedAuditClock implements AuditClock
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-09-10T00:00:00.123456Z');
    }
}
```

Create:

```php
$state = $this->stateForRecord($recordA);
$event = (new AuditEventFactory(new FixedAuditClock()))
    ->create(ActionPipelineOutcome::completed($state));

$manifest = array_map(
    static fn (AuditTrustedContextEntry $entry): array => [
        'requirement' => $entry->requirement->value,
        'provider' => $entry->provider,
    ],
    $event->trustedContextManifest,
);

self::assertContains(
    ['requirement' => 'current_record', 'provider' => 'filament.current_record'],
    $manifest,
);
```

Serialize an explicit test projection of the semantic audit fields/manifest and assert it excludes unique markers placed in the record primary key, model attributes, and `confirmationScopeKey`. Do not change `AuditEventFactory` or the DB audit schema.

- [ ] **Step 9: Run trust-control integration GREEN**

```bash
cd packages/laravel
vendor/bin/phpunit tests/Integration/FilamentRecordTrustControlsIntegrationTest.php
```

Expected: PASS with no non-Filament production changes.

- [ ] **Step 10: Commit**

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
- Consumes: actual Filament 5 `Resource`, `Page`, `InteractsWithRecord`, Eloquent and Orchestra Testbench.
- Produces: compatibility proof that SurfaceRelay uses Filament's real public `getRecord(): Model` API and performs no re-query.

- [ ] **Step 1: Create real Filament fixtures**

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
            'index' => NonRecordPage::route('/'),
            'record' => TestRecordPage::route('/{record}'),
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

- [ ] **Step 2: Write real Filament/Testbench integration**

Create `FilamentRecordPageIntegrationTest.php` extending `Orchestra\Testbench\TestCase`. Configure SQLite `:memory:` in `defineEnvironment()`.

Create schema:

```php
$this->app['db']->connection()->getSchemaBuilder()->create(
    'filament_test_records',
    static function (Blueprint $table): void {
        $table->integer('id')->primary();
        $table->string('name');
    },
);
```

Load a real persisted model and set Filament's public locked record property:

```php
$record = TestRecord::query()->create([
    'id' => 77,
    'name' => 'Visible only in app',
]);

$page = new TestRecordPage();
$page->record = $record;

self::assertSame($record, $page->getRecord());

$resolved = (new FilamentRecordContextResolver($page))->resolve();
self::assertNotNull($resolved);
self::assertSame($record, $resolved->value);
```

Also assert:

```php
self::assertNull((new FilamentRecordContextResolver(new NonRecordPage()))->resolve());
```

- [ ] **Step 3: Prove resolver performs zero database queries**

After the model is loaded and page assigned:

```php
$queries = 0;
$this->app['db']->listen(static function () use (&$queries): void {
    $queries++;
});

$queries = 0;
$resolved = (new FilamentRecordContextResolver($page))->resolve();

self::assertNotNull($resolved);
self::assertSame(0, $queries, 'SurfaceRelay must not re-query the active Filament record.');
```

- [ ] **Step 4: Run focused real-Filament integration**

```bash
cd packages/laravel
vendor/bin/phpunit tests/Integration/FilamentRecordPageIntegrationTest.php
```

Expected: PASS against installed Filament 5. If the public API differs, stop and revise the design; do not add reflection, protected-state access, or private Livewire request fallback.

- [ ] **Step 5: Run all T-501 focused tests together**

```bash
cd packages/laravel
vendor/bin/phpunit \
  tests/Unit/FilamentDependencyPolicyTest.php \
  tests/Unit/FilamentRecordContextResolverTest.php \
  tests/Unit/FilamentTrustedContextComposerTest.php \
  tests/Integration/FilamentRecordTrustControlsIntegrationTest.php \
  tests/Integration/FilamentRecordPageIntegrationTest.php
```

Expected: all PASS.

- [ ] **Step 6: Commit**

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
- Modify: `TASKS.md` only at T-501 marker.

**Interfaces:**
- Consumes: completed T-501 adapter and repository suites.
- Produces: exact-head evidence for external review while M5 remains in progress.

- [ ] **Step 1: Run full Laravel suite**

```bash
cd packages/laravel
composer test
```

Expected: PASS. Record exact test/assertion counts from fresh output.

- [ ] **Step 2: Run lint and Composer validation**

```bash
find packages/laravel -name '*.php' -not -path '*/vendor/*' -print0 | xargs -0 -n1 php -l
cd packages/laravel && composer validate --strict
```

Expected: PASS.

- [ ] **Step 3: Run browser isolation**

```bash
cd packages/browser-runtime
npm ci
npm run typecheck
npm test
```

Expected: PASS. T-501 must have no browser-runtime diff.

- [ ] **Step 4: Run frozen contract validator**

```bash
python scripts/validate.py
```

Expected: PASS.

- [ ] **Step 5: Audit base-to-head scope**

```bash
git diff --name-only 66afcc22704bfe3b317f2894b7737cf18d248e34...HEAD
```

Required:

- no `spec/0.1/**` path;
- no `packages/browser-runtime/**` path;
- no `packages/laravel/src/Livewire/**` path;
- no new Filament RuntimeBinding driver/target;
- no route/request record resolver;
- production additions limited to `packages/laravel/src/Filament/Context/**` plus dependency/CI metadata.

- [ ] **Step 6: Update status/review records**

`STATUS.md` must state:

```text
M5 — IN PROGRESS
T-501 — implementation complete / self-reviewed / external review pending
T-502..T-505 — not started
```

Record branch/base, implementation head, CI workflow, PHP counts, browser result, contract result, D-019/D-048, no protocol diff and no new execution driver.

`REVIEW_REQUEST.md` review focus:

1. caller/request data cannot manufacture `current_record`;
2. exact injected Filament Page + public `getRecord()` only;
3. exact model object retained and no re-query;
4. scope key contains only domain-separated model class/key-name/typed-key identity;
5. tenant remains independent;
6. receipt for record A fails against record B;
7. idempotency intent separates record identities;
8. T-404 audit stores provider only, never record/key/hash;
9. Filament remains dev-only/optional;
10. existing Livewire execution path remains the only binding path.

Change only this T-501 task-board line:

```text
- T-501 — Record context binding.
```

to:

```text
- T-501 — Record context binding — IMPLEMENTED / REVIEW PENDING.
```

Do not mark M5 complete or alter T-502..T-505.

- [ ] **Step 7: Commit review-prep records**

```bash
git add STATUS.md REVIEW_REQUEST.md TASKS.md
git commit -m "docs(review): prepare T-501 record context review"
```

- [ ] **Step 8: Require exact-head CI**

Push the branch and require all seven jobs on the exact SHA:

- contract;
- php-lint;
- browser;
- PHP 8.3 + Illuminate 12 + Testbench 10;
- PHP 8.3 + Illuminate 13 + Testbench 11;
- PHP 8.4 + Illuminate 12 + Testbench 10;
- PHP 8.4 + Illuminate 13 + Testbench 11.

Do not claim review-ready until all seven are success.

- [ ] **Step 9: Final self-review checklist before PR**

Confirm all 17 items:

1. exact Filament Page injected by trusted adapter code;
2. non-record Page yields absence;
3. record-aware invalid state fails static-safe/unchained;
4. exact Eloquent object retained;
5. no route/request/DB re-resolution;
6. scope key uses domain-separated canonical class/key-name/typed-key only;
7. tenant not embedded in record identity;
8. actor/tenant composition unchanged;
9. metadata/input spoofing ineffective;
10. confirmation record mismatch rejected;
11. idempotency fingerprints separate records;
12. audit contains only current-record requirement/provider, not value/hash;
13. Filament 5 resolves on all existing PHP/Illuminate rows;
14. production package has no hard Filament dependency/eager provider wiring;
15. no new execution driver/path;
16. frozen `spec/0.1/**` unchanged;
17. T-502..T-505 untouched.

Only after this checklist and exact-head CI are green should the T-501 PR/review gate be opened.
