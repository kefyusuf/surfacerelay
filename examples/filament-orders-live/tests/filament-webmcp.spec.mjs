import { expect, test } from '@playwright/test';

async function orders(request) {
  const response = await request.get('/__test/orders');
  expect(response.ok()).toBe(true);
  return response.json();
}

async function waitForSurfaceRelay(page) {
  await page.waitForFunction(() => globalThis.surfaceRelayOrderDemo?.ready === true);
}

async function listTools(page) {
  return page.evaluate(async () => (await document.modelContext.getTools()).map((tool) => ({
    name: tool.name,
    annotations: tool.annotations,
    inputSchema: typeof tool.inputSchema === 'string' ? JSON.parse(tool.inputSchema) : tool.inputSchema,
  })));
}

// Plays the agent through the browser's own WebMCP executeTool().
async function invokeAsAgent(page, toolName, input) {
  return page.evaluate(async ({ toolName, input }) => {
    const tool = (await document.modelContext.getTools()).find((t) => t.name === toolName);
    if (!tool) {
      return { status: 'missing' };
    }
    try {
      // Chromium 153 parses inputObject as a JSON string.
      const value = await document.modelContext.executeTool(tool, JSON.stringify(input));
      return { status: 'returned', value: JSON.parse(value) };
    } catch (error) {
      return { status: 'threw', error: `${error?.name}: ${error?.message}` };
    }
  }, { toolName, input });
}

function recordCheckbox(page, id) {
  return page.locator(`input.fi-ta-record-checkbox[value="${id}"]`);
}

test.beforeEach(async ({ request }) => {
  const reset = await request.post('/__test/reset');
  expect(reset.status()).toBe(204);
});

test('real Filament table shows only the trusted tenant and registers one refund tool', async ({ page }) => {
  await page.goto('/admin/orders');
  await waitForSurfaceRelay(page);

  for (const id of [101, 102, 103]) {
    await expect(recordCheckbox(page, id)).toBeVisible();
  }
  await expect(recordCheckbox(page, 201)).toHaveCount(0);

  const tools = await listTools(page);
  expect(tools.map((tool) => tool.name)).toEqual(['orders.refund_selected.v1']);
  expect(tools[0].annotations).toMatchObject({ readOnlyHint: false });
  expect(tools[0].inputSchema).toEqual({
    type: 'object',
    properties: { reason: { type: 'string', minLength: 1 } },
    required: ['reason'],
    additionalProperties: false,
  });
});

test('agent hold on the edit page mutates exactly the trusted current record', async ({ page, request }) => {
  await page.goto('/admin/orders/101/edit');
  await waitForSurfaceRelay(page);
  expect((await listTools(page)).map((tool) => tool.name)).toEqual(['orders.hold_current.v1']);

  const outcome = await invokeAsAgent(page, 'orders.hold_current.v1', { reason: 'manual-review' });

  expect(outcome).toEqual({ status: 'returned', value: { orderId: 101, held: true } });
  const held = (await orders(request)).filter((order) => order.held).map((order) => order.id);
  expect(held).toEqual([101]);
});

test('agent refund over the human-visible selection stops at confirmation without refunding', async ({ page, request }) => {
  await page.goto('/admin/orders');
  await waitForSurfaceRelay(page);
  await recordCheckbox(page, 101).check();
  await recordCheckbox(page, 102).check();

  const outcome = await invokeAsAgent(page, 'orders.refund_selected.v1', { reason: 'customer-request' });

  expect(outcome).toEqual({ status: 'returned', value: { status: 'confirmation_required' } });
  expect((await orders(request)).some((order) => order.refunded)).toBe(false);
});

