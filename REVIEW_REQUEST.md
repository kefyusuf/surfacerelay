# T-704 External Review Request — Optional OpenAPI Importer

## Review state

- **Task:** T-704 — optional OpenAPI importer as a secondary adapter
- **Branch:** `feat/t-704-openapi-importer`
- **State:** **IMPLEMENTED / EXTERNAL REVIEW PENDING**
- **Implementation code head:** `58d18dc201be5e50ea7e20d1747d906a3ec5a412`
- **Implementation CI:** Validate `#911` / `35537472977` — **12/12 SUCCESS**
- **Pre-handoff tracking head:** `106cba54fe85909362cab3c1ec5f3d1db1036011`
- **Pre-handoff tracking CI:** Validate `#912` / `35537581965` — **12/12 SUCCESS**
- **D-065:** **PROPOSED**
- **D-066:** **PROPOSED**
- **D-067:** **PROPOSED**
- **D-068:** **PROPOSED**
- **D-026:** **PROPOSED** — unchanged
- **Documentation/review-handoff head:** `201b030b91c8642387514510ea120cd9282122f2`
- **Documentation/review-handoff CI:** Validate `#913` / `35538173232` — **12/12 SUCCESS**
- **Merge/decision promotion/closure:** **NOT PERFORMED**

The review target is the current feature branch. The implementation/documentation handoff revision above is exact-head green; the subsequent evidence-only tracking commit changes no implementation behavior.

## What changed

T-704 adds one optional package:

```text
packages/openapi-importer
```

Its production dependency surface is:

```text
yaml ^2.9.1
```

There is no generic OpenAPI parser/dereferencer and no dependency on Laravel, Laravel MCP, browser runtime, MCP, or WebMCP packages.

The package implements:

1. bounded JSON/YAML source parsing;
2. OpenAPI 3.1.x/3.2.x family classification;
3. same-document JSON Pointer resolution only;
4. root `paths` operation selection and exact source provenance;
5. effective parameter/security evidence without SurfaceRelay authority;
6. conservative schema suggestions;
7. deterministic import reports with bounded diagnostics;
8. explicit SurfaceRelay resolution and canonical Action Definition materialization.

## Core boundary under review

```text
OpenAPI source
    |
    v
bounded import report
    |
    v
OpenApiImportCandidate + diagnostics
    |
    v
explicit ImportResolution
    |
    v
canonical Action Definition
+
separate OpenApiSourceProvenance
```

Candidate discovery does not make an Action executable.

T-704 does not create RuntimeBindings, HTTP execution, authorization, trusted context, ActionRegistry registration, MCP/WebMCP exposure, routes, or ActionBus execution paths.

## Trust and identity invariants

Please verify that:

- OpenAPI `operationId`, HTTP method/path, server data, and JSON Pointer remain source provenance only;
- `operationId` is never normalized into canonical Action identity;
- final Action `id + version` is explicit and exact;
- HTTP method does not infer effect/risk/idempotency;
- OpenAPI security does not create trusted actor, tenant, role, permission, or context requirements;
- source Markdown/HTML does not become Action/tool metadata automatically;
- exact source provenance remains outside the canonical Action Definition.

## Ingestion and reference invariants

Please verify that:

- the library API accepts one caller-supplied source string and explicit JSON/YAML format;
- OpenAPI 3.1.x and 3.2.x are the only supported families;
- there is no secondary filesystem or network retrieval;
- only same-document JSON Pointer references are accepted;
- reference cycles/hops/unique targets are bounded;
- YAML aliases/custom tags/duplicate keys/multi-document inputs fail closed;
- JSON duplicate object member names fail closed;
- Path Item `$ref` + sibling cases fail closed;
- OpenAPI 3.2 fixed `query` is supported while `additionalOperations` is not silently imported.

## Schema invariants

Please verify that automatic suggestions:

- use only the approved semantics-preserving keyword subset;
- fail closed on custom dialects, schema-local `$schema`, unsupported keywords, and `$ref` siblings;
- never silently drop validation semantics;
- do not copy source schema annotations into canonical/tool metadata;
- preserve ambiguity rather than flattening transport inputs;
- use resource-bounded local-reference expansion.

