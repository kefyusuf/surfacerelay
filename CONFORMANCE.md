# SurfaceRelay Conformance Scenarios

These are SurfaceRelay adapter/runtime scenarios, not W3C WebMCP browser conformance tests.

Scenario IDs referenced below are registered in `spec/0.1/fixtures/conformance-scenarios.json`, which distinguishes:

- `kind=schema` / `status=executable` — provable today by `scripts/validate.py` through the fixture manifest;
- `kind=runtime` / `status=documented` — semantic scenarios that are **schema-valid but not executable/permitted**; they become executable in T-604/T-701 when real runtimes exist.

This distinction is normative: **schema-invalid is not the same as schema-valid but runtime-invalid/stale.** JSON Schema validates structure only; binding authority is a runtime property.

## Definition

1. Valid Action Definition accepted.
2. Duplicate ID/version rejected by registry.
3. Action Definition containing runtime target fields is rejected by schema/additional-properties rule.

## Binding

### Structural (schema-level)

4. Binding references exact action ID/version (`BIND-ACTION-VERSION-EXACT`: a binding without an exact version is schema-invalid).
5. Unknown lifecycle value is rejected by schema (`BIND-LIFECYCLE-UNKNOWN`).
6. Valid page/component/session/persistent bindings are structurally valid (`BIND-PAGE-VALID`, `BIND-COMPONENT-VALID`, `BIND-SESSION-VALID`, `BIND-PERSISTENT-VALID`).
7. Binding name collision never silently overwrites another action (registry-level; concretely exercised in T-102).

### Runtime semantics (fail closed; documented until T-604/T-701)

8. Unknown binding ID fails closed (`BIND-ID-UNKNOWN`, recommended code `binding_not_found`). A binding ID is a reference, never proof of authorization by itself.
9. Unknown driver fails closed (`BIND-DRIVER-UNKNOWN`, `driver_unsupported`). Drivers are extensible identifiers with an explicit registry (D-016); JSON Schema deliberately cannot express registry membership. No fallback driver is permitted.
10. Expired binding fails closed (`BIND-EXPIRED-NOT-EXECUTABLE`, `binding_expired`). Validity is cumulative (D-024): existence AND non-revocation AND lifecycle authority AND `expiresAt` AND exact action id/version availability AND driver support; whichever invalidates first wins.
11. Binding whose referenced action id/version is unavailable fails closed (`BIND-ACTION-VERSION-UNAVAILABLE`, `binding_stale`).
12. Unmounted/destroyed/replaced component binding fails closed (`BIND-COMPONENT-STALE`, `binding_stale`); rerender of the same instance may remain valid.
13. Old binding must never silently retarget to a replacement component/target — even with the same class, method name, DOM position, or business record (`BIND-NO-SILENT-RETARGET`, `binding_stale`). Replacement requires a newly issued binding ID (D-022/D-025).

Recommended provisional binding error codes (D-026, not a closed enum): `binding_not_found`, `binding_stale`, `binding_expired`, `driver_unsupported`.

### Lifecycle reference (D-023)

- `page` — one runtime-defined page/surface instance; not necessarily a browser document.
- `component` — one specific component instance; rerenders keep validity, replacements never inherit the binding.
- `session` — trusted runtime session authority; identity never comes from caller input; expiry/revocation/logout/rotation makes bindings stale.
- `persistent` — not tied to page/component/session lifetime, but never permanent, global, anonymous, or cache-forever: still subject to revocation, `expiresAt`, exact action version availability, driver availability, and all runtime policy.

## Trust

9. Caller `tenant_id` does not override trusted tenant.
10. Caller `user_id` does not override authenticated actor.
11. Caller record IDs do not satisfy `current_selection` authority.
12. Discovery permission does not bypass invocation authorization.
13. `confirmed=true` does not satisfy `human_confirmation`.
14. Confirmation receipt is scoped and expiration/replay is enforced.
15. Required idempotency key prevents duplicate side effect.

## Output

16. Sensitive output passes through output policy/redaction.
17. Untrusted output is projected to the appropriate surface hint where supported.

## Projection

18. Read effect maps to WebMCP read-only hint without changing core definition.
19. Consequential risk maps to supported WebMCP consequential semantics without changing core definition.
20. Browser cancellation is propagated but is not represented as proven rollback.

## Portability

21. Same action definition can be bound through Livewire and HTMX without changing base action semantics.
22. Shared trust scenarios pass for both bindings.
