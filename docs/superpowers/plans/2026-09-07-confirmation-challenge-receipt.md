# T-401 Confirmation Challenge / Receipt Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement single-use opaque confirmation authority for consequential / human-confirmation actions without changing `spec/0.1` wire shapes.

**Architecture:** Keep confirmation authority entirely server-side behind a new `Confirmation` package boundary. `ActionCall` carries caller-supplied `bindingId` and `confirmationReceipt` as non-authoritative invocation candidates; the confirmation stage runs only after validation and authorization, derives a deterministic scope fingerprint, atomically consumes an approved opaque token, and only then materializes `ContextRequirement::HumanConfirmation` into a new immutable `InvocationContext`. A lock-capable Laravel cache store provides the production pending → approved → consumed state machine.

**Tech Stack:** PHP 8.3+, Laravel / Illuminate 12 and 13 contracts, PHPUnit 11, Laravel cache locks, native `random_bytes()`, SHA-256 and JSON encoding.

**Spec:** `docs/superpowers/specs/2026-09-07-confirmation-challenge-receipt-design.md`

## Global Constraints

- Work only on `T-401`; do not implement T-402 idempotency, T-403 output policy, T-404 structured audit, or T-504 UI.
- Base is `main@5eb33c203fb40fc2ff744f2f4a57cbce4c704b80`; feature branch is `feat/confirmation-challenge-receipt`.
- `spec/0.1` schemas and wire shapes stay frozen.
- `confirmed=true`, metadata, action input, challenge IDs in pending state, and prebuilt `HumanConfirmation` entries must never grant confirmation authority.
- Confirmation is mandatory when `risk === consequential` OR the definition declares `human_confirmation`.
- Every declared context requirement except `human_confirmation` remains fail-closed in the ActionBus pre-stage gate.
- Receipt authority is one 32-byte random opaque base64url token with server-side `pending → approved → consumed/expired` state; raw token is never stored server-side.
- Challenge TTL is 300 seconds and approved-receipt TTL is 120 seconds by default; both are positive constructor/config overrides.
- Expiry is strict: valid only when `now < expiresAt`; equality is expired.
- Exact scope includes action ID/version, surface, binding ID or null, validated input, and present trusted actor/tenant/current-record/current-selection/browser-session context.
- `correlationId`, `idempotencyKey`, and `human_confirmation` are excluded from scope.
- Cache-backed production storage must require `Illuminate\\Contracts\\Cache\\Store` that also implements `Illuminate\\Contracts\\Cache\\LockProvider`; no unlocked fallback is allowed.
- Receipt scope mismatch must not consume an otherwise-valid approved receipt.
- Successful exact-scope consumption spends the receipt before downstream idempotency/execution. Later failure does not restore it.
- Raw token, token hash, scope hash, actor/tenant values, or other authority material must not leak into `ActionResult.meta`, halt details, provenance, or exceptions.
- Follow strict TDD: add a test, run it and observe the intended RED failure, then write the minimum production code and rerun GREEN.

---

### Task 1: Add the non-authoritative invocation seam

**Files:**
- Modify: `packages/laravel/src/Runtime/Pipeline/ActionCall.php`
- Modify: `packages/laravel/src/Runtime/Pipeline/ActionPipelineState.php`
- Modify: `packages/laravel/src/Runtime/Pipeline/ActionBus.php`
- Modify: `packages/laravel/src/Runtime/InvocationContext.php`
- Modify/Test: `packages/laravel/tests/Unit/ActionBusTest.php`
- Modify/Test: `packages/laravel/tests/Unit/InvocationContextTest.php`
- Create/Test: `packages/laravel/tests/Unit/ActionCallTest.php`

**Interfaces:**
- `ActionCall::__construct(string $actionId, int $actionVersion, array $input, InvocationContext $context, ?string $bindingId = null, ?string $confirmationReceipt = null)`
- `ActionPipelineState` gains readonly `?string $bindingId` and `?string $confirmationReceipt` and preserves them through `withInput()` / `withOutput()`.
- `ActionPipelineState::withContext(InvocationContext $context): self`
- `InvocationContext::withTrustedEntry(TrustedContextEntry $entry): self`

