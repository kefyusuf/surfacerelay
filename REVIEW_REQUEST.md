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
- **External-review checkpoint:** `010037cf79f97e5dfc0a2dba7f3387a3f246b223`
- **External-review checkpoint workflow:** `34369719085` — **7/7 green**
- **PHP evidence:** **431 tests / 2072 assertions** across PHP 8.3/8.4 × Illuminate 12/13 with MySQL 8.4 service coverage
- **Browser isolation:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract:** `python scripts/validate.py` green; frozen `spec/0.1/**` unchanged
- **Decision:** `D-046 — ACCEPTED`
- **CodeRabbit exact-head review:** run `4c8e5524-25c1-4732-83d8-294b4c957cc6`
- **CodeRabbit result:** **0 actionable comments; Merge Risk = Minimal; reviewed coverage through `010037cf…`**
- **External review result:** **PASSED**
- **Open review threads:** **0**
- **Merge:** not requested; explicit permission remains required

## Review objective

Review the post-execution disclosure boundary for confidentiality leaks, incorrect failure taxonomy, replay bypasses, authority expansion, and unsafe recovery semantics. The implementation intentionally does **not** guess sensitive fields: application code supplies a trusted `SensitiveOutputRedactor`, while SurfaceRelay core owns fail-closed enforcement.

## Implemented semantics

1. `outputSensitivity=normal` passes the exact execution/replay output through and never invokes the sensitive redactor.
2. `outputSensitivity=sensitive` may leave SurfaceRelay only through explicit `OutputRedactionResult::release(value)`.
3. `release(null)` is valid and remains distinguishable from `withhold()` through explicit output-presence tracking.
4. Missing redactor, explicit `withhold()`, or any redactor exception fails closed using `output_policy_failed`.
5. On that failure path, `ActionPipelineState::withoutOutput()` clears raw output before the halted outcome reaches audit/finalization.
6. Output-policy entry without a prior execution/replay output is an internal invariant violation.
7. The policy port receives exact `ActionDefinition`, raw output and restricted `OutputPolicyContext`; it does not receive the complete `InvocationContext` or generic caller metadata through that API.
8. `output_policy_failed` normalizes to public `failed` with one static message and no forwarded halt details, raw output, or exception text.
9. `outputContentTrust` remains independent from confidentiality/redaction per D-032.
10. T-402 completed replay skips confirmation/execution but feeds stored pre-output-policy executor output through the current `OutputPolicyStage` again.
11. A replay can therefore produce a new safe projection under current policy; a prior disclosed/redacted response is not replayed.
12. If current policy withholds or throws during a completed replay, application execution remains skipped and the idempotency record remains `completed`.
13. A later retry may recover by applying current policy again to the same stored pre-policy output; recovery is policy-only and does not reopen execution ownership.
14. Audit observes only post-policy released output on success, or output-free state on policy failure.
15. `spec/0.1/**` is unchanged.

## Hardening red → green

Replay-failure recovery hardening landed after the first CodeRabbit pass. The first PR run containing that scenario failed before production code because the new integration fixture omitted mandatory `ActionDefinition::contextRequirements`:

```text
RED:   9b255cade7e60261e9a3407a579dd40bd06586d4 / 34361584554
       all PHP matrix jobs failed in OutputPolicyReplayFailureRecoveryIntegrationTest
       ArgumentCountError: ActionDefinition contextRequirements not passed

GREEN: d58230d2f91f5d3ae89f9bd89d479dbaf27784e6 / 34368063289
       fixture aligned with contextRequirements: []
       no production code changed
       all 7 jobs green
       PHP: 431 tests / 2072 assertions
```

The scenario now proves both `withhold()` and redactor-exception paths on an exact completed replay: disclosure failure leaves the record completed and does not execute application code again; a later retry can recover only by rerunning current output policy over stored pre-policy output.

## Verification evidence

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
First external review head:  d98f1394f3ccbafe99016dd9cf1b4a3b5e1a4b70 — no actionable comments
Hardening RED:               9b255cade7e60261e9a3407a579dd40bd06586d4 / 34361584554
Fixture fix GREEN:           d58230d2f91f5d3ae89f9bd89d479dbaf27784e6 / 34368063289 — 7/7 green
Final reviewed checkpoint:   010037cf79f97e5dfc0a2dba7f3387a3f246b223 / 34369719085 — 7/7 green
CodeRabbit exact-head:       4c8e5524-25c1-4732-83d8-294b4c957cc6 — 0 actionable comments; Minimal risk
PHP:                         431 tests / 2072 assertions
Browser:                     TypeScript typecheck + 103/103 Vitest tests
Contract:                    green; frozen spec/0.1 unchanged
Open review threads:         0
```

## External review assessment

CodeRabbit's incremental review explicitly covered changes from `d98f1394…` through `010037cf…`, including the replay-recovery integration test and Prep List test-pipeline wiring. It reported:

- **No actionable comments generated.**
- **Merge Risk: Minimal**, coverage through exact commit `010037cf79f97e5dfc0a2dba7f3387a3f246b223`.
- The incremental replay-recovery changes preserve fail-closed output policy behavior and leave no current merge-blocking risk.

CodeRabbit also reports its generic **Docstring Coverage** metric below its configured 80% threshold. This is a tooling/style warning rather than a SurfaceRelay CI/security failure, and it produced no actionable review comment. Adding docstrings across dozens of test/helper functions solely to satisfy that external metric would expand T-403 without improving the implemented trust boundary, so it is intentionally not treated as a merge blocker.

## Diff / scope check

The reviewed branch is ahead-only from `main@08a9862923d60f4e08b1732c3e11053e5b7bb74e`. Changes remain limited to:

- D-046 plus T-403 design/plan/status/review documentation;
- Laravel OutputPolicy contracts/stage;
- pipeline output-state sanitization;
- reserved result-code mapping;
- focused unit/integration/replay-recovery tests;
- Prep List test wiring through the real output-policy stage.

No `spec/0.1/**` file changed. T-404 production audit persistence is not included.

## Explicit non-claims

T-403 does not provide heuristic PII/secret detection, application-specific masking rules, structured audit persistence, a new wire field, or a generic guarantee that arbitrary application redactors are semantically correct. Core guarantees only that sensitive output is not implicitly disclosed, that policy failure is closed before public result/audit finalization, and that T-402 completed replay cannot bypass current disclosure policy or regain execution ownership because disclosure failed.

## Review result

**PASSED.** T-403 has exact-head CI evidence and CodeRabbit coverage through `010037cf79f97e5dfc0a2dba7f3387a3f246b223` with no actionable comments and no open review threads. The next gate is a separate explicit merge decision. **This document is not a merge request.**
