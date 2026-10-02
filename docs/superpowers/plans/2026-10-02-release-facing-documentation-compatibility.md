# T-804 — Release-facing documentation and compatibility policy

Status: PROPOSED / PLAN READY / IMPLEMENTATION NOT STARTED
Date: 2026-10-02
Task: T-804
Branch: docs/t-804-release-facing-plan
Baseline: e7d61ef2f044990d12d2aa4cca44a62c57e9e290
Baseline Validate: 36986576898 — 18/18 SUCCESS
Design: ../specs/2026-09-22-consumer-release-readiness-design.md

## Goal and boundary

Document consumption of the two implemented release-candidate artifacts without implying public registry availability or production readiness beyond executable evidence. This plan prepares T-804; it does not implement its guides or start T-805.

Preserve Action Definition, Runtime Binding, trusted-context, authorization, confirmation, idempotency, output, audit, driver, and projection semantics. ADR 0005 browser API isolation and ADR 0008 monorepo-first remain applicable. D-026 and D-069..D-073 remain PROPOSED. No public version, decision promotion, registry publication, tags, releases, credentials, new adapter, or conformance claim is included.

## Verified baseline

- T-801/T-802/T-803 are merged. Main at the baseline passes 18 Validate jobs.
- The root README exists; CHANGELOG.md, SECURITY.md, Laravel/browser package READMEs, and dedicated consumer/compatibility/release guides do not yet exist.
- Artifact builders are Python functions, not invented command-line interfaces: `build_laravel_release_candidate` and `build_browser_release_candidate` accept repo, stage_root, artifact_version, and source_revision. Source manifests remain development metadata; candidate metadata is staged only.
- Composer installation evidence covers PHP 8.3/8.4 × Laravel 12/13 on Ubuntu. The downstream ActionBus smoke runs only PHP 8.4 + Laravel 13. Manifest PHP ^8.3 and Illuminate ^12.0|^13.0 constraints are not a promise for every untested version/platform.
- The Laravel provider loads migrations; it does not assemble a production ActionBus. The clean-consumer smoke uses pass-through policy stages for a bounded test. Do not turn that harness into recommended production security wiring.
- Browser artifacts expose one curated ESM root with ES2022 JavaScript and declarations. CI uses Node 22, TypeScript 5.9.3, and Vite 7.3.6. The Node DriverRegistry smoke does not prove real browser/WebMCP interoperability; CommonJS and deep imports are excluded.
- T-803 npm process-launch checks pass in Docker Node 22.23.3/Python 3.12.15 and Windows. This proves tested tooling paths, not broad platform/runtime support.
- GitHub private vulnerability reporting is disabled (`GET /repos/kefyusuf/surfacerelay/private-vulnerability-reporting`, checked 2026-10-02). No private reporting contact is established by this plan. Re-check at implementation/publication gates; do not enable settings or invent an address.

Evidence sources: `.github/workflows/validate.yml`, package manifests, `packages/browser-runtime/src/index.ts`, `scripts/laravel_release_candidate.py`, `scripts/browser_release_candidate.py`, and committed clean-consumer fixtures. Repository paths in this plan refer to the root checkout.

## Planned implementation file boundary

| File | Required content |
| --- | --- |
| README.md | Separate repository development from artifact consumption; link guides; retain experimental/unofficial positioning. |
| docs/consumers/laravel.md | Artifact-repository install prerequisites, exact staged version, provider/migration behavior, installed package identity, safe existing ActionBus proof and its harness limits. |
| docs/consumers/browser-runtime.md | Exact tarball install, root-only imports, shipped declarations, bundling and DriverRegistry example; identify build/test hosts and runtime limits. |
| CHANGELOG.md | Unreleased only; implemented readiness work with evidence pointers, no invented release date/version. |
| SECURITY.md | Experimental support status, private reporting availability/blocker, no public disclosure instructions for sensitive reports, no SLA or unverified support guarantee. |
| docs/VERSIONING-COMPATIBILITY.md | Package SemVer versus integer Action versions; evidence matrix and environment limits; future 0.x policy clearly marked Proposed until publication approval. |
| docs/RELEASE-CHECKLIST.md | Exact clean revision, explicit internal prerelease input, isolated archives/consumers, hashes/evidence and review, separate publication go/no-go blockers. |
| STATUS.md, TASKS.md, REVIEW_REQUEST.md | Task progress, exact verification evidence, known limits, external review handoff. |

