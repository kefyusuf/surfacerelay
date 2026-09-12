# T-602 HTMX Browser Driver Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement a fail-closed HTMX 2.x browser `BindingDriver` that executes the exact T-601 HTMX RuntimeBinding through the host page's existing `htmx.ajax()` path, with exact-source stale checks, deterministic Action-input mapping, truthful busy/cancellation semantics, shared RuntimeBinding expiry handling, and WebMCP/DriverRegistry integration proof.

**Architecture:** Keep framework-neutral browser contracts unchanged. Add a narrow host-HTMX runtime adapter, a driver-local execution error type, deterministic Action-input mapper, and `HtmxBrowserDriver`; extract RuntimeBinding expiry classification from the Livewire driver into a shared helper. Unit tests use narrow structural DOM/runtime fakes only; real DOM + real HTMX integration remains T-603.

**Tech Stack:** TypeScript 5.9, Vitest 3.2, DOM typings from the existing browser-runtime `tsconfig`, existing `RuntimeBinding` / `BindingDriver` / WebMCP lifecycle types, repository Python contract validator, GitHub Actions validate workflow.

**Spec:** `docs/superpowers/specs/2026-09-12-htmx-browser-driver-design.md`

## Global Constraints

- Branch: `feat/htmx-browser-driver`.
- Main/base entering T-602: `main@536a89f7a7fe6fb10f4284203cffb5a63ecc0fb4`.
- Approved design checkpoint head: `42cca940af35b3dff918a641b619d578f839047d`; design CI `34682081839` — 7/7 green.
- Pre-T-602 browser baseline: TypeScript typecheck + **174/174 Vitest**.
- T-601 descriptor decision `D-053` is already `ACCEPTED`; do not change its semantics.
- `D-054`, `D-055`, `D-056` remain `PROPOSED` until complete T-602 implementation verification passes.
- `D-020` remains `PROPOSED` through T-604.
- `BindingDriver`, `DriverExecutionContext`, `RuntimeBinding`, `DriverRegistry`, and `WebMcpRegistrationLifecycle` stay unchanged.
- Supported reference runtime is HTMX **2.x only**.
- Do not add `htmx.org`, jsdom, happy-dom, Playwright, Puppeteer, or any other dependency.
- Use the host page's ambient HTMX runtime; do not bundle/import a second HTMX instance.
- Resolve exactly one `data-surfacerelay-htmx-source` identity; 0 or 2+ matches fail stale.
- Require exactly one physical supported request declaration from `hx-get|post|put|patch|delete` or `data-hx-*`.
- Compare method and raw path exactly; no path normalization or semantic equivalence.
- Perform same-origin defense before dispatch, but do not claim to sandbox later host HTMX event hooks.
- Map only allowlisted own Action-input names; required names must be own properties.
- Accept only deterministic JSON-data values; structured values stay under one top-level name as JSON strings.
- Reject Action-value coercions that would silently lose or execute data: `undefined`, non-finite numbers, BigInt, Symbol, Function, Date, Map, Set, Blob/File, custom class instances, accessors, sparse arrays, cycles, nested invalid values.
- Reject reference-source mechanisms that can ambiguously mutate/filter/coordinate/confirm/prompt/relocate/extend SurfaceRelay execution: `hx-vals`, `hx-vars`, restrictive `hx-params`, `hx-confirm`, `hx-prompt`, `hx-sync`, `hx-indicator`, `hx-ext`, active source validation.
- Allow ordinary untrusted host request state such as form fields, hidden fields, `hx-include`, `hx-headers`, `hx-request`, `hx-target`, `hx-swap`, and standard `hx-encoding`.
- Busy exact source fails `htmx_source_busy`; do not queue, replace, or broadly abort HTMX work.
- Strong no-dispatch cancellation exists only before the `htmx.ajax()` invocation frontier.
- Do not call `htmx:abort`, race the HTMX promise against caller cancellation, or claim post-frontier rollback/reversal.
- Preserve natural underlying HTMX rejection identity and `Promise<void>` success semantics.
- No production change under `packages/laravel/src/**`.
- No change under `spec/0.1/**`.
- T-603 fixture work and T-604 shared conformance are out of scope.
- Do not create/merge a PR, delete the branch, or start T-603 automatically.

## File Structure

### Create

- `packages/browser-runtime/src/runtime-binding-expiry.ts`
  - Shared strict RFC3339 RuntimeBinding expiry classification and browser clock abstraction.
- `packages/browser-runtime/src/htmx-errors.ts`
  - HTMX browser execution error taxonomy.
- `packages/browser-runtime/src/htmx-browser-runtime.ts`
  - Narrow HTMX 2.x ambient-runtime compatibility adapter plus structural source-element types.
- `packages/browser-runtime/src/htmx-input-mapping.ts`
  - Named Action-input allowlist enforcement and deterministic JSON-data-to-string encoding.
- `packages/browser-runtime/src/htmx-browser-driver.ts`
  - Exact source/request validation, source-policy gates, busy gate, same-origin defense, cancellation frontier, and `htmx.ajax()` dispatch.
- `packages/browser-runtime/tests/runtime-binding-expiry.test.ts`
  - Shared expiry semantics and strict RFC3339 regression proof.
- `packages/browser-runtime/tests/htmx-browser-runtime.test.ts`
  - Ambient runtime/version/source/request-class/ajax adapter behavior.
- `packages/browser-runtime/tests/htmx-browser-runtime.typecheck.ts`
  - Compile-time runtime/source/driver-facing structural contract proof.
- `packages/browser-runtime/tests/htmx-input-mapping.test.ts`
  - JSON-data mapping and negative coercion matrix.
- `packages/browser-runtime/tests/htmx-browser-driver.test.ts`
  - Exact binding/source/request/path/runtime/source-policy/busy/execution behavior.
- `packages/browser-runtime/tests/htmx-cancellation.test.ts`
  - Pre-frontier/no-dispatch and post-frontier natural-result behavior.
- `packages/browser-runtime/tests/htmx-webmcp-integration.test.ts`
  - WebMCP -> DriverRegistry -> HTMX driver executable proof.

### Modify

- `packages/browser-runtime/src/livewire-browser-driver.ts`
  - Remove duplicated clock/RFC3339 expiry implementation and consume the shared helper only; preserve all Livewire semantics.
- `docs/DECISION-REGISTER.md`
  - Promote D-054/D-055/D-056 only after complete implementation verification.
- `TASKS.md`
  - Record implementation/verification evidence and stop at external-review readiness rather than T-603.
- `STATUS.md`
  - Record exact implementation/review-prep heads, files, verification evidence, limitations, and next gate.
- `REVIEW_REQUEST.md`
  - Replace the previous review handoff with a concise T-602 external-review request at review-prep only.

### Expected unchanged production/dependency files

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

### Task 1: Extract Shared RuntimeBinding Expiry Semantics Without Changing Livewire Behavior

**Files:**
- Create: `packages/browser-runtime/tests/runtime-binding-expiry.test.ts`
- Create: `packages/browser-runtime/src/runtime-binding-expiry.ts`
- Modify: `packages/browser-runtime/src/livewire-browser-driver.ts`
- Regression: `packages/browser-runtime/tests/livewire-browser-driver.test.ts`
- Regression: `packages/browser-runtime/tests/livewire-cancellation.test.ts`

**Interfaces:**
- Consumes: RuntimeBinding `expiresAt` runtime value plus current `Date`.
- Produces:

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

Later tasks rely on this exact API. Each framework driver maps `invalid` and `expired` into its own execution error class; the helper throws no framework-specific errors.

- [ ] **Step 1: Write RED tests for the shared expiry classifier**

Create `packages/browser-runtime/tests/runtime-binding-expiry.test.ts`:

```ts
import { describe, expect, it } from 'vitest';
import {
  classifyRuntimeBindingExpiry,
  systemBrowserClock,
} from '../src/runtime-binding-expiry.js';

const now = new Date('2026-09-12T00:00:00.000Z');

describe('classifyRuntimeBindingExpiry', () => {
  it.each([null, undefined])('treats %j as active without expiry', (expiresAt) => {
    expect(classifyRuntimeBindingExpiry(expiresAt, now)).toBe('active');
  });

  it.each([
    '2026-09-12T00:00:00Z',
    '2026-09-11T23:59:59.999Z',
    '2026-09-12T03:00:00+03:00',
  ])('treats equality/past %s as expired', (expiresAt) => {
    expect(classifyRuntimeBindingExpiry(expiresAt, now)).toBe('expired');
  });

  it.each([
    '2026-09-12T00:00:00.001Z',
    '2026-09-12T01:30:00+01:00',
    '2026-09-12T00:00:00.123456789-00:30',
  ])('accepts future RFC3339 value %s', (expiresAt) => {
    expect(classifyRuntimeBindingExpiry(expiresAt, now)).toBe('active');
  });

  it.each([
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
  ])('rejects malformed/non-RFC3339 runtime expiry %j', (expiresAt) => {
    expect(classifyRuntimeBindingExpiry(expiresAt, now)).toBe('invalid');
  });

  it('exposes the real browser clock as a narrow now() port', () => {
    expect(systemBrowserClock.now()).toBeInstanceOf(Date);
  });
});
```

- [ ] **Step 2: Run the focused test and verify RED**

```bash
cd packages/browser-runtime
npm test -- tests/runtime-binding-expiry.test.ts
```

Expected: FAIL because `../src/runtime-binding-expiry.js` does not exist.

- [ ] **Step 3: Commit the RED checkpoint**

```bash
git add packages/browser-runtime/tests/runtime-binding-expiry.test.ts
git commit -m "test(browser): define shared binding expiry contract"
```

- [ ] **Step 4: Implement the shared strict RFC3339 classifier**

Create `packages/browser-runtime/src/runtime-binding-expiry.ts` with the existing Livewire semantics moved intact:

