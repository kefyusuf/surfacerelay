# T-604 Binding Driver Conformance Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add one executable, test-only conformance matrix that proves the genuinely shared browser `BindingDriver` behavior of the production Livewire and HTMX drivers without changing their framework-specific contracts.

**Architecture:** Define the conformance cases once in a Vitest support module. Run that exact suite through two thin test adapters that construct driver-owned bindings and fake runtimes while instantiating the real `LivewireBrowserDriver` and `HtmxBrowserDriver`. Keep all implementation under `packages/browser-runtime/tests/**`; production code changes are not presumed and require a new decision gate if the shared matrix exposes a real semantic mismatch.

**Tech Stack:** Node.js 22, TypeScript 5.9, Vitest 3.2, existing SurfaceRelay browser runtime, existing Playwright 1.63 / Chromium T-603 fixture for regression verification.

**Spec:** `docs/superpowers/specs/2026-09-13-binding-driver-conformance-design.md`

## Global Constraints

- Work on `feat/binding-driver-conformance`; do not implement on `main`.
- `D-058` remains `PROPOSED` during implementation and review preparation.
- `D-020` remains `PROPOSED` during implementation and review preparation.
- Do not change `packages/browser-runtime/src/**` merely to make the conformance harness convenient.
- Do not change `spec/0.1/**`, `packages/laravel/src/**`, `examples/htmx-prep-list/**`, `.github/workflows/**`, or package dependency files for T-604.
- The shared matrix asserts only common observable behavior: target rejection, expiry, input mappability, stale/no-retarget, exact dispatch count, and already-aborted no-dispatch cancellation.
- Preserve different driver-owned target shapes, lifecycle values, runtime APIs, framework-specific errors, cancellation mechanisms, and successful return values.
- Do not normalize successful driver results.
- Do not add a production conformance API, base driver class, generic target model, CLI runner, JSON scenario engine, or adapter discovery system; those are outside T-604 and belong to later conformance work.
- Existing Livewire- and HTMX-specific tests remain authoritative for framework-local behavior.
- If a shared case fails because the two accepted driver contracts are genuinely different rather than because of a test-harness defect, stop implementation and open a new design/decision gate instead of weakening either driver or the shared assertion.
- Baseline before implementation: branch head `d929dd6d37dd2b736cd564a119de5eb4bb87677a`; `validate` run `34790925425` completed successfully; browser baseline remains 17 Vitest files / 297 tests plus TypeScript typecheck.

## File Structure

Create these test-only files:

```text
packages/browser-runtime/tests/
├── binding-driver-conformance.livewire.test.ts
├── binding-driver-conformance.htmx.test.ts
├── binding-driver-conformance.typecheck.ts
└── support/
    ├── binding-driver-conformance-suite.ts
    ├── livewire-conformance-adapter.ts
    └── htmx-conformance-adapter.ts
```

Responsibilities:

- `binding-driver-conformance-suite.ts` — owns the single shared matrix and its test-only adapter/harness interfaces.
- `livewire-conformance-adapter.ts` — constructs valid/invalid Livewire bindings, a controllable fake Livewire runtime, dispatch evidence, stale state, and equivalent replacement state around the production `LivewireBrowserDriver`.
- `htmx-conformance-adapter.ts` — constructs valid/invalid HTMX bindings, exact-source fake runtime state, dispatch evidence, stale state, and equivalent replacement state around the production `HtmxBrowserDriver`.
- `binding-driver-conformance.livewire.test.ts` — invokes the shared matrix with only the Livewire adapter.
- `binding-driver-conformance.htmx.test.ts` — invokes the same shared matrix with only the HTMX adapter.
- `binding-driver-conformance.typecheck.ts` — imports both adapters through the shared interface so `npm run typecheck` includes the support modules even though normal `.test.ts` files are not part of the package TypeScript include pattern.

Reference-only production files:

```text
packages/browser-runtime/src/types.ts
packages/browser-runtime/src/livewire-browser-driver.ts
packages/browser-runtime/src/livewire-browser-runtime.ts
packages/browser-runtime/src/livewire-errors.ts
packages/browser-runtime/src/htmx-browser-driver.ts
packages/browser-runtime/src/htmx-browser-runtime.ts
packages/browser-runtime/src/htmx-errors.ts
```

