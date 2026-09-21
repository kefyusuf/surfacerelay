# T-704 — Optional OpenAPI Importer Design

**Status:** APPROVED DESIGN / IMPLEMENTED / EXTERNAL REVIEW COMPLETE / D-065..D-068 ACCEPTED / MERGE PENDING  
**Task:** T-704  
**Milestone:** M7 — Conformance / Ecosystem Bridges  
**Branch:** `feat/t-704-openapi-importer-design`  
**Drafted:** 2026-09-19  

## 1. Purpose

T-704 adds an optional OpenAPI import adapter that helps teams convert existing endpoint contracts into SurfaceRelay Action Definition candidates without making OpenAPI the architecture of SurfaceRelay.

D-012 and ADR-0012 remain authoritative:

> OpenAPI is a secondary/import adapter, not the core product.

The importer exists to reduce adoption cost for endpoint-first systems. It must not reinterpret transport metadata as trusted runtime authority or silently invent SurfaceRelay semantics that OpenAPI does not express.

## 2. Design objective

The importer separates four stages:

```text
OpenAPI Description
        │
        ▼
bounded source parsing
        │
        ▼
ImportCandidate + diagnostics
        │
        ▼
explicit ImportResolution
        │
        ▼
canonical Action Definition
```

Import stops before canonical materialization whenever required SurfaceRelay semantics are unresolved or the source is ambiguous.

No imported candidate is executable merely because it was discovered in an OpenAPI Description.

## 3. Existing constraints

T-704 inherits and must preserve:

- D-002: Action Definition and Runtime Binding are separate contracts.
- D-004: core semantics are protocol-neutral; surface/protocol metadata is projection/adapter concern.
- D-007: caller input cannot manufacture trusted authority.
- D-010: exposure is explicit.
- D-011: discovery does not imply invocation authorization.
- D-012 / ADR-0012: OpenAPI is secondary/import-only.
- D-013: Action identity is exact `id + version`.
- D-014/D-015: risk and confirmation are SurfaceRelay semantics, not transport guesses.
- D-027 through D-032: trusted context, authorization, result, sensitivity, and content trust retain their existing meaning.
- D-045: idempotency is a server-side SurfaceRelay contract.
- D-062: adapter documentation/import tooling may not silently create new core semantics.
- D-063/D-064: the optional MCP bridge remains independent from import.

T-704 does not reopen any accepted decision.

## 4. OpenAPI baseline

As of 2026-09-19, the latest published OpenAPI Specification is 3.2.1 (2026-09-10). T-704 v1 targets the OpenAPI **3.1.x and 3.2.x families**, not one exact patch release.

Why:

- 3.1+ Schema Objects use an OAS dialect built on JSON Schema Draft 2020-12 semantics, while allowing dialect selection through `jsonSchemaDialect` / `$schema`;
- supporting 3.0.x would require a separate schema-normalization/transpilation boundary;
- Swagger/OpenAPI 2.0 is a different compatibility problem and is outside v1.

Supporting the 3.1/3.2 document family does not imply accepting every Schema Object dialect or keyword. Unknown/custom schema dialects remain unresolved unless the later implementation plan proves an exact semantics-preserving boundary.

Unsupported source versions fail closed with diagnostics.

## 5. Candidate-first import boundary

T-704 does not construct a canonical Action Definition immediately.

A conceptual candidate contains source evidence plus safe structural suggestions:

```text
OpenApiImportCandidate
├── source
│   ├── openapiVersion
│   ├── operationPointer
│   ├── operationId?
│   ├── httpMethod
│   └── pathTemplate
├── presentation
│   ├── suggestedTitle?
│   └── suggestedDescription?
├── requestFragments
├── responseFragments
├── securityEvidence
├── unresolvedFields
└── diagnostics
```

This object is importer state, not a canonical SurfaceRelay contract and not a runtime object.

## 6. What may be extracted automatically

The importer may extract source facts that are directly represented by OpenAPI:

- operation location and exact JSON Pointer;
- optional `operationId`;
- summary/description as untrusted documentation input;
- parameters and their schemas;
- request-body media types and schemas;
- response status/media/schema variants;
- declared security requirements as source evidence;
- deprecation markers;
- tags as source metadata.

Extraction does not mean those facts become canonical SurfaceRelay fields.