```ts
export interface BrowserClock {
  now(): Date;
}

export const systemBrowserClock: BrowserClock = {
  now: () => new Date(),
};

export type RuntimeBindingExpiryState = 'active' | 'expired' | 'invalid';

const RFC3339_PATTERN = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(?:\.(\d+))?(Z|([+-])(\d{2}):(\d{2}))$/;

function daysInMonth(year: number, month: number): number {
  if (month === 2) {
    const leap = year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0);
    return leap ? 29 : 28;
  }
  return [4, 6, 9, 11].includes(month) ? 30 : 31;
}

function parseRfc3339Millis(value: string): number | null {
  const match = RFC3339_PATTERN.exec(value);
  if (!match) return null;

  const year = Number(match[1]);
  const month = Number(match[2]);
  const day = Number(match[3]);
  const hour = Number(match[4]);
  const minute = Number(match[5]);
  const second = Number(match[6]);
  const fraction = match[7] ?? '';
  const zone = match[8];
  const offsetSign = match[9];
  const offsetHour = match[10] === undefined ? 0 : Number(match[10]);
  const offsetMinute = match[11] === undefined ? 0 : Number(match[11]);

  if (year === 0 || month < 1 || month > 12) return null;
  if (day < 1 || day > daysInMonth(year, month)) return null;
  if (hour < 0 || hour > 23 || minute < 0 || minute > 59 || second < 0 || second > 59) return null;
  if (zone !== 'Z' && (offsetHour < 0 || offsetHour > 23 || offsetMinute < 0 || offsetMinute > 59)) return null;

  const milliseconds = Number((fraction.slice(0, 3) + '000').slice(0, 3));
  const local = new Date(0);
  local.setUTCFullYear(year, month - 1, day);
  local.setUTCHours(hour, minute, second, milliseconds);

  if (
    local.getUTCFullYear() !== year
    || local.getUTCMonth() !== month - 1
    || local.getUTCDate() !== day
    || local.getUTCHours() !== hour
    || local.getUTCMinutes() !== minute
    || local.getUTCSeconds() !== second
  ) {
    return null;
  }

  let offsetMinutes = 0;
  if (zone !== 'Z') {
    offsetMinutes = offsetHour * 60 + offsetMinute;
    if (offsetSign === '-') offsetMinutes *= -1;
  }

  return local.getTime() - offsetMinutes * 60_000;
}

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

Do not “improve” offset/date semantics while extracting; this task is behavior-preserving.

- [ ] **Step 5: Refactor LivewireBrowserDriver to consume the shared helper**

In `packages/browser-runtime/src/livewire-browser-driver.ts`:

1. Remove the local `BrowserClock`, `systemBrowserClock`, `RFC3339_PATTERN`, `daysInMonth()`, `parseRfc3339Millis()`, and `assertNotExpired()` implementations.
2. Add:

```ts
import {
  classifyRuntimeBindingExpiry,
  systemBrowserClock,
  type BrowserClock,
} from './runtime-binding-expiry.js';
```

3. Replace the old expiry call inside `execute()` with:

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

Do not alter Livewire target parsing, input mapping, component resolution, reserved methods, interception, `$call()`, or cancellation behavior.

- [ ] **Step 6: Run shared expiry + Livewire regression tests**

```bash
npm test -- \
  tests/runtime-binding-expiry.test.ts \
  tests/livewire-browser-driver.test.ts \
  tests/livewire-cancellation.test.ts \
  tests/livewire-webmcp-integration.test.ts
npm run typecheck
```

Expected: PASS with the same Livewire expiry/cancellation behavior as before extraction.

- [ ] **Step 7: Commit Task 1 GREEN**

```bash
git add \
  packages/browser-runtime/src/runtime-binding-expiry.ts \
  packages/browser-runtime/src/livewire-browser-driver.ts \
  packages/browser-runtime/tests/runtime-binding-expiry.test.ts
git commit -m "refactor(browser): share runtime binding expiry checks"
```

---

### Task 2: Add HTMX Execution Errors and a Narrow HTMX 2.x Runtime Adapter

**Files:**
- Create: `packages/browser-runtime/src/htmx-errors.ts`
- Create: `packages/browser-runtime/src/htmx-browser-runtime.ts`
- Create: `packages/browser-runtime/tests/htmx-browser-runtime.test.ts`
- Create: `packages/browser-runtime/tests/htmx-browser-runtime.typecheck.ts`

**Interfaces:**
- Produces:

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

export class HtmxBindingExecutionError extends Error {
  constructor(code: HtmxBindingExecutionErrorCode, message: string);
  readonly code: HtmxBindingExecutionErrorCode;
}

export type HtmxAjaxMethod = 'get' | 'post' | 'put' | 'patch' | 'delete';

export interface HtmxClassListLike {
  contains(token: string): boolean;
}

export interface HtmxSourceElement {
  readonly tagName: string;
  readonly parentElement: HtmxSourceElement | null;
  readonly classList: HtmxClassListLike;
  hasAttribute(name: string): boolean;
  getAttribute(name: string): string | null;
}

export interface HtmxLocationSnapshot {
  readonly href: string;
  readonly origin: string;
}

export interface HtmxAjaxContext {
  readonly source: HtmxSourceElement;
  readonly values: Readonly<Record<string, string>>;
}

export interface HtmxBrowserRuntime {
  assertSupported(): void;
  findSources(sourceId: string): readonly HtmxSourceElement[];
  currentLocation(): HtmxLocationSnapshot;
  requestClass(): string;
  ajax(method: HtmxAjaxMethod, path: string, context: HtmxAjaxContext): Promise<void>;
}

export class GlobalHtmxBrowserRuntime implements HtmxBrowserRuntime {
  constructor(root?: HtmxAmbientRoot);
}
```

Task 3+ driver code depends on these exact names.

- [ ] **Step 1: Write RED runtime compatibility tests**

Create `packages/browser-runtime/tests/htmx-browser-runtime.test.ts`:

```ts
import { describe, expect, it, vi } from 'vitest';
import { HtmxBindingExecutionError } from '../src/htmx-errors.js';
import {
  GlobalHtmxBrowserRuntime,
  type HtmxAmbientRoot,
  type HtmxSourceElement,
} from '../src/htmx-browser-runtime.js';

function element(sourceId: string): HtmxSourceElement {
  return {
    tagName: 'BUTTON',
    parentElement: null,
    classList: { contains: () => false },
    hasAttribute(name) {
      return name === 'data-surfacerelay-htmx-source';
    },
    getAttribute(name) {
      return name === 'data-surfacerelay-htmx-source' ? sourceId : null;
    },
  };
}

function root(overrides: Partial<HtmxAmbientRoot> = {}): HtmxAmbientRoot {
  const nodes = [element('src-a'), element('src-b'), element('src-a')];
  return {
    htmx: {
      version: '2.0.10',
      config: { requestClass: 'htmx-request' },
      ajax: vi.fn(async () => undefined),
    },
    document: {
      querySelectorAll: vi.fn(() => nodes),
    },
    location: {
      href: 'https://example.test/items',
      origin: 'https://example.test',
    },
    ...overrides,
  };
}

function expectCode(fn: () => unknown, code: string): void {
  try {
    fn();
    throw new Error('expected HTMX runtime failure');
  } catch (error) {
    expect(error).toBeInstanceOf(HtmxBindingExecutionError);
    expect((error as HtmxBindingExecutionError).code).toBe(code);
  }
}

describe('GlobalHtmxBrowserRuntime', () => {
  it.each(['2.0.0', '2.0.10', '2.7.3', '2.1.0-beta.1'])(
    'accepts compatible HTMX 2.x runtime %s',
    (version) => {
      const ambient = root();
      ambient.htmx!.version = version;
      expect(() => new GlobalHtmxBrowserRuntime(ambient).assertSupported()).not.toThrow();
    },
  );

  it.each(['', '1.9.12', '4.0.0-beta.1', '2', '2.x', 'garbage'])(
    'rejects unsupported/malformed HTMX version %s',
    (version) => {
      const ambient = root();
      ambient.htmx!.version = version;
      expectCode(
        () => new GlobalHtmxBrowserRuntime(ambient).assertSupported(),
        'htmx_runtime_unsupported',
      );
    },
  );

  it('fails unavailable when HTMX or callable ajax is missing', () => {
    expectCode(() => new GlobalHtmxBrowserRuntime({}).assertSupported(), 'htmx_runtime_unavailable');

    const ambient = root();
    ambient.htmx!.ajax = null;
    expectCode(
      () => new GlobalHtmxBrowserRuntime(ambient).assertSupported(),
      'htmx_runtime_unavailable',
    );
  });

  it('finds all exact sourceId matches without CSS value interpolation', () => {
    const runtime = new GlobalHtmxBrowserRuntime(root());
    expect(runtime.findSources('src-a')).toHaveLength(2);
    expect(runtime.findSources('src-b')).toHaveLength(1);
    expect(runtime.findSources('src-c')).toHaveLength(0);
  });

  it('returns a defensive page location snapshot and request class', () => {
    const runtime = new GlobalHtmxBrowserRuntime(root());
    expect(runtime.currentLocation()).toEqual({
      href: 'https://example.test/items',
      origin: 'https://example.test',
    });
    expect(runtime.requestClass()).toBe('htmx-request');
  });

  it.each(['', '   ', 'two classes', 'tab\tclass'])(
    'rejects unusable runtime requestClass %j',
    (requestClass) => {
      const ambient = root();
      ambient.htmx!.config = { requestClass };
      expectCode(
        () => new GlobalHtmxBrowserRuntime(ambient).assertSupported(),
        'htmx_runtime_unsupported',
      );
    },
  );

  it('delegates exact ajax method/path/source/values once', async () => {
    const ambient = root();
    const runtime = new GlobalHtmxBrowserRuntime(ambient);
    const source = element('src-a');
    const values = Object.freeze({ item: 'coffee' });

    await runtime.ajax('post', '/items', { source, values });

    expect(ambient.htmx!.ajax).toHaveBeenCalledExactlyOnceWith(
      'post',
      '/items',
      { source, values },
    );
  });
});
```

- [ ] **Step 2: Run runtime test and verify RED**

```bash
npm test -- tests/htmx-browser-runtime.test.ts
```

Expected: FAIL because `htmx-errors.ts` and `htmx-browser-runtime.ts` do not exist.

- [ ] **Step 3: Commit the RED checkpoint**

