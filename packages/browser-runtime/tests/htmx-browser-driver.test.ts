import { describe, expect, it, vi } from 'vitest';
import { HtmxBrowserDriver } from '../src/htmx-browser-driver.js';
import { HtmxBindingExecutionError } from '../src/htmx-errors.js';
import type {
  HtmxAjaxContext,
  HtmxAjaxMethod,
  HtmxBrowserRuntime,
  HtmxSourceElement,
} from '../src/htmx-browser-runtime.js';
import type { RuntimeBinding } from '../src/types.js';

const now = new Date('2026-09-12T00:00:00.000Z');

class FakeSource implements HtmxSourceElement {
  readonly classTokens = new Set<string>();
  readonly attrs = new Map<string, string>();
  parentElement: HtmxSourceElement | null = null;

  constructor(
    readonly tagName = 'BUTTON',
    attrs: Record<string, string> = {},
  ) {
    Object.entries(attrs).forEach(([name, value]) => this.attrs.set(name, value));
  }

  readonly classList = {
    contains: (token: string) => this.classTokens.has(token),
  };

  hasAttribute(name: string): boolean {
    return this.attrs.has(name);
  }

  getAttribute(name: string): string | null {
    return this.attrs.get(name) ?? null;
  }
}

class FakeRuntime implements HtmxBrowserRuntime {
  readonly assertSupported = vi.fn();
  readonly sources: HtmxSourceElement[] = [];
  readonly findSources = vi.fn((_sourceId: string) => this.sources);
  readonly currentLocation = vi.fn(() => ({
    href: 'https://example.test/page',
    origin: 'https://example.test',
  }));
  requestClass = vi.fn(() => 'htmx-request');
  readonly ajax = vi.fn(async (
    _method: HtmxAjaxMethod,
    _path: string,
    _context: HtmxAjaxContext,
  ) => undefined);
}

function binding(overrides: Partial<RuntimeBinding> = {}): RuntimeBinding {
  return {
    bindingId: 'binding-htmx-1',
    action: { id: 'prep_list.add_item', version: 1 },
    driver: 'htmx',
    lifecycle: 'page',
    target: {
      sourceId: 'src-1',
      method: 'POST',
      path: '/items',
      inputNames: ['item', 'note'],
      requiredInputNames: ['item'],
    },
    expiresAt: null,
    ...overrides,
  };
}

function source(attrs: Record<string, string> = {}): FakeSource {
  return new FakeSource('BUTTON', {
    'data-surfacerelay-htmx-source': 'src-1',
    'hx-post': '/items',
    ...attrs,
  });
}

function targetBinding(method: string, path = '/items'): RuntimeBinding {
  return binding({
    target: {
      sourceId: 'src-1',
      method,
      path,
      inputNames: ['item'],
      requiredInputNames: ['item'],
    },
  });
}

async function expectCode(promise: Promise<unknown>, code: string): Promise<void> {
  try {
    await promise;
    throw new Error('expected HTMX execution failure');
  } catch (error) {
    expect(error).toBeInstanceOf(HtmxBindingExecutionError);
    expect((error as HtmxBindingExecutionError).code).toBe(code);
  }
}