No production file above is expected to change.

---

### Task 1: Declare the shared matrix and make Livewire pass it

**Files:**
- Create: `packages/browser-runtime/tests/binding-driver-conformance.livewire.test.ts`
- Create: `packages/browser-runtime/tests/support/binding-driver-conformance-suite.ts`
- Create: `packages/browser-runtime/tests/support/livewire-conformance-adapter.ts`
- Reference: `packages/browser-runtime/src/types.ts`
- Reference: `packages/browser-runtime/src/livewire-browser-driver.ts`
- Reference: `packages/browser-runtime/src/livewire-browser-runtime.ts`

**Interfaces:**
- Consumes: production `BindingDriver.execute(binding, input, context)`, `RuntimeBinding`, `LivewireBrowserDriver`, and `LivewireBrowserRuntime.find(componentId)`.
- Produces: `BindingDriverConformanceHarness`, `BindingDriverConformanceAdapter`, `CONFORMANCE_NOW`, `defineBindingDriverConformance()`, and `livewireConformanceAdapter` for Task 2 and the final typecheck sentinel.

- [ ] **Step 1: Write the Livewire conformance entry first**

Create `packages/browser-runtime/tests/binding-driver-conformance.livewire.test.ts` with exactly:

```ts
import { defineBindingDriverConformance } from './support/binding-driver-conformance-suite.js';
import { livewireConformanceAdapter } from './support/livewire-conformance-adapter.js';

defineBindingDriverConformance(livewireConformanceAdapter);
```

- [ ] **Step 2: Run the new entry and verify the RED state**

Run:

```bash
cd packages/browser-runtime
npm test -- tests/binding-driver-conformance.livewire.test.ts
```

Expected: FAIL because `support/binding-driver-conformance-suite` and/or `support/livewire-conformance-adapter` do not exist yet. Do not create production code to resolve this failure.

- [ ] **Step 3: Add the single shared conformance suite**

Create `packages/browser-runtime/tests/support/binding-driver-conformance-suite.ts` with:

```ts
import { describe, expect, it } from 'vitest';
import type {
  BindingDriver,
  RuntimeBinding,
} from '../../src/types.js';

export const CONFORMANCE_NOW = new Date('2026-09-14T00:00:00.000Z');

export interface BindingDriverConformanceHarness {
  readonly driver: BindingDriver;
  readonly binding: RuntimeBinding;
  readonly validInput: Record<string, unknown>;
  readonly invalidTargetBinding: RuntimeBinding;
  readonly unknownInput: Record<string, unknown>;
  readonly missingRequiredInput: Record<string, unknown>;

  makeTargetStale(): void;
  replaceTargetWithEquivalentIdentity(): void;
  frameworkDispatchCount(): number;
  replacementDispatchCount(): number;
}

export interface BindingDriverConformanceAdapter {
  readonly name: 'livewire' | 'htmx';
  createHarness(): BindingDriverConformanceHarness;
}

export function defineBindingDriverConformance(
  adapter: BindingDriverConformanceAdapter,
): void {
  describe(`${adapter.name} shared BindingDriver conformance`, () => {
    it('dispatches one valid exact target without normalizing the result', async () => {
      const harness = adapter.createHarness();

      await harness.driver.execute(harness.binding, harness.validInput, {});

      expect(harness.frameworkDispatchCount()).toBe(1);
      expect(harness.replacementDispatchCount()).toBe(0);
    });

    it('rejects a binding owned by another driver before framework dispatch', async () => {
      const harness = adapter.createHarness();

      await expect(harness.driver.execute(
        { ...harness.binding, driver: 'foreign-driver' },
        harness.validInput,
        {},
      )).rejects.toMatchObject({ code: 'binding_target_invalid' });

      expect(harness.frameworkDispatchCount()).toBe(0);
    });

    it('rejects a malformed driver-owned target before framework dispatch', async () => {
      const harness = adapter.createHarness();

      await expect(harness.driver.execute(
        harness.invalidTargetBinding,
        harness.validInput,
        {},
      )).rejects.toMatchObject({ code: 'binding_target_invalid' });

      expect(harness.frameworkDispatchCount()).toBe(0);
    });

    it('rejects malformed expiry before framework dispatch', async () => {
      const harness = adapter.createHarness();

      await expect(harness.driver.execute(
        { ...harness.binding, expiresAt: 'not-a-date' },
        harness.validInput,
        {},
      )).rejects.toMatchObject({ code: 'binding_target_invalid' });

      expect(harness.frameworkDispatchCount()).toBe(0);
    });

    it('rejects a binding expired before the conformance clock', async () => {
      const harness = adapter.createHarness();

      await expect(harness.driver.execute(
        { ...harness.binding, expiresAt: '2026-09-13T23:59:59Z' },
        harness.validInput,
        {},
      )).rejects.toMatchObject({ code: 'binding_expired' });

      expect(harness.frameworkDispatchCount()).toBe(0);
    });

    it('treats expiry equality as expired', async () => {
      const harness = adapter.createHarness();

      await expect(harness.driver.execute(
        { ...harness.binding, expiresAt: '2026-09-14T00:00:00Z' },
        harness.validInput,
        {},
      )).rejects.toMatchObject({ code: 'binding_expired' });

      expect(harness.frameworkDispatchCount()).toBe(0);
    });

    it('rejects an unknown Action input before framework dispatch', async () => {
      const harness = adapter.createHarness();

      await expect(harness.driver.execute(
        harness.binding,
        harness.unknownInput,
        {},
      )).rejects.toMatchObject({ code: 'binding_input_unmappable' });

      expect(harness.frameworkDispatchCount()).toBe(0);
    });

    it('rejects missing required Action input before framework dispatch', async () => {
      const harness = adapter.createHarness();

      await expect(harness.driver.execute(
        harness.binding,
        harness.missingRequiredInput,
        {},
      )).rejects.toMatchObject({ code: 'binding_input_unmappable' });

      expect(harness.frameworkDispatchCount()).toBe(0);
    });

    it('fails stale when the exact issued target no longer exists', async () => {
      const harness = adapter.createHarness();
      harness.makeTargetStale();

      await expect(harness.driver.execute(
        harness.binding,
        harness.validInput,
        {},
      )).rejects.toMatchObject({ code: 'binding_stale' });

      expect(harness.frameworkDispatchCount()).toBe(0);
    });

    it('does not retarget an old binding to an equivalent replacement identity', async () => {
      const harness = adapter.createHarness();
      harness.replaceTargetWithEquivalentIdentity();

      await expect(harness.driver.execute(
        harness.binding,
        harness.validInput,
        {},
      )).rejects.toMatchObject({ code: 'binding_stale' });

      expect(harness.frameworkDispatchCount()).toBe(0);
      expect(harness.replacementDispatchCount()).toBe(0);
    });

    it('surfaces an already-aborted caller reason with zero framework dispatch', async () => {
      const harness = adapter.createHarness();
      const reason = new Error(`${adapter.name} caller cancelled before dispatch`);
      const controller = new AbortController();
      controller.abort(reason);

      await expect(harness.driver.execute(
        harness.binding,
        harness.validInput,
        { signal: controller.signal },
      )).rejects.toBe(reason);

      expect(harness.frameworkDispatchCount()).toBe(0);
    });
  });
}
```

This file is the only place T-604 declares the shared behavioral cases.

- [ ] **Step 4: Add the Livewire adapter around the production driver**

Create `packages/browser-runtime/tests/support/livewire-conformance-adapter.ts` with:

```ts
import { vi } from 'vitest';
import { LivewireBrowserDriver } from '../../src/livewire-browser-driver.js';
import type {
  LivewireBrowserRuntime,
  LivewireWire,
} from '../../src/livewire-browser-runtime.js';
import type { RuntimeBinding } from '../../src/types.js';
import {
  CONFORMANCE_NOW,
  type BindingDriverConformanceAdapter,
} from './binding-driver-conformance-suite.js';

function livewireBinding(): RuntimeBinding {
  return {
    bindingId: 'conformance-livewire-1',
    action: { id: 'prep_list.add_item', version: 1 },
    driver: 'livewire',
    lifecycle: 'component',
    target: {
      componentId: 'component-1',
      method: 'addItem',
      inputOrder: ['name'],
      requiredCount: 1,
    },
    expiresAt: null,
  };
}

function makeWire(id: string): {
  wire: LivewireWire;
  call: ReturnType<typeof vi.fn>;
} {
  const call = vi.fn(async (_method: string, ..._params: unknown[]) => ({ ok: true }));
  return {
    wire: {
      $id: id,
      $call: call,
    },
    call,
  };
}

export const livewireConformanceAdapter: BindingDriverConformanceAdapter = {
  name: 'livewire',

  createHarness() {
    const original = makeWire('component-1');
    const replacement = makeWire('component-2');
    let resolved: LivewireWire | undefined = original.wire;

    const runtime: LivewireBrowserRuntime = {
      find: vi.fn(() => resolved),
    };

    const binding = livewireBinding();

    return {
      driver: new LivewireBrowserDriver(runtime, { now: () => CONFORMANCE_NOW }),
      binding,
      validInput: { name: 'passport' },
      invalidTargetBinding: {
        ...binding,
        target: {
          componentId: 'component-1',
          method: 'addItem',
          inputOrder: ['name'],
        },
      },
      unknownInput: { name: 'passport', extra: true },
      missingRequiredInput: {},

      makeTargetStale() {
        resolved = undefined;
      },

      replaceTargetWithEquivalentIdentity() {
        resolved = replacement.wire;
      },

      frameworkDispatchCount() {
        return original.call.mock.calls.length + replacement.call.mock.calls.length;
      },

      replacementDispatchCount() {
        return replacement.call.mock.calls.length;
      },
    };
  },
};
```

The replacement keeps the same callable operation surface but has a different `$wire.$id`; the production driver must reject it as stale rather than dispatching.

- [ ] **Step 5: Run the Livewire shared matrix and verify GREEN**

Run:

```bash
cd packages/browser-runtime
npm test -- tests/binding-driver-conformance.livewire.test.ts
```

Expected: PASS, 1 test file / 11 tests. No production file should have changed.

- [ ] **Step 6: Re-run the existing Livewire driver/cancellation/integration regression slice**

Run:

```bash
cd packages/browser-runtime
npm test -- \
  tests/livewire-browser-driver.test.ts \
  tests/livewire-cancellation.test.ts \
  tests/livewire-webmcp-integration.test.ts \
  tests/runtime-binding-expiry.test.ts
```

Expected: PASS with zero failures.

- [ ] **Step 7: Commit Task 1**

```bash
git add \
  packages/browser-runtime/tests/binding-driver-conformance.livewire.test.ts \
  packages/browser-runtime/tests/support/binding-driver-conformance-suite.ts \
  packages/browser-runtime/tests/support/livewire-conformance-adapter.ts
git commit -m "test(conformance): add shared Livewire driver matrix"
```

---

### Task 2: Run the exact same matrix against HTMX and typecheck the harness

**Files:**
- Create: `packages/browser-runtime/tests/binding-driver-conformance.htmx.test.ts`
- Create: `packages/browser-runtime/tests/support/htmx-conformance-adapter.ts`
- Create: `packages/browser-runtime/tests/binding-driver-conformance.typecheck.ts`
- Reuse: `packages/browser-runtime/tests/support/binding-driver-conformance-suite.ts`
- Reference: `packages/browser-runtime/src/htmx-browser-driver.ts`
- Reference: `packages/browser-runtime/src/htmx-browser-runtime.ts`

**Interfaces:**
- Consumes: `defineBindingDriverConformance()`, `BindingDriverConformanceAdapter`, and `CONFORMANCE_NOW` from Task 1.
- Produces: `htmxConformanceAdapter`; after this task both drivers execute the same 11-case shared matrix and both support modules are reachable by `tsc` through the typecheck sentinel.

