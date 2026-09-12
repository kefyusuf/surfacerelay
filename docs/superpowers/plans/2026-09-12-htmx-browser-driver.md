# T-602 HTMX Browser Driver Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement a fail-closed HTMX 2.x browser `BindingDriver` that executes the exact T-601 RuntimeBinding through the host page's existing `htmx.ajax()` path while preserving exact-source identity, deterministic Action-input mapping, honest busy/cancellation semantics, shared expiry behavior, and existing WebMCP/DriverRegistry contracts.

**Architecture:** Keep protocol-neutral browser interfaces unchanged. Add a narrow ambient HTMX runtime adapter, HTMX-local execution error taxonomy, deterministic JSON-data input mapper, and `HtmxBrowserDriver`; extract strict RuntimeBinding expiry classification from the Livewire driver into one shared browser-runtime helper. Unit tests use small structural DOM/runtime fakes only. Real DOM + real HTMX + server behavior belongs to T-603.

**Tech Stack:** TypeScript 5.9, Vitest 3.2, existing DOM typings in browser-runtime, existing SurfaceRelay browser runtime/WebMCP types, Python contract validator, GitHub Actions `validate` workflow.

**Spec:** `docs/superpowers/specs/2026-09-12-htmx-browser-driver-design.md`

## Global Constraints

- Branch: `feat/htmx-browser-driver`.
- Main/base entering T-602: `main@536a89f7a7fe6fb10f4284203cffb5a63ecc0fb4`.
- Approved design checkpoint: `42cca940af35b3dff918a641b619d578f839047d`, CI `34682081839` — 7/7 green.
- Browser baseline before T-602: TypeScript typecheck + **174/174 Vitest**.
- D-053 is already `ACCEPTED`; do not change T-601 descriptor semantics.
- D-054, D-055, D-056 stay `PROPOSED` until complete T-602 verification passes.
- D-020 stays `PROPOSED` through T-604.
- Do not change `BindingDriver`, `DriverExecutionContext`, `RuntimeBinding`, `DriverRegistry`, or `WebMcpRegistrationLifecycle`.
- Reference runtime support is HTMX **2.x only**.
- Do not add `htmx.org`, jsdom, happy-dom, Playwright, Puppeteer, or another dependency.
- Use the host page's ambient HTMX instance; never create a second HTMX execution path.
- Resolve exactly one `data-surfacerelay-htmx-source`; zero or multiple exact matches fail stale.
- Require exactly one physical request attribute from `hx-get|post|put|patch|delete` or `data-hx-*`.
- Test **all 10 physical forms**: five verbs under `hx-*` and the same five under `data-hx-*`.
- Method and raw path comparisons are exact; no URL/query/trailing-slash normalization.
- Same-origin defense happens before SurfaceRelay calls HTMX; host `htmx:configRequest` hooks remain host behavior.
- Map only allowlisted own Action-input names; required names must be own properties.
- Structured values stay under one top-level Action name and are JSON-string encoded; no dotted/bracket flattening.
- Reject lossy/executable/non-JSON values including `undefined`, non-finite numbers, BigInt, Symbol, Function, Date, Map, Set, Blob/File, custom instances, accessors, sparse arrays, cycles, and nested invalid values.
- Reject reference-source behavior that can ambiguously mutate/filter/coordinate/confirm/prompt/relocate/extend execution: `hx-vals`, `hx-vars`, restrictive `hx-params`, `hx-confirm`, `hx-prompt`, `hx-sync`, `hx-indicator`, `hx-ext`, and active source validation.
- Ordinary host form/request state remains untrusted host state; `hx-include`, `hx-headers`, `hx-request`, `hx-target`, `hx-swap`, and standard `hx-encoding` remain allowed.
- A busy exact source fails `htmx_source_busy`; never queue, replace, or broadly abort HTMX work.
- Strong no-dispatch cancellation exists only before the synchronous `htmx.ajax()` invocation frontier.
- Do not call `htmx:abort`, race the HTMX promise against the caller signal, or claim post-frontier rollback/reversal.
- Preserve the exact underlying HTMX rejection and `Promise<void>` success semantics.
- No production changes under `packages/laravel/src/**`.
- No changes under `spec/0.1/**`.
- T-603 fixture work and T-604 shared conformance are out of scope.
- Stop at the external-review gate after implementation; do not automatically create/merge a PR or start T-603.

## File Structure

### Create

```text
packages/browser-runtime/src/runtime-binding-expiry.ts
packages/browser-runtime/src/htmx-errors.ts
packages/browser-runtime/src/htmx-browser-runtime.ts
packages/browser-runtime/src/htmx-input-mapping.ts
packages/browser-runtime/src/htmx-browser-driver.ts

packages/browser-runtime/tests/runtime-binding-expiry.test.ts
packages/browser-runtime/tests/htmx-browser-runtime.test.ts
packages/browser-runtime/tests/htmx-browser-runtime.typecheck.ts
packages/browser-runtime/tests/htmx-input-mapping.test.ts
packages/browser-runtime/tests/htmx-browser-driver.test.ts
packages/browser-runtime/tests/htmx-cancellation.test.ts
packages/browser-runtime/tests/htmx-webmcp-integration.test.ts
```