```bash
git add packages/browser-runtime/tests/htmx-browser-runtime.test.ts
git commit -m "test(htmx): define browser runtime compatibility boundary"
```

- [ ] **Step 4: Implement HTMX execution error type**

Create `packages/browser-runtime/src/htmx-errors.ts`:

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

- [ ] **Step 5: Implement the structural HTMX runtime port and ambient adapter**

Create `packages/browser-runtime/src/htmx-browser-runtime.ts`:

```ts
import { HtmxBindingExecutionError } from './htmx-errors.js';

export type HtmxAjaxMethod = 'get' | 'post' | 'put' | 'patch' | 'delete';

export interface HtmxClassListLike {
  contains(token: string): boolean;
}

export interface HtmxSourceElement {
  readonly tagName: string;
  readonly parentElement: HtmxSourceElement | null;
  readonly classList: HtmxClassListLike;
  hasAttribute(name: string): boolean;
  getAttribute(name: string): string | null;
}

export interface HtmxLocationSnapshot {
  readonly href: string;
  readonly origin: string;
}

export interface HtmxAjaxContext {
  readonly source: HtmxSourceElement;
  readonly values: Readonly<Record<string, string>>;
}

export interface HtmxBrowserRuntime {
  assertSupported(): void;
  findSources(sourceId: string): readonly HtmxSourceElement[];
  currentLocation(): HtmxLocationSnapshot;
  requestClass(): string;
  ajax(method: HtmxAjaxMethod, path: string, context: HtmxAjaxContext): Promise<void>;
}

interface HtmxGlobalLike {
  version?: unknown;
  config?: { requestClass?: unknown } | null;
  ajax?: unknown;
}

interface HtmxDocumentLike {
  querySelectorAll(selector: string): ArrayLike<HtmxSourceElement>;
}

interface HtmxLocationLike {
  href?: unknown;
  origin?: unknown;
}

export interface HtmxAmbientRoot {
  htmx?: HtmxGlobalLike;
  document?: HtmxDocumentLike;
  location?: HtmxLocationLike;
}

const VERSION_PATTERN = /^(\d+)\.(\d+)\.(\d+)(?:[-+][0-9A-Za-z.-]+)?$/;
const SOURCE_SELECTOR = '[data-surfacerelay-htmx-source]';

function runtimeError(
  code: 'htmx_runtime_unavailable' | 'htmx_runtime_unsupported',
  message: string,
): HtmxBindingExecutionError {
  return new HtmxBindingExecutionError(code, message);
}

function defaultAmbientRoot(): HtmxAmbientRoot {
  return globalThis as unknown as HtmxAmbientRoot;
}

export class GlobalHtmxBrowserRuntime implements HtmxBrowserRuntime {
  constructor(private readonly root: HtmxAmbientRoot = defaultAmbientRoot()) {}

  assertSupported(): void {
    const htmx = this.requireHtmx();
    const version = htmx.version;
    if (typeof version !== 'string') {
      throw runtimeError('htmx_runtime_unsupported', 'HTMX version is unavailable or malformed.');
    }

    const match = VERSION_PATTERN.exec(version);
    if (!match || Number(match[1]) !== 2) {
      throw runtimeError('htmx_runtime_unsupported', 'SurfaceRelay reference HTMX driver requires HTMX 2.x.');
    }

    this.requireDocument();
    this.requireLocation();
    this.requestClass();
  }

  findSources(sourceId: string): readonly HtmxSourceElement[] {
    this.assertSupported();
    const candidates = Array.from(this.requireDocument().querySelectorAll(SOURCE_SELECTOR));
    return Object.freeze(
      candidates.filter(
        (candidate) => candidate.getAttribute('data-surfacerelay-htmx-source') === sourceId,
      ),
    );
  }

  currentLocation(): HtmxLocationSnapshot {
    this.assertSupported();
    const location = this.requireLocation();
    return Object.freeze({ href: location.href as string, origin: location.origin as string });
  }

  requestClass(): string {
    const htmx = this.requireHtmx();
    const requestClass = htmx.config?.requestClass;
    if (
      typeof requestClass !== 'string'
      || requestClass.length === 0
      || requestClass.trim() !== requestClass
      || /\s/.test(requestClass)
    ) {
      throw runtimeError('htmx_runtime_unsupported', 'HTMX requestClass must be one non-empty class token.');
    }
    return requestClass;
  }

  async ajax(
    method: HtmxAjaxMethod,
    path: string,
    context: HtmxAjaxContext,
  ): Promise<void> {
    this.assertSupported();
    const htmx = this.requireHtmx();
    const ajax = htmx.ajax as (
      verb: HtmxAjaxMethod,
      requestPath: string,
      options: { source: unknown; values: Readonly<Record<string, string>> },
    ) => Promise<void>;

    return ajax.call(htmx, method, path, {
      source: context.source,
      values: context.values,
    });
  }

  private requireHtmx(): HtmxGlobalLike {
    const htmx = this.root.htmx;
    if (!htmx || typeof htmx !== 'object' || typeof htmx.ajax !== 'function') {
      throw runtimeError('htmx_runtime_unavailable', 'HTMX browser runtime is unavailable or does not expose callable ajax().');
    }
    return htmx;
  }

  private requireDocument(): HtmxDocumentLike {
    const document = this.root.document;
    if (!document || typeof document.querySelectorAll !== 'function') {
      throw runtimeError('htmx_runtime_unavailable', 'Browser document.querySelectorAll() is unavailable.');
    }
    return document;
  }

  private requireLocation(): Required<Pick<HtmxLocationLike, 'href' | 'origin'>> {
    const location = this.root.location;
    if (!location || typeof location.href !== 'string' || typeof location.origin !== 'string') {
      throw runtimeError('htmx_runtime_unavailable', 'Browser location href/origin are unavailable.');
    }
    return { href: location.href, origin: location.origin };
  }
}
```

Do not add HTMX private internals, `trigger`, XHR handles, event hooks, or cancellation methods to the port.

- [ ] **Step 6: Add compile-time structural contract proof**

Create `packages/browser-runtime/tests/htmx-browser-runtime.typecheck.ts`:

```ts
import type {
  HtmxAjaxContext,
  HtmxAjaxMethod,
  HtmxBrowserRuntime,
  HtmxSourceElement,
} from '../src/htmx-browser-runtime.js';

const source: HtmxSourceElement = {
  tagName: 'BUTTON',
  parentElement: null,
  classList: { contains: (_token: string) => false },
  hasAttribute: (_name: string) => false,
  getAttribute: (_name: string) => null,
};

const method: HtmxAjaxMethod = 'post';
const context: HtmxAjaxContext = {
  source,
  values: Object.freeze({ item: 'coffee' }),
};

declare const runtime: HtmxBrowserRuntime;
runtime.assertSupported();
runtime.findSources('src-1');
runtime.currentLocation();
runtime.requestClass();
void runtime.ajax(method, '/items', context);

// @ts-expect-error unsupported method
const invalid: HtmxAjaxMethod = 'head';
void invalid;
```

- [ ] **Step 7: Run focused runtime tests and typecheck**

```bash
npm test -- tests/htmx-browser-runtime.test.ts
npm run typecheck
```

Expected: PASS; the `@ts-expect-error` must be consumed by a real type error.

- [ ] **Step 8: Commit Task 2 GREEN**

```bash
git add \
  packages/browser-runtime/src/htmx-errors.ts \
  packages/browser-runtime/src/htmx-browser-runtime.ts \
  packages/browser-runtime/tests/htmx-browser-runtime.test.ts \
  packages/browser-runtime/tests/htmx-browser-runtime.typecheck.ts
git commit -m "feat(htmx): add browser runtime adapter"
```

---

### Task 3: Deterministic Named Action-Input Mapping

**Files:**
- Create: `packages/browser-runtime/src/htmx-input-mapping.ts`
- Create: `packages/browser-runtime/tests/htmx-input-mapping.test.ts`

**Interfaces:**
- Consumes: validated `HtmxBindingTarget` plus runtime Action input object.
- Produces:

```ts
export function mapHtmxActionInput(
  target: HtmxBindingTarget,
  input: Record<string, unknown>,
): Readonly<Record<string, string>>;
```

The mapper throws `HtmxBindingExecutionError` with `binding_input_unmappable` for any allowlist/required/value-domain failure.

- [ ] **Step 1: Write RED input-mapping tests**

Create `packages/browser-runtime/tests/htmx-input-mapping.test.ts`:

```ts
import { describe, expect, it } from 'vitest';
import type { HtmxBindingTarget } from '../src/htmx-binding-descriptor.js';
import { HtmxBindingExecutionError } from '../src/htmx-errors.js';
import { mapHtmxActionInput } from '../src/htmx-input-mapping.js';

const target: HtmxBindingTarget = Object.freeze({
  sourceId: 'src-1',
  method: 'POST',
  path: '/items',
  inputNames: Object.freeze(['count', 'enabled', 'filters', 'item', 'note', 'nothing', 'tags']),
  requiredInputNames: Object.freeze(['item']),
});

function expectUnmappable(input: Record<string, unknown>): void {
  try {
    mapHtmxActionInput(target, input);
    throw new Error('expected unmappable input');
  } catch (error) {
    expect(error).toBeInstanceOf(HtmxBindingExecutionError);
    expect((error as HtmxBindingExecutionError).code).toBe('binding_input_unmappable');
  }
}

describe('mapHtmxActionInput', () => {
  it('maps deterministic JSON-data values without flattening structured values', () => {
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
  });

  it('allows independently omitted optional names', () => {
    expect(mapHtmxActionInput(target, { item: 'coffee', note: 'fresh' })).toEqual({
      item: 'coffee',
      note: 'fresh',
    });
  });

  it('rejects missing required and unknown own keys', () => {
    expectUnmappable({ note: 'missing item' });
    expectUnmappable({ item: 'coffee', unexpected: true });
  });

  it('does not accept inherited values as required Action input', () => {
    const inherited = Object.create({ item: 'inherited' }) as Record<string, unknown>;
    expectUnmappable(inherited);
  });

  it.each([
    undefined,
    Number.NaN,
    Number.POSITIVE_INFINITY,
    Number.NEGATIVE_INFINITY,
    1n,
    Symbol('x'),
    () => 'x',
    new Date('2026-09-12T00:00:00Z'),
    new Map([['x', 1]]),
    new Set(['x']),
  ])('rejects unsupported scalar/object value %#', (value) => {
    expectUnmappable({ item: value });
  });

  it('rejects sparse arrays, nested invalid values, accessors, custom instances, and cycles', () => {
    const sparse = new Array(2);
    sparse[1] = 'x';
    expectUnmappable({ item: sparse });
    expectUnmappable({ item: { nested: Number.NaN } });

    const accessor: Record<string, unknown> = {};
    Object.defineProperty(accessor, 'value', { enumerable: true, get: () => 'computed' });
    expectUnmappable({ item: accessor });

    class Custom { value = 'x'; }
    expectUnmappable({ item: new Custom() });

    const cyclic: Record<string, unknown> = {};
    cyclic.self = cyclic;
    expectUnmappable({ item: cyclic });
  });

  it('returns a frozen output snapshot', () => {
    const mapped = mapHtmxActionInput(target, { item: 'coffee' });
    expect(Object.isFrozen(mapped)).toBe(true);
  });
});
```

