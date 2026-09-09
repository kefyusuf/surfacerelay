# T-403 Output Policy / Redaction Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enforce fail-closed sensitive-output disclosure in the Laravel reference runtime while preserving normal output pass-through, D-032 content-trust independence, T-402 replay semantics, and audit-state secrecy.

**Architecture:** Add a focused `OutputPolicy` package boundary containing a restricted trusted context, explicit redaction decision type, application-supplied sensitive redactor interface, and production `OutputPolicyStage`. Sensitive output can leave the runtime only after an explicit redactor `release(...)`; missing/withheld/failed policy clears raw output before finalization and maps to static `failed/output_policy_failed`. Completed idempotency replay continues to load stored pre-policy output and reruns the current output policy without re-executing application code.

**Tech Stack:** PHP 8.3+, PHPUnit 11, existing immutable `ActionPipelineState`, `ActionBus`, T-402 idempotency runtime, Illuminate 12/13 test matrix, MySQL 8.4 CI service, browser Vitest/TypeScript validation, repository contract validator.

**Spec:** `docs/superpowers/specs/2026-09-09-output-policy-redaction-design.md`

## Global Constraints

- Work only on `T-403`; do not implement T-404 structured audit persistence, Filament output UI, a DLP engine, or output-schema validation.
- Base is `main@08a9862923d60f4e08b1732c3e11053e5b7bb74e`; feature branch is `feat/output-policy-redaction`.
- `spec/0.1/**` remains byte-for-byte unchanged.
- Preserve pipeline order: `input_validation -> authorization -> idempotency -> confirmation -> execution -> output_policy -> audit finalizer`.
- `outputSensitivity=normal` must pass the exact output through unchanged and must not invoke the sensitive redactor.
- `outputSensitivity=sensitive` must never use an automatic permissive/raw pass-through. Disclosure requires an explicit trusted `release(...)` decision from the configured redactor.
- SurfaceRelay does not attempt to prove that a trusted redactor materially changed the raw value; the trust boundary is explicit application-controlled release, not heuristic comparison.
- Missing redactor, explicit withhold, or redactor failure must fail closed.
- `OutputPolicyContext` exposes only trusted runtime entries from `InvocationContext::allTrusted()` and must not expose ordinary input, generic metadata, surface, correlation ID, idempotency key, binding ID, or confirmation receipt.
- `outputContentTrust` remains an independent D-032 dimension. T-403 must not change D-036 browser hint projection.
- Output-policy failure occurs after execution/replay and therefore maps to `failed`, never `rejected`.
- Public failure code is exactly `output_policy_failed` with static public copy and no details.
- `output_policy_failed` does not imply rollback, no side effect, safe business retry, or executor non-execution.
- On sensitive policy failure, raw output must be removed from `ActionPipelineState` before `ActionPipelineAuditor::record(...)` receives the final outcome.
- On sensitive policy success, the final state/auditor/public result must contain only the redactor-released value.
- No redactor exception text/object/stack trace, raw output, redaction rules, trusted identities, or runtime internals may be copied to halt details/public errors/meta by T-403.
- T-402 active completed replay remains `stored pre-policy output -> current output policy`; policy failure must not reopen/re-execute the completed business action.
- Follow strict TDD for each behavior change: failing test first, observe intended RED, minimal GREEN, rerun focused/full relevant tests, then commit.

## File Structure

### New output-policy production files

- `packages/laravel/src/OutputPolicy/SensitiveOutputRedactor.php` — application-supplied trusted transformation/release boundary.
- `packages/laravel/src/OutputPolicy/OutputPolicyContext.php` — restricted view of trusted runtime context only.
- `packages/laravel/src/OutputPolicy/OutputRedactionResult.php` — explicit `release(...) | withhold()` result with null-safe release semantics.
- `packages/laravel/src/OutputPolicy/OutputPolicyStage.php` — production `ActionPipelineStage::OutputPolicy` handler.

### Existing production files modified

- `packages/laravel/src/Runtime/Pipeline/ActionPipelineState.php` — add explicit output removal transition.
- `packages/laravel/src/Result/CoreActionErrorCode.php` — add `OUTPUT_POLICY_FAILED`.
- `packages/laravel/src/Result/ActionResultNormalizer.php` — map the new post-execution halt to static `failed`.

