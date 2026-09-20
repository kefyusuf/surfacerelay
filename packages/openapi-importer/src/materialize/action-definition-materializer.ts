import type {
  OpenApiImportCandidate,
  OpenApiSourceProvenance,
} from '../candidate.js';
import type { ImportDiagnostic } from '../diagnostics.js';
import type { JsonObject } from '../json-value.js';
import type { ImportResolution } from '../resolution.js';

export interface MaterializedAction {
  actionDefinition: JsonObject;
  sourceProvenance: OpenApiSourceProvenance;
}

export interface MaterializationResult {
  materialized: MaterializedAction | null;
  diagnostics: ImportDiagnostic[];
}

export interface MaterializationRequest {
  candidate: OpenApiImportCandidate;
  resolution: ImportResolution;
}

export interface BatchMaterializationResult {
  materialized: MaterializedAction[];
  diagnostics: ImportDiagnostic[];
}

export function materializeActionDefinition(
  _candidate: OpenApiImportCandidate,
  _resolution: ImportResolution,
): MaterializationResult {
  throw new Error('Task 7 materializer not implemented');
}

export function materializeActionDefinitions(
  _requests: readonly MaterializationRequest[],
): BatchMaterializationResult {
  throw new Error('Task 7 batch materializer not implemented');
}
