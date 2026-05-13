/**
 * admin-enhanced.js  —  Admin Dashboard (admin-dashboard.html)
 *
 * Features implemented:
 *  - Auth guard (admin only) + sidebar user info
 *  - Logout button
 *  - Dashboard tab: real stats from api/admin-stats.php, recent donations
 *  - Donations tab: paginated table from api/admin-donations.php + all filters + CSV export
 *  - Volunteers tab: paginated table from api/admin-volunteers.php + filters + CSV export
 *  - Users tab: paginated table from api/admin-users.php + filters
 *  - Quick action buttons → navigate to relevant tabs
 *  - Global search box for active tab
 *  - Lazy loading: each tab fetches data only on first open
 */

'use strict';

// ── Formatters ──────────────────────────────────────────────────────────────
const adminFmt    = n  => Number(n).toLocaleString('en-IN');
const adminFmtRs  = n  => '₹' + adminFmt(Math.round(Number(n)));
const adminFmtDate = d => {
  if (!d) return '—';
  const dt = new Date(d);
  return isNaN(dt) ? d : dt.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
};

// ── Toast ────────────────────────────────────────────────────────────────────
function adminShowToast(msg, type = 'info') {
  const colours = { success: '#16a34a', error: '#dc2626', info: '#21808d', warning: '#ea580c' };
  const n = document.createElement('div');
  n.style.cssText = `position:fixed;top:20px;right:20px;z-index:10000;background:${colours[type] || colours.info};color:#fff;padding:10px 16px;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.2);font-size:13px;max-width:320px;display:flex;align-items:center;gap:8px;animation:slideInToast .2s ease;`;
  n.innerHTML = `<span>${msg}</span><button onclick="this.parentElement.remove()" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;line-height:1;">×</button>`;
  document.body.appendChild(n);
  setTimeout(() => n.isConnected && n.remove(), 5000);
}

const _toastStyle = document.createElement('style');
_toastStyle.textContent = `@keyframes slideInToast{from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}`;
document.head.appendChild(_toastStyle);

// ── Status badges ────────────────────────────────────────────────────────────
function adminStatusBadge(status) {
  const s = (status || '').toLowerCase();
  if (s === 'completed' || s === 'active')   return `<span class="status status-success">${status}</span>`;
  if (s === 'pending')                        return `<span class="status status-warning">Pending</span>`;
  if (s === 'failed' || s === 'suspended' || s === 'banned') return `<span class="status status-error">${status}</span>`;
  return `<span class="status">${status || '—'}</span>`;
}

// ── Shared pagination renderer ────────────────────────────────────────────────
function renderPagination(containerId, pagination, loadFn) {
  const container = document.getElementById(containerId);
  if (!container) return;
  const { page = 1, total = 0, total_pages = 1, per_page = 20 } = pagination;
  const from = total ? (page - 1) * per_page + 1 : 0;
  const to   = Math.min(page * per_page, total);

  container.innerHTML = `
    <div class="pagination-controls">
      <div class="pagination-info">Showing ${from}–${to} of ${total}</div>
      <div class="pagination-buttons">
        <button class="btn btn-outline" ${page <= 1 ? 'disabled' : ''} data-page="${page - 1}">← Prev</button>
        <span style="padding:0 8px;font-size:13px;color:var(--color-text-secondary)">Page ${page} / ${total_pages}</span>
        <button class="btn btn-outline" ${page >= total_pages ? 'disabled' : ''} data-page="${page + 1}">Next →</button>
      </div>
    </div>`;

  container.querySelectorAll('button[data-page]').forEach(btn => {
    btn.addEventListener('click', () => loadFn(parseInt(btn.dataset.page, 10)));
  });
}

// ── CSV export helper ────────────────────────────────────────────────────────
function exportCSV(rows, columns, filename) {
  if (!rows.length) { adminShowToast('No data to export.', 'warning'); return; }
  const header = columns.map(c => c.label);
  const lines  = [header, ...rows.map(r => columns.map(c => {
    const v = c.key ? (r[c.key] ?? '') : (c.fn ? c.fn(r) : '');
    return `"${String(v).replace(/"/g, '""')}"`;
  }))];
  const blob = new Blob(['﻿' + lines.map(l => l.join(',')).join('\n')], { type: 'text/csv;charset=utf-8;' });
  const a = document.createElement('a');
  a.href     = URL.createObjectURL(blob);
  a.download = filename;
  a.click();
  URL.revokeObjectURL(a.href);
}

