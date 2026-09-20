/**
 * Package-local version marker for the bounded OpenAPI importer scaffold.
 *
 * Task 1 intentionally exposes no parsing or import behavior yet.
 */
export const OPENAPI_IMPORTER_VERSION = '0.0.0-dev' as const;

export type {
  ParseOpenApiSourceInput,
  ParsedOpenApiSource,
  SourceFormat,
} from './source/parse-source.js';
export { parseOpenApiSource } from './source/parse-source.js';
