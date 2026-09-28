# T-802 — Laravel Artifact + Clean Consumer Proof — External Review Handoff

## State

- Task: `T-802 — Laravel artifact + clean consumer proof`
- Branch: `feat/t-802-laravel-artifact-clean-consumer`
- State: **DONE / REVIEW HANDOFF**
- Baseline: `main@12de01ae0539a4862adc1acda1cb36b7f4a00fd5`
- Audited implementation head: `bfb6053258a8fe23c9e6a0bc0529fbdfdf85911a`
- Step-10 tracking head: `da39f76378af76a1c64a0ef79de91b06aab1ba8e`
- Step-10 exact-head Validate: `#1036` / `36418100242` — **17/17 SUCCESS**
- Review-handoff head: `d7c50ca50966a8cb3994fed3ea2ac3b3879b6fc6`
- Review-handoff exact-head Validate: `#1038` / `36444627579` — **17/17 SUCCESS**
- Plan: `docs/superpowers/plans/2026-09-27-laravel-release-candidate-clean-consumer.md`
- Design: `docs/superpowers/specs/2026-09-22-consumer-release-readiness-design.md`

This handoff does not authorize merge, T-803, decision promotion, Git tags, GitHub Releases, Packagist publication, or a public SemVer selection.

## What T-802 implements

T-802 adds Laravel release-candidate packaging and clean-consumer evidence only:

1. builds a Composer-consumable `surfacerelay/laravel` ZIP from an exact committed source snapshot;
2. injects the prerelease `version` only into staged `composer.json`, never the source manifest;
3. allowlists package content to `composer.json`, `src/**`, `database/**`, `LICENSE`, and candidate-only `README.md`;
4. rejects symlink/non-regular source entries and unsafe ZIP paths;
5. reuses T-801 content-manifest and artifact-evidence identity/hash contracts;
6. generates an isolated clean-consumer Composer manifest/workspace using only repository type `artifact`;
7. rejects `path`, `dev-main`, `file:`, `workspace:`, package-source and symlink coupling;
8. verifies the exact installed candidate through package metadata and Composer `installed.json`;
9. provides a permanent clean-consumer smoke fixture that boots Laravel and dispatches a safe read-only Action through production `ActionBus`;
10. runs a permanent PHP 8.3/8.4 × Laravel 12/13 consumer matrix, with the real ActionBus smoke only on PHP 8.4 + Laravel 13.

## Review diff boundary

The audited implementation head `bfb6053258a8fe23c9e6a0bc0529fbdfdf85911a` differs from the T-802 baseline only in:

```text
.github/workflows/validate.yml
STATUS.md
TASKS.md
docs/superpowers/plans/2026-09-27-laravel-release-candidate-clean-consumer.md
scripts/fixtures/laravel-clean-consumer/smoke.php
scripts/laravel_release_candidate.py
scripts/tests/test_laravel_release_candidate.py
```

The implementation audit proves zero diff in:

```text
packages/laravel/src/**
packages/laravel/database/**
packages/laravel/composer.json
spec/**
conformance/**
packages/browser-runtime/**
packages/laravel-mcp/**
packages/openapi-importer/**
docs/DECISION-REGISTER.md
```

The source Laravel Composer manifest is byte-identical to baseline.

## Verification evidence

### Whole-task verification

Exact-head Validate #1034 at `86ccdc67ff268014d8948112ac07ad7bee410f87`:

```text
17/17 SUCCESS
```

Required focused checks:

```text
T-802 tooling tests:              20/20 PASS in each consumer leg
release-contract discovery:       49/49 PASS
publication guard:                PASS
python scripts/validate.py:       PASS
```

Step-10 tracking head Validate #1036 at `da39f76378af76a1c64a0ef79de91b06aab1ba8e` is also **17/17 SUCCESS**.

### Clean-consumer matrix

```text
PHP 8.3 + Laravel 12    artifact install + metadata/autoload verify PASS
PHP 8.3 + Laravel 13    artifact install + metadata/autoload verify PASS
PHP 8.4 + Laravel 12    artifact install + metadata/autoload verify PASS
PHP 8.4 + Laravel 13    artifact install + metadata/autoload verify + ActionBus smoke PASS
```

Each leg locks and installs:

```text
surfacerelay/laravel (0.0.0-alpha1)
```

through a Composer `artifact` repository.

The latest-supported leg emits exactly:

```text
SurfaceRelay Laravel clean-consumer smoke: PASS
```

`0.0.0-alpha1` is a non-public CI prerelease used because Composer root require constraints accept a narrower prerelease syntax than the package-neutral T-801 SemVer validator. T-802 does not change T-801's generic SemVer contract and does not select a public release version.

## Security / correctness invariants to review

Please review especially:

1. **Source immutability**
   - staged version injection never mutates `packages/laravel/composer.json`;
   - artifact source is materialized from exact `git archive HEAD`;
   - tests/tooling-generated files cannot become artifact input.

2. **Filesystem and archive containment**
   - package source symlinks/non-regular files fail closed;
   - archive paths cannot be absolute or contain `..`;
   - ZIP entries are deterministic and lexically ordered;
   - package allowlist excludes tests, vendor and repository-only material.

3. **Consumer isolation**
   - SurfaceRelay resolution uses only Composer repository type `artifact`;
   - `path`, `dev-main`, `file:`, `workspace:` and package-source references fail closed;
   - consumer/package-source overlap and symlink-back coupling fail closed.

4. **Installed identity**
   - installed package `composer.json` must match exact package/version;
   - Composer `installed.json` must contain exactly the candidate;
   - `SurfaceRelayServiceProvider` must autoload from the consumer vendor tree.

5. **Runtime authenticity**
   - smoke requires only consumer `vendor/autoload.php`;
   - smoke uses production `ActionBus`, `ActionExecutionStage`, `OutputPolicyStage`, `InMemoryActionRegistry`, `ActionDefinition`, and `ActionCall`;
   - only unrelated validation/authorization/idempotency/confirmation stages are fixture pass-through;
   - the smoke action is read-only, low-risk, portable, non-idempotent and normal-output.

6. **CI authority**
   - consumer jobs use `contents: read`;
   - checkout uses `persist-credentials: false`;
   - no registry credentials are configured;
   - no package publication, tag, or GitHub Release command exists.

## Final validation summary

```text
Audited implementation head:      bfb6053258a8fe23c9e6a0bc0529fbdfdf85911a
Step-10 tracking head:            da39f76378af76a1c64a0ef79de91b06aab1ba8e
Step-10 exact-head Validate:      #1036 / 36418100242 — 17/17 SUCCESS
Review-handoff head:              d7c50ca50966a8cb3994fed3ea2ac3b3879b6fc6
Review-handoff Validate:          #1038 / 36444627579 — 17/17 SUCCESS
Consumer matrix:                  4/4 SUCCESS
Focused T-802 suite:              20/20 PASS per consumer leg
Release-contract discovery:       49/49 PASS
Publication guard:                PASS
Canonical validation:             PASS
Forbidden / unexpected diff:      0 / 0
```

The detailed RED → GREEN chronology and probe-run history remain in `STATUS.md` and the implementation plan.

## Deliberately deferred

T-802 does not:

- publish to Packagist;
- create a tag or GitHub Release;
- select the first public SemVer;
- promote D-026 or D-069 through D-073;
- implement or publish `surfacerelay/laravel-mcp`;
- implement T-803 browser packaging;
- change canonical Action/Binding contracts or conformance;
- add new ActionBus/trust/security semantics.

## Decision state

These remain **PROPOSED** and unchanged:

```text
D-026
D-069
D-070
D-071
D-072
D-073
```

## Reviewer questions

1. Can any source/staging/archive path or symlink bypass the fail-closed containment rules?
2. Can staged version injection alter the source manifest or produce ambiguous Composer identity?
3. Can the consumer resolve SurfaceRelay from anything other than the built artifact?
4. Can package-source coupling slip through via an untested Composer repository/reference form?
5. Is installed identity verification sufficient to prove the candidate, rather than a source/path copy, was consumed?
6. Does the smoke genuinely exercise production ActionBus execution/output-policy flow without introducing a second simplified runtime?
7. Does the four-way matrix over-claim compatibility beyond what it actually installs and verifies?
8. Does the new CI job gain any publication authority or expose credentials?
9. Has any package production code, canonical spec/conformance contract, neighboring package, or decision state leaked into T-802?

## Review closure rule

T-802 is **DONE / REVIEW HANDOFF**, not reviewed or merge-ready.

External review must inspect the current branch against the baseline, disposition every actionable finding, and revalidate the final reviewed head before any merge decision.

Next explicit gate: **T-802 external-review finding disposition / exact-head revalidation only**.
