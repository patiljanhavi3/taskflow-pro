// ============================================================
// script.js — TaskFlow Pro Dashboard Logic
// ============================================================

'use strict';

// ─── STATE ───────────────────────────────────────────────────
const state = {
  filter:       'all',
  search:       '',
  sort:         'recent',
  tasks:        [],
  addFormOpen:  false,
  pomoDuration: 25 * 60,
  pomoRemaining:25 * 60,
  pomoRunning:  false,
  pomoTimer:    null,
  pomoSessions: parseInt(localStorage.getItem('pomoSessions') || '0'),
};

const csrf = () => document.getElementById('csrfToken').value;

// ─── INIT ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  loadTasks();
  initPomodoro();
  initDragDrop();
  initTheme();
  initSearch();
  requestNotificationPermission();
  updateProgressBar();

  // Close user menu on outside click
  document.addEventListener('click', e => {
    const menu = document.getElementById('userMenu');
    const avatar = document.querySelector('.nav-avatar');
    if (!menu.contains(e.target) && !avatar.contains(e.target)) {
      menu.classList.remove('open');
    }
  });

  // Keyboard shortcut Cmd/Ctrl+K → focus search
  document.addEventListener('keydown', e => {
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
      e.preventDefault();
      document.getElementById('searchInput')?.focus();
    }
  });

  // Update pomo sessions count
  document.getElementById('pomoSessions').textContent = state.pomoSessions;
});

// ─── TASKS API ────────────────────────────────────────────────
async function apiPost(data) {
  data.csrf_token = csrf();
  const form = new FormData();
  for (const [k,v] of Object.entries(data)) form.append(k, v);
  const res = await fetch('actions.php', { method: 'POST', body: form });
  return res.json();
}

async function loadTasks() {
  const params = new URLSearchParams({
    action: 'get',
    filter: state.filter,
    search: state.search,
    sort:   document.getElementById('sortSelect')?.value || 'recent',
  });
  const list = document.getElementById('taskList');
  try {
    const data = await fetch('actions.php?' + params).then(r => r.json());
    state.tasks = data.tasks || [];
    renderTasks(state.tasks);
    updateProgressBar();
  } catch {
    list.innerHTML = '<div class="empty-state"><div class="empty-icon">⚠</div><div class="empty-title">Failed to load tasks</div></div>';
  }
}

// ─── RENDER TASKS ─────────────────────────────────────────────
function renderTasks(tasks) {
  const list = document.getElementById('taskList');
  if (!tasks.length) {
    const msgs = {
      all:       ['Nothing here yet', 'Add your first task above ✦'],
      pending:   ['All caught up!', 'No pending tasks 🎉'],
      completed: ['No completed tasks yet', 'Complete some tasks to see them here'],
    };
    const [title, sub] = msgs[state.filter] || msgs.all;
    list.innerHTML = `
      <div class="empty-state">
        <div class="empty-icon">${state.filter === 'completed' ? '🏆' : '📝'}</div>
        <div class="empty-title">${title}</div>
        <div class="empty-sub">${sub}</div>
      </div>`;
    return;
  }

  list.innerHTML = '';
  tasks.forEach((t, idx) => {
    const el = createTaskEl(t);
    el.style.animationDelay = `${idx * 0.04}s`;
    list.appendChild(el);
  });
  initDragDrop();
}

function createTaskEl(t) {
  const div = document.createElement('div');
  div.className = `task-item priority-${t.priority} ${t.status === 'completed' ? 'completed' : ''}`;
  div.dataset.id = t.id;
  div.draggable = true;

  const due    = t.due_date ? formatDue(t.due_date) : '';
  const isOver = t.due_date && new Date(t.due_date) < new Date() && t.status !== 'completed';

  div.innerHTML = `
    <button class="task-check ${t.status === 'completed' ? 'checked' : ''}"
            onclick="toggleTask(${t.id}, '${t.status}')" title="${t.status === 'completed' ? 'Mark pending' : 'Complete'}">
    </button>
    <div class="task-body">
      <div class="task-title">${esc(t.title)}</div>
      ${t.description ? `<div class="task-desc">${esc(t.description)}</div>` : ''}
      <div class="task-meta">
        <span class="task-badge badge-${t.priority}">${priorityLabel(t.priority)}</span>
        ${due ? `<span class="task-due ${isOver ? 'overdue' : ''}">📅 ${due}</span>` : ''}
      </div>
    </div>
    <div class="task-actions">
      ${t.status === 'completed'
        ? `<button class="task-btn reopen" onclick="reopenTask(${t.id})" title="Reopen">↩</button>`
        : ''}
      <button class="task-btn delete" onclick="deleteTask(${t.id})" title="Delete">✕</button>
    </div>`;
  return div;
}