### Modify

```text
packages/browser-runtime/src/livewire-browser-driver.ts   # expiry extraction only
docs/DECISION-REGISTER.md
TASKS.md
STATUS.md
REVIEW_REQUEST.md                                        # review-prep only
```

### Must remain unchanged

```text
packages/browser-runtime/src/htmx-binding-descriptor.ts
packages/browser-runtime/src/types.ts
packages/browser-runtime/src/driver-registry.ts
packages/browser-runtime/src/webmcp-registration-lifecycle.ts
packages/browser-runtime/package.json
packages/browser-runtime/package-lock.json
packages/laravel/src/**
spec/0.1/**
examples/htmx/**
```

---

## Task 1: Shared RuntimeBinding Expiry Extraction

**Files:**
- Create: `packages/browser-runtime/tests/runtime-binding-expiry.test.ts`
- Create: `packages/browser-runtime/src/runtime-binding-expiry.ts`
- Modify: `packages/browser-runtime/src/livewire-browser-driver.ts`
- Regression: `packages/browser-runtime/tests/livewire-browser-driver.test.ts`
- Regression: `packages/browser-runtime/tests/livewire-cancellation.test.ts`

**Interfaces:**

```ts
export interface BrowserClock {
  now(): Date;
}

export const systemBrowserClock: BrowserClock;

export type RuntimeBindingExpiryState = 'active' | 'expired' | 'invalid';

export function classifyRuntimeBindingExpiry(
  expiresAt: unknown,
  now: Date,
): RuntimeBindingExpiryState;
```

- [ ] **Step 1: Write RED expiry tests**

Create `runtime-binding-expiry.test.ts` with these exact classes of proof:

```ts
const now = new Date('2026-09-12T00:00:00.000Z');

expect(classifyRuntimeBindingExpiry(null, now)).toBe('active');
expect(classifyRuntimeBindingExpiry(undefined, now)).toBe('active');
expect(classifyRuntimeBindingExpiry('2026-09-12T00:00:00Z', now)).toBe('expired');
expect(classifyRuntimeBindingExpiry('2026-09-11T23:59:59Z', now)).toBe('expired');
expect(classifyRuntimeBindingExpiry('2026-09-12T00:00:00.001Z', now)).toBe('active');

for (const invalid of [
  123,
  {},
  'not-a-date',
  '0000-01-01T00:00:00Z',
  '2026-02-30T00:00:00Z',
  '2026-09-12T24:00:00Z',
  '2026-09-12T00:60:00Z',
  '2026-09-12T00:00:60Z',
  '2026-09-12T00:00:00+24:00',
  '2026-09-12T00:00:00+03:60',
]) {
  expect(classifyRuntimeBindingExpiry(invalid, now)).toBe('invalid');
}
```

Also assert `systemBrowserClock.now()` returns a `Date`.

- [ ] **Step 2: Run RED**

```bash
cd packages/browser-runtime
npm test -- tests/runtime-binding-expiry.test.ts
```

Expected: FAIL because the shared module does not exist.

- [ ] **Step 3: Commit RED**

```bash
git add packages/browser-runtime/tests/runtime-binding-expiry.test.ts
git commit -m "test(browser): define shared binding expiry contract"
```

- [ ] **Step 4: Implement the shared classifier by extracting Livewire behavior unchanged**

Use the current Livewire RFC3339 regex/date checks verbatim. The public boundary must be:

```ts
export interface BrowserClock {
  now(): Date;
}

export const systemBrowserClock: BrowserClock = {
  now: () => new Date(),
};

export type RuntimeBindingExpiryState = 'active' | 'expired' | 'invalid';

export function classifyRuntimeBindingExpiry(
  expiresAt: unknown,
  now: Date,
): RuntimeBindingExpiryState {
  if (expiresAt === undefined || expiresAt === null) return 'active';
  if (typeof expiresAt !== 'string') return 'invalid';

  const parsed = parseRfc3339Millis(expiresAt);
  if (parsed === null) return 'invalid';
  return parsed <= now.getTime() ? 'expired' : 'active';
}
```

Do not alter current leap-date, fractional-second, offset, or equality semantics during extraction.

- [ ] **Step 5: Refactor LivewireBrowserDriver to use the shared classifier**

Remove its local clock/parser helpers and import:

```ts
import {
  classifyRuntimeBindingExpiry,
  systemBrowserClock,
  type BrowserClock,
} from './runtime-binding-expiry.js';
```

Map states exactly:

```ts
const expiry = classifyRuntimeBindingExpiry(binding.expiresAt, this.clock.now());
if (expiry === 'invalid') {
  throw executionError(
    'binding_target_invalid',
    'Livewire binding expiresAt must be a valid RFC3339 date-time string, null, or absent.',
  );
}
if (expiry === 'expired') {
  throw executionError('binding_expired', 'Livewire binding has expired.');
}
```

No other Livewire behavior changes in this task.

