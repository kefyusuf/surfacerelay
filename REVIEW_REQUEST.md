# T-804 — Step 3 changelog / security handoff

Current gate: **STEP 3 VERIFIED / STEP 4 NOT STARTED**. Added Unreleased-only CHANGELOG and SECURITY, with no public version/release claim. Read-only GitHub private vulnerability reporting returned `enabled: false` on 2026-10-04; no alternative private contact is established, and channel verification remains a publication blocker. SECURITY prevents directing sensitive reports to public channels and states existing trust rules without inventing support windows, response SLAs or disclosure deadlines.

Baseline `ea846f4174588ad28478f4b30ae0c2dd33a47230` passed Validate 37147988730 (18/18), checked live. Offline Docker canonical validation/publication guard, 23/23 existing guardrail tests and 20 relative links PASS. No runtime/metadata/dependency/CI/schema/fixture/ADR/decision/settings changes; six documentation/tracking paths only. Exact-head CI follows commit/push. Review claims against current implemented artifact tooling, trust docs and the approved plan. Next gate: **Step 4 compatibility/versioning policy and release checklist**. PR #24 remains draft and stacked on open PR #23; no merge or publication.

## Previous Step 2 handoff

Current gate: **STEP 2 VERIFIED / STEP 3 NOT STARTED**. Added two English consumer guides for exact local Laravel ZIP and browser tarball installation. The full executable shell blocks pass in Docker against clean source revision `a15567c42d8f50ba8d060dcfbf25d76e371f8e11`. Laravel PHP 8.4.26 / Composer 2.10.3 / Laravel 13.34.0: installed identity, autoload and ActionBus smoke PASS. Browser Node 22.23.3: root import, declarations/typecheck, Vite bundle, DriverRegistry smoke and representative deep-import rejection PASS. Python 3.12.15 tooling 45/45 and browser 333/333 tests PASS; canonical validator, publication guard and relative links PASS. Exact guide-head `3b25d2f1bce2f0147dd51b14a899121197bab0b7` passed [Validate 37147835325](https://github.com/kefyusuf/surfacerelay/actions/runs/37147835325), **18/18 SUCCESS**. Final tracking-only head CI is checked separately before final handoff.

Review focus: exact archive/version and root-only import instructions, source isolation, fixture-only security bypass warnings, provider migration behavior, four Laravel installation legs versus one smoke leg, and Node proof versus real-browser interoperability. Only six documentation/tracking paths change. npm reported two moderate development-dependency advisories; no dependencies were changed.

This draft PR is stacked on the open plan branch `docs/t-804-release-facing-plan` (PR #23). Plan merge and eventual main integration require explicit authorization. Next gate: **Step 3 Unreleased changelog and SECURITY**, not T-805 or merge. No publication, public version selection, runtime/API/metadata/CI/decision change occurred.

## Approved plan handoff — preceding gate

Active scope: **PLAN APPROVED / IMPLEMENTATION NOT STARTED**. Review [the approved plan](docs/superpowers/plans/2026-10-02-release-facing-documentation-compatibility.md) on `docs/t-804-release-facing-plan`, based on main `e7d61ef2f044990d12d2aa4cca44a62c57e9e290` (Validate 36986576898: 18/18 SUCCESS).

The plan defines README, two consumer guides, Unreleased changelog, SECURITY, compatibility/versioning and release-checklist work. This PR changes the plan and three tracking documents only; none of those future guides is implemented. Canonical validation and publication guard pass; link/scope checks and exact-head CI are verified before handoff completion.

Review focus: are install/bundle/smoke claims bounded to current executable evidence, are Laravel harness policy stages clearly excluded from production guidance, is the disabled private-reporting channel honestly recorded as a publication blocker, and are proposed version policies separated from implemented semantics? No registry/public version, support SLA, security address, runtime/API/decision/CI change or T-805 orchestration is included.

Approval recorded on 2026-10-02 from user continuation at the plan-approval gate. Approved head `1bafe57b50c2870e3afea1aa3c0fc6a6cfce6141` passed Validate 37009779269 (18/18). CodeRabbit auto-review was skipped, so external review is not claimed complete. PR #23 remains open; no merge approval is implied.

Next gate: **T-804 implementation baseline and feature branch only**; no consumer-guide implementation or merge is authorized automatically.

## Previous T-803 handoff — historical evidence

Downstream projects previously depended on repository source paths. T-803 adds a curated root ESM API, ES2022 JavaScript/declarations, versioned npm tarball staging, and isolated consumer evidence without changing runtime semantics.

## State and review target

- Branch: `feat/t-803-browser-runtime-artifact-clean-consumer`.
- Task: **DONE / REVIEWED / MERGED / MAIN REVALIDATED**; PR #21 merged, all review threads resolved.
- Baseline: `5de05ea2498bea186aa6d8d11e1726f6c1c56539`.
- Final implementation: `66991da7101a773fcce99a17b99dce608080f956`.
- Pre-handoff verified head: `fd9973d2e9b58755fe8e9088f8e07cf652a09151`.
- Pre-handoff [Validate #1085 / 36934575893](https://github.com/kefyusuf/surfacerelay/actions/runs/36934575893): **18/18 SUCCESS**, checked live at that exact revision.
- Plan: `docs/superpowers/plans/2026-09-29-browser-runtime-release-candidate-clean-consumer.md`.
- This handoff changes only STATUS, TASKS, and REVIEW_REQUEST. Review the current PR head and its checks before approving.

## Implemented behavior

- Exact allowlisted root API: ten runtime values and the reviewed type closure, with runtime snapshot/type tests.
- Root-only package exports, ESM/ES2022 build and declarations; no supported CommonJS/deep-import/source-map contract. Source stays `0.0.0-dev` and `private: true`.
- Stage only package.json, dist JavaScript/declarations, root LICENSE, and a minimal candidate README. Candidate version is injected only into staging.
- Run `npm pack --json` inside staging, verify tar paths/types/content, and reuse T-801 manifest/evidence identity and SHA-256 bindings.
- Install the exact tarball into an isolated consumer, then prove package-root import, shipped declarations, TypeScript typecheck, Vite browser bundle, DriverRegistry smoke, and rejection of a representative deep import.
- Dedicated Ubuntu/Node 22/Python 3.12 consumer CI uses clean exact Git snapshots, read-only permissions, no retained checkout credentials, and no publication authority.

## Evidence

- CI browser: **22 files / 333 tests PASS**, typecheck and canonical browser conformance PASS.
- Focused browser release tooling: **19/19 PASS**.
- Exact-revision clean consumer: root import/typecheck/bundle PASS; smoke and deep-import rejection final PASS markers verified in CI logs.
- Release-contract, canonical validation, publication guard, Laravel and MCP matrices, OpenAPI importer, and PHP lint all succeed in the 18-job run.
- Separate RED/GREEN commits and expected-failure CI evidence remain in STATUS/TASKS and the approved plan (root API, distribution, artifact, isolation, execution).
- Step 12 audit: zero forbidden paths; all 16 pre-existing browser source files and package lockfile blob-identical to baseline. Laravel, Laravel-MCP, OpenAPI importer, spec, conformance, and decision register unchanged.

Local Windows limitation: direct npm.cmd typecheck/build, canonical validation, and publication guard pass. Browser tests report 332 PASS / 1 ERROR; Python browser tooling reports 8 PASS / 11 ERROR because bare `npm` cannot be launched by subprocess (`ENOENT` / WinError 2). Ubuntu CI proves the intended execution path; Windows tooling portability is not claimed. Node is only a test host for the side-effect-free smoke, not an added supported runtime.

## Changed paths

```
.github/workflows/validate.yml
STATUS.md
TASKS.md
REVIEW_REQUEST.md
docs/superpowers/plans/2026-09-29-browser-runtime-release-candidate-clean-consumer.md
packages/browser-runtime/package.json
packages/browser-runtime/tsconfig.build.json
packages/browser-runtime/src/index.ts
packages/browser-runtime/tests/public-api.test.ts
packages/browser-runtime/tests/public-api.typecheck.ts
packages/browser-runtime/tests/distribution-contract.test.ts
scripts/browser_release_candidate.py
scripts/tests/test_browser_release_candidate.py
scripts/fixtures/browser-clean-consumer/main.ts
scripts/fixtures/browser-clean-consumer/smoke.mjs
```

## Review focus

1. Can path, symlink, non-regular-file, or tar entry manipulation bypass containment checks?
2. Do staged/installed package identity, root exports, file lists and evidence hashes match exactly without source-manifest mutation?
3. Can the consumer resolve workspace/source/deep imports or silently receive a symlink installation?
4. Are process environments, npm execution, metadata, and package contents bounded to the reviewed readiness scope?
5. Does the consumer genuinely use shipped declarations and the exact tarball, and are compatibility claims limited to executable evidence?

D-026 and D-069..D-073 remain PROPOSED. T-804/T-805 have not started. No npm/Packagist publication, tags, GitHub Releases, public version selection, or decision promotion occurred. Next gate is external review/finding disposition only; merge requires an explicit user request.


## External review disposition — 2026-10-02

[PR #21](https://github.com/kefyusuf/surfacerelay/pull/21) is non-draft. CodeRabbit completed a full review at `34d8cf0dc7e294af6566d24bdeeb4ce9395720bc` and posted [one actionable Minor](https://github.com/kefyusuf/surfacerelay/pull/21#discussion_r4163289925). Reviewed-head [Validate / 36971286670](https://github.com/kefyusuf/surfacerelay/actions/runs/36971286670) passed **18/18** jobs.

The Windows npm subprocess finding is accepted and verified against the implementation and existing local failures. The next gate is focused RED tests only. The subsequent fix is bounded to npm launch in the browser artifact tooling, its integration fixture, and the distribution emission test. Preserve argv boundaries, fail clearly when npm is missing, and retain archive/consumer isolation and stripped credentials. Do not infer a broader Windows runtime support claim from tooling portability.

No corrective implementation is included in this disposition. The finding remains unresolved until RED/GREEN and exact-head verification support closure. No reviewer reply, thread resolution, merge, later task, or publication is part of this commit.


## T-803 npm launch finding — RED PROVEN / GREEN NOT STARTED

Five focused `BrowserNpmLaunchContractTest` methods now cover npm path resolution for pack/consumer commands, missing-npm fail-closed behavior for both paths, and a Windows npm.cmd fixture executed via Node's npm CLI with literal arguments and no shell. Arguments include spaces and shell metacharacters. These are process-launch tests; the mocked Windows layout does not claim Windows end-to-end support.

Local focused command: `python -B -m unittest scripts.tests.test_browser_release_candidate.BrowserNpmLaunchContractTest` — **5 tests / 5 expected assertion failures / 0 errors**. Existing production behavior launches bare npm and does not check availability first. No corrective implementation changed. Canonical validation, publication guard, and diff checks pass. The previously verified baseline has 19 browser tooling tests passing in Linux CI; the expected new CI result is 19 PASS / 5 expected FAIL in browser-release-consumer, with the other 17 Validate jobs green.

Docker CLI was not found on PATH or in standard Docker Desktop executable locations. No Docker run or installation is claimed. Linux CI is used for independent RED evidence; Docker remains preferred when an available engine/host is provided.

Changed files: focused Python tests plus STATUS, TASKS, and REVIEW_REQUEST only. No production/browser runtime, API, dependency, workflow, schema, conformance, or decision edits. The external-review finding remains open.

Next explicit gate: **GREEN npm launch implementation for this Minor only**, after the expected Linux CI failures are verified. Do not resolve the review thread, merge, or start T-804 automatically.


Linux RED evidence verified at `d4a19c57927e05c1847c47be8a0032771c13895f`: [Validate / 36975704645](https://github.com/kefyusuf/surfacerelay/actions/runs/36975704645) finished **17 SUCCESS / 1 expected FAILURE**. Only `browser-release-consumer` failed. Its job log proves **24 tests / 19 PASS / 5 expected assertion failures / 0 errors**; the isolated real-artifact consumer journey remains green. All five failures are the new npm-launch contracts. RED is independently proven on Linux; next gate remains GREEN implementation only.

## T-803 npm launch finding — GREEN VERIFIED LOCALLY / CI PENDING

The bounded fix resolves npm from the host PATH. POSIX launches the resolved executable; Windows .cmd/.bat shims launch the adjacent npm-cli.js through resolved Node, without a shell. Missing npm, Node, or CLI fails before launching a process. Pack and all four consumer npm commands share this launch path. The integration fixture uses the same launcher; the browser emission test uses the npm-provided CLI path with process.execPath.

Verification on 2026-10-02: Docker Node 22.23.3 / Python 3.12.15, read-only repository mount and temporary filesystem: 25/25 Python artifact/consumer tests PASS, 333/333 browser tests PASS, typecheck/build PASS, canonical validation/publication guard PASS. Windows: 25/25 Python tests including real pack/install/isolated consumer PASS with project-local npm cache and approved process access; 333/333 browser tests and typecheck PASS. The previous Windows bare-npm launch limitation is addressed for these tested paths. This is tooling evidence, not a new supported browser-runtime platform claim.

The five RED tests are GREEN. One additional negative method covers missing Windows Node/CLI prerequisites. Existing archive/consumer isolation, argv boundaries and consumer credential stripping are preserved. No runtime source/API/metadata/dependency/workflow/spec/conformance/decision change. The Dockerfile remains ignored under .tmp; no permanent container infrastructure was added. Docker is available at the user-local Docker Desktop installation, correcting the earlier narrow lookup result.

Changed files: scripts/browser_release_candidate.py, scripts/tests/test_browser_release_candidate.py, packages/browser-runtime/tests/distribution-contract.test.ts, STATUS.md, TASKS.md, REVIEW_REQUEST.md. Exact-head CI remains to be verified after commit. The review thread remains open. Next gate after CI verification: external-review re-check/disposition only; no automatic merge or T-804.

GREEN exact-head evidence: `d70da239434beee09635696c8a1b1489afd0a00a`, [Validate / 36977031979](https://github.com/kefyusuf/surfacerelay/actions/runs/36977031979) — **18/18 SUCCESS**. The browser-release-consumer log proves 25 tests PASS plus clean-consumer smoke and deep-import rejection PASS. Docker and Windows checks above are also green. The npm finding is fixed and verified, but its review thread remains open pending external re-check. Next gate: external-review re-check/disposition only; no merge or T-804.

## T-803 external-review closure — DONE / REVIEW CLOSED

Date: 2026-10-02. PR #21 remains OPEN / non-draft / NOT MERGED. Reviewed pre-closure head: `fa0bafc197dfc5ede0b96bc9fbbabeafe0839fd7`. [Validate / 36977314801](https://github.com/kefyusuf/surfacerelay/actions/runs/36977314801) passed **18/18** jobs at that exact revision.

[CodeRabbit re-check](https://github.com/kefyusuf/surfacerelay/pull/21#discussion_r4164121881) confirms the original Windows npm launch finding is addressed and no issue remains in its covered launch paths. The bot inspected the changed paths and confirmed CI; it did not rerun tests or independently reproduce Windows execution. Docker/Windows execution evidence is recorded above. The single review thread is resolved: **1 total / 0 unresolved**, verified via GitHub GraphQL after the reply.

This closure changes STATUS, TASKS, and REVIEW_REQUEST only. No implementation changes. D-026 / D-069..D-073 remain PROPOSED; T-804/T-805 remain NOT STARTED. No merge, tag, release, publication, or public version selection occurred.

Next explicit gate: **T-803 merge decision only**. External-review closure does not authorize merge or begin T-804 automatically.

## T-803 post-merge closure — DONE / REVIEWED / MERGED / MAIN REVALIDATED

The user explicitly authorized merge on 2026-10-02. [PR #21](https://github.com/kefyusuf/surfacerelay/pull/21) is MERGED as `5ae4562a0b1676858f2c001c29b47eb6a45ef44b`. Post-merge main [Validate / 36985896210](https://github.com/kefyusuf/surfacerelay/actions/runs/36985896210) passed **18/18** jobs at that exact merge commit. Local main canonical validation and publication guard pass; checkout was clean before this tracking update.

Review is closed: 1 thread / 0 unresolved. This post-merge tracking update changes STATUS, TASKS, and REVIEW_REQUEST only. No implementation changes. D-026 / D-069..D-073 remain PROPOSED. T-804/T-805 remain NOT STARTED; no tag/release/publication/version selection occurred.

Next gate: **T-804 implementation-plan preparation only**, on explicit continuation; do not start its implementation automatically.
