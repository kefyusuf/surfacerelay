import {
  DriverRegistry,
  type BindingDriver,
} from '@surfacerelay/browser-runtime';

const registry = new DriverRegistry();
const driver: BindingDriver = {
  async execute() {
    return undefined;
  },
};

registry.register('fixture', driver);
const resolved: BindingDriver = registry.requireDriver('fixture');

void resolved;
