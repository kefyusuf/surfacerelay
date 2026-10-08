import { DriverRegistry } from '/surfacerelay/runtime/driver-registry.js';
import { FilamentBrowserDriver, FilamentSelectionCoordinator } from '/surfacerelay/runtime/filament-browser-driver.js';
import { GlobalFilamentSelectionRuntime } from '/surfacerelay/runtime/filament-selection-runtime.js';
import { GlobalLivewireBrowserRuntime } from '/surfacerelay/runtime/livewire-browser-runtime.js';
import { resolveDocumentModelContext } from '/surfacerelay/runtime/webmcp-model-context.js';
import { WebMcpRegistrationLifecycle } from '/surfacerelay/runtime/webmcp-registration-lifecycle.js';

// Share this coordinator with every Filament helper in this Livewire environment.
const selectionCoordinator = new FilamentSelectionCoordinator();

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
    new FilamentBrowserDriver(new GlobalLivewireBrowserRuntime(), {
      tools,
      selectionRuntime: new GlobalFilamentSelectionRuntime(),
      coordinator: selectionCoordinator,
    }),
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
