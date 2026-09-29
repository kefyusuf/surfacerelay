# T-803 — Browser Runtime Public API + Release-Candidate Artifact + Clean Consumer Proof

Status: APPROVED / IMPLEMENTATION NOT STARTED
Date: 2026-09-29
Task: T-803
Baseline: main@9bd71d1212871a27d2b96fa8e428c98d198ef5ce
Baseline validation: Validate #1049 / 36542316261 — 17/17 SUCCESS
Design: docs/superpowers/specs/2026-09-22-consumer-release-readiness-design.md
Predecessor: T-802 — DONE / REVIEWED / MERGED / MAIN REVALIDATED

## 1. Goal

Prove that @surfacerelay/browser-runtime can expose one reviewed root-only ESM API, emit ES2022 JavaScript plus TypeScript declarations, produce a registry-independent npm tarball from an exact Git revision, and be consumed by an isolated downstream project without source-relative, deep-import, workspace, symlink, or monorepo coupling.

T-803 is packaging and downstream-consumer evidence only. It must not change SurfaceRelay runtime semantics, driver behavior, trust boundaries, Action/Binding semantics, WebMCP semantics, canonical schemas, or conformance verdicts.

## 2. Current-state facts

At the T-803 baseline:

- packages/browser-runtime/package.json is private: true, ESM, version 0.0.0-dev;
- no root exports map exists;
- no root src/index.ts public facade exists;
- no declaration/JavaScript build output is emitted by the normal tsconfig.json;
- no package build script exists;
- existing source tests use repository-local module paths;
- the browser CI job runs source typecheck/tests/conformance only;
- global .gitignore already ignores dist/ and .tmp/;
- T-801 release helpers already provide prerelease SemVer validation, exact source-revision identity, staging containment, content manifests, SHA-256 evidence, and publication guardrails.

The plan therefore adds a distribution boundary around existing behavior; it does not redesign that behavior.

## 3. Exact scope

T-803 must produce all of the following from one explicit non-public prerelease version and one exact Git revision:

1. one curated package-root TypeScript facade at packages/browser-runtime/src/index.ts;
2. one ESM/ES2022 build with .js and .d.ts output and no CommonJS/source-map contract;
3. a root-only package.json exports contract;
4. an explicit runtime-export snapshot plus type-level public API test;
5. a staged package tree containing only reviewed release material;
6. one npm pack .tgz release-candidate artifact;
7. T-801 content-manifest + artifact-evidence identity bound to exact package/version/revision;
8. one isolated TypeScript/browser-oriented consumer that:
   - installs only the generated .tgz for SurfaceRelay;
   - imports only @surfacerelay/browser-runtime;
   - typechecks against shipped declarations;
   - bundles successfully with a normal ESM browser bundler;
   - executes one side-effect-free package-root smoke;
9. static and runtime proof that no deep/source/workspace/symlink coupling is required.

## 4. Non-goals / hard stop boundaries

T-803 must not:

- run npm publish;
- create an npm registry package;
- create a Git tag or GitHub Release;
- choose the first public SemVer;
- add registry credentials or npm auth tokens;
- remove the source package private: true publication blocker;
- replace source version 0.0.0-dev with a public release version;
- implement T-804 consumer/release documentation;
- implement T-805 integrated release-readiness orchestration;
- publish or modify surfacerelay/laravel-mcp;
- publish or modify @surfacerelay/openapi-importer;
- change surfacerelay/laravel production behavior;
- change canonical spec/** or conformance/**;
- change any existing browser-runtime driver/runtime/projection semantics merely to make packaging easier;
- add CommonJS output;
- add a supported deep-import contract;
- add public source maps;
- claim Node as a supported runtime;
- add a general bundler/runtime abstraction;
- start T-804/T-805 automatically.

D-026 and D-069..D-073 remain PROPOSED during T-803 unless a separate explicit decision-promotion gate is authorized.

If implementation discovers that an existing browser runtime implementation file must change semantically for packaging to work, stop and reopen scope instead of silently broadening T-803.

## 5. Planned file boundary

Expected implementation files:

~~~text
packages/browser-runtime/src/index.ts
packages/browser-runtime/tsconfig.build.json
packages/browser-runtime/package.json
packages/browser-runtime/tests/public-api.test.ts
packages/browser-runtime/tests/public-api.typecheck.ts
scripts/browser_release_candidate.py
scripts/tests/test_browser_release_candidate.py
scripts/fixtures/browser-clean-consumer/main.ts
scripts/fixtures/browser-clean-consumer/smoke.mjs
.github/workflows/validate.yml
STATUS.md
TASKS.md
REVIEW_REQUEST.md
docs/superpowers/plans/2026-09-29-browser-runtime-release-candidate-clean-consumer.md
~~~

Expected unchanged surfaces:

~~~text
packages/browser-runtime/src/driver-registry.ts
packages/browser-runtime/src/livewire-*.ts
packages/browser-runtime/src/htmx-*.ts
packages/browser-runtime/src/runtime-binding-expiry.ts
packages/browser-runtime/src/types.ts
packages/browser-runtime/src/webmcp-*.ts
packages/browser-runtime/package-lock.json
packages/laravel/**
packages/laravel-mcp/**
packages/openapi-importer/**
spec/**
conformance/**
docs/DECISION-REGISTER.md
~~~

package-lock.json should not change because T-803 does not add a permanent package dependency. If npm tooling requires a lockfile change despite no dependency change, stop and verify the exact reason before accepting it.

## 6. Curated root public API contract

### 6.1 Runtime value exports

The initial root runtime surface is deliberately allowlisted to these values:

~~~text
DriverRegistry
LivewireBrowserDriver
GlobalLivewireBrowserRuntime
HtmxBrowserDriver
GlobalHtmxBrowserRuntime
createHtmxBindingTarget
WebMcpRegistrationLifecycle
projectAnnotations
projectWebMcpToolName
projectBoundActionTool
~~~

The public-export snapshot must fail on either an accidental addition or removal.

### 6.2 Type-only exports

The root facade may export the type closure needed by the reviewed consumer-facing classes/functions:

~~~text
ActionScope
ActionEffect
ActionRisk
OutputSensitivity
OutputContentTrust
ActionRef
ActionDefinition
RuntimeBinding
DriverExecutionContext
BindingDriver
BrowserClock

LivewireActionHandle
LivewireActionInterceptorContext
LivewireWire
LivewireBrowserRuntime
LivewireAmbientRoot

HtmxRequestMethod
HtmxBindingTarget
CreateHtmxBindingTargetOptions
HtmxAjaxMethod
HtmxClassListLike
HtmxSourceElement
HtmxLocationSnapshot
HtmxAjaxContext
HtmxBrowserRuntime
HtmxAmbientRoot

WebMcpAnnotations
WebMcpRegistrationLease
BoundActionTool
BoundActionExecutor
WebMcpToolExecuteOptions
WebMcpTool
WebMcpRegisterToolOptions
WebMcpModelContext
~~~

### 6.3 Explicit exclusions

Do not root-export internal helpers merely because they already have TypeScript export declarations in their source modules.

The first root API excludes at minimum:

~~~text
mapHtmxActionInput
parseHtmxBindingTarget
classifyRuntimeBindingExpiry
systemBrowserClock
LIVEWIRE_RESERVED_METHOD_NAMES
isLivewireReservedMethodName
LivewireBindingExecutionError
HtmxBindingExecutionError
HtmxBindingDescriptorError
~~~

These remain implementation modules, not supported package subpaths.

If implementation finds that one excluded symbol is genuinely required to make an already-reviewed public constructor/function usable, stop at a public-API scope check before adding it.

## 7. Source package metadata and build contract

Modify packages/browser-runtime/package.json minimally:

- keep name @surfacerelay/browser-runtime;
- keep source version 0.0.0-dev;
- keep private: true;
- keep type: module;
- add types: ./dist/index.d.ts;
- add a root-only exports map with exactly "." -> { types, import };
- add files allowlisting dist, README.md, and LICENSE;
- add a build script using the local TypeScript compiler;
- do not add main/CommonJS/require exports;
- do not add publish/auth configuration.

Create tsconfig.build.json extending the existing source config and overriding only distribution concerns:

- noEmit: false;
- rootDir: src;
- outDir: dist;
- declaration: true;
- declarationMap: false;
- sourceMap: false;
- preserve target ES2022;
- preserve ESM/Bundler module semantics;
- include src/**/*.ts only.

The global .gitignore already ignores dist/; no ignore-file change is planned.

## 8. Public API evidence

### 8.1 Runtime export snapshot

packages/browser-runtime/tests/public-api.test.ts imports all runtime exports from the new source root facade and asserts the exact sorted runtime key list from Section 6.1.

