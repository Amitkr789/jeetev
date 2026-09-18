/* ==========================================================================
   LeadFlow CRM — Shared data store, helpers & sidebar/nav logic
   Loaded on EVERY page. Data persists across pages via localStorage so that
   leads/reminders added on one page are visible on the others.
   ========================================================================== */

window.LeadFlow = (function () {
  "use strict";

  /* ---------------- Seed (default) data ---------------- */
  const AVATAR_COLORS = ["#4338CA", "#0891B2", "#B45309", "#16A34A", "#DC2626", "#7C3AED", "#0E7490", "#C2410C"];
  const STATUSES = ["New", "Contacted", "Qualified", "Unqualified", "Converted"];
  const SOURCES = ["Website", "Referral", "Cold Call", "Social Media", "Webinar", "Trade Show", "Email Campaign"];
  const OWNERS = ["Aisha Khan", "Rohit Verma", "Maria Lopez", "Daniel Cho", "Priya Nair"];

  const SEED_LEADS = [
    { id: 1, name: "Sandeep Mehta", company: "Orbit Logistics", email: "sandeep@orbitlog.com", phone: "+91 98200 11223", status: "New", source: "Website", owner: "Aisha Khan", score: "Hot", lastActivity: "2 hrs ago" },
    { id: 2, name: "Lena Fischer", company: "Nordhaus GmbH", email: "lena.f@nordhaus.de", phone: "+49 151 2233 9981", status: "Qualified", source: "Referral", owner: "Rohit Verma", score: "Hot", lastActivity: "Yesterday" },
    { id: 3, name: "Carlos Mendez", company: "Vertex Retail", email: "carlos@vertexretail.com", phone: "+1 312 555 0192", status: "Contacted", source: "Webinar", owner: "Maria Lopez", score: "Warm", lastActivity: "3 days ago" },
    { id: 4, name: "Aanya Sharma", company: "Bloom & Co", email: "aanya.s@bloomco.in", phone: "+91 90112 44556", status: "Converted", source: "Email Campaign", owner: "Aisha Khan", score: "Hot", lastActivity: "1 week ago" },
    { id: 5, name: "Tom Whitfield", company: "Granite Systems", email: "tom.w@granitesys.co.uk", phone: "+44 7700 900321", status: "Unqualified", source: "Cold Call", owner: "Daniel Cho", score: "Cold", lastActivity: "2 weeks ago" },
    { id: 6, name: "Yuki Tanaka", company: "Sakura Robotics", email: "yuki.t@sakurarobo.jp", phone: "+81 90 1234 5678", status: "New", source: "Trade Show", owner: "Priya Nair", score: "Warm", lastActivity: "5 hrs ago" },
    { id: 7, name: "Fatima Al-Sayed", company: "Dune Energy", email: "fatima@duneenergy.ae", phone: "+971 50 123 4567", status: "Contacted", source: "Website", owner: "Rohit Verma", score: "Warm", lastActivity: "Yesterday" },
    { id: 8, name: "Marco Rossi", company: "Aurora Foods", email: "marco.rossi@aurorafoods.it", phone: "+39 320 765 4321", status: "Qualified", source: "Referral", owner: "Maria Lopez", score: "Hot", lastActivity: "4 hrs ago" },
    { id: 9, name: "Emily Park", company: "Brightline Media", email: "emily.park@brightline.co", phone: "+1 415 555 0143", status: "New", source: "Social Media", owner: "Daniel Cho", score: "Cold", lastActivity: "6 days ago" },
    { id: 10, name: "Ravi Iyer", company: "Coastal Pharma", email: "ravi.iyer@coastalpharma.in", phone: "+91 98450 33221", status: "Converted", source: "Webinar", owner: "Aisha Khan", score: "Hot", lastActivity: "2 days ago" },
    { id: 11, name: "Sophie Laurent", company: "Atelier Noir", email: "sophie@ateliernoir.fr", phone: "+33 6 12 34 56 78", status: "Contacted", source: "Email Campaign", owner: "Priya Nair", score: "Warm", lastActivity: "8 hrs ago" },
    { id: 12, name: "Ben Carter", company: "Ironclad Builders", email: "ben.carter@ironcladbuild.com", phone: "+1 702 555 0110", status: "Unqualified", source: "Cold Call", owner: "Rohit Verma", score: "Cold", lastActivity: "3 weeks ago" },
    { id: 13, name: "Wei Zhang", company: "Pinnacle Cloud", email: "wei.zhang@pinnaclecloud.cn", phone: "+86 138 0013 8000", status: "Qualified", source: "Website", owner: "Maria Lopez", score: "Hot", lastActivity: "1 hr ago" },
    { id: 14, name: "Grace Adeyemi", company: "Lagos Fintech", email: "grace@lagosfintech.ng", phone: "+234 802 345 6789", status: "New", source: "Trade Show", owner: "Daniel Cho", score: "Warm", lastActivity: "Yesterday" },
    { id: 15, name: "Niklas Berg", company: "Polarstern AB", email: "niklas.berg@polarstern.se", phone: "+46 70 123 45 67", status: "Contacted", source: "Referral", owner: "Aisha Khan", score: "Cold", lastActivity: "5 days ago" },
    { id: 16, name: "Priyanka Das", company: "Coral Health", email: "priyanka.das@coralhealth.in", phone: "+91 99887 65432", status: "Converted", source: "Webinar", owner: "Priya Nair", score: "Hot", lastActivity: "9 hrs ago" },
  ];

  const SEED_REMINDERS = [
    { id: 1, title: "Follow up on pricing proposal", lead: "Sandeep Mehta", date: "2026-06-30", time: "11:00 AM", priority: "High", completed: false, group: "Today" },
    { id: 2, title: "Send onboarding deck", lead: "Lena Fischer", date: "2026-06-30", time: "3:30 PM", priority: "Medium", completed: false, group: "Today" },
    { id: 3, title: "Call to confirm demo slot", lead: "Yuki Tanaka", date: "2026-06-29", time: "10:00 AM", priority: "High", completed: false, group: "Overdue" },
    { id: 4, title: "Check contract redlines", lead: "Marco Rossi", date: "2026-06-28", time: "5:00 PM", priority: "High", completed: false, group: "Overdue" },
    { id: 5, title: "Quarterly check-in call", lead: "Aanya Sharma", date: "2026-07-02", time: "1:00 PM", priority: "Low", completed: false, group: "Upcoming" },
    { id: 6, title: "Share case study", lead: "Fatima Al-Sayed", date: "2026-07-03", time: "11:30 AM", priority: "Medium", completed: false, group: "Upcoming" },
    { id: 7, title: "Renewal discussion", lead: "Ravi Iyer", date: "2026-07-05", time: "4:00 PM", priority: "Medium", completed: false, group: "Upcoming" },
    { id: 8, title: "Intro call", lead: "Wei Zhang", date: "2026-06-30", time: "9:00 AM", priority: "Medium", completed: true, group: "Today" },
  ];

  const timelineSamples = [
    { icon: "bi-envelope", color: "#4338CA", title: "Email sent: Welcome & next steps", time: "Today, 10:14 AM", desc: "Automated welcome sequence email #1 delivered and opened." },
    { icon: "bi-telephone", color: "#16A34A", title: "Call logged — 12 min", time: "Yesterday, 4:02 PM", desc: "Discussed budget range and rough timeline for Q3 rollout." },
    { icon: "bi-chat-dots", color: "#D97706", title: "Note added", time: "2 days ago", desc: "Decision maker is the VP of Ops; needs security review before signing." },
    { icon: "bi-person-check", color: "#0891B2", title: "Lead created", time: "1 week ago", desc: "Captured via website pricing page form." },
  ];

  /* ---------------- Persistence (localStorage) ---------------- */
  const LS_LEADS = "leadflow_leads_v1";
  const LS_REMINDERS = "leadflow_reminders_v1";

  function getLeads() {
    try {
      const raw = localStorage.getItem(LS_LEADS);
      if (raw) return JSON.parse(raw);
    } catch (e) { /* ignore */ }
    const seeded = JSON.parse(JSON.stringify(SEED_LEADS));
    saveLeads(seeded);
    return seeded;
  }
  function saveLeads(leads) {
    try { localStorage.setItem(LS_LEADS, JSON.stringify(leads)); } catch (e) { /* ignore */ }
  }

  function getReminders() {
    try {
      const raw = localStorage.getItem(LS_REMINDERS);
      if (raw) return JSON.parse(raw);
    } catch (e) { /* ignore */ }
    const seeded = JSON.parse(JSON.stringify(SEED_REMINDERS));
    saveReminders(seeded);
    return seeded;
  }
  function saveReminders(reminders) {
    try { localStorage.setItem(LS_REMINDERS, JSON.stringify(reminders)); } catch (e) { /* ignore */ }
  }

  function resetData() {
    saveLeads(JSON.parse(JSON.stringify(SEED_LEADS)));
    saveReminders(JSON.parse(JSON.stringify(SEED_REMINDERS)));
  }

  /* ---------------- Display helpers ---------------- */
  const colorFor = (str) => {
    let h = 0;
    for (let i = 0; i < str.length; i++) h = str.charCodeAt(i) + ((h << 5) - h);
    return AVATAR_COLORS[Math.abs(h) % AVATAR_COLORS.length];
  };
  const initials = (name) => name.split(" ").map(p => p[0]).slice(0, 2).join("").toUpperCase();

  function avatarHtml(name, size) {
    const cls = size === "lg" ? "avatar avatar-lg" : size === "sm" ? "avatar avatar-sm" : "avatar";
    return `<div class="${cls}" style="background:${colorFor(name)}">${initials(name)}</div>`;
  }

  function statusBadge(status) {
    const cls = "status-" + status.toLowerCase();
    return `<span class="badge-status ${cls}">${status}</span>`;
  }

  function scorePill(score) {
    const cls = "score-" + score.toLowerCase();
    return `<span class="score-pill ${cls}"><span class="score-dot"></span>${score}</span>`;
  }

  function priorityBadge(p) {
    return `<span class="badge-priority priority-${p.toLowerCase()}">${p}</span>`;
  }

  function formatDate(iso) {
    const d = new Date(iso + "T00:00:00");
    return d.toLocaleDateString("en-US", { month: "short", day: "numeric" });
  }

  function toast(message, variant) {
    variant = variant || "primary";
    const wrap = document.getElementById("toastStack");
    if (!wrap) return;
    const id = "t" + Date.now();
    const el = document.createElement("div");
    el.className = "toast align-items-center text-bg-" + variant + " border-0";
    el.id = id;
    el.setAttribute("role", "alert");
    el.innerHTML = `<div class="d-flex"><div class="toast-body">${message}</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
    wrap.appendChild(el);
    const t = new bootstrap.Toast(el, { delay: 3000 });
    t.show();
    el.addEventListener("hidden.bs.toast", () => el.remove());
  }

  /* ---------------- Sidebar / topbar / nav (shared across all pages) ---------------- */
  function closeSidebarMobile() {
    document.getElementById("sidebar")?.classList.remove("show");
    document.getElementById("sidebarBackdrop")?.classList.remove("show");
  }

  function initSidebarToggle() {
    document.getElementById("hamburgerBtn")?.addEventListener("click", () => {
      document.getElementById("sidebar")?.classList.add("show");
      document.getElementById("sidebarBackdrop")?.classList.add("show");
    });
    document.getElementById("sidebarBackdrop")?.addEventListener("click", closeSidebarMobile);
    // Close sidebar automatically when a nav link is tapped (mobile)
    document.querySelectorAll(".nav-link[data-page]").forEach(link => {
      link.addEventListener("click", closeSidebarMobile);
    });
  }

  function setActiveNav() {
    const page = document.body.dataset.page;
    if (!page) return;
    document.querySelectorAll(".nav-link[data-page]").forEach(link => {
      link.classList.toggle("active", link.dataset.page === page);
    });
  }

  function syncNavBadges() {
    const leads = getLeads();
    const reminders = getReminders();
    const navLead = document.getElementById("navLeadCount");
    const navRem = document.getElementById("navReminderCount");
    if (navLead) navLead.textContent = leads.length;
    if (navRem) navRem.textContent = reminders.filter(r => !r.completed).length;
  }

  function initTooltips() {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
  }

  /* ---------------- Boilerplate run on every page ---------------- */
  document.addEventListener("DOMContentLoaded", () => {
    initSidebarToggle();
    setActiveNav();
    syncNavBadges();
    initTooltips();
  });

  return {
    STATUSES, SOURCES, OWNERS, timelineSamples,
    getLeads, saveLeads, getReminders, saveReminders, resetData,
    colorFor, initials, avatarHtml, statusBadge, scorePill, priorityBadge, formatDate, toast,
    closeSidebarMobile, syncNavBadges, setActiveNav,
  };
})();
