# T-402 Idempotency Store Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement bounded durable server-side idempotency for the Laravel reference runtime so protected retries cannot repeat a side effect within the configured retention window and exact completed retries can safely replay prior execution output.

**Architecture:** Add a focused `Idempotency` package boundary with deterministic trusted-scope hashing, a two-phase `in_progress -> completed | indeterminate` state machine, and a database-backed reference store. Move idempotency preflight before confirmation so an already-completed exact retry can replay without consuming a new confirmation receipt, but claim a fresh execution only after confirmation succeeds. Persist only wire-safe pre-output-policy executor output and rerun output policy on every replay.

**Tech Stack:** PHP 8.3+, Illuminate 12/13 contracts/support/validation/database, PHPUnit 11, Laravel Query Builder transactions and pessimistic row locks, `Illuminate\Database\UniqueConstraintViolationException`, SHA-256, deterministic JSON, SQLite-backed reference-store tests, existing GitHub Actions 7-job validation matrix.

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
- Trusted authority partition is tenant + actor when present; browser-session identity is used for partitioning only when neither tenant nor actor is present; otherwise use the exact-action `global` partition.
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
- Application executor exceptions never imply that the side effect did not happen; claimed execution becomes `indeterminate` when that transition can be persisted and otherwise conservatively remains `in_progress`.
- Persist only replayable pre-output-policy values: null, bool, int, finite float, string, lists, and string-key maps recursively containing the same values. Never use PHP `serialize()` for replay data.
- A successful executor output that cannot be encoded or completed durably must not return normal success and must not reopen same-key execution.
- Public idempotency rejection details are omitted. Raw key, lookup hash, intent fingerprint, replay payload, actor/tenant identity, and DB diagnostics must not be added to public result metadata/errors/provenance by T-402.
- Add `illuminate/database` as `^12.0|^13.0`; preserve PHP 8.3/8.4 and Illuminate 12/13 CI support.
- Follow strict TDD for every trust-boundary step: write the failing test, run and observe the intended RED failure, implement minimum behavior, rerun GREEN, then commit.

## File Structure

### New runtime scope files

- `packages/laravel/src/Runtime/Scope/RuntimeScopeCanonicalizer.php` — deterministic recursive canonicalization and trusted-entry identity representation shared by confirmation/idempotency hashing.
- `packages/laravel/src/Runtime/Scope/UnrepresentableRuntimeScope.php` — path-only internal canonicalization failure; never embeds rejected values.

### New idempotency core files

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

### New schema/setup artifact

- `packages/laravel/database/migrations/0000_00_00_000000_create_surfacerelay_idempotency_records.php` — narrowly usable package migration; no new service-provider/bootstrap subsystem.

### Existing production files modified

- `packages/laravel/composer.json`
- `packages/laravel/src/Confirmation/ConfirmationScopeHasher.php`
- `packages/laravel/src/Confirmation/ConfirmationStage.php`
- `packages/laravel/src/Confirmation/ConfirmationRequirement.php` — create narrow shared requirement predicate so replay guard and ConfirmationStage use the same rule.
- `packages/laravel/src/Runtime/Pipeline/ActionPipelineStage.php`
- `packages/laravel/src/Runtime/Pipeline/ActionPipelineState.php`
- `packages/laravel/src/Runtime/Pipeline/ActionExecutionStage.php`
- `packages/laravel/src/Runtime/Pipeline/ActionBus.php`
- `packages/laravel/src/Result/CoreActionErrorCode.php`
- `packages/laravel/src/Result/ActionResultNormalizer.php`

### Primary new/updated tests

- `packages/laravel/tests/Unit/RuntimeScopeCanonicalizerTest.php`
- `packages/laravel/tests/Unit/ConfirmationScopeHasherTest.php`
- `packages/laravel/tests/Unit/IdempotencyHashingTest.php`
- `packages/laravel/tests/Unit/IdempotencyReplayCodecTest.php`
- `packages/laravel/tests/Unit/IdempotencyServiceTest.php`
- `packages/laravel/tests/Unit/DatabaseIdempotencyStoreTest.php`
- `packages/laravel/tests/Unit/IdempotencyStageTest.php`
- `packages/laravel/tests/Unit/ActionExecutionStageTest.php`
- `packages/laravel/tests/Unit/ActionBusTest.php`
- `packages/laravel/tests/Unit/ActionResultNormalizerTest.php`
- `packages/laravel/tests/Unit/ConfirmationStageTest.php`
- `packages/laravel/tests/Unit/AuthorizationIntegrationTest.php`
- `packages/laravel/tests/Unit/LaravelInputValidationStageTest.php`
- `packages/laravel/tests/Integration/ConfirmationPipelineIntegrationTest.php`
- `packages/laravel/tests/Integration/IdempotencyPipelineIntegrationTest.php`
- `packages/laravel/tests/Fixtures/PrepList/PrepListTestPipeline.php`

---

### Task 1: Extract deterministic runtime scope encoding and add idempotency identity hashing

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
- `RuntimeScopeCanonicalizer::trustedIdentity(TrustedContextEntry $entry, string $path): array{scopeKey:string}|array{value:mixed}`
- `RuntimeScopeCanonicalizer::encode(mixed $value, string $path): string`
- `UnrepresentableRuntimeScope` exposes readonly `string $path` and a static `at(string $path): self`.
- `IdempotencyKeyValidator::isValid(string $key): bool`
- `IdempotencyKeyHasher::hash(ActionPipelineState $state, string $rawKey): string`
- `IdempotencyIntentHasher::fingerprint(ActionPipelineState $state): string`
- `ConfirmationScopeHasher::fingerprint(ActionPipelineState $state): string` remains externally unchanged.

- [ ] **Step 1: Write RED tests for the shared canonicalizer**

