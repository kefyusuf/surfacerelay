# T-402 Idempotency Store Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement bounded durable server-side idempotency for the Laravel reference runtime so protected retries cannot repeat a side effect within the configured retention window and exact completed retries can safely replay prior execution output.

**Architecture:** Add a focused `Idempotency` package boundary with deterministic trusted-scope hashing, a two-phase `in_progress -> completed | indeterminate` state machine, and a database-backed reference store. Run idempotency preflight after validation/authorization but before confirmation so an already-completed exact retry can replay without consuming a new confirmation receipt; claim a fresh execution only after confirmation succeeds. Persist only wire-safe pre-output-policy executor output and rerun output policy on every replay.

**Tech Stack:** PHP 8.3+, Illuminate 12/13 contracts/support/validation/database, PHPUnit 11, Laravel Query Builder transactions and pessimistic row locks, `Illuminate\Database\UniqueConstraintViolationException`, SHA-256, deterministic JSON, SQLite-backed store tests, existing GitHub Actions 7-job validation matrix.

**Spec:** `docs/superpowers/specs/2026-09-07-idempotency-store-design.md`

## Global Constraints

- Work only on `T-402`; do not implement T-403 output redaction, T-404 structured audit persistence, or T-504 confirmation UI.
- Base is `main@b94ed83497e213c155ae0276264e954b9acd3ac3`; feature branch is `feat/idempotency-store`.
- `spec/0.1` schemas and wire shapes stay byte-for-byte unchanged.
- Idempotency is bounded deduplication, not permanent/global exactly-once execution.
- Policies remain exactly `none`, `recommended_key`, and `required_key`.
- A usable key is exactly 1..240 Unicode characters; do not trim, normalize, case-fold, or treat an invalid non-null key as absent.
- Raw caller idempotency keys must never be persisted by the reference store.
- Lookup namespace is exact action ID/version plus trusted authority partition plus caller key hash input. Different action versions and different trusted authority partitions may reuse the same raw key independently.
- Trusted authority partition contains tenant identity when present and actor identity when present. Browser-session identity is used as the partition only when both tenant and actor are absent. If tenant, actor, and browser session are all absent, use an explicit `global` partition for that exact action version.
- Intent fingerprint includes validated input and present actor/tenant/current-record/current-selection/browser-session trusted identity; it excludes correlation ID, generic metadata, raw key, confirmation receipt, runtime HumanConfirmation, surface, and binding ID.
- Surface/binding changes alone may replay the same completed business intent. Browser-session changes remain intent changes and therefore conflict when the authority partition remains the same.
- Reuse T-401's existing `confirmationScopeKey` as the trusted stable identity source without renaming that PHP property in T-402.
- Confirmation fingerprint behavior must remain regression-identical after canonicalizer extraction.
- Internal pipeline order becomes `input_validation -> authorization -> idempotency -> confirmation -> execution -> output_policy`.
- Authorization must rerun before every completed replay.
- Completed replay skips confirmation and execution only after exact key+intent match and must rerun output policy.
- Pre-materialized `HumanConfirmation` remains a fail-closed configuration violation even when completed replay skips the confirmation stage.
- Fresh execution is not claimed until confirmation has passed.
- No database transaction or row lock may be held across application executor code.
- Default retention is 86400 seconds. Records are active only when `now < expiresAt`; equality is expired. Replay does not refresh retention.
- Active `in_progress`, active `indeterminate`, conflict, and corrupt/unavailable storage all fail closed without executing again.
- Application executor exceptions never imply that the side effect did not happen; a claimed execution becomes `indeterminate` when that transition can be persisted and otherwise conservatively remains `in_progress`.
- Persist only replayable pre-output-policy values: null, bool, int, finite float, string, lists, and string-key maps recursively containing the same values. Never use PHP `serialize()` for replay data.
- A successful executor output that cannot be encoded or completed durably must not return normal success and must not reopen same-key execution.
- Public idempotency rejection details are omitted. Raw key, lookup hash, intent fingerprint, replay payload, actor/tenant identity, and database diagnostics must not be added to public result metadata/errors/provenance by T-402.
- Database wrapper exceptions must use static SurfaceRelay messages and must not chain `QueryException` / `UniqueConstraintViolationException` instances whose SQL bindings may expose key hashes or intent fingerprints.
- Add `illuminate/database` as `^12.0|^13.0`; preserve PHP 8.3/8.4 and Illuminate 12/13 CI support.
- Follow strict TDD for every trust-boundary step: write the failing test, run and observe the intended RED failure, implement minimum behavior, rerun GREEN, then commit.

## File Structure

### New shared scope files

- `packages/laravel/src/Runtime/Scope/RuntimeScopeCanonicalizer.php` — recursive deterministic canonicalization and trusted-entry identity projection.
- `packages/laravel/src/Runtime/Scope/UnrepresentableRuntimeScope.php` — path-only internal canonicalization error.

### New idempotency files

