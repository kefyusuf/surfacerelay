# T-801 — Release-Candidate Artifact Contract Implementation Plan

**Status:** APPROVED / IMPLEMENTATION COMPLETE — REVIEW HANDOFF

> **Execution gate:** This plan must be approved separately before any T-801 implementation begins. Execute only T-801, in the order below, with RED → GREEN evidence and an explicit self-review after every implementation decision.

## Goal

Create the package-neutral release-candidate identity/evidence contract that T-802 and T-803 will reuse, without building either real package artifact yet.

T-801 proves only these shared mechanics:

- explicit non-public prerelease SemVer input;
- exact Git source-revision binding;
- clean-worktree preflight;
- ignored release-candidate staging root;
- deterministic package-content manifest generation;
- SHA-256 content/archive evidence;
- symlink/path-traversal fail-closed behavior;
- static no-publication guardrails;
- CI coverage for the shared contract.

Package-specific Composer archive construction belongs to T-802. Browser build/public-export/npm-pack work belongs to T-803.

## Approved design

`docs/superpowers/specs/2026-09-22-consumer-release-readiness-design.md`

Design approval head: `4f4a52db8a1c9e03e0467d6cbe24bb1f6b646af1`

Design approval Validate: `#960` / `35698730292` — **12/12 SUCCESS**

## Proposed decisions in force during implementation

`D-069` through `D-073` remain **PROPOSED** during T-801 implementation.

T-801 must not promote them merely because tooling exists. Acceptance requires later implementation/consumer evidence and explicit review.

`D-026` remains independently **PROPOSED** and is unrelated to this task.

## Implementation branch

After this plan is separately approved:

```text
feat/t-801-release-candidate-artifact-contract
```

Create it from the exact plan-approved head. Do not start from an older design head or directly from `main` if the approved documentation stack has not yet been integrated.

## Scope boundaries

### Files expected to be created

```text
scripts/release_candidate.py
scripts/check_release_guardrails.py
scripts/tests/test_release_candidate_contract.py
scripts/tests/test_release_candidate_guardrails.py
```

### Files expected to be modified

```text
.github/workflows/validate.yml
STATUS.md
TASKS.md
REVIEW_REQUEST.md
```

### Files intentionally unchanged in T-801

```text
packages/laravel/**
packages/browser-runtime/**
packages/laravel-mcp/**
packages/openapi-importer/**
spec/**
conformance/**
docs/DECISION-REGISTER.md   # D-069..D-073 remain PROPOSED
README.md                   # consumer/release docs belong to T-804
CHANGELOG.md                # T-804
SECURITY.md                 # T-804
```

`.gitignore` already ignores `.tmp/`, so T-801 should use `.tmp/release-candidate/` and should not add another staging-ignore rule unless implementation evidence proves the existing rule insufficient.

## Shared release-candidate identity contract

### Artifact version

T-801 accepts an explicit SemVer 2.0.0 identity with these additional M8 restrictions:

- prerelease component is mandatory;
- build metadata is forbidden;
- numeric core identifiers cannot contain leading zeroes;
- empty identifiers are rejected;
- stable versions such as `0.1.0` are rejected by T-801.

Examples:

```text
0.0.0-m8.1          VALID for M8 evidence
0.4.0-rc.1          VALID for M8 evidence
0.1.0               INVALID: public/stable-looking version
01.0.0-m8.1         INVALID: SemVer leading zero
0.0.0-m8.1+local    INVALID: build metadata excluded
```

T-801 does not decide the real first public SurfaceRelay version.

### Source revision

The source revision must:

- be a full 40-character lowercase hexadecimal Git commit SHA;
- equal `git rev-parse HEAD` in the repository used to build evidence;
- be recorded exactly in every generated manifest/evidence document.

Short SHAs, branch names, tags, uppercase hashes, and synthetic revision strings are rejected.

### Clean source tree

Before evidence creation, the tooling must fail closed when:

```bash
git status --porcelain --untracked-files=all
```

returns any entry.

`.tmp/` output is already ignored and therefore does not make an otherwise clean source checkout dirty.

