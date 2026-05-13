/**
 * Volunteer Dashboard script  (volunteer-dashboard.html)
 *
 *  - Auth check (volunteer only)
 *  - Populate profile card + KPIs
 *  - Load tasks from api/volunteer-tasks.php
 *  - Task status toggling
 *  - Proper server-side logout
 *  - Sidebar section scrolling
 */

'use strict';

// ── Toast ─────────────────────────────────────────────────────────────────────
function vdToast(msg, type = 'info') {
  const colours = { success: '#16a34a', error: '#dc2626', info: '#21808d', warning: '#ea580c' };
  const n = document.createElement('div');
  n.style.cssText = `position:fixed;top:20px;right:20px;z-index:10000;background:${colours[type]||colours.info};color:#fff;padding:10px 16px;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.2);font-size:13px;max-width:300px;display:flex;align-items:center;gap:8px;`;
  n.innerHTML = `<span>${msg}</span><button onclick="this.parentElement.remove()" style="background:none;border:none;color:#fff;font-size:16px;cursor:pointer;">×</button>`;
  document.body.appendChild(n);
  setTimeout(() => n.isConnected && n.remove(), 4000);
}

// ── Logout (calls server-side session destroy) ───────────────────────────────
async function logout() {
  try {
    await fetch('api/auth.php?action=logout', { credentials: 'include' });
  } catch { /* network error: proceed anyway */ }
  window.location.href = 'login.html';
}

// Expose globally for the onclick in HTML
window.logout = logout;

// ── Auth guard ────────────────────────────────────────────────────────────────
async function volunteerCheckAuth() {
  try {
    const res  = await fetch('api/auth.php?action=check', { credentials: 'include' });
    const data = await res.json();
    if (!data.logged_in || data.data?.user_type !== 'volunteer') {
      window.location.href = 'login.html?redirect=volunteer-dashboard.html';
      return null;
    }
    return data.data.user;
  } catch {
    window.location.href = 'login.html?redirect=volunteer-dashboard.html';
    return null;
  }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function vdSetText(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = value;
}

// ── KPI cards ─────────────────────────────────────────────────────────────────
function renderVolunteerKpis(stats) {
  const s = stats || {};
  const vals = document.querySelectorAll('.kpi-card .kpi-value');
  if (vals[0]) vals[0].textContent = s.hours_volunteered   ?? '0';
  if (vals[1]) vals[1].textContent = s.tasks_completed     ?? '0';
  if (vals[2]) vals[2].textContent = s.events_attended     ?? '0';
  if (vals[3]) vals[3].textContent = s.recognition_points  ?? '0';
}

// ── Task list ─────────────────────────────────────────────────────────────────
function renderVolunteerTasks(tasks) {
  const list = document.querySelector('.task-list');
  if (!list) return;

  if (!tasks || !tasks.length) {
    list.innerHTML = `
      <li class="task-item">
        <div class="task-detail" style="color:var(--text-muted);text-align:center;padding:12px 0;">
          No tasks assigned yet. Check back soon!
        </div>
      </li>`;
    return;
  }

  const badge = status => {
    if (status === 'done')     return '<span class="badge badge-success" style="margin-left:auto">Done</span>';
    if (status === 'upcoming') return '<span class="badge badge-pending" style="margin-left:auto">Upcoming</span>';
    return '<span class="badge badge-new" style="margin-left:auto">New</span>';
  };

  list.innerHTML = tasks.map(t => `
    <li class="task-item" data-task-id="${t.id}">
      <span class="task-status ${t.status}"></span>
      <div style="flex:1">
        <div class="task-title">${escVd(t.title)}</div>
        <div class="task-detail">${escVd(t.meta)}</div>
      </div>
      ${badge(t.status)}
      ${t.status !== 'done' ? `
        <button class="btn-mark-done" title="Mark as done"
          style="background:none;border:1px solid #16a34a;color:#16a34a;padding:3px 10px;border-radius:6px;font-size:12px;cursor:pointer;margin-left:6px;"
          data-task-id="${t.id}">
          <i class="fas fa-check"></i>
        </button>` : ''}
    </li>`).join('');

  // Wire up "mark done" buttons
  list.querySelectorAll('.btn-mark-done').forEach(btn => {
    btn.addEventListener('click', async () => {
      const taskId = btn.dataset.taskId;
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

      try {
        const res  = await fetch('api/volunteer-tasks.php', {
          method:  'PATCH',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({ task_id: taskId, status: 'done' }),
        });
        const data = await res.json();
        if (data.success) {
          vdToast('Task marked as done!', 'success');
          loadVolunteerDashboard(); // refresh
        } else {
          vdToast(data.message || 'Could not update task.', 'warning');
          btn.disabled = false;
          btn.innerHTML = '<i class="fas fa-check"></i>';
        }
      } catch {
        vdToast('Network error. Try again.', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i>';
      }
    });
  });
}

// ── Smooth scroll for sidebar links ──────────────────────────────────────────
function initSidebarNavigation() {
  document.querySelectorAll('.dash-sidebar .sidebar-nav a[href^="#"]').forEach(link => {
    link.addEventListener('click', e => {
      const target = document.querySelector(link.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        // Update active state
        document.querySelectorAll('.dash-sidebar .sidebar-nav a').forEach(a => a.classList.remove('active'));
        link.classList.add('active');
      }
    });
  });
}

// ── Main load ─────────────────────────────────────────────────────────────────
async function loadVolunteerDashboard() {
  const user = await volunteerCheckAuth();
  if (!user) return;

  const name  = user.name || user.full_name || user.email || 'Volunteer';
  const email = user.email || '';

  vdSetText('userName', name);
  vdSetText('profileName', name);
  vdSetText('profileEmail', email);

  const avatar = document.getElementById('avatarInitial');
  if (avatar) avatar.textContent = name.charAt(0).toUpperCase();

  try {
    const res  = await fetch('api/volunteer-tasks.php', { credentials: 'include' });
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Failed to load volunteer data');

    renderVolunteerKpis(data.stats);
    renderVolunteerTasks(data.tasks);
  } catch (e) {
    console.error('Volunteer dashboard error:', e);
    vdToast('Could not load dashboard data. Please refresh.', 'error');
  }
}

function escVd(str) {
  return String(str || '')
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ── Boot ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  loadVolunteerDashboard();
  initSidebarNavigation();
});
