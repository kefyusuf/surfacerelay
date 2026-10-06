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
  /**
   * Resolves only when the request was actually sent and HTMX reported a successful
   * response. A request HTMX never sent rejects with `htmx_request_not_sent`, unless
   * a confirmation hook could resume it later, which rejects with `htmx_request_failed`.
   * An unsuccessful HTTP status, transport failure, or response-handling failure rejects
   * with `htmx_request_failed`.
   *
   * On success it resolves with the object the server declared as
   * `surfacerelay:result.value` in that response's `HX-Trigger` JSON header, or
   * `undefined` when none is declared (D-078). Output is never derived from HTML.
   */
  ajax(method: HtmxAjaxMethod, path: string, context: HtmxAjaxContext): Promise<unknown>;
}

interface HtmxRequestEventSource {
  addEventListener(type: string, listener: (event: Event) => void): void;
  removeEventListener(type: string, listener: (event: Event) => void): void;
}

interface HtmxRequestEventDetail {
  xhr?: unknown;
  successful?: unknown;
  etc?: { values?: unknown } | null;
}

const REQUEST_CONFIRMATION_EVENT = 'htmx:confirm';
const REQUEST_SENT_EVENT = 'htmx:beforeSend';
const REQUEST_COMPLETED_EVENT = 'htmx:afterRequest';
const RESPONSE_HANDLING_FAILED_EVENT = 'htmx:onLoadError';
const RESULT_TRIGGER_HEADER = 'HX-Trigger';
const RESULT_TRIGGER_NAME = 'surfacerelay:result';

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

function requireRequestEventSource(source: HtmxSourceElement): HtmxRequestEventSource {
  const candidate = source as Partial<HtmxRequestEventSource>;
  if (
    typeof candidate.addEventListener !== 'function'
    || typeof candidate.removeEventListener !== 'function'
  ) {
    throw runtimeError(
      'htmx_runtime_unsupported',
      'HTMX source cannot observe request events.',
    );
  }
  return candidate as HtmxRequestEventSource;
}

function requestEventDetail(event: Event): HtmxRequestEventDetail {
  const detail: unknown = (event as CustomEvent<unknown>).detail;
  return typeof detail === 'object' && detail !== null ? detail : {};
}

function httpStatusOf(xhr: unknown): number | null {
  const status = (xhr as { status?: unknown } | null)?.status;
  return typeof status === 'number' ? status : null;
}

function isPlainObject(value: unknown): value is Record<string, unknown> {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) return false;
  const prototype = Object.getPrototypeOf(value);
  return prototype === Object.prototype || prototype === null;
}

// The request already succeeded server-side, so an absent or malformed result
// yields `undefined` rather than an error that would invite a duplicate retry.
function declaredResultOf(xhr: unknown): Record<string, unknown> | undefined {
  const getResponseHeader = (xhr as { getResponseHeader?: unknown } | null)?.getResponseHeader;
  if (typeof getResponseHeader !== 'function') return undefined;

  let header: unknown;
  try {
    header = getResponseHeader.call(xhr, RESULT_TRIGGER_HEADER);
  } catch {
    return undefined;
  }
  if (typeof header !== 'string' || !header.trimStart().startsWith('{')) return undefined;

  let triggers: unknown;
  try {
    triggers = JSON.parse(header);
  } catch {
    return undefined;
  }
  if (!isPlainObject(triggers)) return undefined;

  const result = triggers[RESULT_TRIGGER_NAME];
  if (!isPlainObject(result) || Object.keys(result).length !== 1
    || !Object.prototype.hasOwnProperty.call(result, 'value')) return undefined;
  return isPlainObject(result.value) ? result.value : undefined;
}

function requestNotSent(): HtmxBindingExecutionError {
  return new HtmxBindingExecutionError(
    'htmx_request_not_sent',
    'HTMX settled without sending the request.',
  );
}

