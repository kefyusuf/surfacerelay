import { DriverRegistry } from '/runtime/driver-registry.js';
import { HtmxBrowserDriver } from '/runtime/htmx-browser-driver.js';
import { GlobalHtmxBrowserRuntime } from '/runtime/htmx-browser-runtime.js';
import { resolveDocumentModelContext } from '/runtime/webmcp-model-context.js';
import { WebMcpRegistrationLifecycle } from '/runtime/webmcp-registration-lifecycle.js';

function readJsonScript(id) {
  const raw = document.getElementById(id)?.textContent;
  if (!raw) {
    throw new Error(`Fixture script #${id} is missing.`);
  }
  return JSON.parse(raw);
}

const definition = readJsonScript('surfacerelay-action');
const binding = readJsonScript('surfacerelay-binding');
const runtime = new GlobalHtmxBrowserRuntime();
const driver = new HtmxBrowserDriver(runtime);

function replaceSourceForTest() {
  const oldSource = document.querySelector('[data-surfacerelay-htmx-source]');
  if (!oldSource) {
    throw new Error('Exact HTMX source is missing.');
  }

  const oldSourceId = oldSource.getAttribute('data-surfacerelay-htmx-source');
  const newSourceId = `prep-add-replacement-${crypto.randomUUID()}`;
  const replacement = oldSource.cloneNode(true);
  replacement.setAttribute('data-surfacerelay-htmx-source', newSourceId);
  oldSource.replaceWith(replacement);

  return { oldSourceId, newSourceId };
}

async function registerWebMcpTools() {
  const modelContext = resolveDocumentModelContext();
  if (!modelContext) {
    return { available: false, lease: null };
  }

  const drivers = new DriverRegistry();
  drivers.register('htmx', driver);
  const lifecycle = new WebMcpRegistrationLifecycle(modelContext, drivers);
  const lease = await lifecycle.register([{ definition, binding }]);
  return { available: true, lease };
}

const webMcp = await registerWebMcpTools();
addEventListener('pagehide', () => webMcp.lease?.dispose(), { once: true });

globalThis.surfaceRelayFixture = Object.freeze({
  ready: true,
  webMcpAvailable: webMcp.available,
  addItem(name) {
    return driver.execute(binding, { name }, {});
  },
  disposeWebMcpForTest() {
    webMcp.lease?.dispose();
  },
  replaceSourceForTest,
});