- [ ] **Step 1: Write the HTMX conformance entry first**

Create `packages/browser-runtime/tests/binding-driver-conformance.htmx.test.ts` with exactly:

```ts
import { defineBindingDriverConformance } from './support/binding-driver-conformance-suite.js';
import { htmxConformanceAdapter } from './support/htmx-conformance-adapter.js';

defineBindingDriverConformance(htmxConformanceAdapter);
```

- [ ] **Step 2: Run the HTMX entry and verify the RED state**

Run:

```bash
cd packages/browser-runtime
npm test -- tests/binding-driver-conformance.htmx.test.ts
```

Expected: FAIL because `support/htmx-conformance-adapter` does not exist yet. Do not change production HTMX code to resolve this expected failure.

- [ ] **Step 3: Add the HTMX adapter around the production driver**

Create `packages/browser-runtime/tests/support/htmx-conformance-adapter.ts` with:

```ts
import { vi } from 'vitest';
import { HtmxBrowserDriver } from '../../src/htmx-browser-driver.js';
import type {
  HtmxAjaxContext,
  HtmxAjaxMethod,
  HtmxBrowserRuntime,
  HtmxSourceElement,
} from '../../src/htmx-browser-runtime.js';
import type { RuntimeBinding } from '../../src/types.js';
import {
  CONFORMANCE_NOW,
  type BindingDriverConformanceAdapter,
} from './binding-driver-conformance-suite.js';

class ConformanceSource implements HtmxSourceElement {
  readonly parentElement = null;
  readonly tagName = 'BUTTON';
  readonly attrs = new Map<string, string>();
  readonly classList = {
    contains: (_token: string) => false,
  };

  constructor(sourceId: string) {
    this.attrs.set('data-surfacerelay-htmx-source', sourceId);
    this.attrs.set('hx-post', '/items');
  }

  hasAttribute(name: string): boolean {
    return this.attrs.has(name);
  }

  getAttribute(name: string): string | null {
    return this.attrs.get(name) ?? null;
  }
}

function htmxBinding(): RuntimeBinding {
  return {
    bindingId: 'conformance-htmx-1',
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

export const htmxConformanceAdapter: BindingDriverConformanceAdapter = {
  name: 'htmx',

  createHarness() {
    const original = new ConformanceSource('src-1');
    const replacement = new ConformanceSource('src-2');
    const sources: HtmxSourceElement[] = [original];

    const ajax = vi.fn(async (
      _method: HtmxAjaxMethod,
      _path: string,
      _context: HtmxAjaxContext,
    ) => undefined);

    const runtime: HtmxBrowserRuntime = {
      assertSupported: vi.fn(),
      findSources: vi.fn((sourceId: string) => sources.filter(
        (candidate) => candidate.getAttribute('data-surfacerelay-htmx-source') === sourceId,
      )),
      currentLocation: () => ({
        href: 'https://example.test/page',
        origin: 'https://example.test',
      }),
      requestClass: vi.fn(() => 'htmx-request'),
      ajax,
    };

    const binding = htmxBinding();

    return {
      driver: new HtmxBrowserDriver(runtime, { now: () => CONFORMANCE_NOW }),
      binding,
      validInput: { item: 'coffee' },
      invalidTargetBinding: {
        ...binding,
        target: {
          sourceId: 'src-1',
          method: 'POST',
          path: '/items',
          inputNames: ['item'],
        },
      },
      unknownInput: { item: 'coffee', extra: true },
      missingRequiredInput: {},

      makeTargetStale() {
        sources.splice(0, sources.length);
      },

      replaceTargetWithEquivalentIdentity() {
        sources.splice(0, sources.length, replacement);
      },

      frameworkDispatchCount() {
        return ajax.mock.calls.length;
      },

      replacementDispatchCount() {
        return ajax.mock.calls.filter(([, , context]) => context.source === replacement).length;
      },
    };
  },
};
```

The replacement retains the same `hx-post="/items"` semantics but uses `sourceId=src-2`; lookup for the issued `src-1` binding must not discover or dispatch it.