- [ ] **Step 6: Run GREEN + Livewire regressions**

```bash
npm test -- \
  tests/runtime-binding-expiry.test.ts \
  tests/livewire-browser-driver.test.ts \
  tests/livewire-cancellation.test.ts \
  tests/livewire-webmcp-integration.test.ts
npm run typecheck
```

Expected: PASS.

- [ ] **Step 7: Commit GREEN**

```bash
git add \
  packages/browser-runtime/src/runtime-binding-expiry.ts \
  packages/browser-runtime/src/livewire-browser-driver.ts \
  packages/browser-runtime/tests/runtime-binding-expiry.test.ts
git commit -m "refactor(browser): share runtime binding expiry checks"
```

---

## Task 2: HTMX Error Taxonomy and Ambient Runtime Adapter

**Files:**
- Create: `packages/browser-runtime/src/htmx-errors.ts`
- Create: `packages/browser-runtime/src/htmx-browser-runtime.ts`
- Create: `packages/browser-runtime/tests/htmx-browser-runtime.test.ts`
- Create: `packages/browser-runtime/tests/htmx-browser-runtime.typecheck.ts`

**Interfaces:**

```ts
export type HtmxBindingExecutionErrorCode =
  | 'binding_stale'
  | 'binding_expired'
  | 'binding_target_invalid'
  | 'binding_input_unmappable'
  | 'htmx_runtime_unavailable'
  | 'htmx_runtime_unsupported'
  | 'htmx_source_unsupported'
  | 'htmx_source_busy';

export type HtmxAjaxMethod = 'get' | 'post' | 'put' | 'patch' | 'delete';

export interface HtmxSourceElement {
  readonly tagName: string;
  readonly parentElement: HtmxSourceElement | null;
  readonly classList: { contains(token: string): boolean };
  hasAttribute(name: string): boolean;
  getAttribute(name: string): string | null;
}

export interface HtmxBrowserRuntime {
  assertSupported(): void;
  findSources(sourceId: string): readonly HtmxSourceElement[];
  currentLocation(): { readonly href: string; readonly origin: string };
  requestClass(): string;
  ajax(
    method: HtmxAjaxMethod,
    path: string,
    context: {
      readonly source: HtmxSourceElement;
      readonly values: Readonly<Record<string, string>>;
    },
  ): Promise<void>;
}
```

- [ ] **Step 1: Write RED runtime tests**

Prove:

```ts
// versions
for (const version of ['2.0.0', '2.0.10', '2.7.3', '2.1.0-beta.1']) {
  // assertSupported() => no throw
}
for (const version of ['', '1.9.12', '4.0.0-beta.1', '2', '2.x', 'garbage']) {
  // => htmx_runtime_unsupported
}

// missing global / non-callable ajax => htmx_runtime_unavailable
// requestClass '', whitespace, or multi-token => htmx_runtime_unsupported
// exact source lookup returns all equal sourceId matches
// ajax delegates method/path/source/values exactly once
```

Use a fake document that exposes only:

```ts
querySelectorAll('[data-surfacerelay-htmx-source]')
```

and then exact-string filter candidate attributes; do not interpolate sourceId into a CSS selector.

- [ ] **Step 2: Run RED and commit**

```bash
npm test -- tests/htmx-browser-runtime.test.ts
git add packages/browser-runtime/tests/htmx-browser-runtime.test.ts
git commit -m "test(htmx): define browser runtime compatibility boundary"
```

Expected test result before implementation: FAIL because runtime/error modules do not exist.

- [ ] **Step 3: Implement `htmx-errors.ts`**

```ts
export class HtmxBindingExecutionError extends Error {
  constructor(
    public readonly code: HtmxBindingExecutionErrorCode,
    message: string,
  ) {
    super(message);
    this.name = 'HtmxBindingExecutionError';
  }
}
```

with the exact code union defined above.

- [ ] **Step 4: Implement `GlobalHtmxBrowserRuntime`**

Use a narrow injectable ambient root:

```ts
export interface HtmxAmbientRoot {
  htmx?: {
    version?: unknown;
    config?: { requestClass?: unknown } | null;
    ajax?: unknown;
  };
  document?: {
    querySelectorAll(selector: string): ArrayLike<HtmxSourceElement>;
  };
  location?: { href?: unknown; origin?: unknown };
}
```

Compatibility rules:

```ts
const VERSION_PATTERN = /^(\d+)\.(\d+)\.(\d+)(?:[-+][0-9A-Za-z.-]+)?$/;

// assertSupported():
// - htmx object + callable ajax required
// - version must match VERSION_PATTERN and major === 2
// - document.querySelectorAll required
// - location.href and location.origin required
// - requestClass must be one non-empty whitespace-free class token
```

Source lookup:

```ts
const candidates = Array.from(
  document.querySelectorAll('[data-surfacerelay-htmx-source]'),
);
return Object.freeze(
  candidates.filter(
    (candidate) => candidate.getAttribute('data-surfacerelay-htmx-source') === sourceId,
  ),
);
```

Ajax delegation must be exactly:

