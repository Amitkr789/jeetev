<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>@yield('title', 'LeadFlow CRM')</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
<link href="{{ asset('assets/css/style.css') }}" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-page="dashboard">
  <div class="app-shell">

@include('partials.sidebar')
@yield('content')
@include('partials.models')
</div>
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastStack" style="z-index:1080"></div>
<script>
  $.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
});
  $('#logoutBtn').on('click', function (e) {
  e.preventDefault();
  $.post($('#logoutForm').attr('action'), function (res) {
    window.location.href = res.redirect;
  });
});
  /* ==========================================================================
   Global reminder alarm + notification bell.
   Included on EVERY page (via the shared topbar/sidebar partial), so a due
   reminder pops up and shows in the bell no matter where the user is.

   Depends on window.GLOBAL_REMINDER_CONFIG, set in the layout:
     {
       csrfToken: '...',
       routes: {
         reminderCheck: '...',            // GET  -> { success, due: [...] }
         reminderStatus: '.../__ID__/status', // POST -> { status }
         leadsPage: '/admin/leads',
       }
     }

   If the current page is the Leads page itself, lead.blade.php exposes
   window.openLeadDetailById(id) so "View lead" opens the offcanvas in
   place instead of doing a full navigation. Everywhere else we navigate
   to `${leadsPage}?lead=ID`, and lead.blade.php auto-opens that lead on
   load.
   ========================================================================== */

/* ==========================================================================
   Global reminder alarm + notification bell.
   Included on EVERY page (via the shared topbar/sidebar partial), so a due
   reminder pops up and shows in the bell no matter where the user is.

   Depends on window.GLOBAL_REMINDER_CONFIG, set in the layout:
     {
       csrfToken: '...',
       routes: {
         reminderCheck: '...',            // GET  -> { success, due, upcoming, server_time }
         reminderStatus: '.../__ID__/status', // POST -> { status }
         leadsPage: '/admin/leads',
       }
     }

   If the current page is the Leads page itself, lead.blade.php exposes
   window.openLeadDetailById(id) so "View lead" opens the offcanvas in
   place instead of doing a full navigation. Everywhere else we navigate
   to `${leadsPage}?lead=ID`, and lead.blade.php auto-opens that lead on
   load.

   Two things this file guarantees:
   1. A reminder you've silenced, viewed, or closed does NOT ring again.
      "Silenced" is permanent (server-side status change). "Viewed"/"Closed"
      are session-only, but that session now survives page navigation
      (sessionStorage) — previously it lived in a plain JS variable, which
      reset on every page load, so the alarm would immediately re-fire the
      moment you navigated away after viewing/closing it.
   2. A reminder rings the moment it becomes due, not up to 30s late. The
      backend also returns "upcoming" scheduled reminders with an exact
      due_at timestamp; this script sets a precise timer for each one
      (clock-skew corrected against server_time) instead of waiting for
      the next poll tick.
   ========================================================================== */

