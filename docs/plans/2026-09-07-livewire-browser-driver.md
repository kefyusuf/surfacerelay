# Livewire Browser Driver Implementation Plan

**Goal:** Implement T-304 as an exact, fail-closed Livewire browser execution adapter using documented Livewire 4 browser APIs, with a server-issued object-to-positional argument plan and explicit stale/expiry/input-mapping failures.

**Architecture:** Extend the driver-owned Livewire RuntimeBinding target so the Laravel producer emits `inputOrder` and `requiredCount` derived from the exact exposed PHP method plus its ActionDefinition input schema. Add a narrow browser compatibility port and a `LivewireBrowserDriver` that validates target/expiry/input, resolves only `Livewire.find(componentId)`, verifies `$wire.$id`, and invokes only `$wire.$call(method, ...params)`. Keep cancellation propagation, revocation persistence, confirmation, idempotency, output policy, and audit outside T-304.

**Tech Stack:** PHP 8.3/8.4, Laravel/Illuminate 12/13, Livewire 4.4 test integration, TypeScript 5.9, ES2022/DOM types, Vitest 3.2.

**Spec:** `docs/design/livewire-browser-driver.md`

## Global Constraints

- `spec/0.1` remains unchanged; Livewire target shape is driver-owned RuntimeBinding metadata.
- Exact component resolution uses only `Livewire.find(componentId)`; no first/name/DOM/class/record/method fallback.
- Browser invocation uses documented `$wire.$call(method, ...params)` only; do not import private Livewire request/fireAction internals.
- Action input object order is never invocation authority; positional order comes from a server-issued call plan.
- Action schema top-level property names must equal the caller-visible exposed PHP parameter names exactly.
- Schema `required` must equal PHP parameters without defaults and must form a required positional prefix.
- Variadic, by-reference, and method-level dependency-injection signatures are unsupported and fail binding issuance.
- Livewire `$wire` reserved names and public component-property collisions fail binding issuance; the browser driver also rejects the static reserved-name set defensively.
- Unknown runtime input keys, missing required keys, and optional positional holes fail before `$call()`.
- Explicit `expiresAt` is cumulative validity; expired bindings fail before component resolution.
- Missing exact component ID is `binding_stale`; arbitrary server/network/application errors from `$call()` propagate unchanged.
- Non-null `outputSchema` plus explicit PHP `void`/`never` return is rejected at binding issuance; no full PHP-return-type/JSON-Schema inference is attempted.
- T-305 owns real cancellation propagation; T-304 must not claim `$wire.$call()` accepts AbortSignal or provides rollback.

---

### Task 1: Server-side Livewire call-plan validation and target shape

**Files:**
- Create: `packages/laravel/src/Livewire/Binding/LivewireMethodCallPlan.php`
- Create: `packages/laravel/src/Livewire/Binding/LivewireMethodCallPlanBuilder.php`
- Modify: `packages/laravel/src/Livewire/LivewireBindingTarget.php`
- Modify: `packages/laravel/src/Livewire/Binding/InvalidLivewireBindingProduction.php`
- Test: `packages/laravel/tests/Unit/LivewireMethodCallPlanBuilderTest.php`
- Test: `packages/laravel/tests/Unit/LivewireRuntimeBindingTest.php`

**Produces:**

```php
final readonly class LivewireMethodCallPlan
{
    /** @param list<string> $inputOrder */
    public function __construct(
        public array $inputOrder,
        public int $requiredCount,
    ) {}
}
```

```php
final class LivewireMethodCallPlanBuilder
{
    public function forExposure(
        object $component,
        LivewireActionExposure $exposure,
    ): LivewireMethodCallPlan;
}
```

`LivewireBindingTarget` becomes:

```php
public function __construct(
    public string $componentId,
    public string $method,
    public array $inputOrder,
    public int $requiredCount,
)
```

and serializes all four fields deterministically.

