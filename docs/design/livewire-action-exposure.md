# Explicit Livewire Action Exposure Design

Status: approved design for T-202 implementation planning.

## Goal

Define a fail-closed, explicit declaration mechanism by which a component can nominate specific registered SurfaceRelay Action Definitions as Livewire-exposed candidates.

T-202 answers only:

> Which exact registered actions does this concrete component explicitly expose, and through which public instance methods?

It does **not** issue Runtime Bindings, discover mounted component IDs, authorize discovery or invocation, execute methods, inspect browser state, or infer actions from arbitrary public methods.

## Security premise

A public Livewire method and a SurfaceRelay-exposed method are different concepts.

```text
public Livewire method
        !=
SurfaceRelay exposure
        !=
discovery authorization
        !=
invocation authorization
```

SurfaceRelay must never scan a component and expose all public methods. Exposure is an explicit allow-list declaration.

This distinction is required because framework-level public method callability is broader than the SurfaceRelay action contract. A method being callable by Livewire does not make it safe, semantically described, discoverable, or authorized as an agent action.

## Existing architecture constraints

T-202 builds on the reviewed T-201 model:

```text
ActionDefinition
      │
      ▼
ActionRegistry
      │ exact id + version
      ▼
LivewireActionExposure
├── ActionDefinition
└── method
      │
      ▼
T-203 binding producer
      │ + mounted componentId + bindingId
      ▼
LivewireRuntimeBinding
```

The following existing rules remain normative:

- Action Definition contains semantic meaning only and never Livewire method/component identity.
- Action identity is exact `id + version`; there is no latest-version fallback.
- Runtime Binding identity/issuance belongs to T-203, not T-202.
- Exposure is not authorization (D-011).
- Caller input cannot create trusted authority.
- Unknown/misconfigured references fail loudly rather than becoming implicit allow.

## Chosen API

Use a method-level Livewire-specific attribute that references an already registered Action Definition by exact identity.

```php
use SurfaceRelay\Laravel\Livewire\Attributes\ExposeAction;

final class PrepList
{
    #[ExposeAction(
        id: 'prep_list.add_item',
        version: 1,
    )]
    public function addItem(string $name): void
    {
        // Existing application path.
    }

    public function internalHelper(): void
    {
        // Public does not mean SurfaceRelay-exposed.
    }
}
```

The attribute is intentionally small:

```php
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class ExposeAction
{
    public function __construct(
        public string $id,
        public int $version,
    ) {}
}
```

It does not duplicate title, description, schema, effect, risk, idempotency, output classification, or context requirements. Those remain owned by the canonical Action Definition.

## Why method-level rather than class-level mapping

Three approaches were considered.

### Method-level attribute — selected

```php
#[ExposeAction(id: 'orders.refund', version: 1)]
public function refund(): void {}
```

Advantages:

- explicit allow-list at the actual callable boundary;
- method name is obtained from reflection rather than duplicated as a string;
- an unannotated public method is unambiguously excluded;
- the declaration is local and auditable;
- later T-203 can combine the resolved exposure with a mounted component instance without changing Action Definition.

### Class-level repeatable mapping — rejected

```php
#[ExposeAction(id: 'orders.refund', version: 1, method: 'refund')]
final class Orders {}
```

This duplicates method names as strings and creates a larger stale-configuration surface.

### Public provider method / array — rejected

```php
public function surfaceRelayActions(): array {}
```

This makes exposure itself another public component method and mixes configuration with runtime component behavior. It is also easier to accidentally broaden into dynamic, state-dependent discovery logic before the project has a discovery-authorization model.

## Package structure

```text
packages/laravel/src/Livewire/
├── Attributes/
│   └── ExposeAction.php
└── Exposure/
    ├── LivewireActionExposure.php
    ├── LivewireActionExposureReader.php
    └── InvalidLivewireActionExposure.php
```

Tests:

```text
packages/laravel/tests/Unit/
└── LivewireActionExposureReaderTest.php
```

No `livewire/livewire` Composer dependency is introduced by T-202.

## ExposeAction semantics

`ExposeAction` is a reference, not a second Action Definition.

Properties:

```text
id      exact Action Definition ID reference
version exact Action Definition version reference
```

The attribute constructor does not duplicate the canonical Action ID grammar. Instead, the reader resolves exact `id + version` against `ActionRegistry`. Registered Action Definitions have already passed canonical validation.

An invalid or unavailable reference therefore fails during exposure resolution rather than creating a parallel identity validator.

The attribute is not repeatable. One concrete method maps to at most one exact Action Definition identity in T-202.

## LivewireActionExposure

Resolved exposure is an immutable value object:

```php
final readonly class LivewireActionExposure
{
    public function __construct(
        public ActionDefinition $definition,
        public string $method,
    ) {}
}
```

It contains:

- the exact registered `ActionDefinition` object;
- the concrete method name.

It does not contain:

- component ID;
- binding ID;
- lifecycle;
- driver;
- browser/session state;
- authorization result;
- discovery visibility decision.

These omissions are architectural boundaries, not missing implementation.

## LivewireActionExposureReader

Interface shape:

```php
final class LivewireActionExposureReader
{
    public function __construct(
        private ActionRegistry $registry,
    ) {}

    /** @return list<LivewireActionExposure> */
    public function forComponent(object $component): array;
}
```

The reader uses reflection only to inspect explicit SurfaceRelay exposure attributes. It never invokes component methods.

### Resolution algorithm

```text
ReflectionObject(component)
        │
        ▼
inspect methods
        │
        ├── no ExposeAction
        │      → ignore
        │
        └── ExposeAction present
               │
               ├── inherited-only declaration
               │      → ignore
               ├── not public
               │      → configuration failure
               ├── static
               │      → configuration failure
               ├── duplicate attribute on one method
               │      → configuration failure
               │
               ▼
        registry.get(id, version)
               │
               ├── exact identity missing
               │      → configuration failure
               ▼
        LivewireActionExposure
               │
               ▼
        duplicate action identity check
               │
               ▼
        deterministic sort
```

## Concrete-class declaration rule

T-202 is fail-closed around inheritance.

An `ExposeAction` annotation declared only on a parent class method does **not** automatically expose that action on every child component.

The reader only accepts an annotation when the effective reflected method is declared by the concrete component class being inspected.

Rationale:

- exposure should be an explicit choice of the concrete component;
- a base component/library update must not silently expose an action in all descendants;
- inheritance should not become an authority propagation mechanism.

If a child intentionally wants to expose inherited behavior, it can override the public method and annotate the override explicitly.

This rule concerns SurfaceRelay exposure only; it does not attempt to redefine PHP or Livewire method inheritance.

## Visibility and method-shape rules

An annotated method must be:

- declared by the concrete inspected class;
- public;
- non-static.

Annotated protected/private methods are configuration errors rather than silently ignored annotations.

Annotated static methods are configuration errors.

T-202 does not invoke the method and does not infer or compile its parameter schema.

T-202 also does not compare the method signature with `ActionDefinition.inputSchema`. End-to-end callable/input compatibility belongs to the later execution integration, where the actual invocation path exists.

## Exact registry resolution

Every accepted annotation must resolve through:

```php
$registry->get($id, $version)
```

There is no:

- latest-version lookup;
- same-ID fallback;
- fuzzy matching;
- method-name-derived action ID;
- implicit registration.

If the reference does not resolve, `LivewireActionExposureReader` throws an adapter-specific configuration exception with method and requested action identity context.

It should not convert the problem into an empty exposure list.

## Duplicate rules

### Duplicate attribute on one method

More than one `ExposeAction` declaration on the same method is invalid even if PHP attribute repeatability would otherwise be detected later by reflection instantiation.

The reader detects the count and throws a focused configuration exception.

### Same action identity on multiple methods

This is invalid within one concrete component:

```php
#[ExposeAction(id: 'orders.refund', version: 1)]
public function refund(): void {}

#[ExposeAction(id: 'orders.refund', version: 1)]
public function refundAgain(): void {}
```

Reason: T-203 must not have two ambiguous method targets for one exact action identity on the same mounted component.

### Different versions

Different exact identities remain distinct:

```text
orders.refund + version 1
orders.refund + version 2
```