- [ ] **Step 4: Add a TypeScript sentinel for both test adapters**

Create `packages/browser-runtime/tests/binding-driver-conformance.typecheck.ts` with:

```ts
import { htmxConformanceAdapter } from './support/htmx-conformance-adapter.js';
import { livewireConformanceAdapter } from './support/livewire-conformance-adapter.js';
import type { BindingDriverConformanceAdapter } from './support/binding-driver-conformance-suite.js';

const adapters: readonly BindingDriverConformanceAdapter[] = [
  livewireConformanceAdapter,
  htmxConformanceAdapter,
];

void adapters;
```

Because `packages/browser-runtime/tsconfig.json` includes `tests/**/*.typecheck.ts`, this file pulls the shared suite and both adapters into the package typecheck without broadening the production TypeScript configuration.

- [ ] **Step 5: Run both shared entries and verify the same 11 cases pass twice**

Run:

```bash
cd packages/browser-runtime
npm test -- \
  tests/binding-driver-conformance.livewire.test.ts \
  tests/binding-driver-conformance.htmx.test.ts
```

Expected: PASS, 2 test files / 22 tests.

- [ ] **Step 6: Typecheck the conformance support graph**

Run:

```bash
cd packages/browser-runtime
npm run typecheck
```

Expected: PASS with zero TypeScript errors. Do not modify `tsconfig.json` to obtain this result; the `.typecheck.ts` sentinel is the intended inclusion mechanism.

- [ ] **Step 7: Re-run the existing HTMX driver/input/cancellation/integration regression slice**

Run:

```bash
cd packages/browser-runtime
npm test -- \
  tests/htmx-browser-driver.test.ts \
  tests/htmx-input-mapping.test.ts \
  tests/htmx-cancellation.test.ts \
  tests/htmx-webmcp-integration.test.ts \
  tests/runtime-binding-expiry.test.ts
```

Expected: PASS with zero failures.

- [ ] **Step 8: Commit Task 2**

```bash
git add \
  packages/browser-runtime/tests/binding-driver-conformance.htmx.test.ts \
  packages/browser-runtime/tests/binding-driver-conformance.typecheck.ts \
  packages/browser-runtime/tests/support/htmx-conformance-adapter.ts
git commit -m "test(conformance): run shared matrix against HTMX"
```

---

### Task 3: Run the complete T-604 verification gate without widening scope

**Files:**
- Verify only; no production file is expected to change.
- Reference: `packages/browser-runtime/package.json`
- Reference: `examples/htmx-prep-list/package.json`
- Reference: `.github/workflows/validate.yml`
- Reference: `.github/workflows/htmx-fixture.yml`

**Interfaces:**
- Consumes: all shared-conformance files from Tasks 1–2 plus all pre-existing driver-specific tests.
- Produces: fresh verification evidence suitable for T-604 review preparation. It does not promote `D-058` or `D-020`.

- [ ] **Step 1: Run the focused shared + driver-specific browser regression set**

Run:

```bash
cd packages/browser-runtime
npm test -- \
  tests/binding-driver-conformance.livewire.test.ts \
  tests/binding-driver-conformance.htmx.test.ts \
  tests/livewire-browser-driver.test.ts \
  tests/livewire-cancellation.test.ts \
  tests/livewire-webmcp-integration.test.ts \
  tests/htmx-browser-driver.test.ts \
  tests/htmx-input-mapping.test.ts \
  tests/htmx-cancellation.test.ts \
  tests/htmx-webmcp-integration.test.ts \
  tests/runtime-binding-expiry.test.ts
```

Expected: PASS with zero failures.

- [ ] **Step 2: Run the full browser-runtime suite**

Run:

```bash
cd packages/browser-runtime
npm test
```

Expected from the current 17-file / 297-test baseline plus the two 11-case conformance entry files: **19 test files / 319 tests, all passing**.

If the count differs, inspect the collected tests before recording evidence; do not silently update the expected count without explaining the difference.

- [ ] **Step 3: Run browser-runtime TypeScript typecheck**

Run:

```bash
cd packages/browser-runtime
npm run typecheck
```

