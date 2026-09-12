import { describe, expect, it, vi } from 'vitest';
import { HtmxBindingExecutionError } from '../src/htmx-errors.js';
import {
  GlobalHtmxBrowserRuntime,
  type HtmxAmbientRoot,
  type HtmxSourceElement,
} from '../src/htmx-browser-runtime.js';

function element(sourceId: string): HtmxSourceElement {
  return {
    tagName: 'BUTTON',
    parentElement: null,
    classList: { contains: () => false },
    hasAttribute(name) {
      return name === 'data-surfacerelay-htmx-source';
    },
    getAttribute(name) {
      return name === 'data-surfacerelay-htmx-source' ? sourceId : null;
    },
  };
}

function root(version = '2.0.10'): HtmxAmbientRoot & {
  htmx: {
    version: string;
    config: { requestClass: string };
    ajax: ReturnType<typeof vi.fn>;
  };
  document: { querySelectorAll: ReturnType<typeof vi.fn> };
  location: { href: string; origin: string };
} {
  return {
    htmx: {
      version,
      config: { requestClass: 'htmx-request' },
      ajax: vi.fn(async () => undefined),
    },
    document: {
      querySelectorAll: vi.fn(() => []),
    },
    location: {
      href: 'https://example.test/page',
      origin: 'https://example.test',
    },
  };
}

function expectCode(fn: () => unknown, code: string): void {
  try {
    fn();
    throw new Error('expected HTMX runtime failure');
  } catch (error) {
    expect(error).toBeInstanceOf(HtmxBindingExecutionError);
    expect((error as HtmxBindingExecutionError).code).toBe(code);
  }
}

describe('HTMX browser compatibility boundary', () => {
  it.each(['2.0.0', '2.0.10', '2.7.3', '2.1.0-beta.1'])(
    'accepts supported HTMX 2.x version %s',
    (version) => {
      expect(() => new GlobalHtmxBrowserRuntime(root(version)).assertSupported()).not.toThrow();
    },
  );

  it.each(['', '1.9.12', '4.0.0-beta.1', '2', '2.x', 'garbage'])(
    'rejects unsupported/malformed HTMX version %j',
    (version) => {
      expectCode(
        () => new GlobalHtmxBrowserRuntime(root(version)).assertSupported(),
        'htmx_runtime_unsupported',
      );
    },
  );

  it('fails when the ambient HTMX runtime or callable ajax() is unavailable', () => {
    expectCode(
      () => new GlobalHtmxBrowserRuntime({}).assertSupported(),
      'htmx_runtime_unavailable',
    );

    const ambient = root();
    ambient.htmx.ajax = null as unknown as ReturnType<typeof vi.fn>;
    expectCode(
      () => new GlobalHtmxBrowserRuntime(ambient).assertSupported(),
      'htmx_runtime_unavailable',
    );
  });

  it('fails when document or location capability is unavailable', () => {
    const withoutDocument = root();
    delete (withoutDocument as Partial<HtmxAmbientRoot>).document;
    expectCode(
      () => new GlobalHtmxBrowserRuntime(withoutDocument).assertSupported(),
      'htmx_runtime_unavailable',
    );

    const withoutLocation = root();
    delete (withoutLocation as Partial<HtmxAmbientRoot>).location;
    expectCode(
      () => new GlobalHtmxBrowserRuntime(withoutLocation).assertSupported(),
      'htmx_runtime_unavailable',
    );
  });

  it.each(['', ' htmx-request', 'htmx-request ', 'htmx request', 'htmx\trequest'])(
    'rejects invalid HTMX requestClass %j',
    (requestClass) => {
      const ambient = root();
      ambient.htmx.config.requestClass = requestClass;
      expectCode(
        () => new GlobalHtmxBrowserRuntime(ambient).assertSupported(),
        'htmx_runtime_unsupported',
      );
    },
  );

  it('returns every exact sourceId match without selector interpolation', () => {
    const ambient = root();
    const first = element('src:a.1');
    const other = element('src-other');
    const second = element('src:a.1');
    ambient.document.querySelectorAll.mockReturnValue([first, other, second]);

    const runtime = new GlobalHtmxBrowserRuntime(ambient);

    expect(runtime.findSources('src:a.1')).toEqual([first, second]);
    expect(ambient.document.querySelectorAll).toHaveBeenCalledExactlyOnceWith(
      '[data-surfacerelay-htmx-source]',
    );
  });

  it('returns a stable location snapshot and configured request class', () => {
    const ambient = root();
    ambient.location.href = 'https://example.test/orders?view=table';

    const runtime = new GlobalHtmxBrowserRuntime(ambient);

    expect(runtime.currentLocation()).toEqual({
      href: 'https://example.test/orders?view=table',
      origin: 'https://example.test',
    });
    expect(runtime.requestClass()).toBe('htmx-request');
  });

  it('delegates exact ajax method/path/source/values once', async () => {
    const ambient = root();
    const runtime = new GlobalHtmxBrowserRuntime(ambient);
    const source = element('src-a');
    const values = Object.freeze({ item: 'coffee' });

    await runtime.ajax('post', '/items', { source, values });

    expect(ambient.htmx.ajax).toHaveBeenCalledExactlyOnceWith(
      'post',
      '/items',
      { source, values },
    );
  });
});
