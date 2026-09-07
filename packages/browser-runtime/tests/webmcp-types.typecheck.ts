import type {
  WebMcpModelContext,
  WebMcpRegisterToolOptions,
  WebMcpTool,
} from '../src/webmcp-types.js';

declare const context: WebMcpModelContext;
declare const tool: WebMcpTool;
declare const options: WebMcpRegisterToolOptions;

const registration: Promise<void> = context.registerTool(tool, options);
const signal = new AbortController().signal;

void tool.execute({}, { signal });
// @ts-expect-error WebMCP execution requires AbortSignal.
void tool.execute({}, {});

void registration;
