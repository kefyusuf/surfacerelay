# T-704 — Optional OpenAPI Importer Implementation Plan

**Status:** APPROVED / IMPLEMENTATION NOT STARTED  

> **For agentic workers:** execute this plan task-by-task only after a separate explicit plan-approval gate. Use RED → GREEN for behavior-bearing tasks and perform the project self-review after every implementation decision.

**Goal:** Add a bounded, framework-neutral OpenAPI 3.1/3.2 importer that turns one caller-supplied OpenAPI document into deterministic import candidates and diagnostics, then materializes a canonical SurfaceRelay Action Definition only after explicit resolution of all SurfaceRelay semantics.

**Architecture:** Create a standalone Node/TypeScript package at `packages/openapi-importer`. The package owns source parsing, bounded same-document reference resolution, operation/provenance extraction, conservative schema suggestions, importer-local diagnostics, and explicit materialization. It owns no HTTP execution, RuntimeBinding generation, trusted-context authority, authorization, exposure, MCP/WebMCP behavior, or ActionBus execution.

**Tech Stack:** Node.js 22, TypeScript 5.9, Vitest 3.2, `yaml:^2.9.1` as the only production dependency, AJV 8 as test-only canonical-schema verification, npm lockfile, GitHub Actions.

**Approved design:** `docs/superpowers/specs/2026-09-19-openapi-importer-design.md`

**Design approval evidence:** `814d610e40ddbccee051885895070f9d08c7a76c` / Validate #876 — 11/11 SUCCESS.  
**Design tracking head:** `b0e4ff4bbae263c67b3680605380176d170025e8` / Validate #877 — 11/11 SUCCESS.

## Implementation rulings

### Package/runtime

T-704 implementation uses:

```text
packages/openapi-importer
Node.js 22
TypeScript
ES modules
Vitest
yaml@^2.9.1
```

The package is initially `private: true`. T-704 does not make a public npm compatibility promise.

The production package MUST NOT depend on:

- `packages/laravel`;
- `packages/laravel-mcp`;
- `packages/browser-runtime`;
- Laravel/Illuminate;
- MCP/WebMCP libraries;
- a generic OpenAPI dereferencer/validator.

The output boundary is JSON-serializable importer data and canonical Action Definition JSON, not framework objects.

### Why no generic OpenAPI parser/dereferencer in v1

T-704 deliberately does not use a broad parser such as `@apidevtools/swagger-parser` or an external-ref-oriented fork.

The approved design requires:

- one caller-supplied root document;
- no secondary filesystem reads;
- no network reference resolution;
- OpenAPI 3.1.x and 3.2.x document-family handling;
- importer-owned fail-closed ambiguity/resource policy.

A broad dereferencer would add behaviors T-704 explicitly forbids and would make the security boundary harder to prove.

### YAML policy

YAML is parsed as untrusted data.

v1 policy:

```text
YAML 1.2 core semantics
merge keys disabled
aliases unsupported
custom/explicit tags unsupported
duplicate keys rejected
multi-document YAML rejected
AST inspected before conversion to plain JSON values
no class/object construction hooks
```

Do not rely only on a positive alias-count threshold. Reject aliases entirely in v1.

### Hard resource budgets

These are v1 implementation constants and tests must enforce them:

```text
MAX_SOURCE_BYTES                 = 2 MiB
MAX_DOCUMENT_DEPTH               = 64
MAX_DOCUMENT_NODES               = 50_000
MAX_OPERATIONS                   = 1_000
MAX_DIAGNOSTICS                  = 500
MAX_REF_HOPS                     = 32
MAX_UNIQUE_REF_TARGETS           = 4_096
MAX_SCHEMA_NODES_PER_FRAGMENT    = 5_000
MAX_SOURCE_TEXT_CHARS            = 8_192
```

`MAX_DIAGNOSTICS=500` is the maximum **final report length**, including truncation evidence. Collect at most 499 ordinary diagnostics; when another diagnostic would exceed that budget, append exactly one terminal `diagnostic_limit_reached` as the 500th entry, set `truncatedDiagnostics=true`, and stop collecting diagnostics.

No task may silently raise a limit. A limit change requires explicit review because it changes the ingestion-security budget.

### Supported local reference form

v1 supports same-document JSON Pointer fragments only:

```text
#
#/components/schemas/Order
#/paths/~1orders~1{id}/get
```

Unsupported in v1:

```text
other.yaml#/...
file:///...
https://...
#namedAnchor
relative-file.yaml
```

URI-fragment and JSON Pointer decoding order is fixed:

1. require `#` or `#/` form;
2. percent-decode the fragment payload exactly once, rejecting malformed percent encoding;
3. parse JSON Pointer tokens;
4. decode `~1` then `~0`, rejecting every other `~` escape.

Invalid pointer syntax fails closed. Percent-decoding is never repeated.

### Schema suggestion subset

T-704 is not a complete OpenAPI validator or a complete OpenAPI Schema Object implementation.

The importer validates the root/version and the bounded structures it consumes. It must not claim that an input document is globally OpenAPI-conformant merely because candidate extraction succeeds. Unconsumed unrelated OpenAPI fields may remain unvalidated by T-704.