- [ ] **Step 1: Write RED tests for candidate separation and immutable propagation**

Add tests proving the following API contract:

```php
$context = new InvocationContext(
    surface: 'webmcp',
    correlationId: 'corr-1',
    metadata: ['confirmationReceipt' => 'attacker-metadata'],
);
$call = new ActionCall(
    actionId: 'orders.refund.commit',
    actionVersion: 1,
    input: ['confirmed' => true],
    context: $context,
    bindingId: 'binding-1',
    confirmationReceipt: 'candidate-token',
);

self::assertSame('binding-1', $call->bindingId);
self::assertSame('candidate-token', $call->confirmationReceipt);
self::assertFalse($context->has(ContextRequirement::HumanConfirmation));
```

Also assert that a state transformed by validation/output keeps the exact candidate fields, and `withTrustedEntry()` returns a new context preserving surface, correlation ID, idempotency key, metadata, and all existing entries.

- [ ] **Step 2: Run the focused tests and verify RED**

Run:

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/ActionCallTest.php tests/Unit/InvocationContextTest.php tests/Unit/ActionBusTest.php
```

Expected RED: unknown named arguments/properties/methods for `bindingId`, `confirmationReceipt`, or `withTrustedEntry()`.

- [ ] **Step 3: Implement the minimal invocation seam**

`ActionCall` appends the two nullable constructor parameters. Enforce frozen lexical limits without creating authority:

```php
if ($this->bindingId !== null) {
    $length = mb_strlen($this->bindingId, 'UTF-8');
    if ($length < 1 || $length > 240) {
        throw new \\InvalidArgumentException('ActionCall bindingId must be null or 1..240 characters.');
    }
}
if ($this->confirmationReceipt !== null && mb_strlen($this->confirmationReceipt, 'UTF-8') > 4096) {
    throw new \\InvalidArgumentException('ActionCall confirmationReceipt must be null or at most 4096 characters.');
}
```

Do **not** reject an empty receipt candidate here because the frozen JSON schema has only `maxLength`; it simply cannot match a real generated token later.

`ActionBus` constructs state with the two candidate fields. `ActionPipelineState::withInput()`, `withOutput()`, and new `withContext()` preserve every field. `InvocationContext::withTrustedEntry()` rebuilds from `allTrusted()` plus the supplied trusted entry; duplicate detection remains owned by the constructor.

- [ ] **Step 4: Run focused tests and verify GREEN**

Run the same PHPUnit command. Expect all selected tests green.

- [ ] **Step 5: Commit**

```bash
git add packages/laravel/src/Runtime packages/laravel/tests/Unit/ActionCallTest.php packages/laravel/tests/Unit/InvocationContextTest.php packages/laravel/tests/Unit/ActionBusTest.php
git commit -m "feat(laravel): carry confirmation invocation candidates"
```

---

### Task 2: Build deterministic confirmation scope fingerprints

**Files:**
- Modify: `packages/laravel/src/Runtime/Context/ResolvedTrustedValue.php`
- Modify: `packages/laravel/src/Runtime/Context/TrustedContextEntry.php`
- Modify: `packages/laravel/src/Runtime/Context/TrustedContextComposer.php`
- Modify: `packages/laravel/src/Auth/LaravelAuthenticatedActorResolver.php`
- Create: `packages/laravel/src/Confirmation/ConfirmationScopeHasher.php`
- Create: `packages/laravel/src/Confirmation/UnrepresentableConfirmationScope.php`
- Create/Test: `packages/laravel/tests/Unit/ConfirmationScopeHasherTest.php`
- Modify/Test: `packages/laravel/tests/Unit/TrustedContextComposerTest.php`
- Modify/Test: `packages/laravel/tests/Unit/LaravelAuthenticatedActorResolverTest.php`

**Interfaces:**
- `ResolvedTrustedValue::__construct(mixed $value, ContextProvenance $provenance, ?string $confirmationScopeKey = null)`
- `TrustedContextEntry::__construct(ContextRequirement $requirement, mixed $value, ContextProvenance $provenance, ?string $confirmationScopeKey = null)`
- `ConfirmationScopeHasher::fingerprint(ActionPipelineState $state): string`

- [ ] **Step 1: Write RED tests for canonicalization and scope dimensions**

Create tests that prove:

```php
$hashA = $hasher->fingerprint($this->state(
    input: ['b' => 2, 'a' => ['z' => 3, 'y' => 2]],
));
$hashB = $hasher->fingerprint($this->state(
    input: ['a' => ['y' => 2, 'z' => 3], 'b' => 2],
));
self::assertSame($hashA, $hashB);
```

Then verify changing any one of action ID, action version, surface, binding ID, validated input, actor, tenant, current record, current selection, or browser session changes the hash. Verify `correlationId`, `idempotencyKey`, metadata, and an existing HumanConfirmation entry do not affect it.

Verify arbitrary objects, resources, closures, non-finite floats, associative arrays with non-string keys, and empty `confirmationScopeKey` fail closed via `UnrepresentableConfirmationScope` / constructor validation.

- [ ] **Step 2: Run focused tests and verify RED**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/ConfirmationScopeHasherTest.php tests/Unit/TrustedContextComposerTest.php tests/Unit/LaravelAuthenticatedActorResolverTest.php
```

