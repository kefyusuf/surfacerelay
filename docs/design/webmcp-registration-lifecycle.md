# WebMCP Async Registration Lifecycle Design

Status: approved architecture candidate for T-303 implementation planning.

## Goal

T-303 adds the first browser registration lifecycle around the already-reviewed M3 primitives:

```text
T-301 DriverRegistry
        +
T-302 WebMCP semantic projection
        +
current ActionDefinition + RuntimeBinding pairs
        ↓
WebMCP registration lifecycle
        ↓
registered browser tools with explicit cleanup authority
```

The task owns browser registration, registration failure cleanup, deterministic WebMCP tool identity, and lifecycle disposal. It does not implement the Livewire browser driver, stale binding resolution, authorization, discovery policy, or production trust controls.

## WebMCP contract assumptions

T-303 targets the current imperative WebMCP model in which:

- `document.modelContext.registerTool(tool, options)` is asynchronous;
- `ModelContextRegisterToolOptions.signal` controls the lifetime of that registration;
- aborting the registration signal unregisters the associated tool;
- a registration promise may reject for browser/API errors;
- a tool execution callback separately receives `ToolExecuteCallbackOptions.signal` for one execution;
- registration lifetime and per-execution cancellation are distinct authorities.

SurfaceRelay must preserve that distinction rather than sharing one controller for both concerns.

## Core model

A browser-visible candidate is one exact semantic action plus one exact executable binding:

```ts
export interface BoundActionTool {
  definition: ActionDefinition;
  binding: RuntimeBinding;
}
```

The pair is valid for T-303 projection only when:

```text
binding.action.id      == definition.id
binding.action.version == definition.version
```

T-303 does not resolve or negotiate another ActionDefinition version.

## Recommended lifecycle model: snapshot lease

T-303 uses a snapshot lease rather than an incremental diff manager.

```text
readonly BoundActionTool[]
        ↓
preflight entire snapshot
        ↓
create one AbortController
        ↓
register projected tools sequentially
        ↓
WebMcpRegistrationLease
        ↓ dispose()
AbortController.abort()
```

Each successful `register()` call represents one coherent generation of currently exposed tools.

The caller replaces a generation explicitly:

```ts
currentLease.dispose();
currentLease = await lifecycle.register(nextSnapshot);
```

T-303 does not automatically observe application state, DOM state, Livewire lifecycle events, routing, or component replacement. Those producers decide when a new snapshot is required.

## Registration lease

Add an immutable ownership handle conceptually equivalent to:

```ts
export interface WebMcpRegistrationLease {
  dispose(): void;
}
```

Requirements:

- `dispose()` aborts the registration controller exactly once semantically;
- repeated `dispose()` is safe/idempotent;
- a disposed lease cannot be reused to register new tools;
- lease disposal does not claim transactional rollback of already-started application work;
- the lease owns registration authority only.

The controller itself should not be exposed as mutable public state unless tests require an inspection seam.

## Browser API compatibility boundary

Do not spread ambient `document.modelContext` access throughout the package.

Introduce a narrow adapter-facing port:

```ts
export interface WebMcpModelContext {
  registerTool(
    tool: WebMcpTool,
    options: { signal: AbortSignal },
  ): Promise<void>;
}
```

A browser adapter may wrap `document.modelContext` later/in the same task, but the lifecycle coordinator depends on this explicit port.

This isolates WebMCP API churn from core lifecycle logic and keeps tests independent of browser implementation availability.

T-303 does not call `getTools()` or `executeTool()`.

## Projected tool model

T-303 needs only the subset required by imperative registration:

```ts
export interface WebMcpTool {
  name: string;
  title: string;
  description: string;
  inputSchema: Record<string, unknown>;
  annotations: WebMcpAnnotations;
  execute(
    input: Record<string, unknown>,
    options: { signal?: AbortSignal },
  ): Promise<unknown>;
}
```

The exact callback option type may mirror the current WebMCP shape more tightly during implementation, but SurfaceRelay must preserve execution cancellation separately from registration cancellation.

## WebMCP tool identity

SurfaceRelay Action IDs are semantic identities and may have multiple explicit versions. WebMCP registration names must therefore include the exact version.

Canonical projection:

```text
<action-id>.v<version>
```

Examples:

```text
prep_list.add_item@1 → prep_list.add_item.v1
orders.refund@3      → orders.refund.v3
```

Current WebMCP imperative tool names must be between 1 and 128 characters inclusive and may contain only ASCII alphanumeric characters plus `_`, `-`, and `.`. SurfaceRelay preflight must enforce that current surface contract before calling `registerTool()`.

Properties:

- deterministic from exact Action identity only;
- never includes `bindingId`;
- never includes component IDs or driver target metadata;
- never truncates;
- never hashes as a fallback;
- never aliases a second version to the first;
- must satisfy the WebMCP name contract before registration.