```ts
return ajax.call(htmx, method, path, {
  source: context.source,
  values: context.values,
});
```

Do not expose `trigger`, private request state, XHR handles, or abort methods.

- [ ] **Step 5: Add typecheck proof**

`htmx-browser-runtime.typecheck.ts` must prove all public structural interfaces compile and that:

```ts
// @ts-expect-error unsupported method
const invalidMethod: HtmxAjaxMethod = 'head';
```

is a real type error.

- [ ] **Step 6: Run GREEN and commit**

```bash
npm test -- tests/htmx-browser-runtime.test.ts
npm run typecheck

git add \
  packages/browser-runtime/src/htmx-errors.ts \
  packages/browser-runtime/src/htmx-browser-runtime.ts \
  packages/browser-runtime/tests/htmx-browser-runtime.test.ts \
  packages/browser-runtime/tests/htmx-browser-runtime.typecheck.ts
git commit -m "feat(htmx): add browser runtime adapter"
```

---

## Task 3: Deterministic Action-Input Mapping

**Files:**
- Create: `packages/browser-runtime/src/htmx-input-mapping.ts`
- Create: `packages/browser-runtime/tests/htmx-input-mapping.test.ts`

**Interface:**

```ts
export function mapHtmxActionInput(
  target: HtmxBindingTarget,
  input: Record<string, unknown>,
): Readonly<Record<string, string>>;
```

All failures use `HtmxBindingExecutionError('binding_input_unmappable', ...)`.

- [ ] **Step 1: Write RED mapping matrix**

Positive expected mapping:

```ts
expect(mapHtmxActionInput(target, {
  item: 'coffee',
  count: 2.5,
  enabled: false,
  nothing: null,
  tags: ['hot', 'drink'],
  filters: { status: ['open', 'paid'] },
})).toEqual({
  item: 'coffee',
  count: '2.5',
  enabled: 'false',
  nothing: 'null',
  tags: '["hot","drink"]',
  filters: '{"status":["open","paid"]}',
});
```

Negative matrix must directly cover:

```text
missing required own property
unknown own property
inherited required property
symbol top-level key
undefined
NaN / +Infinity / -Infinity
BigInt
Symbol value
Function
Date
Map
Set
Blob (Node 22 global)
custom class instance
accessor property
sparse array
array with extra own property
object/array cycle
nested undefined
nested non-finite number
```

The returned mapping must be frozen.

- [ ] **Step 2: Run RED and commit**

```bash
npm test -- tests/htmx-input-mapping.test.ts
git add packages/browser-runtime/tests/htmx-input-mapping.test.ts
git commit -m "test(htmx): define action input mapping contract"
```

Expected before implementation: FAIL because mapper does not exist.

- [ ] **Step 3: Implement recursive JSON-data validation**

Use these exact rules:

```ts
function hasOwn(value: object, key: PropertyKey): boolean {
  return Object.prototype.hasOwnProperty.call(value, key);
}

function assertDataProperty(owner: object, key: string): unknown {
  const descriptor = Object.getOwnPropertyDescriptor(owner, key);
  if (!descriptor || !descriptor.enumerable || !('value' in descriptor)) {
    throw mappingError(`HTMX Action value property "${key}" is not a plain enumerable data property.`);
  }
  return descriptor.value;
}
```

Validation algorithm:

```text
null/string/boolean -> accepted
number -> accepted only if Number.isFinite
undefined/bigint/symbol/function -> rejected
array -> dense only; no symbol/extra own keys; every index is enumerable data; recurse
object -> prototype must be Object.prototype or null; no symbols/accessors; recurse
recursion stack -> reject cycles, but allow repeated non-cyclic shared references
```

Encoding:

```ts
if (typeof value === 'string') return value;
if (value === null) return 'null';
if (typeof value === 'number' || typeof value === 'boolean') return String(value);
return JSON.stringify(value); // only after recursive validation
```

Top-level input iteration uses `Reflect.ownKeys(input)`, rejects symbol/accessor/non-enumerable entries, rejects unknown names, and checks every `requiredInputNames` member with `hasOwn`.

- [ ] **Step 4: Run GREEN and commit**

```bash
npm test -- tests/htmx-input-mapping.test.ts
npm run typecheck

git add \
  packages/browser-runtime/src/htmx-input-mapping.ts \
  packages/browser-runtime/tests/htmx-input-mapping.test.ts
git commit -m "feat(htmx): map deterministic action input values"
```

---

## Task 4: Exact-Source HTMX Browser Driver Core

**Files:**
- Create: `packages/browser-runtime/src/htmx-browser-driver.ts`
- Create: `packages/browser-runtime/tests/htmx-browser-driver.test.ts`

**Interface:**

```ts
export class HtmxBrowserDriver implements BindingDriver {
  constructor(runtime: HtmxBrowserRuntime, clock?: BrowserClock);
  execute(
    binding: RuntimeBinding,
    input: Record<string, unknown>,
    context: DriverExecutionContext,
  ): Promise<unknown>;
}
```