If the test environment exposes `Blob`/`File`, add explicit cases for them. Do not add DOM dependencies solely to manufacture those values.

- [ ] **Step 2: Run focused test and verify RED**

```bash
npm test -- tests/htmx-input-mapping.test.ts
```

Expected: FAIL because `htmx-input-mapping.ts` does not exist.

- [ ] **Step 3: Commit the RED checkpoint**

```bash
git add packages/browser-runtime/tests/htmx-input-mapping.test.ts
git commit -m "test(htmx): define action input mapping contract"
```

- [ ] **Step 4: Implement recursive JSON-data validation and encoding**

Create `packages/browser-runtime/src/htmx-input-mapping.ts`:

```ts
import type { HtmxBindingTarget } from './htmx-binding-descriptor.js';
import { HtmxBindingExecutionError } from './htmx-errors.js';

function mappingError(message: string): HtmxBindingExecutionError {
  return new HtmxBindingExecutionError('binding_input_unmappable', message);
}

function hasOwn(value: object, key: PropertyKey): boolean {
  return Object.prototype.hasOwnProperty.call(value, key);
}

function assertDataProperty(
  owner: object,
  key: string,
): PropertyDescriptor & { value: unknown } {
  const descriptor = Object.getOwnPropertyDescriptor(owner, key);
  if (!descriptor || !descriptor.enumerable || !('value' in descriptor)) {
    throw mappingError(`HTMX Action value property "${key}" is not a plain enumerable data property.`);
  }
  return descriptor as PropertyDescriptor & { value: unknown };
}

function assertJsonData(value: unknown, stack: Set<object>): void {
  if (value === null) return;

  switch (typeof value) {
    case 'string':
    case 'boolean':
      return;
    case 'number':
      if (!Number.isFinite(value)) throw mappingError('HTMX Action numbers must be finite.');
      return;
    case 'undefined':
    case 'bigint':
    case 'symbol':
    case 'function':
      throw mappingError('HTMX Action value is outside the supported JSON-data domain.');
    case 'object':
      break;
    default:
      throw mappingError('HTMX Action value is unsupported.');
  }

  const objectValue = value as object;
  if (stack.has(objectValue)) {
    throw mappingError('HTMX Action value contains a cycle.');
  }
  stack.add(objectValue);

  try {
    if (Array.isArray(value)) {
      const allowedKeys = new Set<string>([
        ...Array.from({ length: value.length }, (_entry, index) => String(index)),
        'length',
      ]);
      const ownKeys = Reflect.ownKeys(value);
      if (
        ownKeys.some((key) => typeof key !== 'string' || !allowedKeys.has(key))
        || ownKeys.length !== allowedKeys.size
      ) {
        throw mappingError('HTMX Action arrays must be dense JSON arrays without extra properties.');
      }

      for (let index = 0; index < value.length; index += 1) {
        const key = String(index);
        if (!hasOwn(value, key)) {
          throw mappingError('HTMX Action arrays must not contain sparse holes.');
        }
        assertJsonData(assertDataProperty(value, key).value, stack);
      }
      return;
    }

    const prototype = Object.getPrototypeOf(value);
    if (prototype !== Object.prototype && prototype !== null) {
      throw mappingError('HTMX Action objects must be plain objects.');
    }

    for (const key of Reflect.ownKeys(value)) {
      if (typeof key !== 'string') {
        throw mappingError('HTMX Action objects must not contain symbol keys.');
      }
      assertJsonData(assertDataProperty(value, key).value, stack);
    }
  } finally {
    stack.delete(objectValue);
  }
}

function encodeActionValue(value: unknown): string {
  assertJsonData(value, new Set<object>());

  if (typeof value === 'string') return value;
  if (value === null) return 'null';
  if (typeof value === 'number' || typeof value === 'boolean') return String(value);

  const encoded = JSON.stringify(value);
  if (typeof encoded !== 'string') {
    throw mappingError('HTMX Action value could not be encoded deterministically.');
  }
  return encoded;
}

export function mapHtmxActionInput(
  target: HtmxBindingTarget,
  input: Record<string, unknown>,
): Readonly<Record<string, string>> {
  const allowed = new Set(target.inputNames);
  const mapped: Record<string, string> = {};

  for (const key of Reflect.ownKeys(input)) {
    if (typeof key !== 'string') {
      throw mappingError('HTMX Action input must not contain symbol keys.');
    }
    if (!allowed.has(key)) {
      throw mappingError(`HTMX Action input contains unknown key "${key}".`);
    }

    const descriptor = assertDataProperty(input, key);
    mapped[key] = encodeActionValue(descriptor.value);
  }

  for (const required of target.requiredInputNames) {
    if (!hasOwn(input, required)) {
      throw mappingError(`HTMX Action input is missing required key "${required}".`);
    }
  }

  return Object.freeze(mapped);
}
```

Do not sort or flatten nested object keys into form paths. The driver preserves one top-level Action name per mapped value.

- [ ] **Step 5: Run focused tests and typecheck**

```bash
npm test -- tests/htmx-input-mapping.test.ts
npm run typecheck
```

Expected: PASS.

- [ ] **Step 6: Commit Task 3 GREEN**

```bash
git add \
  packages/browser-runtime/src/htmx-input-mapping.ts \
  packages/browser-runtime/tests/htmx-input-mapping.test.ts
git commit -m "feat(htmx): map deterministic action input values"
```

---

### Task 4: Implement the Exact-Source HTMX Browser Driver Core

**Files:**
- Create: `packages/browser-runtime/src/htmx-browser-driver.ts`
- Create: `packages/browser-runtime/tests/htmx-browser-driver.test.ts`

**Interfaces:**
- Consumes:
  - `parseHtmxBindingTarget(binding)` from T-601.
  - `classifyRuntimeBindingExpiry()` and `BrowserClock` from Task 1.
  - `HtmxBrowserRuntime`, `HtmxSourceElement`, `HtmxAjaxMethod` from Task 2.
  - `mapHtmxActionInput()` from Task 3.
- Produces:

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

The class must implement `BindingDriver` directly. Task 5 hardens source-policy/busy behavior; Task 6 locks cancellation truth.

- [ ] **Step 1: Write RED tests for binding/expiry/runtime/source/request/same-origin/dispatch**

Create `packages/browser-runtime/tests/htmx-browser-driver.test.ts` with reusable fakes:

```ts
import { describe, expect, it, vi } from 'vitest';
import { HtmxBrowserDriver } from '../src/htmx-browser-driver.js';
import { HtmxBindingExecutionError } from '../src/htmx-errors.js';
import type {
  HtmxAjaxContext,
  HtmxAjaxMethod,
  HtmxBrowserRuntime,
  HtmxSourceElement,
} from '../src/htmx-browser-runtime.js';
import type { RuntimeBinding } from '../src/types.js';

const now = new Date('2026-09-12T00:00:00.000Z');

class FakeSource implements HtmxSourceElement {
  readonly classTokens = new Set<string>();
  readonly attrs = new Map<string, string>();
  parentElement: HtmxSourceElement | null = null;

  constructor(
    readonly tagName = 'BUTTON',
    attrs: Record<string, string> = {},
  ) {
    Object.entries(attrs).forEach(([name, value]) => this.attrs.set(name, value));
  }

  readonly classList = {
    contains: (token: string) => this.classTokens.has(token),
  };

  hasAttribute(name: string): boolean {
    return this.attrs.has(name);
  }

  getAttribute(name: string): string | null {
    return this.attrs.get(name) ?? null;
  }
}

class FakeRuntime implements HtmxBrowserRuntime {
  readonly assertSupported = vi.fn();
  readonly sources: HtmxSourceElement[] = [];
  readonly findSources = vi.fn((_sourceId: string) => this.sources);
  readonly currentLocation = vi.fn(() => ({
    href: 'https://example.test/page',
    origin: 'https://example.test',
  }));
  readonly requestClass = vi.fn(() => 'htmx-request');
  readonly ajax = vi.fn(async (
    _method: HtmxAjaxMethod,
    _path: string,
    _context: HtmxAjaxContext,
  ) => undefined);
}

function binding(overrides: Partial<RuntimeBinding> = {}): RuntimeBinding {
  return {
    bindingId: 'binding-htmx-1',
    action: { id: 'prep_list.add_item', version: 1 },
    driver: 'htmx',
    lifecycle: 'page',
    target: {
      sourceId: 'src-1',
      method: 'POST',
      path: '/items',
      inputNames: ['item', 'note'],
      requiredInputNames: ['item'],
    },
    expiresAt: null,
    ...overrides,
  };
}

function source(attrs: Record<string, string> = {}): FakeSource {
  return new FakeSource('BUTTON', {
    'data-surfacerelay-htmx-source': 'src-1',
    'hx-post': '/items',
    ...attrs,
  });
}

async function expectCode(promise: Promise<unknown>, code: string): Promise<void> {
  try {
    await promise;
    throw new Error('expected HTMX execution failure');
  } catch (error) {
    expect(error).toBeInstanceOf(HtmxBindingExecutionError);
    expect((error as HtmxBindingExecutionError).code).toBe(code);
  }
}
```

