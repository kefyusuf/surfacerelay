import { describe, expect, it, vi } from 'vitest';
import { HtmxBrowserDriver } from '../src/htmx-browser-driver.js';
import type {
  HtmxAjaxContext,
  HtmxAjaxMethod,
  HtmxBrowserRuntime,
  HtmxSourceElement,
} from '../src/htmx-browser-runtime.js';
import type { RuntimeBinding } from '../src/types.js';

function deferred<T>() {
  let resolve!: (value: T | PromiseLike<T>) => void;
  let reject!: (reason?: unknown) => void;
  const promise = new Promise<T>((res, rej) => {
    resolve = res;
    reject = rej;
  });
  return { promise, resolve, reject };
}

const source: HtmxSourceElement = {
  tagName: 'BUTTON',
  parentElement: null,
  classList: { contains: () => false },
  hasAttribute(name) {
    return name === 'data-surfacerelay-htmx-source' || name === 'hx-post';
  },
  getAttribute(name) {
    if (name === 'data-surfacerelay-htmx-source') return 'src-1';
    if (name === 'hx-post') return '/items';
    return null;
  },
};

function binding(): RuntimeBinding {
  return {
    bindingId: 'binding-1',
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

function runtime(
  ajax: HtmxBrowserRuntime['ajax'],
): HtmxBrowserRuntime & {
  assertSupported: ReturnType<typeof vi.fn>;
  findSources: ReturnType<typeof vi.fn>;
  requestClass: ReturnType<typeof vi.fn>;
} {
  return {
    assertSupported: vi.fn(),
    findSources: vi.fn(() => [source]),
    currentLocation: () => ({
      href: 'https://example.test/page',
      origin: 'https://example.test',
    }),
    requestClass: vi.fn(() => 'htmx-request'),
    ajax,
  };
}

describe('HTMX cancellation frontier', () => {
  it('surfaces an already-aborted reason before runtime access', async () => {
    const ajax = vi.fn(async () => undefined);
    const fake = runtime(ajax);
    const controller = new AbortController();
    const reason = new Error('caller cancelled before invocation');
    controller.abort(reason);

    await expect(new HtmxBrowserDriver(fake).execute(
      binding(),
      { item: 'coffee' },
      { signal: controller.signal },
    )).rejects.toBe(reason);

    expect(fake.assertSupported).not.toHaveBeenCalled();
    expect(fake.findSources).not.toHaveBeenCalled();
    expect(ajax).not.toHaveBeenCalled();
  });

  it('rechecks cancellation synchronously immediately before ajax', async () => {
    const controller = new AbortController();
    const reason = new Error('caller cancelled before dispatch');
    const ajax = vi.fn(async () => undefined);
    const fake = runtime(ajax);
    fake.requestClass.mockImplementation(() => {
      controller.abort(reason);
      return 'htmx-request';
    });

    await expect(new HtmxBrowserDriver(fake).execute(
      binding(),
      { item: 'coffee' },
      { signal: controller.signal },
    )).rejects.toBe(reason);

    expect(ajax).not.toHaveBeenCalled();
  });

  it('treats ajax invocation as the frontier and preserves later natural success', async () => {
    const controller = new AbortController();
    const pending = deferred<void>();
    const ajax = vi.fn((
      _method: HtmxAjaxMethod,
      _path: string,
      _context: HtmxAjaxContext,
    ) => {
      controller.abort(new Error('caller stopped observing'));
      return pending.promise;
    });
    const fake = runtime(ajax);

    const execution = new HtmxBrowserDriver(fake).execute(
      binding(),
      { item: 'coffee' },
      { signal: controller.signal },
    );
    pending.resolve(undefined);

    await expect(execution).resolves.toBeUndefined();
    expect(ajax).toHaveBeenCalledTimes(1);
  });

  it('preserves the exact natural HTMX failure after a later caller abort', async () => {
    const controller = new AbortController();
    const pending = deferred<void>();
    const original = new Error('network failed');
    const ajax = vi.fn(() => pending.promise);
    const fake = runtime(ajax);

    const execution = new HtmxBrowserDriver(fake).execute(
      binding(),
      { item: 'coffee' },
      { signal: controller.signal },
    );

    controller.abort(new Error('caller stopped observing'));
    pending.reject(original);

    await expect(execution).rejects.toBe(original);
    expect(ajax).toHaveBeenCalledTimes(1);
  });

  it('keeps no-signal execution on the same ajax path', async () => {
    const ajax = vi.fn(async () => undefined);
    const fake = runtime(ajax);

    await expect(new HtmxBrowserDriver(fake).execute(
      binding(),
      { item: 'coffee' },
      {},
    )).resolves.toBeUndefined();

    expect(ajax).toHaveBeenCalledTimes(1);
  });
});