describe('HtmxBrowserDriver core', () => {
  it('rejects another driver and lifecycle before HTMX runtime access', async () => {
    const runtime = new FakeRuntime();
    const driver = new HtmxBrowserDriver(runtime, { now: () => now });

    await expectCode(
      driver.execute(binding({ driver: 'livewire' }), {}, {}),
      'binding_target_invalid',
    );
    await expectCode(
      driver.execute(binding({ lifecycle: 'component' }), {}, {}),
      'binding_target_invalid',
    );

    expect(runtime.assertSupported).not.toHaveBeenCalled();
    expect(runtime.findSources).not.toHaveBeenCalled();
  });

  it('normalizes malformed HTMX descriptor errors before runtime access', async () => {
    const runtime = new FakeRuntime();
    const driver = new HtmxBrowserDriver(runtime, { now: () => now });

    await expectCode(
      driver.execute(binding({ target: { sourceId: 'src-1' } }), {}, {}),
      'binding_target_invalid',
    );

    expect(runtime.assertSupported).not.toHaveBeenCalled();
  });

  it('rejects malformed and expired binding expiry before source lookup', async () => {
    const runtime = new FakeRuntime();
    const driver = new HtmxBrowserDriver(runtime, { now: () => now });

    await expectCode(
      driver.execute(binding({ expiresAt: 'bad-date' }), { item: 'coffee' }, {}),
      'binding_target_invalid',
    );
    await expectCode(
      driver.execute(
        binding({ expiresAt: '2026-09-12T00:00:00Z' }),
        { item: 'coffee' },
        {},
      ),
      'binding_expired',
    );

    expect(runtime.findSources).not.toHaveBeenCalled();
  });

  it('requires exactly one source instance', async () => {
    const runtime = new FakeRuntime();
    const driver = new HtmxBrowserDriver(runtime, { now: () => now });

    await expectCode(driver.execute(binding(), { item: 'coffee' }, {}), 'binding_stale');

    runtime.sources.push(source(), source());
    await expectCode(driver.execute(binding(), { item: 'coffee' }, {}), 'binding_stale');
    expect(runtime.ajax).not.toHaveBeenCalled();
  });

  it.each([
    ['GET', 'hx-get', 'get'],
    ['GET', 'data-hx-get', 'get'],
    ['POST', 'hx-post', 'post'],
    ['POST', 'data-hx-post', 'post'],
    ['PUT', 'hx-put', 'put'],
    ['PUT', 'data-hx-put', 'put'],
    ['PATCH', 'hx-patch', 'patch'],
    ['PATCH', 'data-hx-patch', 'patch'],
    ['DELETE', 'hx-delete', 'delete'],
    ['DELETE', 'data-hx-delete', 'delete'],
  ] as const)(
    'dispatches %s through exact physical %s',
    async (method, attribute, ajaxMethod) => {
      const runtime = new FakeRuntime();
      const exactSource = source();
      exactSource.attrs.delete('hx-post');
      exactSource.attrs.set(attribute, '/items');
      runtime.sources.push(exactSource);

      const driver = new HtmxBrowserDriver(runtime, { now: () => now });
      await expect(
        driver.execute(targetBinding(method), { item: 'coffee' }, {}),
      ).resolves.toBeUndefined();

      expect(runtime.ajax).toHaveBeenCalledExactlyOnceWith(
        ajaxMethod,
        '/items',
        { source: exactSource, values: Object.freeze({ item: 'coffee' }) },
      );
    },
  );

  it('fails stale when the source has no physical HTMX request declaration', async () => {
    const runtime = new FakeRuntime();
    const exactSource = source();
    exactSource.attrs.delete('hx-post');
    runtime.sources.push(exactSource);

    await expectCode(
      new HtmxBrowserDriver(runtime, { now: () => now }).execute(
        binding(),
        { item: 'coffee' },
        {},
      ),
      'binding_stale',
    );
    expect(runtime.ajax).not.toHaveBeenCalled();
  });

  it('fails stale on duplicate equivalent physical request declarations', async () => {
    const runtime = new FakeRuntime();
    const exactSource = source({ 'data-hx-post': '/items' });
    runtime.sources.push(exactSource);

    await expectCode(
      new HtmxBrowserDriver(runtime, { now: () => now }).execute(
        binding(),
        { item: 'coffee' },
        {},
      ),
      'binding_stale',
    );
    expect(runtime.ajax).not.toHaveBeenCalled();
  });

  it('fails stale on method drift', async () => {
    const runtime = new FakeRuntime();
    const exactSource = source();
    exactSource.attrs.delete('hx-post');
    exactSource.attrs.set('hx-put', '/items');
    runtime.sources.push(exactSource);

    await expectCode(
      new HtmxBrowserDriver(runtime, { now: () => now }).execute(
        binding(),
        { item: 'coffee' },
        {},
      ),
      'binding_stale',
    );
  });

  it('fails stale on trailing-slash raw path drift', async () => {
    const runtime = new FakeRuntime();
    runtime.sources.push(source({ 'hx-post': '/items/' }));

    await expectCode(
      new HtmxBrowserDriver(runtime, { now: () => now }).execute(
        binding(),
        { item: 'coffee' },
        {},
      ),
      'binding_stale',
    );
  });

  it('fails stale on query-order raw path drift', async () => {
    const runtime = new FakeRuntime();
    runtime.sources.push(source({ 'hx-post': '/items?b=2&a=1' }));

    await expectCode(
      new HtmxBrowserDriver(runtime, { now: () => now }).execute(
        targetBinding('POST', '/items?a=1&b=2'),
        { item: 'coffee' },
        {},
      ),
      'binding_stale',
    );
  });

  it('checks same-origin before ajax', async () => {
    const runtime = new FakeRuntime();
    runtime.sources.push(source());
    runtime.currentLocation.mockReturnValue({
      href: 'https://example.test/page',
      origin: 'https://different.test',
    });

    await expectCode(
      new HtmxBrowserDriver(runtime, { now: () => now }).execute(
        binding(),
        { item: 'coffee' },
        {},
      ),
      'binding_target_invalid',
    );
    expect(runtime.ajax).not.toHaveBeenCalled();
  });

  it('returns undefined for a successful HTMX dispatch', async () => {
    const runtime = new FakeRuntime();
    runtime.sources.push(source());

    await expect(
      new HtmxBrowserDriver(runtime, { now: () => now }).execute(
        binding(),
        { item: 'coffee' },
        {},
      ),
    ).resolves.toBeUndefined();
  });

  it('preserves the exact underlying HTMX rejection', async () => {
    const runtime = new FakeRuntime();
    runtime.sources.push(source());
    const original = new Error('network failed');
    runtime.ajax.mockRejectedValueOnce(original);

    await expect(
      new HtmxBrowserDriver(runtime, { now: () => now }).execute(
        binding(),
        { item: 'coffee' },
        {},
      ),
    ).rejects.toBe(original);
  });
});