function priorityLabel(p) {
  return { low: '🟢 Low', medium: '🟡 Med', high: '🔴 High' }[p] || p;
}

function formatDue(dateStr) {
  const d = new Date(dateStr + 'T00:00:00');
  return d.toLocaleDateString('en', { month: 'short', day: 'numeric' });
}

function esc(str) {
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

// ─── ADD TASK ─────────────────────────────────────────────────
async function addTask() {
  const title    = document.getElementById('taskTitle').value.trim();
  const desc     = document.getElementById('taskDesc').value.trim();
  const priority = document.getElementById('taskPriority').value;
  const due      = document.getElementById('taskDue').value;

  if (!title) { toast('Please enter a task title', 'error'); return; }

  const data = await apiPost({ action: 'add', title, description: desc, priority, due_date: due });
  if (data.success) {
    document.getElementById('taskTitle').value    = '';
    document.getElementById('taskDesc').value     = '';
    document.getElementById('taskDue').value      = '';
    document.getElementById('taskPriority').value = 'medium';
    toast('Task added! ✦', 'success');
    loadTasks();
    updateStatsRow();
  } else {
    toast(data.error || 'Failed to add task', 'error');
  }
}

// Enter key in title → add
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('taskTitle')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') addTask();
  });
});

// ─── TOGGLE COMPLETE ──────────────────────────────────────────
async function toggleTask(id, currentStatus) {
  const action = currentStatus === 'completed' ? 'reopen' : 'complete';
  const data   = await apiPost({ action, id });
  if (data.success) {
    if (action === 'complete') {
      const taskEl = document.querySelector(`.task-item[data-id="${id}"]`);
      if (taskEl) {
        taskEl.classList.add('completed');
        const check = taskEl.querySelector('.task-check');
        if (check) check.classList.add('checked');
      }
      confettiBurst();
      toast('Task completed! 🎉', 'success');
    } else {
      toast('Task reopened', 'info');
    }
    setTimeout(() => { loadTasks(); updateStatsRow(); }, 500);
  }
}

async function reopenTask(id) { await toggleTask(id, 'completed'); }

// ─── DELETE TASK ──────────────────────────────────────────────
async function deleteTask(id) {
  const taskEl = document.querySelector(`.task-item[data-id="${id}"]`);
  if (taskEl) {
    taskEl.style.transition = 'all 0.25s';
    taskEl.style.opacity    = '0';
    taskEl.style.transform  = 'translateX(20px)';
  }
  const data = await apiPost({ action: 'delete', id });
  if (data.success) {
    setTimeout(() => { loadTasks(); updateStatsRow(); }, 250);
    toast('Task deleted', 'info');
  }
}

// ─── FILTERS ──────────────────────────────────────────────────
function setFilter(btn, filter) {
  state.filter = filter;
  document.querySelectorAll('.filter-tab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  loadTasks();
}

// ─── SEARCH ───────────────────────────────────────────────────
function initSearch() {
  const inp = document.getElementById('searchInput');
  if (!inp) return;
  let timeout;
  inp.addEventListener('input', () => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
      state.search = inp.value.trim();
      loadTasks();
    }, 280);
  });
}

// ─── ADD FORM TOGGLE ──────────────────────────────────────────
function toggleAddForm() {
  state.addFormOpen = !state.addFormOpen;
  const body   = document.getElementById('addTaskBody');
  const toggle = document.getElementById('addToggle');
  body.classList.toggle('open', state.addFormOpen);
  toggle.classList.toggle('open', state.addFormOpen);
  if (state.addFormOpen) document.getElementById('taskTitle').focus();
}

