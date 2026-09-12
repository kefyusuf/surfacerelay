# T-603 Non-Laravel HTMX Fixture Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Prove the merged SurfaceRelay HTMX binding against a real non-Laravel Node application, real HTMX 2.0.10 runtime, real Chromium network/DOM execution, and the existing `prep_list.add_item@1` ActionDefinition without introducing a second business mutation path.

**Architecture:** Add an independent private fixture package under `examples/htmx-prep-list/`. A loopback-only Node 22 `node:http` server emits a fresh page-scoped HTMX RuntimeBinding derived through the real `createHtmxBindingTarget()`, serves pinned HTMX and compiled browser-runtime ESM, and exposes exactly one business mutation route (`POST /items`). Playwright/Chromium proves normal human HTMX execution, SurfaceRelay driver execution, real request convergence, page lifecycle renewal, stale-source failure, and server-side persistence.

**Tech Stack:** Node.js 22 built-ins, `htmx.org@2.0.10`, TypeScript `5.9.3`, `@playwright/test@1.63.0`, Chromium, existing SurfaceRelay browser-runtime source.

**Spec:** `docs/superpowers/specs/2026-09-12-htmx-fixture-design.md`

## Global Constraints

- Base branch is `main@83df122d84f6881a4a3541bd5c72e1108e5d1e48`; implementation branch is `feat/htmx-fixture`.
- The fixture is non-Laravel and uses Node 22 built-in `node:http`; do not add Express, Hono, Fastify, Vite, or another application framework.
- Server binds only `127.0.0.1:4173`; a busy port fails loudly; never bind `0.0.0.0` and never choose a random fallback port.
- Pin `htmx.org` exactly `2.0.10`, `@playwright/test` exactly `1.63.0`, and fixture TypeScript exactly `5.9.3`.
- Fixture dependencies belong only to `examples/htmx-prep-list/package.json` and its lockfile. Do not modify `packages/browser-runtime/package*.json` or Laravel Composer manifests/locks.
- Reuse `examples/prep-list/action.add-item.json`; do not duplicate the ActionDefinition.
- Compile the real `packages/browser-runtime/src/**/*.ts` source to fixture-local ignored `.tmp/runtime/` ESM; do not copy or reimplement `HtmxBrowserDriver`, `GlobalHtmxBrowserRuntime`, or descriptor logic.
- `POST /items` is the only business mutation route. `POST /__test/reset` is test-only state control and must never add business data.
- The client test bridge may delegate to `HtmxBrowserDriver.execute()` and may perform DOM-only source replacement. It must not call `fetch`, XHR, direct `htmx.ajax()`, or mutate server state.
- Human and SurfaceRelay paths must use the same rendered source semantics, `/items` route, server mutation, HTML fragment response, and source-defined `hx-target` / `hx-swap` behavior.
- Each full `GET /` creates a fresh `sourceId` and `bindingId`; page-scoped identity must not survive reload.
- Stale replacement must fail `binding_stale`, send zero `POST /items` requests, and never retarget to an equivalent replacement source.
- Generated runtime JS, Playwright results, traces, screenshots, and browser artifacts stay under ignored `.tmp/` / tool-managed cache paths and are never committed.
- Keep the existing `.github/workflows/validate.yml` behavior unchanged; add a separate path-filtered `.github/workflows/htmx-fixture.yml`.
- `D-057` remains `PROPOSED` until the complete real-browser fixture verification succeeds. `D-020` remains `PROPOSED` through T-604.
- Do not add T-604 shared Livewire/HTMX conformance scenarios during T-603.

## File Structure

Create:

```text
examples/htmx-prep-list/package.json
examples/htmx-prep-list/package-lock.json
examples/htmx-prep-list/tsconfig.runtime.json
examples/htmx-prep-list/server.mjs
examples/htmx-prep-list/client.mjs
examples/htmx-prep-list/playwright.config.mjs
examples/htmx-prep-list/README.md
examples/htmx-prep-list/tests/prep-list.spec.mjs
.github/workflows/htmx-fixture.yml
```

Modify only at closure:

```text
docs/DECISION-REGISTER.md
TASKS.md
STATUS.md
REVIEW_REQUEST.md   # only when preparing external review after implementation
```

Expected unchanged production/library boundaries:

```text
packages/browser-runtime/src/**            # consumed, not modified unless a real fixture bug proves T-602 incorrect
packages/browser-runtime/package.json
packages/browser-runtime/package-lock.json
packages/laravel/**
spec/0.1/**
examples/prep-list/action.add-item.json
.github/workflows/validate.yml
```

If the real fixture exposes a contradiction requiring a browser-runtime behavior change, stop T-603 implementation and reopen a design gate rather than silently patching T-602 semantics inside this fixture task.

---

### Task 1: Bootstrap the real fixture and prove runtime/binding identity

