import { describe, expect, it } from 'vitest';
import {
  classifyRuntimeBindingExpiry,
  systemBrowserClock,
} from '../src/runtime-binding-expiry.js';

const now = new Date('2026-09-12T00:00:00.000Z');

describe('classifyRuntimeBindingExpiry', () => {
  it.each([null, undefined])('treats %j as active without expiry', (expiresAt) => {
    expect(classifyRuntimeBindingExpiry(expiresAt, now)).toBe('active');
  });

  it.each([
    '2026-09-12T00:00:00Z',
    '2026-09-11T23:59:59.999Z',
    '2026-09-12T03:00:00+03:00',
  ])('treats equality/past %s as expired', (expiresAt) => {
    expect(classifyRuntimeBindingExpiry(expiresAt, now)).toBe('expired');
  });

  it.each([
    '2026-09-12T00:00:00.001Z',
    '2026-09-12T01:30:00+01:00',
    '2026-09-12T00:00:00.123456789-00:30',
  ])('accepts future RFC3339 value %s', (expiresAt) => {
    expect(classifyRuntimeBindingExpiry(expiresAt, now)).toBe('active');
  });

  it.each([
    123,
    {},
    'not-a-date',
    '0000-01-01T00:00:00Z',
    '2026-02-30T00:00:00Z',
    '2026-09-12T24:00:00Z',
    '2026-09-12T00:60:00Z',
    '2026-09-12T00:00:60Z',
    '2026-09-12T00:00:00+24:00',
    '2026-09-12T00:00:00+03:60',
  ])('rejects malformed/non-RFC3339 runtime expiry %#', (expiresAt) => {
    expect(classifyRuntimeBindingExpiry(expiresAt, now)).toBe('invalid');
  });

  it('exposes the real browser clock as a narrow now() port', () => {
    expect(systemBrowserClock.now()).toBeInstanceOf(Date);
  });
});
