import type {
  LivewireActionHandle,
  LivewireActionInterceptorContext,
  LivewireWire,
} from '../src/livewire-browser-runtime.js';

declare const action: LivewireActionHandle;
declare const context: LivewireActionInterceptorContext;

const wire: LivewireWire = {
  $id: 'component-1',
  async $call(): Promise<unknown> {
    return null;
  },
  intercept(method, callback) {
    void method;
    callback(context);
    return () => {};
  },
};

const unsubscribe = wire.intercept?.('save', ({ action: interceptedAction, onSend }) => {
  onSend(() => interceptedAction.cancel());
});

void action;
void unsubscribe;

// Broader message/request cancellation APIs are intentionally not part of the narrow port.
// @ts-expect-error SurfaceRelay does not model request-level cancellation here.
wire.interceptRequest;
// @ts-expect-error SurfaceRelay does not model message-level cancellation here.
wire.interceptMessage;
