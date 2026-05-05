/**
 * Volunteer dashboard script
 * - Auth check (volunteer only)
 * - Populate profile + KPIs
 * - Load tasks from api/volunteer-tasks.php
 */

async function volunteerCheckAuth() {
  try {
    const res  = await fetch('api/auth.php?action=check', { credentials: 'include' });
    const data = await res.json();
    if (!data.logged_in || data.data.user_type !== 'volunteer') {
      window.location.href = 'login.html?redirect=volunteer-dashboard.html';
      return null;
    }
    return data.data.user;
  } catch {
    window.location.href = 'login.html?redirect=volunteer-dashboard.html';
    return null;
  }
}

function vdSetText(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = value;
}

function renderVolunteerKpis(stats) {
  const s = stats || {};
  const kpiValues = document.querySelectorAll('.kpi-card .kpi-value');
  if (kpiValues[0]) kpiValues[0].textContent = s.hours_volunteered ?? '—';
  if (kpiValues[1]) kpiValues[1].textContent = s.tasks_completed ?? '—';
  if (kpiValues[2]) kpiValues[2].textContent = s.events_attended ?? '—';
  if (kpiValues[3]) kpiValues[3].textContent = s.recognition_points ?? '—';
}

function renderVolunteerTasks(tasks) {
  const list = document.querySelector('.task-list');
  if (!list) return;

  if (!tasks || !tasks.length) {
    list.innerHTML = '<li class="task-item"><div class="task-detail">No tasks assigned yet.</div></li>';
    return;
  }

  const badge = status => {
    if (status === 'done') return '<span class="badge badge-success" style="margin-left:auto">Done</span>';
    if (status === 'upcoming') return '<span class="badge badge-pending" style="margin-left:auto">Upcoming</span>';
    return '<span class="badge badge-new" style="margin-left:auto">New</span>';
  };

  list.innerHTML = tasks.map(t => `
    <li class="task-item">
      <span class="task-status ${t.status}"></span>
      <div>
        <div class="task-title">${t.title}</div>
        <div class="task-detail">${t.meta}</div>
      </div>
      ${badge(t.status)}
    </li>
  `).join('');
}

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
    console.error(e);
  }
}

document.addEventListener('DOMContentLoaded', loadVolunteerDashboard);
