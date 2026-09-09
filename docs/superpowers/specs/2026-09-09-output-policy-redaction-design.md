# T-403 Output Policy / Redaction Design

**Status:** ACCEPTED DESIGN — implementation pending

**Task:** `T-403 — Output policy/redaction`

**Base:** `main@08a9862923d60f4e08b1732c3e11053e5b7bb74e`

**Feature branch:** `feat/output-policy-redaction`

## 1. Purpose

T-403 turns the existing `OutputPolicy` pipeline placeholder into a real production trust control for the Laravel reference runtime.

The task enforces the confidentiality dimension already present in the frozen v0.1 Action Definition contract:

- `outputSensitivity=normal`
- `outputSensitivity=sensitive`

It deliberately does not reinterpret or collapse the independent `outputContentTrust` dimension defined by D-032.

The implementation must preserve the current pipeline order:

```text
validation
  → authorization
  → idempotency
  → confirmation
  → execution
  → output policy
  → audit finalizer
```

A completed T-402 idempotency replay continues to reuse stored pre-output-policy executor output, skip confirmation/application execution, and rerun the current output policy before finalization.

## 2. Problem statement

`ActionDefinition.outputSensitivity` already classifies output confidentiality, and the frozen contract states that `sensitive` output is subject to output policy/redaction. The Laravel runtime currently has only an `OutputPolicy` pipeline stage placeholder and `ActionResultNormalizer` intentionally forwards the pipeline output unchanged.

Therefore a sensitive action can currently produce raw application output without a runtime confidentiality gate.

T-403 must make sensitive disclosure fail closed without teaching SurfaceRelay application-specific secret-detection heuristics.

## 3. Non-goals

T-403 does not implement:

- automatic secret/PII detection;
- magic field-name handling such as `password`, `token`, `ssn`, `email`, or `phone`;
- regex-based data-loss prevention;
- an `extensions`-based JSON-path redaction DSL;
- output-schema validation or schema transformation;
- encryption-at-rest changes;
- T-404 structured audit persistence;
- browser/WebMCP payload rewriting;
- a new surface authorization model;
- changes to `spec/0.1/**`;
- changes to the D-032 `outputContentTrust` semantics.

## 4. Design decision

Use a trusted, explicitly configured redactor boundary rather than guessing which fields are secret.

`normal` output passes through unchanged.

`sensitive` output requires a configured trusted `SensitiveOutputRedactor` implementation. The runtime passes the exact Action Definition, raw executor output, and a restricted trusted output-policy context to the redactor. The redactor must explicitly return either a safe releasable value or a withhold decision.

Conceptually:

```text
raw execution/replay output
        ↓
OutputPolicyStage
        ├── outputSensitivity = normal
        │       ↓
        │   exact pass-through
        │
        └── outputSensitivity = sensitive
                ↓
        SensitiveOutputRedactor
                ├── release(safeOutput)
                │       ↓
                │   replace pipeline output
                │
                └── withhold / exception / unavailable
                        ↓
                   clear raw output
                        ↓
                   output_policy_failed
```

SurfaceRelay defines the enforcement boundary, not an application-specific redaction algorithm.

## 5. Components

### 5.1 `SensitiveOutputRedactor`

A small protocol-neutral Laravel runtime interface owns application-specific sensitive-output transformation.

Target contract:

```php
interface SensitiveOutputRedactor
{
    public function redact(
        ActionDefinition $definition,
        mixed $rawOutput,
        OutputPolicyContext $context,
    ): OutputRedactionResult;
}
```

The interface must not depend on Livewire, Filament, WebMCP, MCP, HTTP, browser APIs, or driver concepts.

### 5.2 `OutputPolicyContext`

The redactor must not receive the full `InvocationContext` directly.

`InvocationContext` contains authority and non-authoritative invocation labels/metadata together. In particular, `surface`, `correlationId`, `idempotencyKey`, and generic `metadata` must never accidentally become sensitive-output disclosure authority.

`OutputPolicyContext` therefore exposes only the trusted runtime entries already materialized in `InvocationContext::allTrusted()`.

It does not expose:

- ordinary caller input;
- generic invocation metadata;
- surface name;
- correlation ID;
- idempotency key;
- confirmation receipt;
- binding ID.

The redactor still receives `ActionDefinition`, whose declared output sensitivity/content-trust metadata is part of the core contract.

### 5.3 `OutputRedactionResult`

The redactor returns a narrow typed decision, not arbitrary control flags.

Required decisions:

```text
release(safeOutput)
withhold()
```

`release()` may legitimately contain `null`, therefore release/withhold must be represented explicitly rather than inferred from nullability.

### 5.4 `OutputPolicyStage`

A production `ActionPipelineStageHandler` for `ActionPipelineStage::OutputPolicy` applies the rules above.

The stage must require an existing execution/replay output. Reaching output policy without output remains a pipeline invariant/configuration failure rather than silently succeeding.

For `normal` sensitivity, the stage returns the state unchanged and must not invoke the sensitive redactor.

For `sensitive` sensitivity:

1. a redactor must be configured;
2. the stage calls it with the raw output and restricted trusted context;
3. `release()` replaces the pipeline output with the safe output;
4. `withhold()` clears the raw output and halts with `output_policy_failed`;
5. any redactor exception clears the raw output and halts with the same static failure code;
6. no raw output, exception text, exception object, stack trace, redaction rule, or redactor-internal detail is copied into the halt.

## 6. Pipeline state sanitization

Public result secrecy is insufficient because the existing `ActionPipelineAuditor` receives the final `ActionPipelineOutcome`, including its `ActionPipelineState`.

Therefore T-403 must add a state transition capable of explicitly removing output, e.g. `ActionPipelineState::withoutOutput()`.

On sensitive policy failure:

```text
before policy:
  hasOutput = true
  output = <raw sensitive value>

before finalization/audit:
  hasOutput = false
  output = null
```

The raw sensitive value must be unreachable from the outcome passed to the auditor.

On successful sensitive redaction, the auditor sees only the released safe output.

This is a T-403 requirement even though durable audit persistence belongs to T-404.

## 7. Result semantics

D-031 defines:

- `rejected` as a deliberate pre-success refusal;
- `failed` as runtime/execution failure;
- `succeeded` as completed execution;
- `confirmation_required` as waiting for trusted confirmation.

Output policy runs after application execution or after replaying a previously completed execution. Therefore inability to disclose output safely is not a pre-success refusal.

T-403 introduces the core failure code:

```text
output_policy_failed
```

`ActionResultNormalizer` maps this halt to a static failed result:

```json
{
  "status": "failed",
  "correlationId": "<current invocation correlation id>",
  "error": {
    "code": "output_policy_failed",
    "message": "The action completed, but its output could not be safely disclosed."
  }
}
```

No public details are attached.

The wording intentionally communicates that execution may already have occurred. It must not imply rollback, refusal-before-execution, or safe retry of the business action.

## 8. Idempotency interaction

T-402 persists deterministic pre-output-policy executor output.

That behavior remains unchanged.

For an active exact completed retry:

```text
current validation/authorization
        ↓
idempotency replay
        ↓
stored pre-policy raw executor output
        ↓
current OutputPolicyStage
        ↓
current redaction decision
```

Consequences:

- policy changes apply to historical completed retries;
- sensitive output is never persisted in a post-policy form solely to bypass current policy;
- policy failure does not reopen or invalidate a completed idempotency record;
- retrying after policy failure never re-executes application code for the same active completed key/intent;
- a later retry may succeed if the current redactor/policy can safely release the stored output.

T-403 must add integration coverage proving this behavior.

## 9. Output content trust remains independent

D-032 remains authoritative.

`outputSensitivity` answers confidentiality/disclosure policy.

`outputContentTrust` answers whether downstream agent consumers must treat returned content as untrusted.

Examples:

```text
normal + trusted_application_data
sensitive + trusted_application_data
normal + contains_untrusted_content
sensitive + contains_untrusted_content
```

All four combinations remain valid.

Redacting a `sensitive + contains_untrusted_content` output does not upgrade it to trusted content. The Action Definition continues to carry `contains_untrusted_content`, and existing surface projections remain responsible for their current untrusted-content hint behavior.

T-403 must not modify the browser projection semantics established by D-036.

## 10. Configuration failure behavior

A runtime that allows sensitive actions but does not supply a sensitive redactor must fail closed at output policy.

No permissive default redactor will be supplied.

The runtime must not implement a default "return raw value" fallback for sensitive output.

Whether constructor-time configuration validation can catch a missing redactor is secondary; invocation-time enforcement remains mandatory because the Action Definition classification is resolved per invocation.

## 11. Output schema boundary

`ActionDefinition.outputSchema` is not currently a general runtime output validator. T-403 does not create one.

A configured `SensitiveOutputRedactor` is responsible for returning an application-appropriate safe representation. If an application wants the redacted value to continue satisfying its declared `outputSchema`, that requirement belongs to the application/redactor until a separate output-schema validation task is explicitly designed.

