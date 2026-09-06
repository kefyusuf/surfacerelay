# Livewire Runtime Binding Implementation Plan

> **For implementation:** execute this plan task-by-task with test-driven development. Each behavior change must be proven by a failing test before production code is added.

**Goal:** Implement T-201 by adding a generic immutable PHP `RuntimeBinding` model plus a typed Livewire component target/factory that produces the existing frozen Runtime Binding shape without introducing Livewire execution or discovery behavior.

**Architecture:** `RuntimeBinding` is protocol-neutral and stores only frozen contract fields plus an exact `ActionDefinition` reference from which `action.id + version` are serialized. `LivewireBindingTarget` owns only exact mounted component instance identity plus explicit method mapping; `LivewireRuntimeBinding` converts that target into a generic binding with `driver=livewire` and `lifecycle=component` fixed by construction. No Livewire package dependency is introduced.

**Tech Stack:** PHP 8.3+, PHPUnit 11, existing SurfaceRelay Laravel package. No new Composer dependency.

**Spec:** `docs/design/livewire-runtime-binding.md`

## Global Constraints

- Do not change `spec/0.1/runtime-binding.schema.json`.
- Preserve D-016, D-022, D-023, D-024, and D-025.
- No binding ID generation, storage, lookup, revocation, lifecycle producer, discovery, component lookup, method reflection, browser runtime, or execution.
- No `livewire/livewire` Composer dependency in T-201.
- `LivewireRuntimeBinding` always emits driver `livewire` and lifecycle `component`.
- Exact Action Definition `id + version` comes from the supplied `ActionDefinition`; no version lookup/fallback exists.
- Binding and Livewire descriptors are immutable.
- Serialization must be deterministic and schema-valid.

---

### Task 1: Generic RuntimeBinding model

**Files:**
- Create: `packages/laravel/src/Binding/BindingLifecycle.php`
- Create: `packages/laravel/src/Binding/InvalidRuntimeBinding.php`
- Create: `packages/laravel/src/Binding/RuntimeBinding.php`
- Create: `packages/laravel/tests/Unit/RuntimeBindingTest.php`

**Interfaces:**
- Consumes: `SurfaceRelay\Laravel\Definition\ActionDefinition`.
- Produces:

```php
namespace SurfaceRelay\Laravel\Binding;

enum BindingLifecycle: string
{
    case Page = 'page';
    case Component = 'component';
    case Session = 'session';
    case Persistent = 'persistent';
}
```

```php
final readonly class RuntimeBinding implements \JsonSerializable
{
    public function __construct(
        public string $bindingId,
        public ActionDefinition $definition,
        public string $driver,
        public BindingLifecycle $lifecycle,
        public array $target,
        public ?string $expiresAt = null,
        public array $extensions = [],
    );

    public function toArray(): array;
    public function jsonSerialize(): array;
}
```

`toArray()` must emit in this deterministic order:

```text
bindingId
→ action { id, version }
→ driver
→ lifecycle
→ target
→ expiresAt
→ extensions only when non-empty
```

- [ ] **Step 1: Write the failing `RuntimeBindingTest`.**

Cover the exact generic contract:

```php
public function test_serializes_exact_action_identity_and_binding_shape(): void
{
    $definition = $this->definition(version: 2);
    $binding = new RuntimeBinding(
        bindingId: 'binding-1',
        definition: $definition,
        driver: 'custom.driver:v1',
        lifecycle: BindingLifecycle::Page,
        target: ['handle' => 'x'],
        expiresAt: '2026-09-07T10:00:00Z',
        extensions: ['example/cache' => ['ttl' => 30]],
    );

    self::assertSame([
        'bindingId' => 'binding-1',
        'action' => ['id' => 'orders.refund', 'version' => 2],
        'driver' => 'custom.driver:v1',
        'lifecycle' => 'page',
        'target' => ['handle' => 'x'],
        'expiresAt' => '2026-09-07T10:00:00Z',
        'extensions' => ['example/cache' => ['ttl' => 30]],
    ], $binding->toArray());
}
```

