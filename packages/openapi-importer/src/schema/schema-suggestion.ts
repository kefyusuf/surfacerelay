import type { OpenApiImportCandidate } from '../candidate.js';
import {
  blockingDiagnostic,
  type ImportDiagnostic,
} from '../diagnostics.js';
import type { JsonObject } from '../json-value.js';
import { copySupportedSchema } from './schema-subset.js';

function withoutField(
  fields: readonly string[],
  field: string,
): string[] {
  return fields.filter((value) => value !== field);
}

function ambiguous(code: 'ambiguous_input_mapping' | 'ambiguous_success_output', message: string): ImportDiagnostic {
  return blockingDiagnostic(code, message);
}

function emptyInputSchema(): JsonObject {
  const properties = Object.create(null) as JsonObject;
  const schema = Object.create(null) as JsonObject;
  schema.type = 'object';
  schema.properties = properties;
  schema.additionalProperties = false;
  return schema;
}

function suggestInput(
  document: JsonObject,
  candidate: OpenApiImportCandidate,
): {
  schema?: JsonObject;
  resolved: boolean;
  diagnostics: ImportDiagnostic[];
} {
  if (candidate.parameters.length > 0) {
    return {
      resolved: false,
      diagnostics: [
        ambiguous(
          'ambiguous_input_mapping',
          'HTTP parameters require explicit Action input mapping.',
        ),
      ],
    };
  }

  if (candidate.requestBodies.length === 0) {
    return { schema: emptyInputSchema(), resolved: true, diagnostics: [] };
  }

  if (candidate.requestBodies.length !== 1) {
    return {
      resolved: false,
      diagnostics: [
        ambiguous(
          'ambiguous_input_mapping',
          'Multiple request-body choices require explicit input mapping.',
        ),
      ],
    };
  }

  const body = candidate.requestBodies[0];
  if (
    body === undefined ||
    body.content.length !== 1 ||
    body.content[0]?.mediaType !== 'application/json' ||
    body.content[0]?.schema === undefined
  ) {
    return {
      resolved: false,
      diagnostics: [
        ambiguous(
          'ambiguous_input_mapping',
          'Request body must contain exactly one supported application/json schema.',
        ),
      ],
    };
  }

  const copied = copySupportedSchema(document, body.content[0].schema);
  return copied.schema === null
    ? { resolved: false, diagnostics: copied.diagnostics }
    : { schema: copied.schema, resolved: true, diagnostics: [] };
}

function suggestOutput(
  document: JsonObject,
  candidate: OpenApiImportCandidate,
): {
  schema?: JsonObject | null;
  resolved: boolean;
  diagnostics: ImportDiagnostic[];
} {
  if (
    candidate.responses.some((response) => /^2XX$/i.test(response.statusCode))
  ) {
    return {
      resolved: false,
      diagnostics: [
        ambiguous(
          'ambiguous_success_output',
          'Wildcard successful responses require explicit output selection.',
        ),
      ],
    };
  }

  const successes = candidate.responses.filter((response) =>
    /^2\d\d$/.test(response.statusCode),
  );

  if (successes.length !== 1) {
    return {
      resolved: false,
      diagnostics: [
        ambiguous(
          'ambiguous_success_output',
          'Exactly one explicit 2xx response is required for an automatic output suggestion.',
        ),
      ],
    };
  }

  const success = successes[0];
  if (success === undefined) {
    return {
      resolved: false,
      diagnostics: [
        ambiguous(
          'ambiguous_success_output',
          'Successful response selection failed.',
        ),
      ],
    };
  }

  if (success.content.length === 0) {
    return { schema: null, resolved: true, diagnostics: [] };
  }

  if (
    success.content.length !== 1 ||
    success.content[0]?.mediaType !== 'application/json' ||
    success.content[0]?.schema === undefined
  ) {
    return {
      resolved: false,
      diagnostics: [
        ambiguous(
          'ambiguous_success_output',
          'Successful response must contain exactly one supported application/json schema.',
        ),
      ],
    };
  }

  const copied = copySupportedSchema(document, success.content[0].schema);
  return copied.schema === null
    ? { resolved: false, diagnostics: copied.diagnostics }
    : { schema: copied.schema, resolved: true, diagnostics: [] };
}

export function applySchemaSuggestions(
  document: JsonObject,
  candidate: OpenApiImportCandidate,
): OpenApiImportCandidate {
  const input = suggestInput(document, candidate);
  const output = suggestOutput(document, candidate);
  const result: OpenApiImportCandidate = {
    ...candidate,
    unresolvedFields: [...candidate.unresolvedFields],
    diagnostics: [
      ...candidate.diagnostics,
      ...input.diagnostics,
      ...output.diagnostics,
    ],
  };

  if (input.resolved && input.schema !== undefined) {
    result.suggestedInputSchema = input.schema;
    result.unresolvedFields = withoutField(
      result.unresolvedFields,
      'inputSchema',
    );
  }

  if (output.resolved) {
    result.suggestedOutputSchema = output.schema ?? null;
    result.unresolvedFields = withoutField(
      result.unresolvedFields,
      'outputSchema',
    );
  }

  return result;
}
