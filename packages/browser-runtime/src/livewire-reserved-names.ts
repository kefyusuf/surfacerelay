export const LIVEWIRE_RESERVED_METHOD_NAMES = [
  'on',
  'el',
  'id',
  'js',
  'get',
  'set',
  'refs',
  'call',
  'hook',
  'watch',
  'dirty',
  'effect',
  'commit',
  'errors',
  'island',
  'upload',
  'entangle',
  'dispatch',
  'intercept',
  'interceptAction',
  'interceptMessage',
  'interceptRequest',
  'dispatchTo',
  'dispatchSelf',
  'dispatchEl',
  'dispatchRef',
  'removeUpload',
  'cancelUpload',
  'uploadMultiple',
  'then',
  'toJSON',
  '__instance',
] as const;

const RESERVED_METHOD_NAMES = new Set<string>(LIVEWIRE_RESERVED_METHOD_NAMES);

export function isLivewireReservedMethodName(name: string): boolean {
  return RESERVED_METHOD_NAMES.has(name);
}