The test must not snapshot deep/internal modules.

### 8.2 Type-level public API proof

packages/browser-runtime/tests/public-api.typecheck.ts imports the Section 6.2 types from the source root facade and proves representative constructor/function signatures compile through that root.

The existing tsconfig.json already includes tests/**/*.typecheck.ts; no source-typecheck config expansion is planned.

## 9. Release-candidate package contract

### 9.1 Build source

Authoritative CI materializes an exact clean source snapshot using git archive HEAD into runner temp storage.

Inside that exact snapshot:

1. npm ci installs only browser-runtime development tooling;
2. npm run build emits dist/**;
3. the release-candidate builder stages the package from the exact built snapshot.

The repository checkout itself is not used as a consumer or package source link.

### 9.2 Staged package root

The staged npm package contains only:

~~~text
package.json
dist/**/*.js
dist/**/*.d.ts
LICENSE
README.md
~~~

Rules:

- copy packages/browser-runtime/package.json;
- inject only the explicit artifact version into the staged manifest;
- source package.json remains 0.0.0-dev;
- retain private: true during M8 so publication remains blocked;
- copy root LICENSE verbatim;
- generate a deterministic minimal candidate README.md containing package identity, artifact version, exact source revision, and “not a public release” wording only;
- do not include src/**, tests/**, conformance/**, node_modules/**, lockfiles, tsconfig files, Vitest files, Git metadata, source maps, or repository docs.

### 9.3 npm tarball

Run npm pack --json only inside the staged package directory.

Requirements:

- parse npm's JSON result rather than hardcoding the scope-normalized filename;
- produced artifact must be one .tgz;
- validate tar entries before consumer use;
- no absolute/traversal/symlink/hardlink/device entries;
- archive must contain only the staged release material under npm's normal package/ prefix;
- no .map, .ts, test, source, or config files;
- staged package.json identity/version/root-only exports must match the requested candidate;
- no preinstall/install/postinstall/prepublish/publish or registry-auth behavior may be introduced.

Byte-for-byte tarball reproducibility across operating systems is not an M8 requirement.

### 9.4 T-801 evidence reuse

scripts/browser_release_candidate.py must reuse the T-801 identity/evidence helpers:

- validate_artifact_version();
- validate_source_revision();
- release_candidate_root();
- resolve_staging_path();
- build_content_manifest();
- build_artifact_evidence();
- serialize_evidence_json().

Package identity is exactly @surfacerelay/browser-runtime.

## 10. Clean consumer contract

### 10.1 Temporary consumer

Create a fresh temporary consumer outside:

- the repository checkout;
- the exact source snapshot;
- the candidate staging directory.

Generated consumer metadata contains no SurfaceRelay source dependency.

The consumer's only permanent third-party development dependencies are pinned test-host tools:

~~~text
typescript: 5.9.3
vite: 7.3.6
~~~

These versions match the already-resolved browser-runtime toolchain at the planning baseline and do not become runtime dependencies of SurfaceRelay.

Install SurfaceRelay separately from the exact generated tarball using npm with no saved source/file/workspace dependency.

### 10.2 Root-only consumer fixture

scripts/fixtures/browser-clean-consumer/main.ts must import exclusively from:

~~~text
@surfacerelay/browser-runtime
~~~

It must not reference:

- @surfacerelay/browser-runtime/dist/*;
- packages/browser-runtime/src;
- repository-relative source paths.

The fixture should exercise representative type/value imports without creating new runtime semantics.

### 10.3 Typecheck

Generated consumer tsconfig.json uses:

- ES2022;
- ESM;
- Bundler module resolution;
- DOM + ES2022 libs;
- strict mode;
- no emit.

Typecheck must resolve exclusively through the installed tarball's root declaration entry.

### 10.4 Browser bundle

Generate a minimal index.html that imports the fixture entry and run Vite production build.

The bundle proves ordinary browser ESM tooling can consume the root package entry.

No Vite-specific SurfaceRelay integration is created or claimed.

### 10.5 Side-effect-free package-root smoke

scripts/fixtures/browser-clean-consumer/smoke.mjs imports only the package root and exercises DriverRegistry:

1. register one fake BindingDriver;
2. resolve that exact driver;
3. prove unsupported lookup fails closed.

This smoke is intentionally side-effect-free and DOM-independent. Node 22 is only the CI/test host for this package-root primitive; T-803 does not claim Node as a supported SurfaceRelay browser-runtime environment.

Expected observable output:

~~~text
SurfaceRelay browser-runtime clean-consumer smoke: PASS
~~~

### 10.6 Installed identity and deep-import checks

After tarball installation, verify:

- installed package name is exact;
- installed version equals the requested prerelease;
- installed package directory is a real directory, not a symlink;
- real path is outside repository/source package trees;
- shipped package.json exposes only ".";
- package root resolves successfully;
- a representative deep import such as @surfacerelay/browser-runtime/dist/driver-registry.js is rejected by the exports map.

The deep-import rejection may use Node module resolution as a test-host mechanism; it is not a Node runtime support claim.

## 11. Isolation guard

Before consumer execution, fail if generated consumer configuration or fixtures reference:

~~~text
packages/browser-runtime
packages/browser-runtime/src
../packages
workspace:
link:
@surfacerelay/browser-runtime/dist/
~~~

A direct install of the exact generated .tgz is allowed and required. Generic file: text must not be rejected blindly if it refers to that tarball; the guard must instead reject file/workspace/directory references that resolve to the source package tree.

Also reject:

- consumer symlinks resolving into the repository/source package tree;
- installed package symlinks;
- package-root imports that bypass exports;
- copied source .ts files in the staged artifact.

This isolation proof is separate from the T-801 publication guard.

## 12. Implementation sequence

### Step 1 — baseline + branch

Only after this plan is separately approved:

- create feat/t-803-browser-runtime-artifact-clean-consumer from the then-current green main;
- record exact baseline SHA and Validate run;
- confirm T-802 remains final on main;
- do not change implementation in this baseline step.

### Step 2 — RED public-root API tests

Add the runtime-export snapshot and root typecheck fixture before src/index.ts exists.

Expected RED:

- public-api runtime import fails because no root facade exists;
- public-api typecheck fails for the same missing root facade.

Do not change existing runtime implementation files.

### Step 3 — GREEN curated root facade

Create only packages/browser-runtime/src/index.ts with the exact Section 6 allowlist.

Run:

~~~bash
npm --prefix packages/browser-runtime run typecheck
npm --prefix packages/browser-runtime test
~~~

The existing browser tests must remain green.

### Step 4 — RED build/distribution metadata tests

Add focused assertions for:

- root-only exports map;
- declarations + ESM entry;
- source version remains 0.0.0-dev;
- source package remains private;
- no require/CommonJS export;
- build emits no source/declaration maps;
- build output is ES2022 ESM;
- expected root declaration exists.

Capture expected RED before adding build metadata/config.

### Step 5 — GREEN ESM/declaration build

Add tsconfig.build.json and minimal package.json build/export metadata.

Run:

~~~bash
npm --prefix packages/browser-runtime run typecheck
npm --prefix packages/browser-runtime test
npm --prefix packages/browser-runtime run build
~~~

Inspect dist/** and confirm it is ignored/uncommitted.

### Step 6 — RED artifact-contract tests

Create scripts/tests/test_browser_release_candidate.py with failing tests for:

1. exact package/version/revision identity;
2. staged version injection without source-manifest mutation;
3. source private: true remains intact;
4. staged content allowlist;
5. source/tests/conformance/lock/config/maps excluded;
6. symlink/non-regular staged input rejection;
7. exact root-only exports in staged manifest;
8. tar path/type safety;
9. exact npm-pack file list;
10. T-801 content/evidence identity and SHA-256 bindings;
11. candidate README contains no public-release claim.

### Step 7 — GREEN artifact builder + npm pack verification

Create scripts/browser_release_candidate.py minimally to satisfy Step 6.

It may invoke npm pack --json for the staged package but must not invoke npm publish, registry login, tag, or release commands.

Focused verification:

~~~bash
python -m unittest scripts.tests.test_browser_release_candidate -v
python scripts/check_release_guardrails.py
python scripts/validate.py
~~~

### Step 8 — RED clean-consumer/isolation tests

Extend T-803 tooling tests first with expected failures covering:

- consumer source/deep/workspace references;
- consumer/source symlink coupling;
- wrong tarball identity/version;
- installed package symlink;
- missing declaration/root entry;
- deep-import contract leakage;
- consumer path placed inside source package.

Add the permanent main.ts and smoke.mjs fixtures only after their failure boundary is explicit.

### Step 9 — GREEN clean-consumer generator/verifier

Extend the T-803 tooling with bounded workspace generation and installed-package verification.

Authoritative consumer steps:

1. install pinned TypeScript/Vite test-host tools;
2. install exact generated .tgz with no saved SurfaceRelay source dependency;
3. verify installed identity and physical isolation;
4. typecheck main.ts;
5. Vite production bundle;
6. execute smoke.mjs;
7. verify representative deep import is blocked.

No browser automation framework is needed for this side-effect-free package-root proof.

### Step 10 — dedicated CI consumer job

Modify .github/workflows/validate.yml with one non-matrix job, suggested name:

~~~text
browser-release-consumer
~~~

Requirements:

- Ubuntu;
- checkout with persist-credentials: false;
- contents: read only;
- verify checkout exact/clean before tooling creates caches;
- Node 22;
- Python 3.12;
- npm ci for browser-runtime source build tooling;
- install requirements-dev.txt;
- artifact version 0.0.0-alpha1 as a non-public CI prerelease;
- source revision from exact git rev-parse HEAD;
- exact source snapshot from git archive HEAD;
- build ESM/declarations from that snapshot;
- stage + npm pack the candidate;
- isolated install/typecheck/Vite bundle/smoke;
- no npm token/auth;
- no npm publish;
- no tags/releases.

Current Validate has 17 jobs. T-803 adds exactly one dedicated job, so the expected total becomes 18 jobs.

### Step 11 — whole-task verification

Required focused verification:

~~~bash
npm --prefix packages/browser-runtime run typecheck
npm --prefix packages/browser-runtime test
npm --prefix packages/browser-runtime run build
python -m unittest scripts.tests.test_browser_release_candidate -v
python -m unittest discover -s scripts/tests -p 'test_release_candidate*.py' -v
python scripts/check_release_guardrails.py
python scripts/validate.py
~~~

Then require exact-head GitHub Validate:

~~~text
18/18 SUCCESS
~~~

Explicitly inspect the new browser-release-consumer job and confirm:

- exact candidate version;
- exact revision;
- root-only import;
- typecheck PASS;
- bundle PASS;
- smoke PASS;
- deep-import rejection PASS;
- no source/package symlink.

### Step 12 — forbidden-diff / semantic-mutation audit

At the final implementation head:

- existing packages/browser-runtime/src/*.ts implementation files must be unchanged;
- only new src/index.ts may change the source module tree;
- packages/browser-runtime/package-lock.json should be unchanged;
- packages/laravel/** diff empty;
- packages/laravel-mcp/** diff empty;
- packages/openapi-importer/** diff empty;
- spec/** diff empty;
- conformance/** diff empty;
- docs/DECISION-REGISTER.md unchanged;
- source browser package version remains 0.0.0-dev;
- source browser package remains private: true;
- no tag/release/publication/auth wiring exists.

If any forbidden or semantic diff appears, stop and correct or reopen scope.

### Step 13 — tracking / external-review handoff

Only after all focused tests, exact-head 18/18 CI, artifact inspection, consumer proof, and forbidden-diff audit are green:

- mark T-803 DONE / REVIEW HANDOFF;
- record exact evidence in STATUS.md;
- update TASKS.md;
- rewrite REVIEW_REQUEST.md for T-803;
- keep D-026 and D-069..D-073 PROPOSED;
- do not begin T-804.

## 13. Acceptance criteria

T-803 is complete only when all are true:

- package root exports exactly the reviewed allowlist;
- source package remains private and source version remains 0.0.0-dev;
- ESM ES2022 JavaScript and declarations build successfully;
- no CommonJS or public source-map contract is introduced;
- package exports expose only ".";
- public-export runtime snapshot and type-level API tests are green;
- staged package contains only package.json, dist JS/declarations, LICENSE, and minimal candidate README;
- npm tarball is generated from exact source revision and explicit prerelease;
- T-801 content/evidence format is reused;
- isolated consumer installs the tarball, not repository source;
- root-only import typechecks against shipped declarations;
- normal Vite browser bundle succeeds;
- side-effect-free DriverRegistry smoke passes;
- representative deep import is blocked;
- installed package is not a source/workspace/symlink link;
- existing browser runtime implementation semantics remain unchanged;
- exact-head Validate is 18/18 green;
- no registry publication/tag/release/public SemVer/decision promotion occurs.

## 14. Explicit self-review checklist

After every implementation step:

- Scope: is this still browser packaging/public-surface proof only?
- API minimization: did a root export get added only because the reviewed consumer journey requires it?
- Deep-import boundary: can a consumer bypass the root exports map?
- Runtime semantics: did any existing driver/runtime/projection implementation change?
- Artifact identity: are package/version/revision exact and T-801-bound?
- Source mutation: did artifact version injection avoid changing source version/private state?
- Package contents: are src/tests/config/maps/lockfiles absent from the tarball?
- Filesystem safety: can a symlink/path escape enter stage/artifact/consumer?
- Consumer isolation: can npm silently fall back to workspace/source/file-directory coupling?
- Declarations: does the clean consumer use shipped .d.ts through package root?
- Bundle: does ordinary ESM browser tooling consume the root package?
- Runtime claim: is Node used only as a test host for the side-effect-free smoke?
- Publication safety: are npm auth/publish/tag/release still absent?
- Architecture: are Action/Binding/trust/WebMCP/conformance semantics unchanged?
- Evidence: are RED/GREEN and exact-head CI recorded rather than assumed?

## 15. Stop boundary

This plan-preparation gate does not authorize implementation.

After this plan is committed and exact-head validation is green, stop.

The next explicit gate is T-803 implementation-plan approval only. Approval may authorize creation of the T-803 feature branch, but implementation must not begin from this plan-preparation commit automatically.


## 16. Plan approval checkpoint

Approved against:

~~~text
Plan-preparation head:            8f1a560f8c878221a6c1008bf7d6f7db5cd9e420
Plan-preparation Validate:        #1050 / 36554343680 — 17/17 SUCCESS
Approval scope:                   T-803 plan only
Implementation branch:           NOT CREATED
Implementation:                  NOT STARTED
D-026:                            PROPOSED / unchanged
D-069..D-073:                    PROPOSED / unchanged
T-804..T-805:                    NOT STARTED
Publish/tag/release:              NOT AUTHORIZED
~~~

The plan is approved as the implementation contract for T-803. Approval does not itself create the feature branch or authorize Step 2 RED work in this commit.

Next explicit gate: **T-803 Step 1 — baseline + feature branch only**. After that gate is separately completed, stop before Step 2 RED public-root API tests.


## 17. Step 1 checkpoint — baseline + feature branch complete

~~~text
Implementation baseline:          main@5de05ea2498bea186aa6d8d11e1726f6c1c56539
Baseline Validate:                #1051 / 36562318964 — 17/17 SUCCESS
Feature branch:                   feat/t-803-browser-runtime-artifact-clean-consumer
Production implementation:       NOT STARTED
RED public-root API tests:        NOT STARTED
Package/build/CI changes:         NONE
~~~

The feature branch was created from the exact green baseline SHA. Step 1 introduced no production, test, CI, package, build, artifact, consumer, spec, or conformance behavior.

Next explicit gate: **Step 2 — RED public-root API tests only**. Stop before Step 3 GREEN curated-root facade implementation.


## 18. Step 2 checkpoint — public-root API RED proven

~~~text
RED test files:
  packages/browser-runtime/tests/public-api.test.ts
  packages/browser-runtime/tests/public-api.typecheck.ts
Initial RED head:                 88720b6da4c15e4e3a2f4880de11c8f5ab734c6d
Authoritative RED head:           e5cfbb73b12d5fdd0d10307ec8455f3e776ca4aa
Validate:                         #1055 / 36582905214
Repository result:                16 SUCCESS / 1 expected FAILURE
Expected failing job:             browser
Typecheck RED:                    TS2307 — ../src/index.js missing
Unexpected typecheck errors:      NONE
Runtime export snapshot:          committed; Vitest step blocked by earlier expected typecheck failure
GREEN root facade:                NOT STARTED
Build/artifact/consumer/CI work:  NOT STARTED
~~~

The initial RED commit correctly proved the missing root-facade boundary but also surfaced two fixture-only implicit-any errors because the missing module removed contextual typing. The authoritative test-only correction added explicit parameter types and introduced no production or GREEN implementation.

On the authoritative RED head, the browser job fails only because src/index does not exist. The normal job ordering stops before Vitest, so the runtime snapshot remains committed but unexecuted until Step 3 supplies the root facade. All other repository jobs remain green.

Next explicit gate: **Step 3 — GREEN curated root facade only**. Create only packages/browser-runtime/src/index.ts with the approved allowlist, then run the existing typecheck/test harness. Stop before Step 4 build/distribution metadata RED work.


## 19. Step 3 checkpoint — GREEN curated root facade verified

~~~text
GREEN implementation head:       85ffb7925a61747ba0c099b4cd27c646cb45233f
Implementation file:             packages/browser-runtime/src/index.ts
Implementation diff:             index.ts only
Typecheck:                       PASS
Browser-runtime tests:           21 files / 329 tests PASS
Public runtime export snapshot:  PASS
Canonical browser conformance:   7 PASS / 0 FAIL / 0 ERROR / 1 NOT_APPLICABLE
Repository Validate:             #1057 / 36589996508 — 17/17 SUCCESS
HTMX fixture:                    #26 / 36589996536 — SUCCESS
Existing runtime implementation: UNCHANGED
Build/package metadata:          NOT STARTED
Artifact/consumer/CI work:       NOT STARTED
~~~

The root facade satisfies the Step 2 contract using only the approved runtime-value allowlist and type closure. No excluded helper or error type is exported as a runtime value, and the exact runtime export snapshot is green.

No existing browser-runtime implementation file changed. Step 3 adds no package exports map, declaration build, tarball tooling, clean-consumer fixture, or dedicated release-consumer CI.

Next explicit gate: **Step 4 — RED build/distribution metadata tests only**. Stop before Step 5 GREEN ESM/declaration build metadata implementation.

## 20. Step 4 checkpoint — build/distribution metadata RED proven

~~~text
RED test head:                    bfc8c522e1c7dd9e7e3ceee57a67ac3dfb7f52da
RED test file:                    packages/browser-runtime/tests/distribution-contract.test.ts
Validate:                         #1059 / 36592466741
Repository result:                16 SUCCESS / 1 expected FAILURE
Expected failing job:             browser
Browser typecheck:                PASS
Distribution contract:            4 tests / 1 PASS / 3 expected FAIL
Full Vitest result:               22 files / 330 PASS / 3 expected FAIL
Missing package seam:             types/root exports/files/build script
Missing build config:             tsconfig.build.json
Missing emit seam:                npm run build
Source package invariants:        PASS
GREEN metadata/config:            NOT STARTED
Artifact/consumer/CI work:        NOT STARTED
~~~

The passing test proves the source package identity, 0.0.0-dev version, private: true publication blocker, and ESM module type remain intact.

The three RED failures map one-to-one to the approved Step 5 implementation boundary: minimal root-only distribution metadata and build script, bounded tsconfig.build.json, and emitted ESM JavaScript plus declarations without source/declaration maps or CommonJS output. No unrelated failure is present.

Next explicit gate: **Step 5 — GREEN ESM/declaration build metadata only**. Add only the approved package metadata and tsconfig.build.json, then make the existing distribution contract green. Stop before Step 6 artifact-contract RED tests.

## 21. Step 5 checkpoint — GREEN ESM/declaration build metadata verified

~~~text
GREEN implementation head:       71af1a28f792723f9c49be911c10130f78d937c1
Implementation files:
  packages/browser-runtime/package.json
  packages/browser-runtime/tsconfig.build.json
Distribution contract:          4/4 PASS
Browser-runtime tests:           22 files / 333 tests PASS
Canonical browser conformance:   7 PASS / 0 FAIL / 0 ERROR / 1 NOT_APPLICABLE
Repository Validate:             #1061 / 36609079739 — 17/17 SUCCESS
Source version/private:          0.0.0-dev / true
Root exports:                    "." only
Build output:                    ESM JS + .d.ts
CommonJS/maps:                   NONE
package-lock.json:               UNCHANGED
Committed dist/**:               NONE
Existing runtime implementation: UNCHANGED
Artifact/consumer/CI work:       NOT STARTED
~~~

The Step 4 RED contract is now fully green. The source package gains only the approved root distribution metadata, files allowlist, build script, and bounded build tsconfig. The real compiler emit is exercised into temporary storage and proves the expected root JavaScript/declaration output without maps or CommonJS.

No runtime implementation source, dependency lock, artifact builder, npm-pack staging, consumer fixture, workflow, publication credential, tag, or release behavior changed.

Next explicit gate: **Step 6 — RED artifact-contract tests only**. Stop before Step 7 GREEN browser release-candidate builder/npm-pack implementation.

## 22. Step 6 checkpoint — browser artifact-contract RED proven

~~~text
Permanent RED test file:         scripts/tests/test_browser_release_candidate.py
Permanent RED test head:         1168947dc24a107a447eb21a3c693fb86eb35e60
Temporary discovery bridge:      fa8491ba774d68c7e2f3d64c69ed4ddb9588e651
Authoritative Validate:           #1064 / 36611234115
Repository result:                16 SUCCESS / 1 expected FAILURE
Expected failing job:             release-contract
release-contract total:           60 tests / 11 expected ERROR
Existing release-contract tests:  49 PASS
Expected missing implementation:  scripts.browser_release_candidate
GREEN builder/npm-pack:           NOT STARTED
Consumer/isolation/CI work:       NOT STARTED
~~~

The eleven RED tests cover exactly the approved Step 6 artifact requirements: candidate identity, staged version injection without source mutation, private blocker retention, release allowlist and repository-only exclusions, symlink fail-closed behavior, exact root exports, npm tar path/type safety, exact npm-pack file list, T-801 manifest/evidence identity and hashes, and candidate-only README wording.

The existing release-contract workflow glob intentionally remains unchanged. Because it does not discover test_browser_release_candidate.py directly, a temporary test-only bridge made the new suite authoritative for RED proof. All eleven errors are the missing builder-module boundary; no unrelated test failure is present. The bridge is removed in the tracking checkpoint.

Next explicit gate: **Step 7 — GREEN browser release-candidate builder/npm-pack only**. Create only scripts/browser_release_candidate.py to satisfy this contract and stop before Step 8 clean-consumer/isolation RED work.

## 23. Step 7 checkpoint — GREEN browser release-candidate builder/npm-pack verified

~~~text
GREEN implementation head:       279f4b8758303496e50d5e1d306cdd75f2813e6b
Implementation file:             scripts/browser_release_candidate.py
Temporary GREEN bridge:          2d0e069044ac09d014eb7071450a2027c5485978
Authoritative Validate:          #1067 / 36631327207 — 17/17 SUCCESS
release-contract:                60/60 PASS
Browser artifact contract:       11/11 PASS
Pre-existing release contract:   49/49 PASS
Publication guard:               PASS
npm artifact:                    real npm pack .tgz
T-801 evidence format:           reused
Source package mutation:         NONE
Runtime/package/build/workflow:  unchanged from prior approved steps
Consumer/isolation/CI work:      NOT STARTED
~~~

The Step 6 artifact contract is fully green. The builder performs staged-only candidate version injection, explicit dist JS/declaration copying, repository-only exclusion, symlink fail-closed checks, candidate-only README generation, real npm pack execution with scripts disabled, tar path/type/exact-file validation, and T-801 content-manifest/artifact-evidence hashing.

The temporary GREEN discovery bridge is removed after evidence capture. Permanent workflow discovery remains unchanged until the dedicated T-803 CI step.

Next explicit gate: **Step 8 — RED clean-consumer/isolation tests only**. Stop before Step 9 GREEN clean-consumer generator/verifier.

## 24. Step 8 checkpoint — clean-consumer/isolation RED proven

~~~text
Permanent RED test head:          cb68b44f0afc51b577b2efb0f9e8f574bb155ac5
Temporary discovery bridge:      303e1ca0d65df4eff45b2ac41542f4eeee1638ab
Authoritative Validate:           #1070 / 36640215454
Repository result:                16 SUCCESS / 1 expected FAILURE
Expected failing job:             release-contract
release-contract total:           67 tests / 12 expected ERROR
Existing contract tests:          60 PASS
New consumer methods:             7 RED
Missing Step-9 seams:             6
Permanent main.ts/smoke.mjs:      NOT ADDED
Consumer implementation:         NOT STARTED
~~~

The seven RED methods cover consumer manifest isolation from SurfaceRelay source/file/workspace/link dependencies, root-only source import enforcement, consumer/source directory and symlink isolation, tarball identity validation, installed-package symlink rejection, root declaration presence, and deep-export leakage rejection.

Subtests expand those seven methods into twelve expected error records. Every error is caused by one of the six intentionally absent Step-9 tooling seams; all sixty pre-existing contract tests remain green. This proves the failure boundary before permanent main.ts or smoke.mjs fixtures are introduced.

The temporary discovery bridge is removed after evidence capture; permanent workflow wiring remains deferred.

Next explicit gate: **Step 9 — GREEN clean-consumer generator/verifier only**. Implement only the six proven tooling seams needed to satisfy this contract and stop before dedicated CI wiring.
