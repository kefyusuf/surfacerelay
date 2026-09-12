# T-603 — Non-Laravel HTMX Fixture Design

## Status

- Task: `T-603 — Non-Laravel HTMX fixture app`
- Milestone: `M6 — HTMX Portability Proof`
- Branch: `feat/htmx-fixture`
- Base: `main@83df122d84f6881a4a3541bd5c72e1108e5d1e48`
- Design state: **APPROVED IN CHAT / WRITTEN SPEC UNDER REVIEW**
- Implementation state: **NOT STARTED**
- Existing HTMX decisions: `D-053`, `D-054`, `D-055`, `D-056` — **ACCEPTED**
- Proposed fixture decision: `D-057`
- Portability decision: `D-020` — remains **PROPOSED** through T-604

T-603 is an architectural proof task. It does not add another browser driver or another server-side trust-control implementation. It proves the already-merged T-601/T-602 HTMX binding against a real non-Laravel application with a real browser, real HTMX 2.x runtime, real HTTP request, real server mutation, and real HTMX DOM swap.

Implementation must not begin until this written spec is reviewed and approved.

## Objective

Build the smallest executable fixture that proves all of the following in a real Chromium session:

1. a normal human click and a SurfaceRelay HTMX-driver invocation use the same rendered HTMX request surface;
2. both paths use the host page's real HTMX 2.x runtime;
3. both paths send the same business mutation route and method;
4. the agent path preserves ordinary host form state while SurfaceRelay Action input overrides a same-named stale form value;
5. both paths consume the same server response through the same source-defined HTMX target/swap behavior;
6. the mutation is real server state, not client-side DOM simulation;
7. page reload renews exact source identity;
8. replacing an exact source with an equivalent new source does not let an old binding retarget itself.

T-603 proves a real second binding fixture. It does **not** establish cross-driver conformance. T-604 remains the gate for shared Livewire/HTMX scenarios and for any promotion of D-020.

## Proposed decision — D-057: real HTMX fixture proof boundary

Record during design/implementation as `PROPOSED`:

> The T-603 reference fixture proves the HTMX adapter against a real non-Laravel Node HTTP application, real HTMX 2.x browser runtime, real Chromium DOM/network execution, and the exact `prep_list.add_item@1` ActionDefinition. Human interaction and SurfaceRelay execution must converge on the same rendered HTMX source semantics, same `/items` business mutation route, same server state transition, and same HTMX response-swap behavior. Test-only reset/bootstrap helpers may control fixture state or invoke the production driver, but may never provide a second business mutation path. T-603 alone does not establish shared cross-driver conformance or promote D-020.

D-057 becomes `ACCEPTED` only after the complete real-fixture verification gate succeeds.

## Architecture choice

Selected architecture:

```text
Node 22 built-in HTTP server
        +
htmx.org 2.0.10
        +
real compiled SurfaceRelay browser-runtime source
        +
Playwright Test 1.63.0 / Chromium
```

### Why plain Node HTTP

The fixture should prove HTMX/browser portability, not introduce an unrelated server framework. Express, Hono, Fastify, Laravel, or another application framework would add dependencies and architectural questions that do not contribute to the proof.

The server therefore uses `node:http`, in-memory state, exact route handling, and a small explicit static-file map.

### Why real Playwright/Chromium

T-602 intentionally used structural DOM/runtime fakes. T-603 must move one level higher and prove the merged production implementation against an actual browser, actual DOM, actual network request, and actual HTMX response swap. Static HTML tests, jsdom/happy-dom, or mocked network calls do not satisfy that boundary.

## Fixture location and package boundary

Create an independent private npm package:

```text
examples/htmx-prep-list/
├── package.json
├── package-lock.json
├── tsconfig.runtime.json
├── server.mjs
├── client.mjs
├── playwright.config.mjs
├── README.md
└── tests/
    └── prep-list.spec.mjs
```

Generated files live only under the already ignored root `.tmp/` tree:

```text
examples/htmx-prep-list/.tmp/
├── runtime/
└── test-results/
```

No generated JavaScript, Playwright output, browser binaries, or `node_modules` are committed.

### Dependency ownership

Fixture package dependencies only:

```text
dependencies
└── htmx.org = 2.0.10

devDependencies
├── @playwright/test = 1.63.0
└── typescript = 5.9.3
```

Exact pinning is deliberate for the reference fixture.

These dependency files may change:

```text
examples/htmx-prep-list/package.json
examples/htmx-prep-list/package-lock.json
```

