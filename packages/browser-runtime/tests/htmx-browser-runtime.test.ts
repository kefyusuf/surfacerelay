import { describe, expect, it, vi } from 'vitest';
import { readFileSync } from 'node:fs';
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
    const source = eventSource('src-a');
    const values = Object.freeze({ item: 'coffee' });
    ambient.htmx.ajax.mockImplementation(htmxRequest({ status: 201, successful: true }));

    await runtime.ajax('post', '/items', { source, values });

    expect(ambient.htmx.ajax).toHaveBeenCalledExactlyOnceWith(
      'post',
      '/items',
      { source, values },
    );
  });
});

type EventSourceElement = HtmxSourceElement & EventTarget;

function eventSource(sourceId: string): EventSourceElement {
  return Object.assign(new EventTarget(), element(sourceId));
}

function emit(source: EventTarget, type: string, detail: unknown): void {
  source.dispatchEvent(new CustomEvent(type, { detail }));
}

interface FakeRequest {
  status?: number;
  successful?: boolean;
  sent?: boolean;
  transport?: 'load' | 'error' | 'load_error';
  responseHeaders?: Record<string, string>;
}

// Mirrors htmx 2 issueAjaxRequest(): beforeSend and afterRequest are dispatched on the
// source with the request's xhr in detail; the returned promise resolves after onload
// (including HTTP error statuses), rejects on transport errors, and never settles
// when the response handler throws (htmx:onLoadError).
function htmxRequest(request: FakeRequest) {
  return (_method: string, _path: string, context: { source: EventTarget }) => {
    const { source } = context;
    if (request.sent === false) {
      return Promise.resolve();
    }
    const headers = request.responseHeaders ?? {};
    const xhr = {
      status: request.status ?? 0,
      getResponseHeader: (name: string): string | null => headers[name] ?? null,
    };
    emit(source, 'htmx:beforeSend', { xhr });
    if (request.transport === 'error') {
      emit(source, 'htmx:afterRequest', { xhr });
      return Promise.reject(undefined);
    }
    if (request.transport === 'load_error') {
      emit(source, 'htmx:onLoadError', { xhr, error: new Error('swap failed') });
      return new Promise<void>(() => undefined);
    }
    emit(source, 'htmx:afterRequest', {
      xhr,
      successful: request.successful,
      failed: request.successful === false,
    });
    return Promise.resolve();
  };
}

async function expectAjaxCode(promise: Promise<unknown>, code: string): Promise<void> {
  await expect(promise).rejects.toBeInstanceOf(HtmxBindingExecutionError);
  await expect(promise).rejects.toMatchObject({ code });
}

