import { describe, expect, it } from 'vitest';
import {
  parseConformanceRequest,
  type ConformanceRequest,
} from '../conformance/protocol.js';
import { observeBindingDriverScenario } from '../conformance/run-binding-driver-scenario.js';
import { createLivewireConformanceTarget } from '../conformance/support/livewire-target.js';

function requestFor(scenarioId: string): ConformanceRequest {
  return {
    protocolVersion: '0.1',
    requestId: `browser/livewire::${scenarioId}`,
    scenarioId,
    targetId: 'browser/livewire',
    profile: 'runtime-binding/driver',
    now: '2026-09-14T00:00:00.000Z',
  };
}

describe('conformance process protocol', () => {
  it('accepts the exact v1 request envelope', () => {
    const request = requestFor('BIND-EXACT-TARGET-EXECUTES');

    expect(parseConformanceRequest(request)).toEqual(request);
  });

  it('rejects expected-result fields at the harness boundary', () => {
    const request = requestFor('BIND-EXACT-TARGET-EXECUTES');

    expect(() => parseConformanceRequest({
      ...request,
      expectation: {
        termination: 'returned',
        frameworkDispatchCount: 1,
      },
    })).toThrow(/exactly/);
  });

  it('rejects wrong protocol/profile values and missing required fields', () => {
    const request = requestFor('BIND-EXACT-TARGET-EXECUTES');

    expect(() => parseConformanceRequest({ ...request, protocolVersion: '9.9' }))
      .toThrow(/protocolVersion/);
    expect(() => parseConformanceRequest({ ...request, profile: 'runtime-binding/other' }))
      .toThrow(/profile/);
    const { now: _now, ...withoutNow } = request;
    expect(() => parseConformanceRequest(withoutNow)).toThrow(/exactly/);
  });
});

describe('binding driver conformance observation', () => {
  it('observes one exact successful dispatch without producing a verdict', async () => {
    const target = createLivewireConformanceTarget();
    const observation = await observeBindingDriverScenario(
      target,
      requestFor('BIND-EXACT-TARGET-EXECUTES'),
    );

    expect(observation).toEqual({
      termination: 'returned',
      frameworkDispatchCount: 1,
      replacementDispatchCount: 0,
    });
    expect(observation).not.toHaveProperty('passed');
    expect(observation).not.toHaveProperty('conformant');
  });

  it('observes expiry as a raw throw with zero dispatches', async () => {
    const target = createLivewireConformanceTarget();
    const observation = await observeBindingDriverScenario(
      target,
      requestFor('BIND-EXPIRED-NOT-EXECUTABLE'),
    );

    expect(observation).toEqual({
      termination: 'threw',
      errorCode: 'binding_expired',
      frameworkDispatchCount: 0,
      replacementDispatchCount: 0,
    });
  });

  it('observes a stale component as a raw throw with zero dispatches', async () => {
    const target = createLivewireConformanceTarget();
    const observation = await observeBindingDriverScenario(
      target,
      requestFor('BIND-COMPONENT-STALE'),
    );

    expect(observation).toEqual({
      termination: 'threw',
      errorCode: 'binding_stale',
      frameworkDispatchCount: 0,
      replacementDispatchCount: 0,
    });
  });

  it('observes no-retarget failure without dispatching the replacement', async () => {
    const target = createLivewireConformanceTarget();
    const observation = await observeBindingDriverScenario(
      target,
      requestFor('BIND-NO-SILENT-RETARGET'),
    );

    expect(observation).toEqual({
      termination: 'threw',
      errorCode: 'binding_stale',
      frameworkDispatchCount: 0,
      replacementDispatchCount: 0,
    });
  });
});
