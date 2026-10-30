/**
 * Quickstart step 3 (app-owned browser glue): register the server-issued
 * descriptor on a supported WebMCP surface. Self-guarding — no
 * document.modelContext, no registration. Discovery carries no authority.
 */
import {
  DriverRegistry,
  GlobalLivewireBrowserRuntime,
  LivewireBrowserDriver,
  WebMcpRegistrationLifecycle,
  resolveDocumentModelContext,
} from '@surfacerelay/browser-runtime';

let lease = null;

// Small diagnostic surface for tests and the browser console; never authority.
window.__quickstartRegistration = { registered: false, reason: 'module_loaded' };

function descriptorFromMountedComponents() {
  if (!window.Livewire || typeof window.Livewire.all !== 'function') return null;
  for (const component of window.Livewire.all()) {
    const wire = component?.$wire;
    if (!wire || typeof wire.get !== 'function') continue;
    let descriptor = null;
    try {
      descriptor = wire.get('surfacerelayDescriptor');
    } catch {
      continue;
    }
    if (descriptor && descriptor.definition && descriptor.binding) return descriptor;
  }
  return null;
}

async function register() {
  if (lease) return;
  const descriptor = descriptorFromMountedComponents();
  if (!descriptor) return;
  if (descriptor.definition.id !== descriptor.binding.action?.id
    || descriptor.definition.version !== descriptor.binding.action?.version) {
    window.__quickstartRegistration = { registered: false, reason: 'identity_mismatch' };
    return;
  }
  const modelContext = resolveDocumentModelContext(document);
  if (modelContext === null) {
    window.__quickstartRegistration = { registered: false, reason: 'model_context_unavailable' };
    return; // unsupported surface: fail closed, no tool
  }

  const drivers = new DriverRegistry();
  drivers.register('livewire', new LivewireBrowserDriver(new GlobalLivewireBrowserRuntime()));

  const lifecycle = new WebMcpRegistrationLifecycle(modelContext, drivers, { resultMode: 'envelope' });
  try {
    lease = await lifecycle.register([{ definition: descriptor.definition, binding: descriptor.binding }]);
    window.__quickstartRegistration = { registered: true, reason: null };
  } catch {
    // Registration failed (identity, duplicate tool, surface rejection):
    // report it diagnostically instead of leaving an unhandled rejection.
    lease = null;
    window.__quickstartRegistration = { registered: false, reason: 'registration_threw' };
  }
}

function dispose() {
  try {
    lease?.dispose();
  } catch {
    // never break the host page
  }
  lease = null;
}

window.addEventListener('livewire:navigating', dispose);
window.addEventListener('livewire:navigated', () => {
  dispose();
  void register();
});

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => void register());
} else {
  void register();
}