Also test:

```text
bindingId empty → reject
bindingId > 240 → reject
driver invalid grammar → reject
target [] → reject
expiresAt null → valid
valid RFC3339 with offset/fraction → valid
invalid/missing-timezone/impossible expiresAt → reject
invalid extension key → reject
empty extensions omitted from toArray
all four BindingLifecycle values exactly match frozen vocabulary
readonly class invariant
```

- [ ] **Step 2: Run the focused suite and prove RED.**

Run:

```bash
cd packages/laravel
composer test -- --filter RuntimeBinding
```

Expected: FAIL because `BindingLifecycle` / `RuntimeBinding` do not yet exist.

- [ ] **Step 3: Implement the minimum generic model.**

Implementation rules:

```text
bindingId: non-empty, max 240

driver regex:
^[a-z][a-z0-9_.:-]{0,79}$

target:
at least one property

extensions key regex:
^[a-z0-9.-]+/[a-zA-Z0-9._-]+$
```

For `expiresAt`, use the same contract behavior already established by `ConfirmationChallenge`: accept `null` or valid RFC3339 date-time, preserve the supplied valid string verbatim, reject impossible dates and values without a timezone. Do not add Carbon or another dependency.

Do not validate or re-parse `ActionDefinition.id/version`; constructor type safety and the existing `ActionDefinition` invariants are the canonical source.

- [ ] **Step 4: Run focused and full tests.**

```bash
cd packages/laravel
composer test -- --filter RuntimeBinding
composer test
composer validate --strict
cd ../..
python scripts/validate.py
```

Expected: all green and frozen contract unchanged.

- [ ] **Step 5: Commit Task 1.**

```text
feat(laravel): add generic runtime binding model
```

---

### Task 2: Typed Livewire binding target and factory

**Files:**
- Create: `packages/laravel/src/Livewire/LivewireBindingTarget.php`
- Create: `packages/laravel/src/Livewire/LivewireRuntimeBinding.php`
- Create: `packages/laravel/tests/Unit/LivewireRuntimeBindingTest.php`
- Do not modify: `packages/laravel/composer.json` except if verification proves an accidental need; T-201 design forbids adding Livewire.

**Interfaces:**
- Consumes: `ActionDefinition`, `RuntimeBinding`, `BindingLifecycle`.
- Produces:

```php
namespace SurfaceRelay\Laravel\Livewire;

final readonly class LivewireBindingTarget implements \JsonSerializable
{
    public function __construct(
        public string $componentId,
        public string $method,
    );

    public function toArray(): array;
    public function jsonSerialize(): array;
}
```

```php
final class LivewireRuntimeBinding
{
    public static function forComponent(
        string $bindingId,
        ActionDefinition $definition,
        LivewireBindingTarget $target,
        ?string $expiresAt = null,
        array $extensions = [],
    ): RuntimeBinding;
}
```

Factory invariants:

```text
driver    = livewire
lifecycle = component
```

Neither is caller-configurable.

- [ ] **Step 1: Write the failing Livewire tests.**

Exact reference serialization:

```php
public function test_reference_binding_matches_existing_fixture_shape(): void
{
    $binding = LivewireRuntimeBinding::forComponent(
        bindingId: 'prep-list:component:example-123:add-item',
        definition: $this->definition('prep_list.add_item', 1),
        target: new LivewireBindingTarget('example-123', 'addItem'),
    );

    self::assertSame([
        'bindingId' => 'prep-list:component:example-123:add-item',
        'action' => [
            'id' => 'prep_list.add_item',
            'version' => 1,
        ],
        'driver' => 'livewire',
        'lifecycle' => 'component',
        'target' => [
            'componentId' => 'example-123',
            'method' => 'addItem',
        ],
        'expiresAt' => null,
    ], $binding->toArray());
}
```

Also test:

```text
empty componentId → reject
empty method → reject
componentId/method preserved verbatim
version 1 and version 2 remain exact and independent
driver cannot be changed from livewire
lifecycle cannot be changed from component
expiry/extensions pass through generic validation
LivewireBindingTarget readonly
no ActionDefinition mutation
```

Add a dependency-boundary test using Composer metadata or Reflection/file inspection only if needed to prove no Livewire class is required. Prefer the stronger practical proof: full package suite installs/runs with current composer.json unchanged.

- [ ] **Step 2: Run focused tests and prove RED.**

```bash
cd packages/laravel
composer test -- --filter LivewireRuntimeBinding
```

Expected: FAIL because the Livewire descriptor classes do not exist.

- [ ] **Step 3: Implement the minimum Livewire descriptor.**

`LivewireBindingTarget` validates only non-empty component ID and method. It must NOT:

```text
look up a Livewire component
reflect on method existence
accept component class/name as fallback identity
execute the method
expose every public method
```

`LivewireRuntimeBinding` must be a thin factory around `RuntimeBinding`.

- [ ] **Step 4: Run focused and full verification.**

```bash
cd packages/laravel
composer test -- --filter 'RuntimeBinding|LivewireRuntimeBinding'
composer test
composer validate --strict
cd ../..
python scripts/validate.py
```

Also verify browser starter remains green:

```bash
cd packages/browser-runtime
npm ci
npm run typecheck
npm test
cd ../..
```

- [ ] **Step 5: Commit Task 2.**

```text
feat(livewire): add component runtime binding descriptor
```

---

### Task 3: T-201 checkpoint documentation and final review

**Files:**
- Modify: `TASKS.md`
- Modify: `STATUS.md`
- Modify: `REVIEW_REQUEST.md`
- Modify only if needed for factual accuracy: `docs/design/livewire-runtime-binding.md`

**Interfaces:**
- Consumes: verified Task 1 and Task 2 implementation evidence.
- Produces: repository state declaring T-201 DONE while T-202 remains the next unstarted task.

- [ ] **Step 1: Update task/status records.**

Required final state:

```text
M0 DONE
M1 DONE
M1.1 DONE/reviewed
M2 IN PROGRESS
T-201 DONE
T-202 TODO / next task
```

Record actual test/assertion counts from the final run; do not reuse pre-T-201 counts blindly.

`REVIEW_REQUEST.md` must state that T-202 has not started and summarize:

```text
generic RuntimeBinding added
Livewire target/factory added
no Livewire dependency
no binding generation/storage/lifecycle/execution
no schema change
```

- [ ] **Step 2: Run final repository verification.**

```bash
python scripts/validate.py

cd packages/laravel
composer test
composer validate --strict
cd ../..

cd packages/browser-runtime
npm ci
npm run typecheck
npm test
cd ../..
```

CI expectations remain:

```text
contract
php-tests: PHP 8.3/8.4 × Illuminate 12/13
php-lint
browser
```

- [ ] **Step 3: Self-review the complete T-201 diff.**

Reject the task if any of these appear:

```text
spec/0.1 changes
livewire/livewire dependency
component lookup/execution
binding registry/store
auto-generated binding ID
method reflection
discovery/exposure API
ActionDefinition Livewire fields
browser-runtime execution changes
T-202/T-203/T-304 implementation
version fallback
silent target replacement
```

- [ ] **Step 4: Commit checkpoint docs.**

```text
docs(status): complete T-201 runtime binding descriptor
```

- [ ] **Step 5: Stop for external review.**

Do not begin T-202 until T-201 code and CI evidence are reviewed.

## Plan Self-Review

- Spec coverage: generic contract model, Livewire target/factory, exact identity, lifecycle lock, validation, no-Livewire dependency, serialization, and T-201 non-goals are all assigned to explicit tasks.
- Placeholder scan: no implementation placeholder is used; later roadmap tasks are referenced only as explicit non-goals.
- Type consistency: Task 2 consumes the exact `RuntimeBinding` and `BindingLifecycle` interfaces produced by Task 1.
- Scope: no registry, lifecycle producer, component discovery, execution, or browser driver is included.