Add these first driver tests:

```ts
describe('HtmxBrowserDriver core', () => {
  it('rejects another driver/lifecycle before HTMX runtime access', async () => {
    const runtime = new FakeRuntime();
    const driver = new HtmxBrowserDriver(runtime, { now: () => now });

    await expectCode(driver.execute(binding({ driver: 'livewire' }), {}, {}), 'binding_target_invalid');
    await expectCode(driver.execute(binding({ lifecycle: 'component' }), {}, {}), 'binding_target_invalid');
    expect(runtime.assertSupported).not.toHaveBeenCalled();
  });

  it('rejects malformed and expired binding expiry before source lookup', async () => {
    const runtime = new FakeRuntime();
    const driver = new HtmxBrowserDriver(runtime, { now: () => now });

    await expectCode(driver.execute(binding({ expiresAt: 'bad-date' }), { item: 'coffee' }, {}), 'binding_target_invalid');
    await expectCode(driver.execute(binding({ expiresAt: '2026-09-12T00:00:00Z' }), { item: 'coffee' }, {}), 'binding_expired');
    expect(runtime.findSources).not.toHaveBeenCalled();
  });

  it('requires exactly one source instance', async () => {
    const runtime = new FakeRuntime();
    const driver = new HtmxBrowserDriver(runtime, { now: () => now });

    await expectCode(driver.execute(binding(), { item: 'coffee' }, {}), 'binding_stale');

    runtime.sources.push(source(), source());
    await expectCode(driver.execute(binding(), { item: 'coffee' }, {}), 'binding_stale');
    expect(runtime.ajax).not.toHaveBeenCalled();
  });

  it.each([
    ['GET', 'hx-get', 'get'],
    ['POST', 'hx-post', 'post'],
    ['PUT', 'hx-put', 'put'],
    ['PATCH', 'hx-patch', 'patch'],
    ['DELETE', 'hx-delete', 'delete'],
    ['POST', 'data-hx-post', 'post'],
  ] as const)('dispatches %s through one exact physical %s declaration', async (method, attribute, ajaxMethod) => {
    const runtime = new FakeRuntime();
    const target = source({ 'hx-post': '' });
    target.attrs.delete('hx-post');
    target.attrs.set(attribute, '/items');
    runtime.sources.push(target);

    const driver = new HtmxBrowserDriver(runtime, { now: () => now });
    await expect(driver.execute(binding({
      target: {
        sourceId: 'src-1',
        method,
        path: '/items',
        inputNames: ['item'],
        requiredInputNames: ['item'],
      },
    }), { item: 'coffee' }, {})).resolves.toBeUndefined();

    expect(runtime.ajax).toHaveBeenCalledExactlyOnceWith(
      ajaxMethod,
      '/items',
      { source: target, values: Object.freeze({ item: 'coffee' }) },
    );
  });

  it('fails stale on missing, duplicate, method-drift, and raw-path-drift declarations', async () => {
    const runtime = new FakeRuntime();
    const driver = new HtmxBrowserDriver(runtime, { now: () => now });

    runtime.sources.push(source({ 'hx-post': '/items/' }));
    await expectCode(driver.execute(binding(), { item: 'coffee' }, {}), 'binding_stale');

    runtime.sources[0] = source({ 'hx-post': '/items', 'data-hx-post': '/items' });
    await expectCode(driver.execute(binding(), { item: 'coffee' }, {}), 'binding_stale');

    runtime.sources[0] = source({ 'hx-post': '/items?b=2&a=1' });
    await expectCode(driver.execute(binding({
      target: { ...binding().target, path: '/items?a=1&b=2' },
    }), { item: 'coffee' }, {}), 'binding_stale');
  });

  it('checks same-origin before ajax', async () => {
    const runtime = new FakeRuntime();
    runtime.sources.push(source());
    runtime.currentLocation.mockReturnValue({
      href: 'https://example.test/page',
      origin: 'https://different.test',
    });
    const driver = new HtmxBrowserDriver(runtime, { now: () => now });

    await expectCode(driver.execute(binding(), { item: 'coffee' }, {}), 'binding_target_invalid');
    expect(runtime.ajax).not.toHaveBeenCalled();
  });

  it('preserves the exact underlying HTMX rejection', async () => {
    const runtime = new FakeRuntime();
    runtime.sources.push(source());
    const original = new Error('network failed');
    runtime.ajax.mockRejectedValueOnce(original);
    const driver = new HtmxBrowserDriver(runtime, { now: () => now });

    await expect(driver.execute(binding(), { item: 'coffee' }, {})).rejects.toBe(original);
  });
});
```

- [ ] **Step 2: Run driver test and verify RED**

```bash
npm test -- tests/htmx-browser-driver.test.ts
```

Expected: FAIL because `htmx-browser-driver.ts` does not exist.

- [ ] **Step 3: Commit the RED checkpoint**

```bash
git add packages/browser-runtime/tests/htmx-browser-driver.test.ts
git commit -m "test(htmx): define exact browser driver contract"
```

- [ ] **Step 4: Implement descriptor/expiry normalization, exact request parsing, same-origin check, and dispatch**

Create `packages/browser-runtime/src/htmx-browser-driver.ts`:

```ts
import {
  HtmxBindingDescriptorError,
  parseHtmxBindingTarget,
  type HtmxBindingTarget,
  type HtmxRequestMethod,
} from './htmx-binding-descriptor.js';
import { HtmxBindingExecutionError } from './htmx-errors.js';
import {
  type HtmxAjaxMethod,
  type HtmxBrowserRuntime,
  type HtmxSourceElement,
} from './htmx-browser-runtime.js';
import { mapHtmxActionInput } from './htmx-input-mapping.js';
import {
  classifyRuntimeBindingExpiry,
  systemBrowserClock,
  type BrowserClock,
} from './runtime-binding-expiry.js';
import type {
  BindingDriver,
  DriverExecutionContext,
  RuntimeBinding,
} from './types.js';

interface SourceRequest {
  readonly method: HtmxRequestMethod;
  readonly path: string;
}

const REQUEST_ATTRIBUTES = [
  ['GET', 'hx-get'],
  ['GET', 'data-hx-get'],
  ['POST', 'hx-post'],
  ['POST', 'data-hx-post'],
  ['PUT', 'hx-put'],
  ['PUT', 'data-hx-put'],
  ['PATCH', 'hx-patch'],
  ['PATCH', 'data-hx-patch'],
  ['DELETE', 'hx-delete'],
  ['DELETE', 'data-hx-delete'],
] as const satisfies readonly (readonly [HtmxRequestMethod, string])[];

const AJAX_METHOD: Record<HtmxRequestMethod, HtmxAjaxMethod> = {
  GET: 'get',
  POST: 'post',
  PUT: 'put',
  PATCH: 'patch',
  DELETE: 'delete',
};

function executionError(
  code: HtmxBindingExecutionError['code'],
  message: string,
): HtmxBindingExecutionError {
  return new HtmxBindingExecutionError(code, message);
}

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

function exactSourceRequest(source: HtmxSourceElement): SourceRequest {
  const found: Array<SourceRequest & { attribute: string }> = [];
  for (const [method, attribute] of REQUEST_ATTRIBUTES) {
    if (!source.hasAttribute(attribute)) continue;
    const path = source.getAttribute(attribute);
    found.push({ method, path: path ?? '', attribute });
  }

  if (found.length !== 1) {
    throw executionError(
      'binding_stale',
      'Exact HTMX source must declare exactly one supported physical request attribute.',
    );
  }

  return { method: found[0].method, path: found[0].path };
}

function assertSourceRequestMatches(
  source: HtmxSourceElement,
  target: HtmxBindingTarget,
): void {
  const request = exactSourceRequest(source);
  if (request.method !== target.method || request.path !== target.path) {
    throw executionError('binding_stale', 'Exact HTMX source request method/path no longer matches the binding.');
  }
}

function assertSameOrigin(
  target: HtmxBindingTarget,
  runtime: HtmxBrowserRuntime,
): void {
  const location = runtime.currentLocation();
  let resolved: URL;
  try {
    resolved = new URL(target.path, location.href);
  } catch {
    throw executionError('binding_target_invalid', 'HTMX binding path cannot be resolved against current location.');
  }

  if (resolved.origin !== location.origin) {
    throw executionError('binding_target_invalid', 'HTMX binding path resolves outside the current origin.');
  }
}

export class HtmxBrowserDriver implements BindingDriver {
  constructor(
    private readonly runtime: HtmxBrowserRuntime,
    private readonly clock: BrowserClock = systemBrowserClock,
  ) {}

  async execute(
    binding: RuntimeBinding,
    input: Record<string, unknown>,
    context: DriverExecutionContext,
  ): Promise<unknown> {
    if (context.signal?.aborted) throw context.signal.reason;

    const target = parseTarget(binding);
    const expiry = classifyRuntimeBindingExpiry(binding.expiresAt, this.clock.now());
    if (expiry === 'invalid') {
      throw executionError('binding_target_invalid', 'HTMX binding expiresAt is not a valid RFC3339 date-time.');
    }
    if (expiry === 'expired') {
      throw executionError('binding_expired', 'HTMX binding has expired.');
    }

    this.runtime.assertSupported();
    const sources = this.runtime.findSources(target.sourceId);
    if (sources.length !== 1) {
      throw executionError('binding_stale', 'Exact HTMX source instance is missing or duplicated.');
    }

    const source = sources[0];
    assertSourceRequestMatches(source, target);
    assertSameOrigin(target, this.runtime);
    const values = mapHtmxActionInput(target, input);

    if (context.signal?.aborted) throw context.signal.reason;

    return this.runtime.ajax(AJAX_METHOD[target.method], target.path, { source, values });
  }
}
```

Do not add source-modifier or busy logic yet; Task 5 adds those gates with explicit RED tests.

- [ ] **Step 5: Run driver, mapper, runtime, expiry and Livewire regressions**

```bash
npm test -- \
  tests/htmx-browser-driver.test.ts \
  tests/htmx-input-mapping.test.ts \
  tests/htmx-browser-runtime.test.ts \
  tests/runtime-binding-expiry.test.ts \
  tests/livewire-browser-driver.test.ts \
  tests/livewire-cancellation.test.ts
npm run typecheck
```