**Files:**
- Create: `examples/htmx-prep-list/package.json`
- Create: `examples/htmx-prep-list/package-lock.json`
- Create: `examples/htmx-prep-list/tsconfig.runtime.json`
- Create: `examples/htmx-prep-list/playwright.config.mjs`
- Create: `examples/htmx-prep-list/server.mjs`
- Create: `examples/htmx-prep-list/client.mjs`
- Create/Test: `examples/htmx-prep-list/tests/prep-list.spec.mjs`
- Consume unchanged: `examples/prep-list/action.add-item.json`
- Consume unchanged: `packages/browser-runtime/src/htmx-binding-descriptor.ts`
- Consume unchanged: `packages/browser-runtime/src/htmx-browser-runtime.ts`
- Consume unchanged: `packages/browser-runtime/src/htmx-browser-driver.ts`

**Interfaces:**
- Consumes: `createHtmxBindingTarget(definition, { sourceId, method, path })`, `GlobalHtmxBrowserRuntime`, `HtmxBrowserDriver` from emitted browser-runtime ESM.
- Produces: HTTP `GET /`, static `/vendor/htmx.min.js`, `/client.mjs`, `/runtime/<flat>.js`; `window.surfaceRelayFixture.ready`; page-embedded `RuntimeBinding` in `#surfacerelay-binding`.

- [ ] **Step 1: Create the private fixture package manifest and runtime compiler config**

Create `examples/htmx-prep-list/package.json` exactly with fixture-owned dependencies and scripts:

```json
{
  "name": "@surfacerelay/example-htmx-prep-list",
  "version": "0.0.0-dev",
  "private": true,
  "type": "module",
  "scripts": {
    "build:runtime": "tsc -p tsconfig.runtime.json",
    "start": "node server.mjs",
    "test": "npm run build:runtime && playwright test"
  },
  "dependencies": {
    "htmx.org": "2.0.10"
  },
  "devDependencies": {
    "@playwright/test": "1.63.0",
    "typescript": "5.9.3"
  }
}
```

Create `examples/htmx-prep-list/tsconfig.runtime.json`:

```json
{
  "extends": "../../packages/browser-runtime/tsconfig.json",
  "compilerOptions": {
    "noEmit": false,
    "rootDir": "../../packages/browser-runtime/src",
    "outDir": ".tmp/runtime",
    "declaration": false,
    "sourceMap": false
  },
  "include": [
    "../../packages/browser-runtime/src/**/*.ts"
  ]
}
```

Generate the lockfile from the fixture directory, do not hand-edit it:

```bash
cd examples/htmx-prep-list
npm install --package-lock-only
```

Expected: lockfile records exact HTMX 2.0.10, Playwright Test 1.63.0, and TypeScript 5.9.3 roots.

- [ ] **Step 2: Create Playwright configuration and the first failing bootstrap test**

Create `playwright.config.mjs`:

```js
import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  workers: 1,
  retries: 0,
  outputDir: '.tmp/test-results',
  reporter: 'line',
  use: {
    browserName: 'chromium',
    baseURL: 'http://127.0.0.1:4173',
    trace: 'retain-on-failure',
  },
  webServer: {
    command: 'npm run start',
    url: 'http://127.0.0.1:4173/',
    reuseExistingServer: false,
    timeout: 15_000,
    stdout: 'pipe',
    stderr: 'pipe',
  },
});
```

Start `tests/prep-list.spec.mjs` with the bootstrap proof:

```js
import { expect, test } from '@playwright/test';

const bindingSelector = '#surfacerelay-binding';
const sourceSelector = '[data-surfacerelay-htmx-source]';

test('boots real HTMX 2.0.10 with one exact server-issued page binding', async ({ page }) => {
  await page.goto('/');
  await page.waitForFunction(() => globalThis.surfaceRelayFixture?.ready === true);

  const snapshot = await page.evaluate(({ bindingSelector, sourceSelector }) => {
    const raw = document.querySelector(bindingSelector)?.textContent;
    const source = document.querySelector(sourceSelector);
    return {
      htmxVersion: globalThis.htmx?.version,
      binding: raw ? JSON.parse(raw) : null,
      sourceId: source?.getAttribute('data-surfacerelay-htmx-source'),
      sourceCount: document.querySelectorAll(sourceSelector).length,
    };
  }, { bindingSelector, sourceSelector });

  expect(snapshot.htmxVersion).toBe('2.0.10');
  expect(snapshot.sourceCount).toBe(1);
  expect(snapshot.binding.driver).toBe('htmx');
  expect(snapshot.binding.lifecycle).toBe('page');
  expect(snapshot.binding.action).toEqual({ id: 'prep_list.add_item', version: 1 });
  expect(snapshot.binding.target.method).toBe('POST');
  expect(snapshot.binding.target.path).toBe('/items');
  expect(snapshot.binding.target.sourceId).toBe(snapshot.sourceId);
});
```

