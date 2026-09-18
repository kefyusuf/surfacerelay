# T-702 External Review Request — Adapter Author Guide

## Review status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `main`
- **Task:** `T-702 — Adapter author guide`
- **State:** **IMPLEMENTED / MERGED / MAIN REVALIDATED / REVIEW PENDING**
- **Design:** `docs/superpowers/specs/2026-09-16-adapter-author-guide-design.md`
- **Implementation plan:** `docs/superpowers/plans/2026-09-16-adapter-author-guide.md`
- **Verified documentation implementation head:** `04e69ecb8a4d752732f2ab85eaf0f14798944e68`
- **Implementation-head validate:** `#821 / 35150202622` — **7/7 SUCCESS**
- **Merged main head:** `ffd97c9a6608e37a4042d899e079ab751ecc5272`
- **Post-merge main validate:** `#825 / 35299100221` — **7/7 SUCCESS**
- **D-059 / D-060 / D-061:** **ACCEPTED** and unchanged
- **D-026 / D-062:** **PROPOSED** and unchanged
- **T-703 / T-704:** **NOT STARTED**

T-702 is intentionally documentation-only. The implementation has been fast-forwarded to `main` by explicit user choice and revalidated there, but the task is not review-closed and no decision promotion is implied.

## Primary review surface

Review these files first:

1. `docs/adapters/README.md`
2. `docs/adapters/author-guide.md`
3. `docs/adapters/security.md`
4. `docs/adapters/conformance.md`
5. `README.md`
6. `TASKS.md`
7. `STATUS.md`

The design and plan are the review contracts for scope:

- `docs/superpowers/specs/2026-09-16-adapter-author-guide-design.md`
- `docs/superpowers/plans/2026-09-16-adapter-author-guide.md`

## Intended documentation boundary

The guide should explain existing SurfaceRelay architecture without becoming another specification.

It classifies adapter work into three author-facing responsibility categories:

```text
definition / import
runtime / binding
surface / projection
```

Those categories must not become a universal `Adapter` interface, plugin loader, shared lifecycle abstraction, or cross-role registry.

The implementation should preserve these existing invariants:

- explicit exposure;
- discovery permission is not invocation authorization;
- caller input/client metadata cannot manufacture trusted actor, tenant, record, selection, confirmation, or binding authority;
- RuntimeBindings resolve exact issued Action versions and exact issued targets;
- stale/unknown/ambiguous/unsupported states fail closed rather than silently retargeting or guessing;
- driver-owned host mapping remains deterministic and bounded;
- cancellation guarantees stop at the actual framework dispatch frontier;
- profile/capability claims follow D-059 through D-061;
- the runner owns canonical scenario selection, applicability, and verdicts;
- harnesses emit bounded raw observations only;
- compatibility claims are bounded to profile, target, capabilities, repository/adapter revision, and runner revision;
- Livewire/HTMX examples distinguish portable invariants from framework-specific techniques;
- D-026 is not promoted into a global error enum;
- D-062 remains proposed during implementation/review.

## Verification evidence

Implementation-head GitHub Actions run:

```text
Head:                         04e69ecb8a4d752732f2ab85eaf0f14798944e68
Validate run:                 #821 / 35150202622 — 7/7 SUCCESS
Contract / scripts/validate:  PASS
PHP matrix:                   4/4 PASS
PHP lint:                     PASS
Browser typecheck:            PASS
Browser Vitest:               20 files / 328/328 PASS
Python conformance tests:     47/47 PASS on CPython 3.12.14
Harness build:                PASS
Canonical runtime matrix:     7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR
```

The canonical matrix remained:

```text
N/A  browser/htmx BIND-COMPONENT-STALE
PASS browser/htmx BIND-EXACT-TARGET-EXECUTES
PASS browser/htmx BIND-EXPIRED-NOT-EXECUTABLE
PASS browser/htmx BIND-NO-SILENT-RETARGET
PASS browser/livewire BIND-COMPONENT-STALE
PASS browser/livewire BIND-EXACT-TARGET-EXECUTES
PASS browser/livewire BIND-EXPIRED-NOT-EXECUTABLE
PASS browser/livewire BIND-NO-SILENT-RETARGET
```

## Scope audit

The pre-merge `main..feat/t-702-adapter-author-guide` diff contained only the prior T-702 design/tracking artifacts, implementation plan, four adapter documents, root README navigation, and review/tracking updates. That exact feature head was fast-forwarded to `main`.

T-702 introduces no semantic change under:

```text
packages/browser-runtime/src/**
packages/laravel/src/**
spec/0.1/**
conformance/targets/**
scripts/conformance_model.py
scripts/run_conformance.py
```

No new dependency, profile, capability, canonical scenario, runtime driver behavior, projection implementation, OpenAPI importer, or MCP transport/projection implementation is part of this task.

## Review questions

Please review specifically:

1. Does any guide prose accidentally create a second specification or override canonical artifacts?
2. Are definition/import, runtime/binding, and surface/projection responsibilities clearly separated without creating a universal adapter abstraction?
3. Is trusted context protected from caller payload/client metadata authority?
4. Are explicit exposure and discovery-vs-invocation authorization kept separate?
5. Are exact-target, no-silent-retarget, lifecycle/expiry, deterministic mapping, and fail-closed invariants preserved?
6. Do cancellation statements avoid claiming rollback/server cancellation beyond the supported dispatch frontier?
7. Are D-059/D-060/D-061 represented exactly, especially runner-owned selection/applicability/verdicts?
8. Are harnesses described as bounded raw-observation producers only?
9. Are capability advertisements and compatibility claims truthful and evidence-bounded?
10. Are Livewire and HTMX techniques clearly examples rather than a universal host shape?
11. Do D-026 and D-062 remain **PROPOSED** rather than being silently promoted through documentation?
12. Do T-703 Laravel MCP projection and T-704 OpenAPI import remain unimplemented?
13. Did T-702 avoid production code, canonical schema, target-manifest, and conformance-verdict semantic changes?

## Required outcome before closure

T-702 should remain **IMPLEMENTED / REVIEW PENDING** until external review is complete and any actionable findings are resolved with fresh exact-head verification.

Do not promote D-062, mark T-702 reviewed/closed, or begin T-703/T-704 automatically from this review request.
