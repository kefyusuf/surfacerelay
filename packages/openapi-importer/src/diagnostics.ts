export type ImportDiagnosticCode =
  | 'source_too_large'
  | 'invalid_json'
  | 'invalid_yaml'
  | 'yaml_alias_unsupported'
  | 'yaml_tag_unsupported'
  | 'document_limit_exceeded'
  | 'invalid_openapi_document';

export interface ImportDiagnostic {
  code: ImportDiagnosticCode;
  message: string;
  blocking: true;
}

export function blockingDiagnostic(
  code: ImportDiagnosticCode,
  message: string,
): ImportDiagnostic {
  return { code, message, blocking: true };
}
