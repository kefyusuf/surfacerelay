# Project Status

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/htmx-fixture`
- **Milestone:** `M6 — HTMX Portability Proof` — **IN_PROGRESS**
- **Last completed task:** `T-602 — HTMX browser driver`
- **Current task:** `T-603 — Non-Laravel HTMX fixture app`
- **T-603 state:** **IN_PROGRESS — DESIGN APPROVED / IMPLEMENTATION PLAN UNDER REVIEW**
- **T-603 implementation:** **NOT STARTED**
- **Written spec:** `docs/superpowers/specs/2026-09-12-htmx-fixture-design.md`
- **Implementation plan:** `docs/superpowers/plans/2026-09-12-htmx-fixture.md`
- **Proposed fixture decision:** `D-057`
- **Portability decision:** `D-020` — **PROPOSED; remains gated on T-604**
- **Branch base:** `main@83df122d84f6881a4a3541bd5c72e1108e5d1e48`
- **Next gate:** user review/approval of the committed T-603 implementation plan; only then begin fixture implementation.

## Baseline entering T-603

T-602 closure on `main`:

```text
T-602 final feature head:     ebad0f04a3b540a5a1536350038d0919ff600ebd
T-602 merge commit:           ee9af986f22cba45b05c59059c11e68ac46111fd
Post-merge main CI:           34701911188 — 7/7 green
Final closure main:           83df122d84f6881a4a3541bd5c72e1108e5d1e48
Final closure CI:             34702679885 — 7/7 green
Browser baseline:             TypeScript typecheck + 297/297 Vitest across 17 files
CodeRabbit:                   2 actionable findings / 2 resolved / 0 unresolved
Contract / PHP lint / matrix: green
```

Accepted T-602 decisions:

```text
D-054 — exact-source host HTMX 2.x execution boundary
D-055 — busy-source failure + pre-ajax-only strong cancellation
D-056 — deterministic Action-input integrity and fail-closed source policy
```

`D-020` intentionally remained proposed because T-602 did not use a real non-Laravel browser/server fixture and did not run shared cross-driver conformance.

## T-603 design outcome

The design gate selects a deliberately small real fixture rather than a second framework integration.

### Real fixture architecture

```text
existing prep_list.add_item@1 ActionDefinition
        ↓
plain Node 22 HTTP application
        ↓
server-issued page-scoped HTMX RuntimeBinding
        ↓
real htmx.org 2.0.10 in Chromium
        ↓
normal human click OR merged HtmxBrowserDriver.execute()
        ↓
POST /items
        ↓
same in-memory server mutation
        ↓
same HTML fragment response
        ↓
same source-defined hx-target / hx-swap
```

Selected boundaries:

- fixture lives under `examples/htmx-prep-list/` as an independent private npm package;
- server uses only Node's built-in `node:http`, binds to `127.0.0.1:4173`, and fails if the fixed port is unavailable;
- real browser runtime is pinned `htmx.org@2.0.10`;
- real browser proof uses Playwright Test / Chromium;
- SurfaceRelay browser-runtime source is compiled from `packages/browser-runtime/src/**/*.ts` into ignored fixture-local `.tmp/runtime/` ESM; implementation is not copied or rewritten;
- `examples/prep-list/action.add-item.json` is reused as the exact `prep_list.add_item@1` ActionDefinition;
- every full-page render creates a fresh `sourceId` and `bindingId` and derives the HTMX target through production `createHtmxBindingTarget()`;
- the rendered human-facing button is the exact SurfaceRelay source and carries `hx-post=/items`, `hx-target=#items`, and `hx-swap=beforeend`;
- `POST /items` is the only business mutation route;
- `POST /__test/reset` is the only test-only server state endpoint and exists solely for test isolation;
- the browser test bridge may invoke production `HtmxBrowserDriver.execute()` and perform DOM-only stale-source replacement, but may not call `fetch`, XHR, `htmx.ajax()` directly, or mutate server/business state;
- normal human and SurfaceRelay execution paths must produce real HTMX requests to the same `/items` route and consume the same HTML response through the same HTMX target/swap semantics;
- a stale same-name form value is intentionally overridden by SurfaceRelay Action input while unrelated hidden host state (`uiContext=prep-list`) remains present in the actual agent request;
- full-page reload proves that both human and agent interactions mutate real server state rather than only the DOM;
- full-page reload also proves page-scoped source identity renewal;
- replacing the exact source with an equivalent new source must make the old binding fail `binding_stale`, issue zero `/items` requests, and leave server state unchanged;
- item rendering and embedded RuntimeBinding JSON use explicit HTML/script-context escaping;
- static serving is explicit and traversal-safe rather than a generic project-root file server;
- fixture dependencies remain isolated to `examples/htmx-prep-list/package*.json`; browser-runtime and Laravel dependency files remain unchanged;
- generated runtime/test artifacts stay in ignored `.tmp/` directories.

