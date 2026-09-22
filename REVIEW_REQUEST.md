# T-801 Release-Candidate Artifact Contract — Plan Approval Handoff

## Scope

- Branch: `docs/t-801-release-candidate-artifact-contract-plan`
- M8 design approval head: `4f4a52db8a1c9e03e0467d6cbe24bb1f6b646af1`
- Design Validate: `#960` / `35698730292` — **12/12 SUCCESS**
- Plan: `docs/superpowers/plans/2026-09-22-release-candidate-artifact-contract.md`
- State: **PLAN READY FOR APPROVAL / IMPLEMENTATION NOT STARTED**

## T-801 implementation boundary

T-801 may later implement only:

- prerelease SemVer + exact clean Git revision preflight;
- `.tmp/release-candidate` staging containment;
- deterministic regular-file content manifests;
- SHA-256 content/archive evidence;
- symlink/path-escape fail-closed behavior;
- no-publication static guardrails;
- one dedicated package-neutral CI contract job.

## Deferred

- real Laravel Composer artifact: T-802;
- browser build/root API/npm tarball: T-803;
- release-facing README/CHANGELOG/SECURITY/versioning docs: T-804;
- integrated release-readiness handoff: T-805.

## Explicit non-goals

- no package source or package metadata changes in T-801;
- no canonical spec/conformance changes;
- no public version selection;
- no npm/Packagist publication;
- no tag/GitHub Release;
- no D-026 promotion;
- no D-069..D-073 promotion from this plan.

## Review focus

1. Is T-801 package-neutral enough to avoid swallowing T-802/T-803?
2. Is prerelease-only SemVer plus exact clean revision sufficient for evidence identity?
3. Are staging/path/symlink rules fail-closed?
4. Is content/archive evidence deterministic and free of host/time metadata?
5. Are publication guardrails strong without scanning prose documentation?
6. Does the dedicated CI job avoid registry credentials and real package publishing/building?
7. Is the forbidden package/spec/conformance diff explicit?

## Next gate

If this plan is approved, the next gate may start **T-801 implementation execution only** on `feat/t-801-release-candidate-artifact-contract`.

T-802 and publication remain unauthorized.
