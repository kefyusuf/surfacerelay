# T-704 External Re-Review — Optional OpenAPI Importer

## Review target

- **PR:** #17
- **Branch:** `feat/t-704-openapi-importer`
- **State:** **IMPLEMENTED / EXTERNAL REVIEW FIXES APPLIED / RE-REVIEW PENDING**
- **Reviewed head:** `f004463185876b3a40d8fd71a4519e81b1f7c3f7`
- **Review-fix code head:** `e88cd9e34e515aa63df0a7ab87c8d8ec4495edf2`
- **Review-fix CI:** push Validate **#932** + PR Validate **#933** — **12/12 SUCCESS each**
- **Importer after fixes:** **7 files / 184 tests PASS + typecheck + npm ci**
- **D-065 / D-066 / D-067 / D-068:** **PROPOSED**
- **D-026:** **PROPOSED**
- **Merge / decision promotion / closure:** **NOT PERFORMED**

Detailed task history, resource budgets, regression matrices, and revision-bounded evidence are in `STATUS.md` and `TASKS.md`. The approved design and plan remain:

- `docs/superpowers/specs/2026-09-19-openapi-importer-design.md`
- `docs/superpowers/plans/2026-09-19-openapi-importer.md`

## Scope boundary

T-704 remains an optional, secondary/import-only adapter under D-012.

It accepts one caller-supplied OpenAPI 3.1.x/3.2.x JSON/YAML source, performs bounded same-document reference handling, produces reviewable candidates/diagnostics, and materializes a canonical Action Definition only after explicit SurfaceRelay resolution.

It does **not** create RuntimeBindings, execute HTTP, grant authorization/trusted context, register actions automatically, or expose MCP/WebMCP tools. OpenAPI method/path/operationId/security remain non-authoritative source evidence.

Round-one review fixes are intentionally bounded to:

1. chained same-document Parameter Reference Object resolution;
2. treating a resolved JSON `null` target as a successful lookup followed by type validation, never as silent unresolved evidence;
3. suppressing both automatic schema suggestions when either suggestion dimension produces a blocking ambiguity;
4. marking stale pre-implementation tracking text as historical;
5. restoring this file to a concise review handoff.

Production dependency remains `yaml` only. Forbidden Laravel/MCP/browser/spec/conformance paths remain outside the review-fix code diff.

## Focused re-review questions

1. Are chained Parameter refs now bounded by one traversal budget and resolved to the terminal Parameter Object?
2. Can a same-document ref to JSON `null` still silently drop Path Item, request/response, parameter, or schema evidence?
3. When either input or output suggestion is blocked, are both suggestions kept unresolved while all blocking diagnostics are preserved?
4. Can any OpenAPI/source data create Action identity, risk/effect/idempotency, trusted context, authorization, execution targets, or tool exposure?
5. Are provenance, no-I/O/resource limits, canonical materialization, and forbidden-path boundaries still intact?
6. Are any actionable review findings still unresolved?

## Review stop

This re-review does **not** authorize:

- D-065..D-068 promotion;
- D-026 promotion;
- merge to `main`;
- T-704 closure;
- later work.

Those remain separate explicit gates after external review closure.
