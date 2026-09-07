import { describe, expect, it, vi } from 'vitest';
import { DriverRegistry } from '../src/driver-registry.js';
import { LivewireBrowserDriver } from '../src/livewire-browser-driver.js';
import { LivewireBindingExecutionError } from '../src/livewire-errors.js';
import type { LivewireBrowserRuntime, LivewireWire } from '../src/livewire-browser-runtime.js';
import { WebMcpRegistrationLifecycle } from '../src/webmcp-registration-lifecycle.js';
import type { WebMcpModelContext, WebMcpTool } from '../src/webmcp-types.js';
import type { ActionDefinition, RuntimeBinding } from '../src/types.js';

function definition(): ActionDefinition {
  return {
    id: 'prep_list.add_item',
    version: 1,
    title: 'Add preparation item',
    description: 'Adds an item to the shared preparation list.',
    inputSchema: {
      type: 'object',
      additionalProperties: false,
      properties: { name: { type: 'string' } },
      required: ['name'],
    },
    outputSchema: {
      type: 'object',
      properties: { itemId: { type: 'string' } },
      required: ['itemId'],
    },
    scope: 'page_scoped',
    effect: 'reversible_write',
    risk: 'low',
    idempotency: 'recommended_key',
    outputSensitivity: 'normal',
    outputContentTrust: 'trusted_application_data',
    contextRequirements: ['browser_session'],
  };
}

function binding(componentId = 'component-old'): RuntimeBinding {
  return {
    bindingId: `binding:${componentId}`,
    action: { id: 'prep_list.add_item', version: 1 },
    driver: 'livewire',
    lifecycle: 'component',
    target: {
      componentId,
      method: 'addItem',
      inputOrder: ['name'],
      requiredCount: 1,
    },
    expiresAt: null,
  };
}

function executionOptions(): { signal: AbortSignal } {
  return { signal: new AbortController().signal };
}

class RecordingModelContext implements WebMcpModelContext {
  readonly tools: WebMcpTool[] = [];

  async registerTool(tool: WebMcpTool): Promise<void> {
    this.tools.push(tool);
  }
}

class MutableLivewireRuntime implements LivewireBrowserRuntime {
  readonly wires = new Map<string, LivewireWire>();
  readonly find = vi.fn((componentId: string) => this.wires.get(componentId));
}

describe('Livewire WebMCP integration', () => {
  it('dispatches a registered WebMCP tool through DriverRegistry to the exact Livewire binding', async () => {
    const runtime = new MutableLivewireRuntime();
    const output = { itemId: 'item-1', name: 'passport' };
    const wire: LivewireWire = {
      $id: 'component-old',
      $call: vi.fn(async () => output),
    };
    runtime.wires.set('component-old', wire);

    const drivers = new DriverRegistry();
    drivers.register('livewire', new LivewireBrowserDriver(runtime));
    const modelContext = new RecordingModelContext();
    const lifecycle = new WebMcpRegistrationLifecycle(modelContext, drivers);
    const exactBinding = binding();

    const lease = await lifecycle.register([{ definition: definition(), binding: exactBinding }]);

    expect(modelContext.tools).toHaveLength(1);
    expect(modelContext.tools[0].name).toBe('prep_list.add_item.v1');
    await expect(modelContext.tools[0].execute({ name: 'passport' }, executionOptions())).resolves.toBe(output);
    expect(runtime.find).toHaveBeenCalledExactlyOnceWith('component-old');
    expect(wire.$call).toHaveBeenCalledExactlyOnceWith('addItem', 'passport');

    lease.dispose();
  });

  it('does not retarget an old binding to a replacement component', async () => {
    const runtime = new MutableLivewireRuntime();
    const replacement: LivewireWire = {
      $id: 'component-new',
      $call: vi.fn(async () => ({ itemId: 'replacement' })),
    };
    runtime.wires.set('component-new', replacement);

    const drivers = new DriverRegistry();
    drivers.register('livewire', new LivewireBrowserDriver(runtime));
    const modelContext = new RecordingModelContext();
    const lifecycle = new WebMcpRegistrationLifecycle(modelContext, drivers);

    await lifecycle.register([{ definition: definition(), binding: binding('component-old') }]);

    try {
      await modelContext.tools[0].execute({ name: 'passport' }, executionOptions());
      throw new Error('expected stale binding failure');
    } catch (error) {
      expect(error).toBeInstanceOf(LivewireBindingExecutionError);
      expect((error as LivewireBindingExecutionError).code).toBe('binding_stale');
    }

    expect(runtime.find).toHaveBeenCalledExactlyOnceWith('component-old');
    expect(replacement.$call).not.toHaveBeenCalled();
  });
});
