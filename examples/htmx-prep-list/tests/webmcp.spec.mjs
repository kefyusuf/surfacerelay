import { expect, test } from '@playwright/test';

const toolName = 'prep_list.add_item.v1';

function isItemsPost(request) {
  return request.method() === 'POST' && new URL(request.url()).pathname === '/items';
}

async function listTools(page) {
  return page.evaluate(async () => {
    const tools = await document.modelContext.getTools();
    return tools.map((tool) => ({
      name: tool.name,
      title: tool.title,
      description: tool.description,
      inputSchema: typeof tool.inputSchema === 'string' ? JSON.parse(tool.inputSchema) : tool.inputSchema,
      origin: tool.origin,
      annotations: tool.annotations,
    }));
  });
}

// Plays the agent: discovers the tool and invokes it through the browser's own
// WebMCP executeTool(), so execution goes through Chromium, not a direct call.
async function invokeAsAgent(page, input) {
  return page.evaluate(async ({ toolName, input }) => {
    const tool = (await document.modelContext.getTools()).find((t) => t.name === toolName);
    if (!tool) {
      return { status: 'missing' };
    }
    try {
      // Chromium 153 parses inputObject as a JSON string; the 2026-10-02 draft types it as object.
      const value = await document.modelContext.executeTool(tool, JSON.stringify(input));
      return { status: 'returned', value };
    } catch (error) {
      return { status: 'threw', error: `${error?.name}: ${error?.message}` };
    }
  }, { toolName, input });
}

test.beforeEach(async ({ page, request }) => {
  const reset = await request.post('/__test/reset');
  expect(reset.status()).toBe(204);
  await page.goto('/');
  await page.waitForFunction(() => globalThis.surfaceRelayFixture?.ready === true);
});

test('registers exactly one projected Action tool on the real document.modelContext', async ({ page }) => {
  expect(await page.evaluate(() => globalThis.surfaceRelayFixture.webMcpAvailable)).toBe(true);

  const tools = await listTools(page);

  expect(tools).toHaveLength(1);
  expect(tools[0]).toMatchObject({
    name: toolName,
    title: 'Add preparation item',
    description: 'Adds an item to the shared preparation list.',
    origin: 'http://127.0.0.1:4173',
    annotations: { readOnlyHint: false, untrustedContentHint: false },
  });
  expect(tools[0].inputSchema).toEqual({
    type: 'object',
    additionalProperties: false,
    properties: { name: { type: 'string', minLength: 1 } },
    required: ['name'],
  });
});

test('browser-mediated tool call runs the same real HTMX POST /items as the human path', async ({ page }) => {
  await page.locator('input[name="name"]').fill('stale-human-value');
  const requestPromise = page.waitForRequest(isItemsPost);

  const outcome = await invokeAsAgent(page, { name: 'agent-webmcp' });
  const request = await requestPromise;
  const body = new URLSearchParams(request.postData() ?? '');

  expect(outcome.status).toBe('returned');
  expect(request.headers()['hx-request']).toBe('true');
  expect(body.getAll('name')).toEqual(['agent-webmcp']);
  expect(body.get('uiContext')).toBe('prep-list');

  await expect(page.locator('#items li')).toHaveText(['agent-webmcp']);
  await page.reload();
  await expect(page.locator('#items li')).toHaveText(['agent-webmcp']);
});

test('stale binding fails the tool call closed without sending /items', async ({ page }) => {
  const posts = [];
  page.on('request', (request) => {
    if (isItemsPost(request)) posts.push(request);
  });

  await page.evaluate(() => globalThis.surfaceRelayFixture.replaceSourceForTest());
  const outcome = await invokeAsAgent(page, { name: 'should-not-send' });

  expect(outcome.status).toBe('threw');
  await page.waitForTimeout(100);
  expect(posts).toHaveLength(0);
  await expect(page.locator('#items li')).toHaveCount(0);
});

test('disposing the registration lease unregisters the tool from the browser', async ({ page }) => {
  expect(await listTools(page)).toHaveLength(1);

  await page.evaluate(() => globalThis.surfaceRelayFixture.disposeWebMcpForTest());

  await expect.poll(() => listTools(page)).toEqual([]);
  expect((await invokeAsAgent(page, { name: 'after-dispose' })).status).toBe('missing');
});

test('reload re-registers one tool bound to the renewed page binding', async ({ page }) => {
  const firstBindingId = await page.evaluate(
    () => JSON.parse(document.getElementById('surfacerelay-binding').textContent).bindingId,
  );

  await page.reload();
  await page.waitForFunction(() => globalThis.surfaceRelayFixture?.ready === true);

  const secondBindingId = await page.evaluate(
    () => JSON.parse(document.getElementById('surfacerelay-binding').textContent).bindingId,
  );
  expect(secondBindingId).not.toBe(firstBindingId);
  expect((await listTools(page)).map((tool) => tool.name)).toEqual([toolName]);

  const requestPromise = page.waitForRequest(isItemsPost);
  expect((await invokeAsAgent(page, { name: 'after-reload' })).status).toBe('returned');
  await requestPromise;
  await expect(page.locator('#items li')).toHaveText(['after-reload']);
});

test('undeclared input properties fail the tool call closed without sending /items', async ({ page }) => {
  const posts = [];
  page.on('request', (request) => {
    if (isItemsPost(request)) posts.push(request);
  });

  const outcome = await invokeAsAgent(page, { name: 'x', injected: 'y' });

  expect(outcome.status).toBe('threw');
  await page.waitForTimeout(100);
  expect(posts).toHaveLength(0);
});

test('server-side rejection is not reported to the agent as success', async ({ page }) => {
  const responsePromise = page.waitForResponse((response) => isItemsPost(response.request()));
  const outcome = await invokeAsAgent(page, { name: '' });
  const response = await responsePromise;

  expect(response.status()).toBe(422);
  expect(outcome.status).toBe('threw');
  await expect(page.locator('#items li')).toHaveCount(0);
});

test('a request HTMX never sends is not reported to the agent as success', async ({ page }) => {
  const posts = [];
  page.on('request', (request) => {
    if (isItemsPost(request)) posts.push(request);
  });
  await page.evaluate(() => {
    document.addEventListener('htmx:beforeRequest', (event) => event.preventDefault(), { once: true });
  });

  const outcome = await invokeAsAgent(page, { name: 'vetoed' });

  expect(outcome.status).toBe('threw');
  await page.waitForTimeout(100);
  expect(posts).toHaveLength(0);
});

test('the driver rejects a server rejection with htmx_request_failed', async ({ page }) => {
  const error = await page.evaluate(async () => {
    try {
      await globalThis.surfaceRelayFixture.addItem('');
      return null;
    } catch (caught) {
      return { name: caught.name, code: caught.code, message: caught.message };
    }
  });

  expect(error).toEqual({
    name: 'HtmxBindingExecutionError',
    code: 'htmx_request_failed',
    message: 'HTMX request completed with unsuccessful HTTP status 422.',
  });
});