These dependency files must remain unchanged:

```text
packages/browser-runtime/package.json
packages/browser-runtime/package-lock.json
packages/laravel/composer.json
packages/laravel/composer.lock
```

HTMX and Playwright are fixture dependencies, not SurfaceRelay library/runtime dependencies.

## Existing ActionDefinition reuse

The fixture must reuse:

```text
examples/prep-list/action.add-item.json
```

for `prep_list.add_item@1`.

The fixture must not define a second equivalent ActionDefinition.

The Node application reads and parses the existing JSON file. It uses the Action Definition for action identity, input-schema-driven HTMX target creation, and the embedded RuntimeBinding reference.

T-603 does **not** reimplement Laravel ActionBus, authorization, confirmation, idempotency, output policy, or audit infrastructure in Node. Those are not fixture goals.

## Production browser-runtime reuse

The fixture must execute the actual merged browser-runtime implementation. It must not copy, rewrite, bundle by hand, or recreate `HtmxBrowserDriver` logic.

A fixture-local `tsconfig.runtime.json` extends the browser-runtime TypeScript config and changes only emit behavior:

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

The generated `.tmp/runtime/*.js` files are served to Chromium as native ESM modules.

The page imports the actual emitted implementations, including at minimum:

```text
HtmxBrowserDriver
GlobalHtmxBrowserRuntime
createHtmxBindingTarget
```

No fixture-local mini-driver may substitute for production code.

## HTTP application contract

The application binds only to:

```text
127.0.0.1:4173
```

A busy port causes startup failure. The fixture must not silently choose another port and must not bind `0.0.0.0`.

### Routes

The only required routes are:

| Route | Method | Purpose |
|---|---|---|
| `/` | GET | full prep-list page |
| `/items` | POST | **only business mutation path** |
| `/vendor/htmx.min.js` | GET | pinned HTMX browser distribution |
| `/client.mjs` | GET | fixture bootstrap module |
| `/runtime/<flat-file>.js` | GET | emitted SurfaceRelay runtime modules |
| `/__test/reset` | POST | test-only in-memory state reset |

The fixture must not expose a second mutation path such as `/agent/items`, `/surfacerelay/items`, `/api/items`, or `/__test/add-item`.

### Unknown and invalid requests

Minimum explicit behavior:

```text
unknown route        -> 404
wrong method         -> 405 where route is known
unsupported content  -> 400/415 as appropriate
body over 16 KiB     -> 413
invalid/empty name   -> 422
unexpected failure   -> 500
```

The fixture is not an HTTP error-taxonomy project; these rules exist to avoid an ambiguous or unsafe demo server.

## Server state

Use one process-local in-memory list:

```text
items = [{ id, name }, ...]
nextItemId
```

No database is required.

The proof that a mutation is server-side is a full page reload: an item added through human or agent execution must still be rendered after reload.

`POST /__test/reset` clears the list and resets the counter, then returns `204 No Content`.

No `GET /__test/state` is required. Normal `GET /` is sufficient to prove persisted fixture state.

## Full page render

Every `GET /` creates a fresh page instance:

```text
sourceId  = prep-add-<uuid>
bindingId = htmx-binding-<uuid>
```

The server creates the HTMX target through the actual production helper:

```text
createHtmxBindingTarget(
    definition,
    {
        sourceId,
        method: 'POST',
        path: '/items'
    }
)
```

and embeds a RuntimeBinding with:

```text
driver = htmx
lifecycle = page
expiresAt = null
```

A full page reload must therefore produce a new sourceId and bindingId.

## Rendered HTMX source

The human-facing form contains ordinary host state and one exact HTMX source:

```html
<form id="prep-form">
  <input type="hidden" name="uiContext" value="prep-list">
  <input name="name">

  <button
    data-surfacerelay-htmx-source="prep-add-..."
    hx-post="/items"
    hx-target="#items"
    hx-swap="beforeend"
  >
    Add
  </button>
</form>

<ul id="items"></ul>
```

The button, not the form, is the SurfaceRelay source. This avoids active form validation semantics while allowing real HTMX non-GET form-value collection.

No unsupported T-602 reference-source mechanism may appear on the source or ancestors.

## Binding transport into the page

Embed the exact server-created RuntimeBinding as JSON:

```html
<script type="application/json" id="surfacerelay-binding">
{...}
</script>
```

Before embedding, `<` must be escaped to `\u003c` so JSON content cannot terminate the script element.

The client parses only this server-issued binding. It must not reconstruct or rediscover a new binding in the browser.

