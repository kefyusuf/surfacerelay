import { HtmxBrowserDriver } from '/runtime/htmx-browser-driver.js';
import { GlobalHtmxBrowserRuntime } from '/runtime/htmx-browser-runtime.js';

const raw = document.querySelector('#surfacerelay-binding')?.textContent;
if (!raw) {
  throw new Error('Fixture RuntimeBinding is missing.');
}

const binding = JSON.parse(raw);
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

globalThis.surfaceRelayFixture = Object.freeze({
  ready: true,
  addItem(name) {
    return driver.execute(binding, { name }, {});
  },
  replaceSourceForTest,
});
