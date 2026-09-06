# Decision Register

Status values: `PROPOSED`, `ACCEPTED`, `SUPERSEDED`.

| ID | Status | Decision |
|---|---|---|
| D-001 | ACCEPTED | Focus first on stateful/server-driven UI actions, not generic backend frameworks. |
| D-002 | ACCEPTED | Action Definition and Runtime Binding are separate contracts. |
| D-003 | ACCEPTED | Cross-language sharing is semantic/schema-level; Laravel has a real runtime implementation. |
| D-004 | ACCEPTED | Core semantics are protocol-neutral; WebMCP/MCP annotations are projections. |
| D-005 | ACCEPTED | Browser WebMCP API changes are isolated behind one adapter. |
| D-006 | ACCEPTED | Do not implement an MCP transport stack from scratch. |
| D-007 | ACCEPTED | Caller input cannot manufacture actor, tenant, selection, confirmation, or binding authority. |
| D-008 | ACCEPTED | Start as one monorepo; split only with dependency/release evidence. |
| D-009 | ACCEPTED | Do not promote a public cross-framework spec before two materially different bindings pass shared scenarios. |
| D-010 | ACCEPTED | Action exposure is explicit; no expose-all convention. |
| D-011 | ACCEPTED | Discovery permission does not imply invocation permission. |
| D-012 | ACCEPTED | OpenAPI is a secondary/import adapter, not the core product. |
| D-013 | ACCEPTED | Action versions are explicit positive integers and RuntimeBindings reference exact `id + version`. |
| D-014 | ACCEPTED | `consequential` risk is a categorical confirmation gate, orthogonal to effect/destructiveness. |
| D-015 | ACCEPTED | Confirmation authority uses opaque, scoped, expiring runtime-issued receipts; caller booleans never grant authority. |
| D-016 | ACCEPTED | Binding drivers are extensible identifiers with an explicit registry; unknown drivers fail closed and never fall back. |
| D-017 | ACCEPTED | `current_selection` is trusted runtime context, never caller record IDs. T-105/T-106 operationalize this with typed trusted entries and a fail-closed context gate. |
| D-018 | ACCEPTED | Apache-2.0 is the project license; the complete license text is published in `LICENSE`. |
| D-019 | PROPOSED | Filament is the first production-oriented reference vertical. |
| D-020 | PROPOSED | HTMX is the second binding used to test portability. |
| D-021 | SUPERSEDED | The old single-valued `outputTrust` precedence model is superseded by D-032 because it collapsed independent confidentiality/content-trust properties. |
| D-022 | ACCEPTED | `bindingId` identifies one exact issued binding instance, exact action version and target; no ID reuse or silent retargeting. Binding ID is a reference, not authorization proof. |
| D-023 | ACCEPTED | Binding lifecycles are `page`, `component`, `session`, and `persistent`; persistent never means permanent/global/anonymous. |
| D-024 | ACCEPTED | Binding validity is cumulative: existence, non-revocation, lifecycle authority, expiry, exact action availability, and driver support must all hold. |
| D-025 | ACCEPTED | Stale bindings fail closed and must not rediscover/substitute similar replacement targets. |
| D-026 | PROPOSED | Provisional binding failure codes: `binding_not_found`, `binding_stale`, `binding_expired`, `driver_unsupported`; ActionError codes remain extensible. |
| D-027 | ACCEPTED | InvocationContext separates trusted typed entries from non-authoritative metadata; no payload/metadata fallback, duplicates fail loudly, absence is entry absence. |
| D-028 | ACCEPTED | Actor and tenant authority are resolved from injected trusted services before ActionBus dispatch through zero-argument resolver contracts. |
| D-029 | ACCEPTED | Laravel authorization evaluates the exact trusted actor via `Gate::forUser(actor)`; no ambient-user fallback or implicit Action-ID-to-ability mapping. |
| D-030 | ACCEPTED | `ActionError.code` is an extensible machine-readable lowercase string namespace, not a closed enum. |
| D-031 | ACCEPTED | `succeeded` = execution completed; `rejected` = deliberate pre-success refusal; `failed` = runtime/execution failure; `confirmation_required` = awaiting a real trusted challenge. Unknown halt codes fail loudly. |
| D-032 | ACCEPTED | Output confidentiality and content trust are independent: `outputSensitivity` (`normal`/`sensitive`) and `outputContentTrust` (`trusted_application_data`/`contains_untrusted_content`). Sensitive output may simultaneously contain untrusted content. |

When a proposed decision becomes part of implemented public behavior, promote it to `ACCEPTED` in the same change or record why it remains experimental.