async function approveInModal(page) {
  // requiresConfirmation() renders Filament's modal as an alertdialog whose own
  // box is a zero-size positioning wrapper; assert on its visible contents.
  const modal = page.getByRole('alertdialog').filter({ hasText: 'Confirm action' });
  const approve = modal.getByRole('button', { name: 'Approve' });
  await expect(approve).toBeVisible();
  await expect(modal).toContainText('Refund selected orders');
  await approve.click();
  await expect(page.getByText('Confirmation approved. Retry the original operation.')).toBeVisible();
}

async function refundedIds(request) {
  return (await orders(request)).filter((order) => order.refunded).map((order) => order.id);
}

test('human approval in the Filament modal lets the agent retry execute exactly once', async ({ page, request }) => {
  await page.goto('/admin/orders');
  await waitForSurfaceRelay(page);
  await recordCheckbox(page, 101).check();
  await recordCheckbox(page, 102).check();

  const first = await invokeAsAgent(page, 'orders.refund_selected.v1', { reason: 'customer-request' });
  expect(first).toEqual({ status: 'returned', value: { status: 'confirmation_required' } });
  expect(await refundedIds(request)).toEqual([]);

  await approveInModal(page);
  expect(await refundedIds(request)).toEqual([]);

  const retry = await invokeAsAgent(page, 'orders.refund_selected.v1', { reason: 'customer-request' });
  expect(retry.status).toBe('returned');
  expect(retry.value).toMatchObject({ refundedCount: 2, orderIds: [101, 102] });
  expect(await refundedIds(request)).toEqual([101, 102]);

  const again = await invokeAsAgent(page, 'orders.refund_selected.v1', { reason: 'customer-request' });
  expect(again).toEqual({ status: 'returned', value: { status: 'confirmation_required' } });
  expect(await refundedIds(request)).toEqual([101, 102]);
});

test('selection drift after approval requires a new confirmation and refunds nothing', async ({ page, request }) => {
  await page.goto('/admin/orders');
  await waitForSurfaceRelay(page);
  await recordCheckbox(page, 101).check();

  await invokeAsAgent(page, 'orders.refund_selected.v1', { reason: 'customer-request' });
  await approveInModal(page);
  await recordCheckbox(page, 102).check();

  const retry = await invokeAsAgent(page, 'orders.refund_selected.v1', { reason: 'customer-request' });

  expect(retry).toEqual({ status: 'returned', value: { status: 'confirmation_required' } });
  expect(await refundedIds(request)).toEqual([]);
});

test('another HTTP session cannot use an approved component snapshot or spend its owner receipt', async ({ page, browser, request }) => {
  await page.goto('/admin/orders');
  await waitForSurfaceRelay(page);
  await recordCheckbox(page, 101).check();
  await invokeAsAgent(page, 'orders.refund_selected.v1', { reason: 'customer-request' });
  await approveInModal(page);
  const snapshot = await page.evaluate(() => {
    const root = document.querySelector('[data-surfacerelay-bindings]').closest('[wire\\:id]');
    return JSON.stringify(Livewire.find(root.getAttribute('wire:id')).__instance.snapshot);
  });

  const outsider = await browser.newContext();
  try {
    const outsiderPage = await outsider.newPage();
    await outsiderPage.goto(new URL('/admin/orders', page.url()).href);
    await waitForSurfaceRelay(outsiderPage);
    const transport = await outsiderPage.evaluate(() => ({
      token: document.querySelector('meta[name="csrf-token"]').content,
      uri: document.querySelector('script[data-update-uri]').getAttribute('data-update-uri'),
    }));
    const response = await outsider.request.post(new URL(transport.uri, page.url()).href, {
      headers: { 'X-Livewire': '' },
      data: {
        _token: transport.token,
        components: [{ snapshot, updates: {}, calls: [{ method: 'refundSelected', params: ['customer-request'] }] }],
      },
    });
    expect(response.status()).toBe(200);
    const result = await response.json();
    expect(result.components[0].effects.returns).toEqual([{ status: 'confirmation_required' }]);
    expect(await refundedIds(request)).toEqual([]);
  } finally {
    await outsider.close();
  }

  const ownerRetry = await invokeAsAgent(page, 'orders.refund_selected.v1', { reason: 'customer-request' });
  expect(ownerRetry.status).toBe('returned');
  expect(ownerRetry.value).toMatchObject({ refundedCount: 1, orderIds: [101] });
  expect(await refundedIds(request)).toEqual([101]);
});