Create tests proving associative maps sort recursively, lists retain order, finite floats remain distinguishable, and unsupported values fail with path-only errors:

```php
$canonicalizer = new RuntimeScopeCanonicalizer();

self::assertSame(
    '{"a":{"y":2,"z":3},"b":2}',
    $canonicalizer->encode(['b' => 2, 'a' => ['z' => 3, 'y' => 2]], 'scope'),
);

$this->expectException(UnrepresentableRuntimeScope::class);
$canonicalizer->encode(['amount' => INF], 'scope');
```

Also prove a trusted object with `confirmationScopeKey: 'actor:model:42'` becomes exactly `['scopeKey' => 'actor:model:42']` and that an arbitrary object without that stable key is rejected without its value appearing in the exception message.

- [ ] **Step 2: Run canonicalizer tests and verify RED**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/RuntimeScopeCanonicalizerTest.php
```

Expected RED: `RuntimeScopeCanonicalizer` / `UnrepresentableRuntimeScope` classes do not exist.

- [ ] **Step 3: Implement the canonicalizer**

Use one recursive rule set:

```php
if ($value === null || is_bool($value) || is_int($value) || is_string($value)) {
    return $value;
}
if (is_float($value)) {
    if (!is_finite($value)) {
        throw UnrepresentableRuntimeScope::at($path);
    }
    return $value;
}
if (!is_array($value)) {
    throw UnrepresentableRuntimeScope::at($path);
}
```

For list arrays, recurse in order. For maps, require string keys, `ksort($value, SORT_STRING)`, then recurse. `encode()` must call `json_encode()` with:

```php
JSON_THROW_ON_ERROR
| JSON_UNESCAPED_SLASHES
| JSON_UNESCAPED_UNICODE
| JSON_PRESERVE_ZERO_FRACTION
```

`trustedIdentity()` must use `confirmationScopeKey` when present, otherwise canonicalize the trusted value; never inspect arbitrary object properties.

- [ ] **Step 4: Pin T-401 fingerprint compatibility before refactoring its implementation**

Extend `ConfirmationScopeHasherTest` with a fixed snapshot for the existing standard fixture:

```php
self::assertSame(
    '0057701f23d96b62d1a763c8dad01ae9a0412bf1783e72201d82949df0826e5a',
    (new ConfirmationScopeHasher())->fingerprint($this->state()),
);
```

Run the current hasher before modifying it. Expected: PASS. If this snapshot does not match the current PHP runtime output, stop and replace the constant with the exact output produced by the untouched current implementation before proceeding; do not alter production hashing to fit a guessed constant.

- [ ] **Step 5: Refactor `ConfirmationScopeHasher` onto the shared canonicalizer**

Preserve the domain separator exactly:

```php
private const string DOMAIN = "surfacerelay.confirmation.scope.v1\n";
```

Catch only `UnrepresentableRuntimeScope`, remap it to the existing public/internal T-401 exception contract:

```php
try {
    $json = $this->canonicalizer->encode($scope, 'scope');
} catch (UnrepresentableRuntimeScope $e) {
    throw UnrepresentableConfirmationScope::at($e->path);
}

return hash('sha256', self::DOMAIN . $json);
```

Allow `new ConfirmationScopeHasher()` to continue working without a new mandatory constructor dependency by defaulting to a new `RuntimeScopeCanonicalizer` internally.

- [ ] **Step 6: Write RED tests for key validation, trusted partition hashing, and intent hashing**

Test exact policy-independent key grammar:

```php
$validator = new IdempotencyKeyValidator();
self::assertTrue($validator->isValid('idem-1'));
self::assertFalse($validator->isValid(''));
self::assertFalse($validator->isValid(str_repeat('x', 241)));
self::assertTrue($validator->isValid(str_repeat('x', 240)));
```

Hash tests must prove:

- same action/version + same tenant/actor partition + same raw key => same lookup hash;
- different tenant or actor => different lookup hash, therefore no cross-partition collision;
- different action ID/version => different lookup hash;
- current record/selection do not alter lookup hash;
- validated input, record, selection, browser session alter intent fingerprint;
- surface, binding ID, correlation ID, metadata, raw key value, confirmation receipt, and HumanConfirmation do not alter intent fingerprint when the true intent is otherwise equal;
- when actor/tenant exist, browser session is not a partition dimension but remains an intent dimension;
- when neither actor nor tenant exists, browser session becomes the partition identity;
- when none of actor/tenant/session exists, exact action/version uses explicit `global` partition.

- [ ] **Step 7: Implement idempotency hashers**

`IdempotencyKeyHasher` canonical document:

```php
[
    'action' => ['id' => $state->definition->id, 'version' => $state->definition->version],
    'partition' => [
        'tenant' => <trusted identity when present>,
        'actor' => <trusted identity when present>,
        'browserSession' => <identity only when tenant+actor both absent>,
        'global' => true <only when all three are absent>,
    ],
    'key' => $rawKey,
]
```

Hash with:

```php
hash('sha256', "surfacerelay.idempotency.key.v1\n" . $json)
```

`IdempotencyIntentHasher` canonical document:

```php
[
    'action' => ['id' => $state->definition->id, 'version' => $state->definition->version],
    'input' => $state->input,
    'context' => [
        'authenticated_actor' => ...,
        'tenant' => ...,
        'current_record' => ...,
        'current_selection' => ...,
        'browser_session' => ...,
    ],
]
```

Hash with:

```php
hash('sha256', "surfacerelay.idempotency.intent.v1\n" . $json)
```

Map `UnrepresentableRuntimeScope` to `UnrepresentableIdempotencyScope::at($path)` with no rejected value in messages.

- [ ] **Step 8: Run focused tests and verify GREEN**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/RuntimeScopeCanonicalizerTest.php tests/Unit/ConfirmationScopeHasherTest.php tests/Unit/IdempotencyHashingTest.php
```

