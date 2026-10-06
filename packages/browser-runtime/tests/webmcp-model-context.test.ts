import { describe, expect, it } from 'vitest';
import { resolveDocumentModelContext } from '../src/webmcp-model-context.js';

describe('resolveDocumentModelContext', () => {
  it('returns the native document.modelContext when it exposes registerTool', () => {
    const modelContext = { registerTool: async () => undefined };

    expect(resolveDocumentModelContext({ modelContext })).toBe(modelContext);
  });

  it('returns null when there is no document', () => {
    expect(resolveDocumentModelContext(undefined)).toBeNull();
    expect(resolveDocumentModelContext(null)).toBeNull();
  });

  it('returns null when WebMCP is not available', () => {
    expect(resolveDocumentModelContext({})).toBeNull();
  });

  it('fails closed for a malformed modelContext', () => {
    expect(resolveDocumentModelContext({ modelContext: null })).toBeNull();
    expect(resolveDocumentModelContext({ modelContext: {} })).toBeNull();
    expect(resolveDocumentModelContext({ modelContext: { registerTool: 'nope' } })).toBeNull();
  });

  it('does not fall back to the pre-2026-05 navigator.modelContext surface', () => {
    const navigatorOnly = { navigator: { modelContext: { registerTool: async () => undefined } } };

    expect(resolveDocumentModelContext(navigatorOnly)).toBeNull();
  });
});