// ── Excel export helper (SheetJS) ────────────────────────────────────────────
function exportExcel(rows, columns, filename) {
  if (!rows.length) { adminShowToast('No data to export.', 'warning'); return; }
  if (typeof XLSX === 'undefined') {
    adminShowToast('Excel library not loaded. Try CSV instead.', 'error');
    return;
  }
  const header = columns.map(c => c.label);
  const data   = [header, ...rows.map(r => columns.map(c =>
    c.key ? (r[c.key] ?? '') : (c.fn ? c.fn(r) : '')
  ))];
  const ws = XLSX.utils.aoa_to_sheet(data);
  // Auto column widths
  ws['!cols'] = columns.map((_, i) => ({
    wch: Math.max(columns[i].label.length, ...rows.map(r => {
      const v = columns[i].key ? String(r[columns[i].key] ?? '') : String(columns[i].fn ? columns[i].fn(r) : '');
      return v.length;
    })) + 2,
  }));
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'Donations');
  XLSX.writeFile(wb, filename);
}

// ── Donation export columns ───────────────────────────────────────────────────
const DONATION_EXPORT_COLS = [
  { label: 'ID',                  key: 'id' },
  { label: 'Transaction ID',      key: 'transaction_id' },
  { label: 'Donor Name',          key: 'donor_name' },
  { label: 'Email',               key: 'donor_email' },
  { label: 'Phone',               key: 'donor_phone' },
  { label: 'PAN',                 key: 'donor_pan' },
  { label: 'Address',             key: 'donor_address' },
  { label: 'Amount (INR)',        key: 'amount' },
  { label: 'Cause',               key: 'cause' },
  { label: 'Payment Method',      key: 'payment_method' },
  { label: 'Status',              key: 'payment_status' },
  { label: 'Razorpay Order ID',   key: 'razorpay_order_id' },
  { label: 'Razorpay Payment ID', key: 'razorpay_payment_id' },
  { label: 'Date',                fn: r => adminFmtDate(r.created_at) },
];

// ── Auth guard ───────────────────────────────────────────────────────────────
async function adminCheckAuth() {
  try {
    const res  = await fetch('api/auth.php?action=check', { credentials: 'include' });
    const data = await res.json();
    if (!data.logged_in || (data.data?.user_type !== 'admin')) {
      window.location.href = 'login.html?redirect=admin-dashboard.html';
      return null;
    }
    return data.data.user;
  } catch {
    window.location.href = 'login.html?redirect=admin-dashboard.html';
    return null;
  }
}

