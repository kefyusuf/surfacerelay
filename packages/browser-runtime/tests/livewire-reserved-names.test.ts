import { describe, expect, it } from 'vitest';
import {
  LIVEWIRE_RESERVED_METHOD_NAMES,
  isLivewireReservedMethodName,
} from '../src/livewire-reserved-names.js';

const expected = [
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

describe('Livewire reserved method names', () => {
  it('pins the reviewed Livewire 4.4 public proxy collision set exactly', () => {
    expect(LIVEWIRE_RESERVED_METHOD_NAMES).toEqual(expected);
  });

  it('checks names exactly without trimming, lowercasing, or aliases', () => {
    expect(isLivewireReservedMethodName('set')).toBe(true);
    expect(isLivewireReservedMethodName('Set')).toBe(false);
    expect(isLivewireReservedMethodName(' set ')).toBe(false);
    expect(isLivewireReservedMethodName('$set')).toBe(false);
    expect(isLivewireReservedMethodName('save')).toBe(false);
  });
});