Expected: PASS with zero TypeScript errors.

- [ ] **Step 4: Run contract/repository validation locally**

Run from repository root:

```bash
python -m pip install -r requirements-dev.txt
python scripts/validate.py
```

Expected: PASS with no validation errors.

- [ ] **Step 5: Re-run the real T-603 Chromium fixture explicitly**

The path-filtered `htmx-fixture` workflow does not run automatically for test-only files under `packages/browser-runtime/tests/**`, so T-604 must collect this evidence explicitly.

On the same Linux/CI-compatible environment used for Playwright verification, run:

```bash
cd examples/htmx-prep-list
npm ci
npx playwright install --with-deps chromium
npm test
```

Expected: **8/8 Playwright tests passing**. This is regression evidence only; do not modify fixture files to force its workflow to run.

- [ ] **Step 6: Prove the implementation diff stayed test-only**

Run from repository root:

```bash
git diff --name-only main...HEAD
```

Expected implementation additions under browser runtime are limited to:

```text
packages/browser-runtime/tests/binding-driver-conformance.livewire.test.ts
packages/browser-runtime/tests/binding-driver-conformance.htmx.test.ts
packages/browser-runtime/tests/binding-driver-conformance.typecheck.ts
packages/browser-runtime/tests/support/binding-driver-conformance-suite.ts
packages/browser-runtime/tests/support/livewire-conformance-adapter.ts
packages/browser-runtime/tests/support/htmx-conformance-adapter.ts
```

The branch also legitimately contains the already-approved T-604 design/plan/tracking documentation. The command output must contain **no** unexpected changes under:

```text
packages/browser-runtime/src/
spec/0.1/
packages/laravel/src/
examples/htmx-prep-list/
.github/workflows/
```

- [ ] **Step 7: Push the implementation commits and require branch-head `validate` to finish green**

Push normally, then inspect the `validate` workflow for the exact branch head. Required result: **7/7 jobs successful**. Record the literal branch-head commit SHA and workflow run ID returned by GitHub; do not reuse T-603 or the pre-implementation plan-gate run as implementation evidence.

- [ ] **Step 8: Stop on any mismatch**

If a shared case fails because Livewire and HTMX expose genuinely different accepted semantics, or if satisfying the matrix requires production/source/spec/workflow changes, stop here. Do not weaken the case or refactor production behavior under T-604 without a new explicit design decision.

---

### Task 4: Record verified implementation and prepare the external-review gate

**Files:**
- Modify: `TASKS.md`
- Modify: `STATUS.md`
- Modify: `REVIEW_REQUEST.md`
- Do not modify decision status in `docs/DECISION-REGISTER.md` during review preparation.

**Interfaces:**
- Consumes: literal evidence from Task 3 — branch-head SHA, focused/full browser results, typecheck result, contract validation, real fixture result, changed-file audit, and branch-head `validate` run.
- Produces: an explicit review handoff with `D-058` and `D-020` still `PROPOSED`; no automatic decision promotion or M6 closure.

- [ ] **Step 1: Capture the exact implementation head**

Run:

```bash
git rev-parse HEAD
```

Copy the exact returned SHA into the T-604 evidence records. Do not use an earlier commit.

- [ ] **Step 2: Update `TASKS.md` to the review-ready state**

Change the T-604 heading to:

```text
### T-604 — Shared conformance against Livewire + HTMX — IMPLEMENTED / VERIFIED / READY FOR EXTERNAL REVIEW
```

Under the existing design boundary, add these verified facts using the literal results collected in Task 3:

```text
- one shared 11-case matrix executes against both production drivers;
- shared matrix total: 22/22 passing;
- full browser-runtime suite: 19 files / 319 tests passing;
- TypeScript typecheck passing;
- repository validation passing;
- T-603 real Chromium fixture: 8/8 passing;
- branch-head validate: 7/7 jobs green;
- implementation diff is test-only under packages/browser-runtime/tests/**;
- no production browser-runtime, frozen spec, Laravel, fixture, workflow, or dependency changes.
```

Keep the decision state exactly:

```text
- D-058 — PROPOSED pending external review/closure.
- D-020 — PROPOSED pending explicit portability decision after external review/closure.
```

- [ ] **Step 3: Update `STATUS.md` with the same evidence boundary**

Record T-604 as the current implemented/verified task awaiting review. Include:

```text
Milestone: M6 — HTMX Portability Proof — IN_PROGRESS
Task: T-604 — IMPLEMENTED / VERIFIED / READY FOR EXTERNAL REVIEW
D-058: PROPOSED
D-020: PROPOSED
```

Record the exact implementation head and exact fresh verification results from Task 3. State explicitly that no production contract was changed and that T-701 was not implemented.

- [ ] **Step 4: Replace `REVIEW_REQUEST.md` with a concise T-604 review handoff**

The handoff must ask reviewers to verify these exact points:

```text
1. The same shared matrix, not duplicated case definitions, runs against both production drivers.
2. The matrix covers only genuinely common behavior and does not normalize target shapes, lifecycle, framework errors, cancellation internals, or success results.
3. Every fail-closed case proves zero unintended framework dispatch.
4. Equivalent replacement identities receive zero dispatch from an old binding.
5. The harness is test-only and does not duplicate either production driver.
6. Existing Livewire/HTMX driver-specific, cancellation, input-mapping, and WebMCP integration behavior remains covered by its original suites.
7. The real T-603 Chromium fixture remains green.
8. No T-701 runner, frozen-spec change, production refactor, workflow expansion, or dependency expansion leaked into T-604.
9. D-058 and D-020 remain proposed until explicit post-review closure.
```

Include the literal Task 3 evidence, not historical evidence from another head.

- [ ] **Step 5: Run docs-inclusive validation after tracking changes**

Run:

```bash
python scripts/validate.py
```

Expected: PASS.

Then push the tracking commit and require the new docs-inclusive branch-head `validate` workflow to finish **7/7 green** before presenting the review handoff as ready.

- [ ] **Step 6: Commit review preparation**

```bash
git add TASKS.md STATUS.md REVIEW_REQUEST.md
git commit -m "docs(conformance): prepare T-604 external review"
```

- [ ] **Step 7: Stop at the external-review boundary**

Do not promote `D-058` or `D-020`, do not close M6, and do not merge automatically. External review and explicit closure authorization are separate gates.

## Self-Review Record

### Spec coverage

- Single shared executable matrix: Tasks 1–2.
- Production Livewire + HTMX drivers under test: Tasks 1–2.
- Shared target validation / expiry / input / stale / no-retarget / already-aborted semantics: `binding-driver-conformance-suite.ts` in Task 1.
- Zero unintended framework dispatch: all failure cases in the shared suite.
- Driver-owned structural differences preserved: adapter-only setup plus Global Constraints.
- Framework-local behavior retained in original suites: Tasks 1–3 regression commands.
- No success-result normalization: valid shared case asserts dispatch count only.
- No T-701 runner: Global Constraints and Task 3 diff gate.
- Real T-603 proof retained: Task 3 explicit 8/8 Playwright run.
- Decision promotion remains explicit and post-review: Task 4 stop gate.

### Placeholder scan

The plan contains no deferred implementation markers. Runtime-generated evidence such as future commit SHAs and GitHub Actions run IDs is captured by explicit commands at execution time and must be copied literally into review records.

### Type consistency

- Both adapters implement the single `BindingDriverConformanceAdapter` interface.
- Both return the single `BindingDriverConformanceHarness` shape.
- Both instantiate production classes implementing `BindingDriver`.
- `CONFORMANCE_NOW` is shared by both driver clocks.
- The `.typecheck.ts` sentinel imports both adapter modules so their support graph is included by the existing TypeScript configuration.

## Execution Boundary

This plan is complete when it is written and reviewed. **Writing this plan does not authorize implementation.**

When implementation is explicitly authorized, use `superpowers:subagent-driven-development` when subagents are available, otherwise use `superpowers:executing-plans`. Execute task-by-task, preserve the RED/GREEN evidence in Tasks 1–2, and stop at Task 4's external-review boundary rather than promoting decisions automatically.