// ── Sidebar + tab switching ──────────────────────────────────────────────────
function initAdminSidebar(tabLoadedFlags) {
  const links      = document.querySelectorAll('.sidebar-nav .nav-link[data-tab]');
  const breadcrumb = document.getElementById('current-section');

  function activateTab(tab) {
    links.forEach(l => l.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

    const target = document.getElementById(`${tab}-content`);
    if (target) target.classList.add('active');

    const link = document.querySelector(`.sidebar-nav .nav-link[data-tab="${tab}"]`);
    if (link) link.classList.add('active');

    if (breadcrumb) {
      breadcrumb.textContent = link ? link.querySelector('span')?.textContent?.trim() || tab : tab;
    }

    // Lazy-load on first visit
    if (!tabLoadedFlags[tab]) {
      tabLoadedFlags[tab] = true;
      if (tab === 'donations')  loadAdminDonations(1);
      if (tab === 'volunteers') loadAdminVolunteers(1);
      if (tab === 'users')      loadAdminUsers(1);
    }
  }

  links.forEach(link => {
    link.addEventListener('click', e => {
      e.preventDefault();
      activateTab(link.dataset.tab);
    });
  });

  // "View All" links on dashboard card
  document.querySelectorAll('[data-tab="donations"]').forEach(el => {
    el.addEventListener('click', e => { e.preventDefault(); activateTab('donations'); });
  });

  // Mobile sidebar toggle
  document.querySelector('.mobile-sidebar-toggle')?.addEventListener('click', () => {
    document.querySelector('.sidebar')?.classList.toggle('open');
  });
  document.querySelector('.sidebar-toggle')?.addEventListener('click', () => {
    document.querySelector('.sidebar')?.classList.toggle('collapsed');
  });

  return activateTab; // expose so quick actions can reuse it
}

// ── Populate sidebar user name / avatar ─────────────────────────────────────
function updateSidebarUser(user) {
  const name = user?.name || user?.full_name || 'Admin';
  const nameEl   = document.querySelector('.sidebar-footer .user-name');
  const avatarEl = document.querySelector('.sidebar-footer .avatar-placeholder');
  if (nameEl)   nameEl.textContent   = name;
  if (avatarEl) avatarEl.textContent = name.charAt(0).toUpperCase();
}

// ── Logout ───────────────────────────────────────────────────────────────────
function initLogout() {
  async function doAdminLogout(e) {
    e.preventDefault();
    try { await fetch('api/auth.php?action=logout', { credentials: 'include' }); } catch {}
    window.location.href = 'login.html';
  }

  // Top-bar Logout button (primary trigger)
  document.getElementById('logoutBtn')?.addEventListener('click', doAdminLogout);
  // Sidebar user-dropdown Logout link (secondary trigger)
  document.getElementById('sidebarUserLogout')?.addEventListener('click', doAdminLogout);

  // User dropdown toggle (CSS shows .user-dropdown.active, so toggle 'active')
  document.querySelector('.user-menu-toggle')?.addEventListener('click', () => {
    document.querySelector('.user-dropdown')?.classList.toggle('active');
  });
  document.addEventListener('click', e => {
    if (!e.target.closest('.user-profile')) {
      document.querySelector('.user-dropdown')?.classList.remove('active');
    }
  });
}

// ── Quick actions ────────────────────────────────────────────────────────────
function initQuickActions(activateTab) {
  document.querySelectorAll('.quick-action-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const action = btn.dataset.action;
      switch (action) {
        case 'add-donation':    activateTab('donations');  break;
        case 'add-volunteer':   activateTab('volunteers'); break;
        case 'create-event':    activateTab('events');     break;
        case 'generate-report': activateTab('reports');    break;
      }
    });
  });
}

// ── Global search ─────────────────────────────────────────────────────────────
function initGlobalSearch() {
  const input = document.querySelector('.search-input');
  if (!input) return;
  let timer;
  input.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      // Search within whichever tab is active
      const active = document.querySelector('.tab-content.active');
      if (!active) return;
      const id = active.id.replace('-content', '');
      if (id === 'donations')  { adminDonationsState.search = input.value; loadAdminDonations(1); }
      if (id === 'volunteers') { adminVolunteersState.search = input.value; loadAdminVolunteers(1); }
      if (id === 'users')      { adminUsersState.search     = input.value; loadAdminUsers(1); }
    }, 350);
  });
}

// ════════════════════════════════════════════════════════════
// DASHBOARD TAB — real stats
// ════════════════════════════════════════════════════════════

async function loadAdminStats() {
  try {
    const res  = await fetch('api/admin-stats.php', { credentials: 'include' });
    const data = await res.json();
    if (!data.success) return;

    const s = data.stats;

    // Stat cards — select by position (data-target attributes in HTML)
    const numbers = document.querySelectorAll('#dashboard-content .stat-number');
    if (numbers[0]) numbers[0].textContent = adminFmtRs(s.total_amount);
    if (numbers[1]) numbers[1].textContent = adminFmt(s.active_volunteers);
    if (numbers[2]) numbers[2].textContent = '—';              // projects not in DB yet
    if (numbers[3]) numbers[3].textContent = adminFmt(s.lives_impacted);

    // Stat change text
    const changes = document.querySelectorAll('#dashboard-content .stat-change');
    if (changes[0]) {
      const pct = s.month_change_pct;
      const dir = pct >= 0 ? 'positive' : 'negative';
      const icon = pct >= 0 ? 'arrow-up' : 'arrow-down';
      changes[0].className = `stat-change ${dir}`;
      changes[0].innerHTML = `<i class="fas fa-${icon}"></i> ${Math.abs(pct)}% vs last month`;
    }
    if (changes[1]) {
      changes[1].innerHTML = `<i class="fas fa-users"></i> ${adminFmt(s.total_volunteers)} total`;
    }

    // Nav badge (pending donations count)
    const badge = document.querySelector('.sidebar-nav .nav-badge');
    if (badge && s.pending_count > 0) {
      badge.textContent = s.pending_count;
      badge.style.display = '';
    } else if (badge) {
      badge.style.display = 'none';
    }

    // Recent donations card
    renderRecentDonations(data.recent_donations || []);

  } catch (e) {
    console.error('Stats load error:', e);
  }
}