Expected RED: new scope-key properties and hasher types are absent.

- [ ] **Step 3: Implement stable context scope keys**

Add optional non-secret scope key properties; `TrustedContextComposer` copies the resolver-provided key into its entry.

For Laravel authenticated users, derive a stable key only when the user implements `Illuminate\\Contracts\\Auth\\Authenticatable` and exposes a scalar/stringable identifier:

```php
$scopeKey = null;
if ($user instanceof Authenticatable) {
    $identifier = $user->getAuthIdentifier();
    if (is_scalar($identifier) || $identifier instanceof \\Stringable) {
        $scopeKey = implode(':', [
            $user::class,
            $user->getAuthIdentifierName(),
            (string) $identifier,
        ]);
    }
}
```

Do not break low-risk resolution for non-Authenticatable test/application objects; leave their scope key null. Confirmation scope derivation will fail only when such an object is actually required in a confirmation scope.

- [ ] **Step 4: Implement canonical scope hashing**

`ConfirmationScopeHasher` constructs this logical document in exact key order before canonical recursive encoding:

```php
[
    'action' => ['id' => $definition->id, 'version' => $definition->version],
    'surface' => $context->surface,
    'bindingId' => $state->bindingId,
    'input' => $state->input,
    'context' => [
        'authenticated_actor' => <scope key or canonical value if present>,
        'tenant' => ...,
        'current_record' => ...,
        'current_selection' => ...,
        'browser_session' => ...,
    ],
]
```

Canonicalize lists recursively; for associative arrays require all string keys and `ksort(..., SORT_STRING)`. Encode with:

```php
$json = json_encode(
    $canonical,
    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
);
return hash('sha256', "surfacerelay.confirmation.scope.v1\n" . $json);
```

Wrap every trusted-context value as either `{scopeKey: <string>}` or `{value: <canonical value>}` so a raw string can never collide semantically with an explicit resolver scope key.

- [ ] **Step 5: Run focused tests and verify GREEN**

Run the Task 2 PHPUnit command and require all selected tests green.

- [ ] **Step 6: Commit**

```bash
git add packages/laravel/src/Confirmation packages/laravel/src/Runtime/Context packages/laravel/src/Auth packages/laravel/tests/Unit/ConfirmationScopeHasherTest.php packages/laravel/tests/Unit/TrustedContextComposerTest.php packages/laravel/tests/Unit/LaravelAuthenticatedActorResolverTest.php
git commit -m "feat(laravel): bind confirmation to deterministic scope"
```

---

### Task 3: Implement the opaque confirmation state machine and service

**Files:**
- Create: `packages/laravel/src/Confirmation/ConfirmationRecordState.php`
- Create: `packages/laravel/src/Confirmation/ConfirmationRecord.php`
- Create: `packages/laravel/src/Confirmation/ConfirmationStore.php`
- Create: `packages/laravel/src/Confirmation/ConfirmationClock.php`
- Create: `packages/laravel/src/Confirmation/SystemConfirmationClock.php`
- Create: `packages/laravel/src/Confirmation/ConfirmationTokenGenerator.php`
- Create: `packages/laravel/src/Confirmation/RandomConfirmationTokenGenerator.php`
- Create: `packages/laravel/src/Confirmation/ConfirmationTokenGenerationFailed.php`
- Create: `packages/laravel/src/Confirmation/ConfirmationService.php`
- Create/Test: `packages/laravel/tests/Unit/ConfirmationServiceTest.php`

