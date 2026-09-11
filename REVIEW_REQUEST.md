# External Review Request — T-601 HTMX Binding Descriptor

## Current status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Scope:** `T-601 — Explicit HTMX binding descriptor`
- **Feature branch:** `feat/htmx-binding-descriptor`
- **Pull request:** **NOT CREATED YET**
- **Base / merge-base:** `main@5b22eef928d2fb1f8fac8ab13507e2f22661d3df`
- **Verified implementation/scope head:** `b06204e2c7acf6be01df3fec5085e55ddd650329`
- **Implementation validation:** `34654979136` — **7/7 green**
- **Decision:** `D-053` — **ACCEPTED for descriptor semantics only**
- **Portability decision:** `D-020` — **PROPOSED; remains gated on T-604**
- **Browser:** TypeScript typecheck + **172/172 Vitest tests**; focused HTMX descriptor **69/69**
- **PHP baseline:** **595 tests / 3164 assertions** across PHP 8.3/8.4 × Illuminate 12/13 + MySQL 8.4
- **Contract / lint / Composer:** green
- **T-602:** **NOT STARTED**
- **Merge state:** **NOT MERGED**

## Review thesis

T-601 is intentionally a descriptor-only portability checkpoint. It asks whether the existing protocol-neutral RuntimeBinding envelope can carry an HTMX-specific exact target without changing ActionDefinition semantics, weakening binding identity, or introducing a second execution path.

```text
ActionDefinition
      │
      │ exact finite top-level caller field set
      ▼
createHtmxBindingTarget()
      │
      ▼
{
  sourceId,
  method,
  path,
  inputNames,
  requiredInputNames
}
      │
      ▼
RuntimeBinding(driver=htmx, lifecycle=page)

arbitrary binding JSON
      │
      ▼
parseHtmxBindingTarget()
      │ strict fail-closed validation
      ▼
immutable target snapshot
```

No DOM lookup, HTMX request execution, cancellation, fixture app, or shared portability conformance is implemented in this task.

## Primary review focus

### 1. Silent retarget / identity risk

`sourceId` is one opaque rendered-source identity. It is not a selector convention, business record ID, or authorization proof. Replacement elements must receive fresh identities; T-602 is required to fail stale rather than discover a similar replacement by endpoint, class, text, position, or record.

Review whether any T-601 target rule accidentally permits target identity to float.

### 2. Method/path ambiguity

The descriptor supports only exact uppercase:

```text
GET POST PUT PATCH DELETE
```

`path` must be a bounded absolute same-origin path reference. It rejects relative paths, `//` authority forms, full URLs, fragments, backslashes, controls, and >2048 characters.

Review for parsing/normalization edge cases that could make producer and future consumer disagree.

### 3. Open-ended schema escape paths

Named Action-input mapping is derived only from a closed top-level object schema with `additionalProperties:false`.

The producer rejects top-level forms that can make the finite key set ambiguous, including:

```text
patternProperties
$ref / $dynamicRef
allOf / anyOf / oneOf
not
if / then / else
unevaluatedProperties
dependentSchemas
```

It also rejects malformed `properties`/`required`, duplicate/empty required names, and required names outside exact properties.

Review whether a valid JSON Schema shape can still create caller keys outside the issued `inputNames` without being rejected.

### 4. Caller-controlled mapping promotion

The public producer API accepts only:

```text
ActionDefinition
sourceId
method
path
```

Callers cannot supply `inputNames` or `requiredInputNames`; those are derived from the exact ActionDefinition. Arbitrary RuntimeBinding JSON is separately parsed by `parseHtmxBindingTarget()`.

Review for any path where caller object order or arbitrary mapping lists become producer authority.

### 5. Trusted-authority leakage

The exact target key set is:

```text
sourceId
method
path
inputNames
requiredInputNames
```

There is no actor, tenant, role, current record, current selection, browser-session authority, confirmation receipt/challenge, raw idempotency key, or authorization decision.

Review whether any accepted field accidentally acts as trusted server authority rather than browser addressing data.

### 6. T-602 scope creep

The descriptor source has an executable source guard forbidding:

```text
htmx.org import
htmx.ajax(
document.
window.
fetch(
@surfacerelay/laravel
```

