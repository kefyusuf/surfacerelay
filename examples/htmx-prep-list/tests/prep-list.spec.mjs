import { expect, test } from '@playwright/test';

const bindingSelector = '#surfacerelay-binding';
const sourceSelector = '[data-surfacerelay-htmx-source]';

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