T-403 must not silently infer or mutate schema definitions.

## 12. Exception and secret handling

Sensitive redaction is a security boundary.

Redactor failures must not propagate arbitrary redactor exception messages into public results or halt details.

The implementation must also avoid preserving the raw sensitive output in:

- `ActionPipelineState` after policy failure;
- `ActionPipelineHalt.details`;
- public `ActionError.details`;
- automatically generated result `meta`;
- output-policy provenance/debug structures introduced by this task.

Application/runtime logging outside SurfaceRelay remains the host application's responsibility, but SurfaceRelay must not deliberately log or serialize the raw sensitive output as part of T-403.

## 13. Security invariants

T-403 locks these invariants:

1. `normal` output passes through exactly and never invokes the sensitive redactor.
2. `sensitive` output is never released raw by the reference runtime.
3. Sensitive disclosure requires an explicit trusted redactor release decision.
4. Missing redactor, withhold, or redactor failure fail closed.
5. Caller input/metadata cannot grant disclosure authority.
6. Generic `InvocationContext.metadata`, surface, correlation ID, idempotency key, binding ID, and confirmation receipt are absent from `OutputPolicyContext`.
7. Policy failure after execution maps to `failed`, not `rejected`.
8. Raw sensitive output is removed from pipeline state before audit finalization on policy failure.
9. Successful sensitive redaction replaces raw output before audit finalization.
10. Public `output_policy_failed` is static and contains no secret/details.
11. D-032 content-trust classification is preserved independently.
12. T-402 completed replay reruns current output policy without re-execution.
13. `spec/0.1/**` remains unchanged.

## 14. Required verification

Minimum automated coverage:

1. normal output exact pass-through;
2. normal output never invokes a configured sensitive redactor;
3. sensitive release exposes only the redacted/safe value;
4. sensitive release of legitimate null remains a successful released output;
5. sensitive output with no redactor fails closed;
6. `withhold()` produces `output_policy_failed` and no data;
7. redactor exception produces the same static failure and leaks no exception/raw output;
8. auditor sees only redacted output on success;
9. auditor sees no raw output on policy failure;
10. caller metadata cannot influence trusted `OutputPolicyContext`;
11. sensitive + untrusted-content classification remains independent;
12. completed idempotency replay invokes current redactor again;
13. redaction failure during replay does not re-execute the application executor;
14. a later replay may succeed when policy/redactor becomes able to release the same stored pre-policy output;
15. full PHP matrix remains green;
16. browser tests remain unchanged/green;
17. `python scripts/validate.py` remains green;
18. `spec/0.1/**` has no diff.

Because output redaction is security-sensitive under `AGENTS.md`, negative tests are mandatory.

## 15. Expected code surface

Likely new files:

```text
packages/laravel/src/OutputPolicy/SensitiveOutputRedactor.php
packages/laravel/src/OutputPolicy/OutputPolicyContext.php
packages/laravel/src/OutputPolicy/OutputRedactionResult.php
packages/laravel/src/OutputPolicy/OutputPolicyStage.php
```

Likely modified files:

```text
packages/laravel/src/Runtime/Pipeline/ActionPipelineState.php
packages/laravel/src/Result/CoreActionErrorCode.php
packages/laravel/src/Result/ActionResultNormalizer.php
packages/laravel/tests/Fixtures/PrepList/PrepListTestPipeline.php
packages/laravel/tests/Unit/...
packages/laravel/tests/Integration/...
docs/DECISION-REGISTER.md
docs/GLOSSARY.md
docs/THREAT-MODEL.md
CONFORMANCE.md
TASKS.md
STATUS.md
REVIEW_REQUEST.md
```

Exact file decomposition may be refined in the implementation plan, but the trust boundaries in this design are normative for T-403.

## 16. Accepted decision

**D-046 — Sensitive output disclosure is fail-closed and runtime-policy-driven. SurfaceRelay does not guess sensitive fields. Sensitive output is released only by an explicit trusted redactor decision; policy failure after execution is a `failed` result, and raw sensitive output is removed from pipeline state before audit/finalization. Output content trust remains an independent D-032 dimension.**

## 17. Completion boundary

T-403 is complete only when the Laravel reference runtime enforces the above rules, required negative/integration tests pass across the supported matrix, documentation is updated, `spec/0.1` remains unchanged, and external review is prepared.

Completion of T-403 does not start T-404 automatically.
