# T-602 — HTMX Browser Driver Design

## Status

- Task: `T-602 — HTMX browser driver`
- Milestone: `M6 — HTMX Portability Proof`
- Branch: `feat/htmx-browser-driver`
- Base: `main@536a89f7a7fe6fb10f4284203cffb5a63ecc0fb4`
- Design state: **APPROVED IN CHAT / WRITTEN SPEC UNDER REVIEW**
- Implementation state: **NOT STARTED**
- Existing descriptor decision: `D-053` — **ACCEPTED**
- Proposed execution decisions: `D-054`, `D-055`, `D-056`
- Portability decision: `D-020` — remains **PROPOSED** through T-604

T-602 is an architectural browser-runtime task. It consumes the exact HTMX RuntimeBinding descriptor established by T-601 and adds the smallest fail-closed browser execution driver that can invoke the existing human-facing HTMX request path without changing SurfaceRelay's frozen protocol-neutral contracts.

Implementation must not begin until this written spec is reviewed and approved.

## Objective

Implement a reference `BindingDriver` for `driver=htmx` that:

1. validates the existing T-601 descriptor at execution time;
2. resolves exactly one current rendered HTMX source instance;
3. verifies that the source's explicit declarative request method/path still matches the issued binding;
4. validates and deterministically maps Action input without allowing host HTMX modifiers to overwrite or remove caller Action values;
5. dispatches only through the host page's supported HTMX 2.x public `htmx.ajax()` API;
6. fails closed for stale, unsupported, ambiguous, busy, expired, or unmappable execution state;
7. preserves truthful cancellation and result semantics;
8. proves integration through the existing `DriverRegistry` and WebMCP registration lifecycle.

T-602 is not the end-to-end portability proof. T-603 provides the real non-Laravel HTMX fixture; T-604 provides shared Livewire/HTMX conformance.

## Existing contract compatibility

No frozen protocol contract change is required.

The existing browser contract is already sufficient:

```ts
interface BindingDriver {
  execute(
    binding: RuntimeBinding,
    input: Record<string, unknown>,
    context: DriverExecutionContext,
  ): Promise<unknown>;
}

interface DriverExecutionContext {
  signal?: AbortSignal;
}
```

T-602 must not modify:

```text
spec/0.1/**
packages/browser-runtime/src/types.ts
packages/browser-runtime/src/driver-registry.ts
packages/browser-runtime/src/webmcp-registration-lifecycle.ts
packages/laravel/src/**
```

The generic `DriverRegistry` remains responsible only for exact driver-name registration/lookup. HTMX-specific target, DOM, runtime, lifecycle, input, concurrency, and execution rules stay inside the HTMX adapter/driver.

## Proposed decision — D-054: HTMX execution boundary

Record during design/implementation as `PROPOSED`:

> The reference HTMX browser driver resolves exactly one current rendered source instance from the issued `sourceId`, verifies that the source still declares the binding's exact HTMX method and raw path, validates the same-origin boundary and named Action input mapping, and invokes only the host page's supported HTMX 2.x public `htmx.ajax()` API using that exact source. It does not use raw `fetch`, rediscover a similar target, silently retarget a changed source, synthesize a business response from returned HTML, or bundle a second HTMX runtime.

D-054 becomes `ACCEPTED` only after T-602 implementation and verification succeed.

## Proposed decision — D-055: HTMX concurrency and cancellation boundary

Record during design/implementation as `PROPOSED`:

> The reference HTMX driver does not use element-scoped `htmx:abort`, request replacement, request queuing, or other broad HTMX concurrency controls to manufacture SurfaceRelay cancellation semantics. An exact source that is already busy fails closed. SurfaceRelay provides a strong no-dispatch cancellation guarantee only while cancellation is observable before the `htmx.ajax()` invocation frontier. After that frontier, caller cancellation does not imply network cancellation, server cancellation, rollback, reversal, or suppression of the natural HTMX success/failure outcome.

D-055 becomes `ACCEPTED` only after T-602 implementation and verification succeed.

## Proposed decision — D-056: HTMX Action-input integrity

Record during design/implementation as `PROPOSED`:

> SurfaceRelay maps only allowlisted top-level Action input names into deterministic JSON-data-safe HTMX override values. Ordinary host form/request state remains host state and is not trusted authority. HTMX mechanisms that can overwrite, remove, evaluate, queue, confirm, prompt, invoke custom extension processing, or otherwise ambiguously transform SurfaceRelay Action values are unsupported on the reference source. Structured Action values remain under one top-level name and are JSON-string encoded rather than flattened into form path syntax. Standard host `hx-encoding` remains ordinary request encoding and is not rejected by this rule.

D-056 becomes `ACCEPTED` only after T-602 implementation and verification succeed.

## Alternatives considered

### A — Host HTMX runtime behind a narrow browser adapter — selected

`HtmxBrowserDriver` receives a narrow `HtmxBrowserRuntime` port. A `GlobalHtmxBrowserRuntime` implementation adapts the page's ambient HTMX/document/location surface.

Advantages:

- reuses the exact HTMX runtime already serving the human UI;
- prevents a second bundled HTMX instance;
- keeps browser compatibility checks out of driver semantics;
- mirrors the existing Livewire runtime/driver separation;
- allows unit testing without a DOM emulator or HTMX package dependency.

### B — Bundle/import `htmx.org` directly — rejected

This can create a runtime different from the one the host page uses and weakens the human/agent convergence claim.

### C — Raw `fetch()` plus manual swap — rejected

This creates a second HTTP/business execution path and bypasses host HTMX request/swap semantics.

### D — Trigger the source through `htmx.trigger()` — rejected

This depends on page-specific trigger configuration/event state, weakens exact input mapping, and makes invocation depend on host event conventions rather than the issued request descriptor.

## Architecture

```text
RuntimeBinding(driver=htmx)
        │
        ▼
HtmxBrowserDriver
        │
        ├── parse exact T-601 target
        ├── shared expiry validation
        ├── runtime compatibility validation
        ├── exact source resolution
        ├── source eligibility + drift checks
        ├── same-origin defense
        ├── deterministic Action-input mapping
        ├── busy gate
        └── pre-dispatch cancellation check
        │
        ▼
HtmxBrowserRuntime.ajax(...)
        │
        ▼
host HTMX 2.x `htmx.ajax()`
```

### Expected browser-runtime modules

```text
packages/browser-runtime/src/htmx-browser-runtime.ts
packages/browser-runtime/src/htmx-browser-driver.ts
packages/browser-runtime/src/htmx-errors.ts
packages/browser-runtime/src/htmx-input-mapping.ts
packages/browser-runtime/src/runtime-binding-expiry.ts
```

The existing `htmx-binding-descriptor.ts` remains the descriptor authority and should be reused rather than duplicated.

## Runtime abstraction

The runtime port should expose only the capabilities required by the driver. The exact TypeScript shape may be adjusted during the implementation plan, but the semantic boundary is:

```text
- identify/validate the ambient HTMX runtime and its version;
- resolve all exact SurfaceRelay source-identity matches;
- expose current page URL/origin;
- expose the configured HTMX request class needed for busy detection;
- invoke HTMX ajax with exact method/path/source/values.
```

The driver must not directly spread `window`, `document`, or arbitrary HTMX internals through production code.

### Ambient runtime compatibility

The reference driver supports HTMX **2.x only**.

Rules:

```text
missing HTMX/global/ajax capability   -> htmx_runtime_unavailable
malformed/unknown major version       -> htmx_runtime_unsupported
HTMX 1.x                              -> htmx_runtime_unsupported
HTMX 2.x                              -> supported
HTMX 4.x / beta                       -> htmx_runtime_unsupported
```

Capability detection alone is not enough because T-602 relies on HTMX 2.x request/value/concurrency semantics.

T-602 must not add `htmx.org` as a production or dev dependency.

## Exact source identity

The T-601 `sourceId` identifies one exact rendered source instance using a dedicated SurfaceRelay attribute:

```html
<button
  data-surfacerelay-htmx-source="htmx-src-..."
  hx-post="/items"
>
  Add
</button>
```

Resolution rules:

```text
0 exact matches  -> binding_stale
1 exact match    -> continue
2+ exact matches -> binding_stale
```

The runtime should avoid interpolating `sourceId` directly into a CSS value selector. It may enumerate `[data-surfacerelay-htmx-source]` candidates and compare exact attribute values.

The driver must never fall back to:

