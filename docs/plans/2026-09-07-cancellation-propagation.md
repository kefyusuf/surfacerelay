# T-305 Cancellation Propagation Implementation Plan

**Goal:** Propagate caller cancellation through the existing WebMCP → DriverRegistry → Livewire execution path up to the exact pre-dispatch Livewire action boundary, without cancelling broader Livewire work or implying rollback after dispatch.

**Architecture:** Tighten the WebMCP execution callback to require `AbortSignal`, add an invocation-time already-aborted gate before driver resolution, extend the Livewire compatibility port with the minimal documented component-scoped action interceptor, and make `LivewireBrowserDriver` cancel only the exact captured action before its `onSend` frontier. Post-dispatch abort preserves the framework's natural result/error and never calls action/message/request cancellation.

**Tech Stack:** TypeScript 5.9, Vitest 3.2, WebMCP compatibility types, Livewire 4.4 documented browser APIs, existing SurfaceRelay DriverRegistry and browser-runtime package.

**Spec:** `docs/design/cancellation-propagation.md`

## Global Constraints

- `spec/0.1` remains frozen and unchanged.
- `DriverExecutionContext.signal` remains optional; only WebMCP execution options require a signal.
- Registration lifetime and execution cancellation remain separate authorities.
- Use only documented component-scoped Livewire action interception and `action.cancel()`.
- Never use generic `message.cancel()` or `request.cancel()` for SurfaceRelay action cancellation.
- Never require `#[Async]`, `#[Isolate]`, private `fireAction`, request internals, or agent-only business methods.
- Preserve T-304 exact target, expiry, input mapping, stale handling, and raw result/error behavior.
- A strong cancellation claim is limited to pre-`onSend`: no rollback/reversal/server-stop claim after dispatch.
- Interceptor callbacks are logical one-shot immediately, but physical unsubscribe must not mutate Livewire's interceptor array during its current iteration.
- If abort is observed after the signal listener is installed but before SurfaceRelay captures the action (for example, an earlier synchronous Livewire interceptor aborts the controller), record the abort and cancel the exact action immediately when it is captured, provided `onSend` has not fired.
- Public tracked content remains implementation/tool neutral.

---

## File map

### Existing files to modify

- `packages/browser-runtime/src/webmcp-types.ts` — make the WebMCP execution signal required.
- `packages/browser-runtime/src/webmcp-registration-lifecycle.ts` — invocation-time already-aborted gate before driver lookup.
- `packages/browser-runtime/src/livewire-browser-runtime.ts` — minimal documented action-interceptor compatibility types.
- `packages/browser-runtime/src/livewire-browser-driver.ts` — phase-aware exact-action cancellation and cleanup.
- `packages/browser-runtime/src/livewire-errors.ts` — only if a focused `livewire_cancellation_unavailable` compatibility code is needed.
- `packages/browser-runtime/tests/webmcp-types.typecheck.ts` — compile-time required-signal contract.
- `packages/browser-runtime/tests/webmcp-registration-lifecycle.test.ts` — generic abort-gate behavior.
- `packages/browser-runtime/tests/livewire-browser-runtime.test.ts` — compatibility surface behavior.
- `packages/browser-runtime/tests/livewire-browser-driver.test.ts` — exact-action cancellation/frontier/race/cleanup matrix.
- `packages/browser-runtime/tests/livewire-webmcp-integration.test.ts` — full WebMCP → registry → Livewire cancellation proof.
- `docs/THREAT-MODEL.md` — tighten T13 only after behavior is implemented.
- `docs/DECISION-REGISTER.md`, `TASKS.md`, `STATUS.md`, `REVIEW_REQUEST.md` — update only after implementation evidence.

### No expected PHP production changes

T-305 is browser-runtime work. If a PHP production change appears necessary during implementation, stop and re-evaluate scope rather than leaking a server protocol into this task.

---

## Task 1 — WebMCP required execution signal and already-aborted gate

**Files:**
- Modify: `packages/browser-runtime/src/webmcp-types.ts`
- Modify: `packages/browser-runtime/src/webmcp-registration-lifecycle.ts`
- Modify: `packages/browser-runtime/tests/webmcp-types.typecheck.ts`
- Modify: `packages/browser-runtime/tests/webmcp-registration-lifecycle.test.ts`