**Interfaces:**

```php
interface ConfirmationStore
{
    public function createPending(string $tokenHash, ConfirmationRecord $record, int $ttlSeconds): bool;
    public function approvePending(string $tokenHash, int $now, int $receiptExpiresAt): bool;
    public function consumeApproved(string $tokenHash, string $expectedScopeFingerprint, int $now): bool;
}

interface ConfirmationClock
{
    public function now(): int;
}

interface ConfirmationTokenGenerator
{
    public function generate(): string;
}
```

`ConfirmationService` exposes:

```php
public function issueChallenge(string $scopeFingerprint, string $summary): ConfirmationChallenge;
public function approveChallenge(string $challengeId): ?string;
public function consumeReceipt(string $candidate, string $scopeFingerprint): bool;
```

- [ ] **Step 1: Write RED tests for token/state/expiry semantics**

Use deterministic fake clock/token generator/store and prove:

- issue produces one 43-character base64url token and RFC3339 UTC expiry at `now + 300`;
- store receives only `sha256(token)`, never raw token;
- collision causes another generated token, bounded to three attempts, then `ConfirmationTokenGenerationFailed`;
- approval of pending/unexpired token returns the **same** token and records `receiptExpiresAt = now + 120`;
- unknown/pending-as-receipt/expired/repeat approval returns no authority;
- exact approved receipt consumes once;
- replay fails;
- scope mismatch fails without consuming the valid record;
- equality with challenge/receipt expiry is expired;
- zero/negative TTL configuration throws before any store operation;
- malformed candidates never expose candidate contents in exception messages.

- [ ] **Step 2: Run service tests and verify RED**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/ConfirmationServiceTest.php
```

Expected RED: confirmation state-machine/service types do not exist.

- [ ] **Step 3: Implement value objects, generators, clock and service**

`RandomConfirmationTokenGenerator` must use exactly:

```php
return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
```

`ConfirmationRecord` contains scalar/cache-safe fields only:

```php
public function __construct(
    public ConfirmationRecordState $state,
    public string $scopeFingerprint,
    public string $summary,
    public int $issuedAt,
    public int $challengeExpiresAt,
    public ?int $receiptExpiresAt = null,
) {}
```

`toArray()` / `fromArray()` use strings/ints/null only and reject corrupt impossible combinations. `ConfirmationService` hashes candidates with `hash('sha256', $token)` and accepts real generated receipt candidates only when they match `^[A-Za-z0-9_-]{43}$`.

Use `gmdate('Y-m-d\\TH:i:s\\Z', $expiresAt)` for public challenge expiry.

- [ ] **Step 4: Run service tests and verify GREEN**

Run `./vendor/bin/phpunit tests/Unit/ConfirmationServiceTest.php` and require green.

- [ ] **Step 5: Commit**

```bash
git add packages/laravel/src/Confirmation packages/laravel/tests/Unit/ConfirmationServiceTest.php
git commit -m "feat(laravel): add opaque confirmation state machine"
```

---

### Task 4: Add the lock-backed Laravel cache store

**Files:**
- Create: `packages/laravel/src/Confirmation/CacheConfirmationStore.php`
- Create: `packages/laravel/src/Confirmation/ConfirmationStoreUnavailable.php`
- Create: `packages/laravel/src/Confirmation/CorruptConfirmationRecord.php`
- Create/Test: `packages/laravel/tests/Unit/CacheConfirmationStoreTest.php`

**Interfaces:**
- `CacheConfirmationStore::__construct(Illuminate\\Contracts\\Cache\\Store $store)`; constructor rejects stores that are not also `Illuminate\\Contracts\\Cache\\LockProvider`.
- Implements the `ConfirmationStore` interface from Task 3.

- [ ] **Step 1: Inspect the installed Illuminate 12/13 contract signatures before writing the fake**

After dependency setup, inspect:

```bash
cd packages/laravel
php -r 'require "vendor/autoload.php"; foreach ([Illuminate\\Contracts\\Cache\\Store::class, Illuminate\\Contracts\\Cache\\LockProvider::class, Illuminate\\Contracts\\Cache\\Lock::class] as $c) { $r=new ReflectionClass($c); echo $c, PHP_EOL; foreach ($r->getMethods() as $m) echo "  ",$m,PHP_EOL; }'
```

Use those exact supported contracts; do not invent a facade API.

- [ ] **Step 2: Write RED tests for atomicity/fail-closed storage**

Prove:

- constructor rejects a `Store` without `LockProvider`;
- `createPending()` acquires a per-token lock, refuses collision, writes scalar `ConfirmationRecord::toArray()` with requested TTL;
- `approvePending()` under lock refuses missing/corrupt/non-pending/expired records and never resets an already-approved receipt expiry;
- `consumeApproved()` under lock refuses pending/expired/scope mismatch; mismatch leaves record intact; exact scope removes record before returning true;
- lock acquisition failure throws `ConfirmationStoreUnavailable` instead of falling back to an unlocked mutation.

- [ ] **Step 3: Run store tests and verify RED**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/CacheConfirmationStoreTest.php
```