function requestFailed(message: string): HtmxBindingExecutionError {
  return new HtmxBindingExecutionError('htmx_request_failed', message);
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
  ): Promise<unknown> {
    this.assertSupported();
    const htmx = this.requireHtmx();
    const events = requireRequestEventSource(context.source);
    const ajax = htmx.ajax as (
      verb: HtmxAjaxMethod,
      requestPath: string,
      options: {
        source: HtmxSourceElement;
        values: Readonly<Record<string, string>>;
      },
    ) => Promise<void>;

    // htmx.ajax() resolves after HTTP error responses and on several paths that never
    // send the request, so its promise alone cannot prove success. The issued request
    // is identified by the xhr in its own beforeSend event on this exact source.
    // Once sent, failures never downgrade to "not sent": that would invite a retry of a
    // request the server may already have applied.
    let sent = false;
    let xhr: unknown = null;
    let completion: HtmxRequestEventDetail | null = null;
    let confirmation: Event | null = null;
    let failResponseHandling!: (error: HtmxBindingExecutionError) => void;
    const responseHandlingFailed = new Promise<never>((_resolve, reject) => {
      failResponseHandling = reject;
    });

    const onConfirmation = (event: Event): void => {
      if (
        confirmation === null
        && event.target === (events as unknown)
        && requestEventDetail(event).etc?.values === context.values
      ) {
        confirmation = event;
      }
    };
    const unsentOutcome = (): HtmxBindingExecutionError => {
      // HTMX resolves the original promise after a confirmation veto, but the
      // application's issueRequest callback may still send it later. Never
      // describe that unknown outcome as proof that no request can be sent.
      return confirmation?.defaultPrevented === true
        ? requestFailed('HTMX confirmation hook may resume the request later.')
        : requestNotSent();
    };
    const onSent = (event: Event): void => {
      if (!sent && event.target === (events as unknown)) {
        sent = true;
        xhr = requestEventDetail(event).xhr ?? null;
      }
    };
    const onCompleted = (event: Event): void => {
      const detail = requestEventDetail(event);
      if (xhr !== null && detail.xhr === xhr) completion = detail;
    };
    const onResponseHandlingFailed = (event: Event): void => {
      if (xhr !== null && requestEventDetail(event).xhr === xhr) {
        failResponseHandling(requestFailed('HTMX failed while handling the response.'));
      }
    };

    events.addEventListener(REQUEST_CONFIRMATION_EVENT, onConfirmation);
    events.addEventListener(REQUEST_SENT_EVENT, onSent);
    events.addEventListener(REQUEST_COMPLETED_EVENT, onCompleted);
    events.addEventListener(RESPONSE_HANDLING_FAILED_EVENT, onResponseHandlingFailed);
    try {
      await Promise.race([
        ajax.call(htmx, method, path, {
          source: context.source,
          values: context.values,
        }),
        responseHandlingFailed,
      ]);
    } catch (error) {
      if (error instanceof HtmxBindingExecutionError) throw error;
      if (!sent) throw unsentOutcome();
      throw requestFailed('HTMX request failed before a successful response.');
    } finally {
      events.removeEventListener(REQUEST_CONFIRMATION_EVENT, onConfirmation);
      events.removeEventListener(REQUEST_SENT_EVENT, onSent);
      events.removeEventListener(REQUEST_COMPLETED_EVENT, onCompleted);
      events.removeEventListener(RESPONSE_HANDLING_FAILED_EVENT, onResponseHandlingFailed);
    }

    if (!sent) throw unsentOutcome();

    const completed = completion as HtmxRequestEventDetail | null;
    if (completed?.successful !== true) {
      const status = httpStatusOf(xhr);
      throw requestFailed(
        status === null
          ? 'HTMX request did not complete successfully.'
          : `HTMX request completed with unsuccessful HTTP status ${status}.`,
      );
    }

    return declaredResultOf(xhr);
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