### Tests created/modified

- Create `packages/laravel/tests/Unit/OutputPolicyContextTest.php`.
- Create `packages/laravel/tests/Unit/OutputRedactionResultTest.php`.
- Create `packages/laravel/tests/Unit/OutputPolicyStageTest.php`.
- Modify `packages/laravel/tests/Unit/ActionPipelineStateTest.php` if present; otherwise add state-removal coverage to `OutputPolicyStageTest` without creating a broad unrelated test file.
- Modify `packages/laravel/tests/Unit/ActionResultNormalizerTest.php`.
- Create `packages/laravel/tests/Integration/OutputPolicyPipelineIntegrationTest.php`.
- Modify `packages/laravel/tests/Integration/IdempotencyPipelineIntegrationTest.php` for current-redactor replay proof.
- Modify `packages/laravel/tests/Fixtures/PrepList/PrepListTestPipeline.php` to replace the test-only output-policy pass-through with the production stage and an explicit fixture redactor suitable for that vertical.
- Modify affected PrepList integration tests only where the production stage changes harness construction.

### Documentation modified at closure

- `docs/DECISION-REGISTER.md` — keep D-046 aligned with implemented behavior.
- `docs/GLOSSARY.md` — define output policy/redaction/release semantics.
- `docs/THREAT-MODEL.md` — document post-execution disclosure failure and audit-state leak mitigation.
- `CONFORMANCE.md` — record executable sensitive-output policy behavior.
- `TASKS.md` — mark T-403 complete only after verification.
- `STATUS.md` — exact implementation/review evidence and next task T-404 not started.
- `REVIEW_REQUEST.md` — external review handoff with security checklist and exact-head evidence.

---

## Task 1: Add explicit redaction contracts and restricted trusted context

**Files:**
- Create: `packages/laravel/src/OutputPolicy/SensitiveOutputRedactor.php`
- Create: `packages/laravel/src/OutputPolicy/OutputPolicyContext.php`
- Create: `packages/laravel/src/OutputPolicy/OutputRedactionResult.php`
- Create/Test: `packages/laravel/tests/Unit/OutputPolicyContextTest.php`
- Create/Test: `packages/laravel/tests/Unit/OutputRedactionResultTest.php`

**Target contracts:**

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

```php
final readonly class OutputPolicyContext
{
    /** @param list<TrustedContextEntry> $trusted */
    private function __construct(private array $trusted) {}

    public static function fromInvocationContext(InvocationContext $context): self;

    /** @return list<TrustedContextEntry> */
    public function allTrusted(): array;

    public function has(ContextRequirement $requirement): bool;
    public function get(ContextRequirement $requirement): ?TrustedContextEntry;
}
```

Do not expose the source `InvocationContext` object or any methods that reveal its generic metadata/surface/correlation/idempotency values.

```php
final readonly class OutputRedactionResult
{
    public static function release(mixed $output): self;
    public static function withhold(): self;

    public function isReleased(): bool;
    public function output(): mixed; // valid only when released; fail loudly otherwise
}
```

Use an explicit boolean/discriminant so `release(null)` is distinct from `withhold()`.

- [ ] **Step 1: Write RED tests for restricted policy context**

Build an `InvocationContext` containing actor/tenant trusted entries plus caller-controlled metadata such as:

```php
metadata: [
    'role' => 'admin',
    'tenant' => 'caller-tenant',
    'secret' => 'caller-metadata',
],
idempotencyKey: 'raw-retry-key',
```

Assert `OutputPolicyContext` exposes the trusted entries in canonical runtime order, but its public API has no accessors for metadata, surface, correlation ID or idempotency key. Also prove a metadata key named `tenant` cannot appear as trusted tenant authority.

