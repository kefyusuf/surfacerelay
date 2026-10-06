# Project Status

Current state only; history is in Git, PRs and [the archive](docs/archive/STATUS-through-2026-10-04.md).

## Current work

- [Integration PR #34](https://github.com/kefyusuf/surfacerelay/pull/34) is merged
  with owner authorization. Main includes T-804–T-809.
- T-805 full integration agent source review is complete: three independent
  runtime/browser/readiness scopes found no new actionable production defect.
  Claim corrections clarify session pull versus atomic consumption and current
  documentation/evidence boundaries. See [review and disposition](docs/reviews/integration-pr-34.md).
- Docker Python tooling: 158 tests pass. Canonical validation covers 22 HTMX
  envelope fixtures. Publication guardrails, PHP syntax and documentation links pass.
- Existing runtime evidence: browser 398 tests/typecheck; Laravel 612 tests,
  3272 assertions (2 skipped); Chrome + Docker Filament 16 tests; real HTMX 14 tests.
  T-805 corrected artifacts and both isolated consumers now pass on merged main;
  see [post-merge evidence](docs/reviews/merged-main-readiness.md). Latest main Validate: 18 successful checks.
- T-807a table-state/overlap guards, T-807b receipt secrecy and HTTP session
  isolation, T-809 sorted receipt locks and T-808 result envelope remain verified.
- Both user Composer lockfiles are preserved and excluded. The owner selected
  `0.1.0-alpha.1` for preparation; publication remains NO-GO.

## Remaining gates and limits

- PR #35 is merged. T-901 scope freeze and local alpha candidates are verified;
  see [the preparation plan](docs/releases/0.1.0-alpha.1.md) and
  [candidate evidence](docs/reviews/alpha-0.1.0-alpha.1-candidates.md). Both consumers
  and Docker Python 158 tests pass at the recorded baseline.
- Private local-candidate metadata and fixed-NO-GO tooling need a reviewed
  publication path in T-903; these alpha archives are preparation evidence.
- Fixture-fixed identity does not qualify authentication changes or concurrent
  session writes. Unrelated UI/Livewire concurrency, reentrant HTMX host hooks
  and back/forward-cache restoration remain unqualified, not demonstrated defects.
- Native WebMCP uses flag-enabled Chromium and page callers, not a real agent;
  Chromium drops `consequentialHint`. Server confirmation remains authoritative.
- D-026 and D-069–D-078 remain Proposed; accepted decisions end at D-068.
- Private reporting, registry authority/credentials and final publication approval
  remain separate owner gates.

Next scope after this handoff: T-902 installed-application acceptance. Do not begin
it automatically or publish. See [TASKS](TASKS.md).
