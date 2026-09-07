import { LivewireBindingExecutionError } from './livewire-errors.js';

export interface LivewireActionHandle {
  cancel(): void;
}

export interface LivewireActionInterceptorContext {
  action: LivewireActionHandle;
  onSend(callback: () => void): void;
}

export interface LivewireWire {
  readonly $id: string;
  $call(method: string, ...params: unknown[]): Promise<unknown>;
  intercept?(
    method: string,
    callback: (context: LivewireActionInterceptorContext) => void,
  ): () => void;
}

export interface LivewireBrowserRuntime {
  find(componentId: string): LivewireWire | undefined;
}

interface LivewireGlobalLike {
  find?: unknown;
}

export interface LivewireAmbientRoot {
  Livewire?: LivewireGlobalLike;
}

function defaultAmbientRoot(): LivewireAmbientRoot {
  return globalThis as unknown as LivewireAmbientRoot;
}

/** Narrow compatibility adapter around the documented global Livewire.find API. */
export class GlobalLivewireBrowserRuntime implements LivewireBrowserRuntime {
  constructor(private readonly root: LivewireAmbientRoot = defaultAmbientRoot()) {}

  find(componentId: string): LivewireWire | undefined {
    const livewire = this.root.Livewire;
    if (!livewire || typeof livewire.find !== 'function') {
      throw new LivewireBindingExecutionError(
        'livewire_runtime_unavailable',
        'Livewire browser runtime is unavailable or does not expose a callable find().',
      );
    }

    return (livewire.find as (id: string) => LivewireWire | undefined)(componentId);
  }
}
