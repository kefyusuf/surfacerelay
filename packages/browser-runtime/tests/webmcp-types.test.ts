import { describe, expect, it } from 'vitest';
import type {
  WebMcpModelContext,
  WebMcpRegisterToolOptions,
  WebMcpTool,
} from '../src/webmcp-types.js';

describe('WebMCP compatibility boundary', () => {
  it('defines a narrow async registerTool compatibility port', async () => {
    const calls: Array<{ tool: WebMcpTool; options: WebMcpRegisterToolOptions }> = [];
    const context: WebMcpModelContext = {
      async registerTool(tool, options) {
        calls.push({ tool, options });
      },
    };

    const controller = new AbortController();
    const tool: WebMcpTool = {
      name: 'orders.get.v1',
      title: 'Get order',
      description: 'Gets an order',
      inputSchema: {},
      annotations: {
        readOnlyHint: true,
        untrustedContentHint: false,
        consequentialHint: false,
      },
      async execute() {
        return null;
      },
    };

    await context.registerTool(tool, { signal: controller.signal });

    expect(calls).toEqual([{ tool, options: { signal: controller.signal } }]);
  });
});