- `packages/laravel/src/Idempotency/IdempotencyKeyValidator.php`
- `packages/laravel/src/Idempotency/IdempotencyKeyHasher.php`
- `packages/laravel/src/Idempotency/IdempotencyIntentHasher.php`
- `packages/laravel/src/Idempotency/UnrepresentableIdempotencyScope.php`
- `packages/laravel/src/Idempotency/IdempotencyExecutionPlanKind.php`
- `packages/laravel/src/Idempotency/IdempotencyExecutionPlan.php`
- `packages/laravel/src/Idempotency/IdempotencyRecordState.php`
- `packages/laravel/src/Idempotency/IdempotencyRecord.php`
- `packages/laravel/src/Idempotency/IdempotencyStore.php`
- `packages/laravel/src/Idempotency/IdempotencyStoreClaimResult.php`
- `packages/laravel/src/Idempotency/IdempotencyPreflightKind.php`
- `packages/laravel/src/Idempotency/IdempotencyPreflightResult.php`
- `packages/laravel/src/Idempotency/IdempotencyClaimKind.php`
- `packages/laravel/src/Idempotency/IdempotencyClaimResult.php`
- `packages/laravel/src/Idempotency/IdempotencyClock.php`
- `packages/laravel/src/Idempotency/SystemIdempotencyClock.php`
- `packages/laravel/src/Idempotency/IdempotencyReplayCodec.php`
- `packages/laravel/src/Idempotency/UnreplayableIdempotencyOutput.php`
- `packages/laravel/src/Idempotency/IdempotencyStoreUnavailable.php`
- `packages/laravel/src/Idempotency/CorruptIdempotencyRecord.php`
- `packages/laravel/src/Idempotency/IdempotencyConfigurationViolation.php`
- `packages/laravel/src/Idempotency/IdempotencyService.php`
- `packages/laravel/src/Idempotency/IdempotencyStage.php`
- `packages/laravel/src/Idempotency/DatabaseIdempotencyStore.php`

### New confirmation helper

- `packages/laravel/src/Confirmation/ConfirmationRequirement.php` — one shared predicate used by `ConfirmationStage` and ActionBus replay guard.

### New migration artifact

- `packages/laravel/database/migrations/0000_00_00_000000_create_surfacerelay_idempotency_records.php`

### Existing production files modified

- `packages/laravel/composer.json`
- `packages/laravel/src/Confirmation/ConfirmationScopeHasher.php`
- `packages/laravel/src/Confirmation/ConfirmationStage.php`
- `packages/laravel/src/Runtime/Pipeline/ActionPipelineStage.php`
- `packages/laravel/src/Runtime/Pipeline/ActionPipelineState.php`
- `packages/laravel/src/Runtime/Pipeline/ActionExecutionStage.php`
- `packages/laravel/src/Runtime/Pipeline/ActionBus.php`
- `packages/laravel/src/Result/CoreActionErrorCode.php`
- `packages/laravel/src/Result/ActionResultNormalizer.php`

---

### Task 1: Extract shared canonicalization and add exact idempotency hashes

**Files:**
- Create: `packages/laravel/src/Runtime/Scope/RuntimeScopeCanonicalizer.php`
- Create: `packages/laravel/src/Runtime/Scope/UnrepresentableRuntimeScope.php`
- Modify: `packages/laravel/src/Confirmation/ConfirmationScopeHasher.php`
- Create: `packages/laravel/src/Idempotency/IdempotencyKeyValidator.php`
- Create: `packages/laravel/src/Idempotency/IdempotencyKeyHasher.php`
- Create: `packages/laravel/src/Idempotency/IdempotencyIntentHasher.php`
- Create: `packages/laravel/src/Idempotency/UnrepresentableIdempotencyScope.php`
- Create/Test: `packages/laravel/tests/Unit/RuntimeScopeCanonicalizerTest.php`
- Create/Test: `packages/laravel/tests/Unit/IdempotencyHashingTest.php`
- Modify/Test: `packages/laravel/tests/Unit/ConfirmationScopeHasherTest.php`

**Interfaces:**
- `RuntimeScopeCanonicalizer::canonicalize(mixed $value, string $path): mixed`
- `RuntimeScopeCanonicalizer::trustedIdentity(TrustedContextEntry $entry, string $path): array`
- `RuntimeScopeCanonicalizer::encode(mixed $value, string $path): string`
- `UnrepresentableRuntimeScope::at(string $path): self`; exception exposes readonly `string $path`.
- `IdempotencyKeyValidator::isValid(string $key): bool`
- `IdempotencyKeyHasher::hash(ActionPipelineState $state, string $rawKey): string`
- `IdempotencyIntentHasher::fingerprint(ActionPipelineState $state): string`
- `ConfirmationScopeHasher::fingerprint(ActionPipelineState $state): string` remains unchanged externally.

- [ ] **Step 1: Write RED canonicalizer tests**

```php
$canonicalizer = new RuntimeScopeCanonicalizer();

self::assertSame(
    '{"a":{"y":2,"z":3},"b":2}',
    $canonicalizer->encode(['b' => 2, 'a' => ['z' => 3, 'y' => 2]], 'scope'),
);

$this->expectException(UnrepresentableRuntimeScope::class);
$canonicalizer->encode(['amount' => INF], 'scope');
```

Also test list order, finite `1.0`, UTF-8, non-string associative keys, arbitrary object/resource/closure rejection, and trusted object identity via `confirmationScopeKey`. Exception messages may contain structural paths only.

