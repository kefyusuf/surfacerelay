import { DriverRegistry } from '@surfacerelay/browser-runtime';

const registry = new DriverRegistry();
const driver = {
  async execute() {
    return undefined;
  },
};

registry.register('fixture', driver);

if (registry.requireDriver('fixture') !== driver) {
  throw new Error('DriverRegistry did not resolve the exact registered driver.');
}

let rejected = false;
try {
  registry.requireDriver('unsupported');
} catch (error) {
  rejected = error instanceof Error && error.message === 'Unsupported binding driver.';
}

if (!rejected) {
  throw new Error('DriverRegistry did not fail closed for an unsupported driver.');
}

console.log('SurfaceRelay browser-runtime clean-consumer smoke: PASS');