T-801 must not add an override such as `--allow-dirty`.

## Staging contract

Canonical staging root:

```text
.tmp/release-candidate/<artifact-version>/<source-revision>/
```

Rules:

- all generated T-801 evidence paths must resolve under this root;
- no absolute-path output may be accepted;
- `..` traversal that escapes the staging root fails closed;
- existing non-empty target evidence paths fail rather than being silently replaced;
- T-801 must not recursively delete arbitrary caller-selected paths;
- no artifact/evidence output is committed.

## Deterministic content manifest

`scripts/release_candidate.py` must expose a small importable library plus a CLI.

Proposed internal data model:

```text
ReleaseCandidateIdentity
  artifactVersion
  sourceRevision

ContentEntry
  path
  size
  sha256

PackageContentManifest
  schemaVersion = 1
  packageName
  artifactVersion
  sourceRevision
  files[]

ArtifactEvidence
  schemaVersion = 1
  packageName
  artifactVersion
  sourceRevision
  contentManifestSha256
  archiveFilename
  archiveSize
  archiveSha256
```

Manifest rules:

- only regular files are accepted;
- symlinks anywhere in the staged package tree fail closed;
- paths are relative POSIX paths;
- entries are sorted lexicographically by relative path;
- each entry records byte size and lowercase SHA-256;
- empty package trees fail closed;
- JSON output is UTF-8, deterministic, sorted-key, newline-terminated;
- no timestamp, hostname, username, absolute path, branch name, or environment-specific metadata is included.

The manifest is release-tooling evidence, not a new canonical SurfaceRelay protocol/spec contract.

## Archive evidence

T-801 does not define how Composer or npm archives are built. It only defines how a future archive is evidenced.

Given:

- a valid release-candidate identity;
- a deterministic content-manifest file;
- one regular archive file;

the tooling records:

- archive basename;
- archive byte size;
- SHA-256 of the content-manifest file;
- SHA-256 of the archive;
- exact package/version/source-revision identity.

Archive symlinks, missing files, directories, or paths outside the staging contract are rejected.

## No-publication guardrail

`scripts/check_release_guardrails.py` is a static fail-closed check over executable release surfaces only, not prose documentation.

Scan at minimum:

```text
.github/workflows/*.yml
.github/workflows/*.yaml
Makefile
package.json scripts where present
packages/*/package.json scripts
scripts/release_*.py
```

Do not scan Markdown design/plan files because they must be able to document prohibited commands.

The guard must reject executable publication actions such as:

```text
npm publish
pnpm publish
yarn publish
yarn npm publish
gh release create
git tag
git push ... --tags
```

and registry-publication credential identifiers in CI/release executable surfaces such as:

```text
NPM_TOKEN
NODE_AUTH_TOKEN
PACKAGIST_TOKEN
```

The exact matcher should be token/command-aware enough to avoid accidental substring matches.

`npm pack`, archive creation, hashing, and ordinary package-manager install/update commands remain allowed.

## CLI boundary

Keep the CLI narrow and package-neutral. The plan expects these logical operations; exact argparse spelling may remain implementation-local:

```text
preflight
manifest
evidence
guard
```

T-802/T-803 may import the library directly or call these CLI operations.

T-801 must not:

- invoke `composer archive`;
- invoke `npm pack`;
- compile TypeScript;
- rewrite package manifests;
- install dependencies;
- publish anything.

## Implementation sequence

### Step 0 — implementation preflight

Before writing code:

- confirm the implementation branch is based on the plan-approved head;
- record the exact base/head revision;
- run `python scripts/validate.py`;
- confirm existing Validate is green;
- confirm the expected T-801 package/source forbidden diff is empty.

Expected initial forbidden diff:

```bash
git diff --name-only <base>...HEAD -- \
  packages/laravel \
  packages/browser-runtime \
  packages/laravel-mcp \
  packages/openapi-importer \
  spec \
  conformance
```

Expected: empty.

### Step 1 — write RED identity/preflight tests

Create `scripts/tests/test_release_candidate_contract.py` first.

Required RED cases:

1. valid prerelease SemVer accepted;
2. stable SemVer rejected;
3. SemVer build metadata rejected;
4. malformed/leading-zero SemVer rejected;
5. full lowercase 40-hex revision accepted;
6. short/uppercase/non-hex revision rejected;
7. expected revision mismatch with HEAD rejected;
8. dirty tracked file rejected;
9. dirty untracked file rejected;
10. clean temporary Git repository accepted;
11. stage-root path escape rejected;
12. existing non-empty evidence target rejected.

Use temporary Git repositories in tests. Do not make test correctness depend on the developer's current working tree.

Run:

```bash
python -m unittest scripts.tests.test_release_candidate_contract -v
```

Expected: RED because the release-candidate module does not yet exist or lacks the required behavior.

Commit only the RED tests.

### Step 2 — implement identity + Git/staging preflight

Create `scripts/release_candidate.py` with only the minimum behavior needed to turn Step 1 green.

Implementation requirements:

- Python 3.12 stdlib only;
- explicit typed exceptions rather than `assert` for user/input failures;
- subprocess Git commands use argv arrays with `shell=False`;
- no network access;
- no package-manager calls;
- no automatic cleanup outside `.tmp/release-candidate`.

Run:

```bash
python -m unittest scripts.tests.test_release_candidate_contract -v
python scripts/validate.py
```

Expected: current Step 1 tests GREEN.

Perform explicit self-review before proceeding.

### Step 3 — extend RED tests for content/evidence determinism

Add RED coverage to `test_release_candidate_contract.py` for:

1. regular nested files generate deterministic lexical manifest order;
2. file size and SHA-256 are exact;
3. repeated manifest generation over identical files produces identical bytes;
4. symlinked file rejected;
5. symlinked directory rejected;
6. empty package tree rejected;
7. absolute/environment data absent from output;
8. content-manifest SHA-256 exact;
9. archive SHA-256/size exact;
10. archive directory/missing path rejected;
11. package/version/revision copied exactly into evidence;
12. JSON outputs are newline-terminated and deterministic.

Run and capture expected RED evidence.

### Step 4 — implement manifest/evidence helpers

Extend `scripts/release_candidate.py` minimally to satisfy Step 3.

Do not add package-specific knowledge such as Composer/npm filenames or Laravel/browser package allow-lists.

Run:

```bash
python -m unittest scripts.tests.test_release_candidate_contract -v
python scripts/validate.py
```

Expected: GREEN.

Perform explicit self-review.

### Step 5 — write RED publication-guard tests

Create `scripts/tests/test_release_candidate_guardrails.py`.

Use temporary fixture trees to prove:

- `npm publish` rejected;
- `pnpm publish` rejected;
- Yarn publication rejected;
- `gh release create` rejected;
- `git tag` rejected;
- tag-pushing command rejected;
- NPM/Packagist publication credential identifiers rejected in executable workflow/release surfaces;
- `npm pack` allowed;
- Markdown text containing `npm publish` is not scanned;
- unrelated existing workflow commands allowed;
- matcher reports file + rule without leaking environment values.

Run:

```bash
python -m unittest scripts.tests.test_release_candidate_guardrails -v
```

Expected: RED.

### Step 6 — implement guard + repository check

Create `scripts/check_release_guardrails.py`.

Make its logic importable so tests do not need shell subprocess parsing.

Then run it against the actual repository:

```bash
python scripts/check_release_guardrails.py
```

Expected: PASS on the current M8 repository.

Also run both T-801 test modules.

Perform explicit self-review.

### Step 7 — add dedicated CI job

Modify `.github/workflows/validate.yml` with one new package-neutral job:

```text
release-contract
```

Job requirements:

- Ubuntu runner;
- Python 3.12;
- checkout only plus stdlib tooling;
- run release-candidate unit tests;
- run publication guard;
- do not receive npm/Packagist publication credentials;
- do not build real Laravel/browser package artifacts.

Suggested commands:

```bash
python -m unittest discover -s scripts/tests -p 'test_release_candidate*.py' -v
python scripts/check_release_guardrails.py
```