- [ ] **Step 2: Run canonicalizer tests and verify RED**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/RuntimeScopeCanonicalizerTest.php
```

Expected RED: the new runtime-scope classes do not exist.

- [ ] **Step 3: Implement `RuntimeScopeCanonicalizer`**

```php
public function trustedIdentity(TrustedContextEntry $entry, string $path): array
{
    if ($entry->confirmationScopeKey !== null) {
        return ['scopeKey' => $entry->confirmationScopeKey];
    }

    return ['value' => $this->canonicalize($entry->value, $path)];
}
```

`canonicalize()` accepts null/bool/int/string, finite float, lists, and string-key maps. For maps, require string keys and `ksort($value, SORT_STRING)`. `encode()` uses:

```php
return json_encode(
    $this->canonicalize($value, $path),
    JSON_THROW_ON_ERROR
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_PRESERVE_ZERO_FRACTION,
);
```

Catch `JsonException` and throw `UnrepresentableRuntimeScope::at($path)`.

- [ ] **Step 4: Pin current T-401 fingerprint before refactoring the hasher**

Add a snapshot assertion to the existing standard `ConfirmationScopeHasherTest` fixture. First run the untouched current implementation and capture its exact 64-hex output. The expected value for the current fixture should be verified by PHP before modifying production code; production code must never be changed to satisfy an unverified snapshot.

- [ ] **Step 5: Refactor `ConfirmationScopeHasher` onto the canonicalizer**

Preserve exactly:

```php
private const string DOMAIN = "surfacerelay.confirmation.scope.v1\n";
```

Use optional/default construction so existing `new ConfirmationScopeHasher()` calls remain valid. Convert `UnrepresentableRuntimeScope` back to `UnrepresentableConfirmationScope::at($e->path)`.

- [ ] **Step 6: Write RED idempotency key/hash tests**

```php
$validator = new IdempotencyKeyValidator();
self::assertTrue($validator->isValid('idem-1'));
self::assertFalse($validator->isValid(''));
self::assertTrue($validator->isValid(str_repeat('x', 240)));
self::assertFalse($validator->isValid(str_repeat('x', 241)));
```

Hash tests must prove all partition and intent dimensions from Global Constraints, including cross-actor/cross-tenant/cross-action non-collision and browser-session conflict semantics.

- [ ] **Step 7: Implement `IdempotencyKeyHasher` with exact partition construction**

```php
$partition = [];
$tenant = $state->context->get(ContextRequirement::Tenant);
$actor = $state->context->get(ContextRequirement::AuthenticatedActor);

if ($tenant !== null) {
    $partition['tenant'] = $this->canonicalizer->trustedIdentity($tenant, 'partition.tenant');
}
if ($actor !== null) {
    $partition['actor'] = $this->canonicalizer->trustedIdentity($actor, 'partition.actor');
}

if ($tenant === null && $actor === null) {
    $session = $state->context->get(ContextRequirement::BrowserSession);
    if ($session !== null) {
        $partition['browser_session'] = $this->canonicalizer->trustedIdentity(
            $session,
            'partition.browser_session',
        );
    } else {
        $partition['global'] = true;
    }
}

$document = [
    'action' => [
        'id' => $state->definition->id,
        'version' => $state->definition->version,
    ],
    'partition' => $partition,
    'key' => $rawKey,
];

return hash(
    'sha256',
    "surfacerelay.idempotency.key.v1\n" . $this->canonicalizer->encode($document, 'key'),
);
```

Map `UnrepresentableRuntimeScope` to path-only `UnrepresentableIdempotencyScope`.

- [ ] **Step 8: Implement `IdempotencyIntentHasher` with exact context loop**

```php
$context = [];
foreach ([
    ContextRequirement::AuthenticatedActor,
    ContextRequirement::Tenant,
    ContextRequirement::CurrentRecord,
    ContextRequirement::CurrentSelection,
    ContextRequirement::BrowserSession,
] as $requirement) {
    $entry = $state->context->get($requirement);
    if ($entry !== null) {
        $context[$requirement->value] = $this->canonicalizer->trustedIdentity(
            $entry,
            'context.' . $requirement->value,
        );
    }
}

$document = [
    'action' => [
        'id' => $state->definition->id,
        'version' => $state->definition->version,
    ],
    'input' => $state->input,
    'context' => $context,
];

return hash(
    'sha256',
    "surfacerelay.idempotency.intent.v1\n" . $this->canonicalizer->encode($document, 'intent'),
);
```

- [ ] **Step 9: Run focused tests and verify GREEN**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/RuntimeScopeCanonicalizerTest.php tests/Unit/ConfirmationScopeHasherTest.php tests/Unit/IdempotencyHashingTest.php
```

- [ ] **Step 10: Commit Task 1**

```bash
git add packages/laravel/src/Runtime/Scope packages/laravel/src/Confirmation/ConfirmationScopeHasher.php packages/laravel/src/Idempotency packages/laravel/tests/Unit/RuntimeScopeCanonicalizerTest.php packages/laravel/tests/Unit/ConfirmationScopeHasherTest.php packages/laravel/tests/Unit/IdempotencyHashingTest.php
git commit -m "feat(laravel): add deterministic idempotency identity hashing"
```

---

### Task 2: Build typed idempotency state/service contracts and replay codec

**Files:**
- Create all Task-2 idempotency value/enum/store/service files listed in File Structure except `IdempotencyStage.php` and `DatabaseIdempotencyStore.php`.
- Create/Test: `packages/laravel/tests/Unit/IdempotencyReplayCodecTest.php`
- Create/Test: `packages/laravel/tests/Unit/IdempotencyServiceTest.php`

**Interfaces:**

```php
enum IdempotencyExecutionPlanKind: string
{
    case Bypass = 'bypass';
    case FreshAttempt = 'fresh_attempt';
    case Replay = 'replay';
}

enum IdempotencyRecordState: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Indeterminate = 'indeterminate';
}

enum IdempotencyPreflightKind: string
{
    case Fresh = 'fresh';
    case Replay = 'replay';
    case Conflict = 'conflict';
    case InProgress = 'in_progress';
    case Indeterminate = 'indeterminate';
}

enum IdempotencyClaimKind: string
{
    case Claimed = 'claimed';
    case Replay = 'replay';
    case Conflict = 'conflict';
    case InProgress = 'in_progress';
    case Indeterminate = 'indeterminate';
}
```

