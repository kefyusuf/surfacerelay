import { randomUUID } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import { createServer } from 'node:http';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { createHtmxBindingTarget } from './.tmp/runtime/htmx-binding-descriptor.js';

const HOST = '127.0.0.1';
const PORT = 4173;
const here = dirname(fileURLToPath(import.meta.url));
const runtimeRoot = resolve(here, '.tmp/runtime');
const clientPath = resolve(here, 'client.mjs');
const htmxPath = resolve(here, 'node_modules/htmx.org/dist/htmx.min.js');
const definitionPath = resolve(here, '../prep-list/action.add-item.json');
const runtimeFilePattern = /^[A-Za-z0-9._-]+\.js$/;

const definition = JSON.parse(await readFile(definitionPath, 'utf8'));

function jsonForHtmlScript(value) {
  return JSON.stringify(value).replaceAll('<', '\\u003c');
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
    <ul id="items"></ul>
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

const server = createServer(async (request, response) => {
  try {
    const url = new URL(request.url ?? '/', `http://${HOST}:${PORT}`);
    const pathname = url.pathname;

    if (request.method === 'GET' && pathname === '/') {
      sendText(response, 200, 'text/html; charset=utf-8', renderPage());
      return;
    }

    if (request.method === 'GET' && pathname === '/client.mjs') {
      await sendFile(response, clientPath, 'text/javascript; charset=utf-8');
      return;
    }

    if (request.method === 'GET' && pathname === '/vendor/htmx.min.js') {
      await sendFile(response, htmxPath, 'text/javascript; charset=utf-8');
      return;
    }

    if (request.method === 'GET' && pathname.startsWith('/runtime/')) {
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
