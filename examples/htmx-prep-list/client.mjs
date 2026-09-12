import { HtmxBrowserDriver } from '/runtime/htmx-browser-driver.js';
import { GlobalHtmxBrowserRuntime } from '/runtime/htmx-browser-runtime.js';

const raw = document.querySelector('#surfacerelay-binding')?.textContent;
if (!raw) {
  throw new Error('Fixture RuntimeBinding is missing.');
}

const binding = JSON.parse(raw);
const runtime = new GlobalHtmxBrowserRuntime();
const driver = new HtmxBrowserDriver(runtime);

globalThis.surfaceRelayFixture = Object.freeze({
  ready: true,
});

void binding;
void driver;
