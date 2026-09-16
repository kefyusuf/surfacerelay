# External Review Request — T-701 Executable Conformance Runner

## Status

- **Pull request:** #14 — `feat(conformance): add executable browser conformance runner`
- **Branch:** `feat/executable-conformance-runner`
- **Approved design diff base:** `05b020c94279c295068895a8163a892549327d3f`
- **Verified implementation head:** `2c15515a77d2dd1d79ea970a2811ca0c40191ceb`
- **Review-prep head:** `041dc9126f63225de0338bdb557446906da78c62`
- **External-review code-fix head:** `89a968785a09032cbf3b71f7f60c3f14b76c10ab`
- **Review-fix validate:** #795 / `35049685560` — 7/7 jobs SUCCESS
- **Current task state:** implementation verified; external-review fixes applied; not merged
- **Decisions:** D-026, D-059, D-060, D-061 remain **PROPOSED**

Detailed architecture, implementation sequence, and historical evidence live in:

- `docs/superpowers/specs/2026-09-14-executable-conformance-runner-design.md`
- `docs/superpowers/plans/2026-09-15-executable-conformance-runner.md`
- `STATUS.md`
- `TASKS.md`

## Review scope

T-701 implements a repo-local executable conformance runner for exactly one v1 profile, `runtime-binding/driver`, against `browser/livewire` and `browser/htmx`.

The review boundary is:

- Python runner owns canonical scenario selection, applicability, and PASS/FAIL verdicts;
- harnesses emit bounded raw observations only;
- one applicable target/scenario pair runs in one fresh subprocess;
- process protocol is one JSON request on stdin, one protocol JSON response on stdout, stderr diagnostics only, fixed 10-second timeout;
- canonical semantics remain in `spec/0.1/fixtures/conformance-scenarios.json`;
- repo-local target wiring remains under `conformance/targets/`;
- v1 completeness requires exactly the approved four executable runtime scenarios and two target IDs;
- HTMX component-stale is runner-owned `NOT_APPLICABLE` before spawn;
- `recommendedCode` / raw `errorCode` remain advisory;
- no semantic production change exists under `packages/browser-runtime/src/**`.

Explicit non-goals remain public SDK/certification, standalone spec extraction, Laravel/PHP target coverage, Trust/Output/Projection conformance, real-browser orchestration in this runner, remote targets, retries, parallelism, watch mode, plugin discovery, or T-702/T-703/T-704 implementation.

## Verification

Bound to review-fix code head `89a968785a09032cbf3b71f7f60c3f14b76c10ab`:

```text
validate push run:             #795 / 35049685560 — 7/7 SUCCESS
TypeScript typecheck:          PASS
Vitest:                        20 files / 328/328 PASS
CPython:                       3.12.14
Conformance unit tests:        47/47 PASS
Harness build:                 PASS
Canonical runtime matrix:      7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR
Contract validation:           PASS
```

External-review RED evidence for the accepted correctness findings is run #794 / `35049387890`: after the unchanged 328/328 browser suite, the Python step failed exactly the new v1-completeness and non-root-cwd regressions. Both pass on #795.

## CodeRabbit review outcome

CodeRabbit posted four actionable threads on PR #14:

1. **HTMX replacement identity** — not changed. Re-review confirmed the original finding invalid: D-053 requires replacement targets to receive a new opaque `sourceId`; reusing `src-1` would violate the accepted identity contract and would require a production-driver redesign outside T-701.
2. **Concise review handoff** — addressed by this shortened `REVIEW_REQUEST.md`.
3. **Closed v1 matrix completeness** — fixed by `validate_v1_conformance_config()` plus regression coverage; confirmed/resolved by CodeRabbit.
4. **Repo-relative harness commands depend on caller cwd** — fixed with `subprocess.run(..., cwd=ROOT)` plus regression coverage; confirmed/resolved by CodeRabbit.

The generic CodeRabbit docstring-coverage metric is not a repository requirement and is not treated as an actionable T-701 correctness finding.

## Remaining review questions

- Does the runner/harness authority split match the approved D-059 proposal without enabling self-certification?
- Is the strict process/error boundary sufficient to distinguish conformance FAIL from infrastructure ERROR?
- Is capability-gated HTMX N/A determined early enough and without adapter-controlled skipping?
- Does the mandatory positive control prevent an always-reject implementation from passing the profile?
- Is `errorCode` correctly advisory while D-026 remains PROPOSED?
- Is no-retarget evidence consistent with accepted exact binding/source identity semantics?
- Has scope remained contained to the approved T-701 v1 boundary?

## Decision / merge boundary

This handoff does **not** promote architecture decisions and does **not** authorize merge.

- D-026 — PROPOSED
- D-059 — PROPOSED
- D-060 — PROPOSED
- D-061 — PROPOSED

After external-review closure, decision promotion and merge are separate explicit gates. T-702/T-703/T-704 must not start automatically.
