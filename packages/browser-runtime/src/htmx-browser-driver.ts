import {
  HtmxBindingDescriptorError,
  parseHtmxBindingTarget,
  type HtmxBindingTarget,
  type HtmxRequestMethod,
} from './htmx-binding-descriptor.js';
import { HtmxBindingExecutionError } from './htmx-errors.js';
import type {
  HtmxAjaxMethod,
  HtmxBrowserRuntime,
  HtmxSourceElement,
} from './htmx-browser-runtime.js';
import { mapHtmxActionInput } from './htmx-input-mapping.js';
import {
  classifyRuntimeBindingExpiry,
  systemBrowserClock,
  type BrowserClock,
} from './runtime-binding-expiry.js';
import type {
  BindingDriver,
  DriverExecutionContext,
  RuntimeBinding,
} from './types.js';

interface SourceRequest {
  readonly method: HtmxRequestMethod;
  readonly path: string;
}

const REQUEST_ATTRIBUTES: readonly (readonly [HtmxRequestMethod, string])[] = [
  ['GET', 'hx-get'],
  ['GET', 'data-hx-get'],
  ['POST', 'hx-post'],
  ['POST', 'data-hx-post'],
  ['PUT', 'hx-put'],
  ['PUT', 'data-hx-put'],
  ['PATCH', 'hx-patch'],
  ['PATCH', 'data-hx-patch'],
  ['DELETE', 'hx-delete'],
  ['DELETE', 'data-hx-delete'],
];

const AJAX_METHOD: Readonly<Record<HtmxRequestMethod, HtmxAjaxMethod>> = {
  GET: 'get',
  POST: 'post',
  PUT: 'put',
  PATCH: 'patch',
  DELETE: 'delete',
};

const UNSUPPORTED_INHERITED_ATTRIBUTES = [
  'hx-vals',
  'hx-vars',
  'hx-confirm',
  'hx-prompt',
  'hx-sync',
  'hx-indicator',
  'hx-ext',
] as const;

function executionError(
  code: HtmxBindingExecutionError['code'],
  message: string,
): HtmxBindingExecutionError {
  return new HtmxBindingExecutionError(code, message);
}

function parseTarget(binding: RuntimeBinding): HtmxBindingTarget {
  try {
    return parseHtmxBindingTarget(binding);
  } catch (error) {
    if (error instanceof HtmxBindingDescriptorError) {
      throw executionError(
        'binding_target_invalid',
        `HTMX binding target is invalid: ${error.code}.`,
      );
    }
    throw error;
  }
}

function assertNotExpired(binding: RuntimeBinding, now: Date): void {
  const expiry = classifyRuntimeBindingExpiry(binding.expiresAt, now);
  if (expiry === 'invalid') {
    throw executionError(
      'binding_target_invalid',
      'HTMX binding expiresAt must be a valid RFC3339 date-time string, null, or absent.',
    );
  }
  if (expiry === 'expired') {
    throw executionError('binding_expired', 'HTMX binding has expired.');
  }
}

function parseSourceRequest(source: HtmxSourceElement): SourceRequest {
  const found: SourceRequest[] = [];

  for (const [method, attribute] of REQUEST_ATTRIBUTES) {
    if (!source.hasAttribute(attribute)) continue;
    const path = source.getAttribute(attribute);
    if (path === null) {
      throw executionError(
        'binding_stale',
        'Exact HTMX source request declaration is unreadable.',
      );
    }
    found.push({ method, path });
  }

  if (found.length !== 1) {
    throw executionError(
      'binding_stale',
      'Exact HTMX source must declare exactly one supported request attribute.',
    );
  }

  return found[0];
}

function assertExactRequest(
  source: HtmxSourceElement,
  target: HtmxBindingTarget,
): void {
  const request = parseSourceRequest(source);
  if (request.method !== target.method || request.path !== target.path) {
    throw executionError(
      'binding_stale',
      'Exact HTMX source request contract no longer matches the issued binding.',
    );
  }
}

function assertSameOrigin(
  runtime: HtmxBrowserRuntime,
  target: HtmxBindingTarget,
): void {
  const location = runtime.currentLocation();
  let resolved: URL;

  try {
    resolved = new URL(target.path, location.href);
  } catch {
    throw executionError(
      'binding_target_invalid',
      'HTMX binding path cannot be resolved against the current page location.',
    );
  }

  if (resolved.origin !== location.origin) {
    throw executionError(
      'binding_target_invalid',
      'HTMX binding path resolves outside the current origin.',
    );
  }
}

function physicalAttributeValues(
  element: HtmxSourceElement,
  name: string,
): readonly (string | null)[] {
  const values: (string | null)[] = [];
  for (const attribute of [name, `data-${name}`]) {
    if (element.hasAttribute(attribute)) {
      values.push(element.getAttribute(attribute));
    }
  }
  return values;
}

function assertReferenceSourceSupported(source: HtmxSourceElement): void {
  let current: HtmxSourceElement | null = source;
  while (current !== null) {
    for (const name of UNSUPPORTED_INHERITED_ATTRIBUTES) {
      if (physicalAttributeValues(current, name).length > 0) {
        throw executionError(
          'htmx_source_unsupported',
          `HTMX reference source uses unsupported ${name} behavior.`,
        );
      }
    }

    for (const params of physicalAttributeValues(current, 'hx-params')) {
      if (params !== '*') {
        throw executionError(
          'htmx_source_unsupported',
          'HTMX reference source uses restrictive hx-params behavior.',
        );
      }
    }

    current = current.parentElement;
  }

  if (
    physicalAttributeValues(source, 'hx-validate')
      .some((value) => value === 'true')
  ) {
    throw executionError(
      'htmx_source_unsupported',
      'HTMX reference source enables browser validation.',
    );
  }

  if (source.tagName.toUpperCase() === 'FORM' && !source.hasAttribute('novalidate')) {
    throw executionError(
      'htmx_source_unsupported',
      'HTMX reference FORM source must disable browser validation explicitly.',
    );
  }
}

function assertSourceNotBusy(
  source: HtmxSourceElement,
  runtime: HtmxBrowserRuntime,
): void {
  const requestClass = runtime.requestClass();
  if (source.classList.contains(requestClass)) {
    throw executionError(
      'htmx_source_busy',
      'Exact HTMX source is already processing another request.',
    );
  }
}

export class HtmxBrowserDriver implements BindingDriver {
  constructor(
    private readonly runtime: HtmxBrowserRuntime,
    private readonly clock: BrowserClock = systemBrowserClock,
  ) {}

  async execute(
    binding: RuntimeBinding,
    input: Record<string, unknown>,
    context: DriverExecutionContext,
  ): Promise<unknown> {
    if (context.signal?.aborted) throw context.signal.reason;

    const target = parseTarget(binding);
    assertNotExpired(binding, this.clock.now());

    this.runtime.assertSupported();

    const sources = this.runtime.findSources(target.sourceId);
    if (sources.length !== 1) {
      throw executionError(
        'binding_stale',
        'Exact HTMX source is missing or its identity is duplicated.',
      );
    }

    const source = sources[0];
    assertExactRequest(source, target);
    assertSameOrigin(this.runtime, target);
    assertReferenceSourceSupported(source);
    assertSourceNotBusy(source, this.runtime);

    const values = mapHtmxActionInput(target, input);

    if (context.signal?.aborted) throw context.signal.reason;

    return this.runtime.ajax(AJAX_METHOD[target.method], target.path, {
      source,
      values,
    });
  }
}