They may be exposed independently if declared on different methods.

No version fallback exists between them.

## Deterministic ordering

Reflection order is not a public contract.

`forComponent()` returns exposures sorted by:

1. `ActionDefinition.id` ascending;
2. `ActionDefinition.version` ascending;
3. method name ascending.

This gives T-203 deterministic input when binding issuance is introduced.

## Error model

Create an adapter-specific configuration exception:

```text
InvalidLivewireActionExposure
```

Expected focused constructors/factories may include:

```text
actionNotRegistered(method, id, version)
methodNotPublic(method)
methodStatic(method)
duplicateAttribute(method)
duplicateActionIdentity(id, version, firstMethod, secondMethod)
```

Exact method names may be adjusted during implementation if the resulting API stays small and explicit.

These are installation/component configuration failures. They are **not** normalized `ActionResult` failures and are not caller-facing authorization denials.

## Relationship to authorization

T-202 does not answer whether an action should be visible to a particular actor or whether a specific invocation may execute.

```text
Exposure declaration
        │
        ▼
candidate action
        │
        ├── future discovery authorization
        │
        └── invocation pipeline authorization
```

This preserves D-011:

> Discovery permission and invocation permission are separate decisions.

An exposure declaration is weaker than both.

## Relationship to T-201 RuntimeBinding

T-202 deliberately produces no Runtime Binding.

A future T-203 producer will combine:

```text
LivewireActionExposure
+ trusted mounted componentId
+ newly issued bindingId
+ optional expiry/extensions policy
        │
        ▼
LivewireRuntimeBinding::forComponent(...)
```

The exact Action Definition object from the exposure is reused. There is no second action lookup/fallback step required to determine semantics.

## No Livewire dependency in T-202

T-202 accepts `object` and uses PHP reflection only.

It does not require:

```text
livewire/livewire
```

This is intentional:

- the exposure declaration boundary can be tested without booting a framework;
- the existing PHP/Illuminate compatibility matrix remains focused;
- actual mounted-component identity and lifecycle APIs belong to T-203.

Real Livewire integration may add the dependency when the first task genuinely needs framework types/APIs.

## Non-goals

T-202 must not implement:

- public-method auto-exposure;
- component ID resolution;
- Runtime Binding issuance;
- binding ID generation;
- binding registry/storage;
- lifecycle/stale tracking;
- browser execution;
- `$wire.$call()` or `Livewire.find()` integration;
- ActionBus execution;
- authorization/discovery policy;
- current-record/current-selection resolution;
- automatic Action Definition creation from method signatures;
- input-schema/method-signature compatibility checking;
- Livewire Composer dependency.

## Acceptance tests

Minimum behavioral coverage:

```text
✓ annotated public instance method resolves exact registered definition
✓ unannotated public method is ignored
✓ empty exposure list is valid
✓ exact action version retained
✓ missing exact version fails loudly with no fallback
✓ two versions of same action remain distinct
✓ duplicate same action identity on two methods fails loudly
✓ duplicate ExposeAction declarations on one method fail loudly
✓ annotated protected method fails loudly
✓ annotated private method fails loudly
✓ annotated static method fails loudly
✓ parent-only annotation does not auto-expose on child component
✓ override + explicit annotation can expose intentionally
✓ returned exposures are deterministic by id/version/method
✓ registry ActionDefinition object is reused, not copied/mutated
✓ reader never invokes component methods
✓ no livewire/livewire dependency introduced
```

## Verification

T-202 completion requires:

```bash
cd packages/laravel
composer test -- --filter "LivewireActionExposure|ExposeAction"
composer test
composer validate --strict
cd ../..
python scripts/validate.py

cd packages/browser-runtime
npm ci
npm run typecheck
npm test
cd ../..
```

GitHub Actions must pass:

```text
contract
php-tests: PHP 8.3/8.4 × Illuminate 12/13
php-lint
browser
```

## Completion boundary

T-202 is DONE when the explicit exposure declaration and exact registry reader are implemented, tested, reviewed, and source-of-truth documents are updated.

Then stop.

The next task is T-203 — mounted Livewire binding lifecycle producer. T-203 must not begin automatically without review of T-202.