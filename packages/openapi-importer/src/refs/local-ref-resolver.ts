import type { ImportDiagnostic } from '../diagnostics.js';
import type { JsonValue } from '../json-value.js';

export class LocalRefTraversalBudget {
  fork(): LocalRefTraversalBudget {
    throw new Error('Task 3 ref budget not implemented');
  }
}

export interface LocalRefResolution {
  value: JsonValue | null;
  pointer: string | null;
  diagnostics: ImportDiagnostic[];
}

export function resolveLocalPointer(
  _root: JsonValue,
  _reference: string,
  _budget: LocalRefTraversalBudget,
): LocalRefResolution {
  throw new Error('Task 3 local ref resolver not implemented');
}
