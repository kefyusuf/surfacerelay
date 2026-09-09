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
| D-033 | ACCEPTED | Livewire PHP request teardown is not component-lifecycle authority. Server binding production derives the exact mounted component ID and fresh binding IDs; browser registration cleanup and stale-target resolution belong to client component lifecycle signals. Server `destroy` must not revoke or retarget component bindings. |
| D-034 | ACCEPTED | For the Livewire vertical, human interaction and binding-derived agent invocation converge at the same explicitly exposed Livewire component method and shared ActionBus/application action. A future browser driver selects and invokes that exact existing binding target; it must not define an agent-only business endpoint or duplicate mutation path. |
| D-035 | ACCEPTED | Browser `DriverRegistry` maps an exact contract-valid RuntimeBinding driver identifier only to an explicitly registered browser `BindingDriver`. Registration and lookup do not produce bindings, confer authorization, validate lifecycle/expiry/revocation, resolve stale targets, execute drivers, or permit aliases/defaults/fallback. Unknown drivers fail closed. |
| D-036 | ACCEPTED | WebMCP annotations are independent projections of core semantics: only `effect=read` sets `readOnlyHint`, only `outputContentTrust=contains_untrusted_content` sets `untrustedContentHint`, and only `risk=consequential` sets `consequentialHint`. Projection must not infer risk from effect, content trust from sensitivity, or emit unrelated/unsupported hints. The reference projection returns all three booleans deterministically. |
| D-037 | ACCEPTED | One current WebMCP tool snapshot is owned by one SurfaceRelay registration lease and one registration `AbortController`. Disposal/abort ends that generation's registration authority; partial registration failure aborts the generation and preserves the original error. Registration lifetime and per-execution cancellation are separate signals and authorities. |
| D-038 | ACCEPTED | WebMCP tool names are deterministic exact Action identity projections using `<action-id>.v<version>`. Invalid, too-long, or duplicate projected names fail loudly; binding IDs, component IDs, truncation, hashes, aliases, and fallback binding selection are not used to manufacture browser tool identity. |
| D-039 | ACCEPTED | The Livewire browser driver resolves only the exact binding `componentId` through documented `Livewire.find()`, verifies the returned `$wire.$id`, and invokes no name/DOM/class/position/replacement fallback. Missing or mismatched exact component identity is stale and fails closed. |
| D-040 | ACCEPTED | Object-shaped Action input is converted to Livewire positional arguments only through a server-issued driver-owned call plan (`inputOrder` + `requiredCount`) derived from the exact exposed PHP method and Action input schema. JSON/schema/caller property order is never invocation authority; ambiguous or unsupported mappings fail closed. |
| D-041 | ACCEPTED | The reference Livewire driver uses documented `Livewire.find()` and `$wire.$call()` APIs only. Methods that collide with the public `$wire` proxy/state namespace are rejected instead of bypassed through private Livewire request internals. |
| D-042 | ACCEPTED | A SurfaceRelay execution has a strong no-dispatch cancellation guarantee only before framework dispatch begins. WebMCP execution provides a required AbortSignal, and an already-aborted invocation does not perform invocation-time driver resolution/execution. For Livewire, the exact action's documented `onSend` hook marks the dispatch frontier. Cancellation after that frontier does not imply server work stopped, rolled back, or reversed. |
| D-043 | ACCEPTED | The Livewire reference driver uses documented component-scoped action interception and exact `action.cancel()` only before `onSend`. It never uses message/request cancellation for generic SurfaceRelay action cancellation because those broader scopes may contain unrelated framework work. SurfaceRelay does not require `#[Async]`, `#[Isolate]`, or private Livewire request APIs to manufacture cancellability. |
| D-044 | ACCEPTED | Confirmation grants are single-use opaque bearer capabilities bound to exact action version, validated invocation intent, binding/surface, and relevant trusted runtime context. Only successful server-side receipt verification/consumption may materialize `human_confirmation` authority; challenge IDs and caller fields are never authority. |
| D-045 | ACCEPTED | Server-side idempotency is a bounded deduplication guarantee: the caller key is never persisted raw and is SHA-256 namespaced by exact action ID/version plus the trusted authority partition (tenant/actor when present, otherwise browser session, otherwise explicit global); the fingerprint binds validated input and present actor/tenant/current-record/current-selection/browser-session context. Fresh execution is atomically claimed only after confirmation. An exact active `completed` retry replays stored pre-output-policy executor output while current validation, authorization and output policy rerun and confirmation/execution are skipped; conflict, active `in_progress`, and `indeterminate` reuse fail closed. Expiry equality ends the guarantee and permits a new claim. |
| D-046 | ACCEPTED | Sensitive output disclosure is fail-closed and runtime-policy-driven. SurfaceRelay does not guess sensitive fields. Sensitive output is released only by an explicit trusted redactor decision; policy failure after execution is a `failed` result, and raw sensitive output is removed from pipeline state before audit/finalization. Output content trust remains an independent D-032 dimension. |

When a proposed decision becomes part of implemented public behavior, promote it to `ACCEPTED` in the same change or record why it remains experimental.