- [ ] **RED:** Add focused builder tests for ordinary required parameters, required-prefix/optional-suffix signatures, exact schema-property equality, exact required-key equality, and output-schema/void rejection.
- [ ] **RED:** Add fail-closed tests for variadic parameters, by-reference parameters, method-level class/interface parameters, missing/extra schema properties, non-object/ambiguous top-level schema shape, invalid required suffix, public property collision, reserved `$wire` method names, duplicate/empty target inputOrder, and invalid requiredCount.
- [ ] Run the focused PHP tests and confirm failures are caused only by missing T-304 call-plan/target behavior.
- [ ] Implement `LivewireMethodCallPlan`, builder validation, target validation, and focused production errors.
- [ ] Use reflection parameter order only. A parameter is required iff `!$parameter->isOptional() && !$parameter->isDefaultValueAvailable()`.
- [ ] Reject any exposed parameter that is variadic, passed by reference, or has a non-builtin named type. Do not attempt Livewire method dependency injection in T-304.
- [ ] Require `inputSchema['type'] === 'object'` when type is present, `properties` to be an array/map of explicit names, and `required` to be an array/list when present. Reject unsupported top-level composition (`oneOf`, `anyOf`, `allOf`) when it prevents one deterministic property map.
- [ ] Compare schema property set to reflection parameter set without using schema key order.
- [ ] Compare schema required set to PHP required parameter set and require required parameters to be the positional prefix.
- [ ] Reject method names in the pinned Livewire reserved set and names colliding with public non-static properties on the concrete component.
- [ ] If `definition.outputSchema !== null` and the exposed method has explicit return type `void` or `never`, fail binding production.
- [ ] Run focused tests, full Laravel PHPUnit suite, and `composer validate --strict`.
- [ ] Commit as `feat(livewire): add binding call-plan validation`.

---

### Task 2: Producer integration and Prep List semantic output

**Files:**
- Modify: `packages/laravel/src/Livewire/Binding/LivewireBindingProducer.php`
- Modify: `packages/laravel/tests/Unit/LivewireBindingProducerTest.php`
- Modify: `packages/laravel/tests/Fixtures/PrepList/PrepListComponent.php`
- Modify: `packages/laravel/tests/Integration/PrepListLivewireE2ETest.php`
- Modify if needed: `examples/prep-list/binding.livewire.json`

**Consumes:** `LivewireMethodCallPlanBuilder`.

**Required constructor shape:**

```php
public function __construct(
    LivewireActionExposureReader $exposureReader,
    LivewireComponentIdentityResolver $componentIdentityResolver,
    BindingIdGenerator $bindingIdGenerator,
    LivewireMethodCallPlanBuilder $callPlanBuilder,
) {}
```

**Producer behavior:** for each exact exposure, build the call plan before issuing the RuntimeBinding, then create `LivewireBindingTarget(componentId, method, inputOrder, requiredCount)`.

- [ ] **RED:** Update producer tests so emitted Prep List binding target equals `componentId + method + inputOrder=['name'] + requiredCount=1`.
- [ ] **RED:** Prove incompatible signatures prevent binding issuance and no partial list is returned.
- [ ] **RED:** Update E2E expectation so the exposed Livewire method returns the ActionBus output rather than discarding it.
- [ ] Run focused producer/E2E tests and confirm expected failures.
- [ ] Inject/use `LivewireMethodCallPlanBuilder` in the producer.
- [ ] Change `PrepListComponent::addItem(string $name): array` to return `$this->actions->addItem($name)`; retain the single business mutation path.
- [ ] Update static binding example only if its target is intended to illustrate the current driver-owned descriptor; document illustrative values as before.
- [ ] Run the complete PHP suite across the local dependency set and contract validator.
- [ ] Commit as `feat(livewire): issue executable browser call plans`.

---

### Task 3: Browser Livewire compatibility port and execution errors

**Files:**
- Create: `packages/browser-runtime/src/livewire-browser-runtime.ts`
- Create: `packages/browser-runtime/src/livewire-errors.ts`
- Create: `packages/browser-runtime/src/livewire-reserved-names.ts`
- Test: `packages/browser-runtime/tests/livewire-browser-runtime.test.ts`
- Test: `packages/browser-runtime/tests/livewire-reserved-names.test.ts`

**Produces:**

```ts
export interface LivewireWire {
  readonly $id: string;
  $call(method: string, ...params: unknown[]): Promise<unknown>;
}

export interface LivewireBrowserRuntime {
  find(componentId: string): LivewireWire | undefined;
}
```

```ts
export type LivewireBindingExecutionErrorCode =
  | 'binding_stale'
  | 'binding_expired'
  | 'binding_target_invalid'
  | 'binding_input_unmappable'
  | 'livewire_runtime_unavailable'
  | 'livewire_method_unsupported';
```

```ts
export class LivewireBindingExecutionError extends Error {
  constructor(
    public readonly code: LivewireBindingExecutionErrorCode,
    message: string,
  ) { super(message); }
}
```

- [ ] **RED:** Add type/behavior tests for the narrow runtime port and the exact reserved-name set pinned from current Livewire 4.4 `$wire` aliases/properties plus `then`, `toJSON`, and `__instance`.
- [ ] **RED:** Add an adapter test for ambient global absence if a concrete `WindowLivewireBrowserRuntime` is included; absence must become `livewire_runtime_unavailable`, never a `TypeError` leak from dereferencing an undefined global.
- [ ] Implement the compatibility types/adapter, reserved-name constant/function, and typed error.
- [ ] Keep ambient `window.Livewire` access confined to this compatibility module.
- [ ] Run browser typecheck and focused tests.
- [ ] Commit as `feat(browser): add Livewire compatibility boundary`.

