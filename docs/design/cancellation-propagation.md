# T-305 — Cancellation Propagation Design

Status: approved design, implementation not started.

Date: 2026-09-07

## Purpose

T-305 defines how caller cancellation propagates through SurfaceRelay's browser execution path without turning a stopped client request into a false claim that server-side work was rolled back or reversed.

The core rule is:

> SurfaceRelay may make a strong "not dispatched" cancellation claim only before framework dispatch begins. After dispatch begins, cancellation may stop observation, but server/application outcome is not inferred.

This task implements that rule for the existing WebMCP → DriverRegistry → Livewire browser path.

## Existing execution path

Before T-305:

```text
WebMCP tool execute(input, options)
        ↓
WebMcpRegistrationLifecycle
        ↓
DriverRegistry.requireDriver(binding.driver)
        ↓
LivewireBrowserDriver.execute(binding, input, { signal })
        ↓
Livewire.find(exact componentId)
        ↓
$wire.$call(exact method, ...mappedParams)
```

T-303 already forwards WebMCP's per-execution signal into `DriverExecutionContext`.

T-304 deliberately does not reinterpret that signal as a `$wire.$call()` argument or claim that cancelling the caller reverses already-started work.

## Current external contracts

### WebMCP

The current WebMCP execution callback contract requires an `AbortSignal` in tool execution options. The browser runtime compatibility type must therefore model the execution signal as required rather than optional.

```ts
interface WebMcpToolExecuteOptions {
  signal: AbortSignal;
}
```

The generic `DriverExecutionContext.signal` remains optional because SurfaceRelay drivers may later be invoked from non-WebMCP surfaces that do not provide cancellation.

### Livewire 4.4+

The reference Livewire compatibility target is the documented Livewire 4.4 interceptor surface.

Relevant documented component-scoped APIs:

```js
$wire.intercept(callback)
$wire.intercept('method', callback)
```

Action interceptor context includes:

```text
action.cancel()
onSend(callback)
onCancel(callback)
onFinish(callback)
```

All interceptors return an unsubscribe function.

This task uses only component-scoped action interception. It does not use message-level or request-level cancellation for generic SurfaceRelay action cancellation.

## Cancellation model

T-305 defines an explicit dispatch frontier.

```text
PRE-DISPATCH                              DISPATCHED

queued / buffered / deferred             request has started
        │                                       │
        │                                       │
        ├── exact action.cancel() allowed       ├── no SurfaceRelay Livewire cancel
        │                                       │
        └── strong no-dispatch claim            └── server outcome unknown
                         ▲
                         │
                    onSend frontier
```

### Pre-dispatch

Before the exact Livewire action's `onSend` hook fires, SurfaceRelay may cancel that exact action with `action.cancel()`.

The guarantee is narrow:

> If SurfaceRelay cancels the exact action before the dispatch frontier, that action is not intentionally dispatched by SurfaceRelay to the server.

This is not a general transaction or rollback guarantee. It is a browser/framework dispatch guarantee for the exact captured action.

### Dispatched

Once `onSend` fires, dispatch has begun.

After this point SurfaceRelay must not claim that browser cancellation stopped PHP execution, database work, queue publication, external calls, or any other server/application effect.

Post-dispatch signal abort therefore does not invoke:

```text
action.cancel()
message.cancel()
request.cancel()
```

The underlying Livewire operation is allowed to complete naturally so that framework state synchronization and error handling remain consistent.

## Why request-level cancellation is rejected

Livewire may bundle multiple messages/components/actions into one HTTP request.

`request.cancel()` aborts the request controller and cancels all messages in that request. Therefore a SurfaceRelay action abort could unintentionally cancel unrelated human UI work or another component's update.

Example:

```text
one Livewire request
├── SurfaceRelay agent action
└── unrelated human/component update

agent abort
    ↓
request.cancel()      ← forbidden
    ↓
both operations affected
```

T-305 therefore never uses `request.cancel()` for generic action cancellation.

