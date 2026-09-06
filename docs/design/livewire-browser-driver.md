# Livewire Browser Driver Design

Status: approved architecture candidate for T-304 implementation planning.

## Goal

T-304 implements the first real framework-specific browser execution adapter in SurfaceRelay.

It must execute one exact server-issued Livewire RuntimeBinding against the exact currently mounted Livewire browser component without guessing a replacement target, while preserving the protocol-neutral Action input/output contract as far as the documented Livewire browser API allows.

T-304 builds on:

```text
T-203 server-issued Livewire RuntimeBinding
        +
T-301 exact DriverRegistry
        +
T-303 WebMCP execution callback
        ↓
Livewire browser driver
        ↓
exact Livewire.find(componentId)
        ↓
documented $wire.$call(method, ...params)
```

The task also closes one prerequisite exposed by browser execution: SurfaceRelay Action input is object-shaped while Livewire's documented `$wire.$call()` API is positional. The server-issued Livewire binding must therefore carry a deterministic driver-owned argument plan.

## Current reviewed baseline

T-303 is DONE / REVIEWED and merged to `main`.

Baseline:

```text
main:     7ab8c2f5ef538affa4ac8f2f6224412412ca5765
browser:  TypeScript typecheck + 49/49 Vitest tests
PHP:      266 tests / 783 assertions
contract: 52 fixture manifest entries + 12 conformance scenarios
CI:       all 7 jobs green
```

`spec/0.1` remains frozen. RuntimeBinding `target` is explicitly driver-owned, so T-304 may extend the Livewire target shape without modifying the protocol-neutral RuntimeBinding schema.

## Critical Livewire browser findings

### Exact component lookup is available

Livewire 4 exposes:

```js
Livewire.find(id)
```

which returns the exact component `$wire` object or `undefined` when that component ID is not present.

This is the correct component-lifecycle lookup authority for T-304.

Do not substitute:

```text
Livewire.first()
Livewire.getByName(...)
DOM position
component class/name
nearest component
same record
same method
```

for an absent exact component ID.

### Documented method call API is positional

Livewire exposes:

```js
$wire.$call(method, ...params)
```

SurfaceRelay browser drivers receive:

```ts
input: Record<string, unknown>
```

Therefore this is WRONG:

```ts
wire.$call('addItem', input)
```

for a PHP method:

```php
public function addItem(string $name)
```

because it passes the whole object as one positional argument.

Correct browser execution requires a server-issued input-key-to-position plan.

### `$wire` is a Proxy with reserved names

Livewire resolves `$wire.$call(method, ...)` through its `$wire` Proxy. Names such as `set`, `get`, `call`, `dispatch`, `watch`, `upload`, and other `$wire` aliases/properties can resolve to framework behavior instead of the intended PHP server method.

The Proxy also resolves public component state properties before its server-method fallback, so a server method name colliding with a public Livewire state property can be shadowed.

SurfaceRelay must reject these collisions rather than bypassing the documented API with private Livewire internals.

### Livewire server dispatch supports more than T-304 will expose

Livewire's PHP runtime can perform implicit binding, dependency injection, variadics, and named dependency resolution.

T-304 deliberately supports a smaller deterministic subset because the public browser API provides positional invocation and SurfaceRelay must not infer hidden server invocation semantics.

## Chosen architecture

T-304 has two cooperating halves:

```text
SERVER / Laravel

ActionDefinition
        +
explicit exposed ReflectionMethod
        ↓
LivewireMethodCallPlanBuilder
        ↓
LivewireBindingTarget
├── componentId
├── method
├── inputOrder
└── requiredCount
        ↓
LivewireBindingProducer
        ↓
RuntimeBinding(driver=livewire, lifecycle=component)


BROWSER

RuntimeBinding
        ↓
LivewireBrowserDriver
        ├── validate exact Livewire target shape
        ├── validate expiry if present
        ├── map object input → positional params
        ├── LivewireBrowserRuntime.find(exact componentId)
        ├── verify exact $wire.$id
        └── $wire.$call(exact method, ...params)
```

The protocol-neutral ActionDefinition remains unchanged.

## Livewire target shape

Extend the driver-owned Livewire target from:

```json
{
  "componentId": "example-123",
  "method": "addItem"
}
```

