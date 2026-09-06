# WebMCP Registration Lifecycle Implementation Plan

**Goal:** Implement T-303 as a browser-runtime snapshot registration subsystem that projects exact ActionDefinition + RuntimeBinding pairs into deterministic WebMCP tools, registers them sequentially, owns registration lifetime through one AbortController-backed lease, cleans up partial failures, and preserves per-execution cancellation separately.

**Architecture:** Keep WebMCP API compatibility types, semantic/tool projection, and registration lifecycle as separate browser-runtime units. Preflight the entire snapshot before the first browser registration, use exact T-301 DriverRegistry lookup and T-302 annotations without reinterpretation, then register a deterministic tool-name-sorted batch with one registration signal. A returned lease owns cleanup; failed batches abort the generation and rethrow the original browser error.

**Tech Stack:** TypeScript 5.9, ES2022/DOM types, Vitest 3.2, existing SurfaceRelay browser-runtime types/DriverRegistry/WebMCP projection.

**Spec:** `docs/design/webmcp-registration-lifecycle.md`

## Global Constraints

- Public repo artifacts remain tool-neutral; no agent-framework-specific tracked paths or prose.
- `spec/0.1` remains unchanged.
- Canonical WebMCP tool identity is `<action-id>.v<version>`.
- WebMCP tool names must be 1–128 ASCII characters drawn only from alphanumeric characters plus `_`, `-`, `.`; invalid/too-long names fail before registration.
- Binding action identity must exactly match ActionDefinition `id + version`; no version fallback or negotiation.
- Duplicate projected tool names are ambiguous and fail before registration; do not append `bindingId` or component/runtime target metadata.
- `DriverRegistry.requireDriver(binding.driver)` is the only driver-support check; it confers no authorization or lifecycle validity.
- Registration lifetime signal and per-execution cancellation signal are distinct authorities.
- Registration is sequential after deterministic sort by projected tool name.
- Partial registration failure aborts the generation and rethrows the original browser registration error.
- `exposedTo`, cross-origin policy, `getTools()`, `executeTool()`, Livewire target execution, stale/expiry/revocation checks, automatic diff reconciliation, and T-304 behavior are out of scope.

---

### Task 1: Narrow WebMCP Compatibility Boundary

**Files:**
- Create: `packages/browser-runtime/src/webmcp-types.ts`
- Create: `packages/browser-runtime/tests/webmcp-types.test.ts`

**Interfaces:**
- Consumes: `WebMcpAnnotations` from `src/webmcp-projection.ts`.
- Produces:
  - `WebMcpToolExecuteOptions { signal?: AbortSignal }`
  - `WebMcpTool { name, title, description, inputSchema, annotations, execute(...) }`
  - `WebMcpRegisterToolOptions { signal: AbortSignal }`
  - `WebMcpModelContext { registerTool(tool, options): Promise<void> }`

- [ ] **Step 1: Write the failing type/behavior test**

Create a boundary test that constructs a structural fake implementing the exact port and proves the same tool/options objects can be passed without ambient `document.modelContext` access:

```ts
import { describe, expect, it } from 'vitest';
import type {
  WebMcpModelContext,
  WebMcpRegisterToolOptions,
  WebMcpTool,
} from '../src/webmcp-types.js';

it('defines a narrow async registerTool compatibility port', async () => {
  const calls: Array<{ tool: WebMcpTool; options: WebMcpRegisterToolOptions }> = [];
  const context: WebMcpModelContext = {
    async registerTool(tool, options) {
      calls.push({ tool, options });
    },
  };

  const controller = new AbortController();
  const tool: WebMcpTool = {
    name: 'orders.get.v1',
    title: 'Get order',
    description: 'Gets an order',
    inputSchema: {},
    annotations: {
      readOnlyHint: true,
      untrustedContentHint: false,
      consequentialHint: false,
    },
    async execute() {
      return null;
    },
  };

  await context.registerTool(tool, { signal: controller.signal });

  expect(calls).toEqual([{ tool, options: { signal: controller.signal } }]);
});
```

- [ ] **Step 2: Run RED verification**

Run:

```bash
cd packages/browser-runtime
npm test -- tests/webmcp-types.test.ts
```

Expected: FAIL because `../src/webmcp-types.js` does not exist.

