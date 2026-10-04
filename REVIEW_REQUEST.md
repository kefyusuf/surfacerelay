# Integration Review Request

Review [PR #34](https://github.com/kefyusuf/surfacerelay/pull/34) against main.
It contains unmerged release documentation/readiness and WebMCP/Filament work.

## Latest correction — T-808 HTMX result envelope

HTMX interpreted business `target` as event routing and could fail after a server
write. The owner-approved wire declaration is now
`{"surfacerelay:result":{"value":{...}}}`. The runtime unwraps only the exact
envelope from its issued xhr. Business control-like fields stay data. Request
correlation, failure handling and deferred-confirmation classification are preserved.

TDD: 11 failures before correction. Docker: 398 browser tests and typecheck pass;
schema and actual runtime share 22 fixtures. Canonical validation passes. Real
Chrome HTMX: 14 tests pass, including five business-target cases with persisted
writes. Scoped independent review found no material defect. Local Chrome uses
isolated port 48173; check the full native fixture on current-head CI.

## Migration impact — D-078

Update servers and runtimes together for this unpublished wire-format change.
No legacy fallback exists; declarations outside the exact envelope return undefined.
Old output with only an object-valued `value` is structurally indistinguishable;
inventory producers. See [migration guidance](docs/consumers/browser-runtime.md#htmx-result-envelope).
Neutral core schemas and the returned business-object shape remain unchanged.

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