`IdempotencyExecutionPlan` readonly properties:

```php
public IdempotencyExecutionPlanKind $kind;
public ?string $keyHash;
public ?string $intentFingerprint;
public mixed $replayOutput;
```

Factories:

```php
IdempotencyExecutionPlan::bypass();
IdempotencyExecutionPlan::fresh(string $keyHash, string $intentFingerprint);
IdempotencyExecutionPlan::replay(string $keyHash, string $intentFingerprint, mixed $output);
```

`IdempotencyPreflightResult` readonly properties are `IdempotencyPreflightKind $kind` and `mixed $output`; factories `fresh()`, `replay(mixed $output)`, `conflict()`, `inProgress()`, `indeterminate()`.

`IdempotencyClaimResult` readonly properties are `IdempotencyClaimKind $kind` and `mixed $output`; factories `claimed()`, `replay(mixed $output)`, `conflict()`, `inProgress()`, `indeterminate()`.

`IdempotencyStoreClaimResult` readonly properties are `bool $claimed` and `IdempotencyRecord $record`; factories `claimed(IdempotencyRecord $record)` and `existing(IdempotencyRecord $record)`.

Store/service signatures:

```php
interface IdempotencyClock
{
    public function now(): int;
}

interface IdempotencyStore
{
    public function find(string $keyHash): ?IdempotencyRecord;
    public function claim(IdempotencyRecord $fresh, int $now): IdempotencyStoreClaimResult;
    public function complete(string $keyHash, string $intentFingerprint, string $outputPayload): void;
    public function markIndeterminate(string $keyHash, string $intentFingerprint): void;
}
```

- [ ] **Step 1: Write RED replay-codec tests**

Cover null, bool, int, `1.0`, strings, lists, nested string-key maps, and rejection of object/resource/closure/non-finite float/mixed-key map. Assert deterministic encoding and no PHP serialization.

- [ ] **Step 2: Run codec tests and verify RED**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/IdempotencyReplayCodecTest.php
```

- [ ] **Step 3: Implement `IdempotencyReplayCodec`**

Use `RuntimeScopeCanonicalizer` to validate/canonicalize output, JSON-encode with the same deterministic flags, and decode with:

```php
return json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
```

Map decode corruption to `CorruptIdempotencyRecord` with a static message that never includes payload bytes.

- [ ] **Step 4: Write RED record/service tests with a test-only fake store and mutable clock**

Prove:

```text
no active record -> Fresh
completed + same intent -> Replay(output)
completed + different intent -> Conflict
in_progress + same intent -> InProgress
indeterminate + same intent -> Indeterminate
now == expiresAt -> Fresh
replay does not extend expiry
fresh claim uses createdAt=clock now and expiresAt=now+86400
claim race existing completed -> Replay without new ownership
claim race existing different intent -> Conflict
claim race existing in_progress -> InProgress
claim race existing indeterminate -> Indeterminate
complete only accepts FreshAttempt plan
markIndeterminate only accepts FreshAttempt plan
retention 0 or negative -> IdempotencyConfigurationViolation
```

- [ ] **Step 5: Run service tests and verify RED**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/IdempotencyServiceTest.php
```

- [ ] **Step 6: Implement `IdempotencyRecord` invariants**

```php
if (preg_match('/^[a-f0-9]{64}$/D', $keyHash) !== 1
    || preg_match('/^[a-f0-9]{64}$/D', $intentFingerprint) !== 1) {
    throw CorruptIdempotencyRecord::invalidHashShape();
}
if ($expiresAt <= $createdAt) {
    throw CorruptIdempotencyRecord::invalidExpiry();
}
if ($state === IdempotencyRecordState::Completed && $outputPayload === null) {
    throw CorruptIdempotencyRecord::completedWithoutOutput();
}
if ($state !== IdempotencyRecordState::Completed && $outputPayload !== null) {
    throw CorruptIdempotencyRecord::unexpectedOutput();
}
```

`isActiveAt(int $now)` returns `$now < $this->expiresAt`.

- [ ] **Step 7: Implement `IdempotencyService`**

Constructor:

```php
public function __construct(
    private IdempotencyStore $store,
    private IdempotencyClock $clock,
    private IdempotencyReplayCodec $codec,
    private int $retentionSeconds = 86400,
) {
    if ($retentionSeconds < 1) {
        throw IdempotencyConfigurationViolation::invalidRetention();
    }
}
```

`preflight()` loads record, treats missing/expired as Fresh, rejects intent mismatch as Conflict, and maps exact state to Replay/InProgress/Indeterminate. `claim()` creates an `InProgress` record from the current clock, delegates atomic ownership to the store, and maps the returned active record exactly. `complete()` encodes output before store transition. `markIndeterminate()` never deletes/reopens a claim.

