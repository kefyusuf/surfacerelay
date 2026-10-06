# Project Status

Current state only; history is in git, PRs and [the archive](docs/archive/STATUS-through-2026-10-04.md).

## Current work

- [Integration PR #34](https://github.com/kefyusuf/surfacerelay/pull/34) contains the
  unmerged T-804–T-809 work. Main contains M0–M7 and T-801–T-803.
- Reviewed corrections pass: T-805 archived-source isolation, T-809 sorted receipt
  locks, T-807b receipt secrecy/protected-call evidence and T-807a selection guard.
  Scoped independent reviews do not replace the full integration review.
- T-807a rejects missing, ambiguous, foreign-owned or malformed table selection
  before retry. Old-client real-browser RED refunded a stale selected order;
  Chrome + Docker Filament: 15 tests pass, including overlapping calls. An old-client
  RED returned order 101 for a call selecting 102; component-wide exclusion now
  preserves the first request and rejects overlap before state writes. Independent
  exclusion matrix: 9 scenarios pass. Canonical validation passes.
- T-808 uses the owner-approved `surfacerelay:result.value` envelope. Business
  `target`, `value` and `elt` remain data; deferred confirmation remains an unknown
  outcome. Docker: 398 browser tests/typecheck and 22 shared schema fixtures pass.
  Real Chrome HTMX: 14 tests and scoped independent review pass. See
  [wire migration guidance](docs/consumers/browser-runtime.md#htmx-result-envelope).
- Docker Laravel: 612 tests, 3272 assertions (2 skipped); Composer validation,
  changed PHP syntax and canonical validation pass.
- T-805 evidence: 111 Python tests and two actual artifacts/isolated consumers pass.
- The two local Composer lockfiles are preserved and excluded from the change.
- No release or public version is selected. Publication remains NO-GO.

## Remaining review and gaps

- Full external integration review and cross-contract finding disposition remain
  open. Wrapper overlap is rejected; unrelated UI/Livewire concurrency is not qualified.
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