- [ ] **Step 3: Add the minimal compatibility types**

Create `webmcp-types.ts`:

```ts
import type { WebMcpAnnotations } from './webmcp-projection.js';

export interface WebMcpToolExecuteOptions {
  signal?: AbortSignal;
}

export interface WebMcpTool {
  name: string;
  title: string;
  description: string;
  inputSchema: Record<string, unknown>;
  annotations: WebMcpAnnotations;
  execute(
    input: Record<string, unknown>,
    options: WebMcpToolExecuteOptions,
  ): Promise<unknown>;
}

export interface WebMcpRegisterToolOptions {
  signal: AbortSignal;
}

export interface WebMcpModelContext {
  registerTool(
    tool: WebMcpTool,
    options: WebMcpRegisterToolOptions,
  ): Promise<void>;
}
```

Do not add an ambient `document` adapter yet; the explicit port is the browser API compatibility boundary used by T-303 and remains directly compatible with a real `document.modelContext` object by structural typing.

- [ ] **Step 4: Run GREEN verification**

Run:

```bash
cd packages/browser-runtime
npm run typecheck
npm test -- tests/webmcp-types.test.ts
```

Expected: typecheck PASS and focused test PASS.

- [ ] **Step 5: Commit**

```bash
git add packages/browser-runtime/src/webmcp-types.ts packages/browser-runtime/tests/webmcp-types.test.ts
git commit -m "feat(browser): add WebMCP registration boundary"
```

---

### Task 2: Exact Bound-Action Tool Projection and Identity Preflight

**Files:**
- Create: `packages/browser-runtime/src/webmcp-tool-projection.ts`
- Create: `packages/browser-runtime/tests/webmcp-tool-projection.test.ts`

**Interfaces:**
- Consumes: `ActionDefinition`, `RuntimeBinding`, `WebMcpTool`, `WebMcpToolExecuteOptions`, `projectAnnotations()`.
- Produces:
  - `BoundActionTool { definition: ActionDefinition; binding: RuntimeBinding }`
  - `projectWebMcpToolName(definition): string`
  - `projectBoundActionTool(candidate, execute): WebMcpTool`

- [ ] **Step 1: Write failing identity/name tests**

Cover these cases with table-driven Vitest tests:

```ts
expect(projectWebMcpToolName(definition({ id: 'prep_list.add_item', version: 1 })))
  .toBe('prep_list.add_item.v1');

expect(projectWebMcpToolName(definition({ id: 'orders.refund', version: 3 })))
  .toBe('orders.refund.v3');
```

Add failure tests proving:

```ts
expect(() => projectBoundActionTool({
  definition: definition({ id: 'orders.refund', version: 2 }),
  binding: binding({ action: { id: 'orders.refund', version: 1 } }),
}, executor)).toThrow('Action definition and binding identity must match exactly.');
```

and a too-long projected name:

```ts
const id = `${'a'.repeat(62)}.${'b'.repeat(62)}`; // valid Action ID, projected name exceeds 128
expect(() => projectWebMcpToolName(definition({ id, version: 1234 })))
  .toThrow('Projected WebMCP tool name is invalid.');
```

Also assert projection uses only stable definition metadata and T-302 annotations:

```ts
expect(tool).toMatchObject({
  name: 'orders.get.v1',
  title: definition.title,
  description: definition.description,
  inputSchema: definition.inputSchema,
  annotations: projectAnnotations(definition),
});
```

- [ ] **Step 2: Run RED verification**

Run:

```bash
cd packages/browser-runtime
npm test -- tests/webmcp-tool-projection.test.ts
```

Expected: FAIL because projection module/functions do not exist.

- [ ] **Step 3: Implement exact identity and name projection**

Create `webmcp-tool-projection.ts` with:

```ts
import { projectAnnotations } from './webmcp-projection.js';
import type { ActionDefinition, RuntimeBinding } from './types.js';
import type { WebMcpTool, WebMcpToolExecuteOptions } from './webmcp-types.js';

const WEBMCP_TOOL_NAME_PATTERN = /^[A-Za-z0-9_.-]{1,128}$/;

export interface BoundActionTool {
  definition: ActionDefinition;
  binding: RuntimeBinding;
}

export type BoundActionExecutor = (
  input: Record<string, unknown>,
  options: WebMcpToolExecuteOptions,
) => Promise<unknown>;

export function projectWebMcpToolName(definition: ActionDefinition): string {
  const name = `${definition.id}.v${definition.version}`;
  if (!WEBMCP_TOOL_NAME_PATTERN.test(name)) {
    throw new Error('Projected WebMCP tool name is invalid.');
  }
  return name;
}

export function projectBoundActionTool(
  candidate: BoundActionTool,
  execute: BoundActionExecutor,
): WebMcpTool {
  if (
    candidate.definition.id !== candidate.binding.action.id
    || candidate.definition.version !== candidate.binding.action.version
  ) {
    throw new Error('Action definition and binding identity must match exactly.');
  }

  return {
    name: projectWebMcpToolName(candidate.definition),
    title: candidate.definition.title,
    description: candidate.definition.description,
    inputSchema: candidate.definition.inputSchema,
    annotations: projectAnnotations(candidate.definition),
    execute,
  };
}
```

Do not validate the full ActionDefinition schema in T-303; the browser runtime receives contract-valid definitions from the server/runtime boundary. Only T-303-owned exact identity and WebMCP-name constraints are enforced here.

- [ ] **Step 4: Add execution-callback preservation tests**

Prove the callback passed to projection receives input/options unchanged and that projection does not inspect binding target metadata or output sensitivity:

```ts
const execute = vi.fn(async () => ({ ok: true }));
const input = { orderId: 42 };
const signal = new AbortController().signal;
const tool = projectBoundActionTool(candidate, execute);

await expect(tool.execute(input, { signal })).resolves.toEqual({ ok: true });
expect(execute).toHaveBeenCalledWith(input, { signal });
```

- [ ] **Step 5: Run GREEN verification**

Run:

```bash
cd packages/browser-runtime
npm run typecheck
npm test -- tests/webmcp-tool-projection.test.ts
```

Expected: all focused tests PASS.

- [ ] **Step 6: Commit**

```bash
git add packages/browser-runtime/src/webmcp-tool-projection.ts packages/browser-runtime/tests/webmcp-tool-projection.test.ts
git commit -m "feat(browser): project exact WebMCP tools"
```

---

### Task 3: Snapshot Preflight, Sequential Registration, and Lease Cleanup

**Files:**
- Create: `packages/browser-runtime/src/webmcp-registration-lifecycle.ts`
- Create: `packages/browser-runtime/tests/webmcp-registration-lifecycle.test.ts`

**Interfaces:**
- Consumes: `DriverRegistry`, `BoundActionTool`, `projectBoundActionTool()`, `WebMcpModelContext`.
- Produces:
  - `WebMcpRegistrationLease { dispose(): void }`
  - `WebMcpRegistrationLifecycle.register(candidates: readonly BoundActionTool[]): Promise<WebMcpRegistrationLease>`

- [ ] **Step 1: Write failing preflight tests**

Construct a fake `WebMcpModelContext` that records calls and a real `DriverRegistry` populated with fake `BindingDriver`s.

Add RED tests proving no browser registration happens when:

1. definition/binding identity mismatches;
2. driver is unsupported;
3. projected name is invalid/too long;
4. two candidates project to the same name, including two different bindings for the same action/version.

Example duplicate test:

```ts
await expect(lifecycle.register([
  candidate({ bindingId: 'bind-a' }),
  candidate({ bindingId: 'bind-b' }),
])).rejects.toThrow('Duplicate WebMCP tool identity in registration snapshot.');

expect(modelContext.calls).toHaveLength(0);
```

Also verify preflight does not invoke `BindingDriver.execute()`.

- [ ] **Step 2: Run RED verification**

Run:

```bash
cd packages/browser-runtime
npm test -- tests/webmcp-registration-lifecycle.test.ts
```

Expected: FAIL because lifecycle class does not exist.

- [ ] **Step 3: Implement full-snapshot preflight**

Create the coordinator skeleton:

```ts
export interface WebMcpRegistrationLease {
  dispose(): void;
}

export class WebMcpRegistrationLifecycle {
  constructor(
    private readonly modelContext: WebMcpModelContext,
    private readonly drivers: DriverRegistry,
  ) {}

  async register(
    candidates: readonly BoundActionTool[],
  ): Promise<WebMcpRegistrationLease> {
    const projected = candidates.map((candidate) => {
      this.drivers.requireDriver(candidate.binding.driver);

      return projectBoundActionTool(candidate, async (input, options) => {
        const driver = this.drivers.requireDriver(candidate.binding.driver);
        return driver.execute(candidate.binding, input, {
          signal: options.signal,
        });
      });
    });

    const names = new Set<string>();
    for (const tool of projected) {
      if (names.has(tool.name)) {
        throw new Error('Duplicate WebMCP tool identity in registration snapshot.');
      }
      names.add(tool.name);
    }

    projected.sort((left, right) => left.name.localeCompare(right.name));

    // registration implemented in the next step
    throw new Error('registration not implemented');
  }
}
```

Before committing or marking green, continue directly to registration/lease implementation; the temporary final throw is only an intermediate local step and must never be committed.

- [ ] **Step 4: Write sequential-registration and signal tests**

Add tests that deliberately hold registration promises to prove the second call does not begin until the first resolves:

```ts
const first = deferred<void>();
modelContext.onRegister = (tool) => {
  if (tool.name === 'a.one.v1') return first.promise;
  return Promise.resolve();
};

const pending = lifecycle.register([candidateB, candidateA]);
await nextMicrotask();
expect(modelContext.names()).toEqual(['a.one.v1']);
first.resolve();
await pending;
expect(modelContext.names()).toEqual(['a.one.v1', 'b.two.v1']);
```

Assert every `registerTool` call receives the same non-aborted registration `AbortSignal`, and input order does not change alphabetical registration order.

- [ ] **Step 5: Implement registration lease and successful batch**

Use one controller per `register()` call and a private idempotent lease:

```ts
class RegistrationLease implements WebMcpRegistrationLease {
  #disposed = false;

  constructor(private readonly controller: AbortController) {}

  dispose(): void {
    if (this.#disposed) return;
    this.#disposed = true;
    this.controller.abort();
  }
}
```

Registration algorithm:

```ts
const controller = new AbortController();

try {
  for (const tool of projected) {
    await this.modelContext.registerTool(tool, {
      signal: controller.signal,
    });
  }
} catch (error) {
  controller.abort();
  throw error;
}

return new RegistrationLease(controller);
```

An empty `projected` array follows the same path and returns a live disposable lease without browser calls.

- [ ] **Step 6: Add partial-failure and lease tests**

Prove:

- a failure after earlier successful registrations aborts the shared signal;
- the exact original `Error` object is rethrown with object identity preserved;
- repeated `dispose()` is safe;
- successful lease signal is initially live and becomes aborted after disposal;
- empty snapshot makes zero `registerTool` calls and returns a disposable lease.

Example original-error assertion:

```ts
const failure = new DOMException('denied', 'NotAllowedError');
modelContext.failOn('b.two.v1', failure);

await expect(lifecycle.register(candidates)).rejects.toBe(failure);
expect(modelContext.firstRegistrationSignal()?.aborted).toBe(true);
```

- [ ] **Step 7: Add execution cancellation/identity tests**

After a successful registration, invoke the captured registered tool callback manually and verify:

```ts
const executionController = new AbortController();
await tool.execute({ name: 'passport' }, { signal: executionController.signal });

expect(driver.execute).toHaveBeenCalledWith(
  exactBindingObject,
  { name: 'passport' },
  { signal: executionController.signal },
);
```

Also assert:

```ts
expect(executionController.signal).not.toBe(registrationSignal);
```

and that lookup uses the exact driver name captured in `binding.driver`. Do not cache a fallback driver or invoke any Livewire-specific browser API.

- [ ] **Step 8: Run GREEN verification**

Run:

```bash
cd packages/browser-runtime
npm run typecheck
npm test
```

Expected: all browser tests PASS, including T-301/T-302 regression suites.

- [ ] **Step 9: Commit**

```bash
git add packages/browser-runtime/src/webmcp-registration-lifecycle.ts packages/browser-runtime/tests/webmcp-registration-lifecycle.test.ts
git commit -m "feat(browser): add WebMCP registration lifecycle"
```