- class;
- DOM position;
- visible text;
- business record identity;
- endpoint similarity;
- another element with equivalent `hx-*` attributes;
- first-match behavior when identity is duplicated.

A replaced source requires a new `sourceId` and a new binding.

## Explicit request contract

The exact source must physically declare exactly one supported HTMX request attribute from this set:

```text
hx-get / data-hx-get
hx-post / data-hx-post
hx-put / data-hx-put
hx-patch / data-hx-patch
hx-delete / data-hx-delete
```

Exactly one physical declaration is required. Even two equivalent declarations such as both `hx-post` and `data-hx-post` are ambiguous for the SurfaceRelay reference contract and fail stale.

The driver compares:

```text
resolved method === binding.target.method
raw attribute path === binding.target.path
```

Path comparison is exact string comparison. The driver is not a URL canonicalizer:

```text
/foo != /foo/
/foo?a=1&b=2 != /foo?b=2&a=1
```

Missing declaration, multiple declarations, method drift, or path drift means the exact issued surface has changed and returns `binding_stale`.

T-602 does not support boosted-link discovery, native form `action/method` fallback, inherited generic HTTP semantics, HTMX 4 `hx-action`/`hx-method`, or custom verbs.

## Same-origin defense

T-601 already constrains paths to bounded absolute-path references. T-602 additionally resolves the path against the current page URL and verifies that the resulting origin equals the current page origin before dispatch.

This check protects the path SurfaceRelay passes to HTMX. It is not a sandbox around host JavaScript.

HTMX host hooks such as `htmx:configRequest` can mutate path, verb, parameters, or headers later in the host pipeline. T-602 does not attempt to reimplement or police the HTMX event system. Such host hooks remain host runtime behavior and never become SurfaceRelay authorization or binding authority.

## Host request state versus Action input

The reference driver intentionally reuses the existing HTMX source so normal human-facing request state can remain in the host request path, including ordinary form fields, hidden fields, cookies, headers, `hx-include`, request configuration, target/swap choices, and standard encoding choices.

Examples of host state that may travel with the request but never become SurfaceRelay authority:

```text
CSRF token
locale
view state
hidden host fields
cookies
headers
record/tenant values present in HTML
```

Server-side authentication, authorization, tenant resolution, trusted current-record/current-selection resolution, confirmation, and idempotency remain server/runtime responsibilities.

## Action input allowlist

The driver accepts only own-properties whose names appear in `target.inputNames`.

Rules:

- unknown own keys fail `binding_input_unmappable`;
- every `requiredInputNames` member must be an own property;
- inherited properties never satisfy required input;
- optional named inputs may be omitted independently;
- caller property order has no authority;
- no nested flattening occurs.

## Deterministic Action-value encoding

The reference driver maps Action values into deterministic form-compatible strings before handing them to HTMX.

Supported JSON-data domain:

```text
string        -> same string
finite number -> canonical JavaScript string representation
true/false    -> "true" / "false"
null          -> "null"
array         -> JSON string under one top-level name
plain object  -> JSON string under one top-level name
```

Example:

```json
{
  "filters": {
    "status": ["open", "paid"]
  }
}
```

maps to one named value:

```text
filters={"status":["open","paid"]}
```

The driver rejects values that would require lossy, host-dependent, executable, binary, or non-JSON coercion, including:

```text
undefined
NaN
Infinity / -Infinity
BigInt
Symbol
Function
Date
Map
Set
Blob / File
custom class instances
cyclic values
nested undefined or non-finite numbers
```

Implementation must validate recursively before calling `JSON.stringify()` so JavaScript's silent `undefined` removal, `NaN -> null`, custom `toJSON`, or similar coercions cannot change Action meaning.

## Action-input integrity versus HTMX modifiers

HTMX 2.x merges request values in an order where SurfaceRelay `ajax(..., { values })` can later be overwritten by `hx-vars`/`hx-vals` and filtered by `hx-params`. The reference driver therefore fails closed when known declarative mechanisms can ambiguously change SurfaceRelay Action values or dispatch semantics.

### Unsupported source/ancestor mechanisms

The reference source is unsupported when the source or its ancestor chain contains any effective or conservatively detected variant of:

```text
hx-vals / data-hx-vals
hx-vars / data-hx-vars
hx-confirm / data-hx-confirm
hx-prompt / data-hx-prompt
hx-sync / data-hx-sync
hx-indicator / data-hx-indicator
hx-ext / data-hx-ext
```

