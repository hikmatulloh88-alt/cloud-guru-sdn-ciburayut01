import crypto from 'node:crypto';
import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { CanvasError } from '@github/copilot-sdk/extension';

export const CANVAS_ID = 'cloud-guru-school-dashboard';
const FORMAT = 'copilot-canvas';
const VERSION = 1;

function copilotHome() {
  const configured = process.env.COPILOT_HOME;
  return typeof configured === 'string' && configured.length > 0
    ? configured
    : path.join(os.homedir(), '.copilot');
}

function validBaseUrl(value) {
  if (typeof value !== 'string' || value.trim() === '') return '';
  const trimmed = value.trim();
  try {
    const url = new URL(trimmed);
    return url.protocol === 'http:' || url.protocol === 'https:' ? url.href.replace(/\/$/, '') : '';
  } catch {
    return '';
  }
}

export function resolveDomain(input) {
  if (input !== null && typeof input === 'object' && Array.isArray(input) === false) {
    const baseUrl = validBaseUrl(input.baseUrl);
    const workspaceRoot = typeof input.workspaceRoot === 'string' ? input.workspaceRoot.trim().slice(0, 2048) : '';
    if (typeof input.baseUrl === 'string' && input.baseUrl.trim() !== '' && baseUrl === '') {
      throw new CanvasError('invalid_base_url', 'baseUrl must be a valid HTTP or HTTPS URL.');
    }
    return { baseUrl, workspaceRoot, identity: JSON.stringify({ workspaceRoot }) };
  }
  return { baseUrl: '', workspaceRoot: '', identity: JSON.stringify({ baseUrl: '', workspaceRoot: '' }) };
}

function dataPath(identity) {
  const key = crypto.createHash('sha256').update(identity).digest('hex');
  return path.join(copilotHome(), 'extension-data', CANVAS_ID, `${key}.json`);
}

function defaultState(domain) {
  return {
    format: FORMAT,
    version: VERSION,
    identity: domain.identity,
    baseUrl: domain.baseUrl,
    note: '',
    tasks: []
  };
}

function validateState(value, identity) {
  if (!value || typeof value !== 'object' || Array.isArray(value)) {
    throw new CanvasError('corrupt_canvas_state', 'The canvas state is not a valid object.');
  }
  if (value.format !== FORMAT || value.version !== VERSION) {
    throw new CanvasError('unsupported_canvas_state', 'The canvas state format or version is not recognized.');
  }
  if (value.identity !== identity || typeof value.note !== 'string' || !Array.isArray(value.tasks)) {
    throw new CanvasError('corrupt_canvas_state', 'The canvas state is corrupted or belongs to another dashboard.');
  }
  for (const task of value.tasks) {
    if (!task || typeof task.id !== 'string' || typeof task.title !== 'string' ||
        typeof task.done !== 'boolean' || typeof task.createdAt !== 'string') {
      throw new CanvasError('corrupt_canvas_state', 'The canvas task data is corrupted.');
    }
  }
  return value;
}

export async function loadState(domain) {
  const target = dataPath(domain.identity);
  try {
    const raw = await fs.readFile(target, 'utf8');
    let value;
    try {
      value = JSON.parse(raw);
    } catch {
      throw new CanvasError('corrupt_canvas_state', 'The canvas state contains invalid JSON.');
    }
    return validateState(value, domain.identity);
  } catch (error) {
    if (error?.code === 'ENOENT') return defaultState(domain);
    if (error instanceof CanvasError) throw error;
    throw new CanvasError('canvas_storage_read_failed', `Could not read canvas state: ${error.message}`);
  }
}

export async function saveState(state) {
  const target = dataPath(state.identity);
  const directory = path.dirname(target);
  const temp = path.join(directory, `.${path.basename(target)}.${process.pid}.${crypto.randomUUID()}.tmp`);
  try {
    await fs.mkdir(directory, { recursive: true });
    await fs.writeFile(temp, JSON.stringify(state, null, 2), { encoding: 'utf8', flag: 'wx', mode: 0o600 });
    await fs.rename(temp, target);
  } catch (error) {
    await fs.rm(temp, { force: true }).catch(() => {});
    throw new CanvasError('canvas_storage_write_failed', `Could not save canvas state: ${error.message}`);
  }
}