- [ ] **Step 8: Run Task-2 tests and verify GREEN**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/IdempotencyReplayCodecTest.php tests/Unit/IdempotencyServiceTest.php
```

- [ ] **Step 9: Commit Task 2**

```bash
git add packages/laravel/src/Idempotency packages/laravel/tests/Unit/IdempotencyReplayCodecTest.php packages/laravel/tests/Unit/IdempotencyServiceTest.php
git commit -m "feat(laravel): add idempotency state machine"
```

---

### Task 3: Add the durable database-backed store and migration

**Files:**
- Modify: `packages/laravel/composer.json`
- Create: `packages/laravel/src/Idempotency/DatabaseIdempotencyStore.php`
- Create: `packages/laravel/database/migrations/0000_00_00_000000_create_surfacerelay_idempotency_records.php`
- Create/Test: `packages/laravel/tests/Unit/DatabaseIdempotencyStoreTest.php`

**Interfaces:**

```php
public function __construct(
    Illuminate\Database\ConnectionInterface $connection,
    string $table = 'surfacerelay_idempotency_records',
)
```

- [ ] **Step 1: Add the Illuminate Database dependency**

Add:

```json
"illuminate/database": "^12.0|^13.0"
```

Run:

```bash
cd packages/laravel
composer update illuminate/database --with-all-dependencies
composer validate --strict
```

- [ ] **Step 2: Write RED SQLite store tests**

Create the test connection with `Illuminate\Database\Capsule\Manager`, use SQLite `:memory:`, and create this schema:

```php
$schema->create('surfacerelay_idempotency_records', function (Blueprint $table): void {
    $table->char('key_hash', 64)->primary();
    $table->char('intent_fingerprint', 64);
    $table->string('state', 32);
    $table->text('output_payload')->nullable();
    $table->timestamp('created_at');
    $table->timestamp('expires_at')->index();
});
```

Test first claim, duplicate active claim, expired replacement, completed/indeterminate immutability while active, exact guarded completion, exact guarded indeterminate transition, corruption rejection, raw-key absence, and fail-closed database failures.

- [ ] **Step 3: Run store tests and verify RED**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/DatabaseIdempotencyStoreTest.php
```

- [ ] **Step 4: Implement UTC timestamp mapping**

Domain records keep epoch seconds. Database rows use UTC second-precision strings:

```php
private function formatTimestamp(int $timestamp): string
{
    return gmdate('Y-m-d H:i:s', $timestamp);
}
```

Hydration parses `created_at` and `expires_at` with `DateTimeImmutable` + `DateTimeZone('UTC')`; parse failure throws `CorruptIdempotencyRecord` without including row values.

- [ ] **Step 5: Implement first-claim race handling**

Algorithm:

```text
attempt plain INSERT
success -> claimed
UniqueConstraintViolationException only -> existing key won
short transaction -> SELECT key FOR UPDATE
active row -> existing result
expired row -> replace locked row with fresh in_progress claim
row disappeared -> retry plain INSERT path, maximum 3 attempts
all other database exceptions -> static IdempotencyStoreUnavailable
```

Never catch generic `QueryException` as a duplicate. Do not chain query exceptions into `IdempotencyStoreUnavailable`.

- [ ] **Step 6: Implement guarded completion / indeterminate updates**

```php
$updated = $this->connection->table($this->table)
    ->where('key_hash', $keyHash)
    ->where('intent_fingerprint', $intentFingerprint)
    ->where('state', IdempotencyRecordState::InProgress->value)
    ->update([
        'state' => IdempotencyRecordState::Completed->value,
        'output_payload' => $outputPayload,
    ]);
```

Require exactly one updated row. `markIndeterminate()` uses the same key+intent+in-progress guard and sets `output_payload` to null. Neither method changes `created_at` or `expires_at`.

- [ ] **Step 7: Add the package migration artifact**

Use anonymous `Migration` + `Schema::create()` / `dropIfExists()` with the same tested schema. Do not add a new service provider or automatic migration execution.

- [ ] **Step 8: Run Task-3 tests and verify GREEN**

```bash
cd packages/laravel
composer validate --strict
./vendor/bin/phpunit tests/Unit/DatabaseIdempotencyStoreTest.php tests/Unit/IdempotencyServiceTest.php
```

- [ ] **Step 9: Commit Task 3**

```bash
git add packages/laravel/composer.json packages/laravel/src/Idempotency/DatabaseIdempotencyStore.php packages/laravel/database/migrations packages/laravel/tests/Unit/DatabaseIdempotencyStoreTest.php
git commit -m "feat(laravel): add durable idempotency store"
```

---

### Task 4: Add idempotency preflight stage and reorder the canonical pipeline

**Files:**
- Create: `packages/laravel/src/Idempotency/IdempotencyStage.php`
- Modify: `packages/laravel/src/Runtime/Pipeline/ActionPipelineStage.php`
- Modify: `packages/laravel/src/Runtime/Pipeline/ActionPipelineState.php`
- Modify: `packages/laravel/src/Result/CoreActionErrorCode.php`
- Create/Test: `packages/laravel/tests/Unit/IdempotencyStageTest.php`
- Modify/Test: `packages/laravel/tests/Unit/ActionBusTest.php`
- Modify/Test: `packages/laravel/tests/Unit/AuthorizationIntegrationTest.php`
- Modify/Test: `packages/laravel/tests/Unit/LaravelInputValidationStageTest.php`
- Modify/Test: `packages/laravel/tests/Unit/ConfirmationStageTest.php`
- Modify/Test: `packages/laravel/tests/Integration/ConfirmationPipelineIntegrationTest.php`
- Modify/Test: `packages/laravel/tests/Fixtures/PrepList/PrepListTestPipeline.php`

**Interfaces:**

```php
public function __construct(
    IdempotencyKeyValidator $validator,
    IdempotencyKeyHasher $keyHasher,
    IdempotencyIntentHasher $intentHasher,
    IdempotencyService $service,
)
```

`ActionPipelineState` gains readonly `?IdempotencyExecutionPlan $idempotencyPlan = null` plus:

```php
public function withIdempotencyPlan(IdempotencyExecutionPlan $plan): self;
```

