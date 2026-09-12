import {
  GlobalHtmxBrowserRuntime,
  type HtmxAjaxContext,
  type HtmxAjaxMethod,
  type HtmxAmbientRoot,
  type HtmxBrowserRuntime,
  type HtmxSourceElement,
} from '../src/htmx-browser-runtime.js';

declare const source: HtmxSourceElement;
declare const runtime: HtmxBrowserRuntime;

const method: HtmxAjaxMethod = 'post';
const context: HtmxAjaxContext = {
  source,
  values: Object.freeze({ item: 'coffee' }),
};
const ambient: HtmxAmbientRoot = {};
const globalRuntime: HtmxBrowserRuntime = new GlobalHtmxBrowserRuntime(ambient);

void runtime.ajax(method, '/items', context);
void globalRuntime;

// @ts-expect-error unsupported HTMX reference method
const invalidMethod: HtmxAjaxMethod = 'head';
void invalidMethod;
