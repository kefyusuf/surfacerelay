# T-802 — Laravel Release-Candidate Artifact + Clean Consumer Proof

Status: APPROVED / IMPLEMENTATION NOT STARTED
Date: 2026-09-27
Task: T-802
Baseline: main@138d27f21c92af2c26d62f32dd217724f56efacf
Baseline validation: Validate #1002 / 36273346790 — 13/13 SUCCESS
Design: docs/superpowers/specs/2026-09-22-consumer-release-readiness-design.md
Predecessor: T-801 — DONE / REVIEWED / MERGED / MAIN REVALIDATED

## 1. Goal

Prove that `surfacerelay/laravel` can be built as a registry-independent Composer release-candidate archive and consumed by isolated Laravel projects without monorepo path repositories, `dev-main`, source-relative package coupling, or registry publication.

T-802 is packaging and downstream-consumer evidence only. It must not change SurfaceRelay Action/Binding/trust/projection/conformance semantics.

## 2. Exact scope

T-802 must produce all of the following from one explicit prerelease artifact version and one exact Git revision:

1. a staged `surfacerelay/laravel` package tree;
2. a Composer-consumable ZIP archive whose root contains `composer.json`;
3. T-801 package-content manifest + artifact evidence bound to exact package/version/revision;
4. clean Composer artifact-repository installation proof for:
   - PHP 8.3 + Laravel 12;
   - PHP 8.3 + Laravel 13;
   - PHP 8.4 + Laravel 12;
   - PHP 8.4 + Laravel 13;
5. one PHP 8.4 + Laravel 13 application smoke that loads the artifact and dispatches a safe read-only action through the production `ActionBus`;
6. static isolation proof that no SurfaceRelay source/path/dev-main shortcut is used.

## 3. Non-goals / hard stop boundaries

T-802 must not:

- publish to Packagist;
- register a Packagist package;
- create a Git tag or GitHub Release;
- choose the first public SemVer;
- add registry credentials;
- implement T-803 browser packaging;
- publish or modify `surfacerelay/laravel-mcp`;
- publish or modify `@surfacerelay/openapi-importer`;
- change canonical `spec/**` or `conformance/**`;
- change Action Definition, RuntimeBinding, ActionBus, trust-control, authorization, confirmation, idempotency, audit, output-policy, Livewire, Filament, HTMX, MCP or OpenAPI semantics;
- commit a `version` field to `packages/laravel/composer.json`;
- use a Composer `path` repository, `dev-main`, symlink, workspace/file link, or direct `packages/laravel` autoload in consumer proof;
- write package-specific getting-started/release documentation owned by T-804;
- start T-803/T-804/T-805 automatically.

D-026 and D-069..D-073 remain PROPOSED during T-802 unless a separate explicit decision-promotion gate is authorized.

## 4. Planned file boundary

Expected implementation files:

```text
scripts/laravel_release_candidate.py
scripts/tests/test_laravel_release_candidate.py
scripts/fixtures/laravel-clean-consumer/smoke.php
.github/workflows/validate.yml
STATUS.md
TASKS.md
REVIEW_REQUEST.md
docs/superpowers/plans/2026-09-27-laravel-release-candidate-clean-consumer.md
```

T-802 should require **no production change under `packages/laravel/src/**`**, no migration change, and no committed source-manifest version change.

If implementation discovers that package production code or canonical semantics must change for clean consumption, stop and open a separate scope/decision gate rather than silently expanding T-802.

## 5. Artifact content contract

### 5.1 Package root

The staged Composer package contains only reviewed release-package material:

```text
composer.json
src/**
database/**
LICENSE
README.md
```

Rules:

- `composer.json` is copied from `packages/laravel/composer.json` and receives only a staged build-time `version` field equal to the explicit T-801 prerelease artifact version.
- The source `packages/laravel/composer.json` must remain byte-for-byte unchanged by the build.
- `src/**` and `database/**` are copied as regular files only.
- `tests/**`, `phpunit.xml`, `vendor/**`, Git metadata, temporary files and repository-only fixtures are excluded.
- Root `LICENSE` is copied verbatim.
- Because no package-specific Laravel README exists yet and T-804 owns consumer documentation, T-802 generates a deterministic minimal candidate `README.md` containing only package identity, artifact version, exact source revision, and an explicit “not a public release” notice. It must not invent installation/support promises.

### 5.2 ZIP archive

Use Python ZIP construction rather than `composer archive` so the package allow-list remains explicit and testable.

Requirements:

- archive root contains `composer.json`, not an extra wrapper directory;
- lexical entry ordering;
- normalized regular-file permissions;
- fixed ZIP timestamps so repeated builds from identical staged content are byte-stable on the same supported build environment;
- no symlink entries;
- no absolute paths or `..` traversal;
- archive filename is deterministic and package/version derived;
- byte-for-byte cross-OS reproducibility is not claimed.

### 5.3 T-801 evidence reuse

T-802 must reuse T-801 helpers rather than inventing a second evidence format:

- `validate_artifact_version()`;
- `validate_source_revision()`;
- `preflight_repository()`;
- `release_candidate_root()`;
- `resolve_staging_path()`;
- `build_content_manifest()`;
- `build_artifact_evidence()`;
- `serialize_evidence_json()`.

Package identity is exactly `surfacerelay/laravel`.

## 6. Clean consumer contract

### 6.1 Temporary project

Consumer verification creates a fresh temporary directory outside `packages/laravel` and copies only the committed consumer smoke fixture needed for execution.

Generated consumer `composer.json` must:

- use a Composer repository with exact `type: artifact`;
- point only to the generated archive directory for SurfaceRelay;
- require exact `surfacerelay/laravel:<artifact-version>`;
- require the selected `laravel/framework` major;
- contain no Composer `path` repository;
- contain no `dev-main`;
- contain no `packages/laravel` reference.

Packagist remains available only for ordinary third-party dependencies such as Laravel itself. SurfaceRelay resolution must come from the local artifact repository.

### 6.2 Four-way install matrix

Every matrix leg must prove:

- Composer dependency resolution succeeds;
- `surfacerelay/laravel` is installed under consumer `vendor/**`;
- Composer reports the exact requested prerelease version;
- package classes autoload from the installed archive;
- `SurfaceRelayServiceProvider` exists;
- no source/path link points back to the monorepo package.

Matrix:

| PHP | Laravel |
| --- | --- |
| 8.3 | ^12.0 |
| 8.3 | ^13.0 |
| 8.4 | ^12.0 |
| 8.4 | ^13.0 |

### 6.3 Latest-supported ActionBus smoke

Only the PHP 8.4 + Laravel 13 matrix leg runs the full smoke.

The smoke must:

1. load Composer autoload from the temporary consumer;
2. bootstrap a minimal `Illuminate\Foundation\Application`;
3. explicitly register `SurfaceRelayServiceProvider` (package discovery is not required for this bounded proof);
4. create one `ActionDefinition`:
   - id: `consumer.health.read`;
   - effect: `read`;
   - risk: `low`;
   - scope: `portable`;
   - idempotency: `none`;
   - output sensitivity: `normal`;
   - trusted application output;
   - no trusted-context requirements;
5. register it in production `InMemoryActionRegistry`;
6. build the production `ActionBus`;
7. use production `ActionExecutionStage` with a fixture `ActionExecutor`;
8. use production `OutputPolicyStage`;
9. use fixture pass-through handlers only for unrelated validation/authorization/idempotency/confirmation stages;
10. dispatch a real production `ActionCall`;
11. assert the `ActionPipelineOutcome` is completed and contains the expected execution output.

This fixture is test harness wiring around the real package runtime. It must never replace or bypass `ActionBus`, invoke an agent-only business endpoint, or create a second simplified SurfaceRelay runtime.