---

### Task 4: Decision/Source-of-Truth Checkpoint and Full Verification

**Files:**
- Modify: `docs/DECISION-REGISTER.md`
- Modify: `STATUS.md`
- Modify: `TASKS.md`
- Modify: `REVIEW_REQUEST.md`

**Interfaces:**
- Consumes: completed T-303 implementation and test evidence.
- Produces: D-037/D-038 ACCEPTED, T-303 DONE pending/after review evidence, T-304 explicitly not started.

- [ ] **Step 1: Run full browser verification and capture exact count**

Run:

```bash
cd packages/browser-runtime
npm run typecheck
npm test
```

Record exact Vitest test count from output; do not guess from planned cases.

- [ ] **Step 2: Run full repository CI on the exact feature head**

Push/attach the feature head and require all existing jobs:

```text
contract
php-lint
php-tests PHP 8.3 × Illuminate 12 × Testbench 10
php-tests PHP 8.3 × Illuminate 13 × Testbench 11
php-tests PHP 8.4 × Illuminate 12 × Testbench 10
php-tests PHP 8.4 × Illuminate 13 × Testbench 11
browser
```

Expected: all 7 jobs GREEN.

- [ ] **Step 3: Record D-037 and D-038**

Append exact accepted decisions:

```text
D-037 ACCEPTED — One current WebMCP tool snapshot is owned by one SurfaceRelay registration lease and one registration AbortController. Disposal/abort ends that generation's registration authority; partial registration failure aborts the generation and preserves the original error. Registration lifetime and per-execution cancellation are separate signals/authorities.

D-038 ACCEPTED — WebMCP tool names are deterministic exact Action identity projections `<action-id>.v<version>`. Invalid/too-long or duplicate projected names fail loudly; binding IDs, component IDs, truncation, hashes, aliases, and fallback binding selection are not used to manufacture browser tool identity.
```

- [ ] **Step 4: Update task/status/review docs**

State explicitly:

```text
M3 IN PROGRESS
T-301 DONE / REVIEWED
T-302 DONE / REVIEWED
T-303 DONE / pending review (then DONE / REVIEWED after review)
T-304 TODO / not started
T-305 TODO
```

Include TDD RED/GREEN commit SHAs and workflow IDs once they exist. Do not claim stale/expiry/revocation checks, Livewire browser execution, final D-026 codes, cross-origin exposure, or M4 trust controls.

- [ ] **Step 5: External-style diff review**

Compare `main...feat/webmcp-registration-lifecycle` and verify:

```text
✓ no spec/0.1 changes
✓ no Laravel production changes
✓ no Livewire.find/$wire.$call implementation
✓ no exposedTo public option
✓ no getTools/executeTool orchestration
✓ no bindingId/componentId-derived tool naming
✓ no incremental reconcile manager
✓ no production trust-control claims
✓ only T-303 browser runtime + focused tests/docs
```

- [ ] **Step 6: Commit checkpoint docs**

```bash
git add docs/DECISION-REGISTER.md STATUS.md TASKS.md REVIEW_REQUEST.md
git commit -m "docs(review): prepare T-303 external review"
```

- [ ] **Step 7: Fresh CI on review checkpoint and merge only if all green**

Require all 7 jobs GREEN on the exact review checkpoint. If clean, fast-forward `main`, run fresh merged-main CI, then add only a docs-only reviewed-main checkpoint marking T-303 DONE/REVIEWED and T-304 not started.

## Plan Self-Review

- Spec coverage: all T-303 sections map to Tasks 1–4: explicit browser boundary, exact/versioned identity, full preflight, ambiguity rejection, deterministic sequential registration, lease disposal, partial-failure cleanup, execution-signal forwarding, empty snapshot, cross-origin exclusion, decisions, and final review.
- Placeholder scan: no TBD/TODO/"handle edge cases" placeholders are used as implementation instructions; temporary code in Task 3 Step 3 is explicitly forbidden from being committed and is completed in the immediately following steps.
- Type consistency: `WebMcpModelContext`, `WebMcpTool`, `BoundActionTool`, `WebMcpRegistrationLease`, and execution option signatures are defined before downstream use and match the approved design.
- Scope: no T-304 Livewire execution or M4 trust controls are included.