The same principle excludes message-level cancellation: a message can contain work beyond the exact SurfaceRelay action.

## Why `#[Async]` / isolation is not required

T-305 does not require applications to change the exposed Livewire action's execution semantics merely to manufacture a more cancellable transport.

The reference implementation must not require:

```text
#[Async]
#[Isolate]
private Livewire request APIs
agent-only Livewire methods
agent-only business endpoints
```

Human and agent execution continue to converge at the same exposed Livewire component method established by D-034.

## WebMCP execution gate

`WebMcpRegistrationLifecycle` receives a mandatory WebMCP execution signal.

Before resolving or executing a driver:

```text
options.signal.aborted?
        │
    yes ┴ no
    │       │
throw      DriverRegistry
abort      → driver.execute(...)
reason
```

An already-aborted WebMCP execution must not:

- resolve a driver;
- execute a driver;
- perform Livewire component lookup;
- invoke `$wire.$call()`.

The abort reason must be preserved rather than replaced with a generic driver error.

This is a generic browser-surface rule and does not belong only to the Livewire driver.

## Abort reason semantics

SurfaceRelay distinguishes caller cancellation from Livewire's internal action-cancellation rejection.

When caller cancellation caused a pre-dispatch `action.cancel()`:

```text
AbortSignal reason
        ↓
SurfaceRelay cancels exact Livewire action
        ↓
Livewire action promise rejects internally
        ↓
SurfaceRelay exposes the original AbortSignal reason
```

SurfaceRelay must not expose Livewire's internal cancellation-shaped rejection as if it were the caller's public cancellation contract.

Implementation should use the platform signal's abort reason semantics and must not invent a new business result such as `rolled_back`, `reverted`, or `cancelled_successfully`.

## Livewire compatibility boundary

T-304's compatibility port currently exposes only exact component lookup and `$call()`.

T-305 extends the narrow port with the smallest documented action-interceptor surface needed for cancellation.

Conceptual types:

```ts
interface LivewireActionHandle {
  cancel(): void;
}

interface LivewireActionInterceptorContext {
  action: LivewireActionHandle;
  onSend(callback: () => void): void;
}

interface LivewireWire {
  readonly $id: string;

  $call(
    method: string,
    ...params: unknown[]
  ): Promise<unknown>;

  intercept(
    method: string,
    callback: (context: LivewireActionInterceptorContext) => void,
  ): () => void;
}
```

The production type may include only the fields required by the implementation and tests. It must not mirror Livewire's entire interceptor API.

### Compatibility failure

For a cancellation-aware execution (`context.signal` present), the reference Livewire runtime must expose the documented `intercept` function.

If it does not, execution fails before `$call()` with an explicit runtime-compatibility error rather than silently advertising cancellation semantics that cannot be honored.

A non-cancellation execution path (`context.signal` absent) may retain T-304 behavior and does not need an interceptor solely for T-305.

The supported reference matrix remains Livewire `^4.4`; no lower Livewire compatibility promise is introduced by this task.

## Exact action capture

SurfaceRelay must capture only the exact component/method invocation that it is about to start.

Flow:

```text
resolved exact $wire
        ↓
install component-scoped method interceptor
        ↓
(no await / async gap)
        ↓
$wire.$call(exact method, ...params)
        ↓
interceptor receives exact action
        ↓
immediately unsubscribe interceptor
```

Rules:

1. The interceptor is registered on the exact `$wire` resolved by T-304.
2. It filters by the exact binding method through the documented method-specific interceptor API.
3. No `await`, timer, microtask handoff, or user callback is inserted between interceptor registration and `$call()` initiation.
4. The interceptor is one-shot: it unsubscribes immediately after capturing the first expected action.
5. A final cleanup path also unsubscribes defensively if capture or `$call()` fails.
6. The interceptor is never left installed for future human or unrelated invocations.

If the expected action cannot be captured on a runtime that claims interceptor support, SurfaceRelay fails loudly rather than falling back to global action search or request interception.

## Livewire execution state

