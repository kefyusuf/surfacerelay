# External Review Request

## Review status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Scope:** `T-403 — Output policy/redaction`
- **Branch:** `feat/output-policy-redaction`
- **Base / merge-base:** `main@08a9862923d60f4e08b1732c3e11053e5b7bb74e`
- **Implementation checkpoint:** `0195875b5f2d5d9646407337ea54df52fe4c8bb3`
- **Implementation workflow:** `34356958945` — **7/7 green**
- **PHP evidence:** **430 tests / 2034 assertions** across PHP 8.3/8.4 × Illuminate 12/13 with MySQL 8.4 service coverage
- **Browser isolation:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract:** `python scripts/validate.py` green; frozen `spec/0.1/**` unchanged
- **Decision:** `D-046 — ACCEPTED`
- **External review result:** **PENDING**
- **Merge:** not requested; explicit permission remains required

## Review objective

Review the new post-execution disclosure boundary for confidentiality leaks, incorrect failure taxonomy, replay bypasses, and authority expansion. The implementation intentionally does **not** guess sensitive fields: application code supplies a trusted `SensitiveOutputRedactor`, while SurfaceRelay core owns fail-closed enforcement.

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
12. Audit observes only post-policy released output on success, or output-free state on policy failure.
13. `spec/0.1/**` is unchanged.

## Primary review focus

Please challenge these trust-boundary claims in particular:

- Can any sensitive raw value survive a missing redactor, `withhold()`, or thrown redactor and become observable by the public result or auditor?
- Can `ActionPipelineHalt.details` or exception text leak through the reserved `output_policy_failed` public mapping?
- Can normal output accidentally invoke or depend on sensitive policy code?
- Can a completed idempotency replay bypass current output policy or replay an already-disclosed payload instead of stored pre-policy output?
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
PHP:                         430 tests / 2034 assertions
Browser:                     TypeScript typecheck + 103/103 Vitest tests
Contract:                    green; frozen spec/0.1 unchanged
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
- first execution then exact completed idempotency replay proving executor runs once, current redactor runs twice, retry correlation ID is retained, stored raw pre-policy output is re-evaluated, and audit sees only the current safe projection.

## Diff / scope check

At implementation checkpoint `0195875b5f2d5d9646407337ea54df52fe4c8bb3`, comparison against `main@08a9862923d60f4e08b1732c3e11053e5b7bb74e` was ahead-only (**20 ahead / 0 behind**) and contained only:

- D-046 plus T-403 design/plan docs;
- Laravel OutputPolicy contracts/stage;
- pipeline output-state sanitization;
- reserved result-code mapping;
- focused unit/integration tests.

No `spec/0.1/**` file changed.

## Explicit non-claims

T-403 does not provide heuristic PII/secret detection, application-specific masking rules, structured audit persistence, a new wire field, or a generic guarantee that arbitrary application redactors are semantically correct. Core guarantees only that **sensitive output is not implicitly disclosed** and that a policy failure is closed before public result/audit finalization.

## Requested outcome

External review should either identify a concrete trust-boundary defect with a reproducible path, or confirm that T-403 is ready for the separate merge gate. **Do not treat this document as a merge request.**
