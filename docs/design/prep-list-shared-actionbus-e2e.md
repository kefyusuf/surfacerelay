# Prep List End-to-End Shared ActionBus Design

Status: approved architecture candidate for T-204 implementation planning.

## Goal

Prove the central M2 claim with a real Livewire 4 test harness:

> Human Livewire interaction and an agent path derived from a SurfaceRelay RuntimeBinding reach the same Livewire component method, the same ActionBus, the same execution stage, and the same application business service. Business state mutation exists once.

T-204 is the M2 end-to-end proof. It must not create a second agent-only endpoint or a duplicate application implementation.

## Why a real Livewire harness is required

A plain PHP fixture can prove reflection and method calls, but it cannot honestly prove a Livewire human path.

Livewire 4 provides a real component test API:

```php
Livewire::test(ComponentClass::class)
    ->call('method', ...$params);
```

The test harness mounts the component and executes Livewire's request/update lifecycle. T-204 therefore uses real Livewire as a development/test dependency.

This does NOT make Livewire a runtime dependency of `surfacerelay/laravel` for consumers. The production SurfaceRelay classes added by T-204 remain protocol-neutral and do not type-hint Livewire classes.

## Core execution gap exposed by T-204

The ActionBus already has a canonical `execution` stage slot, but the package currently has no real production execution-stage implementation. Existing ActionBus tests use fake handlers.

An E2E fixture that still uses a fake execution handler would not prove the reference runtime can execute application actions.

T-204 therefore adds the smallest protocol-neutral execution port and stage.

## Production additions

### ActionExecutor

Add:

```text
packages/laravel/src/Contracts/ActionExecutor.php
```

Contract:

```php
interface ActionExecutor
{
    /** @param array<string, mixed> $input */
    public function execute(
        ActionDefinition $definition,
        array $input,
        InvocationContext $context,
    ): mixed;
}
```

Properties:

- protocol-neutral;
- receives the exact already-resolved `ActionDefinition`;
- receives only the pipeline's current validated input;
- receives the trusted `InvocationContext` separately;
- returns the raw application output;
- performs no validation, authorization, confirmation, idempotency, output policy, or audit itself;
- exceptions propagate unchanged.

The package does not invent a second handler registry in T-204. Applications may implement routing inside their `ActionExecutor`; a reusable exact executor registry can be introduced later only with evidence.

### ActionExecutionStage

Add:

```text
packages/laravel/src/Runtime/Pipeline/ActionExecutionStage.php
```

Behavior:

```text
ActionPipelineStage::Execution
        │
        ▼
ActionExecutor::execute(
    exact definition,
    current validated input,
    trusted InvocationContext
)
        │
        ▼
state.withOutput(result)
```

It must:

- implement `ActionPipelineStageHandler`;
- always report `ActionPipelineStage::Execution`;
- delegate exactly once;
- preserve the exact definition/context objects;
- use the current pipeline input (therefore validation transformations are observed);
- mark output present even when the executor returns `null`;
- never catch and normalize arbitrary executor exceptions.

This is the only new production runtime behavior required for T-204.

## Reference Prep List application fixture

Create a real reference fixture under the Laravel package test fixtures, not as public package API:

```text
packages/laravel/tests/Fixtures/PrepList/
├── PrepListStore.php
├── AddPrepListItem.php
├── PrepListActionExecutor.php
├── PrepListActionGateway.php
├── PrepListInvocationContextFactory.php
└── PrepListComponent.php
```

The fixture may be mirrored/documented from `examples/prep-list`, but application-specific types do not belong under package `src/`.

### PrepListStore

In-memory state holder used only by the reference app/test.

It stores ordered items and provides deterministic IDs so equivalent state transitions are easy to compare in isolated tests.

No business validation or SurfaceRelay logic belongs here.

### AddPrepListItem

This is the single business mutation implementation.

Conceptually:

```php
final class AddPrepListItem
{
    public function __construct(private PrepListStore $store) {}

    public function handle(string $name): array
    {
        return $this->store->add($name);
    }
}
```

The component must not mutate `PrepListStore` directly.

The agent simulation must not mutate `PrepListStore` directly.

The executor is the only adapter from ActionBus execution into this application service.

### PrepListActionExecutor

Implements protocol-neutral `ActionExecutor` for the fixture.

For T-204 it supports exactly:

```text
prep_list.add_item@1
```

It verifies the exact identity and delegates to `AddPrepListItem` using validated input:

```text
input.name
```

Unknown identities fail loudly; no latest-version or method-name fallback.

This fixture executor is application code, not a generic package registry.

## PrepListActionGateway

The Livewire component should not manually construct pipeline internals on every method call.

Use an example-specific gateway:

```php
final class PrepListActionGateway
{
    public function addItem(string $name): mixed;
}
```

The gateway:

1. obtains a trusted `InvocationContext` from `PrepListInvocationContextFactory`;
2. constructs exact `ActionCall('prep_list.add_item', 1, ['name' => $name], context)`;
3. dispatches through the shared `ActionBus`;
4. requires a completed outcome for this reference happy-path fixture;
5. returns the execution output.

