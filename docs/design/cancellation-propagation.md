# T-305 — Cancellation Propagation Design

Status: approved design, implementation not started.

Date: 2026-09-07

## Purpose

T-305 defines how caller cancellation propagates through SurfaceRelay's existing WebMCP → DriverRegistry → Livewire execution path without turning a stopped client request into a false claim that server-side work was rolled back or reversed.

Core rule:

> SurfaceRelay may make a strong "not dispatched" cancellation claim only before framework dispatch begins. After dispatch begins, caller cancellation does not prove that server/application work stopped, rolled back, or reversed.

This design operationalizes Threat Model T13 — cancellation confusion.

## Existing path

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

T-303 already forwards the per-execution WebMCP signal into `DriverExecutionContext`.

T-304 deliberately does not append that signal to `$wire.$call()` or claim that caller cancellation reverses already-started work.

## External contract corrections

### WebMCP signal is required

The current WebMCP tool execution callback contract requires an `AbortSignal`.

The compatibility type therefore becomes:

```ts
interface WebMcpToolExecuteOptions {
  signal: AbortSignal;
}
```

`DriverExecutionContext.signal` remains optional because future non-WebMCP surfaces may invoke drivers without cancellation support.

### Livewire 4.4+ cancellation surface

The reference Livewire compatibility target is the documented Livewire 4.4 action-interceptor API:

```js
$wire.intercept(callback)
$wire.intercept('method', callback)
```

The action interceptor exposes the exact action plus lifecycle hooks including:

```text
action.cancel()
onSend(callback)
onCancel(callback)
onFinish(callback)
```

Interceptors return an unsubscribe function.

T-305 uses only component-scoped action interception. It does not use message-level or request-level cancellation for generic SurfaceRelay execution.

## Cancellation frontier

T-305 defines one hard browser/framework dispatch frontier:

```text
PRE-DISPATCH                              DISPATCHED

queued / buffered / deferred             exact action onSend fired
        │                                       │
        ├── exact action.cancel() allowed       ├── no SurfaceRelay Livewire cancel
        │                                       │
        └── strong no-dispatch claim            └── server outcome unknown
```

### Pre-dispatch guarantee

Before the exact captured action's `onSend` hook fires, SurfaceRelay may invoke `action.cancel()`.

The guarantee is intentionally narrow:

> If SurfaceRelay cancels the exact Livewire action before `onSend`, SurfaceRelay does not intentionally dispatch that action to the server.

This is not a database, queue, external-system, or transaction rollback guarantee.

### Post-dispatch rule

Once `onSend` fires, dispatch has begun.

After this frontier, caller abort must not trigger:

```text
action.cancel()
message.cancel()
request.cancel()
```

The Livewire operation is allowed to complete naturally so framework state synchronization remains coherent and the direct driver result continues to describe the framework's real outcome.

## Why broad Livewire cancellation is forbidden

A Livewire HTTP request may bundle multiple messages, components, actions, or state updates.

`request.cancel()` aborts the request controller and cancels every message in that request. Cancelling one SurfaceRelay invocation could therefore interfere with unrelated human/UI work.

```text
one Livewire request
├── SurfaceRelay invocation
└── unrelated component/UI work

SurfaceRelay signal abort
        ↓
request.cancel()          ← forbidden
        ↓
unrelated work affected
```

Message cancellation is rejected for the same scope reason: the message may contain more than the exact SurfaceRelay action.

T-305 uses only exact `action.cancel()` and only pre-dispatch.

## No forced isolation

SurfaceRelay must not change application semantics merely to manufacture cancellability.

T-305 does not require:

```text
#[Async]
#[Isolate]
agent-only Livewire methods
agent-only business endpoints
private Livewire request/fireAction APIs
```

Human and binding-derived invocation continue to converge at the same exposed component method under D-034.

## Generic WebMCP abort gate

Before invocation-time driver lookup/execution, the registered tool callback checks the required WebMCP signal:

```text
options.signal.aborted?
        │
    yes ┴ no
    │       │
throw      invocation-time
abort      DriverRegistry.requireDriver(...)
reason     → driver.execute(...)
```

Important distinction:

- T-303 registration-time driver-support preflight remains unchanged.
- T-305 blocks only invocation-time driver resolution/execution when the execution signal is already aborted.

An already-aborted invocation must not execute a driver, perform Livewire lookup, or call `$wire.$call()`.

The exact abort reason is preserved.

## Abort reason semantics