Expected: all selected tests green and the T-401 fixed fingerprint unchanged.

- [ ] **Step 9: Commit Task 1**

```bash
git add packages/laravel/src/Runtime/Scope packages/laravel/src/Confirmation/ConfirmationScopeHasher.php packages/laravel/src/Idempotency packages/laravel/tests/Unit/RuntimeScopeCanonicalizerTest.php packages/laravel/tests/Unit/ConfirmationScopeHasherTest.php packages/laravel/tests/Unit/IdempotencyHashingTest.php
git commit -m "feat(laravel): add deterministic idempotency identity hashing"
```

---

### Task 2: Build the idempotency state machine, replay codec, and service contract

**Files:**
- Create: `packages/laravel/src/Idempotency/IdempotencyExecutionPlanKind.php`
- Create: `packages/laravel/src/Idempotency/IdempotencyExecutionPlan.php`
- Create: `packages/laravel/src/Idempotency/IdempotencyRecordState.php`
- Create: `packages/laravel/src/Idempotency/IdempotencyRecord.php`
- Create: `packages/laravel/src/Idempotency/IdempotencyStore.php`
- Create: `packages/laravel/src/Idempotency/IdempotencyStoreClaimResult.php`
- Create: `packages/laravel/src/Idempotency/IdempotencyPreflightKind.php`
- Create: `packages/laravel/src/Idempotency/IdempotencyPreflightResult.php`
- Create: `packages/laravel/src/Idempotency/IdempotencyClaimKind.php`
- Create: `packages/laravel/src/Idempotency/IdempotencyClaimResult.php`
- Create: `packages/laravel/src/Idempotency/IdempotencyClock.php`
- Create: `packages/laravel/src/Idempotency/SystemIdempotencyClock.php`
- Create: `packages/laravel/src/Idempotency/IdempotencyReplayCodec.php`
- Create: `packages/laravel/src/Idempotency/UnreplayableIdempotencyOutput.php`
- Create: `packages/laravel/src/Idempotency/IdempotencyStoreUnavailable.php`
- Create: `packages/laravel/src/Idempotency/CorruptIdempotencyRecord.php`
- Create: `packages/laravel/src/Idempotency/IdempotencyConfigurationViolation.php`
- Create: `packages/laravel/src/Idempotency/IdempotencyService.php`
- Create/Test: `packages/laravel/tests/Unit/IdempotencyReplayCodecTest.php`
- Create/Test: `packages/laravel/tests/Unit/IdempotencyServiceTest.php`

**Interfaces:**
- `IdempotencyClock::now(): int`
- `IdempotencyRecord::__construct(string $keyHash, string $intentFingerprint, IdempotencyRecordState $state, ?string $outputPayload, int $createdAt, int $expiresAt)`
- `IdempotencyRecord::isActiveAt(int $now): bool`
- `IdempotencyStore::find(string $keyHash): ?IdempotencyRecord`
- `IdempotencyStore::claim(IdempotencyRecord $fresh, int $now): IdempotencyStoreClaimResult`
- `IdempotencyStore::complete(string $keyHash, string $intentFingerprint, string $outputPayload): void`
- `IdempotencyStore::markIndeterminate(string $keyHash, string $intentFingerprint): void`
- `IdempotencyReplayCodec::encode(mixed $output): string`
- `IdempotencyReplayCodec::decode(string $payload): mixed`
- `IdempotencyService::__construct(IdempotencyStore $store, IdempotencyClock $clock, IdempotencyReplayCodec $codec, int $retentionSeconds = 86400)`
- `IdempotencyService::preflight(string $keyHash, string $intentFingerprint): IdempotencyPreflightResult`
- `IdempotencyService::claim(IdempotencyExecutionPlan $freshPlan): IdempotencyClaimResult`
- `IdempotencyService::complete(IdempotencyExecutionPlan $freshPlan, mixed $output): void`
- `IdempotencyService::markIndeterminate(IdempotencyExecutionPlan $freshPlan): void`

- [ ] **Step 1: Write RED codec tests**

Cover null, bool, int, finite float including `1.0`, UTF-8 strings, lists, nested string-key maps, and explicit rejection of object/resource/closure/non-finite float/mixed-key map.

```php
$codec = new IdempotencyReplayCodec();
$payload = $codec->encode(['ok' => true, 'amount' => 1.0, 'items' => [1, 2]]);
self::assertSame(['amount' => 1.0, 'items' => [1, 2], 'ok' => true], $codec->decode($payload));
```

The payload format must be deterministic and must not call `serialize()` / `unserialize()`.

- [ ] **Step 2: Run codec tests and verify RED**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/IdempotencyReplayCodecTest.php
```

Expected RED: codec and exception classes are absent.

- [ ] **Step 3: Implement the replay codec**

Reuse `RuntimeScopeCanonicalizer` to validate/canonicalize the value, encode with the same safe JSON flags, and decode with:

```php
json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
```

Reject corrupt persisted payloads by throwing `CorruptIdempotencyRecord` rather than exposing the payload.

- [ ] **Step 4: Write RED state/service tests using a test-only in-memory store**

The fake store lives inside `IdempotencyServiceTest.php`; it is not production code. Tests must prove:

```text
no active record -> preflight fresh
completed exact intent -> replay decoded output
completed different intent -> conflict
in_progress exact intent -> in_progress
indeterminate exact intent -> indeterminate
now == expiresAt -> treated expired/fresh
replay does not change expiresAt
claim creates in_progress with expiresAt = claimNow + 86400
claim race can return completed/conflict/in_progress/indeterminate without second ownership
complete transitions exact claimed record only
executor-failure helper marks exact record indeterminate
invalid retention 0/-1 throws configuration violation
```

Use a mutable fake clock:

```php
final class FakeIdempotencyClock implements IdempotencyClock
{
    public function __construct(public int $time) {}
    public function now(): int { return $this->time; }
}
```

- [ ] **Step 5: Run service tests and verify RED**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/IdempotencyServiceTest.php
```

