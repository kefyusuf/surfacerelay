import { HtmxBindingExecutionError } from './htmx-errors.js';

export type HtmxAjaxMethod = 'get' | 'post' | 'put' | 'patch' | 'delete';

export interface HtmxClassListLike {
  contains(token: string): boolean;
}

export interface HtmxSourceElement {
  readonly tagName: string;
  readonly parentElement: HtmxSourceElement | null;
  readonly classList: HtmxClassListLike;
  hasAttribute(name: string): boolean;
  getAttribute(name: string): string | null;
}

export interface HtmxLocationSnapshot {
  readonly href: string;
  readonly origin: string;
}

export interface HtmxAjaxContext {
  readonly source: HtmxSourceElement;
  readonly values: Readonly<Record<string, string>>;
}

export interface HtmxBrowserRuntime {
  assertSupported(): void;
  findSources(sourceId: string): readonly HtmxSourceElement[];
  currentLocation(): HtmxLocationSnapshot;
  requestClass(): string;
  ajax(method: HtmxAjaxMethod, path: string, context: HtmxAjaxContext): Promise<void>;
}

interface HtmxGlobalLike {
  version?: unknown;
  config?: { requestClass?: unknown } | null;
  ajax?: unknown;
}

interface HtmxDocumentLike {
  querySelectorAll(selector: string): ArrayLike<HtmxSourceElement>;
}

interface HtmxLocationLike {
  href?: unknown;
  origin?: unknown;
}

export interface HtmxAmbientRoot {
  htmx?: HtmxGlobalLike;
  document?: HtmxDocumentLike;
  location?: HtmxLocationLike;
}

const VERSION_PATTERN = /^(\d+)\.(\d+)\.(\d+)(?:[-+][0-9A-Za-z.-]+)?$/;
const SOURCE_SELECTOR = '[data-surfacerelay-htmx-source]';

function runtimeError(
  code: 'htmx_runtime_unavailable' | 'htmx_runtime_unsupported',
  message: string,
): HtmxBindingExecutionError {
  return new HtmxBindingExecutionError(code, message);
}

function defaultAmbientRoot(): HtmxAmbientRoot {
  return globalThis as unknown as HtmxAmbientRoot;
}

export class GlobalHtmxBrowserRuntime implements HtmxBrowserRuntime {
  constructor(private readonly root: HtmxAmbientRoot = defaultAmbientRoot()) {}

  assertSupported(): void {
    const htmx = this.requireHtmx();
    const version = htmx.version;
    if (typeof version !== 'string') {
      throw runtimeError('htmx_runtime_unsupported', 'HTMX version is unavailable or malformed.');
    }

    const match = VERSION_PATTERN.exec(version);
    if (!match || Number(match[1]) !== 2) {
      throw runtimeError(
        'htmx_runtime_unsupported',
        'SurfaceRelay reference HTMX driver requires HTMX 2.x.',
      );
    }

    this.requireDocument();
    this.requireLocation();
    this.requestClass();
  }

  findSources(sourceId: string): readonly HtmxSourceElement[] {
    this.assertSupported();
    const candidates = Array.from(this.requireDocument().querySelectorAll(SOURCE_SELECTOR));
    return Object.freeze(
      candidates.filter(
        (candidate) => candidate.getAttribute('data-surfacerelay-htmx-source') === sourceId,
      ),
    );
  }

  currentLocation(): HtmxLocationSnapshot {
    this.assertSupported();
    const location = this.requireLocation();
    return Object.freeze({ href: location.href, origin: location.origin });
  }

  requestClass(): string {
    const htmx = this.requireHtmx();
    const requestClass = htmx.config?.requestClass;
    if (
      typeof requestClass !== 'string'
      || requestClass.length === 0
      || requestClass.trim() !== requestClass
      || /\s/.test(requestClass)
    ) {
      throw runtimeError(
        'htmx_runtime_unsupported',
        'HTMX requestClass must be one non-empty class token.',
      );
    }
    return requestClass;
  }

  async ajax(
    method: HtmxAjaxMethod,
    path: string,
    context: HtmxAjaxContext,
  ): Promise<void> {
    this.assertSupported();
    const htmx = this.requireHtmx();
    const ajax = htmx.ajax as (
      verb: HtmxAjaxMethod,
      requestPath: string,
      options: {
        source: HtmxSourceElement;
        values: Readonly<Record<string, string>>;
      },
    ) => Promise<void>;

    return ajax.call(htmx, method, path, {
      source: context.source,
      values: context.values,
    });
  }

  private requireHtmx(): HtmxGlobalLike {
    const htmx = this.root.htmx;
    if (!htmx || typeof htmx !== 'object' || typeof htmx.ajax !== 'function') {
      throw runtimeError(
        'htmx_runtime_unavailable',
        'HTMX browser runtime is unavailable or does not expose callable ajax().',
      );
    }
    return htmx;
  }

  private requireDocument(): HtmxDocumentLike {
    const document = this.root.document;
    if (!document || typeof document.querySelectorAll !== 'function') {
      throw runtimeError(
        'htmx_runtime_unavailable',
        'Browser document.querySelectorAll() is unavailable.',
      );
    }
    return document;
  }

  private requireLocation(): { href: string; origin: string } {
    const location = this.root.location;
    if (
      !location
      || typeof location.href !== 'string'
      || typeof location.origin !== 'string'
    ) {
      throw runtimeError(
        'htmx_runtime_unavailable',
        'Browser location href/origin are unavailable.',
      );
    }
    return { href: location.href, origin: location.origin };
  }
}
