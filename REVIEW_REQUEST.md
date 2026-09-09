# External Review Record

## Review and merge status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Scope:** `T-403 — Output policy/redaction`
- **Pull request:** `#3`
- **Feature head at merge:** `52ffb5d8071006378dd3d81fc16088394714d081`
- **Merge commit:** `969f65321ca7bd5e3496b081eb2d946350b3ec8f`
- **Merged-main workflow:** `34372399222` — **7/7 green**
- **PHP evidence:** **431 tests / 2072 assertions** across PHP 8.3/8.4 × Illuminate 12/13 with MySQL 8.4 service coverage
- **Browser isolation:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract:** `python scripts/validate.py` green; frozen `spec/0.1/**` unchanged
- **Decision:** `D-046 — ACCEPTED`
- **CodeRabbit exact-head review:** run `4c8e5524-25c1-4732-83d8-294b4c957cc6`
- **CodeRabbit result:** **0 actionable comments; Merge Risk = Minimal**
- **Open review threads at merge:** **0**
- **Review result:** **PASSED**
- **Merge result:** **PASSED / MERGED TO MAIN / MERGED MAIN REVALIDATED**

## Review objective

The review challenged the post-execution disclosure boundary for confidentiality leaks, incorrect failure taxonomy, replay bypasses, authority expansion, and unsafe recovery semantics. SurfaceRelay intentionally does not guess sensitive fields: application code supplies a trusted `SensitiveOutputRedactor`, while core owns fail-closed enforcement.

## Implemented semantics

1. `outputSensitivity=normal` passes exact execution/replay output through and never invokes the sensitive redactor.
2. `outputSensitivity=sensitive` may leave SurfaceRelay only through explicit `OutputRedactionResult::release(value)`.
3. `release(null)` is valid and remains distinguishable from `withhold()` through explicit output-presence tracking.
4. Missing redactor, explicit `withhold()`, or any redactor exception fails closed using `output_policy_failed`.
5. `ActionPipelineState::withoutOutput()` clears raw output before a disclosure-failure outcome reaches audit/finalization.
6. Output-policy entry without a prior execution/replay output is an internal invariant violation.
7. The policy port receives exact `ActionDefinition`, raw output, and restricted `OutputPolicyContext`; the complete `InvocationContext` and generic caller metadata are not exposed through that API.
8. `output_policy_failed` normalizes to public `failed` with one static message and no forwarded halt details, raw output, or exception text.
9. `outputContentTrust` remains independent from confidentiality/redaction per D-032.
10. T-402 completed replay skips confirmation/execution but feeds stored pre-output-policy executor output through current `OutputPolicyStage` again.
11. A replay can therefore produce a new safe projection under current policy; prior disclosed/redacted response data is not replayed.
12. If current policy withholds or throws during a completed replay, application execution remains skipped and the idempotency record remains `completed`.
13. A later retry may recover by applying current policy again to the same stored pre-policy output; recovery is policy-only and does not reopen execution ownership.
14. Audit observes only post-policy released output on success, or output-free state on policy failure.
15. `spec/0.1/**` remains unchanged.

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

The scenario proves both `withhold()` and redactor-exception paths on an exact completed replay: disclosure failure leaves the record completed and does not execute application code again; a later retry can recover only by rerunning current output policy over stored pre-policy output.

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
Review closure head:         52ffb5d8071006378dd3d81fc16088394714d081 / 34371371764 — 7/7 green
Merged main:                 969f65321ca7bd5e3496b081eb2d946350b3ec8f / 34372399222 — 7/7 green
PHP:                         431 tests / 2072 assertions
Browser:                     TypeScript typecheck + 103/103 Vitest tests
Contract:                    green; frozen spec/0.1 unchanged
```

## External review assessment

CodeRabbit's incremental review explicitly covered changes through `010037cf79f97e5dfc0a2dba7f3387a3f246b223`, including replay-recovery integration and Prep List test-pipeline wiring. It reported no actionable comments and classified merge risk as Minimal.

CodeRabbit's generic docstring-coverage metric remained below its configured 80% threshold. That is a tooling/style warning rather than a SurfaceRelay CI/security failure and generated no actionable review comment. It remained non-blocking to avoid unrelated scope growth.

## Merge assessment

The PR was merged using exact expected-head protection against `52ffb5d8071006378dd3d81fc16088394714d081`. GitHub created merge commit `969f65321ca7bd5e3496b081eb2d946350b3ec8f`, whose parents are the previous `main` checkpoint and the reviewed feature head.

The post-merge push workflow `34372399222` re-ran the full seven-job validation matrix on the exact merge commit and all jobs passed. A PHP 8.3 / Illuminate 12 matrix job reported `OK (431 tests, 2072 assertions)`.

## Scope / non-claims

T-403 does not provide heuristic PII/secret detection, application-specific masking rules, structured audit persistence, a new wire field, or a generic guarantee that arbitrary application redactors are semantically correct. Core guarantees only that sensitive output is not implicitly disclosed, policy failure closes before public result/audit finalization, and T-402 completed replay cannot bypass current disclosure policy or regain execution ownership because disclosure failed.

## Final result

**PASSED / MERGED / MAIN REVALIDATED.** T-403 is closed. The next task is `T-404 — Structured audit events`, which has not been started by this change.