---

### Task 4: Exact LivewireBrowserDriver execution

**Files:**
- Create: `packages/browser-runtime/src/livewire-browser-driver.ts`
- Test: `packages/browser-runtime/tests/livewire-browser-driver.test.ts`
- Modify if necessary: `packages/browser-runtime/src/types.ts` only for a tiny reusable clock type; otherwise keep the clock local to the driver module.

**Produces:**

```ts
export interface BrowserClock {
  now(): Date;
}

export class LivewireBrowserDriver implements BindingDriver {
  constructor(
    livewire: LivewireBrowserRuntime,
    clock?: BrowserClock,
  );

  execute(
    binding: RuntimeBinding,
    input: Record<string, unknown>,
    context: DriverExecutionContext,
  ): Promise<unknown>;
}
```

- [ ] **RED target-shape tests:** reject wrong `driver`, wrong lifecycle, missing/extra/ill-typed target fields, duplicate/empty inputOrder entries, invalid requiredCount, and reserved method names before any runtime lookup.
- [ ] **RED expiry tests:** null/absent expiry allowed; malformed expiry is `binding_target_invalid`; `expiresAt <= now` is `binding_expired`; no `find()` occurs on expired binding.
- [ ] **RED input-mapping tests:** required keys must be own-properties; unknown input keys fail; optional trailing omission is allowed; optional hole before a later value fails; caller object key order does not matter; values pass unchanged.
- [ ] **RED exact-resolution tests:** `find(componentId)` is called once with the exact binding componentId; `undefined` is `binding_stale`; mismatching returned `$id` fails closed; no fallback lookup exists.
- [ ] **RED invocation tests:** `$call(method, ...params)` is called exactly once with exact method/params; resolved value is returned unchanged; rejection from `$call()` is the exact original error object.
- [ ] **RED cancellation-boundary test:** passing `context.signal` does not alter `$call()` arguments or claim cancellation support; T-305 remains the owner of propagation.
- [ ] Implement strict target parsing, expiry, positional mapping, exact resolution, identity verification, and exact documented `$call()` invocation.
- [ ] Use a strict RFC3339/ISO date parse rule compatible with existing RuntimeBinding date-time expectations; do not accept implementation-dependent junk dates.
- [ ] Run browser typecheck and full Vitest suite.
- [ ] Commit as `feat(browser): execute exact Livewire bindings`.

---

### Task 5: Integration, decisions, verification, and review checkpoint

**Files:**
- Modify: `docs/DECISION-REGISTER.md`
- Modify: `STATUS.md`
- Modify: `TASKS.md`
- Modify: `REVIEW_REQUEST.md`
- Add/modify tests only if final review exposes a concrete uncovered invariant.

- [ ] Register `new LivewireBrowserDriver(...)` under exact driver name `livewire` in an integration-style browser test using the real `DriverRegistry` and T-303 registration lifecycle; execute a projected WebMCP tool and prove the exact captured RuntimeBinding reaches the Livewire driver.
- [ ] Prove same-action replacement behavior: after exact component disappearance, old binding execution returns `binding_stale`; no replacement component is selected even if it exposes the same method/action.
- [ ] Prove server-produced Prep List binding target matches the browser driver's accepted target contract.
- [ ] Record D-039 ACCEPTED: exact `Livewire.find(componentId)` resolution and no fallback/retarget.
- [ ] Record D-040 ACCEPTED: object input maps to positional Livewire calls only through server-issued argument plan; JSON/property order is never authority.
- [ ] Record D-041 ACCEPTED: reference driver uses documented `Livewire.find()` + `$wire.$call()` and rejects public `$wire` namespace collisions instead of bypassing through private APIs.
- [ ] Keep D-026 only partially/provisionally resolved: T-304 owns proven `binding_stale` and `binding_expired` conditions, but does not claim a complete generic browser binding-validity authority.
- [ ] Update source-of-truth docs with exact RED/GREEN commits, workflow IDs, PHP/browser/contract counts, and explicit T-305-not-started boundary.
- [ ] Run full repository CI across contract, PHP 8.3/8.4 × Illuminate 12/13, php-lint, and browser.
- [ ] Perform external-style diff review against `main`: no `spec/0.1` change, no private Livewire JS imports, no name/DOM fallback, no M4 controls, no false cancellation/rollback claim.
- [ ] Prepare a final review checkpoint and stop before T-305 until review/merge is explicitly completed.
