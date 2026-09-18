@extends('layouts.app')
@section('title', 'Tasks | LeadFlow')

@section('content')

<style>
  /* ---- Priority badges ---- */
  .badge-status.pr-low     { background: var(--info-bg);    color: var(--info); }
  .badge-status.pr-medium  { background: var(--warning-bg); color: var(--warning); }
  .badge-status.pr-high    { background: var(--danger-bg);  color: var(--danger); }
  .badge-status.pr-urgent  { background: var(--danger);     color: #fff; }

  /* ---- Status badges ---- */
  .badge-status.st-pending     { background: var(--info-bg);    color: var(--info); }
  .badge-status.st-in_progress { background: var(--warning-bg); color: var(--warning); }
  .badge-status.st-completed   { background: var(--success-bg); color: var(--success); }
  .badge-status.st-reverted    { background: var(--danger-bg);  color: var(--danger); }
  .badge-status.st-on_hold     { background: #EEF1F6;           color: var(--text-muted); }

  .crm-table tbody tr.is-overdue-row { box-shadow: inset 3px 0 0 var(--danger); }
  .crm-table tbody tr:hover { filter: brightness(0.98); cursor: pointer; }
  .due-date-overdue { color: var(--danger); font-weight: 700; }

  .status-select {
    appearance: none; -webkit-appearance: none; -moz-appearance: none;
    border: none; border-radius: 20px; padding: 7px 32px 7px 14px;
    font-size: 12.5px; font-weight: 700; cursor: pointer;
    background-repeat: no-repeat; background-position: right 11px center; background-size: 12px;
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23666' stroke-width='2'><polyline points='6 9 12 15 18 9'/></svg>");
  }
  .status-select.st-pending     { background-color: var(--info-bg);    color: var(--info); }
  .status-select.st-in_progress { background-color: var(--warning-bg); color: var(--warning); }
  .status-select.st-completed   { background-color: var(--success-bg); color: var(--success); }
  .status-select.st-reverted    { background-color: var(--danger-bg);  color: var(--danger); }
  .status-select.st-on_hold     { background-color: #EEF1F6;           color: var(--text-muted); }

  /* ---- Detail panel tabs ---- */
  .td-tabs { display: flex; gap: 6px; border-bottom: 1px solid var(--border); margin-bottom: 14px; }
  .td-tab { background: none; border: none; padding: 9px 4px; font-size: 13px; font-weight: 700; color: var(--text-muted); border-bottom: 2px solid transparent; }
  .td-tab.active { color: var(--primary); border-bottom-color: var(--primary); }
  .td-pane { display: none; }
  .td-pane.active { display: block; }

  /* ---- Comments ---- */
  .comment-item { display: flex; gap: 10px; margin-bottom: 14px; }
  .comment-item .comment-body { background: var(--bg); border-radius: 12px; padding: 9px 12px; flex: 1; }
  .comment-item .comment-author { font-weight: 700; font-size: 12.5px; }
  .comment-item .comment-time { font-size: 11px; color: var(--text-muted); margin-left: 6px; }
  .comment-item .comment-text { font-size: 13px; margin-top: 2px; white-space: pre-wrap; }

  /* ---- Attachments ---- */
  .attach-item { display: flex; align-items: center; gap: 10px; padding: 9px 4px; border-bottom: 1px dashed var(--border); }
  .attach-item:last-child { border-bottom: none; }
  .attach-icon { width: 34px; height: 34px; border-radius: 9px; background: var(--primary-50); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; }
  .attach-name { font-size: 13px; font-weight: 600; }
  .attach-meta { font-size: 11px; color: var(--text-muted); }

  /* ---- Activity log ---- */
  .activity-item { display: flex; gap: 10px; padding-bottom: 14px; position: relative; }
  .activity-item:not(:last-child)::before { content: ""; position: absolute; left: 5px; top: 16px; bottom: -2px; width: 1px; background: var(--border); }
  .activity-dot { width: 11px; height: 11px; border-radius: 50%; background: var(--primary); margin-top: 4px; flex-shrink: 0; }
  .activity-text { font-size: 13px; }
  .activity-time { font-size: 11px; color: var(--text-muted); }

  .file-drop { border: 1.5px dashed var(--border); border-radius: 10px; padding: 12px; text-align: center; font-size: 12.5px; color: var(--text-muted); cursor: pointer; }
  .file-drop:hover { border-color: var(--primary); color: var(--primary); }
</style>

<div class="page-content">
  <section class="page-section active" id="page-tasks">

    <div class="page-header">
      <div>
        <span class="breadcrumb-eyebrow">Workspace</span>
        <h1>Tasks</h1>
        <p>@if($isSuperAdmin) Assign, track and follow up on work across the team. @else Tasks assigned to you — update status as you make progress. @endif</p>
      </div>
      @if($isSuperAdmin)
      <div class="page-header-actions d-flex gap-2">
        <button class="btn btn-primary" id="openAddTaskBtn">
          <i class="bi bi-plus-lg me-1"></i>Add Task
        </button>
      </div>
      @endif
    </div>

    <!-- Stat cards -->
    <div class="row g-3 mb-3">
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div><div class="stat-label">Total Tasks</div><div class="stat-value" id="statTotal">0</div></div>
          <div class="stat-icon" style="background:var(--primary-50); color:var(--primary)"><i class="bi bi-list-task"></i></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div><div class="stat-label">In Progress</div><div class="stat-value" id="statInProgress">0</div></div>
          <div class="stat-icon" style="background:var(--warning-bg); color:var(--warning)"><i class="bi bi-hourglass-split"></i></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div><div class="stat-label">Completed</div><div class="stat-value" id="statCompleted">0</div></div>
          <div class="stat-icon" style="background:var(--success-bg); color:var(--success)"><i class="bi bi-check-circle"></i></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div><div class="stat-label">Overdue</div><div class="stat-value" id="statOverdue">0</div></div>
          <div class="stat-icon" style="background:var(--danger-bg); color:var(--danger)"><i class="bi bi-exclamation-circle"></i></div>
        </div>
      </div>
    </div>

    <!-- Category tabs -->
    <ul class="nav lead-tabs mb-3" id="taskCategoryTabs">
      <li class="nav-item"><a class="nav-link active" data-category="all" href="#">All</a></li>
      <li class="nav-item"><a class="nav-link" data-category="pending" href="#">Pending</a></li>
      <li class="nav-item"><a class="nav-link" data-category="in_progress" href="#">In Progress</a></li>
      <li class="nav-item"><a class="nav-link" data-category="completed" href="#">Completed</a></li>
      <li class="nav-item"><a class="nav-link" data-category="reverted" href="#">Reverted</a></li>
      <li class="nav-item"><a class="nav-link" data-category="overdue" href="#">Overdue</a></li>
    </ul>

    <!-- Filter bar -->
    <div class="filter-bar">
      <div class="filter-search">
        <i class="bi bi-search"></i>
        <input type="text" class="form-control" id="filterSearch" placeholder="Search by task name...">
      </div>
      <select class="form-select" id="filterPriority" style="max-width:150px">
        <option value="">All Priorities</option>
        @foreach($priorities as $p)
          <option value="{{ $p }}">{{ ucfirst($p) }}</option>
        @endforeach
      </select>
      @if($isSuperAdmin)
      <select class="form-select" id="filterAssignee" style="max-width:180px">
        <option value="">All Employees</option>
        @foreach($admins as $a)
          <option value="{{ $a->id }}">{{ $a->name }}</option>
        @endforeach
      </select>
      @endif
      <button class="btn btn-outline-secondary btn-sm" id="clearFiltersBtn">Clear</button>
    </div>

    <!-- Bulk actions bar -->
    @if($isSuperAdmin)
    <div class="filter-bar d-none" id="bulkBar">
      <span class="fs-13 fw-600"><span id="bulkCount">0</span> selected</span>
      <button class="btn btn-sm btn-outline-secondary ms-auto" id="bulkClearBtn">Clear selection</button>
      <button class="btn btn-sm" style="background:var(--danger-bg);color:var(--danger)" id="bulkDeleteBtn"><i class="bi bi-trash me-1"></i>Delete selected</button>
    </div>
    @endif

    <!-- Table -->
    <div class="section-card">
      <div class="table-wrap">
        <table class="crm-table">
          <thead>
            <tr>
              @if($isSuperAdmin)<th style="width:40px"><input type="checkbox" class="form-check-input" id="selectAllCheck"></th>@endif
              <th>Task</th>
              <th>Priority</th>
              <th>Due Date</th>
              <th>Status</th>
              <th>Assigned To</th>
              <th>Assigned By</th>
              <th style="width:90px"></th>
              <th></th>
            </tr>
          </thead>
          <tbody id="tasksTbody"></tbody>
        </table>
      </div>
      <div class="crm-pagination">
        <span class="page-info" id="pageInfo"></span>
        <div class="d-flex gap-1" id="pageButtons"></div>
      </div>
    </div>
  </section>
</div>

<!-- ===================== ADD / EDIT TASK MODAL ===================== -->
@if($isSuperAdmin)
<div class="modal fade" id="taskModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form id="taskForm">
        <input type="hidden" id="taskId">
        <div class="modal-header">
          <h5 class="modal-title" id="taskModalTitle">Add new task</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Task name</label>
              <input type="text" class="form-control" id="taskName" placeholder="e.g. Follow up with vendor" required>
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea class="form-control" id="taskDescription" rows="3" placeholder="What needs to be done..."></textarea>
            </div>
            <div class="col-4">
              <label class="form-label">Priority</label>
              <select class="form-select" id="taskPriority">
                @foreach($priorities as $p)
                  <option value="{{ $p }}">{{ ucfirst($p) }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-4">
              <label class="form-label">Due date</label>
              <input type="date" class="form-control" id="taskDueDate">
            </div>
            <div class="col-4">
              <label class="form-label">Assign to</label>
              <select class="form-select" id="taskAssignedTo" required>
                <option value="">Select employee</option>
                @foreach($admins as $a)
                  <option value="{{ $a->id }}">{{ $a->name }}</option>
                @endforeach
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="taskSubmitBtn">Add task</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif

<!-- ===================== TASK DETAIL OFFCANVAS ===================== -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="taskDetailOffcanvas" style="width:460px">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title">Task details</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body">
    <div class="mb-2 d-flex justify-content-between align-items-start">
      <div class="fw-600 fs-15" id="tdName" style="font-size:16px"></div>
      <span id="tdPriorityBadge"></span>
    </div>
    <div class="text-muted-2 fs-13 mb-3" id="tdDescription"></div>

    <div class="mb-3">
      <label class="form-label fs-11 text-uppercase text-muted-2 fw-700 mb-1">Status</label><br>
      <select class="status-select" id="tdStatusSelect">
        @foreach($statuses as $s)
          <option value="{{ $s }}">{{ ucwords(str_replace('_',' ',$s)) }}</option>
        @endforeach
      </select>
    </div>

    <div class="card-flat p-3 mb-3">
      <div class="d-flex justify-content-between mb-2"><span class="text-muted-2 fs-13">Due date</span><span class="fw-600 fs-13" id="tdDueDate">—</span></div>
      <div class="d-flex justify-content-between mb-2"><span class="text-muted-2 fs-13">Assigned to</span><span class="fw-600 fs-13" id="tdAssignedTo">—</span></div>
      <div class="d-flex justify-content-between"><span class="text-muted-2 fs-13">Assigned by</span><span class="fw-600 fs-13" id="tdAssignedBy">—</span></div>
    </div>

    <div class="td-tabs">
      <button class="td-tab active" data-tab="comments">Comments</button>
      <button class="td-tab" data-tab="attachments">Attachments</button>
      <button class="td-tab" data-tab="activity">Activity Log</button>
    </div>

    <div class="td-pane active" id="tdPaneComments">
      <form id="commentForm" class="mb-3">
        <input type="hidden" id="commentTaskId">
        <textarea class="form-control mb-2" id="commentText" rows="2" placeholder="Write a comment..." required></textarea>
        <button type="submit" class="btn btn-primary btn-sm w-100">Post comment</button>
      </form>
      <div id="commentsList"></div>
    </div>

    <div class="td-pane" id="tdPaneAttachments">
      <label class="file-drop mb-3" id="fileDropLabel">
        <i class="bi bi-cloud-arrow-up fs-4 d-block mb-1"></i>
        Click to upload a file (max 10MB)
        <input type="file" id="fileInput" hidden>
      </label>
      <div id="attachmentsList"></div>
    </div>

    <div class="td-pane" id="tdPaneActivity">
      <div id="activityList"></div>
    </div>
  </div>
</div>

<!-- ===================== DELETE CONFIRM MODAL ===================== -->
@if($isSuperAdmin)
<div class="modal fade" id="deleteConfirmModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-content-compact">
    <div class="modal-content modal-content-danger">
      <div class="modal-header">
        <h5 class="modal-title">Delete task(s)?</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0">This will permanently delete the selected task(s), their comments and attachments. This can't be undone.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirmDeleteBtn">Delete</button>
      </div>
    </div>
  </div>
</div>
@endif

<script>
  window.TASK_PAGE_DATA = {
    isSuperAdmin: @json($isSuperAdmin),
    admins: @json($admins),
    csrfToken: '{{ csrf_token() }}',
    routes: {
      data: '{{ route('tasks.data') }}',
      store: '{{ route('tasks.store') }}',
      update: '{{ url('admin/tasks') }}/__ID__',
      destroy: '{{ url('admin/tasks') }}/__ID__',
      show: '{{ url('admin/tasks') }}/__ID__',
      updateStatus: '{{ url('admin/tasks') }}/__ID__/status',
      bulkDelete: '{{ route('tasks.bulkDestroy') }}',
      commentStore: '{{ route('tasks.comments.store') }}',
      attachmentStore: '{{ route('tasks.attachments.store') }}',
      attachmentDestroy: '{{ url('admin/tasks/attachments') }}/__ID__',
    }
  };

  (function () {
    "use strict";
    const CFG = window.TASK_PAGE_DATA;
    const CSRF = CFG.csrfToken;
    const AVATAR_COLORS = ["#4338CA", "#0891B2", "#B45309", "#16A34A", "#DC2626", "#6D28D9", "#0D9488"];

    let tasks = [];
    let currentAdmin = null;
    let activeFilters = { search: "", priority: "", assignee: "" };
    let activeCategory = "all";
    let currentPage = 1;
    const PAGE_SIZE = 8;
    let selectedIds = new Set();
    let currentDetailTaskId = null;
    let currentDetailTask = null;

    function initials(name) { return (name || "?").trim().split(/\s+/).slice(0, 2).map(p => p[0]).join("").toUpperCase(); }
    function colorFor(name) { let h = 0; for (let i = 0; i < (name || "").length; i++) h = name.charCodeAt(i) + ((h << 5) - h); return AVATAR_COLORS[Math.abs(h) % AVATAR_COLORS.length]; }
    function avatarHtml(name, size) {
      const cls = size === "lg" ? "avatar avatar-lg" : size === "sm" ? "avatar avatar-sm" : "avatar";
      return `<div class="${cls}" style="background:${colorFor(name)}">${initials(name)}</div>`;
    }
    function priorityBadge(p) { return `<span class="badge-status pr-${p}">${p.charAt(0).toUpperCase() + p.slice(1)}</span>`; }
    function statusBadge(s) { return `<span class="badge-status st-${s}">${s.split('_').map(w => w[0].toUpperCase() + w.slice(1)).join(' ')}</span>`; }
    function fmtDate(d) { if (!d) return ""; const dt = new Date(d); if (isNaN(dt)) return d; return dt.toLocaleDateString(undefined, { day: "numeric", month: "short", year: "numeric" }); }
    function fmtDateTime(d) { if (!d) return ""; const dt = new Date(d); if (isNaN(dt)) return d; return dt.toLocaleString(undefined, { day: "numeric", month: "short", hour: "2-digit", minute: "2-digit" }); }
    function isOverdue(t) { if (!t.due_date || t.status === "completed") return false; return new Date(t.due_date) < new Date(new Date().toDateString()); }

    function toast(message, variant) {
      let wrap = document.getElementById("tfToastWrap");
      if (!wrap) { wrap = document.createElement("div"); wrap.id = "tfToastWrap"; wrap.style.cssText = "position:fixed;bottom:20px;right:20px;z-index:2000;display:flex;flex-direction:column;gap:8px;"; document.body.appendChild(wrap); }
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
      if (res.status === 401 || res.status === 419) { toast("Your session expired, please log in again.", "danger"); throw new Error("unauthenticated"); }
      return res.json();
    }

    async function loadTasks() {
      const data = await apiFetch(CFG.routes.data);
      if (!data.success) return;
      tasks = data.tasks;
      currentAdmin = data.current_admin;
      renderAll();
    }

    function getFilteredTasks() {
      return tasks.filter(t => {
        const term = activeFilters.search.toLowerCase();
        const matchesSearch = !term || t.task_name.toLowerCase().includes(term);
        const matchesPriority = !activeFilters.priority || t.priority === activeFilters.priority;
        const matchesAssignee = !activeFilters.assignee || String(t.assigned_to) === String(activeFilters.assignee);

        let matchesCategory = true;
        if (activeCategory === "overdue") matchesCategory = isOverdue(t);
        else if (activeCategory !== "all") matchesCategory = t.status === activeCategory;

        return matchesSearch && matchesPriority && matchesAssignee && matchesCategory;
      });
    }

    function renderAll() { renderStats(); renderTable(); }

    function renderStats() {
      document.getElementById("statTotal").textContent = tasks.length;
      document.getElementById("statInProgress").textContent = tasks.filter(t => t.status === "in_progress").length;
      document.getElementById("statCompleted").textContent = tasks.filter(t => t.status === "completed").length;
      document.getElementById("statOverdue").textContent = tasks.filter(isOverdue).length;
    }

    function renderTable() {
      const filtered = getFilteredTasks();
      const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
      currentPage = Math.min(currentPage, totalPages);
      const start = (currentPage - 1) * PAGE_SIZE;
      const pageItems = filtered.slice(start, start + PAGE_SIZE);
      const tbody = document.getElementById("tasksTbody");
      const colspan = CFG.isSuperAdmin ? 9 : 8;

      if (pageItems.length === 0) {
        tbody.innerHTML = `<tr><td colspan="${colspan}"><div class="empty-state"><i class="bi bi-inbox"></i><h6>No tasks found</h6><p class="fs-13 mb-0">Try adjusting filters${CFG.isSuperAdmin ? " or add a new task" : ""}.</p></div></td></tr>`;
      } else {
        tbody.innerHTML = pageItems.map(t => {
          const overdue = isOverdue(t);
          return `
          <tr data-id="${t.id}" class="${overdue ? "is-overdue-row" : ""}">
            ${CFG.isSuperAdmin ? `<td onclick="event.stopPropagation()"><input class="form-check-input row-check" type="checkbox" data-id="${t.id}" ${selectedIds.has(t.id) ? "checked" : ""}></td>` : ""}
            <td><div class="lead-name">${t.task_name}</div></td>
            <td>${priorityBadge(t.priority)}</td>
            <td class="${overdue ? "due-date-overdue" : "text-muted-2"}">${t.due_date ? fmtDate(t.due_date) : "—"}</td>
            <td>${statusBadge(t.status)}</td>
            <td>${t.assignee ? `<div class="d-flex align-items-center gap-2">${avatarHtml(t.assignee.name, "sm")}<span class="fs-13">${t.assignee.name}</span></div>` : `<span class="unassigned-text">Unassigned</span>`}</td>
            <td class="text-muted-2">${t.assigner ? t.assigner.name : "—"}</td>
            <td class="text-muted-2 fs-12"><i class="bi bi-chat-left-text me-1"></i>${t.comments_count || 0} &nbsp; <i class="bi bi-paperclip"></i>${t.attachments_count || 0}</td>
            <td onclick="event.stopPropagation()">
              <div class="dropdown row-actions">
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li><a class="dropdown-item view-task-action" href="#" data-id="${t.id}"><i class="bi bi-eye me-2"></i>View details</a></li>
                  ${CFG.isSuperAdmin ? `<li><a class="dropdown-item edit-task-action" href="#" data-id="${t.id}"><i class="bi bi-pencil me-2"></i>Edit task</a></li>
                  <li><hr class="dropdown-divider"></li>
                  <li><a class="dropdown-item text-danger delete-task-action" href="#" data-id="${t.id}"><i class="bi bi-trash me-2"></i>Delete</a></li>` : ""}
                </ul>
              </div>
            </td>
          </tr>`;
        }).join("");
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
      document.querySelectorAll("#tasksTbody tr[data-id]").forEach(row => {
        row.addEventListener("click", () => openTaskDetail(parseInt(row.dataset.id, 10)));
      });
      document.querySelectorAll(".row-check").forEach(cb => {
        cb.addEventListener("change", () => {
          const id = parseInt(cb.dataset.id, 10);
          if (cb.checked) selectedIds.add(id); else selectedIds.delete(id);
          updateBulkBar();
        });
      });
      document.querySelectorAll(".view-task-action").forEach(a => a.addEventListener("click", e => { e.preventDefault(); openTaskDetail(parseInt(a.dataset.id, 10)); }));
      document.querySelectorAll(".edit-task-action").forEach(a => a.addEventListener("click", e => { e.preventDefault(); openTaskModal(parseInt(a.dataset.id, 10)); }));
      document.querySelectorAll(".delete-task-action").forEach(a => {
        a.addEventListener("click", e => {
          e.preventDefault();
          selectedIds = new Set([parseInt(a.dataset.id, 10)]);
          bootstrap.Modal.getOrCreateInstance(document.getElementById("deleteConfirmModal")).show();
        });
      });
      const selectAll = document.getElementById("selectAllCheck");
      if (selectAll) {
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
    }

    function updateBulkBar() {
      const bar = document.getElementById("bulkBar");
      if (!bar) return;
      if (selectedIds.size > 0) { bar.classList.remove("d-none"); document.getElementById("bulkCount").textContent = selectedIds.size; }
      else bar.classList.add("d-none");
    }

    function initFilters() {
      document.getElementById("filterSearch").addEventListener("input", e => { activeFilters.search = e.target.value; currentPage = 1; renderTable(); });
      document.getElementById("filterPriority").addEventListener("change", e => { activeFilters.priority = e.target.value; currentPage = 1; renderTable(); });
      const assigneeEl = document.getElementById("filterAssignee");
      if (assigneeEl) assigneeEl.addEventListener("change", e => { activeFilters.assignee = e.target.value; currentPage = 1; renderTable(); });
      document.getElementById("clearFiltersBtn").addEventListener("click", () => {
        activeFilters = { search: "", priority: "", assignee: "" };
        document.getElementById("filterSearch").value = "";
        document.getElementById("filterPriority").value = "";
        if (assigneeEl) assigneeEl.value = "";
        currentPage = 1;
        renderTable();
      });
      document.querySelectorAll("#taskCategoryTabs .nav-link").forEach(tab => {
        tab.addEventListener("click", e => {
          e.preventDefault();
          document.querySelectorAll("#taskCategoryTabs .nav-link").forEach(t => t.classList.remove("active"));
          tab.classList.add("active");
          activeCategory = tab.dataset.category;
          currentPage = 1;
          renderTable();
        });
      });
    }

    function initBulkActions() {
      const clearBtn = document.getElementById("bulkClearBtn");
      if (!clearBtn) return;
      clearBtn.addEventListener("click", () => { selectedIds.clear(); renderTable(); });
      document.getElementById("bulkDeleteBtn").addEventListener("click", () => {
        bootstrap.Modal.getOrCreateInstance(document.getElementById("deleteConfirmModal")).show();
      });
      document.getElementById("confirmDeleteBtn").addEventListener("click", async () => {
        const ids = Array.from(selectedIds);
        if (!ids.length) return;
        const res = ids.length === 1
          ? await apiFetch(CFG.routes.destroy.replace("__ID__", ids[0]), { method: "DELETE" })
          : await apiFetch(CFG.routes.bulkDelete, { method: "POST", headers: csrfHeaders({ "Content-Type": "application/json" }), body: JSON.stringify({ ids }) });
        if (res.success) {
          tasks = tasks.filter(t => !ids.includes(t.id));
          selectedIds.clear();
          renderAll();
          toast(res.message || "Deleted", "dark");
        }
        bootstrap.Modal.getOrCreateInstance(document.getElementById("deleteConfirmModal")).hide();
      });
    }

    function openTaskModal(id) {
      const form = document.getElementById("taskForm");
      form.reset();
      document.getElementById("taskId").value = "";
      if (id) {
        const t = tasks.find(x => x.id === id);
        document.getElementById("taskModalTitle").textContent = "Edit task";
        document.getElementById("taskSubmitBtn").textContent = "Save changes";
        document.getElementById("taskId").value = t.id;
        document.getElementById("taskName").value = t.task_name;
        document.getElementById("taskDescription").value = t.description || "";
        document.getElementById("taskPriority").value = t.priority;
        document.getElementById("taskDueDate").value = t.due_date ? t.due_date.slice(0, 10) : "";
        document.getElementById("taskAssignedTo").value = t.assigned_to || "";
      } else {
        document.getElementById("taskModalTitle").textContent = "Add new task";
        document.getElementById("taskSubmitBtn").textContent = "Add task";
      }
      bootstrap.Modal.getOrCreateInstance(document.getElementById("taskModal")).show();
    }

    function initTaskForm() {
      const addBtn = document.getElementById("openAddTaskBtn");
      if (addBtn) addBtn.addEventListener("click", () => openTaskModal(null));
      const form = document.getElementById("taskForm");
      if (!form) return;
      form.addEventListener("submit", async e => {
        e.preventDefault();
        const id = document.getElementById("taskId").value;
        const payload = {
          task_name: document.getElementById("taskName").value.trim(),
          description: document.getElementById("taskDescription").value.trim(),
          priority: document.getElementById("taskPriority").value,
          due_date: document.getElementById("taskDueDate").value || null,
          assigned_to: document.getElementById("taskAssignedTo").value,
        };
        const url = id ? CFG.routes.update.replace("__ID__", id) : CFG.routes.store;
        const res = await apiFetch(url, { method: id ? "PUT" : "POST", headers: csrfHeaders({ "Content-Type": "application/json" }), body: JSON.stringify(payload) });
        if (!res.success) { toast(res.errors ? Object.values(res.errors)[0][0] : (res.message || "Something went wrong."), "danger"); return; }
        if (id) tasks = tasks.map(t => (t.id == id ? res.task : t));
        else tasks.unshift(res.task);
        renderAll();
        bootstrap.Modal.getOrCreateInstance(document.getElementById("taskModal")).hide();
        toast(res.message);
      });
    }

    /* ---------------- Detail offcanvas ---------------- */

    function initTabs() {
      document.querySelectorAll(".td-tab").forEach(tab => {
        tab.addEventListener("click", () => {
          document.querySelectorAll(".td-tab").forEach(t => t.classList.remove("active"));
          document.querySelectorAll(".td-pane").forEach(p => p.classList.remove("active"));
          tab.classList.add("active");
          document.getElementById("tdPane" + tab.dataset.tab.charAt(0).toUpperCase() + tab.dataset.tab.slice(1)).classList.add("active");
        });
      });
    }

    async function openTaskDetail(id) {
      const res = await apiFetch(CFG.routes.show.replace("__ID__", id));
      if (!res.success) { toast(res.message || "Could not open task.", "danger"); return; }
      const t = res.task;
      currentDetailTaskId = t.id;
      currentDetailTask = t;

      document.getElementById("tdName").textContent = t.task_name;
      document.getElementById("tdPriorityBadge").innerHTML = priorityBadge(t.priority);
      document.getElementById("tdDescription").textContent = t.description || "No description.";
      document.getElementById("tdDueDate").textContent = t.due_date ? fmtDate(t.due_date) : "—";
      document.getElementById("tdAssignedTo").textContent = t.assignee ? t.assignee.name : "Unassigned";
      document.getElementById("tdAssignedBy").textContent = t.assigner ? t.assigner.name : "—";

      const sel = document.getElementById("tdStatusSelect");
      sel.value = t.status;
      sel.className = "status-select st-" + t.status;

      document.getElementById("commentTaskId").value = t.id;
      document.getElementById("commentText").value = "";

      renderComments(t.comments || []);
      renderAttachments(t.attachments || []);
      renderActivity(t.activity_logs || []);

      document.querySelectorAll(".td-tab").forEach(t2 => t2.classList.remove("active"));
      document.querySelectorAll(".td-pane").forEach(p => p.classList.remove("active"));
      document.querySelector('.td-tab[data-tab="comments"]').classList.add("active");
      document.getElementById("tdPaneComments").classList.add("active");

      bootstrap.Offcanvas.getOrCreateInstance(document.getElementById("taskDetailOffcanvas")).show();
    }

    // Exposed for the bell dropdown to open a task directly if already on this page.
    window.openTaskDetailById = openTaskDetail;

    function renderComments(comments) {
      const box = document.getElementById("commentsList");
      if (!comments.length) { box.innerHTML = `<div class="empty-state py-3"><i class="bi bi-chat-left"></i><h6 class="fs-13">No comments yet</h6></div>`; return; }
      box.innerHTML = comments.map(c => `
        <div class="comment-item">
          ${avatarHtml(c.author ? c.author.name : "?", "sm")}
          <div class="comment-body">
            <span class="comment-author">${c.author ? c.author.name : "Unknown"}</span><span class="comment-time">${fmtDateTime(c.created_at)}</span>
            <div class="comment-text">${c.comment}</div>
          </div>
        </div>`).join("");
    }

    function renderAttachments(attachments) {
      const box = document.getElementById("attachmentsList");
      if (!attachments.length) { box.innerHTML = `<div class="empty-state py-3"><i class="bi bi-paperclip"></i><h6 class="fs-13">No attachments yet</h6></div>`; return; }
      box.innerHTML = attachments.map(a => {
        const canDelete = CFG.isSuperAdmin || (currentAdmin && a.admin_id === currentAdmin.id);
        return `
        <div class="attach-item">
          <div class="attach-icon"><i class="bi bi-file-earmark"></i></div>
          <div class="flex-grow-1">
            <div class="attach-name">${a.original_name}</div>
            <div class="attach-meta">${a.human_size} · uploaded by ${a.uploader ? a.uploader.name : "—"}</div>
          </div>
          <a href="${a.url}" target="_blank" class="btn btn-sm btn-outline-secondary" style="width:30px;height:30px;padding:0"><i class="bi bi-download"></i></a>
          ${canDelete ? `<button class="btn btn-sm btn-outline-secondary" data-del-attach="${a.id}" style="width:30px;height:30px;padding:0"><i class="bi bi-x"></i></button>` : ""}
        </div>`;
      }).join("");
      box.querySelectorAll("[data-del-attach]").forEach(btn => {
        btn.addEventListener("click", async () => {
          const id = parseInt(btn.dataset.delAttach, 10);
          const res = await apiFetch(CFG.routes.attachmentDestroy.replace("__ID__", id), { method: "DELETE" });
          if (res.success) { toast("Attachment removed"); openTaskDetail(currentDetailTaskId); }
        });
      });
    }

    function renderActivity(logs) {
      const box = document.getElementById("activityList");
      if (!logs.length) { box.innerHTML = `<div class="empty-state py-3"><i class="bi bi-clock-history"></i><h6 class="fs-13">No activity yet</h6></div>`; return; }
      box.innerHTML = logs.map(l => `
        <div class="activity-item">
          <div class="activity-dot"></div>
          <div>
            <div class="activity-text">${l.description}</div>
            <div class="activity-time">${fmtDateTime(l.created_at)}</div>
          </div>
        </div>`).join("");
    }

    function initStatusSelect() {
      const sel = document.getElementById("tdStatusSelect");
      sel.addEventListener("change", async () => {
        if (!currentDetailTaskId) return;
        const previousClass = sel.className;
        sel.className = "status-select st-" + sel.value;
        const res = await apiFetch(CFG.routes.updateStatus.replace("__ID__", currentDetailTaskId), {
          method: "PATCH", headers: csrfHeaders({ "Content-Type": "application/json" }), body: JSON.stringify({ status: sel.value }),
        });
        if (res.success) {
          const cached = tasks.find(t => t.id === currentDetailTaskId);
          if (cached) { cached.status = res.task.status; }
          renderAll();
          toast(res.message);
          openTaskDetail(currentDetailTaskId);
        } else {
          sel.className = previousClass;
          toast(res.message || "Could not update status.", "danger");
        }
      });
    }

    function initCommentForm() {
      document.getElementById("commentForm").addEventListener("submit", async e => {
        e.preventDefault();
        const taskId = document.getElementById("commentTaskId").value;
        const payload = { task_id: taskId, comment: document.getElementById("commentText").value.trim() };
        if (!payload.comment) return;
        const res = await apiFetch(CFG.routes.commentStore, { method: "POST", headers: csrfHeaders({ "Content-Type": "application/json" }), body: JSON.stringify(payload) });
        if (!res.success) { toast(res.message || "Could not add comment.", "danger"); return; }
        document.getElementById("commentText").value = "";
        openTaskDetail(taskId);
        loadTasks();
      });
    }

    function initFileUpload() {
      const input = document.getElementById("fileInput");
      input.addEventListener("change", async () => {
        if (!input.files.length || !currentDetailTaskId) return;
        const fd = new FormData();
        fd.append("task_id", currentDetailTaskId);
        fd.append("file", input.files[0]);
        const res = await fetch(CFG.routes.attachmentStore, { method: "POST", headers: csrfHeaders({ "Accept": "application/json" }), body: fd });
        const data = await res.json();
        if (!data.success) { toast(data.message || "Upload failed.", "danger"); return; }
        toast("File attached");
        input.value = "";
        openTaskDetail(currentDetailTaskId);
        loadTasks();
      });
    }

    function openTaskFromQueryString() {
      const params = new URLSearchParams(window.location.search);
      const taskId = params.get("task");
      if (!taskId) return;
      openTaskDetail(parseInt(taskId, 10));
      params.delete("task");
      const newUrl = window.location.pathname + (params.toString() ? `?${params}` : "");
      window.history.replaceState({}, "", newUrl);
    }

    document.addEventListener("DOMContentLoaded", () => {
      initFilters();
      initBulkActions();
      initTaskForm();
      initTabs();
      initStatusSelect();
      initCommentForm();
      initFileUpload();
      loadTasks().then(openTaskFromQueryString);
    });
  })();
</script>

@endsection
