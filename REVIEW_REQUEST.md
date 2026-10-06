# Integration Review Request

Review [PR #34](https://github.com/kefyusuf/surfacerelay/pull/34) against main.
It contains unmerged release documentation/readiness and WebMCP/Filament work.

## Latest evidence — T-807b HTTP session isolation

No production defect was found in the scoped receipt-boundary review. The new
negative browser test replays the approving component's valid signed snapshot
using another HTTP session's cookies and CSRF token. Livewire accepts the request
but returns `confirmation_required`; nothing is refunded. The original owner's
retry still refunds exactly the approved order.

Replacing session receipt storage with a shared file cache only inside the
temporary container caused this test to fail: the other session refunded 101.
The original source was restored; Chrome + isolated Docker Filament: 16 tests
pass. Canonical validation and changed JS syntax pass. No production code or
public contract changes are included in this evidence increment.

The fixture has a fixed actor/tenant; authentication changes and concurrent
session writes are not qualified. Session pull is not an atomic concurrency
guard; the confirmation store's scope-checked atomic consumption governs reuse.
Scoped review does not close full integration review. Check current-head CI.

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
