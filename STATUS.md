# Project Status

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/htmx-binding-descriptor`
- **Base:** `main@5b22eef928d2fb1f8fac8ab13507e2f22661d3df`
- **Milestone:** `M6 — HTMX Portability Proof` — **IN_PROGRESS**
- **Current task:** `T-601 — Explicit HTMX binding descriptor`
- **T-601 state:** **DONE / EXTERNALLY REVIEWED / READY FOR MERGE**
- **Pull request:** `#10` — **OPEN / REVIEW COMPLETE**
- **Pre-review head:** `c4e9b841d98464cc2fdb0b3fb259e9cebba08c76`
- **Review-hardening code head:** `0fd29621c1ae3084649095f8a8595e741c504e50`
- **Final reviewed branch head before closure tracking:** `9939715e3357e3f63d52ab26f9de549e3738bd09`
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

The exact driver-owned target contains only:

```text
sourceId
method
path
inputNames
requiredInputNames
```

Enforced boundaries:

- exact `driver=htmx` and `lifecycle=page` at the consumer boundary;
- opaque bounded `sourceId` grammar;
- methods limited to exact uppercase `GET|POST|PUT|PATCH|DELETE`;
- bounded same-origin absolute-path-reference grammar;
- exact target-key set; unknown keys fail closed;
- producer mapping derived only from an exact finite closed top-level ActionDefinition object schema;
- `additionalProperties:false` required;
- reference/composition/open/conditional top-level mapping forms fail closed;
- `dependentRequired` and legacy `dependencies` are explicitly rejected after external-review hardening;
- nested values remain under one top-level Action input name;
- consumer mapping lists are unique, non-empty string lists with required-subset enforcement;
- produced and parsed descriptors are frozen defensive snapshots;
- descriptor carries no trusted actor/tenant/record/selection/confirmation/idempotency/browser-session/authorization authority;
- T-601 contains no DOM lookup, HTMX execution, network dispatch, cancellation behavior, fixture app, Laravel production coupling, HTMX dependency, or `spec/0.1/**` change.

## External review result

PR #10 was reviewed by CodeRabbit run `77dbb63c-4223-4177-a56a-8cad74daddb8`.

Initial result:

```text
1 Major + 3 Minor
```

### Major — conditional required-key schemas — FIXED / CONFIRMED

The review identified that JSON Schema `dependentRequired` and legacy `dependencies` can impose conditional required keys while the descriptor derives `requiredInputNames` from the unconditional `required` array only.

RED evidence:

```text
commit: 9b241e4ef58740e512d57fde16efd1caaf8db5bd
CI:     34656195469
result: browser 174 total — 172 passed / exactly 2 failed
cases:  dependentRequired, dependencies
```

GREEN evidence:

```text
commit: 0fd29621c1ae3084649095f8a8595e741c504e50
CI:     34656249493 — 7/7 green
browser: TypeScript typecheck + 174/174 Vitest
focused HTMX descriptor: 71/71
contract / lint / PHP matrix: green
```

CodeRabbit explicitly confirmed the fix and resolved the thread.

### Minor — design snapshot/current-state mismatch — VERIFIED / WITHDRAWN

The original design file is intentionally retained as the committed pre-implementation design snapshot. Current state is authoritative in `STATUS.md`, `REVIEW_REQUEST.md`, `TASKS.md`, and `docs/DECISION-REGISTER.md`; the implementation plan records the later repository-specific refinement that avoids introducing a new `src/index.ts` barrel solely for T-601. CodeRabbit verified that distinction, withdrew the finding, and resolved the thread.

### Minor — review handoff too long — FIXED / CONFIRMED

`REVIEW_REQUEST.md` was reduced to a concise external-review/merge handoff. CodeRabbit confirmed and resolved the thread.

### Minor — milestone status token — FIXED / CONFIRMED

The M6 heading now uses the declared `IN_PROGRESS` token. CodeRabbit confirmed and resolved the thread.

### Review completion evidence

```text
Initial review findings:       1 Major + 3 Minor
Resolved review threads:       4 / 4
Unresolved review threads:     0
Final reviewed branch head:    9939715e3357e3f63d52ab26f9de549e3738bd09
Final push CI:                 34656613121 — 7/7 green
Final PR CI:                   34656616252 — 7/7 green
Browser:                       TypeScript typecheck + 174/174 Vitest
Focused HTMX descriptor:      71/71
PHP baseline:                  595 tests / 3164 assertions
Contract / PHP lint:           green
```

A second full CodeRabbit sweep after the hardening changes was rate-limited by the service. This does not count as a second complete review pass. The four original findings were nevertheless rechecked individually in their review threads; CodeRabbit explicitly confirmed/withdrew each and all four threads are resolved.

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
Initial PR #10 CI:               34655515805 — 7/7 green
Review-hardening RED:            9b241e4ef58740e512d57fde16efd1caaf8db5bd / 34656195469 — exactly 2 expected browser failures
Review-hardening GREEN:          0fd29621c1ae3084649095f8a8595e741c504e50 / 34656249493 — 7/7 green
Final reviewed push CI:          34656613121 — 7/7 green
Final reviewed PR CI:            34656616252 — 7/7 green
```

## Deliberate limitation / next boundary

T-601 remains descriptor-only. It does not resolve DOM sources, verify live HTMX attributes, execute `htmx.ajax()`, define response/swap or cancellation semantics, or prove a second server fixture/shared-conformance implementation.

**Current gate:** PR #10 is externally reviewed and ready for the merge gate. Do not start T-602 before merge closure and explicit advancement of that next task.
