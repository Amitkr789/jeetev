/* ==========================================================================
   LeadFlow CRM — Leads page logic
   ========================================================================== */

(function () {
  "use strict";
  const LF = window.LeadFlow;

  let activeFilters = { search: "", status: "", source: "", owner: "" };
  let currentPage = 1;
  const PAGE_SIZE = 8;
  let selectedIds = new Set();

  function populateFilterOptions() {
    const ownerSel = document.getElementById("filterOwner");
    LF.OWNERS.forEach(o => ownerSel.insertAdjacentHTML("beforeend", `<option value="${o}">${o}</option>`));
    const sourceSel = document.getElementById("filterSource");
    LF.SOURCES.forEach(s => sourceSel.insertAdjacentHTML("beforeend", `<option value="${s}">${s}</option>`));
  }

  function getFilteredLeads() {
    const leads = LF.getLeads();
    return leads.filter(l => {
      const matchesSearch = !activeFilters.search ||
        (l.name + l.company + l.email).toLowerCase().includes(activeFilters.search.toLowerCase());
      const matchesStatus = !activeFilters.status || l.status === activeFilters.status;
      const matchesSource = !activeFilters.source || l.source === activeFilters.source;
      const matchesOwner = !activeFilters.owner || l.owner === activeFilters.owner;
      return matchesSearch && matchesStatus && matchesSource && matchesOwner;
    });
  }

  function renderActivePills() {
    const wrap = document.getElementById("activeFilterPills");
    wrap.innerHTML = "";
    const map = { status: "Status", source: "Source", owner: "Owner" };
    Object.keys(map).forEach(key => {
      if (activeFilters[key]) {
        wrap.insertAdjacentHTML("beforeend", `
          <span class="filter-pill">${map[key]}: ${activeFilters[key]}
            <button type="button" data-clear="${key}"><i class="bi bi-x"></i></button>
          </span>`);
      }
    });
    wrap.querySelectorAll("[data-clear]").forEach(btn => {
      btn.addEventListener("click", () => {
        activeFilters[btn.dataset.clear] = "";
        document.getElementById("filter" + btn.dataset.clear.charAt(0).toUpperCase() + btn.dataset.clear.slice(1)).value = "";
        currentPage = 1;
        renderLeadsTable();
      });
    });
  }

  function initFilters() {
    populateFilterOptions();
    document.getElementById("filterSearch").addEventListener("input", (e) => {
      activeFilters.search = e.target.value; currentPage = 1; renderLeadsTable();
    });
    document.getElementById("filterStatus").addEventListener("change", (e) => {
      activeFilters.status = e.target.value; currentPage = 1; renderLeadsTable();
    });
    document.getElementById("filterSource").addEventListener("change", (e) => {
      activeFilters.source = e.target.value; currentPage = 1; renderLeadsTable();
    });
    document.getElementById("filterOwner").addEventListener("change", (e) => {
      activeFilters.owner = e.target.value; currentPage = 1; renderLeadsTable();
    });
    document.getElementById("clearFiltersBtn").addEventListener("click", () => {
      activeFilters = { search: "", status: "", source: "", owner: "" };
      ["filterSearch", "filterStatus", "filterSource", "filterOwner"].forEach(id => document.getElementById(id).value = "");
      currentPage = 1;
      renderLeadsTable();
    });
  }

  /* ---------------- Leads table ---------------- */
  function renderLeadsTable() {
    const filtered = getFilteredLeads();
    const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
    currentPage = Math.min(currentPage, totalPages);
    const start = (currentPage - 1) * PAGE_SIZE;
    const pageItems = filtered.slice(start, start + PAGE_SIZE);

    const tbody = document.getElementById("leadsTbody");
    document.getElementById("leadsCountBadge").textContent = filtered.length + " leads";

    if (pageItems.length === 0) {
      tbody.innerHTML = `<tr><td colspan="9"><div class="empty-state">
        <i class="bi bi-inbox"></i><h6>No leads match your filters</h6>
        <p class="fs-13 mb-0">Try adjusting or clearing the filters above.</p></div></td></tr>`;
    } else {
      tbody.innerHTML = pageItems.map(l => `
        <tr data-id="${l.id}">
          <td onclick="event.stopPropagation()">
            <input class="form-check-input row-check" type="checkbox" data-id="${l.id}" ${selectedIds.has(l.id) ? "checked" : ""}>
          </td>
          <td>
            <div class="cell-with-avatar">
              ${LF.avatarHtml(l.name)}
              <div>
                <div class="lead-name">${l.name}</div>
                <div class="lead-company">${l.company}</div>
              </div>
            </div>
          </td>
          <td>${l.email}</td>
          <td>${l.phone}</td>
          <td>${LF.statusBadge(l.status)}</td>
          <td>${l.source}</td>
          <td>${l.owner}</td>
          <td>${LF.scorePill(l.score)}</td>
          <td class="text-muted-2">${l.lastActivity}</td>
          <td onclick="event.stopPropagation()">
            <div class="dropdown row-actions">
              <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item view-lead-action" href="#" data-id="${l.id}"><i class="bi bi-eye me-2"></i>View details</a></li>
                <li><a class="dropdown-item" href="#"><i class="bi bi-pencil me-2"></i>Edit lead</a></li>
                <li><a class="dropdown-item" href="#"><i class="bi bi-clock-history me-2"></i>Add reminder</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger delete-lead-action" href="#" data-id="${l.id}"><i class="bi bi-trash me-2"></i>Delete</a></li>
              </ul>
            </div>
          </td>
        </tr>
      `).join("");
    }

    renderActivePills();
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
    for (let i = 1; i <= totalPages; i++) {
      html += `<button class="btn-page ${i === currentPage ? "active" : ""}" data-page="${i}">${i}</button>`;
    }
    html += `<button class="btn-page" ${currentPage === totalPages ? "disabled" : ""} data-page="next"><i class="bi bi-chevron-right"></i></button>`;
    nav.innerHTML = html;
    nav.querySelectorAll("[data-page]").forEach(btn => {
      btn.addEventListener("click", () => {
        if (btn.dataset.page === "prev") currentPage--;
        else if (btn.dataset.page === "next") currentPage++;
        else currentPage = parseInt(btn.dataset.page, 10);
        renderLeadsTable();
        document.getElementById("page-leads").scrollIntoView({ behavior: "smooth", block: "start" });
      });
    });
  }

  function bindRowEvents() {
    document.querySelectorAll("#leadsTbody tr[data-id]").forEach(row => {
      row.addEventListener("click", () => openLeadDetail(parseInt(row.dataset.id, 10)));
    });
    document.querySelectorAll(".row-check").forEach(cb => {
      cb.addEventListener("change", () => {
        const id = parseInt(cb.dataset.id, 10);
        if (cb.checked) selectedIds.add(id); else selectedIds.delete(id);
        updateBulkBar();
      });
    });
    document.querySelectorAll(".view-lead-action").forEach(a => {
      a.addEventListener("click", (e) => { e.preventDefault(); openLeadDetail(parseInt(a.dataset.id, 10)); });
    });
    document.querySelectorAll(".delete-lead-action").forEach(a => {
      a.addEventListener("click", (e) => {
        e.preventDefault();
        const id = parseInt(a.dataset.id, 10);
        const leads = LF.getLeads().filter(l => l.id !== id);
        LF.saveLeads(leads);
        selectedIds.delete(id);
        renderLeadsTable();
        LF.syncNavBadges();
        LF.toast("Lead deleted", "dark");
      });
    });
    const selectAll = document.getElementById("selectAllCheck");
    selectAll.checked = false;
    selectAll.addEventListener("change", () => {
      document.querySelectorAll(".row-check").forEach(cb => {
        cb.checked = selectAll.checked;
        const id = parseInt(cb.dataset.id, 10);
        if (selectAll.checked) selectedIds.add(id); else selectedIds.delete(id);
      });
      updateBulkBar();
    });
  }

  function updateBulkBar() {
    const bar = document.getElementById("bulkBar");
    if (selectedIds.size > 0) {
      bar.classList.remove("d-none");
      document.getElementById("bulkCount").textContent = selectedIds.size;
    } else {
      bar.classList.add("d-none");
    }
  }

  function initBulkActions() {
    document.getElementById("bulkDeleteBtn")?.addEventListener("click", () => {
      const leads = LF.getLeads().filter(l => !selectedIds.has(l.id));
      LF.saveLeads(leads);
      selectedIds.clear();
      renderLeadsTable();
      LF.syncNavBadges();
      LF.toast("Selected leads deleted", "dark");
    });
    document.getElementById("bulkClearBtn")?.addEventListener("click", () => {
      selectedIds.clear();
      renderLeadsTable();
    });
  }

  /* ---------------- Lead Detail Offcanvas ---------------- */
  function openLeadDetail(id) {
    const leads = LF.getLeads();
    const lead = leads.find(l => l.id === id);
    if (!lead) return;
    document.getElementById("ldAvatar").outerHTML = LF.avatarHtml(lead.name, "lg").replace('class="avatar avatar-lg"', 'class="avatar avatar-lg" id="ldAvatar"');
    document.getElementById("ldName").textContent = lead.name;
    document.getElementById("ldCompany").textContent = lead.company;
    document.getElementById("ldStatus").innerHTML = LF.statusBadge(lead.status);
    document.getElementById("ldEmail").textContent = lead.email;
    document.getElementById("ldPhone").textContent = lead.phone;
    document.getElementById("ldSource").textContent = lead.source;
    document.getElementById("ldOwner").textContent = lead.owner;
    document.getElementById("ldScore").innerHTML = LF.scorePill(lead.score);
    document.getElementById("ldLastActivity").textContent = lead.lastActivity;

    document.getElementById("ldTimeline").innerHTML = LF.timelineSamples.map(t => `
      <div class="timeline-item">
        <div class="timeline-dot" style="background:${t.color}"><i class="bi ${t.icon}"></i></div>
        <div class="ti-title">${t.title}</div>
        <div class="ti-time">${t.time}</div>
        <p>${t.desc}</p>
      </div>
    `).join("");

    const reminders = LF.getReminders();
    const leadReminders = reminders.filter(r => r.lead === lead.name);
    const ldTasks = document.getElementById("ldTasks");
    ldTasks.innerHTML = leadReminders.length ? leadReminders.map(r => `
      <div class="reminder-item ${r.completed ? "completed" : ""}">
        <div class="reminder-check"><i class="bi bi-check"></i></div>
        <div class="flex-grow-1">
          <div class="reminder-title">${r.title}</div>
          <div class="reminder-meta"><span><i class="bi bi-calendar3 me-1"></i>${LF.formatDate(r.date)}</span><span>${r.time}</span>${LF.priorityBadge(r.priority)}</div>
        </div>
      </div>
    `).join("") : `<div class="empty-state py-4"><i class="bi bi-bell-slash"></i><h6 class="fs-13">No reminders for this lead</h6></div>`;

    const offcanvasEl = document.getElementById("leadDetailOffcanvas");
    bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl).show();
  }

  /* ---------------- Add Lead Form ---------------- */
  function initAddLeadForm() {
    const form = document.getElementById("addLeadForm");
    if (!form) return;
    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const name = document.getElementById("alName").value.trim();
      const company = document.getElementById("alCompany").value.trim();
      const email = document.getElementById("alEmail").value.trim();
      const phone = document.getElementById("alPhone").value.trim();
      const source = document.getElementById("alSource").value;
      const owner = document.getElementById("alOwner").value;
      if (!name || !company || !email) return;

      const leads = LF.getLeads();
      leads.unshift({
        id: Date.now(), name, company, email, phone: phone || "—",
        status: "New", source: source || LF.SOURCES[0], owner: owner || LF.OWNERS[0],
        score: "Warm", lastActivity: "Just now"
      });
      LF.saveLeads(leads);

      currentPage = 1;
      renderLeadsTable();
      LF.syncNavBadges();
      e.target.reset();
      bootstrap.Modal.getOrCreateInstance(document.getElementById("addLeadModal")).hide();
      LF.toast("New lead added successfully");
    });
  }

  /* ---------------- Add Reminder Form (triggered from lead detail offcanvas) ---------------- */
  function initReminderForm() {
    const form = document.getElementById("reminderForm");
    if (!form) return;

    const leadSel = document.getElementById("remLead");
    LF.getLeads().forEach(l => leadSel.insertAdjacentHTML("beforeend", `<option value="${l.name}">${l.name}</option>`));

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const title = document.getElementById("remTitle").value.trim();
      const lead = document.getElementById("remLead").value;
      const date = document.getElementById("remDate").value;
      const time = document.getElementById("remTime").value;
      const priority = document.getElementById("remPriority").value;
      if (!title || !lead || !date || !time) return;

      const reminders = LF.getReminders();
      reminders.unshift({
        id: Date.now(), title, lead, date, time, priority,
        completed: false, group: "Upcoming"
      });
      LF.saveReminders(reminders);

      LF.syncNavBadges();
      e.target.reset();
      bootstrap.Modal.getOrCreateInstance(document.getElementById("addReminderModal")).hide();
      LF.toast("Reminder created");
    });
  }

  document.addEventListener("DOMContentLoaded", () => {
    initFilters();
    initAddLeadForm();
    initReminderForm();
    initBulkActions();
    renderLeadsTable();
  });
})();