Caller-originated cancellation and Livewire's internal cancellation rejection are different contracts.

When caller abort causes pre-dispatch `action.cancel()`:

```text
AbortSignal reason
        ↓
exact Livewire action.cancel()
        ↓
Livewire action promise rejects internally
        ↓
SurfaceRelay exposes original AbortSignal reason
```

SurfaceRelay must not expose Livewire's internal cancellation object as its public caller-cancellation result.

No synthetic business result such as `rolled_back`, `reverted`, or `cancelled_successfully` is introduced.

## Livewire compatibility port

T-304 currently models exact component lookup plus `$call()`.

T-305 extends the port only with the documented action-interceptor fields it needs:

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

The implementation must not mirror the entire Livewire interceptor API.

### Runtime compatibility failure

When `DriverExecutionContext.signal` is present, the reference Livewire runtime must expose callable documented `intercept` support.

If not, execution fails before `$call()` with an explicit cancellation-compatibility error rather than silently claiming cancellation semantics that cannot be provided.

When no signal is present, the T-304 execution path may continue without installing a cancellation interceptor.

The reference compatibility matrix remains Livewire `^4.4`; T-305 introduces no lower-version support promise.

## Exact one-shot action capture

SurfaceRelay captures only the action created by the exact `$wire.$call()` it is about to initiate.

```text
exact $wire resolved by T-304
        ↓
install component-scoped exact-method interceptor
        ↓
(no await / timer / user callback)
        ↓
$wire.$call(exact method, ...params)
        ↓
interceptor captures exact action
```

Rules:

1. The interceptor is attached to the exact `$wire` resolved by T-304.
2. It uses the documented method-specific interceptor form.
3. No asynchronous gap is introduced between interceptor installation and `$call()` initiation.
4. The callback is logically one-shot immediately: once the expected action is captured, a local `captured` guard makes any later callback invocation a no-op.
5. Physical unsubscribe is **not** performed synchronously from inside the interceptor callback.
6. Physical unsubscribe is scheduled after Livewire's current interceptor iteration (for example with a microtask), then repeated defensively by final cleanup if still needed.
7. This avoids mutating Livewire's interceptor array while Livewire is iterating it, which could otherwise skip an unrelated interceptor registered after SurfaceRelay's callback.
8. No future human/same-method invocation may be captured by this execution-local interceptor.

If a runtime claims interceptor support but the expected action is not captured from the synchronous `$call()` initiation, SurfaceRelay fails loudly. It does not fall back to global action search, message inspection, request interception, DOM lookup, or private Livewire APIs.

## Per-invocation state

A small private state machine is sufficient:

```text
CREATED
  │
  ├─ already aborted → ABORTED_PRE_DISPATCH
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
                 ├─ signal abort → no framework cancellation
                 ├─ Livewire success → NATURAL_SUCCESS
                 └─ Livewire failure → NATURAL_FAILURE
```

No public lifecycle enum is required unless implementation evidence shows one is necessary. Private captured/dispatched/cancelled flags are sufficient if transition tests are exhaustive.

## Detailed algorithm

For a Livewire execution with a signal:

1. Reuse all T-304 target, expiry, reserved-method, input mapping and exact component checks.
2. Check `signal.aborted` before interceptor setup or `$call()`; throw the exact abort reason if already aborted.
3. Require callable documented `$wire.intercept` support.
4. Install one component-scoped interceptor for the exact method.
5. Add one AbortSignal listener for this invocation.
6. Start exact `$wire.$call(method, ...params)` synchronously with no asynchronous gap after interceptor installation.
7. In the first expected interceptor callback:
   - mark the callback logically captured so later invocations are ignored;
   - capture the exact action handle;
   - register `onSend` to set `dispatched = true`;
   - schedule physical unsubscribe after Livewire's current interceptor iteration.
8. If the signal aborts after action capture but before `dispatched`:
   - record caller-originated pre-dispatch cancellation;
   - invoke exact `action.cancel()` exactly once.
9. If the signal aborts after `dispatched`:
   - do not invoke any Livewire cancellation primitive.
10. Await the natural `$call()` promise.
11. If caller cancellation caused pre-dispatch action cancellation, surface the original signal reason regardless of Livewire's internal rejection shape.
12. Otherwise preserve the exact natural Livewire success value or rejection.
13. In all paths, remove the AbortSignal listener and defensively unsubscribe any remaining interceptor subscription.

## Race semantics

JavaScript run-to-completion is part of the design boundary.