- [ ] **Step 4: Implement lock-backed operations**

Use stable key namespaces such as:

```php
private function recordKey(string $tokenHash): string
{
    return 'surfacerelay:confirmation:record:' . $tokenHash;
}

private function lockName(string $tokenHash): string
{
    return 'surfacerelay:confirmation:lock:' . $tokenHash;
}
```

Acquire via the `LockProvider` contract for every mutation. If the lock cannot be obtained, throw. Always release in `finally`. Never perform `get → put/delete` outside the lock. Cache only scalar arrays from `ConfirmationRecord::toArray()` so Laravel 13 cache serialization hardening does not depend on unserializing project domain objects.

- [ ] **Step 5: Run store + service tests and verify GREEN**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/CacheConfirmationStoreTest.php tests/Unit/ConfirmationServiceTest.php
```

- [ ] **Step 6: Commit**

```bash
git add packages/laravel/src/Confirmation packages/laravel/tests/Unit/CacheConfirmationStoreTest.php
git commit -m "feat(laravel): add atomic confirmation cache store"
```

---

### Task 5: Enforce confirmation in the canonical pipeline

**Files:**
- Create: `packages/laravel/src/Confirmation/VerifiedConfirmation.php`
- Create: `packages/laravel/src/Confirmation/ConfirmationConfigurationViolation.php`
- Create: `packages/laravel/src/Confirmation/ConfirmationStage.php`
- Modify: `packages/laravel/src/Runtime/Pipeline/ActionBus.php`
- Modify: `packages/laravel/src/Runtime/Pipeline/ActionPipelineHalt.php`
- Modify: `packages/laravel/src/Result/CoreActionErrorCode.php`
- Create/Test: `packages/laravel/tests/Unit/ConfirmationStageTest.php`
- Modify/Test: `packages/laravel/tests/Unit/ActionBusTest.php`

**Interfaces:**
- `CoreActionErrorCode::CONFIRMATION_REQUIRED = 'confirmation_required'` as internal implemented vocabulary.
- `ActionPipelineHalt` gains optional typed `?ConfirmationChallenge $confirmation = null` separate from non-authoritative `details`.
- `ConfirmationStage::__construct(ConfirmationService $service, ConfirmationScopeHasher $scopeHasher)`.

- [ ] **Step 1: Write RED tests for the confirmation gate**

Prove all of these separately:

```text
low + no human_confirmation        -> pass through
moderate + human_confirmation      -> challenge
consequential + omitted requirement -> challenge
consequential + preloaded HumanConfirmation -> configuration exception
consequential + missing receipt    -> challenge, no later stage
consequential + invalid receipt    -> fresh challenge, no later stage
consequential + exact approved receipt -> materialize verified entry, continue
```

The successful entry must satisfy:

```php
$entry = $decision->state->context->require(ContextRequirement::HumanConfirmation);
self::assertInstanceOf(VerifiedConfirmation::class, $entry->value);
self::assertSame('surfacerelay.confirmation', $entry->provenance->provider);
self::assertNull($entry->provenance->reference);
```

No raw token/hash/scope hash may be present in the marker/provenance.

- [ ] **Step 2: Run focused tests and verify RED**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/ConfirmationStageTest.php tests/Unit/ActionBusTest.php
```