test('overlapping agent calls cannot replace an in-flight selection with an approved older selection', async ({ page, request }) => {
  await page.goto('/admin/orders');
  await waitForSurfaceRelay(page);
  await recordCheckbox(page, 101).check();
  await invokeAsAgent(page, 'orders.refund_selected.v1', { reason: 'customer-request' });
  await approveInModal(page);

  const invocationBodies = [];
  page.on('request', (request) => {
    if (request.method() === 'POST') invocationBodies.push(JSON.parse(request.postData()));
  });

  const outcomes = await page.evaluate(async () => {
    const tool = (await document.modelContext.getTools()).find((tool) => tool.name === 'orders.refund_selected.v1');
    const tableElement = document.querySelector('[x-data^="filamentTable("]');
    const table = Alpine.$data(tableElement);
    const componentId = tableElement.closest('[wire\\:id]').getAttribute('wire:id');
    const wire = Livewire.find(componentId);
    let resolveSynced;
    const synced = new Promise((resolve) => { resolveSynced = resolve; });
    const unwatch = wire.$watch('selectedTableRecords', () => resolveSynced());
    const invoke = async () => {
      try {
        const result = await document.modelContext.executeTool(tool, JSON.stringify({ reason: 'customer-request' }));
        return { status: 'returned', value: JSON.parse(result) };
      } catch {
        return { status: 'threw' };
      }
    };
    try {
      table.selectedRecords = new Set(['102']);
      const first = invoke();
      await synced;
      table.selectedRecords = new Set(['101']);
      const second = invoke();
      return await Promise.all([first, second]);
    } finally {
      unwatch();
    }
  });

  expect(outcomes[0]).toEqual({ status: 'returned', value: { status: 'confirmation_required' } });
  expect(outcomes[1].status).toBe('threw');
  const invocationComponents = invocationBodies.flatMap((body) => body.components)
    .filter((component) => component.calls.some((call) => call.method === 'refundSelected'));
  expect(invocationComponents).toHaveLength(1);
  expect(invocationComponents[0].updates).toMatchObject({ 'selectedTableRecords.0': '102' });
  expect(await refundedIds(request)).toEqual([]);
  let subsequentPosts = 0;
  page.on('request', (request) => {
    if (request.method() === 'POST') subsequentPosts++;
  });
  await invokeAsAgent(page, 'orders.refund_selected.v1', { reason: 'customer-request' });
  expect(subsequentPosts).toBe(1);
  expect(await refundedIds(request)).toEqual([]);
});

for (const state of ['missing', 'ambiguous', 'foreign', 'malformed']) {
  test(`${state} table selection after approval refuses retry before sending the stale selection`, async ({ page, request }) => {
    await page.goto('/admin/orders');
    await waitForSurfaceRelay(page);
    await recordCheckbox(page, 101).check();
    await invokeAsAgent(page, 'orders.refund_selected.v1', { reason: 'customer-request' });
    await approveInModal(page);
    await page.evaluate((state) => {
      const table = document.querySelector('[x-data^="filamentTable("]');
      if (state === 'missing') table.removeAttribute('x-data');
      if (state === 'ambiguous') {
        const duplicate = document.createElement('div');
        duplicate.setAttribute('x-data', 'filamentTable({})');
        table.parentElement.append(duplicate);
      }
      if (state === 'foreign') {
        const nestedComponent = document.createElement('div');
        nestedComponent.setAttribute('wire:id', 'another-component');
        table.parentElement.insertBefore(nestedComponent, table);
        nestedComponent.append(table);
      }
      if (state === 'malformed') Alpine.$data(table).selectedRecords = null;
    }, state);
    let invocations = 0;
    page.on('request', (request) => {
      if (request.method() === 'POST') invocations++;
    });

    const retry = await invokeAsAgent(page, 'orders.refund_selected.v1', { reason: 'customer-request' });

    expect(retry.status).toBe('threw');
    expect(invocations).toBe(0);
    expect(await refundedIds(request)).toEqual([]);
    if (state === 'malformed') {
      await page.evaluate(() => {
        Alpine.$data(document.querySelector('[x-data^="filamentTable("]')).selectedRecords = new Set(['101']);
      });
      const validRetry = await invokeAsAgent(page, 'orders.refund_selected.v1', { reason: 'customer-request' });
      expect(validRetry.status).toBe('returned');
      expect(validRetry.value).toMatchObject({ refundedCount: 1, orderIds: [101] });
      expect(await refundedIds(request)).toEqual([101]);
    }
  });
}

