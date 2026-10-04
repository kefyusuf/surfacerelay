# Integration Review Request

Review [PR #34](https://github.com/kefyusuf/surfacerelay/pull/34) against main.
It contains unmerged release documentation/readiness and WebMCP/Filament work.

## Latest correction — T-807a selection fail-closed guard

Example browser glue silently skipped an absent table and invoked the page with
older server selection. Real old-client Chrome + Docker Filament RED reproduced
an approved refund of order 101 after table resolution disappeared.

Only exact server-issued bindings requiring `current_selection` now synchronize.
They require one component-owned table and validate all three state fields before
deferred writes. Missing, ambiguous, foreign or malformed state stops before a
Livewire request; current-record edit actions still work without a table.

Corrected real Chrome + isolated Docker Filament: 14 tests pass, including four
negative approved-retry cases. Independent guard matrix: 17 scenarios pass with
zero calls/partial writes on invalid state. Canonical validation and syntax pass.
The fixture uses PHP intl and Linux-extracted sources, avoiding Windows mount delays.
Check current-head CI; scoped review does not close full integration review.

## Existing migration impact — D-078

Update servers and runtimes together for this unpublished wire-format change.
No legacy fallback exists; declarations outside the exact envelope return undefined.
Old output with only an object-valued `value` is structurally indistinguishable;
inventory producers. See [migration guidance](docs/consumers/browser-runtime.md#htmx-result-envelope).
Neutral core schemas and the returned business-object shape remain unchanged.

## Integration focus

- T-805 source isolation and isolated artifact consumers passed local verification
  and CI. T-809 sorted dual-lock correction passed independent real-store review.
  Review cross-contract behavior across the full integration diff. Selection proofs
  cover sequential calls; independent concurrent snapshots are not qualified.
- Scoped correction reviews do not close the full external integration review.
- Node/fixture and flag-enabled browser proofs are bounded; they are not general
  WebMCP certification or production-security approval.
- Readiness evidence remains pre-merge. Public API of the readiness function and core
  runtime contracts are unchanged by this correction; publication always stays NO-GO.
- User Composer lockfiles are excluded. Check the latest PR CI before approval.

Next gate: external integration review/finding disposition. Merge, decision promotion,
settings changes and publication need explicit authorization.
