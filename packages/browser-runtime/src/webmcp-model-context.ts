import type { WebMcpModelContext } from './webmcp-types.js';

export function resolveDocumentModelContext(
  doc: unknown = globalThis.document,
): WebMcpModelContext | null {
  if (typeof doc !== 'object' || doc === null || !('modelContext' in doc)) {
    return null;
  }

  const modelContext: unknown = doc.modelContext;
  if (
    typeof modelContext !== 'object'
    || modelContext === null
    || typeof (modelContext as { registerTool?: unknown }).registerTool !== 'function'
  ) {
    return null;
  }

  return modelContext as WebMcpModelContext;
}
