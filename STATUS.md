# Project Status

Current state only; history is in git, PRs and [the archive](docs/archive/STATUS-through-2026-10-04.md).

## Current work

- [Integration PR #34](https://github.com/kefyusuf/surfacerelay/pull/34) contains the
  unmerged T-804–T-809 work. Main contains M0–M7 and T-801–T-803.
- T-805 source-isolation correction passed local verification and PR checks.
  Readiness builds from the requested Git archive and freshly compiles browser output;
  ignored source files and stale checkout distribution are excluded.
- T-809 receipt-address concurrency correction locks both token addresses in sorted
  order. Three regressions proved RED first; independent real-store reproduction
  confirms one winner and an unchanged loser.
- T-807b receipt-boundary tests now inspect the actual receipt and Livewire snapshot,
  and reject browser calls to the protected accessor. Isolated leak/public-accessor
  mutations are rejected; scoped independent review found no production defect.
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
