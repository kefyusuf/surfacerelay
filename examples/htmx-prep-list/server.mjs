import { randomUUID } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import { createServer } from 'node:http';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { createHtmxBindingTarget } from './.tmp/runtime/htmx-binding-descriptor.js';

const HOST = '127.0.0.1';
const PORT = 4173;
const MAX_FORM_BYTES = 16 * 1024;
const here = dirname(fileURLToPath(import.meta.url));
const runtimeRoot = resolve(here, '.tmp/runtime');
const clientPath = resolve(here, 'client.mjs');
const htmxPath = resolve(here, 'node_modules/htmx.org/dist/htmx.min.js');
const definitionPath = resolve(here, '../prep-list/action.add-item.json');
const runtimeFilePattern = /^[A-Za-z0-9._-]+\.js$/;

const definition = JSON.parse(await readFile(definitionPath, 'utf8'));
const state = {
  items: [],
  nextItemId: 1,
};

function jsonForHtmlScript(value) {
  return JSON.stringify(value).replaceAll('<', '\\u003c');
}

function escapeHtml(value) {
  return value
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function renderItem(item) {
  const name = escapeHtml(item.name);
  return `<li data-item-id="${item.id}" data-item-name="${name}">${name}</li>`;
}

function createPageBinding() {
  const sourceId = `prep-add-${randomUUID()}`;
  const bindingId = `htmx-binding-${randomUUID()}`;
  const target = createHtmxBindingTarget(definition, {
    sourceId,
    method: 'POST',
    path: '/items',
  });

  return {
    sourceId,
    binding: {
      bindingId,
      action: { id: definition.id, version: definition.version },
      driver: 'htmx',
      lifecycle: 'page',
      target,
      expiresAt: null,
    },
  };
}

function renderPage() {
  const { sourceId, binding } = createPageBinding();
  const items = state.items.map(renderItem).join('');
  return `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>SurfaceRelay HTMX Prep List</title>
</head>
<body>
  <main>
    <form id="prep-form">
      <input type="hidden" name="uiContext" value="prep-list">
      <input name="name">
      <button
        type="button"
        data-surfacerelay-htmx-source="${sourceId}"
        hx-post="/items"
        hx-target="#items"
        hx-swap="beforeend"
      >Add</button>
    </form>
    <ul id="items">${items}</ul>
  </main>
  <script type="application/json" id="surfacerelay-binding">${jsonForHtmlScript(binding)}</script>
  <script src="/vendor/htmx.min.js"></script>
  <script type="module" src="/client.mjs"></script>
</body>
</html>`;
}

function sendText(response, status, contentType, body) {
  response.writeHead(status, {
    'content-type': contentType,
    'content-length': Buffer.byteLength(body),
    'cache-control': 'no-store',
  });
  response.end(body);
}

function sendEmpty(response, status) {
  response.writeHead(status, {
    'content-length': '0',
    'cache-control': 'no-store',
  });
  response.end();
}

async function sendFile(response, path, contentType) {
  try {
    const body = await readFile(path);
    response.writeHead(200, {
      'content-type': contentType,
      'content-length': body.byteLength,
      'cache-control': 'no-store',
    });
    response.end(body);
  } catch {
    sendText(response, 404, 'text/plain; charset=utf-8', 'Not found');
  }
}

function readBoundedBody(request) {
  return new Promise((resolveBody, rejectBody) => {
    let size = 0;
    let oversized = false;
    const chunks = [];

    request.on('data', (chunk) => {
      size += chunk.length;
      if (size > MAX_FORM_BYTES) {
        oversized = true;
        return;
      }
      if (!oversized) {
        chunks.push(chunk);
      }
    });

    request.on('end', () => {
      if (oversized) {
        rejectBody(Object.assign(new Error('Request body is too large.'), { statusCode: 413 }));
        return;
      }
      resolveBody(Buffer.concat(chunks).toString('utf8'));
    });

    request.on('error', rejectBody);
  });
}

async function handleItemsPost(request, response) {
  const contentType = request.headers['content-type'];
  if (typeof contentType !== 'string' || !contentType.startsWith('application/x-www-form-urlencoded')) {
    sendText(response, 415, 'text/plain; charset=utf-8', 'Unsupported media type');
    return;
  }

  const body = await readBoundedBody(request);
  const params = new URLSearchParams(body);
  const names = params.getAll('name');
  if (names.length !== 1 || names[0].length === 0) {
    sendText(response, 422, 'text/plain; charset=utf-8', 'Invalid name');
    return;
  }

  const item = { id: state.nextItemId, name: names[0] };
  state.nextItemId += 1;
  state.items.push(item);
  sendText(response, 201, 'text/html; charset=utf-8', renderItem(item));
}

function rejectWrongMethod(response, pathname, method) {
  const expected = pathname === '/' || pathname === '/client.mjs' || pathname === '/vendor/htmx.min.js'
    ? 'GET'
    : pathname === '/items' || pathname === '/__test/reset'
      ? 'POST'
      : pathname.startsWith('/runtime/')
        ? 'GET'
        : null;

  if (expected !== null && method !== expected) {
    sendText(response, 405, 'text/plain; charset=utf-8', 'Method not allowed');
    return true;
  }
  return false;
}

const server = createServer(async (request, response) => {
  try {
    const url = new URL(request.url ?? '/', `http://${HOST}:${PORT}`);
    const pathname = url.pathname;
    const method = request.method ?? '';

    if (rejectWrongMethod(response, pathname, method)) {
      return;
    }

    if (method === 'GET' && pathname === '/') {
      sendText(response, 200, 'text/html; charset=utf-8', renderPage());
      return;
    }

    if (method === 'POST' && pathname === '/__test/reset') {
      state.items.length = 0;
      state.nextItemId = 1;
      sendEmpty(response, 204);
      return;
    }

    if (method === 'POST' && pathname === '/items') {
      await handleItemsPost(request, response);
      return;
    }

    if (method === 'GET' && pathname === '/client.mjs') {
      await sendFile(response, clientPath, 'text/javascript; charset=utf-8');
      return;
    }

    if (method === 'GET' && pathname === '/vendor/htmx.min.js') {
      await sendFile(response, htmxPath, 'text/javascript; charset=utf-8');
      return;
    }

    if (method === 'GET' && pathname.startsWith('/runtime/')) {
      const filename = pathname.slice('/runtime/'.length);
      if (!runtimeFilePattern.test(filename)) {
        sendText(response, 404, 'text/plain; charset=utf-8', 'Not found');
        return;
      }
      await sendFile(response, resolve(runtimeRoot, filename), 'text/javascript; charset=utf-8');
      return;
    }

    sendText(response, 404, 'text/plain; charset=utf-8', 'Not found');
  } catch (error) {
    const statusCode = Number.isInteger(error?.statusCode) ? error.statusCode : 500;
    if (statusCode !== 500) {
      sendText(response, statusCode, 'text/plain; charset=utf-8', error.message);
      return;
    }

    console.error(error);
    if (!response.headersSent) {
      sendText(response, 500, 'text/plain; charset=utf-8', 'Internal server error');
    } else {
      response.destroy();
    }
  }
});

await new Promise((resolveListen, rejectListen) => {
  server.once('error', rejectListen);
  server.listen(PORT, HOST, resolveListen);
});

console.log(`SurfaceRelay HTMX fixture listening on http://${HOST}:${PORT}`);
