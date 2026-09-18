@extends('layouts.app')
@section('title', 'Chatbot Settings | Dalal Adda')

@section('content')

<style>
  .settings-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
  @media (max-width:960px){ .settings-grid{ grid-template-columns:1fr; } }
  .settings-card { background:#fff; border:1px solid var(--border); border-radius:14px; padding:18px; }
  .settings-card h6 { font-size:13px; font-weight:800; text-transform:uppercase; letter-spacing:.3px; color:var(--text-muted); margin-bottom:14px; }
  .switch-row { display:flex; align-items:center; justify-content:space-between; border:1.5px solid var(--border); border-radius:10px; padding:12px 14px; margin-bottom:14px; }
  .field-hint { font-size:11px; color:var(--text-muted); margin-top:4px; }
  .webhook-box { background:#0F172A; color:#E2E8F0; border-radius:10px; padding:10px 13px; font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:12px; display:flex; justify-content:space-between; align-items:center; gap:10px; word-break:break-all; }
  .webhook-box button { flex-shrink:0; }
</style>

<div class="page-content">
  <section class="page-section active" id="page-chatbot-settings">

    <div class="page-header">
      <div>
        <span class="breadcrumb-eyebrow">Support</span>
        <h1>Chatbot Settings</h1>
        <p>Master AI switch, OpenAI configuration, and WhatsApp connection.</p>
      </div>
      <div class="page-header-actions">
        <a href="{{ route('chat.index') }}" class="btn btn-outline-secondary"><i class="bi bi-chat-dots me-1"></i>Back to Live Chat</a>
      </div>
    </div>

    <form id="settingsForm">
      <div class="switch-row" style="margin-bottom:16px">
        <div>
          <div class="fw-700 fs-14">AI Engine — Master Switch</div>
          <div class="fs-12 text-muted-2">When OFF, the AI will never auto-reply on any conversation, anywhere. Existing human-handled chats are unaffected.</div>
        </div>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="ai_globally_enabled" style="width:44px;height:24px">
        </div>
      </div>

      <div class="settings-grid">

        <div class="settings-card">
          <h6><i class="bi bi-robot me-1"></i>OpenAI</h6>

          <label class="form-label">API Key</label>
          <input type="password" class="form-control mb-1" id="openai_api_key" placeholder="Leave blank to keep current key">
          <div class="field-hint mb-3" id="openaiKeyHint"></div>

          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label">Chat model</label>
              <input type="text" class="form-control" id="openai_model" placeholder="gpt-4o-mini">
            </div>
            <div class="col-6">
              <label class="form-label">Embedding model</label>
              <input type="text" class="form-control" id="openai_embedding_model" placeholder="text-embedding-3-small">
            </div>
          </div>

          <label class="form-label">Confidence threshold — below this, the chat escalates to a human (0–100)</label>
          <input type="number" class="form-control mb-3" id="confidence_threshold" min="0" max="100">

          <label class="form-label">Extra brand instructions <span class="text-muted-2">(optional, appended to the system prompt)</span></label>
          <textarea class="form-control" id="system_prompt" rows="3" placeholder="e.g. Our brand name is Dalal Adda. Always be polite and concise."></textarea>
        </div>

        <div class="settings-card">
          <h6><i class="bi bi-whatsapp me-1"></i>WhatsApp Cloud API</h6>

          <label class="form-label">Phone Number ID</label>
          <input type="text" class="form-control mb-3" id="whatsapp_phone_number_id">

          <label class="form-label">WhatsApp Business Account ID</label>
          <input type="text" class="form-control mb-3" id="whatsapp_business_account_id">

          <label class="form-label">Access Token</label>
          <input type="password" class="form-control mb-1" id="whatsapp_access_token" placeholder="Leave blank to keep current token">
          <div class="field-hint mb-3" id="whatsappTokenHint"></div>

          <label class="form-label">API Version</label>
          <input type="text" class="form-control mb-3" id="whatsapp_api_version" placeholder="v20.0">

          <label class="form-label">Webhook URL <span class="text-muted-2">— paste this into Meta App Dashboard → WhatsApp → Configuration</span></label>
          <div class="webhook-box mb-2">
            <span id="webhookUrlText">{{ $webhookUrl }}</span>
            <button type="button" class="btn btn-sm btn-outline-light" id="copyWebhookBtn">Copy</button>
          </div>
          <button type="button" class="btn btn-sm btn-outline-secondary" id="regenTokenBtn">Regenerate verify token</button>
        </div>

        <div class="settings-card">
          <h6><i class="bi bi-translate me-1"></i>Fallback Replies (guaranteed, never model-generated)</h6>
          <label class="form-label">English</label>
          <textarea class="form-control mb-3" id="fallback_message_en" rows="2"></textarea>
          <label class="form-label">Hindi</label>
          <textarea class="form-control mb-3" id="fallback_message_hi" rows="2"></textarea>
          <label class="form-label">Bengali</label>
          <textarea class="form-control" id="fallback_message_bn" rows="2"></textarea>
        </div>

      </div>

      <div class="d-flex justify-content-end mt-3">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Settings</button>
      </div>
    </form>

  </section>
</div>

<script>
window.CHATBOT_SETTINGS_DATA = {
  csrfToken: '{{ csrf_token() }}',
  routes: {
    show: '{{ route('chatbotSettings.show') }}',
    update: '{{ route('chatbotSettings.update') }}',
    regenToken: '{{ route('chatbotSettings.regenerateToken') }}',
  }
};

(function () {
  "use strict";
  const CFG = window.CHATBOT_SETTINGS_DATA;
  const CSRF = CFG.csrfToken;

  function toast(message, variant) {
    let wrap = document.getElementById("stgToastWrap");
    if (!wrap) {
      wrap = document.createElement("div");
      wrap.id = "stgToastWrap";
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
    return res.json();
  }
  function firstError(res) { return res.errors ? Object.values(res.errors)[0][0] : (res.message || "Something went wrong."); }

  const FIELDS = [
    "ai_globally_enabled", "openai_model", "openai_embedding_model", "confidence_threshold", "system_prompt",
    "fallback_message_en", "fallback_message_hi", "fallback_message_bn",
    "whatsapp_phone_number_id", "whatsapp_business_account_id", "whatsapp_api_version",
  ];

  async function load() {
    const res = await apiFetch(CFG.routes.show);
    if (!res.success) return;
    const s = res.settings;
    document.getElementById("ai_globally_enabled").checked = !!s.ai_globally_enabled;
    FIELDS.filter(f => f !== "ai_globally_enabled").forEach(f => { const el = document.getElementById(f); if (el) el.value = s[f] ?? ""; });
    document.getElementById("openaiKeyHint").textContent = s.has_openai_key ? "A key is already saved." : "No key saved yet — chatbot replies will fail until one is set.";
    document.getElementById("whatsappTokenHint").textContent = s.has_whatsapp_token ? "A token is already saved." : "No token saved yet — outbound WhatsApp messages will fail until one is set.";
  }

  document.getElementById("settingsForm").addEventListener("submit", async e => {
    e.preventDefault();
    const payload = { ai_globally_enabled: document.getElementById("ai_globally_enabled").checked };
    FIELDS.filter(f => f !== "ai_globally_enabled").forEach(f => payload[f] = document.getElementById(f).value);

    const key = document.getElementById("openai_api_key").value.trim();
    if (key) payload.openai_api_key = key;
    const token = document.getElementById("whatsapp_access_token").value.trim();
    if (token) payload.whatsapp_access_token = token;

    const res = await apiFetch(CFG.routes.update, { method: "PUT", headers: csrfHeaders({ "Content-Type": "application/json" }), body: JSON.stringify(payload) });
    if (!res.success) { toast(firstError(res), "danger"); return; }
    toast(res.message, "dark");
    document.getElementById("openai_api_key").value = "";
    document.getElementById("whatsapp_access_token").value = "";
    load();
  });

  document.getElementById("copyWebhookBtn").addEventListener("click", () => {
    navigator.clipboard?.writeText(document.getElementById("webhookUrlText").textContent);
    toast("Webhook URL copied.", "dark");
  });

  document.getElementById("regenTokenBtn").addEventListener("click", async () => {
    if (!confirm("Regenerate the webhook verify token? You'll need to update it in the Meta App Dashboard too, or the webhook will stop verifying.")) return;
    const res = await apiFetch(CFG.routes.regenToken, { method: "POST" });
    toast(res.message, "dark");
  });

  document.addEventListener("DOMContentLoaded", load);
})();
</script>

@endsection