Expected RED: state/store/service types are absent.

- [ ] **Step 6: Implement records, typed results, clock, and service**

Record invariants:

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

`IdempotencyExecutionPlan` factories:

```php
IdempotencyExecutionPlan::bypass();
IdempotencyExecutionPlan::fresh($keyHash, $intentFingerprint);
IdempotencyExecutionPlan::replay($keyHash, $intentFingerprint, $output);
```

Only a `FreshAttempt` plan may be passed to `claim()`, `complete()`, or `markIndeterminate()`; wrong plan kind throws `IdempotencyConfigurationViolation`.

`preflight()` classifies active records only. Expired records return fresh; actual expired-row replacement stays store-owned at claim time.

- [ ] **Step 7: Run codec/service tests and verify GREEN**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/IdempotencyReplayCodecTest.php tests/Unit/IdempotencyServiceTest.php
```

Expected: all selected tests green.

- [ ] **Step 8: Commit Task 2**

```bash
git add packages/laravel/src/Idempotency packages/laravel/tests/Unit/IdempotencyReplayCodecTest.php packages/laravel/tests/Unit/IdempotencyServiceTest.php
git commit -m "feat(laravel): add idempotency state machine"
```

---

### Task 3: Add the durable database-backed reference store and migration

**Files:**
- Modify: `packages/laravel/composer.json`
- Create: `packages/laravel/src/Idempotency/DatabaseIdempotencyStore.php`
- Create: `packages/laravel/database/migrations/0000_00_00_000000_create_surfacerelay_idempotency_records.php`
- Create/Test: `packages/laravel/tests/Unit/DatabaseIdempotencyStoreTest.php`

**Interfaces:**
- `DatabaseIdempotencyStore::__construct(Illuminate\Database\ConnectionInterface $connection, string $table = 'surfacerelay_idempotency_records')`
- Implements the exact `IdempotencyStore` contract from Task 2.

- [ ] **Step 1: Add `illuminate/database` dependency and install the branch dependency graph**

Edit requirement:

```json
"illuminate/database": "^12.0|^13.0"
```

Run:

```bash
cd packages/laravel
composer update illuminate/database --with-all-dependencies
composer validate --strict
```

Expected: dependency resolves under the current local/default constraint. CI will later prove both Illuminate 12 and 13 matrices.

- [ ] **Step 2: Write RED migration/store tests with SQLite**

Create the schema directly in test setup using the same columns as the package migration, then instantiate `DatabaseIdempotencyStore` with the test connection.

Required schema shape:

```php
$table->char('key_hash', 64)->primary();
$table->char('intent_fingerprint', 64);
$table->string('state', 32);
$table->text('output_payload')->nullable();
$table->timestamp('created_at');
$table->timestamp('expires_at')->index();
```

Test:

- raw caller key has no column and never appears in persisted rows;
- first insert claims;
- second active same-key claim returns existing record;
- expired row is atomically replaced in-place with new `in_progress` claim;
- completed/indeterminate active rows are never overwritten;
- completion updates only exact `key_hash + intent_fingerprint + in_progress`;
- indeterminate transition updates only exact expected active claim;
- corrupt state/payload/hash/time row throws `CorruptIdempotencyRecord`;
- arbitrary DB failure is wrapped as static `IdempotencyStoreUnavailable` message with no key/hash/fingerprint in that SurfaceRelay message.

- [ ] **Step 3: Run database store tests and verify RED**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/DatabaseIdempotencyStoreTest.php
```

Expected RED: database store and package migration are absent.

- [ ] **Step 4: Implement first-claim race semantics without holding a transaction across executor work**

Claim algorithm:

```text
1. attempt plain INSERT of fresh in_progress row
2. if INSERT succeeds -> claimed
3. catch only UniqueConstraintViolationException -> an existing key won
4. open short transaction, SELECT exact key FOR UPDATE
5. if active -> return existing
6. if expired -> UPDATE that locked row to the fresh in_progress claim and return claimed
7. if row disappeared between duplicate insert and locked read -> retry insert path, max 3 attempts
8. any other DB exception -> fail closed; never classify it as a duplicate
```

Implementation outline:

```php
try {
    $this->connection->table($this->table)->insert($this->toRow($fresh));
    return IdempotencyStoreClaimResult::claimed($fresh);
} catch (UniqueConstraintViolationException) {
    // Resolve only the proven unique-key race.
}
```

Use `lockForUpdate()` only inside the short existing-row resolution transaction. No method in the store accepts an executor callback.

- [ ] **Step 5: Implement completion and indeterminate transitions with conditional updates**

Completion must be one guarded update:

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

Require `$updated === 1`; otherwise inspect the exact record and throw a static impossible-transition/corrupt-state exception. `markIndeterminate()` follows the same exact conditional pattern and sets `output_payload` null.

- [ ] **Step 6: Add the package migration artifact**

Use an anonymous Laravel migration with `Schema::create()` / `dropIfExists()`. Do not add service-provider auto-loading or auto-run behavior. The migration is a package artifact that application setup can copy/publish explicitly.

- [ ] **Step 7: Run store tests plus Composer validation and verify GREEN**

```bash
cd packages/laravel
composer validate --strict
./vendor/bin/phpunit tests/Unit/DatabaseIdempotencyStoreTest.php tests/Unit/IdempotencyServiceTest.php
```

Expected: green.

- [ ] **Step 8: Commit Task 3**