## HTMX runtime loading

The server serves the pinned package distribution at:

```text
/vendor/htmx.min.js
```

The page loads scripts in this order:

```html
<script src="/vendor/htmx.min.js"></script>
<script type="module" src="/client.mjs"></script>
```

`client.mjs` therefore starts with the host page's real `globalThis.htmx` already present.

T-603 must verify that the loaded version is a supported HTMX 2.x runtime and specifically originates from the fixture-pinned 2.0.10 package.

## Client bootstrap and test bridge

`client.mjs` remains a very small adapter:

```text
parse embedded RuntimeBinding
        ↓
new GlobalHtmxBrowserRuntime()
        ↓
new HtmxBrowserDriver(runtime)
        ↓
expose a minimal test bridge
```

Allowed bridge shape:

```text
surfaceRelayFixture.ready
surfaceRelayFixture.addItem(name)
surfaceRelayFixture.replaceSourceForTest()
```

### `addItem(name)`

May only delegate to:

```text
HtmxBrowserDriver.execute(binding, { name }, {})
```

It must not call `fetch`, `XMLHttpRequest`, `htmx.ajax()` directly, manipulate server state, append item DOM, or recreate the binding.

### `replaceSourceForTest()`

May only manipulate DOM for the stale-target proof:

1. remove the exact old source;
2. insert a new button with a different `data-surfacerelay-htmx-source`;
3. keep equivalent `hx-post="/items"`, `hx-target="#items"`, and `hx-swap="beforeend"` behavior;
4. leave the original RuntimeBinding unchanged in client memory.

It must not mutate server state.

## `POST /items` request contract

The endpoint expects normal HTMX form encoding:

```text
Content-Type: application/x-www-form-urlencoded
```

The server enforces a bounded request body, parses `name`, and ignores `uiContext` for authority/business selection purposes.

`uiContext=prep-list` exists only to prove preservation of ordinary host form state.

The route creates exactly one server item and returns only the HTML fragment needed by the source-defined swap:

```html
<li data-item-id="3" data-item-name="coffee">coffee</li>
```

Recommended success status:

```text
201 Created
```

The response does not return a SurfaceRelay business result object. The proof is server state plus HTMX swap behavior.

## HTML safety

All item names rendered into the full page or response fragment must HTML-escape:

```text
&
<
>
"
'
```

A user value such as `<script>alert(1)</script>` must render as text, never executable markup.

Binding JSON must use the script-safe `< -> \u003c` encoding described above.

## Static file serving safety

Do not implement a generic project-root static server.

Serve `/client.mjs` and `/vendor/htmx.min.js` through explicit file mappings.

For `/runtime/<filename>.js`, accept only one flat emitted filename matching:

```regex
^[A-Za-z0-9._-]+\.js$
```

Path traversal and nested paths fail closed, including encoded traversal forms.

The fixture must never translate arbitrary URL paths into filesystem paths with an unrestricted `path.join(root, pathname)` pattern.

## Human execution path

A normal human/browser interaction is:

```text
fill input[name=name]
        ↓
click exact HTMX button
        ↓
real HTMX 2.0.10
        ↓
POST /items
        ↓
server mutation
        ↓
HTML fragment response
        ↓
source hx-target / hx-swap
        ↓
#items updated
```

No SurfaceRelay driver method participates in this path.

## SurfaceRelay execution path

The agent/browser-fixture path is:

```text
surfaceRelayFixture.addItem('agent-tea')
        ↓
actual HtmxBrowserDriver.execute()
        ↓
actual GlobalHtmxBrowserRuntime
        ↓
exact rendered sourceId
        ↓
real host htmx.ajax()
        ↓
POST /items
        ↓
same server mutation
        ↓
same HTML fragment
        ↓
same source hx-target / hx-swap
```

There is no agent-only HTTP route and no direct fixture-side `htmx.ajax()` call.

## Action override and host-state proof

The real agent test intentionally creates a conflict:

```text
DOM input[name=name] = stale-human-value
SurfaceRelay input   = agent-tea
hidden uiContext     = prep-list
```

The real network request must contain:

```text
name=agent-tea
uiContext=prep-list
```

This proves on a real HTMX runtime that the explicit SurfaceRelay Action value overrides the same-named host form value while unrelated ordinary host form state remains present.

Neither field is treated as trusted authorization/context authority by the fixture server.

## Same-path network proof

Playwright must capture real requests and compare normalized human/agent signatures.

Required shared facts:

```text
method       = POST
pathname     = /items
HX-Request   = true
content type = application/x-www-form-urlencoded
uiContext    = prep-list
```

Only the intended business input differs between the two test invocations.

The test may not infer convergence only from DOM appearance.

## Page lifecycle proof

A full page reload must yield:

```text
source A != source B
binding A != binding B
binding B.target.sourceId == source B
```

This demonstrates that `lifecycle=page` refers to one exact rendered page instance rather than a permanent business target.

## Real stale-replacement proof

The test obtains a page and therefore binding/source A, then invokes `replaceSourceForTest()`.

The DOM then contains source B with the same HTMX method/path/target/swap semantics but a different source identity.

Executing the old binding must:

```text
reject with HtmxBindingExecutionError.code == binding_stale
send zero POST /items requests
leave replacement source B unused
leave server state unchanged
```

A subsequent full reload proves that the rejected stale invocation did not add an item.

## Playwright lifecycle

Use a fixed loopback server:

```text
http://127.0.0.1:4173
```

`playwright.config.mjs` owns the server process:

```text
workers = 1
retries = 0
reuseExistingServer = false
```

`reuseExistingServer=false` is deliberate. If another process already owns the reference port, the proof must fail rather than accidentally test unrelated software.

`webServer.url` is the normal root page; no separate readiness endpoint is required.

Each test starts with:

```text
POST /__test/reset
GET /
wait surfaceRelayFixture.ready == true
```

The reset request is issued by Playwright's test/request harness, not through the fixture's business UI path.

## Required E2E test matrix

T-603 must provide at least these six real-browser tests.

### E2E-1 — runtime and bootstrap

Prove:

```text
real HTMX runtime is 2.0.10 / supported 2.x
exact source count is one
embedded binding driver == htmx
embedded binding lifecycle == page
embedded binding sourceId == exact DOM sourceId
```

### E2E-2 — normal human HTMX path

Prove:

```text
fill human-coffee
click human-facing button
observe real POST /items
HX-Request == true
request name == human-coffee
DOM receives response fragment
reload
human-coffee still exists
```

No SurfaceRelay driver call is used.

### E2E-3 — agent path plus input override

Prove:

```text
DOM name = stale-human-value
hidden uiContext = prep-list
SurfaceRelay addItem('agent-tea')
observe real POST /items
request name == agent-tea
request uiContext == prep-list
HX-Request == true
DOM receives response fragment
reload
agent-tea still exists
```

### E2E-4 — human/agent convergence

Capture one human request and one agent request and compare normalized signatures:

```text
same POST /items
same HX-Request header semantics
same urlencoded request class
same ordinary host uiContext state
same HTMX response-swap mechanism
```

Only the business input value intentionally differs.

### E2E-5 — page lifecycle renewal

Prove a full reload creates a new exact source and new embedded binding identity while preserving the same Action identity and request contract.

### E2E-6 — exact stale replacement

Prove an old binding does not use a replacement source with equivalent HTMX attributes and produces no business request/state mutation.

## Test isolation

Use one worker and zero retries because the Node server stores in-memory state.

Each test resets state explicitly before navigation.

Flakiness must not be hidden by retrying a failed portability proof.

No test may depend on execution order or state left by another test.

## Fixture scripts

Expected package scripts:

```json
{
  "scripts": {
    "build:runtime": "tsc -p tsconfig.runtime.json",
    "start": "node server.mjs",
    "test": "npm run build:runtime && playwright test"
  }
}
```

Local verification flow:

```text
npm ci
npx playwright install chromium
npm test
```

CI installs Chromium with required Linux system dependencies.

## CI architecture

Do not add Chromium installation to the existing always-running `validate.yml` browser job.

Add a separate path-filtered workflow:

```text
.github/workflows/htmx-fixture.yml
```

### Trigger paths

The fixture workflow must run when any of these change:

```text
examples/htmx-prep-list/**
examples/prep-list/action.add-item.json
packages/browser-runtime/src/**
packages/browser-runtime/tsconfig.json
.github/workflows/htmx-fixture.yml
```

It does not need to run for unrelated PHP/docs-only changes.

### Fixture job

One job:

```text
htmx-fixture
```

with:

```text
Node 22
npm ci in examples/htmx-prep-list
npx playwright install --with-deps chromium
npm test
```

The existing `validate.yml` remains unchanged in semantics and must continue to pass all existing 7 jobs.

T-603 review gate therefore requires:

```text
validate workflow      -> 7/7 green
htmx-fixture workflow  -> 1/1 green when triggered
```

## Explicitly out of scope

