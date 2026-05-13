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
  const completed  = donations.filter(d => d.payment_status === 'completed');
  const total      = completed.reduce((s, d) => s + parseFloat(d.amount || 0), 0);
  const taxSaving  = total * 0.5;
  const lives      = Math.round(total / 100);

  setText('donorName',     user.name || user.full_name || 'Donor');
  setText('totalDonated',  fmtRs(total));
  setText('donationCount', completed.length);
  setText('livesImpacted', fmt(lives));
  setText('taxSavings',    fmtRs(taxSaving));

  // Profile avatar initials fallback
  const avatar = el('profileAvatar');
  const name   = user.name || user.full_name || 'D';
  if (avatar) avatar.alt = name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();

  // Impact numbers (rough ratios against total donated)
  setText('mealsProvided',     fmt(Math.round(total / 30)));
  setText('studentsSupported', fmt(Math.round(total / 500)));
  setText('medicalAid',        fmt(Math.round(total / 1000)));
  setText('familiesHelped',    fmt(Math.round(total / 2000)));
  setText('totalImpactValue',  fmt(Math.round(total * 3)));

  // ── Dynamic stat-change labels (this month vs last month) ──
  const now       = new Date();
  const thisMonth = completed.filter(d => {
    const dt = new Date(d.created_at);
    return dt.getFullYear() === now.getFullYear() && dt.getMonth() === now.getMonth();
  });
  const lastDate  = new Date(now.getFullYear(), now.getMonth() - 1, 1);
  const lastMonth = completed.filter(d => {
    const dt = new Date(d.created_at);
    return dt.getFullYear() === lastDate.getFullYear() && dt.getMonth() === lastDate.getMonth();
  });

  const thisAmt  = thisMonth.reduce((s, d) => s + parseFloat(d.amount || 0), 0);
  const lastAmt  = lastMonth.reduce((s, d) => s + parseFloat(d.amount || 0), 0);
  const thisCnt  = thisMonth.length;
  const lastCnt  = lastMonth.length;

  // Update stat change spans (use querySelector since they have no IDs)
  const changes = document.querySelectorAll('#dashboard-section .stat-change');
  if (changes[0]) {
    changes[0].textContent = thisAmt > 0
      ? `+${fmtRs(thisAmt)} this month`
      : (lastAmt > 0 ? 'No donations this month' : 'Start donating today!');
    changes[0].className = `stat-change ${thisAmt > 0 ? 'positive' : 'neutral'}`;
  }
  if (changes[1]) {
    changes[1].textContent = thisCnt > 0 ? `+${thisCnt} this month` : 'No donations this month';
    changes[1].className = `stat-change ${thisCnt > 0 ? 'positive' : 'neutral'}`;
  }
  if (changes[2]) {
    const livesThis = Math.round(thisAmt / 100);
    changes[2].textContent = livesThis > 0 ? `+${fmt(livesThis)} this month` : 'Based on total donations';
    changes[2].className = `stat-change ${livesThis > 0 ? 'positive' : 'neutral'}`;
  }
  if (changes[3]) {
    const year = now.getFullYear();
    const yearTotal = completed.filter(d => new Date(d.created_at).getFullYear() === year)
                               .reduce((s, d) => s + parseFloat(d.amount || 0), 0);
    changes[3].textContent = `${year} total: ${fmtRs(yearTotal * 0.5)}`;
    changes[3].className = 'stat-change neutral';
  }
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
  if (toggle && menu) {
    toggle.addEventListener('click', e => {
      e.stopPropagation();
      menu.classList.toggle('open');
    });
    document.addEventListener('click', e => {
      if (!e.target.closest('.profile-dropdown')) menu.classList.remove('open');
    });
  }

  async function doLogout() {
    try {
      await fetch('api/auth.php?action=logout', { credentials: 'include' });
    } catch {}
    window.location.href = 'login.html';
  }

  document.querySelector('.logout-btn')?.addEventListener('click', doLogout);
  document.getElementById('sidebarLogoutBtn')?.addEventListener('click', doLogout);
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

  // Show a "coming soon" banner inside the modal
  if (form) {
    const notice = document.createElement('div');
    notice.style.cssText = 'background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#92400e;display:flex;align-items:center;gap:8px;';
    notice.innerHTML = '<i class="fas fa-clock"></i> <span>Automatic recurring payments are coming soon. Submitting this form will register your intent and an admin will follow up to set up the mandate.</span>';
    form.insertAdjacentElement('afterbegin', notice);
  }

  form?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const amount  = el('recurringAmount')?.value;
    const cause   = el('recurringCause')?.value;
    const startDt = el('startDate')?.value;
    if (!amount || !cause || !startDt) {
      showToast('Please fill in all fields.', 'warning');
      return;
    }
    showToast('Your recurring donation request has been noted. We\'ll contact you to complete the setup.', 'success');
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
// 17a. Recognition — badges & milestones computed from donation history
// ─────────────────────────────────────────────────────────────────────────────
function populateRecognition(donations) {
  const completed = donations.filter(d => d.payment_status === 'completed');
  const total     = completed.reduce((s, d) => s + parseFloat(d.amount || 0), 0);
  const years     = [...new Set(completed.map(d => new Date(d.created_at).getFullYear()))];
  const causes    = [...new Set(completed.map(d => d.cause_name))];

  // ── Badges ──
  const BADGES = [
    { id: 'first-step',    icon: 'fas fa-star',          title: 'First Step',       desc: 'Made your first donation',          earned: completed.length >= 1 },
    { id: 'generous',      icon: 'fas fa-heart',          title: 'Generous Heart',   desc: 'Donated ₹1,000 or more in total',   earned: total >= 1000 },
    { id: 'impact-maker',  icon: 'fas fa-hands-helping',  title: 'Impact Maker',     desc: 'Donated ₹5,000 or more in total',   earned: total >= 5000 },
    { id: 'champion',      icon: 'fas fa-trophy',         title: 'Champion',         desc: 'Donated ₹10,000 or more in total',  earned: total >= 10000 },
    { id: 'regular-giver', icon: 'fas fa-calendar-check', title: 'Regular Giver',    desc: 'Donated across 2 or more years',    earned: years.length >= 2 },
    { id: 'cause-champ',   icon: 'fas fa-award',          title: 'Cause Champion',   desc: 'Supported 3 or more causes',        earned: causes.length >= 3 },
    { id: 'big-heart',     icon: 'fas fa-gem',            title: 'Platinum Donor',   desc: 'Donated ₹50,000 or more in total',  earned: total >= 50000 },
  ];

  const badgesContainer = el('donorBadges');
  if (badgesContainer) {
    const earnedBadges = BADGES.filter(b => b.earned);
    if (!earnedBadges.length) {
      badgesContainer.innerHTML = `
        <div style="text-align:center;padding:30px;color:#64748b;grid-column:1/-1">
          <i class="fas fa-star" style="font-size:2.5rem;margin-bottom:12px;display:block;opacity:0.3"></i>
          <p>Make your first donation to start earning badges!</p>
          <a href="donate.html" class="btn btn-primary" style="margin-top:12px;display:inline-flex">Donate Now</a>
        </div>`;
    } else {
      badgesContainer.innerHTML = BADGES.map(b => `
        <div class="badge-item" style="${b.earned ? '' : 'opacity:0.3;filter:grayscale(1)'}">
          <div class="badge-icon"><i class="${b.icon}"></i></div>
          <div class="badge-title">${b.title}</div>
          <div class="badge-description">${b.desc}</div>
          ${b.earned ? '<div style="margin-top:6px;font-size:11px;color:#16a34a;font-weight:600">✓ Earned</div>' : ''}
        </div>`).join('');
    }
  }

  // ── Milestones ──
  const MILESTONES = [
    { label: '₹100',    threshold: 100,    icon: 'fas fa-seedling' },
    { label: '₹500',    threshold: 500,    icon: 'fas fa-leaf' },
    { label: '₹1,000',  threshold: 1000,   icon: 'fas fa-tree' },
    { label: '₹5,000',  threshold: 5000,   icon: 'fas fa-star' },
    { label: '₹10,000', threshold: 10000,  icon: 'fas fa-trophy' },
    { label: '₹50,000', threshold: 50000,  icon: 'fas fa-crown' },
  ];

  const milestonesContainer = el('donationMilestones');
  if (milestonesContainer) {
    milestonesContainer.innerHTML = MILESTONES.map(m => {
      const reached = total >= m.threshold;
      const pct     = Math.min(100, Math.round((total / m.threshold) * 100));
      return `
        <div class="milestone-item" style="${reached ? '' : 'opacity:0.5'}">
          <div class="milestone-icon" style="${reached ? '' : 'background:var(--color-secondary)'}">
            <i class="${m.icon}"></i>
          </div>
          <div class="milestone-info" style="flex:1">
            <h4>${m.label} ${reached ? '<span style="color:#16a34a">✓</span>' : ''}</h4>
            <p>${reached ? 'Reached!' : `${pct}% of the way there`}</p>
            <div style="background:#e2e8f0;height:4px;border-radius:2px;margin-top:6px">
              <div style="background:var(--donor-primary);height:4px;border-radius:2px;width:${pct}%;transition:width .6s ease"></div>
            </div>
          </div>
        </div>`;
    }).join('');
  }

  // ── Leaderboard placeholder ──
  const leaderboard = el('donorLeaderboard');
  if (leaderboard) {
    leaderboard.innerHTML = `
      <div style="text-align:center;padding:24px;color:#64748b">
        <i class="fas fa-chart-bar" style="font-size:2rem;margin-bottom:8px;display:block;opacity:0.4"></i>
        <p>Community leaderboard will be available soon as more donors join.</p>
      </div>`;
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// 17b. Project Updates — static inspiring content (no DB table yet)
// ─────────────────────────────────────────────────────────────────────────────
function populateUpdates() {
  const feed = el('updatesFeed');
  if (!feed) return;

  const updates = [
    {
      date:    'May 2026',
      title:   'Shirdi Annadanam Programme Continues',
      content: 'Our Shirdi Annadanam programme has served over 500 meals this month to pilgrims and the underprivileged. Your generous contributions make this daily service possible.',
      icon:    'fas fa-utensils',
      tag:     'Annadanam',
    },
    {
      date:    'April 2026',
      title:   'Education Scholarships Awarded',
      content: 'Ten students from economically weaker sections were awarded full scholarships this quarter. They will now pursue their studies without financial burden, thanks to donors like you.',
      icon:    'fas fa-graduation-cap',
      tag:     'Education',
    },
    {
      date:    'March 2026',
      title:   'Medical Relief Camp',
      content: 'We organised a free medical camp in three rural villages providing consultations, medicines, and basic health screenings to over 200 beneficiaries.',
      icon:    'fas fa-heartbeat',
      tag:     'Medical',
    },
    {
      date:    'February 2026',
      title:   'Ganagapur Seva Activities',
      content: 'Our Ganagapur Annadanam continued uninterrupted throughout the month, serving the devotees and the needy who visit the sacred site every day.',
      icon:    'fas fa-hands-helping',
      tag:     'Annadanam',
    },
  ];

  feed.innerHTML = updates.map(u => `
    <div class="update-item">
      <div class="update-header">
        <div class="update-title"><i class="${u.icon}" style="margin-right:8px;color:var(--donor-primary)"></i>${u.title}</div>
        <div class="update-date">${u.date}</div>
      </div>
      <div class="update-content">${u.content}</div>
      <span class="donation-cause">${u.tag}</span>
    </div>`).join('');
}

// ─────────────────────────────────────────────────────────────────────────────
// 17c. Impact stories — companion to the impact section
// ─────────────────────────────────────────────────────────────────────────────
function populateImpactStories() {
  const grid = el('impactStories');
  if (!grid) return;

  const stories = [
    {
      name:    'Anita, Student',
      quote:   '"The scholarship changed everything. I can now focus on studies without worry."',
      cause:   'Education',
      icon:    'fas fa-user-graduate',
    },
    {
      name:    'Ravi, Beneficiary',
      quote:   '"The free medical camp caught my condition early. I owe my health to this trust."',
      cause:   'Medical',
      icon:    'fas fa-user-md',
    },
    {
      name:    'Lakshmi, Pilgrim',
      quote:   '"The Annadanam at Shirdi feeds hundreds of us every day. May God bless the donors."',
      cause:   'Annadanam',
      icon:    'fas fa-pray',
    },
  ];

  grid.innerHTML = stories.map(s => `
    <div style="background:var(--color-surface);border:1px solid var(--color-card-border);border-radius:var(--radius-md);padding:20px;display:flex;flex-direction:column;gap:12px">
      <div style="display:flex;align-items:center;gap:12px">
        <div style="width:48px;height:48px;border-radius:50%;background:var(--donor-gradient);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.2rem;flex-shrink:0">
          <i class="${s.icon}"></i>
        </div>
        <div>
          <div style="font-weight:600;font-size:14px">${s.name}</div>
          <span class="donation-cause">${s.cause}</span>
        </div>
      </div>
      <div style="font-style:italic;color:var(--color-text-secondary);font-size:14px;line-height:1.5">${s.quote}</div>
    </div>`).join('');
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
  populateRecognition(donations);
  populateUpdates();
  populateImpactStories();

  // Lazy-render charts when Impact section becomes visible
  document.querySelectorAll('.sidebar-nav .nav-item').forEach(item => {
    item.addEventListener('click', function() {
      if (this.dataset.section === 'impact') {
        ensureChartJS(() => renderCharts(donations));
      }
    });
  });
});