```bash
git add packages/laravel/composer.json packages/laravel/composer.lock packages/laravel/src/Idempotency/DatabaseIdempotencyStore.php packages/laravel/database/migrations packages/laravel/tests/Unit/DatabaseIdempotencyStoreTest.php
git commit -m "feat(laravel): add durable idempotency store"
```

If this package does not track `packages/laravel/composer.lock`, omit only that path from `git add`; do not introduce a lockfile solely for T-402.

---

### Task 4: Add idempotency preflight stage, execution plan state, and new canonical stage order

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
- Modify/Test: `packages/laravel/tests/Fixtures/PrepList/PrepListTestPipeline.php`
- Modify/Test: `packages/laravel/tests/Integration/ConfirmationPipelineIntegrationTest.php`

**Interfaces:**
- `IdempotencyStage::__construct(IdempotencyKeyValidator $validator, IdempotencyKeyHasher $keyHasher, IdempotencyIntentHasher $intentHasher, IdempotencyService $service)`
- `ActionPipelineState` gains readonly `?IdempotencyExecutionPlan $idempotencyPlan = null`.
- `ActionPipelineState::withIdempotencyPlan(IdempotencyExecutionPlan $plan): self`
- Every existing state copier (`withInput`, `withOutput`, `withContext`) preserves the plan.

- [ ] **Step 1: Write RED policy/preflight tests**

Test stage behavior:

```text
none + any key -> Bypass plan, no store lookup
recommended + null -> Bypass plan
required + null -> halt idempotency_key_required
recommended/required + empty or 241-char key -> halt idempotency_key_invalid
valid key + no active record -> FreshAttempt plan
valid key + completed exact intent -> Replay plan and state output is set to stored raw output
valid key + completed different intent -> idempotency_conflict
valid key + active in_progress -> idempotency_in_progress
valid key + active indeterminate -> idempotency_indeterminate
```

Assert all halt details are null/empty and do not contain the raw key/hashes.

- [ ] **Step 2: Run stage tests and verify RED**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/IdempotencyStageTest.php
```

Expected RED: stage and state-plan API are absent.

- [ ] **Step 3: Add new core error codes**

```php
public const IDEMPOTENCY_KEY_REQUIRED = 'idempotency_key_required';
public const IDEMPOTENCY_KEY_INVALID = 'idempotency_key_invalid';
public const IDEMPOTENCY_CONFLICT = 'idempotency_conflict';
public const IDEMPOTENCY_IN_PROGRESS = 'idempotency_in_progress';
public const IDEMPOTENCY_INDETERMINATE = 'idempotency_indeterminate';
```

Do not make `ActionError.code` a closed enum.

- [ ] **Step 4: Reorder `ActionPipelineStage`**

Case declaration order becomes exactly:

```php
case InputValidation = 'input_validation';
case Authorization = 'authorization';
case Idempotency = 'idempotency';
case Confirmation = 'confirmation';
case Execution = 'execution';
case OutputPolicy = 'output_policy';
```

- [ ] **Step 5: Implement state-plan propagation and `IdempotencyStage`**

Policy switch must be explicit:

```php
if ($policy === IdempotencyPolicy::None) {
    return ActionPipelineDecision::continueWith(
        $state->withIdempotencyPlan(IdempotencyExecutionPlan::bypass()),
    );
}

$key = $state->context->idempotencyKey;
if ($key === null) {
    if ($policy === IdempotencyPolicy::RecommendedKey) {
        return ActionPipelineDecision::continueWith(
            $state->withIdempotencyPlan(IdempotencyExecutionPlan::bypass()),
        );
    }
    return $this->halt(CoreActionErrorCode::IDEMPOTENCY_KEY_REQUIRED, $state);
}
```

For valid keys, calculate key hash + intent only after input validation/authorization have already transformed/approved state. Convert service preflight result to a plan or static halt. On replay, call `withIdempotencyPlan(...)->withOutput($replayOutput)`.

- [ ] **Step 6: Update stage-order tests/fixtures only for the superseded canonical ordering**

Update all existing fake/passthrough arrays so their expected log becomes:

```text
input_validation
authorization
idempotency
confirmation
execution
output_policy
```

Do not weaken any T-401 authorization-before-confirmation assertion. In `ConfirmationPipelineIntegrationTest`, keep the idempotency handler test-only passthrough at this checkpoint, but place it before the real `ConfirmationStage`.

- [ ] **Step 7: Run all affected focused tests and verify GREEN**

```bash
cd packages/laravel
./vendor/bin/phpunit \
  tests/Unit/IdempotencyStageTest.php \
  tests/Unit/ActionBusTest.php \
  tests/Unit/AuthorizationIntegrationTest.php \
  tests/Unit/LaravelInputValidationStageTest.php \
  tests/Unit/ConfirmationStageTest.php \
  tests/Integration/ConfirmationPipelineIntegrationTest.php
