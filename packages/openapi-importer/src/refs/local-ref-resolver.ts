import {
  blockingDiagnostic,
  type ImportDiagnostic,
} from '../diagnostics.js';
import type { JsonValue } from '../json-value.js';
import {
  MAX_REF_HOPS,
  MAX_UNIQUE_REF_TARGETS,
} from '../limits.js';
import { parseLocalJsonPointer } from './json-pointer.js';

interface SharedRefTargets {
  readonly unique: Set<string>;
}

export class LocalRefTraversalBudget {
  private readonly activePointers: string[];

  private readonly sharedTargets: SharedRefTargets;

  constructor(
    activePointers: readonly string[] = [],
    sharedTargets?: SharedRefTargets,
  ) {
    this.activePointers = [...activePointers];
    this.sharedTargets = sharedTargets ?? { unique: new Set<string>() };
  }

  fork(): LocalRefTraversalBudget {
    return new LocalRefTraversalBudget(
      this.activePointers,
      this.sharedTargets,
    );
  }

  consume(pointer: string): ImportDiagnostic | null {
    if (this.activePointers.includes(pointer)) {
      return blockingDiagnostic(
        'ref_cycle',
        `Reference cycle detected at JSON Pointer "${pointer}".`,
      );
    }

    if (this.activePointers.length >= MAX_REF_HOPS) {
      return blockingDiagnostic(
        'ref_limit_exceeded',
        `Reference traversal exceeds the ${MAX_REF_HOPS}-hop limit.`,
      );
    }

    if (
      !this.sharedTargets.unique.has(pointer) &&
      this.sharedTargets.unique.size >= MAX_UNIQUE_REF_TARGETS
    ) {
      return blockingDiagnostic(
        'ref_limit_exceeded',
        `Reference traversal exceeds the ${MAX_UNIQUE_REF_TARGETS}-unique-target limit.`,
      );
    }

    this.sharedTargets.unique.add(pointer);
    this.activePointers.push(pointer);
    return null;
  }
}

export interface LocalRefResolution {
  value: JsonValue | null;
  pointer: string | null;
  diagnostics: ImportDiagnostic[];
}

function failure(diagnostic: ImportDiagnostic): LocalRefResolution {
  return {
    value: null,
    pointer: null,
    diagnostics: [diagnostic],
  };
}

function invalidPointer(message: string): LocalRefResolution {
  return failure(blockingDiagnostic('invalid_json_pointer', message));
}

function resolvePointerTokens(
  root: JsonValue,
  tokens: readonly string[],
): JsonValue | undefined {
  let current: JsonValue = root;

  for (const token of tokens) {
    if (Array.isArray(current)) {
      if (!/^(?:0|[1-9]\d*)$/.test(token)) {
        return undefined;
      }

      const index = Number(token);
      if (
        !Number.isSafeInteger(index) ||
        index >= current.length ||
        !Object.prototype.hasOwnProperty.call(current, index)
      ) {
        return undefined;
      }

      const next = current[index];
      if (next === undefined) {
        return undefined;
      }

      current = next;
      continue;
    }

    if (current !== null && typeof current === 'object') {
      if (!Object.prototype.hasOwnProperty.call(current, token)) {
        return undefined;
      }

      const next = current[token];
      if (next === undefined) {
        return undefined;
      }

      current = next;
      continue;
    }

    return undefined;
  }

  return current;
}

export function resolveLocalPointer(
  root: JsonValue,
  reference: string,
  budget: LocalRefTraversalBudget,
): LocalRefResolution {
  const parsed = parseLocalJsonPointer(reference);

  if (
    parsed.pointer === null ||
    parsed.tokens === null ||
    parsed.diagnostics.length > 0
  ) {
    return {
      value: null,
      pointer: null,
      diagnostics: parsed.diagnostics,
    };
  }

  const value = resolvePointerTokens(root, parsed.tokens);
  if (value === undefined) {
    return invalidPointer(
      `JSON Pointer target "${parsed.pointer}" does not exist.`,
    );
  }

  const budgetDiagnostic = budget.consume(parsed.pointer);
  if (budgetDiagnostic !== null) {
    return failure(budgetDiagnostic);
  }

  return {
    value,
    pointer: parsed.pointer,
    diagnostics: [],
  };
}