All existing state-copy methods preserve that plan.

- [ ] **Step 1: Write RED stage tests**

Prove:

```text
none + key -> Bypass; store not touched
recommended + null -> Bypass
required + null -> idempotency_key_required
non-null empty/241-char key -> idempotency_key_invalid
fresh preflight -> FreshAttempt plan
completed exact -> Replay plan and state output set
completed mismatched intent -> idempotency_conflict
in_progress exact -> idempotency_in_progress
indeterminate exact -> idempotency_indeterminate
```

All halt details must be absent.

- [ ] **Step 2: Run stage tests and verify RED**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/IdempotencyStageTest.php
```

- [ ] **Step 3: Add exact core error constants**

```php
public const IDEMPOTENCY_KEY_REQUIRED = 'idempotency_key_required';
public const IDEMPOTENCY_KEY_INVALID = 'idempotency_key_invalid';
public const IDEMPOTENCY_CONFLICT = 'idempotency_conflict';
public const IDEMPOTENCY_IN_PROGRESS = 'idempotency_in_progress';
public const IDEMPOTENCY_INDETERMINATE = 'idempotency_indeterminate';
```

- [ ] **Step 4: Reorder `ActionPipelineStage`**

```php
case InputValidation = 'input_validation';
case Authorization = 'authorization';
case Idempotency = 'idempotency';
case Confirmation = 'confirmation';
case Execution = 'execution';
case OutputPolicy = 'output_policy';
```

- [ ] **Step 5: Implement `IdempotencyStage` policy flow**

`none` and recommended-null set Bypass. Required-null and invalid non-null key halt. Valid keys compute hash+intent, call service preflight, then map result to FreshAttempt, Replay, or one of the three fail-closed reuse halts. Replay uses `withIdempotencyPlan(...)->withOutput($result->output)`.

- [ ] **Step 6: Update all existing stage-order fixtures**

Expected order becomes exactly:

```text
input_validation
authorization
idempotency
confirmation
execution
output_policy
```

Keep T-401 authorization-before-confirmation and confirmation security assertions intact. At this checkpoint, `ConfirmationPipelineIntegrationTest` may keep a test-only idempotency passthrough, but it must be placed before the real confirmation stage.

- [ ] **Step 7: Run affected tests and verify GREEN**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/IdempotencyStageTest.php tests/Unit/ActionBusTest.php tests/Unit/AuthorizationIntegrationTest.php tests/Unit/LaravelInputValidationStageTest.php tests/Unit/ConfirmationStageTest.php tests/Integration/ConfirmationPipelineIntegrationTest.php
```

- [ ] **Step 8: Commit Task 4**

```bash
git add packages/laravel/src/Idempotency/IdempotencyStage.php packages/laravel/src/Runtime/Pipeline packages/laravel/src/Result/CoreActionErrorCode.php packages/laravel/tests/Unit packages/laravel/tests/Integration/ConfirmationPipelineIntegrationTest.php packages/laravel/tests/Fixtures/PrepList/PrepListTestPipeline.php
git commit -m "feat(laravel): add idempotency preflight stage"
```

---

### Task 5: Claim fresh execution and persist completion around the executor

**Files:**
- Modify: `packages/laravel/src/Runtime/Pipeline/ActionExecutionStage.php`
- Modify/Test: `packages/laravel/tests/Unit/ActionExecutionStageTest.php`
- Modify/Test: `packages/laravel/tests/Unit/IdempotencyServiceTest.php`

**Interface:**

```php
public function __construct(
    ActionExecutor $executor,
    ?IdempotencyService $idempotency = null,
)
```

One-argument construction remains valid for bypass-only existing fixtures. A FreshAttempt plan without service throws `IdempotencyConfigurationViolation` before executor invocation.

- [ ] **Step 1: Write RED execution ownership tests**

Prove Bypass preserves old behavior; claimed FreshAttempt executes exactly once and completes; claim-race Replay skips executor; claim-race Conflict/InProgress/Indeterminate halt before executor; executor exception attempts indeterminate and rethrows original; indeterminate transition failure does not delete/release claim; unreplayable successful output marks indeterminate and throws; completion-store failure never becomes success.

- [ ] **Step 2: Run execution tests and verify RED**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/ActionExecutionStageTest.php tests/Unit/IdempotencyServiceTest.php
```

- [ ] **Step 3: Implement claim mapping before executor**

```php
$plan = $state->idempotencyPlan;
if ($plan?->kind === IdempotencyExecutionPlanKind::FreshAttempt) {
    $claim = $this->idempotencyOrFail()->claim($plan);

    if ($claim->kind === IdempotencyClaimKind::Replay) {
        return ActionPipelineDecision::continueWith($state->withOutput($claim->output));
    }

    if ($claim->kind !== IdempotencyClaimKind::Claimed) {
        return $this->haltForClaimResult($claim, $state);
    }
}
```

Only `Claimed` reaches application executor code.

- [ ] **Step 4: Implement exception and completion behavior**

On executor exception, best-effort `markIndeterminate($plan)` and rethrow the original executor exception. Never delete/release. On successful output, call `complete($plan, $output)` before output policy. If codec rejects output, best-effort mark indeterminate and throw `UnreplayableIdempotencyOutput`. If durable completion throws, propagate failure; the claim remains `in_progress` unless the database commit actually completed, in which case subsequent reads observe `completed`.

- [ ] **Step 5: Run Task-5 tests and verify GREEN**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/ActionExecutionStageTest.php tests/Unit/IdempotencyServiceTest.php
```

