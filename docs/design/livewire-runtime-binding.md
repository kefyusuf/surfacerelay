# Livewire Runtime Binding Design

Status: approved design for T-201 implementation planning.

## Goal

Introduce the first real PHP Runtime Binding model and a Livewire-specific descriptor without leaking Livewire concepts into `ActionDefinition` or prematurely implementing discovery, lifecycle management, execution, or browser integration.

T-201 establishes only the descriptor boundary required by later M2/M3 tasks.

## Existing contract constraints

The design must preserve the frozen `spec/0.1/runtime-binding.schema.json` semantics:

- `bindingId` identifies one exact issued binding instance;
- `action.id + action.version` identifies one exact Action Definition version;
- `driver` is an extensible identifier, not a framework enum;
- lifecycle validity is cumulative with expiry/revocation/runtime availability;
- stale or replaced targets fail closed;
- bindings never silently retarget to replacement components;
- `target` shape belongs to the named driver;
- a binding reference is never authorization by itself.

The current Livewire example already uses:

```json
{
  "bindingId": "prep-list:component:example-123:add-item",
  "action": {
    "id": "prep_list.add_item",
    "version": 1
  },
  "driver": "livewire",
  "lifecycle": "component",
  "target": {
    "componentId": "example-123",
    "method": "addItem"
  },
  "expiresAt": null
}
```

T-201 should make the PHP reference runtime capable of producing this shape without changing the language-neutral schema.

## Chosen architecture

Use a generic immutable `RuntimeBinding` value object plus a typed Livewire target/factory.

```text
ActionDefinition
      │
      ▼
RuntimeBinding
├── bindingId
├── exact action id + version
├── driver
├── lifecycle
├── target
├── expiresAt
└── extensions
      ▲
      │
LivewireBindingTarget
├── componentId
└── method
```

Proposed package structure:

```text
packages/laravel/src/
├── Binding/
│   ├── BindingLifecycle.php
│   ├── RuntimeBinding.php
│   └── InvalidRuntimeBinding.php
└── Livewire/
    ├── LivewireBindingTarget.php
    └── LivewireRuntimeBinding.php
```

Equivalent naming is acceptable only if it preserves the same boundaries.

## Generic RuntimeBinding

`RuntimeBinding` is protocol-neutral PHP runtime code. It must not reference Livewire, Filament, WebMCP, MCP, HTTP, DOM concepts, or browser APIs.

It represents:

```text
bindingId
exact Action Definition id + version
driver
lifecycle
target
expiresAt
extensions
```

The preferred construction path accepts an existing validated `ActionDefinition` rather than accepting arbitrary action ID/version strings and duplicating the Action ID validation logic.

Conceptually:

```php
RuntimeBinding::forDefinition(
    definition: $definition,
    bindingId: $bindingId,
    driver: $driver,
    lifecycle: $lifecycle,
    target: $target,
    expiresAt: $expiresAt,
    extensions: $extensions,
);
```

The resulting binding copies the exact definition `id` and `version` into its serialized ActionReference. It does not retain a mutable route to alter the definition or retarget the binding later.

No “latest version”, fallback version, or version negotiation API is allowed.

## Binding lifecycle representation

Add a PHP backed enum matching the frozen vocabulary exactly:

```text
page
component
session
persistent
```

The generic value object may represent all four lifecycle values because HTMX and later drivers will reuse the same PHP contract.

The Livewire T-201 factory, however, always produces:

```text
lifecycle = component
```

because its target references one exact mounted component instance.

Callers must not be able to use the Livewire component-target factory to manufacture `page`, `session`, or `persistent` bindings.

## Livewire target

The T-201 target contains only:

```text
componentId
method
```

Both are non-empty strings and preserved verbatim after validation.

Do not include:

```text
componentName
component class
DOM selector
URL
business record ID
route
browser state
```

The exact component instance ID is the target authority reference. Component class/name is intentionally excluded because it could encourage rediscovery of a replacement instance, which conflicts with D-022/D-025.

Livewire's browser runtime exposes exact component access by ID and explicit method invocation. That later supports T-304 without requiring T-201 to perform execution.

## LivewireRuntimeBinding factory

`LivewireRuntimeBinding` should be a thin framework-specific constructor/factory that produces a generic `RuntimeBinding` with these fixed properties:

```text
driver = livewire
lifecycle = component
target = LivewireBindingTarget::toArray()
```

It accepts:

```text
ActionDefinition
bindingId
componentId
method
optional expiresAt
optional extensions
```

It does not generate a binding ID.

Binding issuance/generation/revocation belongs to T-203.

## Validation invariants

The PHP descriptor must enforce structural invariants consistent with the frozen schema.

