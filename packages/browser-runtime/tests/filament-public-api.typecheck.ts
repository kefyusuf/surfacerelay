import {
  FilamentBrowserDriver, FilamentSelectionCoordinator, GlobalFilamentSelectionRuntime,
  type BindingDriver, type BoundActionTool, type FilamentSelectionRuntime,
  type FilamentBrowserDriverOptions, type LivewireBrowserRuntime,
} from '../src/index.js';

declare const tools: readonly BoundActionTool[];
declare const runtime: LivewireBrowserRuntime;
const selectionRuntime: FilamentSelectionRuntime = new GlobalFilamentSelectionRuntime();
const options: FilamentBrowserDriverOptions = { tools, selectionRuntime, coordinator: new FilamentSelectionCoordinator() };
const driver: BindingDriver = new FilamentBrowserDriver(runtime, options);
void driver;
// @ts-expect-error Shared coordinator ownership must be explicit.
new FilamentBrowserDriver(runtime, { tools, selectionRuntime });
// @ts-expect-error Invocation input does not define exposure policy.
new FilamentBrowserDriver(runtime, { selectionBindings: [], selectionRuntime, coordinator: options.coordinator });