- [ ] **Step 6: Commit Task 5**

```bash
git add packages/laravel/src/Runtime/Pipeline/ActionExecutionStage.php packages/laravel/tests/Unit/ActionExecutionStageTest.php packages/laravel/tests/Unit/IdempotencyServiceTest.php
git commit -m "feat(laravel): claim idempotent execution atomically"
```

---

### Task 6: Skip confirmation/execution only for safe completed replay and normalize public rejections

**Files:**
- Create: `packages/laravel/src/Confirmation/ConfirmationRequirement.php`
- Modify: `packages/laravel/src/Confirmation/ConfirmationStage.php`
- Modify: `packages/laravel/src/Runtime/Pipeline/ActionBus.php`
- Modify: `packages/laravel/src/Result/ActionResultNormalizer.php`
- Modify/Test: `packages/laravel/tests/Unit/ActionBusTest.php`
- Modify/Test: `packages/laravel/tests/Unit/ConfirmationStageTest.php`
- Modify/Test: `packages/laravel/tests/Unit/ActionResultNormalizerTest.php`

**Interface:**

```php
final class ConfirmationRequirement
{
    public static function isRequired(ActionDefinition $definition): bool;
}
```

- [ ] **Step 1: Write RED replay-orchestration tests**

For a Replay plan produced by idempotency, expected handler log is:

```php
self::assertSame(
    ['input_validation', 'authorization', 'idempotency', 'output_policy'],
    $log,
);
```

Assert confirmation/execution call counts are zero, output policy receives stored raw output, auditor records once, current correlation ID is preserved, and no HumanConfirmation is created.

- [ ] **Step 2: Write RED replay confirmation-guard test**

A consequential replay candidate with pre-materialized `HumanConfirmation` must throw `ConfirmationConfigurationViolation`; completed replay must not bypass that T-401 invariant.

- [ ] **Step 3: Write RED normalizer tests for all five idempotency codes**

Each maps to `ActionResult.status = rejected`, uses a static message, and drops all halt details. Messages:

```text
idempotency_key_required  = An idempotency key is required.
idempotency_key_invalid   = The idempotency key is invalid.
idempotency_conflict      = The idempotency key is already bound to a different invocation intent.
idempotency_in_progress   = An invocation with this idempotency key is already in progress.
idempotency_indeterminate = The prior invocation outcome is indeterminate and will not be retried automatically.
```

- [ ] **Step 4: Run focused tests and verify RED**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/ActionBusTest.php tests/Unit/ConfirmationStageTest.php tests/Unit/ActionResultNormalizerTest.php
```

- [ ] **Step 5: Extract `ConfirmationRequirement` and preserve T-401 behavior**

```php
public static function isRequired(ActionDefinition $definition): bool
{
    return $definition->risk === ActionRisk::Consequential
        || in_array(ContextRequirement::HumanConfirmation, $definition->contextRequirements, true);
}
```

`ConfirmationStage` delegates to this helper and keeps its own pre-materialized authority guard for direct stage tests. Update its class comment to reflect that idempotency now precedes confirmation.

- [ ] **Step 6: Implement explicit ActionBus replay skipping**

Before invoking Confirmation or Execution stages, inspect current state's plan. If Replay:

```php
if ($stage === ActionPipelineStage::Confirmation) {
    if (ConfirmationRequirement::isRequired($state->definition)
        && $state->context->has(ContextRequirement::HumanConfirmation)) {
        throw ConfirmationConfigurationViolation::preMaterializedAuthority();
    }
    continue;
}

if ($stage === ActionPipelineStage::Execution) {
    continue;
}
```

Do not add idempotency-specific behavior inside `ConfirmationStage`. OutputPolicy still runs and final `hasOutput` invariant remains mandatory.

- [ ] **Step 7: Implement exact normalizer match arms and verify GREEN**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/ActionBusTest.php tests/Unit/ConfirmationStageTest.php tests/Unit/ActionResultNormalizerTest.php
```

- [ ] **Step 8: Commit Task 6**

```bash
git add packages/laravel/src/Confirmation packages/laravel/src/Runtime/Pipeline/ActionBus.php packages/laravel/src/Result/ActionResultNormalizer.php packages/laravel/tests/Unit/ActionBusTest.php packages/laravel/tests/Unit/ConfirmationStageTest.php packages/laravel/tests/Unit/ActionResultNormalizerTest.php
git commit -m "feat(laravel): replay completed idempotent results"
```

---

### Task 7: Prove full T-402 and T-401/T-402 combined behavior

**Files:**
- Create/Test: `packages/laravel/tests/Integration/IdempotencyPipelineIntegrationTest.php`
- Modify/Test: `packages/laravel/tests/Integration/ConfirmationPipelineIntegrationTest.php`
- Modify/Test: `packages/laravel/tests/Fixtures/PrepList/PrepListTestPipeline.php`

- [ ] **Step 1: Write RED lost-response consequential replay test**

Exact scenario:

```text
call 1 -> validation -> authorization -> idempotency Fresh -> confirmation_required
trusted bridge approves challenge
call 2 with same key + valid receipt -> claim -> executor once -> completed -> output policy -> success
client loses call-2 response
call 3 with same key + same validated intent -> validation -> authorization -> completed Replay -> output policy -> success
call 3 does not need/consume a new receipt
executor total calls = 1
call-3 result uses call-3 correlation ID
```

Use explicit counters for authorization, confirmation receipt consumption, executor, output policy, and auditor.

- [ ] **Step 2: Write RED conflict/partition/context tests**