**Consumes:** existing `WebMcpTool`, `DriverRegistry.requireDriver()`, `BindingDriver.execute()`.

**Produces:**

```ts
export interface WebMcpToolExecuteOptions {
  signal: AbortSignal;
}
```

and an invocation-time gate with this semantic:

```text
if options.signal.aborted
→ reject/throw options.signal.reason
→ do not call invocation-time DriverRegistry.requireDriver()
→ do not execute a driver
```

Registration-time preflight remains unchanged.

- [ ] **Step 1: Add compile-time RED for required signal**

Extend `webmcp-types.typecheck.ts` with a valid call and one intentionally invalid no-signal call:

```ts
declare const webTool: WebMcpTool;

const signal = new AbortController().signal;
void webTool.execute({}, { signal });

// @ts-expect-error WebMCP execution requires AbortSignal.
void webTool.execute({}, {});
```

With the current optional type, TypeScript must fail because the `@ts-expect-error` is unused.

- [ ] **Step 2: Add runtime RED for already-aborted execution**

In `webmcp-registration-lifecycle.test.ts`, register a normal tool, then spy on `registry.requireDriver` only after registration preflight has completed:

```ts
const lease = await lifecycle.register([value]);
const requireDriver = vi.spyOn(registry, 'requireDriver');
const reason = new DOMException('stopped', 'AbortError');
const controller = new AbortController();
controller.abort(reason);

await expect(context.calls[0].tool.execute({}, {
  signal: controller.signal,
})).rejects.toBe(reason);

expect(requireDriver).not.toHaveBeenCalled();
expect(execute).not.toHaveBeenCalled();
lease.dispose();
```

Also retain the existing non-aborted test proving the exact execution signal is forwarded unchanged.

- [ ] **Step 3: Run focused RED**

Run through CI/browser job or local equivalent:

```bash
cd packages/browser-runtime
npm run typecheck
npm test -- --run tests/webmcp-registration-lifecycle.test.ts
```

Expected: typecheck fails on unused `@ts-expect-error`; runtime test fails because invocation currently resolves/executes the driver.

- [ ] **Step 4: Implement minimal GREEN**

Change `WebMcpToolExecuteOptions.signal` from optional to required.

In the registered tool callback, check before invocation-time driver resolution:

```ts
if (options.signal.aborted) {
  throw options.signal.reason;
}

const driver = this.drivers.requireDriver(candidate.binding.driver);
return driver.execute(candidate.binding, input, {
  signal: options.signal,
});
```

Do not alter registration-time `requireDriver()` preflight.

- [ ] **Step 5: Run focused GREEN and full browser suite**

Expected: TypeScript contract and lifecycle tests pass; existing T-303 signal-distinction behavior remains green.

- [ ] **Step 6: Commit Task 1**

Suggested commit:

```text
feat(browser): gate aborted WebMCP executions
```

---

## Task 2 — Narrow Livewire action-interceptor compatibility port

**Files:**
- Modify: `packages/browser-runtime/src/livewire-browser-runtime.ts`
- Modify: `packages/browser-runtime/tests/livewire-browser-runtime.test.ts`
- Modify: `packages/browser-runtime/src/livewire-errors.ts` only if the focused compatibility code is introduced here.

**Produces:** minimal public compatibility types, not a mirror of all Livewire internals:

```ts
export interface LivewireActionHandle {
  cancel(): void;
}

export interface LivewireActionInterceptorContext {
  action: LivewireActionHandle;
  onSend(callback: () => void): void;
}

export interface LivewireWire {
  readonly $id: string;
  $call(method: string, ...params: unknown[]): Promise<unknown>;
  intercept?(
    method: string,
    callback: (context: LivewireActionInterceptorContext) => void,
  ): () => void;
}
```

Keep `intercept` optional at the ambient compatibility type level so T-304's no-signal path can still model a runtime lacking cancellation support; the driver decides when its absence is fatal.

- [ ] **Step 1: Write compatibility RED tests**

Extend `livewire-browser-runtime.test.ts` with a fake ambient `$wire` exposing `intercept` and assert `GlobalLivewireBrowserRuntime.find()` preserves the callable surface without wrapping/rewriting it.