- [ ] **Step 1: Write RED driver foundation tests**

Create reusable `FakeSource` and `FakeRuntime` implementations of the structural Task 2 interfaces.

Required early-failure tests:

```text
wrong driver -> binding_target_invalid before runtime access
wrong lifecycle -> binding_target_invalid before runtime access
malformed descriptor -> binding_target_invalid
malformed expiresAt -> binding_target_invalid before source lookup
expiresAt == now -> binding_expired before source lookup
0 exact sources -> binding_stale
2+ exact sources -> binding_stale
```

- [ ] **Step 2: Write the complete 10-case physical request matrix**

The positive `it.each` table must contain all ten rows, not one representative data-prefixed case:

```ts
it.each([
  ['GET',    'hx-get',         'get'],
  ['GET',    'data-hx-get',    'get'],
  ['POST',   'hx-post',        'post'],
  ['POST',   'data-hx-post',   'post'],
  ['PUT',    'hx-put',         'put'],
  ['PUT',    'data-hx-put',    'put'],
  ['PATCH',  'hx-patch',       'patch'],
  ['PATCH',  'data-hx-patch',  'patch'],
  ['DELETE', 'hx-delete',      'delete'],
  ['DELETE', 'data-hx-delete', 'delete'],
] as const)(
  'dispatches %s through exact physical %s',
  async (method, attribute, ajaxMethod) => {
    // source carries only sourceId + this one request attribute
    // binding target uses the same uppercase method and '/items'
    // expect runtime.ajax(ajaxMethod, '/items', { source, values }) exactly once
  },
);
```

The fake source helper must allow deleting the default `hx-post` before inserting the table attribute so every row has exactly one physical request declaration.

- [ ] **Step 3: Write explicit stale-request negatives**

These must be separate executable assertions:

```ts
// missing physical request declaration
source.attrs.delete('hx-post');
await expectCode(execute(), 'binding_stale');

// duplicate equivalent physical declarations
source.attrs.set('hx-post', '/items');
source.attrs.set('data-hx-post', '/items');
await expectCode(execute(), 'binding_stale');

// method drift: binding POST, source PUT
source.attrs.delete('hx-post');
source.attrs.set('hx-put', '/items');
await expectCode(execute(), 'binding_stale');

// trailing-slash drift
source.attrs.set('hx-post', '/items/');
await expectCode(execute(), 'binding_stale');

// query-order drift
source.attrs.set('hx-post', '/items?b=2&a=1');
// binding path = '/items?a=1&b=2'
await expectCode(execute(), 'binding_stale');
```

- [ ] **Step 4: Write same-origin and underlying-error tests**

```text
resolved URL origin != runtime.currentLocation().origin -> binding_target_invalid, no ajax
runtime.ajax rejects original Error -> driver rejects the exact same Error object
valid execution -> resolves undefined
```

- [ ] **Step 5: Run RED and commit**

```bash
npm test -- tests/htmx-browser-driver.test.ts
git add packages/browser-runtime/tests/htmx-browser-driver.test.ts
git commit -m "test(htmx): define exact browser driver contract"
```

- [ ] **Step 6: Implement the driver core**

Descriptor normalization:

```ts
function parseTarget(binding: RuntimeBinding): HtmxBindingTarget {
  try {
    return parseHtmxBindingTarget(binding);
  } catch (error) {
    if (error instanceof HtmxBindingDescriptorError) {
      throw executionError('binding_target_invalid', `HTMX binding target is invalid: ${error.code}.`);
    }
    throw error;
  }
}
```

Request table:

```ts
const REQUEST_ATTRIBUTES = [
  ['GET', 'hx-get'], ['GET', 'data-hx-get'],
  ['POST', 'hx-post'], ['POST', 'data-hx-post'],
  ['PUT', 'hx-put'], ['PUT', 'data-hx-put'],
  ['PATCH', 'hx-patch'], ['PATCH', 'data-hx-patch'],
  ['DELETE', 'hx-delete'], ['DELETE', 'data-hx-delete'],
] as const;
```

Source request parsing must collect every physically present request attribute and require `found.length === 1` before comparing exact method/path.

Same-origin defense:

```ts
const location = runtime.currentLocation();
const resolved = new URL(target.path, location.href);
if (resolved.origin !== location.origin) {
  throw executionError(
    'binding_target_invalid',
    'HTMX binding path resolves outside the current origin.',
  );
}
```

Execution order in this task:

```text
already-aborted signal
parse T-601 target
shared expiry classification
runtime.assertSupported()
find exact sources; require one
exact physical request parse; compare method/path
same-origin check
mapHtmxActionInput()
final synchronous abort check
runtime.ajax(lowercaseMethod, rawPath, { source, values })
```

Do not add source-policy/busy gates until Task 5.

- [ ] **Step 7: Run GREEN + affected regressions and commit**