Because the frozen SurfaceRelay Action ID grammar already uses only lowercase ASCII alphanumeric characters, `_`, and `.`, and the `.v<version>` suffix uses only WebMCP-valid characters, a canonical valid ActionDefinition can fail this projection check only when the resulting name exceeds WebMCP's 128-character limit. The validator still checks the complete WebMCP grammar rather than relying on that inference.

If the projected name is invalid or exceeds the supported WebMCP name limit, preflight fails loudly before any tool is registered.

## Ambiguous bindings

One Action Definition can theoretically have multiple simultaneous RuntimeBindings, for example two mounted component instances exposing the same action/version.

Because WebMCP identity is action identity, both would project to the same tool name:

```text
component A → prep_list.add_item@1
component B → prep_list.add_item@1
                    ↓
          prep_list.add_item.v1
```

T-303 must not invent authority selection.

Therefore duplicate projected tool names within one snapshot fail preflight.

Do not resolve ambiguity by:

- appending `bindingId`;
- appending `componentId`;
- choosing first/last binding;
- sorting and selecting one;
- reusing an existing browser registration.

A later explicit surface-selection policy may choose which binding is exposed, but T-303 does not guess.

## Preflight

The complete snapshot is validated before the first `registerTool()` call.

For every candidate:

1. exact ActionDefinition id/version equals `binding.action`;
2. `binding.driver` is supported by `DriverRegistry.requireDriver()`;
3. projected WebMCP name is contract-valid;
4. projected names are unique within the snapshot;
5. required projection fields are available from the ActionDefinition.

Important boundary:

`DriverRegistry.requireDriver()` here proves only that an execution adapter is explicitly supported. It does not validate binding lifecycle, expiry, revocation, target existence, authorization, or invocation context.

T-303 must not claim those checks.

Preflight must not call `BindingDriver.execute()`.

## Tool execution callback

For each preflighted candidate, registration uses a callback equivalent to:

```ts
execute(input, options) {
  const driver = driverRegistry.requireDriver(binding.driver);

  return driver.execute(binding, input, {
    signal: options.signal,
  });
}
```

The exact RuntimeBinding object captured during snapshot projection is preserved. The callback never rediscovers or retargets a similar binding.

T-303 intentionally relies on T-301 for exact driver lookup. Actual `livewire` behavior remains T-304.

If a driver is removed/reconfigured after registration, invocation behavior remains governed by the registry/runtime design chosen by later tasks; T-303 must not silently cache a replacement driver under another name.

## Semantic metadata projection

Tool metadata is projected directly from the ActionDefinition:

```text
name        ← exact Action identity projection

title       ← definition.title

description ← definition.description

inputSchema ← definition.inputSchema

annotations ← projectAnnotations(definition)
```

T-302 remains the sole semantic annotation mapping. T-303 does not reinterpret effect, risk, sensitivity, or content trust.

No untrusted runtime data, component labels, selected record values, or binding target metadata is embedded into tool title/description.

## Registration order

Registration is sequential and deterministic.

Before registration, projected candidates are sorted by tool name ascending.

Rationale:

- deterministic browser call order;
- deterministic first failure;
- simple cleanup semantics;
- no meaningful performance need for parallel registration at T-303 scale;
- avoids unnecessary promise/race complexity.

Input array order is therefore not an authority signal.

## Partial-failure cleanup

A registration batch must not intentionally leave a partially exposed SurfaceRelay generation alive.

Algorithm:

```text
preflight all
        ↓
controller = new AbortController()
        ↓
for tool in deterministic order:
    await registerTool(tool, {signal})
        ↓
if any registration rejects:
    controller.abort()
    rethrow original registration error
```

This establishes a SurfaceRelay invariant:

```text
registration succeeds as a generation
OR
no registration from that generation should remain intentionally alive
```

This is lifecycle cleanup, not a database/browser transaction claim. Browser internals may process asynchronous notifications/races according to WebMCP semantics.

The original registration error remains the caller-visible error; cleanup must not replace it with a secondary abort error.

## Empty snapshot

Registering an empty snapshot is valid.

It returns a disposable lease owning no tools. This keeps caller code simple and avoids a special null/no-lease state.

## Pre-aborted/external signals

T-303 does not accept a caller-provided registration `AbortSignal` in the public lifecycle API.

The lifecycle coordinator owns its controller so cleanup authority cannot be split across unrelated callers.

If later requirements need composition with an external signal, that should be designed explicitly rather than added as an optional convenience parameter now.

## Cross-origin exposure

T-303 does not expose `exposedTo` through SurfaceRelay's public registration API.

Cross-origin tool exposure is an authority/policy decision and should not become a generic convenience option before a dedicated policy boundary exists.

Registration calls therefore use only:

```ts
{ signal: controller.signal }
```

for T-303.

## Error behavior

T-303 keeps native/browser registration errors intact unless a SurfaceRelay preflight invariant fails first.

SurfaceRelay preflight errors should be explicit for:

