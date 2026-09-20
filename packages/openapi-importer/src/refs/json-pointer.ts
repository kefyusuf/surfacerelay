import type { ImportDiagnostic } from '../diagnostics.js';

export interface LocalJsonPointerResult {
  pointer: string | null;
  tokens: string[] | null;
  diagnostics: ImportDiagnostic[];
}

export function parseLocalJsonPointer(
  _reference: string,
): LocalJsonPointerResult {
  throw new Error('Task 3 JSON Pointer parser not implemented');
}