No package README is required in this bounded task: the current builders generate minimal candidate READMEs. Link repository guides without silently changing artifact file contents or builder behavior. Runtime/package metadata, lockfiles, workflows, schemas, conformance fixtures, ADRs and decision register are outside the implementation diff. If a guide requires an API change or new security contract, record Needs decision and stop before encoding it.

## Acceptance criteria

1. A consumer can distinguish local artifact installation from repository development and future registry publication. No registry install command is presented as currently available.
2. Laravel instructions use a Composer artifact repository and exact staged version; browser instructions install an exact tarball and import only the package root. Path repositories, dev-main, workspace/file links to source and symlinks cannot serve as proof.
3. Commands and examples map to existing builders, manifests and fixture APIs. Do not claim a nonexistent CLI or automatic pipeline registration. Clearly label fixture-only policy bypasses; do not recommend them for applications.
4. Compatibility tables separate dependency constraints, CI installation matrix, the one Laravel smoke leg, browser bundle/typecheck/primitive smoke, and unverified real-browser/platform claims.
5. CHANGELOG remains Unreleased; source manifests and public version remain unchanged. Package SemVer is independent from Action versions. Proposed 0.x policy: patches avoid intentional documented API breaks; minors may break API with changelog/migration notes; prereleases may change before public approval.
6. SECURITY honestly describes the reporting-channel gap. An absent private channel remains a publication blocker; reporting setup, credentials and registry namespace ownership require separate authorized work.
7. Release checklist names exact revision, staged version, package identity, deterministic contents and archive SHA-256; it does not promise byte-identical archives across operating systems. Integrated same-revision/two-artifact orchestration remains T-805.
8. Every relative documentation link resolves. Executable snippets are verified against existing tests/artifacts at an exact revision; unsupported claims are removed or marked Proposed/Future. No API/schema/decision change occurs.

## Execution gates after plan approval

1. Establish a clean approved baseline and implementation branch; verify the existing artifact/consumer checks. Select a non-public prerelease only as test input, never as the first public version.
2. Write the two bounded consumer guides from current fixture/manifests. Keep security harness details explicitly separate from production setup. Verify exact tarball/root import and Composer artifact installation with existing tooling; reuse tests rather than add a second runtime or orchestrator.
3. Add Unreleased changelog and SECURITY; re-check private reporting state read-only. Do not invent historical releases, addresses, support windows or SLAs.
4. Add compatibility/versioning policy and checklist. Label future release-train/policy commitments Proposed; keep decisions unchanged and T-805 distinct.
5. Update README links and positioning; audit all guide commands/claims against current source and evidence. Documentation-only text does not need artificial RED tests; any genuinely new executable verification behavior requires a focused failing test before implementation within separately agreed scope.
6. Run verification below, review the complete diff, update task/handoff evidence, submit PR, and stop at external review. Do not begin T-805 or merge automatically.

## Verification

For this plan-preparation gate: canonical `python scripts/validate.py`, `python scripts/check_release_guardrails.py`, diff/allowlist audit, relative-link checks, and existing Validate CI. No consumer guide implementation or new test runner is included.

For later implementation:

- Run the canonical validator and publication guard.
- Check all links in changed guides, root README and handoff; inspect executable blocks for unsupported commands and unsafe authority assumptions.
- Reuse `python -m unittest scripts.tests.test_browser_release_candidate` and `python -m unittest scripts.tests.test_laravel_release_candidate` in suitable hosts; verify the commands from guides consume built archives and do not silently use source.
- Browser: existing npm typecheck/test/build and artifact clean-consumer checks. Laravel: existing four install jobs and one ActionBus smoke job; do not report the smoke as four legs.
- Prefer Docker with Node 22/Python 3.12 and supported PHP/Composer hosts; use exact-head CI for the complete matrix. Record actual image/runtime versions and any unavailable checks. Docker is installed at the user-local Docker Desktop path; shell PATH availability is not engine absence.
- Confirm only approved documentation/tracking paths changed; existing APIs, source manifests, lockfiles, CI permissions, canonical schemas/fixtures and decisions remain unchanged.

Acceptance does not require broad regression repetition for unchanged prose once exact-head required checks pass. New failures or a changed executable example justify targeted additional checks.

## Readiness and next gate

Plan preparation records implementation scope, evidence limits, acceptance and verification. The private security reporting gap is already an explicit publication blocker, not an unresolved new core contract. No security contact or registry promise is selected.

Next explicit gate: **T-804 implementation-plan approval only**. Approval precedes the implementation baseline/branch and consumer-guide work. T-804 implementation and T-805 remain NOT STARTED.
