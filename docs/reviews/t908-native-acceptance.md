# T-908 Native Execution Envelope Acceptance

The owner-approved opt-in `resultMode: 'envelope'` makes native execution failures
visible without reading driver errors. Default callbacks and direct drivers keep
their existing output/rejection behavior. D-079 defines this browser projection;
the protocol-neutral Action Result contract is unchanged.

## Provenance

Real Chrome 154.0.8037.98 / native client 1.10.1 used an isolated test context and
loopback port 4187. The disposable Docker consumer installed the exact published
Laravel alpha.1 and locked Laravel 13.35.0, Livewire 4.4.7 and Filament 5.10.0.
Its browser dependency was the private `0.0.0-t908.1` artifact built from archived
source `c2d797c70c881477bbd7ce0295110d608ab3c938`, not npm alpha.1.
The artifact SHA-256 is
`f88a44d0480ec85633bc57ca7e21bfd1ec99c0bf595c5b8afae2bbc2a459ff52`.
Final helper/document changes do not change these qualified runtime bytes.
Clean root import, shipped-type typecheck, bundling, Node smoke and blocked deep
import passed; see the [candidate manifest and clean-consumer proof](t908-candidate-proof.json).
[Raw native and SQL evidence](t908-native-acceptance.json) records
the exact revision, artifact manifest and observations.

## Native and SQL observations

| Case | Native presentation | SQL effect |
| --- | --- | --- |
| Confirmation request / actual UI approval | Original `confirmation_required` stays nested; approval alone does not execute | No approval-only effect |
| Normal selected refund / explicit replay | Nested `succeeded`, records 101+102 | One effect for that binding |
| Committed effect, then one-shot HTTP 503 | Safe `execution_failed`, `outcome: unknown`, nonempty fixed message | Exactly one new effect; fault consumed |
| Wait without retry / explicit authorized replay after SQL inspection | No automatic invocation; explicit replay returns original success | Lost-response case remains one effect |
| Stale list and edit102 after another edit mounts | Safe `execution_failed`, unknown outcome | No extra effect |
| Permission revoked / fresh discovery | Nested `rejected/authorization_denied`; fresh page exposes zero tools | No extra effect |
| UI tenant switch, old page / new tenant-b edit201 | Old page fails safely; new page displays only its tenant/record | No extra effect |
| Invalid reason | Original `rejected/input_validation_failed` stays nested | No extra effect |
| Approved receipt expires before invocation | Fresh `confirmation_required` instead of completion | No extra effect |

The native sequence began with an empty ledger and finished with four effects:
two separately mounted refunds and exact holds of 102 and 101. The fault case
alone moved the ledger from one to two; waiting and its explicit replay kept two.
Subsequent rejection/expiry cases kept the final four. The first attempted fault
arm failed because the local CLI called the time module; its following invocation
is labeled a normal completion control, never fault evidence. Observed CLI RED
(three failures) was fixed with `time.time()` and four CLI tests now pass.

## Verification and limits

- Registered callback RED/GREEN; 49 envelope tests and 447 browser tests pass,
  including hostile proxies, real error-class forgery, undefined/null, nested
  values, pre-abort/no-dispatch and post-dispatch unknown outcome. Typecheck/build pass.
- Canonical validation, 22 HTMX and 41 execution-envelope fixtures pass; Python
  regression suite has 193 tests. Existing 23 Filament HTTP checks, seven new
  fault/control checks, five generator safety checks and four CLI checks pass.
- Independent source/evidence review found no remaining blocker. The reviewer
  did not replay native calls. Hosted exact-head CI is a separate PR check.
- Fault provenance uses this invocation's ledger boundary and exact new effect,
  not a global arm-time count. Bundled-effect then singleton replay was observed
  RED before the provenance correction and GREEN afterward.
- This is a serial, single-worker simulation, without real payment, bank 3DS,
  concurrency qualification, performance guarantee or general browser conformance.
  Outer native `Completed` means callback resolution; inspect the envelope and
  nested application status. Unknown outcome does not authorize automatic retry.

Task-created demo, runtime/Compose resources and browser tabs are removed after
verification. Existing image/resources and both user Composer locks are preserved.
No npm/Packagist publication or merge is part of this acceptance.
