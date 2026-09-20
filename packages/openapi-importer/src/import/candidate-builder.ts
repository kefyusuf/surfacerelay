import type { OpenApiImportCandidate } from '../candidate.js';
import {
  blockingDiagnostic,
  type ImportDiagnostic,
} from '../diagnostics.js';
import { MAX_DIAGNOSTICS } from '../limits.js';
import { selectRootPathOperations } from '../operations/operation-selector.js';
import { applySchemaSuggestions } from '../schema/schema-suggestion.js';
import {
  parseOpenApiFamily,
} from '../source/openapi-version.js';
import {
  parseOpenApiSource,
  type ParseOpenApiSourceInput,
} from '../source/parse-source.js';

export interface OpenApiImportReport {
  openapiVersion?: string;
  candidates: OpenApiImportCandidate[];
  diagnostics: ImportDiagnostic[];
  truncatedDiagnostics: boolean;
}

interface SequencedDiagnostic {
  diagnostic: ImportDiagnostic;
  sequence: number;
}

function compareText(left: string, right: string): number {
  if (left < right) {
    return -1;
  }
  if (left > right) {
    return 1;
  }
  return 0;
}

function sortCandidates(
  candidates: readonly OpenApiImportCandidate[],
): OpenApiImportCandidate[] {
  return [...candidates].sort((left, right) => {
    const path = compareText(
      left.source.pathTemplate,
      right.source.pathTemplate,
    );
    if (path !== 0) {
      return path;
    }

    const method = compareText(
      left.source.httpMethod,
      right.source.httpMethod,
    );
    if (method !== 0) {
      return method;
    }

    return compareText(
      left.source.operationPointer,
      right.source.operationPointer,
    );
  });
}

function withSourcePointer(
  diagnostic: ImportDiagnostic,
  sourcePointer: string,
): ImportDiagnostic {
  if (diagnostic.sourcePointer !== undefined) {
    return diagnostic;
  }

  return {
    ...diagnostic,
    sourcePointer,
  };
}

function finalizeDiagnostics(
  diagnostics: readonly ImportDiagnostic[],
): {
  diagnostics: ImportDiagnostic[];
  truncatedDiagnostics: boolean;
} {
  const sequenced: SequencedDiagnostic[] = diagnostics.map(
    (diagnostic, sequence) => ({ diagnostic, sequence }),
  );

  sequenced.sort((left, right) => {
    const pointer = compareText(
      left.diagnostic.sourcePointer ?? '',
      right.diagnostic.sourcePointer ?? '',
    );
    if (pointer !== 0) {
      return pointer;
    }

    const code = compareText(
      left.diagnostic.code,
      right.diagnostic.code,
    );
    if (code !== 0) {
      return code;
    }

    return left.sequence - right.sequence;
  });

  if (sequenced.length < MAX_DIAGNOSTICS) {
    return {
      diagnostics: sequenced.map((entry) => entry.diagnostic),
      truncatedDiagnostics: false,
    };
  }

  const ordinary = sequenced
    .slice(0, MAX_DIAGNOSTICS - 1)
    .map((entry) => entry.diagnostic);

  ordinary.push(
    blockingDiagnostic(
      'diagnostic_limit_reached',
      `Diagnostic report exceeded the ${MAX_DIAGNOSTICS}-entry limit and was truncated.`,
    ),
  );

  return {
    diagnostics: ordinary,
    truncatedDiagnostics: true,
  };
}

function report(
  candidates: readonly OpenApiImportCandidate[],
  diagnostics: readonly ImportDiagnostic[],
  openapiVersion?: string,
): OpenApiImportReport {
  const finalized = finalizeDiagnostics(diagnostics);

  return {
    ...(openapiVersion === undefined ? {} : { openapiVersion }),
    candidates: sortCandidates(candidates),
    diagnostics: finalized.diagnostics,
    truncatedDiagnostics: finalized.truncatedDiagnostics,
  };
}

export function importOpenApi(
  input: ParseOpenApiSourceInput,
): OpenApiImportReport {
  const parsed = parseOpenApiSource(input);
  if (parsed.document === null) {
    return report([], parsed.diagnostics);
  }

  const rawVersion = parsed.document.openapi;
  const openapiVersion =
    typeof rawVersion === 'string'
      ? rawVersion
      : undefined;

  const family = parseOpenApiFamily(parsed.document);
  if (family.family === null) {
    return report(
      [],
      [...parsed.diagnostics, ...family.diagnostics],
      openapiVersion,
    );
  }

  const selected = selectRootPathOperations(
    parsed.document,
    family.family,
  );

  const candidates = selected.candidates.map((candidate) =>
    applySchemaSuggestions(parsed.document as NonNullable<typeof parsed.document>, candidate),
  );

  const diagnostics: ImportDiagnostic[] = [
    ...parsed.diagnostics,
    ...family.diagnostics,
    ...selected.diagnostics,
  ];

  for (const candidate of candidates) {
    diagnostics.push(
      ...candidate.diagnostics.map((diagnostic) =>
        withSourcePointer(
          diagnostic,
          candidate.source.operationPointer,
        ),
      ),
    );
  }

  return report(
    candidates,
    diagnostics,
    openapiVersion,
  );
}
