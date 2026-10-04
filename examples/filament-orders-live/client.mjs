import { DriverRegistry } from '/surfacerelay/runtime/driver-registry.js';
import { LivewireBrowserDriver } from '/surfacerelay/runtime/livewire-browser-driver.js';
import { GlobalLivewireBrowserRuntime } from '/surfacerelay/runtime/livewire-browser-runtime.js';
import { resolveDocumentModelContext } from '/surfacerelay/runtime/webmcp-model-context.js';
import { WebMcpRegistrationLifecycle } from '/surfacerelay/runtime/webmcp-registration-lifecycle.js';

// Filament keeps table selection client-side (Alpine) and pushes it to the
// server only when a table action is mounted (filament/tables table.js
// mountAction). Mirror that push before an agent call so the server-side
// current_selection is the selection the human sees at invocation time. This
// grants no new authority: the server still validates every selected record.
function syncFilamentTableSelection(componentId) {
  const wire = globalThis.Livewire?.find(componentId);
  const tableElement = wire?.$el?.querySelector('[x-data^="filamentTable("]');
  if (!tableElement) {
    return;
  }

  const table = globalThis.Alpine.$data(tableElement);
  wire.$set('isTrackingDeselectedTableRecords', table.isTrackingDeselectedRecords, false);
  wire.$set('selectedTableRecords', [...table.selectedRecords], false);
  wire.$set('deselectedTableRecords', [...table.deselectedRecords], false);
}

class FilamentSelectionSyncingDriver {
  constructor(inner) {
    this.inner = inner;
  }

  execute(binding, input, context) {
    syncFilamentTableSelection(binding.target.componentId);
    return this.inner.execute(binding, input, context);
  }
}

function readServerIssuedTools() {
  return [...document.querySelectorAll('script[data-surfacerelay-bindings]')]
    .flatMap((script) => JSON.parse(script.textContent ?? '[]'));
}

async function registerWebMcpTools() {
  const modelContext = resolveDocumentModelContext();
  if (!modelContext) {
    return { available: false, lease: null, tools: [] };
  }

  const tools = readServerIssuedTools();
  const drivers = new DriverRegistry();
  drivers.register(
    'livewire',
    new FilamentSelectionSyncingDriver(new LivewireBrowserDriver(new GlobalLivewireBrowserRuntime())),
  );
  const lifecycle = new WebMcpRegistrationLifecycle(modelContext, drivers);
  const lease = await lifecycle.register(tools);
  return { available: true, lease, tools: tools.map((tool) => tool.binding.action.id) };
}

function whenLivewireInitialized() {
  return new Promise((resolve) => {
    if (globalThis.Livewire?.all?.().length) {
      resolve();
      return;
    }
    document.addEventListener('livewire:initialized', () => resolve(), { once: true });
  });
}

await whenLivewireInitialized();
const webMcp = await registerWebMcpTools();
addEventListener('pagehide', () => webMcp.lease?.dispose(), { once: true });

globalThis.surfaceRelayOrderDemo = Object.freeze({
  ready: true,
  webMcpAvailable: webMcp.available,
  actionIds: webMcp.tools,
});
