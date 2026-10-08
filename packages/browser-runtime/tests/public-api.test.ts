import { describe, expect, it } from 'vitest';
import * as api from '../src/index.js';

describe('browser-runtime public root API', () => {
  it('exports exactly the reviewed runtime value allowlist', () => {
    expect(Object.keys(api).sort()).toEqual([
      'DriverRegistry',
      'FilamentBrowserDriver',
      'FilamentSelectionCoordinator',
      'GlobalFilamentSelectionRuntime',
      'GlobalHtmxBrowserRuntime',
      'GlobalLivewireBrowserRuntime',
      'HtmxBrowserDriver',
      'LivewireBrowserDriver',
      'WebMcpRegistrationLifecycle',
      'createHtmxBindingTarget',
      'projectAnnotations',
      'projectBoundActionTool',
      'projectWebMcpToolName',
      'resolveDocumentModelContext',
    ]);
  });
});
