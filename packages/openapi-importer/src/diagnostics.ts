export type ImportDiagnosticCode =
  | 'source_too_large'
  | 'invalid_json'
  | 'invalid_yaml'
  | 'yaml_alias_unsupported'
  | 'yaml_tag_unsupported'
  | 'document_limit_exceeded'
  | 'invalid_openapi_document'
  | 'unsupported_openapi_version'
  | 'external_ref_forbidden'
  | 'anchor_ref_unsupported'
  | 'invalid_json_pointer'
  | 'ref_cycle'
  | 'ref_limit_exceeded'
  | 'path_item_ref_sibling_ambiguous'
  | 'additional_operation_unsupported'
  | 'operation_limit_exceeded'
  | 'source_text_too_large'
  | 'unsupported_schema_dialect'
  | 'schema_keyword_unsupported'
  | 'schema_ref_sibling_unsupported'
  | 'schema_limit_exceeded'
  | 'ambiguous_input_mapping'
  | 'ambiguous_success_output'
  | 'diagnostic_limit_reached'
  | 'missing_surface_semantics'
  | 'invalid_action_identity'
  | 'duplicate_action_identity';

export interface ImportDiagnostic {
  code: ImportDiagnosticCode;
  message: string;
  blocking: boolean;
  sourcePointer?: string;
}

export function blockingDiagnostic(
  code: ImportDiagnosticCode,
  message: string,
): ImportDiagnostic {
  return { code, message, blocking: true };
}

export function warningDiagnostic(
  code: ImportDiagnosticCode,
  message: string,
): ImportDiagnostic {
  return { code, message, blocking: false };
}