The driver needs only a small local state machine for one invocation:

```text
CREATED
  │
  ├─ signal already aborted → ABORTED_PRE_DISPATCH
  │
  ▼
INTERCEPTOR_INSTALLED
  │
  ▼
ACTION_CAPTURED
  │
  ├─ signal abort → action.cancel() → ABORTED_PRE_DISPATCH
  │
  └─ onSend → DISPATCHED
                 │
                 ├─ signal abort → observation cancelled externally;
                 │                 Livewire execution not cancelled
                 │
                 ├─ Livewire success → NATURAL_SUCCESS
                 └─ Livewire failure → NATURAL_FAILURE
```

No public state enum is required unless implementation evidence shows one materially improves correctness. A private boolean such as `dispatched` plus captured-action state is sufficient if tests cover all transitions.

## Detailed algorithm

For a Livewire execution with a signal:

1. Reuse all T-304 descriptor, expiry, reserved-method, input and exact component validation.
2. Check `signal.aborted` before any interceptor or `$call()` work. If aborted, throw the exact abort reason.
3. Require callable documented `$wire.intercept` support.
4. Install one component-scoped interceptor for the exact target method.
5. In that interceptor:
   - capture the exact action handle;
   - register `onSend` to mark `dispatched = true`;
   - immediately unsubscribe the one-shot interceptor.
6. Add one AbortSignal listener for this invocation.
7. Start exact `$wire.$call(method, ...params)` synchronously after interceptor installation.
8. If the signal aborts before `dispatched`:
   - mark cancellation as caller-originated pre-dispatch cancellation;
   - invoke captured `action.cancel()` exactly once.
9. If the signal aborts after `dispatched`:
   - do not invoke any Livewire cancellation primitive.
10. Await the natural `$call()` promise.
11. If the invocation was cancelled pre-dispatch, expose the original signal reason, regardless of Livewire's internal cancellation rejection shape.
12. Otherwise preserve the natural Livewire success value or exact original rejection.
13. In all paths, remove the signal listener and any remaining interceptor subscription.

## Already-aborted race protection

Both the generic WebMCP gate and Livewire driver check already-aborted state.

The duplicate-looking check is intentional defense in depth:

- WebMCP gate prevents needless driver resolution/execution for the primary surface.
- Driver gate preserves correct behavior when the driver is invoked directly or by a future non-WebMCP surface with a signal.

Neither check confers authorization or replaces T-304 binding validity checks.

## Pre-dispatch race semantics

JavaScript execution is run-to-completion within the synchronous interceptor-registration → `$call()` initiation sequence.

The design relies on this property:

```text
install interceptor
$call(...)
capture action
```

is one synchronous initiation sequence with no intentional asynchronous gap.

After action capture, an abort event may occur while the action is buffered/deferred but before `onSend`; in that state exact `action.cancel()` is valid.

## Deferred Livewire actions

Livewire may defer a new action while an overlapping same-scope message is active.

This is a primary reason T-305 uses action-level cancellation.

```text
existing Livewire request in flight
        ↓
SurfaceRelay action created/deferred
        ↓
caller aborts before deferred action fires
        ↓
exact action.cancel()
        ↓
deferred action never intentionally dispatches
```

T-305 must include a focused test for this state rather than only testing an immediate action.

## Post-dispatch semantics

Post-dispatch cancellation has two distinct truths:

```text
caller/WebMCP observation
        = cancelled / no longer waiting

framework/server operation
        = outcome not inferred from cancellation
```

The direct Livewire driver's promise continues to represent the framework's natural result after dispatch:

- successful Livewire response → resolve raw result;
- server/network/application failure → reject exact original error;
- caller signal abort after `onSend` does not replace either result.

This is intentional. It preserves truthful framework outcome semantics and avoids pretending a transport-level cancellation signal can reverse server state.

The WebMCP host may independently stop awaiting/exposing the tool result when its execution signal is aborted. SurfaceRelay does not reinterpret that caller-facing behavior as a server rollback.

