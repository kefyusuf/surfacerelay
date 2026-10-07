import type { WebMcpAnnotations } from './webmcp-projection.js';

/** Projection execution status, distinct from a server Action Result. */
export type WebMcpExecutionResult =
  | {
    kind: 'surfacerelay.webmcp.execution.v1';
    status: 'returned';
    output: { kind: 'undefined' } | { kind: 'value'; value: unknown };
  }
  | {
    kind: 'surfacerelay.webmcp.execution.v1';
    status: 'execution_failed';
    outcome: 'unknown';
    error: {
      code: 'execution_failed';
      message: 'Execution failed. The application outcome is unknown. Verify application state before considering a retry.';
    };
  }
  | {
    kind: 'surfacerelay.webmcp.execution.v1';
    status: 'cancelled';
    outcome: 'not_dispatched';
    error: {
      code: 'execution_cancelled';
      message: 'Execution was cancelled before driver dispatch.';
    };
  };

export interface WebMcpToolExecuteOptions {
  signal: AbortSignal;
}

export interface WebMcpTool {
  name: string;
  title: string;
  description: string;
  inputSchema: Record<string, unknown>;
  annotations: WebMcpAnnotations;
  execute(
    input: Record<string, unknown>,
    options: WebMcpToolExecuteOptions,
  ): Promise<unknown>;
}

export interface WebMcpRegisterToolOptions {
  signal: AbortSignal;
}

export interface WebMcpModelContext {
  registerTool(
    tool: WebMcpTool,
    options: WebMcpRegisterToolOptions,
  ): Promise<void>;
}
