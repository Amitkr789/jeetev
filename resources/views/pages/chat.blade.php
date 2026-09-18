@extends('layouts.app')
@section('title', 'Live Chat | Dalal Adda')

@section('content')

<style>
  /* ---- Page header AI switch ---- */
  .ai-master-switch { display:flex; align-items:center; gap:10px; background:#FBFBFE; border:1.5px solid var(--border); border-radius:12px; padding:8px 14px; }
  .ai-master-switch .dot { width:9px; height:9px; border-radius:50%; background:#C7CAD9; }
  .ai-master-switch.on .dot { background: var(--success); box-shadow:0 0 0 3px var(--success-bg); }
  .ai-master-switch .lbl { font-size:12.5px; font-weight:700; color:var(--text-muted); }
  .ai-master-switch.on .lbl { color: var(--success); }

  /* ---- Stat row (reuses stat-card pattern) ---- */
  .chat-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:14px; }
  @media (max-width:900px){ .chat-stats{ grid-template-columns:repeat(2,1fr);} }

  /* ---- Shell ---- */
  .chat-shell { display:flex; height: calc(100vh - 300px); min-height:520px; background:#fff; border:1px solid var(--border); border-radius:14px; overflow:hidden; box-shadow:var(--shadow-lg); }

  /* ---- Inbox column ---- */
  .chat-inbox { width:330px; flex-shrink:0; border-right:1px solid var(--border); display:flex; flex-direction:column; background:#FBFBFE; }
  .chat-inbox-search { padding:12px; border-bottom:1px solid var(--border); }
  .chat-inbox-tabs { display:flex; gap:4px; padding:0 10px 10px; flex-wrap:wrap; }
  .chat-inbox-tabs button { border:none; background:#EEF0F8; color:var(--text-muted); font-size:11px; font-weight:700; padding:5px 10px; border-radius:20px; }
  .chat-inbox-tabs button.active { background:var(--primary); color:#fff; }
  .chat-inbox-list { flex:1; overflow-y:auto; }
  .chat-row { display:flex; gap:10px; padding:11px 14px; border-bottom:1px solid #F0F1F8; cursor:pointer; position:relative; }
  .chat-row:hover { background:#F3F4FB; }
  .chat-row.active { background: var(--primary-50); }
  .chat-avatar { width:38px; height:38px; border-radius:50%; background:var(--primary); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:14px; flex-shrink:0; }
  .chat-row-body { min-width:0; flex:1; }
  .chat-row-top { display:flex; justify-content:space-between; gap:6px; }
  .chat-row-name { font-size:13px; font-weight:700; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .chat-row-time { font-size:10.5px; color:var(--text-muted); flex-shrink:0; }
  .chat-row-preview { font-size:12px; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:200px; }
  .chat-row-meta { display:flex; align-items:center; gap:5px; margin-top:4px; }
  .chat-unread { background:var(--primary); color:#fff; font-size:10px; font-weight:800; min-width:18px; height:18px; border-radius:9px; display:flex; align-items:center; justify-content:center; padding:0 5px; }
  .chat-empty-inbox { padding:40px 20px; text-align:center; color:var(--text-muted); font-size:12.5px; }

  .pill-status { font-size:9.5px; font-weight:800; text-transform:uppercase; letter-spacing:.3px; padding:2px 7px; border-radius:20px; }
  .pill-status.ai { background:var(--success-bg); color:var(--success); }
  .pill-status.human { background:var(--info-bg); color:var(--info); }
  .pill-status.escalated { background:var(--danger-bg); color:var(--danger); }
  .pill-status.paused { background:#EEF0F8; color:var(--text-muted); }

  /* ---- Thread column ---- */
  .chat-thread-col { flex:1; display:flex; flex-direction:column; min-width:0; }
  .chat-thread-header { display:flex; align-items:center; gap:12px; padding:12px 18px; border-bottom:1px solid var(--border); flex-wrap:wrap; }
  .chat-thread-header .thn { font-size:14.5px; font-weight:700; color:var(--text); }
  .chat-thread-header .thp { font-size:11.5px; color:var(--text-muted); }
  .chat-thread-actions { margin-left:auto; display:flex; gap:6px; flex-wrap:wrap; align-items:center; }
  .lock-flag { font-size:11px; font-weight:700; color:var(--warning); background:var(--warning-bg); padding:4px 10px; border-radius:20px; display:flex; align-items:center; gap:5px; }

  .chat-thread-body { flex:1; overflow-y:auto; padding:18px; background:#F7F8FC; display:flex; flex-direction:column; gap:10px; }
  .chat-placeholder { margin:auto; text-align:center; color:var(--text-muted); }
  .chat-placeholder i { font-size:34px; opacity:.35; }

  .bubble-row { display:flex; }
  .bubble-row.in { justify-content:flex-start; }
  .bubble-row.out { justify-content:flex-end; }
  .bubble-row.sys { justify-content:center; }
  .bubble { max-width:62%; padding:9px 13px; border-radius:14px; font-size:13px; line-height:1.45; position:relative; }
  .bubble.in { background:#fff; border:1px solid var(--border); border-bottom-left-radius:4px; }
  .bubble.out-ai { background:var(--primary-50); border:1px solid #DCE1FB; border-bottom-right-radius:4px; }
  .bubble.out-admin { background:var(--primary); color:#fff; border-bottom-right-radius:4px; }
  .bubble.sys { background:transparent; color:var(--text-muted); font-size:11px; text-align:center; max-width:100%; padding:2px 8px; }
  .bubble-meta { display:flex; gap:6px; align-items:center; margin-top:5px; font-size:10px; opacity:.75; }
  .bubble-tag { font-size:9px; font-weight:800; text-transform:uppercase; padding:1px 6px; border-radius:20px; background:rgba(0,0,0,.06); }
  .bubble.out-admin .bubble-tag { background:rgba(255,255,255,.2); }
  .confidence-chip { font-size:9.5px; font-weight:700; padding:1px 6px; border-radius:20px; }
  .confidence-chip.hi { background:var(--success-bg); color:var(--success); }
  .confidence-chip.md { background:var(--warning-bg); color:var(--warning); }
  .confidence-chip.lo { background:var(--danger-bg); color:var(--danger); }

  .ai-active-banner { display:flex; align-items:center; gap:10px; padding:10px 18px; background:var(--info-bg); color:var(--info); font-size:12.5px; font-weight:600; }
  .ai-active-banner button { margin-left:auto; }

  .chat-composer { display:flex; gap:10px; padding:12px 16px; border-top:1px solid var(--border); align-items:flex-end; }
  .chat-composer textarea { flex:1; resize:none; border:1.5px solid var(--border); border-radius:12px; padding:10px 13px; font-size:13px; max-height:110px; }
  .chat-composer button { flex-shrink:0; }

  /* ---- Notes side panel ---- */
  .chat-notes-col { width:280px; flex-shrink:0; border-left:1px solid var(--border); display:flex; flex-direction:column; }
  .chat-notes-col.d-none { display:none !important; }
  .chat-notes-head { padding:12px 14px; border-bottom:1px solid var(--border); font-weight:700; font-size:12.5px; display:flex; justify-content:space-between; align-items:center; }
  .chat-notes-list { flex:1; overflow-y:auto; padding:12px 14px; display:flex; flex-direction:column; gap:10px; }
  .note-card { background:#FBFBFE; border:1px solid var(--border); border-radius:10px; padding:9px 11px; font-size:12px; }
  .note-card .n-meta { font-size:10.5px; color:var(--text-muted); margin-bottom:3px; }
  .chat-notes-add { padding:10px 14px; border-top:1px solid var(--border); }
  .chat-notes-add textarea { width:100%; border:1.5px solid var(--border); border-radius:10px; padding:8px 10px; font-size:12px; resize:none; }

  .dropdown-menu-scroll { max-height:220px; overflow-y:auto; }
  .composer-preview { padding:0 16px 8px; }
.attach-chip { display:flex; align-items:center; gap:8px; background:#F0F1F8; border:1px solid var(--border); border-radius:10px; padding:6px 10px; font-size:12px; max-width:280px; }
.attach-chip img.thumb { width:32px; height:32px; border-radius:6px; object-fit:cover; }
.attach-chip .name { flex:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.attach-chip .remove { cursor:pointer; color:var(--danger); font-weight:700; }
.upload-progress-wrap { height:4px; background:#E4E6F3; border-radius:2px; margin-top:6px; overflow:hidden; }
.upload-progress-bar { height:100%; background:var(--primary); width:0%; transition:width .15s; }

.bubble-media img.chat-img { max-width:220px; border-radius:10px; display:block; cursor:zoom-in; }
.bubble-media audio { width:230px; }
.bubble-media video { max-width:220px; border-radius:10px; }
.bubble-file { display:flex; align-items:center; gap:10px; background:rgba(0,0,0,.03); border-radius:10px; padding:8px 10px; text-decoration:none; color:inherit; }
.bubble.out-admin .bubble-file { background:rgba(255,255,255,.15); color:#fff; }
.bubble-file i { font-size:22px; }
.bubble-file .fname { font-size:12.5px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:150px; }
.bubble-file .fsize { font-size:10.5px; opacity:.7; }

.lightbox-overlay { position:fixed; inset:0; background:rgba(0,0,0,.85); z-index:3000; display:flex; align-items:center; justify-content:center; }
.lightbox-overlay img { max-width:90vw; max-height:90vh; border-radius:8px; }
.lightbox-overlay .lightbox-close { position:absolute; top:20px; right:28px; color:#fff; font-size:28px; cursor:pointer; }
</style>

<div class="page-content">
  <section class="page-section active" id="page-chat">

    <div class="page-header">
      <div>
        <span class="breadcrumb-eyebrow">Support</span>
        <h1>Live Chat</h1>
        <p>WhatsApp conversations — AI-first, with full human takeover.</p>
      </div>
      <div class="page-header-actions d-flex align-items-center gap-2">
        <button class="ai-master-switch" id="aiMasterSwitch" title="Master AI switch — turns AI auto-replies off across every conversation">
          <span class="dot"></span>
          <span class="lbl" id="aiMasterLabel">Loading...</span>
        </button>
        <a href="{{ route('chatbotSettings.index') }}" class="btn btn-outline-secondary"><i class="bi bi-sliders me-1"></i>Chatbot Settings</a>
      </div>
    </div>

    <div class="chat-stats">
      <div class="stat-card">
        <div><div class="stat-label">Conversations</div><div class="stat-value" id="statTotal">0</div></div>
        <div class="stat-icon" style="background:var(--primary-50); color:var(--primary)"><i class="bi bi-chat-dots"></i></div>
      </div>
      <div class="stat-card">
        <div><div class="stat-label">AI Handling</div><div class="stat-value" id="statAi">0</div></div>
        <div class="stat-icon" style="background:var(--success-bg); color:var(--success)"><i class="bi bi-robot"></i></div>
      </div>
      <div class="stat-card">
        <div><div class="stat-label">Human Handling</div><div class="stat-value" id="statHuman">0</div></div>
        <div class="stat-icon" style="background:var(--info-bg); color:var(--info)"><i class="bi bi-person-check"></i></div>
      </div>
      <div class="stat-card">
        <div><div class="stat-label">Escalated</div><div class="stat-value" id="statEscalated">0</div></div>
        <div class="stat-icon" style="background:var(--danger-bg); color:var(--danger)"><i class="bi bi-exclamation-triangle"></i></div>
      </div>
    </div>

    <div class="chat-shell">

      <!-- ===================== INBOX ===================== -->
      <div class="chat-inbox">
        <div class="chat-inbox-search">
          <div class="filter-search">
            <i class="bi bi-search"></i>
            <input type="text" class="form-control" id="inboxSearch" placeholder="Search name or number...">
          </div>
        </div>
        <div class="chat-inbox-tabs" id="inboxTabs">
          <button class="active" data-filter="">All</button>
          <button data-filter="ai">AI</button>
          <button data-filter="human">Human</button>
          <button data-filter="escalated">Escalated</button>
          <button data-filter="unassigned">Unassigned</button>
        </div>
        <div class="chat-inbox-list" id="inboxList"></div>
      </div>

      <!-- ===================== THREAD ===================== -->
      <div class="chat-thread-col">
        <div id="threadEmpty" class="chat-thread-body">
          <div class="chat-placeholder">
            <i class="bi bi-chat-square-text d-block mb-2"></i>
            Select a conversation to view the chat.
          </div>
        </div>

        <div id="threadActive" style="display:none; flex:1; display:flex; flex-direction:column; min-height:0;">
          <div class="chat-thread-header">
            <div class="chat-avatar" id="thAvatar">?</div>
            <div>
              <div class="thn" id="thName">—</div>
              <div class="thp" id="thPhone">—</div>
            </div>
            <span class="pill-status" id="thStatusPill">—</span>
            <span class="lock-flag d-none" id="thLockFlag"><i class="bi bi-lock-fill"></i><span id="thLockText"></span></span>

            <div class="chat-thread-actions">
              <button class="btn btn-sm btn-primary d-none" id="btnTakeover"><i class="bi bi-person-check me-1"></i>Take Over</button>
              <button class="btn btn-sm btn-outline-secondary d-none" id="btnResumeAi"><i class="bi bi-robot me-1"></i>Resume AI</button>

              <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown"><i class="bi bi-person-plus me-1"></i>Assign</button>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-scroll" id="assignMenu"></ul>
              </div>
              <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown"><i class="bi bi-arrow-left-right me-1"></i>Transfer</button>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-scroll" id="transferMenu"></ul>
              </div>

              <button class="btn btn-sm btn-outline-secondary" id="btnNotesToggle"><i class="bi bi-sticky me-1"></i>Notes</button>
              <button class="btn btn-sm btn-outline-secondary" id="btnConvertLead"><i class="bi bi-person-lines-fill me-1"></i>Convert to Lead</button>
            </div>
          </div>

          <div class="ai-active-banner d-none" id="aiPausedBanner">
            <i class="bi bi-info-circle"></i>
            <span id="aiPausedBannerText">A human is currently handling this chat.</span>
          </div>

          <div class="chat-thread-body" id="threadBody"></div>

          <div id="composerWrap">
  <div class="composer-preview d-none" id="attachPreviewWrap"></div>
  <div class="chat-composer">
    <input type="file" id="attachInput" class="d-none" accept="image/*,audio/*,video/mp4,.pdf,.doc,.docx,.xls,.xlsx">
    <button class="btn btn-outline-secondary" id="btnAttach" title="Attach image, audio, or file"><i class="bi bi-paperclip"></i></button>
    <textarea id="composerInput" rows="1" placeholder="Type a reply..."></textarea>
    <button class="btn btn-primary" id="btnSend"><i class="bi bi-send"></i></button>
  </div>
</div>
        </div>
      </div>

      <!-- ===================== NOTES PANEL ===================== -->
      <div class="chat-notes-col d-none" id="notesCol">
        <div class="chat-notes-head">
          <span>Internal Notes</span>
          <button class="btn-close" id="btnNotesClose"></button>
        </div>
        <div class="chat-notes-list" id="notesList"></div>
        <div class="chat-notes-add">
          <textarea id="noteInput" rows="2" placeholder="Add a note (not visible to customer)..."></textarea>
          <button class="btn btn-sm btn-primary mt-2 w-100" id="btnAddNote">Add Note</button>
        </div>
      </div>

    </div>
  </section>
</div>



<script>
  window.CHAT_PAGE_DATA = {
    employees: @json($employees),
    pusherKey: @json($pusherKey),
    pusherCluster: @json($pusherCluster),
    csrfToken: '{{ csrf_token() }}',
    currentAdminId: {{ auth('admin')->id() }},
    routes: {
      data: '{{ route('chat.data') }}',
      employees: '{{ route('chat.employees') }}',
      show: '{{ url('admin/chat') }}/__ID__',
      send: '{{ url('admin/chat') }}/__ID__/send',
      takeover: '{{ url('admin/chat') }}/__ID__/takeover',
      resumeAi: '{{ url('admin/chat') }}/__ID__/resume-ai',
      transfer: '{{ url('admin/chat') }}/__ID__/transfer',
      assign: '{{ url('admin/chat') }}/__ID__/assign',
      aiToggle: '{{ url('admin/chat') }}/__ID__/ai-toggle',
      convertToLead: '{{ url('admin/chat') }}/__ID__/convert-to-lead',
      notesStore: '{{ url('admin/chat') }}/__ID__/notes',
      settingsShow: '{{ route('chatbotSettings.show') }}',
      settingsUpdate: '{{ route('chatbotSettings.update') }}',
      sendMedia: '{{ url('admin/chat') }}/__ID__/send-media',

    }
  };

(function () {
  "use strict";
  const CFG = window.CHAT_PAGE_DATA;
  const CSRF = CFG.csrfToken;

  let conversations = [];
  let activeId = null;
  let activeFilter = "";
  let searchTerm = "";
  let settingsCache = null;
  // Seeded from the initial page render as a fallback only — loadEmployees()
  // below overwrites this with a live fetch right after boot, and again
  // whenever a conversation is opened, so it can never go stale.
  let employeesList = window.CHAT_PAGE_DATA.employees || [];

  /* ---------------- Helpers (same pattern as kb.blade.php) ---------------- */

  function escapeHtml(str) {
    return String(str ?? "").replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  }
  function initials(name) {
    if (!name) return "?";
    return name.trim().split(/\s+/).slice(0, 2).map(w => w[0]?.toUpperCase() || "").join("");
  }
  function fmtTime(d) {
    if (!d) return "";
    const dt = new Date(d.replace(" ", "T"));
    if (isNaN(dt)) return "";
    const now = new Date();
    const sameDay = dt.toDateString() === now.toDateString();
    return sameDay ? dt.toLocaleTimeString(undefined, { hour: "2-digit", minute: "2-digit" })
                   : dt.toLocaleDateString(undefined, { day: "numeric", month: "short" });
  }
  function toast(message, variant) {
    let wrap = document.getElementById("chatToastWrap");
    if (!wrap) {
      wrap = document.createElement("div");
      wrap.id = "chatToastWrap";
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
  if (res.status === 403) { toast("Permission denied (403) — check your role/route middleware.", "danger"); throw new Error("forbidden"); }
  try {
    return await res.json();
  } catch (e) {
    toast(`Unexpected server response (${res.status}) — check console.`, "danger");
    throw e;
  }
}
  function firstError(res) { return res.errors ? Object.values(res.errors)[0][0] : (res.message || "Something went wrong."); }

  /* ---------------- Inbox rendering ---------------- */

  function getFiltered() {
    return conversations.filter(c => {
      const term = searchTerm.toLowerCase();
      const matchesSearch = !term || (c.contact_name || "").toLowerCase().includes(term) || c.wa_phone_number.includes(term);
      let matchesFilter = true;
      if (activeFilter === "ai") matchesFilter = c.display_status === "ai";
      else if (activeFilter === "human") matchesFilter = c.display_status === "human";
      else if (activeFilter === "escalated") matchesFilter = c.display_status === "escalated";
      else if (activeFilter === "unassigned") matchesFilter = !c.assigned_admin_id;
      return matchesSearch && matchesFilter;
    });
  }

  function renderStats() {
    document.getElementById("statTotal").textContent = conversations.length;
    document.getElementById("statAi").textContent = conversations.filter(c => c.display_status === "ai").length;
    document.getElementById("statHuman").textContent = conversations.filter(c => c.display_status === "human").length;
    document.getElementById("statEscalated").textContent = conversations.filter(c => c.display_status === "escalated").length;
  }

  function renderInbox() {
    renderStats();
    const list = getFiltered();
    const box = document.getElementById("inboxList");

    if (!list.length) {
      box.innerHTML = `<div class="chat-empty-inbox"><i class="bi bi-inbox d-block mb-2" style="font-size:26px;opacity:.4"></i>No conversations found.</div>`;
      return;
    }

    box.innerHTML = list.map(c => `
      <div class="chat-row ${c.id === activeId ? 'active' : ''}" data-id="${c.id}">
        <div class="chat-avatar">${initials(c.contact_name || c.wa_phone_number)}</div>
        <div class="chat-row-body">
          <div class="chat-row-top">
            <span class="chat-row-name">${escapeHtml(c.contact_name || c.wa_phone_number)}</span>
            <span class="chat-row-time">${fmtTime(c.last_message_at)}</span>
          </div>
          <div class="chat-row-preview">${escapeHtml(c.last_message_preview || "No messages yet")}</div>
          <div class="chat-row-meta">
            <span class="pill-status ${c.display_status}">${c.display_status}</span>
            ${c.unread_count > 0 ? `<span class="chat-unread">${c.unread_count}</span>` : ""}
          </div>
        </div>
      </div>`).join("");

    box.querySelectorAll(".chat-row").forEach(row => {
      row.addEventListener("click", () => openConversation(parseInt(row.dataset.id, 10)));
    });
  }

  async function loadConversations(preserveActive) {
    const res = await apiFetch(CFG.routes.data);
    if (!res.success) return;
    conversations = res.conversations;
    renderInbox();
    if (preserveActive && activeId) {
      const stillExists = conversations.find(c => c.id === activeId);
      if (stillExists) renderThreadHeader(stillExists);
    }
  }

  /* ---------------- Thread rendering ---------------- */

  function confidenceClass(score) {
    if (score === null || score === undefined) return "";
    if (score >= 75) return "hi";
    if (score >= 50) return "md";
    return "lo";
  }

  let pendingFile = null;

function fmtFileSize(bytes) {
  if (!bytes) return "";
  const units = ["B", "KB", "MB", "GB"];
  let i = 0, n = bytes;
  while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
  return `${n.toFixed(n < 10 && i > 0 ? 1 : 0)} ${units[i]}`;
}

function renderMediaContent(m) {
  if (m.type === "image") return `<div class="bubble-media"><img class="chat-img" src="${m.media_url}" data-full="${m.media_url}" alt="image"></div>`;
  if (m.type === "audio") return `<div class="bubble-media"><audio controls src="${m.media_url}"></audio></div>`;
  if (m.type === "video") return `<div class="bubble-media"><video controls src="${m.media_url}"></video></div>`;
  return `<a class="bubble-file" href="${m.media_url}" target="_blank" rel="noopener">
      <i class="bi bi-file-earmark-arrow-down"></i>
      <span><span class="fname d-block">${escapeHtml(m.media_filename || "File")}</span><span class="fsize">${fmtFileSize(m.media_size)}</span></span>
    </a>`;
}

function renderMessage(m) {
  if (m.sender_type === "system") {
    return `<div class="bubble-row sys"><div class="bubble sys">${escapeHtml(m.body)}</div></div>`;
  }
  const isIn = m.direction === "inbound";
  const cls = isIn ? "in" : (m.sender_type === "ai" ? "out-ai" : "out-admin");
  let tag = "";
  if (m.sender_type === "ai") tag = `<span class="bubble-tag">AI</span>`;
  else if (m.sender_type === "admin") tag = `<span class="bubble-tag">${escapeHtml(m.sender_admin_name || "Agent")}</span>`;

  let conf = "";
  if (m.sender_type === "ai" && m.ai_confidence !== null && m.ai_confidence !== undefined) {
    conf = `<span class="confidence-chip ${confidenceClass(m.ai_confidence)}">${m.ai_confidence}% confidence</span>`;
  }

  const isMedia = ["image", "audio", "video", "document"].includes(m.type);
  const content = isMedia
    ? renderMediaContent(m) + (m.body ? `<div class="mt-1">${escapeHtml(m.body)}</div>` : "")
    : `<div>${escapeHtml(m.body || "")}</div>`;

  return `
    <div class="bubble-row ${isIn ? 'in' : 'out'}">
      <div class="bubble ${isIn ? 'in' : cls}">
        ${content}
        <div class="bubble-meta">${tag}${conf}<span>${fmtTime(m.created_at)}</span></div>
      </div>
    </div>`;
}

function initAttachments() {
  const input = document.getElementById("attachInput");
  document.getElementById("btnAttach").addEventListener("click", () => input.click());
  input.addEventListener("change", () => {
    if (!input.files.length) return;
    const file = input.files[0];
    if (file.size > 16 * 1024 * 1024) { toast("File 16MB se bada hai.", "danger"); input.value = ""; return; }
    pendingFile = file;
    renderAttachPreview();
  });
}

function renderAttachPreview() {
  const wrap = document.getElementById("attachPreviewWrap");
  if (!pendingFile) { wrap.classList.add("d-none"); wrap.innerHTML = ""; return; }
  wrap.classList.remove("d-none");
  const isImg = pendingFile.type.startsWith("image/");
  const thumb = isImg ? `<img class="thumb" src="${URL.createObjectURL(pendingFile)}">` : `<i class="bi bi-file-earmark-text fs-4"></i>`;
  wrap.innerHTML = `<div class="attach-chip">${thumb}<span class="name">${escapeHtml(pendingFile.name)}</span><span class="remove" id="btnRemoveAttach">&times;</span></div>`;
  document.getElementById("btnRemoveAttach").addEventListener("click", () => { pendingFile = null; document.getElementById("attachInput").value = ""; renderAttachPreview(); });
}

function showUploadProgress(pct) {
  let bar = document.getElementById("uploadProgressBar");
  if (!bar) {
    const wrap = document.createElement("div");
    wrap.className = "upload-progress-wrap";
    wrap.id = "uploadProgressWrap";
    wrap.innerHTML = `<div class="upload-progress-bar" id="uploadProgressBar"></div>`;
    document.getElementById("attachPreviewWrap").appendChild(wrap);
    bar = document.getElementById("uploadProgressBar");
  }
  bar.style.width = pct + "%";
}
function hideUploadProgress() {
  const wrap = document.getElementById("uploadProgressWrap");
  if (wrap) wrap.remove();
}

function sendMediaMessage(caption) {
  return new Promise((resolve) => {
    const fd = new FormData();
    fd.append("file", pendingFile);
    if (caption) fd.append("caption", caption);

    const xhr = new XMLHttpRequest();
    xhr.open("POST", CFG.routes.sendMedia.replace("__ID__", activeId));
    xhr.setRequestHeader("X-CSRF-TOKEN", CSRF);
    xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
    xhr.setRequestHeader("Accept", "application/json");

    showUploadProgress(0);
    xhr.upload.addEventListener("progress", (e) => { if (e.lengthComputable) showUploadProgress(Math.round((e.loaded / e.total) * 100)); });
    xhr.onload = () => {
      hideUploadProgress();
      let res;
      try { res = JSON.parse(xhr.responseText); } catch (e) { toast("Upload response samajh nahi aaya.", "danger"); resolve(); return; }
      if (!res.success) { toast(firstError(res), "danger"); resolve(); return; }
      appendLiveMessage(res.message);
      pendingFile = null;
      document.getElementById("attachInput").value = "";
      renderAttachPreview();
      document.getElementById("composerInput").value = "";
      resolve();
    };
    xhr.onerror = () => { hideUploadProgress(); toast("Upload fail ho gaya.", "danger"); resolve(); };
    xhr.send(fd);
  });
}

function initLightbox() {
  document.getElementById("threadBody").addEventListener("click", (e) => {
    const img = e.target.closest(".chat-img");
    if (!img) return;
    const overlay = document.createElement("div");
    overlay.className = "lightbox-overlay";
    overlay.innerHTML = `<span class="lightbox-close">&times;</span><img src="${img.dataset.full}">`;
    overlay.addEventListener("click", () => overlay.remove());
    document.body.appendChild(overlay);
  });
}

  function renderThreadHeader(c) {
    document.getElementById("thAvatar").textContent = initials(c.contact_name || c.wa_phone_number);
    document.getElementById("thName").textContent = c.contact_name || c.wa_phone_number;
    document.getElementById("thPhone").textContent = c.wa_phone_number;
    const pill = document.getElementById("thStatusPill");
    pill.className = "pill-status " + c.display_status;
    pill.textContent = c.display_status;

    const lockFlag = document.getElementById("thLockFlag");
    if (c.is_locked) {
      lockFlag.classList.remove("d-none");
      document.getElementById("thLockText").textContent = "Locked by " + (c.locked_by_admin_name || "someone");
    } else {
      lockFlag.classList.add("d-none");
    }

    document.getElementById("btnTakeover").classList.toggle("d-none", c.display_status === "human");
    document.getElementById("btnResumeAi").classList.toggle("d-none", c.display_status !== "human" && c.display_status !== "escalated" && c.ai_active);

    const banner = document.getElementById("aiPausedBanner");
    const composer = document.getElementById("composerWrap");
    if (c.display_status === "human") {
      banner.classList.remove("d-none");
      document.getElementById("aiPausedBannerText").textContent = `${c.handling_admin_name || "An agent"} is handling this chat. AI will not auto-reply.`;
      composer.style.display = "";
    } else if (c.display_status === "escalated") {
      banner.classList.remove("d-none");
      document.getElementById("aiPausedBannerText").textContent = "AI escalated this chat — it wasn't confident enough to answer. Take over or resume AI.";
      composer.style.display = "none";
    } else {
      banner.classList.add("d-none");
      composer.style.display = "none";
    }

    document.getElementById("btnConvertLead").disabled = !!c.lead_id;
    document.getElementById("btnConvertLead").innerHTML = c.lead_id
      ? `<i class="bi bi-check-circle me-1"></i>Linked to Lead #${c.lead_id}`
      : `<i class="bi bi-person-lines-fill me-1"></i>Convert to Lead`;
  }

  /**
   * Loads the employee list from the server (GET /admin/chat/employees)
   * instead of trusting only the blade-embedded copy. If this comes back
   * empty or fails, we say so explicitly instead of silently leaving the
   * dropdowns blank — that's the difference between "no other admins
   * exist yet" and "something is broken."
   */
  async function loadEmployees() {
    try {
      const res = await apiFetch(CFG.routes.employees);
      if (!res.success) { toast("Could not load employee list.", "danger"); return; }
      employeesList = res.employees || [];
      if (!employeesList.length) {
        console.warn("chat.employees returned zero admins — check that the 'admins' table actually has more than the logged-in user, and that any status filter in ChatController::employees() matches your data.");
      }
      // Repaint dropdowns immediately if a conversation is already open.
      const current = conversations.find(c => c.id === activeId);
      if (current) renderAssignMenus(current);
    } catch (e) {
      toast("Could not load employee list — check the console.", "danger");
      console.error(e);
    }
  }

  function renderAssignMenus(c) {
    const buildItem = (emp) => `<li><a class="dropdown-item assign-item" href="#" data-id="${emp.id}">${escapeHtml(emp.name)}</a></li>`;

    if (!employeesList.length) {
      document.getElementById("assignMenu").innerHTML = `<li><span class="dropdown-item-text text-muted-2 fs-12">No admins found</span></li>`;
      document.getElementById("transferMenu").innerHTML = `<li><span class="dropdown-item-text text-muted-2 fs-12">No other admins found</span></li>`;
      return;
    }

    document.getElementById("assignMenu").innerHTML =
      `<li><a class="dropdown-item assign-item" href="#" data-id="">— Unassign —</a></li>` + employeesList.map(buildItem).join("");
    document.getElementById("transferMenu").innerHTML = employeesList
      .filter(e => e.id !== CFG.currentAdminId)
      .map(emp => `<li><a class="dropdown-item transfer-item" href="#" data-id="${emp.id}">${escapeHtml(emp.name)}</a></li>`).join("");

    document.querySelectorAll(".assign-item").forEach(a => a.addEventListener("click", async e => {
      e.preventDefault();
      const res = await apiFetch(CFG.routes.assign.replace("__ID__", activeId), {
        method: "POST", headers: csrfHeaders({ "Content-Type": "application/json" }), body: JSON.stringify({ admin_id: a.dataset.id || null }),
      });
      if (res.success) { toast(res.message, "dark"); await refreshActive(); }
    }));
    document.querySelectorAll(".transfer-item").forEach(a => a.addEventListener("click", async e => {
      e.preventDefault();
      const res = await apiFetch(CFG.routes.transfer.replace("__ID__", activeId), {
        method: "POST", headers: csrfHeaders({ "Content-Type": "application/json" }), body: JSON.stringify({ admin_id: a.dataset.id }),
      });
      if (!res.success) { toast(firstError(res), "danger"); return; }
      toast(res.message, "dark");
      await refreshActive();
    }));
  }

  function renderNotes(notes) {
    const box = document.getElementById("notesList");
    if (!notes || !notes.length) { box.innerHTML = `<div class="fs-12 text-muted-2 text-center">No notes yet.</div>`; return; }
    box.innerHTML = notes.map(n => `
      <div class="note-card">
        <div class="n-meta">${escapeHtml(n.admin_name || "—")} · ${n.created_at}</div>
        <div>${escapeHtml(n.note)}</div>
      </div>`).join("");
  }

  async function openConversation(id) {
    activeId = id;
    document.getElementById("threadEmpty").style.display = "none";
    document.getElementById("threadActive").style.display = "flex";
    renderInbox();
    await refreshActive(true);
    subscribeToConversation(id);
  }

  async function refreshActive(scrollToBottom) {
    if (!activeId) return;
    const res = await apiFetch(CFG.routes.show.replace("__ID__", activeId));
    if (!res.success) return;
    const c = res.conversation;

    renderThreadHeader(c);
    renderAssignMenus(c);
    renderNotes(c.notes);

    const body = document.getElementById("threadBody");
    body.innerHTML = c.messages.map(renderMessage).join("") || `<div class="chat-placeholder"><i class="bi bi-chat-dots d-block mb-2"></i>No messages yet.</div>`;
    if (scrollToBottom !== false) body.scrollTop = body.scrollHeight;

    const idx = conversations.findIndex(x => x.id === c.id);
    if (idx > -1) conversations[idx] = Object.assign({}, conversations[idx], c);
    renderInbox();
  }

  function appendLiveMessage(m) {
    if (m.conversation_id !== activeId) return;
    const body = document.getElementById("threadBody");
    const nearBottom = body.scrollHeight - body.scrollTop - body.clientHeight < 120;
    body.insertAdjacentHTML("beforeend", renderMessage(m));
    if (nearBottom) body.scrollTop = body.scrollHeight;
  }

  /* ---------------- Composer ---------------- */

  function initComposer() {
    const textarea = document.getElementById("composerInput");
    textarea.addEventListener("input", () => { textarea.style.height = "auto"; textarea.style.height = Math.min(textarea.scrollHeight, 110) + "px"; });
    textarea.addEventListener("keydown", e => { if (e.key === "Enter" && !e.shiftKey) { e.preventDefault(); sendMessage(); } });
    document.getElementById("btnSend").addEventListener("click", sendMessage);
  }

  async function sendMessage() {
  const textarea = document.getElementById("composerInput");
  const body = textarea.value.trim();
  if (!activeId) return;
  if (pendingFile) { await sendMediaMessage(body); return; }
  if (!body) return;
  textarea.value = ""; textarea.style.height = "auto";
  const res = await apiFetch(CFG.routes.send.replace("__ID__", activeId), {
    method: "POST", headers: csrfHeaders({ "Content-Type": "application/json" }), body: JSON.stringify({ body }),
  });
  if (!res.success) { toast(firstError(res), "danger"); return; }
  appendLiveMessage(res.message);
}

  /* ---------------- Workflow buttons ---------------- */

  function initWorkflowButtons() {
    document.getElementById("btnTakeover").addEventListener("click", async () => {
      const res = await apiFetch(CFG.routes.takeover.replace("__ID__", activeId), { method: "POST" });
      if (!res.success) { toast(res.message, "danger"); return; }
      toast(res.message, "dark");
      await refreshActive();
    });
    document.getElementById("btnResumeAi").addEventListener("click", async () => {
      const res = await apiFetch(CFG.routes.resumeAi.replace("__ID__", activeId), { method: "POST" });
      toast(res.message, "dark");
      await refreshActive();
    });
    document.getElementById("btnConvertLead").addEventListener("click", async () => {
      if (!confirm("Convert this WhatsApp conversation into a CRM lead?")) return;
      const res = await apiFetch(CFG.routes.convertToLead.replace("__ID__", activeId), { method: "POST" });
      if (!res.success) { toast(res.message, "danger"); return; }
      toast(res.message, "dark");
      await refreshActive();
    });
    document.getElementById("btnNotesToggle").addEventListener("click", () => document.getElementById("notesCol").classList.remove("d-none"));
    document.getElementById("btnNotesClose").addEventListener("click", () => document.getElementById("notesCol").classList.add("d-none"));
    document.getElementById("btnAddNote").addEventListener("click", async () => {
      const input = document.getElementById("noteInput");
      const note = input.value.trim();
      if (!note || !activeId) return;
      const res = await apiFetch(CFG.routes.notesStore.replace("__ID__", activeId), {
        method: "POST", headers: csrfHeaders({ "Content-Type": "application/json" }), body: JSON.stringify({ note }),
      });
      if (!res.success) { toast(firstError(res), "danger"); return; }
      input.value = "";
      await refreshActive();
    });
  }

  /* ---------------- Filters / search ---------------- */

  function initFilters() {
    document.getElementById("inboxSearch").addEventListener("input", e => { searchTerm = e.target.value; renderInbox(); });
    document.querySelectorAll("#inboxTabs button").forEach(btn => {
      btn.addEventListener("click", () => {
        document.querySelectorAll("#inboxTabs button").forEach(b => b.classList.remove("active"));
        btn.classList.add("active");
        activeFilter = btn.dataset.filter;
        renderInbox();
      });
    });
  }

  /* ---------------- Master AI switch ---------------- */

  async function loadMasterSwitch() {
    const res = await apiFetch(CFG.routes.settingsShow);
    if (!res.success) return;
    settingsCache = res.settings;
    paintMasterSwitch();
  }
  function paintMasterSwitch() {
    const el = document.getElementById("aiMasterSwitch");
    const lbl = document.getElementById("aiMasterLabel");
    el.classList.toggle("on", !!settingsCache.ai_globally_enabled);
    lbl.textContent = settingsCache.ai_globally_enabled ? "AI Engine: ON" : "AI Engine: OFF";
  }
  function initMasterSwitch() {
    document.getElementById("aiMasterSwitch").addEventListener("click", async () => {
      if (!settingsCache) return;
      const next = !settingsCache.ai_globally_enabled;
      if (!confirm(next ? "Turn the AI engine back ON for every conversation?" : "Turn the AI engine OFF everywhere? No conversation will get an automatic AI reply until you turn it back on.")) return;
      const payload = Object.assign({}, settingsCache, { ai_globally_enabled: next });
      const res = await apiFetch(CFG.routes.settingsUpdate, { method: "PUT", headers: csrfHeaders({ "Content-Type": "application/json" }), body: JSON.stringify(payload) });
      if (!res.success) { toast(firstError(res), "danger"); return; }
      settingsCache = res.settings;
      paintMasterSwitch();
      toast(res.message, "dark");
    });
  }

  /* ---------------- Real-time (Pusher / Laravel Echo) ---------------- */

  let echo = null;
  let conversationChannelName = null;

  function initEcho() {
      console.log("initEcho called");

    // Using the project's own global window.Echo (set up via your
    // resources/js/bootstrap.js + `npm run build`) instead of the CDN
    // Pusher/Echo bundle — keeps one Echo instance/auth config for the
    // whole app instead of a second one just for this page.
    if (!window.Echo) {
      console.error("Laravel Echo is not loaded (window.Echo is undefined). Live updates will fall back to the 15s poll.");
      return;
    }

    echo = window.Echo;
    echo.private("admin-chat-list").listen(".conversation-updated", () => loadConversations(true));
  }

  function subscribeToConversation(id) {
    if (!echo) return;
    if (conversationChannelName) echo.leave(conversationChannelName);
    conversationChannelName = `chat.conversation.${id}`;
    echo.private(conversationChannelName).listen(".new-message", (m) => appendLiveMessage(m));
  }

  /* ---------------- Boot ---------------- */

  document.addEventListener("DOMContentLoaded", () => {
    initFilters();
    initComposer();
    initWorkflowButtons();
    initMasterSwitch();
    initEcho();
    loadMasterSwitch();
    loadEmployees();
    loadConversations();
    initAttachments();
initLightbox();
    setInterval(() => loadConversations(true), 15000); // fallback poll in case a Pusher event is missed
  });
})();
</script>

@endsection