There is no `htmx-browser-driver.ts`, HTMX package dependency, fixture app, or network/cancellation logic.

Review for hidden browser execution semantics that should belong to T-602.

### 7. Immutability / reference retention

Both parser and producer return frozen target objects with frozen defensive mapping-list copies. Runtime regression tests mutate original mapping/schema containers after construction and require the issued descriptor to remain unchanged. Typecheck tests also enforce readonly fields/arrays.

Review for nested reference retention that could affect the actual descriptor output after construction.

### 8. Frozen contract drift

T-601 relies on the existing RuntimeBinding extension points:

- extensible driver identifier;
- driver-owned target object;
- existing `page` lifecycle.

There are **no changes under `spec/0.1/**`** and no Laravel production changes.

Review whether the implementation is genuinely reference-driver policy rather than an undeclared new cross-framework wire contract.

## TDD evidence

```text
Design checkpoint:               cca98af90323819789a56d28a2174b9b7ea25059 / 34625130732 — 7/7 green
Implementation plan:             1e47c6bb4121bcc1fc43e69c73c213d17092b5c1 / 34629136719 — 7/7 green
Parser RED:                      e0fceb6d7cf277dd1a1693daefc9b593d1a5d6a6 / 34653908938
Parser shell GREEN:              cf8436710a21dbb5888d62cd26197e7e7fc3ac62 / 34653972487
Primitive validation RED:        f9da2714d7be4890ea4cd49623352224d73415de / 34654093046
Primitive validation GREEN:      b1845aab1ed82b32a14f738be08ef668f9d45342 / 34654144660 — 7/7 green
List/freeze RED:                 cbb410aa926c464d4011caccc607215e1f9422ca / 34654294846
List/freeze GREEN:               4e640112d5b9d9129f4ea07feddba538384699a9 / 34654371743
Mapping RED:                     07a86db5756c0d75e7e6b0b854b8d24765a5fca1 / 34654482062 — exactly 2 expected missing-builder failures
Mapping valid-path GREEN:        92b392b5304c456465f9fa8ac68e0f2dad9e42a3 / 34654613401
Schema fail-closed RED:          2df42bee8c91dbb609f313a4720408045b8d5f8c / 34654717686
Schema fail-closed GREEN:        831496a119535c31e0d76890b59b8f7ce0fb508c / 34654782553
Immutability checkpoint:         757835dee45e44724feba1972dcacdce43c82228 / 34654885713 — 7/7 green
Type contract checkpoint:        acb82ef246f09cc427da53e20facb567b5093694 / 34654896859
Implementation/scope head:       b06204e2c7acf6be01df3fec5085e55ddd650329 / 34654979136 — 7/7 green
Browser:                         TypeScript typecheck + 172/172 tests
HTMX descriptor focused tests:  69/69
Contract:                        python scripts/validate.py green
```

## Change surface

Implementation/test surface:

```text
packages/browser-runtime/src/htmx-binding-descriptor.ts
packages/browser-runtime/tests/htmx-binding-descriptor.test.ts
packages/browser-runtime/tests/htmx-binding-descriptor.typecheck.ts
```

Design/tracking surface:

```text
docs/superpowers/specs/2026-09-11-htmx-binding-descriptor-design.md
docs/superpowers/plans/2026-09-11-htmx-binding-descriptor.md
docs/DECISION-REGISTER.md
TASKS.md
STATUS.md
REVIEW_REQUEST.md
```

Explicitly unchanged/out of scope:

```text
packages/laravel/src/**
spec/0.1/**
packages/browser-runtime/src/htmx-browser-driver.ts
examples/htmx/**
packages/browser-runtime/package.json
packages/browser-runtime/package-lock.json
```

## Deliberate limitation

T-601 does not prove an executable HTMX interaction. In particular it does not resolve DOM source identity, verify current `hx-*` state, call `htmx.ajax()`, define response/swap semantics, define cancellation guarantees, or issue the binding from a non-Laravel fixture.

Those are T-602/T-603/T-604 responsibilities. D-020 remains proposed until shared conformance is executable.

## Review gate

T-601 is self-reviewed and ready for external review after final review-prep CI passes. **Do not merge or begin T-602 from this handoff.**