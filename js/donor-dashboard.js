/**
 * js/donor-dashboard.js
 * Full logic for the Donor Dashboard (donor-dashboard.html)
 * ─ Auth guard (redirects to login if not logged in)
 * ─ Loads real donation data from api/donations.php?action=user-history
 * ─ Populates stats, recent donations, full table, receipts, impact numbers
 * ─ Sidebar navigation between sections
 * ─ Profile form (save via api/auth.php?action=update-profile)
 * ─ Export donations as CSV
 * ─ Recurring donation setup (UI + future API hook)
 * ─ Chart.js donation trend & cause distribution charts
 */

// ─────────────────────────────────────────────────────────────────────────────
// 1. Constants & helpers
// ─────────────────────────────────────────────────────────────────────────────
const fmt      = n  => Number(n).toLocaleString('en-IN');
const fmtRs    = n  => '\u20B9' + fmt(Math.round(Number(n)));
const fmtDate  = d  => new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
const el       = id => document.getElementById(id);
const setText  = (id, v) => { const e = el(id); if (e) e.textContent = v; };

const CAUSE_LABELS = {
  'general'      : 'General Fund',
  'poor-feeding' : 'Poor Feeding',
  'education'    : 'Education',
  'medical'      : 'Medical',
  'disaster'     : 'Disaster Relief',
};