This is deliberately example-specific. T-204 does not create a generic public `ActionRuntime` facade merely for convenience.

A future public facade should be introduced only when multiple surfaces need the same API shape.

## Trusted browser-session context

The existing Prep List Action Definition requires:

```text
browser_session
```

T-204 must preserve this contract.

`PrepListInvocationContextFactory` creates a trusted `TrustedContextEntry` for `ContextRequirement::BrowserSession` from server/test runtime wiring.

The Livewire action parameter remains only:

```text
name
```

No caller-supplied `browser_session`, `componentId`, binding ID, actor, tenant, or trusted context payload is accepted.

This carries D-007/D-027 into the E2E proof.

## Real Livewire component

`PrepListComponent` extends real `Livewire\Component` in the test fixture.

It declares the explicit T-202 exposure:

```php
#[ExposeAction(id: 'prep_list.add_item', version: 1)]
public function addItem(string $name): void
{
    $this->actions->addItem($name);
}
```

The component receives the fixture gateway through Livewire lifecycle dependency injection:

```php
protected PrepListActionGateway $actions;

public function boot(PrepListActionGateway $actions): void
{
    $this->actions = $actions;
}
```

Livewire 4 supports container dependency injection on lifecycle hooks, and `boot()` runs on both initial and subsequent component requests. This matches Livewire's hydration model and avoids constructor injection or a service-locator call inside the action.

The component does not contain business mutation logic.

## Human path

The human-path test uses real Livewire testing:

```text
Livewire::test(PrepListComponent::class)
    ->call('addItem', 'passport')
```

Expected path:

```text
Livewire human action
        │
        ▼
PrepListComponent::addItem
        │
        ▼
PrepListActionGateway
        │
        ▼
ActionBus
        │
        ▼
real validation
        │
        ▼
real authorization stage
        │
        ▼
explicit test-only future-stage placeholders
        │
        ▼
ActionExecutionStage
        │
        ▼
PrepListActionExecutor
        │
        ▼
AddPrepListItem
        │
        ▼
PrepListStore
```

## Agent binding-derived path

T-204 MUST NOT implement the M3 browser driver.

Instead, the integration test proves the descriptor points to the exact same real Livewire action that a future driver will call:

1. mount a real `PrepListComponent` with `Livewire::test()`;
2. obtain the real component instance with `Testable::instance()`;
3. run T-203 `LivewireBindingProducer::forComponent(instance)`;
4. verify the produced binding targets the real component ID and method `addItem`;
5. simulate the future browser driver's method call using the exact binding target method:

```text
$testable->call($binding->target['method'], 'passport')
```

The test does not add any production binding invoker, browser resolver, `Livewire.find()`, `$wire.$call()`, WebMCP adapter, or transport endpoint.

This isolates the T-204 claim:

> the binding emitted by the server names the same Livewire action used by a normal human interaction, and that action reaches the same ActionBus/application logic.

Actual browser target resolution remains T-304.

## Equivalent-state proof

Human and agent-derived paths run from isolated fresh Prep List stores with the same input.

Both must produce the same final application state, for example:

```text
[
  ['itemId' => 'item-1', 'name' => 'passport']
]
```

The test also tracks `AddPrepListItem` invocation count and proves one business mutation per interaction.

There must be no alternate agent-side application service call.

## Pipeline wiring in the reference fixture

T-204 must use real package behavior where it exists:

- real `InMemoryActionRegistry`;
- real `LaravelInputValidationStage`;
- real generic `AuthorizationStage` with an explicit fixture authorizer;
- real `ActionExecutionStage`;
- real ActionBus canonical ordering.

The following future controls are not implemented yet:

- confirmation receipts (T-401);
- idempotency enforcement (T-402);
- output redaction/policy (T-403);
- structured production audit (T-404).

Therefore the T-204 integration harness may use explicit TEST-ONLY pass-through handlers for `Confirmation`, `Idempotency`, and `OutputPolicy`, plus a test auditor.

These helpers must live under `tests/` and must never become default production handlers in `src/`.

This preserves the existing invariant that security-sensitive stages have no implicit no-op production defaults.

The Prep List Action Definition currently has `idempotency=recommended_key`. T-204 must not claim that recommended-key deduplication is enforced; the E2E test proves shared execution only. T-402 remains the authority for real idempotency semantics.

## Validation proof

The fixture registers explicit Laravel rules for exact `prep_list.add_item@1`, matching the existing contract intent:

```text
name: required|string|min:1
```

At least one integration test should prove invalid input does not mutate `PrepListStore` and never reaches `AddPrepListItem`.

The test does not need to invent Livewire form-validation UX; it only proves the shared ActionBus blocks invalid input before execution.

## Authorization proof

Use an explicit fixture `ActionAuthorizer` that allows the Prep List action.

Do not use an implicit production allow-all authorizer.

At least one low-cost integration assertion should prove authorization is actually traversed (for example a spy/counter on the fixture authorizer), without expanding T-204 into policy semantics already owned by T-109.

