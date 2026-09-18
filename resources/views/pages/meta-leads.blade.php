@extends('layouts.app')
@section('title', 'Meta Leads | Dalal Adda')

@section('content')

<style>
  /* ---- Status badges (reuse .badge-status base look, same pattern as lead.blade.php) ---- */
  .badge-status.ml-new        { background: var(--info-bg);    color: var(--info); }
  .badge-status.ml-converted  { background: var(--success-bg); color: var(--success); }
  .badge-status.ml-discarded  { background: var(--danger-bg);  color: var(--danger); }

  .crm-table tbody tr[data-ml-status="new"]        { background: var(--info-bg); }
  .crm-table tbody tr[data-ml-status="converted"]  { background: var(--success-bg); }
  .crm-table tbody tr[data-ml-status="discarded"]  { background: var(--danger-bg); opacity: .7; }
  .crm-table tbody tr[data-ml-status]:hover        { filter: brightness(0.97); cursor: pointer; }

  /* ---- Convert form's "assign to" multi-select — same widget as the Leads page ---- */
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
  .ms-option { display: flex; align-items: center; gap: 9px; padding: 7px 6px; border-radius: 7px; font-size: 13px; cursor: pointer; }
  .ms-option:hover { background: var(--primary-50); }

  .raw-field-row { display: flex; justify-content: space-between; gap: 10px; padding: 7px 0; border-top: 1px dashed var(--border); font-size: 12.5px; }
  .raw-field-row span:first-child { color: var(--text-muted); text-transform: capitalize; }
  .raw-field-row span:last-child { font-weight: 600; color: var(--text); text-align: right; }
</style>

<div class="page-content">
  <section class="page-section active" id="page-meta-leads">

    <div class="page-header">
      <div>
        <span class="breadcrumb-eyebrow">Pipeline</span>
        <h1>Meta Leads</h1>
        <p>Everyone who filled your Facebook / Instagram lead ad form, before they become a real lead.</p>
      </div>
    </div>

    <!-- Stat cards -->
    <div class="row g-3 mb-3">
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div><div class="stat-label">Total Received</div><div class="stat-value" id="statTotal">0</div></div>
          <div class="stat-icon" style="background:var(--primary-50); color:var(--primary)"><i class="bi bi-meta"></i></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div><div class="stat-label">New</div><div class="stat-value" id="statNew">0</div></div>
          <div class="stat-icon" style="background:var(--info-bg); color:var(--info)"><i class="bi bi-inbox"></i></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div><div class="stat-label">Converted</div><div class="stat-value" id="statConverted">0</div></div>
          <div class="stat-icon" style="background:var(--success-bg); color:var(--success)"><i class="bi bi-check-circle"></i></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div><div class="stat-label">Discarded</div><div class="stat-value" id="statDiscarded">0</div></div>
          <div class="stat-icon" style="background:var(--danger-bg); color:var(--danger)"><i class="bi bi-x-circle"></i></div>
        </div>
      </div>
    </div>

    <!-- Category tabs -->
    <ul class="nav lead-tabs mb-3" id="mlCategoryTabs">
      <li class="nav-item"><a class="nav-link active" data-status="" href="#">All</a></li>
      <li class="nav-item"><a class="nav-link" data-status="new" href="#">New</a></li>
      <li class="nav-item"><a class="nav-link" data-status="converted" href="#">Converted</a></li>
      <li class="nav-item"><a class="nav-link" data-status="discarded" href="#">Discarded</a></li>
    </ul>

    <!-- Filter bar -->
    <div class="filter-bar">
      <div class="filter-search">
        <i class="bi bi-search"></i>
        <input type="text" class="form-control" id="filterSearch" placeholder="Search by customer, phone or campaign...">
      </div>
      <button class="btn btn-outline-secondary btn-sm" id="clearFiltersBtn">Clear</button>
    </div>

    <!-- Bulk actions bar -->
    <div class="filter-bar d-none" id="bulkBar">
      <span class="fs-13 fw-600"><span id="bulkCount">0</span> selected</span>
      <button class="btn btn-sm btn-outline-secondary ms-auto" id="bulkClearBtn">Clear selection</button>
      <button class="btn btn-sm" style="background:var(--danger-bg);color:var(--danger)" id="bulkDeleteBtn"><i class="bi bi-trash me-1"></i>Delete selected</button>
    </div>

    <!-- Table -->
    <div class="section-card">
      <div class="table-wrap">
        <table class="crm-table">
          <thead>
            <tr>
              <th style="width:40px"><input type="checkbox" class="form-check-input" id="selectAllCheck"></th>
              <th>Customer</th>
              <th>Interested In</th>
              <th>Contact</th>
              <th>Campaign / Ad</th>
              <th>Received</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="mlTbody"></tbody>
        </table>
      </div>
      <div class="crm-pagination">
        <span class="page-info" id="pageInfo"></span>
        <div class="d-flex gap-1" id="pageButtons"></div>
      </div>
    </div>
  </section>