function renderRecentDonations(donations) {
  const list = document.querySelector('#dashboard-content .activity-list');
  if (!list) return;

  if (!donations.length) {
    list.innerHTML = '<div style="padding:20px;text-align:center;color:var(--color-text-secondary)">No donations yet.</div>';
    return;
  }

  const causeLabels = {
    'general-fund': 'General Fund', 'shirdi-annadanam': 'Shirdi Annadanam',
    'ganagapur-annadanam': 'Ganagapur Annadanam', 'corpus-fund': 'Corpus Fund',
    'general': 'General Fund', 'education': 'Education', 'medical': 'Medical',
  };

  list.innerHTML = donations.map(d => {
    const ago = timeAgo(d.created_at);
    const name  = d.donor_name || d.donor_email || 'Anonymous';
    const cause = causeLabels[d.cause] || d.cause || 'General';
    const s     = (d.payment_status || '').toLowerCase();
    const cls   = s === 'completed' ? 'success' : s === 'pending' ? 'warning' : 'error';
    return `
      <div class="activity-item">
        <div class="activity-avatar"><i class="fas fa-heart"></i></div>
        <div class="activity-info">
          <div class="activity-title">${escHtml(name)}</div>
          <div class="activity-meta">${adminFmtRs(d.amount)} · ${cause} · ${ago}</div>
        </div>
        <div class="activity-status ${cls}">${ucFirst(d.payment_status)}</div>
      </div>`;
  }).join('');
}

// ════════════════════════════════════════════════════════════
// DONATIONS TAB
// ════════════════════════════════════════════════════════════

const adminDonationsState = { page: 1, perPage: 20, loading: false, search: '', _allRows: [] };

async function loadAdminDonations(page = 1) {
  if (adminDonationsState.loading) return;
  adminDonationsState.loading = true;

  const status = document.getElementById('donationStatusFilter')?.value      || '';
  const cause  = document.getElementById('donationCauseFilter')?.value       || '';
  const method = document.getElementById('donationPaymentMethodFilter')?.value || '';
  const range  = document.getElementById('donationAmountFilter')?.value        || '';
  const from   = document.getElementById('donationDateFrom')?.value            || '';
  const to     = document.getElementById('donationDateTo')?.value              || '';
  const search = adminDonationsState.search || '';

  const params = new URLSearchParams({
    page: String(page), per_page: String(adminDonationsState.perPage),
  });
  if (status) params.append('status',         status);
  if (cause)  params.append('cause',          cause);
  if (method) params.append('payment_method', method);
  if (range)  params.append('amount_range',   range);
  if (from)   params.append('from',           from);
  if (to)     params.append('to',             to);
  if (search) params.append('search',         search);

  const tbody = document.getElementById('donationsTableBody');
  if (tbody) tbody.innerHTML = `<tr><td colspan="11" class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading donations…</td></tr>`;

  try {
    const res  = await fetch(`api/admin-donations.php?${params}`, { credentials: 'include' });
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Failed');

    adminDonationsState.page    = page;
    adminDonationsState._allRows = data.data || [];
    renderAdminDonationsTable(data.data || []);
    renderPagination('donationsPagination', data.pagination || {}, loadAdminDonations);
  } catch (e) {
    if (tbody) tbody.innerHTML = `<tr><td colspan="11" class="loading-spinner">${escHtml(e.message)}</td></tr>`;
    adminShowToast('Failed to load donations: ' + e.message, 'error');
  } finally {
    adminDonationsState.loading = false;
  }
}

