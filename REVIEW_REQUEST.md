# T-701 External Review / Merge Closure Record — Executable Conformance Runner

## Final status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `main`
- **Task:** `T-701 — Executable conformance runner`
- **Pull request:** #14 — `feat(conformance): add executable browser conformance runner` — **MERGED**
- **Verified implementation head:** `2c15515a77d2dd1d79ea970a2811ca0c40191ceb`
- **External-review correctness-fix head:** `89a968785a09032cbf3b71f7f60c3f14b76c10ab`
- **Final reviewed PR head:** `b7d2c4aee175da80f0c97d103f915f751c6b0fc7`
- **Review-closure tracking head:** `3777fce830ae2d4a1bcc64925af24563f611ea3e`
- **Decision-promotion head:** `1efe11714e9d3abcd0e8a12fcb90912b23522ad4`
- **Final feature/tracking head:** `5da153c3c0a0f6c38d9b5f01b26899efa24ea949`
- **Merge commit:** `50c8c482165a115b8b3b8cb740f123ecf4203041`
- **Post-merge main validate:** #809 / `35080877519` — **7/7 SUCCESS**
- **Post-merge browser:** **20 files / 328/328 Vitest + TypeScript typecheck**
- **Post-merge Python conformance:** **47/47 PASS** on CPython 3.12.14
- **Post-merge canonical matrix:** **7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR**
- **CodeRabbit:** **4/4 actionable threads individually rechecked/resolved / 0 unresolved**
- **Decisions:** D-059, D-060, D-061 **ACCEPTED**; D-026 remains **PROPOSED**
- **Task state:** **DONE / REVIEWED / MERGED / MAIN REVALIDATED**

Detailed architecture and implementation history remain in `STATUS.md`, `TASKS.md`, the approved design, and the implementation plan.

## Closed v1 boundary

T-701 v1 remains deliberately bounded to:

- profile `runtime-binding/driver`;
- targets `browser/livewire` and `browser/htmx`;
- exactly four executable runtime scenarios: exact execution, expiry, component stale, and no-silent-retarget;
- runner-owned canonical scenario selection, capability applicability and PASS/FAIL verdicts;
- raw harness observations only;
- one fresh subprocess per applicable target/scenario pair;
- one JSON request on stdin, exactly one protocol JSON response on stdout, stderr diagnostics only, fixed 10-second timeout;
- repo-root argv execution with `shell=False`;
- HTMX component-stale as runner-owned `NOT_APPLICABLE` before spawn;
- mandatory positive control for each claimed profile;
- advisory `recommendedCode` / raw `errorCode`;
- canonical semantics in `spec/0.1/fixtures/conformance-scenarios.json` and target wiring outside the spec.

It does not create a public conformance SDK/certification API, standalone specification extraction, Laravel/PHP target coverage, Trust/Output/Projection conformance, real-browser orchestration in the runner, remote targets, retries, parallelism, watch mode, plugin discovery, or a generic assertion DSL.

## External review outcome

CodeRabbit opened four actionable threads:

1. **HTMX replacement identity** — not implemented; reviewer recheck confirmed the original suggestion invalid because accepted D-053 exact source identity requires replacement targets to receive a new `sourceId`.
2. **Concise review handoff** — addressed in `b7d2c4aee175da80f0c97d103f915f751c6b0fc7`.
3. **Closed v1 matrix completeness** — addressed in `89a968785a09032cbf3b71f7f60c3f14b76c10ab` with dedicated v1 membership validation plus regression coverage.
4. **Repo-relative harness cwd** — addressed in `89a968785a09032cbf3b71f7f60c3f14b76c10ab` with `cwd=ROOT` plus an external-cwd regression.

All four threads were individually rechecked and resolved. No second full CodeRabbit sweep is claimed.

## Verification and decision evidence

```text
External-review RED:            #794 / 35049387890 — expected regression failure
Correctness-fix validate:       #796 / 35049689721 — SUCCESS
Final reviewed PR validate:     #798 / 35049891609 — SUCCESS
Review-closure push validate:   #799 / 35050355079 — SUCCESS
Decision-promotion PR validate: #802 / 35058068033 — 7/7 SUCCESS
Tracking-closure push validate: #803 / 35058313792 — 7/7 SUCCESS
Merge commit:                   50c8c482165a115b8b3b8cb740f123ecf4203041
Post-merge main validate:       #809 / 35080877519 — 7/7 SUCCESS
Post-merge browser:             20 files / 328/328 + typecheck
Post-merge Python suite:        47/47 PASS
Post-merge harness build:       PASS
Post-merge canonical matrix:    7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR
```

Decision outcome:

- **D-059 — ACCEPTED:** profile + capability applicability; runner-owned selection/applicability/verdicts; raw harness observations only.
- **D-060 — ACCEPTED:** repo-local one-scenario/one-subprocess JSON protocol, fixed 10-second timeout, argv + `shell=False`, repo-root child execution.
- **D-061 — ACCEPTED:** canonical scenario semantics remain in the registry; target wiring stays outside the spec; mandatory positive control; closed four-scenario/two-browser-target v1 claim.
- **D-026 — PROPOSED:** provisional binding failure-code names remain advisory and were not promoted by T-701.

## Boundary after closure

T-701 is **DONE / REVIEWED / MERGED / MAIN REVALIDATED** on `main`.

M7 remains **IN PROGRESS** because T-702/T-703/T-704 remain TODO. No T-702 implementation or publication starts automatically; the next work requires a new explicit user gate.