to:

```json
{
  "componentId": "example-123",
  "method": "addItem",
  "inputOrder": ["name"],
  "requiredCount": 1
}
```

For:

```php
public function update(string $name, ?string $note = null)
```

the target is:

```json
{
  "componentId": "example-123",
  "method": "update",
  "inputOrder": ["name", "note"],
  "requiredCount": 1
}
```

### Target invariants

`inputOrder`:

- is a list of unique non-empty strings;
- comes from PHP Reflection parameter order, never JSON object/property order;
- contains every caller-visible method parameter exactly once;
- contains no dependency-injection-only parameter;
- is immutable descriptor data once issued.

`requiredCount`:

- is an integer in `0..count(inputOrder)`;
- means the first `requiredCount` parameters are required;
- all parameters after that index must have PHP defaults / be optional;
- no required parameter may appear after the optional suffix.

This compact plan is sufficient only because T-304 rejects signatures that do not fit the required-prefix/optional-suffix model.

## Server-side method-call plan builder

Add a Livewire-specific builder, conceptually:

```php
final class LivewireMethodCallPlanBuilder
{
    public function forExposure(
        object $component,
        LivewireActionExposure $exposure,
    ): LivewireMethodCallPlan;
}
```

`LivewireMethodCallPlan` may be an internal value object or folded into `LivewireBindingTarget` construction if implementation remains clearer. The important requirement is that reflection/signature compatibility is independently testable and is evaluated before issuing an executable browser binding.

### Signature source of truth

The builder uses:

```text
exact T-202 exposed method
        +
exact ActionDefinition.inputSchema
```

It does not discover another method and does not use method/class naming conventions.

### Supported signature subset

T-304 supports ordinary public scalar/object-compatible method parameters with deterministic positional order, for example:

```php
public function addItem(string $name)

public function update(
    string $name,
    ?string $note = null,
)
```

SurfaceRelay server validation remains the semantic/type authority after invocation. T-304 does NOT attempt to duplicate JSON Schema or Laravel validation in the browser.

### Explicitly unsupported signatures

Fail binding production for exposed methods containing:

```php
public function action(...$values)       // variadic
public function action(&$value)          // by-reference
public function action(Service $service, string $name) // method-level DI
```

The reference pattern for services remains lifecycle/component DI, for example:

```php
public function boot(PrepListActionGateway $actions): void
```

while the exposed action method itself stays caller-input-only.

### Action input schema compatibility

T-304 needs a deterministic top-level input mapping.

The ActionDefinition input schema must expose a top-level `properties` object whose property names can be compared with the exposed PHP method parameter names.

Binding production requires exact property-set equality:

```text
set(ActionDefinition.inputSchema.properties.keys)
    ==
set(exposed PHP caller parameter names)
```

This prevents both:

```text
Action input property silently dropped
```

and:

```text
PHP argument invented outside the Action contract
```

Property ORDER in JSON Schema is never invocation authority.

The schema's `required` property must describe exactly the PHP parameters that do not have defaults, and those required parameters must form the positional prefix.

Examples:

```php
public function update(string $name, ?string $note = null)
```

requires schema semantics equivalent to:

```json
{
  "type": "object",
  "properties": {
    "name": {},
    "note": {}
  },
  "required": ["name"]
}
```

If the Action schema uses a shape that cannot be reduced safely to this top-level property model (`oneOf`-only alternatives, dynamic keys with no explicit property mapping, etc.), T-304 fails binding production rather than guessing.

T-304 does not require `additionalProperties: false` as a schema authoring rule, but the browser driver rejects runtime input keys not present in `inputOrder`. Unknown keys are never silently discarded.

## Output compatibility

The browser driver returns the raw resolved value from:

```ts
await wire.$call(method, ...params)
```

It does not synthesize a new output or scrape component state.

For actions with a non-null/non-absent `outputSchema`, the exposed method must be capable of returning a semantic action result.

T-304 therefore fails binding production when the ActionDefinition declares an output schema and the exposed PHP method explicitly declares an impossible return type:

```php
: void
: never
```

T-304 does NOT attempt full PHP return-type ↔ JSON Schema conformance inference. An untyped or non-void return is allowed; actual ActionBus/output-policy behavior remains authoritative.

The Prep List reference fixture must be corrected from:

```php
public function addItem(string $name): void
{
    $this->actions->addItem($name);
}
```

to a method that returns the exact gateway result, for example:

```php
public function addItem(string $name): array
{
    return $this->actions->addItem($name);
}
```

Human UI behavior remains unchanged, while an agent invocation can now receive the semantic Action output rather than `null`.

## Reserved Livewire method names

The reference driver uses documented `$wire.$call()` and therefore must reject server method names that collide with Livewire's public `$wire` namespace.

The compatibility set is pinned to the current Livewire 4.4 public/proxy surface and includes at least the unprefixed aliases corresponding to:

```text
on
el
id
js
get
set
refs
call
hook
watch
dirty
effect
commit
errors
island
upload
entangle
dispatch
intercept
interceptAction
interceptMessage
interceptRequest
dispatchTo
dispatchSelf
dispatchEl
dispatchRef
removeUpload
cancelUpload
uploadMultiple
```

Also reject Proxy-reserved/special names that do not reach normal server fallback safely, including:

```text
then
toJSON
__instance
```

The exact set should live in one Livewire compatibility module/constant and be covered by tests.

Do not try to escape or rename a colliding method.

### Public state-property collision

Because the `$wire` Proxy checks component state before server-method fallback, binding production must also reject an explicitly exposed method whose name collides with a public property defined on the concrete Livewire component.

This is checked server-side with reflection at issuance time.

The browser driver still checks the static reserved-name set defensively before `$call()`.

## Browser compatibility port

Do not spread ambient `window.Livewire` access across the driver.

Introduce a narrow port:

```ts
export interface LivewireWire {
  readonly $id: string;

  $call(
    method: string,
    ...params: unknown[]
  ): Promise<unknown>;
}

export interface LivewireBrowserRuntime {
  find(componentId: string): LivewireWire | undefined;
}
```

The actual browser adapter wraps the current global Livewire API.

This isolates framework drift and allows unit tests without loading Livewire itself in Vitest.

T-304 does not depend on undocumented `fireAction()` or internal component request modules.

## LivewireBrowserDriver

Reference shape:

```ts
export class LivewireBrowserDriver implements BindingDriver {
  constructor(
    private readonly livewire: LivewireBrowserRuntime,
    private readonly clock: BrowserClock = systemBrowserClock,
  ) {}

  async execute(
    binding: RuntimeBinding,
    input: Record<string, unknown>,
    context: DriverExecutionContext,
  ): Promise<unknown>;
}
```

A small injectable clock is justified because RuntimeBinding expiry is already an ACCEPTED cumulative validity rule under D-024 and must be deterministic in tests.

## Execution algorithm

### 1. Validate binding driver/lifecycle

Require:

```text
binding.driver == "livewire"
binding.lifecycle == "component"
```

A direct call to the driver with another driver/lifecycle fails closed.

Normal T-301 dispatch already selects by exact driver name, but the driver validates its own descriptor defensively.

### 2. Validate Livewire target shape

Require exact usable fields:

```text
componentId: non-empty string
method: non-empty string
inputOrder: unique string[]
requiredCount: integer 0..inputOrder.length
```

Unexpected/invalid values fail before any `Livewire.find()` or `$call()`.

The browser does not reinterpret another target shape as Livewire.

### 3. Enforce expiry

If `expiresAt` is non-null/present:

- invalid/unparseable date-time fails closed as invalid binding data;
- if `expiresAt <= now`, return `binding_expired`;
- no component call occurs.

T-203 currently emits `expiresAt = null`, but lower-level RuntimeBinding permits explicit expiry and D-024 says lifecycle and expiry are cumulative.

T-304 does not add a revocation store; revocation remains unenforceable in the browser until such a source exists.

### 4. Reject reserved method names

If `target.method` collides with the pinned Livewire `$wire` reserved set, fail before component lookup/call.

No private-API fallback is used.

### 5. Map object input to positional params

For:

```ts
inputOrder = ['name', 'note']
requiredCount = 1
```

and:

```ts
input = {
  name: 'passport',
  note: 'carry-on',
}
```

produce:

```ts
['passport', 'carry-on']
```

Rules:

1. every required-prefix key must be an own property of input;
2. input must contain no key outside `inputOrder`;
3. trailing optional keys may be omitted;
4. a missing optional positional key before a later-present key is a hole and fails closed;
5. property enumeration order of the caller object is irrelevant;
6. values are passed unchanged; T-304 does not duplicate semantic validation.

Examples:

```ts
inputOrder = ['a', 'b', 'c']
requiredCount = 1
input = { a: 1, c: 3 }
```

fails because omitting `b` while sending positional `c` would change argument meaning.

### 6. Resolve exact component

Call only:

```ts
const wire = livewire.find(componentId)
```

If it returns `undefined`:

```text
binding_stale
```

No alternative lookup is attempted.

### 7. Verify exact identity

Require:

```text
wire.$id === componentId
```

If an adapter/runtime returns a mismatching `$wire`, fail closed as stale/invalid resolution. Do not accept the object merely because `find()` returned something.

### 8. Invoke exact method

Call:

```ts
return await wire.$call(method, ...params)
```

exactly once.

Do not call:

```text
wire[method](...)
private fireAction(...)
DOM click dispatch
method-name fallback
```

### 9. Preserve server/browser errors

Once exact `$call()` begins, Livewire/server errors propagate unchanged.

The driver must NOT relabel an arbitrary validation, authorization, network, or application rejection as `binding_stale`.

Only conditions the driver can prove locally receive SurfaceRelay binding-driver error codes.

## Error model

Introduce a typed browser-driver error:

```ts
export type LivewireBindingExecutionErrorCode =
  | 'binding_stale'
  | 'binding_expired'
  | 'binding_target_invalid'
  | 'binding_input_unmappable'
  | 'livewire_runtime_unavailable'
  | 'livewire_method_unsupported';
```

```ts
export class LivewireBindingExecutionError extends Error {
  readonly code: LivewireBindingExecutionErrorCode;
}
```

Expected ownership:

### `binding_stale`

Exact component ID does not resolve, or exact runtime identity cannot be confirmed.

### `binding_expired`

Explicit binding expiry has passed.

### `binding_target_invalid`

Malformed/mismatched Livewire descriptor (`driver`, lifecycle, target structure, invalid expiry representation).

### `binding_input_unmappable`

Required key missing, unknown key present, or positional optional hole exists.

### `livewire_runtime_unavailable`

The ambient Livewire adapter cannot access a compatible `Livewire.find()` runtime.

### `livewire_method_unsupported`

The issued method collides with the documented/pinned `$wire` namespace and cannot safely be invoked through `$wire.$call()`.

D-026 remains PROPOSED as a complete generic binding-error taxonomy. T-304 may operationalize `binding_stale` and `binding_expired` without claiming every provisional code is finalized.

## Registration lifecycle interaction

T-303 registration leases already allow a caller to remove a current tool generation with `AbortController` cleanup.

T-304 execution safety does not depend on proactive cleanup being perfectly timed:

```text
stale tool remains visible temporarily
        ↓
agent invokes
        ↓
Livewire.find(exact old componentId) === undefined
        ↓
binding_stale
        ↓
NO fallback execution
```

This is the fail-closed safety invariant required by T8.

Livewire also exposes client component lifecycle cleanup (`component.init` cleanup callback). A higher orchestration layer can use that signal to dispose/recompute T-303 registration leases proactively.

T-304 does NOT introduce an automatic multi-component registration reconciler because T-303 currently owns snapshot leases and there is not yet a browser binding-source/reconciliation subsystem. Adding one inside the driver would mix target execution with discovery/registration ownership.

Therefore:

- T-304 owns exact stale-target resolution at execution;
- T-303 lease disposal remains the registration cleanup primitive;
- proactive Livewire lifecycle → snapshot recomputation remains an integration concern and must not be falsely claimed as completed by T-304.

D-033 remains valid: server `destroy` is not browser lifecycle authority; exact browser component existence is.

## Cancellation boundary

T-303 already forwards the WebMCP per-execution `AbortSignal` into `DriverExecutionContext`.

The documented Livewire `$wire.$call()` surface does not accept an AbortSignal argument.

T-304 therefore does not claim cancellation propagation or rollback.

The driver may observe that `context.signal` exists, but it must not pass it as an application method parameter or pretend the Livewire request was cancelled.

T-305 owns:

- pre-aborted execution behavior;
- request interception possibilities;
- actual transport cancellation support;
- race behavior after server work begins;
- non-rollback semantics.

## Reference Prep List vertical

T-304 extends the existing executable reference proof.

Server binding target for `prep_list.add_item@1` becomes conceptually:

```json
{
  "componentId": "runtime-issued-id",
  "method": "addItem",
  "inputOrder": ["name"],
  "requiredCount": 1
}
```

The browser driver receives:

```json
{
  "name": "passport"
}
```

and calls:

```text
Livewire.find(exact componentId)
        ↓
$wire.$call('addItem', 'passport')
        ↓
PrepListComponent::addItem
        ↓
PrepListActionGateway
        ↓
ActionBus
        ↓
AddPrepListItem
```

The component method returns the gateway's semantic result so the WebMCP invocation can receive the Action output.

T-304 browser tests may use a structural fake Livewire runtime for deterministic driver behavior. The Laravel integration suite continues proving the real component/exposure/binding side. If a practical real-browser Livewire JS harness can be added without introducing a browser-test platform into the repository, it may be considered separately; it is not required merely to unit-test the adapter port.

## Proposed production files

### Laravel

Expected additions/modifications:

```text
packages/laravel/src/Livewire/
├── LivewireBindingTarget.php                 # extend call-plan fields
└── Binding/
    ├── LivewireBindingProducer.php           # request call plan per exposure
    ├── LivewireMethodCallPlan.php            # optional focused value object
    ├── LivewireMethodCallPlanBuilder.php     # reflection/schema compatibility
    └── InvalidLivewireBindingProduction.php  # new fail-loud cases
```

Exact filenames may be collapsed if the plan/value object would be trivial, but signature compatibility must not become an unreadable block inside the producer.

### Browser runtime

Expected additions:

```text
packages/browser-runtime/src/livewire/
├── livewire-browser-runtime.ts
├── livewire-binding-target.ts
├── livewire-browser-driver.ts
└── livewire-binding-execution-error.ts
```

A separate reserved-name compatibility constant/module is appropriate if it keeps `livewire-browser-driver.ts` focused.

## Proposed decisions

### D-039 — Exact Livewire browser resolution

> Livewire browser execution resolves only the exact RuntimeBinding `componentId` through the documented `Livewire.find()` API and invokes only the exact binding method. A missing or mismatched exact component identity is stale; no component-name, DOM-position, first-component, record, method-search, or replacement-target fallback is permitted.

### D-040 — Server-issued Livewire argument plan

> SurfaceRelay Action input remains object-shaped, while Livewire browser calls are positional. Livewire RuntimeBindings therefore carry a server-issued driver-owned positional argument plan derived from the exact exposed method signature and exact Action input properties. JSON object/property order is never invocation authority; unsupported, ambiguous, unknown-key, missing-required, or positional-hole mappings fail closed.

### D-041 — Documented Livewire browser API boundary

> The reference Livewire browser driver uses documented `Livewire.find()` and `$wire.$call()` APIs. It does not bypass `$wire` collisions through undocumented Livewire request internals. Known `$wire` namespace and component-state collisions are rejected fail-closed.

These decisions become ACCEPTED only after implementation/tests/review.

## Security and correctness invariants

1. Caller input never supplies `componentId`, method, inputOrder, requiredCount, bindingId, or driver authority.
2. Exact server-issued target data remains separate from Action input.
3. ActionDefinition input property order is never used as PHP invocation order.
4. Action input properties and exposed PHP caller parameter names must match exactly before binding issuance.
5. Unsupported PHP signatures do not receive executable browser bindings.
6. Public component state and `$wire` API name collisions fail closed.
7. The driver never guesses a component replacement.
8. Missing exact component identity is stale.
9. Explicit expiry is checked before invocation.
10. Unknown runtime input keys are rejected rather than silently discarded.
11. Missing required parameters fail before Livewire invocation.
12. Positional holes fail before Livewire invocation.
13. `$wire.$call()` is invoked exactly once for a valid execution.
14. Server/Livewire errors after invocation are preserved rather than misdiagnosed.
15. No private Livewire JS request API becomes a SurfaceRelay dependency.
16. A non-null Action output contract cannot knowingly bind to an explicitly `void`/`never` exposed method.
17. Browser cancellation is not misrepresented as rollback or supported transport cancellation.
18. T-304 does not confer authorization; ActionBus/server policy remains invocation authority.
19. `spec/0.1` stays unchanged because Livewire target shape is driver-owned.
20. No T-304 code creates an agent-only server endpoint or duplicate business mutation path.

