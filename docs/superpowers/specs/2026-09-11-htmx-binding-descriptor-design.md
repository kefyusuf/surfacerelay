# T-601 — HTMX Binding Descriptor Design

## Status

- Task: `T-601 — Explicit HTMX binding descriptor`
- Milestone: `M6 — HTMX Portability Proof`
- Branch: `feat/htmx-binding-descriptor`
- Base: `main@5b22eef928d2fb1f8fac8ab13507e2f22661d3df`
- Design state: **APPROVED IN CHAT / WRITTEN SPEC UNDER REVIEW**
- Implementation state: **NOT STARTED**
- Proposed decision: `D-053`

T-601 is an architectural task. This document defines the driver-owned HTMX RuntimeBinding target contract only. It does not implement DOM resolution, HTMX request execution, cancellation, a fixture application, or shared Livewire/HTMX conformance.

## Objective

Define the smallest explicit HTMX RuntimeBinding descriptor that can later support an exact, fail-closed browser driver without changing SurfaceRelay's frozen protocol-neutral contracts.

The descriptor must prove that a second materially different binding can fit the existing RuntimeBinding model:

```text
ActionDefinition
    │
    │ framework-neutral meaning
    ▼
RuntimeBinding
    ├── driver = htmx
    ├── lifecycle = page
    └── target = HTMX-owned descriptor
```

T-601 succeeds when the browser-runtime package can construct and validate the HTMX target as pure data, with exhaustive negative tests, while importing no HTMX runtime and touching no Laravel production code or frozen `spec/0.1/**` contract.

## Existing contract compatibility

No frozen contract change is required.

The existing RuntimeBinding contract already provides all required extension points:

- `driver` is an extensible identifier rather than a closed enum;
- the contract explicitly allows drivers such as `livewire`, `htmx`, `liveview`, and `http`;
- `target` is explicitly driver-owned data;
- lifecycle is one of `page`, `component`, `session`, or `persistent`;
- unknown drivers fail closed;
- binding identity and target identity remain separate from ActionDefinition semantics.

Therefore T-601 must not modify:

```text
spec/0.1/runtime-binding.schema.json
spec/0.1/action-definition.schema.json
spec/0.1/fixtures/**
```

D-009 also remains intact: T-601 does not promote an HTMX target shape into a public cross-framework protocol contract. It is a reference-driver contract inside the implementation repository. Public portability claims remain gated on T-604 shared conformance.

## Proposed decision — D-053

Record the following as `PROPOSED` during T-601 design/implementation:

> HTMX RuntimeBindings use `driver=htmx` with `page` lifecycle and an explicit driver-owned target that pins one opaque rendered source-element identity, one same-origin HTMX request method/path, and an allowlisted named Action-input mapping. The binding carries no trusted actor/tenant/record/selection authority and no confirmation/idempotency capability. Execution must resolve the exact source instance, verify that its request contract still matches the binding, and fail stale rather than rediscovering or silently retargeting a replacement element.

D-053 may become `ACCEPTED` only after the pure descriptor contract and its negative tests are implemented and verified. D-020 remains `PROPOSED` until the wider HTMX portability milestone proves the second binding through T-604.

## Why the descriptor belongs in browser-runtime

T-601 must not add HTMX-specific production code to `packages/laravel`.

The second binding is specifically intended to test portability outside Laravel. A Laravel-side `HtmxRuntimeBinding` factory would make the portability proof depend on the first framework runtime before the second fixture exists.

The narrow implementation location is therefore:

```text
packages/browser-runtime/src/htmx-binding-descriptor.ts
```

This module is pure data construction/validation. It must not access:

- `window`;
- `document`;
- DOM APIs;
- global `htmx`;
- `htmx.ajax()`;
- `fetch()`;
- network state;
- cookies or browser storage.

T-602 will consume the descriptor in the browser execution driver. T-603 will show that a non-Laravel server can issue matching RuntimeBinding JSON without depending on this TypeScript implementation.

## Alternatives considered

### A — Exact source element + pinned request descriptor — selected

