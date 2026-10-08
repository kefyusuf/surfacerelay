import type { BindingDriver, DriverExecutionContext, RuntimeBinding } from './types.js';
import type { LivewireBrowserRuntime } from './livewire-browser-runtime.js';
import { systemBrowserClock, type BrowserClock } from './runtime-binding-expiry.js';
import { executeLivewireBinding } from './livewire-execution.js';

export type { BrowserClock } from './runtime-binding-expiry.js';

export class LivewireBrowserDriver implements BindingDriver {
  constructor(
    private readonly livewire: LivewireBrowserRuntime,
    private readonly clock: BrowserClock = systemBrowserClock,
  ) {}

  execute(
    binding: RuntimeBinding,
    input: Record<string, unknown>,
    context: DriverExecutionContext,
  ): Promise<unknown> {
    return executeLivewireBinding(this.livewire, this.clock, binding, input, context);
  }
}
