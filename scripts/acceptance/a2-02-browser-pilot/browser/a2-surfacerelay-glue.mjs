/**
 * A2-02 B2 app-owned browser glue for the read-only `a2.orders.inspect@1` pilot.
 *
 * Consumes ONLY the published `@surfacerelay/browser-runtime` root export and the
 * server-issued descriptor document (canonical ActionDefinition JSON plus the
 * exact LivewireBindingProducer descriptor for the mounted component). The glue
 * never fabricates bindings, never derives authority from tool input, and never
 * registers on an unsupported surface: discovery metadata carries no invocation
 * authority, and the server re-checks actor, tenant and binding on every call.
 *
 * One shared FilamentSelectionCoordinator per Livewire environment is required
 * by the published contract; callers must reuse `handle.coordinator` for every
 * helper built in the same environment. B2 verifies envelope return semantics:
 * `returned` is not business success, and a void server method yields
 * `output.kind: 'undefined'`.
 */

import {
  DriverRegistry,
  FilamentBrowserDriver,
  FilamentSelectionCoordinator,
  GlobalFilamentSelectionRuntime,
  GlobalLivewireBrowserRuntime,
  WebMcpRegistrationLifecycle,
  resolveDocumentModelContext,
} from '@surfacerelay/browser-runtime';

const MODEL_CONTEXT_UNAVAILABLE = 'model_context_unavailable';

/**
 * Register the server-issued A2 read-only tool on a supported WebMCP surface.
 *
 * @param {{
 *   definition: import('@surfacerelay/browser-runtime').ActionDefinition,
 *   binding: import('@surfacerelay/browser-runtime').RuntimeBinding,
 *   doc?: unknown,
 *   livewireRoot?: unknown,
 *   selectionRoot?: unknown,
 *   selectionRuntime?: import('@surfacerelay/browser-runtime').FilamentSelectionRuntime,
 *   coordinator?: FilamentSelectionCoordinator,
 * }} options
 * @returns {Promise<{
 *   registered: boolean,
 *   lease: import('@surfacerelay/browser-runtime').WebMcpRegistrationLease | null,
 *   coordinator: FilamentSelectionCoordinator | null,
 *   reason: string | null,
 * }>}
 */
export async function createA2ToolRegistration({
  definition,
  binding,
  doc = globalThis.document,
  livewireRoot = globalThis,
  selectionRoot = globalThis,
  selectionRuntime,
  coordinator,
} = {}) {
  if (definition === null || typeof definition !== 'object') {
    throw new Error('A2 browser descriptor requires a definition object.');
  }
  if (binding === null || typeof binding !== 'object') {
    throw new Error('A2 browser descriptor requires a binding object.');
  }
  if (definition.id !== binding.action?.id || definition.version !== binding.action?.version) {
    throw new Error('A2 action definition and binding identity must match exactly.');
  }

  const modelContext = resolveDocumentModelContext(doc);
  if (modelContext === null) {
    // Fail closed: no registration, no lease, no synthetic surface.
    return { registered: false, lease: null, coordinator: null, reason: MODEL_CONTEXT_UNAVAILABLE };
  }

  const sharedCoordinator = coordinator ?? new FilamentSelectionCoordinator();
  const resolvedSelectionRuntime =
    selectionRuntime ?? new GlobalFilamentSelectionRuntime(selectionRoot);
  const tools = Object.freeze([{ definition, binding }]);

  const drivers = new DriverRegistry();
  drivers.register(
    'livewire',
    new FilamentBrowserDriver(new GlobalLivewireBrowserRuntime(livewireRoot), {
      tools,
      selectionRuntime: resolvedSelectionRuntime,
      coordinator: sharedCoordinator,
    }),
  );

  // Envelope mode is the B2 return-semantics contract since alpha.2: every
  // invocation resolves to the projection-only execution envelope.
  const lifecycle = new WebMcpRegistrationLifecycle(modelContext, drivers, {
    resultMode: 'envelope',
  });
  const lease = await lifecycle.register(tools);

  return { registered: true, lease, coordinator: sharedCoordinator, reason: null };
}
