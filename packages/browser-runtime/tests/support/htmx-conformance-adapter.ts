import { vi } from 'vitest';
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
  type BindingDriverConformanceAdapter,
} from './binding-driver-conformance-suite.js';

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

export const htmxConformanceAdapter: BindingDriverConformanceAdapter = {
  name: 'htmx',

  createHarness() {
    const original = new ConformanceSource('src-1');
    const replacement = new ConformanceSource('src-2');
    const sources: HtmxSourceElement[] = [original];

    const ajax = vi.fn(async (
      _method: HtmxAjaxMethod,
      _path: string,
      _context: HtmxAjaxContext,
    ) => undefined);

    const runtime: HtmxBrowserRuntime = {
      assertSupported: vi.fn(),
      findSources: vi.fn((sourceId: string) => sources.filter(
        (candidate) => candidate.getAttribute('data-surfacerelay-htmx-source') === sourceId,
      )),
      currentLocation: () => ({
        href: 'https://example.test/page',
        origin: 'https://example.test',
      }),
      requestClass: vi.fn(() => 'htmx-request'),
      ajax,
    };

    const binding = htmxBinding();

    return {
      driver: new HtmxBrowserDriver(runtime, { now: () => CONFORMANCE_NOW }),
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
        return ajax.mock.calls.length;
      },

      replacementDispatchCount() {
        return ajax.mock.calls.filter(([, , context]) => context.source === replacement).length;
      },
    };
  },
};