Bind one rendered HTMX source instance plus its exact method/path and named Action-input plan.

Advantages:

- preserves exact-target/stale semantics from D-022/D-025;
- gives T-602 a deterministic execution contract;
- allows the future driver to reuse the real human-facing HTMX source context instead of inventing a raw fetch path;
- does not make DOM selectors, record IDs, tenant IDs, or request parameters into authority;
- remains small enough to prove portability rather than building a generic HTTP client.

### B — Custom HTMX event descriptor — rejected

Binding only an element/event name and executing through `htmx.trigger()` would couple Action input to page-specific event handlers or executable `hx-vals` logic. It also provides a weaker request/result boundary and makes the reference contract depend on host JavaScript conventions.

### C — Pure method/path HTTP descriptor — rejected

Binding only method/path would be simpler, but it would detach agent execution from the existing human-facing HTMX source instance. The browser runtime could accidentally become a second HTTP business path with weaker stale-target semantics.

## RuntimeBinding shape

A T-601 binding has these fixed driver-level properties:

```json
{
  "bindingId": "binding-q7Y...",
  "action": {
    "id": "prep_list.add_item",
    "version": 1
  },
  "driver": "htmx",
  "lifecycle": "page",
  "target": {
    "sourceId": "htmx-src-q7Y...",
    "method": "POST",
    "path": "/prep-list/items",
    "inputNames": ["item"],
    "requiredInputNames": ["item"]
  },
  "expiresAt": null
}
```

T-601 owns only the `target` descriptor rules. Generic RuntimeBinding identity, action reference, expiry, extensions, revocation, and lifecycle semantics remain the existing contract.

## Target object exactness

The HTMX target contains exactly these five fields:

```text
sourceId
method
path
inputNames
requiredInputNames
```

Unknown target fields fail descriptor validation.

This exactness is intentional. Driver-owned target extensions must not be smuggled into the first portability proof. If later HTMX work genuinely needs additional target semantics, that requires a separate design decision rather than an open-ended target bag.

## `sourceId` semantics

`sourceId` identifies one exact rendered HTMX source element instance.

It is:

- fresh when that rendered source instance is issued;
- opaque to Action semantics;
- not a business record ID;
- not a DOM `id` requirement;
- not authorization proof;
- not a secret/capability;
- never reused for a materially replaced source element.

The future host fixture is expected to emit it in a dedicated SurfaceRelay-owned data attribute, for example:

```html
<button
    data-surfacerelay-htmx-source="htmx-src-q7Y..."
    hx-post="/prep-list/items"
>
    Add
</button>
```

Reference grammar for T-601:

```text
1..240 ASCII characters
first character: [A-Za-z0-9]
remaining: [A-Za-z0-9._:-]
```

The grammar exists to make exact browser lookup deterministic and avoid selector/control-character ambiguity. It does not make the value authoritative.

T-602 must resolve exactly this identity and must not fall back to:

- CSS class;
- DOM position;
- visible text;
- `hx-*` similarity;
- endpoint similarity;
- a replacement element with the same business record;
- the first matching element.

Missing exact source means stale binding.

## Lifecycle

HTMX reference bindings use:

```text
lifecycle = page
```

The reason is structural: HTMX does not provide a framework component-instance identity equivalent to a Livewire component ID. The binding is valid only within one runtime-defined browser page/surface authority, subject to stricter target existence checks.

`page` lifecycle is a maximum validity boundary, not permission for retargeting within the page.

A source element may disappear while the page remains alive. In that case the binding is stale under D-024/D-025. A newly rendered source element receives a different `sourceId` and requires a new binding.

## HTTP method contract

T-601 supports exactly:

```text
GET
POST
PUT
PATCH
DELETE
```

Rules:

- method matching is exact and uppercase;
- no normalization from arbitrary caller strings;
- no `HEAD`, `OPTIONS`, `CONNECT`, `TRACE`, custom methods, or method override in T-601;
- the method is trusted descriptor data authored by the binding producer, not caller Action input.

This is intentionally narrower than generic HTTP. M6 is proving an HTMX binding, not designing a complete HTTP transport abstraction.

