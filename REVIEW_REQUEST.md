# M8 Consumer & Release Readiness — Design Approval Handoff

## Scope

- Branch: `docs/m8-consumer-release-readiness-design`
- Base: reassessment head `3ea6904f0131588769464dd16ff68180220ec7bd`
- State: **DESIGN READY FOR APPROVAL / IMPLEMENTATION NOT STARTED**
- Design: `docs/superpowers/specs/2026-09-22-consumer-release-readiness-design.md`

## Design decisions

- first release-candidate package set: `surfacerelay/laravel` + `@surfacerelay/browser-runtime` only;
- Laravel MCP + OpenAPI importer publication deferred;
- coordinated first release train, but exact first public SemVer deferred;
- Composer/npm release-candidate artifacts must be registry-independent and bound to exact revision/hash evidence;
- clean consumers may not use monorepo path/dev-main/deep-import shortcuts;
- browser package uses one curated root ESM API with declarations and no supported deep imports;
- publication/tag/release is a separate later go/no-go gate.

## Proposed decisions

`D-069` through `D-073` are PROPOSED only.

## Proposed tasks

`T-801` through `T-805` are defined but NOT STARTED.

## Explicit non-goals

- no package implementation/build changes;
- no version bump;
- no npm/Packagist publish;
- no tag/GitHub Release;
- no Laravel MCP/OpenAPI importer publication work;
- no new adapter/capability;
- no canonical spec/conformance semantic change;
- no D-026 promotion.

## Review focus

1. Is the first package set minimal enough to prove the core product journey?
2. Does coordinated release-train versioning avoid coupling package versions to Action versions?
3. Does artifact-only consumer proof actually prevent monorepo shortcuts?
4. Is root-only browser API safer than exposing current source modules?
5. Is the consumer verification matrix strong but bounded?
6. Are security/versioning/release docs sufficient without pretending publication exists?
7. Is publication clearly separated from M8 readiness?

## Next gate

If approved, the next gate is **T-801 implementation-plan preparation only**. Implementation does not start from this design handoff.
