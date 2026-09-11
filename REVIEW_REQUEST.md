# External Review Result / Merge Handoff — T-601 HTMX Binding Descriptor

## Review state

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Scope:** `T-601 — Explicit HTMX binding descriptor`
- **Feature branch:** `feat/htmx-binding-descriptor`
- **Pull request:** `#10` — **OPEN / REVIEW COMPLETE**
- **Base / merge-base:** `main@5b22eef928d2fb1f8fac8ab13507e2f22661d3df`
- **Pre-review head:** `c4e9b841d98464cc2fdb0b3fb259e9cebba08c76`
- **Review-hardening code head:** `0fd29621c1ae3084649095f8a8595e741c504e50`
- **Final reviewed branch head before closure tracking:** `9939715e3357e3f63d52ab26f9de549e3738bd09`
- **CodeRabbit run:** `77dbb63c-4223-4177-a56a-8cad74daddb8`
- **Initial findings:** **1 Major + 3 Minor**
- **Resolved threads:** **4 / 4**
- **Unresolved threads:** **0**
- **Decision:** `D-053` — **ACCEPTED for descriptor semantics only**
- **Portability decision:** `D-020` — **PROPOSED; gated on T-604**
- **T-602:** **NOT STARTED**
- **Merge:** **NOT MERGED**

## Reviewed behavior

T-601 adds a pure browser-runtime descriptor for `RuntimeBinding(driver=htmx, lifecycle=page)` with exact driver-owned target keys:

```text
sourceId
method
path
inputNames
requiredInputNames
```

The producer derives named caller-input mapping only from a finite closed top-level `ActionDefinition.inputSchema`. Arbitrary RuntimeBinding JSON is independently fail-closed parsed. Targets and mapping lists are immutable defensive snapshots.

There is no DOM lookup, live HTMX attribute resolution, `htmx.ajax()`, network dispatch, response/swap handling, cancellation behavior, fixture application, Laravel production coupling, HTMX dependency, or `spec/0.1/**` change in T-601.

## Review findings and rulings

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

This file was reduced to the external-review/merge essentials. Detailed checkpoint history remains in `STATUS.md`, `TASKS.md`, Git history, and the design/plan documents. CodeRabbit confirmed and resolved the thread.

### Minor — milestone status token — FIXED / CONFIRMED

The M6 task-board heading now uses the declared `IN_PROGRESS` status token. CodeRabbit confirmed and resolved the thread.

## Final verification

```text
Final reviewed branch head:  9939715e3357e3f63d52ab26f9de549e3738bd09
Final push CI:               34656613121 — 7/7 green
Final PR CI:                 34656616252 — 7/7 green
Browser:                     TypeScript typecheck + 174/174 Vitest
Focused HTMX descriptor:    71/71
PHP baseline:                595 tests / 3164 assertions
Contract / PHP lint:         green
Unresolved review threads:   0
```

A second complete CodeRabbit sweep after the hardening commits was rate-limited by the service and is **not** counted as a second full review pass. The four findings from the completed review were individually rechecked in their threads; CodeRabbit explicitly confirmed or withdrew each one and all four threads are resolved.

## Merge focus

Before merging, verify only these final conditions:

1. PR #10 still points to the expected final feature head.
2. PR remains mergeable against `main`.
3. Exact final head CI is 7/7 green.
4. Review threads remain 0 unresolved.
5. Merge uses expected-head protection so a moved branch cannot be merged accidentally.
6. After merge, `main` is revalidated before T-601/M6 tracking is closed.

## Deliberate limitations

T-601 does **not** resolve a source element in the DOM, revalidate live `hx-*` request state, execute HTMX, define response/swap semantics, define cancellation guarantees, or prove the second server fixture/shared Livewire-HTMX conformance path. Those remain T-602–T-604 responsibilities.

## Reference material

- Current delivery/review state: `STATUS.md`
- Task evidence board: `TASKS.md`
- Original pre-implementation design snapshot: `docs/superpowers/specs/2026-09-11-htmx-binding-descriptor-design.md`
- Implementation-plan refinement: `docs/superpowers/plans/2026-09-11-htmx-binding-descriptor.md`
- Accepted descriptor decision: `docs/DECISION-REGISTER.md` (`D-053`)

**External review is complete. PR #10 is ready for the merge gate. Do not begin T-602 until merge closure and main revalidation are complete.**