A source schema is eligible for an importer-generated suggestion only when:

- the document uses the default OAS dialect for its source family;
- no explicit/custom `jsonSchemaDialect` is present;
- the schema subtree contains no `$schema`;
- every schema keyword is in the v1 whitelist;
- all local refs resolve acyclically inside the same document;
- the resolved/inlined fragment stays within schema-node and ref budgets.

v1 whitelist:

```text
$ref
type
properties
required
items
additionalProperties
enum
const
minimum
maximum
exclusiveMinimum
exclusiveMaximum
multipleOf
minLength
maxLength
pattern
minItems
maxItems
uniqueItems
minProperties
maxProperties
```

All other schema keywords make that suggestion unresolved. Do not drop unsupported validation or OpenAPI-directional semantics and then claim equivalence.

In particular, v1 does not auto-suggest schemas containing:

```text
allOf / anyOf / oneOf / not
if / then / else
prefixItems / contains
unevaluatedProperties / unevaluatedItems
patternProperties / propertyNames
dependentSchemas / dependentRequired
format
readOnly / writeOnly
discriminator
xml
externalDocs
deprecated
examples / example
contentEncoding / contentMediaType
custom vocabulary keywords
```

Source `title` / `description` annotations inside schemas are not copied into generated canonical schemas. This avoids turning untrusted API documentation into downstream tool metadata.

### Input suggestion boundary

The importer may generate a candidate `suggestedInputSchema` only for:

1. an operation with **zero effective parameters** and no request body:
   ```json
   {"type":"object","properties":{},"additionalProperties":false}
   ```
2. an operation with **zero effective parameters** and exactly one supported `application/json` request-body schema.

If path/query/header/cookie parameters exist, or request-body media selection is ambiguous, `inputSchema` remains unresolved. T-704 v1 does not flatten or namespace HTTP transport locations into canonical Action input automatically.

### Output suggestion boundary

The importer may generate `suggestedOutputSchema` only when there is exactly one supported successful response choice:

- one explicit 2xx response with no content → suggestion is `null`;
- one explicit 2xx response with exactly one supported `application/json` schema → use the supported schema suggestion.

Multiple 2xx response shapes, wildcard success ranges, multiple media types, or unsupported schema features remain unresolved.

HTTP status codes never map to SurfaceRelay ActionResult statuses.

### Presentation boundary

OpenAPI `summary` and `description` are retained only as bounded source evidence.

Final canonical Action `title` and `description` are mandatory explicit `ImportResolution` fields in v1.

T-704 v1 therefore does not need a Markdown/HTML sanitizer to create canonical metadata and cannot leak raw source prose into tool-facing descriptions.

### Materialization boundary

`ImportResolution` must explicitly provide:

```text
id
version
title
description
scope
effect
risk
idempotency
outputSensitivity
outputContentTrust
contextRequirements
input schema choice
output schema choice
```

For input/output schema choice:

```text
use_candidate_suggestion
or
explicit caller-authored canonical schema
```

Using a candidate suggestion is allowed only when that suggestion exists and has no blocking diagnostic.

The materializer returns:

```text
{
  actionDefinition: <canonical JSON object>,
  sourceProvenance: <exact OpenApiSourceProvenance only>
}
```

HTTP/OpenAPI provenance MUST NOT be inserted into canonical Action Definition `extensions` in T-704 v1.

### Diagnostics

Importer diagnostics are local to `@surfacerelay/openapi-importer`; they are not `ActionError` codes and do not promote D-026.

Initial stable codes:

```text
source_too_large
invalid_json
invalid_yaml
yaml_alias_unsupported
yaml_tag_unsupported
document_limit_exceeded
unsupported_openapi_version
invalid_openapi_document
external_ref_forbidden
anchor_ref_unsupported
invalid_json_pointer
ref_cycle
ref_limit_exceeded
path_item_ref_sibling_ambiguous
additional_operation_unsupported
operation_limit_exceeded
unsupported_schema_dialect
schema_keyword_unsupported
schema_ref_sibling_unsupported
schema_limit_exceeded
ambiguous_input_mapping
ambiguous_success_output
source_text_too_large
missing_surface_semantics
invalid_action_identity
duplicate_action_identity
diagnostic_limit_reached
```

Messages may evolve; codes and blocking/non-blocking meaning require tests.

## Planned file structure

```text
packages/openapi-importer/
├── package.json
├── package-lock.json
├── tsconfig.json
├── README.md
├── src/
│   ├── index.ts
│   ├── diagnostics.ts
│   ├── json-value.ts
│   ├── limits.ts
│   ├── candidate.ts
│   ├── resolution.ts
│   ├── source/
│   │   ├── parse-source.ts
│   │   ├── parse-json.ts
│   │   ├── parse-yaml.ts
│   │   └── openapi-version.ts
│   ├── refs/
│   │   ├── json-pointer.ts
│   │   └── local-ref-resolver.ts
│   ├── operations/
│   │   ├── operation-selector.ts
│   │   ├── effective-parameters.ts
│   │   └── effective-security.ts
│   ├── schema/
│   │   ├── schema-subset.ts
│   │   └── schema-suggestion.ts
│   ├── import/
│   │   └── candidate-builder.ts
│   └── materialize/
│       └── action-definition-materializer.ts
└── tests/
    ├── architecture.test.ts
    ├── source-parser.test.ts
    ├── local-ref-resolver.test.ts
    ├── operation-selector.test.ts
    ├── schema-suggestion.test.ts
    ├── candidate-builder.test.ts
    ├── materializer.test.ts
    ├── support/
    │   └── canonical-action-validator.ts
    └── fixtures/
        ├── positive/
        └── negative/
```