## 7. Isolation guard

Before Composer execution, statically inspect generated consumer configuration/source and fail if any of these appear:

```text
packages/laravel
"type": "path"
dev-main
file:
workspace:
../packages
```

Also reject symlinks in the consumer project that resolve into the repository package tree.

This isolation proof is separate from the T-801 publication guard.

## 8. Implementation sequence

### Step 1 — baseline + branch

After this plan is separately approved:

- create `feat/t-802-laravel-artifact-clean-consumer` from the then-current green `main`;
- record exact baseline SHA and Validate run;
- confirm T-801 remains green;
- do not alter package production code.

### Step 2 — RED artifact-content tests

Create `scripts/tests/test_laravel_release_candidate.py` with failing tests for:

1. exact package name/version/revision;
2. staged `composer.json` gets exact version;
3. source `packages/laravel/composer.json` remains unchanged;
4. only `composer.json`, `src/**`, `database/**`, `LICENSE`, `README.md` are staged;
5. `tests/**`, `phpunit.xml`, `vendor/**` excluded;
6. source symlink/non-regular entry rejected;
7. generated README contains no public-release claim;
8. ZIP root/entry ordering/path safety;
9. repeated same-input ZIP build is byte-identical in the test environment;
10. T-801 manifest/evidence identity and hashes match the archive.

Capture expected RED evidence.

### Step 3 — GREEN artifact builder

Create `scripts/laravel_release_candidate.py` minimally to satisfy Step 2.

The implementation must import/reuse T-801 release helpers and must not modify source package files.

Run:

```bash
python -m unittest scripts.tests.test_laravel_release_candidate -v
python scripts/check_release_guardrails.py
python scripts/validate.py
```

Perform explicit self-review before proceeding.

### Step 4 — RED consumer/isolation tests

Extend the T-802 tests with failing cases proving:

- artifact repository type is `artifact`;
- exact candidate version required;
- selected Laravel major required;
- path/dev-main/file/workspace/source references rejected;
- consumer symlink back into package source rejected;
- missing/wrong archive identity rejected;
- consumer directory is not the package source tree.

Capture expected RED evidence.

### Step 5 — GREEN clean-consumer generator/verifier

Extend `scripts/laravel_release_candidate.py` with bounded consumer workspace generation and Composer verification.

Do not make network/package publication calls other than ordinary Composer dependency resolution.

Focused local verification may use the developer's installed PHP/Composer version; the authoritative 4-way compatibility evidence is CI.

### Step 6 — RED real ActionBus consumer smoke

Create `scripts/fixtures/laravel-clean-consumer/smoke.php` first and prove it fails before the required artifact-installed runtime is available.

The fixture must import only installed package namespaces and Laravel dependencies from consumer `vendor/autoload.php`.

No `require`/autoload path may point into the repository.

### Step 7 — GREEN latest-supported smoke

Wire the smoke exactly as Section 6.3 specifies.

Required observable result:

```text
SurfaceRelay Laravel clean-consumer smoke: PASS
```

The output must not include secrets, absolute repository paths, or artifact credentials.

### Step 8 — add dedicated CI matrix

Modify `.github/workflows/validate.yml` with one matrix job family, suggested name:

```text
laravel-release-consumer
```

Requirements:

- Ubuntu;
- checkout with `persist-credentials: false`;
- `contents: read` only;
- Python 3.12;
- PHP 8.3/8.4 matrix;
- Laravel 12/13 matrix;
- Composer available from the PHP setup;
- install `requirements-dev.txt`;
- use a fixed non-public CI prerelease identity such as `0.0.0-t802-ci.1`;
- source revision is exact `git rev-parse HEAD`;
- build the candidate from the exact checkout;
- run artifact-repository consumer install in all four legs;
- run ActionBus smoke only on PHP 8.4 + Laravel 13;
- no registry credentials;
- no `composer config repositories.* path`;
- no package publication.

