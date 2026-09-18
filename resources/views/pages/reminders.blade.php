@extends('layouts.app')
@section('title', 'Reminders | Dalal Adda')

@section('content')

<style>
  /* ---- Reminder status badges ---- */
  .badge-status.rs-scheduled { background: var(--info-bg);    color: var(--info); }
  .badge-status.rs-missed    { background: var(--danger-bg);  color: var(--danger); }
  .badge-status.rs-silent    { background: #EEF1F6;           color: var(--text-muted); }
  .badge-status.rs-completed { background: var(--success-bg); color: var(--success); }
  .badge-status.rs-note      { background: var(--gold-bg);    color: var(--gold); }

  /* ---- Subtle left-accent instead of a full row tint, so the reminders
     table stays readable even with a lot of rows ---- */
  .crm-table tbody tr[data-rem-status="missed"]    { border-left: 3px solid var(--danger); }
  .crm-table tbody tr[data-rem-status="scheduled"] { border-left: 3px solid var(--info); }
  .crm-table tbody tr[data-rem-status="completed"] { border-left: 3px solid var(--success); }
  .crm-table tbody tr[data-rem-status="silent"]    { border-left: 3px solid var(--border); opacity: .7; }
  .crm-table tbody tr[data-rem-status="note"]      { border-left: 3px solid var(--gold); }

  /* ---- Searchable single-select "lead" control (reused for the filter
     bar and for the add-reminder modal) ---- */
  .ms-control { position: relative; }
  .ms-toggle {
    width: 100%; text-align: left; background: #FBFBFE; border: 1.5px solid var(--border);
    border-radius: 10px; padding: 10px 13px; font-size: 13.5px; color: var(--text);
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
  }
  .ms-toggle .chips { display: flex; flex-wrap: wrap; gap: 5px; }
  .ms-toggle .chip { background: var(--primary-50); color: var(--primary); font-size: 11.5px; font-weight: 700; padding: 2px 8px; border-radius: 20px; }
  .ms-toggle .placeholder { color: #A7ABC2; }
  .ms-panel {
    display: none; position: absolute; top: calc(100% + 6px); left: 0; right: 0; z-index: 50;
    background: #fff; border: 1px solid var(--border); border-radius: 12px; box-shadow: var(--shadow-lg);
    padding: 10px; max-height: 240px; overflow-y: auto;
  }
  .ms-panel.show { display: block; }
  .ms-panel input.ms-search { margin-bottom: 8px; }
  .ms-option { display: flex; align-items: center; gap: 9px; padding: 7px 6px; border-radius: 7px; font-size: 13px; cursor: pointer; }
  .ms-option:hover { background: var(--primary-50); }

  .apt-type-group { display: flex; gap: 10px; }
  .apt-type-option { flex: 1; }
  .apt-type-option input { display: none; }
  .apt-type-option label {
    display: flex; align-items: center; justify-content: center; gap: 7px;
    border: 1.5px solid var(--border); border-radius: 10px; padding: 9px; font-size: 13px; font-weight: 600;
    color: var(--text-muted); cursor: pointer; background: #FBFBFE;
  }
  .apt-type-option input:checked + label { border-color: var(--primary); color: var(--primary); background: var(--primary-50); }
</style>

<div class="page-content">
  <section class="page-section active" id="page-reminders">

    <div class="page-header">
      <div>
        <span class="breadcrumb-eyebrow">Pipeline</span>
        <h1>Reminders</h1>
        <p>Every follow-up across every lead, in one place.</p>
      </div>
      <div class="page-header-actions d-flex gap-2">
        <button class="btn btn-primary" id="openAddReminderBtn">
          <i class="bi bi-plus-lg me-1"></i>Add Reminder
        </button>
      </div>
    </div>

    <!-- Stat cards -->
    <div class="row g-3 mb-3">
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div>
            <div class="stat-label">Total Reminders</div>
            <div class="stat-value" id="statTotal">0</div>
          </div>
          <div class="stat-icon" style="background:var(--primary-50); color:var(--primary)"><i class="bi bi-bell"></i></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div>
            <div class="stat-label">Scheduled</div>
            <div class="stat-value" id="statScheduled">0</div>
          </div>
          <div class="stat-icon" style="background:var(--info-bg); color:var(--info)"><i class="bi bi-calendar-event"></i></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div>
            <div class="stat-label">Missed</div>
            <div class="stat-value" id="statMissed">0</div>
          </div>
          <div class="stat-icon" style="background:var(--danger-bg); color:var(--danger)"><i class="bi bi-bell-slash"></i></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div>
            <div class="stat-label">Silenced</div>
            <div class="stat-value" id="statSilent">0</div>
          </div>
          <div class="stat-icon" style="background:#EEF1F6; color:var(--text-muted)"><i class="bi bi-volume-mute"></i></div>
        </div>
      </div>
    </div>

    <!-- Reminder vs Note tabs — same list/table below, just partitioned by kind -->
    <ul class="nav lead-tabs mb-3" id="reminderKindTabs">
      <li class="nav-item"><a class="nav-link active" data-kind="reminder" href="#">Reminders <span class="badge-count" id="remindersCountTab"></span></a></li>
      <li class="nav-item"><a class="nav-link" data-kind="note" href="#">Notes <span class="badge-count" id="notesCountTab"></span></a></li>
    </ul>

    <!-- Filter bar -->
    <div class="filter-bar">
      <div class="filter-search">
        <i class="bi bi-search"></i>
        <input type="text" class="form-control" id="filterSearch" placeholder="Search by note, customer or product...">
      </div>
      <select class="form-select" id="filterStatus" style="max-width:170px">
        <option value="">All Statuses</option>
        <option value="scheduled">Scheduled</option>
        <option value="missed">Missed</option>
        <option value="silent">Silenced</option>
        <option value="completed">Completed</option>
      </select>
      <div class="ms-control" id="filterLeadMsControl" style="max-width:360px">
        <button type="button" class="ms-toggle" id="filterLeadMsToggle">
          <span class="chips" id="filterLeadMsChips"><span class="placeholder">All leads</span></span>
          <i class="bi bi-chevron-down"></i>
        </button>
        <div class="ms-panel" id="filterLeadMsPanel">
          <input type="text" class="form-control ms-search" id="filterLeadMsSearch" placeholder="Search leads...">
          <div id="filterLeadMsOptions"></div>
        </div>
      </div>
      <button class="btn btn-outline-secondary btn-sm" id="clearFiltersBtn">Clear</button>
    </div>

    <div id="activeFilterPills" class="d-flex flex-wrap gap-2 mb-2"></div>

    <!-- Table -->
    <div class="section-card">
      <div class="table-wrap">
        <table class="crm-table">
          <thead>
            <tr>
              <th>Lead</th>
              <th>Note</th>
              <th>Date</th>
              <th>Time</th>
              <th>Type</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="remindersTbody"></tbody>
        </table>
      </div>
      <div class="crm-pagination">
        <span class="page-info" id="pageInfo"></span>
        <div class="d-flex gap-1" id="pageButtons"></div>
      </div>
    </div>
  </section>
</div>

<!-- ===================== ADD REMINDER MODAL ===================== -->
<div class="modal fade" id="addReminderModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="addReminderForm">
        <div class="modal-header">
          <h5 class="modal-title">Add reminder</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Lead (search &amp; select)</label>
            <div class="ms-control" id="addRemLeadMsControl">
              <button type="button" class="ms-toggle" id="addRemLeadMsToggle">
                <span class="chips" id="addRemLeadMsChips"><span class="placeholder">Select a lead...</span></span>
                <i class="bi bi-chevron-down"></i>
              </button>
              <div class="ms-panel" id="addRemLeadMsPanel">
                <input type="text" class="form-control ms-search" id="addRemLeadMsSearch" placeholder="Search leads by name, product or phone...">
                <div id="addRemLeadMsOptions"></div>
              </div>
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label">Note</label>
            <textarea class="form-control" id="addRemNote" rows="2" placeholder="e.g. follow up on pricing" required></textarea>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label">Date</label>
              <input type="date" class="form-control" id="addRemDate">
            </div>
            <div class="col-6">
              <label class="form-label">Time</label>
              <input type="time" class="form-control" id="addRemTime">
            </div>
          </div>
          <div class="apt-type-group">
            <div class="apt-type-option">
              <input type="radio" name="addRemAptType" id="addRemAptWhatsapp" value="whatsapp">
              <label for="addRemAptWhatsapp"><i class="bi bi-whatsapp"></i>WhatsApp</label>
            </div>
            <div class="apt-type-option">
              <input type="radio" name="addRemAptType" id="addRemAptMeeting" value="meeting">
              <label for="addRemAptMeeting"><i class="bi bi-camera-video"></i>Meeting</label>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add reminder</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  window.REMINDER_PAGE_DATA = {
    leads: @json($leads),
    csrfToken: '{{ csrf_token() }}',
    routes: {
      // NOTE: adjust the two route() names below to whatever you register
      // in routes/web.php for LeadManageController@remindersPage /
      // @remindersData (this page assumes 'reminders.page' / 'reminders.data').
      data: '{{ route('reminders.data') }}',
      store: '{{ route('leads.reminders.store') }}',
      status: '{{ url('admin/leads/reminders') }}/__ID__/status',
      destroy: '{{ url('admin/leads/reminders') }}/__ID__',
      leadsPage: '/admin/leads',
    }
  };

  (function () {
    "use strict";

    const CFG = window.REMINDER_PAGE_DATA;
    const CSRF = CFG.csrfToken;
    const AVATAR_COLORS = ["#4338CA", "#0891B2", "#B45309", "#16A34A", "#DC2626", "#6D28D9", "#0D9488"];

    let reminders = [];
    let activeFilters = { search: "", status: "", leadId: null };
    let activeKind = "reminder"; // "reminder" | "note" — which tab is selected
    let currentPage = 1;
    const PAGE_SIZE = 10;
    let selectedFilterLeadId = null;
    let selectedAddLeadId = null;

    function initials(name) {
      return (name || "?").trim().split(/\s+/).slice(0, 2).map(p => p[0]).join("").toUpperCase();
    }
    function colorFor(name) {
      let hash = 0;
      for (let i = 0; i < (name || "").length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash);
      return AVATAR_COLORS[Math.abs(hash) % AVATAR_COLORS.length];
    }
    function avatarHtml(name, size) {
      const cls = size === "sm" ? "avatar avatar-sm" : "avatar";
      return `<div class="${cls}" style="background:${colorFor(name)}">${initials(name)}</div>`;
    }
    function fmtDate(d) {
      if (!d) return "—";
      const dt = new Date(d);
      if (isNaN(dt)) return d;
      return dt.toLocaleDateString(undefined, { day: "numeric", month: "short", year: "numeric" });
    }
    function fmtTime(t) {
      if (!t) return "—";
      const [h, m] = t.split(":");
      const hour = parseInt(h, 10);
      const ampm = hour >= 12 ? "PM" : "AM";
      const h12 = ((hour + 11) % 12) + 1;
      return `${h12}:${m} ${ampm}`;
    }
    function toast(message, variant) {
      let wrap = document.getElementById("lfToastWrap");
      if (!wrap) {
        wrap = document.createElement("div");
        wrap.id = "lfToastWrap";
        wrap.style.cssText = "position:fixed;bottom:20px;right:20px;z-index:2000;display:flex;flex-direction:column;gap:8px;";
        document.body.appendChild(wrap);
      }
      const bg = variant === "danger" ? "#DC2626" : variant === "dark" ? "#1E2233" : "#16A34A";
      const el = document.createElement("div");
      el.style.cssText = `background:${bg};color:#fff;padding:11px 16px;border-radius:10px;font-size:13.5px;font-weight:600;box-shadow:0 8px 20px rgba(0,0,0,.18);`;
      el.textContent = message;
      wrap.appendChild(el);
      setTimeout(() => el.remove(), 3200);
    }
    function csrfHeaders(extra) {
      return Object.assign({ "X-CSRF-TOKEN": CSRF, "X-Requested-With": "XMLHttpRequest" }, extra || {});
    }
    async function apiFetch(url, options) {
      const res = await fetch(url, Object.assign({ headers: csrfHeaders({ "Accept": "application/json" }) }, options));
      if (res.status === 401 || res.status === 419) {
        toast("Your session expired, please log in again.", "danger");
        throw new Error("unauthenticated");
      }
      return res.json();
    }

    function isMissed(r) {
      if (r.status === "completed" || r.status === "silent") return false;
      if (r.status === "missed") return true;
      if (!r.reminder_date) return false;
      const dt = new Date(`${r.reminder_date}T${r.reminder_time || "23:59:59"}`);
      return dt.getTime() < Date.now();
    }
    function effectiveStatus(r) {
      if ((r.type || "reminder") === "note") return "note"; // notes don't have a scheduled/missed/silent lifecycle
      if (r.status === "completed") return "completed";
      if (r.status === "silent") return "silent";
      return isMissed(r) ? "missed" : "scheduled";
    }
    function statusBadge(status) {
      const labels = { scheduled: "Scheduled", missed: "Missed", silent: "Silenced", completed: "Completed", note: "Note" };
      return `<span class="badge-status rs-${status}">${labels[status]}</span>`;
    }

    async function loadReminders() {
      const res = await apiFetch(CFG.routes.data);
      if (!res.success) return;
      reminders = res.reminders;
      renderAll();
    }

    function getFiltered() {
      return reminders.filter(r => {
        const matchesKind = (r.type || "reminder") === activeKind;
        const term = activeFilters.search.toLowerCase();
        const haystack = `${r.note} ${r.lead ? r.lead.customer_name : ""} ${r.lead ? r.lead.product_name : ""}`.toLowerCase();
        const matchesSearch = !term || haystack.includes(term);
        const status = effectiveStatus(r);
        const matchesStatus = !activeFilters.status || status === activeFilters.status;
        const matchesLead = !activeFilters.leadId || (r.lead && r.lead.id === activeFilters.leadId);
        return matchesKind && matchesSearch && matchesStatus && matchesLead;
      });
    }

    function renderAll() {
      renderStats();
      renderTable();
    }

    function renderStats() {
      const reminderItems = reminders.filter(r => (r.type || "reminder") === "reminder");
      const noteItems = reminders.filter(r => (r.type || "reminder") === "note");

      document.getElementById("statTotal").textContent = reminderItems.length;
      let scheduled = 0, missed = 0, silent = 0;
      reminderItems.forEach(r => {
        const s = effectiveStatus(r);
        if (s === "scheduled") scheduled++;
        else if (s === "missed") missed++;
        else if (s === "silent") silent++;
      });
      document.getElementById("statScheduled").textContent = scheduled;
      document.getElementById("statMissed").textContent = missed;
      document.getElementById("statSilent").textContent = silent;

      document.getElementById("remindersCountTab").textContent = reminderItems.length;
      document.getElementById("notesCountTab").textContent = noteItems.length;
    }

    function renderTable() {
      const filtered = getFiltered();
      const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
      currentPage = Math.min(currentPage, totalPages);
      const start = (currentPage - 1) * PAGE_SIZE;
      const pageItems = filtered.slice(start, start + PAGE_SIZE);

      const tbody = document.getElementById("remindersTbody");
      if (pageItems.length === 0) {
        const label = activeKind === "note" ? "notes" : "reminders";
        tbody.innerHTML = `<tr><td colspan="7"><div class="empty-state">
          <i class="bi bi-bell-slash"></i><h6>No ${label} found</h6>
          <p class="fs-13 mb-0">Try adjusting filters${activeKind === "reminder" ? " or add a new reminder" : ""}.</p></div></td></tr>`;
      } else {
        tbody.innerHTML = pageItems.map(r => {
          const status = effectiveStatus(r);
          const lead = r.lead;
          const canSilence = status === "scheduled" || status === "missed";
          const canComplete = status !== "completed" && status !== "note";
          return `
          <tr data-rem-status="${status}">
            <td>
              <div class="cell-with-avatar">
                ${avatarHtml(lead ? lead.customer_name : "?")}
                <div><div class="lead-name">${lead ? lead.customer_name : "Deleted lead"}</div><div class="lead-company">${lead ? lead.product_name : ""}</div></div>
              </div>
            </td>
            <td class="text-muted-2">${r.note}</td>
            <td>${fmtDate(r.reminder_date)}</td>
            <td>${fmtTime(r.reminder_time)}</td>
            <td>${r.appointment_type ? `<i class="bi bi-${r.appointment_type === "whatsapp" ? "whatsapp" : "camera-video"} me-1"></i>${r.appointment_type === "whatsapp" ? "WhatsApp" : "Meeting"}` : "—"}</td>
            <td>${statusBadge(status)}</td>
            <td>
              <div class="dropdown row-actions" style="z-index:9999999;">
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                  ${lead ? `<li><a class="dropdown-item" href="#" data-view-lead="${lead.id}"><i class="bi bi-eye me-2"></i>View lead</a></li>` : ""}
                  ${canComplete ? `<li><a class="dropdown-item" href="#" data-complete="${r.id}"><i class="bi bi-check2 me-2"></i>Mark completed</a></li>` : ""}
                  ${canSilence ? `<li><a class="dropdown-item" href="#" data-silence="${r.id}"><i class="bi bi-volume-mute me-2"></i>Silence</a></li>` : ""}
                  <li><hr class="dropdown-divider"></li>
                  <li><a class="dropdown-item text-danger" href="#" data-delete="${r.id}"><i class="bi bi-trash me-2"></i>Delete</a></li>
                </ul>
              </div>
            </td>
          </tr>`;
        }).join("");
      }

      renderActivePills();
      renderPagination(filtered.length, totalPages);
      bindRowEvents();
    }

    function renderActivePills() {
      const wrap = document.getElementById("activeFilterPills");
      wrap.innerHTML = "";
      if (activeFilters.status) {
        wrap.insertAdjacentHTML("beforeend", `<span class="filter-pill">Status: ${activeFilters.status} <button type="button" data-clear="status"><i class="bi bi-x"></i></button></span>`);
      }
      if (activeFilters.leadId) {
        const lead = CFG.leads.find(l => l.id === activeFilters.leadId);
        wrap.insertAdjacentHTML("beforeend", `<span class="filter-pill">Lead: ${lead ? lead.customer_name : activeFilters.leadId} <button type="button" data-clear="lead"><i class="bi bi-x"></i></button></span>`);
      }
      wrap.querySelectorAll("[data-clear]").forEach(btn => {
        btn.addEventListener("click", () => {
          if (btn.dataset.clear === "status") {
            activeFilters.status = "";
            document.getElementById("filterStatus").value = "";
          } else if (btn.dataset.clear === "lead") {
            activeFilters.leadId = null;
            selectedFilterLeadId = null;
            renderFilterLeadChip();
          }
          currentPage = 1;
          renderTable();
        });
      });
    }

    function renderPagination(total, totalPages) {
      const start = total === 0 ? 0 : (currentPage - 1) * PAGE_SIZE + 1;
      const end = Math.min(currentPage * PAGE_SIZE, total);
      document.getElementById("pageInfo").textContent = `Showing ${start}–${end} of ${total}`;
      const nav = document.getElementById("pageButtons");
      let html = `<button class="btn-page" ${currentPage === 1 ? "disabled" : ""} data-page="prev"><i class="bi bi-chevron-left"></i></button>`;
      for (let i = 1; i <= totalPages; i++) html += `<button class="btn-page ${i === currentPage ? "active" : ""}" data-page="${i}">${i}</button>`;
      html += `<button class="btn-page" ${currentPage === totalPages ? "disabled" : ""} data-page="next"><i class="bi bi-chevron-right"></i></button>`;
      nav.innerHTML = html;
      nav.querySelectorAll("[data-page]").forEach(btn => {
        btn.addEventListener("click", () => {
          if (btn.dataset.page === "prev") currentPage--;
          else if (btn.dataset.page === "next") currentPage++;
          else currentPage = parseInt(btn.dataset.page, 10);
          renderTable();
        });
      });
    }

    function bindRowEvents() {
      document.querySelectorAll("[data-view-lead]").forEach(a => a.addEventListener("click", e => {
        e.preventDefault();
        window.location.href = `${CFG.routes.leadsPage}?lead=${a.dataset.viewLead}`;
      }));
      document.querySelectorAll("[data-complete]").forEach(a => a.addEventListener("click", async e => {
        e.preventDefault();
        await apiFetch(CFG.routes.status.replace("__ID__", a.dataset.complete), {
          method: "POST", headers: csrfHeaders({ "Content-Type": "application/json" }), body: JSON.stringify({ status: "completed" }),
        });
        toast("Marked completed");
        await loadReminders();
        if (window.GlobalReminders) window.GlobalReminders.refresh();
      }));
      document.querySelectorAll("[data-silence]").forEach(a => a.addEventListener("click", async e => {
        e.preventDefault();
        await apiFetch(CFG.routes.status.replace("__ID__", a.dataset.silence), {
          method: "POST", headers: csrfHeaders({ "Content-Type": "application/json" }), body: JSON.stringify({ status: "silent" }),
        });
        toast("Reminder silenced");
        await loadReminders();
        if (window.GlobalReminders) window.GlobalReminders.refresh();
      }));
      document.querySelectorAll("[data-delete]").forEach(a => a.addEventListener("click", async e => {
        e.preventDefault();
        await apiFetch(CFG.routes.destroy.replace("__ID__", a.dataset.delete), { method: "DELETE" });
        toast("Reminder deleted", "dark");
        await loadReminders();
        if (window.GlobalReminders) window.GlobalReminders.refresh();
      }));
    }

    /* ---------------- Filters wiring ---------------- */

    function initFilters() {
      document.getElementById("filterSearch").addEventListener("input", e => { activeFilters.search = e.target.value; currentPage = 1; renderTable(); });
      document.getElementById("filterStatus").addEventListener("change", e => { activeFilters.status = e.target.value; currentPage = 1; renderTable(); });
      document.getElementById("clearFiltersBtn").addEventListener("click", () => {
        activeFilters = { search: "", status: "", leadId: null };
        selectedFilterLeadId = null;
        document.getElementById("filterSearch").value = "";
        document.getElementById("filterStatus").value = "";
        renderFilterLeadChip();
        currentPage = 1;
        renderTable();
      });
    }

    /* ---------------- Reminders / Notes tabs ----------------
       Same table underneath, just partitioned by kind. The Status filter
       (Scheduled/Missed/Silenced/Completed) only means something for
       reminders, so it's hidden — and reset — while the Notes tab is active. */

    function initKindTabs() {
      document.querySelectorAll("#reminderKindTabs .nav-link").forEach(tab => {
        tab.addEventListener("click", e => {
          e.preventDefault();
          document.querySelectorAll("#reminderKindTabs .nav-link").forEach(t => t.classList.remove("active"));
          tab.classList.add("active");
          activeKind = tab.dataset.kind;

          const statusSelect = document.getElementById("filterStatus");
          statusSelect.style.display = activeKind === "note" ? "none" : "";
          if (activeKind === "note") {
            activeFilters.status = "";
            statusSelect.value = "";
          }

          currentPage = 1;
          renderTable();
        });
      });
    }

    /* ---------------- Searchable lead select (shared helper) ---------------- */

    function initLeadSearchSelect(opts) {
      const toggle = document.getElementById(opts.toggleId);
      const panel = document.getElementById(opts.panelId);
      const searchInput = document.getElementById(opts.searchId);
      const optionsBox = document.getElementById(opts.optionsId);
      const chips = document.getElementById(opts.chipsId);

      function renderOptions(term) {
        term = (term || "").toLowerCase();
        const list = CFG.leads.filter(l => `${l.customer_name} ${l.product_name} ${l.phone}`.toLowerCase().includes(term));
        optionsBox.innerHTML = list.map(l => `
          <label class="ms-option" data-lead-option="${l.id}">
            ${avatarHtml(l.customer_name, "sm")}
            <span>${l.customer_name} <span class="text-muted-2 fs-12">— ${l.product_name}</span></span>
          </label>`).join("") || `<div class="fs-13 text-muted-2 p-2">No leads found</div>`;
        optionsBox.querySelectorAll("[data-lead-option]").forEach(opt => {
          opt.addEventListener("click", () => {
            opts.setSelected(parseInt(opt.dataset.leadOption, 10));
            opts.renderChip();
            panel.classList.remove("show");
          });
        });
      }

      toggle.addEventListener("click", () => {
        panel.classList.toggle("show");
        if (panel.classList.contains("show")) {
          searchInput.value = "";
          renderOptions("");
          searchInput.focus();
        }
      });
      searchInput.addEventListener("input", e => renderOptions(e.target.value));
      document.addEventListener("click", e => {
        if (!toggle.closest(".ms-control").contains(e.target)) panel.classList.remove("show");
      });

      return { chips };
    }

    function renderFilterLeadChip() {
      const chips = document.getElementById("filterLeadMsChips");
      const lead = CFG.leads.find(l => l.id === selectedFilterLeadId);
      chips.innerHTML = lead ? `<span class="chip">${lead.customer_name}</span>` : `<span class="placeholder">All leads</span>`;
    }
    function renderAddLeadChip() {
      const chips = document.getElementById("addRemLeadMsChips");
      const lead = CFG.leads.find(l => l.id === selectedAddLeadId);
      chips.innerHTML = lead ? `<span class="chip">${lead.customer_name} — ${lead.product_name}</span>` : `<span class="placeholder">Select a lead...</span>`;
    }

    function initLeadSelects() {
      initLeadSearchSelect({
        toggleId: "filterLeadMsToggle", panelId: "filterLeadMsPanel", searchId: "filterLeadMsSearch",
        optionsId: "filterLeadMsOptions", chipsId: "filterLeadMsChips",
        setSelected: id => { selectedFilterLeadId = id; activeFilters.leadId = id; currentPage = 1; renderTable(); },
        renderChip: renderFilterLeadChip,
      });
      initLeadSearchSelect({
        toggleId: "addRemLeadMsToggle", panelId: "addRemLeadMsPanel", searchId: "addRemLeadMsSearch",
        optionsId: "addRemLeadMsOptions", chipsId: "addRemLeadMsChips",
        setSelected: id => { selectedAddLeadId = id; },
        renderChip: renderAddLeadChip,
      });
      renderFilterLeadChip();
      renderAddLeadChip();
    }

    /* ---------------- Add reminder modal ---------------- */

    function initAddReminderForm() {
      document.getElementById("openAddReminderBtn").addEventListener("click", () => {
        document.getElementById("addReminderForm").reset();
        selectedAddLeadId = null;
        renderAddLeadChip();
        const now = new Date();
        document.getElementById("addRemDate").value = now.toISOString().slice(0, 10);
        document.getElementById("addRemTime").value = now.toTimeString().slice(0, 5);
        bootstrap.Modal.getOrCreateInstance(document.getElementById("addReminderModal")).show();
      });

      document.getElementById("addReminderForm").addEventListener("submit", async e => {
        e.preventDefault();
        if (!selectedAddLeadId) {
          toast("Please select a lead first.", "danger");
          return;
        }
        const aptEl = document.querySelector('input[name="addRemAptType"]:checked');
        const payload = {
          lead_id: selectedAddLeadId,
          // This page is specifically for date/time follow-ups, so items
          // created here are always the "reminder" kind. Plain notes (no
          // date/time) are added from within a lead's own detail panel.
          type: "reminder",
          note: document.getElementById("addRemNote").value.trim(),
          reminder_date: document.getElementById("addRemDate").value || null,
          reminder_time: document.getElementById("addRemTime").value || null,
          appointment_type: aptEl ? aptEl.value : null,
        };
        const res = await apiFetch(CFG.routes.store, {
          method: "POST", headers: csrfHeaders({ "Content-Type": "application/json" }), body: JSON.stringify(payload),
        });
        if (!res.success) {
          toast(res.errors ? Object.values(res.errors)[0][0] : "Could not add reminder.", "danger");
          return;
        }
        toast("Reminder added");
        bootstrap.Modal.getOrCreateInstance(document.getElementById("addReminderModal")).hide();
        await loadReminders();
        if (window.GlobalReminders) window.GlobalReminders.refresh();
      });
    }

    document.addEventListener("DOMContentLoaded", () => {
      initFilters();
      initKindTabs();
      initLeadSelects();
      initAddReminderForm();
      loadReminders();
    });
  })();
</script>

@endsection