Existing files modified only when required by their task:

```text
.github/workflows/validate.yml
README.md
TASKS.md
STATUS.md
REVIEW_REQUEST.md
```

Forbidden implementation paths:

```text
packages/laravel/src/**
packages/laravel-mcp/src/**
packages/browser-runtime/src/**
spec/0.1/**
conformance/targets/**
scripts/conformance_model.py
scripts/run_conformance.py
```

If implementation requires changing any forbidden path, stop and reopen design.

---

## Execution preflight

Implementation must start on a new branch created from the exact approved-plan head:

```text
feat/t-704-openapi-importer
```

Do not implement directly on `feat/t-704-openapi-importer-design`.

Before Task 1:

```bash
test -z "$(git status --short)"
git rev-parse HEAD
python scripts/validate.py
```

Expected:

- clean working tree;
- HEAD equals the approved-plan head;
- baseline structural validation passes.

---

### Task 1: Scaffold the framework-neutral importer package and CI guard

**Files:**
- Create: `packages/openapi-importer/package.json`
- Create: `packages/openapi-importer/package-lock.json`
- Create: `packages/openapi-importer/tsconfig.json`
- Create: `packages/openapi-importer/src/index.ts`
- Create: `packages/openapi-importer/tests/architecture.test.ts`
- Modify: `.github/workflows/validate.yml`

**Acceptance:**
- Node 22 / TypeScript package exists;
- runtime dependency is only `yaml`;
- no internal SurfaceRelay package dependency;
- production source imports no fs/network client;
- CI adds one importer job;
- existing 11 jobs remain unchanged.

- [ ] **Step 1: Write package manifest**

Use:

```json
{
  "name": "@surfacerelay/openapi-importer",
  "version": "0.0.0-dev",
  "private": true,
  "type": "module",
  "license": "Apache-2.0",
  "engines": {"node": ">=22"},
  "scripts": {
    "typecheck": "tsc --noEmit",
    "test": "vitest run"
  },
  "dependencies": {
    "yaml": "^2.9.1"
  },
  "devDependencies": {
    "@types/node": "^22.0.0",
    "ajv": "^8.17.1",
    "typescript": "^5.9.0",
    "vitest": "^3.2.0"
  }
}
```

Run `npm install` to create the lockfile.

- [ ] **Step 2: Write architecture RED tests**

Prove:

- package.json has exactly one production dependency: `yaml`;
- no dependency/import on Laravel, Laravel MCP, browser runtime, MCP, WebMCP, generic OpenAPI parsers;
- every production static/dynamic module specifier is either a relative internal module, `yaml`, or `node:buffer`;
- production `src/**/*.ts` has no filesystem, process-spawn, socket/DNS/TLS/HTTP client import and no direct/global `fetch` use;
- architecture tests do **not** inspect Git history; repository-diff scope audit is a separate verification step because unit tests must remain valid under shallow CI checkout.

- [ ] **Step 3: Run RED**

Before creating the minimal source/package shape expected by the test, run:

```bash
cd packages/openapi-importer
npm test -- architecture.test.ts
```

Capture the expected failure reason.

- [ ] **Step 4: Implement minimal scaffold and GREEN**

Create `src/index.ts` with no behavior export beyond version/package placeholder types.

Run:

```bash
npm run typecheck
npm test -- architecture.test.ts
```

- [ ] **Step 5: Add CI job**

Add exactly one job:

```yaml
openapi-importer:
  runs-on: ubuntu-latest
  steps:
    - uses: actions/checkout@v4
    - uses: actions/setup-node@v4
      with:
        node-version: '22'
        cache: npm
        cache-dependency-path: packages/openapi-importer/package-lock.json
    - working-directory: packages/openapi-importer
      run: npm ci
    - working-directory: packages/openapi-importer
      run: npm run typecheck
    - working-directory: packages/openapi-importer
      run: npm test
```

Expected full workflow count after T-704 implementation starts: **12 jobs**.

- [ ] **Step 6: Self-review**

Check:

- scope alignment;
- D-012 / D-065 dependency boundary;
- no hidden runtime/execution dependency;
- no unnecessary parser stack;
- no forbidden path change;
- baseline evidence.

Do not proceed on failure.

- [ ] **Step 7: Commit**

```bash
git add packages/openapi-importer .github/workflows/validate.yml
git commit -m "build(openapi): scaffold bounded importer package"
```

---

### Task 2: Implement bounded JSON/YAML source parsing

