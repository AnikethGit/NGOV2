/**
 * Admin dashboard interactions (admin-dashboard.html)
 * - Auth guard (admin only)
 * - Sidebar tab switching + breadcrumb
 * - Users tab: load users via api/admin-users.php
 */

// Simple toast helper
function adminShowToast(msg, type = 'info') {
  const colours = { success: '#16a34a', error: '#dc2626', info: '#21808d', warning: '#ea580c' };
  const n = document.createElement('div');
  n.style.cssText = `position:fixed;top:20px;right:20px;z-index:10000;background:${colours[type]};color:#fff;padding:10px 16px;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,0.2);font-size:13px;max-width:320px;display:flex;align-items:center;gap:8px;`;
  n.innerHTML = `<span>${msg}</span><button onclick="this.parentElement.remove()" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;">×</button>`;
  document.body.appendChild(n);
  setTimeout(() => n.isConnected && n.remove(), 4000);
}

async function adminCheckAuth() {
  try {
    const res  = await fetch('api/auth.php?action=check', { credentials: 'include' });
    const data = await res.json();
    if (!data.logged_in || data.data.user_type !== 'admin') {
      window.location.href = 'login.html?redirect=admin-dashboard.html';
      return null;
    }
    return data.data.user;
  } catch {
    window.location.href = 'login.html?redirect=admin-dashboard.html';
    return null;
  }
}

function initAdminSidebar() {
  const links = document.querySelectorAll('.sidebar-nav .nav-link[data-tab]');
  const breadcrumb = document.getElementById('current-section');

  links.forEach(link => {
    link.addEventListener('click', e => {
      e.preventDefault();
      const tab = link.dataset.tab;

      links.forEach(l => l.classList.remove('active'));
      link.classList.add('active');

      document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
      const target = document.getElementById(`${tab}-content`);
      if (target) target.classList.add('active');

      if (breadcrumb) breadcrumb.textContent = link.textContent.trim();
    });
  });

  // Quick links ("View All" recent donations)
  document.querySelectorAll('[data-tab="donations"]').forEach(el => {
    el.addEventListener('click', e => {
      e.preventDefault();
      const donationsLink = document.querySelector('.sidebar-nav .nav-link[data-tab="donations"]');
      if (donationsLink) donationsLink.click();
    });
  });
}

// USERS TAB --------------------------------------------------------------
let adminUsersState = { page: 1, perPage: 20, loading: false };

async function loadAdminUsers(page = 1) {
  if (adminUsersState.loading) return;
  adminUsersState.loading = true;

  const type   = document.getElementById('userTypeFilter')?.value || '';
  const status = document.getElementById('userStatusFilter')?.value || '';
  const from   = document.getElementById('userRegDateFrom')?.value || '';
  const to     = document.getElementById('userRegDateTo')?.value || '';

  const params = new URLSearchParams({
    action: 'list',
    page: String(page),
    per_page: String(adminUsersState.perPage),
  });
  if (type) params.append('type', type);
  if (status) params.append('status', status);
  if (from) params.append('from', from);
  if (to) params.append('to', to);

  const tbody = document.getElementById('usersTableBody');
  if (tbody) {
    tbody.innerHTML = `<tr><td colspan="7" class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading users...</td></tr>`;
  }

  try {
    const res  = await fetch(`api/admin-users.php?${params.toString()}`, { credentials: 'include' });
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Failed to load users');

    adminUsersState.page = page;
    renderAdminUsersTable(data.data || []);
    renderAdminUsersPagination(data.pagination || {});
  } catch (e) {
    if (tbody) {
      tbody.innerHTML = `<tr><td colspan="7" class="loading-spinner">${e.message || 'Failed to load users'}</td></tr>`;
    }
  } finally {
    adminUsersState.loading = false;
  }
}

function renderAdminUsersTable(users) {
  const tbody = document.getElementById('usersTableBody');
  if (!tbody) return;

  if (!users.length) {
    tbody.innerHTML = `<tr><td colspan="7" class="no-data"><div class="no-data-content"><i class="fas fa-users"></i><p>No users found for the selected filters.</p></div></td></tr>`;
    return;
  }

  tbody.innerHTML = users.map(u => `
    <tr>
      <td>${u.id}</td>
      <td>${u.full_name || ''}</td>
      <td>${u.email || ''}</td>
      <td>${u.user_type || ''}</td>
      <td>${renderUserStatusBadge(u.status)}</td>
      <td>${u.last_login ? new Date(u.last_login).toLocaleString('en-IN') : '—'}</td>
      <td>
        <div class="action-buttons">
          <button class="btn btn-xs" data-user-id="${u.id}" data-action="view-user"><i class="fas fa-eye"></i></button>
          <button class="btn btn-xs" data-user-id="${u.id}" data-action="edit-user"><i class="fas fa-edit"></i></button>
        </div>
      </td>
    </tr>
  `).join('');
}

function renderUserStatusBadge(status) {
  const s = (status || '').toLowerCase();
  if (s === 'active') return '<span class="status status-success">Active</span>';
  if (s === 'inactive') return '<span class="status status-warning">Inactive</span>';
  if (s === 'banned' || s === 'suspended') return '<span class="status status-error">Suspended</span>';
  return `<span class="status">${status || 'Unknown'}</span>`;
}

function renderAdminUsersPagination(p) {
  const container = document.getElementById('donationsPagination'); // reuse pagination block
  if (!container) return;

  const page      = p.page || 1;
  const total     = p.total || 0;
  const totalPage = p.total_pages || 1;

  container.innerHTML = `
    <div class="pagination-controls">
      <div class="pagination-info">Showing page ${page} of ${totalPage} (${total} users)</div>
      <div class="pagination-buttons">
        <button class="btn btn-outline" ${page <= 1 ? 'disabled' : ''} data-page="${page - 1}">Prev</button>
        <button class="btn btn-outline" ${page >= totalPage ? 'disabled' : ''} data-page="${page + 1}">Next</button>
      </div>
    </div>`;

  container.querySelectorAll('button[data-page]').forEach(btn => {
    btn.addEventListener('click', () => {
      const p = parseInt(btn.dataset.page || '1', 10);
      loadAdminUsers(p);
    });
  });
}

function initAdminUsersTab() {
  document.getElementById('userTypeFilter')?.addEventListener('change', () => loadAdminUsers(1));
  document.getElementById('userStatusFilter')?.addEventListener('change', () => loadAdminUsers(1));
  document.getElementById('userRegDateFrom')?.addEventListener('change', () => loadAdminUsers(1));
  document.getElementById('userRegDateTo')?.addEventListener('change', () => loadAdminUsers(1));

  document.getElementById('createUser')?.addEventListener('click', () => {
    adminShowToast('User creation form coming soon. For now, create users via registration.', 'info');
  });
}

// INIT ----------------------------------------------------------------------

document.addEventListener('DOMContentLoaded', async () => {
  const user = await adminCheckAuth();
  if (!user) return;

  initAdminSidebar();
  initAdminUsersTab();

  // Preload users when Users tab is first opened
  const usersTabLink = document.querySelector('.sidebar-nav .nav-link[data-tab="users"]');
  if (usersTabLink) {
    usersTabLink.addEventListener('click', () => {
      if (!adminUsersState.loadedOnce) {
        adminUsersState.loadedOnce = true;
        loadAdminUsers(1);
      }
    });
  }
});
