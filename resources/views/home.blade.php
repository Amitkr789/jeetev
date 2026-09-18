@extends('layouts.app')
@section('title', 'Jeetvoltev')

@section('content')
    <div class="page-content">
      <!-- ===================== DASHBOARD ===================== -->
      <section class="page-section active" id="page-dashboard">

        <div class="page-header">
          <div>
            <span class="breadcrumb-eyebrow">Overview</span>
            <h1>Good morning, {{ auth('admin')->user()->name }}</h1>
            <p id="dashRangeSubtitle">Here's what's happening with your pipeline today.</p>
          </div>
          <div class="page-header-actions d-flex gap-2">
            <button class="btn btn-outline-secondary" id="dashExportBtn"><i class="bi bi-download me-1"></i>Export</button>
          </div>
        </div>

        <!-- ===================== FILTER BAR ===================== -->
        <div class="section-card mb-3">
          <div class="section-card-body py-2">
            <div class="d-flex flex-wrap align-items-center gap-2">

              <div class="btn-group flex-wrap" role="group" id="dashRangeButtons">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-range="today">Today</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-range="yesterday">Yesterday</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-range="week">This Week</button>
                <button type="button" class="btn btn-sm btn-primary" data-range="month">This Month</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-range="last_month">Last Month</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-range="year">This Year</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-range="custom">Custom</button>
              </div>

              <div id="dashCustomRangeWrap" class="d-none align-items-center gap-2">
                <input type="date" class="form-control form-control-sm" id="dashDateFrom" style="width:150px">
                <span class="text-muted-2 fs-12">to</span>
                <input type="date" class="form-control form-control-sm" id="dashDateTo" style="width:150px">
                <button type="button" class="btn btn-sm btn-dark" id="dashCustomApplyBtn">Apply</button>
              </div>

              @if($isSuperAdmin)
                <div class="ms-auto d-flex align-items-center gap-2">
                  <i class="bi bi-people fs-13 text-muted-2"></i>
                  <select class="form-select form-select-sm" id="dashAdminFilter" style="width:190px">
                    <option value="all">All Team</option>
                    @foreach($teamAdmins as $a)
                      <option value="{{ $a->id }}">{{ $a->name }}{{ $a->role === 'super_admin' ? ' (Super Admin)' : '' }}</option>
                    @endforeach
                  </select>
                </div>
              @endif

            </div>
          </div>
        </div>

        <!-- ===================== STAT CARDS ===================== -->
        <div class="row g-3 mb-3">
          <div class="col-6 col-lg-3">
            <div class="stat-card">
              <div>
                <div class="stat-label">Total Leads</div>
                <div class="stat-value" id="statTotalLeads">0</div>
                <div class="stat-trend" id="statLeadsTrend"></div>
              </div>
              <div class="stat-icon" style="background:var(--primary-50); color:var(--primary)"><i class="bi bi-person-lines-fill"></i></div>
            </div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="stat-card">
              <div>
                <div class="stat-label">Converted</div>
                <div class="stat-value" id="statConverted">0</div>
                <div class="stat-trend trend-up" id="statConversionRate">0% conversion</div>
              </div>
              <div class="stat-icon" style="background:var(--success-bg); color:var(--success)"><i class="bi bi-trophy"></i></div>
            </div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="stat-card">
              <div>
                <div class="stat-label">Total Earning</div>
                <div class="stat-value" id="statEarning">₹0</div>
                <div class="stat-trend" id="statEarningTrend"></div>
              </div>
              <div class="stat-icon" style="background:var(--success-bg); color:var(--success)"><i class="bi bi-cash-stack"></i></div>
            </div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="stat-card">
              <div>
                <div class="stat-label">Open Reminders</div>
                <div class="stat-value" id="statOpenReminders">0</div>
                <div class="stat-trend trend-down" id="statOverdueReminders">0 overdue</div>
              </div>
              <div class="stat-icon" style="background:var(--warning-bg); color:var(--warning)"><i class="bi bi-bell"></i></div>
            </div>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-6 col-lg-3">
            <div class="stat-card">
              <div>
                <div class="stat-label">Ad Leads (Meta)</div>
                <div class="stat-value" id="statAdLeads">0</div>
                <div class="stat-trend trend-up" id="statAdLeadsRate">0% converted</div>
              </div>
              <div class="stat-icon" style="background:var(--info-bg); color:var(--info)"><i class="bi bi-facebook"></i></div>
            </div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="stat-card">
              <div>
                <div class="stat-label">Avg. Invoice Value</div>
                <div class="stat-value" id="statAvgInvoice">₹0</div>
                <div class="stat-trend" id="statInvoiceCount">0 invoices</div>
              </div>
              <div class="stat-icon" style="background:var(--primary-50); color:var(--primary)"><i class="bi bi-receipt"></i></div>
            </div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="stat-card">
              <div>
                <div class="stat-label">Tasks Completed</div>
                <div class="stat-value" id="statTasksCompleted">0</div>
                <div class="stat-trend" id="statTasksTrend"></div>
              </div>
              <div class="stat-icon" style="background:var(--success-bg); color:var(--success)"><i class="bi bi-check2-square"></i></div>
            </div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="stat-card">
              <div>
                <div class="stat-label">Tasks Overdue</div>
                <div class="stat-value" id="statTasksOverdue">0</div>
                <div class="stat-trend" id="statChatEscalated"></div>
              </div>
              <div class="stat-icon" style="background:var(--warning-bg); color:var(--warning)"><i class="bi bi-exclamation-triangle"></i></div>
            </div>
          </div>
        </div>

        <!-- ===================== CHARTS ===================== -->
        <div class="row g-3 mb-3">
          <div class="col-lg-7">
            <div class="section-card h-100">
              <div class="section-card-head">
                <h2>Leads &amp; Earnings Trend</h2>
                <span class="fs-12 text-muted-2" id="trendRangeLabel">This Month</span>
              </div>
              <div class="section-card-body">
                <canvas id="trendChart" height="190"></canvas>
              </div>
            </div>
          </div>
          <div class="col-lg-5">
            <div class="section-card h-100">
              <div class="section-card-head"><h2>Leads by Source</h2></div>
              <div class="section-card-body">
                <canvas id="sourceChart" height="190"></canvas>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-lg-5">
            <div class="section-card h-100">
              <div class="section-card-head"><h2>Leads by Type</h2></div>
              <div class="section-card-body">
                <canvas id="typeChart" height="190"></canvas>
              </div>
            </div>
          </div>
          <div class="col-lg-7">
            <div class="section-card h-100">
              <div class="section-card-head">
                <h2>Ad Leads Funnel</h2>
                <span class="fs-12 text-muted-2">Meta / Facebook Lead Ads</span>
              </div>
              <div class="section-card-body">
                <div class="row text-center g-2" id="adFunnelBoxes">
                  <div class="col-3">
                    <div class="fs-24 fw-700" id="adTotal">0</div>
                    <div class="fs-12 text-muted-2">Received</div>
                  </div>
                  <div class="col-3">
                    <div class="fs-24 fw-700 text-success" id="adConverted">0</div>
                    <div class="fs-12 text-muted-2">Converted</div>
                  </div>
                  <div class="col-3">
                    <div class="fs-24 fw-700 text-warning" id="adPending">0</div>
                    <div class="fs-12 text-muted-2">Pending</div>
                  </div>
                  <div class="col-3">
                    <div class="fs-24 fw-700 text-danger" id="adDiscarded">0</div>
                    <div class="fs-12 text-muted-2">Discarded</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ===================== LEADERBOARD (super admin, All Team only) ===================== -->
        <div class="row g-3 mb-3 d-none" id="leaderboardRow">
          <div class="col-12">
            <div class="section-card">
              <div class="section-card-head">
                <h2>Team Leaderboard</h2>
                <span class="fs-12 text-muted-2" id="leaderboardRangeLabel">This Month</span>
              </div>
              <div class="section-card-body pt-1 table-responsive">
                <table class="table align-middle mb-0">
                  <thead>
                    <tr class="fs-12 text-muted-2">
                      <th>Admin</th>
                      <th>Leads</th>
                      <th>Earning</th>
                      <th>Tasks Completed</th>
                    </tr>
                  </thead>
                  <tbody id="leaderboardBody"></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- ===================== LISTS ===================== -->
        <div class="row g-3 mb-3">
          <div class="col-lg-4">
            <div class="section-card">
              <div class="section-card-head">
                <h2>Recent Leads</h2>
                <a href="{{ route('leads.index') }}" class="fs-13 fw-600" style="color:var(--primary)">View all</a>
              </div>
              <div class="section-card-body pt-1" id="dashRecentLeads"></div>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="section-card">
              <div class="section-card-head">
                <h2>Recent Invoices</h2>
              </div>
              <div class="section-card-body pt-1" id="dashRecentBills"></div>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="section-card">
              <div class="section-card-head">
                <h2>Upcoming Reminders</h2>
                <a href="{{ route('reminders.page') }}" class="fs-13 fw-600" style="color:var(--primary)">View all</a>
              </div>
              <div class="section-card-body pt-1" id="dashReminders"></div>
            </div>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-lg-6">
            <div class="section-card">
              <div class="section-card-head"><h2>Top Customers (by revenue)</h2></div>
              <div class="section-card-body pt-1" id="dashTopCustomers"></div>
            </div>
          </div>
        </div>

      </section>
    </div>

    <script>
      // Handed to dashboard.js so it knows the current role and, for a
      // super_admin, who it's allowed to filter the whole page by.
      window.DASHBOARD_CONFIG = {
        dataUrl: @json(route('dashboard.data')),
        isSuperAdmin: @json($isSuperAdmin),
      };
      /* ==========================================================================
   Dashboard — real data from DashboardController@data (AJAX)
   ========================================================================== */