```bash
npm test -- \
  tests/htmx-browser-driver.test.ts \
  tests/htmx-input-mapping.test.ts \
  tests/htmx-browser-runtime.test.ts \
  tests/runtime-binding-expiry.test.ts \
  tests/livewire-browser-driver.test.ts \
  tests/livewire-cancellation.test.ts
npm run typecheck

git add \
  packages/browser-runtime/src/htmx-browser-driver.ts \
  packages/browser-runtime/tests/htmx-browser-driver.test.ts
git commit -m "feat(htmx): execute exact browser binding source"
```

---

## Task 5: Unsupported Source Policy and Busy-Source Gate

**Files:**
- Modify: `packages/browser-runtime/src/htmx-browser-driver.ts`
- Modify: `packages/browser-runtime/tests/htmx-browser-driver.test.ts`

- [ ] **Step 1: Write RED unsupported-modifier tests**

For both normal and `data-` names where applicable, fail `htmx_source_unsupported` before ajax for:

```text
hx-vals
hx-vars
hx-confirm
hx-prompt
hx-sync
hx-indicator
hx-ext
```

Also test conservative ancestor detection, not only source-local attributes.

`hx-params` matrix:

```ts
// allowed
undefined
'*'

// rejected
'none'
'item'
'not item'
```

Allowed host-state positive case must include at least:

```text
hx-include
hx-headers
hx-request
hx-target
hx-swap
hx-encoding
```

and still reach ajax.

- [ ] **Step 2: Write RED validation and busy tests**

```text
FORM source without novalidate -> htmx_source_unsupported
FORM source with novalidate -> allowed
source hx-validate="true" -> htmx_source_unsupported
source data-hx-validate="true" -> htmx_source_unsupported
source.classList contains runtime.requestClass() -> htmx_source_busy, no ajax
```

- [ ] **Step 3: Run RED and commit**

```bash
npm test -- tests/htmx-browser-driver.test.ts
git add packages/browser-runtime/tests/htmx-browser-driver.test.ts
git commit -m "test(htmx): lock source policy and busy semantics"
```

- [ ] **Step 4: Implement conservative source policy**

Use:

```ts
const UNSUPPORTED_INHERITED_ATTRIBUTES = [
  'hx-vals',
  'hx-vars',
  'hx-confirm',
  'hx-prompt',
  'hx-sync',
  'hx-indicator',
  'hx-ext',
] as const;
```

For every source/ancestor node, inspect both `name` and `data-${name}` physically. Any presence is unsupported.

For every source/ancestor `hx-params` / `data-hx-params` value, only `'*'` is allowed.

Source-only validation check:

```ts
if (
  physicalAttributeValues(source, 'hx-validate').some((value) => value === 'true')
) {
  throw executionError('htmx_source_unsupported', 'HTMX reference source enables browser validation.');
}

if (source.tagName.toUpperCase() === 'FORM' && !source.hasAttribute('novalidate')) {
  throw executionError('htmx_source_unsupported', 'HTMX reference FORM source must disable browser validation explicitly.');
}
```

Busy check:

```ts
const requestClass = runtime.requestClass();
if (source.classList.contains(requestClass)) {
  throw executionError('htmx_source_busy', 'Exact HTMX source is already processing another request.');
}
```

Do not implement HTMX inheritance resolution, queueing, replacement, or abort.

- [ ] **Step 5: Run GREEN and commit**

```bash
npm test -- \
  tests/htmx-browser-driver.test.ts \
  tests/htmx-input-mapping.test.ts \
  tests/htmx-browser-runtime.test.ts
npm run typecheck

git add \
  packages/browser-runtime/src/htmx-browser-driver.ts \
  packages/browser-runtime/tests/htmx-browser-driver.test.ts
git commit -m "feat(htmx): fail closed on unsafe source state"
```

---

## Task 6: Cancellation Frontier and Natural HTMX Result

**Files:**
- Create: `packages/browser-runtime/tests/htmx-cancellation.test.ts`
- Modify only if RED proves necessary: `packages/browser-runtime/src/htmx-browser-driver.ts`

- [ ] **Step 1: Write the cancellation truth-table tests**

Required executable cases:

```text
signal already aborted before execute -> exact reason; no runtime access/ajax
signal becomes aborted during synchronous pre-dispatch checks -> exact reason; no ajax
ajax invoked, caller aborts afterward, HTMX resolves -> execution resolves undefined
ajax invoked, caller aborts afterward, HTMX rejects original -> exact original rejection wins
no signal -> same normal ajax path
```

Use a deferred promise:

```ts
function deferred<T>() {
  let resolve!: (value: T | PromiseLike<T>) => void;
  let reject!: (reason?: unknown) => void;
  const promise = new Promise<T>((res, rej) => {
    resolve = res;
    reject = rej;
  });
  return { promise, resolve, reject };
}
```

For the pre-dispatch race, make fake `requestClass()` synchronously call `controller.abort(reason)`; the final signal check must stop ajax.

For post-frontier success, make fake `ajax()` synchronously abort the caller and return the deferred HTMX promise; resolving that promise must still resolve execution.

- [ ] **Step 2: Run characterization/RED test**

```bash
npm test -- tests/htmx-cancellation.test.ts
```

Do not weaken tests if a frontier contradiction appears.

