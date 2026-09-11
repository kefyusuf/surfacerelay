# T-601 HTMX Binding Descriptor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement a pure, fail-closed HTMX RuntimeBinding target descriptor in `@surfacerelay/browser-runtime` that derives a finite named Action-input mapping from an exact closed ActionDefinition schema, without adding DOM/HTMX execution or changing frozen SurfaceRelay contracts.

**Architecture:** Add one focused TypeScript module that owns the HTMX driver-local target type, construction error taxonomy, strict RuntimeBinding target parser, and ActionDefinition-derived target builder. The module remains pure: no DOM, HTMX runtime, network, Laravel, or cancellation behavior. T-602 will later consume the strict parser when implementing browser execution.

**Tech Stack:** TypeScript 5.9, Vitest 3.2, existing `RuntimeBinding` / `ActionDefinition` browser-runtime types, repository Python contract validator.

**Spec:** `docs/superpowers/specs/2026-09-11-htmx-binding-descriptor-design.md`

## Global Constraints

- Branch: `feat/htmx-binding-descriptor`.
- Design checkpoint: `cca98af90323819789a56d28a2174b9b7ea25059`; base `main@5b22eef928d2fb1f8fac8ab13507e2f22661d3df`.
- `driver` is exactly `htmx` and lifecycle is exactly `page`.
- Target keys are exactly `sourceId`, `method`, `path`, `inputNames`, `requiredInputNames`.
- `sourceId` is opaque rendered-source identity only; never actor/tenant/record/selection/authorization identity.
- Supported methods are exactly `GET`, `POST`, `PUT`, `PATCH`, `DELETE`.
- `path` is a bounded same-origin absolute-path reference; no scheme/authority/fragment/backslash/control ambiguity.
- Named input mapping is derived only from the exact closed top-level `ActionDefinition.inputSchema`.
- Open-ended/ambiguous top-level input schemas fail closed; no guessed mapping.
- Nested input values remain nested; no dotted/bracket flattening convention.
- No actor, tenant, roles, permissions, current record, current selection, browser-session authority, confirmation receipt/challenge, raw idempotency key, or authorization decision enters the HTMX target.
- No `document`, `window`, DOM lookup, `htmx.ajax()`, `fetch()`, request dispatch, cancellation, fixture app, or shared conformance in T-601.
- No HTMX dependency is added.
- No production change under `packages/laravel/src/**`.
- No change under `spec/0.1/**`.
- D-053 remains `PROPOSED` until all executable T-601 proofs and full verification pass. D-020 remains `PROPOSED` until T-604.
- Repository-specific refinement: the browser-runtime package currently has no `src/index.ts` barrel and existing tests import modules directly. Do **not** introduce the first barrel export solely for T-601; import `../src/htmx-binding-descriptor.js` directly, matching established package structure.

## File Structure

- Create `packages/browser-runtime/src/htmx-binding-descriptor.ts`
  - Owns HTMX descriptor types, driver-local construction errors, strict target parsing, primitive validation, and ActionDefinition-derived named input mapping.
- Create `packages/browser-runtime/tests/htmx-binding-descriptor.test.ts`
  - Runtime RED/GREEN coverage for target parsing, method/path/source identity, schema mapping, immutability, and scope behavior.
- Create `packages/browser-runtime/tests/htmx-binding-descriptor.typecheck.ts`
  - Compile-time proof for readonly target arrays/types and public signatures.
- Modify `docs/DECISION-REGISTER.md`
  - Promote D-053 only after implementation and verification are green.
- Modify `TASKS.md`
  - Record T-601 verification evidence and only mark DONE after final gates pass.
- Modify `STATUS.md`
  - Record exact implementation head, changed files, test counts, limitations, and next boundary T-602.
- Modify `REVIEW_REQUEST.md`
  - Prepare external-review handoff only at review-prep; do not create/merge a PR in this plan.

---

### Task 1: Strict HTMX RuntimeBinding Target Parser

**Files:**
- Create: `packages/browser-runtime/tests/htmx-binding-descriptor.test.ts`
- Create: `packages/browser-runtime/src/htmx-binding-descriptor.ts`

