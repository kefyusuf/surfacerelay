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
