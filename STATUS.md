# Project Status

Current state only; history is in git, PRs and [the archive](docs/archive/STATUS-through-2026-10-04.md).

## Current work

- [Integration PR #34](https://github.com/kefyusuf/surfacerelay/pull/34) contains the
  unmerged T-804–T-809 work. Main contains M0–M7 and T-801–T-803.
- Reviewed corrections pass: T-805 archived-source isolation, T-809 sorted receipt
  locks, and T-807b receipt secrecy/protected-call evidence. Scoped independent
  reviews do not replace the full integration review.
- T-808 review correction treats vetoed HTMX confirmation as an unknown outcome
  (`htmx_request_failed`), preserving deferred human approval. Docker: 366 browser
  tests and typecheck pass; real Chrome HTMX fixture: 9 tests pass.
- Docker Laravel: 612 tests, 3272 assertions (2 skipped); Composer validation,
  changed PHP syntax and canonical validation pass.
- T-805 evidence: 111 Python tests and two actual artifacts/isolated consumers pass.
- The two local Composer lockfiles are preserved and excluded from the change.
- No release or public version is selected. Publication remains NO-GO.

## Remaining review and gaps

- External integration review remains open, including confirmation/store changes,
  server-held approved receipts, HTMX request/result handling and selection sync.
- Native WebMCP evidence uses flag-enabled Chromium and page callers, not a real agent.
  Chromium drops `consequentialHint`; server confirmation receipts remain required.
- Corrected readiness evidence is pre-merge; repeat it on merged main.
- D-026 and D-069–D-078 remain Proposed; accepted decisions end at D-068.

## Owner actions

- Review PR #34 and explicitly authorize merge; no automatic merge.
- Review proposed decisions and resolve private security reporting, registry authority,
  credentials and public-version/publication approval separately.
- Next agent scope: integration review findings only, then merged-main verification
  after an authorized merge. See [TASKS](TASKS.md) and [review handoff](REVIEW_REQUEST.md).

## Needs decision

- D-078: HTMX interprets business output's top-level `target` as an event destination.
  Real HTMX 2.0.10 writes successfully then throws for `{target: "archive"}`, `null`
  or numeric targets. The current arbitrary-object output claim is not qualified.
  Proposed: wrap business data in `{"surfacerelay:result":{"value":{...}}}` and
  unwrap `value` in the adapter, with fixtures/docs and unpublished-contract migration.
  Alternative: explicitly forbid HTMX control keys in business output. The wire
  contract stays unchanged until this owner decision; do not hide response failures.