**Files:**
- Create: `src/json-value.ts`
- Create: `src/limits.ts`
- Create: `src/diagnostics.ts`
- Create: `src/source/parse-source.ts`
- Create: `src/source/parse-json.ts`
- Create: `src/source/parse-yaml.ts`
- Create: `tests/source-parser.test.ts`
- Create fixtures under `tests/fixtures/positive` / `negative`

**Interface:**

```ts
type SourceFormat = 'json' | 'yaml';

interface ParseOpenApiSourceInput {
  format: SourceFormat;
  content: string;
}

interface ParsedOpenApiSource {
  document: JsonObject | null;
  diagnostics: ImportDiagnostic[];
}
```

No file path and no URL are accepted by the library API.

- [ ] **Step 1: Write RED source-budget tests**

Required:

- >2 MiB input rejected before parse;
- JSON object accepted;
- invalid JSON gives `invalid_json`;
- YAML mapping accepted;
- YAML aliases rejected;
- YAML explicit/custom tags rejected;
- duplicate YAML keys rejected;
- multi-document YAML rejected;
- depth >64 rejected;
- node count >50,000 rejected;
- duplicate JSON object keys are rejected;
- JS result contains only JSON-compatible values and safe/null-prototype maps.

- [ ] **Step 2: Implement safe YAML AST conversion**

Use `yaml.parseDocument` only to create an AST.

Configuration/behavior:

- YAML 1.2 core;
- merge disabled;
- unique keys required;
- reject document errors/warnings that change interpretation;
- walk AST iteratively;
- reject any alias node;
- reject explicit tags;
- convert mappings into null-prototype plain records;
- never call arbitrary tag constructors;
- apply depth/node/string budgets during conversion.

Do not enable merge keys.

- [ ] **Step 3: Implement JSON parser + common bounded-tree validator**

Check UTF-8 JS string byte size via `Buffer.byteLength(content, 'utf8')` from `node:buffer` before JSON parsing.

JSON handling must be strict and deterministic:

- reject duplicate object member names before accepting the parsed document;
- use native `JSON.parse` for JSON syntax/value semantics;
- normalize the parsed result through the same iterative safe-tree copier used by YAML;
- produce only JSON-compatible primitives/arrays and null-prototype maps;
- apply the same depth/node/string safety checks during normalization.

Do not merely validate a normal-prototype `JSON.parse` result and return it unchanged.

- [ ] **Step 4: Run GREEN**

```bash
npm test -- source-parser.test.ts
npm run typecheck
```

- [ ] **Step 5: Self-review**

Check untrusted-input handling, alias/tag attack surface, prototype safety, bounded memory assumptions, deterministic diagnostics, and no I/O capability.

- [ ] **Step 6: Commit**

```bash
git add packages/openapi-importer/src packages/openapi-importer/tests
git commit -m "feat(openapi): add bounded source parser"
```

---

### Task 3: Enforce version family and same-document JSON Pointer references

**Files:**
- Create: `src/source/openapi-version.ts`
- Create: `src/refs/json-pointer.ts`
- Create: `src/refs/local-ref-resolver.ts`
- Create: `tests/local-ref-resolver.test.ts`

**Interfaces:**

```ts
type OpenApiFamily = '3.1' | '3.2';

parseOpenApiFamily(document): Result<OpenApiFamily>;
resolveLocalPointer(root, ref, budget): Result<JsonValue>;
```

- [ ] **Step 1: Write RED version tests**

Accept:

```text
3.1.0
3.1.1
3.2.0
3.2.1
future 3.1.x / 3.2.x patch strings matching semantic triplet
```

Reject:

```text
2.0
3.0.x
4.x
missing/non-string openapi
malformed version
```

- [ ] **Step 2: Write RED reference tests**

Required:

- `#` root resolves;
- `#/components/schemas/X` resolves;
- strict `~0` / `~1` decoding;
- valid percent-encoded fragment works deterministically;
- malformed percent encoding rejected;
- `#namedAnchor` → `anchor_ref_unsupported`;
- relative/external path ref → `external_ref_forbidden`;
- file/http/https ref → `external_ref_forbidden`;
- missing pointer → diagnostic;
- >32 hop chain rejected;
- cycle rejected;
- >4096 unique targets rejected.

- [ ] **Step 3: Implement resolver without dereference copying**

Resolver should return referenced values by lookup, not recursively clone the whole document.

Cycle/hop accounting belongs to the caller traversal context.

- [ ] **Step 4: Run GREEN**

```bash
npm test -- local-ref-resolver.test.ts
npm run typecheck
```

- [ ] **Step 5: Self-review**

Check D-067, no filesystem/network path, strict pointer behavior, bounded cycles/hops, and no implicit anchor support.

- [ ] **Step 6: Commit**

```bash
git add packages/openapi-importer/src packages/openapi-importer/tests
git commit -m "feat(openapi): add bounded local reference resolver"
```

---

### Task 4: Select root path operations and preserve provenance/security evidence

**Files:**
- Create: `src/candidate.ts`
- Create: `src/operations/operation-selector.ts`
- Create: `src/operations/effective-parameters.ts`
- Create: `src/operations/effective-security.ts`
- Create: `tests/operation-selector.test.ts`