(function () {
  "use strict";

  const CFG = window.GLOBAL_REMINDER_CONFIG;
  if (!CFG || !CFG.routes || !CFG.routes.reminderCheck) return; // not configured on this page

  const POLL_MS = 30000;
  const MAX_SCHEDULE_AHEAD_MS = 24 * 60 * 60 * 1000; // don't hold timers more than 24h out; the next poll re-schedules as they get closer

  const DISMISS_KEY = "lf_dismissed_reminders";
  function loadDismissed() {
    try {
      const raw = sessionStorage.getItem(DISMISS_KEY);
      return new Set(raw ? JSON.parse(raw) : []);
    } catch (err) { return new Set(); }
  }
  function saveDismissed() {
    try { sessionStorage.setItem(DISMISS_KEY, JSON.stringify(Array.from(dismissedThisSession))); } catch (err) { /* storage unavailable — falls back to in-memory only */ }
  }
  function markDismissed(id) {
    dismissedThisSession.add(id);
    saveDismissed();
  }

  const dismissedThisSession = loadDismissed(); // reminder ids the user has "Close"d or silenced — persists for this browser tab
  let dueReminders = [];        // everything currently due (drives the bell)
  let currentAlarmReminders = []; // subset shown in the popup right now
  let alarmInterval = null;
  let audioCtx = null;
  let clockOffsetMs = 0;        // server_time - client now, so scheduled timers fire on the dot even with client clock drift
  let scheduledTimers = [];     // [{ id, timer }] for "upcoming" reminders

  /* ---------------- helpers ---------------- */

  function csrfHeaders(extra) {
    return Object.assign({ "X-CSRF-TOKEN": CFG.csrfToken, "X-Requested-With": "XMLHttpRequest" }, extra || {});
  }

  async function apiFetch(url, options) {
    const res = await fetch(url, Object.assign({ headers: csrfHeaders({ "Accept": "application/json" }) }, options));
    return res.json();
  }

  function fmtDate(d) {
    if (!d) return "";
    const dt = new Date(d);
    if (isNaN(dt)) return d;
    return dt.toLocaleDateString(undefined, { day: "numeric", month: "short", year: "numeric" });
  }

  function fmtTime(t) {
    if (!t) return "";
    const [h, m] = t.split(":");
    const hour = parseInt(h, 10);
    const ampm = hour >= 12 ? "PM" : "AM";
    const h12 = ((hour + 11) % 12) + 1;
    return `${h12}:${m} ${ampm}`;
  }

  function goToLead(leadId) {
    if (typeof window.openLeadDetailById === "function") {
      window.openLeadDetailById(leadId); // already on the leads page
    } else {
      window.location.href = `${CFG.routes.leadsPage}?lead=${leadId}`;
    }
  }

  /* ---------------- sound ---------------- */

  function beep() {
    try {
      audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
      const osc = audioCtx.createOscillator();
      const gain = audioCtx.createGain();
      osc.type = "square";
      osc.frequency.value = 880;
      gain.gain.setValueAtTime(0.25, audioCtx.currentTime);
      osc.connect(gain).connect(audioCtx.destination);
      osc.start();
      osc.stop(audioCtx.currentTime + 0.28);
    } catch (err) { /* audio not available — modal/bell still work */ }
  }

  function startAlarmSound() {
    stopAlarmSound();
    beep();
    alarmInterval = setInterval(beep, 500);
  }

  function stopAlarmSound() {
    if (alarmInterval) clearInterval(alarmInterval);
    alarmInterval = null;
  }

  /* ---------------- alarm modal (injected once, works on any page) ---------------- */

  function ensureAlarmModal() {
    if (document.getElementById("globalAlarmModal")) return;
    const wrap = document.createElement("div");
    wrap.innerHTML = `
      <div class="modal fade" id="globalAlarmModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-content-compact">
          <div class="modal-content modal-content-danger">
            <div class="modal-header">
              <h5 class="modal-title"><i class="bi bi-bell-fill me-2"></i>Reminder due</h5>
            </div>
            <div class="modal-body" id="globalAlarmModalBody"></div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary w-100" id="globalAlarmDismissBtn">Close</button>
            </div>
          </div>
        </div>
      </div>`;
    document.body.appendChild(wrap.firstElementChild);
    document.getElementById("globalAlarmDismissBtn").addEventListener("click", () => {
      currentAlarmReminders.forEach(r => markDismissed(r.id));
      currentAlarmReminders = [];
      closeAlarmModal();
    });
  }

  async function silenceReminder(id) {
    await apiFetch(CFG.routes.reminderStatus.replace("__ID__", id), {
      method: "POST",
      headers: csrfHeaders({ "Content-Type": "application/json" }),
      body: JSON.stringify({ status: "silent" }),
    });
    markDismissed(id);
    currentAlarmReminders = currentAlarmReminders.filter(r => r.id !== id);
    dueReminders = dueReminders.filter(r => r.id !== id);
    scheduledTimers = scheduledTimers.filter(t => {
      if (t.id === id) { clearTimeout(t.timer); return false; }
      return true;
    });
    renderBell();
  }

  function renderAlarmBody() {
    const body = document.getElementById("globalAlarmModalBody");
    body.innerHTML = currentAlarmReminders.map(r => `
      <div class="alarm-card">
        <div class="alarm-lead-name">${r.lead ? r.lead.customer_name : "Lead"}</div>
        <div class="alarm-note">${r.note}</div>
        <div class="alarm-actions">
          <button class="btn btn-primary" data-alarm-view="${r.lead ? r.lead.id : ""}" data-alarm-reminder="${r.id}"><i class="bi bi-eye me-1"></i>View lead</button>
          <button class="btn" style="background:#EEF1F6;color:var(--text-muted)" data-alarm-silence="${r.id}"><i class="bi bi-volume-mute me-1"></i>Silence</button>
        </div>
      </div>`).join("");

    body.querySelectorAll("[data-alarm-view]").forEach(btn => {
      btn.addEventListener("click", () => {
        const leadId = btn.dataset.alarmView;
        const reminderId = parseInt(btn.dataset.alarmReminder, 10);
        markDismissed(reminderId); // stop nagging while they're looking at it — persists across navigation
        currentAlarmReminders = currentAlarmReminders.filter(r => r.id !== reminderId);
        closeAlarmModal();
        if (leadId) goToLead(parseInt(leadId, 10));
      });
    });
    body.querySelectorAll("[data-alarm-silence]").forEach(btn => {
      btn.addEventListener("click", async () => {
        await silenceReminder(parseInt(btn.dataset.alarmSilence, 10));
        if (currentAlarmReminders.length === 0) closeAlarmModal();
        else renderAlarmBody();
      });
    });
  }

  function closeAlarmModal() {
    stopAlarmSound();
    const el = document.getElementById("globalAlarmModal");
    if (el && window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(el).hide();
  }

  function triggerAlarm(newlyDue) {
    ensureAlarmModal();
    currentAlarmReminders = newlyDue;
    renderAlarmBody();
    startAlarmSound();
    window.bootstrap.Modal.getOrCreateInstance(document.getElementById("globalAlarmModal")).show();

    if (window.Notification && Notification.permission === "granted") {
      newlyDue.forEach(r => new Notification("Reminder due", { body: `${r.lead ? r.lead.customer_name : "Lead"}: ${r.note}` }));
    }
  }

  /* ---------------- notification bell dropdown ---------------- */

  function renderBell() {
    const badge = document.getElementById("notifBellCount");
    const list = document.getElementById("notifBellList");
    if (!badge || !list) return; // topbar not on this page yet

    const count = dueReminders.length;
    badge.textContent = count > 9 ? "9+" : String(count);
    badge.style.display = count > 0 ? "flex" : "none";

    if (!count) {
      list.innerHTML = `<div class="notif-empty">No reminders due right now</div>`;
      return;
    }

    list.innerHTML = dueReminders.map(r => `
      <div class="notif-item">
        <div class="notif-name">${r.lead ? r.lead.customer_name : "Lead"}</div>
        <div class="notif-note">${r.note}</div>
        <div class="notif-meta">${fmtDate(r.reminder_date)}${r.reminder_time ? " · " + fmtTime(r.reminder_time) : ""}</div>
        <div class="notif-actions">
          <button class="btn btn-primary" data-notif-view="${r.lead ? r.lead.id : ""}">View lead</button>
          <button class="btn btn-outline-secondary" data-notif-silence="${r.id}">Silence</button>
        </div>
      </div>`).join("");

    list.querySelectorAll("[data-notif-view]").forEach(btn => {
      btn.addEventListener("click", () => {
        const leadId = btn.dataset.notifView;
        const bellBtn = document.getElementById("notifBellBtn");
        if (bellBtn && window.bootstrap) window.bootstrap.Dropdown.getOrCreateInstance(bellBtn).hide();
        if (leadId) goToLead(parseInt(leadId, 10));
      });
    });
    list.querySelectorAll("[data-notif-silence]").forEach(btn => {
      btn.addEventListener("click", async (e) => {
        e.stopPropagation();
        await silenceReminder(parseInt(btn.dataset.notifSilence, 10));
      });
    });
  }

  /* ---------------- precise ring timing ----------------
     The backend also returns "upcoming" (not-yet-due) reminders with an
     exact due_at timestamp. Instead of waiting for the next 30s poll to
     notice a reminder went from "scheduled for later" to "due", we set a
     timer for the exact moment — the timer just re-runs poll(), so the
     usual due/dismissed checks (including anything another tab or admin
     did in the meantime) still apply.
  */

  function clearScheduledTimers() {
    scheduledTimers.forEach(t => clearTimeout(t.timer));
    scheduledTimers = [];
  }

  function scheduleUpcoming(upcoming) {
    clearScheduledTimers();
    (upcoming || []).forEach(r => {
      if (dismissedThisSession.has(r.id) || !r.due_at) return;
      const dueAtMs = new Date(r.due_at).getTime();
      const msUntilDue = dueAtMs - (Date.now() + clockOffsetMs);
      if (msUntilDue <= 0 || msUntilDue > MAX_SCHEDULE_AHEAD_MS) return;
      const timer = setTimeout(poll, msUntilDue + 250); // small buffer so it's reliably past due server-side
      scheduledTimers.push({ id: r.id, timer });
    });
  }

  /* ---------------- polling ---------------- */

  async function poll() {
    try {
      const res = await apiFetch(CFG.routes.reminderCheck);
      if (!res.success) return;
      if (res.server_time) clockOffsetMs = new Date(res.server_time).getTime() - Date.now();
      dueReminders = res.due || [];
      renderBell();
      scheduleUpcoming(res.upcoming);
      const toAlert = dueReminders.filter(r => !dismissedThisSession.has(r.id));
      if (toAlert.length) triggerAlarm(toAlert);
    } catch (err) { /* silent — retried next tick */ }
  }

  /* ---------------- boot ---------------- */

  document.addEventListener("DOMContentLoaded", () => {
    ensureAlarmModal();
    if (window.Notification && Notification.permission === "default") {
      Notification.requestPermission();
    }
    poll();
    setInterval(poll, POLL_MS);
  });

  // Let any page (e.g. after silencing a reminder in the lead offcanvas)
  // ask the bell to refresh immediately instead of waiting for the next poll.
  window.GlobalReminders = { refresh: poll };
})();
(function () {
  "use strict";
  const CFG = window.TASK_NOTIF_CONFIG;
  if (!CFG) return;

  function csrfHeaders(extra) {
    return Object.assign({ "X-CSRF-TOKEN": CFG.csrfToken, "X-Requested-With": "XMLHttpRequest", "Accept": "application/json" }, extra || {});
  }

  function renderList(notifications) {
    const list = document.getElementById("taskBellList");
    if (!notifications.length) {
      list.innerHTML = `<div class="notif-empty">No task notifications</div>`;
      return;
    }
    list.innerHTML = notifications.map(n => `
      <div class="notif-item" data-notif-id="${n.id}" data-task-id="${n.task_id || ""}">
        <div class="notif-note">${n.message}</div>
        <div class="notif-meta">${n.created_at}</div>
      </div>`).join("");

    list.querySelectorAll("[data-notif-id]").forEach(el => {
      el.addEventListener("click", async () => {
        const notifId = el.dataset.notifId;
        const taskId = el.dataset.taskId;
        await fetch(CFG.routes.read, { method: "POST", headers: csrfHeaders({ "Content-Type": "application/json" }), body: JSON.stringify({ id: notifId }) });
        poll();
        if (taskId) {
          if (typeof window.openTaskDetailById === "function") {
            window.openTaskDetailById(parseInt(taskId, 10));
          } else {
            window.location.href = `${CFG.routes.tasksPage}?task=${taskId}`;
          }
        }
      });
    });
  }

  function updateBadge(count) {
    const badge = document.getElementById("taskBellCount");
    if (!badge) return;
    if (count > 0) { badge.textContent = count > 9 ? "9+" : count; badge.style.display = "flex"; }
    else { badge.style.display = "none"; }
  }

  async function poll() {
    try {
      const res = await fetch(CFG.routes.check, { headers: csrfHeaders() });
      const data = await res.json();
      if (!data.success) return;
      updateBadge(data.count);
      renderList(data.notifications || []);
    } catch (e) { /* silent — network hiccup, next poll will retry */ }
  }

  document.addEventListener("DOMContentLoaded", () => {
    poll();
    setInterval(poll, 3000);
  });
})();
document.addEventListener("DOMContentLoaded", () => {

    const search = document.getElementById("sidebarMenuSearch");
    const nav = document.querySelector(".sidebar-nav");

    if (!search || !nav) return;

    const links = [...nav.querySelectorAll(".nav-link")];
    const labels = [...nav.querySelectorAll(".sidebar-section-label")];

    function filterMenu() {

        const keyword = search.value.trim().toLowerCase();

        // Hide all section labels initially
        labels.forEach(label => label.style.display = "none");

        links.forEach(link => {

            const text = link.textContent.toLowerCase();

            if (keyword === "" || text.includes(keyword)) {
                link.style.display = "";
            } else {
                link.style.display = "none";
            }
        });

        // Show section label only if it has visible menu below it
        labels.forEach(label => {

            let next = label.nextElementSibling;
            let visible = false;

            while (next && !next.classList.contains("sidebar-section-label")) {

                if (next.classList.contains("nav-link") && next.style.display !== "none") {
                    visible = true;
                    break;
                }

                next = next.nextElementSibling;
            }

            label.style.display = visible ? "" : "none";
        });

        // If search empty, show all labels
        if (keyword === "") {
            labels.forEach(label => label.style.display = "");
        }
    }

    search.addEventListener("input", filterMenu);

});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="{{ asset('assets/js/dashboard.js') }}"></script>
<script src="{{ asset('assets/js/shared.js') }}"></script>
</body>
</html>