Expected: PASS.

- [ ] **Step 6: Commit Task 4 GREEN**

```bash
git add \
  packages/browser-runtime/src/htmx-browser-driver.ts \
  packages/browser-runtime/tests/htmx-browser-driver.test.ts
git commit -m "feat(htmx): execute exact browser binding source"
```

---

### Task 5: Fail Closed on Unsupported HTMX Source Modifiers and Busy Sources

**Files:**
- Modify: `packages/browser-runtime/src/htmx-browser-driver.ts`
- Modify: `packages/browser-runtime/tests/htmx-browser-driver.test.ts`

**Interfaces:**
- No new public API.
- Strengthens `HtmxBrowserDriver.execute()` before `runtime.ajax()`.
- Error outcomes:

```text
unsupported modifier / active validation -> htmx_source_unsupported
busy exact source                        -> htmx_source_busy
```

- [ ] **Step 1: Add RED source-policy tests**

Append to `htmx-browser-driver.test.ts`:

```ts
describe('HtmxBrowserDriver source policy', () => {
  it.each([
    'hx-vals',
    'data-hx-vals',
    'hx-vars',
    'data-hx-vars',
    'hx-confirm',
    'data-hx-confirm',
    'hx-prompt',
    'data-hx-prompt',
    'hx-sync',
    'data-hx-sync',
    'hx-indicator',
    'data-hx-indicator',
    'hx-ext',
    'data-hx-ext',
  ])('rejects source modifier %s before ajax', async (attribute) => {
    const runtime = new FakeRuntime();
    runtime.sources.push(source({ [attribute]: attribute.includes('sync') ? 'this:queue last' : 'x' }));
    const driver = new HtmxBrowserDriver(runtime, { now: () => now });

    await expectCode(driver.execute(binding(), { item: 'coffee' }, {}), 'htmx_source_unsupported');
    expect(runtime.ajax).not.toHaveBeenCalled();
  });

  it('rejects unsupported inherited modifier conservatively', async () => {
    const runtime = new FakeRuntime();
    const parent = new FakeSource('DIV', { 'hx-vals': '{"item":"host"}' });
    const child = source();
    child.parentElement = parent;
    runtime.sources.push(child);
    const driver = new HtmxBrowserDriver(runtime, { now: () => now });

    await expectCode(driver.execute(binding(), { item: 'coffee' }, {}), 'htmx_source_unsupported');
  });

  it.each(['none', 'item', 'not item'])('rejects restrictive inherited hx-params=%s', async (value) => {
    const runtime = new FakeRuntime();
    const parent = new FakeSource('DIV', { 'hx-params': value });
    const child = source();
    child.parentElement = parent;
    runtime.sources.push(child);
    const driver = new HtmxBrowserDriver(runtime, { now: () => now });

    await expectCode(driver.execute(binding(), { item: 'coffee' }, {}), 'htmx_source_unsupported');
  });

  it('allows absent or star hx-params and ordinary host modifiers', async () => {
    const runtime = new FakeRuntime();
    runtime.sources.push(source({
      'hx-params': '*',
      'hx-include': '#csrf',
      'hx-headers': '{"X-View":"items"}',
      'hx-request': '{"timeout":1000}',
      'hx-target': '#result',
      'hx-swap': 'outerHTML',
      'hx-encoding': 'multipart/form-data',
    }));
    const driver = new HtmxBrowserDriver(runtime, { now: () => now });

    await expect(driver.execute(binding(), { item: 'coffee' }, {})).resolves.toBeUndefined();
    expect(runtime.ajax).toHaveBeenCalledTimes(1);
  });

  it('rejects FORM source with active validation but allows explicit novalidate', async () => {
    const runtime = new FakeRuntime();
    runtime.sources.push(new FakeSource('FORM', {
      'data-surfacerelay-htmx-source': 'src-1',
      'hx-post': '/items',
    }));
    const driver = new HtmxBrowserDriver(runtime, { now: () => now });

    await expectCode(driver.execute(binding(), { item: 'coffee' }, {}), 'htmx_source_unsupported');

    runtime.sources[0] = new FakeSource('FORM', {
      'data-surfacerelay-htmx-source': 'src-1',
      'hx-post': '/items',
      novalidate: '',
    });
    await expect(driver.execute(binding(), { item: 'coffee' }, {})).resolves.toBeUndefined();
  });

  it('rejects exact source hx-validate=true', async () => {
    const runtime = new FakeRuntime();
    runtime.sources.push(source({ 'hx-validate': 'true' }));
    const driver = new HtmxBrowserDriver(runtime, { now: () => now });

    await expectCode(driver.execute(binding(), { item: 'coffee' }, {}), 'htmx_source_unsupported');
  });

  it('fails busy exact source instead of entering HTMX queue behavior', async () => {
    const runtime = new FakeRuntime();
    const target = source();
    target.classTokens.add('htmx-request');
    runtime.sources.push(target);
    const driver = new HtmxBrowserDriver(runtime, { now: () => now });

    await expectCode(driver.execute(binding(), { item: 'coffee' }, {}), 'htmx_source_busy');
    expect(runtime.ajax).not.toHaveBeenCalled();
  });
});
```

- [ ] **Step 2: Run focused driver test and verify RED**

```bash
npm test -- tests/htmx-browser-driver.test.ts
```

Expected: FAIL because unsupported modifier/validation/busy gates are not implemented.

- [ ] **Step 3: Commit the RED checkpoint**

```bash
git add packages/browser-runtime/tests/htmx-browser-driver.test.ts
git commit -m "test(htmx): lock source policy and busy semantics"
```

- [ ] **Step 4: Implement conservative source-policy scanning**

Add these helpers to `htmx-browser-driver.ts`:

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

function physicalAttributeValues(
  source: HtmxSourceElement,
  name: string,
): readonly string[] {
  const values: string[] = [];
  for (const attribute of [name, `data-${name}`]) {
    if (source.hasAttribute(attribute)) {
      values.push(source.getAttribute(attribute) ?? '');
    }
  }
  return values;
}

function assertReferenceSourceSupported(source: HtmxSourceElement): void {
  for (let node: HtmxSourceElement | null = source; node !== null; node = node.parentElement) {
    for (const name of UNSUPPORTED_INHERITED_ATTRIBUTES) {
      if (physicalAttributeValues(node, name).length > 0) {
        throw executionError(
          'htmx_source_unsupported',
          `HTMX reference source uses unsupported ${name} behavior.`,
        );
      }
    }

    for (const value of physicalAttributeValues(node, 'hx-params')) {
      if (value !== '*') {
        throw executionError(
          'htmx_source_unsupported',
          'HTMX reference source uses restrictive hx-params behavior.',
        );
      }
    }
  }

  if (
    physicalAttributeValues(source, 'hx-validate').some((value) => value === 'true')
  ) {
    throw executionError(
      'htmx_source_unsupported',
      'HTMX reference source enables browser validation.',
    );
  }

  if (source.tagName.toUpperCase() === 'FORM' && !source.hasAttribute('novalidate')) {
    throw executionError(
      'htmx_source_unsupported',
      'HTMX reference FORM source must disable browser validation explicitly.',
    );
  }
}

function assertSourceNotBusy(
  source: HtmxSourceElement,
  runtime: HtmxBrowserRuntime,
): void {
  const requestClass = runtime.requestClass();
  if (source.classList.contains(requestClass)) {
    throw executionError(
      'htmx_source_busy',
      'Exact HTMX source is already processing another request.',
    );
  }
}
```

Call both helpers after exact source/request matching and before input mapping / final cancellation / `ajax()`:

```ts
assertReferenceSourceSupported(source);
assertSourceNotBusy(source, this.runtime);
```

Do not evaluate `hx-inherit`/`hx-disinherit`; conservative ancestor rejection is intentional.

- [ ] **Step 5: Run driver + mapping + runtime tests**

```bash
npm test -- \
  tests/htmx-browser-driver.test.ts \
  tests/htmx-input-mapping.test.ts \
  tests/htmx-browser-runtime.test.ts
npm run typecheck
```

Expected: PASS.

- [ ] **Step 6: Commit Task 5 GREEN**

```bash
git add \
  packages/browser-runtime/src/htmx-browser-driver.ts \
  packages/browser-runtime/tests/htmx-browser-driver.test.ts
git commit -m "feat(htmx): fail closed on unsafe source state"
```

---

### Task 6: Lock the HTMX Cancellation Frontier and Natural Result Semantics

**Files:**
- Create: `packages/browser-runtime/tests/htmx-cancellation.test.ts`
- Modify only if required by RED evidence: `packages/browser-runtime/src/htmx-browser-driver.ts`

**Interfaces:**
- No new public API.
- Required dispatch frontier: the synchronous call to `runtime.ajax()`.
- No AbortSignal listener after that call.

- [ ] **Step 1: Write RED/characterization cancellation tests**

Create `packages/browser-runtime/tests/htmx-cancellation.test.ts` with a minimal valid fake runtime/source; use a deferred helper:

```ts
import { describe, expect, it, vi } from 'vitest';
import { HtmxBrowserDriver } from '../src/htmx-browser-driver.js';
import type {
  HtmxAjaxContext,
  HtmxAjaxMethod,
  HtmxBrowserRuntime,
  HtmxSourceElement,
} from '../src/htmx-browser-runtime.js';
import type { RuntimeBinding } from '../src/types.js';

function deferred<T>() {
  let resolve!: (value: T | PromiseLike<T>) => void;
  let reject!: (reason?: unknown) => void;
  const promise = new Promise<T>((res, rej) => {
    resolve = res;
    reject = rej;
  });
  return { promise, resolve, reject };
}

const source: HtmxSourceElement = {
  tagName: 'BUTTON',
  parentElement: null,
  classList: { contains: () => false },
  hasAttribute(name) {
    return name === 'data-surfacerelay-htmx-source' || name === 'hx-post';
  },
  getAttribute(name) {
    if (name === 'data-surfacerelay-htmx-source') return 'src-1';
    if (name === 'hx-post') return '/items';
    return null;
  },
};

