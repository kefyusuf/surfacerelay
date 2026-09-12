import { expect, test } from '@playwright/test';

const bindingSelector = '#surfacerelay-binding';
const sourceSelector = '[data-surfacerelay-htmx-source]';

function normalizedRequestSignature(request) {
  const headers = request.headers();
  const body = new URLSearchParams(request.postData() ?? '');
  return {
    method: request.method(),
    pathname: new URL(request.url()).pathname,
    hxRequest: headers['hx-request'],
    contentType: headers['content-type']?.split(';')[0],
    uiContext: body.get('uiContext'),
  };
}

test.beforeEach(async ({ page, request }) => {
  const reset = await request.post('/__test/reset');
  expect(reset.status()).toBe(204);
  await page.goto('/');
  await page.waitForFunction(() => globalThis.surfaceRelayFixture?.ready === true);
});

test('boots real HTMX 2.0.10 with one exact server-issued page binding', async ({ page }) => {
  const snapshot = await page.evaluate(({ bindingSelector, sourceSelector }) => {
    const raw = document.querySelector(bindingSelector)?.textContent;
    const source = document.querySelector(sourceSelector);
    return {
      htmxVersion: globalThis.htmx?.version,
      binding: raw ? JSON.parse(raw) : null,
      sourceId: source?.getAttribute('data-surfacerelay-htmx-source'),
      sourceCount: document.querySelectorAll(sourceSelector).length,
    };
  }, { bindingSelector, sourceSelector });

  expect(snapshot.htmxVersion).toBe('2.0.10');
  expect(snapshot.sourceCount).toBe(1);
  expect(snapshot.binding.driver).toBe('htmx');
  expect(snapshot.binding.lifecycle).toBe('page');
  expect(snapshot.binding.action).toEqual({ id: 'prep_list.add_item', version: 1 });
  expect(snapshot.binding.target.method).toBe('POST');
  expect(snapshot.binding.target.path).toBe('/items');
  expect(snapshot.binding.target.sourceId).toBe(snapshot.sourceId);
});

test('normal human click uses real HTMX POST /items, swaps HTML, and persists server state', async ({ page }) => {
  await page.locator('input[name="name"]').fill('human-coffee');

  const requestPromise = page.waitForRequest((request) => {
    return request.method() === 'POST' && new URL(request.url()).pathname === '/items';
  });

  await page.locator(sourceSelector).click();
  const request = await requestPromise;
  const body = new URLSearchParams(request.postData() ?? '');

  expect(request.headers()['hx-request']).toBe('true');
  expect(request.headers()['content-type']).toContain('application/x-www-form-urlencoded');
  expect(body.get('name')).toBe('human-coffee');
  expect(body.get('uiContext')).toBe('prep-list');

  await expect(page.locator('#items li')).toContainText('human-coffee');
  await page.reload();
  await expect(page.locator('#items li')).toContainText('human-coffee');
});

test('SurfaceRelay driver uses real HTMX and overrides stale same-name form state', async ({ page }) => {
  await page.locator('input[name="name"]').fill('stale-human-value');

  const requestPromise = page.waitForRequest((request) => {
    return request.method() === 'POST' && new URL(request.url()).pathname === '/items';
  });

  await page.evaluate(() => globalThis.surfaceRelayFixture.addItem('agent-tea'));
  const request = await requestPromise;
  const body = new URLSearchParams(request.postData() ?? '');

  expect(request.headers()['hx-request']).toBe('true');
  expect(request.headers()['content-type']).toContain('application/x-www-form-urlencoded');
  expect(body.get('name')).toBe('agent-tea');
  expect(body.get('uiContext')).toBe('prep-list');
  expect(body.get('name')).not.toBe('stale-human-value');

  await expect(page.locator('#items li')).toContainText('agent-tea');
  await page.reload();
  await expect(page.locator('#items li')).toContainText('agent-tea');
});

test('human and SurfaceRelay execution converge on the same real HTMX request path', async ({ page }) => {
  await page.locator('input[name="name"]').fill('human-item');
  const humanRequestPromise = page.waitForRequest((request) =>
    request.method() === 'POST' && new URL(request.url()).pathname === '/items');
  await page.locator(sourceSelector).click();
  const humanRequest = await humanRequestPromise;

  await expect(page.locator('#items li')).toContainText('human-item');
  await page.waitForFunction((selector) => {
    const source = document.querySelector(selector);
    return source && !source.classList.contains(globalThis.htmx.config.requestClass);
  }, sourceSelector);

  const agentRequestPromise = page.waitForRequest((request) =>
    request.method() === 'POST' && new URL(request.url()).pathname === '/items');
  await page.evaluate(() => globalThis.surfaceRelayFixture.addItem('agent-item'));
  const agentRequest = await agentRequestPromise;

  expect(normalizedRequestSignature(agentRequest)).toEqual(
    normalizedRequestSignature(humanRequest),
  );
});

test('full page reload renews sourceId and bindingId together', async ({ page }) => {
  const first = await page.evaluate(() => {
    const binding = JSON.parse(document.querySelector('#surfacerelay-binding').textContent);
    return {
      sourceId: document.querySelector('[data-surfacerelay-htmx-source]')
        .getAttribute('data-surfacerelay-htmx-source'),
      bindingId: binding.bindingId,
      targetSourceId: binding.target.sourceId,
    };
  });

  await page.reload();
  await page.waitForFunction(() => globalThis.surfaceRelayFixture?.ready === true);

  const second = await page.evaluate(() => {
    const binding = JSON.parse(document.querySelector('#surfacerelay-binding').textContent);
    return {
      sourceId: document.querySelector('[data-surfacerelay-htmx-source]')
        .getAttribute('data-surfacerelay-htmx-source'),
      bindingId: binding.bindingId,
      targetSourceId: binding.target.sourceId,
    };
  });

  expect(first.targetSourceId).toBe(first.sourceId);
  expect(second.targetSourceId).toBe(second.sourceId);
  expect(second.sourceId).not.toBe(first.sourceId);
  expect(second.bindingId).not.toBe(first.bindingId);
});
