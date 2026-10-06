# Project Status

Current state only; history is in Git, PRs and [the archive](docs/archive/STATUS-through-2026-10-04.md).

## Current work

- Main includes merged T-804–T-809 and the reviewed integration handoff.
- T-901 alpha scope/candidates are verified on open [PR #36](https://github.com/kefyusuf/surfacerelay/pull/36).
  The owner selected `0.1.0-alpha.1`; publication remains NO-GO.
- T-902 installed-application acceptance passes: 11 actual HTTP tests against
  the installed Laravel alpha archive, real authentication/membership/Gate,
  shared confirmation locks, SQLite idempotency/audit and output redaction.
- A temporary Gate bypass fails the denied-actor test; restoring the real
  policy passes. Two independent HTTP processes rendezvous before dispatch;
  receipt/key races each produce exactly one database effect.
- Docker Python tooling: 159 tests pass; canonical validation covers 22 HTMX
  fixtures; publication guardrails, fixture PHP syntax and diff checks pass.
  See [installed-application evidence](docs/reviews/alpha-installed-application.md).
- Branch `test/t-902-installed-app-acceptance` is stacked on PR #36. Its added
  CI job rebuilds an exact-source alpha archive and repeats the application tests.
  Check current-head CI before merge. Both owner Composer lockfiles are preserved.

## Remaining gates and limits

- The application fixture qualifies the Laravel HTTP host boundary. Browser
  installation evidence remains T-901; no new UI/native-agent certification,
  concurrent session-write safety or production deployment is claimed.
- Unrelated UI/Livewire concurrency, reentrant HTMX host hooks and back/forward
  cache restoration remain unqualified. Native WebMCP uses page callers and
  flag-enabled Chromium; server confirmation remains authoritative.
- The browser candidate stays private; fixed-NO-GO tooling needs a reviewed
  publication path. D-026 and D-069–D-078 remain Proposed.
- Private reporting, registry authority/credentials and final publication
  authorization remain owner gates. See [the alpha plan](docs/releases/0.1.0-alpha.1.md).

Next task: T-903 publication eligibility preparation, after this handoff and an
explicit continuation request. Do not begin it automatically, merge or publish.