function binding(): RuntimeBinding {
  return {
    bindingId: 'binding-1',
    action: { id: 'prep_list.add_item', version: 1 },
    driver: 'htmx',
    lifecycle: 'page',
    target: {
      sourceId: 'src-1',
      method: 'POST',
      path: '/items',
      inputNames: ['item'],
      requiredInputNames: ['item'],
    },
    expiresAt: null,
  };
}

function runtime(ajax: HtmxBrowserRuntime['ajax']): HtmxBrowserRuntime & {
  assertSupported: ReturnType<typeof vi.fn>;
  findSources: ReturnType<typeof vi.fn>;
} {
  return {
    assertSupported: vi.fn(),
    findSources: vi.fn(() => [source]),
    currentLocation: () => ({ href: 'https://example.test/page', origin: 'https://example.test' }),
    requestClass: () => 'htmx-request',
    ajax,
  };
}
```

Add the truth-table tests:

```ts
describe('HTMX cancellation frontier', () => {
  it('surfaces an already-aborted reason before runtime access', async () => {
    const ajax = vi.fn(async () => undefined);
    const fake = runtime(ajax);
    const controller = new AbortController();
    const reason = new Error('caller cancelled before invocation');
    controller.abort(reason);

    await expect(new HtmxBrowserDriver(fake).execute(
      binding(),
      { item: 'coffee' },
      { signal: controller.signal },
    )).rejects.toBe(reason);

    expect(fake.assertSupported).not.toHaveBeenCalled();
    expect(fake.findSources).not.toHaveBeenCalled();
    expect(ajax).not.toHaveBeenCalled();
  });

  it('rechecks cancellation synchronously immediately before ajax', async () => {
    const controller = new AbortController();
    const reason = new Error('caller cancelled before dispatch');
    const ajax = vi.fn(async () => undefined);
    const fake = runtime(ajax);
    fake.requestClass = () => {
      controller.abort(reason);
      return 'htmx-request';
    };

    await expect(new HtmxBrowserDriver(fake).execute(
      binding(),
      { item: 'coffee' },
      { signal: controller.signal },
    )).rejects.toBe(reason);

    expect(ajax).not.toHaveBeenCalled();
  });

  it('treats ajax invocation as the frontier and preserves later natural success', async () => {
    const controller = new AbortController();
    const pending = deferred<void>();
    const ajax = vi.fn((
      _method: HtmxAjaxMethod,
      _path: string,
      _context: HtmxAjaxContext,
    ) => {
      controller.abort(new Error('caller stopped observing'));
      return pending.promise;
    });
    const fake = runtime(ajax);

    const execution = new HtmxBrowserDriver(fake).execute(
      binding(),
      { item: 'coffee' },
      { signal: controller.signal },
    );
    pending.resolve(undefined);

    await expect(execution).resolves.toBeUndefined();
    expect(ajax).toHaveBeenCalledTimes(1);
  });

  it('preserves the exact natural HTMX failure after a later caller abort', async () => {
    const controller = new AbortController();
    const pending = deferred<void>();
    const original = new Error('network failed');
    const ajax = vi.fn(() => pending.promise);
    const fake = runtime(ajax);

    const execution = new HtmxBrowserDriver(fake).execute(
      binding(),
      { item: 'coffee' },
      { signal: controller.signal },
    );

    controller.abort(new Error('caller stopped observing'));
    pending.reject(original);

    await expect(execution).rejects.toBe(original);
  });

  it('keeps no-signal execution on the same ajax path', async () => {
    const ajax = vi.fn(async () => undefined);
    const fake = runtime(ajax);

    await expect(new HtmxBrowserDriver(fake).execute(
      binding(),
      { item: 'coffee' },
      {},
    )).resolves.toBeUndefined();
    expect(ajax).toHaveBeenCalledTimes(1);
  });
});
```

- [ ] **Step 2: Run cancellation tests**

```bash
npm test -- tests/htmx-cancellation.test.ts
```

Expected: either PASS immediately if Task 4/5 already placed the final synchronous abort check correctly, or FAIL only where the implementation contradicts the approved frontier. Do not weaken the tests to make them pass.

- [ ] **Step 3: If RED, make the minimal driver correction**

The final section before dispatch in `HtmxBrowserDriver.execute()` must be exactly this shape:

```ts
const values = mapHtmxActionInput(target, input);

if (context.signal?.aborted) throw context.signal.reason;

return this.runtime.ajax(AJAX_METHOD[target.method], target.path, { source, values });
```

Do not add:

```text
signal.addEventListener(...)
Promise.race(...)
htmx:abort
runtime.trigger(...)
XHR.abort()
```

- [ ] **Step 4: Run HTMX + Livewire cancellation regressions and typecheck**

```bash
npm test -- \
  tests/htmx-cancellation.test.ts \
  tests/htmx-browser-driver.test.ts \
  tests/livewire-cancellation.test.ts
npm run typecheck
```

Expected: PASS. Livewire keeps its framework-specific documented cancellation mechanism; HTMX keeps the narrower pre-ajax frontier.

- [ ] **Step 5: Commit Task 6 GREEN**

```bash
git add \
  packages/browser-runtime/src/htmx-browser-driver.ts \
  packages/browser-runtime/tests/htmx-cancellation.test.ts
git commit -m "test(htmx): lock cancellation dispatch frontier"
```

If the driver required no production change, commit only the new test file with the same message.

---

### Task 7: Prove DriverRegistry and WebMCP Integration Without Shared Conformance Claims

**Files:**
- Create: `packages/browser-runtime/tests/htmx-webmcp-integration.test.ts`
- Regression: `packages/browser-runtime/src/driver-registry.ts` — unchanged
- Regression: `packages/browser-runtime/src/webmcp-registration-lifecycle.ts` — unchanged
- Regression: `packages/browser-runtime/tests/livewire-webmcp-integration.test.ts`

**Interfaces:**
- Consumes: existing `DriverRegistry.register('htmx', driver)` and `WebMcpRegistrationLifecycle`.
- Produces: executable proof that a registered WebMCP tool dispatches to the exact HTMX binding/source and returns `undefined`.

- [ ] **Step 1: Write RED integration tests**

Create `packages/browser-runtime/tests/htmx-webmcp-integration.test.ts`:

```ts
import { describe, expect, it, vi } from 'vitest';
import { DriverRegistry } from '../src/driver-registry.js';
import { HtmxBrowserDriver } from '../src/htmx-browser-driver.js';
import { HtmxBindingExecutionError } from '../src/htmx-errors.js';
import type {
  HtmxAjaxContext,
  HtmxAjaxMethod,
  HtmxBrowserRuntime,
  HtmxSourceElement,
} from '../src/htmx-browser-runtime.js';
import { WebMcpRegistrationLifecycle } from '../src/webmcp-registration-lifecycle.js';
import type { WebMcpModelContext, WebMcpTool } from '../src/webmcp-types.js';
import type { ActionDefinition, RuntimeBinding } from '../src/types.js';

function definition(): ActionDefinition {
  return {
    id: 'prep_list.add_item',
    version: 1,
    title: 'Add preparation item',
    description: 'Adds one item through the current page interaction.',
    inputSchema: {
      type: 'object',
      additionalProperties: false,
      properties: { item: { type: 'string' } },
      required: ['item'],
    },
    outputSchema: null,
    scope: 'page_scoped',
    effect: 'reversible_write',
    risk: 'low',
    idempotency: 'none',
    outputSensitivity: 'normal',
    outputContentTrust: 'trusted_application_data',
    contextRequirements: [],
  };
}

function binding(sourceId = 'src-old'): RuntimeBinding {
  return {
    bindingId: `binding:${sourceId}`,
    action: { id: 'prep_list.add_item', version: 1 },
    driver: 'htmx',
    lifecycle: 'page',
    target: {
      sourceId,
      method: 'POST',
      path: '/items',
      inputNames: ['item'],
      requiredInputNames: ['item'],
    },
    expiresAt: null,
  };
}

function source(sourceId: string): HtmxSourceElement {
  return {
    tagName: 'BUTTON',
    parentElement: null,
    classList: { contains: () => false },
    hasAttribute(name) {
      return name === 'data-surfacerelay-htmx-source' || name === 'hx-post';
    },
    getAttribute(name) {
      if (name === 'data-surfacerelay-htmx-source') return sourceId;
      if (name === 'hx-post') return '/items';
      return null;
    },
  };
}

class MutableHtmxRuntime implements HtmxBrowserRuntime {
  readonly sources = new Map<string, HtmxSourceElement>();
  readonly assertSupported = vi.fn();
  readonly findSources = vi.fn((sourceId: string) => {
    const found = this.sources.get(sourceId);
    return found ? [found] : [];
  });
  readonly currentLocation = () => ({ href: 'https://example.test/page', origin: 'https://example.test' });
  readonly requestClass = () => 'htmx-request';
  readonly ajax = vi.fn(async (
    _method: HtmxAjaxMethod,
    _path: string,
    _context: HtmxAjaxContext,
  ) => undefined);
}