## Path contract

`path` is a same-origin absolute-path reference, optionally including a query string.

Examples accepted by the intended descriptor validator:

```text
/prep-list/items
/orders/refund?view=table
/
```

It must:

- be a non-empty string;
- begin with exactly one `/`;
- not begin with `//`;
- contain no URL scheme/host authority;
- contain no fragment (`#...`);
- contain no backslash;
- contain no ASCII control characters;
- be bounded to a reasonable implementation limit of 2048 characters.

T-601 does not resolve the path against a browser location. T-602 performs the runtime same-origin check before execution as defense-in-depth.

The path is not authorization. Server-side authentication, tenant resolution, CSRF policy, and application authorization remain host/runtime responsibilities.

## Action input mapping

HTMX request values are name-based, unlike Livewire's positional PHP method invocation. Therefore T-601 does not reuse `inputOrder`/`requiredCount`.

The target carries two lists:

```text
inputNames
requiredInputNames
```

Semantics:

- both are lists of unique, non-empty strings;
- `requiredInputNames` must be a subset of `inputNames`;
- list order has no invocation-authority meaning;
- caller object property order has no meaning;
- T-602 will permit only caller Action-input keys present in `inputNames`;
- T-602 will require all names in `requiredInputNames`;
- optional named inputs may be omitted independently because HTMX values are not positional arguments.

### Producer-side derivation from ActionDefinition

The reference T-601 builder derives these sets from the exact ActionDefinition instead of accepting arbitrary mapping lists from caller data.

The reference builder supports only a closed top-level object input schema:

```json
{
  "type": "object",
  "properties": {
    "item": {"type": "string"},
    "note": {"type": "string"}
  },
  "required": ["item"],
  "additionalProperties": false
}
```

For that schema:

```text
inputNames = exact set(properties.keys)
requiredInputNames = exact set(required)
```

The descriptor builder fails closed when the Action input schema cannot produce a finite named mapping, including:

- non-object top-level schema;
- missing/non-object `properties` when caller fields are possible;
- `additionalProperties` not explicitly `false`;
- `patternProperties` or other open-ended top-level caller-key mechanisms;
- non-list `required`;
- duplicate/non-string required names;
- a required name not present in `properties`.

Nested values remain normal JSON values under one top-level name. T-601 does not flatten nested objects into form-style dotted/bracket paths.

This restriction is reference-runtime policy, not a change to ActionDefinition's frozen JSON Schema freedom. An ActionDefinition may be valid while being intentionally unsupported by the HTMX reference descriptor.

## Input values versus ordinary HTMX form state

T-601 describes only the Action-input values that SurfaceRelay is allowed to map into the future HTMX execution call.

It does not attempt to snapshot or promote ordinary source/form data such as:

```text
_token
_csrf
order_id
tenant_id
filter
hidden host fields
cookies
headers
```

Those values may exist in an ordinary human HTMX request. If T-602 later uses the exact source element as HTMX request context, such host values remain ordinary request data and remain untrusted from SurfaceRelay's perspective.

In particular, hidden `tenant_id` or `order_id` values do not become trusted tenant/current-record authority merely because they originate from the exact HTMX source/form.

The server must still resolve and authorize trusted state independently.

## Trusted authority boundary

An HTMX target descriptor must never contain or manufacture:

- authenticated actor identity;
- tenant authority;
- trusted roles/permissions;
- current record authority;
- current selection authority;
- human confirmation authority;
- confirmation receipt/challenge capability;
- raw idempotency key;
- browser-session authority;
- authorization decisions.

These remain runtime/server concerns under the existing SurfaceRelay model.

A binding target tells the browser driver **where/how the existing human-facing HTMX interaction is addressed**. It does not authorize the resulting business action.

## Human/agent convergence

The intended M6 convergence model is:

```text
Human click/submit
    │
    ▼
exact HTMX source element
    │
    └── existing hx-* request contract

Agent invocation
    │
    ▼
RuntimeBinding(driver=htmx)
    │
    ▼
exact same sourceId
    │
    ▼
T-602 verifies same method/path
    │
    ▼
htmx.ajax(... source = exact element ...)
```

