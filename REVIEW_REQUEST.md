# Integration Review Request

Review [PR #34](https://github.com/kefyusuf/surfacerelay/pull/34) against main.
It contains unmerged release documentation/readiness and WebMCP/Filament work.

## Latest correction — T-807a overlapping selection calls

Livewire batches matching calls and reads deferred updates when sending. Real
Chrome + Docker RED returned an approved refund of order 101 to a first call that
selected 102, after a second call restored 101 before the batch was sent.

The example wrapper now rejects a second invocation on the same component before
sync, until the exact inner promise settles. It never queues or retries calls;
failures release the guard. Different components remain independent. Existing
table-state guards and current-record edit behavior are preserved.

Corrected real Chrome + isolated Docker Filament: 15 tests pass. The overlap test
checks one refund call carrying the 102 selection, zero refunds, rejected overlap
and a subsequent POST after release. Validation/inner failure release also passes.
Independent exclusion matrix: 9 scenarios pass. Canonical validation/syntax pass.
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
  reject wrapper overlap; unrelated UI/Livewire concurrency is not qualified.
- Scoped correction reviews do not close the full external integration review.
- Node/fixture and flag-enabled browser proofs are bounded; they are not general
  WebMCP certification or production-security approval.
- Readiness evidence remains pre-merge. Public API of the readiness function and core
  runtime contracts are unchanged by this correction; publication always stays NO-GO.
- User Composer lockfiles are excluded. Check the latest PR CI before approval.

Next gate: external integration review/finding disposition. Merge, decision promotion,
settings changes and publication need explicit authorization.