</div>

<!-- ===================== META LEAD DETAIL OFFCANVAS ===================== -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="mlDetailOffcanvas" style="width:440px">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title">Meta lead details</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body">
    <div class="d-flex align-items-center gap-3 mb-3">
      <div id="mlAvatar"></div>
      <div>
        <div class="fw-600 fs-15" id="mlName" style="font-size:16px"></div>
        <div class="text-muted-2 fs-13" id="mlProduct"></div>
      </div>
      <div class="ms-auto" id="mlStatusBadgeWrap"></div>
    </div>

    <div class="card-flat p-3 mb-3">
      <div class="d-flex justify-content-between mb-2"><span class="text-muted-2 fs-13">Phone</span><span class="fw-600 fs-13" id="mlPhone">—</span></div>
      <div class="d-flex justify-content-between mb-2"><span class="text-muted-2 fs-13">WhatsApp</span><span class="fw-600 fs-13" id="mlWhatsapp">—</span></div>
      <div class="d-flex justify-content-between mb-2"><span class="text-muted-2 fs-13">Email</span><span class="fw-600 fs-13" id="mlEmail">—</span></div>
      <div class="d-flex justify-content-between mb-2"><span class="text-muted-2 fs-13">Campaign</span><span class="fw-600 fs-13 text-end" id="mlCampaign">—</span></div>
      <div class="d-flex justify-content-between mb-2"><span class="text-muted-2 fs-13">Ad</span><span class="fw-600 fs-13 text-end" id="mlAd">—</span></div>
      <div class="d-flex justify-content-between"><span class="text-muted-2 fs-13">Form</span><span class="fw-600 fs-13 text-end" id="mlForm">—</span></div>
    </div>

    <div id="mlRawFieldsWrap" class="mb-4" style="display:none">
      <h6 class="fs-13 fw-700 text-uppercase text-muted-2 mb-2">All form answers</h6>
      <div class="card-flat p-3" id="mlRawFields"></div>
    </div>

    <!-- Shown only while status = new -->
    <div id="mlConvertBlock">
      <h6 class="fs-13 fw-700 text-uppercase text-muted-2 mb-2">Convert to lead</h6>
      <form id="convertForm">
        <input type="hidden" id="mlId">
        <div class="mb-2">
          <label class="form-label">Customer name</label>
          <input type="text" class="form-control" id="cvCustomerName" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Product / interest</label>
          <input type="text" class="form-control" id="cvProductName" required>
        </div>
        <div class="row g-2 mb-2">
          <div class="col-6">
            <label class="form-label">Phone</label>
            <input type="text" class="form-control" id="cvPhone" required>
          </div>
          <div class="col-6">
            <label class="form-label">WhatsApp</label>
            <input type="text" class="form-control" id="cvWhatsapp">
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label">Lead type</label>
          <select class="form-select" id="cvLeadType">
            @foreach($leadTypes as $type)
              <option value="{{ $type }}">{{ ucfirst($type) }}</option>
            @endforeach
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Assign to</label>
          <div class="ms-control" id="assignMsControl">
            <button type="button" class="ms-toggle" id="assignMsToggle">
              <span class="chips" id="assignMsChips"><span class="placeholder">Select employees...</span></span>
              <i class="bi bi-chevron-down"></i>
            </button>
            <div class="ms-panel" id="assignMsPanel">
              <input type="text" class="form-control ms-search mb-2" id="assignMsSearch" placeholder="Search employees...">
              <div id="assignMsOptions"></div>
            </div>
          </div>
        </div>
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Convert to lead</button>
          <button type="button" class="btn btn-outline-secondary btn-sm" id="discardBtn">Discard</button>
        </div>
      </form>
    </div>

    <!-- Shown only after conversion -->
    <div id="mlConvertedBlock" class="d-none">
      <div class="card-flat p-3 text-center">
        <i class="bi bi-check-circle fs-3" style="color:var(--success)"></i>
        <p class="fs-13 mb-2 mt-1">Already converted to a lead.</p>
        <a href="#" class="btn btn-sm btn-primary" id="viewConvertedLeadBtn">View in Leads</a>
      </div>
    </div>
  </div>