- action/binding identity mismatch;
- unsupported driver;
- invalid projected tool name;
- duplicate projected tool identity.

T-303 does not finalize D-026 browser binding failure codes. Those belong to actual binding resolution/execution behavior in T-304.

## Proposed decisions

### D-037 — Registration lifetime authority

> One current WebMCP tool snapshot is owned by one SurfaceRelay registration lease and one registration AbortController. Aborting/disposal ends that generation's registration authority. Partial registration failure aborts the batch and rethrows the original failure. Registration lifetime signals and per-execution cancellation signals are separate authorities.

### D-038 — WebMCP tool identity

> WebMCP tool names are deterministic projections of exact Action identity using `<action-id>.v<version>`. Invalid/too-long or duplicate projected names fail loudly. SurfaceRelay never uses binding IDs, component IDs, truncation, hashes, aliases, or fallback selection to manufacture a unique WebMCP identity.

Both decisions become ACCEPTED only with implemented/tested behavior.

## Production shape

Expected browser-runtime additions:

```text
packages/browser-runtime/src/
├── webmcp-types.ts                    # narrow compatibility types if useful
├── webmcp-tool-projection.ts          # BoundActionTool → WebMcpTool
└── webmcp-registration-lifecycle.ts   # snapshot preflight/register/lease cleanup
```

Existing:

```text
driver-registry.ts
webmcp-projection.ts
```

remain focused on their current responsibilities.

Exact filenames may be collapsed if implementation shows a smaller clearer boundary, but lifecycle coordination and pure projection must remain independently testable.

## Testing strategy

TDD should prove at minimum:

```text
✓ exact action id/version projects to <action-id>.v<version>
✓ version is part of browser tool identity
✓ invalid/too-long projected names fail before browser registration
✓ exact definition/binding identity mismatch fails before registration
✓ unsupported driver fails before registration
✓ duplicate projected names fail before registration
✓ same action/version on two bindings is rejected as ambiguous
✓ preflight never executes a driver
✓ input order does not change deterministic registration order
✓ registration is sequential
✓ all tools receive the same lease registration signal
✓ successful batch returns a live lease
✓ dispose aborts the registration signal
✓ dispose is idempotent
✓ empty snapshot returns a disposable lease and makes no browser calls
✓ failure after earlier successful registrations aborts the generation
✓ original registration error is rethrown
✓ execution callback receives the exact captured RuntimeBinding
✓ execution callback resolves the exact registered driver name
✓ execution input is passed unchanged to BindingDriver
✓ execution cancellation signal is forwarded to DriverExecutionContext
✓ registration signal is never reused as execution cancellation signal
✓ T-302 annotations are included unchanged
✓ outputSensitivity is not reinterpreted by registration
✓ no exposedTo/cross-origin policy surface is introduced
✓ no Livewire-specific browser behavior is implemented
✓ spec/0.1 remains unchanged
```

Use fake `WebMcpModelContext` and fake BindingDrivers only as boundary test doubles. Tests should assert behavior/state rather than implementation-private call mechanics where possible.

## Security and authority invariants

1. Registration is not authorization.
2. Binding ID remains a reference, not proof of invocation authority.
3. Registration does not validate stale/expired/revoked binding state unless such a resolver exists later.
4. Unknown drivers fail closed through T-301.
5. Duplicate action-visible identity never selects a binding implicitly.
6. Registration cleanup authority is owned by the lease.
7. Execution cancellation authority is per execution, not the registration lease.
8. Browser errors are not suppressed into success.
9. Partial batch failure does not intentionally leave earlier SurfaceRelay registrations active.
10. WebMCP metadata contains stable ActionDefinition data only, not untrusted runtime content.
11. Cross-origin exposure remains out of scope.
12. No surface projection may create a second business implementation path.

## Non-goals

T-303 must not implement:

- Livewire `Livewire.find()` target resolution;
- `$wire.$call()` execution;
- stale/expired/revoked RuntimeBinding validation;
- D-026 final browser failure codes;
- automatic observation of DOM/router/component lifecycle;
- incremental/diff-based tool reconciliation;
- tool selection among ambiguous bindings;
- cross-origin `exposedTo` policy;
- WebMCP `getTools()`/`executeTool()` orchestration;
- agent planning;
- transactional rollback claims;
- confirmation/idempotency/output-redaction/audit production controls;
- Filament or HTMX drivers.

These remain later tasks/milestones.

## Completion boundary

T-303 is complete when:

- snapshot preflight and deterministic tool projection exist;
- browser registration is async and sequential;
- successful registration returns an owning disposable lease;
- disposal uses WebMCP registration AbortSignal semantics;
- partial registration errors clean up the generation and preserve the original error;
- execution cancellation remains separately forwarded to BindingDriver;
- D-037/D-038 are accepted;
- browser typecheck/tests and full repository CI are green;
- external-style review passes.

After T-303 review, stop before T-304. The Livewire browser driver requires its own design gate.
