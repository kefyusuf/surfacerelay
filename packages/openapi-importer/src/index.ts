/**
 * Package-local version marker for the bounded OpenAPI importer.
 */
export const OPENAPI_IMPORTER_VERSION = '0.0.0-dev' as const;

export type {
  ParseOpenApiSourceInput,
  ParsedOpenApiSource,
  SourceFormat,
} from './source/parse-source.js';
export { parseOpenApiSource } from './source/parse-source.js';

export type { OpenApiImportReport } from './import/candidate-builder.js';
export { importOpenApi } from './import/candidate-builder.js';

export type {
  ActionEffect,
  ActionRisk,
  ActionScope,
  ContextRequirement,
  IdempotencyPolicy,
  ImportResolution,
  OutputContentTrust,
  OutputSchemaResolution,
  OutputSensitivity,
  SchemaResolution,
} from './resolution.js';
export type {
  BatchMaterializationResult,
  MaterializationRequest,
  MaterializationResult,
  MaterializedAction,
} from './materialize/action-definition-materializer.js';
export {
  materializeActionDefinition,
  materializeActionDefinitions,
} from './materialize/action-definition-materializer.js';
