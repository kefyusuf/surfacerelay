# Prep List Shared ActionBus E2E Implementation Plan

**Goal:** Complete T-204 by proving, with real Livewire 4/Testbench integration, that a normal human Livewire action and a path derived from a T-203 RuntimeBinding call the same explicitly exposed component method, shared ActionBus, production execution stage, and single Prep List business mutation.

**Spec:** `docs/design/prep-list-shared-actionbus-e2e.md`

## Global constraints

- Do not modify `spec/0.1`.
- Do not create an agent-only controller, REST endpoint, MCP endpoint, or business handler.
- Do not implement the M3 browser driver, `Livewire.find()`, `$wire.$call()`, WebMCP registration, DriverRegistry, or stale-target resolution.
- Keep Livewire/Testbench development-only dependencies; production `src/` additions must remain protocol-neutral.
- Preserve `prep_list.add_item@1` and its `browser_session` context requirement.
- Use real package validation and authorization stages where they already exist.
- Keep confirmation/idempotency/output-policy placeholders test-only; do not add production allow-through defaults.
- Stop before M3/T-301.

## Task 1 — Development integration harness

Update `packages/laravel/composer.json`:

```json
"require-dev": {
  "phpunit/phpunit": "^11.0",
  "livewire/livewire": "^4.4",
  "orchestra/testbench": "^10.0|^11.0"
}
```

Because this is a reusable library and the supported CI intentionally resolves multiple Laravel/Testbench generations, remove `packages/laravel/composer.lock` rather than retaining a lock that represents only one matrix cell.

Update the PHP CI matrix with an explicit matching Testbench major:

```text
Illuminate 12 → orchestra/testbench ^10.0
Illuminate 13 → orchestra/testbench ^11.0
```

Each cell must still run the entire PHPUnit suite.

Commit: `test(laravel): add Livewire integration harness`.

## Task 2 — Production execution port and stage (TDD)

Create RED tests first:

```text
packages/laravel/tests/Unit/ActionExecutionStageTest.php
```

Acceptance:

- `ActionExecutionStage::stage()` is exactly `Execution`;
- delegates exactly once to `ActionExecutor`;
- passes the exact ActionDefinition object;
- passes the current pipeline input (including transformed/validated input);
- passes the exact trusted InvocationContext object;
- executor return becomes pipeline output;
- `null` output still sets `hasOutput=true`;
- executor exceptions propagate unchanged.

Then create:

```text
packages/laravel/src/Contracts/ActionExecutor.php
packages/laravel/src/Runtime/Pipeline/ActionExecutionStage.php
```

No generic executor registry or fallback routing.

Commit RED: `test(runtime): define execution-stage behavior`.
Commit GREEN: `feat(runtime): add action execution stage`.

## Task 3 — Real Prep List fixture and Livewire E2E (TDD)

Create test fixtures only:

```text
packages/laravel/tests/Fixtures/PrepList/
├── PrepListStore.php
├── AddPrepListItem.php
├── PrepListActionExecutor.php
├── PrepListActionGateway.php
├── PrepListInvocationContextFactory.php
├── PrepListAuthorizer.php
├── PrepListComponent.php
└── PrepListTestPipeline.php
```

`PrepListStore`
- in-memory ordered state;
- deterministic `item-N` IDs;
- no SurfaceRelay behavior.

`AddPrepListItem`
- only business mutation;
- tracks invocation count for proof.

`PrepListActionExecutor`
- implements `ActionExecutor`;
- supports exact `prep_list.add_item@1` only;
- delegates to `AddPrepListItem` with validated `name`;
- unknown identity fails loudly.

`PrepListInvocationContextFactory`
- creates trusted `BrowserSession` entry with explicit provenance;
- caller input never provides browser-session authority.

`PrepListAuthorizer`
- explicit fixture `ActionAuthorizer`;
- counts authorization calls;
- allows only the exact Prep List action.

`PrepListTestPipeline`
- wires real `InMemoryActionRegistry`;
- real `LaravelInputValidationStage` with exact rules `name => required|string|min:1`;
- real `AuthorizationStage`;
- TEST-ONLY pass-through Confirmation, Idempotency and OutputPolicy handlers;
- real `ActionExecutionStage`;
- TEST-ONLY auditor.

`PrepListActionGateway`
- constructs exact `ActionCall`;
- dispatches the shared `ActionBus`;
- requires completed outcome for the happy-path fixture;
- returns raw output.

`PrepListComponent`
- extends real `Livewire\Component`;
- `#[ExposeAction(id: 'prep_list.add_item', version: 1)]` on `addItem`;
- receives `PrepListActionGateway` through `boot()` lifecycle injection;
- contains no direct store/business mutation;
- renders minimal valid markup.

Create integration test:

```text
packages/laravel/tests/Integration/PrepListLivewireE2ETest.php
```

Use `Orchestra\Testbench\TestCase` and real Livewire testing APIs.

RED acceptance set:

1. Human path:
   `Livewire::test(PrepListComponent::class)->call('addItem', 'passport')`
   produces exactly one item through `AddPrepListItem`.
2. Agent-derived path:
   mount component, obtain real instance, run `LivewireBindingProducer`, verify exact component ID and `addItem` target, then call exactly `$binding->target['method']` through the same real Livewire testable.
3. Fresh isolated human and agent-derived runs with same input produce equivalent final state.
4. `AddPrepListItem` is invoked once per interaction.
5. Authorization is traversed.
6. BrowserSession exists as trusted context; it is absent from action input.
7. Invalid empty name is stopped before execution; store and business-service call count stay unchanged.
8. Binding production identifies exact `prep_list.add_item@1`; no method guessing.
9. Component uses lifecycle `boot()` injection and no constructor/service-locator action wiring.

Commit RED: `test(livewire): define Prep List shared-path E2E`.
Commit GREEN fixture: `test(livewire): add Prep List shared ActionBus fixture`.

## Task 4 — Decision, examples and checkpoint

Add D-034 ACCEPTED:

> Livewire human interaction and agent binding execution converge at the same explicitly exposed Livewire component method and shared ActionBus/application action. SurfaceRelay does not create an agent-only application endpoint or duplicate business mutation path; the browser driver only selects and invokes the exact existing binding target.

Update static Prep List documentation only where needed to explain that fixture binding/component IDs are runtime-issued and therefore static JSON IDs are illustrative.

Update:

```text
TASKS.md
STATUS.md
REVIEW_REQUEST.md
docs/DECISION-REGISTER.md
```

Mark:

```text
T-204 DONE (pending final external-style review)
M2 implementation complete (pending T-204 review)
M3/T-301 not started
```

Record actual PHPUnit/assertion counts and CI evidence.

## Verification

```bash
cd packages/laravel
composer update --no-interaction --prefer-dist
composer validate --strict
composer test -- --filter ActionExecutionStageTest
composer test -- --filter PrepListLivewireE2ETest
composer test
cd ../..
python scripts/validate.py

cd packages/browser-runtime
npm ci
npm run typecheck
npm test
cd ../..
```

GitHub Actions must pass:

```text
contract
PHP 8.3 × Illuminate 12 × Testbench 10 × Livewire 4.4
PHP 8.3 × Illuminate 13 × Testbench 11 × Livewire 4.4
PHP 8.4 × Illuminate 12 × Testbench 10 × Livewire 4.4
PHP 8.4 × Illuminate 13 × Testbench 11 × Livewire 4.4
php-lint
browser
```

Perform final `main...feat/prep-list-e2e` external-style review. If clean, fast-forward `main`, verify merged-main CI, record reviewed main checkpoint, mark M2 complete, and stop before M3/T-301.
