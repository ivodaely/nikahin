// frontend/assets/js/main.js
// Global utilities for nikahin app

// ── Auto-detect API base path ────────────────────────────
// Works at root (http://localhost/) AND in a subdirectory (http://localhost/nikahin/)
// All frontend pages live under /frontend/ or /invitation/, so we find that
// segment and replace everything after it with /api.
const API = (function () {
  const p = location.pathname; // e.g. /nikahin/frontend/pages/login.html
  for (const marker of ['/frontend/', '/invitation/']) {
    const i = p.indexOf(marker);
    if (i !== -1) return p.slice(0, i) + '/api';
  }
  return '/api'; // fallback for root installs
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

    let json;
    try {
      json = await res.json();
    } catch (_) {
      const raw = await res.text().catch(() => '(unreadable)');
      console.error(`[nikahin] Non-JSON response from ${method} ${endpoint} (HTTP ${res.status}):\n`, raw);
      if (res.status === 404) {
        throw new Error(`Endpoint tidak ditemukan: ${endpoint}. Pastikan .htaccess sudah dikonfigurasi dan mod_rewrite aktif.`);
      }
      if (res.status >= 500) {
        throw new Error('Terjadi kesalahan pada server. Cek error log PHP.');
      }
      throw new Error(`Server tidak mengembalikan JSON yang valid (HTTP ${res.status}). Periksa konfigurasi web server.`);
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
    el.id = 'loading-overlay';
    el.innerHTML = `<div class="loading-box"><div class="loading-spinner"></div><div class="loading-msg"></div></div>`;
    document.body.appendChild(el);
  }
  el.querySelector('.loading-msg').textContent = msg;
  el.classList.add('show');
}

function hideLoading() {
  const el = document.getElementById('loading-overlay');
  if (el) el.classList.remove('show');
}