Prove changed validated input/current record/current selection/browser session conflicts inside one authority partition; different actor partition, tenant partition, or action ID/version may reuse the same raw key independently; different surface or binding alone replays; authorization denial occurs before replay; active in-progress/indeterminate never reaches confirmation/execution; required missing key never reaches confirmation/execution; policy `none` executes twice when called twice.

- [ ] **Step 3: Write RED failure-safety tests**

Prove executor-side-effect-then-throw cannot execute again with same active key; unreplayable successful output does not reopen; completion persistence failure never returns succeeded; exact expiry equality ends the bounded guarantee and permits a new claim.

- [ ] **Step 4: Run integration tests and verify RED**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Integration/IdempotencyPipelineIntegrationTest.php tests/Integration/ConfirmationPipelineIntegrationTest.php
```

- [ ] **Step 5: Wire the integration harness**

Use real validation, authorization, IdempotencyStage, ConfirmationStage, ActionExecutionStage, ActionResultNormalizer, and output-policy spy. A deterministic test-only IdempotencyStore fake may be used for precise race/failure injection; it must implement the production store interface exactly. Task 3 separately proves the concrete database adapter.

- [ ] **Step 6: Run integration + existing Livewire E2E and verify GREEN**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Integration/IdempotencyPipelineIntegrationTest.php tests/Integration/ConfirmationPipelineIntegrationTest.php tests/Integration/PrepListLivewireE2ETest.php
```

- [ ] **Step 7: Run entire Laravel suite**

```bash
cd packages/laravel
./vendor/bin/phpunit
```

- [ ] **Step 8: Commit Task 7**

```bash
git add packages/laravel/tests/Integration packages/laravel/tests/Fixtures/PrepList/PrepListTestPipeline.php
git commit -m "test(laravel): prove idempotent replay integration"
```

---

### Task 8: Update authoritative docs, verify repository, and prepare review checkpoint

**Files:**
- Modify: `docs/DECISION-REGISTER.md`
- Modify: `docs/GLOSSARY.md`
- Modify: `docs/THREAT-MODEL.md`
- Modify: `CONFORMANCE.md`
- Modify: `TASKS.md`
- Modify: `STATUS.md`
- Modify: `REVIEW_REQUEST.md`
- Do not modify: `spec/0.1/**`

- [ ] **Step 1: Add accepted D-045 from actual implemented behavior**

Record bounded server-side deduplication, exact action/trusted partition key hashing, validated intent binding, atomic pre-execution claim, successful pre-output-policy replay storage, exact completed replay, fail-closed conflict/concurrency/indeterminate reuse, and independent raw-key reuse across different action versions/trusted partitions.

- [ ] **Step 2: Update glossary/threat/conformance docs**

`GLOSSARY.md` documents exact policy/replay/retention semantics. `THREAT-MODEL.md` T12 records T-402 as implemented and keeps T-401 single-use confirmation separate. `CONFORMANCE.md` Trust scenario 15 records Laravel reference-runtime evidence without claiming the future shared runner exists.

- [ ] **Step 3: Run PHP lint and full Laravel suite**

```bash
find packages/laravel/src packages/laravel/tests -name '*.php' -print0 | xargs -0 -n1 php -l
cd packages/laravel && ./vendor/bin/phpunit
```

- [ ] **Step 4: Run contract validation**

```bash
python scripts/validate.py
```

Expected: green; `spec/0.1` unchanged.

- [ ] **Step 5: Run browser isolation checks**

```bash
cd browser-runtime
npm ci
npm run typecheck
npm test -- --run
```

Expected: existing browser suite green; no browser production behavior required by T-402.

- [ ] **Step 6: Run diff/scope/secret review**

```bash
git diff --check main...HEAD
git diff --name-status main...HEAD
git diff --stat main...HEAD
git diff main...HEAD -- spec/0.1
grep -R "idempotencyKey\|key_hash\|intent_fingerprint\|output_payload" packages/laravel/src/Result packages/laravel/src/Runtime/Context -n
```

Review every grep match. Public result/provenance code must not emit raw key/hash/fingerprint/replay payload. Confirm no T-403 redaction implementation, T-404 audit persistence implementation, or browser production behavior change entered the diff.

- [ ] **Step 7: Update task/status/review evidence only after verification is green**

Record exact TDD checkpoint SHAs, CI run IDs, test/assertion totals, accepted D-045, unchanged `spec/0.1`, T-401 regression evidence, and keep T-403/T-404 unstarted.

- [ ] **Step 8: Commit review checkpoint**

```bash
git add docs/DECISION-REGISTER.md docs/GLOSSARY.md docs/THREAT-MODEL.md CONFORMANCE.md TASKS.md STATUS.md REVIEW_REQUEST.md
git commit -m "docs(status): prepare T-402 review checkpoint"
```

- [ ] **Step 9: Require exact-head GitHub Actions validation**

All seven existing jobs must be green:

```text
browser
php-lint
contract
PHP 8.3 / Illuminate 12
PHP 8.4 / Illuminate 12
PHP 8.3 / Illuminate 13
PHP 8.4 / Illuminate 13
```

If a matrix-specific DB behavior fails, reproduce it with a focused RED test and fix it without weakening the contract.

- [ ] **Step 10: Perform final compare before external review**

Verify base/merge-base is exact `b94ed83497e213c155ae0276264e954b9acd3ac3`, branch is ahead-only, T-401 confirmation tests remain green, raw key is absent from the database schema, replay uses no PHP serialization, and no transaction/row lock spans executor code. Then open external review/PR as a separate explicit gate; do not merge automatically.