Expected RED: ActionBus still treats missing HumanConfirmation as a kernel missing-context halt and `ConfirmationStage` does not exist.

- [ ] **Step 3: Narrow the ActionBus pre-stage gate**

Change exactly the missing-context loop:

```php
foreach ($definition->contextRequirements as $requirement) {
    if ($requirement === ContextRequirement::HumanConfirmation) {
        continue;
    }
    if (!$call->context->has($requirement)) {
        $missing[] = $requirement->value;
    }
}
```

Do not change behavior for actor, tenant, record, selection, or browser session.

- [ ] **Step 4: Implement `ConfirmationStage`**

The stage algorithm is exactly:

```php
if (!$this->requiresConfirmation($state->definition)) {
    return ActionPipelineDecision::continueWith($state);
}

if ($state->context->has(ContextRequirement::HumanConfirmation)) {
    throw ConfirmationConfigurationViolation::preMaterializedAuthority();
}

$scope = $this->scopeHasher->fingerprint($state);
$candidate = $state->confirmationReceipt;

if ($candidate !== null && $this->service->consumeReceipt($candidate, $scope)) {
    $entry = new TrustedContextEntry(
        ContextRequirement::HumanConfirmation,
        new VerifiedConfirmation(),
        new ContextProvenance('surfacerelay.confirmation'),
        confirmationScopeKey: 'verified',
    );

    return ActionPipelineDecision::continueWith(
        $state->withContext($state->context->withTrustedEntry($entry)),
    );
}

$challenge = $this->service->issueChallenge($scope, $state->definition->title);
return ActionPipelineDecision::halt(
    new ActionPipelineHalt(CoreActionErrorCode::CONFIRMATION_REQUIRED, confirmation: $challenge),
    $state,
);
```

`VerifiedConfirmation` must contain no token-bearing properties. Its optional scope-key value is irrelevant to scope hashing because HumanConfirmation is excluded; use a constant non-secret marker only if the constructor requires one.

- [ ] **Step 5: Run focused tests and verify GREEN**

Run the Task 5 PHPUnit command and require green.

- [ ] **Step 6: Commit**

```bash
git add packages/laravel/src/Confirmation packages/laravel/src/Runtime/Pipeline/ActionBus.php packages/laravel/src/Runtime/Pipeline/ActionPipelineHalt.php packages/laravel/src/Result/CoreActionErrorCode.php packages/laravel/tests/Unit/ConfirmationStageTest.php packages/laravel/tests/Unit/ActionBusTest.php
git commit -m "feat(laravel): enforce confirmation before execution"
```

---

### Task 6: Normalize real challenges and prove end-to-end trust semantics

**Files:**
- Modify: `packages/laravel/src/Result/ActionResultNormalizer.php`
- Modify/Test: `packages/laravel/tests/Unit/ActionResultNormalizerTest.php`
- Create/Test: `packages/laravel/tests/Integration/ConfirmationPipelineIntegrationTest.php`

**Interfaces:**
- Known `confirmation_required` halt maps only when `$halt->confirmation instanceof ConfirmationChallenge`.
- Malformed known confirmation halts fail loudly via `UnmappedPipelineOutcome`; never fabricate a challenge from `details`.

- [ ] **Step 1: Write RED normalizer tests**

Add:

```php
$challenge = new ConfirmationChallenge('challenge-1', 'Approve refund', '2026-09-07T12:05:00Z');
$outcome = ActionPipelineOutcome::halted(
    $state,
    ActionPipelineStage::Confirmation,
    new PipelineHalt(CoreActionErrorCode::CONFIRMATION_REQUIRED, confirmation: $challenge),
);

self::assertSame([
    'status' => 'confirmation_required',
    'correlationId' => 'corr-123',
    'confirmation' => $challenge->toArray(),
], $this->normalizer->normalize($outcome)->toArray());
```

