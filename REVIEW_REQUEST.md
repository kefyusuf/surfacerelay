# T-701 Review and Decision Record — Executable Conformance Runner

## Status

- **Pull request:** #14 — `feat(conformance): add executable browser conformance runner`
- **Branch:** `feat/executable-conformance-runner`
- **Approved design diff base:** `05b020c94279c295068895a8163a892549327d3f`
- **Verified implementation head:** `2c15515a77d2dd1d79ea970a2811ca0c40191ceb`
- **Review-prep head:** `041dc9126f63225de0338bdb557446906da78c62`
- **External-review correctness-fix head:** `89a968785a09032cbf3b71f7f60c3f14b76c10ab`
- **Final reviewed PR head:** `b7d2c4aee175da80f0c97d103f915f751c6b0fc7`
- **Review-closure tracking head:** `3777fce830ae2d4a1bcc64925af24563f611ea3e`
- **Decision-promotion head:** `1efe11714e9d3abcd0e8a12fcb90912b23522ad4`
- **Decision-promotion PR validate:** #802 / `35058068033` — **7/7 SUCCESS**
- **CodeRabbit:** **4/4 actionable threads individually rechecked/resolved / 0 unresolved**
- **Task state:** implementation verified; external review complete; decisions accepted; merge pending
- **Decisions:** D-059, D-060, D-061 **ACCEPTED**; D-026 remains **PROPOSED**

Detailed architecture and implementation history remain in `STATUS.md`, `TASKS.md`, the approved design, and the implementation plan.

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
Review-closure push validate:   #799 / 35050355079 — 7/7 SUCCESS
Decision-promotion PR validate: #802 / 35058068033 — 7/7 SUCCESS
TypeScript typecheck:           PASS
Vitest:                         20 files / 328/328 PASS
CPython:                        3.12.14
Conformance unit tests:         47/47 PASS
Harness build:                  PASS
Canonical runtime matrix:       7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR
Contract validation:            PASS
```

PR #14 remains open, mergeable, and unmerged at the decision-promotion checkpoint.

## Decision promotion outcome

The reviewed implementation now carries accepted architecture decisions for the behavior it actually implements:

- **D-059 — ACCEPTED:** profile + capability applicability; runner-owned canonical selection/applicability/PASS-FAIL verdicts; raw harness observations only; harnesses cannot self-certify or self-report N/A for mandatory scenarios.
- **D-060 — ACCEPTED:** repo-local one-scenario/one-subprocess JSON process protocol with fixed 10-second timeout; argv + `shell=False`; repo-root command execution; Python stdlib orchestrator imports no runtime implementation.
- **D-061 — ACCEPTED:** canonical scenario semantics remain in the registry; target wiring remains outside the spec; positive control is mandatory; T-701 v1 is the closed reviewed four-scenario/two-browser-target claim.
- **D-026 — PROPOSED:** provisional binding failure-code names remain advisory and are not promoted by T-701.

The accepted vocabulary remains bounded to the reviewed repo-local T-701 v1 runner. It is not a standalone specification, public certification API, or global error-code enum.

## Merge boundary

Decision promotion does **not** merge PR #14 and does not start another task.

The next explicit gate is T-701 merge. Post-merge main revalidation is a separate later gate. T-702/T-703/T-704 must not start automatically.