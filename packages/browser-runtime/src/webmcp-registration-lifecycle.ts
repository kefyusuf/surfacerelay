import { DriverRegistry } from './driver-registry.js';
import {
  projectBoundActionTool,
  type BoundActionTool,
} from './webmcp-tool-projection.js';
import type { WebMcpModelContext, WebMcpTool } from './webmcp-types.js';

export interface WebMcpRegistrationLease {
  dispose(): void;
}

class RegistrationLease implements WebMcpRegistrationLease {
  #disposed = false;

  constructor(private readonly controller: AbortController) {}

  dispose(): void {
    if (this.#disposed) return;
    this.#disposed = true;
    this.controller.abort();
  }
}

function compareToolNames(left: WebMcpTool, right: WebMcpTool): number {
  if (left.name < right.name) return -1;
  if (left.name > right.name) return 1;
  return 0;
}

export class WebMcpRegistrationLifecycle {
  constructor(
    private readonly modelContext: WebMcpModelContext,
    private readonly drivers: DriverRegistry,
  ) {}

  async register(
    candidates: readonly BoundActionTool[],
  ): Promise<WebMcpRegistrationLease> {
    const projected = candidates.map((candidate) => {
      const tool = projectBoundActionTool(candidate, async (input, options) => {
        const driver = this.drivers.requireDriver(candidate.binding.driver);
        return driver.execute(candidate.binding, input, {
          signal: options.signal,
        });
      });

      this.drivers.requireDriver(candidate.binding.driver);

      return tool;
    });

    const names = new Set<string>();
    for (const tool of projected) {
      if (names.has(tool.name)) {
        throw new Error('Duplicate WebMCP tool identity in registration snapshot.');
      }
      names.add(tool.name);
    }

    projected.sort(compareToolNames);

    const controller = new AbortController();

    try {
      for (const tool of projected) {
        await this.modelContext.registerTool(tool, {
          signal: controller.signal,
        });
      }
    } catch (error) {
      controller.abort();
      throw error;
    }

    return new RegistrationLease(controller);
  }
}