**Candidate source shape:**

```ts
interface OpenApiSourceProvenance {
  openapiVersion: string;
  family: '3.1' | '3.2';
  operationPointer: string;
  operationId?: string;
  httpMethod: string;
  pathTemplate: string;
}

interface OpenApiImportCandidate {
  source: OpenApiSourceProvenance;
  sourceSummary?: string;
  sourceDescription?: string;
  parameters: ParameterEvidence[];
  requestBodies: RequestBodyEvidence[];
  responses: ResponseEvidence[];
  security: SecurityEvidence;
  suggestedInputSchema?: JsonObject;
  suggestedOutputSchema?: JsonObject | null;
  unresolvedFields: string[];
  diagnostics: ImportDiagnostic[];
}
```

- [ ] **Step 1: Write RED operation-selection tests**

3.1 fixed methods:

```text
get put post delete options head patch trace
```

3.2 fixed methods:

```text
get put post delete options head patch trace query
```

Required negative cases:

- 3.1 `query` not selected;
- 3.2 `additionalOperations` emits `additional_operation_unsupported`;
- callbacks/webhooks never become root candidates;
- Path Item `$ref` + sibling fields → `path_item_ref_sibling_ambiguous`;
- operation count >1000 stops with deterministic diagnostic.

- [ ] **Step 2: Implement exact provenance**

Preserve operationId exactly if present. Do not normalize it.

Build JSON Pointer provenance from the source location.

Bound source summary/description to 8192 characters; when exceeded, omit the field and emit `source_text_too_large`. Do not truncate silently.

- [ ] **Step 3: Implement effective parameters**

Merge path-level and operation-level parameter evidence using OpenAPI override identity `(name, in)`.

Do not flatten them into an Action input schema.

- [ ] **Step 4: Implement effective security evidence**

Operation `security` overrides root security when present.

Retain only bounded structural evidence:

```text
none / inherited / operation_override
scheme names
declared scopes
anonymous alternative present
inherited security explicitly removed
```

Tests must distinguish:

- absent operation `security` → inherit root evidence;
- `security: []` → explicitly remove inherited top-level security;
- `security: [{}]` → anonymous access is an allowed alternative;
- multiple non-empty Security Requirement Objects → alternatives;
- multiple schemes inside one Security Requirement Object → combined requirement evidence.

Never resolve credentials or convert schemes, scopes, or role-like values into trusted context requirements or authorization policy.

- [ ] **Step 5: Run GREEN**

```bash
npm test -- operation-selector.test.ts
npm run typecheck
```

- [ ] **Step 6: Self-review**

Check D-066/D-068, exact source identity, no semantic inference, source text remains untrusted evidence, and security evidence has no authority.

- [ ] **Step 7: Commit**

```bash
git add packages/openapi-importer/src packages/openapi-importer/tests
git commit -m "feat(openapi): extract operation provenance"
```

---

### Task 5: Implement conservative schema-subset analysis and safe suggestions

**Files:**
- Create: `src/schema/schema-subset.ts`
- Create: `src/schema/schema-suggestion.ts`
- Create: `tests/schema-suggestion.test.ts`
- Add schema fixtures.

- [ ] **Step 1: Write RED dialect/keyword tests**

Required:

- absent document `jsonSchemaDialect` accepted;
- explicit `jsonSchemaDialect` makes source schema suggestion unresolved in v1;
- nested/schema-local `$schema` unsupported;
- every whitelist keyword supported with valid shapes;
- every documented unsupported keyword produces `schema_keyword_unsupported`;
- schema node count >5000 rejected;
- local acyclic `$ref` inlined;
- cyclic schema `$ref` rejected;
- schema annotations such as title/description are not copied.

- [ ] **Step 2: Implement schema copier**

Produce a new JSON schema object containing only supported semantics.

Never mutate the source tree.

When `$ref` is encountered:

- require `$ref` to be the only schema keyword at that schema node in v1;
- if sibling schema keywords exist, emit `schema_ref_sibling_unsupported` and leave the suggestion unresolved;
- resolve the same-document pointer;
- recursively copy within hop/node budgets;
- do not retain component-path refs in final suggestion;
- fail on cycles.

This restriction is intentional: JSON Schema `$ref` siblings are conjunctive semantics and cannot be preserved by a naive object merge/override.

- [ ] **Step 3: Write RED input suggestion tests**

Suggestion exists only when:

- zero effective parameters;
- no request body → exact empty object schema; or
- exactly one `application/json` body schema and it passes subset analysis.

All other cases mark `inputSchema` unresolved with `ambiguous_input_mapping` or schema diagnostic.

- [ ] **Step 4: Write RED output suggestion tests**

Suggestion exists only for one explicit successful 2xx choice:

- no content → `null`;
- exactly one `application/json` supported schema → copied suggestion.

Multiple 2xx responses/media/schema shapes → `ambiguous_success_output`.

- [ ] **Step 5: Run GREEN**

```bash
npm test -- schema-suggestion.test.ts
npm run typecheck
```