The existing 13 jobs remain unchanged. With the 4 new matrix legs, expected Validate total becomes **17 jobs**.

### Step 9 — whole-task verification

Required focused verification:

```bash
python -m unittest scripts.tests.test_laravel_release_candidate -v
python -m unittest discover -s scripts/tests -p 'test_release_candidate*.py' -v
python scripts/check_release_guardrails.py
python scripts/validate.py
```

Then require exact-head GitHub Validate:

```text
17/17 SUCCESS
```

and explicitly inspect all four `laravel-release-consumer` jobs.

### Step 10 — forbidden-diff / source-mutation audit

At implementation head:

- `packages/laravel/src/**` diff must be empty;
- `packages/laravel/database/**` diff must be empty;
- source `packages/laravel/composer.json` diff must be empty unless a separately approved packaging blocker is discovered;
- `spec/**` diff empty;
- `conformance/**` diff empty;
- `packages/browser-runtime/**` diff empty;
- `packages/laravel-mcp/**` diff empty;
- `packages/openapi-importer/**` diff empty;
- no tag/release/publication/credential wiring exists.

If any forbidden diff is non-empty, stop and correct or reopen scope.

### Step 11 — tracking / external-review handoff

Only after all tests, matrix legs and audits are green:

- mark T-802 `DONE / REVIEW HANDOFF`;
- record exact build/CI evidence in `STATUS.md`;
- update `TASKS.md`;
- rewrite `REVIEW_REQUEST.md` for T-802 external review;
- keep D-026 and D-069..D-073 PROPOSED;
- do not begin T-803.

## 9. Acceptance criteria

T-802 is complete only when all are true:

- a Composer-consumable `surfacerelay/laravel` ZIP is built from exact clean source;
- staged manifest has exact prerelease version without mutating source `composer.json`;
- artifact contents are explicitly allowlisted and symlink-safe;
- T-801 content/evidence format is reused;
- Composer artifact-repository install succeeds for all 4 PHP/Laravel combinations;
- SurfaceRelay is resolved from the archive, never a path/dev-main/source link;
- latest PHP/Laravel consumer boots Laravel and completes one safe action through production `ActionBus`;
- no second simplified runtime is created;
- dedicated CI evidence is exact-head green;
- package production source/canonical spec/conformance/browser/MCP/OpenAPI boundaries remain unchanged;
- no registry publication/tag/release/public SemVer action occurs.

## 10. Explicit self-review checklist

After every implementation step:

- **Scope:** is this still Laravel packaging/consumer proof only?
- **Artifact identity:** are package/version/revision exact and T-801-bound?
- **Source mutation:** did staged version injection avoid changing source `composer.json`?
- **Package contents:** did tests/dev/vendor/repository files stay out?
- **Filesystem safety:** can a symlink/path escape enter stage/archive/consumer?
- **Consumer isolation:** can Composer silently fall back to path/dev-main/source coupling?
- **Runtime authenticity:** does the smoke dispatch production `ActionBus` rather than a second runtime?
- **Compatibility:** are claims limited to the four tested matrix legs?
- **Publication safety:** are registry credentials/publish/tag/release still absent?
- **Architecture:** are Action/Binding/trust/projection/conformance semantics unchanged?
- **Evidence:** are RED/GREEN, hashes and exact-head CI recorded rather than assumed?

## 11. Stop boundary

This plan-preparation gate does not authorize implementation.

After this plan is committed and exact-head validation is green, stop.

The next explicit gate is **T-802 implementation-plan approval only**. Approval may then authorize creation of the T-802 feature branch, but implementation must not begin from this plan-preparation commit automatically.

## 12. Plan approval checkpoint

Approved against:

```text
Plan-preparation head:            13fa25393c179257d1de724752f2dec49173653f
Plan-preparation Validate:        #1003 / 36277458713 — 13/13 SUCCESS
Approval scope:                   T-802 plan only
Implementation branch:           NOT CREATED
Implementation:                  NOT STARTED
D-026:                            PROPOSED / unchanged
D-069..D-073:                    PROPOSED / unchanged
T-803..T-805:                    NOT STARTED
Publish/tag/release:              NOT AUTHORIZED
```

