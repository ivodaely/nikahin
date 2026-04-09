// frontend/assets/js/main.js
// Global utilities for nikahin app

// ── Auto-detect API base path ────────────────────────────
// Works at root (localhost/) AND in a subdirectory (localhost/nikahin/)
const API = (function () {
  const p = location.pathname;
  for (const marker of ['/frontend/', '/invitation/']) {
    const i = p.indexOf(marker);
    if (i !== -1) return p.slice(0, i) + '/api';
  }
  return '/api';
})();

// ── Auth ─────────────────────────────────────────────────
const Auth = {
  token: () => localStorage.getItem('nk_token'),
  user:  () => JSON.parse(localStorage.getItem('nk_user') || 'null'),
  set:   (token, user) => {
    localStorage.setItem('nk_token', token);
    localStorage.setItem('nk_user', JSON.stringify(user));
  },
  clear: () => {
    localStorage.removeItem('nk_token');
    localStorage.removeItem('nk_user');
  },
  check: () => {
    if (!Auth.token()) { window.location = '/frontend/pages/login.html'; return false; }
    return true;
  }
};

// ── API Client ───────────────────────────────────────────
const api = {
  async request(method, endpoint, data = null, isFormData = false) {
    const headers = {};
    if (Auth.token()) headers['Authorization'] = 'Bearer ' + Auth.token();
    if (!isFormData) headers['Content-Type'] = 'application/json';

    const opts = { method, headers };
    if (data) opts.body = isFormData ? data : JSON.stringify(data);

    let res;
    try {
      res = await fetch(API + endpoint, opts);
    } catch (networkErr) {
      throw new Error('Tidak dapat terhubung ke server. Periksa koneksi atau konfigurasi server.');
    }

    // Read as text first so we always have the raw body for debugging
    const rawText = await res.text();

    let json;
    try {
      json = JSON.parse(rawText);
    } catch (_) {
      console.error(
        `%c[nikahin] Non-JSON response — ${method} ${API}${endpoint} (HTTP ${res.status})`,
        'color:red;font-weight:bold'
      );
      console.error('%cRaw server response:', 'color:orange', rawText);

      if (res.status === 404) {
        throw new Error(`Endpoint tidak ditemukan: ${endpoint}. Pastikan .htaccess sudah dikonfigurasi dan mod_rewrite aktif.`);
      }
      if (res.status >= 500) {
        throw new Error('Terjadi kesalahan pada server. Cek error log PHP.');
      }
      throw new Error(`Server tidak mengembalikan JSON yang valid (HTTP ${res.status}). Buka Console browser untuk lihat detail error.`);
    }

    if (!json.success) throw new Error(json.message || 'Request failed');
    return json.data;
  },

  get:    (ep)       => api.request('GET', ep),
  post:   (ep, data) => api.request('POST', ep, data),
  put:    (ep, data) => api.request('PUT', ep, data),
  delete: (ep)       => api.request('DELETE', ep),

  async upload(file) {
    const fd = new FormData();
    fd.append('file', file);
    return api.request('POST', '/upload', fd, true);
  }
};

// ── Toast ────────────────────────────────────────────────
let toastTimer;
function toast(msg, type = 'info') {
  let el = document.getElementById('toast');
  if (!el) {
    el = document.createElement('div');
    el.id = 'toast'; el.className = 'toast';
    document.body.appendChild(el);
  }
  el.textContent = msg;
  el.className = `toast show ${type}`;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.remove('show'), 3000);
}

// ── Loading ──────────────────────────────────────────────
function showLoading(msg = 'Memuat…') {
  let el = document.getElementById('loading-overlay');
  if (!el) {
    el = document.createElement('div');
    el.id = 'loading-overlay'; el.className = 'loading-overlay';
    el.innerHTML = `<div class="spinner"></div><div class="loading-text"></div>`;
    document.body.appendChild(el);
  }
  el.querySelector('.loading-text').textContent = msg;
  el.classList.add('show');
}

function hideLoading() {
  const el = document.getElementById('loading-overlay');
  if (el) el.classList.remove('show');
}

// ── Date / Time Formatting ───────────────────────────────
function formatDate(dateStr) {
  if (!dateStr) return '';
  // Append T00:00 to avoid timezone shifting the date
  const d = new Date(dateStr + 'T00:00:00');
  return d.toLocaleDateString('id-ID', {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
  });
}

function formatTime(timeStr) {
  if (!timeStr) return '';
  return timeStr.slice(0, 5) + ' WIB';
}

// ── Countdown Timer ──────────────────────────────────────
function startCountdown(targetDate, el) {
  function update() {
    const diff = new Date(targetDate) - new Date();
    if (!el) return;
    if (diff <= 0) { el.textContent = 'Hari ini!'; return; }
    const d = Math.floor(diff / 86400000);
    const h = Math.floor((diff % 86400000) / 3600000);
    const m = Math.floor((diff % 3600000) / 60000);
    const s = Math.floor((diff % 60000) / 1000);
    el.innerHTML =
      `<span>${d}<small>hari</small></span>` +
      `<span>${h}<small>jam</small></span>` +
      `<span>${m}<small>menit</small></span>` +
      `<span>${s}<small>detik</small></span>`;
  }
  update();
  return setInterval(update, 1000);
}

// ── Slug Helper ──────────────────────────────────────────
function makeSlug(groom, bride) {
  const clean = s => s.toLowerCase().replace(/[^a-z0-9]/g, '');
  return `${clean(groom)}-${clean(bride)}`;
}

// ── Relative Time ────────────────────────────────────────
function relativeTime(dateStr) {
  const diff  = Date.now() - new Date(dateStr);
  const mins  = Math.floor(diff / 60000);
  const hours = Math.floor(diff / 3600000);
  const days  = Math.floor(diff / 86400000);
  if (mins < 1)   return 'baru saja';
  if (mins < 60)  return `${mins} menit lalu`;
  if (hours < 24) return `${hours} jam lalu`;
  return `${days} hari lalu`;
}

// alias used in some pages
const relTime = relativeTime;

// ── Photo Upload Widget ──────────────────────────────────
function initPhotoUpload(containerId, onUrl) {
  const container = document.getElementById(containerId);
  if (!container) return;
  const input = container.querySelector('input[type=file]');
  if (!input) return;

  input.addEventListener('change', async (e) => {
    const file = e.target.files[0];
    if (!file) return;
    showLoading('Uploading…');
    try {
      const res = await api.upload(file);
      let img = container.querySelector('img');
      if (!img) { img = document.createElement('img'); container.prepend(img); }
      img.src = res.url; img.style.display = 'block';
      const label = container.querySelector('.photo-upload-label');
      if (label) label.style.display = 'none';
      const icon = container.querySelector('.photo-upload-icon');
      if (icon) icon.style.display = 'none';
      if (onUrl) onUrl(res.url);
      toast('Foto berhasil diupload', 'success');
    } catch (err) {
      toast(err.message, 'error');
    } finally {
      hideLoading();
    }
  });
}