- [ ] **Step 3: Run the bootstrap test and record RED**

```bash
cd examples/htmx-prep-list
npm ci
npx playwright install chromium
npm test -- --grep "boots real HTMX"
```

Expected RED: Playwright cannot start the fixture because `server.mjs` / page bootstrap behavior does not yet exist. Existing repository `validate` behavior must remain unaffected.

- [ ] **Step 4: Implement the minimum loopback server, page render, binding creation, static routes, and client bootstrap**

`server.mjs` must:

1. import Node built-ins only for server behavior (`node:http`, `node:fs/promises`, `node:path`, `node:url`, `node:crypto`);
2. read `../prep-list/action.add-item.json` at startup;
3. import `createHtmxBindingTarget` from `.tmp/runtime/htmx-binding-descriptor.js` after `build:runtime` has run;
4. create fresh source/binding UUIDs on each `GET /`;
5. render exactly one HTMX source button;
6. serve only explicit static files plus flat runtime JS filenames;
7. listen on `127.0.0.1:4173`.

Use exact binding construction semantics:

```js
function createPageBinding(definition) {
  const sourceId = `prep-add-${randomUUID()}`;
  const bindingId = `htmx-binding-${randomUUID()}`;
  const target = createHtmxBindingTarget(definition, {
    sourceId,
    method: 'POST',
    path: '/items',
  });

  return {
    sourceId,
    binding: {
      bindingId,
      action: { id: definition.id, version: definition.version },
      driver: 'htmx',
      lifecycle: 'page',
      target,
      expiresAt: null,
    },
  };
}
```

Embed the binding safely:

```js
function jsonForHtmlScript(value) {
  return JSON.stringify(value).replaceAll('<', '\\u003c');
}
```

Render the source with exactly these relevant attributes:

```html
<form id="prep-form">
  <input type="hidden" name="uiContext" value="prep-list">
  <input name="name">
  <button
    type="button"
    data-surfacerelay-htmx-source="..."
    hx-post="/items"
    hx-target="#items"
    hx-swap="beforeend"
  >Add</button>
</form>
<ul id="items"></ul>
<script type="application/json" id="surfacerelay-binding">...</script>
<script src="/vendor/htmx.min.js"></script>
<script type="module" src="/client.mjs"></script>
```

`client.mjs` must import emitted production modules and expose only readiness at this stage:

```js
import { HtmxBrowserDriver } from '/runtime/htmx-browser-driver.js';
import { GlobalHtmxBrowserRuntime } from '/runtime/htmx-browser-runtime.js';

const raw = document.querySelector('#surfacerelay-binding')?.textContent;
if (!raw) throw new Error('Fixture RuntimeBinding is missing.');

const binding = JSON.parse(raw);
const runtime = new GlobalHtmxBrowserRuntime();
const driver = new HtmxBrowserDriver(runtime);

globalThis.surfaceRelayFixture = Object.freeze({
  ready: true,
});

// Keep binding, runtime and driver module-local for later tasks; do not expose direct htmx.ajax.
void binding;
void driver;
```

Static runtime route must reject nested/traversal paths and only accept a single filename matching `^[A-Za-z0-9._-]+\\.js$`.

- [ ] **Step 5: Run bootstrap proof and browser-runtime regressions**

```bash
cd examples/htmx-prep-list
npm test -- --grep "boots real HTMX"

cd ../../packages/browser-runtime
npm run typecheck
npm test
```

Expected: bootstrap test PASS with HTMX `2.0.10`, exactly one source, exact embedded binding/source identity; browser-runtime remains 297/297 or higher with typecheck green.

- [ ] **Step 6: Commit Task 1**

```bash
git add \
  examples/htmx-prep-list/package.json \
  examples/htmx-prep-list/package-lock.json \
  examples/htmx-prep-list/tsconfig.runtime.json \
  examples/htmx-prep-list/playwright.config.mjs \
  examples/htmx-prep-list/server.mjs \
  examples/htmx-prep-list/client.mjs \
  examples/htmx-prep-list/tests/prep-list.spec.mjs

git commit -m "test(htmx): bootstrap real browser fixture"
```

---

### Task 2: Add the single business mutation path and prove normal human HTMX execution

**Files:**
- Modify: `examples/htmx-prep-list/server.mjs`
- Modify/Test: `examples/htmx-prep-list/tests/prep-list.spec.mjs`

**Interfaces:**
- Consumes: rendered source `hx-post="/items"`, hidden `uiContext=prep-list`.
- Produces: `POST /items` URL-encoded mutation, in-memory `items`, HTML `<li>` fragment, `POST /__test/reset` isolation route.

