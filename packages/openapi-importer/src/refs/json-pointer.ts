import {
  blockingDiagnostic,
  type ImportDiagnostic,
} from '../diagnostics.js';

export interface LocalJsonPointerResult {
  pointer: string | null;
  tokens: string[] | null;
  diagnostics: ImportDiagnostic[];
}

function failure(
  code: 'external_ref_forbidden' | 'anchor_ref_unsupported' | 'invalid_json_pointer',
  message: string,
): LocalJsonPointerResult {
  return {
    pointer: null,
    tokens: null,
    diagnostics: [blockingDiagnostic(code, message)],
  };
}

function decodePointerToken(token: string): string | null {
  if (/~(?:[^01]|$)/.test(token)) {
    return null;
  }

  return token.replace(/~1/g, '/').replace(/~0/g, '~');
}

export function parseLocalJsonPointer(
  reference: string,
): LocalJsonPointerResult {
  if (!reference.startsWith('#')) {
    return failure(
      'external_ref_forbidden',
      'Only same-document fragment references are supported.',
    );
  }

  const encodedFragment = reference.slice(1);
  let pointer: string;

  try {
    pointer = decodeURIComponent(encodedFragment);
  } catch {
    return failure(
      'invalid_json_pointer',
      'Reference fragment contains malformed percent encoding.',
    );
  }

  if (pointer === '') {
    return { pointer: '', tokens: [], diagnostics: [] };
  }

  if (!pointer.startsWith('/')) {
    return failure(
      'anchor_ref_unsupported',
      'Named-anchor references are unsupported; use a JSON Pointer fragment.',
    );
  }

  const tokens: string[] = [];
  for (const encodedToken of pointer.slice(1).split('/')) {
    const token = decodePointerToken(encodedToken);
    if (token === null) {
      return failure(
        'invalid_json_pointer',
        'JSON Pointer contains an invalid ~ escape.',
      );
    }
    tokens.push(token);
  }

  return { pointer, tokens, diagnostics: [] };
}