## Non-goals

T-304 must not implement:

- generic browser binding registry/persistence;
- server-side binding revocation store;
- automatic multi-component WebMCP reconciliation;
- component-name/DOM/record fallback lookup;
- Livewire private `fireAction()` integration;
- arbitrary Livewire method-level dependency injection;
- variadic/by-reference exposed method mapping;
- full PHP type ↔ JSON Schema type inference;
- Action semantic validation in JavaScript;
- authorization, confirmation, idempotency, output redaction, or audit controls;
- transport cancellation / rollback claims (T-305);
- Filament/HTMX browser drivers;
- D-026 complete generic error-taxonomy finalization.

## Acceptance tests

### Laravel call-plan production

```text
✓ one required parameter → inputOrder + requiredCount
✓ required prefix + optional suffix maps deterministically
✓ JSON Schema property order does not affect inputOrder
✓ Action input property set must exactly equal PHP caller parameter set
✓ schema required set must match PHP no-default required parameters
✓ variadic parameter fails binding production
✓ by-reference parameter fails binding production
✓ method-level DI/object service parameter fails binding production
✓ required-after-optional unsupported shape fails
✓ public component property/method collision fails
✓ reserved $wire method name fails binding production
✓ outputSchema + explicit void/never method fails binding production
✓ null/no outputSchema does not require non-void return
✓ producer includes call-plan fields in every Livewire binding target
✓ Prep List binding becomes inputOrder=[name], requiredCount=1
✓ existing fresh bindingId/replacement semantics remain unchanged
✓ no production livewire/livewire dependency is introduced solely for reflection
```

### Browser target/input validation

```text
✓ rejects non-livewire driver when invoked directly
✓ rejects non-component lifecycle
✓ rejects malformed/missing componentId/method/inputOrder/requiredCount
✓ rejects duplicate/empty inputOrder keys
✓ rejects requiredCount outside range
✓ rejects unknown runtime input keys
✓ rejects missing required input
✓ omits missing trailing optional parameters
✓ rejects optional positional holes
✓ caller object enumeration order does not affect params
✓ values pass unchanged
```

### Browser exact resolution

```text
✓ exact componentId is passed to Livewire.find once
✓ undefined exact component → binding_stale
✓ no first/name/DOM fallback API is called
✓ mismatched returned $wire.$id fails closed
✓ valid exact component invokes exact method once
✓ exact positional params are passed to $wire.$call
✓ raw resolved method output is returned unchanged
✓ original $call rejection object propagates unchanged
```

### Expiry / compatibility

```text
✓ future/null expiry permits normal lookup
✓ expired descriptor → binding_expired before find/call
✓ invalid expiry representation fails closed
✓ pinned reserved $wire names are rejected
✓ normal application method names remain allowed
✓ unavailable/incompatible Livewire runtime → livewire_runtime_unavailable
```

### Scope regression

```text
✓ T-303 WebMCP registration lifecycle tests remain green
✓ browser total suite/typecheck green
✓ PHP full matrix green
✓ contract fixtures/conformance unchanged
✓ spec/0.1 unchanged
✓ no T-305 cancellation implementation mixed in
✓ no M4 trust controls mixed in
```

## Completion boundary

T-304 is DONE when:

- server-issued Livewire bindings contain a deterministic safe positional call plan;
- unsupported/colliding exposed signatures fail before executable binding issuance;
- the browser driver uses only documented exact Livewire lookup/call APIs;
- missing exact targets and expired bindings fail closed with explicit driver errors;
- object Action input maps deterministically to positional Livewire calls with no silent drops/holes;
- semantic method output is returned unchanged;
- Prep List reference method returns its ActionBus result and its binding call plan is proven;
- D-039/D-040/D-041 are accepted;
- browser/PHP/contract/full CI are green;
- external-style review passes.

After T-304 review, stop before T-305. T-305 owns cancellation propagation and must not be implemented implicitly inside this task.
