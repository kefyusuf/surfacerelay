# External Review Request

## Review status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Scope:** `T-403 — Output policy/redaction`
- **Branch:** `feat/output-policy-redaction`
- **Pull request:** `#3`
- **Base / merge-base:** `main@08a9862923d60f4e08b1732c3e11053e5b7bb74e`
- **Initial implementation checkpoint:** `0195875b5f2d5d9646407337ea54df52fe4c8bb3`
- **Exact verified code checkpoint:** `d58230d2f91f5d3ae89f9bd89d479dbaf27784e6`
- **Exact code workflow:** `34368063289` — **7/7 green**
- **PHP evidence:** **431 tests / 2072 assertions** across PHP 8.3/8.4 × Illuminate 12/13 with MySQL 8.4 service coverage
- **Browser isolation:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract:** `python scripts/validate.py` green; frozen `spec/0.1/**` unchanged
- **Decision:** `D-046 — ACCEPTED`
- **Previous external review:** CodeRabbit reviewed through `d98f1394f3ccbafe99016dd9cf1b4a3b5e1a4b70` and generated no actionable comments
- **External review result for current head:** **PENDING**
- **Merge:** not requested; explicit permission remains required

## Why exact-head review is required again

The first CodeRabbit review covered the implementation through `d98f1394…`. After that review, replay-failure recovery hardening was added to prove that a completed T-402 replay remains closed when the current T-403 policy withholds or throws, and that a later policy-only retry can recover without re-executing application code.

The first PR CI run containing that hardening (`9b255cade7e60261e9a3407a579dd40bd06586d4` / `34361584554`) failed all PHP matrix jobs before production code was reached because the new integration fixture omitted mandatory `ActionDefinition::contextRequirements`. The fixture was corrected with `contextRequirements: []` only; no production code changed. Exact code checkpoint `d58230d2f91f5d3ae89f9bd89d479dbaf27784e6` then passed all seven CI jobs.

Because these replay-hardening changes landed after CodeRabbit's earlier reviewed commit, external review must cover the current PR head rather than relying on the earlier no-actionable-comments result.

## Review objective

Review the post-execution disclosure boundary for confidentiality leaks, incorrect failure taxonomy, replay bypasses, authority expansion, and unsafe recovery semantics. The implementation intentionally does **not** guess sensitive fields: application code supplies a trusted `SensitiveOutputRedactor`, while SurfaceRelay core owns fail-closed enforcement.

## Implemented semantics

1. `outputSensitivity=normal` passes the exact execution/replay output through and never invokes the sensitive redactor.
2. `outputSensitivity=sensitive` may leave SurfaceRelay only through explicit `OutputRedactionResult::release(value)`.
3. `release(null)` is valid and remains distinguishable from `withhold()` through explicit output-presence tracking.
4. Missing redactor, explicit `withhold()`, or any redactor exception fails closed using `output_policy_failed`.
5. On that failure path, `ActionPipelineState::withoutOutput()` clears raw output **before** the halted outcome reaches audit/finalization.
6. Output-policy entry without a prior execution/replay output is an internal invariant violation.
7. The policy port receives exact `ActionDefinition`, raw output and restricted `OutputPolicyContext`; it does not receive the complete `InvocationContext` or generic caller metadata through that API.
8. `output_policy_failed` normalizes to public `failed` with one static message and no forwarded halt details, raw output, or exception text.
9. `outputContentTrust` remains independent from confidentiality/redaction per D-032.
10. T-402 completed replay skips confirmation/execution but feeds stored **pre-output-policy** executor output through the current `OutputPolicyStage` again.
11. Consequently, a replay can produce a new safe projection under the current policy; the prior disclosed/redacted response is not replayed.
12. If current policy withholds or throws during a completed replay, application execution remains skipped and the idempotency record remains `completed`.
13. A later retry may recover by applying current policy again to the same stored pre-policy output; recovery is policy-only and does not reopen execution ownership.
14. Audit observes only post-policy released output on success, or output-free state on policy failure.
15. `spec/0.1/**` is unchanged.

## Primary review focus

Please challenge these trust-boundary claims in particular:

