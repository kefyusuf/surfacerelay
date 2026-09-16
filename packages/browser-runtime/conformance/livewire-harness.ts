import {
  readSingleStdinDocument,
  setFailureExitCode,
  writeDiagnostic,
  writeProtocolJson,
} from './node-stdio.js';
import {
  parseConformanceRequest,
  type ConformanceResponse,
} from './protocol.js';
import { observeBindingDriverScenario } from './run-binding-driver-scenario.js';
import { createLivewireConformanceTarget } from './support/livewire-target.js';

async function main(): Promise<void> {
  const raw = await readSingleStdinDocument();
  const request = parseConformanceRequest(JSON.parse(raw) as unknown);
  if (request.targetId !== 'browser/livewire') {
    throw new Error('Unexpected targetId.');
  }

  const now = new Date(request.now);
  if (Number.isNaN(now.getTime())) {
    throw new Error('Invalid conformance clock.');
  }

  const target = createLivewireConformanceTarget(now);
  const observation = await observeBindingDriverScenario(target, request);
  const response: ConformanceResponse = {
    protocolVersion: '0.1',
    requestId: request.requestId,
    scenarioId: request.scenarioId,
    targetId: request.targetId,
    profile: request.profile,
    observation,
  };

  writeProtocolJson(response);
}

void main().catch((error: unknown) => {
  const message = error instanceof Error
    ? (error.stack ?? error.message)
    : String(error);
  writeDiagnostic(message);
  setFailureExitCode();
});
