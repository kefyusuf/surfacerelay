# Explicit Livewire Action Exposure Implementation Plan

**Goal:** Implement T-202 as a fail-closed, method-level explicit exposure declaration that resolves exact registered Action Definitions without issuing bindings or invoking component methods.

**Architecture:** `#[ExposeAction(id, version)]` marks an eligible concrete component method. `LivewireActionExposureReader` inspects only explicit attributes, validates method shape, resolves exact identity through `ActionRegistry`, rejects ambiguous configuration, and returns deterministic immutable `LivewireActionExposure` values.

**Tech Stack:** PHP 8.3+, Reflection API, PHPUnit 11, existing SurfaceRelay ActionRegistry/ActionDefinition model. No `livewire/livewire` dependency.

**Spec:** `docs/design/livewire-action-exposure.md`

## Global constraints

- Do not modify `spec/0.1`.
- Do not add a Livewire Composer dependency.
- Do not issue Runtime Bindings or binding IDs.
- Do not invoke component methods.
- Do not auto-expose unannotated public methods.
- Exact Action Definition `id + version` only; no latest/fuzzy fallback.
- Exposure is not discovery authorization and not invocation authorization.
- Parent-only exposure attributes do not propagate to child components.
- T-203 must not begin in this branch.

---

## Task 1 — Define the explicit exposure vocabulary

**Files:**
- Create: `packages/laravel/src/Livewire/Attributes/ExposeAction.php`
- Create: `packages/laravel/src/Livewire/Exposure/LivewireActionExposure.php`
- Test: `packages/laravel/tests/Unit/LivewireActionExposureReaderTest.php`

**Produces:**

```php
#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class ExposeAction
{
    public function __construct(
        public string $id,
        public int $version,
    ) {}
}
```

```php
final readonly class LivewireActionExposure
{
    public function __construct(
        public ActionDefinition $definition,
        public string $method,
    ) {}
}
```

- [ ] Add failing tests proving the attribute/VO classes do not yet exist.
- [ ] Add tests proving an exposure reuses the exact ActionDefinition object and preserves the method name.
- [ ] Add tests proving both types are readonly.
- [ ] Run the focused tests and record RED.
- [ ] Add the minimal two classes above.
- [ ] Run focused tests and record GREEN.
- [ ] Commit as `feat(livewire): add explicit exposure vocabulary`.

---

## Task 2 — Implement exact, fail-closed exposure reading

**Files:**
- Create: `packages/laravel/src/Livewire/Exposure/InvalidLivewireActionExposure.php`
- Create: `packages/laravel/src/Livewire/Exposure/LivewireActionExposureReader.php`
- Extend test: `packages/laravel/tests/Unit/LivewireActionExposureReaderTest.php`

**Reader interface:**

```php
final class LivewireActionExposureReader
{
    public function __construct(private ActionRegistry $registry) {}

    /** @return list<LivewireActionExposure> */
    public function forComponent(object $component): array;
}
```

**Resolution algorithm:**

1. Reflect the concrete object.
2. Inspect methods only for `ExposeAction` attributes.
3. Ignore an attribute whose reflected method is declared only by a parent class.
4. If an accepted concrete declaration is non-public, static, or repeats `ExposeAction`, throw `InvalidLivewireActionExposure`.
5. Resolve exact `id + version` with the injected `ActionRegistry`; missing identity throws a focused adapter configuration error with method/id/version context.
6. Reject the same exact action identity mapped to more than one concrete method.
7. Return immutable exposures sorted by action ID ASC, version ASC, method ASC.
8. Never invoke any component method.

**Focused exception factories:**

```php
InvalidLivewireActionExposure::actionNotRegistered(string $method, string $id, int $version)
InvalidLivewireActionExposure::methodNotPublic(string $method)
InvalidLivewireActionExposure::methodStatic(string $method)
InvalidLivewireActionExposure::duplicateAttribute(string $method)
InvalidLivewireActionExposure::duplicateActionIdentity(string $id, int $version, string $firstMethod, string $secondMethod)
```

- [ ] Add RED tests for annotated public instance method exact resolution.
- [ ] Add RED test proving an unannotated public method is ignored.
- [ ] Add RED test proving an empty exposure list is valid.
- [ ] Add RED test proving missing exact version fails with no fallback even when another version exists.
- [ ] Add RED test proving versions 1 and 2 of the same action remain distinct.
- [ ] Add RED test proving duplicate exact action identity across two methods fails loudly.
- [ ] Add RED test proving duplicate attributes on one method fail through the focused adapter exception.
- [ ] Add RED tests for annotated protected/private/static methods.
- [ ] Add RED test proving a parent-only annotation does not expose on a child.
- [ ] Add RED test proving an explicitly annotated child override can expose inherited behavior intentionally.
- [ ] Add RED test for deterministic `id/version/method` ordering.
- [ ] Add RED test whose annotated method increments/throws if called, proving the reader never invokes it.
- [ ] Run focused tests and record RED.
- [ ] Implement the minimal exception + reader.
- [ ] Run focused tests and full Laravel suite.
- [ ] Commit as `feat(livewire): resolve explicit action exposures`.

---

## Task 3 — Verify boundaries and checkpoint T-202

**Files:**
- Modify: `TASKS.md`
- Modify: `STATUS.md`
- Modify: `REVIEW_REQUEST.md`

- [ ] Confirm `packages/laravel/composer.json` contains no `livewire/livewire` dependency.
- [ ] Confirm no T-203 concepts were introduced: component ID lookup, binding ID generation, binding registry, lifecycle/stale tracking.
- [ ] Confirm `spec/0.1` is unchanged.
- [ ] Run `python scripts/validate.py`.
- [ ] Run the full PHP 8.3/8.4 × Illuminate 12/13 GitHub Actions matrix.
- [ ] Run browser typecheck/tests through CI.
- [ ] Record the actual final PHP test/assertion count from CI.
- [ ] Update task/status/review documents: T-202 DONE, T-203 next but not started.
- [ ] Commit as `docs(status): complete T-202 explicit exposure`.
- [ ] Perform a final `main...feat/livewire-action-exposure` scope review.
- [ ] Stop before T-203.