**Interfaces:**
- Consumes: `RuntimeBinding` from `packages/browser-runtime/src/types.ts`.
- Produces:

```ts
export type HtmxRequestMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

export interface HtmxBindingTarget {
  readonly sourceId: string;
  readonly method: HtmxRequestMethod;
  readonly path: string;
  readonly inputNames: readonly string[];
  readonly requiredInputNames: readonly string[];
}

export type HtmxBindingDescriptorErrorCode =
  | 'runtime_binding_invalid'
  | 'source_id_invalid'
  | 'method_invalid'
  | 'path_invalid'
  | 'input_mapping_invalid'
  | 'input_schema_unsupported';

export class HtmxBindingDescriptorError extends Error {
  constructor(
    public readonly code: HtmxBindingDescriptorErrorCode,
    message: string,
  );
}

export function parseHtmxBindingTarget(binding: RuntimeBinding): HtmxBindingTarget;
```

`parseHtmxBindingTarget()` is the strict consumer-side boundary that T-602 will reuse. It validates `driver`, lifecycle, exact target keys, primitive field contracts, list uniqueness/subset rules, and returns defensive frozen copies.

- [ ] **Step 1: Write RED parser tests for driver/lifecycle and exact target shape**

Create `packages/browser-runtime/tests/htmx-binding-descriptor.test.ts` with a canonical binding helper and the first fail-closed cases:

```ts
import { describe, expect, it } from 'vitest';
import {
  HtmxBindingDescriptorError,
  parseHtmxBindingTarget,
} from '../src/htmx-binding-descriptor.js';
import type { RuntimeBinding } from '../src/types.js';

function binding(overrides: Partial<RuntimeBinding> = {}): RuntimeBinding {
  return {
    bindingId: 'binding-htmx-1',
    action: { id: 'prep_list.add_item', version: 1 },
    driver: 'htmx',
    lifecycle: 'page',
    target: {
      sourceId: 'htmx-src-1',
      method: 'POST',
      path: '/prep-list/items',
      inputNames: ['item', 'note'],
      requiredInputNames: ['item'],
    },
    expiresAt: null,
    ...overrides,
  };
}

function expectCode(fn: () => unknown, code: HtmxBindingDescriptorError['code']): void {
  try {
    fn();
    throw new Error('Expected HTMX descriptor failure.');
  } catch (error) {
    expect(error).toBeInstanceOf(HtmxBindingDescriptorError);
    expect((error as HtmxBindingDescriptorError).code).toBe(code);
  }
}

describe('parseHtmxBindingTarget', () => {
  it('accepts the exact HTMX page binding target', () => {
    expect(parseHtmxBindingTarget(binding())).toEqual({
      sourceId: 'htmx-src-1',
      method: 'POST',
      path: '/prep-list/items',
      inputNames: ['item', 'note'],
      requiredInputNames: ['item'],
    });
  });

  it('rejects another driver and another lifecycle', () => {
    expectCode(() => parseHtmxBindingTarget(binding({ driver: 'livewire' })), 'runtime_binding_invalid');
    expectCode(() => parseHtmxBindingTarget(binding({ lifecycle: 'component' })), 'runtime_binding_invalid');
  });

  it('rejects missing and extra target keys', () => {
    const missing = { ...binding(), target: { sourceId: 'htmx-src-1' } };
    expectCode(() => parseHtmxBindingTarget(missing), 'runtime_binding_invalid');

    const extra = {
      ...binding(),
      target: { ...binding().target, tenantId: 'tenant-a' },
    };
    expectCode(() => parseHtmxBindingTarget(extra), 'runtime_binding_invalid');
  });
});
```

- [ ] **Step 2: Run the focused test and verify RED**

Run:

```bash
cd packages/browser-runtime
npm test -- tests/htmx-binding-descriptor.test.ts
```

Expected: FAIL because `../src/htmx-binding-descriptor.js` does not exist yet.

- [ ] **Step 3: Commit the RED checkpoint**

```bash
git add packages/browser-runtime/tests/htmx-binding-descriptor.test.ts
git commit -m "test(htmx): define binding target parser contract"
```

- [ ] **Step 4: Implement the minimal parser shell and error type**