```text
install interceptor
add abort listener
$call(...)
capture action
```

contains no intentional asynchronous yield.

The driver also rechecks already-aborted state itself even though WebMCP performs an outer gate. This defense-in-depth check preserves direct/future non-WebMCP driver correctness.

An abort event can then occur while the captured action is queued/deferred and before `onSend`; that is the cancellable window.

## Deferred Livewire actions

Livewire may defer a new same-scope action while another message is active.

This is a primary T-305 use case:

```text
existing Livewire request active
        ↓
SurfaceRelay action created/deferred
        ↓
caller aborts before deferred action fires
        ↓
exact action.cancel()
        ↓
action never intentionally dispatches
```

A focused test must cover deferred/queued cancellation rather than only immediate actions.

## Post-dispatch truth model

After `onSend`, caller cancellation and server/framework outcome are independent facts:

```text
caller/WebMCP observation
= cancelled / no longer waiting

framework/server outcome
= not inferred from caller cancellation
```

For direct driver execution after dispatch:

- Livewire success resolves the exact raw result;
- Livewire/server/network/application failure rejects the exact original error;
- a later caller abort does not replace either outcome.

The WebMCP host may independently stop observing/exposing an execution when its signal aborts. SurfaceRelay does not reinterpret that host behavior as proof of rollback.

## Explicit non-claims

T-305 never claims:

```text
server execution stopped
database transaction rolled back
external side effect reversed
queue work cancelled
request cancellation restored prior state
```

No such authority exists in this task.

## Error handling

T-304 descriptor/target errors remain unchanged.

T-305 may add one focused runtime compatibility code if implementation needs it, such as:

```text
livewire_cancellation_unavailable
```

It means only that the documented action-interceptor capability needed for cancellation-aware invocation is unavailable.

It does not mean stale binding, server failure, or rollback.

Caller-originated abort uses the original AbortSignal reason rather than a new `LivewireBindingExecutionError` code.

D-026 remains PROPOSED as a complete generic binding-failure vocabulary.

## Cleanup

Every cancellation-aware execution owns two temporary resources:

```text
component-method interceptor subscription
AbortSignal event listener
```

Cleanup is mandatory and idempotent on:

- natural success;
- natural failure;
- pre-dispatch cancellation;
- missing/capture-incompatible interceptor behavior;
- synchronous `$call()` initiation failure.

No execution-local interceptor or signal listener may remain after the driver's promise settles.

## Registration lifetime remains independent

T-303 registration lifetime and T-305 invocation cancellation remain different authorities:

```text
registration lease AbortSignal
→ controls browser tool registration lifetime

WebMCP execute options.signal
→ controls one invocation
```

Disposing a registration lease does not automatically cancel already-running business work unless the WebMCP host separately aborts that execution's signal.

D-037 remains unchanged.

## M4 interaction

Cancellation does not replace later trust controls:

```text
T-402 idempotency
→ duplicate/retry protection

T-404 structured audit
→ authoritative execution/final-state evidence
```

A cancelled caller without final-state evidence cannot infer whether a dispatched write occurred.

## Threat-model requirement

T-305 operationalizes T13.

Security property:

> Cancelling one SurfaceRelay action must not cancel broader unrelated Livewire work, and cancellation after dispatch must not be described as reversal of an effect that may already have happened.

Required negative test:

```text
SurfaceRelay action exists alongside unrelated Livewire work
        ↓
SurfaceRelay execution signal aborts
        ↓
no message.cancel()
no request.cancel()
no broad cancellation primitive
```

## Proposed decisions

Promote these to `ACCEPTED` only when implementation evidence exists.

### D-042 — Cancellation frontier

A SurfaceRelay execution has a strong no-dispatch cancellation guarantee only before framework dispatch begins. WebMCP provides a required execution AbortSignal, and an already-aborted invocation does not perform invocation-time driver resolution/execution. For Livewire, the exact action's documented `onSend` hook marks the dispatch frontier. Cancellation after that frontier does not imply server work stopped, rolled back, or reversed.

### D-043 — Granular Livewire cancellation only

The Livewire reference driver uses documented component-scoped action interception and exact `action.cancel()` only before `onSend`. It never uses message/request cancellation for generic SurfaceRelay action cancellation because those broader scopes may contain unrelated framework work. SurfaceRelay does not require `#[Async]`, `#[Isolate]`, or private Livewire request APIs to manufacture cancellability.

## TDD acceptance matrix

### WebMCP