function renderAdminDonationsTable(rows) {
  const tbody = document.getElementById('donationsTableBody');
  if (!tbody) return;

  const causeLabels = {
    'general-fund': 'General Fund', 'shirdi-annadanam': 'Shirdi Annadanam',
    'ganagapur-annadanam': 'Ganagapur Annadanam', 'corpus-fund': 'Corpus Fund',
    'general': 'General Fund', 'education': 'Education', 'medical': 'Medical',
    'poor-feeding': 'Poor Feeding', 'disaster': 'Disaster Relief',
  };

  if (!rows.length) {
    tbody.innerHTML = `<tr><td colspan="12" class="no-data"><div class="no-data-content"><i class="fas fa-heart"></i><p>No donations found for the selected filters.</p></div></td></tr>`;
    return;
  }

  const mono = v => v ? `<small style="font-family:monospace;font-size:11px">${escHtml(v)}</small>` : '<span style="color:var(--color-text-secondary)">—</span>';

  tbody.innerHTML = rows.map(d => `
    <tr>
      <td>${mono(d.transaction_id)}</td>
      <td>
        <div class="donor-info">
          <strong>${escHtml(d.donor_name || '—')}</strong>
          <small>${escHtml(d.donor_email || '')}</small>
          ${d.donor_phone ? `<small>${escHtml(d.donor_phone)}</small>` : ''}
        </div>
      </td>
      <td>${mono(d.donor_pan)}</td>
      <td style="max-width:160px;white-space:normal;font-size:12px">${d.donor_address ? escHtml(d.donor_address) : '<span style="color:var(--color-text-secondary)">—</span>'}</td>
      <td><span class="amount">${adminFmtRs(d.amount)}</span></td>
      <td>${escHtml(causeLabels[d.cause] || d.cause || '—')}</td>
      <td>${escHtml(d.payment_method || '—')}</td>
      <td>${mono(d.razorpay_order_id)}</td>
      <td>${mono(d.razorpay_payment_id)}</td>
      <td>${adminStatusBadge(d.payment_status)}</td>
      <td>${adminFmtDate(d.created_at)}</td>
      <td>
        <div class="action-buttons">
          <button class="btn btn-outline btn-xs" title="Copy Transaction ID"
            onclick="navigator.clipboard?.writeText('${escHtml(d.transaction_id || '')}').then(()=>adminShowToast('Copied!','success'))">
            <i class="fas fa-copy"></i>
          </button>
        </div>
      </td>
    </tr>`).join('');
}