- [ ] **Step 2: Run the focused context test and verify RED**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/OutputPolicyContextTest.php
```

Expected RED: `OutputPolicyContext` is absent.

- [ ] **Step 3: Implement minimal `OutputPolicyContext`**

Copy only the `TrustedContextEntry` list returned by `InvocationContext::allTrusted()` into the new value object. Implement `has/get/allTrusted` by requirement identity; do not retain the full source `InvocationContext`.

- [ ] **Step 4: Rerun context tests and verify GREEN**

```bash
./vendor/bin/phpunit tests/Unit/OutputPolicyContextTest.php
```

- [ ] **Step 5: Write RED tests for release/withhold semantics**

Prove:

```php
$releasedNull = OutputRedactionResult::release(null);
self::assertTrue($releasedNull->isReleased());
self::assertNull($releasedNull->output());

$withheld = OutputRedactionResult::withhold();
self::assertFalse($withheld->isReleased());
```

Calling `output()` on withheld must fail loudly with a static internal exception/message and must not carry sensitive data.

- [ ] **Step 6: Run result tests and verify RED**

```bash
./vendor/bin/phpunit tests/Unit/OutputRedactionResultTest.php
```

Expected RED: result class is absent.

- [ ] **Step 7: Implement `OutputRedactionResult` and `SensitiveOutputRedactor`**

Keep the API narrow. Do not add policy names, redaction paths, regex rules, provenance, logging, surface awareness, or mutable configuration.

- [ ] **Step 8: Run both Task-1 test files and verify GREEN**

```bash
./vendor/bin/phpunit \
  tests/Unit/OutputPolicyContextTest.php \
  tests/Unit/OutputRedactionResultTest.php
```

- [ ] **Step 9: Commit Task 1**

```bash
git add packages/laravel/src/OutputPolicy packages/laravel/tests/Unit/OutputPolicyContextTest.php packages/laravel/tests/Unit/OutputRedactionResultTest.php
git commit -m "feat(laravel): add output redaction contracts"
```

---

## Task 2: Enforce sensitive output policy and erase raw output on failure

**Files:**
- Create: `packages/laravel/src/OutputPolicy/OutputPolicyStage.php`
- Modify: `packages/laravel/src/Runtime/Pipeline/ActionPipelineState.php`
- Create/Test: `packages/laravel/tests/Unit/OutputPolicyStageTest.php`

**Target stage shape:**

```php
final class OutputPolicyStage implements ActionPipelineStageHandler
{
    public function __construct(
        private readonly ?SensitiveOutputRedactor $sensitiveRedactor = null,
    ) {}

