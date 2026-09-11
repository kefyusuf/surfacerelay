# External Review / Merge Record — T-601 HTMX Binding Descriptor

## Final status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Scope:** `T-601 — Explicit HTMX binding descriptor`
- **Feature branch:** `feat/htmx-binding-descriptor`
- **Pull request:** `#10` — **MERGED**
- **Original base / merge-base:** `main@5b22eef928d2fb1f8fac8ab13507e2f22661d3df`
- **Final feature/review-closure head:** `e4cbf0b3831863f4e0a7c89a0700726246b6f4b9`
- **Merge commit:** `96581ff9d12dba5487b4831c3bc146081945decb`
- **Post-merge main validation:** `34657967629` — **7/7 green**
- **Decision:** `D-053` — **ACCEPTED for descriptor semantics only**
- **Portability decision:** `D-020` — **PROPOSED; remains gated on T-604**
- **CodeRabbit review:** `77dbb63c-4223-4177-a56a-8cad74daddb8`
- **Initial review findings:** **1 Major + 3 Minor**
- **Review threads:** **4 / 4 resolved; 0 unresolved**
- **Browser:** TypeScript typecheck + **174/174 Vitest**; focused HTMX descriptor **71/71**
- **PHP baseline:** **595 tests / 3164 assertions** across PHP 8.3/8.4 × Illuminate 12/13 with MySQL 8.4
- **Contract / PHP lint:** green
- **Merge state:** **MERGED / MAIN REVALIDATED**
- **Next boundary:** `T-602 — HTMX browser driver` — **NOT STARTED**

## Final reviewed behavior

T-601 adds a pure browser-runtime descriptor for `RuntimeBinding(driver=htmx, lifecycle=page)` with the exact driver-owned target:

```text
sourceId
method
path
inputNames
requiredInputNames
```

The producer derives named caller-input mapping only from a finite closed top-level `ActionDefinition.inputSchema`. Arbitrary RuntimeBinding JSON is independently fail-closed parsed. Targets and mapping lists are immutable defensive snapshots.

The descriptor carries no trusted actor, tenant, role, record, selection, browser-session, confirmation, idempotency, or authorization authority.

T-601 deliberately does not contain DOM lookup, live HTMX attribute resolution, `htmx.ajax()`, network dispatch, response/swap handling, cancellation behavior, fixture application, Laravel production coupling, HTMX dependency, or `spec/0.1/**` changes.

## Review findings and closure

### Major — conditional required-key schemas — FIXED / CONFIRMED

CodeRabbit identified that JSON Schema `dependentRequired` and legacy `dependencies` can impose conditional required keys that are not represented by the unconditional `required` list.

The issue was reproduced before changing production code:

```text
RED:    9b241e4ef58740e512d57fde16efd1caaf8db5bd
CI:     34656195469
Result: 174 total — 172 passed / exactly 2 failed
Cases:  dependentRequired, dependencies
```

The builder now rejects both keywords fail-closed alongside the existing reference/composition/conditional top-level schema forms.

```text
GREEN:   0fd29621c1ae3084649095f8a8595e741c504e50
CI:      34656249493 — 7/7 green
Browser: TypeScript typecheck + 174/174 Vitest
Focused: 71/71 HTMX descriptor tests
```

CodeRabbit explicitly confirmed the fix and resolved the thread.

### Minor — design snapshot/current-state mismatch — VERIFIED / WITHDRAWN

The design file is intentionally retained as the committed pre-implementation design snapshot. Current delivery state is authoritative in `STATUS.md`, `TASKS.md`, `REVIEW_REQUEST.md`, and `docs/DECISION-REGISTER.md`; the implementation plan records the later repository-specific refinement that avoids creating a new `src/index.ts` barrel solely for T-601.

CodeRabbit verified this distinction, withdrew the finding, and resolved the thread.

### Minor — review handoff too long — FIXED / CONFIRMED

The review handoff was reduced to the external-review/merge essentials. Detailed checkpoint history remains in `STATUS.md`, `TASKS.md`, Git history, and the design/plan documents. CodeRabbit confirmed and resolved the thread.

### Minor — milestone status token — FIXED / CONFIRMED

The M6 task-board heading uses the declared `IN_PROGRESS` status token. CodeRabbit confirmed and resolved the thread.

A second complete CodeRabbit sweep after the hardening commits was service-rate-limited and is **not** counted as a second full review pass. The four findings from the completed review were individually rechecked in their threads; CodeRabbit explicitly confirmed or withdrew each one and all four threads are resolved.

## Verification evidence

```text
Base closure:                    5b22eef928d2fb1f8fac8ab13507e2f22661d3df / 34621807162 — 7/7 green
Design checkpoint:               cca98af90323819789a56d28a2174b9b7ea25059 / 34625130732 — 7/7 green
Implementation plan:             1e47c6bb4121bcc1fc43e69c73c213d17092b5c1 / 34629136719 — 7/7 green
Initial implementation/scope:    b06204e2c7acf6be01df3fec5085e55ddd650329 / 34654979136 — 7/7 green
Review-prep head:                c4e9b841d98464cc2fdb0b3fb259e9cebba08c76 / 34655391095 — 7/7 green
Initial PR #10 CI:               34655515805 — 7/7 green
Review-hardening RED:            9b241e4ef58740e512d57fde16efd1caaf8db5bd / 34656195469
Review-hardening GREEN:          0fd29621c1ae3084649095f8a8595e741c504e50 / 34656249493 — 7/7 green
Final reviewed branch head:      9939715e3357e3f63d52ab26f9de549e3738bd09
Final review-closure head:       e4cbf0b3831863f4e0a7c89a0700726246b6f4b9
Final reviewed push CI:          34656613121 — 7/7 green
Final reviewed PR CI:            34656616252 — 7/7 green
Merge commit:                    96581ff9d12dba5487b4831c3bc146081945decb
Post-merge main CI:              34657967629 — 7/7 green
Browser:                         TypeScript typecheck + 174/174 Vitest
Focused HTMX descriptor:         71/71
PHP:                             595 tests / 3164 assertions
Contract / PHP lint:             green
Unresolved review threads:       0
```

## Final change surface

Implementation/test surface:

```text
packages/browser-runtime/src/htmx-binding-descriptor.ts
packages/browser-runtime/tests/htmx-binding-descriptor.test.ts
packages/browser-runtime/tests/htmx-binding-descriptor.typecheck.ts
```

Design/tracking surface:

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

## Merge closure

PR #10 was merged using a normal merge commit with expected-head protection pinned to exact feature head `e4cbf0b3831863f4e0a7c89a0700726246b6f4b9`. The resulting merge commit is `96581ff9d12dba5487b4831c3bc146081945decb`.

Post-merge `main` validation `34657967629` passed all seven jobs, so the merge commit itself is revalidated.

The feature branch is preserved; it was not deleted automatically.

**T-601 is DONE / REVIEWED / MERGED / MAIN REVALIDATED. M6 remains IN_PROGRESS. T-602 has not started.**