For `hx-params`:

```text
absent          -> allowed
"*"             -> allowed
anything else   -> unsupported
```

A conservative ancestor scan is acceptable even when an HTMX inheritance override/disinherit rule might make an ancestor attribute ineffective. T-602 is not an HTMX inheritance interpreter; ambiguous cases fail closed.

### Allowed host modifiers

T-602 may leave normal host behavior intact for mechanisms that do not redefine SurfaceRelay Action identity, including:

```text
ordinary form fields / hidden fields
hx-include
hx-headers
hx-request
hx-target
hx-swap
hx-encoding
```

These remain untrusted host request state.

## Browser validation boundary

HTMX may resolve `htmx.ajax()` without sending a request when validation halts execution. The reference source therefore must not rely on active HTMX/browser validation semantics that can turn an invocation into a silent no-dispatch success.

Unsupported cases include:

- exact source is a `FORM` with normal validation active;
- exact source declares `hx-validate="true"` / `data-hx-validate="true"`.

A non-form source such as a button inside a form remains supportable: HTMX may collect the related form for a non-GET request without the source itself becoming the form-validation trigger.

The driver should use structural DOM checks such as `tagName` rather than requiring global browser constructors like `HTMLFormElement` in unit tests.

## Busy-source and concurrency boundary

HTMX can queue or suppress requests when a source is already processing another request. `htmx.ajax()` may resolve before a queued request later executes, which is not a truthful `BindingDriver.execute()` completion model.

The reference driver therefore rejects a busy exact source before dispatch.

Busy detection uses the host HTMX runtime's configured request class. Because `hx-indicator` can move request state to another element, `hx-indicator` is unsupported as described above.

Required flow:

```text
resolve exact source
  -> source eligibility checks
  -> validate runtime requestClass
  -> source currently has requestClass?
       yes -> htmx_source_busy
       no  -> continue
```

No asynchronous gap should be introduced between the final busy/cancellation checks and `htmx.ajax()` invocation.

The reference driver does not use `hx-sync` strategies, queue replacement, or `htmx:abort` to coordinate with unrelated human/framework requests.

## Cancellation frontier

Cancellation truth table:

```text
before driver invocation / already aborted
  -> surface exact caller abort reason
  -> no runtime lookup or HTMX ajax

before HTMX dispatch, after validation
  -> surface exact caller abort reason
  -> no HTMX ajax

htmx.ajax() invoked
  -> dispatch frontier crossed

caller aborts after frontier
  -> do not call htmx:abort
  -> do not race/replace the HTMX promise
  -> preserve natural HTMX success/failure
```

T-602 does not claim that post-frontier cancellation stops the network, server execution, side effects, swaps, rollback, or reversal.

## Result semantics

HTMX 2.x exposes `htmx.ajax()` as `Promise<void>`. The reference driver preserves that shape.

Successful execution resolves `undefined`. It does not:

- parse returned HTML into a business result;
- read private XHR internals to invent an Action result;
- synthesize JSON from the DOM;
- claim that a successful promise proves a server-side business mutation occurred.

A natural underlying HTMX/runtime rejection is propagated as the exact original error rather than wrapped in a generic request-failed error.

Known reference-source mechanisms that can cause silent no-dispatch resolution (confirmation, prompt, validation, sync/queue/busy behavior) are excluded before dispatch where practical. Arbitrary host event handlers remain host behavior under the host-hook boundary.

## Shared RuntimeBinding expiry extraction

The existing Livewire driver contains strict RFC3339 parsing, clock injection, and expiry classification. T-602 must not duplicate that logic.

Extract the framework-neutral portion into:

```text
packages/browser-runtime/src/runtime-binding-expiry.ts
```

The shared helper owns:

- injected browser clock abstraction;
- strict RFC3339 date-time parsing;
- `null`/absent = no expiry;
- equality with `now` = expired;
- invalid date-time classification distinct from expired.

Each driver maps the shared result into its own execution error class.

This is an internal refactor only. Existing Livewire expiry behavior and tests must remain green.

## HTMX execution errors

Add a driver-local error class with this minimum code set:

```text
binding_stale
binding_expired
binding_target_invalid
binding_input_unmappable
htmx_runtime_unavailable
htmx_runtime_unsupported
htmx_source_unsupported
htmx_source_busy
```

Semantics:

### `binding_target_invalid`

Malformed/wrong-driver/wrong-lifecycle descriptor, malformed expiry, or another invalid execution descriptor condition.

T-601 `HtmxBindingDescriptorError` should be normalized at the browser execution boundary rather than leaked as a construction-time descriptor error.

### `binding_stale`

Exact source missing/duplicated, explicit request declaration missing/ambiguous, or method/path drift.

### `binding_expired`

Shared expiry state is expired.

### `binding_input_unmappable`

Unknown/missing Action input or unsupported/non-JSON Action value.

### `htmx_runtime_unavailable`

Ambient HTMX/document/location/callable public surface is absent.

### `htmx_runtime_unsupported`

Runtime exists but its version/configuration cannot satisfy the HTMX 2.x reference boundary.

### `htmx_source_unsupported`

Exact source uses excluded HTMX mechanisms such as value mutation/filtering, confirmation/prompt, sync, indicator relocation, extension behavior, or active validation.

### `htmx_source_busy`

Exact binding is still valid but the exact source is transiently processing another HTMX request. This is not a stale binding.

## Test architecture

T-602 keeps the existing browser-runtime unit-test style: narrow runtime fakes plus structural DOM-like fakes. It does not add a DOM emulator, Playwright, Puppeteer, or HTMX dependency.

Real DOM + real HTMX + server execution belongs to T-603.

### New tests

```text
packages/browser-runtime/tests/htmx-browser-runtime.test.ts
packages/browser-runtime/tests/htmx-browser-runtime.typecheck.ts
packages/browser-runtime/tests/htmx-browser-driver.test.ts
packages/browser-runtime/tests/htmx-input-mapping.test.ts
packages/browser-runtime/tests/htmx-cancellation.test.ts
packages/browser-runtime/tests/htmx-webmcp-integration.test.ts
packages/browser-runtime/tests/runtime-binding-expiry.test.ts
```

### Runtime compatibility matrix

Prove at minimum:

- HTMX missing;
- non-callable `ajax`;
- malformed/missing version;
- 1.x rejected;
- 2.x accepted;
- 4.x rejected;
- exact source enumeration/equality behavior;
- request-class exposure;
- exact `ajax()` delegation.

### Exact source/request matrix

Prove at minimum:

- 0/1/2+ source matches;
- no replacement-source retargeting;
- all five methods;
- `hx-*` and `data-hx-*` physical forms;
- duplicate equivalent physical declaration rejected;
- method drift;
- path drift;
- trailing-slash/query-order drift;
- no endpoint/path canonicalization.

### Source eligibility negative proofs

Prove all excluded mechanisms fail before `ajax()`:

- `hx-vals` / `hx-vars`;
- restrictive `hx-params`;
- `hx-confirm`;
- `hx-prompt`;
- `hx-sync`;
- `hx-indicator`;
- `hx-ext`;
- active form/source validation;
- conservative ancestor cases.

Also prove allowed host state does not become SurfaceRelay authority or change the explicit Action-value record passed to HTMX.

### Input mapping matrix

Prove supported scalar/array/plain-object JSON values and reject the full non-JSON/lossy set, including nested invalid values and prototype/inherited-property edge cases.

### Busy/cancellation matrix

Prove:

- busy source => `htmx_source_busy`, no `ajax()`;
- already-aborted direct invocation => exact abort reason, no runtime resolution/dispatch;
- pre-frontier abort => no dispatch;
- post-frontier abort => no `htmx:abort`, natural HTMX result wins;
- natural HTMX rejection identity is preserved.

### WebMCP integration proof

Prove the existing pipeline dispatches an HTMX binding without generic changes:

```text
ActionDefinition
  + RuntimeBinding(driver=htmx)
    -> WebMcpRegistrationLifecycle
    -> DriverRegistry
    -> HtmxBrowserDriver
    -> exact source/runtime.ajax()
```

Also prove an old binding is not retargeted to a replacement source.

This is not T-604 shared conformance.

## Expected implementation surface

Production/refactor files:

```text
packages/browser-runtime/src/htmx-browser-runtime.ts
packages/browser-runtime/src/htmx-browser-driver.ts
packages/browser-runtime/src/htmx-errors.ts
packages/browser-runtime/src/htmx-input-mapping.ts
packages/browser-runtime/src/runtime-binding-expiry.ts
packages/browser-runtime/src/livewire-browser-driver.ts   # expiry-only refactor
```

Existing files expected to remain semantically unchanged:

```text
packages/browser-runtime/src/htmx-binding-descriptor.ts
packages/browser-runtime/src/types.ts
packages/browser-runtime/src/driver-registry.ts
packages/browser-runtime/src/webmcp-registration-lifecycle.ts
```

No new `src/index.ts` barrel is required; the repository does not currently use one.

Dependency files should remain unchanged:

```text
packages/browser-runtime/package.json
packages/browser-runtime/package-lock.json
```

unless implementation discovers an unavoidable contradiction requiring a new design gate.

## Explicitly out of scope

T-602 must not add:

- HTMX 4/beta compatibility;
- HTMX npm/runtime dependency;
- raw `fetch()` execution;
- generic HTTP driver;
- browser automation dependency;
- DOM emulator dependency;
- fixture application/server;
- Laravel production coupling;
- HTMX-generated confirmation authority;
- idempotency behavior;
- response HTML -> ActionResult synthesis;
- generic HTMX inheritance/event sandbox;
- request retry policy;
- shared Livewire/HTMX conformance runner;
- `spec/0.1/**` changes;
- promotion of D-020 to `ACCEPTED`.

## Acceptance criteria

T-602 implementation is acceptable only when all of the following are proven:

1. `HtmxBrowserDriver` implements the existing `BindingDriver` interface without core type changes.
2. Only the exact T-601 `driver=htmx`, `lifecycle=page` descriptor is executable.
3. HTMX reference execution supports only compatible 2.x host runtimes.
4. No HTMX package is bundled or installed for the driver.
5. Exactly one current `sourceId` match is required.
6. Similar/replacement sources are never rediscovered or substituted.
7. Exactly one explicit supported physical HTMX request attribute is required.
8. Exact method/raw-path drift fails stale.
9. Same-origin is checked before dispatch.
10. Unknown/missing caller Action keys fail closed.
11. Action values are recursively validated as deterministic JSON data.
12. Structured values remain one top-level JSON-string-encoded parameter; no flattening occurs.
13. Ordinary host form/request state is preserved only as untrusted host state.
14. Known HTMX mechanisms that can ambiguously overwrite/filter/evaluate/coordinate/confirm/prompt/relocate/extend the request are rejected by the reference driver.
15. Busy exact sources fail `htmx_source_busy`; no queue/replace/abort policy is manufactured.
16. Already-aborted and pre-frontier cancellation perform no HTMX dispatch.
17. Post-frontier cancellation does not call `htmx:abort` or replace the natural HTMX result.
18. Underlying HTMX rejection identity is preserved.
19. Successful result remains `void`; no business output is synthesized from HTML/XHR/DOM state.
20. Shared expiry extraction preserves all existing Livewire expiry behavior.
21. WebMCP -> registry -> HTMX driver integration is executable in tests.
22. Existing Livewire browser/cancellation/integration regressions remain green.
23. Full browser typecheck and Vitest suite pass without deleting prior tests.
24. Repository contract/PHP/lint CI remains green.
25. `spec/0.1/**`, Laravel production source, and protocol-neutral browser contracts remain unchanged.
26. T-603/T-604 work is not pulled into T-602.
27. D-054/D-055/D-056 are promoted only after verified implementation; D-020 remains proposed through T-604.

## Verification gate

At implementation completion, minimum verification is:

```text
cd packages/browser-runtime
npm run typecheck
npm test

# repository root
python scripts/validate.py
```

Full CI must remain green, including the PHP compatibility matrix and PHP lint. The pre-T-602 browser baseline is 174/174 Vitest tests; T-602 adds tests and must not achieve green by deleting or weakening prior coverage.

## Design-review handoff

This document is the implementation boundary, not proof of implemented behavior.

Current state after committing this spec:

```text
T-602 design:          approved in chat
Written spec:          under user review
Implementation plan:  not written
Implementation:       not started
D-054/055/056:         proposed
D-020:                 proposed
```

After written-spec approval, the next step is to write the implementation plan. No production code should be changed before that gate.