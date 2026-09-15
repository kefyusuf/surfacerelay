import {
  parseConformanceRequest,
  type ConformanceResponse,
} from './protocol.js';
import {
  readSingleStdinDocument,
  setFailureExitCode,
  writeDiagnostic,
  writeProtocolJson,
} from './node-stdio.js';
import { observeBindingDriverScenario } from './run-binding-driver-scenario.js';
import { createHtmxConformanceTarget } from './support/htmx-target.js';

async function main(): Promise<void> {
  const raw = await readSingleStdinDocument();
  const request = parseConformanceRequest(JSON.parse(raw));
  if (request.targetId !== 'browser/htmx') {
    throw new Error('Unexpected targetId.');
  }

  const now = new Date(request.now);
  if (Number.isNaN(now.getTime())) {
    throw new Error('Invalid conformance clock.');
  }

  const target = createHtmxConformanceTarget(now);
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

main().catch((error: unknown) => {
  writeDiagnostic(error instanceof Error ? error.message : String(error));
  setFailureExitCode();
});