// ─────────────────────────────────────────────────────────────────────────────
// 2. Auth guard
// ─────────────────────────────────────────────────────────────────────────────
async function checkAuth() {
  try {
    const res  = await fetch('api/auth.php?action=check', { credentials: 'include' });
    const data = await res.json();
    if (!data.logged_in) {
      window.location.href = 'login.html?redirect=donor-dashboard.html';
      return null;
    }
    return data.user;
  } catch {
    window.location.href = 'login.html?redirect=donor-dashboard.html';
    return null;
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. Sidebar navigation
// ─────────────────────────────────────────────────────────────────────────────
function initSidebar() {
  const navItems = document.querySelectorAll('.sidebar-nav .nav-item');
  navItems.forEach(item => {
    item.addEventListener('click', function(e) {
      e.preventDefault();
      const section = this.dataset.section;
      navItems.forEach(n => n.classList.remove('active'));
      this.classList.add('active');
      document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
      const target = document.getElementById(section + '-section');
      if (target) target.classList.add('active');
    });
  });

  // Quick-action buttons that switch sections
  document.querySelector('[data-action="view-impact"]')?.addEventListener('click', () => switchSection('impact'));
  document.querySelector('[data-action="update-profile"]')?.addEventListener('click', () => switchSection('profile'));
  document.querySelector('[data-action="download-receipt"]')?.addEventListener('click', () => switchSection('receipts'));
}

function switchSection(name) {
  document.querySelectorAll('.sidebar-nav .nav-item').forEach(n => {
    n.classList.toggle('active', n.dataset.section === name);
  });
  document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
  const t = document.getElementById(name + '-section');
  if (t) t.classList.add('active');
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. Load donation data
// ─────────────────────────────────────────────────────────────────────────────
async function loadDonations() {
  try {
    const res  = await fetch('api/donations.php?action=user-history', { credentials: 'include' });
    const data = await res.json();
    if (!data.success) return [];
    return data.data || [];
  } catch {
    return [];
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// 5. Populate stat cards
// ─────────────────────────────────────────────────────────────────────────────
function populateStats(donations, user) {
  const completed = donations.filter(d => d.payment_status === 'completed');
  const total     = completed.reduce((s, d) => s + parseFloat(d.amount || 0), 0);
  const taxSaving = total * 0.5;     // 50% under 80G
  const lives     = Math.round(total / 100); // rough impact multiplier

  setText('donorName',    user.name || user.full_name || 'Donor');
  setText('totalDonated', fmtRs(total));
  setText('donationCount', completed.length);
  setText('livesImpacted', fmt(lives));
  setText('taxSavings',   fmtRs(taxSaving));

  // Profile avatar initials fallback
  const avatar = el('profileAvatar');
  if (avatar && !avatar.src.includes('default-avatar.png')) return;
  const name  = user.name || user.full_name || 'D';
  const initials = name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
  if (avatar) { avatar.alt = initials; }

  // Impact numbers (rough ratios)
  setText('mealsProvided',     fmt(Math.round(total / 30)));
  setText('studentsSupported', fmt(Math.round(total / 500)));
  setText('medicalAid',        fmt(Math.round(total / 1000)));
  setText('familiesHelped',    fmt(Math.round(total / 2000)));
  setText('totalImpactValue',  fmt(Math.round(total * 3)));
}

// ─────────────────────────────────────────────────────────────────────────────
// 6. Recent donations (dashboard overview)
// ─────────────────────────────────────────────────────────────────────────────
function populateRecentDonations(donations) {
  const container = el('recentDonations');
  if (!container) return;
  const recent = donations.slice(0, 5);

  if (recent.length === 0) {
    container.innerHTML = `<div class="empty-state">
      <i class="fas fa-heart" style="font-size:2rem;color:var(--primary,#21808d);margin-bottom:12px;"></i>
      <p>No donations yet. <a href="donate.html">Make your first donation!</a></p>
    </div>`;
    return;
  }

  container.innerHTML = recent.map(d => `
    <div class="donation-item">
      <div class="donation-info">
        <span class="donation-cause">${CAUSE_LABELS[d.cause_name] || d.cause_name || 'General Fund'}</span>
        <span class="donation-date">${fmtDate(d.created_at)}</span>
      </div>
      <div class="donation-right">
        <span class="donation-amount">${fmtRs(d.amount)}</span>
        <span class="donation-status status-${d.payment_status}">${ucFirst(d.payment_status)}</span>
      </div>
    </div>
  `).join('');
}

// ─────────────────────────────────────────────────────────────────────────────
// 7. Full donations table
// ─────────────────────────────────────────────────────────────────────────────
let _allDonations = [];

function populateDonationsTable(donations) {
  _allDonations = donations;
  renderTable(donations);

  // Filters
  ['yearFilter', 'statusFilter', 'causeFilter'].forEach(id => {
    el(id)?.addEventListener('change', applyFilters);
  });

  el('exportDonations')?.addEventListener('click', () => exportCSV(donations));
}

function applyFilters() {
  const year   = el('yearFilter')?.value  || 'all';
  const status = el('statusFilter')?.value || 'all';
  const cause  = el('causeFilter')?.value  || 'all';

  let filtered = _allDonations;
  if (year !== 'all')   filtered = filtered.filter(d => new Date(d.created_at).getFullYear() == year);
  if (status !== 'all') filtered = filtered.filter(d => d.payment_status === status);
  if (cause !== 'all')  filtered = filtered.filter(d => d.cause_name === cause);
  renderTable(filtered);
}

function renderTable(donations) {
  const tbody = el('donationsTableBody');
  if (!tbody) return;

  if (donations.length === 0) {
    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:30px;color:#64748b;">No donations match your filters.</td></tr>`;
    return;
  }

  tbody.innerHTML = donations.map(d => `
    <tr>
      <td>${fmtDate(d.created_at)}</td>
      <td><strong>${fmtRs(d.amount)}</strong></td>
      <td>${CAUSE_LABELS[d.cause_name] || d.cause_name || '—'}</td>
      <td><span class="badge status-${d.payment_status}">${ucFirst(d.payment_status)}</span></td>
      <td>${d.payment_status === 'completed'
        ? `<button class="btn-link" onclick="downloadReceipt('${d.transaction_id}')">
             <i class="fas fa-download"></i> Receipt</button>`
        : '—'}</td>
      <td>${d.transaction_id}</td>
    </tr>
  `).join('');
}

// ─────────────────────────────────────────────────────────────────────────────
// 8. Tax receipts section
// ─────────────────────────────────────────────────────────────────────────────
function populateReceipts(donations) {
  const completed = donations.filter(d => d.payment_status === 'completed');

  const byYear = (y) => completed
    .filter(d => new Date(d.created_at).getFullYear() === y)
    .reduce((s, d) => s + parseFloat(d.amount || 0), 0);

  const curYear  = new Date().getFullYear();
  const prevYear = curYear - 1;

  setText('currentYearTax',  fmt(Math.round(byYear(curYear))));
  setText('previousYearTax', fmt(Math.round(byYear(prevYear))));

  // Wire download buttons
  document.querySelectorAll('[data-year]').forEach(btn => {
    btn.addEventListener('click', function() {
      const year = this.dataset.year;
      downloadTaxCertificate(year, byYear(parseInt(year)), donations.filter(d =>
        new Date(d.created_at).getFullYear() == year && d.payment_status === 'completed'
      ));
    });
  });

  // Individual receipts table
  const table = el('receiptsTable');
  if (!table) return;
  if (completed.length === 0) {
    table.innerHTML = '<p style="color:#64748b;">No completed donations yet.</p>';
    return;
  }
  table.innerHTML = `<table class="donations-table">
    <thead><tr><th>Date</th><th>Amount</th><th>Cause</th><th>Transaction ID</th><th>Receipt</th></tr></thead>
    <tbody>${completed.map(d => `
      <tr>
        <td>${fmtDate(d.created_at)}</td>
        <td>${fmtRs(d.amount)}</td>
        <td>${CAUSE_LABELS[d.cause_name] || d.cause_name}</td>
        <td style="font-size:12px;font-family:monospace;">${d.transaction_id}</td>
        <td><button class="btn-link" onclick="downloadReceipt('${d.transaction_id}')">
          <i class="fas fa-download"></i> Download</button></td>
      </tr>`).join('')}
    </tbody></table>`;
}

// ─────────────────────────────────────────────────────────────────────────────
// 9. Charts (Chart.js loaded lazily)
// ─────────────────────────────────────────────────────────────────────────────
function renderCharts(donations) {
  const completed = donations.filter(d => d.payment_status === 'completed');
  if (completed.length === 0) return;

  // ── Cause distribution (doughnut) ─────────────────────────────────────────
  const causeCtx = el('causeChart');
  if (causeCtx && window.Chart) {
    const causeTotals = {};
    completed.forEach(d => {
      const label = CAUSE_LABELS[d.cause_name] || d.cause_name || 'General';
      causeTotals[label] = (causeTotals[label] || 0) + parseFloat(d.amount);
    });
    new Chart(causeCtx, {
      type: 'doughnut',
      data: {
        labels: Object.keys(causeTotals),
        datasets: [{
          data: Object.values(causeTotals),
          backgroundColor: ['#21808d','#16a34a','#ea580c','#0369a1','#7c3aed'],
          borderWidth: 2,
          borderColor: '#fff',
        }]
      },
      options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });
  }

  // ── Monthly trend (bar) ───────────────────────────────────────────────────
  const trendCtx = el('trendChart');
  if (trendCtx && window.Chart) {
    const months = {};
    completed.forEach(d => {
      const key = new Date(d.created_at).toLocaleDateString('en-IN', { month: 'short', year: '2-digit' });
      months[key] = (months[key] || 0) + parseFloat(d.amount);
    });
    const sorted = Object.entries(months).slice(-12); // last 12 months
    new Chart(trendCtx, {
      type: 'bar',
      data: {
        labels: sorted.map(([k]) => k),
        datasets: [{
          label: 'Donations (₹)',
          data: sorted.map(([, v]) => v),
          backgroundColor: '#21808d',
          borderRadius: 4,
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { ticks: { callback: v => '₹' + fmt(v) } } }
      }
    });
  }
}

// Lazy-load Chart.js only when Impact section is visited
function ensureChartJS(cb) {
  if (window.Chart) { cb(); return; }
  const s = document.createElement('script');
  s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
  s.onload = cb;
  document.head.appendChild(s);
}

// ─────────────────────────────────────────────────────────────────────────────
// 10. Profile form
// ─────────────────────────────────────────────────────────────────────────────
function populateProfile(user) {
  const form = el('profileForm');
  if (!form) return;

  const setVal = (id, v) => { const e = el(id); if (e) e.value = v || ''; };
  setVal('fullName',  user.name || user.full_name || '');
  setVal('email',     user.email || '');
  setVal('phone',     user.phone || '');
  setVal('address',   user.address || '');
  setVal('panNumber', user.pan_number || '');

  form.addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    try {
      const fd = new FormData(form);
      fd.append('action', 'update-profile');

      // Add fresh CSRF token
      const csrfRes = await fetch('api/csrf-token.php', { credentials: 'include' });
      const csrfData = await csrfRes.json();
      fd.append('csrf_token', csrfData.csrf_token || '');

      const res  = await fetch('api/auth.php', { method: 'POST', body: fd, credentials: 'include' });
      const data = await res.json();

      if (data.success) {
        showToast('Profile updated successfully!', 'success');
      } else {
        showToast(data.message || 'Update failed. Please try again.', 'error');
      }
    } catch {
      showToast('Network error. Please try again.', 'error');
    } finally {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-save"></i> Update Profile';
    }
  });

  el('cancelEdit')?.addEventListener('click', () => switchSection('dashboard'));
}

// ─────────────────────────────────────────────────────────────────────────────
// 11. Profile dropdown & logout
// ─────────────────────────────────────────────────────────────────────────────
function initProfileDropdown() {
  const toggle = document.querySelector('.dropdown-toggle');
  const menu   = document.querySelector('.dropdown-menu');
  if (!toggle || !menu) return;

  toggle.addEventListener('click', e => {
    e.stopPropagation();
    menu.classList.toggle('open');
  });
  document.addEventListener('click', () => menu.classList.remove('open'));

  document.querySelector('.logout-btn')?.addEventListener('click', async () => {
    try {
      await fetch('api/auth.php?action=logout', { credentials: 'include' });
    } finally {
      window.location.href = 'index.html';
    }
  });
}

// ─────────────────────────────────────────────────────────────────────────────
// 12. Recurring donation modal
// ─────────────────────────────────────────────────────────────────────────────
function initRecurringModal() {
  const openBtn = el('setupRecurring');
  const modal   = el('recurringModal');
  const form    = el('recurringForm');
  if (!openBtn || !modal) return;

  openBtn.addEventListener('click', () => {
    el('startDate').min = new Date().toISOString().split('T')[0];
    modal.classList.add('open');
  });

  modal.querySelectorAll('.modal-close').forEach(btn =>
    btn.addEventListener('click', () => modal.classList.remove('open'))
  );

  form?.addEventListener('submit', async function(e) {
    e.preventDefault();
    // TODO: POST to api/recurring.php once built
    showToast('Recurring donation set up! (coming soon — will charge monthly via Paytm)', 'info');
    modal.classList.remove('open');
  });
}

// ─────────────────────────────────────────────────────────────────────────────
// 13. Donation detail modal
// ─────────────────────────────────────────────────────────────────────────────
function initDonationModal() {
  const modal = el('donationModal');
  modal?.querySelectorAll('.modal-close').forEach(btn =>
    btn.addEventListener('click', () => modal.classList.remove('open'))
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// 14. CSV export
// ─────────────────────────────────────────────────────────────────────────────
function exportCSV(donations) {
  if (!donations.length) { showToast('No donations to export.', 'info'); return; }

  const header  = ['Date', 'Amount (INR)', 'Cause', 'Status', 'Transaction ID', 'Payment Mode'];
  const rows    = donations.map(d => [
    fmtDate(d.created_at),
    d.amount,
    CAUSE_LABELS[d.cause_name] || d.cause_name,
    d.payment_status,
    d.transaction_id,
    d.payment_mode || 'Paytm'
  ]);

  const csv  = [header, ...rows].map(r => r.map(v => `"${v}"`).join(',')).join('\n');
  const blob = new Blob([csv], { type: 'text/csv' });
  const a    = document.createElement('a');
  a.href     = URL.createObjectURL(blob);
  a.download = 'my-donations.csv';
  a.click();
  URL.revokeObjectURL(a.href);
}

// ─────────────────────────────────────────────────────────────────────────────
// 15. Receipt download (opens new tab / generates basic printable)
// ─────────────────────────────────────────────────────────────────────────────
function downloadReceipt(txnId) {
  const donation = _allDonations.find(d => d.transaction_id === txnId);
  if (!donation) { showToast('Receipt not found.', 'error'); return; }

  const win = window.open('', '_blank');
  win.document.write(`
    <!DOCTYPE html><html lang="en"><head>
      <meta charset="UTF-8"><title>Donation Receipt - ${txnId}</title>
      <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 40px auto; color: #1a1a1a; }
        .header { text-align: center; border-bottom: 2px solid #21808d; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { color: #21808d; font-size: 22px; margin: 0; }
        .header p  { margin: 4px 0; color: #555; font-size: 13px; }
        .row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; font-size: 14px; }
        .row .label { color: #555; }
        .row .value { font-weight: 600; }
        .amount-row .value { color: #21808d; font-size: 20px; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #888; }
        .badge { background: #dcfce7; color: #15803d; padding: 2px 10px; border-radius: 20px; font-size: 12px; }
      </style>
    </head><body>
      <div class="header">
        <h1>Sai Seva Foundation</h1>
        <p>Sri Dutta Sai Manga Bharadwaja Trust</p>
        <p>sadgurubharadwaja.org | admin@sadgurubharadwaja.org</p>
        <p style="margin-top:12px;font-size:15px;font-weight:600;">Donation Receipt</p>
      </div>
      <div class="row amount-row"><span class="label">Amount Donated</span><span class="value">${fmtRs(donation.amount)}</span></div>
      <div class="row"><span class="label">Donor Name</span><span class="value">${donation.donor_name}</span></div>
      <div class="row"><span class="label">Email</span><span class="value">${donation.donor_email}</span></div>
      <div class="row"><span class="label">Cause</span><span class="value">${CAUSE_LABELS[donation.cause_name] || donation.cause_name}</span></div>
      <div class="row"><span class="label">Date</span><span class="value">${fmtDate(donation.created_at)}</span></div>
      <div class="row"><span class="label">Transaction ID</span><span class="value" style="font-size:12px;font-family:monospace;">${txnId}</span></div>
      <div class="row"><span class="label">Payment Mode</span><span class="value">${donation.payment_mode || 'Paytm'}</span></div>
      <div class="row"><span class="label">Status</span><span class="value"><span class="badge">${ucFirst(donation.payment_status)}</span></span></div>
      <div class="row"><span class="label">80G Tax Deduction</span><span class="value">${fmtRs(donation.amount * 0.5)} (50% of amount)</span></div>
      <div class="footer">
        <p>This is a computer-generated receipt. No signature required.</p>
        <p>For queries contact admin@sadgurubharadwaja.org</p>
      </div>
      <script>window.onload = () => window.print();<\/script>
    </body></html>
  `);
  win.document.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// 16. Toast notifications
// ─────────────────────────────────────────────────────────────────────────────
function showToast(msg, type = 'info') {
  const colours = { success: '#16a34a', error: '#dc2626', info: '#21808d', warning: '#ea580c' };
  const n = document.createElement('div');
  n.style.cssText = `
    position:fixed;top:20px;right:20px;z-index:10000;
    background:${colours[type]};color:#fff;padding:14px 20px;
    border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,0.2);
    font-size:14px;max-width:360px;animation:slideIn .25s ease;
    display:flex;align-items:center;gap:12px;`;
  n.innerHTML = `<span>${msg}</span>
    <button onclick="this.parentElement.remove()" style="background:none;border:none;color:#fff;font-size:20px;cursor:pointer;">×</button>`;
  document.body.appendChild(n);
  setTimeout(() => n.isConnected && n.remove(), 5000);
}

// ─────────────────────────────────────────────────────────────────────────────
// 17. Utility
// ─────────────────────────────────────────────────────────────────────────────
function ucFirst(str) {
  return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
}

const style = document.createElement('style');
style.textContent = `
  @keyframes slideIn { from{transform:translateX(110%);opacity:0} to{transform:translateX(0);opacity:1} }
  .dropdown-menu.open { display:block!important; }
  .modal.open { display:flex!important; }
  .status-completed { background:#dcfce7;color:#15803d;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:600; }
  .status-pending   { background:#fef3c7;color:#92400e;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:600; }
  .status-failed    { background:#fee2e2;color:#991b1b;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:600; }
  .donation-item { display:flex;justify-content:space-between;align-items:center;padding:14px 16px;border-bottom:1px solid #e2e8f0;gap:12px; }
  .donation-cause { font-weight:600;font-size:14px; }
  .donation-date  { font-size:12px;color:#64748b; }
  .donation-right { text-align:right;flex-shrink:0; }
  .donation-amount{ font-weight:700;font-size:15px;color:#21808d;display:block; }
  .btn-link       { background:none;border:none;color:#21808d;cursor:pointer;font-size:13px;text-decoration:underline; }
  .empty-state    { text-align:center;padding:40px 20px;color:#64748b; }
  .badge { display:inline-block; }
`;
document.head.appendChild(style);

// ─────────────────────────────────────────────────────────────────────────────
// 18. Init
// ─────────────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
  const user = await checkAuth();
  if (!user) return;

  initSidebar();
  initProfileDropdown();
  initRecurringModal();
  initDonationModal();

  // Show loading spinner
  const overlay = el('loadingOverlay');
  if (overlay) overlay.style.display = 'flex';

  const donations = await loadDonations();

  if (overlay) overlay.style.display = 'none';

  populateStats(donations, user);
  populateRecentDonations(donations);
  populateDonationsTable(donations);
  populateReceipts(donations);
  populateProfile(user);

  // Lazy-render charts when Impact section becomes visible
  document.querySelectorAll('.sidebar-nav .nav-item').forEach(item => {
    item.addEventListener('click', function() {
      if (this.dataset.section === 'impact') {
        ensureChartJS(() => renderCharts(donations));
      }
    });
  });
});
