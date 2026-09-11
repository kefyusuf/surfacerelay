# Project Status

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/htmx-binding-descriptor`
- **Base:** `main@5b22eef928d2fb1f8fac8ab13507e2f22661d3df`
- **Milestone:** `M6 — HTMX Portability Proof` — **IN_PROGRESS**
- **Current task:** `T-601 — Explicit HTMX binding descriptor`
- **T-601 state:** **IMPLEMENTED / EXTERNAL REVIEW HARDENING**
- **Pull request:** `#10` — **OPEN**
- **Pre-review head:** `c4e9b841d98464cc2fdb0b3fb259e9cebba08c76`
- **Review-hardening code head:** `0fd29621c1ae3084649095f8a8595e741c504e50`
- **Decision:** `D-053` — **ACCEPTED for descriptor semantics only**
- **Portability decision:** `D-020` — **PROPOSED; remains gated on T-604**
- **Next task:** `T-602 — HTMX browser driver` — **NOT STARTED**
- **Merge state:** **NOT MERGED**

## Delivered T-601 behavior

Browser-runtime now has a pure HTMX RuntimeBinding descriptor with two public seams:

```text
createHtmxBindingTarget(ActionDefinition, { sourceId, method, path })
parseHtmxBindingTarget(RuntimeBinding)
```

The exact HTMX target contains only:

```text
sourceId
method
path
inputNames
requiredInputNames
```

Key enforced boundaries:

- exact `driver=htmx` and `lifecycle=page` at the consumer boundary;
- opaque bounded `sourceId` grammar;
- methods limited to exact uppercase `GET|POST|PUT|PATCH|DELETE`;
- bounded same-origin absolute-path reference grammar;
- exact target-key set; unknown keys fail closed;
- producer mapping derived only from an exact finite closed top-level ActionDefinition object schema;
- `additionalProperties:false` required;
- reference/composition/open/conditional top-level mapping forms fail closed;
- `dependentRequired` and legacy `dependencies` are explicitly rejected after external-review hardening;
- nested values remain under one top-level Action input name;
- consumer mapping lists are unique, non-empty string lists with required subset enforcement;
- produced and parsed descriptors are frozen defensive snapshots;
- descriptor carries no trusted actor/tenant/record/selection/confirmation/idempotency/browser-session/authorization authority.

## Review result so far

CodeRabbit run `77dbb63c-4223-4177-a56a-8cad74daddb8` reported **1 Major + 3 Minor**.

### Major — conditional required-key schemas — reproduced and fixed TDD-first

The review identified that `dependentRequired` and legacy `dependencies` can impose conditional required keys while the descriptor maps only unconditional `required` names.

RED evidence:

```text
commit: 9b241e4ef58740e512d57fde16efd1caaf8db5bd
CI:     34656195469
browser: 174 total — 172 passed / exactly 2 failed
failures: dependentRequired, dependencies
```

GREEN evidence:

```text
commit: 0fd29621c1ae3084649095f8a8595e741c504e50
CI:     34656249493 — 7/7 green
browser: TypeScript typecheck + 174/174 Vitest
focused HTMX descriptor: 71/71
contract / lint / PHP matrix: green
```

### Minor review items

- milestone token is being normalized to declared `IN_PROGRESS`;
- `REVIEW_REQUEST.md` is reduced to a concise handoff;
- the original design document is treated as the committed pre-implementation design snapshot; the implementation plan is the later repository-specific refinement that explicitly removed the unused `src/index.ts` barrel assumption.

## Change surface

Implementation/test files:

```text
packages/browser-runtime/src/htmx-binding-descriptor.ts
packages/browser-runtime/tests/htmx-binding-descriptor.test.ts
packages/browser-runtime/tests/htmx-binding-descriptor.typecheck.ts
```

Design/tracking files:

```text
docs/superpowers/specs/2026-09-11-htmx-binding-descriptor-design.md
docs/superpowers/plans/2026-09-11-htmx-binding-descriptor.md
docs/DECISION-REGISTER.md
TASKS.md
STATUS.md
REVIEW_REQUEST.md
```

Explicitly unchanged/out of scope:

```text
packages/laravel/src/**
spec/0.1/**
packages/browser-runtime/src/htmx-browser-driver.ts
examples/htmx/**
packages/browser-runtime/package.json
packages/browser-runtime/package-lock.json
```

## Verification history

```text
Base closure:                    5b22eef928d2fb1f8fac8ab13507e2f22661d3df / 34621807162 — 7/7 green
Design checkpoint:               cca98af90323819789a56d28a2174b9b7ea25059 / 34625130732 — 7/7 green
Implementation plan:             1e47c6bb4121bcc1fc43e69c73c213d17092b5c1 / 34629136719 — 7/7 green
Initial implementation/scope:    b06204e2c7acf6be01df3fec5085e55ddd650329 / 34654979136 — 7/7 green
Review-prep head:                c4e9b841d98464cc2fdb0b3fb259e9cebba08c76 / 34655391095 — 7/7 green
Initial PR CI:                   34655515805 — 7/7 green
Review-hardening RED:            9b241e4ef58740e512d57fde16efd1caaf8db5bd / 34656195469 — exactly 2 expected browser failures
Review-hardening GREEN:          0fd29621c1ae3084649095f8a8595e741c504e50 / 34656249493 — 7/7 green
Browser after hardening:         174/174; HTMX focused 71/71; typecheck green
PHP baseline:                    595 tests / 3164 assertions
```

## Deliberate limitation / next boundary

T-601 remains descriptor-only. It does not resolve DOM sources, verify live HTMX attributes, execute `htmx.ajax()`, define response/swap or cancellation semantics, or prove a second server fixture/shared conformance implementation.

The current gate is **external review of PR #10**. Do not merge or start T-602 until that gate is explicitly advanced.
