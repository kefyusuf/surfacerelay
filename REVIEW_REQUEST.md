# T-803 — Browser Runtime Artifact + Clean Consumer Proof — Review Handoff

Downstream projects previously depended on repository source paths. T-803 adds a curated root ESM API, ES2022 JavaScript/declarations, versioned npm tarball staging, and isolated consumer evidence without changing runtime semantics.

## State and review target

- Branch: `feat/t-803-browser-runtime-artifact-clean-consumer`.
- Task: **DONE / REVIEW HANDOFF / EXTERNAL REVIEW PENDING**; not reviewed or merged.
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