Existing 12 Validate jobs remain unchanged. With this new job, the expected Validate matrix becomes 13 jobs.

Run local contract checks and push only after they pass.

Perform explicit self-review.

### Step 8 — whole-task verification

Required local verification:

```bash
python -m unittest discover -s scripts/tests -p 'test_release_candidate*.py' -v
python scripts/check_release_guardrails.py
python scripts/validate.py
```

Then run the normal repository/package regressions affected by CI policy as practical and require exact-head GitHub Validate success.

Expected GitHub result after CI-job addition:

```text
13/13 SUCCESS
```

### Step 9 — forbidden-diff audit

At the implementation head, this command must remain empty:

```bash
git diff --name-only <implementation-base>...HEAD -- \
  packages/laravel \
  packages/browser-runtime \
  packages/laravel-mcp \
  packages/openapi-importer \
  spec \
  conformance
```

If it is non-empty, T-801 scope has leaked. Stop and correct before review.

### Step 10 — tracking / external-review handoff

Only after all verification is green:

- mark T-801 `DONE / REVIEW HANDOFF` in `TASKS.md`;
- record exact implementation head and CI run in `STATUS.md`;
- rewrite `REVIEW_REQUEST.md` as the T-801 external-review handoff;
- keep D-069…D-073 PROPOSED unless a separate explicit decision-promotion gate is authorized;
- do not begin T-802.

## Acceptance criteria

T-801 is complete only when all are true:

- prerelease-only SemVer identity is fail-closed and tested;
- exact clean Git revision is mandatory;
- staging output is contained under ignored `.tmp/release-candidate/`;
- deterministic content manifests use regular files only and reject symlinks;
- manifest/evidence JSON contains no machine/time/path noise;
- archive/content hashes are SHA-256 and exact;
- publication commands/credential wiring are statically guarded on executable release surfaces;
- a dedicated CI job continuously verifies the contract;
- package/spec/conformance forbidden diff is empty;
- no real package artifact or public version is introduced;
- no registry/tag/release operation is performed;
- D-026 and D-069…D-073 are not silently promoted;
- exact-head repository validation is green.

## Explicit self-review checklist

After every implementation step, answer:

- Scope alignment: did this remain shared T-801 tooling rather than T-802/T-803 packaging?
- Architecture: did any Action/Binding/trust/projection/conformance semantic change?
- Dependency direction: did release tooling import package implementation code?
- Determinism: can host time/path/environment alter evidence bytes?
- Filesystem safety: can a caller escape staging or follow a symlink?
- Publication safety: did any executable surface gain publish/tag/release capability or credentials?
- Brownfield safety: were existing package manifests/public APIs left unchanged?
- Evidence: are RED/GREEN and exact-head CI claims recorded rather than assumed?

If any answer is unsatisfactory, revise before continuing.

## Deferred to later M8 tasks

### T-802

- real `surfacerelay/laravel` Composer artifact;
- Composer artifact repository consumer install;
- PHP/Laravel compatibility matrix;
- clean Laravel ActionBus smoke.

### T-803

- browser root public API;
- TypeScript emit/declarations;
- package `exports`/files contract;
- `npm pack` tarball;
- clean browser consumer typecheck/bundle/smoke.

### T-804

- consumer README/getting started;
- CHANGELOG;
- SECURITY;
- versioning/compatibility docs;
- release checklist.

### T-805

- integrated artifact build;
- final hashes/evidence bundle;
- external review;
- Publication Go/No-Go handoff.

## Next gate

After this plan is committed and verified, stop.

T-801 implementation is **COMPLETE / REVIEW HANDOFF**. The next explicit gate is **T-801 external review only**. It must not authorize T-802, D-069..D-073 promotion, merge, tag, release, or any publication action automatically.
## External-review amendment — workflow YAML parsing boundary

External review found a valid Major gap after the first folded-scalar fix: the handwritten workflow folding logic covers only a bounded subset of YAML and can miss valid GitHub Actions forms such as multi-line plain scalars and explicit indentation indicators (for example `>2`). This amendment supersedes the Step 7 "stdlib tooling" constraint for the publication guard only.

