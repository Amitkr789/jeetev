/* ==========================================================================
   LeadFlow CRM — Dashboard page logic
   ========================================================================== */

(function () {
  "use strict";
  const LF = window.LeadFlow;

  function renderDashboard() {
    const leads = LF.getLeads();
    const reminders = LF.getReminders();

    document.getElementById("statTotalLeads").textContent = leads.length;
    document.getElementById("statNewLeads").textContent = leads.filter(l => l.status === "New").length;
    document.getElementById("statConverted").textContent = leads.filter(l => l.status === "Converted").length;
    document.getElementById("statOpenReminders").textContent = reminders.filter(r => !r.completed).length;

    const recent = [...leads].slice(0, 5);
    document.getElementById("dashRecentLeads").innerHTML = recent.map(l => `
      <div class="d-flex align-items-center gap-3 py-2 border-bottom" style="border-color:var(--border)!important">
        ${LF.avatarHtml(l.name)}
        <div class="flex-grow-1 min-w-0">
          <div class="fw-600 fs-13 text-truncate">${l.name}</div>
          <div class="fs-12 text-muted-2 text-truncate">${l.company}</div>
        </div>
        ${LF.statusBadge(l.status)}
      </div>
    `).join("");

    const upcoming = reminders.filter(r => !r.completed).slice(0, 5);
    document.getElementById("dashReminders").innerHTML = upcoming.length ? upcoming.map(r => `
      <div class="d-flex align-items-start gap-2 py-2 border-bottom" style="border-color:var(--border)!important">
        <i class="bi bi-clock text-muted-2 mt-1"></i>
        <div class="flex-grow-1 min-w-0">
          <div class="fw-600 fs-13 text-truncate">${r.title}</div>
          <div class="fs-12 text-muted-2">${r.lead} · ${LF.formatDate(r.date)}, ${r.time}</div>
        </div>
        ${LF.priorityBadge(r.priority)}
      </div>
    `).join("") : `<p class="fs-13 text-muted-2 mb-0">Nothing scheduled. Nice work!</p>`;
  }

  function initCharts() {
    const leads = LF.getLeads();
    const sourceCounts = {};
    LF.SOURCES.forEach(s => sourceCounts[s] = 0);
    leads.forEach(l => { sourceCounts[l.source] = (sourceCounts[l.source] || 0) + 1; });

    new Chart(document.getElementById("sourceChart"), {
      type: "doughnut",
      data: {
        labels: Object.keys(sourceCounts),
        datasets: [{
          data: Object.values(sourceCounts),
          backgroundColor: ["#4338CA", "#6366F1", "#0891B2", "#16A34A", "#D97706", "#DC2626", "#7C3AED"],
          borderWidth: 0,
        }],
      },
      options: {
        plugins: { legend: { position: "right", labels: { boxWidth: 10, font: { size: 11 }, color: "#767B91" } } },
        cutout: "68%",
      },
    });

    new Chart(document.getElementById("trendChart"), {
      type: "bar",
      data: {
        labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun"],
        datasets: [
          { label: "New Leads", data: [22, 28, 19, 34, 30, 41], backgroundColor: "#6366F1", borderRadius: 6, maxBarThickness: 26 },
          { label: "Converted", data: [6, 9, 7, 12, 11, 16], backgroundColor: "#16A34A", borderRadius: 6, maxBarThickness: 26 },
        ],
      },
      options: {
        responsive: true,
        plugins: { legend: { position: "top", align: "end", labels: { boxWidth: 10, font: { size: 11 }, color: "#767B91" } } },
        scales: {
          x: { grid: { display: false }, ticks: { font: { size: 11 }, color: "#767B91" } },
          y: { grid: { color: "#F0F1F7" }, ticks: { font: { size: 11 }, color: "#767B91" } },
        },
      },
    });
  }

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

      renderDashboard();
      LF.syncNavBadges();
      e.target.reset();
      bootstrap.Modal.getOrCreateInstance(document.getElementById("addLeadModal")).hide();
      LF.toast("New lead added successfully");
    });
  }

  function initJumpLinks() {
    // "View all" quick links already point to leads.html / reminders.html as real hrefs,
    // nothing extra needed — kept here in case future JS-based behaviour is required.
  }

  document.addEventListener("DOMContentLoaded", () => {
    renderDashboard();
    initCharts();
    initAddLeadForm();
    initJumpLinks();
  });
})();