- [ ] **Step 6: Self-review**

Check no lossy schema mapping, no source annotations leak, no OAS directional keyword is discarded silently, and candidate suggestions remain optional/non-authoritative.

- [ ] **Step 7: Commit**

```bash
git add packages/openapi-importer/src packages/openapi-importer/tests
git commit -m "feat(openapi): add conservative schema suggestions"
```

---

### Task 6: Build deterministic import reports and bounded diagnostics

**Files:**
- Create: `src/import/candidate-builder.ts`
- Modify: `src/diagnostics.ts`
- Modify: `src/index.ts`
- Create: `tests/candidate-builder.test.ts`

**Interface:**

```ts
interface OpenApiImportReport {
  openapiVersion?: string;
  candidates: OpenApiImportCandidate[];
  diagnostics: ImportDiagnostic[];
  truncatedDiagnostics: boolean;
}

importOpenApi(input: ParseOpenApiSourceInput): OpenApiImportReport;
```

- [ ] **Step 1: Write RED end-to-end candidate tests**

Required positive fixtures:

- minimal OpenAPI 3.1 JSON operation;
- minimal OpenAPI 3.2 YAML operation;
- 3.2 `query`;
- local component schema ref;
- body-only JSON input suggestion;
- one successful JSON output suggestion;
- effective security evidence.

Required negative fixtures:

- 3.0/2.0;
- external ref;
- anchor ref;
- cycle;
- Path Item ref+sibling;
- additionalOperations;
- parameterized operation remains input-unresolved;
- multiple request media;
- multiple success outputs;
- unsupported schema dialect/keyword;
- raw HTML/Markdown description retained only as source evidence;
- no HTTP method changes effect/risk/idempotency because candidate contains no such canonical fields.

- [ ] **Step 2: Implement deterministic report ordering**

Candidates sorted by:

```text
pathTemplate
httpMethod
operationPointer
```

Diagnostics sorted stably by source pointer, code, then insertion sequence where required.

Never sort in a way that loses duplicate diagnostics.

- [ ] **Step 3: Enforce diagnostic cap**

At the 500-entry final-report budget:

- collect no more than 499 ordinary diagnostics;
- when one more diagnostic would overflow the budget, append exactly one `diagnostic_limit_reached` as entry 500;
- stop collecting additional diagnostics;
- set `truncatedDiagnostics=true`;
- assert `report.diagnostics.length <= MAX_DIAGNOSTICS` in tests.

- [ ] **Step 4: Run GREEN**

```bash
npm test -- candidate-builder.test.ts
npm test
npm run typecheck
```

- [ ] **Step 5: Self-review**

Check determinism, error/warning ownership, no hidden exposure/execution path, and diagnostics remain importer-local.

- [ ] **Step 6: Commit**

```bash
git add packages/openapi-importer/src packages/openapi-importer/tests
git commit -m "feat(openapi): build deterministic import candidates"
```

---

### Task 7: Materialize only after explicit SurfaceRelay resolution

**Files:**
- Create: `src/resolution.ts`
- Create: `src/materialize/action-definition-materializer.ts`
- Create: `tests/materializer.test.ts`
- Create: `tests/support/canonical-action-validator.ts`
- Modify: `src/index.ts`

**Resolution types:**

```ts
type SchemaResolution =
  | { kind: 'candidate_suggestion' }
  | { kind: 'explicit'; schema: JsonObject };

type OutputSchemaResolution =
  | { kind: 'candidate_suggestion' }
  | { kind: 'explicit'; schema: JsonObject | null };

interface ImportResolution {
  id: string;
  version: number;
  title: string;
  description: string;
  scope: 'portable' | 'page_scoped' | 'browser_local' | 'headless';
  effect: 'read' | 'reversible_write' | 'destructive_write' | 'external_side_effect';
  risk: 'low' | 'moderate' | 'high' | 'consequential';
  idempotency: 'none' | 'recommended_key' | 'required_key';
  outputSensitivity: 'normal' | 'sensitive';
  outputContentTrust: 'trusted_application_data' | 'contains_untrusted_content';
  contextRequirements: ContextRequirement[];
  inputSchema: SchemaResolution;
  outputSchema: OutputSchemaResolution;
}
```

- [ ] **Step 1: Write RED semantic-completion tests**

Materialization must reject missing explicit:

- id/version;
- title/description;
- scope/effect/risk/idempotency;
- output sensitivity/content trust;
- context requirements;
- input/output schema choice.

OpenAPI method/security/operationId must never fill these fields.

- [ ] **Step 2: Write RED canonical identity tests**

Use the exact canonical constraints:

```text
id pattern ^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$
id max 160 bytes
version >=1
title 1..120 Unicode characters
description 1..2000 Unicode characters
unique context requirements
```

No trim/slug/case normalization.

- [ ] **Step 3: Implement materializer**

Rules:

- `candidate_suggestion` requires a non-blocked suggestion;
- explicit schemas are caller-authored canonical data but must still pass the importer JSON-value safety copier and the same depth/schema-node budgets used for candidate schema materialization;
- explicit schemas must be deep-copied and never retained by reference to caller-owned mutable objects;
- no OpenAPI provenance enters ActionDefinition;
- no RuntimeBinding or executor is produced;
- output object contains only canonical Action Definition fields plus a separate exact `OpenApiSourceProvenance` value; do not attach the whole candidate/report as provenance.

