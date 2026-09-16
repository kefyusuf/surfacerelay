import type { BindingDriverConformanceTarget } from './support/binding-driver-target.js';
import type {
  ConformanceObservation,
  ConformanceRequest,
} from './protocol.js';

function rawErrorCode(error: unknown): string | undefined {
  if (typeof error !== 'object' || error === null || !('code' in error)) {
    return undefined;
  }

  const code = (error as { code?: unknown }).code;
  return typeof code === 'string' ? code : undefined;
}

function expiredBinding(
  target: BindingDriverConformanceTarget,
  nowValue: string,
): BindingDriverConformanceTarget['binding'] {
  const now = new Date(nowValue);
  if (Number.isNaN(now.getTime())) {
    throw new Error('Invalid conformance clock.');
  }

  return {
    ...target.binding,
    expiresAt: new Date(now.getTime() - 1).toISOString(),
  };
}

export async function observeBindingDriverScenario(
  target: BindingDriverConformanceTarget,
  request: ConformanceRequest,
): Promise<ConformanceObservation> {
  let binding = target.binding;

  switch (request.scenarioId) {
    case 'BIND-EXACT-TARGET-EXECUTES':
      break;
    case 'BIND-EXPIRED-NOT-EXECUTABLE':
      binding = expiredBinding(target, request.now);
      break;
    case 'BIND-COMPONENT-STALE':
      target.makeTargetStale();
      break;
    case 'BIND-NO-SILENT-RETARGET':
      target.replaceTargetWithEquivalentIdentity();
      break;
    default:
      throw new Error(`Unsupported runtime-binding/driver scenario: ${request.scenarioId}`);
  }

  let termination: ConformanceObservation['termination'] = 'returned';
  let errorCode: string | undefined;

  try {
    await target.driver.execute(binding, target.validInput, {});
  } catch (error) {
    termination = 'threw';
    errorCode = rawErrorCode(error);
  }

  return {
    termination,
    ...(errorCode === undefined ? {} : { errorCode }),
    frameworkDispatchCount: target.frameworkDispatchCount(),
    replacementDispatchCount: target.replacementDispatchCount(),
  };
}
