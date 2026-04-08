// frontend/assets/js/main.js
// Global utilities for nikahin app

const API = '/api';

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

    const res = await fetch(API + endpoint, opts);
    const json = await res.json();
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
function showLoading(msg = 'Please wait…') {
  let el = document.getElementById('loading-overlay');
  if (!el) {
    el = document.createElement('div');
    el.id = 'loading-overlay'; el.className = 'loading-overlay';
    el.innerHTML = `<div class="spinner"></div><div class="loading-text">${msg}</div>`;
    document.body.appendChild(el);
  }
  el.querySelector('.loading-text').textContent = msg;
  el.classList.add('show');
}
function hideLoading() {
  const el = document.getElementById('loading-overlay');
  if (el) el.classList.remove('show');
}

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
      // Show preview
      let img = container.querySelector('img');
      if (!img) { img = document.createElement('img'); container.prepend(img); }
      img.src = res.url;
      img.style.display = 'block';
      const label = container.querySelector('.photo-upload-label');
      if (label) label.style.display = 'none';
      const icon = container.querySelector('.photo-upload-icon');
      if (icon) icon.style.display = 'none';
      if (onUrl) onUrl(res.url);
      toast('Photo uploaded', 'success');
    } catch (err) {
      toast(err.message, 'error');
    } finally {
      hideLoading();
    }
  });
}

// ── Date/Time Format ─────────────────────────────────────
function formatDate(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  return d.toLocaleDateString('id-ID', { weekday:'long', day:'numeric', month:'long', year:'numeric' });
}
function formatTime(timeStr) {
  if (!timeStr) return '';
  return timeStr.slice(0, 5) + ' WIB';
}

// ── Countdown ────────────────────────────────────────────
function startCountdown(targetDate, el) {
  function update() {
    const diff = new Date(targetDate) - new Date();
    if (diff <= 0) { el.textContent = 'Hari ini!'; return; }
    const d = Math.floor(diff / 86400000);
    const h = Math.floor((diff % 86400000) / 3600000);
    const m = Math.floor((diff % 3600000) / 60000);
    const s = Math.floor((diff % 60000) / 1000);
    el.innerHTML = `<span>${d}<small>hari</small></span><span>${h}<small>jam</small></span><span>${m}<small>menit</small></span><span>${s}<small>detik</small></span>`;
  }
  update();
  return setInterval(update, 1000);
}

// ── Slug ─────────────────────────────────────────────────
function makeSlug(groom, bride) {
  const clean = s => s.toLowerCase().replace(/[^a-z0-9]/g,'');
  return `${clean(groom)}-${clean(bride)}`;
}

// ── Relative Time ─────────────────────────────────────────
function relativeTime(dateStr) {
  const diff = Date.now() - new Date(dateStr);
  const mins  = Math.floor(diff / 60000);
  const hours = Math.floor(diff / 3600000);
  const days  = Math.floor(diff / 86400000);
  if (mins < 1)  return 'baru saja';
  if (mins < 60) return `${mins} menit lalu`;
  if (hours < 24)return `${hours} jam lalu`;
  return `${days} hari lalu`;
}
