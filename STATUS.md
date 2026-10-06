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
  see [post-merge evidence](docs/reviews/merged-main-readiness.md). Main CI: 20 successful checks.
- T-807a table-state/overlap guards, T-807b receipt secrecy and HTTP session
  isolation, T-809 sorted receipt locks and T-808 result envelope remain verified.
- Both user Composer lockfiles are preserved and excluded. No public version
  is selected; publication remains NO-GO.

## Remaining gates and limits

- The current documentation branch records merged-main evidence and closes the
  integration gate. It changes no executable code; review this handoff separately.
- Fixture-fixed identity does not qualify authentication changes or concurrent
  session writes. Unrelated UI/Livewire concurrency, reentrant HTMX host hooks
  and back/forward-cache restoration remain unqualified, not demonstrated defects.
- Native WebMCP uses flag-enabled Chromium and page callers, not a real agent;
  Chromium drops `consequentialHint`. Server confirmation remains authoritative.
- D-026 and D-069–D-078 remain Proposed; accepted decisions end at D-068.
- Private reporting, registry authority/credentials and public-version/publication
  approval remain separate owner gates.

Next scope: review the post-merge evidence handoff, then select an explicit next
task. No new feature or publication work is selected. See [TASKS](TASKS.md).
