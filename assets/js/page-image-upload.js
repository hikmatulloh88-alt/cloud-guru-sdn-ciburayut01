(function () {
  'use strict';

  const endpoint = '/admin/tampilan/upload_page_image.php';
  const widgetClass = 'cg-direct-page-image-upload';
  let csrfToken = null;
  let csrfPromise = null;

  const css = document.createElement('style');
  css.textContent = `
    .${widgetClass}{margin-top:10px;padding:12px 13px;border:1px dashed #93b8e8;border-radius:10px;background:#f5f9ff;display:grid;gap:8px;max-width:100%}
    .${widgetClass} .cg-upload-heading{font-size:13px;font-weight:750;color:#174a88}
    .${widgetClass} .cg-upload-hint,.${widgetClass} .cg-upload-status{font-size:12px;line-height:1.5;color:#52657a;margin:0}
    .${widgetClass} input[type=file]{display:block;width:100%;max-width:100%;box-sizing:border-box;padding:8px;background:#fff;border:1px solid #cbd7e8;border-radius:8px}
    .${widgetClass} .cg-upload-status[data-kind=error]{color:#b42318}
    .${widgetClass} .cg-upload-status[data-kind=success]{color:#137333}
    .${widgetClass} .cg-upload-preview{display:none;max-width:220px;max-height:140px;object-fit:cover;border-radius:8px;border:1px solid #d6deea}
    .${widgetClass} .cg-upload-preview.is-visible{display:block}
  `;
  (document.head || document.documentElement).appendChild(css);

  function normalise(value) {
    return String(value || '').replace(/\s+/g, ' ').trim().replace(/:$/, '').toLowerCase();
  }

  function findImageUrlInput(label) {
    if (label.htmlFor) {
      const byId = document.getElementById(label.htmlFor);
      if (byId && byId.matches('input:not([type=hidden]):not([type=file]):not([type=submit])')) return byId;
    }
    if (label.control && label.control.matches('input:not([type=hidden]):not([type=file]):not([type=submit])')) return label.control;
    let node = label.parentElement;
    for (let depth = 0; node && depth < 4; depth++, node = node.parentElement) {
      const inputs = Array.from(node.querySelectorAll('input:not([type=hidden]):not([type=file]):not([type=submit]):not([type=button])'))
        .filter(el => !el.classList.contains('cg-direct-upload-file'));
      if (inputs.length === 1) return inputs[0];
      if (inputs.length > 1) {
        const matching = inputs.find(el => /gambar|image|url/i.test((el.name || '') + ' ' + (el.id || '') + ' ' + (el.placeholder || '')));
        if (matching) return matching;
      }
    }
    const next = label.nextElementSibling;
    if (next && next.matches('input:not([type=hidden]):not([type=file]):not([type=submit])')) return next;
    return null;
  }

  async function getCsrfToken() {
    if (csrfToken) return csrfToken;
    if (!csrfPromise) {
      csrfPromise = fetch(endpoint + '?csrf=1', {
        method: 'GET',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
        cache: 'no-store'
      }).then(async response => {
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.ok || !data.csrf_token) {
          throw new Error(data.message || 'Token keamanan tidak dapat dimuat. Muat ulang halaman.');
        }
        csrfToken = data.csrf_token;
        return csrfToken;
      }).finally(() => { csrfPromise = null; });
    }
    return csrfPromise;
  }

  function addUploader(label, urlInput) {
    if (urlInput.dataset.cgDirectUploader === '1') return;
    urlInput.dataset.cgDirectUploader = '1';

    const widget = document.createElement('section');
    widget.className = widgetClass;
    widget.innerHTML = `
      <div class="cg-upload-heading">📷 Upload gambar langsung dari komputer</div>
      <p class="cg-upload-hint">Pilih JPG, PNG, WEBP, atau GIF (maksimal 8 MB). Setelah upload, alamat gambar akan terisi otomatis.</p>
      <input class="cg-direct-upload-file" type="file" accept="image/jpeg,image/png,image/webp,image/gif" aria-label="Pilih gambar untuk halaman ini">
      <p class="cg-upload-status" aria-live="polite">Gambar akan dipasang setelah Anda menyimpan draft dan memublikasikan halaman.</p>
      <img class="cg-upload-preview" alt="Pratinjau gambar yang diunggah">
    `;
    const fileInput = widget.querySelector('input[type=file]');
    const status = widget.querySelector('.cg-upload-status');
    const preview = widget.querySelector('.cg-upload-preview');

    function setStatus(message, kind) {
      status.textContent = message;
      status.dataset.kind = kind || '';
    }

    fileInput.addEventListener('change', async function () {
      const file = fileInput.files && fileInput.files[0];
      if (!file) return;
      if (file.size > 8 * 1024 * 1024) {
        fileInput.value = '';
        setStatus('Ukuran file melebihi 8 MB. Pilih gambar yang lebih kecil.', 'error');
        return;
      }
      if (!/^image\/(jpeg|png|webp|gif)$/i.test(file.type)) {
        fileInput.value = '';
        setStatus('Format tidak didukung. Pilih JPG, PNG, WEBP, atau GIF.', 'error');
        return;
      }

      fileInput.disabled = true;
      setStatus('Sedang mengunggah gambar…', '');
      try {
        const token = await getCsrfToken();
        const form = new FormData();
        form.append('image', file, file.name);
        form.append('csrf_token', token);
        const response = await fetch(endpoint, {
          method: 'POST',
          body: form,
          credentials: 'same-origin',
          headers: { 'Accept': 'application/json' }
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.ok || !data.url) {
          throw new Error(data.message || 'Upload gagal. Coba lagi.');
        }

        urlInput.value = data.url;
        urlInput.dispatchEvent(new Event('input', { bubbles: true }));
        urlInput.dispatchEvent(new Event('change', { bubbles: true }));
        preview.src = data.url;
        preview.classList.add('is-visible');
        setStatus('✓ ' + (data.message || 'Gambar berhasil diunggah. URL sudah terisi.'), 'success');
      } catch (error) {
        setStatus(error && error.message ? error.message : 'Upload gagal. Periksa koneksi lalu coba lagi.', 'error');
      } finally {
        fileInput.disabled = false;
      }
    });

    const fieldWrap = urlInput.parentElement || label.parentElement;
    if (fieldWrap && fieldWrap.parentElement) {
      fieldWrap.insertAdjacentElement('afterend', widget);
    } else {
      label.insertAdjacentElement('afterend', widget);
    }
  }

  function install() {
    const labels = Array.from(document.querySelectorAll('label'));
    const label = labels.find(el => normalise(el.textContent) === 'gambar url');
    if (!label) return;
    const input = findImageUrlInput(label);
    if (input) addUploader(label, input);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', install, { once: true });
  } else {
    install();
  }
  const observer = new MutationObserver(() => install());
  observer.observe(document.documentElement, { childList: true, subtree: true });
})();