The fix is intentionally split into a design gate, a RED gate, and a GREEN gate. This section locks the design only; no scanner/test/dependency implementation is authorized by this commit.

### Parsing boundary

- Workflow files under `.github/workflows/*.yml` and `.github/workflows/*.yaml` must be parsed as YAML before publication-command scanning.
- The handwritten `_FOLDED_WORKFLOW_RUN` / manual folding path must be removed rather than extended with more regex forms.
- Use a bounded PyYAML 6.x development dependency (`PyYAML>=6.0.3,<7`) with a custom `BaseLoader`-derived loader so scalar values remain strings and GitHub workflow keys are not changed by YAML 1.1 implicit boolean coercion.
- The loader must reject duplicate mapping keys. YAML parser errors and structurally unusable workflow documents must fail closed as `GuardrailScanError`.
- Command scanning authority comes only from parsed step `run` string values. A `run` value that is present but not a string fails closed.
- Credential-identifier scanning for workflow files must operate over parsed string keys/values rather than raw source text so comments are not treated as executable wiring.
- Non-workflow executable surfaces (package.json scripts, Makefile, `scripts/release_*.py`) retain their current bounded scanners.
- This is YAML semantic normalization only; T-801 does not become a general shell interpreter. Existing CI authority restrictions remain an independent defense.

### Required RED evidence before implementation

Add focused failing tests for at least:

1. a valid multi-line plain-scalar `run` value that YAML resolves to `npm publish`;
2. a folded scalar using an explicit indentation indicator such as `>2`;
3. duplicate workflow mapping keys failing closed;
4. malformed YAML failing closed;
5. a non-string parsed `run` value failing closed;
6. comments containing publication text or credential identifiers not creating workflow violations;
7. the existing folded `>`, `>-`, and `>+` coverage remaining valid.

Only after those RED tests are captured may implementation modify `requirements-dev.txt`, `scripts/check_release_guardrails.py`, the dedicated `release-contract` CI dependency setup, and the focused guardrail tests.

### Stop boundary

After this design amendment, stop. The next explicit gate is **T-801 external-review finding RED tests only**. Do not implement the parser, resolve the review thread, merge PR #19, promote D-069..D-073, or begin T-802 automatically.
### External-review RED checkpoint — complete

The required RED evidence is captured at `3702b9786fe3a171571acd3988021ba7083cf232`.

Validate #992 (`36250401675`) completed **12 SUCCESS / 1 FAILURE**, with only the dedicated `release-contract` job failing. The T-801 suite ran 49 tests and produced six expected failures for the accepted workflow-YAML parsing gap. Existing folded `>`, `>-`, and `>+` cases stayed green.

No production scanner, parser dependency, workflow dependency setup, package, spec, conformance, decision, or publication behavior changed in this RED gate.

The next explicit gate is **GREEN implementation for this Major finding only**. It may modify only the already-authorized parser dependency/setup, publication-guard implementation, focused tests as needed for correctness, and tracking evidence. It must not begin T-802 or resolve the external-review thread before exact-head green evidence exists.
### External-review GREEN checkpoint — verified

GREEN implementation is complete at `c50b9f12cd809107f4d95f553716d9a96df3f038`.

Validate #996 (`36266106312`) completed **13/13 SUCCESS**. The dedicated T-801 suite is **49/49 PASS** and the publication guard passes.

The parser implementation follows the locked amendment: a bounded PyYAML 6.x dependency, `BaseLoader`-derived strict duplicate-key-rejecting loading, parsed workflow `run` command scanning, parsed credential key/value scanning, fail-closed malformed/structurally invalid workflow handling, and removal of handwritten YAML-folding logic.

The focused non-string `run` regression fixture uses a sequence value rather than numeric `123`, because `BaseLoader` intentionally preserves scalar values as strings. This keeps the test aligned with the selected parser semantics while still proving non-string parsed values fail closed.

Next explicit gate: **external-review finding disposition / thread resolution only**. Do not begin T-802, decision promotion, merge, tag, release, or publication automatically.