- [ ] **Step 3: If necessary, make the minimal driver correction**

The final dispatch block must remain:

```ts
const values = mapHtmxActionInput(target, input);
if (context.signal?.aborted) throw context.signal.reason;
return this.runtime.ajax(AJAX_METHOD[target.method], target.path, { source, values });
```

Forbidden additions:

```text
signal.addEventListener
Promise.race
htmx:abort
runtime.trigger
XHR.abort
```

- [ ] **Step 4: Run GREEN + Livewire cancellation regression and commit**

```bash
npm test -- \
  tests/htmx-cancellation.test.ts \
  tests/htmx-browser-driver.test.ts \
  tests/livewire-cancellation.test.ts
npm run typecheck

git add \
  packages/browser-runtime/src/htmx-browser-driver.ts \
  packages/browser-runtime/tests/htmx-cancellation.test.ts
git commit -m "test(htmx): lock cancellation dispatch frontier"
```

If production code was already correct, commit only the test file.

---

## Task 7: WebMCP / DriverRegistry Integration Proof

**Files:**
- Create: `packages/browser-runtime/tests/htmx-webmcp-integration.test.ts`
- Regression only: `driver-registry.ts`, `webmcp-registration-lifecycle.ts`, Livewire integration tests.

- [ ] **Step 1: Write integration tests without generic production changes**

Build a `MutableHtmxRuntime` implementing `HtmxBrowserRuntime`, register:

```ts
const drivers = new DriverRegistry();
drivers.register('htmx', new HtmxBrowserDriver(runtime));

const lifecycle = new WebMcpRegistrationLifecycle(modelContext, drivers);
await lifecycle.register([{ definition, binding }]);
```

Positive proof:

```text
tool name = prep_list.add_item.v1
tool execute({ item: 'coffee' }) -> undefined
findSources called with exact old sourceId
ajax called once with post, /items, exact source, { item: 'coffee' }
```

Negative proof:

```text
binding points to src-old
runtime contains only src-new
execute -> binding_stale
src-new is never substituted
ajax is never called
```

- [ ] **Step 2: Run HTMX integration proof**

```bash
npm test -- tests/htmx-webmcp-integration.test.ts
```

Expected: PASS using only the generic existing registry/lifecycle contracts.

- [ ] **Step 3: Run cross-regressions and typecheck**

```bash
npm test -- \
  tests/htmx-webmcp-integration.test.ts \
  tests/livewire-webmcp-integration.test.ts \
  tests/webmcp-registration-lifecycle.test.ts \
  tests/driver-registry.test.ts
npm run typecheck
```

Expected: PASS and zero source diff in `driver-registry.ts` or `webmcp-registration-lifecycle.ts`.

- [ ] **Step 4: Commit integration proof**

```bash
git add packages/browser-runtime/tests/htmx-webmcp-integration.test.ts
git commit -m "test(htmx): prove WebMCP driver integration"
```

---

## Task 8: Full Verification, Decision Promotion, and Review Prep

**Files:**
- Modify: `docs/DECISION-REGISTER.md`
- Modify: `TASKS.md`
- Modify: `STATUS.md`
- Modify: `REVIEW_REQUEST.md`

- [ ] **Step 1: Run every focused T-602 test plus affected Livewire regressions**

```bash
cd packages/browser-runtime
npm test -- \
  tests/runtime-binding-expiry.test.ts \
  tests/htmx-browser-runtime.test.ts \
  tests/htmx-input-mapping.test.ts \
  tests/htmx-browser-driver.test.ts \
  tests/htmx-cancellation.test.ts \
  tests/htmx-webmcp-integration.test.ts \
  tests/livewire-browser-driver.test.ts \
  tests/livewire-cancellation.test.ts \
  tests/livewire-webmcp-integration.test.ts
npm run typecheck
```

Expected: PASS.

- [ ] **Step 2: Run full browser suite and record exact count**

```bash
npm test
npm run typecheck
```

Expected: all **174 pre-T-602 tests plus all new T-602 tests** pass. Do not delete or weaken old coverage.

- [ ] **Step 3: Run repository contract validation**

```bash
cd ../..
python scripts/validate.py
```

Expected: PASS.

- [ ] **Step 4: Prove scope boundaries from the diff**

```bash
git diff --name-only main...HEAD -- packages/laravel/src spec/0.1
```

Expected: no output.

```bash
git diff --name-only main...HEAD -- \
  packages/browser-runtime/package.json \
  packages/browser-runtime/package-lock.json
```

Expected: no output.

Review the complete changed-file list. No generic browser contract, fixture app, or T-604 conformance file may appear unexpectedly.

- [ ] **Step 5: Execute the acceptance-proof checklist**

Every row must point to a specific test or diff proof:

```text
existing BindingDriver interface unchanged
exact HTMX page descriptor only
HTMX 2.x only
no HTMX dependency
0/1/2+ exact source behavior
no replacement retargeting
all 10 physical hx/data-hx request forms
missing physical request fails stale
duplicate physical request fails stale
method drift fails stale
trailing-slash/query-order raw-path drift fails stale
same-origin pre-dispatch defense
unknown/missing Action keys fail
recursive JSON-data validation
single-name structured JSON encoding
ordinary host state remains untrusted
unsupported HTMX modifiers fail closed
FORM/hx-validate behavior explicit
busy source fails closed
pre-frontier cancellation no dispatch
post-frontier natural result preserved
underlying HTMX error identity preserved
void success semantics
shared expiry preserves Livewire behavior
WebMCP/registry integration
Livewire browser/cancellation/integration regressions
full browser suite/typecheck
contract/PHP/lint CI
no spec/Laravel/dependency/core-contract drift
no T-603/T-604 scope creep
```

Add a missing test before tracking updates if any row is unproven.

- [ ] **Step 6: Promote D-054/D-055/D-056 only after the full local green gate**

`docs/DECISION-REGISTER.md`:

```text
D-054 -> ACCEPTED: exact-source host HTMX 2.x execution boundary only
D-055 -> ACCEPTED: busy-source failure + pre-ajax-only strong cancellation
D-056 -> ACCEPTED: deterministic Action-input integrity + reference-source exclusions
D-020 -> remains PROPOSED through T-604
```

Do not broaden these decisions into generic HTTP or completed portability claims.

- [ ] **Step 7: Update TASKS.md and STATUS.md to review-ready**

Record exact:

```text
branch/head/base
RED and GREEN task commits
final browser test count + typecheck
focused HTMX runtime/input/driver/cancellation/WebMCP evidence
Livewire expiry/cancellation regressions
python scripts/validate.py result
D-054/55/56 ACCEPTED
D-020 PROPOSED
T-603 NOT STARTED
known boundary: real DOM/server fixture still absent
```

Use status wording:

```text
T-602 — IMPLEMENTED / SELF-REVIEWED / READY FOR EXTERNAL REVIEW
```

Do not claim merge/main revalidation before they happen.

- [ ] **Step 8: Prepare concise REVIEW_REQUEST.md**

Ask external reviewers to focus on:

```text
exact-source duplication/replacement retargeting
physical hx/data-hx request ambiguity
method/raw-path drift
same-origin boundary vs host configRequest disclaimer
JSON coercion/accessor/sparse-array/custom-object escape paths
hx-vals/hx-vars/hx-params inheritance escape paths
confirm/prompt/sync/indicator/extension scope gaps
busy-source false negatives and silent queueing
pre-vs-post ajax cancellation truthfulness
Livewire expiry drift after extraction
generic DriverRegistry/WebMCP drift
dependency/Laravel/spec scope creep
```

- [ ] **Step 9: Commit review-prep tracking**

```bash
git add docs/DECISION-REGISTER.md TASKS.md STATUS.md REVIEW_REQUEST.md
git commit -m "docs(htmx): prepare T-602 external review"
```

- [ ] **Step 10: Verify exact review-prep head in GitHub Actions**

Required `validate` jobs:

```text
contract
php-lint
browser
php-tests PHP 8.3 / Illuminate 12
php-tests PHP 8.4 / Illuminate 12
php-tests PHP 8.3 / Illuminate 13
php-tests PHP 8.4 / Illuminate 13
```

All **7/7** must be green.

- [ ] **Step 11: Stop at external review**

Report the exact review-ready head, CI run, final browser test total, and decision state. Do not create/merge a PR, delete the branch, begin T-603, run T-604 shared conformance, or promote D-020 without explicit authorization.

---

## Plan Self-Review Result

- Spec coverage: all 27 T-602 acceptance criteria map to Tasks 1–8.
- Request matrix correction: Task 4 now explicitly requires all **10** physical `hx-*` / `data-hx-*` method forms plus missing, duplicate, method-drift, trailing-slash, and query-order negative proofs.
- Placeholder scan: no TBD/TODO/“implement later”/generic “add validation” instructions remain.
- Type consistency: `BrowserClock`, `RuntimeBindingExpiryState`, `HtmxBindingExecutionError`, `HtmxAjaxMethod`, `HtmxSourceElement`, `HtmxBrowserRuntime`, `mapHtmxActionInput()`, and `HtmxBrowserDriver` have one consistent definition/boundary.
- File-boundary check: no barrel export, generic HTTP abstraction, HTMX dependency, DOM emulator, Laravel production adapter, or frozen schema change is planned.
- Cancellation check: HTMX uses the narrower synchronous `runtime.ajax()` frontier; Livewire keeps its existing interceptor/onSend semantics.
- Input-integrity check: structured Action values remain one top-level JSON string; no form-path flattening.
- Host-state check: ordinary host request state stays untrusted and cannot manufacture actor/tenant/record/selection/confirmation/idempotency authority.
- T-603/T-604 boundary: no real HTMX fixture/server or shared cross-driver conformance is implemented here.
- Decision boundary: D-054/D-055/D-056 may become `ACCEPTED` only after complete T-602 verification; D-020 remains `PROPOSED` through T-604.
- Review boundary: successful implementation stops at external-review readiness; no automatic PR/merge/T-603 transition.