## Development dependencies

To make the E2E proof honest, add development-only dependencies to `packages/laravel/composer.json`:

```text
livewire/livewire ^4.4
orchestra/testbench ^10|^11
```

Rationale:

- Livewire 4.4.x currently supports Laravel/Illuminate 12 and 13;
- Testbench 10 targets Laravel 12;
- Testbench 11 targets Laravel 13;
- package consumers do not receive these dependencies because they are `require-dev` only.

The exact compatible constraints must be verified by Composer in CI rather than assumed.

## CI matrix update

Current PHP CI claims support for:

```text
PHP 8.3/8.4 × Illuminate 12/13
```

After adding Testbench, the matrix must explicitly constrain the matching Testbench major so Composer does not accidentally test one framework generation only:

```text
Illuminate/Laravel 12 → Orchestra Testbench 10
Illuminate/Laravel 13 → Orchestra Testbench 11
```

Livewire remains `^4.4` across both.

Every matrix cell must run the full PHPUnit suite including the real Livewire E2E test.

Do not downgrade the existing matrix to a single Laravel/Livewire combination.

## Existing JSON examples

The E2E fixture must align semantically with:

```text
examples/prep-list/action.add-item.json
examples/prep-list/binding.livewire.json
examples/prep-list/invocation.add-item.json
examples/prep-list/result.add-item.json
```

Do not silently change `prep_list.add_item@1` identity or its browser-session requirement merely to simplify the test.

If executable fixture output differs from a static sample because binding IDs/component IDs are runtime-issued, document the distinction rather than hardcoding runtime IDs.

## Proposed decision

Record the next free ACCEPTED decision, expected D-034:

> Livewire human interaction and agent binding execution converge at the same explicitly exposed Livewire component method and shared ActionBus/application action. SurfaceRelay does not create an agent-only application endpoint or duplicate business mutation path; the browser driver only selects and invokes the exact existing binding target.

This decision is architectural, not merely test-specific.

## Security invariants

1. Human and agent-derived paths share the same component action and ActionBus.
2. No agent-only controller/API/business handler is introduced.
3. Business mutation exists only in `AddPrepListItem`.
4. Agent simulation takes method identity from T-203 RuntimeBinding; it does not guess a method.
5. Binding ID remains a reference, not invocation authorization.
6. Browser-session authority is created by trusted runtime wiring, not input.
7. Validation occurs before execution.
8. Authorization stage is traversed before execution.
9. Execution sees validated pipeline input, not raw caller input.
10. Unknown action identity in the fixture executor fails loudly.
11. Real Livewire is a dev/test dependency, not leaked into protocol-neutral production types.
12. No production no-op confirmation/idempotency/output-policy stages are added.
13. No browser target fallback, similar-component lookup, or silent retarget is introduced.
14. `spec/0.1` remains frozen unless a concrete contradiction is discovered.

## Non-goals

T-204 must not implement:

- browser `Livewire.find()`;
- `$wire.$call()` production driver;
- WebMCP tool registration;
- DriverRegistry;
- browser component cleanup/stale resolution;
- D-026 final binding-error codes;
- confirmation receipts;
- idempotency persistence/deduplication;
- output redaction policy;
- production audit storage;
- tenant/current-record/current-selection integration;
- Filament behavior;
- a generic action-handler registry without evidence;
- an agent-only REST/MCP endpoint.

These remain later milestones.

## Acceptance tests

Minimum coverage:

```text
✓ ActionExecutionStage delegates exactly once to ActionExecutor
✓ execution receives exact definition + validated input + trusted context
✓ null executor output still marks execution complete
✓ executor exceptions propagate
✓ real Livewire::test human call mutates Prep List through ActionBus
✓ T-203 producer on the real mounted component emits prep_list.add_item@1 binding
✓ binding target componentId equals the real Livewire component ID
✓ binding target method is addItem
✓ agent-derived simulation invokes exactly the binding target method
✓ human and agent-derived paths from fresh state produce equivalent final state
✓ AddPrepListItem is the only business mutation and is invoked once per path
✓ invalid input does not mutate state or call AddPrepListItem
✓ authorization stage is traversed
✓ browser_session context is trusted runtime context, never action input
✓ component action uses boot lifecycle DI rather than constructor/service-locator wiring
✓ no livewire/livewire production dependency (require-dev only)
✓ PHP/Laravel/Testbench/Livewire CI matrix is green
✓ spec/0.1 unchanged
✓ no M3/T-304 production browser execution mixed in
```

## Completion boundary

T-204 is DONE when:

- the protocol-neutral execution stage exists and is tested;
- real Livewire/Testbench integration runs in the supported CI matrix;
- the Prep List human and binding-derived agent simulations prove equivalent shared state transition;
- D-034 is accepted;
- source-of-truth docs are updated;
- external-style review passes.

After T-204 review, M2 is complete.

Then stop before M3/T-301 until the M2 checkpoint is reviewed and recorded.