- [ ] **Step 1: Add a failing human-path test before implementing `/items`**

Add a common reset setup and human proof:

```js
test.beforeEach(async ({ page, request }) => {
  const reset = await request.post('/__test/reset');
  expect(reset.status()).toBe(204);
  await page.goto('/');
  await page.waitForFunction(() => globalThis.surfaceRelayFixture?.ready === true);
});

test('normal human click uses real HTMX POST /items, swaps HTML, and persists server state', async ({ page }) => {
  await page.locator('input[name="name"]').fill('human-coffee');

  const requestPromise = page.waitForRequest((request) => {
    return request.method() === 'POST' && new URL(request.url()).pathname === '/items';
  });

  await page.locator('[data-surfacerelay-htmx-source]').click();
  const request = await requestPromise;
  const body = new URLSearchParams(request.postData() ?? '');

  expect(request.headers()['hx-request']).toBe('true');
  expect(request.headers()['content-type']).toContain('application/x-www-form-urlencoded');
  expect(body.get('name')).toBe('human-coffee');
  expect(body.get('uiContext')).toBe('prep-list');

  await expect(page.locator('#items li')).toContainText('human-coffee');
  await page.reload();
  await expect(page.locator('#items li')).toContainText('human-coffee');
});
```

- [ ] **Step 2: Run the human test and verify RED**

```bash
cd examples/htmx-prep-list
npm test -- --grep "normal human click"
```

Expected RED: reset route and/or `/items` mutation route is not implemented; no successful server persistence/swap exists.

- [ ] **Step 3: Implement bounded request parsing, reset, state mutation, HTML fragment response, and full-page persisted render**

Server state:

```js
const state = {
  items: [],
  nextItemId: 1,
};
```

Implement HTML escaping for all item names:

```js
function escapeHtml(value) {
  return value
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}
```

Implement `renderItem(item)` so full-page render and mutation response use the same function:

```js
function renderItem(item) {
  const name = escapeHtml(item.name);
  return `<li data-item-id="${item.id}" data-item-name="${name}">${name}</li>`;
}
```

Implement `POST /__test/reset`:

```text
items = []
nextItemId = 1
204 No Content
```

Implement `POST /items` with these exact rules:

```text
Content-Type must start application/x-www-form-urlencoded
body <= 16 KiB
name occurs exactly once
name length >= 1
uiContext may be present but is not used for authority/business selection
success = 201 text/html; charset=utf-8
```

Read the request incrementally and abort with `413` once the byte cap is exceeded; do not accumulate unbounded bodies.

- [ ] **Step 4: Run human proof and bootstrap regression**

```bash
cd examples/htmx-prep-list
npm test -- --grep "boots real HTMX|normal human click"
```

Expected: both PASS; reload still shows `human-coffee` from server state.

- [ ] **Step 5: Commit Task 2**

```bash
git add examples/htmx-prep-list/server.mjs examples/htmx-prep-list/tests/prep-list.spec.mjs
git commit -m "test(htmx): prove human fixture path"
```

---

### Task 3: Execute the real SurfaceRelay driver and prove Action override + host-state preservation

**Files:**
- Modify: `examples/htmx-prep-list/client.mjs`
- Modify/Test: `examples/htmx-prep-list/tests/prep-list.spec.mjs`

**Interfaces:**
- Consumes: module-local server-issued binding, production `HtmxBrowserDriver`, production `GlobalHtmxBrowserRuntime`.
- Produces: `surfaceRelayFixture.addItem(name): Promise<unknown>` that delegates only to `driver.execute(binding, { name }, {})`.

- [ ] **Step 1: Add the failing real-agent test**

```js
test('SurfaceRelay driver uses real HTMX and overrides stale same-name form state', async ({ page }) => {
  await page.locator('input[name="name"]').fill('stale-human-value');

  const requestPromise = page.waitForRequest((request) => {
    return request.method() === 'POST' && new URL(request.url()).pathname === '/items';
  });

  await page.evaluate(() => globalThis.surfaceRelayFixture.addItem('agent-tea'));
  const request = await requestPromise;
  const body = new URLSearchParams(request.postData() ?? '');

  expect(request.headers()['hx-request']).toBe('true');
  expect(request.headers()['content-type']).toContain('application/x-www-form-urlencoded');
  expect(body.get('name')).toBe('agent-tea');
  expect(body.get('uiContext')).toBe('prep-list');
  expect(body.get('name')).not.toBe('stale-human-value');

  await expect(page.locator('#items li')).toContainText('agent-tea');
  await page.reload();
  await expect(page.locator('#items li')).toContainText('agent-tea');
});
```

- [ ] **Step 2: Run the agent test and verify RED**

```bash
cd examples/htmx-prep-list
npm test -- --grep "SurfaceRelay driver uses real HTMX"
```

