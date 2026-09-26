# T-801 — Release-Candidate Artifact Contract External Review Closure

## State

- Task: `T-801 — Release-candidate artifact contract`
- Branch: `feat/t-801-release-candidate-artifact-contract`
- State: **DONE / EXTERNALLY REVIEWED / REVIEW CLOSED / NOT MERGED**
- Implementation base: `da8ea76545afaf434b26a69ba92713db2784c785`
- Final implementation-code head: `c50b9f12cd809107f4d95f553716d9a96df3f038`
- Final reviewed pre-closure head: `f1257054f6d828d5e25fa68cdc23bf334188192e`
- Reviewed-head Validate: `#998` / `36266212552` — **13/13 SUCCESS**
- Release-contract suite: **49/49 PASS**
- External review: **CLOSED — 5 review threads / 0 unresolved; latest Major CodeRabbit-confirmed addressed**
- Plan: `docs/superpowers/plans/2026-09-22-release-candidate-artifact-contract.md`
- Design: `docs/superpowers/specs/2026-09-22-consumer-release-readiness-design.md`

This closure does not authorize merge, decision promotion, T-802, tags, releases, registry publication, or a public version.

## What T-801 implements

T-801 adds a package-neutral release-candidate evidence layer only:

1. prerelease-only SemVer identity validation;
2. full lowercase 40-character Git revision validation;
3. exact-HEAD and clean-worktree preflight;
4. contained `.tmp/release-candidate/<version>/<revision>/` staging paths;
5. deterministic regular-file content manifests;
6. SHA-256 content-manifest/archive evidence;
7. fail-closed symlink/path-escape behavior;
8. static publication-command/credential guardrails;
9. a dedicated `release-contract` CI job.

It does **not** build a real Laravel Composer artifact or browser npm tarball. Those remain T-802/T-803.

## Review diff boundary

From implementation base `da8ea76545afaf434b26a69ba92713db2784c785`, T-801 changes only:

```text
.github/workflows/validate.yml
REVIEW_REQUEST.md
STATUS.md
TASKS.md
docs/ROADMAP.md
docs/superpowers/plans/2026-09-22-release-candidate-artifact-contract.md
requirements-dev.txt
scripts/check_release_guardrails.py
scripts/release_candidate.py
scripts/tests/test_release_candidate_contract.py
scripts/tests/test_release_candidate_guardrails.py
```

Forbidden diff is empty for:

```text
packages/laravel/**
packages/browser-runtime/**
packages/laravel-mcp/**
packages/openapi-importer/**
spec/**
conformance/**
```

Package manifests and `docs/DECISION-REGISTER.md` are unchanged by implementation.

## Verification evidence

### Dedicated release contract

The dedicated `release-contract` job runs:

```bash
python -m unittest discover -s scripts/tests -p 'test_release_candidate*.py' -v
python scripts/check_release_guardrails.py
```

Verified evidence:

```text
49/49 tests PASS
publication guard PASS
```

### Repository validation

The existing `contract` job runs:

```bash
python scripts/validate.py
```

and is green.

### Full matrix

Validate #998:

```text
13/13 SUCCESS

release-contract                     SUCCESS
contract                             SUCCESS
browser                              SUCCESS
openapi-importer                     SUCCESS
php-lint                             SUCCESS
Laravel base compatibility matrix    4/4 SUCCESS
Laravel MCP compatibility matrix     4/4 SUCCESS
```

## Important implementation history

The new CI coverage found two real latent problems while T-801 was being built:

1. the initial publication-guard tokenizer regex was invalid and was corrected before Step 6 closure;
2. the first `release-contract` CI run (#974) exposed a double-escaped SemVer-dot bug that the old 12-job matrix could not observe.

The SemVer fix is isolated in:

```text
b605dd240d0cbf54d1b543ad6a8aecff4a2af649
fix(release): correct prerelease SemVer matching
```

Validate #975 was then **13/13 SUCCESS**, followed by green tracking/audit runs.

## Security / correctness invariants to review

Please review especially:

1. **SemVer boundary**
   - prerelease mandatory;
   - build metadata rejected;
   - stable-looking versions rejected;
   - no public release version is selected by T-801.

2. **Git provenance**
   - exact full lowercase SHA;
   - must equal repository HEAD;
   - dirty tracked or untracked source fails closed;
   - no `--allow-dirty` escape hatch.

3. **Filesystem containment**
   - staging remains under `.tmp/release-candidate/<version>/<revision>/`;
   - absolute/path-escape inputs fail closed;
   - symlinks are rejected;
   - arbitrary caller paths are not recursively deleted.

4. **Determinism**
   - manifest entries use relative POSIX paths and lexical ordering;
   - size and SHA-256 are exact;
   - JSON is sorted, compact, UTF-8 and newline terminated;
   - timestamps, hostnames, usernames, branches and absolute paths are excluded.

5. **Publication guard**
   - executable release surfaces only;
   - Markdown/prose is intentionally excluded;
   - command matching should not create obvious substring false positives;
   - credential identifiers are detected without echoing credential values;
   - `npm pack` remains permitted.

6. **CI authority**
   - `release-contract` has only `contents: read`;
   - checkout uses `persist-credentials: false`;
   - no npm/Packagist registry credentials;
   - no package publishing or real artifact build occurs.

## Deliberately deferred

T-801 does not implement:

- real `surfacerelay/laravel` Composer artifact construction;
- clean Laravel consumer installation;
- PHP/Laravel consumer compatibility proof;
- browser runtime root public API/build/declarations;
- `npm pack` browser artifact;
- consumer README / CHANGELOG / SECURITY policy;
- integrated release bundle;
- registry publication;
- Git tag or GitHub Release;
- first public SemVer selection.

## Decision state

These remain intentionally **PROPOSED**:

```text
D-026
D-069
D-070
D-071
D-072
D-073
```

T-801 tooling evidence alone is not sufficient to promote the M8 product/release decisions.

## Reviewer questions

1. Can any filesystem input escape staging or exploit a symlink/race in a way the tests miss?
2. Is the SemVer prerelease validator correct for the bounded M8 contract?
3. Can manifest/evidence bytes vary due to machine, environment, ordering or path representation?
4. Can publication guard matching miss a prohibited command or create damaging false positives?
5. Can guard output expose credential values?
6. Does the dedicated CI job unintentionally gain publication authority?
7. Has any package/public/canonical/conformance contract leaked into T-801?
8. Is any behavior in T-801 actually package-specific and therefore better deferred to T-802/T-803?

## Review closure rule

T-801 has satisfied the review-closure rule: actionable external findings are addressed/dispositioned, the final reviewed pre-closure head is green, and unresolved review threads are zero.

The next gate is **T-801 merge decision only**. T-802 must not start automatically.
## Active external-review finding — workflow YAML parsing boundary

PR #19 currently has one unresolved Major finding against `scripts/check_release_guardrails.py`: the manual folded-scalar logic does not cover all valid YAML `run` scalar forms, including multi-line plain scalars and explicit indentation indicators.

The finding is accepted as valid. The design amendment is now locked:

- workflow command scanning will consume parsed YAML step `run` string values rather than physical source lines or a growing regex/folding approximation;
- the planned parser is a bounded PyYAML 6.x dependency using a string-preserving, duplicate-key-rejecting loader;
- YAML parse failure, duplicate keys, invalid workflow structure, and non-string `run` values fail closed;
- workflow credential scanning will traverse parsed string keys/values so comments do not create false executable findings;
- package.json, Makefile, and release-script scanning remain unchanged;
- this does not broaden T-801 into shell interpretation, package building, publication, decision promotion, or T-802.

No implementation fix has been made by this amendment and the review thread intentionally remains unresolved.

The next review gate is **RED regression tests for this Major only**, followed later by a separately gated GREEN implementation and exact-head revalidation.
## RED evidence for active YAML parsing finding

The parser-boundary regression suite is now committed at:

```text
3702b9786fe3a171571acd3988021ba7083cf232
test(t-801): expose workflow yaml parsing gaps
```

Validate #992 (`36250401675`) produced the intended isolated RED result:

```text
12 jobs SUCCESS
release-contract FAILURE
49 T-801 tests
6 expected failures
```

The failures are limited to the accepted Major finding:

- multi-line plain scalar `run` misses `npm publish`;
- `>2` folded scalar misses `npm publish`;
- duplicate mapping keys do not fail closed;
- malformed YAML does not fail closed;
- non-string `run` does not fail closed;
- workflow comments can create false publication/credential violations.

Existing `>`, `>-`, and `>+` folded-scalar tests remain green. No production/dependency/workflow implementation was changed by the RED commit.

The inline Major review thread intentionally remains unresolved. The next gate is the bounded GREEN parser implementation and exact-head revalidation only.
## GREEN evidence for workflow YAML parsing finding

The bounded fix is implemented at:

```text
c50b9f12cd809107f4d95f553716d9a96df3f038
fix(t-801): parse workflow yaml before release scanning
```

Exact-head Validate #996 (`36266106312`) is **13/13 SUCCESS**.

```text
release-contract tests: 49/49 PASS
publication guard:       PASS
contract:                SUCCESS
browser:                 SUCCESS
openapi-importer:        SUCCESS
php-lint:                SUCCESS
Laravel/PHP matrices:    SUCCESS
```

The implementation removes the manual folded-scalar scanner and instead parses workflow YAML with a bounded PyYAML 6.x `BaseLoader`-derived loader that preserves scalar strings and rejects duplicate mapping keys. Publication commands are scanned only from parsed step `run` strings. Credential identifiers are scanned from parsed string keys/values, so comments are excluded. Malformed YAML, duplicate keys, structurally invalid workflow sections, and non-string `run` values fail closed.

The implementation diff is limited to the four authorized parser/dependency/test/CI files. No package implementation, canonical spec, conformance, decision promotion, publication authority, or T-802 behavior changed.

The inline Major review thread remains open intentionally for the next explicit **finding disposition / resolution gate**.

## External review closure

```text
Final implementation-code head: c50b9f12cd809107f4d95f553716d9a96df3f038
Final reviewed pre-closure head: f1257054f6d828d5e25fa68cdc23bf334188192e
Validate:                       #998 / 36266212552 — 13/13 SUCCESS
Release-contract:               49/49 PASS
Publication guard:              PASS
Review threads:                 5 total / 0 unresolved
Latest Major finding:           CodeRabbit-confirmed addressed
PR state:                       OPEN / mergeable / NOT MERGED
```

External review is closed. No decision promotion, merge, tag, release, registry publication, public SemVer selection, or T-802 implementation is authorized by this closure.

Next explicit gate: **T-801 merge decision only**.
