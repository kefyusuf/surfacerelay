# Integration Review Request

Review [PR #34](https://github.com/kefyusuf/surfacerelay/pull/34) against main.
It contains unmerged release documentation/readiness and WebMCP/Filament work.

## Latest correction — T-809 receipt-address concurrency

Cache approval previously locked only its challenge. Two concurrent approvals
targeting one receipt could both succeed and overwrite the winning scope.
Approval now locks both addresses in sorted order before any cache access, using
the same lock namespace as challenge creation and receipt consumption.

Three negative regressions proved RED before the fix: overlapping approval,
reversed address order, and second-lock failure without mutation. Focused Docker
tests pass (30 tests, 356 assertions); full Laravel passes (611 tests, 3266 assertions,
2 skipped), with Composer validation, PHP syntax and canonical validation passing.
Independent review repeated the race with real Laravel ArrayStore/ArrayLock and a
valid injected receipt generator: one winner, unchanged losing challenge, occupied
retry refused and single-use consumption preserved. No public API change.

## Integration focus

- T-805 source isolation and isolated artifact consumers passed local verification
  and CI. Review the remaining confirmation/session boundary, HTMX request/result
  correlation and Filament selection synchronization across the full diff.
- Scoped correction reviews do not close the full external integration review.
- Node/fixture and flag-enabled browser proofs are bounded; they are not general
  WebMCP certification or production-security approval.
- Readiness evidence remains pre-merge. Public API of the readiness function and core
  runtime contracts are unchanged by this correction; publication always stays NO-GO.
- User Composer lockfiles are excluded. Check the latest PR CI before approval.

Next gate: external integration review/finding disposition. Merge, decision promotion,
settings changes and publication need explicit authorization.
