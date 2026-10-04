# Integration Review Request

Review [PR #34](https://github.com/kefyusuf/surfacerelay/pull/34) against main.
It contains unmerged release documentation/readiness and WebMCP/Filament work.

## Latest correction — T-808 deferred confirmation outcome

Real HTMX resolves its original promise after a `htmx:confirm` veto, while the
application can call `issueRequest` later. The runtime previously claimed `not_sent`
before a delayed server write. It now associates the confirmation event with the
exact source and values object and conservatively reports `htmx_request_failed`.
Native confirmation is preserved; foreign confirmation and beforeRequest veto
retain the existing classification. A negative unit regression proved RED first.

Docker: 366 browser tests and typecheck pass. Real Chrome standard HTMX fixture:
9 tests pass, including failure-before-explicit-resume and exactly one later write.
Independent real-browser review also proves normal result and beforeRequest veto.
The local full fixture's fixed-4173 origin assertion did not fit the isolated port;
check the unmodified full fixture on current-head CI. Canonical validation passes.

## Open contract finding — D-078

Real HTMX interprets output `target` as event routing: a valid business object can
cause onLoadError after the server writes. Do not suppress that error. The proposed
`value` envelope, alternative field restriction and migration gate are recorded in
[STATUS](STATUS.md#needs-decision); no new wire contract is implemented yet.

## Integration focus

- T-805 source isolation and isolated artifact consumers passed local verification
  and CI. T-809 sorted dual-lock correction passed independent real-store review.
  Review HTMX request/result correlation, Filament selection synchronization and
  cross-contract behavior across the full integration diff.
- Scoped correction reviews do not close the full external integration review.
- Node/fixture and flag-enabled browser proofs are bounded; they are not general
  WebMCP certification or production-security approval.
- Readiness evidence remains pre-merge. Public API of the readiness function and core
  runtime contracts are unchanged by this correction; publication always stays NO-GO.
- User Composer lockfiles are excluded. Check the latest PR CI before approval.

Next gate: external integration review/finding disposition. Merge, decision promotion,
settings changes and publication need explicit authorization.