    public function stage(): ActionPipelineStage
    {
        return ActionPipelineStage::OutputPolicy;
    }
}
```

`null` redactor is permitted at construction so runtimes containing only normal actions are valid, but invocation of a sensitive action without one must fail closed.

Add:

```php
public function withoutOutput(): self
```

which returns identical definition/input/context/binding/confirmation/idempotency state with:

```text
hasOutput = false
output = null
```

- [ ] **Step 1: Write the first RED stage test for normal exact pass-through**

Given a normal definition and existing output object/array, assert the returned state is unchanged in output identity/value and a spy redactor is never invoked.

- [ ] **Step 2: Run focused stage test and verify RED**

```bash
./vendor/bin/phpunit tests/Unit/OutputPolicyStageTest.php --filter normal
```

Expected RED: `OutputPolicyStage` is absent.

- [ ] **Step 3: Implement stage identity + normal pass-through only**

Do not add sensitive handling yet.

- [ ] **Step 4: Rerun normal test and verify GREEN**

- [ ] **Step 5: Write RED sensitive-release tests**

Prove a configured redactor receives:

- exact `ActionDefinition`;
- exact raw output;
- restricted `OutputPolicyContext` containing trusted actor/tenant entries only.

Assert released output replaces raw state output. Include `release(null)` and verify `hasOutput=true`, `output=null`.

- [ ] **Step 6: Run sensitive-release tests and verify RED**

Expected RED: current stage does not call redactor/replace output.

- [ ] **Step 7: Implement sensitive release path**

Call the redactor exactly once and replace state via `withOutput($result->output())` only for explicit release.

- [ ] **Step 8: Verify sensitive release GREEN**

- [ ] **Step 9: Write RED fail-closed tests**

Cover separately:

1. sensitive + no redactor;
2. sensitive + `withhold()`;
3. sensitive + redactor throws an exception containing a secret marker.

For each, assert decision halts with exactly `output_policy_failed`, halt details are null/empty, and returned state has `hasOutput=false`, `output=null`. Assert the exception marker/raw output marker is absent from halt data.

- [ ] **Step 10: Run fail-closed tests and verify RED**

Expected RED: no `withoutOutput()` / no sensitive failure handling.

- [ ] **Step 11: Implement `ActionPipelineState::withoutOutput()` and fail-closed branch**

Catch redactor failures at the stage boundary; do not chain the original exception into a new public/pipeline exception. Convert unavailable/withheld/thrown policy into a static `ActionPipelineHalt(CoreActionErrorCode::OUTPUT_POLICY_FAILED)` only after first adding the new code in Task 3, or temporarily use the literal internal string during this RED/GREEN step and replace it immediately in Task 3 without committing an inconsistent public mapper.

Preferred implementation order within the same GREEN change: add the core constant now, but defer public normalizer mapping assertions to Task 3.

- [ ] **Step 12: Run full `OutputPolicyStageTest` and verify GREEN**

```bash
./vendor/bin/phpunit tests/Unit/OutputPolicyStageTest.php
```

- [ ] **Step 13: Commit Task 2**

```bash
git add packages/laravel/src/OutputPolicy/OutputPolicyStage.php packages/laravel/src/Runtime/Pipeline/ActionPipelineState.php packages/laravel/src/Result/CoreActionErrorCode.php packages/laravel/tests/Unit/OutputPolicyStageTest.php
git commit -m "feat(laravel): enforce sensitive output policy"
```

---

## Task 3: Map post-execution policy failure and prove audit/public secrecy

**Files:**
- Modify: `packages/laravel/src/Result/CoreActionErrorCode.php` if not already changed in Task 2.
- Modify: `packages/laravel/src/Result/ActionResultNormalizer.php`
- Modify/Test: `packages/laravel/tests/Unit/ActionResultNormalizerTest.php`
- Create/Test: `packages/laravel/tests/Integration/OutputPolicyPipelineIntegrationTest.php`

- [ ] **Step 1: Write RED normalizer test**

Construct a halted output-policy outcome using a state that has already had output removed. Assert normalization returns:

```php
[
    'status' => 'failed',
    'correlationId' => 'corr-output-policy',
    'error' => [
        'code' => 'output_policy_failed',
        'message' => 'The action completed, but its output could not be safely disclosed.',
    ],
]
```

Inject malicious halt details/secret markers and assert they are discarded completely.

- [ ] **Step 2: Run focused normalizer test and verify RED**

```bash
./vendor/bin/phpunit tests/Unit/ActionResultNormalizerTest.php --filter output_policy
```

Expected RED: unknown halt fails loudly because mapper does not yet recognize `output_policy_failed`.

- [ ] **Step 3: Implement static failed mapping**

Add no details and no meta. Keep all other reserved-code normalization unchanged.

- [ ] **Step 4: Rerun normalizer test and verify GREEN**

- [ ] **Step 5: Write RED full-pipeline auditor secrecy tests**

Use a recording auditor and real `ActionBus`/`OutputPolicyStage`.

Cases:

1. sensitive release: executor returns raw marker, redactor releases safe marker; completed outcome, auditor, and normalized public result contain only safe marker;
2. sensitive withhold: executor returns raw marker; final halted outcome/auditor state has no output, normalized public result is static failed/no data;
3. throwing redactor: exception contains another secret marker; neither raw marker nor exception marker appears in serialized public result or auditor outcome fields exposed by the test.

The test must prove `ActionPipelineAuditor::record(...)` sees the already-sanitized state, not a post-audit cleanup.

- [ ] **Step 6: Run integration test and verify RED**

```bash
./vendor/bin/phpunit tests/Integration/OutputPolicyPipelineIntegrationTest.php
```

Expected RED should identify missing mapper/stage sanitization integration, not test setup errors.

- [ ] **Step 7: Make minimal integration adjustments and verify GREEN**

Do not add durable audit storage; use the existing auditor contract only.

- [ ] **Step 8: Run relevant regression set**

```bash
./vendor/bin/phpunit \
  tests/Unit/ActionBusTest.php \
  tests/Unit/ActionResultNormalizerTest.php \
  tests/Unit/OutputPolicyStageTest.php \
  tests/Integration/OutputPolicyPipelineIntegrationTest.php