Create `packages/browser-runtime/src/htmx-binding-descriptor.ts` with the public types above and these fixed constants/helpers:

```ts
import type { ActionDefinition, RuntimeBinding } from './types.js';

const HTMX_TARGET_KEYS = [
  'inputNames',
  'method',
  'path',
  'requiredInputNames',
  'sourceId',
] as const;

const SOURCE_ID_PATTERN = /^[A-Za-z0-9][A-Za-z0-9._:-]{0,239}$/;
const HTMX_METHODS = new Set<HtmxRequestMethod>(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);
const ASCII_CONTROL_PATTERN = /[\u0000-\u001F\u007F]/;

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}
```

`parseHtmxBindingTarget()` must first fail with `runtime_binding_invalid` unless:

```text
binding.driver === 'htmx'
binding.lifecycle === 'page'
binding.target is a non-array object
Object.keys(binding.target).sort() === HTMX_TARGET_KEYS sorted
```

Do not inspect DOM or HTMX runtime state.

- [ ] **Step 5: Add RED primitive validation tests**

Extend the same test file with table-driven cases:

```ts
it.each([
  '',
  '-starts-with-dash',
  'contains space',
  'x'.repeat(241),
])('rejects invalid sourceId %j', (sourceId) => {
  expectCode(
    () => parseHtmxBindingTarget(binding({ target: { ...binding().target, sourceId } })),
    'source_id_invalid',
  );
});

it.each(['get', 'HEAD', 'OPTIONS', 'TRACE', 'CUSTOM'])('rejects unsupported method %s', (method) => {
  expectCode(
    () => parseHtmxBindingTarget(binding({ target: { ...binding().target, method } })),
    'method_invalid',
  );
});

it.each([
  '',
  'relative/path',
  '//example.com/path',
  'https://example.com/path',
  '/path#fragment',
  '/path\\child',
  '/path\nchild',
  `/${'x'.repeat(2048)}`,
])('rejects invalid path %j', (path) => {
  expectCode(
    () => parseHtmxBindingTarget(binding({ target: { ...binding().target, path } })),
    'path_invalid',
  );
});
```

Also add positive cases for all five supported methods, `/`, `/orders/refund?view=table`, and a 2048-character maximum path.

- [ ] **Step 6: Run focused tests and verify RED on primitive cases**

```bash
npm test -- tests/htmx-binding-descriptor.test.ts
```

Expected: parser-shape tests may pass, but source/method/path cases fail until validation is implemented.

- [ ] **Step 7: Implement primitive validators**

Use these exact rules:

```ts
function parseSourceId(value: unknown): string {
  if (typeof value !== 'string' || !SOURCE_ID_PATTERN.test(value)) {
    throw descriptorError('source_id_invalid', 'HTMX sourceId is invalid.');
  }
  return value;
}

function parseMethod(value: unknown): HtmxRequestMethod {
  if (typeof value !== 'string' || !HTMX_METHODS.has(value as HtmxRequestMethod)) {
    throw descriptorError('method_invalid', 'HTMX request method is unsupported.');
  }
  return value as HtmxRequestMethod;
}

function parsePath(value: unknown): string {
  if (
    typeof value !== 'string'
    || value.length < 1
    || value.length > 2048
    || !value.startsWith('/')
    || value.startsWith('//')
    || value.includes('#')
    || value.includes('\\')
    || ASCII_CONTROL_PATTERN.test(value)
  ) {
    throw descriptorError('path_invalid', 'HTMX request path is invalid.');
  }
  return value;
}
```

Do not normalize method case or rewrite paths.

- [ ] **Step 8: Add RED list-validation tests**

Cover:

```text
inputNames is not an array
requiredInputNames is not an array
empty entry
duplicate entry
non-string entry
required name not present in inputNames
```

Expected code: `input_mapping_invalid`.

- [ ] **Step 9: Implement strict list parsing and subset validation**

Use a helper that copies and freezes a unique non-empty string array:

```ts
function parseNameList(value: unknown, label: string): readonly string[] {
  if (!Array.isArray(value)) {
    throw descriptorError('input_mapping_invalid', `${label} must be an array.`);
  }

  const seen = new Set<string>();
  const copy: string[] = [];
  for (const entry of value) {
    if (typeof entry !== 'string' || entry.length === 0 || seen.has(entry)) {
      throw descriptorError('input_mapping_invalid', `${label} contains an invalid entry.`);
    }
    seen.add(entry);
    copy.push(entry);
  }

  return Object.freeze(copy);
}
```

After parsing both lists, require every `requiredInputNames` entry to exist in `inputNames`.

Return an `Object.freeze({...})` target containing frozen array copies.

- [ ] **Step 10: Run focused tests and typecheck**

```bash
npm test -- tests/htmx-binding-descriptor.test.ts
npm run typecheck
```

Expected: PASS.

- [ ] **Step 11: Commit Task 1 GREEN**

```bash
git add packages/browser-runtime/src/htmx-binding-descriptor.ts packages/browser-runtime/tests/htmx-binding-descriptor.test.ts
git commit -m "feat(htmx): add strict binding target parser"
```

---

### Task 2: ActionDefinition-Derived Named Input Plan

**Files:**
- Modify: `packages/browser-runtime/src/htmx-binding-descriptor.ts`
- Modify: `packages/browser-runtime/tests/htmx-binding-descriptor.test.ts`

**Interfaces:**
- Consumes: exact `ActionDefinition.inputSchema`.
- Produces:

```ts
export interface CreateHtmxBindingTargetOptions {
  readonly sourceId: string;
  readonly method: HtmxRequestMethod;
  readonly path: string;
}

export function createHtmxBindingTarget(
  definition: ActionDefinition,
  options: CreateHtmxBindingTargetOptions,
): HtmxBindingTarget;
```

The builder is producer-side. It never accepts `inputNames` or `requiredInputNames` from caller/options; those lists come only from the exact ActionDefinition schema.

- [ ] **Step 1: Write RED tests for deterministic valid mapping**

Add tests using an ActionDefinition helper:

```ts
function definition(inputSchema: Record<string, unknown>): ActionDefinition {
  return {
    id: 'prep_list.add_item',
    version: 1,
    title: 'Add item',
    description: 'Add an item',
    inputSchema,
    scope: 'page_scoped',
    effect: 'reversible_write',
    risk: 'moderate',
    idempotency: 'none',
    outputSensitivity: 'normal',
    outputContentTrust: 'trusted_application_data',
    contextRequirements: [],
  };
}
```

Required tests:

```ts
it('derives deterministic named input mapping from a closed object schema', () => {
  const target = createHtmxBindingTarget(
    definition({
      type: 'object',
      properties: {
        note: { type: 'string' },
        item: { type: 'string' },
      },
      required: ['item'],
      additionalProperties: false,
    }),
    { sourceId: 'htmx-src-1', method: 'POST', path: '/prep-list/items' },
  );

  expect(target.inputNames).toEqual(['item', 'note']);
  expect(target.requiredInputNames).toEqual(['item']);
});

it('supports a closed zero-input object schema', () => {
  const target = createHtmxBindingTarget(
    definition({ type: 'object', additionalProperties: false }),
    { sourceId: 'htmx-src-1', method: 'POST', path: '/prep-list/items' },
  );

  expect(target.inputNames).toEqual([]);
  expect(target.requiredInputNames).toEqual([]);
});
```

Lexically sort both derived lists so schema property insertion order never becomes invocation authority.

- [ ] **Step 2: Run focused tests and verify RED**

```bash
npm test -- tests/htmx-binding-descriptor.test.ts
```

Expected: FAIL because `createHtmxBindingTarget` does not exist.

- [ ] **Step 3: Commit Task 2 RED**

```bash
git add packages/browser-runtime/tests/htmx-binding-descriptor.test.ts
git commit -m "test(htmx): define Action input mapping contract"
```

- [ ] **Step 4: Implement closed-object schema derivation**

Add an internal helper:

```ts
interface DerivedInputMapping {
  readonly inputNames: readonly string[];
  readonly requiredInputNames: readonly string[];
}
```

A schema is supported only if:

```text
schema is a non-array object
schema.type === 'object'
schema.additionalProperties === false
schema.properties is absent or a non-array object
schema.required is absent or an array
no open/composition keyword can alter the finite top-level key set
```

Reject these exact top-level mapping keywords when present:

```ts
const UNSUPPORTED_TOP_LEVEL_MAPPING_KEYWORDS = [
  '$ref',
  '$dynamicRef',
  'allOf',
  'anyOf',
  'oneOf',
  'not',
  'if',
  'then',
  'else',
  'patternProperties',
  'unevaluatedProperties',
  'dependentSchemas',
] as const;
```

Rationale: any of these can change/condition the accepted top-level key set, so the reference builder must not guess a finite mapping.

`properties` keys must be non-empty strings. `required` entries must be unique non-empty strings and a subset of `properties` keys.

- [ ] **Step 5: Write RED unsupported-schema table tests**

Cover at least:

```text
null / array / primitive schema shape
type != object
additionalProperties absent
additionalProperties true
properties is array/primitive
required is primitive/object
duplicate required names
empty required name
required name not present in properties
patternProperties present
$ref present
allOf present
anyOf present
oneOf present
if/then/else present
unevaluatedProperties present
dependentSchemas present
```

Every case must throw `HtmxBindingDescriptorError` with code `input_schema_unsupported`.

- [ ] **Step 6: Implement fail-closed schema validation**

Do not recursively validate property schemas. The ActionDefinition contract owns nested schema validity. T-601 only proves the top-level caller-key set is finite and unambiguous.

Derive:

```ts
const inputNames = Object.freeze(Object.keys(properties).sort());
const requiredInputNames = Object.freeze([...required].sort());
```

Then call the same target construction/validation path used by `parseHtmxBindingTarget()` so producer and consumer primitive rules cannot drift.

- [ ] **Step 7: Add nested-value non-flattening proof**

Use a property whose schema is nested:

```ts
properties: {
  item: {
    type: 'object',
    properties: { label: { type: 'string' } },
  },
}
```

Assert `inputNames` is exactly `['item']`, not `['item.label']` or bracket notation.

- [ ] **Step 8: Run focused tests and typecheck**

```bash
npm test -- tests/htmx-binding-descriptor.test.ts
npm run typecheck
```

Expected: PASS.

- [ ] **Step 9: Commit Task 2 GREEN**

```bash
git add packages/browser-runtime/src/htmx-binding-descriptor.ts packages/browser-runtime/tests/htmx-binding-descriptor.test.ts
git commit -m "feat(htmx): derive named binding input plan"
```

---

### Task 3: Immutability and Compile-Time API Contract

**Files:**
- Modify: `packages/browser-runtime/src/htmx-binding-descriptor.ts`
- Modify: `packages/browser-runtime/tests/htmx-binding-descriptor.test.ts`
- Create: `packages/browser-runtime/tests/htmx-binding-descriptor.typecheck.ts`

**Interfaces:**
- Consumes: Task 1/2 public API unchanged.
- Produces: readonly/frozen `HtmxBindingTarget` objects safe from retained mutable array references.

- [ ] **Step 1: Write RED runtime immutability tests**

Add proofs that parsed target arrays are copied:

```ts
it('does not retain mutable binding target arrays by reference', () => {
  const inputNames = ['item'];
  const requiredInputNames = ['item'];
  const raw = binding({
    target: {
      sourceId: 'htmx-src-1',
      method: 'POST',
      path: '/prep-list/items',
      inputNames,
      requiredInputNames,
    },
  });

  const parsed = parseHtmxBindingTarget(raw);
  inputNames.push('attacker');
  requiredInputNames.length = 0;

  expect(parsed.inputNames).toEqual(['item']);
  expect(parsed.requiredInputNames).toEqual(['item']);
  expect(Object.isFrozen(parsed)).toBe(true);
  expect(Object.isFrozen(parsed.inputNames)).toBe(true);
  expect(Object.isFrozen(parsed.requiredInputNames)).toBe(true);
});
```

Also mutate the original `definition.inputSchema.required` / `properties` after `createHtmxBindingTarget()` and prove the constructed target is unchanged.

- [ ] **Step 2: Run focused tests and verify RED if any references are still retained**