T-601 does not implement the right-hand execution flow. It only makes the future flow explicit enough that T-602 cannot invent an alternate raw-fetch or endpoint-discovery path.

## Method/path drift and stale behavior

T-602 is required to compare the exact resolved source element's effective request method/path with the descriptor before dispatch.

If the same `sourceId` is found but its application request contract no longer matches the descriptor, execution fails closed. It must not silently update the binding to the new method/path.

T-601 records this requirement but does not inspect DOM attributes itself.

This gives T-602 two independent stale checks:

1. exact source identity still exists;
2. exact source request contract still matches the issued descriptor.

UI-only HTMX presentation attributes such as target/swap styling are not frozen by T-601. The first portability proof pins the business request endpoint, not every visual rendering choice.

## No HTMX dependency in T-601

T-601 must not add `htmx.org` or any browser HTMX package dependency.

The descriptor is pure data and can be tested without a DOM or HTMX runtime. Introducing HTMX now would blur the T-601/T-602 boundary and make descriptor tests unnecessarily integration-heavy.

HTMX runtime compatibility belongs to T-602.

## No network or cancellation behavior in T-601

T-601 explicitly does not define:

- request dispatch;
- response swapping;
- returned response/result semantics;
- cancellation behavior;
- the dispatch frontier;
- request abort support;
- retry policy;
- confirmation handling;
- idempotency handling.

T-602 must separately design browser execution and cancellation truthfully against the HTMX API. Existing Livewire cancellation decisions D-042/D-043 must not be mechanically copied if HTMX exposes different guarantees.

## Error boundary

T-601 failures are descriptor construction/validation failures, not public ActionResult outcomes.

The pure descriptor builder should fail synchronously with a driver-local construction error. It must not add new frozen ActionError codes or mutate D-026 during T-601.

T-602 will own browser execution error mapping such as malformed target, stale source, unsupported runtime, and expiry behavior.

## Immutability and defensive copying

The T-601 descriptor API must not retain mutable caller arrays by reference.

At construction:

- copy `inputNames`;
- copy `requiredInputNames`;
- validate before exposing a descriptor;
- return readonly/frozen TypeScript-facing data where practical.

Mutating arrays supplied to the constructor after descriptor creation must not change the descriptor.

This mirrors the existing immutable RuntimeBinding design intent.

## Expected implementation surface

Expected T-601 production/test files:

```text
packages/browser-runtime/src/htmx-binding-descriptor.ts
packages/browser-runtime/src/index.ts
packages/browser-runtime/tests/htmx-binding-descriptor.test.ts
```

Tracking/design files:

```text
docs/superpowers/specs/2026-09-11-htmx-binding-descriptor-design.md
docs/DECISION-REGISTER.md
TASKS.md
STATUS.md
REVIEW_REQUEST.md   # only when implementation reaches review-prep
```

Explicitly unexpected during T-601:

```text
packages/laravel/src/**
packages/browser-runtime/src/htmx-browser-driver.ts
examples/htmx/**
spec/0.1/**
```

No dependency upgrade or unrelated browser-runtime refactor is part of this task.

If implementing the descriptor appears to require a frozen schema change, Laravel production code, DOM execution, or an HTMX dependency, stop and reopen the design gate instead of broadening T-601 silently.

## Required TDD proofs

The later implementation plan must begin with RED tests. Minimum descriptor coverage:

### Valid descriptor proofs

- exact `driver=htmx`, `lifecycle=page` shape is representable;
- supported methods are accepted;
- `/` and normal same-origin absolute paths are accepted;
- query string is accepted;
- zero-input Action produces empty input/required lists;
- required + optional named inputs derive correctly from a closed object Action schema;
- nested property values do not get flattened;
- caller array mutation after construction does not mutate the descriptor.

### Negative descriptor proofs