// ─── DRAG & DROP ──────────────────────────────────────────────
function initDragDrop() {
  const list  = document.getElementById('taskList');
  if (!list) return;
  let dragged = null;

  list.querySelectorAll('.task-item').forEach(item => {
    item.addEventListener('dragstart', e => {
      dragged = item;
      setTimeout(() => item.classList.add('dragging'), 0);
      e.dataTransfer.effectAllowed = 'move';
    });
    item.addEventListener('dragend', () => {
      item.classList.remove('dragging');
      list.querySelectorAll('.task-item').forEach(i => i.classList.remove('drag-over'));
      saveSortOrder();
    });
    item.addEventListener('dragover', e => {
      e.preventDefault();
      if (item !== dragged) {
        list.querySelectorAll('.task-item').forEach(i => i.classList.remove('drag-over'));
        item.classList.add('drag-over');
        const items  = [...list.querySelectorAll('.task-item')];
        const idx    = items.indexOf(item);
        const dragIdx = items.indexOf(dragged);
        if (idx > dragIdx) item.after(dragged); else item.before(dragged);
      }
    });
  });
}

async function saveSortOrder() {
  const ids = [...document.querySelectorAll('.task-item')].map(el => el.dataset.id);
  await apiPost({ action: 'update_order', ids: JSON.stringify(ids) });
}

// ─── STATS ROW UPDATE ─────────────────────────────────────────
async function updateStatsRow() {
  const params = new URLSearchParams({ action: 'analytics' });
  try {
    const data = await fetch('actions.php?' + params).then(r => r.json());
    const row  = document.getElementById('statsRow');
    if (!row) return;
    const nums = row.querySelectorAll('.stat-num');
    if (nums[0]) nums[0].textContent = data.total || 0;
    if (nums[1]) nums[1].textContent = data.pending || 0;
    if (nums[2]) nums[2].textContent = data.completed || 0;
    updateProgressBar(data.completed, data.total);
  } catch {}
}

// ─── PROGRESS BAR ─────────────────────────────────────────────
function updateProgressBar(completed, total) {
  // compute from tasks in state if not given
  if (completed === undefined) {
    completed = state.tasks.filter(t => t.status === 'completed').length;
    total     = state.tasks.length;
  }
  const pct     = total > 0 ? Math.round((completed / total) * 100) : 0;
  const bar     = document.getElementById('progressBar');
  const pctEl   = document.getElementById('progressPct');
  const subEl   = document.getElementById('progressSub');
  if (bar)    bar.style.width     = pct + '%';
  if (pctEl)  pctEl.textContent   = pct + '%';
  if (subEl) {
    if (pct === 100 && total > 0) subEl.textContent = 'All done! You crushed it! 🏆';
    else if (pct >= 75) subEl.textContent = 'Almost there! 🔥';
    else if (pct >= 50) subEl.textContent = 'Halfway there! 💪';
    else if (pct > 0)   subEl.textContent = 'Keep going! 🚀';
    else                subEl.textContent = 'Ready to start? ✦';
  }
}

// ─── THEME ───────────────────────────────────────────────────
function initTheme() {
  const btn  = document.getElementById('themeToggle');
  const html = document.documentElement;
  const icon = document.getElementById('themeIcon');
  if (!btn) return;

  btn.addEventListener('click', async () => {
    const current  = html.getAttribute('data-theme') || 'dark';
    const next     = current === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    if (icon) icon.textContent = next === 'dark' ? '☀' : '🌙';
    await apiPost({ action: 'update_theme', theme: next });
  });
}

function toggleUserMenu() {
  document.getElementById('userMenu').classList.toggle('open');
}

// ─── TOAST ───────────────────────────────────────────────────
function toast(msg, type = 'info') {
  const container = document.getElementById('toastContainer');
  const el        = document.createElement('div');
  const icons     = { success: '✓', error: '⚠', info: '◆' };
  el.className    = `toast toast-${type}`;
  el.innerHTML    = `<span class="toast-dot">${icons[type] || '◆'}</span> ${esc(msg)}`;
  container.appendChild(el);
  setTimeout(() => {
    el.classList.add('out');
    el.addEventListener('animationend', () => el.remove(), { once: true });
  }, 3000);
}

