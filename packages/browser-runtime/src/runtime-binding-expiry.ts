export interface BrowserClock {
  now(): Date;
}

export const systemBrowserClock: BrowserClock = {
  now: () => new Date(),
};

export type RuntimeBindingExpiryState = 'active' | 'expired' | 'invalid';

const RFC3339_PATTERN = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(?:\.(\d+))?(Z|([+-])(\d{2}):(\d{2}))$/;

function daysInMonth(year: number, month: number): number {
  if (month === 2) {
    const leap = year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0);
    return leap ? 29 : 28;
  }
  return [4, 6, 9, 11].includes(month) ? 30 : 31;
}

function parseRfc3339Millis(value: string): number | null {
  const match = RFC3339_PATTERN.exec(value);
  if (!match) return null;

  const year = Number(match[1]);
  const month = Number(match[2]);
  const day = Number(match[3]);
  const hour = Number(match[4]);
  const minute = Number(match[5]);
  const second = Number(match[6]);
  const fraction = match[7] ?? '';
  const zone = match[8];
  const offsetSign = match[9];
  const offsetHour = match[10] === undefined ? 0 : Number(match[10]);
  const offsetMinute = match[11] === undefined ? 0 : Number(match[11]);

  if (year === 0 || month < 1 || month > 12) return null;
  if (day < 1 || day > daysInMonth(year, month)) return null;
  if (hour < 0 || hour > 23 || minute < 0 || minute > 59 || second < 0 || second > 59) return null;
  if (zone !== 'Z' && (offsetHour < 0 || offsetHour > 23 || offsetMinute < 0 || offsetMinute > 59)) return null;

  const milliseconds = Number((fraction.slice(0, 3) + '000').slice(0, 3));
  const local = new Date(0);
  local.setUTCFullYear(year, month - 1, day);
  local.setUTCHours(hour, minute, second, milliseconds);

  if (
    local.getUTCFullYear() !== year
    || local.getUTCMonth() !== month - 1
    || local.getUTCDate() !== day
    || local.getUTCHours() !== hour
    || local.getUTCMinutes() !== minute
    || local.getUTCSeconds() !== second
  ) {
    return null;
  }

  let offsetMinutes = 0;
  if (zone !== 'Z') {
    offsetMinutes = offsetHour * 60 + offsetMinute;
    if (offsetSign === '-') offsetMinutes *= -1;
  }

  return local.getTime() - offsetMinutes * 60_000;
}

export function classifyRuntimeBindingExpiry(
  expiresAt: unknown,
  now: Date,
): RuntimeBindingExpiryState {
  if (expiresAt === undefined || expiresAt === null) return 'active';
  if (typeof expiresAt !== 'string') return 'invalid';

  const parsed = parseRfc3339Millis(expiresAt);
  if (parsed === null) return 'invalid';
  return parsed <= now.getTime() ? 'expired' : 'active';
}