T-603 must not add:

- another SurfaceRelay browser driver;
- another generic HTTP driver;
- HTMX 4/beta compatibility;
- Laravel code or Laravel fixture integration;
- Node implementation of ActionBus/authorization/confirmation/idempotency/audit/output policy;
- database/persistent datastore;
- WebMCP shared conformance runner;
- T-604 cross-driver scenarios;
- `spec/0.1/**` changes;
- acceptance of D-020;
- business mutation through test-only endpoints;
- direct browser test bridge calls to `fetch`, `XMLHttpRequest`, or `htmx.ajax()`.

## Acceptance criteria

T-603 implementation is acceptable only when all of the following are proven:

1. Fixture is a non-Laravel Node 22 application.
2. `POST /items` is the only business mutation route.
3. Human and SurfaceRelay paths both use `POST /items`.
4. Human path is a normal HTMX click and contains no SurfaceRelay driver invocation.
5. Agent path uses the merged `HtmxBrowserDriver` and `GlobalHtmxBrowserRuntime` implementations.
6. Browser-runtime source is compiled from `packages/browser-runtime/src`; it is not copied into the fixture.
7. Compiled runtime executes in Chromium as real ESM.
8. Fixture uses real pinned `htmx.org@2.0.10`.
9. Fixture tests use real Chromium through Playwright.
10. Existing `prep_list.add_item@1` ActionDefinition is reused.
11. Every full page render creates a fresh `sourceId` and `bindingId`.
12. Embedded binding sourceId exactly matches the rendered source.
13. Human request has real HTMX `HX-Request` semantics.
14. Agent request has real HTMX `HX-Request` semantics.
15. Human and agent request signatures converge on method/path/content type/ordinary host state.
16. SurfaceRelay Action input overrides a same-named stale form value.
17. Unrelated hidden host form state remains present in the real agent request.
18. Human and agent both use source-defined HTMX target/swap behavior.
19. Human and agent both produce real server state visible after full reload.
20. A real DOM replacement causes the old binding to fail `binding_stale`.
21. The replacement source is not automatically selected or retargeted.
22. Stale invocation sends no `POST /items` and makes no server mutation.
23. Test-only reset exists only for isolation and is not a business mutation path.
24. Client bridge delegates only to production driver execution or DOM-only stale-test manipulation.
25. Server binds only to loopback.
26. Runtime/static serving rejects path traversal and does not expose project-root arbitrary files.
27. Rendered item input and embedded binding JSON are safely escaped for their HTML/script contexts.
28. Generated runtime and test artifacts remain ignored under `.tmp/`.
29. Browser-runtime and Laravel package dependency manifests/locks remain unchanged.
30. Existing `validate` workflow remains fully green.
31. Path-filtered real-browser fixture workflow is green when triggered.
32. D-057 is promoted only after the real-fixture verification succeeds.
33. D-020 remains `PROPOSED` after T-603.
34. T-604 shared cross-driver conformance does not enter T-603 scope.

## Expected implementation surface

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

Tracking/review documents may change as the task progresses:

```text
docs/DECISION-REGISTER.md
TASKS.md
STATUS.md
REVIEW_REQUEST.md
```

Expected unchanged implementation contracts:

```text
packages/browser-runtime/src/**
packages/browser-runtime/package.json
packages/browser-runtime/package-lock.json
packages/laravel/**
spec/0.1/**
examples/prep-list/action.add-item.json
```

If real fixture evidence exposes a contradiction requiring production behavior changes, stop and reopen a design gate rather than silently changing T-602 contracts inside T-603.

## Verification gate

At implementation completion, minimum local verification is:

```text
cd packages/browser-runtime
npm ci
npm run typecheck
npm test

cd ../../examples/htmx-prep-list
npm ci
npx playwright install chromium
npm test

cd ../..
python scripts/validate.py
```

CI must prove both:

```text
validate              7/7 green
htmx-fixture          1/1 green
```

The final review must also prove by diff that browser-runtime production source, Laravel production source, frozen specs, and non-fixture dependency manifests were not changed.

## Design-review handoff

This document defines the T-603 proof boundary. It is not evidence that the fixture exists or that the portability proof has passed.

Current state after committing this spec:

```text
T-603 design:          approved in chat
Written spec:          under user review
Implementation plan:  not written
Implementation:       not started
D-057:                 proposed
D-020:                 proposed
T-604:                 not started
```

After written-spec approval, the next step is to write the implementation plan. No fixture implementation or CI workflow should be added before that gate.