```bash
npm test -- tests/htmx-binding-descriptor.test.ts
```

Expected: FAIL until all target/list outputs are defensive frozen copies.

- [ ] **Step 3: Harden construction/parsing to freeze copies consistently**

Use one internal constructor path so both parser and builder produce the same immutable object shape.

Do not deep-freeze nested ActionDefinition property schemas because no nested schema object is retained by the target.

- [ ] **Step 4: Add compile-time readonly contract**

Create `packages/browser-runtime/tests/htmx-binding-descriptor.typecheck.ts`:

```ts
import {
  createHtmxBindingTarget,
  parseHtmxBindingTarget,
  type HtmxBindingTarget,
  type HtmxRequestMethod,
} from '../src/htmx-binding-descriptor.js';
import type { ActionDefinition, RuntimeBinding } from '../src/types.js';

declare const definition: ActionDefinition;
declare const binding: RuntimeBinding;

const method: HtmxRequestMethod = 'POST';
const created: HtmxBindingTarget = createHtmxBindingTarget(definition, {
  sourceId: 'htmx-src-1',
  method,
  path: '/items',
});
const parsed: HtmxBindingTarget = parseHtmxBindingTarget(binding);

// @ts-expect-error readonly target property
created.path = '/other';
// @ts-expect-error readonly array
created.inputNames.push('other');
// @ts-expect-error unsupported method
const invalidMethod: HtmxRequestMethod = 'HEAD';

void parsed;
void invalidMethod;
```

- [ ] **Step 5: Run typecheck and focused tests**

```bash
npm run typecheck
npm test -- tests/htmx-binding-descriptor.test.ts
```

Expected: PASS; the `@ts-expect-error` lines must be consumed by real type errors.

- [ ] **Step 6: Commit Task 3 GREEN**

```bash
git add packages/browser-runtime/src/htmx-binding-descriptor.ts packages/browser-runtime/tests/htmx-binding-descriptor.test.ts packages/browser-runtime/tests/htmx-binding-descriptor.typecheck.ts
git commit -m "test(htmx): lock descriptor immutability contract"
```

---

### Task 4: Scope Guards, Full Verification, Decision Promotion, Review Prep

**Files:**
- Modify: `packages/browser-runtime/tests/htmx-binding-descriptor.test.ts`
- Modify: `docs/DECISION-REGISTER.md`
- Modify: `TASKS.md`
- Modify: `STATUS.md`
- Modify: `REVIEW_REQUEST.md`

**Interfaces:**
- Consumes: completed T-601 descriptor API and tests.
- Produces: verified T-601 review-ready checkpoint; no T-602 implementation.

- [ ] **Step 1: Add source-scope guard tests**

Use Node `fs` only inside the test to read `src/htmx-binding-descriptor.ts` and assert forbidden runtime coupling is absent:

```ts
import { readFileSync } from 'node:fs';

it('keeps the T-601 descriptor free of browser/HTMX execution dependencies', () => {
  const source = readFileSync(new URL('../src/htmx-binding-descriptor.ts', import.meta.url), 'utf8');

  expect(source).not.toContain("from 'htmx.org'");
  expect(source).not.toContain('htmx.ajax(');
  expect(source).not.toContain('document.');
  expect(source).not.toContain('window.');
  expect(source).not.toContain('fetch(');
  expect(source).not.toContain('@surfacerelay/laravel');
});
```

If Node types are not available to the Vitest test environment, do not add `@types/node` merely for this guard. Instead keep the scope proof as repository diff verification commands below; no dependency addition is allowed for a test convenience.

- [ ] **Step 2: Run the complete browser-runtime suite**

```bash
cd packages/browser-runtime
npm test
npm run typecheck
```

Expected: all existing **103 baseline tests plus the new T-601 tests** pass; record the new exact test count.

- [ ] **Step 3: Run repository contract validation**

From repository root:

```bash
python scripts/validate.py
```

Expected: PASS with frozen `spec/0.1/**` unchanged.

- [ ] **Step 4: Prove scope boundaries from the diff**

Run:

```bash
git diff --name-only main...HEAD -- packages/laravel/src spec/0.1
```