Explicit caller-authored canonical schemas may use broader JSON Schema vocabulary, but materialization must deep-copy them under the same depth/schema-node safety budgets.

## Materialization invariants

Please verify that materialization:

- requires every SurfaceRelay semantic field explicitly;
- never fills missing semantics from source metadata;
- enforces canonical Action identity/version and metadata bounds;
- requires canonical trusted-context values explicitly;
- requires a present non-blocked candidate suggestion when that choice is selected;
- deep-copies explicit and candidate schemas;
- validates positive outputs against the repository canonical Action Definition schema in tests;
- rejects duplicate final `id + version` identities across a batch;
- returns source provenance separately rather than embedding it in Action Definition `extensions`.

## Resource budgets

```text
MAX_SOURCE_BYTES                 2 MiB
MAX_DOCUMENT_DEPTH               64
MAX_DOCUMENT_NODES               50,000
MAX_OPERATIONS                   1,000
MAX_DIAGNOSTICS                  500 final entries
MAX_REF_HOPS                     32
MAX_UNIQUE_REF_TARGETS           4,096
MAX_SCHEMA_NODES_PER_FRAGMENT    5,000
MAX_SOURCE_TEXT_CHARS            8,192
```

Please check that limits apply before/through expansion rather than after unbounded work.

## Verification evidence

Implementation/tracking verification before documentation:
`106cba54fe85909362cab3c1ec5f3d1db1036011`

Documentation/review-handoff verification:
`201b030b91c8642387514510ea120cd9282122f2`

Both revisions completed the full 12-job matrix successfully.

```text
Validate #912 / 35537581965:     12/12 SUCCESS
Validate #913 / 35538173232:     12/12 SUCCESS

OpenAPI importer:
  7 Vitest files / 171 tests PASS
  TypeScript typecheck PASS
  npm ci PASS

Browser runtime:
  20 Vitest files / 328 tests PASS
  typecheck PASS
  Python conformance tests: 47/47 PASS
  runtime conformance matrix: 7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR

Laravel:
  4/4 compatibility jobs SUCCESS
  595 tests / 3164 assertions per matrix job

Laravel MCP:
  4/4 compatibility jobs SUCCESS
  47 tests / 337 assertions per matrix job

Contract validation:
  PASS

PHP lint:
  PASS
```

## Scope audit

Against merge-base `7e26d61a4739efb1111ab148cdad38e5c145e141`, T-704 has **no changes** under:

```text
packages/laravel/src/**
packages/laravel-mcp/src/**
packages/browser-runtime/src/**
spec/0.1/**
conformance/targets/**
scripts/conformance_model.py
scripts/run_conformance.py
```

The canonical Action Definition schema is consumed test-only for validation and is not modified.

## External review questions

1. Does the package remain secondary/import-only under D-012?
2. Can any OpenAPI/source data create trusted authority or SurfaceRelay semantics?
3. Can parser/reference behavior read filesystem or network resources?
4. Are resource limits enforced before and throughout reference/schema expansion?
5. Are YAML aliases/custom tags and duplicate-key ambiguity safely rejected?
6. Is source provenance fully separated from canonical Action identity?
7. Can `operationId` become an Action ID automatically anywhere?
8. Are OpenAPI 3.2 `query` and `additionalOperations` handled exactly as designed?
9. Do Path Item `$ref` sibling cases fail closed?
10. Are automatic schema suggestions semantics-preserving and annotation-safe?
11. Can raw OpenAPI descriptions reach canonical Action/tool metadata?
12. Can candidate discovery create RuntimeBinding, execution, authorization, registration, or exposure?
13. Are canonical spec, T-701 conformance, Laravel, Laravel MCP, browser runtime, and MCP projection semantics unchanged?

## Review stop

External review may produce code/doc fixes, but this handoff does **not** authorize:

- D-065..D-068 promotion;
- D-026 promotion;
- merge to `main`;
- T-704 closure;
- any later task.

Those require separate explicit gates after review closure.
