function escapeHtml(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function linkUrl(baseUrl, route) {
  if (!baseUrl) return route;
  return `${baseUrl}${route}`;
}

export function renderPage(state) {
  const baseUrl = typeof state.baseUrl === 'string' ? state.baseUrl : '';
  const tasks = state.tasks.map(task => `
    <li class="task ${task.done ? 'done' : ''}" data-task-id="${escapeHtml(task.id)}">
      <label>
        <input type="checkbox" ${task.done ? 'checked' : ''} aria-label="Complete task: ${escapeHtml(task.title)}">
        <span>${escapeHtml(task.title)}</span>
      </label>
      <button type="button" class="remove" aria-label="Remove task: ${escapeHtml(task.title)}">Hapus</button>
    </li>`).join('');

  return `<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>School Website Dashboard</title>
  <style>
    :root { color-scheme: light dark; --accent:#1565c0; --bg:#eef4fb; --panel:#fff; --text:#17324d; --muted:#60758a; --ok:#18794e; }
    @media (prefers-color-scheme: dark) { :root { --bg:#101820; --panel:#182532; --text:#edf5fc; --muted:#b2c2d2; } }
    * { box-sizing:border-box; }
    body { margin:0; font:15px/1.5 system-ui,-apple-system,sans-serif; background:var(--bg); color:var(--text); }
    main { max-width:980px; margin:auto; padding:24px; }
    header { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; margin-bottom:18px; }
    h1 { margin:0 0 4px; font-size:28px; } h2 { margin:0 0 12px; font-size:18px; }
    .muted { color:var(--muted); }
    .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:14px; }
    .panel { background:var(--panel); border:1px solid color-mix(in srgb,var(--text) 12%,transparent); border-radius:14px; padding:16px; box-shadow:0 8px 24px rgb(20 50 80 / 8%); }
    .status { display:flex; gap:10px; align-items:center; }
    .dot { width:12px; height:12px; border-radius:50%; background:var(--ok); box-shadow:0 0 0 5px rgb(24 121 78 / 12%); }
    .links { display:grid; gap:8px; }
    .links a { color:var(--text); text-decoration:none; border:1px solid color-mix(in srgb,var(--text) 16%,transparent); border-radius:8px; padding:9px 11px; }
    .links a:hover { border-color:var(--accent); color:var(--accent); }
    :focus-visible { outline:3px solid #efb300; outline-offset:2px; }
    textarea,input[type=text] { width:100%; border:1px solid color-mix(in srgb,var(--text) 22%,transparent); border-radius:8px; background:var(--panel); color:var(--text); padding:9px; }
    textarea { min-height:92px; resize:vertical; }
    .task-row { display:flex; gap:8px; } .task-row input { flex:1; }
    button { border:0; border-radius:8px; background:var(--accent); color:white; padding:9px 12px; cursor:pointer; }
    .remove { margin-left:auto; background:transparent; color:#b42318; padding:5px 8px; }
    ul { list-style:none; margin:12px 0 0; padding:0; }
    .task { display:flex; align-items:center; gap:8px; border-top:1px solid color-mix(in srgb,var(--text) 10%,transparent); padding:8px 0; }
    .task label { display:flex; align-items:center; gap:8px; flex:1; }
    .task.done span { text-decoration:line-through; color:var(--muted); }
    #status { min-height:24px; margin-top:10px; }
  </style>
</head>
<body>
<main>
  <header>
    <div><h1>School Website Dashboard</h1><div class="muted">SDN Ciburayut 01</div></div>
    <button id="refresh" type="button">Periksa status</button>
  </header>
  <div class="grid">
    <section class="panel" aria-labelledby="status-heading">
      <h2 id="status-heading">Status</h2>
      <div class="status"><span class="dot" id="status-dot"></span><strong id="status-label">Siap diperiksa</strong></div>
      <div class="muted" id="status-detail">${baseUrl ? escapeHtml(baseUrl) : 'Belum ada base URL'}</div>
    </section>
    <section class="panel" aria-labelledby="links-heading">
      <h2 id="links-heading">Tindakan cepat</h2>
      <nav class="links" aria-label="Website quick links">
        <a href="${escapeHtml(linkUrl(baseUrl, '/'))}" target="_blank" rel="noopener">Buka situs</a>
        <a href="${escapeHtml(linkUrl(baseUrl, '/dashboard'))}" target="_blank" rel="noopener">Dashboard</a>
        <a href="${escapeHtml(linkUrl(baseUrl, '/builder'))}" target="_blank" rel="noopener">Pembuat situs</a>
        <a href="${escapeHtml(linkUrl(baseUrl, '/self-healing/dashboard.php'))}" target="_blank" rel="noopener">Self-healing</a>
      </nav>
    </section>
  </div>
  <section class="panel" style="margin-top:14px" aria-labelledby="task-heading">
    <h2 id="task-heading">Catatan operasi</h2>
    <label for="note">Catatan</label>
    <textarea id="note" maxlength="4000">${escapeHtml(state.note)}</textarea>
    <div class="task-row" style="margin-top:12px">
      <input id="new-task" type="text" maxlength="200" aria-label="Judul tugas" placeholder="Tambahkan tugas">
      <button id="add-task" type="button">Tambah</button>
    </div>
    <ul id="tasks">${tasks}</ul>
  </section>
  <div id="status" role="status" aria-live="polite"></div>
</main>
<script>
  const statusText = document.querySelector('#status');
  async function action(name, input) {
    statusText.textContent = 'Memproses...';
    const response = await fetch('/api/action', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({name, input})});
    const result = await response.json();
    if (!response.ok) throw new Error(result.message || 'Permintaan gagal');
    statusText.textContent = 'Tersimpan.';
    return result;
  }
  document.querySelector('#note').addEventListener('change', async (event) => {
    try { await action('set_note', {note:event.target.value}); } catch (error) { statusText.textContent = error.message; }
  });
  document.querySelector('#add-task').addEventListener('click', async () => {
    const input = document.querySelector('#new-task');
    const title = input.value.trim();
    if (!title) { statusText.textContent = 'Judul tugas wajib diisi.'; return; }
    try { await action('add_task', {title}); location.reload(); } catch (error) { statusText.textContent = error.message; }
  });
  document.querySelector('#tasks').addEventListener('change', async (event) => {
    if (!event.target.matches('input[type=checkbox]')) return;
    const id = event.target.closest('[data-task-id]').dataset.taskId;
    try { await action('toggle_task', {taskId:id}); location.reload(); } catch (error) { statusText.textContent = error.message; }
  });
  document.querySelector('#tasks').addEventListener('click', async (event) => {
    if (!event.target.matches('.remove')) return;
    const id = event.target.closest('[data-task-id]').dataset.taskId;
    try { await action('remove_task', {taskId:id}); location.reload(); } catch (error) { statusText.textContent = error.message; }
  });
  document.querySelector('#refresh').addEventListener('click', async () => {
    const detail = document.querySelector('#status-detail');
    document.querySelector('#status-label').textContent = 'Memeriksa...';
    try {
      const response = await fetch('/api/refresh');
      const result = await response.json();
      document.querySelector('#status-label').textContent = result.ok ? 'Sistem tersedia' : 'Perlu perhatian';
      detail.textContent = result.detail;
      document.querySelector('#status-dot').style.background = result.ok ? '#18794e' : '#b42318';
    } catch (error) {
      document.querySelector('#status-label').textContent = 'Tidak dapat diperiksa';
      detail.textContent = error.message;
      document.querySelector('#status-dot').style.background = '#b42318';
    }
  });
</script>
</body>
</html>`;
}
