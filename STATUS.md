# Project Status

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `main`
- **Milestone:** `M6 — HTMX Portability Proof` — **IN_PROGRESS**
- **Last completed task:** `T-601 — Explicit HTMX binding descriptor`
- **T-601 state:** **DONE / EXTERNALLY REVIEWED / MERGED / MAIN REVALIDATED**
- **Pull request:** `#10` — **MERGED**
- **Final feature/review-closure head:** `e4cbf0b3831863f4e0a7c89a0700726246b6f4b9`
- **Merge commit:** `96581ff9d12dba5487b4831c3bc146081945decb`
- **Post-merge main validation:** `34657967629` — **7/7 green**
- **Decision:** `D-053` — **ACCEPTED for descriptor semantics only**
- **Portability decision:** `D-020` — **PROPOSED; remains gated on T-604**
- **Next task:** `T-602 — HTMX browser driver` — **NOT STARTED**
- **Feature branch:** preserved; not deleted automatically.

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

Accepted boundaries:

- exact `driver=htmx` and `lifecycle=page` at the consumer boundary;
- opaque bounded `sourceId` grammar;
- methods limited to exact uppercase `GET|POST|PUT|PATCH|DELETE`;
- bounded same-origin absolute-path-reference grammar;
- exact target-key set; unknown keys fail closed;
- producer mapping derived only from a finite closed top-level `ActionDefinition.inputSchema`;
- `additionalProperties:false` required;
- reference/composition/open/conditional top-level mapping forms fail closed;
- `dependentRequired` and legacy `dependencies` are explicitly rejected after external-review hardening;
- nested values remain under one top-level Action input name;
- consumer mapping lists are unique non-empty strings with required-subset enforcement;
- produced and parsed descriptors are frozen defensive snapshots;
- descriptor carries no trusted actor/tenant/record/selection/confirmation/idempotency/browser-session/authorization authority;
- no DOM lookup, HTMX execution, network dispatch, response/swap handling, cancellation behavior, fixture app, Laravel production coupling, HTMX dependency, or `spec/0.1/**` change entered T-601.

## External review closure

CodeRabbit run `77dbb63c-4223-4177-a56a-8cad74daddb8` reported **1 Major + 3 Minor**.

- **Major — conditional required-key schemas:** reproduced TDD-first and fixed. `dependentRequired` and legacy `dependencies` now fail closed.
- **Minor — design snapshot/current-state mismatch:** verified as intentional historical design snapshot; finding withdrawn.
- **Minor — review handoff too long:** fixed and confirmed.
- **Minor — milestone status token:** fixed and confirmed.

Review evidence:

```text
Review-hardening RED:           9b241e4ef58740e512d57fde16efd1caaf8db5bd / 34656195469
                               browser 174 total — 172 passed / exactly 2 failed
Review-hardening GREEN:         0fd29621c1ae3084649095f8a8595e741c504e50 / 34656249493 — 7/7 green
Final reviewed branch head:     9939715e3357e3f63d52ab26f9de549e3738bd09
Final review-closure head:      e4cbf0b3831863f4e0a7c89a0700726246b6f4b9
Final push CI before closure:   34656613121 — 7/7 green
Final PR CI before closure:     34656616252 — 7/7 green
Resolved review threads:        4 / 4
Unresolved review threads:      0
```

A second complete CodeRabbit sweep after hardening was service-rate-limited and is not counted as a second full review pass. Every finding from the completed review was individually rechecked and confirmed or withdrawn in its thread.

## Merge and main revalidation

PR #10 was merged with expected-head protection pinned to exact feature head `e4cbf0b3831863f4e0a7c89a0700726246b6f4b9`.

```text
Merge commit:                   96581ff9d12dba5487b4831c3bc146081945decb
Post-merge main CI:             34657967629 — 7/7 green
Browser:                        TypeScript typecheck + 174/174 Vitest
Focused HTMX descriptor:        71/71
PHP baseline:                    595 tests / 3164 assertions
Contract / PHP lint:             green
```

The merge commit itself has therefore been revalidated on `main`.

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
Review-hardening RED:            9b241e4ef58740e512d57fde16efd1caaf8db5bd / 34656195469
Review-hardening GREEN:          0fd29621c1ae3084649095f8a8595e741c504e50 / 34656249493 — 7/7 green
Final reviewed push CI:          34656613121 — 7/7 green
Final reviewed PR CI:            34656616252 — 7/7 green
Merge commit:                    96581ff9d12dba5487b4831c3bc146081945decb
Post-merge main CI:              34657967629 — 7/7 green
```

## Current boundary

**T-601 is closed. M6 remains open.** `T-602 — HTMX browser driver` is **NOT STARTED** and must not begin automatically; its own scope/design gate is the next boundary when explicitly advanced.