function initDonationsTab() {
  // Dropdowns + date pickers → reload table on change
  ['donationStatusFilter','donationCauseFilter','donationPaymentMethodFilter',
   'donationAmountFilter','donationDateFrom','donationDateTo'].forEach(id => {
    document.getElementById(id)?.addEventListener('change', () => loadAdminDonations(1));
  });

  // Donor search: debounced text input
  let _donorSearchTimer;
  document.getElementById('donationDonorSearch')?.addEventListener('input', e => {
    clearTimeout(_donorSearchTimer);
    _donorSearchTimer = setTimeout(() => {
      adminDonationsState.search = e.target.value.trim();
      loadAdminDonations(1);
    }, 350);
  });

  // Clear filters
  document.getElementById('clearDonationFilters')?.addEventListener('click', () => {
    ['donationStatusFilter','donationCauseFilter','donationPaymentMethodFilter',
     'donationAmountFilter'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
    ['donationDateFrom','donationDateTo'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
    const search = document.getElementById('donationDonorSearch');
    if (search) search.value = '';
    adminDonationsState.search = '';
    loadAdminDonations(1);
  });

  // Export button → show modal
  document.getElementById('exportDonations')?.addEventListener('click', () => showExportModal());

  document.getElementById('addDonation')?.addEventListener('click', () => {
    adminShowToast('To record a donation, use the Donate page or import directly into the database.', 'info');
  });

  initExportModal();
}

// ── Export modal ──────────────────────────────────────────────────────────────

function initExportModal() {
  const modal  = document.getElementById('exportModal');
  if (!modal) return;

  // Populate year dropdowns
  const curYear = new Date().getFullYear();
  const years   = Array.from({ length: 6 }, (_, i) => curYear - i);
  ['exportYearMonthly','exportYearYearly'].forEach(id => {
    const sel = document.getElementById(id);
    if (!sel) return;
    sel.innerHTML = years.map(y => `<option value="${y}">${y}</option>`).join('');
  });

  // Set current month default
  const monthSel = document.getElementById('exportMonth');
  if (monthSel) monthSel.value = String(new Date().getMonth() + 1);

  // Scope selector shows/hides extra controls
  document.getElementById('exportScope')?.addEventListener('change', updateExportScopeUI);
  updateExportScopeUI();

  // Close buttons
  ['exportModalClose','exportModalCancel'].forEach(id => {
    document.getElementById(id)?.addEventListener('click', () => closeExportModal());
  });
  modal.addEventListener('click', e => { if (e.target === modal) closeExportModal(); });

  // Confirm → fetch all & download
  document.getElementById('exportModalConfirm')?.addEventListener('click', async () => {
    const btn = document.getElementById('exportModalConfirm');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Fetching…';
    try {
      await doExport();
    } finally {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-download"></i> Download';
    }
  });
}

function updateExportScopeUI() {
  const scope = document.getElementById('exportScope')?.value || 'current';
  document.getElementById('exportScopeDateRange').style.display = scope === 'date-range' ? '' : 'none';
  document.getElementById('exportScopeMonthly').style.display   = scope === 'monthly'    ? '' : 'none';
  document.getElementById('exportScopeYearly').style.display    = scope === 'yearly'     ? '' : 'none';
}

function showExportModal() {
  const modal = document.getElementById('exportModal');
  if (modal) { modal.style.display = 'flex'; updateExportScopeUI(); }
}

function closeExportModal() {
  const modal = document.getElementById('exportModal');
  if (modal) modal.style.display = 'none';
}

async function doExport() {
  const format = document.querySelector('input[name="exportFormat"]:checked')?.value || 'csv';
  const scope  = document.getElementById('exportScope')?.value || 'current';

  // Build query params based on scope
  const params = new URLSearchParams({ export: '1' });

  if (scope === 'current') {
    // Mirror all active filters
    const status = document.getElementById('donationStatusFilter')?.value         || '';
    const cause  = document.getElementById('donationCauseFilter')?.value          || '';
    const method = document.getElementById('donationPaymentMethodFilter')?.value  || '';
    const range  = document.getElementById('donationAmountFilter')?.value         || '';
    const from   = document.getElementById('donationDateFrom')?.value             || '';
    const to     = document.getElementById('donationDateTo')?.value               || '';
    const search = adminDonationsState.search || '';
    if (status) params.append('status',         status);
    if (cause)  params.append('cause',          cause);
    if (method) params.append('payment_method', method);
    if (range)  params.append('amount_range',   range);
    if (from)   params.append('from',           from);
    if (to)     params.append('to',             to);
    if (search) params.append('search',         search);

  } else if (scope === 'date-range') {
    const from = document.getElementById('exportDateFrom')?.value || '';
    const to   = document.getElementById('exportDateTo')?.value   || '';
    if (from) params.append('from', from);
    if (to)   params.append('to',   to);

  } else if (scope === 'monthly') {
    const month = document.getElementById('exportMonth')?.value        || '';
    const year  = document.getElementById('exportYearMonthly')?.value  || '';
    if (month) params.append('month', month);
    if (year)  params.append('year',  year);

  } else if (scope === 'yearly') {
    const year = document.getElementById('exportYearYearly')?.value || '';
    if (year) params.append('year', year);
  }

  try {
    const res  = await fetch(`api/admin-donations.php?${params}`, { credentials: 'include' });
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Failed to fetch data');

    const rows = data.data || [];
    if (!rows.length) {
      adminShowToast('No donations found for the selected scope.', 'warning');
      return;
    }

    const timestamp = new Date().toISOString().slice(0,10);
    const basename  = `donations_${scope}_${timestamp}`;

    if (format === 'xlsx') {
      exportExcel(rows, DONATION_EXPORT_COLS, `${basename}.xlsx`);
    } else {
      exportCSV(rows, DONATION_EXPORT_COLS, `${basename}.csv`);
    }

    adminShowToast(`Exported ${rows.length} row${rows.length !== 1 ? 's' : ''} as ${format.toUpperCase()}.`, 'success');
    closeExportModal();
  } catch (e) {
    adminShowToast('Export failed: ' + e.message, 'error');
  }
}

// ════════════════════════════════════════════════════════════
// VOLUNTEERS TAB
// ════════════════════════════════════════════════════════════

const adminVolunteersState = { page: 1, perPage: 20, loading: false, search: '', _allRows: [] };

async function loadAdminVolunteers(page = 1) {
  if (adminVolunteersState.loading) return;
  adminVolunteersState.loading = true;

  const status = document.getElementById('volunteerStatusFilter')?.value || '';
  const from   = document.getElementById('volunteerDateFrom')?.value     || '';
  const to     = document.getElementById('volunteerDateTo')?.value       || '';
  const search = adminVolunteersState.search || '';

  const params = new URLSearchParams({
    action: 'list', page: String(page), per_page: String(adminVolunteersState.perPage),
  });
  if (status) params.append('status', status);
  if (from)   params.append('from',   from);
  if (to)     params.append('to',     to);
  if (search) params.append('search', search);

  const tbody = document.getElementById('volunteersTableBody');
  if (tbody) tbody.innerHTML = `<tr><td colspan="7" class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading volunteers…</td></tr>`;

  try {
    const res  = await fetch(`api/admin-volunteers.php?${params}`, { credentials: 'include' });
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Failed');

    adminVolunteersState.page     = page;
    adminVolunteersState._allRows = data.data || [];
    renderAdminVolunteersTable(data.data || []);
    renderPagination('volunteersPagination', data.pagination || {}, loadAdminVolunteers);
  } catch (e) {
    if (tbody) tbody.innerHTML = `<tr><td colspan="7" class="loading-spinner">${escHtml(e.message)}</td></tr>`;
    adminShowToast('Failed to load volunteers: ' + e.message, 'error');
  } finally {
    adminVolunteersState.loading = false;
  }
}

function renderAdminVolunteersTable(rows) {
  const tbody = document.getElementById('volunteersTableBody');
  if (!tbody) return;

  if (!rows.length) {
    tbody.innerHTML = `<tr><td colspan="7" class="no-data"><div class="no-data-content"><i class="fas fa-hands-helping"></i><p>No volunteers found for the selected filters.</p></div></td></tr>`;
    return;
  }

  tbody.innerHTML = rows.map(v => `
    <tr>
      <td>${v.id}</td>
      <td>${escHtml(v.full_name || '—')}</td>
      <td>
        <div class="donor-info">
          <small>${escHtml(v.email || '')}</small>
          <small>${escHtml(v.phone || '')}</small>
        </div>
      </td>
      <td>—</td>
      <td>${adminStatusBadge(v.status)}</td>
      <td>${adminFmtDate(v.created_at)}</td>
      <td>
        <div class="action-buttons">
          <button class="btn btn-outline btn-xs" title="Email"
            onclick="window.location='mailto:${escHtml(v.email || '')}'">
            <i class="fas fa-envelope"></i>
          </button>
        </div>
      </td>
    </tr>`).join('');
}

function initVolunteersTab() {
  ['volunteerStatusFilter','volunteerDateFrom','volunteerDateTo'].forEach(id => {
    document.getElementById(id)?.addEventListener('change', () => loadAdminVolunteers(1));
  });

  document.getElementById('exportVolunteers')?.addEventListener('click', () => {
    exportCSV(adminVolunteersState._allRows, [
      { label: 'ID',          key: 'id' },
      { label: 'Name',        key: 'full_name' },
      { label: 'Email',       key: 'email' },
      { label: 'Phone',       key: 'phone' },
      { label: 'Status',      key: 'status' },
      { label: 'Joined',      fn: r => adminFmtDate(r.created_at) },
      { label: 'Last Login',  fn: r => adminFmtDate(r.last_login) },
    ], 'volunteers.csv');
  });

  document.getElementById('addVolunteer')?.addEventListener('click', () => {
    adminShowToast('Direct volunteers to the registration page to create an account with role "Volunteer".', 'info');
  });
}

// ════════════════════════════════════════════════════════════
// USERS TAB
// ════════════════════════════════════════════════════════════

const adminUsersState = { page: 1, perPage: 20, loading: false, loadedOnce: false, search: '' };

async function loadAdminUsers(page = 1) {
  if (adminUsersState.loading) return;
  adminUsersState.loading = true;

  const type   = document.getElementById('userTypeFilter')?.value   || '';
  const status = document.getElementById('userStatusFilter')?.value || '';
  const from   = document.getElementById('userRegDateFrom')?.value  || '';
  const to     = document.getElementById('userRegDateTo')?.value    || '';
  const search = adminUsersState.search || '';

  const params = new URLSearchParams({
    action: 'list', page: String(page), per_page: String(adminUsersState.perPage),
  });
  if (type)   params.append('type',   type);
  if (status) params.append('status', status);
  if (from)   params.append('from',   from);
  if (to)     params.append('to',     to);
  if (search) params.append('search', search);

  const tbody = document.getElementById('usersTableBody');
  if (tbody) tbody.innerHTML = `<tr><td colspan="7" class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading users…</td></tr>`;

  try {
    const res  = await fetch(`api/admin-users.php?${params}`, { credentials: 'include' });
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Failed');

    adminUsersState.page = page;
    renderAdminUsersTable(data.data || []);
    renderPagination('usersPagination', data.pagination || {}, loadAdminUsers);
  } catch (e) {
    if (tbody) tbody.innerHTML = `<tr><td colspan="7" class="loading-spinner">${escHtml(e.message)}</td></tr>`;
    adminShowToast('Failed to load users: ' + e.message, 'error');
  } finally {
    adminUsersState.loading = false;
  }
}

function renderAdminUsersTable(users) {
  const tbody = document.getElementById('usersTableBody');
  if (!tbody) return;

  if (!users.length) {
    tbody.innerHTML = `<tr><td colspan="7" class="no-data"><div class="no-data-content"><i class="fas fa-users"></i><p>No users found.</p></div></td></tr>`;
    return;
  }

  tbody.innerHTML = users.map(u => `
    <tr>
      <td>${u.id}</td>
      <td>${escHtml(u.full_name || '—')}</td>
      <td>${escHtml(u.email || '')}</td>
      <td><span style="text-transform:capitalize">${u.user_type || '—'}</span></td>
      <td>${adminStatusBadge(u.status)}</td>
      <td>${u.last_login ? adminFmtDate(u.last_login) : '—'}</td>
      <td>
        <div class="action-buttons">
          <button class="btn btn-outline btn-xs" title="Email"
            onclick="window.location='mailto:${escHtml(u.email || '')}'">
            <i class="fas fa-envelope"></i>
          </button>
        </div>
      </td>
    </tr>`).join('');
}

function initUsersTab() {
  ['userTypeFilter','userStatusFilter','userRegDateFrom','userRegDateTo'].forEach(id => {
    document.getElementById(id)?.addEventListener('change', () => loadAdminUsers(1));
  });

  document.getElementById('createUser')?.addEventListener('click', () => {
    adminShowToast('Direct the new user to the registration page to create their account.', 'info');
  });
}

// ── Utilities ────────────────────────────────────────────────────────────────
function escHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function ucFirst(str) {
  return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
}

function timeAgo(dateStr) {
  if (!dateStr) return '—';
  const diff = Date.now() - new Date(dateStr).getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 1)   return 'just now';
  if (mins < 60)  return `${mins}m ago`;
  const hrs = Math.floor(mins / 60);
  if (hrs < 24)   return `${hrs}h ago`;
  return adminFmtDate(dateStr);
}

// ════════════════════════════════════════════════════════════
// INIT
// ════════════════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', async () => {
  const user = await adminCheckAuth();
  if (!user) return;

  updateSidebarUser(user);

  const tabLoadedFlags = {};
  const activateTab    = initAdminSidebar(tabLoadedFlags);

  initLogout();
  initQuickActions(activateTab);
  initGlobalSearch();
  initDonationsTab();
  initVolunteersTab();
  initUsersTab();

  // Load real stats immediately for dashboard overview
  loadAdminStats();
});
