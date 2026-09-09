# Project Status

> Current repository state for implementation and review.

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/output-policy-redaction`
- **Base / merge-base:** `main@08a9862923d60f4e08b1732c3e11053e5b7bb74e`
- **Stage:** M0 DONE; M1 DONE; M1.1 DONE/REVIEWED; M2 DONE/REVIEWED; M3 DONE/REVIEWED; **M4 IN PROGRESS**
- **Last merged/revalidated task:** `T-402 — Idempotency store`
- **Current task:** `T-403 — Output policy/redaction` — **DONE / exact-head external review pending**
- **Initial implementation checkpoint:** `0195875b5f2d5d9646407337ea54df52fe4c8bb3`
- **Initial implementation workflow:** `34356958945` — **7/7 green**
- **Exact verified code checkpoint:** `d58230d2f91f5d3ae89f9bd89d479dbaf27784e6`
- **Exact-head workflow:** `34368063289` — **7/7 green**
- **PHP evidence:** **431 tests / 2072 assertions** across PHP 8.3/8.4 and Illuminate 12/13, with MySQL 8.4 service coverage
- **Browser isolation:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract:** `python scripts/validate.py` green; frozen `spec/0.1/**` unchanged
- **Decision:** `D-046 — ACCEPTED`
- **External review:** CodeRabbit reviewed through `d98f1394f3ccbafe99016dd9cf1b4a3b5e1a4b70` with no actionable comments; exact-head review still required after later replay hardening
- **Next gate:** exact-head external review on PR #3; merge remains a separate explicit gate
- **T-404:** not started

## T-403 — Implemented trust boundary

```text
execution or completed idempotency replay
        │
        ▼
output policy
        │
        ├── normal output
        │       └── exact pass-through
        │
        └── sensitive output
                │
                ├── no redactor ───────────────┐
                ├── explicit withhold ─────────┤
                ├── redactor exception ────────┤
                │                               ▼
                │                         remove raw output
                │                               ↓
                │                     output_policy_failed
                │                               ↓
                │                      static public failed
                │                               ↓
                │                            audit
                │
                └── explicit release(value)
                        ↓
                  released value only
                        ↓
                       audit
```

### Locked invariants

1. Core does not guess sensitive fields or perform heuristic redaction.
2. `outputSensitivity=normal` is an exact pass-through and does not invoke the sensitive redactor.
3. `outputSensitivity=sensitive` has no implicit raw-output fallback. Disclosure requires an explicit trusted `OutputRedactionResult::release(...)` decision.
4. `release(null)` is a valid successful output and remains distinct from `withhold()` through explicit output presence tracking.
5. Missing redactor, `withhold()`, and redactor exceptions fail closed as `output_policy_failed`.
6. Fail-closed handling removes raw output from immutable pipeline state before the halted outcome reaches audit/finalization.
7. Output-policy entry without an execution/replay output is an internal pipeline invariant violation, not a public policy result.
8. The redactor receives exact `ActionDefinition`, raw execution/replay output, and restricted `OutputPolicyContext`; generic invocation metadata and the full `InvocationContext` are not exposed through that policy port.
9. Public `output_policy_failed` maps to `failed`, with a static message and no propagated halt details, raw output, or exception text.
10. Confidentiality and content trust remain independent. `outputContentTrust=contains_untrusted_content` is preserved rather than inferred from redaction outcome.
11. T-402 completed replay remains pre-policy: it skips confirmation/execution but reruns the **current** output policy over stored pre-policy executor output.
12. Replay therefore does not freeze or replay a previously disclosed/redacted payload; current policy can produce a different safe projection.
13. A completed replay whose current policy withholds or throws remains idempotency-`completed`, does not re-execute application code, and may later recover through a policy-only retry when the current policy releases a safe projection.
14. Audit observes only the released post-policy value on success or output-free state on disclosure failure.
15. `spec/0.1/**` remains unchanged.
16. T-404 structured audit persistence remains a separate task.

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
Initial PR review head:      d98f1394f3ccbafe99016dd9cf1b4a3b5e1a4b70 — CodeRabbit: no actionable comments
Hardening CI RED:            9b255cade7e60261e9a3407a579dd40bd06586d4 / 34361584554 — PHP matrix failed in new replay-recovery fixture
Fixture fix GREEN:           d58230d2f91f5d3ae89f9bd89d479dbaf27784e6 / 34368063289 — 7/7 green
PHP exact-head:              431 tests / 2072 assertions
Browser exact-head:          TypeScript typecheck + 103/103 Vitest tests
Contract exact-head:         green; frozen spec/0.1 unchanged
```

## Hardening failure and closure

The replay-recovery hardening added after the first external review exposed a test-fixture construction error rather than a production redaction defect. `OutputPolicyReplayFailureRecoveryIntegrationTest` instantiated `ActionDefinition` without the mandatory `contextRequirements` argument, so every PHP matrix job failed before the new scenario reached production code.

The fixture was aligned with the canonical constructor using `contextRequirements: []` only. No production behavior changed. The next exact-head run passed all seven jobs and the PHP suite increased to 431 tests / 2072 assertions.

The added recovery scenario now proves both `withhold()` and redactor-exception paths on a completed idempotency replay: the failed disclosure does not re-execute the application action or reopen/downgrade the completed idempotency record, while a later retry can reapply current policy and release a safe projection from stored pre-policy output.

## Self-review

- Branch remains ahead-only from exact T-402 closure base; no merge-base drift was found.
- Diff remains limited to D-046/design-plan/status documentation, Laravel output-policy contracts/stage/result mapping/state sanitization, and focused unit/integration tests.
- `spec/0.1/**` has no diff.
- Fail-closed raw-output removal occurs before the pipeline constructs the halted outcome observed by the auditor.
- Redactor exception handling is intentionally scoped to the redactor invocation and does not broadly swallow unrelated pipeline failures.
- Existing T-402 replay orchestration is preserved; integration proves current policy reruns over stored pre-policy output.
- Replay disclosure failure recovery is policy-only: idempotency remains completed and the executor call count remains one.
- The final CI defect was isolated to test setup and fixed without changing production code.

## Decisions

- D-032 ACCEPTED — confidentiality and content trust are independent output dimensions.
- D-045 ACCEPTED — idempotency persists/replays pre-output-policy executor output and reruns current output policy.
- **D-046 ACCEPTED — sensitive disclosure is explicit, trusted, fail-closed, and raw output is removed before audit/finalization on policy failure.**

## Known boundaries / non-claims

- T-403 does not define application-specific sensitive fields or a generic heuristic masking algorithm; applications implement `SensitiveOutputRedactor`.
- T-403 does not persist structured audit events; T-404 remains separate.
- T-403 does not change wire schemas or promote new `spec/0.1` fields.
- The output-policy context is a trusted typed runtime view, not caller metadata authority.
- CodeRabbit's previous review coverage ended at `d98f1394…`; the later replay-recovery hardening and its fixture correction require exact-head review before T-403 can be marked REVIEWED or merge-ready.

## Next boundary

**T-403 implementation and exact-head CI are green. PR #3 remains open. The next action is external review covering the current branch head; merge is a separate explicit gate and T-404 must not start automatically.**
