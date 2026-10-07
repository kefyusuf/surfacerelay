import {
  DriverRegistry,
  type BindingDriver,
  WebMcpRegistrationLifecycle,
  type WebMcpRegistrationOptions,
  type WebMcpExecutionResult,
  type WebMcpModelContext,
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

const context: WebMcpModelContext = { async registerTool() {} };
const options: WebMcpRegistrationOptions = { resultMode: 'envelope' };
const lifecycle = new WebMcpRegistrationLifecycle(context, registry, options);
function inspect(result: WebMcpExecutionResult): void {
  if (result.status === 'returned' && result.output.kind === 'value') {
    const value: unknown = result.output.value;
    void value;
  }
}
void inspect;
void lifecycle;
