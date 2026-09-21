# @surfacerelay/openapi-importer

Optional, bounded OpenAPI import adapter for SurfaceRelay.

> **Status:** experimental / private package. This package is a secondary import adapter under D-012. It does not replace OpenAPI tooling and does not turn OpenAPI operations into executable SurfaceRelay actions by itself.

## Boundary

The importer accepts exactly one caller-supplied source string:

```ts
import { importOpenApi } from '@surfacerelay/openapi-importer';

const report = importOpenApi({
  format: 'yaml', // or 'json'
  content: openApiSource,
});
```

Supported source families:

- OpenAPI 3.1.x;
- OpenAPI 3.2.x.

The importer does not read source files and does not fetch URLs. A future CLI may read the one root file explicitly supplied by its caller, but this package's import API receives content, not a path or URL.

Reference handling is deliberately narrow:

- same-document JSON Pointer fragments only;
- no relative-file references;
- no `file:` references;
- no HTTP/HTTPS reference retrieval;
- no named-anchor or dynamic-reference support;
- cycles, hop limits, and unique-target limits fail closed.

OpenAPI 3.0.x and Swagger/OpenAPI 2.0 are out of scope for v1.

## Candidate-first workflow

Importing does **not** produce an executable Action Definition.

```text
OpenAPI source
    |
    v
bounded parsing
    |
    v
source/version/ref/operation evidence
    |
    v
OpenApiImportCandidate + diagnostics
    |
    v
explicit ImportResolution
    |
    v
canonical Action Definition + separate source provenance
```

`importOpenApi()` returns an `OpenApiImportReport` containing candidates and importer-local diagnostics. Candidates preserve source evidence such as:

- OpenAPI family/version;
- path template;
- HTTP method;
- exact `operationId`, when present;
- operation JSON Pointer;
- effective parameter evidence;
- effective OpenAPI security evidence;
- bounded request/response media/schema evidence.

Source identity is provenance only. In particular:

- `operationId` is never normalized into an Action ID;
- HTTP method does not define SurfaceRelay effect, risk, or idempotency;
- OpenAPI security does not define authenticated actor, tenant, roles, permissions, or trusted context;
- server URLs do not create RuntimeBindings.

## Explicit SurfaceRelay resolution

Canonical Action Definition materialization requires an explicit `ImportResolution` for:

- Action `id` and `version`;
- `title` and `description`;
- `scope`;
- `effect`;
- `risk`;
- `idempotency`;
- `outputSensitivity`;
- `outputContentTrust`;
- `contextRequirements`;
- input schema choice;
- output schema choice.

OpenAPI metadata never fills a missing SurfaceRelay semantic field.

Materialization returns the canonical Action Definition and exact OpenAPI provenance as separate values. T-704 does not insert provenance into Action Definition `extensions`.

## Schema suggestions

The importer is not a general OpenAPI-to-JSON-Schema transpiler and is not a full OpenAPI validator.

Automatic schema suggestions use a conservative, semantics-preserving subset:

```text
$ref
type
properties
required
items
additionalProperties
enum
const
minimum / maximum
exclusiveMinimum / exclusiveMaximum
multipleOf
minLength / maxLength / pattern
minItems / maxItems / uniqueItems
minProperties / maxProperties
```

Suggestions fail closed when semantics cannot be preserved exactly. Examples include:

- explicit/custom `jsonSchemaDialect`;
- schema-local `$schema`;
- unsupported or custom keywords;
- `$ref` with sibling schema keywords;
- cycles or resource-limit violations;
- ambiguous transport/media mappings.

Schema annotations such as source `title` and `description` are not copied into automatic suggestions. This prevents untrusted API documentation from becoming downstream tool metadata through import.

Caller-authored explicit canonical schemas are not restricted to the automatic-suggestion whitelist, but they are deep-copied under the materialization depth/node safety budgets.

## Input/output suggestion rules

An input suggestion is created only when:

- there are no effective HTTP parameters; and
- there is no request body, producing an exact empty-object schema; or
- there is exactly one supported `application/json` request-body schema.

Path/query/header/cookie parameters are not flattened into Action input automatically.

An output suggestion is created only when:

- there is exactly one explicit 2xx response; and
- it has no content, producing `null`; or
- it has exactly one supported `application/json` schema.

Multiple success shapes, wildcard success responses, or multiple media types remain unresolved.

## Untrusted source documentation

OpenAPI `summary` and `description` are retained only as bounded source evidence.

They never become canonical Action `title` or `description` automatically. T-704 requires explicit presentation fields during materialization, so raw Markdown/HTML from the imported document cannot flow directly into tool-facing Action metadata.

## Resource budgets

v1 uses explicit fail-closed budgets:

```text
source bytes                 2 MiB
document depth               64
document nodes               50,000
operations                   1,000
diagnostics                  500 final entries
reference hops               32
unique reference targets     4,096
schema fragment nodes        5,000
source text characters       8,192
```

YAML aliases, explicit/custom tags, duplicate keys, and multi-document YAML are rejected. JSON duplicate object keys are rejected, including escaped-equivalent keys.

## What this package does not do

T-704 does not create or install:

- HTTP clients or endpoint execution;
- RuntimeBindings;
- ActionBus execution stages;
- authentication or authorization policy;
- trusted actor/tenant context;
- confirmation or idempotency stores;
- ActionRegistry registration;
- MCP or WebMCP exposure;
- routes/controllers;
- `x-surfacerelay-*` extension semantics.

The package has no filesystem/network retrieval capability and no dependency on Laravel, Laravel MCP, or the browser runtime.

## Public API

The package exports the bounded parsing/report surface and explicit materialization surface:

```ts
import {
  importOpenApi,
  materializeActionDefinition,
  materializeActionDefinitions,
  type ImportResolution,
  type OpenApiImportReport,
} from '@surfacerelay/openapi-importer';
```

The package is currently `private: true`; T-704 does not make an npm compatibility promise.

## Verification

```bash
npm ci
npm run typecheck
npm test
```

The repository CI also runs the existing contract, browser/conformance, Laravel, Laravel MCP, and PHP-lint regression matrix.

## Decision status

T-704 implementation does not promote architectural proposals by itself.

At external-review handoff:

```text
D-065 PROPOSED
D-066 PROPOSED
D-067 PROPOSED
D-068 PROPOSED
D-026 PROPOSED
```

Promotion, merge, and T-704 closure require separate explicit gates after external review.
