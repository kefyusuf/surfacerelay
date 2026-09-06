export type LivewireBindingExecutionErrorCode =
  | 'binding_stale'
  | 'binding_expired'
  | 'binding_target_invalid'
  | 'binding_input_unmappable'
  | 'livewire_runtime_unavailable'
  | 'livewire_method_unsupported';

export class LivewireBindingExecutionError extends Error {
  constructor(
    public readonly code: LivewireBindingExecutionErrorCode,
    message: string,
  ) {
    super(message);
    this.name = 'LivewireBindingExecutionError';
  }
}