```

Expected: green with the new canonical order; T-401 fresh confirmation behavior unchanged.

- [ ] **Step 8: Commit Task 4**

```bash
git add packages/laravel/src/Idempotency/IdempotencyStage.php packages/laravel/src/Runtime/Pipeline packages/laravel/src/Result/CoreActionErrorCode.php packages/laravel/tests/Unit packages/laravel/tests/Integration/ConfirmationPipelineIntegrationTest.php packages/laravel/tests/Fixtures/PrepList/PrepListTestPipeline.php
git commit -m "feat(laravel): add idempotency preflight stage"
```

---

### Task 5: Gate fresh execution with atomic claim and durable completion

**Files:**
- Modify: `packages/laravel/src/Runtime/Pipeline/ActionExecutionStage.php`
- Modify/Test: `packages/laravel/tests/Unit/ActionExecutionStageTest.php`
- Modify/Test: `packages/laravel/tests/Unit/IdempotencyServiceTest.php`

**Interfaces:**
- `ActionExecutionStage::__construct(ActionExecutor $executor, ?IdempotencyService $idempotency = null)` keeps the existing one-argument construction valid for bypass-only fixtures.
- Fresh plan requires the service; missing service with a FreshAttempt plan throws `IdempotencyConfigurationViolation` before executor invocation.

- [ ] **Step 1: Write RED execution ownership tests**

Add tests for:

```text
no plan / Bypass -> old executor behavior unchanged
FreshAttempt + claim=claimed -> executor runs once, completion persists, output continues
FreshAttempt + claim=replay -> executor does not run, returned stored output continues
FreshAttempt + claim=conflict -> executor does not run, halt conflict
FreshAttempt + claim=in_progress -> executor does not run, halt in_progress
FreshAttempt + claim=indeterminate -> executor does not run, halt indeterminate
executor throws after claim -> markIndeterminate attempted, original exception rethrown
markIndeterminate store failure -> original executor exception remains primary; no record deletion/release call
executor returns unreplayable output -> mark indeterminate and throw UnreplayableIdempotencyOutput; no normal success
completion store failure -> no normal success; claimed record is never deleted/released
```

Use a spy executor count to prove zero/one execution exactly.

- [ ] **Step 2: Run execution tests and verify RED**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/ActionExecutionStageTest.php tests/Unit/IdempotencyServiceTest.php
```

Expected RED: execution stage has no idempotency integration.

- [ ] **Step 3: Implement claim-before-executor behavior**

Execution outline:

```php
$plan = $state->idempotencyPlan;
if ($plan?->kind === IdempotencyExecutionPlanKind::FreshAttempt) {
    $claim = $this->idempotencyOrFail()->claim($plan);

    if ($claim->kind === IdempotencyClaimKind::Replay) {
        return ActionPipelineDecision::continueWith($state->withOutput($claim->output));
    }
    if ($claim->kind !== IdempotencyClaimKind::Claimed) {
        return $this->haltForClaim($claim, $state);
    }
}
```

Only after `Claimed` call `$executor->execute(...)`.

- [ ] **Step 4: Implement completion and exception behavior**

```php
try {
    $output = $this->executor->execute($state->definition, $state->input, $state->context);
} catch (\Throwable $e) {
    if ($plan?->kind === IdempotencyExecutionPlanKind::FreshAttempt) {
        try {
            $this->idempotencyOrFail()->markIndeterminate($plan);
        } catch (\Throwable) {
            // Conservative store record remains in_progress when transition could not be written.
        }
    }
    throw $e;
}

if ($plan?->kind === IdempotencyExecutionPlanKind::FreshAttempt) {
    try {
        $this->idempotencyOrFail()->complete($plan, $output);
    } catch (UnreplayableIdempotencyOutput $e) {
        try { $this->idempotencyOrFail()->markIndeterminate($plan); } catch (\Throwable) {}
        throw $e;
    }
}
```

Do not catch completion-store failures and convert them to success. Do not delete the claim on any failure path.

- [ ] **Step 5: Run execution tests and verify GREEN**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/ActionExecutionStageTest.php tests/Unit/IdempotencyServiceTest.php
```

Expected: green.

- [ ] **Step 6: Commit Task 5**

```bash
git add packages/laravel/src/Runtime/Pipeline/ActionExecutionStage.php packages/laravel/tests/Unit/ActionExecutionStageTest.php packages/laravel/tests/Unit/IdempotencyServiceTest.php
git commit -m "feat(laravel): claim idempotent execution atomically"
```

---

### Task 6: Add completed replay orchestration, preserve confirmation invariants, and normalize public errors

**Files:**
- Create: `packages/laravel/src/Confirmation/ConfirmationRequirement.php`
- Modify: `packages/laravel/src/Confirmation/ConfirmationStage.php`
- Modify: `packages/laravel/src/Runtime/Pipeline/ActionBus.php`
- Modify: `packages/laravel/src/Result/ActionResultNormalizer.php`
- Modify/Test: `packages/laravel/tests/Unit/ActionBusTest.php`
- Modify/Test: `packages/laravel/tests/Unit/ConfirmationStageTest.php`
- Modify/Test: `packages/laravel/tests/Unit/ActionResultNormalizerTest.php`

**Interfaces:**
- `ConfirmationRequirement::isRequired(ActionDefinition $definition): bool`
- `ConfirmationStage` delegates its existing risk/requirement predicate to that helper.
- `ActionBus` uses the same helper only to preserve the pre-materialized-HumanConfirmation guard when a replay skips the Confirmation stage.

- [ ] **Step 1: Write RED ActionBus replay tests**

Build fake handlers where Idempotency returns a state with `Replay` plan and `withOutput('stored')`. Assert exact log:

```php
self::assertSame(
    ['input_validation', 'authorization', 'idempotency', 'output_policy'],
    $log,
);
```

Also assert:

- output policy receives `'stored'` and may transform it;
- confirmation and execution handler call counts remain zero;
- auditor still records exactly once;
- final outcome uses current invocation context/correlation ID;
- replay does not mutate/create HumanConfirmation.

- [ ] **Step 2: Write RED confirmation-guard replay test**

For a consequential action with a prebuilt `HumanConfirmation` trusted entry and completed replay plan, expect `ConfirmationConfigurationViolation` rather than replay success. This test proves replay cannot bypass T-401's configuration invariant.

- [ ] **Step 3: Write RED normalizer tests for all five codes**

Each code normalizes to `rejected` with static message and no details:

```php
self::assertSame('rejected', $result->status->value);
self::assertNull($result->error?->details);
```

Expected messages:

```text
idempotency_key_required   -> "An idempotency key is required."
idempotency_key_invalid    -> "The idempotency key is invalid."
idempotency_conflict       -> "The idempotency key is already bound to a different invocation intent."
idempotency_in_progress    -> "An invocation with this idempotency key is already in progress."
idempotency_indeterminate  -> "The prior invocation outcome is indeterminate and will not be retried automatically."
```

Assert injected halt details containing fake raw key/hash/payload are discarded.

- [ ] **Step 4: Run focused tests and verify RED**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/ActionBusTest.php tests/Unit/ConfirmationStageTest.php tests/Unit/ActionResultNormalizerTest.php
```

