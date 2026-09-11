import {
  createHtmxBindingTarget,
  parseHtmxBindingTarget,
  type HtmxBindingTarget,
  type HtmxRequestMethod,
} from '../src/htmx-binding-descriptor.js';
import type { ActionDefinition, RuntimeBinding } from '../src/types.js';

declare const definition: ActionDefinition;
declare const binding: RuntimeBinding;

const method: HtmxRequestMethod = 'POST';
const created: HtmxBindingTarget = createHtmxBindingTarget(definition, {
  sourceId: 'htmx-src-1',
  method,
  path: '/items',
});
const parsed: HtmxBindingTarget = parseHtmxBindingTarget(binding);

// @ts-expect-error readonly target property
created.path = '/other';
// @ts-expect-error readonly array
created.inputNames.push('other');
// @ts-expect-error unsupported HTMX reference method
const invalidMethod: HtmxRequestMethod = 'HEAD';

void parsed;
void invalidMethod;