## No rollback claim

T-305 must never emit or document statements equivalent to:

```text
"action was rolled back"
"server execution stopped"
"side effect was reversed"
"database transaction was cancelled"
```

unless a later trusted runtime feature has explicit evidence for such a guarantee.

This task has no such feature.

## Error handling

T-304 execution error codes remain unchanged for descriptor/target/runtime problems.

T-305 may add one focused compatibility code if needed, for example:

```text
livewire_cancellation_unavailable
```

This code means the documented cancellation-interceptor capability required for a cancellation-aware invocation is not available.

It does not mean:

- the binding is stale;
- the action failed on the server;
- the request was rolled back.

Caller-originated abort itself should use the original AbortSignal reason instead of a new `LivewireBindingExecutionError` code.

D-026 remains PROPOSED as the complete generic binding-error vocabulary.

## Listener and interceptor cleanup

Every execution must clean up both temporary resources:

```text
component method interceptor subscription
AbortSignal event listener
```

Cleanup is mandatory on:

- natural success;
- natural failure;
- pre-dispatch cancellation;
- interceptor/capture failure;
- synchronous `$call()` initiation failure.

Cleanup must be idempotent.

No execution-local listener may remain attached after the driver's promise settles.

## Interaction with T-303 registration lifetime

Registration cancellation and execution cancellation remain distinct authorities.

```text
registration lease AbortSignal
→ controls whether the WebMCP tool remains registered

WebMCP execute options.signal
→ controls one invocation
```

Disposing the T-303 registration lease must not be reinterpreted as cancelling an already-running business invocation unless the WebMCP host separately aborts that invocation's execution signal.

D-037 remains unchanged.

## Interaction with M4 trust controls

T-305 does not solve replay, idempotency or final-state evidence.

Those remain separate:

```text
T-402 idempotency
→ repeated/duplicate execution protection

T-404 structured audit
→ authoritative execution/final-state observability
```

Cancellation does not substitute for either control.

## Threat-model alignment

T-305 operationalizes T13 — Cancellation confusion.

Required security property:

> A cancelled caller must not cause SurfaceRelay to report reversal of an effect that may already have happened, and cancelling one SurfaceRelay action must not cancel unrelated Livewire work through a broader request/message primitive.

Negative security test:

```text
SurfaceRelay action and unrelated Livewire work share/belong to broader runtime activity
        ↓
SurfaceRelay signal aborts
        ↓
SurfaceRelay must not call message.cancel() or request.cancel()
```

## Proposed decisions

The implementation should promote these decisions to `ACCEPTED` only when corresponding behavior is proven by tests.

### D-042 — Cancellation frontier

A SurfaceRelay execution has a strong no-dispatch cancellation guarantee only before framework dispatch begins. WebMCP execution provides a required AbortSignal and an already-aborted signal prevents driver execution. For Livewire, the exact action's documented `onSend` hook marks the dispatch frontier. Cancellation after that frontier does not imply that server work stopped, rolled back, or reversed.

### D-043 — Granular Livewire cancellation only

The Livewire reference driver uses the documented component-scoped action interceptor and exact `action.cancel()` only before the dispatch frontier. It does not use message/request cancellation for generic SurfaceRelay action cancellation because those scopes may contain unrelated framework work. SurfaceRelay does not require `#[Async]`, `#[Isolate]`, or private Livewire request APIs to manufacture cancellability.

## TDD acceptance matrix

### WebMCP boundary

1. `WebMcpToolExecuteOptions.signal` is required by TypeScript contract.
2. Already-aborted WebMCP signal prevents `DriverRegistry.requireDriver()` execution.
3. Already-aborted WebMCP signal preserves the exact abort reason.
4. Non-aborted signal is forwarded unchanged to `DriverExecutionContext`.

### Livewire compatibility port

5. Exact `$wire.intercept(method, callback)` shape is represented narrowly.
6. Runtime missing callable interceptor fails before `$call()` when signal is present.
7. Interceptor unsubscribe is called exactly once after capture/cleanup.