- [ ] **Step 4: Validate output against canonical schema in tests**

Test-only AJV helper loads:

```text
../../../../spec/0.1/action-definition.schema.json
```

Use AJV Draft 2020-12 mode.

Resolve that path from `tests/support/canonical-action-validator.ts` via `import.meta.url`, not process cwd. From that file, repository root is four levels up.

Every positive materialization test must pass the canonical schema.

Do not copy the canonical schema into the importer package.

- [ ] **Step 5: Batch collision test**

A helper materializing multiple candidates must reject duplicate final `id + version` with `duplicate_action_identity`.

- [ ] **Step 6: Run GREEN**

```bash
npm test -- materializer.test.ts
npm test
npm run typecheck
```

- [ ] **Step 7: Self-review**

Check D-065/D-066/D-068, exact identity, canonical-schema alignment, provenance separation, no trusted authority from source, and no runtime/exposure generation.

- [ ] **Step 8: Commit**

```bash
git add packages/openapi-importer/src packages/openapi-importer/tests
git commit -m "feat(openapi): materialize explicit action definitions"
```

---

### Task 8: Documentation, full verification, and external-review handoff

**Files:**
- Create: `packages/openapi-importer/README.md`
- Modify: `README.md`
- Modify: `TASKS.md`
- Modify: `STATUS.md`
- Modify: `REVIEW_REQUEST.md`
- Verify only: all implementation/source/spec/conformance paths.

- [ ] **Step 1: Document the importer boundary**

Package README must state:

- secondary/import-only per D-012;
- one source string, explicit JSON/YAML format;
- 3.1.x/3.2.x only;
- same-document JSON Pointer refs only;
- no file/network ref resolution;
- candidate-first workflow;
- explicit resolution required;
- no HTTP execution/RuntimeBinding/auth/exposure;
- source documentation is untrusted evidence;
- schema suggestion subset and unresolved behavior;
- D-065..D-068 remain PROPOSED pending external review.

- [ ] **Step 2: Add conservative root README discoverability**

Add one optional importer link. Do not reposition SurfaceRelay as an OpenAPI replacement/gateway.

- [ ] **Step 3: Run package verification**

```bash
cd packages/openapi-importer
npm ci
npm run typecheck
npm test
cd ../..
```

- [ ] **Step 4: Run existing regressions**

```bash
python scripts/validate.py

cd packages/browser-runtime
npm ci
npm run typecheck
npm test
npm run conformance:build
cd ../..
python scripts/run_conformance.py

cd packages/laravel
composer update --no-interaction --no-progress --prefer-dist
composer validate --strict
composer test
cd ../laravel-mcp
composer update --no-interaction --no-progress --prefer-dist
composer validate --strict
composer test
cd ../..
```

CI remains the authoritative cross-version matrix.

- [ ] **Step 5: Prove forbidden paths unchanged**

```bash
BASE="$(git merge-base main HEAD)"
for path in \
  packages/laravel/src \
  packages/laravel-mcp/src \
  packages/browser-runtime/src \
  spec/0.1 \
  conformance/targets \
  scripts/conformance_model.py \
  scripts/run_conformance.py
do
  git diff --quiet "$BASE" HEAD -- "$path" || {
    echo "T-704 scope violation: $path changed" >&2
    exit 1
  }
done
echo "T-704 forbidden-path check: PASS"
```

- [ ] **Step 6: Review dependency surface**

Require:

```text
production dependency: yaml only
no generic OpenAPI dereferencer
no fs/network import in src
no internal SurfaceRelay package dependency
package-lock committed
```

- [ ] **Step 7: Update tracking to IMPLEMENTED / REVIEW PENDING**

Record exact implementation head + CI.

Keep:

```text
D-065 PROPOSED
D-066 PROPOSED
D-067 PROPOSED
D-068 PROPOSED
D-026 PROPOSED
```

Do not promote decisions before external review.

- [ ] **Step 8: Prepare concise REVIEW_REQUEST.md**

External review questions must include:

- does the package stay secondary/import-only?
- can source data create trusted authority or SurfaceRelay semantics?
- can parser/ref behavior read filesystem/network?
- are resource limits enforced before/through expansion?
- are aliases/custom tags safely rejected?
- is source provenance separated from Action identity?
- does operationId ever become Action ID automatically?
- are 3.2 query/additionalOperations handled exactly as designed?
- are Path Item ref+sibling cases fail-closed?
- are schema suggestions semantics-preserving and annotation-safe?
- can raw source descriptions reach Action/tool metadata?
- can candidate discovery create runtime binding/execution/exposure?
- do canonical/spec/conformance/MCP/Laravel/browser semantics remain unchanged?

- [ ] **Step 9: Exact-head CI**

Require all 12 jobs green after the importer CI job exists.

Expected evidence categories:

```text
openapi-importer: typecheck + Vitest
contract: PASS
browser: 20 files / 328 tests + typecheck + conformance
Laravel: 4/4 matrix
Laravel MCP: 4/4 matrix
PHP lint: PASS
```

- [ ] **Step 10: Commit review handoff**

```bash
git add packages/openapi-importer/README.md README.md TASKS.md STATUS.md REVIEW_REQUEST.md
git commit -m "docs(review): request T-704 OpenAPI importer review"
```

Stop. Do not promote D-065..D-068, merge, close T-704, or begin any later work automatically.

---

## Final self-review checklist

Before requesting external review, every item must pass:

- [ ] Scope matches the approved T-704 design.
- [ ] D-012 secondary-adapter boundary remains intact.
- [ ] D-065 candidate-first boundary is implemented without execution/exposure.
- [ ] D-066 prevents transport metadata from silently creating SurfaceRelay semantics.
- [ ] D-067 same-document/no-I/O/resource-bounded ingestion is enforced.
- [ ] D-068 preserves source identity as provenance only.
- [ ] Production dependency is only `yaml`.
- [ ] Production module imports are allowlisted to relative modules, `yaml`, and `node:buffer`; no filesystem/process/socket/network capability exists in importer production source.
- [ ] YAML aliases/custom tags/merge semantics cannot expand attacker-controlled graphs.
- [ ] All resource budgets have negative tests, and final diagnostics never exceed 500 entries including the terminal truncation diagnostic.
- [ ] OpenAPI 3.2 `query` is supported as fixed operation.
- [ ] `additionalOperations` is fail-closed in v1.
- [ ] Path Item `$ref` sibling ambiguity is rejected.
- [ ] Custom schema dialects/unsupported keywords cannot be silently copied.
- [ ] Schema `$ref` with sibling schema keywords fails closed rather than using lossy merge semantics.
- [ ] Raw OpenAPI prose cannot become canonical/tool metadata automatically.
- [ ] Candidate suggestions are optional and non-authoritative.
- [ ] Materialization requires every SurfaceRelay semantic field explicitly.
- [ ] Explicit caller-authored schemas are bounded, safe-copied, and cannot bypass schema/depth/node limits.
- [ ] Final Action identity is exact and never normalized from operationId/path/method.
- [ ] Final output passes the canonical Action Definition schema.
- [ ] Provenance is outside ActionDefinition.
- [ ] No RuntimeBinding, HTTP executor, auth policy, MCP/WebMCP exposure, or ActionRegistry registration is created.
- [ ] Forbidden paths are unchanged.
- [ ] D-065..D-068 remain PROPOSED through implementation and review handoff.
- [ ] D-026 remains independently PROPOSED.
- [ ] Verification evidence is exact-head and revision-bounded.

## Scope-stop conditions

Stop implementation and reopen design if any task requires:

```text
a canonical spec change
a RuntimeBinding/HTTP execution contract
an OpenAPI extension vocabulary such as x-surfacerelay-*
network or secondary filesystem reference resolution
support for Swagger 2.0 / OpenAPI 3.0
support for named-anchor or dynamic JSON Schema references
full arbitrary JSON Schema dialect support
automatic risk/effect/idempotency/trusted-context inference
automatic operationId -> Action ID conversion
automatic MCP/WebMCP exposure
changes to packages/laravel/src
changes to packages/laravel-mcp/src
changes to packages/browser-runtime/src
changes to T-701 conformance semantics
a new global ActionError/failure-code enum
```

## Execution handoff gate

This document is an implementation plan only.

After this plan is committed:

```text
T-704 design: APPROVED
T-704 implementation plan: APPROVED
T-704 implementation: NOT STARTED
D-065: PROPOSED
D-066: PROPOSED
D-067: PROPOSED
D-068: PROPOSED
D-026: PROPOSED
```

This plan is approved. Implementation still starts only on the separate execution gate and new `feat/t-704-openapi-importer` branch; approval does not create `packages/openapi-importer/**`, change CI, or start RED tests in this commit.


## Plan review outcome

**APPROVED** after explicit review of task dependencies, dependency capability, parser safety, resource-budget accounting, reference semantics, Schema Object subset behavior, materialization boundaries, canonical-schema verification, CI behavior, and scope-stop conditions.

Review fixes applied before approval:

- added Node typings and an explicit production module-import allow-list;
- moved Git diff auditing out of Vitest/shallow-checkout-sensitive architecture tests;
- required duplicate-key rejection and safe null-prototype normalization for JSON input;
- made the 500-diagnostic limit inclusive of the terminal truncation diagnostic;
- fixed URI-fragment/JSON Pointer decoding order;
- made Schema `$ref` siblings fail closed in v1;
- distinguished OpenAPI security inheritance removal (`[]`) from anonymous alternative (`[{}]`);
- bounded/deep-copied explicit caller-authored schemas during materialization;
- narrowed materializer provenance to exact source provenance only;
- corrected the canonical Action Definition schema path from the test helper;
- stated explicitly that T-704 is not a full OpenAPI validator.

D-065 through D-068 remain PROPOSED. D-026 remains independently PROPOSED. No implementation begins from this approval commit.