- Can any sensitive raw value survive a missing redactor, `withhold()`, or thrown redactor and become observable by the public result or auditor?
- Can `ActionPipelineHalt.details` or exception text leak through the reserved `output_policy_failed` public mapping?
- Can normal output accidentally invoke or depend on sensitive policy code?
- Can a completed idempotency replay bypass current output policy or replay an already-disclosed payload instead of stored pre-policy output?
- Can replay disclosure failure accidentally reopen/downgrade completed idempotency state or cause application re-execution?
- Can a later recovery retry do anything beyond re-run current output policy over stored pre-policy output?
- Does the restricted policy context accidentally reintroduce generic caller metadata as trusted authority?
- Is `null` handled as an explicitly released value rather than confused with missing output?
- Does redaction alter the independent `outputContentTrust` classification?
- Are any unrelated wire/schema or T-404 audit-persistence changes present?

## TDD / verification evidence

```text
Contract/context RED:        0bb9e430… / 34345104742
Task-1 GREEN:                a7f58d90… / 34345494908 — 7/7 green
Stage RED:                   321b32ef… / 34345640741
Normal pass-through GREEN:   433eafa9… / 34346073018 — 7/7 green
Sensitive release RED:       ff18fa96f9bb36d9d03f7cf148220fafe4cfa056 / 34346351525
Sensitive release GREEN:     d993d32037936fd9132743ccac39b7228a6e87b4 / 34346513087 — 7/7 green
Fail-closed RED:             57315af9e183ac2fd63ec88a59c9aabc2cae2559 / 34346906520
Fail-closed GREEN:           df2667dc2a34fa4316ccccbfb2c23a27bb9ca9ff / 34347231312 — 7/7 green
Result mapping RED:          c0d2cad4ecd7870b9464dcb2933cdf189b35ec0d / 34347679997
Result mapping GREEN:        0399ef03f42001f3c5e913fda5687e74e9ba5a90 / 34347966075 — 7/7 green
Audit sanitization proof:    bb3506076f579fb6ee637c12e6dc68bea3800b07 / 34348300687 — 7/7 green
Implementation checkpoint:   0195875b5f2d5d9646407337ea54df52fe4c8bb3 / 34356958945 — 7/7 green
First external review head:  d98f1394f3ccbafe99016dd9cf1b4a3b5e1a4b70 — CodeRabbit: no actionable comments
Hardening CI RED:            9b255cade7e60261e9a3407a579dd40bd06586d4 / 34361584554 — PHP matrix failed in new replay-recovery fixture
Fixture fix GREEN:           d58230d2f91f5d3ae89f9bd89d479dbaf27784e6 / 34368063289 — 7/7 green
PHP exact code:              431 tests / 2072 assertions
Browser exact code:          TypeScript typecheck + 103/103 Vitest tests
Contract exact code:         green; frozen spec/0.1 unchanged
```

## Failure-safety coverage

The focused suites include:

- normal exact object pass-through and redactor call count zero;
- sensitive explicit release and restricted trusted context capture;
- sensitive `release(null)` success;
- missing redactor fail-closed;
- explicit withhold fail-closed;
- redactor exception fail-closed without exception/raw-output leakage;
- output-policy missing-output invariant;
- adversarial halt-details normalization proving static `failed` output;
- real ActionBus audit finalization proving sanitized/output-free state boundaries;
- D-032 content-trust preservation through redaction;
- first execution then exact completed idempotency replay proving executor runs once, current redactor reruns, retry correlation ID is retained, stored raw pre-policy output is re-evaluated, and audit sees only the current safe projection;
- completed replay policy failure via both `withhold()` and exception, proving idempotency remains completed and the executor does not run again;
- later retry recovery after disclosure failure, proving policy-only reevaluation can safely release from stored pre-policy output without reopening application execution.

## Diff / scope check

The branch remains based on `main@08a9862923d60f4e08b1732c3e11053e5b7bb74e` and contains only:

- D-046 plus T-403 design/plan/status/review documentation;
- Laravel OutputPolicy contracts/stage;
- pipeline output-state sanitization;
- reserved result-code mapping;
- focused unit/integration/replay-recovery tests;
- Prep List test wiring through the real output-policy stage.

No `spec/0.1/**` file changed. T-404 production audit persistence is not included.

## Explicit non-claims

T-403 does not provide heuristic PII/secret detection, application-specific masking rules, structured audit persistence, a new wire field, or a generic guarantee that arbitrary application redactors are semantically correct. Core guarantees only that **sensitive output is not implicitly disclosed**, that policy failure is closed before public result/audit finalization, and that T-402 completed replay cannot bypass current disclosure policy or regain execution ownership because disclosure failed.

## Requested outcome

External review should either identify a concrete trust-boundary defect with a reproducible path, or confirm that the current PR head is ready for the separate merge gate. **Do not treat this document as a merge request.**