Add a type-focused test fixture or compile assertion that an interceptor callback receives `action.cancel()` and `onSend()`.

- [ ] **Step 2: Run focused RED**

Expected: compile/test failure because `LivewireWire` does not yet expose interceptor types.

- [ ] **Step 3: Implement minimal types**

Add only `LivewireActionHandle`, `LivewireActionInterceptorContext`, and optional `LivewireWire.intercept`.

Do not add message/request interceptor types or broad cancel functions.

- [ ] **Step 4: Run focused GREEN**

Expected: runtime adapter behavior remains identical; new type surface compiles.

- [ ] **Step 5: Commit Task 2**

Suggested commit:

```text
feat(browser): add Livewire action interceptor port
```

---

## Task 3 — Exact pre-dispatch Livewire cancellation

**Files:**
- Modify: `packages/browser-runtime/src/livewire-browser-driver.ts`
- Modify: `packages/browser-runtime/src/livewire-errors.ts`
- Modify: `packages/browser-runtime/tests/livewire-browser-driver.test.ts`

**Consumes:** T-304 descriptor/expiry/input/exact-target pipeline plus Task 2 interceptor port.

**Produces:** cancellation-aware execution with a hard `onSend` dispatch frontier.

### Private execution state

Keep it local to one `execute()` call:

```ts
let action: LivewireActionHandle | undefined;
let captured = false;
let dispatched = false;
let callerAbortedPreDispatch = false;
let actionCancelled = false;
let unsubscribe: (() => void) | undefined;
```

No new public cancellation lifecycle enum is required.

- [ ] **Step 1: Add RED — direct already-aborted signal**

Use a real `AbortController`, abort with an exact `DOMException`, and prove no `find()` or `$call()` occurs.

- [ ] **Step 2: Add RED — cancellation-aware runtime without `intercept`**

With a non-aborted signal and a `$wire` that has `$call` but no callable `intercept`, expect `livewire_cancellation_unavailable` before `$call()`. No-signal execution remains T-304 compatible.

- [ ] **Step 3: Build a deterministic documented-surface action harness in tests**

The fake `$wire` models only documented action interception, synchronous interceptor iteration, an exact action handle, `onSend`, and a deferred `$call()` promise. Tests explicitly choose whether the action remains queued, crosses `onSend`, succeeds, or fails.

- [ ] **Step 4: Add RED — pre-dispatch exact cancellation**

Abort after capture but before `onSend`; assert exact `action.cancel()` once and exact caller reason, even if fake Livewire rejects with a different internal cancellation object.

- [ ] **Step 5: Add RED — abort observed before SurfaceRelay action capture**

An earlier fake Livewire interceptor synchronously aborts the controller before SurfaceRelay's interceptor runs. SurfaceRelay must record the abort, then cancel the exact action immediately upon capture if it has not crossed `onSend`.

- [ ] **Step 6: Add RED — deferred/queued action**

Keep the captured action pre-`onSend`, abort, assert exact action cancellation and no send.

- [ ] **Step 7: Add RED — interceptor iteration safety**

Register SurfaceRelay's interceptor before another unrelated interceptor. Capturing the action must not synchronously splice the interceptor array; the unrelated interceptor still runs. Physical unsubscribe occurs after current iteration, while a local guard makes SurfaceRelay logical one-shot immediately.

- [ ] **Step 8: Add RED — later same-method invocation isolation**

After cleanup, trigger another same-method invocation and prove SurfaceRelay's execution-local interceptor cannot capture or cancel it.

- [ ] **Step 9: Add RED — hard post-dispatch frontier**

Cross `onSend`, abort, and prove `action.cancel()` is not invoked. Then separately prove natural success returns the exact raw result and natural failure returns the exact original error.

- [ ] **Step 10: Add RED — cleanup on all terminal paths**

Cover success, natural failure, pre-dispatch cancellation, synchronous `$call()` initiation failure, and claimed interceptor support that fails to capture the exact action. Verify abort listener and interceptor cleanup are idempotent.

- [ ] **Step 11: Implement minimal cancellation-aware branch**

Retain existing no-signal T-304 execution unchanged. For signal-aware execution: already-aborted check, require `intercept`, install exact-method interceptor + abort listener, synchronously initiate `$call`, track `onSend`, cancel exact action only pre-dispatch, normalize caller-originated pre-dispatch cancellation to exact signal reason, preserve post-dispatch natural outcome, and always cleanup.