```

- [ ] **Step 9: Commit Task 3**

```bash
git add packages/laravel/src/Result packages/laravel/tests/Unit/ActionResultNormalizerTest.php packages/laravel/tests/Integration/OutputPolicyPipelineIntegrationTest.php
git commit -m "feat(laravel): fail closed on output disclosure errors"
```

---

## Task 4: Integrate current policy into replay and the shared Prep List vertical

**Files:**
- Modify/Test: `packages/laravel/tests/Integration/IdempotencyPipelineIntegrationTest.php`
- Modify: `packages/laravel/tests/Fixtures/PrepList/PrepListTestPipeline.php`
- Modify affected PrepList fixture/tests as narrowly required.

- [ ] **Step 1: Write RED idempotency replay test for current redactor**

Set up an idempotent sensitive action whose executor returns one raw deterministic payload. First dispatch uses redactor version A and succeeds. For exact active completed retry, switch/configure policy to redactor version B and assert:

```text
executor total calls = 1
redactor A calls = 1
redactor B calls = 1
retry data = B(redacted stored raw output)
```

The retry must keep its current correlation ID.

- [ ] **Step 2: Run replay test and verify RED**

```bash
./vendor/bin/phpunit tests/Integration/IdempotencyPipelineIntegrationTest.php --filter output_policy
```

Expected RED: integration harness still uses pass-through output policy or cannot swap/apply the current production stage.

- [ ] **Step 3: Wire real `OutputPolicyStage` into the replay integration harness and verify GREEN**

Preserve T-402 ordering and replay skip rules exactly.

- [ ] **Step 4: Write RED replay-failure/no-reexecution tests**

Prove:

1. first call executes once and completes idempotency record;
2. replay policy withholds/throws -> public `failed/output_policy_failed`;
3. executor call count remains 1;
4. same active replay later using a releasing redactor succeeds without executor re-execution.

- [ ] **Step 5: Run replay-failure test and verify RED**

- [ ] **Step 6: Implement only required harness/stage wiring and verify GREEN**

Do not mutate idempotency record state on policy failure.

- [ ] **Step 7: Replace PrepList test-only OutputPolicy pass-through**

Use production `OutputPolicyStage`. If current PrepList definitions are normal, pass `null` or an explicit test redactor without changing action definitions merely to exercise T-403. If a sensitive fixture exists, supply the smallest deterministic fixture redactor that releases the expected safe shape.

- [ ] **Step 8: Run PrepList + confirmation + idempotency integrations**

```bash
./vendor/bin/phpunit \
  tests/Integration/ConfirmationPipelineIntegrationTest.php \
  tests/Integration/IdempotencyPipelineIntegrationTest.php \
  tests/Integration/OutputPolicyPipelineIntegrationTest.php
```

Also run the exact PrepList test file(s) discovered in the fixture harness before editing.

- [ ] **Step 9: Commit Task 4**

```bash
git add packages/laravel/tests/Integration packages/laravel/tests/Fixtures/PrepList
git commit -m "test(laravel): integrate output policy with replay"
```

---

## Task 5: Documentation, full verification, status handoff, and review gate

**Files:**
- Modify: `docs/DECISION-REGISTER.md`
- Modify: `docs/GLOSSARY.md`
- Modify: `docs/THREAT-MODEL.md`
- Modify: `CONFORMANCE.md`
- Modify: `TASKS.md`
- Modify: `STATUS.md`
- Modify: `REVIEW_REQUEST.md`

- [ ] **Step 1: Update implemented-behavior documentation**

Record D-046 as implemented with these exact points:

- no automatic raw pass-through for sensitive output;
- explicit trusted release boundary;
- restricted trusted policy context;
- post-execution failure = `failed/output_policy_failed`;
- raw output removed before audit on policy failure;
- D-032 independence;
- T-402 replay reruns current policy and does not re-execute on policy failure.

Do not claim a generic DLP engine or schema validation.

- [ ] **Step 2: Update executable conformance wording**

Keep frozen schema references unchanged. Add Laravel runtime evidence for sensitive policy behavior only if backed by the new tests.

- [ ] **Step 3: Run focused T-403 test suite**

```bash
cd packages/laravel
./vendor/bin/phpunit \
  tests/Unit/OutputPolicyContextTest.php \
  tests/Unit/OutputRedactionResultTest.php \
  tests/Unit/OutputPolicyStageTest.php \
  tests/Unit/ActionResultNormalizerTest.php \
  tests/Integration/OutputPolicyPipelineIntegrationTest.php \
  tests/Integration/IdempotencyPipelineIntegrationTest.php
