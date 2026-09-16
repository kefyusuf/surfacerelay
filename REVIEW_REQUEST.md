# External Review Record — T-701 Executable Conformance Runner

## Status

- **Pull request:** #14 — `feat(conformance): add executable browser conformance runner`
- **Branch:** `feat/executable-conformance-runner`
- **Approved design diff base:** `05b020c94279c295068895a8163a892549327d3f`
- **Verified implementation head:** `2c15515a77d2dd1d79ea970a2811ca0c40191ceb`
- **Review-prep head:** `041dc9126f63225de0338bdb557446906da78c62`
- **External-review correctness-fix head:** `89a968785a09032cbf3b71f7f60c3f14b76c10ab`
- **Final reviewed PR head:** `b7d2c4aee175da80f0c97d103f915f751c6b0fc7`
- **Final reviewed PR validate:** #798 / `35049891609` — **SUCCESS, 7 jobs total**
- **CodeRabbit:** **4/4 actionable threads individually rechecked/resolved / 0 unresolved**
- **Task state:** implementation verified; external review complete; decision gate pending; not merged
- **Decisions:** D-026, D-059, D-060, D-061 remain **PROPOSED**

Detailed architecture and history remain in `STATUS.md`, `TASKS.md`, the approved design, and the implementation plan.

## Reviewed boundary

T-701 v1 remains limited to:

- profile `runtime-binding/driver`;
- targets `browser/livewire` and `browser/htmx`;
- exactly four executable runtime scenarios: exact execution, expiry, component stale, and no-silent-retarget;
- runner-owned scenario selection, capability applicability and PASS/FAIL verdicts;
- raw harness observations only;
- one fresh subprocess per applicable target/scenario pair;
- one JSON request on stdin, one protocol JSON response on stdout, stderr diagnostics only, fixed 10-second timeout;
- HTMX component-stale as runner-owned `NOT_APPLICABLE` before spawn;
- advisory `recommendedCode` / raw `errorCode`;
- structural/config validation separated from runtime execution;
- no semantic production changes under `packages/browser-runtime/src/**`.

Explicit non-goals remain public SDK/certification, standalone spec extraction, Laravel/PHP target coverage, Trust/Output/Projection conformance, real-browser orchestration in this runner, remote targets, retries, parallelism, watch mode, plugin discovery, and T-702/T-703/T-704 implementation.

## External review outcome

CodeRabbit opened four actionable threads:

1. **HTMX replacement identity** — not implemented. Recheck confirmed the original suggestion invalid because accepted D-053 exact source identity requires replacement targets to receive a new `sourceId`. Thread resolved by CodeRabbit.
2. **Concise review handoff** — shortened in `b7d2c4aee175da80f0c97d103f915f751c6b0fc7`; CodeRabbit confirmed and resolved.
3. **Closed v1 matrix completeness** — fixed in `89a968785a09032cbf3b71f7f60c3f14b76c10ab` with dedicated v1 scenario/target membership validation plus regression coverage; CodeRabbit confirmed and resolved.
4. **Repo-relative harness cwd** — fixed in `89a968785a09032cbf3b71f7f60c3f14b76c10ab` by executing child commands from repository `ROOT`, with a permanent external-cwd regression; CodeRabbit confirmed and resolved.

No second full CodeRabbit sweep is claimed. Closure evidence is the initial full review, thread-specific rechecks, and fresh exact-head CI.

## Verification

```text
External-review RED:            #794 / 35049387890 — expected failure on new review regressions
Correctness-fix validate:       #796 / 35049689721 — SUCCESS
Final reviewed PR validate:     #798 / 35049891609 — SUCCESS, 7 jobs total
TypeScript typecheck:           PASS
Vitest:                         20 files / 328/328 PASS
CPython:                        3.12.14
Conformance unit tests:         47/47 PASS
Harness build:                  PASS
Canonical runtime matrix:       7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR
Contract validation:            PASS
```

PR #14 remains open, mergeable, and unmerged at external-review closure.

## Decision / merge boundary

External review supplies evidence for a later decision gate; it does not promote or merge anything.

- D-026 — PROPOSED
- D-059 — PROPOSED
- D-060 — PROPOSED
- D-061 — PROPOSED

The next explicit gate is T-701 decision promotion. Merge is a separate later gate, and T-702/T-703/T-704 must not start automatically.