Also construct a `confirmation_required` halt without the typed challenge and assert `UnmappedPipelineOutcome`.

- [ ] **Step 2: Run normalizer tests and verify RED**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/ActionResultNormalizerTest.php
```

- [ ] **Step 3: Implement the narrow normalizer mapping**

Add only one new match case. Do not expose halt details:

```php
CoreActionErrorCode::CONFIRMATION_REQUIRED => $halt->confirmation !== null
    ? ActionResult::confirmationRequired($correlationId, $halt->confirmation)
    : throw UnmappedPipelineOutcome::forHaltCode($halt->code),
```

- [ ] **Step 4: Write RED integration tests for the full flow**

Build a real ActionBus using real validation, authorization probe, confirmation stage, execution stage, and normalizer. Use deterministic clock/token generator plus a test `ConfirmationStore` whose operations preserve Task 3 atomic semantics.

Required flows:

1. authorization denial occurs before challenge issuance;
2. validation strips/transforms input before the confirmation scope is recorded;
3. first consequential invocation returns real challenge and does not execute;
4. trusted `approveChallenge(challengeId)` returns the same token as receipt;
5. exact second invocation executes once and executor observes verified HumanConfirmation;
6. replay returns a new challenge and never executes twice;
7. receipt is spent even if execution throws after consumption;
8. expiry equality fails closed;
9. one parameterized/data-provider matrix changes action ID, version, validated input, actor, tenant, binding, record, selection, and browser session one at a time; every mismatch yields a new challenge while the original valid receipt remains usable for its original exact scope;
10. caller `confirmed=true`, metadata `human_confirmation`, or metadata `confirmationReceipt` never grants authority;
11. resulting challenge/result/provenance contains no token hash, scope hash, raw approved receipt beyond the required pending `challengeId` field.

- [ ] **Step 5: Run integration tests and verify RED if any required pipeline behavior is still missing**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Integration/ConfirmationPipelineIntegrationTest.php
```

Any failure must correspond to a missing T-401 behavior; fix production code minimally, not by weakening expectations.

- [ ] **Step 6: Run all T-401 focused tests and verify GREEN**

```bash
cd packages/laravel
./vendor/bin/phpunit \
  tests/Unit/ActionCallTest.php \
  tests/Unit/InvocationContextTest.php \
  tests/Unit/ConfirmationScopeHasherTest.php \
  tests/Unit/ConfirmationServiceTest.php \
  tests/Unit/CacheConfirmationStoreTest.php \
  tests/Unit/ConfirmationStageTest.php \
  tests/Unit/ActionBusTest.php \
  tests/Unit/ActionResultNormalizerTest.php \
  tests/Unit/TrustedContextComposerTest.php \
  tests/Unit/LaravelAuthenticatedActorResolverTest.php \
  tests/Integration/ConfirmationPipelineIntegrationTest.php
```

- [ ] **Step 7: Commit**

```bash
git add packages/laravel/src/Result/ActionResultNormalizer.php packages/laravel/tests/Unit/ActionResultNormalizerTest.php packages/laravel/tests/Integration/ConfirmationPipelineIntegrationTest.php packages/laravel/src/Confirmation packages/laravel/src/Runtime
git commit -m "test(laravel): prove scoped confirmation receipt flow"
```

---

### Task 7: Document the implemented trust contract and perform full verification

**Files:**
- Modify: `packages/laravel/src/Result/ConfirmationChallenge.php` comment only, removing the stale “later task T-401” statement after implementation.
- Modify: `docs/DECISION-REGISTER.md`
- Modify: `docs/GLOSSARY.md`
- Modify: `docs/THREAT-MODEL.md`
- Modify: `CONFORMANCE.md` only to state that scenarios 13/14 are exercised by Laravel runtime tests; do not claim T-701 executable conformance-runner support.
- Modify: `TASKS.md`
- Modify: `STATUS.md`
- Modify: `REVIEW_REQUEST.md`

**Decision:**

Add D-044 as ACCEPTED:

> Confirmation grants are single-use opaque bearer capabilities bound to exact action version, validated invocation intent, binding/surface, and relevant trusted runtime context. Only successful server-side receipt verification/consumption may materialize `human_confirmation` authority; challenge IDs and caller fields are never authority.

- [ ] **Step 1: Run the complete PHP suite before changing task status**

```bash
cd packages/laravel
composer validate --strict
composer test
```

Do not mark T-401 DONE unless this is green.

- [ ] **Step 2: Run PHP syntax lint**

From repo root:

```bash
find packages/laravel -name '*.php' -not -path '*/vendor/*' -print0 | xargs -0 -n1 php -l
```

Require zero syntax failures.

- [ ] **Step 3: Run contract validation with the frozen schemas**

```bash
python -m pip install -r requirements-dev.txt
python scripts/validate.py
```

Expected: existing schema/fixture validation remains green; no `spec/0.1` schema change is introduced.

- [ ] **Step 4: Re-run browser verification to prove T-401 isolation**

```bash
cd packages/browser-runtime
npm ci
npm run typecheck
npm test
```

Expected: browser baseline stays green; T-305 cancellation behavior is unaffected.

- [ ] **Step 5: Review the exact branch diff for trust regressions**

Check:

```bash
git diff --check main...HEAD
git diff --stat main...HEAD
git diff main...HEAD -- packages/laravel/src docs TASKS.md STATUS.md REVIEW_REQUEST.md CONFORMANCE.md
```

Explicitly verify:

- no `confirmed` boolean authority path;
- no receipt candidate loaded from metadata/input;
- no raw receipt/hash/scope value in halt/result/provenance;
- no unlocked store mutation;
- no `ActionDefinition` protocol/surface coupling;
- no T-402/T-403/T-404 implementation;
- no `spec/0.1` schema change.

- [ ] **Step 6: Update task/status/review documentation with fresh evidence**

Only after Steps 1–5 are green:

- expand `T-401` in `TASKS.md` to DONE with acceptance criteria and exact verification evidence;
- keep M4 incomplete because T-402..T-404 remain TODO;
- update `STATUS.md` to branch/head, T-401 implementation state and explicit next boundary `T-402 — not started`;
- update `REVIEW_REQUEST.md` to a concise T-401 external-review handoff, not a self-approved review result;
- update D-044, glossary, threat model and conformance wording.

- [ ] **Step 7: Commit documentation checkpoint**

```bash
git add packages/laravel/src/Result/ConfirmationChallenge.php docs/DECISION-REGISTER.md docs/GLOSSARY.md docs/THREAT-MODEL.md CONFORMANCE.md TASKS.md STATUS.md REVIEW_REQUEST.md
git commit -m "docs(status): prepare T-401 review checkpoint"
```

- [ ] **Step 8: Re-run all verification on the exact documentation checkpoint**

Run again:

```bash
cd packages/laravel && composer validate --strict && composer test
cd ../..
find packages/laravel -name '*.php' -not -path '*/vendor/*' -print0 | xargs -0 -n1 php -l
python scripts/validate.py
cd packages/browser-runtime && npm run typecheck && npm test
```

Record exact test/assertion counts and exact HEAD SHA in `STATUS.md` / `REVIEW_REQUEST.md` if a final evidence-only documentation commit is necessary.

- [ ] **Step 9: Verify remote CI on exact T-401 checkpoint**

After the checkpoint exists on the feature branch, inspect the GitHub Actions workflow run for that exact commit. Require all seven jobs to finish successfully before describing T-401 as verification-complete. Do not merge into `main` without a separate explicit user instruction.

---

## Plan Self-Review Checklist

Before implementation starts, confirm:

- [ ] Every security behavior in the design has a corresponding test step above.
- [ ] `ConfirmationStore`, `ConfirmationService`, `ConfirmationStage`, and `ConfirmationScopeHasher` signatures are consistent across tasks.
- [ ] The plan never requires a public endpoint/browser modal/Filament bridge.
- [ ] The plan keeps `spec/0.1` wire shapes unchanged.
- [ ] No task says to implement idempotency, output redaction, structured audit persistence, or rollback.
- [ ] All cache mutations requiring atomicity are explicitly lock-protected and fail closed.
- [ ] Every production-code task begins with a test and an observed RED run.