(function () {
  "use strict";

  const cfg = window.DASHBOARD_CONFIG || {};
  let charts = { trend: null, source: null, type: null };
  let state = { range: "month", dateFrom: null, dateTo: null, adminId: "all" };

  /* ---------------- Small helpers (self-contained, no app.js dependency) ---------------- */

  const AVATAR_COLORS = ["#4338CA", "#0891B2", "#B45309", "#16A34A", "#DC2626", "#7C3AED", "#0E7490", "#C2410C"];
  function colorFor(str) {
    let h = 0;
    for (let i = 0; i < str.length; i++) h = str.charCodeAt(i) + ((h << 5) - h);
    return AVATAR_COLORS[Math.abs(h) % AVATAR_COLORS.length];
  }
  function initials(name) {
    return (name || "?").split(" ").map((p) => p[0]).slice(0, 2).join("").toUpperCase();
  }
  function avatarHtml(name, size) {
    const cls = size === "sm" ? "avatar avatar-sm" : "avatar";
    return `<div class="${cls}" style="background:${colorFor(name || "?")}">${initials(name)}</div>`;
  }
  function money(n) {
    n = Number(n) || 0;
    return "₹" + n.toLocaleString("en-IN", { maximumFractionDigits: 0 });
  }
  function formatDate(dateStr) {
    if (!dateStr) return "—";
    const d = new Date(dateStr.length > 10 ? dateStr : dateStr + "T00:00:00");
    return d.toLocaleDateString("en-US", { month: "short", day: "numeric" });
  }
  function trendHtml(pct, goodWhenUp = true) {
    const up = pct >= 0;
    const good = goodWhenUp ? up : !up;
    const cls = good ? "trend-up" : "trend-down";
    const icon = up ? "bi-arrow-up-short" : "bi-arrow-down-short";
    return `<span class="${cls}"><i class="bi ${icon}"></i>${Math.abs(pct)}% vs last period</span>`;
  }
  function typeBadgeClass(type) {
    return { hot: "status-hot", cold: "status-cold", warm: "status-warm", converted: "status-converted", dealer: "status-dealer" }[type] || "status-new";
  }

  /* ---------------- Filter bar ---------------- */

  function initFilterBar() {
    document.querySelectorAll("#dashRangeButtons [data-range]").forEach((btn) => {
      btn.addEventListener("click", () => {
        document.querySelectorAll("#dashRangeButtons [data-range]").forEach((b) => b.classList.replace("btn-primary", "btn-outline-secondary"));
        btn.classList.replace("btn-outline-secondary", "btn-primary");

        const range = btn.dataset.range;
        state.range = range;
        document.getElementById("dashCustomRangeWrap").classList.toggle("d-none", range !== "custom");
        document.getElementById("dashCustomRangeWrap").classList.toggle("d-flex", range === "custom");

        if (range !== "custom") loadDashboard();
      });
    });

    document.getElementById("dashCustomApplyBtn")?.addEventListener("click", () => {
      state.dateFrom = document.getElementById("dashDateFrom").value;
      state.dateTo = document.getElementById("dashDateTo").value;
      if (!state.dateFrom || !state.dateTo) return;
      loadDashboard();
    });

    document.getElementById("dashAdminFilter")?.addEventListener("change", (e) => {
      state.adminId = e.target.value;
      loadDashboard();
    });

    document.getElementById("dashExportBtn")?.addEventListener("click", () => window.print());
  }

  /* ---------------- Fetch + render ---------------- */

  function buildQuery() {
    const params = new URLSearchParams({ range: state.range });
    if (state.range === "custom") {
      params.set("date_from", state.dateFrom || "");
      params.set("date_to", state.dateTo || "");
    }
    if (cfg.isSuperAdmin) params.set("admin_id", state.adminId);
    return params.toString();
  }

  function loadDashboard() {
    fetch(`${cfg.dataUrl}?${buildQuery()}`, { headers: { "X-Requested-With": "XMLHttpRequest" } })
      .then((r) => r.json())
      .then((res) => {
        if (!res.success) return;
        renderSubtitle(res);
        renderCards(res);
        renderCharts(res);
        renderAdFunnel(res.ad_leads);
        renderLeaderboard(res);
        renderRecentLeads(res.recent_leads);
        renderRecentBills(res.recent_bills);
        renderReminders(res.upcoming_reminders);
        renderTopCustomers(res.top_customers);
      })
      .catch(() => {
        // Network/permission failure — leave the last successfully
        // rendered state on screen rather than blanking the dashboard.
      });
  }

  function renderSubtitle(res) {
    const who = res.scoped_admin_name ? ` · ${res.scoped_admin_name}` : res.is_super_admin ? " · All Team" : "";
    document.getElementById("dashRangeSubtitle").textContent = `Showing ${res.range.label}${who}`;
    document.getElementById("trendRangeLabel").textContent = res.range.label;
    document.getElementById("leaderboardRangeLabel").textContent = res.range.label;
  }

  function renderCards(res) {
    const L = res.leads, E = res.earnings, T = res.tasks, R = res.reminders, A = res.ad_leads;

    document.getElementById("statTotalLeads").textContent = L.total_in_range;
    document.getElementById("statLeadsTrend").outerHTML = `<div class="stat-trend" id="statLeadsTrend">${trendHtml(L.change_pct)}</div>`;

    document.getElementById("statConverted").textContent = L.converted_in_range;
    document.getElementById("statConversionRate").textContent = `${L.conversion_rate}% conversion`;

    document.getElementById("statEarning").textContent = money(E.total_earning);
    document.getElementById("statEarningTrend").outerHTML = `<div class="stat-trend" id="statEarningTrend">${trendHtml(E.change_pct)}</div>`;

    document.getElementById("statOpenReminders").textContent = R.open;
    document.getElementById("statOverdueReminders").textContent = `${R.overdue} overdue · ${R.due_today} today`;

    document.getElementById("statAdLeads").textContent = A.total;
    document.getElementById("statAdLeadsRate").textContent = `${A.conversion_rate}% converted`;

    document.getElementById("statAvgInvoice").textContent = money(E.avg_invoice_value);
    document.getElementById("statInvoiceCount").textContent = `${E.invoice_count} invoices`;

    document.getElementById("statTasksCompleted").textContent = T.completed;
    document.getElementById("statTasksTrend").outerHTML = `<div class="stat-trend" id="statTasksTrend">${trendHtml(T.change_pct)}</div>`;

    document.getElementById("statTasksOverdue").textContent = T.overdue;
  }

  function renderCharts(res) {
    const L = res.leads, E = res.earnings;
    const labels = Array.from(new Set([...Object.keys(L.trend), ...Object.keys(E.trend)])).sort();

    if (charts.trend) charts.trend.destroy();
    charts.trend = new Chart(document.getElementById("trendChart"), {
      data: {
        labels,
        datasets: [
          { type: "bar", label: "New Leads", data: labels.map((d) => L.trend[d] || 0), backgroundColor: "#6366F1", borderRadius: 6, yAxisID: "y", order: 2 },
          { type: "line", label: "Earnings (₹)", data: labels.map((d) => E.trend[d] || 0), borderColor: "#16A34A", backgroundColor: "#16A34A", tension: 0.35, yAxisID: "y1", order: 1 },
        ],
      },
      options: {
        responsive: true,
        interaction: { mode: "index", intersect: false },
        plugins: { legend: { position: "top", align: "end", labels: { boxWidth: 10, font: { size: 11 }, color: "#767B91" } } },
        scales: {
          x: { grid: { display: false }, ticks: { font: { size: 11 }, color: "#767B91" } },
          y: { position: "left", grid: { color: "#F0F1F7" }, ticks: { font: { size: 11 }, color: "#767B91" } },
          y1: { position: "right", grid: { display: false }, ticks: { font: { size: 11 }, color: "#767B91", callback: (v) => "₹" + v } },
        },
      },
    });

    if (charts.source) charts.source.destroy();
    const sourceLabels = Object.keys(L.by_source);
    charts.source = new Chart(document.getElementById("sourceChart"), {
      type: "doughnut",
      data: {
        labels: sourceLabels,
        datasets: [{ data: sourceLabels.map((k) => L.by_source[k]), backgroundColor: ["#4338CA", "#6366F1", "#0891B2", "#16A34A", "#D97706", "#DC2626", "#7C3AED", "#0E7490"], borderWidth: 0 }],
      },
      options: { plugins: { legend: { position: "right", labels: { boxWidth: 10, font: { size: 11 }, color: "#767B91" } } }, cutout: "68%" },
    });

    if (charts.type) charts.type.destroy();
    const typeLabels = Object.keys(L.by_type);
    charts.type = new Chart(document.getElementById("typeChart"), {
      type: "bar",
      data: {
        labels: typeLabels.map((t) => t.charAt(0).toUpperCase() + t.slice(1)),
        datasets: [{ data: typeLabels.map((k) => L.by_type[k]), backgroundColor: ["#DC2626", "#0891B2", "#D97706", "#16A34A", "#7C3AED"], borderRadius: 6, maxBarThickness: 34 }],
      },
      options: {
        indexAxis: "y",
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { color: "#F0F1F7" }, ticks: { font: { size: 11 }, color: "#767B91" } },
          y: { grid: { display: false }, ticks: { font: { size: 11 }, color: "#767B91" } },
        },
      },
    });
  }

  function renderAdFunnel(A) {
    document.getElementById("adTotal").textContent = A.total;
    document.getElementById("adConverted").textContent = A.converted;
    document.getElementById("adPending").textContent = A.pending;
    document.getElementById("adDiscarded").textContent = A.discarded;
  }

  function renderLeaderboard(res) {
    const row = document.getElementById("leaderboardRow");
    if (!res.is_super_admin || res.scoped_admin_id !== null || !res.leaderboard.length) {
      row.classList.add("d-none");
      return;
    }
    row.classList.remove("d-none");
    document.getElementById("leaderboardBody").innerHTML = res.leaderboard.map((a) => `
      <tr>
        <td><div class="cell-with-avatar">${avatarHtml(a.name, "sm")}<div><div class="fw-600 fs-13">${a.name}</div>${a.role === "super_admin" ? '<div class="fs-11 text-muted-2">Super Admin</div>' : ""}</div></div></td>
        <td>${a.leads}</td>
        <td class="fw-600">${money(a.earning)}</td>
        <td>${a.tasks_completed}</td>
      </tr>
    `).join("");
  }

  function renderRecentLeads(leads) {
    const wrap = document.getElementById("dashRecentLeads");
    if (!leads.length) {
      wrap.innerHTML = `<p class="fs-13 text-muted-2 mb-0">No leads in this range yet.</p>`;
      return;
    }
    wrap.innerHTML = leads.map((l) => `
      <div class="d-flex align-items-center gap-3 py-2 border-bottom" style="border-color:var(--border)!important">
        ${avatarHtml(l.customer_name)}
        <div class="flex-grow-1 min-w-0">
          <div class="fw-600 fs-13 text-truncate">${l.customer_name}</div>
          <div class="fs-12 text-muted-2 text-truncate">${l.product_name}</div>
        </div>
        <span class="badge-status ${typeBadgeClass(l.lead_type)}">${l.lead_type}</span>
      </div>
    `).join("");
  }

  function renderRecentBills(bills) {
    const wrap = document.getElementById("dashRecentBills");
    if (!bills.length) {
      wrap.innerHTML = `<p class="fs-13 text-muted-2 mb-0">No invoices in this range yet.</p>`;
      return;
    }
    wrap.innerHTML = bills.map((b) => `
      <div class="d-flex align-items-center gap-3 py-2 border-bottom" style="border-color:var(--border)!important">
        <div class="flex-grow-1 min-w-0">
          <div class="fw-600 fs-13 text-truncate">${b.customer_name}</div>
          <div class="fs-12 text-muted-2">${b.bill_number} · ${formatDate(b.billing_date)}</div>
        </div>
        <div class="fw-600 fs-13">${money(b.grand_total)}</div>
      </div>
    `).join("");
  }

  function renderReminders(reminders) {
    const wrap = document.getElementById("dashReminders");
    if (!reminders.length) {
      wrap.innerHTML = `<p class="fs-13 text-muted-2 mb-0">Nothing scheduled. Nice work!</p>`;
      return;
    }
    wrap.innerHTML = reminders.map((r) => `
      <div class="d-flex align-items-start gap-2 py-2 border-bottom" style="border-color:var(--border)!important">
        <i class="bi bi-clock text-muted-2 mt-1"></i>
        <div class="flex-grow-1 min-w-0">
          <div class="fw-600 fs-13 text-truncate">${r.note}</div>
          <div class="fs-12 text-muted-2">${r.lead ? r.lead.customer_name : ""} · ${formatDate(r.reminder_date)}${r.reminder_time ? ", " + r.reminder_time : ""}</div>
        </div>
      </div>
    `).join("");
  }

  function renderTopCustomers(customers) {
    const wrap = document.getElementById("dashTopCustomers");
    if (!customers.length) {
      wrap.innerHTML = `<p class="fs-13 text-muted-2 mb-0">No invoiced customers in this range yet.</p>`;
      return;
    }
    wrap.innerHTML = customers.map((c, i) => `
      <div class="d-flex align-items-center gap-3 py-2 border-bottom" style="border-color:var(--border)!important">
        <div class="fw-700 text-muted-2" style="width:20px">${i + 1}</div>
        <div class="flex-grow-1 min-w-0">
          <div class="fw-600 fs-13 text-truncate">${c.name}</div>
          <div class="fs-12 text-muted-2">${c.invoices} invoice(s)</div>
        </div>
        <div class="fw-600 fs-13">${money(c.total)}</div>
      </div>
    `).join("");
  }

  /* ---------------- Init ---------------- */

  document.addEventListener("DOMContentLoaded", () => {
    initFilterBar();
    loadDashboard();
  });
})();
    </script>
   
@endsection