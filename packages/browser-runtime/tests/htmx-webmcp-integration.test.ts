import { describe, expect, it, vi } from 'vitest';
import { DriverRegistry } from '../src/driver-registry.js';
import { HtmxBrowserDriver } from '../src/htmx-browser-driver.js';
import { HtmxBindingExecutionError } from '../src/htmx-errors.js';
import type {
  HtmxAjaxContext,
  HtmxAjaxMethod,
  HtmxBrowserRuntime,
  HtmxSourceElement,
} from '../src/htmx-browser-runtime.js';
import { WebMcpRegistrationLifecycle } from '../src/webmcp-registration-lifecycle.js';
import type { WebMcpModelContext, WebMcpTool } from '../src/webmcp-types.js';
import type { ActionDefinition, RuntimeBinding } from '../src/types.js';

function definition(): ActionDefinition {
  return {
    id: 'prep_list.add_item',
    version: 1,
    title: 'Add preparation item',
    description: 'Adds one item through the current page interaction.',
    inputSchema: {
      type: 'object',
      additionalProperties: false,
      properties: { item: { type: 'string' } },
      required: ['item'],
    },
    outputSchema: null,
    scope: 'page_scoped',
    effect: 'reversible_write',
    risk: 'low',
    idempotency: 'none',
    outputSensitivity: 'normal',
    outputContentTrust: 'trusted_application_data',
    contextRequirements: [],
  };
}

function binding(sourceId = 'src-old'): RuntimeBinding {
  return {
    bindingId: `binding:${sourceId}`,
    action: { id: 'prep_list.add_item', version: 1 },
    driver: 'htmx',
    lifecycle: 'page',
    target: {
      sourceId,
      method: 'POST',
      path: '/items',
      inputNames: ['item'],
      requiredInputNames: ['item'],
    },
    expiresAt: null,
  };
}

function source(sourceId: string): HtmxSourceElement {
  return {
    tagName: 'BUTTON',
    parentElement: null,
    classList: { contains: () => false },
    hasAttribute(name) {
      return name === 'data-surfacerelay-htmx-source' || name === 'hx-post';
    },
    getAttribute(name) {
      if (name === 'data-surfacerelay-htmx-source') return sourceId;
      if (name === 'hx-post') return '/items';
      return null;
    },
  };
}

class MutableHtmxRuntime implements HtmxBrowserRuntime {
  readonly sources = new Map<string, HtmxSourceElement>();
  readonly assertSupported = vi.fn();
  readonly findSources = vi.fn((sourceId: string) => {
    const found = this.sources.get(sourceId);
    return found === undefined ? [] : [found];
  });
  readonly currentLocation = vi.fn(() => ({
    href: 'https://example.test/page',
    origin: 'https://example.test',
  }));
  readonly requestClass = vi.fn(() => 'htmx-request');
  readonly ajax = vi.fn(async (
    _method: HtmxAjaxMethod,
    _path: string,
    _context: HtmxAjaxContext,
  ) => undefined);
}

class RecordingModelContext implements WebMcpModelContext {
  readonly tools: WebMcpTool[] = [];

  async registerTool(tool: WebMcpTool): Promise<void> {
    this.tools.push(tool);
  }
}

function executionOptions(): { signal: AbortSignal } {
  return { signal: new AbortController().signal };
}

describe('HTMX WebMCP integration', () => {
  it('dispatches a registered tool through DriverRegistry to the exact HTMX source', async () => {
    const runtime = new MutableHtmxRuntime();
    const exactSource = source('src-old');
    runtime.sources.set('src-old', exactSource);

    const drivers = new DriverRegistry();
    drivers.register('htmx', new HtmxBrowserDriver(runtime));
    const modelContext = new RecordingModelContext();
    const lifecycle = new WebMcpRegistrationLifecycle(modelContext, drivers);

    const lease = await lifecycle.register([{
      definition: definition(),
      binding: binding('src-old'),
    }]);

    expect(modelContext.tools).toHaveLength(1);
    expect(modelContext.tools[0].name).toBe('prep_list.add_item.v1');
    await expect(modelContext.tools[0].execute(
      { item: 'coffee' },
      executionOptions(),
    )).resolves.toBeUndefined();

    expect(runtime.findSources).toHaveBeenCalledExactlyOnceWith('src-old');
    expect(runtime.ajax).toHaveBeenCalledExactlyOnceWith(
      'post',
      '/items',
      { source: exactSource, values: Object.freeze({ item: 'coffee' }) },
    );

    lease.dispose();
  });

  it('does not retarget an old binding to a replacement HTMX source', async () => {
    const runtime = new MutableHtmxRuntime();
    runtime.sources.set('src-new', source('src-new'));

    const drivers = new DriverRegistry();
    drivers.register('htmx', new HtmxBrowserDriver(runtime));
    const modelContext = new RecordingModelContext();
    const lifecycle = new WebMcpRegistrationLifecycle(modelContext, drivers);

    await lifecycle.register([{
      definition: definition(),
      binding: binding('src-old'),
    }]);

    try {
      await modelContext.tools[0].execute(
        { item: 'coffee' },
        executionOptions(),
      );
      throw new Error('expected stale HTMX binding failure');
    } catch (error) {
      expect(error).toBeInstanceOf(HtmxBindingExecutionError);
      expect((error as HtmxBindingExecutionError).code).toBe('binding_stale');
    }

    expect(runtime.findSources).toHaveBeenCalledExactlyOnceWith('src-old');
    expect(runtime.ajax).not.toHaveBeenCalled();
  });
});
