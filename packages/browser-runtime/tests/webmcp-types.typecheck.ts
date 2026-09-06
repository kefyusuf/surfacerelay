import type {
  WebMcpModelContext,
  WebMcpRegisterToolOptions,
  WebMcpTool,
} from '../src/webmcp-types.js';

declare const context: WebMcpModelContext;
declare const tool: WebMcpTool;
declare const options: WebMcpRegisterToolOptions;

const registration: Promise<void> = context.registerTool(tool, options);

void registration;