### Required real-browser proof matrix

At minimum six Playwright tests:

```text
1. runtime/bootstrap identity
2. normal human HTMX request/swap/reload persistence
3. agent request + explicit Action override + host-state preservation
4. normalized human/agent request-path convergence
5. page lifecycle source/binding renewal
6. real-DOM stale replacement with no retarget/no mutation
```

The implementation plan additionally requires fail-closed HTTP/static tests and an HTML-escaping regression before closure.

Network assertions inspect actual Playwright-observed requests, including method, path, `HX-Request`, content type, and form body. DOM appearance alone is insufficient proof.

### Implementation plan decomposition

```text
Task 1 — fixture package, runtime emit, real HTMX/bootstrap identity
Task 2 — single /items business mutation + normal human HTMX path
Task 3 — real SurfaceRelay driver path + Action override/host-state proof
Task 4 — human/agent network convergence + page lifecycle renewal
Task 5 — stale-source no-retargeting + HTTP/static/rendering hardening
Task 6 — path-filtered fixture CI + README + verification + review prep
```

Each implementation task uses a RED → GREEN → regression → commit checkpoint. If the real fixture exposes a contradiction that requires changing accepted T-602 production behavior, implementation must stop and reopen a design gate rather than silently modifying browser-runtime semantics under T-603.

### CI boundary

Real-browser verification is a separate path-filtered workflow:

```text
.github/workflows/htmx-fixture.yml
```

It runs when the fixture, existing ActionDefinition, browser-runtime source/config, or fixture workflow changes.

The evidence model is intentionally split:

```text
verified fixture implementation head:
  validate workflow      -> 7/7 green
  htmx-fixture workflow  -> 1/1 green

later tracking-only review-prep head:
  validate workflow      -> fresh 7/7 green
  htmx-fixture           -> not expected to rerun
  diff proof             -> no fixture/runtime/workflow input changed since the green fixture head
```

This preserves path-filter cost control without pretending a docs-only commit executed Chromium.

## Proposed decision

`D-057` — **PROPOSED**:

> T-603 proves the merged HTMX adapter against a real non-Laravel Node application, real HTMX 2.x browser runtime, real Chromium network/DOM behavior, and the existing `prep_list.add_item@1` ActionDefinition. Human and SurfaceRelay paths converge on the same rendered HTMX source semantics, same `/items` business route, same server state transition, and same HTMX response-swap behavior. Test-only helpers may isolate state or delegate to the production driver but may not create a second business mutation path. T-603 alone does not establish T-604 shared conformance or accept D-020.

D-057 must not be promoted by design or plan approval. The implementation plan requires exact fixture-relevant implementation-head real-browser CI success before D-057 can become `ACCEPTED`.

## Expected implementation surface after plan approval

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

Expected unchanged production boundaries:

```text
packages/browser-runtime/src/**
packages/browser-runtime/package.json
packages/browser-runtime/package-lock.json
packages/laravel/**
spec/0.1/**
examples/prep-list/action.add-item.json
.github/workflows/validate.yml
```

## Plan verification expectations

Before T-603 can reach external review, the plan requires:

```text
fresh fixture npm ci + runtime build + Playwright run
browser-runtime npm ci + typecheck + full regression suite
python scripts/validate.py
fixture-relevant implementation head: validate 7/7 + htmx-fixture 1/1
tracking-only review-prep head: fresh validate 7/7
compare fixture implementation head -> review-prep head: tracking docs only
fixture job log: actual Playwright count confirmed
review-prep browser job log: typecheck + browser regression count confirmed
```

Only after the fixture-relevant implementation-head real-browser CI succeeds may D-057 be promoted to accepted. D-020 remains proposed and T-604 remains not started.

## Current boundary

**T-603 implementation is not started.** The written design and implementation plan are committed on `feat/htmx-fixture`; the implementation plan is now the active review gate. The next allowed action is explicit user approval of `docs/superpowers/plans/2026-09-12-htmx-fixture.md`. After approval, execute the plan task-by-task. Do not create fixture code, package manifests, Playwright tests, or CI workflow before that approval, and do not begin T-604 automatically.