The plan is approved as the implementation contract for T-802. Approval does not itself create the feature branch or authorize Step 2 RED work in this commit.

Next explicit gate: **T-802 Step 1 — baseline + feature branch only**. After that gate is separately completed, stop before Step 2 RED artifact-content tests.

## 13. Step 1 checkpoint — baseline + feature branch complete

```text
Implementation baseline:          main@12de01ae0539a4862adc1acda1cb36b7f4a00fd5
Baseline Validate:                #1004 / 36279906163 — 13/13 SUCCESS
Feature branch:                   feat/t-802-laravel-artifact-clean-consumer
Production implementation:       NOT STARTED
RED artifact-content tests:       NOT STARTED
```

The feature branch was created from the exact green baseline SHA. Step 1 introduced no production, test, CI, package, spec, conformance, artifact or consumer behavior.

Next explicit gate: **Step 2 — RED artifact-content tests only**. Stop before Step 3 GREEN artifact-builder implementation.

## 14. Step 2 checkpoint — artifact-content RED proven

```text
RED test head:                    eb727e294420f2b44efa663ed4c806ca9d6e953a
Focused test:                     scripts/tests/test_laravel_release_candidate.py
Focused command:                  python -m unittest scripts.tests.test_laravel_release_candidate -v
Focused result:                   10 tests / 10 ERROR
Expected missing implementation:  scripts.laravel_release_candidate
Repository Validate:              #1007 / 36299355134 — 13/13 SUCCESS
Production builder:               NOT STARTED
CI discovery/matrix change:       NONE
```

The RED suite covers exactly the ten Step 2 artifact-content requirements. All ten currently fail at the missing T-802 builder-module boundary, while the pre-existing repository regression remains green.

The current `release-contract` workflow discovery remains intentionally unchanged in Step 2; the focused T-802 RED command is separate until later CI wiring. No GREEN implementation is present in this checkpoint.

Next explicit gate: **Step 3 — GREEN artifact builder only**. Stop before Step 4 consumer/isolation RED work.

## 15. Step 3 checkpoint — GREEN artifact builder verified

```text
GREEN implementation head:       331490b4942e257749d1e104f20365b5d4583cc7
Implementation file:             scripts/laravel_release_candidate.py
Focused artifact suite:          10/10 PASS
Repository Validate:             #1009 / 36315361027 — 13/13 SUCCESS
Publication guard:               PASS
Step 3 implementation diff:      builder file only
Package production diff:         NONE
Consumer/isolation implementation:NONE
Dedicated CI matrix:             NONE
```

The builder satisfies the ten Step 2 artifact-content contracts: staged-only version injection, explicit package allowlist, source mutation avoidance, symlink fail-closed behavior, candidate-only README, safe deterministic ZIP construction, and T-801 manifest/evidence reuse.

Focused T-802 tests are still outside the existing Actions discovery pattern; the 10/10 result is from isolated scratch execution of the committed builder/test contract. GitHub Actions independently proves the unchanged repository regression, `python scripts/validate.py`, and publication guard at the implementation head.

Next explicit gate: **Step 4 — RED consumer/isolation tests only**. Stop before Step 5 GREEN consumer generator/verifier.

## 16. Step 4 checkpoint — consumer/isolation RED proven

```text
RED test head:                    dc341335f4db17ed04ab776fbda6d684643a6dbe
Temporary CI bridge:              b20ad92dd3deaac6b4de2148267e132b4e52a18f
Test-scope correction:            ad834fde68175700b6533c39c6d832f6cdfdc065
Authoritative Validate:            #1013 / 36317673759 — 12 SUCCESS / 1 expected FAILURE
Failing job:                      release-contract only
release-contract total:           66 tests / 11 expected error records
Existing T-802 artifact tests:    10/10 PASS
New consumer methods:             7 RED
Consumer implementation:         NONE
```

