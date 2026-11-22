import http from 'node:http';
import crypto from 'node:crypto';
import { CanvasError, createCanvas, joinSession } from '@github/copilot-sdk/extension';
import declaration from './extension.json' with { type: 'json' };
import { CANVAS_ID, loadState, resolveDomain, saveState } from './storage.mjs';
import { renderPage } from './page.mjs';

const instances = new Map();

async function mutateInstance(instanceId, mutator) {
  const instance = instances.get(instanceId);
  if (!instance) throw new CanvasError('canvas_not_open', 'Open the canvas before using this action.');
  const state = await mutator(instance.state);
  await saveState(state);
  instance.state = state;
  return state;
}

const actionHandlers = {
  set_note: async ({ instanceId, input }) => {
    const note = typeof input?.note === 'string' ? input.note.slice(0, 4000) : '';
    await mutateInstance(instanceId, state => ({ ...state, note }));
    return { ok: true };
  },
  add_task: async ({ instanceId, input }) => {
    const title = typeof input?.title === 'string' ? input.title.trim().slice(0, 200) : '';
    if (!title) throw new CanvasError('invalid_task', 'Task title is required.');
    await mutateInstance(instanceId, state => ({
      ...state,
      tasks: [...state.tasks, { id: crypto.randomUUID(), title, done: false, createdAt: new Date().toISOString() }]
    }));
    return { ok: true };
  },
  toggle_task: async ({ instanceId, input }) => {
    const taskId = typeof input?.taskId === 'string' ? input.taskId : '';
    const live = instances.get(instanceId);
    if (!live?.state.tasks.some(task => task.id === taskId)) {
      throw new CanvasError('task_not_found', 'The requested task does not exist.');
    }
    await mutateInstance(instanceId, current => ({
      ...current,
      tasks: current.tasks.map(task => task.id === taskId ? { ...task, done: !task.done } : task)
    }));
    return { ok: true };
  },
  remove_task: async ({ instanceId, input }) => {
    const taskId = typeof input?.taskId === 'string' ? input.taskId : '';
    const live = instances.get(instanceId);
    if (!live?.state.tasks.some(task => task.id === taskId)) {
      throw new CanvasError('task_not_found', 'The requested task does not exist.');
    }
    await mutateInstance(instanceId, current => ({
      ...current,
      tasks: current.tasks.filter(task => task.id !== taskId)
    }));
    return { ok: true };
  },
  set_base_url: async ({ instanceId, input }) => {
    const domain = resolveDomain({ baseUrl: input?.baseUrl ?? '' });
    await mutateInstance(instanceId, state => ({ ...state, baseUrl: domain.baseUrl }));
    return { ok: true, baseUrl: domain.baseUrl };
  }
};

function sendJson(response, status, value) {
  response.writeHead(status, { 'Content-Type': 'application/json; charset=utf-8', 'Cache-Control': 'no-store' });
  response.end(JSON.stringify(value));
}

async function readJson(request) {
  const chunks = [];
  for await (const chunk of request) {
    chunks.push(chunk);
  }
  const raw = Buffer.concat(chunks).toString('utf8');
  if (raw.length > 1_000_000) throw new CanvasError('payload_too_large', 'Request body is too large.');
  try {
    return JSON.parse(raw);
  } catch {
    throw new CanvasError('invalid_json', 'Request body must contain valid JSON.');
  }
}

async function createServer(instanceId) {
  const instance = instances.get(instanceId);
  if (!instance) throw new CanvasError('canvas_not_open', 'Canvas instance is not open.');

  const server = http.createServer(async (request, response) => {
    try {
      const url = new URL(request.url || '/', 'http://127.0.0.1');
      if (request.method === 'GET' && url.pathname === '/') {
        response.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' });
        response.end(renderPage(instance.state));
      } else if (request.method === 'GET' && url.pathname === '/api/state') {
        sendJson(response, 200, instance.state);
      } else if (request.method === 'GET' && url.pathname === '/api/refresh') {
        const baseUrl = instance.state.baseUrl;
        if (!baseUrl) {
          sendJson(response, 200, { ok: false, detail: 'Belum ada base URL. Gunakan set_base_url.' });
          return;
        }
        try {
          const controller = new AbortController();
          const timeout = setTimeout(() => controller.abort(), 5000);
          let result;
          try {
            result = await fetch(baseUrl, { signal: controller.signal, redirect: 'follow' });
          } finally {
            clearTimeout(timeout);
          }
          sendJson(response, 200, {
            ok: result.ok,
            detail: result.ok ? `Website merespons HTTP ${result.status}.` : `Website merespons HTTP ${result.status}.`
          });
        } catch (error) {
          sendJson(response, 200, {
            ok: false,
            detail: error.name === 'AbortError' ? 'Pemeriksaan timeout setelah 5 detik.' : 'Website tidak dapat dihubungi.'
          });
        }
      } else if (request.method === 'POST' && url.pathname === '/api/action') {
        const body = await readJson(request);
        const name = typeof body?.name === 'string' ? body.name : '';
        const handler = actionHandlers[name];
        if (!handler) throw new CanvasError('unknown_action', 'The requested action is not available.');
        const result = await handler({ instanceId, input: body.input ?? {} });
        sendJson(response, 200, result);
      } else {
        sendJson(response, 404, { code: 'not_found', message: 'Route not found.' });
      }
    } catch (error) {
      if (error instanceof CanvasError) {
        sendJson(response, 400, { code: error.code, message: error.message });
      } else {
        sendJson(response, 500, { code: 'internal_error', message: 'An unexpected server error occurred.' });
      }
    }
  });

  await new Promise((resolve, reject) => {
    server.once('error', reject);
    server.listen(0, '127.0.0.1', resolve);
  });
  const address = server.address();
  if (!address || typeof address === 'string') {
    server.close();
    throw new CanvasError('canvas_server_failed', 'Could not start the canvas HTTP server.');
  }
  return { server, url: `http://127.0.0.1:${address.port}/` };
}

const session = await joinSession({
  canvases: declaration.canvases.map(canvas => createCanvas({
    ...canvas,
    actions: canvas.actions.map(action => ({
      ...action,
      handler: actionHandlers[action.name]
    })),
    open: async ({ instanceId, input }) => {
      const domain = resolveDomain(input);
      const state = await loadState(domain);
      const old = instances.get(instanceId);
      if (old?.server) await new Promise(resolve => old.server.close(resolve));
      const live = { state, domain, server: null };
      instances.set(instanceId, live);
      try {
        const created = await createServer(instanceId);
        live.server = created.server;
        session.log(`Opened ${CANVAS_ID} for instance ${instanceId}`);
        return created.url;
      } catch (error) {
        instances.delete(instanceId);
        throw error instanceof CanvasError
          ? error
          : new CanvasError('canvas_open_failed', `Could not open canvas: ${error.message}`);
      }
    },
    onClose: async ({ instanceId }) => {
      const live = instances.get(instanceId);
      if (live?.server) await new Promise(resolve => live.server.close(resolve));
      instances.delete(instanceId);
    }
  }))
});