</div>

<!-- ===================== DELETE CONFIRM MODAL ===================== -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-content-compact">
    <div class="modal-content modal-content-danger">
      <div class="modal-header">
        <h5 class="modal-title">Delete meta lead(s)?</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0">This will permanently delete the selected submission(s). This can't be undone.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirmDeleteBtn">Delete</button>
      </div>
    </div>
  </div>
</div>

<script>
  window.META_LEAD_PAGE_DATA = {
    admins: @json($admins),
    leadTypes: @json($leadTypes),
    csrfToken: '{{ csrf_token() }}',
    routes: {
      data: '{{ route('metaLeads.data') }}',
      show: '{{ url('admin/meta-leads') }}/__ID__',
      convert: '{{ url('admin/meta-leads') }}/__ID__/convert',
      discard: '{{ url('admin/meta-leads') }}/__ID__/discard',
      destroy: '{{ url('admin/meta-leads') }}/__ID__',
      bulkDelete: '{{ route('metaLeads.bulkDestroy') }}',
      leadsIndex: '{{ route('leads.index') }}',
    }
  };

(function () {
  "use strict";

  const CFG = window.META_LEAD_PAGE_DATA;
  const CSRF = CFG.csrfToken;
  const AVATAR_COLORS = ["#4338CA", "#0891B2", "#B45309", "#16A34A", "#DC2626", "#6D28D9", "#0D9488"];

  let metaLeads = [];
  let activeStatus = "";
  let searchTerm = "";
  let currentPage = 1;
  const PAGE_SIZE = 8;
  let selectedIds = new Set();
  let selectedAssignIds = new Set();
  let currentDetailId = null;

  function initials(name) { return (name || "?").trim().split(/\s+/).slice(0, 2).map(p => p[0]).join("").toUpperCase(); }
  function colorFor(name) { let h = 0; for (let i = 0; i < (name || "").length; i++) h = name.charCodeAt(i) + ((h << 5) - h); return AVATAR_COLORS[Math.abs(h) % AVATAR_COLORS.length]; }
  function avatarHtml(name, size) {
    const cls = size === "lg" ? "avatar avatar-lg" : size === "sm" ? "avatar avatar-sm" : "avatar";
    return `<div class="${cls}" style="background:${colorFor(name)}">${initials(name)}</div>`;
  }
  function statusBadge(status) {
    const label = status.charAt(0).toUpperCase() + status.slice(1);
    return `<span class="badge-status ml-${status}">${label}</span>`;
  }
  function fmtDateTime(d) {
    if (!d) return "—";
    const dt = new Date(d);
    if (isNaN(dt)) return d;
    return dt.toLocaleString(undefined, { day: "numeric", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit" });
  }
  function toast(message, variant) {
    let wrap = document.getElementById("mlToastWrap");
    if (!wrap) {
      wrap = document.createElement("div");
      wrap.id = "mlToastWrap";
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
  function csrfHeaders(extra) { return Object.assign({ "X-CSRF-TOKEN": CSRF, "X-Requested-With": "XMLHttpRequest" }, extra || {}); }
  async function apiFetch(url, options) {
    const res = await fetch(url, Object.assign({ headers: csrfHeaders({ "Accept": "application/json" }) }, options));
    if (res.status === 401 || res.status === 419) { toast("Session expired, please log in again.", "danger"); throw new Error("unauthenticated"); }
    return res.json();
  }

  async function loadMetaLeads() {
    const data = await apiFetch(CFG.routes.data);
    if (!data.success) return;
    metaLeads = data.meta_leads;
    renderAll();
  }

  function getFiltered() {
    return metaLeads.filter(m => {
      const term = searchTerm.toLowerCase();
      const matchesSearch = !term || ((m.customer_name || "") + (m.phone || "") + (m.campaign_name || "")).toLowerCase().includes(term);
      const matchesStatus = !activeStatus || m.status === activeStatus;
      return matchesSearch && matchesStatus;
    });
  }

  function renderAll() { renderStats(); renderTable(); }

  function renderStats() {
    document.getElementById("statTotal").textContent = metaLeads.length;
    document.getElementById("statNew").textContent = metaLeads.filter(m => m.status === "new").length;
    document.getElementById("statConverted").textContent = metaLeads.filter(m => m.status === "converted").length;
    document.getElementById("statDiscarded").textContent = metaLeads.filter(m => m.status === "discarded").length;
  }

  function renderTable() {
    const filtered = getFiltered();
    const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
    currentPage = Math.min(currentPage, totalPages);
    const start = (currentPage - 1) * PAGE_SIZE;
    const pageItems = filtered.slice(start, start + PAGE_SIZE);
    const tbody = document.getElementById("mlTbody");

    if (pageItems.length === 0) {
      tbody.innerHTML = `<tr><td colspan="8"><div class="empty-state">
        <i class="bi bi-inbox"></i><h6>No submissions found</h6>
        <p class="fs-13 mb-0">New leads from your Meta ad forms will show up here automatically.</p></div></td></tr>`;
    } else {
      tbody.innerHTML = pageItems.map(m => `
        <tr data-id="${m.id}" data-ml-status="${m.status}">
          <td onclick="event.stopPropagation()"><input class="form-check-input row-check" type="checkbox" data-id="${m.id}" ${selectedIds.has(m.id) ? "checked" : ""}></td>
          <td>
            <div class="cell-with-avatar">
              ${avatarHtml(m.customer_name)}
              <div><div class="lead-name">${m.customer_name || "Unknown"}</div><div class="lead-company">${m.email || ""}</div></div>
            </div>
          </td>
          <td>${m.product_name || "—"}</td>
          <td class="text-muted-2">${m.phone || "—"}</td>
          <td>${m.campaign_name || m.form_name || "—"}</td>
          <td class="text-muted-2">${fmtDateTime(m.received_at)}</td>
          <td>${statusBadge(m.status)}</td>
          <td onclick="event.stopPropagation()">
            <div class="dropdown row-actions">
              <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item view-ml-action" href="#" data-id="${m.id}"><i class="bi bi-eye me-2"></i>View details</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger delete-ml-action" href="#" data-id="${m.id}"><i class="bi bi-trash me-2"></i>Delete</a></li>
              </ul>
            </div>
          </td>
        </tr>`).join("");
    }
    renderPagination(filtered.length, totalPages);
    bindRowEvents();
    updateBulkBar();
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
    document.querySelectorAll("#mlTbody tr[data-id]").forEach(row => {
      row.addEventListener("click", () => openDetail(parseInt(row.dataset.id, 10)));
    });
    document.querySelectorAll(".row-check").forEach(cb => {
      cb.addEventListener("change", () => {
        const id = parseInt(cb.dataset.id, 10);
        if (cb.checked) selectedIds.add(id); else selectedIds.delete(id);
        updateBulkBar();
      });
    });
    document.querySelectorAll(".view-ml-action").forEach(a => a.addEventListener("click", e => { e.preventDefault(); openDetail(parseInt(a.dataset.id, 10)); }));
    document.querySelectorAll(".delete-ml-action").forEach(a => {
      a.addEventListener("click", e => {
        e.preventDefault();
        selectedIds = new Set([parseInt(a.dataset.id, 10)]);
        bootstrap.Modal.getOrCreateInstance(document.getElementById("deleteConfirmModal")).show();
      });
    });
    const selectAll = document.getElementById("selectAllCheck");
    selectAll.checked = false;
    selectAll.onchange = () => {
      document.querySelectorAll(".row-check").forEach(cb => {
        cb.checked = selectAll.checked;
        const id = parseInt(cb.dataset.id, 10);
        if (selectAll.checked) selectedIds.add(id); else selectedIds.delete(id);
      });
      updateBulkBar();
    };
  }

  function updateBulkBar() {
    const bar = document.getElementById("bulkBar");
    if (selectedIds.size > 0) { bar.classList.remove("d-none"); document.getElementById("bulkCount").textContent = selectedIds.size; }
    else { bar.classList.add("d-none"); }
  }

  function initFilters() {
    document.getElementById("filterSearch").addEventListener("input", e => { searchTerm = e.target.value; currentPage = 1; renderTable(); });
    document.getElementById("clearFiltersBtn").addEventListener("click", () => { searchTerm = ""; document.getElementById("filterSearch").value = ""; currentPage = 1; renderTable(); });
    document.querySelectorAll("#mlCategoryTabs .nav-link").forEach(tab => {
      tab.addEventListener("click", e => {
        e.preventDefault();
        document.querySelectorAll("#mlCategoryTabs .nav-link").forEach(t => t.classList.remove("active"));
        tab.classList.add("active");
        activeStatus = tab.dataset.status;
        currentPage = 1;
        renderTable();
      });
    });
  }

  function initBulkActions() {
    document.getElementById("bulkClearBtn").addEventListener("click", () => { selectedIds.clear(); renderTable(); });
    document.getElementById("bulkDeleteBtn").addEventListener("click", () => bootstrap.Modal.getOrCreateInstance(document.getElementById("deleteConfirmModal")).show());
    document.getElementById("confirmDeleteBtn").addEventListener("click", async () => {
      const ids = Array.from(selectedIds);
      if (!ids.length) return;
      const res = ids.length === 1
        ? await apiFetch(CFG.routes.destroy.replace("__ID__", ids[0]), { method: "DELETE" })
        : await apiFetch(CFG.routes.bulkDelete, { method: "POST", headers: csrfHeaders({ "Content-Type": "application/json" }), body: JSON.stringify({ ids }) });
      if (res.success) {
        metaLeads = metaLeads.filter(m => !ids.includes(m.id));
        selectedIds.clear();
        renderAll();
        toast(res.message || "Deleted", "dark");
      }
      bootstrap.Modal.getOrCreateInstance(document.getElementById("deleteConfirmModal")).hide();
    });
  }

  /* ---------------- Assign-to multi-select (same pattern as Leads page) ---------------- */

  function renderAssignOptions(term) {
    const box = document.getElementById("assignMsOptions");
    const t = (term || "").toLowerCase();
    const list = CFG.admins.filter(a => a.name.toLowerCase().includes(t));
    box.innerHTML = list.map(a => `
      <label class="ms-option">
        <input type="checkbox" class="form-check-input" value="${a.id}" ${selectedAssignIds.has(a.id) ? "checked" : ""}>
        ${avatarHtml(a.name, "sm")} ${a.name}
      </label>`).join("") || `<div class="fs-13 text-muted-2 p-2">No employees found</div>`;
    box.querySelectorAll('input[type="checkbox"]').forEach(cb => {
      cb.addEventListener("change", () => {
        const id = parseInt(cb.value, 10);
        if (cb.checked) selectedAssignIds.add(id); else selectedAssignIds.delete(id);
        renderAssignChips();
      });
    });
  }

  function renderAssignChips() {
    const chips = document.getElementById("assignMsChips");
    if (selectedAssignIds.size === 0) { chips.innerHTML = `<span class="placeholder">Select employees...</span>`; return; }
    chips.innerHTML = Array.from(selectedAssignIds).map(id => {
      const admin = CFG.admins.find(a => a.id === id);
      return `<span class="chip">${admin ? admin.name : id}</span>`;
    }).join("");
  }

  function initAssignMultiSelect() {
    const toggle = document.getElementById("assignMsToggle");
    const panel = document.getElementById("assignMsPanel");
    toggle.addEventListener("click", () => {
      panel.classList.toggle("show");
      if (panel.classList.contains("show")) {
        document.getElementById("assignMsSearch").value = "";
        renderAssignOptions("");
        document.getElementById("assignMsSearch").focus();
      }
    });
    document.getElementById("assignMsSearch").addEventListener("input", e => renderAssignOptions(e.target.value));
    document.addEventListener("click", e => {
      if (!document.getElementById("assignMsControl").contains(e.target)) panel.classList.remove("show");
    });
  }

  /* ---------------- Detail offcanvas ---------------- */

  async function openDetail(id) {
    const res = await apiFetch(CFG.routes.show.replace("__ID__", id));
    if (!res.success) return;
    const m = res.meta_lead;
    currentDetailId = m.id;
    selectedAssignIds = new Set();

    document.getElementById("mlAvatar").innerHTML = avatarHtml(m.customer_name, "lg");
    document.getElementById("mlName").textContent = m.customer_name || "Unknown";
    document.getElementById("mlProduct").textContent = m.product_name || "";
    document.getElementById("mlStatusBadgeWrap").innerHTML = statusBadge(m.status);
    document.getElementById("mlPhone").textContent = m.phone || "—";
    document.getElementById("mlWhatsapp").textContent = m.whatsapp || "—";
    document.getElementById("mlEmail").textContent = m.email || "—";
    document.getElementById("mlCampaign").textContent = m.campaign_name || "—";
    document.getElementById("mlAd").textContent = m.ad_name || "—";
    document.getElementById("mlForm").textContent = m.form_name || "—";

    const rawWrap = document.getElementById("mlRawFieldsWrap");
    const rawBox = document.getElementById("mlRawFields");
    const fieldData = (m.raw_payload && m.raw_payload.field_data) || [];
    if (fieldData.length) {
      rawWrap.style.display = "block";
      rawBox.innerHTML = fieldData.map(f => `<div class="raw-field-row"><span>${(f.name || "").replace(/_/g, " ")}</span><span>${(f.values && f.values[0]) || "—"}</span></div>`).join("");
    } else {
      rawWrap.style.display = "none";
    }

    document.getElementById("mlId").value = m.id;
    document.getElementById("cvCustomerName").value = m.customer_name || "";
    document.getElementById("cvProductName").value = m.product_name || "";
    document.getElementById("cvPhone").value = m.phone || "";
    document.getElementById("cvWhatsapp").value = m.whatsapp || "";
    document.getElementById("cvLeadType").value = "warm";
    renderAssignChips();

    const convertBlock = document.getElementById("mlConvertBlock");
    const convertedBlock = document.getElementById("mlConvertedBlock");
    if (m.status === "converted") {
      convertBlock.classList.add("d-none");
      convertedBlock.classList.remove("d-none");
      document.getElementById("viewConvertedLeadBtn").href = `${CFG.routes.leadsIndex}?lead=${m.converted_lead_id}`;
    } else {
      convertBlock.classList.remove("d-none");
      convertedBlock.classList.add("d-none");
    }

    bootstrap.Offcanvas.getOrCreateInstance(document.getElementById("mlDetailOffcanvas")).show();
  }

  function initConvertForm() {
    document.getElementById("convertForm").addEventListener("submit", async e => {
      e.preventDefault();
      const id = document.getElementById("mlId").value;
      const payload = {
        customer_name: document.getElementById("cvCustomerName").value.trim(),
        product_name: document.getElementById("cvProductName").value.trim(),
        phone: document.getElementById("cvPhone").value.trim(),
        whatsapp: document.getElementById("cvWhatsapp").value.trim(),
        lead_type: document.getElementById("cvLeadType").value,
        assigned_admins: Array.from(selectedAssignIds),
      };
      const res = await apiFetch(CFG.routes.convert.replace("__ID__", id), {
        method: "POST", headers: csrfHeaders({ "Content-Type": "application/json" }), body: JSON.stringify(payload),
      });
      if (!res.success) {
        toast(res.errors ? Object.values(res.errors)[0][0] : (res.message || "Could not convert."), "danger");
        return;
      }
      toast(res.message || "Converted to a lead");
      await loadMetaLeads();
      bootstrap.Offcanvas.getInstance(document.getElementById("mlDetailOffcanvas"))?.hide();
    });

    document.getElementById("discardBtn").addEventListener("click", async () => {
      if (!currentDetailId) return;
      const res = await apiFetch(CFG.routes.discard.replace("__ID__", currentDetailId), { method: "POST" });
      if (res.success) {
        toast("Marked as discarded", "dark");
        await loadMetaLeads();
        bootstrap.Offcanvas.getInstance(document.getElementById("mlDetailOffcanvas"))?.hide();
      }
    });
  }

  document.addEventListener("DOMContentLoaded", () => {
    initFilters();
    initBulkActions();
    initAssignMultiSelect();
    initConvertForm();
    loadMetaLeads();
  });
})();
</script>

@endsection