Expected: no output.

Then inspect all changed paths:

```bash
git diff --name-only main...HEAD
```

Expected implementation paths are limited to:

```text
packages/browser-runtime/src/htmx-binding-descriptor.ts
packages/browser-runtime/tests/htmx-binding-descriptor.test.ts
packages/browser-runtime/tests/htmx-binding-descriptor.typecheck.ts
docs/superpowers/specs/2026-09-11-htmx-binding-descriptor-design.md
docs/superpowers/plans/2026-09-11-htmx-binding-descriptor.md
docs/DECISION-REGISTER.md
TASKS.md
STATUS.md
REVIEW_REQUEST.md
```

There must be no `htmx-browser-driver.ts`, fixture app, dependency-file change, or frozen contract change.

- [ ] **Step 5: Self-review the implementation against all 20 spec acceptance criteria**

Explicitly verify:

```text
exact source identity grammar
five-method subset
same-origin path-reference grammar
strict target key set
closed schema derivation
deterministic sorted mapping
required subset validation
nested non-flattening
immutable defensive copies
no trusted authority/capabilities in target
no DOM/HTMX/network/cancellation code
no Laravel production changes
no spec/0.1 changes
```

If any criterion lacks an executable test or diff proof, add that proof before updating task status.

- [ ] **Step 6: Promote D-053 only after the full green gate**

Change D-053 from `PROPOSED` to `ACCEPTED` with wording that records **descriptor semantics only**. Do not claim HTMX browser execution or general portability yet.

D-020 stays `PROPOSED` until T-604.

- [ ] **Step 7: Update T-601 tracking**

`TASKS.md` must include:

```text
T-601 — DONE / SELF-REVIEWED / READY FOR EXTERNAL REVIEW
RED commit(s) + workflow evidence
GREEN commit(s) + workflow evidence
final browser test count
typecheck green
python scripts/validate.py green
D-053 ACCEPTED
D-020 still PROPOSED
```

`STATUS.md` must include:

```text
branch/head/base
exact changed files
verification commands/results
known limitation: no DOM/HTMX execution yet
next task: T-602 NOT STARTED
```

`REVIEW_REQUEST.md` must ask reviewers to focus on:

```text
silent retarget risk
path/method ambiguity
open-ended schema escape paths
caller-controlled mapping promotion
trusted-authority leakage
accidental DOM/HTMX/T-602 scope creep
immutability/reference retention
frozen contract drift
```

- [ ] **Step 8: Commit review-prep tracking**

```bash
git add docs/DECISION-REGISTER.md TASKS.md STATUS.md REVIEW_REQUEST.md
git commit -m "docs(htmx): prepare T-601 external review"
```

- [ ] **Step 9: Run final full repository CI on the exact review-prep head**

Push the feature branch only if execution authorization includes pushing the branch. Verify the repository `validate` workflow is fully green on the exact head before claiming review-ready status.

Expected jobs:

```text
contract
php-lint
browser
php-tests PHP 8.3 / Illuminate 12
php-tests PHP 8.4 / Illuminate 12
php-tests PHP 8.3 / Illuminate 13
php-tests PHP 8.4 / Illuminate 13
```

All 7 must be green.

- [ ] **Step 10: Stop at the external-review gate**

Do **not** create a pull request, merge, delete the branch, begin T-602, or promote D-020. Report the exact review-ready head and evidence and wait for explicit authorization for the next gate.

---

## Plan Self-Review Result

- Spec coverage: all T-601 acceptance criteria map to Tasks 1–4.
- Placeholder scan: no TBD/TODO/“implement later” steps remain.
- Type consistency: `HtmxRequestMethod`, `HtmxBindingTarget`, `HtmxBindingDescriptorError`, `parseHtmxBindingTarget()`, and `createHtmxBindingTarget()` are defined once and used consistently.
- Scope correction: no new `src/index.ts` barrel is planned because the current package has no barrel and established tests import source modules directly.
- T-602/T-603/T-604 boundary: no DOM lookup, HTMX runtime execution, fixture app, or cross-driver conformance is implemented here.
- Frozen contracts: no `spec/0.1/**` change is permitted.
