import {
  DriverRegistry,
  GlobalHtmxBrowserRuntime,
  GlobalLivewireBrowserRuntime,
  HtmxBrowserDriver,
  LivewireBrowserDriver,
  WebMcpRegistrationLifecycle,
  createHtmxBindingTarget,
  projectAnnotations,
  projectBoundActionTool,
  projectWebMcpToolName,
  type ActionDefinition,
  type ActionEffect,
  type ActionRef,
  type ActionRisk,
  type ActionScope,
  type BindingDriver,
  type BoundActionExecutor,
  type BoundActionTool,
  type BrowserClock,
  type CreateHtmxBindingTargetOptions,
  type DriverExecutionContext,
  type HtmxAjaxContext,
  type HtmxAjaxMethod,
  type HtmxAmbientRoot,
  type HtmxBindingTarget,
  type HtmxBrowserRuntime,
  type HtmxClassListLike,
  type HtmxLocationSnapshot,
  type HtmxRequestMethod,
  type HtmxSourceElement,
  type LivewireActionHandle,
  type LivewireActionInterceptorContext,
  type LivewireAmbientRoot,
  type LivewireBrowserRuntime,
  type LivewireWire,
  type OutputContentTrust,
  type OutputSensitivity,
  type RuntimeBinding,
  type WebMcpAnnotations,
  type WebMcpModelContext,
  type WebMcpRegisterToolOptions,
  type WebMcpRegistrationLease,
  type WebMcpTool,
  type WebMcpToolExecuteOptions,
} from '../src/index.js';

declare const definition: ActionDefinition;
declare const binding: RuntimeBinding;
declare const driver: BindingDriver;
declare const clock: BrowserClock;
declare const livewireRuntime: LivewireBrowserRuntime;
declare const htmxRuntime: HtmxBrowserRuntime;
declare const modelContext: WebMcpModelContext;

const registry = new DriverRegistry();
registry.register('test', driver);

const livewireDriver: BindingDriver = new LivewireBrowserDriver(livewireRuntime, clock);
const htmxDriver: BindingDriver = new HtmxBrowserDriver(htmxRuntime, clock);
const lifecycle = new WebMcpRegistrationLifecycle(modelContext, registry);

const htmxOptions: CreateHtmxBindingTargetOptions = {
  sourceId: 'public-api-source',
  method: 'POST',
  path: '/public-api',
};
const htmxTarget: HtmxBindingTarget = createHtmxBindingTarget(definition, htmxOptions);

const annotations: WebMcpAnnotations = projectAnnotations(definition);
const toolName: string = projectWebMcpToolName(definition);
const candidate: BoundActionTool = { definition, binding };
const executor: BoundActionExecutor = async (
  _input: Record<string, unknown>,
  _options: WebMcpToolExecuteOptions,
) => undefined;
const tool: WebMcpTool = projectBoundActionTool(candidate, executor);

type PublicTypeClosure = [
  ActionScope,
  ActionEffect,
  ActionRisk,
  OutputSensitivity,
  OutputContentTrust,
  ActionRef,
  ActionDefinition,
  RuntimeBinding,
  DriverExecutionContext,
  BindingDriver,
  BrowserClock,
  LivewireActionHandle,
  LivewireActionInterceptorContext,
  LivewireWire,
  LivewireBrowserRuntime,
  LivewireAmbientRoot,
  HtmxRequestMethod,
  HtmxBindingTarget,
  CreateHtmxBindingTargetOptions,
  HtmxAjaxMethod,
  HtmxClassListLike,
  HtmxSourceElement,
  HtmxLocationSnapshot,
  HtmxAjaxContext,
  HtmxBrowserRuntime,
  HtmxAmbientRoot,
  WebMcpAnnotations,
  WebMcpRegistrationLease,
  BoundActionTool,
  BoundActionExecutor,
  WebMcpToolExecuteOptions,
  WebMcpTool,
  WebMcpRegisterToolOptions,
  WebMcpModelContext,
];

declare const publicTypes: PublicTypeClosure;

void livewireDriver;
void htmxDriver;
void lifecycle;
void htmxTarget;
void annotations;
void toolName;
void tool;
void publicTypes;
