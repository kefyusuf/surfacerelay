export interface ConformanceRequest {
  protocolVersion: '0.1';
  requestId: string;
  scenarioId: string;
  targetId: string;
  profile: 'runtime-binding/driver';
  now: string;
}

export interface ConformanceObservation {
  termination: 'returned' | 'threw';
  errorCode?: string;
  frameworkDispatchCount: number;
  replacementDispatchCount?: number;
}

export interface ConformanceResponse {
  protocolVersion: '0.1';
  requestId: string;
  scenarioId: string;
  targetId: string;
  profile: 'runtime-binding/driver';
  observation: ConformanceObservation;
}

const REQUEST_KEYS = [
  'now',
  'profile',
  'protocolVersion',
  'requestId',
  'scenarioId',
  'targetId',
] as const;

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function requireNonEmptyString(
  value: Record<string, unknown>,
  key: 'requestId' | 'scenarioId' | 'targetId' | 'now',
): string {
  const candidate = value[key];
  if (typeof candidate !== 'string' || candidate.length === 0) {
    throw new Error(`Conformance request ${key} must be a non-empty string.`);
  }
  return candidate;
}

export function parseConformanceRequest(value: unknown): ConformanceRequest {
  if (!isRecord(value)) {
    throw new Error('Conformance request must be a JSON object.');
  }

  const keys = Object.keys(value).sort();
  if (
    keys.length !== REQUEST_KEYS.length
    || keys.some((key, index) => key !== REQUEST_KEYS[index])
  ) {
    throw new Error(
      'Conformance request must contain exactly protocolVersion, requestId, scenarioId, targetId, profile, and now.',
    );
  }

  if (value.protocolVersion !== '0.1') {
    throw new Error('Unsupported conformance protocolVersion.');
  }
  if (value.profile !== 'runtime-binding/driver') {
    throw new Error('Unsupported conformance profile.');
  }

  return {
    protocolVersion: '0.1',
    requestId: requireNonEmptyString(value, 'requestId'),
    scenarioId: requireNonEmptyString(value, 'scenarioId'),
    targetId: requireNonEmptyString(value, 'targetId'),
    profile: 'runtime-binding/driver',
    now: requireNonEmptyString(value, 'now'),
  };
}