describe('HTMX request outcome boundary', () => {
  function setup() {
    const ambient = root();
    const runtime = new GlobalHtmxBrowserRuntime(ambient);
    const source = eventSource('src-a');
    const call = () => runtime.ajax('post', '/items', { source, values: { name: 'x' } });
    return { ambient, source, call };
  }

  it('resolves only after the issued request completes successfully', async () => {
    const { ambient, call } = setup();
    ambient.htmx.ajax.mockImplementation(htmxRequest({ status: 201, successful: true }));

    await expect(call()).resolves.toBeUndefined();
  });

  it.each([400, 403, 409, 422, 500, 503])(
    'rejects a completed request with unsuccessful HTTP %i',
    async (status) => {
      const { ambient, call } = setup();
      ambient.htmx.ajax.mockImplementation(htmxRequest({ status, successful: false }));

      const promise = call();
      await expectAjaxCode(promise, 'htmx_request_failed');
      await expect(promise).rejects.toThrow(String(status));
    },
  );

  it('rejects when htmx resolves without sending the request', async () => {
    const { ambient, call } = setup();
    ambient.htmx.ajax.mockImplementation(htmxRequest({ sent: false }));

    await expectAjaxCode(call(), 'htmx_request_not_sent');
  });

  it('does not claim a deferred confirmation can never send the request', async () => {
    const { ambient, source, call } = setup();
    ambient.htmx.ajax.mockImplementation((_method, _path, context) => {
      const event = new CustomEvent('htmx:confirm', {
        detail: { etc: { values: context.values } },
        cancelable: true,
      });
      source.dispatchEvent(event);
      // HTMX resolves the original promise when an application vetoes confirmation;
      // its issueRequest callback may still send the request later.
      event.preventDefault();
      return Promise.resolve();
    });

    await expectAjaxCode(call(), 'htmx_request_failed');
  });

  it('does not associate another request confirmation veto with this invocation', async () => {
    const { ambient, source, call } = setup();
    ambient.htmx.ajax.mockImplementation(() => {
      const event = new CustomEvent('htmx:confirm', {
        detail: { etc: { values: { name: 'other-request' } } },
        cancelable: true,
      });
      source.dispatchEvent(event);
      event.preventDefault();
      return Promise.resolve();
    });

    await expectAjaxCode(call(), 'htmx_request_not_sent');
  });

  it('rejects when htmx rejects before sending the request', async () => {
    const { ambient, call } = setup();
    ambient.htmx.ajax.mockImplementation(() => Promise.reject(undefined));

    await expectAjaxCode(call(), 'htmx_request_not_sent');
  });

  it('rejects transport failures after the request was sent', async () => {
    const { ambient, call } = setup();
    ambient.htmx.ajax.mockImplementation(htmxRequest({ transport: 'error' }));

    await expectAjaxCode(call(), 'htmx_request_failed');
  });

  it('rejects instead of hanging when htmx fails while handling the response', async () => {
    const { ambient, call } = setup();
    ambient.htmx.ajax.mockImplementation(htmxRequest({ status: 200, transport: 'load_error' }));

    await expectAjaxCode(call(), 'htmx_request_failed');
  });

  it('treats a completion without an explicit successful flag as failure', async () => {
    const { ambient, call } = setup();
    ambient.htmx.ajax.mockImplementation(htmxRequest({ status: 200 }));

    await expectAjaxCode(call(), 'htmx_request_failed');
  });

  it('ignores completions of other requests on the same source', async () => {
    const { ambient, source, call } = setup();
    ambient.htmx.ajax.mockImplementation((...args: Parameters<ReturnType<typeof htmxRequest>>) => {
      emit(source, 'htmx:afterRequest', { xhr: { status: 500 }, successful: false });
      return htmxRequest({ status: 201, successful: true })(...args);
    });

    await expect(call()).resolves.toBeUndefined();
  });

  it('does not let a foreign successful completion mask its own failure', async () => {
    const { ambient, source, call } = setup();
    ambient.htmx.ajax.mockImplementation((...args: Parameters<ReturnType<typeof htmxRequest>>) => {
      const result = htmxRequest({ status: 422, successful: false })(...args);
      emit(source, 'htmx:afterRequest', { xhr: { status: 201 }, successful: true });
      return result;
    });

    await expectAjaxCode(call(), 'htmx_request_failed');
  });

  it('never reports a sent but uncorrelatable request as not sent', async () => {
    const { ambient, source, call } = setup();
    ambient.htmx.ajax.mockImplementation(() => {
      emit(source, 'htmx:beforeSend', {});
      emit(source, 'htmx:afterRequest', { successful: true });
      return Promise.resolve();
    });

    await expectAjaxCode(call(), 'htmx_request_failed');
  });

  it('removes its listeners once the request settles', async () => {
    const { ambient, source, call } = setup();
    ambient.htmx.ajax.mockImplementation(htmxRequest({ status: 201, successful: true }));
    const added = vi.spyOn(source, 'addEventListener');
    const removed = vi.spyOn(source, 'removeEventListener');

    await call();

    expect(added.mock.calls.length).toBeGreaterThan(0);
    expect(removed.mock.calls.map(([type, listener]) => [type, listener]))
      .toEqual(added.mock.calls.map(([type, listener]) => [type, listener]));
  });

  describe('business result (D-078)', () => {
    const ok = (headers?: Record<string, string>): FakeRequest => ({
      status: 201,
      successful: true,
      responseHeaders: headers,
    });

    const contractCases = JSON.parse(readFileSync(
      new URL('../conformance/htmx-result-envelope.fixtures.json', import.meta.url), 'utf8',
    )) as { name: string; expect: string; header: Record<string, { value: unknown }> }[];
    it.each(contractCases)('matches the adapter fixture $name', async (fixture) => {
      const { ambient, call } = setup();
      ambient.htmx.ajax.mockImplementation(htmxRequest(ok({
        'HX-Trigger': JSON.stringify(fixture.header),
      })));
      await expect(call()).resolves.toEqual(fixture.expect === 'valid'
        ? fixture.header['surfacerelay:result'].value : undefined);
    });

    it('returns the surfacerelay:result object from HX-Trigger of the issued request', async () => {
      const { ambient, call } = setup();
      ambient.htmx.ajax.mockImplementation(htmxRequest(ok({
        'HX-Trigger': JSON.stringify({ 'surfacerelay:result': { value: { itemId: '7' } }, unrelated: 1 }),
      })));

      await expect(call()).resolves.toEqual({ itemId: '7' });
    });

    it.each(['archive', null, 42, '#items', { nested: true }])(
      'preserves business target %j and other HTMX-like fields inside value', async (target) => {
        const { ambient, call } = setup();
        const value = { itemId: '7', target, value: { nested: 'business' }, elt: 'business' };
        ambient.htmx.ajax.mockImplementation(htmxRequest(ok({
          'HX-Trigger': JSON.stringify({ 'surfacerelay:result': { value } }),
        })));
        await expect(call()).resolves.toEqual(value);
      },
    );

    it('returns undefined when the response declares no result', async () => {
      const { ambient, call } = setup();
      ambient.htmx.ajax.mockImplementation(htmxRequest(ok()));

      await expect(call()).resolves.toBeUndefined();
    });

    it.each([
      ['a non-JSON trigger list', 'item-added, refresh'],
      ['an unrelated JSON trigger', JSON.stringify({ refresh: true })],
      ['an array result', JSON.stringify({ 'surfacerelay:result': [1, 2] })],
      ['a string result', JSON.stringify({ 'surfacerelay:result': 'done' })],
      ['a null result', JSON.stringify({ 'surfacerelay:result': null })],
      ['a legacy result', JSON.stringify({ 'surfacerelay:result': { itemId: '7' } })],
      ['an outer target', JSON.stringify({ 'surfacerelay:result': { value: {}, target: '#items' } })],
      ['an array value', JSON.stringify({ 'surfacerelay:result': { value: [] } })],
      ['a null value', JSON.stringify({ 'surfacerelay:result': { value: null } })],
      ['a scalar value', JSON.stringify({ 'surfacerelay:result': { value: 'done' } })],
      ['a JSON array trigger', JSON.stringify([{ 'surfacerelay:result': { itemId: '7' } }])],
    ])('treats %s as no result without failing the completed request', async (_label, header) => {
      const { ambient, call } = setup();
      ambient.htmx.ajax.mockImplementation(htmxRequest(ok({ 'HX-Trigger': header })));

      await expect(call()).resolves.toBeUndefined();
    });

    it('never returns a result for an unsuccessful request', async () => {
      const { ambient, call } = setup();
      ambient.htmx.ajax.mockImplementation(htmxRequest({
        status: 422,
        successful: false,
        responseHeaders: { 'HX-Trigger': JSON.stringify({ 'surfacerelay:result': { value: { itemId: '7' } } }) },
      }));

      await expectAjaxCode(call(), 'htmx_request_failed');
    });
  });

  it('fails closed before ajax when the source cannot observe request events', async () => {
    const ambient = root();
    const runtime = new GlobalHtmxBrowserRuntime(ambient);

    await expectAjaxCode(
      runtime.ajax('post', '/items', { source: element('src-a'), values: {} }),
      'htmx_runtime_unsupported',
    );
    expect(ambient.htmx.ajax).not.toHaveBeenCalled();
  });
});