1. `WebMcpToolExecuteOptions.signal` is required by TypeScript.
2. Registration-time driver support preflight remains unchanged.
3. Already-aborted execution prevents invocation-time driver lookup/execution.
4. Already-aborted execution preserves the exact abort reason.
5. Non-aborted signal is forwarded unchanged to `DriverExecutionContext`.

### Livewire compatibility

6. Narrow `$wire.intercept(method, callback)` shape is represented.
7. Missing callable interceptor fails before `$call()` when signal is present.
8. No-signal T-304 path remains compatible.

### Exact capture / isolation

9. Interceptor captures the exact component/method action created by the immediate `$call()`.
10. Logical one-shot guard ignores any later callback invocation.
11. Physical unsubscribe occurs after current interceptor iteration, not by mutating the Livewire interceptor array inside its callback.
12. An unrelated interceptor registered after SurfaceRelay's interceptor still runs.
13. A later same-method human invocation is not captured.

### Pre-dispatch

14. Already-aborted direct-driver signal performs no Livewire lookup/call.
15. Abort after action capture but before `onSend` invokes exact `action.cancel()` once.
16. Deferred/queued exact action can be cancelled before dispatch.
17. Pre-dispatch cancellation surfaces exact caller AbortSignal reason.
18. No request/message cancellation primitive is invoked.

### Dispatch frontier

19. `onSend` marks dispatch before subsequent abort handling.
20. Abort after `onSend` does not invoke `action.cancel()`.
21. Abort after `onSend` does not invoke request/message cancellation.

### Natural outcome

22. Post-dispatch abort followed by success resolves exact raw Livewire result for direct driver execution.
23. Post-dispatch abort followed by failure rejects exact original error.
24. No synthetic rollback or cancelled-success result is emitted.

### Cleanup

25. AbortSignal listener is removed on success, failure and pre-dispatch cancellation.
26. Interceptor cleanup is idempotent in all terminal paths.
27. T-303 registration signal remains independent from execution cancellation.

## Scope

### In scope

- required WebMCP execution-signal type;
- invocation-time already-aborted WebMCP gate;
- narrow Livewire action-interceptor compatibility port;
- exact one-shot action capture without interceptor-array mutation during callback iteration;
- exact pre-dispatch `action.cancel()`;
- `onSend` dispatch frontier;
- exact abort-reason preservation;
- post-dispatch natural-outcome preservation;
- cleanup/isolation tests;
- T13 documentation/tests;
- D-042/D-043 after implementation evidence.

### Out of scope

- request-level or message-level generic cancellation;
- claiming HTTP abort stops PHP;
- rollback/reversal semantics;
- forcing `#[Async]` or `#[Isolate]`;
- private Livewire action/message/request APIs;
- server cooperative cancellation protocol;
- queue-job cancellation;
- external compensation;
- confirmation receipts;
- idempotency persistence;
- structured audit/final-state evidence;
- Filament/HTMX cancellation behavior.

## Expected production touch points

Likely browser files:

```text
packages/browser-runtime/src/webmcp-types.ts
packages/browser-runtime/src/webmcp-registration-lifecycle.ts
packages/browser-runtime/src/livewire-browser-runtime.ts
packages/browser-runtime/src/livewire-browser-driver.ts
packages/browser-runtime/src/livewire-errors.ts   (only if compatibility code is needed)
```

Likely tests:

```text
packages/browser-runtime/tests/webmcp-types.typecheck.ts
packages/browser-runtime/tests/webmcp-registration-lifecycle.test.ts
packages/browser-runtime/tests/livewire-browser-runtime.test.ts
packages/browser-runtime/tests/livewire-browser-driver.test.ts
packages/browser-runtime/tests/livewire-webmcp-integration.test.ts
```

No Laravel PHP production change is expected unless implementation evidence reveals an independent server-side requirement.

## Completion criteria

T-305 is complete only when:

1. this written design is approved;
2. implementation follows TDD with meaningful RED evidence;
3. required WebMCP signal and invocation gate are proven;
4. exact Livewire pre-dispatch cancellation is proven;
5. post-dispatch no-broad-cancel/no-rollback behavior is proven;
6. interceptor iteration safety, same-method isolation and cleanup are proven;
7. existing T-301 through T-304 browser tests remain green;
8. PHP and contract baselines remain green;
9. D-042/D-043 are accepted only after behavior exists;
10. `spec/0.1` remains unchanged unless a separate contract issue is discovered;
11. external-style review passes;
12. T-401/M4 does not begin automatically.
