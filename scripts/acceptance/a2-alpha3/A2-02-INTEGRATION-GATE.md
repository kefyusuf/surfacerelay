# A2-02 — first independent authenticated Filament action

**State: NOT RUN.** This is a source-verified execution checklist, **not** a working Laravel application, proof of integration, or new SurfaceRelay contract. Complete A2-01 first. Review baseline: `kefyusuf/surfacerelay` alpha.3 release source `83ebb86984ef99ad397fa18ccaf48dbf5f8150e5` and current published public API.

## Purpose

In one new Laravel 13 + Filament 5 consumer (using only actual registry installs), demonstrate that a logged-in member can discover and invoke an explicitly exposed application action through a mounted Filament page. The normal human/agent route must reach the same trusted server operation. There must be no extra agent-only business route. Only synthetic records and local storage are permitted.

## Minimal implementation order (the consuming application owns the wiring)

1. **Application + authentication.** Create one Filament Orders resource with a login-protected panel, real `tenant-a` membership for a fixture user and one authorized, harmless `orders.inspect`-style read. Do not copy monorepo generator sources or its generated gateway. Anonymous and non-member requests must fail.
2. **Action Definition.** Register a protocol-neutral `SurfaceRelay\Laravel\Definition\ActionDefinition` in `InMemoryActionRegistry` with an exact positive version, bounded input/output, explicit context requirements, `effect=read`, and the correct risk/idempotency/output classifications. Do **not** invent a new ActionDefinition property for Filament, browser or WebMCP.
3. **ActionBus.** Construct `SurfaceRelay\Laravel\Runtime\Pipeline\ActionBus` with exactly one implementation for each `ActionPipelineStage` (`input_validation`, `authorization`, `idempotency`, `confirmation`, `execution`, `output_policy`) and a real `ActionPipelineAuditor`. The `SurfaceRelayServiceProvider` only exposes the migration path and **does not** assemble this bus. Do not use no-op authorization as a shortcut. For the initial read, downstream write/receipt controls are not evidence yet.
4. **Trusted actor and tenant.** Provide application-controlled `AuthenticatedActorResolver` and `TenantResolver` to `TrustedContextComposer`. Do not hydrate authority from request JSON, metadata, tool parameters, browser form fields, or selected IDs.
5. **Active page invocation.** Route the existing, explicitly exposed Filament/Livewire method through `FilamentActionGateway::dispatch(Page $page, string $actionId, int $actionVersion, array $input, string $surface, string $correlationId, ...)`. Only the actual active Page instance is accepted. The server must recheck invocation authorization.
6. **Owned bindings and browser projection.** Issue exact application binding descriptors for the current mounted component. Pair each canonical `ActionDefinition` with its corresponding `RuntimeBinding` as `BoundActionTool`; use those **same object instances** when constructing opt-in `new FilamentBrowserDriver(new GlobalLivewireBrowserRuntime(), { tools, selectionRuntime: new GlobalFilamentSelectionRuntime(), coordinator })` and calling `WebMcpRegistrationLifecycle::register(tools)`. One shared `FilamentSelectionCoordinator` per Livewire environment. Do not create fake binding IDs or derive selected row authority from tool input.
7. **Browser compatibility.** Use `resolveDocumentModelContext`/capability checking. Register only on a supported browser surface and clean up lease on unmount. A real Chrome `document.modelContext` discovery/invocation proof remains A2-08; a TypeScript build alone is not that proof.

## Public API cross-check (source inspected; no runtime invocation claimed)

| Surface | Public contract verified |
| --- | --- |
| Laravel provider | `SurfaceRelayServiceProvider::boot()` calls `loadMigrationsFrom` only |
| Runtime bus | `ActionBus::__construct(ActionRegistry, ActionPipelineAuditor, array $handlers)`; rejects missing/duplicate stage implementations |
| Filament gateway | `FilamentActionGateway` derives trusted page context, creates `ActionCall`, forwards to the same bus; optional `FilamentConfirmationBridge` |
| Server selection | `FilamentCurrentSelectionResolver` obtains actual table Eloquent model records, with bounds and identity checks |
| Browser driver | `FilamentBrowserDriver` registers only exact `BoundActionTool[]` bindings, checks mutation and shares a coordinator |
| WebMCP lease | `WebMcpRegistrationLifecycle::register(readonly BoundActionTool[])` returns a disposable registration lease |

## Acceptance evidence, in this order

- Record fresh Composer/npm lock verification `PASS`, installed package paths and complete environment diagnostics from A2-01. If unavailable, A2-02 = `BLOCKED`, not `FAIL`.
- Show an authenticated Filament page carrying exactly one intended action/binding, and no unauthorized/anonymous exposure.
- Invoke the page's actual handler; assert the ActionBus receives the exact ID/version, actor, tenant, binding and input, and produces expected read output. The human and agent routes must reach the **same** implementation. A successful browser import is insufficient.
- Negative checks for the first action: unauthorized actor or tenant, a forged tenant in caller input and stale binding must be rejected. Forged selection is checked only after the selection-dependent action is added under A2-05. Use the canonical security contract; do not promote a temporary browser fixture into authority.
- For consequential selection/write approval, defer to A2-04 through A2-07: human confirmation, effect count, replay, and response loss. Do not declare these `PASS` based on the harmless read.

## Decision boundaries

- **DOCS** when public class/API suffices but instructions are missing. Record the exact missing step and propose *one* focused docs correction, without altering runtime.
- **FAIL** only for an independently reproduced published-product defect with a minimal negative regression.
- **BLOCKED** for network/toolchain/browser limitations or unperformed steps.
- No code, PR, release, tag, CI trigger or new adapter is authorized by this checklist. Reuse the existing accepted contracts, not a newly designed abstraction.

## Source links

- https://github.com/kefyusuf/surfacerelay/blob/main/packages/laravel/src/SurfaceRelayServiceProvider.php
- https://github.com/kefyusuf/surfacerelay/blob/main/packages/laravel/src/Runtime/Pipeline/ActionBus.php
- https://github.com/kefyusuf/surfacerelay/blob/main/packages/laravel/src/Filament/Invocation/FilamentActionGateway.php
- https://github.com/kefyusuf/surfacerelay/blob/main/packages/laravel/src/Filament/Context/FilamentCurrentSelectionResolver.php
- https://github.com/kefyusuf/surfacerelay/blob/main/packages/browser-runtime/src/filament-browser-driver.ts
- https://github.com/kefyusuf/surfacerelay/blob/main/packages/browser-runtime/src/webmcp-registration-lifecycle.ts
