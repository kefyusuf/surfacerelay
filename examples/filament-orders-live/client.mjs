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
  const componentElement = wire?.$el;
  if (componentElement?.getAttribute('wire:id') !== componentId || typeof wire?.$set !== 'function') {
    throw new Error('Filament selection component is unavailable.');
  }
  const tables = [...componentElement.querySelectorAll('[x-data^="filamentTable("]')]
    .filter((element) => element.closest('[wire\\:id]') === componentElement);
  if (tables.length !== 1 || typeof globalThis.Alpine?.$data !== 'function') {
    throw new Error('Filament selection requires exactly one current owned table.');
  }
  const table = globalThis.Alpine.$data(tables[0]);
  if (typeof table?.isTrackingDeselectedRecords !== 'boolean'
    || !(table.selectedRecords instanceof Set) || !(table.deselectedRecords instanceof Set)) {
    throw new Error('Filament table selection state is invalid.');
  }
  const selected = [...table.selectedRecords];
  const deselected = [...table.deselectedRecords];
  if ([...selected, ...deselected].some((key) => typeof key !== 'string')) {
    throw new Error('Filament table selection keys are invalid.');
  }
  wire.$set('isTrackingDeselectedTableRecords', table.isTrackingDeselectedRecords, false);
  wire.$set('selectedTableRecords', selected, false);
  wire.$set('deselectedTableRecords', deselected, false);
}

class FilamentSelectionSyncingDriver {
  constructor(inner, selectionBindings) {
    this.inner = inner;
    this.selectionBindings = selectionBindings;
    this.pendingComponents = new Set();
  }

  async execute(binding, input, context) {
    const componentId = binding.target.componentId;
    if (this.pendingComponents.has(componentId)) {
      throw new Error('A SurfaceRelay invocation is already pending for this Filament component.');
    }
    this.pendingComponents.add(componentId);
    try {
      if (this.selectionBindings.has(binding)) {
        syncFilamentTableSelection(componentId);
      }
      return await this.inner.execute(binding, input, context);
    } finally {
      this.pendingComponents.delete(componentId);
    }
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
    new FilamentSelectionSyncingDriver(
      new LivewireBrowserDriver(new GlobalLivewireBrowserRuntime()),
      new Set(tools.filter((tool) => tool.definition.contextRequirements.includes('current_selection'))
        .map((tool) => tool.binding)),
    ),
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