Expected RED: replay orchestration and idempotency normalization are absent.

- [ ] **Step 5: Extract `ConfirmationRequirement` without changing fresh T-401 semantics**

```php
public static function isRequired(ActionDefinition $definition): bool
{
    return $definition->risk === ActionRisk::Consequential
        || in_array(ContextRequirement::HumanConfirmation, $definition->contextRequirements, true);
}
```

`ConfirmationStage` still performs its existing pre-materialized authority check and receipt logic for every fresh confirmation-required attempt.

- [ ] **Step 6: Implement explicit replay skipping in ActionBus**

Do not put idempotency-specific replay logic into `ConfirmationStage`.

In the stage loop, before invoking Confirmation/Execution:

```php
$plan = $state->idempotencyPlan;
$isReplay = $plan?->kind === IdempotencyExecutionPlanKind::Replay;

if ($isReplay && $stage === ActionPipelineStage::Confirmation) {
    if (ConfirmationRequirement::isRequired($state->definition)
        && $state->context->has(ContextRequirement::HumanConfirmation)) {
        throw ConfirmationConfigurationViolation::preMaterializedAuthority();
    }
    continue;
}

if ($isReplay && $stage === ActionPipelineStage::Execution) {
    continue;
}
```

OutputPolicy still runs normally. The final `hasOutput` invariant remains active.

- [ ] **Step 7: Map public idempotency rejections**

Add explicit match arms in `ActionResultNormalizer`. Do not use generic halt details for any of these five codes.

- [ ] **Step 8: Run focused tests and verify GREEN**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/ActionBusTest.php tests/Unit/ConfirmationStageTest.php tests/Unit/ActionResultNormalizerTest.php
```

Expected: green.

- [ ] **Step 9: Commit Task 6**

```bash
git add packages/laravel/src/Confirmation packages/laravel/src/Runtime/Pipeline/ActionBus.php packages/laravel/src/Result/ActionResultNormalizer.php packages/laravel/tests/Unit/ActionBusTest.php packages/laravel/tests/Unit/ConfirmationStageTest.php packages/laravel/tests/Unit/ActionResultNormalizerTest.php
git commit -m "feat(laravel): replay completed idempotent results"
```

---

### Task 7: Prove full ActionBus idempotency and T-401/T-402 combined behavior

**Files:**
- Create/Test: `packages/laravel/tests/Integration/IdempotencyPipelineIntegrationTest.php`
- Modify/Test: `packages/laravel/tests/Integration/ConfirmationPipelineIntegrationTest.php`
- Modify/Test: `packages/laravel/tests/Fixtures/PrepList/PrepListTestPipeline.php`

**Interfaces:**
- Integration harness uses real `LaravelInputValidationStage`, real authorization stage, real `IdempotencyStage`, real `ConfirmationStage` where required, real `ActionExecutionStage`, test-only OutputPolicy spy, real `ActionResultNormalizer`, and a deterministic store/clock. At least one store-focused test in Task 3 already covers the concrete database adapter; the full pipeline integration may use a deterministic in-memory `IdempotencyStore` fake to make race/failure injection precise.

- [ ] **Step 1: Write the lost-response consequential replay RED test**

Scenario:

```text
first call: validated -> authorized -> idempotency fresh -> confirmation_required
trusted bridge approves challenge
second call with receipt + same key: claim -> executor once -> completed -> success
simulate client losing response
third call with same key + same validated intent + no usable old receipt:
  validation reruns
  authorization reruns
  idempotency sees completed exact record
  confirmation skipped
  executor skipped
  output policy reruns
  result succeeds with third call correlation ID
executor total count = 1
```

Use counters for authorization, confirmation service consume, executor, output policy, and auditor.

- [ ] **Step 2: Write RED conflict/partition/context tests**

Prove:

- same partition/key + changed validated input conflicts;
- changed current record conflicts;
- changed current selection conflicts;
- changed browser session conflicts when tenant/actor partition stays same;
- different actor partition can use same raw key independently;
- different tenant partition can use same raw key independently;
- different action ID/version can use same raw key independently;
- different surface alone replays;
- different binding ID alone replays;
- authorization denial occurs before replay and does not leak the stored result;
- `in_progress` / `indeterminate` never reaches confirmation/execution;
- `required_key` rejection occurs before confirmation/execution;
- `none` policy still executes twice when invoked twice, proving T-402 does not silently change unprotected actions.

- [ ] **Step 3: Write RED execution-failure/replay-safety tests**

Prove:

- executor performs a simulated side effect counter then throws; same key cannot execute the counter twice;
- successful executor with unsupported object output does not reopen execution;
- completion-store failure never produces succeeded result and subsequent same-key invocation remains blocked or observes completed if the DB write actually committed;
- `now == expiresAt` allows a new bounded-window claim and therefore a second execution only after the retention guarantee has expired.

- [ ] **Step 4: Run integration tests and verify RED**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Integration/IdempotencyPipelineIntegrationTest.php tests/Integration/ConfirmationPipelineIntegrationTest.php
```

Expected RED: full T-402 wiring is not yet assembled in the harness.

- [ ] **Step 5: Wire the integration harness and prep-list fixture**

Replace idempotency passthrough with real idempotency wiring only where the fixture definition/purpose expects it. Keep prep-list behavior compatible with its declared policy; do not invent browser-specific key generation.