test('input drift after approval requires a new confirmation and refunds nothing', async ({ page, request }) => {
  await page.goto('/admin/orders');
  await waitForSurfaceRelay(page);
  await recordCheckbox(page, 101).check();

  await invokeAsAgent(page, 'orders.refund_selected.v1', { reason: 'customer-request' });
  await approveInModal(page);

  const retry = await invokeAsAgent(page, 'orders.refund_selected.v1', { reason: 'different-reason' });

  expect(retry).toEqual({ status: 'returned', value: { status: 'confirmation_required' } });
  expect(await refundedIds(request)).toEqual([]);
});

test('without human approval a repeated agent call never refunds', async ({ page, request }) => {
  await page.goto('/admin/orders');
  await waitForSurfaceRelay(page);
  await recordCheckbox(page, 101).check();

  await invokeAsAgent(page, 'orders.refund_selected.v1', { reason: 'customer-request' });
  const repeated = await invokeAsAgent(page, 'orders.refund_selected.v1', { reason: 'customer-request' });

  expect(repeated.status === 'threw' || repeated.value?.status === 'confirmation_required').toBe(true);
  expect(await refundedIds(request)).toEqual([]);
});

test('caller-supplied confirmation fields are rejected before reaching the server', async ({ page, request }) => {
  await page.goto('/admin/orders');
  await waitForSurfaceRelay(page);
  await recordCheckbox(page, 101).check();

  for (const forged of [{ confirmed: true }, { confirmationReceipt: 'A'.repeat(43) }]) {
    const outcome = await invokeAsAgent(page, 'orders.refund_selected.v1', { reason: 'customer-request', ...forged });
    expect(outcome.status).toBe('threw');
  }
  const legitimate = await invokeAsAgent(page, 'orders.refund_selected.v1', { reason: 'customer-request' });
  expect(legitimate).toEqual({ status: 'returned', value: { status: 'confirmation_required' } });
  expect(await refundedIds(request)).toEqual([]);
});

test('with no visible selection the refund tool fails closed', async ({ page, request }) => {
  await page.goto('/admin/orders');
  await waitForSurfaceRelay(page);
  await recordCheckbox(page, 101).check();
  await recordCheckbox(page, 101).uncheck();

  const outcome = await invokeAsAgent(page, 'orders.refund_selected.v1', { reason: 'customer-request' });

  expect(outcome.status).toBe('threw');
  expect((await orders(request)).some((order) => order.refunded)).toBe(false);
});

test('caller-supplied order ids are rejected before reaching the server', async ({ page, request }) => {
  await page.goto('/admin/orders');
  await waitForSurfaceRelay(page);
  await recordCheckbox(page, 101).check();

  const outcome = await invokeAsAgent(page, 'orders.refund_selected.v1', {
    reason: 'customer-request',
    orderIds: [201, 202],
  });

  expect(outcome.status).toBe('threw');
  expect((await orders(request)).some((order) => order.refunded)).toBe(false);
});
