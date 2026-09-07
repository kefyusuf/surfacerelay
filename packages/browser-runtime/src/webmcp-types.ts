import type { WebMcpAnnotations } from './webmcp-projection.js';

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