Expected RED: `surfaceRelayFixture.addItem` is absent; no driver invocation exists.

- [ ] **Step 3: Implement the minimal test bridge delegation**

Update `client.mjs` so the module-local `binding` and `driver` are used directly:

```js
globalThis.surfaceRelayFixture = Object.freeze({
  ready: true,
  addItem(name) {
    return driver.execute(binding, { name }, {});
  },
});
```

Do not add `fetch`, `XMLHttpRequest`, direct `htmx.ajax()`, manual DOM append, server-state mutation, or browser-side binding reconstruction.

- [ ] **Step 4: Run the agent test, human test, and browser-runtime driver regressions**

```bash
cd examples/htmx-prep-list
npm test -- --grep "normal human click|SurfaceRelay driver uses real HTMX"

cd ../../packages/browser-runtime
npm run typecheck
npm test
```

Expected: fixture tests PASS; browser-runtime typecheck and 297/297-or-higher unit/integration suite remain green.

- [ ] **Step 5: Commit Task 3**

```bash
git add examples/htmx-prep-list/client.mjs examples/htmx-prep-list/tests/prep-list.spec.mjs
git commit -m "test(htmx): prove real driver fixture path"
```

---

### Task 4: Prove human/agent convergence and page-scoped identity renewal

**Files:**
- Modify/Test: `examples/htmx-prep-list/tests/prep-list.spec.mjs`
- Modify only if test exposes a fixture bug: `examples/htmx-prep-list/server.mjs`

**Interfaces:**
- Consumes: real human click path and `surfaceRelayFixture.addItem(name)`.
- Produces: executable normalized request-signature comparison and reload-based source/binding renewal proof.

- [ ] **Step 1: Add a convergence test comparing actual network signatures**

Add a helper in the test file:

```js
function normalizedRequestSignature(request) {
  const headers = request.headers();
  const body = new URLSearchParams(request.postData() ?? '');
  return {
    method: request.method(),
    pathname: new URL(request.url()).pathname,
    hxRequest: headers['hx-request'],
    contentType: headers['content-type']?.split(';')[0],
    uiContext: body.get('uiContext'),
  };
}
```

Then add:

```js
test('human and SurfaceRelay execution converge on the same real HTMX request path', async ({ page }) => {
  await page.locator('input[name="name"]').fill('human-item');
  const humanRequestPromise = page.waitForRequest((request) =>
    request.method() === 'POST' && new URL(request.url()).pathname === '/items');
  await page.locator('[data-surfacerelay-htmx-source]').click();
  const humanRequest = await humanRequestPromise;

  const agentRequestPromise = page.waitForRequest((request) =>
    request.method() === 'POST' && new URL(request.url()).pathname === '/items');
  await page.evaluate(() => globalThis.surfaceRelayFixture.addItem('agent-item'));
  const agentRequest = await agentRequestPromise;

  expect(normalizedRequestSignature(agentRequest)).toEqual(
    normalizedRequestSignature(humanRequest),
  );
});
```

- [ ] **Step 2: Add the page lifecycle renewal test**

```js
test('full page reload renews sourceId and bindingId together', async ({ page }) => {
  const first = await page.evaluate(() => {
    const binding = JSON.parse(document.querySelector('#surfacerelay-binding').textContent);
    return {
      sourceId: document.querySelector('[data-surfacerelay-htmx-source]')
        .getAttribute('data-surfacerelay-htmx-source'),
      bindingId: binding.bindingId,
      targetSourceId: binding.target.sourceId,
    };
  });

  await page.reload();
  await page.waitForFunction(() => globalThis.surfaceRelayFixture?.ready === true);

  const second = await page.evaluate(() => {
    const binding = JSON.parse(document.querySelector('#surfacerelay-binding').textContent);
    return {
      sourceId: document.querySelector('[data-surfacerelay-htmx-source]')
        .getAttribute('data-surfacerelay-htmx-source'),
      bindingId: binding.bindingId,
      targetSourceId: binding.target.sourceId,
    };
  });

  expect(first.targetSourceId).toBe(first.sourceId);
  expect(second.targetSourceId).toBe(second.sourceId);
  expect(second.sourceId).not.toBe(first.sourceId);
  expect(second.bindingId).not.toBe(first.bindingId);
});
```

- [ ] **Step 3: Run the two proofs**

```bash
cd examples/htmx-prep-list
npm test -- --grep "converge|renews sourceId"
```

Expected: PASS without production browser-runtime changes. If either fails because the fixture differs from the written contract, fix only the fixture implementation. If failure reveals T-602 semantics are wrong, stop and reopen design rather than changing browser-runtime behavior inside T-603.

- [ ] **Step 4: Run all fixture tests accumulated so far**

```bash
cd examples/htmx-prep-list
npm test
```

