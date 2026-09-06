# External Review Handoff

## Review target

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Base:** `main` at `44eb738bae54c8066e04ccc015edfea160de58f2`
- **Head branch:** `feat/prep-list-e2e`
- **Scope:** T-204 only — protocol-neutral application execution stage plus real Livewire/Testbench Prep List proof that human and binding-derived calls converge on one shared application path.
- **M2 status:** implementation complete; review pending.
- **M3/T-301:** not started.

## T-204 architecture under review

```text
Human Livewire call ───────────────┐
                                   ▼
                            PrepListComponent::addItem
                                   │
Binding-derived invocation ────────┘
                                   │
                                   ▼
                              ActionBus
                                   │
                                   ▼
                         ActionExecutionStage
                                   │
                                   ▼
                         PrepListActionExecutor
                                   │
                                   ▼
                           AddPrepListItem
                                   │
                                   ▼
                             PrepListStore
```

The binding-derived path is deliberately a server-side integration simulation of the future browser driver: the test produces a real T-203 RuntimeBinding from an actual Livewire component instance and calls exactly the binding's `target.method` through Livewire's real test harness. It does not implement or claim M3/T-304 browser execution.

## Production changes

Only these production runtime files are new:

```text
packages/laravel/src/Contracts/ActionExecutor.php
packages/laravel/src/Runtime/Pipeline/ActionExecutionStage.php
```

Review invariants:

1. `ActionExecutor` is protocol-neutral and knows no Livewire/browser/WebMCP vocabulary.
2. `ActionExecutionStage` is exactly the canonical `execution` stage.
3. It receives the exact resolved `ActionDefinition`, current pipeline input and exact trusted `InvocationContext`.
4. Executor return becomes pipeline output; `null` remains a legitimate executed output.
5. Application exceptions propagate unchanged.
6. No fallback executor registry, fuzzy action resolution, agent-only endpoint or surface-specific execution route exists.

## Development-only integration dependencies

`packages/laravel/composer.json` now carries:

```text
require-dev:
  livewire/livewire ^4.4
  orchestra/testbench ^10|^11
```

`livewire/livewire` is absent from production `require`.

The package-level Composer lock was removed because this repository intentionally validates multiple supported Laravel/Testbench generations rather than publishing an application lock as canonical library state.

CI explicitly validates:

```text
PHP 8.3 × Illuminate 12 × Testbench 10 × Livewire 4.4
PHP 8.3 × Illuminate 13 × Testbench 11 × Livewire 4.4
PHP 8.4 × Illuminate 12 × Testbench 10 × Livewire 4.4
PHP 8.4 × Illuminate 13 × Testbench 11 × Livewire 4.4
```

Current observed Livewire resolution is `v4.4.3`.

## Test-only Prep List fixture

All reference application code is under:

```text
packages/laravel/tests/Fixtures/PrepList/
```

Files:

```text
PrepListStore.php
AddPrepListItem.php
PrepListActionExecutor.php
PrepListAuthorizer.php
PrepListInvocationContextFactory.php
PrepListActionGateway.php
PrepListComponent.php
PrepListTestPipeline.php
```

Integration proof:

```text
packages/laravel/tests/Integration/PrepListLivewireE2ETest.php
```

### Shared mutation invariant

`AddPrepListItem` is the only business mutation service.

The real Livewire component does not mutate `PrepListStore` directly. It receives `PrepListActionGateway` through Livewire's real `boot()` lifecycle injection and its exposed method delegates to that gateway.

### Real production pipeline stages used

The fixture uses:

- `LaravelInputValidationStage`;
- `AuthorizationStage`;
- `ActionExecutionStage`;
- `ActionBus`.

Confirmation, idempotency and output-policy pass-through handlers plus the auditor are explicitly test-only harness pieces. T-204 must not be interpreted as completing M4 controls.

### Trusted context invariant

`prep_list.add_item@1` retains `browser_session` as a context requirement.

`PrepListInvocationContextFactory` creates a real trusted `BrowserSession` entry with explicit provenance. The E2E asserts the authorizer receives only validated `['name' => 'passport']` input and BrowserSession is absent from action input.

## E2E proof to verify

Please inspect these assertions directly:

1. Human path uses real `Livewire::test(...)->call('addItem', 'passport')`.
2. Human path produces exactly one `item-1` mutation through `AddPrepListItem`.
3. Authorization is traversed once with validated input and trusted context.
4. A real mounted Livewire instance is passed through T-203 `LivewireBindingProducer`.
5. Produced binding references exact `prep_list.add_item@1` and reuses the exact registered `ActionDefinition` object.
6. Binding `target.componentId` equals the actual component `getId()`.
7. Binding `target.method` is exactly `addItem`.
8. Binding-derived test calls exactly that method through the same real Livewire testable.
9. Binding-derived path reaches the same `AddPrepListItem` business mutation once.
10. Fresh human and binding-derived runs produce equivalent final state.
11. Empty invalid `name` halts in input validation before authorization or business execution.
12. Component uses lifecycle `boot(PrepListActionGateway)` injection and owns no constructor application wiring.
13. No browser driver/WebMCP registration/agent-only endpoint exists in T-204.

## TDD evidence

### Production execution stage

RED:

```text
868eb1f3dc89e47023af95217bd44279b7a80994
260 tests / 713 assertions / 4 deliberate failures
```

The failures were only the absent execution port/stage.

GREEN:

```text
7267a43d6ede657d52cffc0d8a96f047f6c885af
```

### Real Livewire Prep List E2E

RED:

```text
75022ae6594dfcabfd33bec89825d51459d0b8fa
266 tests / 735 assertions / 6 deliberate failures
```

The failures were absent Prep List fixture/wiring while the actual Livewire/Testbench harness was already loaded.

Fixture implementation:

```text
f1eca5290d4ddbbd4b36990feddf76e20cc76f1c
```

The first real Livewire run then exposed one harness-only requirement: Testbench had no application encryption key, which Livewire snapshot checksums require. A fixed test-only key was configured in:

```text
e7e6a9809d647070aff78105285ac08da0b4a03b
```

Final observed evidence:

```text
PHP:      266 tests / 783 assertions
contract: 52 fixture manifest entries + 12 conformance scenarios
browser:  TypeScript typecheck + 3 Vitest tests
CI:       all four PHP/Illuminate/Testbench/Livewire cells + contract + php-lint + browser GREEN
```

## Decision status

- D-022..D-025 remain ACCEPTED.
- D-026 remains PROPOSED until actual binding-resolution failure behavior exists.
- D-033 remains ACCEPTED.
- D-034 ACCEPTED — for the Livewire vertical, human interaction and binding-derived agent invocation converge on the same explicitly exposed component method and shared ActionBus/application action. The future browser driver selects/invokes that existing target; it does not define an agent-only business endpoint.

## Deliberate non-goals retained

T-204 does not implement:

- browser `Livewire.find()` or `$wire.$call()` driver code;
- DriverRegistry;
- WebMCP registration/projection;
- stale-target browser resolution;
- D-026 final public binding failures;
- production confirmation receipts;
- production idempotency store;
- production output policy/redaction;
- production structured audit;
- Filament/current-record/current-selection integration.

## Explicit statement

**T-204 implementation is complete and the exact implementation head is green. M2 implementation is complete. The next action is external-style review of this branch; M3/T-301 has NOT started.**