class RecordingModelContext implements WebMcpModelContext {
  readonly tools: WebMcpTool[] = [];
  async registerTool(tool: WebMcpTool): Promise<void> {
    this.tools.push(tool);
  }
}
```

Add the integration scenarios:

```ts
describe('HTMX WebMCP integration', () => {
  it('dispatches a registered tool through DriverRegistry to the exact HTMX source', async () => {
    const runtime = new MutableHtmxRuntime();
    const exactSource = source('src-old');
    runtime.sources.set('src-old', exactSource);

    const drivers = new DriverRegistry();
    drivers.register('htmx', new HtmxBrowserDriver(runtime));
    const modelContext = new RecordingModelContext();
    const lifecycle = new WebMcpRegistrationLifecycle(modelContext, drivers);

    const lease = await lifecycle.register([{ definition: definition(), binding: binding() }]);

    expect(modelContext.tools).toHaveLength(1);
    expect(modelContext.tools[0].name).toBe('prep_list.add_item.v1');
    await expect(modelContext.tools[0].execute(
      { item: 'coffee' },
      { signal: new AbortController().signal },
    )).resolves.toBeUndefined();

    expect(runtime.findSources).toHaveBeenCalledExactlyOnceWith('src-old');
    expect(runtime.ajax).toHaveBeenCalledExactlyOnceWith(
      'post',
      '/items',
      { source: exactSource, values: Object.freeze({ item: 'coffee' }) },
    );

    lease.dispose();
  });

  it('does not retarget an old binding to a replacement source', async () => {
    const runtime = new MutableHtmxRuntime();
    runtime.sources.set('src-new', source('src-new'));

    const drivers = new DriverRegistry();
    drivers.register('htmx', new HtmxBrowserDriver(runtime));
    const modelContext = new RecordingModelContext();
    const lifecycle = new WebMcpRegistrationLifecycle(modelContext, drivers);

    await lifecycle.register([{ definition: definition(), binding: binding('src-old') }]);

    try {
      await modelContext.tools[0].execute(
        { item: 'coffee' },
        { signal: new AbortController().signal },
      );
      throw new Error('expected stale binding failure');
    } catch (error) {
      expect(error).toBeInstanceOf(HtmxBindingExecutionError);
      expect((error as HtmxBindingExecutionError).code).toBe('binding_stale');
    }

    expect(runtime.findSources).toHaveBeenCalledExactlyOnceWith('src-old');
    expect(runtime.ajax).not.toHaveBeenCalled();
  });
});
```

- [ ] **Step 2: Run HTMX WebMCP integration test**

```bash
npm test -- tests/htmx-webmcp-integration.test.ts
```

Expected: PASS if Tasks 1–6 are correctly integrated; any failure must be fixed in the HTMX driver/runtime, not by modifying generic registry/WebMCP contracts.

- [ ] **Step 3: Run both HTMX and Livewire WebMCP integration tests**

```bash
npm test -- \
  tests/htmx-webmcp-integration.test.ts \
  tests/livewire-webmcp-integration.test.ts \
  tests/webmcp-registration-lifecycle.test.ts \
  tests/driver-registry.test.ts
npm run typecheck
```

Expected: PASS with zero production changes to `driver-registry.ts` or `webmcp-registration-lifecycle.ts`.

- [ ] **Step 4: Commit Task 7 GREEN**

```bash
git add packages/browser-runtime/tests/htmx-webmcp-integration.test.ts
git commit -m "test(htmx): prove WebMCP driver integration"
```

---

### Task 8: Full Verification, Decision Promotion, Tracking, and External-Review Prep

**Files:**
- Modify: `docs/DECISION-REGISTER.md`
- Modify: `TASKS.md`
- Modify: `STATUS.md`
- Modify: `REVIEW_REQUEST.md`
- Verify all T-602 implementation/test files.

**Interfaces:**
- Consumes: completed Tasks 1–7.
- Produces: exact T-602 review-ready checkpoint with D-054/D-055/D-056 accepted, D-020 still proposed, no T-603 implementation.

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

Expected: all focused tests PASS and browser typecheck PASS.

- [ ] **Step 2: Run the complete browser-runtime suite**

```bash
npm test
npm run typecheck
```

Expected: **all 174 pre-T-602 baseline tests plus all new T-602 tests** pass. Record the exact final total; do not delete/weaken prior tests to obtain green.

- [ ] **Step 3: Run repository contract validation**

From repository root:

```bash
python scripts/validate.py
```

Expected: PASS.

- [ ] **Step 4: Prove frozen/Laravel/dependency scope boundaries from the diff**

Run:

```bash
git diff --name-only main...HEAD -- packages/laravel/src spec/0.1
```

Expected: no output.

Run:

```bash
git diff --name-only main...HEAD -- \
  packages/browser-runtime/package.json \
  packages/browser-runtime/package-lock.json
```

Expected: no output.

Inspect the complete change surface:

```bash
git diff --name-only main...HEAD
```

Expected implementation/review paths are limited to:

```text
STATUS.md
TASKS.md
REVIEW_REQUEST.md
docs/DECISION-REGISTER.md
docs/superpowers/specs/2026-09-12-htmx-browser-driver-design.md
docs/superpowers/plans/2026-09-12-htmx-browser-driver.md
packages/browser-runtime/src/runtime-binding-expiry.ts
packages/browser-runtime/src/livewire-browser-driver.ts
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

Any additional production path requires an explicit scope review before proceeding.

- [ ] **Step 5: Self-review all 27 spec acceptance criteria against executable proof**

Create a temporary checklist while reviewing; every row must point to a concrete test or diff proof:

```text
BindingDriver interface unchanged
exact HTMX page descriptor only
HTMX 2.x only
no HTMX dependency
exactly one source
no replacement retargeting
exactly one physical request declaration
exact raw method/path
same-origin check
unknown/missing Action keys fail
recursive JSON-data validation
structured single-name JSON encoding
ordinary host state remains untrusted
unsupported HTMX modifiers fail closed
busy source fails closed
pre-frontier cancellation no dispatch
post-frontier natural result
underlying HTMX error identity
void success semantics
shared expiry preserves Livewire
WebMCP/registry integration
Livewire regressions
full browser suite/typecheck
repo validation/PHP/lint CI
no spec/Laravel/core-contract drift
no T-603/T-604 scope creep
decision promotion boundary
```

If any item lacks a proof, add the missing test/diff proof before tracking changes.

- [ ] **Step 6: Promote D-054, D-055, D-056 only after the complete local green gate**

Update `docs/DECISION-REGISTER.md`:

```text
D-054 -> ACCEPTED for the exact-source host HTMX 2.x execution boundary only.
D-055 -> ACCEPTED for busy-source failure and pre-ajax-only strong cancellation semantics.
D-056 -> ACCEPTED for deterministic Action-input integrity/reference-source exclusions.
D-020 -> stays PROPOSED through T-604.
```

Do not broaden D-054 into a generic HTTP/HTMX portability claim and do not claim T-603/T-604 evidence.

- [ ] **Step 7: Update TASKS.md to review-ready, not T-603**

Record:

```text
T-602 — IMPLEMENTED / SELF-REVIEWED / READY FOR EXTERNAL REVIEW
implementation branch/head
RED/GREEN task commits
design checkpoint
final browser test total + typecheck
shared expiry regression result
focused HTMX driver/runtime/input/cancellation/WebMCP result
python scripts/validate.py result
D-054/D-055/D-056 ACCEPTED
D-020 still PROPOSED
T-603 NOT STARTED
```

Do not mark T-603 in progress and do not claim merge/main revalidation before those events occur.

- [ ] **Step 8: Update STATUS.md with exact implementation state**

Record:

```text
branch + current review-prep head
base main SHA
all changed production/test files
browser baseline and final test total
focused test commands/results
contract validator result
known boundaries: HTMX 2.x only, no real browser fixture yet, no post-ajax cancellation claim, host configRequest hooks outside SurfaceRelay sandbox
next gate: external review
next task T-603 remains NOT STARTED
```

- [ ] **Step 9: Replace REVIEW_REQUEST.md with concise T-602 review focus**

The handoff must ask reviewers to examine these concrete risks:

```text
exact source duplication/replacement retarget risk
physical hx-* method/path ambiguity
same-origin boundary and host configRequest disclaimer
JSON input coercion/accessor/sparse-array/custom-object escape paths
hx-vals/hx-vars/hx-params inheritance escape paths
hx-confirm/hx-prompt/hx-sync/hx-indicator/hx-ext scope gaps
busy-source false negative leading to silent HTMX queueing
pre-vs-post ajax cancellation truthfulness
accidental generic DriverRegistry/WebMCP contract changes
Livewire expiry behavior drift after extraction
HTMX dependency/DOM-emulator/frozen-contract/Laravel scope creep
```

Keep it short enough for an external reviewer to act on directly.

- [ ] **Step 10: Commit review-prep tracking**

```bash
git add \
  docs/DECISION-REGISTER.md \
  TASKS.md \
  STATUS.md \
  REVIEW_REQUEST.md
git commit -m "docs(htmx): prepare T-602 external review"
```

- [ ] **Step 11: Verify the exact review-prep head in repository CI**

Verify the existing `validate` workflow on the exact review-prep head. Required jobs:

```text
contract
php-lint
browser
php-tests PHP 8.3 / Illuminate 12
php-tests PHP 8.4 / Illuminate 12
php-tests PHP 8.3 / Illuminate 13
php-tests PHP 8.4 / Illuminate 13
```

All **7/7** must be green before reporting T-602 review-ready status.

- [ ] **Step 12: Stop at the external-review gate**

Do not create a PR, merge, delete the branch, begin T-603, run shared T-604 conformance, or promote D-020. Report the exact review-ready head, exact CI run, final browser test total, and decision state; wait for explicit authorization for external review/PR handling.

---

## Plan Self-Review Result

- Spec coverage: all 27 T-602 acceptance criteria map to Tasks 1–8.
- Placeholder scan: no TBD/TODO/“implement later”/generic “add validation” steps remain.
- Type consistency: `BrowserClock`, `RuntimeBindingExpiryState`, `HtmxBindingExecutionError`, `HtmxAjaxMethod`, `HtmxSourceElement`, `HtmxBrowserRuntime`, `mapHtmxActionInput()`, and `HtmxBrowserDriver` are defined once and reused consistently.
- File-boundary check: no new barrel export, generic HTTP abstraction, HTMX dependency, DOM emulator, Laravel production adapter, or frozen schema change is planned.
- Cancellation check: HTMX uses the narrower `runtime.ajax()` invocation frontier; Livewire keeps its existing documented interceptor/onSend behavior.
- Input-integrity check: nested Action objects/arrays are JSON-string encoded under one top-level name; no dotted/bracket flattening is introduced.
- Host-state check: ordinary host form/request state is allowed only as untrusted state; no actor/tenant/record/selection/confirmation/idempotency authority is manufactured.
- T-603/T-604 boundary: no real browser fixture/server or shared cross-driver conformance is part of this plan.
- Decision boundary: D-054/D-055/D-056 may become `ACCEPTED` only after complete T-602 verification; D-020 stays `PROPOSED` through T-604.
- Review boundary: successful implementation stops at external-review readiness; it does not automatically open/merge a PR or begin T-603.