Expected: bootstrap, human, agent override, convergence, and lifecycle proofs all PASS.

- [ ] **Step 5: Commit Task 4**

```bash
git add examples/htmx-prep-list/tests/prep-list.spec.mjs
git commit -m "test(htmx): prove real path convergence"
```

---

### Task 5: Prove stale-source no-retargeting and harden fixture HTTP/static boundaries

**Files:**
- Modify: `examples/htmx-prep-list/client.mjs`
- Modify: `examples/htmx-prep-list/server.mjs`
- Modify/Test: `examples/htmx-prep-list/tests/prep-list.spec.mjs`

**Interfaces:**
- Consumes: old module-local RuntimeBinding and exact current source.
- Produces: `surfaceRelayFixture.replaceSourceForTest()` DOM-only helper; executable stale/no-request proof; server traversal/input/rendering hardening.

- [ ] **Step 1: Add a failing real-DOM stale replacement test**

```js
test('old binding fails stale after equivalent real-DOM source replacement without sending /items', async ({ page }) => {
  let itemRequests = 0;
  page.on('request', (request) => {
    if (request.method() === 'POST' && new URL(request.url()).pathname === '/items') {
      itemRequests += 1;
    }
  });

  const replacement = await page.evaluate(() => globalThis.surfaceRelayFixture.replaceSourceForTest());
  expect(replacement.oldSourceId).not.toBe(replacement.newSourceId);

  const result = await page.evaluate(async () => {
    try {
      await globalThis.surfaceRelayFixture.addItem('must-not-run');
      return { resolved: true };
    } catch (error) {
      return { resolved: false, code: error?.code, name: error?.name };
    }
  });

  expect(result).toMatchObject({
    resolved: false,
    code: 'binding_stale',
    name: 'HtmxBindingExecutionError',
  });
  expect(itemRequests).toBe(0);

  await page.reload();
  await expect(page.locator('#items')).not.toContainText('must-not-run');
});
```

- [ ] **Step 2: Run the stale test and verify RED**

```bash
cd examples/htmx-prep-list
npm test -- --grep "old binding fails stale"
```

Expected RED: `replaceSourceForTest()` is absent.

- [ ] **Step 3: Implement DOM-only replacement without changing the binding**

Update `client.mjs`:

```js
function replaceSourceForTest() {
  const oldSource = document.querySelector('[data-surfacerelay-htmx-source]');
  if (!oldSource) throw new Error('Exact HTMX source is missing.');

  const oldSourceId = oldSource.getAttribute('data-surfacerelay-htmx-source');
  const newSourceId = `prep-add-replacement-${crypto.randomUUID()}`;
  const replacement = oldSource.cloneNode(true);
  replacement.setAttribute('data-surfacerelay-htmx-source', newSourceId);
  oldSource.replaceWith(replacement);

  return { oldSourceId, newSourceId };
}
```

Expose this function through the frozen bridge without reconstructing the original binding.

- [ ] **Step 4: Add server/static fail-closed tests**

Add one request-contract test using Playwright's `request` fixture:

```js
test('fixture server rejects invalid mutation and traversal requests fail closed', async ({ request }) => {
  expect((await request.get('/does-not-exist')).status()).toBe(404);
  expect((await request.get('/items')).status()).toBe(405);

  const wrongType = await request.post('/items', {
    headers: { 'content-type': 'application/json' },
    data: JSON.stringify({ name: 'x' }),
  });
  expect(wrongType.status()).toBe(415);

  const empty = await request.post('/items', {
    headers: { 'content-type': 'application/x-www-form-urlencoded' },
    data: 'name=&uiContext=prep-list',
  });
  expect(empty.status()).toBe(422);

  const oversized = await request.post('/items', {
    headers: { 'content-type': 'application/x-www-form-urlencoded' },
    data: `name=${'x'.repeat(17 * 1024)}`,
  });
  expect(oversized.status()).toBe(413);

  expect((await request.get('/runtime/../server.mjs')).status()).toBe(404);
  expect((await request.get('/runtime/%2e%2e/server.mjs')).status()).toBe(404);
  expect((await request.get('/runtime/foo/bar.js')).status()).toBe(404);
});
```

Ensure known routes return `405` for wrong methods and unknown routes return `404`.

- [ ] **Step 5: Add a real rendering/XSS regression**

```js
test('server renders item names as text in partials and full-page reloads', async ({ page }) => {
  const attack = '<script>globalThis.__fixtureXss = true</script>';
  await page.locator('input[name="name"]').fill(attack);
  await page.locator('[data-surfacerelay-htmx-source]').click();

  await expect(page.locator('#items li')).toHaveText(attack);
  expect(await page.evaluate(() => globalThis.__fixtureXss ?? false)).toBe(false);

  await page.reload();
  await expect(page.locator('#items li')).toHaveText(attack);
  expect(await page.evaluate(() => globalThis.__fixtureXss ?? false)).toBe(false);
});
```