- [ ] **Step 12: Run focused GREEN and full browser suite**

Expected: all cancellation tests plus existing 90 browser tests pass.

- [ ] **Step 13: Commit Task 3**

Suggested commit:

```text
feat(browser): cancel exact Livewire actions pre-dispatch
```

---

## Task 4 — End-to-end cancellation proof, threat model, decisions, review checkpoint

**Files:**
- Modify: `packages/browser-runtime/tests/livewire-webmcp-integration.test.ts`
- Modify: `docs/THREAT-MODEL.md`
- Modify: `docs/DECISION-REGISTER.md`
- Modify: `TASKS.md`
- Modify: `STATUS.md`
- Modify: `REVIEW_REQUEST.md`

- [ ] **Step 1: Add WebMCP → registry → Livewire pre-dispatch integration proof**

Use real `WebMcpRegistrationLifecycle`, `DriverRegistry`, and `LivewireBrowserDriver` with a documented-surface fake Livewire runtime. Abort pre-dispatch and prove exact action cancellation + exact abort reason while registration lease signal stays independent.

- [ ] **Step 2: Add post-dispatch truth integration proof**

Cross `onSend`, abort, then resolve/reject Livewire and prove no broad cancellation and natural framework outcome preservation.

- [ ] **Step 3: Add T13 negative security proof**

Model unrelated broader Livewire work in the harness; abort SurfaceRelay execution; assert zero request/message broad-cancel calls without adding those methods to the production compatibility port.

- [ ] **Step 4: Run full repository verification**

Required: contract validator, four PHP matrix cells, PHP lint, browser TypeScript typecheck, browser Vitest.

- [ ] **Step 5: Update T13 mitigation after evidence**

Document exact pre-dispatch action cancellation, no generic broad cancellation, and no post-dispatch rollback claim.

- [ ] **Step 6: Promote D-042/D-043 only after green evidence**

D-042: hard no-dispatch guarantee ends at exact action `onSend`; already-aborted WebMCP execution does not reach invocation-time driver lookup/execution.

D-043: Livewire uses only documented component-scoped exact `action.cancel()` pre-dispatch; no generic request/message cancellation, forced async/isolation, or private APIs.

- [ ] **Step 7: Update `TASKS.md`, `STATUS.md`, `REVIEW_REQUEST.md` with exact evidence**

Record RED/GREEN commit SHAs, workflow IDs, final counts. Do not start M4.

- [ ] **Step 8: External-style diff review**

Verify no `spec/0.1`, PHP production, request/message cancellation, private Livewire import, forced `#[Async]`/`#[Isolate]`, M4 leakage, or synthetic rollback result.

- [ ] **Step 9: Fresh CI on exact review checkpoint**

Only mark T-305 review passed after all seven jobs succeed on the exact review SHA.

- [ ] **Step 10: Present branch integration choice**

Do not merge automatically.

---

## TDD checkpoint strategy

```text
Task 1 RED   → WebMCP signal/gate
Task 1 GREEN → required signal + outer gate
Task 2 RED   → missing Livewire interceptor port
Task 2 GREEN → narrow documented port
Task 3 RED   → cancellation/frontier/race/isolation
Task 3 GREEN → exact pre-dispatch action cancellation
Task 4 proof → T-303/T-304/T-305 integration
review docs  → D-042/D-043 + source of truth
fresh CI     → review decision
```

Do not manufacture failures. Characterization tests that already pass should be recorded as characterization rather than forcing production changes.

## Self-review checklist

- Spec coverage includes required WebMCP signal, invocation gate, exact action capture, synchronous prior-interceptor abort race, logical-vs-physical one-shot cleanup, deferred cancellation, `onSend` frontier, post-dispatch natural outcome, no broad cancellation, registration-signal independence, T13, D-042/D-043.
- Scope remains browser runtime; no planned PHP production or frozen-spec change.
- Types stay consistent: WebMCP signal required, generic driver signal optional, Livewire interceptor capability optional at compatibility boundary but required for signal-aware execution.
- No placeholders; every behavior change has RED, implementation, GREEN, and commit step.