- empty/malformed/too-long `sourceId` rejected;
- unsupported/lowercase/arbitrary method rejected;
- empty/relative/scheme-relative/cross-origin-style/fragment/backslash/control-character/too-long path rejected;
- target with missing/extra keys rejected at parsing/validation boundary;
- duplicate/empty/non-string input names rejected;
- duplicate/empty/non-string required names rejected;
- required name outside input names rejected;
- non-object/open-ended Action input schema fails descriptor issuance;
- `additionalProperties` other than explicit `false` fails descriptor issuance;
- `patternProperties`/other open-ended top-level key semantics fail descriptor issuance;
- caller-provided input mapping is never used instead of ActionDefinition-derived mapping.

### Scope proofs

- no DOM globals referenced;
- no HTMX runtime imported;
- no Laravel package dependency/reference introduced;
- `spec/0.1/**` unchanged.

## Acceptance criteria

T-601 is complete only when all of the following hold:

1. A pure HTMX driver-owned descriptor exists in browser-runtime.
2. The descriptor fixes `driver=htmx` and is designed only for `page` lifecycle.
3. Target shape is exactly `sourceId`, `method`, `path`, `inputNames`, `requiredInputNames`.
4. `sourceId` represents one exact rendered source instance and is never business or authorization identity.
5. Supported request methods are the fixed five-method HTMX reference subset.
6. Path validation admits only bounded same-origin absolute-path references and rejects scheme/authority/fragment/backslash/control ambiguity.
7. Input mapping is named and independent from caller/schema property order.
8. The reference mapping is derived from the exact ActionDefinition closed top-level object schema.
9. Unsupported/open-ended Action input schemas fail descriptor issuance rather than guessing a mapping.
10. Required names are a validated subset of all input names.
11. Nested Action input values remain nested; no form-path flattening convention is invented.
12. Ordinary source/form request values never become trusted SurfaceRelay context.
13. Descriptor data contains no actor, tenant, record, selection, confirmation, idempotency, or authorization authority.
14. Descriptor validation is pure and synchronous with defensive copying.
15. No DOM/HTMX runtime/network/cancellation logic is added.
16. No Laravel production code changes.
17. No frozen `spec/0.1/**` changes.
18. D-053 is promoted only after executable descriptor tests pass; D-020 remains proposed.
19. Full browser-runtime tests/typecheck and repository contract validation pass.
20. Full diff review finds no accidental T-602/T-603/T-604 scope creep.

## Verification gate

Expected implementation verification:

```bash
cd packages/browser-runtime
npm test -- --run
npm run typecheck
cd ../..
python scripts/validate.py
```

Repository CI remains the final matrix gate.

The implementation plan should use focused RED/GREEN commits before the full workflow. Exact command names may be adjusted to the package scripts that exist at implementation time, but validation scope may not be weakened.

## Follow-on task boundaries

### T-602 — HTMX browser driver

May add:

- exact source lookup;
- method/path revalidation;
- `htmx.ajax()` compatibility port;
- named Action-input mapping at execution;
- expiry/stale handling;
- truthful cancellation/dispatch-frontier behavior;
- browser execution errors.

Must not redesign T-601 target semantics without reopening D-053.

### T-603 — Non-Laravel HTMX fixture app

Will prove a non-Laravel host can:

- issue exact RuntimeBinding JSON;
- emit fresh source IDs;
- expose a real human HTMX interaction;
- execute through the same application operation/authorization boundary.

The fixture must not need Laravel code to issue the descriptor.

### T-604 — Shared conformance against Livewire + HTMX

Will determine whether the second binding is materially portable enough to support the wider project claim. D-020 should remain proposed until that evidence exists.

## Design conclusion

T-601 should be deliberately small: one explicit HTMX target descriptor, pure validation, ActionDefinition-derived named input planning, and strong negative proofs.

The key portability claim is not that HTMX looks like Livewire. It is that the existing generic RuntimeBinding envelope can carry a materially different driver-owned target without contaminating ActionDefinition semantics or weakening exact-target/trusted-authority rules.

Implementation must stop before browser execution. If T-601 remains pure, T-602 can later answer the harder HTMX runtime questions without mixing them into the descriptor contract.