Server must use the same escaped `renderItem()` for partial and full-page render.

- [ ] **Step 6: Run stale/security tests, then the full fixture suite**

```bash
cd examples/htmx-prep-list
npm test -- --grep "old binding fails stale|rejects invalid mutation|renders item names"
npm test
```

Expected: all fixture tests PASS with `workers: 1`, `retries: 0`.

- [ ] **Step 7: Commit Task 5**

```bash
git add \
  examples/htmx-prep-list/client.mjs \
  examples/htmx-prep-list/server.mjs \
  examples/htmx-prep-list/tests/prep-list.spec.mjs

git commit -m "test(htmx): harden real fixture proof"
```

---

### Task 6: Add fixture CI, documentation, full verification, and promote D-057 only after proof succeeds

**Files:**
- Create: `.github/workflows/htmx-fixture.yml`
- Create: `examples/htmx-prep-list/README.md`
- Modify after verified implementation: `docs/DECISION-REGISTER.md`
- Modify after verified implementation: `TASKS.md`
- Modify after verified implementation: `STATUS.md`
- Modify at review-prep: `REVIEW_REQUEST.md`

**Interfaces:**
- Consumes: complete local fixture suite and existing repository `validate` workflow.
- Produces: path-filtered real-browser CI proof, reproducible local run instructions, accepted `D-057` after evidence, T-603 external-review handoff. `D-020` remains proposed.

- [ ] **Step 1: Write the path-filtered real-browser workflow**

Create `.github/workflows/htmx-fixture.yml`:

```yaml
name: htmx-fixture

on:
  push:
    paths:
      - 'examples/htmx-prep-list/**'
      - 'examples/prep-list/action.add-item.json'
      - 'packages/browser-runtime/src/**'
      - 'packages/browser-runtime/tsconfig.json'
      - '.github/workflows/htmx-fixture.yml'
  pull_request:
    paths:
      - 'examples/htmx-prep-list/**'
      - 'examples/prep-list/action.add-item.json'
      - 'packages/browser-runtime/src/**'
      - 'packages/browser-runtime/tsconfig.json'
      - '.github/workflows/htmx-fixture.yml'

jobs:
  htmx-fixture:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: '22'
          cache: npm
          cache-dependency-path: examples/htmx-prep-list/package-lock.json
      - name: Install fixture dependencies
        working-directory: examples/htmx-prep-list
        run: npm ci
      - name: Install Chromium
        working-directory: examples/htmx-prep-list
        run: npx playwright install --with-deps chromium
      - name: Run real HTMX fixture
        working-directory: examples/htmx-prep-list
        run: npm test
```

Do not change `.github/workflows/validate.yml` unless the new workflow reveals an explicit repository-level contradiction requiring a separate design decision.

- [ ] **Step 2: Write the fixture README with exact proof and commands**

`examples/htmx-prep-list/README.md` must document:

```text
Purpose: real non-Laravel T-603 proof, not a production starter app.
Runtime: Node 22 + HTMX 2.0.10 + Playwright Chromium.
Action: reuses ../prep-list/action.add-item.json.
Business route: POST /items only.
Test-only route: POST /__test/reset only.
Human path: button click -> HTMX -> /items -> fragment -> swap.
Agent path: HtmxBrowserDriver.execute -> host HTMX -> same /items -> same fragment -> same swap.
Boundary: D-020 remains proposed until T-604.
```

Local commands:

```bash
cd examples/htmx-prep-list
npm ci
npx playwright install chromium
npm test
```

Also state that server binds `127.0.0.1:4173`, fails if occupied, generated runtime lives under `.tmp/`, and fixture bridge is test-only delegation rather than a second mutation path.

- [ ] **Step 3: Run fresh full local verification before any decision promotion**

```bash
cd examples/htmx-prep-list
rm -rf .tmp
npm ci
npm run build:runtime
npx playwright test

cd ../../packages/browser-runtime
npm ci
npm run typecheck
npm test

cd ../..
python scripts/validate.py
```

Expected before promotion:

```text
fixture Playwright: all tests pass, retries 0
browser runtime: typecheck green, existing 297/297-or-higher suite green
contract validator: green
```

Also inspect the git diff and confirm no committed `.tmp`, browser binary, `node_modules`, or dependency changes outside the fixture package.

- [ ] **Step 4: Commit workflow + README while D-057 is still PROPOSED**

```bash
git add \
  .github/workflows/htmx-fixture.yml \
  examples/htmx-prep-list/README.md

git commit -m "ci(htmx): verify real fixture in Chromium"
```

Push/commit through the repository integration and wait for both fresh workflow families on the exact implementation head:

```text
validate       -> 7/7 green
htmx-fixture   -> 1/1 green
```

Do not promote D-057 if either workflow is missing, skipped because of a bad path filter, cancelled, flaky, or failing.

- [ ] **Step 5: After exact-head CI is green, promote D-057 and update T-603 tracking**

Change only after the evidence in Step 4 exists:

```text
D-057: PROPOSED -> ACCEPTED
T-603: IMPLEMENTED / SELF-REVIEWED / READY FOR EXTERNAL REVIEW
D-020: stays PROPOSED
T-604: stays NOT STARTED
```

`docs/DECISION-REGISTER.md` final D-057 wording remains the design wording; only status changes to `ACCEPTED` unless implementation evidence requires a precisely documented narrowing.

`TASKS.md` and `STATUS.md` must record:

```text
final implementation head
validate workflow run ID and 7/7 result
htmx-fixture workflow run ID and 1/1 result
Playwright test count
browser-runtime regression count/typecheck result
D-057 ACCEPTED
D-020 PROPOSED
T-604 NOT STARTED
```

Update `REVIEW_REQUEST.md` to a T-603 external-review record focusing reviewers on:

```text
real same-path proof
single /items business mutation route
real HTMX version/runtime loading
Action override + host form state
source/binding renewal
stale no-retarget/no-request behavior
static/path traversal safety
HTML/script-context escaping
fixture-only dependency ownership
path-filter CI correctness
no T-604 scope creep
```

- [ ] **Step 6: Commit review-prep tracking**

```bash
git add docs/DECISION-REGISTER.md TASKS.md STATUS.md REVIEW_REQUEST.md
git commit -m "docs(htmx): prepare T-603 fixture review"
```

- [ ] **Step 7: Verify the exact review-prep head again**

On the new exact head, require fresh evidence:

```text
validate       -> 7/7 green
htmx-fixture   -> 1/1 green
```

Fetch the fixture job log and confirm the actual Playwright test count. Fetch the browser job log and confirm TypeScript typecheck plus the browser-runtime regression count. Do not infer success from an earlier commit.

- [ ] **Step 8: Stop at external-review gate**

Report the exact feature head, workflow run IDs, Playwright count, browser-runtime count, accepted/proposed decisions, and changed-file scope. Do not create/merge a PR or begin T-604 without the next explicit user gate.

---

## Final Acceptance Checklist

Before declaring T-603 ready for external review, verify each item explicitly against the implementation and test evidence:

1. Fixture is a non-Laravel Node 22 application using built-in `node:http`.
2. Server binds only `127.0.0.1:4173` and does not reuse/fallback to another server/port.
3. Fixture package pins HTMX 2.0.10, Playwright Test 1.63.0, and TypeScript 5.9.3.
4. Browser-runtime and Laravel dependency manifests/locks are unchanged.
5. Existing `prep_list.add_item@1` ActionDefinition is reused and not duplicated.
6. Actual browser-runtime TypeScript source is emitted to `.tmp/runtime` and imported by Chromium.
7. Real host `globalThis.htmx.version` is 2.0.10.
8. Every page render issues a fresh sourceId and bindingId.
9. Embedded binding target sourceId equals the exact rendered source identity.
10. `POST /items` is the only business mutation route.
11. Human click path does not invoke SurfaceRelay driver code.
12. Agent bridge only invokes `HtmxBrowserDriver.execute()` and never direct HTTP/HTMX mutation APIs.
13. Human request is real HTMX with `HX-Request: true`.
14. Agent request is real HTMX with `HX-Request: true`.
15. Human and agent normalized request signatures converge on method/path/content-type/host state.
16. Agent Action value overrides a stale same-name form value.
17. Ordinary hidden host form state survives into the real agent request.
18. Both paths use the same source-defined `hx-target` / `hx-swap` response behavior.
19. Full reload proves both paths mutate server state rather than only the DOM.
20. Full reload renews page-scoped source and binding identity.
21. Equivalent replacement source does not inherit old identity.
22. Old binding fails `binding_stale` against replacement DOM.
23. Stale execution sends zero `/items` requests and leaves server state unchanged.
24. Test reset endpoint only clears state and never adds business data.
25. Static runtime route rejects nested/traversal/encoded traversal paths.
26. Mutation request body is bounded and invalid requests fail closed.
27. Item names are escaped in both fragment and full-page rendering.
28. Embedded binding JSON is script-safe (`<` escaped as `\u003c`).
29. Generated artifacts are not committed.
30. Existing `validate` workflow remains 7/7 green.
31. Path-filtered `htmx-fixture` workflow executes and is 1/1 green on relevant changes.
32. `D-057` is accepted only after exact implementation-head real-browser CI succeeds.
33. `D-020` remains proposed.
34. T-604 is not started and no shared cross-driver conformance claim is made.
