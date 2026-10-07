import { DriverRegistry } from './driver-registry.js';
import {
  projectBoundActionTool,
  type BoundActionTool,
} from './webmcp-tool-projection.js';
import type { WebMcpExecutionResult, WebMcpModelContext, WebMcpTool } from './webmcp-types.js';

export interface WebMcpRegistrationLease {
  dispose(): void;
}

export interface WebMcpRegistrationOptions {
  resultMode?: 'passthrough' | 'envelope';
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
  private readonly resultMode: 'passthrough' | 'envelope';

  constructor(
    private readonly modelContext: WebMcpModelContext,
    private readonly drivers: DriverRegistry,
    options: WebMcpRegistrationOptions = {},
  ) {
    const mode = options.resultMode ?? 'passthrough';
    if (mode !== 'passthrough' && mode !== 'envelope') {
      throw new Error('Unsupported WebMCP result mode.');
    }
    this.resultMode = mode;
  }

  async register(
    candidates: readonly BoundActionTool[],
  ): Promise<WebMcpRegistrationLease> {
    const projected = candidates.map((candidate) => {
      const tool = projectBoundActionTool(candidate, async (input, options) => {
        if (options.signal.aborted) {
          if (this.resultMode === 'envelope') {
            return {
              kind: 'surfacerelay.webmcp.execution.v1', status: 'cancelled', outcome: 'not_dispatched',
              error: { code: 'execution_cancelled', message: 'Execution was cancelled before driver dispatch.' },
            } satisfies WebMcpExecutionResult;
          }
          throw options.signal.reason;
        }

        try {
          const driver = this.drivers.requireDriver(candidate.binding.driver);
          const value = await driver.execute(candidate.binding, input, {
            signal: options.signal,
          });
          if (this.resultMode === 'passthrough') return value;
          return {
            kind: 'surfacerelay.webmcp.execution.v1', status: 'returned',
            output: value === undefined ? { kind: 'undefined' } : { kind: 'value', value },
          } satisfies WebMcpExecutionResult;
        } catch (error) {
          if (this.resultMode === 'passthrough') throw error;
          return {
            kind: 'surfacerelay.webmcp.execution.v1', status: 'execution_failed', outcome: 'unknown',
            error: {
              code: 'execution_failed',
              message: 'Execution failed. The application outcome is unknown. Verify application state before considering a retry.',
            },
          } satisfies WebMcpExecutionResult;
        }
      });

      if (this.resultMode === 'envelope') {
        tool.description += '\nSurfaceRelay execution envelope v1: inspect status and output. returned does not imply application success; inspect output.value for the application result. output.kind undefined means no returned value. execution_failed has unknown application outcome. Do not retry automatically; verify application state first. cancelled/not_dispatched means this callback did not invoke its driver.';
      }

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
