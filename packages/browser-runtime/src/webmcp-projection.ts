import type { ActionDefinition } from './types.js';

export interface WebMcpAnnotations {
  readOnlyHint?: boolean;
  untrustedContentHint?: boolean;
  consequentialHint?: boolean;
}

export function projectAnnotations(action: ActionDefinition): WebMcpAnnotations {
  return {
    readOnlyHint: action.effect === 'read',
    untrustedContentHint: action.outputTrust === 'contains_untrusted_content',
    consequentialHint: action.risk === 'consequential',
  };
}
