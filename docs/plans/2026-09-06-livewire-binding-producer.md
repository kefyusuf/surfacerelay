# Livewire Mounted Binding Producer Implementation Plan

**Goal:** Implement T-203 as a trusted server-side producer that turns exact T-202 exposures into fresh component-scoped Runtime Bindings for the exact Livewire component identity, without pretending PHP request teardown is browser component lifetime.

**Spec:** `docs/design/livewire-binding-producer.md`

## Global constraints

- Do not modify `spec/0.1`.
- Do not add `livewire/livewire` as a Composer dependency.
- Do not accept `componentId` or `bindingId` from caller/action input at the producer boundary.
- Do not use Livewire server `destroy` as binding invalidation authority.
- Do not add browser registration, `Livewire.find()`, WebMCP, stale-target resolution, or ActionBus execution.
- Preserve exact T-202 exposure ordering and exact `ActionDefinition` objects.
- Stop before T-204.

## Task 1 — Fresh opaque binding IDs

Create:

```text
packages/laravel/src/Binding/BindingIdGenerator.php
packages/laravel/src/Binding/RandomBindingIdGenerator.php
```

Contract:

```php
interface BindingIdGenerator
{
    public function generate(): string;
}
```

Reference generator uses native `random_bytes()` and returns an opaque value satisfying the frozen RuntimeBinding `bindingId` bounds. It must not derive IDs from component/action/method data.

Tests:

- generator contract exists;
- generated IDs are non-empty and <= 240 characters;
- repeated generation produces distinct IDs in normal operation;
- generated IDs can construct a RuntimeBinding.

Commit: `feat(binding): add fresh binding ID generation`.

## Task 2 — Trusted mounted component identity

Create:

```text
packages/laravel/src/Livewire/Identity/LivewireComponentIdentityResolver.php
packages/laravel/src/Livewire/Identity/MethodLivewireComponentIdentityResolver.php
```

Contract:

```php
interface LivewireComponentIdentityResolver
{
    public function resolve(object $component): string;
}
```

Reference behavior:

- requires callable `getId()`;
- invokes only `getId()` as the trusted identity accessor;
- requires a non-empty string result;
- accepts no caller-provided ID or metadata fallback;
- does not inspect component class/name as a replacement locator.

Failures are reported through the producer's focused adapter exception.

Tests:

- exact non-empty ID is preserved;
- missing `getId()` fails loudly;
- empty result fails loudly;
- non-string result fails loudly.

Commit: `feat(livewire): resolve trusted component identity`.

## Task 3 — Produce fresh component bindings

Create:

```text
packages/laravel/src/Livewire/Binding/InvalidLivewireBindingProduction.php
packages/laravel/src/Livewire/Binding/LivewireBindingProducer.php
```

Producer dependencies:

```php
LivewireActionExposureReader
LivewireComponentIdentityResolver
BindingIdGenerator
```

Algorithm:

1. Resolve exact component ID from the trusted component object.
2. Read deterministic T-202 exposures.
3. For each exposure request one fresh binding ID.
4. Reject duplicate generated IDs within the batch.
5. Build `LivewireBindingTarget(componentId, exposure.method)`.
6. Reuse the exact exposure `ActionDefinition` in `LivewireRuntimeBinding::forComponent()`.
7. Preserve exposure ordering; random IDs never affect sort order.
8. Default `expiresAt` to null and extensions to empty.

Tests:

- exact exposures become exact RuntimeBindings;
- driver is always `livewire`;
- lifecycle is always `component`;
- target uses resolver component ID + explicit exposure method;
- exact ActionDefinition object is retained;
- one fresh ID per exposure;
- repeated production for the same component gets new IDs;
- replacement component gets a different exact target and old binding is not mutated;
- empty exposure list returns empty list;
- deterministic exposure order is preserved independent of binding IDs;
- duplicate generated IDs fail loudly with no returned batch;
- exposure-reader configuration errors propagate;
- producer never invokes exposed methods;
- `expiresAt` is null and extensions are empty by default.

Commit: `feat(livewire): produce mounted component bindings`.

## Task 4 — Decision and checkpoint

Update:

```text
docs/DECISION-REGISTER.md
TASKS.md
STATUS.md
REVIEW_REQUEST.md
```

Add the next free accepted decision (expected D-033): Livewire PHP request teardown is not component-lifecycle authority; server binding production derives exact mounted component ID and fresh IDs, while browser lifecycle cleanup/stale resolution belongs to M3 client signals. Do not use server `destroy` to revoke/retarget component bindings.

Verification:

```bash
cd packages/laravel
composer test -- --filter "LivewireBindingProducer|BindingIdGenerator|ComponentIdentity"
composer test
composer validate --strict
cd ../..
python scripts/validate.py
```

GitHub Actions must be green for:

```text
contract
PHP 8.3/8.4 × Illuminate 12/13
php-lint
browser
```

Record actual test/assertion counts. Mark T-203 DONE, T-204 next/not started. Perform a final `main...feat/livewire-binding-producer` review and stop before T-204.