describe('HtmxBrowserDriver reference-source policy', () => {
  const unsupportedNames = [
    'hx-vals',
    'hx-vars',
    'hx-confirm',
    'hx-prompt',
    'hx-sync',
    'hx-indicator',
    'hx-ext',
  ] as const;

  const unsupportedPhysicalAttributes = unsupportedNames.flatMap((name) => [
    name,
    `data-${name}`,
  ]);

  it.each(unsupportedPhysicalAttributes)(
    'rejects source-local unsupported modifier %s before ajax',
    async (attribute) => {
      const runtime = new FakeRuntime();
      runtime.sources.push(source({ [attribute]: 'value' }));

      await expectCode(
        new HtmxBrowserDriver(runtime, { now: () => now }).execute(
          binding(),
          { item: 'coffee' },
          {},
        ),
        'htmx_source_unsupported',
      );
      expect(runtime.ajax).not.toHaveBeenCalled();
    },
  );

  it('rejects unsupported modifiers found conservatively on ancestors', async () => {
    const runtime = new FakeRuntime();
    const exactSource = source();
    const parent = new FakeSource('DIV', { 'hx-vals': '{"item":"host"}' });
    const grandparent = new FakeSource('MAIN', { 'data-hx-sync': 'this:queue last' });
    parent.parentElement = grandparent;
    exactSource.parentElement = parent;
    runtime.sources.push(exactSource);

    await expectCode(
      new HtmxBrowserDriver(runtime, { now: () => now }).execute(
        binding(),
        { item: 'coffee' },
        {},
      ),
      'htmx_source_unsupported',
    );
    expect(runtime.ajax).not.toHaveBeenCalled();
  });

  it.each(['none', 'item', 'not item'])(
    'rejects restrictive hx-params value %j',
    async (params) => {
      const runtime = new FakeRuntime();
      runtime.sources.push(source({ 'hx-params': params }));

      await expectCode(
        new HtmxBrowserDriver(runtime, { now: () => now }).execute(
          binding(),
          { item: 'coffee' },
          {},
        ),
        'htmx_source_unsupported',
      );
      expect(runtime.ajax).not.toHaveBeenCalled();
    },
  );

  it('rejects restrictive data-hx-params inherited from an ancestor', async () => {
    const runtime = new FakeRuntime();
    const exactSource = source();
    exactSource.parentElement = new FakeSource('DIV', { 'data-hx-params': 'none' });
    runtime.sources.push(exactSource);

    await expectCode(
      new HtmxBrowserDriver(runtime, { now: () => now }).execute(
        binding(),
        { item: 'coffee' },
        {},
      ),
      'htmx_source_unsupported',
    );
  });

  it.each([undefined, '*'])(
    'allows non-restrictive hx-params %j',
    async (params) => {
      const runtime = new FakeRuntime();
      const exactSource = source();
      if (params !== undefined) exactSource.attrs.set('hx-params', params);
      runtime.sources.push(exactSource);

      await expect(
        new HtmxBrowserDriver(runtime, { now: () => now }).execute(
          binding(),
          { item: 'coffee' },
          {},
        ),
      ).resolves.toBeUndefined();
      expect(runtime.ajax).toHaveBeenCalledTimes(1);
    },
  );

  it('allows ordinary host request-state modifiers without promoting them to authority', async () => {
    const runtime = new FakeRuntime();
    const exactSource = source({
      'hx-include': '#csrf',
      'hx-headers': '{"X-View":"table"}',
      'hx-request': '{"timeout":1000}',
      'hx-target': '#result',
      'hx-swap': 'innerHTML',
      'hx-encoding': 'multipart/form-data',
    });
    runtime.sources.push(exactSource);

    await expect(
      new HtmxBrowserDriver(runtime, { now: () => now }).execute(
        binding(),
        { item: 'coffee' },
        {},
      ),
    ).resolves.toBeUndefined();
    expect(runtime.ajax).toHaveBeenCalledExactlyOnceWith(
      'post',
      '/items',
      { source: exactSource, values: Object.freeze({ item: 'coffee' }) },
    );
  });

  it('rejects a FORM source with active browser validation', async () => {
    const runtime = new FakeRuntime();
    runtime.sources.push(new FakeSource('FORM', {
      'data-surfacerelay-htmx-source': 'src-1',
      'hx-post': '/items',
    }));

    await expectCode(
      new HtmxBrowserDriver(runtime, { now: () => now }).execute(
        binding(),
        { item: 'coffee' },
        {},
      ),
      'htmx_source_unsupported',
    );
  });

  it('allows a FORM source only when novalidate is physically present', async () => {
    const runtime = new FakeRuntime();
    const exactSource = new FakeSource('FORM', {
      'data-surfacerelay-htmx-source': 'src-1',
      'hx-post': '/items',
      novalidate: '',
    });
    runtime.sources.push(exactSource);

    await expect(
      new HtmxBrowserDriver(runtime, { now: () => now }).execute(
        binding(),
        { item: 'coffee' },
        {},
      ),
    ).resolves.toBeUndefined();
  });

  it.each(['hx-validate', 'data-hx-validate'])(
    'rejects explicit source validation through %s=true',
    async (attribute) => {
      const runtime = new FakeRuntime();
      runtime.sources.push(source({ [attribute]: 'true' }));

      await expectCode(
        new HtmxBrowserDriver(runtime, { now: () => now }).execute(
          binding(),
          { item: 'coffee' },
          {},
        ),
        'htmx_source_unsupported',
      );
      expect(runtime.ajax).not.toHaveBeenCalled();
    },
  );

  it('fails a busy exact source without entering HTMX ajax', async () => {
    const runtime = new FakeRuntime();
    const exactSource = source();
    exactSource.classTokens.add('htmx-request');
    runtime.sources.push(exactSource);

    await expectCode(
      new HtmxBrowserDriver(runtime, { now: () => now }).execute(
        binding(),
        { item: 'coffee' },
        {},
      ),
      'htmx_source_busy',
    );
    expect(runtime.requestClass).toHaveBeenCalledTimes(1);
    expect(runtime.ajax).not.toHaveBeenCalled();
  });
});