### RuntimeBinding

- `bindingId`: non-empty, maximum 240 characters;
- `driver`: `^[a-z][a-z0-9_.:-]{0,79}$`;
- `target`: non-empty associative object-like array;
- `expiresAt`: null or valid RFC3339 date-time string;
- `extensions`: keys follow `namespace/key` grammar;
- action identity comes from an existing `ActionDefinition`;
- value object is immutable.

The RuntimeBinding implementation may share a small internal RFC3339 validator with `ConfirmationChallenge` only if doing so reduces duplicate correctness logic without introducing a broad date abstraction. Otherwise a narrowly duplicated private check is preferable to an unrelated refactor.

### LivewireBindingTarget

- `componentId`: non-empty string;
- `method`: non-empty string;
- deterministic serialization order: `componentId`, `method`;
- immutable.

T-201 does not verify that the method currently exists on a component. Doing that here would either require a Livewire runtime dependency or reflection-based exposure and would cross into T-202/T-203 concerns.

## Serialization

`RuntimeBinding::toArray()` should deterministically emit a schema-valid object.

Preferred ordering:

```text
bindingId
action
driver
lifecycle
target
expiresAt
extensions (only when non-empty)
```

For Livewire, exact expected output is:

```json
{
  "bindingId": "prep-list:component:example-123:add-item",
  "action": {
    "id": "prep_list.add_item",
    "version": 1
  },
  "driver": "livewire",
  "lifecycle": "component",
  "target": {
    "componentId": "example-123",
    "method": "addItem"
  },
  "expiresAt": null
}
```

`expiresAt: null` should be serialized explicitly for parity with the current reference fixture. `extensions` may be omitted when empty.

## Dependency boundary

T-201 must not add `livewire/livewire` to Composer dependencies.

The descriptor accepts strings/value objects only and never accepts a `Livewire\Component` instance.

Framework integration such as resolving a component's runtime ID belongs to T-202/T-203.

This keeps T-201 compatible with the existing PHP/Illuminate CI matrix and avoids prematurely introducing a Livewire-version matrix.

## Relationship to later tasks

### T-202 — explicit exposure

Owns the application API that declares which actions/method mappings a mounted component intentionally exposes.

No “all public methods” reflection path is allowed.

### T-203 — lifecycle producer

Owns:

- binding ID generation/issuance;
- binding registry/storage if needed;
- mounted component identity capture;
- invalidation/revocation;
- stale/replacement detection.

### T-204 — shared ActionBus E2E

Proves human UI and agent binding execute the same business action once.

### T-304 — browser execution

Owns exact browser-side component lookup and invocation using the Livewire browser API. Old component IDs must never be replaced with a similar component automatically.

## Explicit non-goals

T-201 does not implement:

- component discovery;
- component-name fallback;
- reflection-based method exposure;
- `livewire/livewire` dependency;
- binding ID generation;
- binding registry/storage;
- revocation;
- component existence checks;
- stale lookup handling;
- browser `Livewire.find()` calls;
- `$wire.$call()` execution;
- ActionBus integration;
- current record/selection resolution;
- Filament behavior;
- WebMCP projection;
- confirmation/idempotency/output policy.

## Security properties

The implementation must preserve these properties:

1. A binding ID is never authorization.
2. Exact Action Definition version is retained; no fallback.
3. The Livewire target identifies an exact component instance, not a component type.
4. Replacing a component requires a new binding later; T-201 exposes no retarget mutation API.
5. Component method metadata stays in binding/driver code and never enters `ActionDefinition`.
6. No caller input or invocation metadata is used to construct trusted context in this task.
7. No public-method reflection creates an accidental exposure surface.

## Acceptance tests

T-201 implementation must include tests for at least:

- exact Livewire fixture serialization;
- `driver` always `livewire` through the Livewire factory;
- lifecycle always `component` through the Livewire factory;
- exact action ID/version copied from `ActionDefinition`;
- two Action Definition versions remain distinct;
- empty binding ID rejected;
- overlength binding ID rejected;
- malformed driver rejected by generic RuntimeBinding;
- empty target rejected by generic RuntimeBinding;
- empty component ID rejected;
- empty method rejected;
- null expiry accepted;
- valid RFC3339 expiry accepted and preserved;
- invalid expiry rejected;
- malformed extension key rejected;
- deterministic serialization;
- immutability;
- no Livewire Composer dependency added;
- ActionDefinition remains free of component/method fields.

## Completion boundary

T-201 is complete when the descriptor/value-object layer and its tests are green under the full existing CI matrix.

Stop after T-201 review evidence is recorded. Do not begin T-202 automatically.