Descriptions may contain Markdown/HTML. Raw source text may be retained only as bounded provenance/evidence. It must never flow directly into canonical Action metadata or tool-facing metadata. Materialization requires either an explicit presentation override or a deterministic bounded sanitization policy defined and verified by the implementation plan.

## 7. No silent SurfaceRelay semantic inference

The importer MUST NOT silently infer canonical values for:

- Action `id`;
- Action `version`;
- `scope`;
- `effect`;
- `risk`;
- `idempotency`;
- `outputSensitivity`;
- `outputContentTrust`;
- `contextRequirements`.

Examples of prohibited shortcuts:

- GET -> `effect=read`;
- DELETE -> `risk=consequential`;
- PUT -> idempotent SurfaceRelay policy;
- OAuth/security declaration -> trusted `authenticated_actor`;
- server URL -> runtime binding;
- operationId -> normalized Action ID.

HTTP/OpenAPI information may generate diagnostics or suggested review hints, but it cannot cross the canonical boundary without explicit resolution.

## 8. Explicit ImportResolution

A candidate becomes materializable only after an explicit resolution supplies every required canonical semantic field and resolves structural ambiguity.

Conceptually:

```text
ImportResolution
├── id
├── version
├── scope
├── effect
├── risk
├── idempotency
├── outputSensitivity
├── outputContentTrust
├── contextRequirements
├── presentation overrides if needed
├── input mapping / media choice if needed
└── output choice if needed
```

Resolution may eventually come from a user-authored sidecar policy or another explicit reviewed mechanism. T-704 v1 does not define an OpenAPI extension vocabulary such as `x-surfacerelay-*`; adding one requires a separate decision because it becomes a cross-document semantic contract.

## 9. Source identity is provenance, not Action identity

OpenAPI identifiers are retained as provenance:

- `operationId`;
- HTTP method;
- path template;
- server information;
- operation JSON Pointer.

They do not become RuntimeBinding data or Action identity automatically.

No lossy slugging/case conversion/path-to-dot conversion is allowed to manufacture Action IDs. If an explicit proposed Action ID does not satisfy the canonical grammar, materialization fails.

The importer must preserve enough source provenance in its candidate/report to let reviewers trace every generated definition back to the exact OpenAPI operation without embedding HTTP runtime-target semantics into the final Action Definition.

## 10. OpenAPI ingestion security

OpenAPI descriptions are untrusted input.

v1 ingestion boundary:

- exactly one caller-supplied root OpenAPI document, represented as JSON or YAML content;
- OpenAPI 3.1.x or 3.2.x only;
- local same-document fragment `$ref` resolution only;
- no importer-initiated secondary filesystem reads and no HTTP/HTTPS/file-URI/network dereferencing;
- reference cycles must be detected;
- document size, nesting depth, operation count, reference expansion, and diagnostic volume must be bounded;
- parser-specific YAML aliases/custom tags must not bypass resource limits or instantiate arbitrary objects;
- unknown/custom Schema Object dialects fail closed into unresolved diagnostics unless explicitly supported;
- malformed/unsupported constructs produce bounded diagnostics.

A future CLI may read the one explicitly supplied root file on behalf of the user, but reference resolution itself must not discover or open additional filesystem paths.

Exact numeric limits and parser/library choices belong in the later implementation plan.

## 11. Operation selection

v1 considers operations under the root `paths` surface only.

Supported fixed operation fields are the standard methods defined by the source OAS family:

- OpenAPI 3.1: `get`, `put`, `post`, `delete`, `options`, `head`, `patch`, `trace`;
- OpenAPI 3.2: the same set plus the fixed `query` operation.

Boundaries:

- callbacks are not independently imported;
- webhooks are not independently imported;
- OpenAPI 3.2 `additionalOperations` is explicitly unsupported in v1 and produces a diagnostic rather than an imported action;
- external path documents are not fetched;
- a Path Item using `$ref` together with sibling Path Item fields is rejected in v1 rather than relying on specification-defined/implementation-defined merge ambiguity;
- unsupported operation shapes are reported, not silently skipped as successful imports.

Path/operation-level parameters must be resolved according to OpenAPI override rules before candidate extraction.

## 12. Request/schema handling

T-704 is not a universal OpenAPI-to-JSON-Schema transpiler.

The candidate retains request fragments and ambiguity until an exact mapping can be proven.