Any test-only fake store must implement the same `IdempotencyStore` interface and model exact state transitions; never add an in-memory production fallback.

- [ ] **Step 6: Run integration tests and verify GREEN**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Integration/IdempotencyPipelineIntegrationTest.php tests/Integration/ConfirmationPipelineIntegrationTest.php tests/Integration/PrepListLivewireE2ETest.php
```

Expected: green; consequential lost-response path executes exactly once.

- [ ] **Step 7: Run the complete Laravel suite before documentation**

```bash
cd packages/laravel
./vendor/bin/phpunit
```

Expected: entire package green on the current development environment.

- [ ] **Step 8: Commit Task 7**

```bash
git add packages/laravel/tests/Integration packages/laravel/tests/Fixtures/PrepList/PrepListTestPipeline.php
git commit -m "test(laravel): prove idempotent replay integration"
```

---

### Task 8: Update decisions/security docs, verify whole repository, and prepare review checkpoint

**Files:**
- Modify: `docs/DECISION-REGISTER.md`
- Modify: `docs/GLOSSARY.md`
- Modify: `docs/THREAT-MODEL.md`
- Modify: `CONFORMANCE.md`
- Modify: `TASKS.md`
- Modify: `STATUS.md`
- Modify: `REVIEW_REQUEST.md`
- Do not modify: `spec/0.1/**`

**Interfaces / decision:**
- Add accepted `D-045` with the exact implemented semantics from the design: bounded server-side deduplication, hashed caller key inside exact action/trusted partition, validated-intent binding, atomic pre-execution claim, successful pre-output-policy replay storage, exact completed replay, fail-closed conflict/concurrency/indeterminate reuse, and independent raw-key reuse across different action versions/trusted partitions.

- [ ] **Step 1: Update architecture/security documentation from actual code, not assumptions**

`GLOSSARY.md` must document:

```text
none: no deduplication even if key present
recommended_key: deduplicate only when non-null valid key supplied
required_key: reject missing/invalid key before execution
completed exact retry: replay prior pre-policy execution output
in_progress/conflict/indeterminate: no automatic execution
retention default 24h, strict now < expiresAt
```

`THREAT-MODEL.md` T12 must state that T-402 is implemented in Laravel and distinguish bounded idempotency from T-401 single-use confirmation.

`CONFORMANCE.md` Trust scenario 15 should cite Laravel reference-runtime coverage while preserving shared-runner status.

- [ ] **Step 2: Update task/status/review records**

Mark T-402 implementation state only after all verification below is green. Record exact commit SHAs and workflow run IDs. Keep T-403/T-404 unstarted.

`REVIEW_REQUEST.md` must summarize:

- trusted partition + intent model;
- durable DB store and migration;
- state machine and retention;
- pipeline reorder/replay rule;
- T-401 combined-control proof;
- secret/non-leakage boundary;
- exact test/CI evidence;
- explicit statement that `spec/0.1` is unchanged.

- [ ] **Step 3: Run PHP lint and full Laravel tests**

```bash
find packages/laravel/src packages/laravel/tests -name '*.php' -print0 | xargs -0 -n1 php -l
cd packages/laravel && ./vendor/bin/phpunit
```

Expected: all green.

- [ ] **Step 4: Run contract validation**

From repository root:

```bash
python scripts/validate.py
```

Expected: green with the same frozen `spec/0.1` contract/fixture baseline unless non-spec documentation-only scenario evidence legitimately changes outside the frozen schema tree.

- [ ] **Step 5: Run browser isolation verification**

```bash
cd browser-runtime
npm ci
npm run typecheck
npm test -- --run
```

Expected: TypeScript typecheck green and existing Vitest suite green; T-402 must not require browser production changes.

- [ ] **Step 6: Diff and scope review**

Run:

```bash
git diff --check main...HEAD
git diff --name-status main...HEAD
git diff --stat main...HEAD
git diff main...HEAD -- spec/0.1
```

Expected:

```text
no whitespace errors
no spec/0.1 diff
no T-403 output-redaction implementation
no T-404 structured-audit persistence implementation
no browser production behavior change
```

Also grep for accidental secret projection:

```bash
grep -R "idempotencyKey\|key_hash\|intent_fingerprint\|output_payload" packages/laravel/src/Result packages/laravel/src/Runtime/Context -n
```

Every match must be reviewed; public result/provenance code must not emit raw key/hash/fingerprint/payload values.

- [ ] **Step 7: Commit documentation/review checkpoint**

```bash
git add docs/DECISION-REGISTER.md docs/GLOSSARY.md docs/THREAT-MODEL.md CONFORMANCE.md TASKS.md STATUS.md REVIEW_REQUEST.md
git commit -m "docs(status): prepare T-402 review checkpoint"
```

- [ ] **Step 8: Verify exact checkpoint in CI**

Push `feat/idempotency-store` and require all seven existing validation jobs green:

```text
browser
php-lint
contract
PHP 8.3 / Illuminate 12
PHP 8.4 / Illuminate 12
PHP 8.3 / Illuminate 13
PHP 8.4 / Illuminate 13
```

If any matrix job exposes Laravel-version-specific database behavior, fix it TDD-first on the same task; do not weaken the idempotency contract to make the matrix pass.

- [ ] **Step 9: Perform final review preparation**

Compare exact base `b94ed83497e213c155ae0276264e954b9acd3ac3` to final head and verify:

```text
merge base is exact T-401 post-merge main baseline
branch is ahead only, not behind
D-045 is ACCEPTED only because behavior is implemented/tested
all T-402 security-negative tests are present
T-401 confirmation regression tests remain green
raw key is absent from database schema
no PHP serialization is used for replay payloads
no application executor runs under DB transaction/row lock
```

Then open external review/PR as a separate explicit gate. Do not merge automatically.