```

Record tests/assertions exactly from output.

- [ ] **Step 4: Run the full Laravel package suite**

```bash
./vendor/bin/phpunit
composer validate --strict
```

Record exact test/assertion counts.

- [ ] **Step 5: Run browser regression verification**

```bash
cd ../../browser-runtime
npm ci
npm run typecheck
npm test -- --run
```

Record exact Vitest count. No browser code should be required for T-403.

- [ ] **Step 6: Run contract validation from repository root**

```bash
cd ..
python scripts/validate.py
```

- [ ] **Step 7: Prove frozen spec has no diff**

```bash
git diff main...HEAD -- spec/0.1
```

Expected output: empty.

- [ ] **Step 8: Full diff security self-review**

Review for:

- any raw sensitive output retained in failure outcome state;
- any exception/detail leakage;
- caller metadata accidentally exposed to redactor authority;
- sensitive default/pass-through behavior;
- D-032 collapse or WebMCP projection changes;
- T-402 replay regression/re-execution;
- unrelated refactors/dependencies/spec changes.

- [ ] **Step 9: Update task/status/review docs only after all verification passes**

`TASKS.md`: T-403 DONE only after fresh evidence.

`STATUS.md`: include exact branch head, focused/full test evidence, browser/contract evidence, D-046, known boundaries, and `T-404 — not started`.

`REVIEW_REQUEST.md`: request explicit security review of output sanitization, exception leakage, caller metadata isolation, result taxonomy, and replay behavior.

- [ ] **Step 10: Commit closure docs**

```bash
git add docs/DECISION-REGISTER.md docs/GLOSSARY.md docs/THREAT-MODEL.md CONFORMANCE.md TASKS.md STATUS.md REVIEW_REQUEST.md
git commit -m "docs(t-403): record output policy evidence"
```

- [ ] **Step 11: Verify exact-head CI**

Use `.github/workflows/validate.yml` on the exact branch head. Required: all 7 jobs green, including PHP 8.3/8.4 × Illuminate 12/13 with MySQL 8.4, browser, lint, and contract validation.

- [ ] **Step 12: Prepare external review; do not merge**

Create/update the T-403 pull request only after exact-head CI is green. Run/request external review and resolve actionable findings TDD-first. Do not merge without explicit user permission.

---

## Definition of Done

T-403 may be called DONE/REVIEWED-ready only when all are true:

1. normal output exact pass-through is executable-tested;
2. sensitive output requires explicit trusted redactor release;
3. missing/withheld/thrown sensitive policy fails closed;
4. `release(null)` remains a successful explicit release;
5. `OutputPolicyContext` contains trusted entries only;
6. caller metadata/surface/correlation/idempotency/binding/receipt cannot become output-disclosure authority;
7. raw sensitive output is absent from failure state before audit finalization;
8. successful sensitive state contains only released output;
9. public failure is static `failed/output_policy_failed` with no details;
10. the failure copy does not imply rollback or safe business retry;
11. D-032 content-trust semantics/browser hint projection remain unchanged;
12. completed idempotency replay reruns current policy without re-execution;
13. replay policy failure leaves completed idempotency state closed and later policy-only retry can release safely;
14. PrepList harness uses the production output-policy stage instead of the old pass-through;
15. focused/full PHP, browser, and contract verification are green on exact head;
16. `spec/0.1/**` has no diff;
17. docs/status/review request contain exact evidence;
18. T-404 has not started automatically.