The seven new tests cover artifact-repository type, exact candidate version, selected Laravel major, path/dev-main/file/workspace/source coupling rejection, source-symlink rejection, artifact identity validation, and consumer-directory isolation.

One pre-existing Step-3 test incorrectly retained file paths beyond the temporary-directory lifetime. The test-only lifetime bug was corrected before the authoritative RED rerun so the signal is isolated: existing artifact tests pass, and all remaining failures are the intentionally missing Step-5 consumer tooling seams.

The temporary discovery bridge is removed after this checkpoint. Permanent workflow discovery/matrix wiring remains Step 8.

Next explicit gate: **Step 5 — GREEN clean-consumer generator/verifier only**. Stop before Step 6 ActionBus smoke RED work.

## 17. Step 5 checkpoint — clean-consumer generator/verifier GREEN verified

```text
Initial consumer seams:           9aad5add6299b70528fcb4e4bf0d59347b9dd605
Initial GREEN Validate:           #1016 / 36324499591 — 13/13 SUCCESS
Initial release-contract:         66/66 PASS
Workspace/verifier RED:           a27c25c83976521cde6e2ffee916e969f136f2be
Workspace/verifier RED Validate:  #1017 / 36324666762 — 12 SUCCESS / 1 expected FAILURE
Workspace/verifier RED detail:    69 tests / 3 expected errors
Final Step 5 implementation:      80f0fef6e4c4c2e41a3d338438d9bf989f4a2962
Final Step 5 Validate:            #1018 / 36324731032 — 13/13 SUCCESS
Final release-contract:           69/69 PASS
Publication guard:                PASS
Composer/network execution:       NONE
```

Step 5 now provides deterministic consumer composer-manifest generation, source-coupling validation, archive identity validation, consumer-directory/symlink isolation, clean workspace creation, and post-Composer installed-metadata verification.

No Composer subprocess or network dependency resolution runs in Step 5. Step 8 owns the authoritative four-way Composer installation matrix using these seams. No ActionBus smoke code exists yet.

The temporary GREEN discovery bridge is removed after this checkpoint; permanent Actions wiring remains Step 8.

Next explicit gate: **Step 6 — RED real ActionBus consumer smoke only**. Stop before Step 7 GREEN smoke implementation.

## 18. Step 6 checkpoint — real ActionBus consumer smoke RED proven

```text
Permanent smoke fixture:          7c97ec9448646ab32af3e2a42bdc6635077e954a
Authoritative RED bridge:         e95caa402f4c961aa5116fac09483cb2ff6caf30
Authoritative Validate:           #1023 / 36333900561 — 12 SUCCESS / 1 expected FAILURE
Failing job:                      PHP 8.4 + Illuminate 13 only
Target job result:                596 tests / 3171 assertions / 1 failure
Smoke exit code:                  66
Smoke stderr:                     clean consumer vendor/autoload.php is missing
Repository source fallback:       NONE
Step 7 implementation:           NOT STARTED
Dedicated CI matrix:              NOT STARTED
```

The permanent smoke fixture requires only `<consumer-root>/vendor/autoload.php`; it contains no repository `require`, no `packages/laravel` fallback, and no source-relative package autoload. After autoload it is designed to bootstrap minimal Laravel, explicitly register `SurfaceRelayServiceProvider`, register one safe read-only Action, and dispatch the production ActionBus using production execution/output-policy stages.

The temporary PHPUnit bridge is test-only RED instrumentation and is removed after this checkpoint. Earlier probe runs that only exercised bridge-targeting mistakes are not Step-6 evidence; Validate #1023 is the authoritative RED run.

Next explicit gate: **Step 7 — GREEN latest-supported ActionBus smoke only**. Stop before Step 8 dedicated CI matrix.
