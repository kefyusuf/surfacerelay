import type { ImportDiagnostic } from './diagnostics.js';
import type { JsonObject } from './json-value.js';
import type { OpenApiFamily } from './source/openapi-version.js';

export interface OpenApiSourceProvenance {
  openapiVersion: string;
  family: OpenApiFamily;
  operationPointer: string;
  operationId?: string;
  httpMethod: string;
  pathTemplate: string;
}

export interface ParameterEvidence {
  name: string;
  in: string;
  required: boolean;
  schema?: JsonObject;
  sourcePointer: string;
}

export interface MediaTypeEvidence {
  mediaType: string;
  schema?: JsonObject;
  sourcePointer: string;
}

export interface RequestBodyEvidence {
  required: boolean;
  content: MediaTypeEvidence[];
  sourcePointer: string;
}

export interface ResponseEvidence {
  statusCode: string;
  content: MediaTypeEvidence[];
  sourcePointer: string;
}

export interface SecuritySchemeEvidence {
  scheme: string;
  scopes: string[];
}

export interface SecurityRequirementEvidence {
  schemes: SecuritySchemeEvidence[];
  anonymous: boolean;
}

export interface SecurityEvidence {
  source: 'none' | 'inherited' | 'operation_override';
  requirements: SecurityRequirementEvidence[];
  anonymousAlternative: boolean;
  inheritedSecurityRemoved: boolean;
}

export interface OpenApiImportCandidate {
  source: OpenApiSourceProvenance;
  sourceSummary?: string;
  sourceDescription?: string;
  parameters: ParameterEvidence[];
  requestBodies: RequestBodyEvidence[];
  responses: ResponseEvidence[];
  security: SecurityEvidence;
  suggestedInputSchema?: JsonObject;
  suggestedOutputSchema?: JsonObject | null;
  unresolvedFields: string[];
  diagnostics: ImportDiagnostic[];
}

export interface OperationSelectionResult {
  candidates: OpenApiImportCandidate[];
  diagnostics: ImportDiagnostic[];
}
