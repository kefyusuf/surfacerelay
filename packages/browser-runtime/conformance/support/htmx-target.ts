import { HtmxBrowserDriver } from '../../src/htmx-browser-driver.js';
import type {
  HtmxAjaxContext,
  HtmxAjaxMethod,
  HtmxBrowserRuntime,
  HtmxSourceElement,
} from '../../src/htmx-browser-runtime.js';
import type { RuntimeBinding } from '../../src/types.js';
import {
  CONFORMANCE_NOW,
  type BindingDriverConformanceTarget,
} from './binding-driver-target.js';

class ConformanceSource implements HtmxSourceElement {
  readonly parentElement = null;
  readonly tagName = 'BUTTON';
  readonly attrs = new Map<string, string>();
  readonly classList = {
    contains: (_token: string) => false,
  };

  constructor(sourceId: string) {
    this.attrs.set('data-surfacerelay-htmx-source', sourceId);
    this.attrs.set('hx-post', '/items');
  }

  hasAttribute(name: string): boolean {
    return this.attrs.has(name);
  }

  getAttribute(name: string): string | null {
    return this.attrs.get(name) ?? null;
  }
}

function htmxBinding(): RuntimeBinding {
  return {
    bindingId: 'conformance-htmx-1',
    action: { id: 'prep_list.add_item', version: 1 },
    driver: 'htmx',
    lifecycle: 'page',
    target: {
      sourceId: 'src-1',
      method: 'POST',
      path: '/items',
      inputNames: ['item'],
      requiredInputNames: ['item'],
    },
    expiresAt: null,
  };
}

interface AjaxDispatch {
  readonly method: HtmxAjaxMethod;
  readonly path: string;
  readonly context: HtmxAjaxContext;
}

export function createHtmxConformanceTarget(
  now: Date = CONFORMANCE_NOW,
): BindingDriverConformanceTarget {
  const original = new ConformanceSource('src-1');
  const replacement = new ConformanceSource('src-2');
  const sources: HtmxSourceElement[] = [original];
  const ajaxDispatches: AjaxDispatch[] = [];

  const runtime: HtmxBrowserRuntime = {
    assertSupported() {},
    findSources(sourceId: string) {
      return sources.filter(
        (candidate) => candidate.getAttribute('data-surfacerelay-htmx-source') === sourceId,
      );
    },
    currentLocation: () => ({
      href: 'https://example.test/page',
      origin: 'https://example.test',
    }),
    requestClass: () => 'htmx-request',
    async ajax(method, path, context) {
      ajaxDispatches.push({ method, path, context });
    },
  };

  const binding = htmxBinding();

  return {
    driver: new HtmxBrowserDriver(runtime, { now: () => now }),
    binding,
    validInput: { item: 'coffee' },
    invalidTargetBinding: {
      ...binding,
      target: {
        sourceId: 'src-1',
        method: 'POST',
        path: '/items',
        inputNames: ['item'],
      },
    },
    unknownInput: { item: 'coffee', extra: true },
    missingRequiredInput: {},

    makeTargetStale() {
      sources.splice(0, sources.length);
    },

    replaceTargetWithEquivalentIdentity() {
      sources.splice(0, sources.length, replacement);
    },

    frameworkDispatchCount() {
      return ajaxDispatches.length;
    },

    replacementDispatchCount() {
      return ajaxDispatches.filter(({ context }) => context.source === replacement).length;
    },
  };
}
