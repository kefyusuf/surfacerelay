export type HtmxBindingExecutionErrorCode =
  | 'binding_stale'
  | 'binding_expired'
  | 'binding_target_invalid'
  | 'binding_input_unmappable'
  | 'htmx_runtime_unavailable'
  | 'htmx_runtime_unsupported'
  | 'htmx_source_unsupported'
  | 'htmx_source_busy';

export class HtmxBindingExecutionError extends Error {
  constructor(
    public readonly code: HtmxBindingExecutionErrorCode,
    message: string,
  ) {
    super(message);
    this.name = 'HtmxBindingExecutionError';
  }
}
