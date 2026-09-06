import { projectAnnotations } from './webmcp-projection.js';
import type { ActionDefinition, RuntimeBinding } from './types.js';
import type { WebMcpTool, WebMcpToolExecuteOptions } from './webmcp-types.js';

const WEBMCP_TOOL_NAME_PATTERN = /^[A-Za-z0-9_.-]{1,128}$/;

export interface BoundActionTool {
  definition: ActionDefinition;
  binding: RuntimeBinding;
}

export type BoundActionExecutor = (
  input: Record<string, unknown>,
  options: WebMcpToolExecuteOptions,
) => Promise<unknown>;

export function projectWebMcpToolName(definition: ActionDefinition): string {
  const name = `${definition.id}.v${definition.version}`;
  if (!WEBMCP_TOOL_NAME_PATTERN.test(name)) {
    throw new Error('Projected WebMCP tool name is invalid.');
  }
  return name;
}

export function projectBoundActionTool(
  candidate: BoundActionTool,
  execute: BoundActionExecutor,
): WebMcpTool {
  if (
    candidate.definition.id !== candidate.binding.action.id
    || candidate.definition.version !== candidate.binding.action.version
  ) {
    throw new Error('Action definition and binding identity must match exactly.');
  }

  return {
    name: projectWebMcpToolName(candidate.definition),
    title: candidate.definition.title,
    description: candidate.definition.description,
    inputSchema: candidate.definition.inputSchema,
    annotations: projectAnnotations(candidate.definition),
    execute,
  };
}
