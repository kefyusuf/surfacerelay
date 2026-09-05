export type ActionScope = 'portable' | 'page_scoped' | 'browser_local' | 'headless';
export type ActionEffect = 'read' | 'reversible_write' | 'destructive_write' | 'external_side_effect';
export type ActionRisk = 'low' | 'moderate' | 'high' | 'consequential';
export type OutputSensitivity = 'normal' | 'sensitive';
export type OutputContentTrust = 'trusted_application_data' | 'contains_untrusted_content';

export interface ActionRef { id: string; version: number }

export interface ActionDefinition {
  id: string;
  version: number;
  title: string;
  description: string;
  inputSchema: Record<string, unknown>;
  outputSchema?: Record<string, unknown> | null;
  scope: ActionScope;
  effect: ActionEffect;
  risk: ActionRisk;
  idempotency: 'none' | 'recommended_key' | 'required_key';
  outputSensitivity: OutputSensitivity;
  outputContentTrust: OutputContentTrust;
  contextRequirements: string[];
}

export interface RuntimeBinding {
  bindingId: string;
  action: ActionRef;
  driver: string;
  lifecycle: 'page' | 'component' | 'session' | 'persistent';
  target: Record<string, unknown>;
  expiresAt?: string | null;
}

export interface DriverExecutionContext { signal?: AbortSignal }

export interface BindingDriver {
  execute(binding: RuntimeBinding, input: Record<string, unknown>, context: DriverExecutionContext): Promise<unknown>;
}