// ─── CONFETTI ─────────────────────────────────────────────────
function confettiBurst() {
  const canvas  = document.getElementById('confettiCanvas');
  if (!canvas) return;
  const ctx     = canvas.getContext('2d');
  canvas.width  = window.innerWidth;
  canvas.height = window.innerHeight;
  const particles = Array.from({ length: 80 }, () => ({
    x:  Math.random() * canvas.width,
    y:  Math.random() * canvas.height * 0.4,
    r:  Math.random() * 6 + 2,
    d:  Math.random() * 2 + 1,
    color: ['#a78bfa','#ffb899','#34d399','#fbbf24','#fb7185','#60a5fa'][Math.floor(Math.random() * 6)],
    tilt: Math.random() * 10 - 5,
    tiltSpeed: Math.random() * 0.1 + 0.05,
    angle: 0,
  }));

  let frame = 0;
  function draw() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    particles.forEach(p => {
      p.angle += p.tiltSpeed;
      p.tilt   = Math.sin(p.angle) * 12;
      p.y     += p.d + Math.random() * 0.5;
      p.x     += Math.sin(frame / 20);
      ctx.beginPath();
      ctx.lineWidth   = p.r;
      ctx.strokeStyle = p.color;
      ctx.moveTo(p.x + p.tilt, p.y);
      ctx.lineTo(p.x, p.y + p.r);
      ctx.stroke();
    });
    frame++;
    if (frame < 120) requestAnimationFrame(draw);
    else ctx.clearRect(0, 0, canvas.width, canvas.height);
  }
  draw();
}

// ─── POMODORO ─────────────────────────────────────────────────
function initPomodoro() {
  renderPomoTime(state.pomoRemaining);
  updatePomoRing(state.pomoRemaining, state.pomoDuration);
}

function setPomoMode(btn, minutes) {
  document.querySelectorAll('.pomo-mode-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  pomodoroAction('reset');
  state.pomoDuration  = minutes * 60;
  state.pomoRemaining = minutes * 60;
  renderPomoTime(state.pomoRemaining);
  updatePomoRing(state.pomoRemaining, state.pomoDuration);
  const startBtn = document.getElementById('pomoStart');
  if (startBtn) startBtn.textContent = '▶ Start';
}

function pomodoroAction(action) {
  if (action === 'start') {
    if (state.pomoRunning) {
      // Pause
      clearInterval(state.pomoTimer);
      state.pomoRunning = false;
      const btn = document.getElementById('pomoStart');
      if (btn) btn.textContent = '▶ Resume';
    } else {
      if (state.pomoRemaining <= 0) {
        state.pomoRemaining = state.pomoDuration;
      }
      state.pomoRunning = true;
      const btn = document.getElementById('pomoStart');
      if (btn) btn.textContent = '⏸ Pause';
      state.pomoTimer = setInterval(() => {
        state.pomoRemaining--;
        renderPomoTime(state.pomoRemaining);
        updatePomoRing(state.pomoRemaining, state.pomoDuration);
        if (state.pomoRemaining <= 0) {
          clearInterval(state.pomoTimer);
          state.pomoRunning = false;
          state.pomoSessions++;
          localStorage.setItem('pomoSessions', state.pomoSessions);
          document.getElementById('pomoSessions').textContent = state.pomoSessions;
          const startBtn = document.getElementById('pomoStart');
          if (startBtn) startBtn.textContent = '▶ Start';
          showPomoNotification();
          toast('Pomodoro complete! Take a break 🌿', 'success');
          const progress = document.querySelector('.pomo-progress');
          if (progress) progress.style.stroke = '#34d399';
          setTimeout(() => { if (progress) progress.style.stroke = ''; }, 2000);
        }
      }, 1000);
    }
  } else if (action === 'reset') {
    clearInterval(state.pomoTimer);
    state.pomoRunning   = false;
    state.pomoRemaining = state.pomoDuration;
    renderPomoTime(state.pomoRemaining);
    updatePomoRing(state.pomoRemaining, state.pomoDuration);
    const btn = document.getElementById('pomoStart');
    if (btn) btn.textContent = '▶ Start';
  }
}

function renderPomoTime(seconds) {
  const m = Math.floor(seconds / 60).toString().padStart(2, '0');
  const s = (seconds % 60).toString().padStart(2, '0');
  const el = document.getElementById('pomoDisplay');
  if (el) el.textContent = `${m}:${s}`;
  document.title = state.pomoRunning ? `${m}:${s} — TaskFlow` : 'TaskFlow — Dashboard';
}

function updatePomoRing(remaining, total) {
  const circle    = document.getElementById('pomoProgress');
  if (!circle) return;
  const circumference = 2 * Math.PI * 52; // r=52
  const offset        = circumference * (1 - remaining / total);
  circle.style.strokeDashoffset = offset;
}

// ─── NOTIFICATIONS ────────────────────────────────────────────
function requestNotificationPermission() {
  if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission();
  }
}

function showPomoNotification() {
  if ('Notification' in window && Notification.permission === 'granted') {
    new Notification('⏱ Pomodoro Complete!', {
      body: 'Time for a well-earned break. You crushed it!',
      icon: 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">✦</text></svg>',
    });
  }
}