### Pre-dispatch

8. Already-aborted driver signal performs no `find()` or `$call()`.
9. Signal abort after action capture but before `onSend` invokes exact `action.cancel()` once.
10. Pre-dispatch cancellation returns/throws the exact caller AbortSignal reason rather than Livewire's internal cancellation object.
11. Deferred/queued exact action can be cancelled before dispatch.
12. No broader request/message cancellation primitive is called.

### Dispatch frontier

13. `onSend` marks dispatch before subsequent abort handling.
14. Abort after `onSend` does not invoke `action.cancel()`.
15. Abort after `onSend` does not invoke request/message cancellation.

### Natural post-dispatch outcome

16. Post-dispatch abort followed by Livewire success resolves the exact raw Livewire result for direct driver execution.
17. Post-dispatch abort followed by Livewire failure rejects the exact original error.
18. No synthetic rollback/cancelled-success result is returned.

### Cleanup / isolation

19. AbortSignal listener is removed on success, failure and pre-dispatch cancellation.
20. One-shot interceptor cannot capture a later unrelated same-method invocation.
21. Existing T-304 no-signal execution remains behaviorally compatible.
22. T-303 registration lifetime signal remains independent from execution cancellation.

## Scope

### In scope

- WebMCP required execution signal type correction;
- generic already-aborted WebMCP execution gate;
- narrow Livewire action-interceptor compatibility types;
- exact one-shot action capture;
- exact action cancellation before `onSend`;
- dispatch-frontier tracking;
- exact abort-reason preservation;
- post-dispatch natural-outcome preservation;
- listener/interceptor cleanup;
- T13 cancellation-confusion tests/documentation;
- D-042 and D-043 after implementation evidence.

### Out of scope

- `request.cancel()` for generic SurfaceRelay actions;
- `message.cancel()` for generic SurfaceRelay actions;
- claiming HTTP abort stops PHP execution;
- transaction rollback/reversal semantics;
- forcing Livewire `#[Async]` or `#[Isolate]`;
- private Livewire `fireAction`, request or message internals;
- cancellation of registration lease as a substitute for invocation cancellation;
- server-side cooperative cancellation protocol;
- queue-job cancellation;
- external API compensation;
- confirmation receipts;
- idempotency persistence;
- structured audit/final-state evidence;
- Filament/HTMX cancellation behavior.

## Expected production touch points

Likely browser-runtime files:

```text
packages/browser-runtime/src/webmcp-types.ts
packages/browser-runtime/src/webmcp-registration-lifecycle.ts
packages/browser-runtime/src/livewire-browser-runtime.ts
packages/browser-runtime/src/livewire-browser-driver.ts
packages/browser-runtime/src/livewire-errors.ts     (only if compatibility code is needed)
```

Likely tests:

```text
packages/browser-runtime/tests/webmcp-types.typecheck.ts
packages/browser-runtime/tests/webmcp-registration-lifecycle.test.ts
packages/browser-runtime/tests/livewire-browser-runtime.test.ts
packages/browser-runtime/tests/livewire-browser-driver.test.ts
packages/browser-runtime/tests/livewire-webmcp-integration.test.ts
```

No Laravel PHP production change is expected unless implementation evidence demonstrates a real server-side contract requirement. T-305 is primarily a browser execution concern.

## Completion criteria

T-305 is complete only when:

1. the written design has been approved;
2. implementation follows TDD with meaningful RED evidence;
3. WebMCP signal type/gate is proven;
4. Livewire pre-dispatch exact-action cancellation is proven;
5. post-dispatch no-rollback/no-broad-cancel behavior is proven;
6. cleanup and same-method isolation are proven;
7. existing T-301 through T-304 browser tests remain green;
8. PHP and contract baselines remain green;
9. D-042/D-043 are recorded only after behavior exists;
10. `spec/0.1` remains unchanged unless an independent contract-level issue is discovered;
11. external-style review passes;
12. T-401/M4 work does not begin automatically.