OpenAPI Schema Objects must not be blindly copied into canonical SurfaceRelay JSON Schema. Materialization is allowed only for a supported dialect/keyword subset whose semantics are preserved exactly; unsupported OAS vocabulary, custom `$schema` / `jsonSchemaDialect`, or serialization-dependent meaning remains unresolved.

v1 should prefer JSON-compatible request shapes. Cases that require explicit resolution or diagnostic rejection include:

- multiple competing request media types;
- multipart/file/binary semantics;
- parameter-name collisions across locations;
- transport-specific serialization that cannot be represented faithfully as ordinary Action input;
- unsupported Schema Object/dialect features;
- unresolved/cyclic local references.

The implementation MUST NOT flatten path/query/header/cookie/body sources into one input object if doing so changes meaning or creates collisions.

## 13. Response/output handling

OpenAPI may describe many success/error response shapes. The importer must not arbitrarily pick the first one.

A candidate may suggest an output schema only when one unambiguous supported successful JSON shape exists. Otherwise output selection remains unresolved.

HTTP status codes do not redefine SurfaceRelay ActionResult status semantics.

## 14. Security declarations

OpenAPI `security` and Security Scheme Objects are evidence about endpoint authentication requirements, not SurfaceRelay authorization/trusted-context configuration.

The importer first computes the effective operation security evidence using OpenAPI's top-level/operation override rules, then may report:

```text
source declares bearer/OAuth/API-key security
source allows anonymous alternative
source requires multiple schemes
source explicitly removes inherited top-level security
```

It must not:

- install authentication;
- synthesize an actor;
- copy credentials into Action input;
- infer permissions/roles;
- infer tenant authority;
- automatically add trusted-context requirements.

Those remain host/runtime decisions.

## 15. No runtime or exposure generation

T-704 does not generate:

- RuntimeBindings;
- HTTP BindingDriver/executor implementations;
- routes/controllers;
- ActionBus stages;
- authorization policy;
- confirmation/idempotency stores;
- MCP tool exposure;
- WebMCP registrations;
- automatic ActionRegistry registration.

A future HTTP execution adapter is a separate task because “endpoint described by OpenAPI” and “trusted executable SurfaceRelay binding” are different claims.

## 16. Package/dependency boundary

The importer should be framework-neutral and optional.

The eventual implementation must not make these packages depend on OpenAPI tooling:

- `packages/laravel`;
- `packages/laravel-mcp`;
- `packages/browser-runtime`.

The implementation plan will choose the exact package/runtime/library shape. The stable output boundary is semantic JSON/candidate data, not a Laravel-specific object graph.

## 17. Determinism and diagnostics

Given the same input document and explicit resolution, import output must be deterministic.

Diagnostics need stable machine-readable categories for importer-local use, but T-704 must not reuse D-026 or create a new global ActionError enum.

At minimum diagnostics distinguish:

- unsupported OpenAPI version;
- invalid document;
- external reference forbidden;
- unresolved/cyclic local reference;
- unsupported schema/media construct;
- ambiguous request mapping;
- ambiguous success output;
- missing explicit SurfaceRelay semantic resolution;
- invalid proposed Action identity;
- duplicate/colliding final Action identity.

## 18. Verification strategy

Implementation planning must include:

### 18.1 Positive fixtures

- minimal OpenAPI 3.1 JSON operation;
- minimal OpenAPI 3.2 YAML operation;
- local component-schema references;
- operation with parameters + JSON body;
- one unambiguous successful JSON output.

### 18.2 Negative/security fixtures

- OpenAPI 3.0 / Swagger 2.0 rejected;
- external URL/file `$ref` rejected;
- secondary filesystem reference reads rejected;
- cyclic references bounded;
- oversized/deep/ref-expansion input bounded;
- Path Item `$ref` + sibling ambiguity rejected;
- unknown/custom Schema Object dialect remains unresolved;
- Markdown/HTML description cannot flow directly to canonical/tool-facing metadata;
- multiple media types remain unresolved;
- multiple success schemas remain unresolved;
- HTTP method cannot silently set effect/risk/idempotency;
- security scheme cannot create trusted context;
- operationId cannot be lossy-normalized into Action ID;
- callbacks/webhooks do not appear as imported actions;
- OpenAPI 3.2 `additionalOperations` produces an unsupported diagnostic;
- OpenAPI 3.2 fixed `query` operation is selected as an ordinary source operation without inferring SurfaceRelay semantics.

### 18.3 Architecture regression

Prove no dependency or semantic change under:

- canonical `spec/0.1/**`;
- `packages/laravel/src/**`;
- `packages/laravel-mcp/src/**`;
- `packages/browser-runtime/src/**`;
- T-701 conformance targets/model/runner.

## 19. Decisions introduced by this draft

### D-065 — Candidate-only secondary importer

T-704 parses OpenAPI into bounded candidates/diagnostics first. It creates no execution, authority, binding, exposure, or canonical Action Definition until explicit materialization.

### D-066 — Explicit SurfaceRelay semantic completion

Transport/API metadata cannot silently define SurfaceRelay policy/authority semantics. Required canonical semantic fields and ambiguous structural mappings need explicit resolution.

### D-067 — Bounded OpenAPI ingestion

v1 accepts one caller-supplied OpenAPI 3.1.x/3.2.x root document, resolves only same-document fragment references, performs no secondary filesystem/network retrieval, and fails closed on cycles, resource limits, unsupported/ambiguous constructs, and unsupported schema dialects.

### D-068 — Source identity/provenance separation

OpenAPI operation identity and HTTP location remain import provenance. Final exact SurfaceRelay `id + version` is explicitly resolved; no lossy automatic identity conversion is permitted.

D-065 through D-068 are ACCEPTED after implementation, full verification, external review, review-fix revalidation, and incremental reviewer closure. This acceptance is bounded to the reviewed T-704 importer behavior and does not promote D-026 or authorize merge/T-704 closure by itself.

## 20. Explicit non-goals

T-704 v1 does not:

- replace OpenAPI tooling;
- support Swagger/OpenAPI 2.0 or OpenAPI 3.0.x;
- fetch remote references;
- execute HTTP endpoints;
- generate RuntimeBindings;
- create authentication/authorization;
- infer risk/confirmation/idempotency;
- import callbacks/webhooks as actions;
- define `x-surfacerelay-*` extensions;
- change the canonical SurfaceRelay schema;
- broaden T-701 conformance;
- modify T-703 MCP projection;
- create an automatic expose-all path.

## 21. Historical implementation gate

At design approval, implementation had not started. The following constraints governed the later implementation gate:

1. D-065 through D-068 remained PROPOSED through implementation and external review;
2. T-704 tracking remained explicit about implementation state before execution;
3. the approved implementation plan was `docs/superpowers/plans/2026-09-19-openapi-importer.md`;
4. implementation began only in a separate execution gate on `feat/t-704-openapi-importer`;
5. decision promotion required its own explicit post-review gate.

## 22. Design review outcome

**APPROVED** after explicit review of scope alignment, accepted-decision consistency, dependency direction, ambiguity handling, security/resource boundaries, and verification strategy.

Review refinements added before approval:

- one caller-supplied root document and no secondary filesystem/network reference retrieval;
- exact fixed-operation selection, including OpenAPI 3.2 `query`, while rejecting `additionalOperations` in v1;
- fail-closed handling for Path Item `$ref` sibling ambiguity;
- explicit Schema Object dialect/keyword compatibility boundary instead of blind schema copying;
- effective OpenAPI security inheritance remains source evidence only;
- untrusted Markdown/HTML source descriptions cannot flow directly to Action/tool metadata.

No implementation plan, package code, parser dependency, runtime behavior, canonical schema, conformance semantics, or MCP behavior is introduced by this approval.


## 23. Decision promotion outcome

D-065, D-066, D-067, and D-068 are **ACCEPTED**.

Promotion basis:

- implementation and full verification completed;
- external review PR #17 remained open/unmerged while review ran;
- round-one actionable findings were fixed and revalidated;
- review-fix head `e88cd9e34e515aa63df0a7ab87c8d8ec4495edf2` passed push #932 and PR #933 at 12/12;
- re-review/docs head `b83632c790b893f993630c452c47a41444832918` passed push #934 and PR #935 at 12/12;
- incremental CodeRabbit re-review completed with 0 new inline findings and 0 unresolved review threads;
- review-closure head `32385c1ace536178ce7dbc90907d4b670f915384` passed push #936 and PR #937 at 12/12.

Acceptance is revision- and scope-bounded to the reviewed T-704 optional OpenAPI importer. It does not promote D-026, authorize merge, close T-704, broaden the canonical SurfaceRelay specification, or create any HTTP execution/exposure authority.

The next gate is merge + post-merge main revalidation planning only.
