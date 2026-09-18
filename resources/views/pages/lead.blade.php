@extends('layouts.app')
@section('title', 'Leads | Dalal Adda')

@section('content')

<style>
  /* ---- Lead type badges (reuse .badge-status base look) ---- */
  .badge-status.lt-hot       { background: var(--danger-bg);  color: var(--danger); }
  .badge-status.lt-warm      { background: var(--warning-bg); color: var(--warning); }
  .badge-status.lt-cold      { background: var(--info-bg);    color: var(--info); }
  .badge-status.lt-converted { background: var(--success-bg); color: var(--success); }
  .badge-status.lt-dealer    { background: var(--gold-bg);    color: var(--gold); }

  /* ---- Table rows tinted by lead type/status ----
     Pinned rows still win (kept below with !important) so pinning stays
     visually obvious no matter what status a lead is in. */
  .crm-table tbody tr[data-lead-type="hot"]       { background: var(--danger-bg); }
  .crm-table tbody tr[data-lead-type="warm"]      { background: var(--warning-bg); }
  .crm-table tbody tr[data-lead-type="cold"]      { background: var(--info-bg); }
  .crm-table tbody tr[data-lead-type="converted"] { background: var(--success-bg); }
  .crm-table tbody tr[data-lead-type="dealer"]    { background: var(--gold-bg); }
  .crm-table tbody tr.is-pinned-row               { background: var(--gold-bg) !important; }
  .crm-table tbody tr[data-lead-type]:hover       { filter: brightness(0.97); cursor: pointer; }

  /* ---- Assigned-to avatar stack ---- */
  .avatar-stack { display: flex; align-items: center; }
  .avatar-stack .avatar { width: 26px; height: 26px; font-size: 10px; border: 2px solid #fff; margin-left: -8px; }
  .avatar-stack .avatar:first-child { margin-left: 0; }
  .avatar-stack .avatar-more { background: var(--bg); color: var(--text-muted); }
  .unassigned-text { font-size: 12px; color: var(--text-muted); font-style: italic; }

  /* ---- WhatsApp quick-open links (customer contact cell + offcanvas) ----
     Clicking these opens wa.me/<number>, which hands off to the WhatsApp
     app installed on the device (or WhatsApp Web as a fallback). */
  .wa-link { color: var(--success); text-decoration: none; font-weight: 600; }
  .wa-link:hover { text-decoration: underline; }

  /* ---- Pin / share buttons ---- */
  .pin-btn { background: none; border: none; color: var(--border); font-size: 15px; padding: 4px 6px; line-height: 1; }
  .pin-btn:hover { color: var(--gold); }
  .pin-btn.pinned { color: var(--gold); }
  #ldShareBtn:hover { color: var(--primary); }
  tr.is-pinned-row { background: var(--gold-bg); }
  tr.is-pinned-row:hover { background: var(--primary-50); }

  /* ---- Inline "status" (lead type) select shown in the lead detail panel ----
     Looks like the badge, but is a real <select> so the status can be
     changed right there without opening the edit form. */
  .status-select {
    appearance: none; -webkit-appearance: none; -moz-appearance: none;
    border: none; border-radius: 20px; padding: 7px 32px 7px 14px;
    font-size: 12.5px; font-weight: 700; cursor: pointer;
    background-repeat: no-repeat; background-position: right 11px center; background-size: 12px;
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23666' stroke-width='2'><polyline points='6 9 12 15 18 9'/></svg>");
  }
  .status-select.lt-hot       { background-color: var(--danger-bg);  color: var(--danger); }
  .status-select.lt-warm      { background-color: var(--warning-bg); color: var(--warning); }
  .status-select.lt-cold      { background-color: var(--info-bg);    color: var(--info); }
  .status-select.lt-converted { background-color: var(--success-bg); color: var(--success); }
  .status-select.lt-dealer    { background-color: var(--gold-bg);    color: var(--gold); }

  /* ---- Multi-select "assign to" control (also reused, single-select mode,
     by the super-admin-only "view leads by user" control below) ---- */
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

  /* ---- Lead-count badge shown per user in the "view leads by user" panel ---- */
  .ms-option .count-badge {
    margin-left: auto; background: var(--bg); color: var(--text-muted);
    font-size: 11px; font-weight: 700; padding: 2px 9px; border-radius: 20px; flex-shrink: 0;
  }
  .ms-option.active { background: var(--primary-50); }
  .ms-option.active .count-badge { background: var(--primary); color: #fff; }

  /* ---- Reminder appointment-type radios (also reused for the reminder/note kind toggle) ---- */
  .apt-type-group { display: flex; gap: 10px; }
  .apt-type-option { flex: 1; }
  .apt-type-option input { display: none; }
  .apt-type-option label {
    display: flex; align-items: center; justify-content: center; gap: 7px;
    border: 1.5px solid var(--border); border-radius: 10px; padding: 9px; font-size: 13px; font-weight: 600;
    color: var(--text-muted); cursor: pointer; background: #FBFBFE;
  }
  .apt-type-option input:checked + label { border-color: var(--primary); color: var(--primary); background: var(--primary-50); }

  #ldReminders .reminder-item.missed-reminder { background: var(--danger-bg); }
  #ldReminders .reminder-item.missed-reminder .reminder-title { color: var(--danger); }
  #ldReminders .reminder-item.silent-reminder { opacity: .6; }
  #ldReminders .reminder-item.silent-reminder .reminder-title { color: var(--text-muted); }

  /* ---- Attachments (lead detail offcanvas) ---- */
  .attachment-item { display: flex; align-items: center; gap: 10px; padding: 9px 4px; border-top: 1px solid var(--border); }
  .attachment-item:first-child { border-top: none; }
  .attachment-icon {
    width: 34px; height: 34px; border-radius: 9px; background: var(--primary-50); color: var(--primary);
    display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0;
  }
  .attachment-name { font-size: 13px; font-weight: 600; color: var(--text); word-break: break-all; }
  .attachment-meta { font-size: 11.5px; color: var(--text-muted); }

  /* ---- Attachment upload progress bar ---- */
  .attachment-progress { margin-bottom: 10px; padding: 10px 12px; border: 1px solid var(--border); border-radius: 10px; background: #FBFBFE; }
  .attachment-progress-info { display: flex; justify-content: space-between; align-items: center; font-size: 12.5px; margin-bottom: 6px; }
  .attachment-progress-name { font-weight: 600; color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 260px; }
  .attachment-progress-pct { color: var(--primary); font-weight: 700; flex-shrink: 0; margin-left: 8px; }
  .attachment-progress-track { height: 6px; border-radius: 4px; background: var(--border); overflow: hidden; }
  .attachment-progress-fill { height: 100%; width: 0%; background: var(--primary); transition: width .15s ease; }

  /* ---- Share-lead card ---- */
  .share-lead-card {
    width: 340px; border-radius: 18px; overflow: hidden; background: #fff;
    border: 1px solid var(--border); box-shadow: var(--shadow-lg); font-family: inherit;
  }
  .share-lead-card .slc-header {
    background: linear-gradient(135deg, var(--primary) 0%, #4338CA 100%);
    padding: 22px 20px 44px; color: #fff; position: relative;
  }
  .share-lead-card .slc-brand { font-size: 11px; letter-spacing: .08em; text-transform: uppercase; opacity: .85; font-weight: 700; }
  .share-lead-card .slc-avatar {
    width: 56px; height: 56px; border-radius: 50%; background: #fff; color: var(--primary);
    display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 20px;
    position: absolute; left: 20px; bottom: -28px; border: 3px solid #fff; box-shadow: var(--shadow-lg);
  }
  .share-lead-card .slc-body { padding: 36px 20px 18px; }
  .share-lead-card .slc-name { font-size: 17px; font-weight: 800; color: var(--text); }
  .share-lead-card .slc-product { font-size: 13px; color: var(--text-muted); margin-bottom: 12px; }
  .share-lead-card .slc-row { display: flex; justify-content: space-between; padding: 7px 0; border-top: 1px dashed var(--border); font-size: 12.5px; }
  .share-lead-card .slc-row span:first-child { color: var(--text-muted); }
  .share-lead-card .slc-row span:last-child { font-weight: 700; color: var(--text); text-align: right; }
  .share-lead-card .slc-footer { text-align: center; font-size: 10.5px; color: var(--text-muted); padding: 12px 0 2px; }

  /* Note: .alarm-card styles moved to the shared topbar partial — the alarm
     modal is now injected globally by /js/global-reminders.js (see the
     shared topbar partial), so it can pop up on any page in the app, not
     just this one. */
</style>

<div class="page-content">
  <section class="page-section active" id="page-leads">

    <div class="page-header">
      <div>
        <span class="breadcrumb-eyebrow">Pipeline</span>
        <h1>Leads</h1>
        <p>Manage, assign and follow up on every lead in one place.</p>
      </div>
      <div class="page-header-actions d-flex gap-2">
        <button class="btn btn-primary" id="openAddLeadBtn">
          <i class="bi bi-plus-lg me-1"></i>Add Lead
        </button>
      </div>
    </div>

    <!-- Stat cards -->
    <div class="row g-3 mb-3">
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div>
            <div class="stat-label">Total Leads</div>
            <div class="stat-value" id="statTotal">0</div>
          </div>
          <div class="stat-icon" style="background:var(--primary-50); color:var(--primary)"><i class="bi bi-person-lines-fill"></i></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div>
            <div class="stat-label">Pinned</div>
            <div class="stat-value" id="statPinned">0</div>
          </div>
          <div class="stat-icon" style="background:var(--gold-bg); color:var(--gold)"><i class="bi bi-pin-angle-fill"></i></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div>
            <div class="stat-label">Scheduled Reminders</div>
            <div class="stat-value" id="statScheduled">0</div>
          </div>
          <div class="stat-icon" style="background:var(--info-bg); color:var(--info)"><i class="bi bi-bell"></i></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div>
            <div class="stat-label">Missed Reminders</div>
            <div class="stat-value" id="statMissed">0</div>
          </div>
          <div class="stat-icon" style="background:var(--danger-bg); color:var(--danger)"><i class="bi bi-bell-slash"></i></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div>
            <div class="stat-label">Silenced Reminders</div>
            <div class="stat-value" id="statSilent">0</div>
          </div>
          <div class="stat-icon" style="background:#EEF1F6; color:var(--text-muted)"><i class="bi bi-volume-mute"></i></div>
        </div>
      </div>
    </div>

    <!-- Category tabs -->
    <ul class="nav lead-tabs mb-3" id="leadCategoryTabs">
      <li class="nav-item"><a class="nav-link active" data-category="all" href="#">All Leads</a></li>
      <li class="nav-item"><a class="nav-link" data-category="mine" href="#">My Leads</a></li>
      <li class="nav-item"><a class="nav-link" data-category="pinned" href="#">Pinned</a></li>
      <li class="nav-item"><a class="nav-link" data-category="scheduled" href="#">Scheduled Reminders</a></li>
      <li class="nav-item"><a class="nav-link" data-category="missed" href="#">Missed Reminders <span class="badge-count" id="missedCountTab" style="background:var(--danger-bg);color:var(--danger)"></span></a></li>
      <li class="nav-item"><a class="nav-link" data-category="silent" href="#">Silenced</a></li>
    </ul>

    <!-- Filter bar -->
    <div class="filter-bar">
      <div class="filter-search">
        <i class="bi bi-search"></i>
        <input type="text" class="form-control" id="filterSearch" placeholder="Search by customer, product or phone...">
      </div>
      <select class="form-select" id="filterLeadType" style="max-width:150px">
        <option value="">All Lead Types</option>
        @foreach($leadTypes as $type)
          <option value="{{ $type }}">{{ ucfirst($type) }}</option>
        @endforeach
      </select>
      <select class="form-select" id="filterSource" style="max-width:160px">
        <option value="">All Sources</option>
        @foreach($sources as $source)
          <option value="{{ $source }}">{{ $source }}</option>
        @endforeach
      </select>
      <div class="d-flex align-items-center gap-1">
        <input type="date" class="form-control" id="filterDateFrom" style="max-width:150px" title="Created from">
        <span class="text-muted-2 fs-12">to</span>
        <input type="date" class="form-control" id="filterDateTo" style="max-width:150px" title="Created to">
      </div>
      @if($isSuperAdmin)
        <!-- Super-admin only: searchable select to view any one user's leads,
             with a live lead-count badge next to every name. -->
        <div class="ms-control" id="viewAsMsControl" style="max-width:230px">
          <button type="button" class="ms-toggle" id="viewAsMsToggle">
            <span class="chips" id="viewAsMsChips"><span class="placeholder"><i class="bi bi-people me-1"></i>View by user...</span></span>
            <i class="bi bi-chevron-down"></i>
          </button>
          <div class="ms-panel" id="viewAsMsPanel">
            <input type="text" class="form-control ms-search" id="viewAsMsSearch" placeholder="Search users...">
            <div id="viewAsMsOptions"></div>
          </div>
        </div>
      @endif
      <button class="btn btn-outline-secondary btn-sm" id="clearFiltersBtn">Clear</button>
    </div>

    <div id="activeFilterPills" class="d-flex flex-wrap gap-2 mb-2"></div>

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
              <th style="width:34px"></th>
              <th>Customer</th>
              <th>Product</th>
              <th>Contact</th>
              <th>Source</th>
              <th>Lead Type</th>
              <th>Assigned To</th>
              <th>Created By</th>
              <th>Date</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="leadsTbody"></tbody>
        </table>
      </div>
      <div class="crm-pagination">
        <span class="page-info" id="pageInfo"></span>
        <div class="d-flex gap-1" id="pageButtons"></div>
      </div>
    </div>
  </section>
</div>

<!-- ===================== ADD / EDIT LEAD MODAL ===================== -->
<div class="modal fade" id="leadModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form id="leadForm">
        <input type="hidden" id="leadId">
        <div class="modal-header">
          <h5 class="modal-title" id="leadModalTitle">Add new lead</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label">Customer name</label>
              <input type="text" class="form-control" id="leadCustomerName" placeholder="e.g. Jordan Patel" required>
            </div>
            <div class="col-6">
              <label class="form-label">Product name</label>
              <input type="text" class="form-control" id="leadProductName" placeholder="e.g. CRM Subscription" required>
            </div>
            <div class="col-6">
              <label class="form-label">WhatsApp number</label>
              <input type="text" class="form-control" id="leadWhatsapp" placeholder="+91 98765 43210">
            </div>
            <div class="col-6">
              <label class="form-label">Phone</label>
              <input type="text" class="form-control" id="leadPhone" placeholder="+91 98765 43210" required>
            </div>

            <div class="col-12">
              <label class="form-label">Assign to (search &amp; select multiple)</label>
              <div class="ms-control" id="assignMsControl">
                <button type="button" class="ms-toggle" id="assignMsToggle">
                  <span class="chips" id="assignMsChips"><span class="placeholder">Select employees...</span></span>
                  <i class="bi bi-chevron-down"></i>
                </button>
                <div class="ms-panel" id="assignMsPanel">
                  <input type="text" class="form-control ms-search" id="assignMsSearch" placeholder="Search employees...">
                  <div id="assignMsOptions"></div>
                </div>
              </div>
            </div>
            <div class="col-6">
              <label class="form-label">Source</label>
              <select class="form-select" id="leadSource">
                @foreach($sources as $source)
                  <option value="{{ $source }}">{{ $source }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-6" id="socialPlatformWrap" style="display:none">
              <label class="form-label">Social platform</label>
              <select class="form-select" id="leadSocialPlatform">
                @foreach($platforms as $platform)
                  <option value="{{ $platform }}">{{ $platform }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-6">
              <label class="form-label">Lead type</label>
              <select class="form-select" id="leadType">
                @foreach($leadTypes as $type)
                  <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                @endforeach
              </select>
            </div>


          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="leadSubmitBtn">Add lead</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===================== LEAD DETAIL OFFCANVAS ===================== -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="leadDetailOffcanvas" style="width:440px">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title">Lead details</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body">
    <div class="d-flex align-items-center gap-3 mb-3">
      <div id="ldAvatar"></div>
      <div>
        <div class="fw-600 fs-15" id="ldName" style="font-size:16px"></div>
        <div class="text-muted-2 fs-13" id="ldProduct"></div>
      </div>
      <div class="d-flex align-items-center gap-1 ms-auto">
        <button type="button" class="pin-btn fs-5" id="ldShareBtn" title="Share this lead"><i class="bi bi-share-fill"></i></button>
        <button type="button" class="pin-btn fs-4" id="ldPinBtn" title="Pin this lead"><i class="bi bi-pin-angle-fill"></i></button>
      </div>
    </div>

    <!-- Status (lead type) — editable right here, saves immediately -->
    <div class="mb-3">
      <label class="form-label fs-11 text-uppercase text-muted-2 fw-700 mb-1">Status</label><br>
      <select class="status-select" id="ldTypeSelect">
        @foreach($leadTypes as $type)
          <option value="{{ $type }}">{{ ucfirst($type) }}</option>
        @endforeach
      </select>
    </div>

    <div class="card-flat p-3 mb-3">
      <div class="d-flex justify-content-between mb-2"><span class="text-muted-2 fs-13">WhatsApp</span><span class="fw-600 fs-13" id="ldWhatsapp">—</span></div>
      <div class="d-flex justify-content-between mb-2"><span class="text-muted-2 fs-13">Phone</span><span class="fw-600 fs-13" id="ldPhone">—</span></div>
      <div class="d-flex justify-content-between mb-2"><span class="text-muted-2 fs-13">Source</span><span class="fw-600 fs-13" id="ldSource">—</span></div>
      <div class="d-flex justify-content-between mb-2"><span class="text-muted-2 fs-13">Created by</span><span class="fw-600 fs-13" id="ldCreatedBy">—</span></div>
      <div class="d-flex justify-content-between"><span class="text-muted-2 fs-13">Assigned to</span><span class="fw-600 fs-13 text-end" id="ldAssigned">—</span></div>
    </div>

    <!-- Attachments — super_admin sees everyone's, other admins see only
         the files they themselves uploaded (scoped server-side). -->
    <h6 class="fs-13 fw-700 text-uppercase text-muted-2 mb-2 d-flex align-items-center justify-content-between">
      Attachments
      <label class="btn btn-sm btn-outline-secondary mb-0" id="ldAttachmentUploadBtn" style="cursor:pointer">
        <i class="bi bi-paperclip me-1"></i>Add<input type="file" id="ldAttachmentInput" hidden>
      </label>
    </h6>
    <div class="attachment-progress d-none" id="ldAttachmentUploadProgress">
      <div class="attachment-progress-info">
        <span class="attachment-progress-name" id="ldAttachmentUploadName"></span>
        <span class="attachment-progress-pct" id="ldAttachmentUploadPct">0%</span>
      </div>
      <div class="attachment-progress-track"><div class="attachment-progress-fill" id="ldAttachmentUploadFill"></div></div>
    </div>
    <div class="section-card mb-4" id="ldAttachments"></div>

    <!-- Add a reminder (has date/time, alerts) or a note (just text, never alerts) -->
    <h6 class="fs-13 fw-700 text-uppercase text-muted-2 mb-2">Add a reminder or note</h6>
    <form id="reminderForm" class="mb-4">
      <input type="hidden" id="remLeadId">

      <div class="apt-type-group mb-3" id="remKindGroup">
        <div class="apt-type-option">
          <input type="radio" name="remKind" id="remKindReminder" value="reminder" checked>
          <label for="remKindReminder"><i class="bi bi-alarm"></i>Reminder</label>
        </div>
        <div class="apt-type-option">
          <input type="radio" name="remKind" id="remKindNote" value="note">
          <label for="remKindNote"><i class="bi bi-sticky"></i>Note</label>
        </div>
      </div>

      <div class="mb-2">
        <textarea class="form-control" id="remNote" rows="2" placeholder="Note (e.g. follow up on pricing)" required></textarea>
      </div>

      <!-- Date/time apply to both kinds now — a note can carry a date/time too. -->
      <div class="row g-2 mb-2">
        <div class="col-6">
          <input type="date" class="form-control" id="remDate">
        </div>
        <div class="col-6">
          <input type="time" class="form-control" id="remTime">
        </div>
      </div>

      <!-- Only shown for the "Reminder" kind — appointment type doesn't apply to a plain note. -->
      <div id="remAppointmentFields">
        <div class="apt-type-group mb-3">
          <div class="apt-type-option">
            <input type="radio" name="remAptType" id="remAptWhatsapp" value="whatsapp">
            <label for="remAptWhatsapp"><i class="bi bi-whatsapp"></i>WhatsApp</label>
          </div>
          <div class="apt-type-option">
            <input type="radio" name="remAptType" id="remAptMeeting" value="meeting">
            <label for="remAptMeeting"><i class="bi bi-camera-video"></i>Meeting</label>
          </div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-sm w-100" id="remSubmitBtn">Add reminder</button>
    </form>

    <h6 class="fs-13 fw-700 text-uppercase text-muted-2 mb-2">Reminders</h6>
    <div class="section-card mb-4" id="ldReminders"></div>

    <h6 class="fs-13 fw-700 text-uppercase text-muted-2 mb-2">Notes</h6>
    <div class="section-card" id="ldNotes"></div>
  </div>
</div>

<!-- ===================== SHARE LEAD CARD MODAL ===================== -->
<div class="modal fade" id="shareCardModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Share lead</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div style="display:flex;justify-content:center;padding:6px">
          <div id="shareCard" class="share-lead-card"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" id="shareCardDownloadBtn"><i class="bi bi-download me-1"></i>Download</button>
        <button type="button" class="btn btn-primary" id="shareCardShareBtn"><i class="bi bi-share-fill me-1"></i>Share</button>
      </div>
    </div>
  </div>
</div>

<!-- ===================== DELETE CONFIRM MODAL ===================== -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-content-compact">
    <div class="modal-content modal-content-danger">
      <div class="modal-header">
        <h5 class="modal-title">Delete lead(s)?</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0">This will permanently delete the selected lead(s) and their reminders. This can't be undone.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirmDeleteBtn">Delete</button>
      </div>
    </div>
  </div>
</div>

<!-- Note: the "reminder due" alarm modal used to live here. It's now injected
     globally by /js/global-reminders.js (see the shared topbar partial), so
     it can pop up on any page in the app, not just this one. -->

<!-- Used to turn the share card into a downloadable/shareable PNG. -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
  window.LEAD_PAGE_DATA = {
    admins: @json($admins),
    leadTypes: @json($leadTypes),
    csrfToken: '{{ csrf_token() }}',
    isSuperAdmin: @json($isSuperAdmin),
    routes: {
      data: '{{ route('leads.data') }}',
      store: '{{ route('leads.store') }}',
      update: '{{ url('admin/leads') }}/__ID__',
      destroy: '{{ url('admin/leads') }}/__ID__',
      show: '{{ url('admin/leads') }}/__ID__',
      togglePin: '{{ url('admin/leads') }}/__ID__/toggle-pin',
      updateType: '{{ url('admin/leads') }}/__ID__/type',
      bulkDelete: '{{ route('leads.bulkDestroy') }}',
      reminderStore: '{{ route('leads.reminders.store') }}',
      reminderStatus: '{{ url('admin/leads/reminders') }}/__ID__/status',
      reminderDestroy: '{{ url('admin/leads/reminders') }}/__ID__',
      attachmentStore: '{{ route('leads.attachments.store') }}',
      attachmentDestroy: '{{ url('admin/leads/attachments') }}/__ID__',
    }
  };
  /* ==========================================================================
   Leads page logic (talks to LeadManageController via fetch/AJAX)

   The reminder ALARM POPUP and the 30s DUE-REMINDER POLL used to live in
   this file. They've moved to /js/global-reminders.js so they run on every
   page, not just this one — see window.openLeadDetailById below, which is
   how the global script opens a lead's detail panel without a page reload
   when the user is already sitting on this page.
   ========================================================================== */

(function () {
  "use strict";

  const CFG = window.LEAD_PAGE_DATA;
  const CSRF = CFG.csrfToken;

  const AVATAR_COLORS = ["#4338CA", "#0891B2", "#B45309", "#16A34A", "#DC2626", "#6D28D9", "#0D9488"];

  let leads = [];
  let currentAdmin = null;
  let activeFilters = { search: "", leadType: "", source: "", dateFrom: "", dateTo: "" };
  let activeCategory = "all";
  let currentPage = 1;
  const PAGE_SIZE = 8;
  let selectedIds = new Set();
  let selectedAssignIds = new Set();
  let currentDetailLeadId = null; // the lead currently open in the offcanvas — used by the status select + share button
  let viewAsAdminId = null; // super_admin only: selected user id from the "view leads by user" control

  /* ---------------- Small helpers ---------------- */

  function initials(name) {
    return (name || "?").trim().split(/\s+/).slice(0, 2).map(p => p[0]).join("").toUpperCase();
  }

  function colorFor(name) {
    let hash = 0;
    for (let i = 0; i < (name || "").length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash);
    return AVATAR_COLORS[Math.abs(hash) % AVATAR_COLORS.length];
  }

  function avatarHtml(name, size) {
    const cls = size === "lg" ? "avatar avatar-lg" : size === "sm" ? "avatar avatar-sm" : "avatar";
    return `<div class="${cls}" style="background:${colorFor(name)}">${initials(name)}</div>`;
  }

  function leadTypeBadge(type) {
    const label = type.charAt(0).toUpperCase() + type.slice(1);
    return `<span class="badge-status lt-${type}">${label}</span>`;
  }

  // Turns a phone/WhatsApp number into the WhatsApp app's own "whatsapp://"
  // link — clicking it launches the installed WhatsApp app directly (not a
  // browser tab). Strips everything except digits since it wants
  // "<countrycode><number>" with no +, spaces or dashes.
  function waUrl(number) {
    const digits = (number || "").replace(/\D/g, "");
    return digits ? `whatsapp://send?phone=${digits}` : null;
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

  function fileIcon(mime) {
    if (!mime) return "bi-file-earmark";
    if (mime.includes("pdf")) return "bi-file-earmark-pdf";
    if (mime.includes("image")) return "bi-file-earmark-image";
    if (mime.includes("sheet") || mime.includes("excel") || mime.includes("csv")) return "bi-file-earmark-spreadsheet";
    if (mime.includes("word")) return "bi-file-earmark-word";
    if (mime.includes("zip")) return "bi-file-earmark-zip";
    return "bi-file-earmark";
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

  /* ---------------- Data fetch ---------------- */

  async function loadLeads() {
    const data = await apiFetch(CFG.routes.data);
    if (!data.success) return;
    leads = data.leads;
    currentAdmin = data.current_admin;
    renderAll();
  }

  /* ---------------- Filtering / categorisation ---------------- */

  // Only type=reminder entries count toward the scheduled/missed/silent
  // buckets — type=note entries are plain notes and are never "due".
  function reminderBuckets(lead) {
    const relevant = (lead.reminders || []).filter(r => (r.type || "reminder") !== "note");
    const scheduled = relevant.filter(r => r.status === "scheduled" && !isMissed(r));
    const missed = relevant.filter(r => r.status !== "completed" && r.status !== "silent" && (r.status === "missed" || isMissed(r)));
    const silent = relevant.filter(r => r.status === "silent");
    return { scheduled, missed, silent };
  }

  function isMissed(reminder) {
    if (reminder.status === "completed" || reminder.status === "silent") return false;
    if (reminder.status === "missed") return true;
    if (!reminder.reminder_date) return false;
    const dt = new Date(`${reminder.reminder_date}T${reminder.reminder_time || "23:59:59"}`);
    return dt.getTime() < Date.now();
  }

  // Used both by table filtering and by the "view leads by user" counts:
  // a lead "belongs" to an admin if they created it OR are assigned to it —
  // same definition the backend's Lead::scopeVisibleTo() uses for non-super admins.
  function leadBelongsToAdmin(lead, adminId) {
    return lead.created_by === adminId || (lead.assigned_admins || []).some(a => a.id === adminId);
  }

  function adminLeadCount(adminId) {
    return leads.filter(l => leadBelongsToAdmin(l, adminId)).length;
  }

  function getFilteredLeads() {
    return leads.filter(l => {
      const term = activeFilters.search.toLowerCase();
      const matchesSearch = !term || (l.customer_name + l.product_name + l.phone).toLowerCase().includes(term);
      const matchesType = !activeFilters.leadType || l.lead_type === activeFilters.leadType;
      const matchesSource = !activeFilters.source || l.source === activeFilters.source;

      // created_at is an ISO string ("2026-07-03T10:15:00.000Z" or similar) —
      // comparing just the date portion as a string works fine since it's
      // always YYYY-MM-DD first.
      const leadDate = (l.created_at || "").slice(0, 10);
      const matchesDateFrom = !activeFilters.dateFrom || (leadDate && leadDate >= activeFilters.dateFrom);
      const matchesDateTo = !activeFilters.dateTo || (leadDate && leadDate <= activeFilters.dateTo);

      // Super-admin-only "view leads by user" filter.
      const matchesViewAs = viewAsAdminId === null || leadBelongsToAdmin(l, viewAsAdminId);

      let matchesCategory = true;
      const { scheduled, missed, silent } = reminderBuckets(l);
      if (activeCategory === "pinned") matchesCategory = !!l.is_pinned;
      else if (activeCategory === "mine") matchesCategory = l.created_by === currentAdmin.id;
      else if (activeCategory === "scheduled") matchesCategory = scheduled.length > 0;
      else if (activeCategory === "missed") matchesCategory = missed.length > 0;
      else if (activeCategory === "silent") matchesCategory = silent.length > 0;

      return matchesSearch && matchesType && matchesSource && matchesDateFrom && matchesDateTo && matchesViewAs && matchesCategory;
    });
  }

  /* ---------------- Rendering ---------------- */

  function renderAll() {
    renderStats();
    renderTable();
  }

  function renderStats() {
    document.getElementById("statTotal").textContent = leads.length;
    document.getElementById("statPinned").textContent = leads.filter(l => l.is_pinned).length;
    let scheduled = 0, missed = 0, silent = 0;
    leads.forEach(l => {
      const b = reminderBuckets(l);
      scheduled += b.scheduled.length;
      missed += b.missed.length;
      silent += b.silent.length;
    });
    document.getElementById("statScheduled").textContent = scheduled;
    document.getElementById("statMissed").textContent = missed;
    document.getElementById("statSilent").textContent = silent;
    const tab = document.getElementById("missedCountTab");
    tab.textContent = missed > 0 ? missed : "";
    tab.style.display = missed > 0 ? "inline-block" : "none";
  }

  function renderTable() {
    const filtered = getFilteredLeads();
    const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
    currentPage = Math.min(currentPage, totalPages);
    const start = (currentPage - 1) * PAGE_SIZE;
    const pageItems = filtered.slice(start, start + PAGE_SIZE);

    const tbody = document.getElementById("leadsTbody");

    if (pageItems.length === 0) {
      tbody.innerHTML = `<tr><td colspan="11"><div class="empty-state">
        <i class="bi bi-inbox"></i><h6>No leads found</h6>
        <p class="fs-13 mb-0">Try adjusting filters or add a new lead.</p></div></td></tr>`;
    } else {
      tbody.innerHTML = pageItems.map(l => {
        const assigned = l.assigned_admins || [];
        const assignedHtml = assigned.length
          ? `<div class="avatar-stack">${assigned.slice(0, 3).map(a => avatarHtml(a.name, "sm")).join("")}${assigned.length > 3 ? `<div class="avatar avatar-sm avatar-more">+${assigned.length - 3}</div>` : ""}</div>`
          : `<span class="unassigned-text">Unassigned</span>`;
        const waHtml = l.whatsapp
          ? `<a href="${waUrl(l.whatsapp)}" class="wa-link" title="Open in WhatsApp" onclick="event.stopPropagation()"><i class="bi bi-whatsapp me-1"></i>${l.whatsapp}</a>`
          : "";

        return `
        <tr data-id="${l.id}" data-lead-type="${l.lead_type}" class="${l.is_pinned ? "is-pinned-row" : ""}">
          <td onclick="event.stopPropagation()"><input class="form-check-input row-check" type="checkbox" data-id="${l.id}" ${selectedIds.has(l.id) ? "checked" : ""}></td>
          <td onclick="event.stopPropagation()"><button class="pin-btn ${l.is_pinned ? "pinned" : ""}" data-pin-id="${l.id}" title="Pin"><i class="bi bi-pin-angle-fill"></i></button></td>
          <td>
            <div class="cell-with-avatar">
              ${avatarHtml(l.customer_name)}
              <div><div class="lead-name">${l.customer_name}</div><div class="lead-company">${waHtml}</div></div>
            </div>
          </td>
          <td>${l.product_name}</td>
          <td class="text-muted-2">${l.phone}</td>
          <td>${l.source}${l.social_platform ? " · " + l.social_platform : ""}</td>
          <td>${leadTypeBadge(l.lead_type)}</td>
          <td>${assignedHtml}</td>
          <td class="text-muted-2">${l.creator ? l.creator.name : "—"}</td>
          <td class="text-muted-2">${fmtDate(l.created_at)}</td>
          <td onclick="event.stopPropagation()">
            <div class="dropdown row-actions">
              <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item view-lead-action" href="#" data-id="${l.id}"><i class="bi bi-eye me-2"></i>View details</a></li>
                <li><a class="dropdown-item edit-lead-action" href="#" data-id="${l.id}"><i class="bi bi-pencil me-2"></i>Edit lead</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger delete-lead-action" href="#" data-id="${l.id}"><i class="bi bi-trash me-2"></i>Delete</a></li>
              </ul>
            </div>
          </td>
        </tr>`;
      }).join("");
    }

    renderActivePills();
    renderPagination(filtered.length, totalPages);
    bindRowEvents();
    updateBulkBar();
  }

  function renderActivePills() {
    const wrap = document.getElementById("activeFilterPills");
    wrap.innerHTML = "";
    const map = { leadType: "Lead type", source: "Source" };
    Object.keys(map).forEach(key => {
      if (activeFilters[key]) {
        wrap.insertAdjacentHTML("beforeend", `<span class="filter-pill">${map[key]}: ${activeFilters[key]} <button type="button" data-clear="${key}"><i class="bi bi-x"></i></button></span>`);
      }
    });
    if (activeFilters.dateFrom || activeFilters.dateTo) {
      const label = activeFilters.dateFrom && activeFilters.dateTo
        ? `${fmtDate(activeFilters.dateFrom)} – ${fmtDate(activeFilters.dateTo)}`
        : activeFilters.dateFrom ? `From ${fmtDate(activeFilters.dateFrom)}` : `Until ${fmtDate(activeFilters.dateTo)}`;
      wrap.insertAdjacentHTML("beforeend", `<span class="filter-pill">Date: ${label} <button type="button" data-clear="date"><i class="bi bi-x"></i></button></span>`);
    }
    if (viewAsAdminId !== null) {
      const admin = CFG.admins.find(a => a.id === viewAsAdminId);
      wrap.insertAdjacentHTML("beforeend", `<span class="filter-pill">User: ${admin ? admin.name : viewAsAdminId} <button type="button" data-clear="viewAs"><i class="bi bi-x"></i></button></span>`);
    }
    wrap.querySelectorAll("[data-clear]").forEach(btn => {
      btn.addEventListener("click", () => {
        if (btn.dataset.clear === "date") {
          activeFilters.dateFrom = "";
          activeFilters.dateTo = "";
          document.getElementById("filterDateFrom").value = "";
          document.getElementById("filterDateTo").value = "";
        } else if (btn.dataset.clear === "viewAs") {
          viewAsAdminId = null;
          updateViewAsChip();
        } else {
          activeFilters[btn.dataset.clear] = "";
          const el = document.getElementById(btn.dataset.clear === "leadType" ? "filterLeadType" : "filterSource");
          if (el) el.value = "";
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
    document.querySelectorAll("[data-pin-id]").forEach(btn => {
      btn.addEventListener("click", async () => {
        const id = parseInt(btn.dataset.pinId, 10);
        const res = await apiFetch(CFG.routes.togglePin.replace("__ID__", id), { method: "POST" });
        if (res.success) {
          const lead = leads.find(l => l.id === id);
          lead.is_pinned = res.is_pinned;
          renderAll();
        }
      });
    });
    document.querySelectorAll(".view-lead-action").forEach(a => a.addEventListener("click", e => { e.preventDefault(); openLeadDetail(parseInt(a.dataset.id, 10)); }));
    document.querySelectorAll(".edit-lead-action").forEach(a => a.addEventListener("click", e => { e.preventDefault(); openLeadModal(parseInt(a.dataset.id, 10)); }));
    document.querySelectorAll(".delete-lead-action").forEach(a => {
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
    if (selectedIds.size > 0) {
      bar.classList.remove("d-none");
      document.getElementById("bulkCount").textContent = selectedIds.size;
    } else {
      bar.classList.add("d-none");
    }
  }

  /* ---------------- Filters / tabs wiring ---------------- */

  function initFilters() {
    document.getElementById("filterSearch").addEventListener("input", e => { activeFilters.search = e.target.value; currentPage = 1; renderTable(); });
    document.getElementById("filterLeadType").addEventListener("change", e => { activeFilters.leadType = e.target.value; currentPage = 1; renderTable(); });
    document.getElementById("filterSource").addEventListener("change", e => { activeFilters.source = e.target.value; currentPage = 1; renderTable(); });
    document.getElementById("filterDateFrom").addEventListener("change", e => { activeFilters.dateFrom = e.target.value; currentPage = 1; renderTable(); });
    document.getElementById("filterDateTo").addEventListener("change", e => { activeFilters.dateTo = e.target.value; currentPage = 1; renderTable(); });
    document.getElementById("clearFiltersBtn").addEventListener("click", () => {
      activeFilters = { search: "", leadType: "", source: "", dateFrom: "", dateTo: "" };
      viewAsAdminId = null;
      updateViewAsChip();
      ["filterSearch", "filterLeadType", "filterSource", "filterDateFrom", "filterDateTo"].forEach(id => document.getElementById(id).value = "");
      currentPage = 1;
      renderTable();
    });
    document.querySelectorAll("#leadCategoryTabs .nav-link").forEach(tab => {
      tab.addEventListener("click", e => {
        e.preventDefault();
        document.querySelectorAll("#leadCategoryTabs .nav-link").forEach(t => t.classList.remove("active"));
        tab.classList.add("active");
        activeCategory = tab.dataset.category;
        currentPage = 1;
        renderTable();
      });
    });
  }

  function initBulkActions() {
    document.getElementById("bulkClearBtn").addEventListener("click", () => { selectedIds.clear(); renderTable(); });
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
        leads = leads.filter(l => !ids.includes(l.id));
        selectedIds.clear();
        renderAll();
        toast(res.message || "Deleted", "dark");
      }
      bootstrap.Modal.getOrCreateInstance(document.getElementById("deleteConfirmModal")).hide();
    });
  }

  /* ---------------- Assign-to multi-select ---------------- */

  function renderAssignOptions(filterTerm) {
    const box = document.getElementById("assignMsOptions");
    const term = (filterTerm || "").toLowerCase();
    const list = CFG.admins.filter(a => a.name.toLowerCase().includes(term));
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
    if (selectedAssignIds.size === 0) {
      chips.innerHTML = `<span class="placeholder">Select employees...</span>`;
      return;
    }
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

  /* ---------------- "View leads by user" single-select (super_admin only) ----------------
     Same ms-toggle/ms-panel widget as "Assign to" above, but single-select
     and each option carries a live lead-count badge (created_by OR assigned
     to that user, matching the backend's visibleTo() definition). */

  function renderViewAsOptions(filterTerm) {
    const box = document.getElementById("viewAsMsOptions");
    if (!box) return;
    const term = (filterTerm || "").toLowerCase();
    const list = CFG.admins.filter(a => a.name.toLowerCase().includes(term));

    const allOptionHtml = `
      <label class="ms-option ${viewAsAdminId === null ? "active" : ""}" data-view-as="">
        <i class="bi bi-people"></i> All users
        <span class="count-badge">${leads.length}</span>
      </label>`;

    box.innerHTML = allOptionHtml + (list.map(a => `
      <label class="ms-option ${viewAsAdminId === a.id ? "active" : ""}" data-view-as="${a.id}">
        ${avatarHtml(a.name, "sm")} ${a.name}
        <span class="count-badge">${adminLeadCount(a.id)}</span>
      </label>`).join("") || `<div class="fs-13 text-muted-2 p-2">No users found</div>`);

    box.querySelectorAll("[data-view-as]").forEach(el => {
      el.addEventListener("click", () => {
        const val = el.dataset.viewAs;
        viewAsAdminId = val === "" ? null : parseInt(val, 10);
        updateViewAsChip();
        document.getElementById("viewAsMsPanel").classList.remove("show");
        currentPage = 1;
        renderTable();
      });
    });
  }

  function updateViewAsChip() {
    const chips = document.getElementById("viewAsMsChips");
    if (!chips) return;
    if (viewAsAdminId === null) {
      chips.innerHTML = `<span class="placeholder"><i class="bi bi-people me-1"></i>View by user...</span>`;
    } else {
      const admin = CFG.admins.find(a => a.id === viewAsAdminId);
      chips.innerHTML = `<span class="chip">${admin ? admin.name : viewAsAdminId}</span>`;
    }
  }

  function initViewAsFilter() {
    if (!CFG.isSuperAdmin) return;
    const toggle = document.getElementById("viewAsMsToggle");
    const panel = document.getElementById("viewAsMsPanel");
    if (!toggle || !panel) return;
    toggle.addEventListener("click", () => {
      panel.classList.toggle("show");
      if (panel.classList.contains("show")) {
        document.getElementById("viewAsMsSearch").value = "";
        renderViewAsOptions("");
        document.getElementById("viewAsMsSearch").focus();
      }
    });
    document.getElementById("viewAsMsSearch").addEventListener("input", e => renderViewAsOptions(e.target.value));
    document.addEventListener("click", e => {
      if (!document.getElementById("viewAsMsControl").contains(e.target)) panel.classList.remove("show");
    });
  }

  /* ---------------- Add / Edit lead modal ---------------- */

  function toggleSocialPlatform() {
    document.getElementById("socialPlatformWrap").style.display =
      document.getElementById("leadSource").value === "Social Media" ? "block" : "none";
  }

  function openLeadModal(id) {
    const form = document.getElementById("leadForm");
    form.reset();
    selectedAssignIds = new Set();
    document.getElementById("leadId").value = "";

    if (id) {
      const lead = leads.find(l => l.id === id);
      document.getElementById("leadModalTitle").textContent = "Edit lead";
      document.getElementById("leadSubmitBtn").textContent = "Save changes";
      document.getElementById("leadId").value = lead.id;
      document.getElementById("leadCustomerName").value = lead.customer_name;
      document.getElementById("leadProductName").value = lead.product_name;
      document.getElementById("leadWhatsapp").value = lead.whatsapp || "";
      document.getElementById("leadPhone").value = lead.phone;
      document.getElementById("leadSource").value = lead.source;
      document.getElementById("leadSocialPlatform").value = lead.social_platform || "";
      document.getElementById("leadType").value = lead.lead_type;
      (lead.assigned_admins || []).forEach(a => selectedAssignIds.add(a.id));
    } else {
      document.getElementById("leadModalTitle").textContent = "Add new lead";
      document.getElementById("leadSubmitBtn").textContent = "Add lead";
    }
    toggleSocialPlatform();
    renderAssignChips();
    bootstrap.Modal.getOrCreateInstance(document.getElementById("leadModal")).show();
  }

  function initLeadForm() {
    document.getElementById("openAddLeadBtn").addEventListener("click", () => openLeadModal(null));
    document.getElementById("leadSource").addEventListener("change", toggleSocialPlatform);

    document.getElementById("leadForm").addEventListener("submit", async e => {
      e.preventDefault();
      const id = document.getElementById("leadId").value;
      const payload = {
        customer_name: document.getElementById("leadCustomerName").value.trim(),
        product_name: document.getElementById("leadProductName").value.trim(),
        whatsapp: document.getElementById("leadWhatsapp").value.trim(),
        phone: document.getElementById("leadPhone").value.trim(),
        source: document.getElementById("leadSource").value,
        social_platform: document.getElementById("leadSocialPlatform").value,
        lead_type: document.getElementById("leadType").value,
        assigned_admins: Array.from(selectedAssignIds),
      };

      const url = id ? CFG.routes.update.replace("__ID__", id) : CFG.routes.store;
      const res = await apiFetch(url, {
        method: id ? "PUT" : "POST",
        headers: csrfHeaders({ "Content-Type": "application/json" }),
        body: JSON.stringify(payload),
      });

      if (!res.success) {
        const firstError = res.errors ? Object.values(res.errors)[0][0] : "Something went wrong.";
        toast(firstError, "danger");
        return;
      }

      if (id) {
        leads = leads.map(l => (l.id == id ? res.lead : l));
      } else {
        leads.unshift(res.lead);
      }
      renderAll();
      bootstrap.Modal.getOrCreateInstance(document.getElementById("leadModal")).hide();
      toast(res.message);
    });
  }

  /* ---------------- Lead detail offcanvas + reminders ---------------- */

  async function openLeadDetail(id) {
    const res = await apiFetch(CFG.routes.show.replace("__ID__", id));
    if (!res.success) return;
    const lead = res.lead;
    currentDetailLeadId = lead.id;

    document.getElementById("ldAvatar").innerHTML = avatarHtml(lead.customer_name, "lg");
    document.getElementById("ldName").textContent = lead.customer_name;
    document.getElementById("ldProduct").textContent = lead.product_name;

    const ldWhatsappEl = document.getElementById("ldWhatsapp");
    ldWhatsappEl.innerHTML = lead.whatsapp
      ? `<a href="${waUrl(lead.whatsapp)}" class="wa-link" title="Open in WhatsApp"><i class="bi bi-whatsapp me-1"></i>${lead.whatsapp}</a>`
      : "—";

    document.getElementById("ldPhone").textContent = lead.phone;
    document.getElementById("ldSource").textContent = lead.source + (lead.social_platform ? " · " + lead.social_platform : "");
    document.getElementById("ldCreatedBy").textContent = lead.creator ? lead.creator.name : "—";
    document.getElementById("ldAssigned").textContent = (lead.assigned_admins || []).map(a => a.name).join(", ") || "Unassigned";

    const typeSelect = document.getElementById("ldTypeSelect");
    typeSelect.value = lead.lead_type;
    typeSelect.className = "status-select lt-" + lead.lead_type;

    const pinBtn = document.getElementById("ldPinBtn");
    pinBtn.classList.toggle("pinned", !!lead.is_pinned);
    pinBtn.onclick = async () => {
      const r = await apiFetch(CFG.routes.togglePin.replace("__ID__", lead.id), { method: "POST" });
      if (r.success) {
        lead.is_pinned = r.is_pinned;
        pinBtn.classList.toggle("pinned", r.is_pinned);
        const cached = leads.find(l => l.id === lead.id);
        if (cached) cached.is_pinned = r.is_pinned;
        renderAll();
      }
    };

    document.getElementById("remLeadId").value = lead.id;
    document.getElementById("remNote").value = "";
    // Date/time auto-select to "now" — still fully editable.
    const now = new Date();
    document.getElementById("remDate").value = now.toISOString().slice(0, 10);
    document.getElementById("remTime").value = now.toTimeString().slice(0, 5);
    document.querySelectorAll('input[name="remAptType"]').forEach(r => r.checked = false);
    // Default the kind back to "Reminder" every time the panel opens.
    document.getElementById("remKindReminder").checked = true;
    updateRemKindUI();

    const allEntries = lead.reminders || [];
    renderReminders(allEntries.filter(r => (r.type || "reminder") !== "note"));
    renderNotes(allEntries.filter(r => (r.type || "reminder") === "note"));
    renderAttachments(lead.attachments || []);

    bootstrap.Offcanvas.getOrCreateInstance(document.getElementById("leadDetailOffcanvas")).show();
  }

  // Exposed so /js/global-reminders.js can open a lead's detail panel
  // directly (no page reload) when the user is already on this page and
  // clicks "View lead" from the bell or the alarm popup.
  window.openLeadDetailById = openLeadDetail;

  /* ---------------- Reminder vs Note kind toggle ----------------
     Date/time apply to both kinds. Switching to "Note" only hides the
     appointment-type (WhatsApp/Meeting) radios, since that concept is
     specific to reminders, and relabels the submit button. Switching back
     to "Reminder" restores it. */

  function updateRemKindUI() {
    const isNote = document.getElementById("remKindNote").checked;
    document.getElementById("remAppointmentFields").style.display = isNote ? "none" : "";
    document.getElementById("remSubmitBtn").textContent = isNote ? "Add note" : "Add reminder";
  }

  function initReminderKindToggle() {
    document.querySelectorAll('input[name="remKind"]').forEach(r => r.addEventListener("change", updateRemKindUI));
  }

  /* ---------------- Inline status (lead type) update ----------------
     Changing the select in the offcanvas saves immediately via PATCH,
     then keeps the table row's color/badge and the offcanvas in sync
     without a page reload. */

  function initStatusSelect() {
    const sel = document.getElementById("ldTypeSelect");
    sel.addEventListener("change", async () => {
      if (!currentDetailLeadId) return;
      const previousClass = sel.className;
      sel.className = "status-select lt-" + sel.value;

      const res = await apiFetch(CFG.routes.updateType.replace("__ID__", currentDetailLeadId), {
        method: "PATCH",
        headers: csrfHeaders({ "Content-Type": "application/json" }),
        body: JSON.stringify({ lead_type: sel.value }),
      });

      if (res.success) {
        const cached = leads.find(l => l.id === currentDetailLeadId);
        if (cached) cached.lead_type = res.lead.lead_type;
        renderAll();
        toast("Status updated to " + res.lead.lead_type.charAt(0).toUpperCase() + res.lead.lead_type.slice(1));
      } else {
        sel.className = previousClass; // revert the colored pill if the save failed
        toast(res.errors ? Object.values(res.errors)[0][0] : "Could not update status.", "danger");
      }
    });
  }

  /* ---------------- Attachments ---------------- */

  function renderAttachments(attachments) {
    const box = document.getElementById("ldAttachments");
    if (!attachments || !attachments.length) {
      box.innerHTML = `<div class="empty-state py-3"><i class="bi bi-paperclip"></i><h6 class="fs-13 mb-0">No attachments yet</h6></div>`;
      return;
    }
    const sorted = [...attachments].sort((a, b) => (b.id - a.id));
    box.innerHTML = sorted.map(a => `
      <div class="attachment-item">
        <div class="attachment-icon"><i class="bi ${fileIcon(a.mime_type)}"></i></div>
        <div class="flex-grow-1" style="min-width:0">
          <div class="attachment-name">${a.file_name}</div>
          <div class="attachment-meta">${a.file_size_label || ""}${a.uploader ? " · " + a.uploader.name : ""}</div>
        </div>
        <a class="btn btn-sm btn-outline-secondary" href="${a.file_url}" target="_blank" rel="noopener" title="Download" style="width:30px;height:30px;padding:0;display:flex;align-items:center;justify-content:center">
          <i class="bi bi-download"></i>
        </a>
        <button class="btn btn-sm btn-outline-secondary" data-del-attachment="${a.id}" title="Delete" style="width:30px;height:30px;padding:0"><i class="bi bi-x"></i></button>
      </div>`).join("");

    box.querySelectorAll("[data-del-attachment]").forEach(el => {
      el.addEventListener("click", async () => {
        if (!confirm("Delete this attachment?")) return;
        const id = parseInt(el.dataset.delAttachment, 10);
        const res = await apiFetch(CFG.routes.attachmentDestroy.replace("__ID__", id), { method: "DELETE" });
        if (!res.success) {
          toast("Could not delete attachment.", "danger");
          return;
        }
        const leadId = currentDetailLeadId;
        await loadLeads();
        openLeadDetail(leadId);
      });
    });
  }

  // fetch() has no upload-progress event, so the actual upload (not just the
  // "done/not done" toast) uses XMLHttpRequest instead, whose xhr.upload
  // 'progress' event gives real bytes-sent-so-far — that's what drives the
  // progress bar under the "Add" button.

  function showUploadProgress(fileName) {
    document.getElementById("ldAttachmentUploadName").textContent = fileName;
    updateUploadProgress(0);
    document.getElementById("ldAttachmentUploadProgress").classList.remove("d-none");
  }

  function updateUploadProgress(pct) {
    document.getElementById("ldAttachmentUploadFill").style.width = pct + "%";
    document.getElementById("ldAttachmentUploadPct").textContent = pct + "%";
  }

  function hideUploadProgress() {
    document.getElementById("ldAttachmentUploadProgress").classList.add("d-none");
  }

  function setUploadButtonBusy(busy) {
    const btn = document.getElementById("ldAttachmentUploadBtn");
    btn.classList.toggle("disabled", busy);
  }

  function uploadAttachment(file) {
    const leadId = currentDetailLeadId;
    const input = document.getElementById("ldAttachmentInput");

    setUploadButtonBusy(true);
    showUploadProgress(file.name);

    const formData = new FormData();
    formData.append("lead_id", leadId);
    formData.append("attachment", file);

    const xhr = new XMLHttpRequest();
    xhr.open("POST", CFG.routes.attachmentStore);
    xhr.setRequestHeader("X-CSRF-TOKEN", CSRF);
    xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
    xhr.setRequestHeader("Accept", "application/json");

    xhr.upload.addEventListener("progress", e => {
      if (e.lengthComputable) updateUploadProgress(Math.round((e.loaded / e.total) * 100));
    });

    const finishUp = async (message, variant) => {
      input.value = "";
      setUploadButtonBusy(false);
      hideUploadProgress();
      if (message) toast(message, variant);
      await loadLeads();
      if (currentDetailLeadId === leadId) openLeadDetail(leadId);
    };

    xhr.onload = () => {
      if (xhr.status === 401 || xhr.status === 419) {
        finishUp("Your session expired, please log in again.", "danger");
        return;
      }
      let res;
      try { res = JSON.parse(xhr.responseText); } catch (err) { res = { success: false }; }

      if (!res.success) {
        finishUp(res.errors ? Object.values(res.errors)[0][0] : "Could not upload attachment.", "danger");
        return;
      }
      finishUp("Attachment uploaded");
    };

    xhr.onerror = () => finishUp("Upload failed — check your connection.", "danger");

    xhr.send(formData);
  }

  function initAttachmentUpload() {
    document.getElementById("ldAttachmentInput").addEventListener("change", e => {
      const file = e.target.files[0];
      if (!file || !currentDetailLeadId) return;
      uploadAttachment(file);
    });
  }

  /* ---------------- Share lead as a card ---------------- */

  function buildShareCard(lead) {
    const card = document.getElementById("shareCard");
    card.innerHTML = `
      <div class="slc-header">
        <div class="slc-brand">Dalal Adda · Lead</div>
        <div class="slc-avatar">${initials(lead.customer_name)}</div>
      </div>
      <div class="slc-body">
        <div class="slc-name">${lead.customer_name}</div>
        <div class="slc-product">${lead.product_name}</div>
        ${leadTypeBadge(lead.lead_type)}
        <div class="slc-row"><span>Phone</span><span>${lead.phone}</span></div>
        ${lead.whatsapp ? `<div class="slc-row"><span>WhatsApp</span><span>${lead.whatsapp}</span></div>` : ""}
        <div class="slc-row"><span>Source</span><span>${lead.source}${lead.social_platform ? " · " + lead.social_platform : ""}</span></div>
        <div class="slc-row"><span>Assigned to</span><span>${(lead.assigned_admins || []).map(a => a.name).join(", ") || "Unassigned"}</span></div>
        <div class="slc-footer">Shared from Dalal Adda CRM</div>
      </div>`;
  }

  function renderCardToBlob(cb) {
    if (typeof html2canvas !== "function") {
      toast("Could not generate the card image.", "danger");
      return;
    }
    html2canvas(document.getElementById("shareCard"), { backgroundColor: null, scale: 2 }).then(canvas => {
      canvas.toBlob(blob => { if (blob) cb(blob); });
    });
  }

  function initShare() {
    document.getElementById("ldShareBtn").addEventListener("click", () => {
      const lead = leads.find(l => l.id === currentDetailLeadId);
      if (!lead) return;
      buildShareCard(lead);
      bootstrap.Modal.getOrCreateInstance(document.getElementById("shareCardModal")).show();
    });

    document.getElementById("shareCardDownloadBtn").addEventListener("click", () => {
      renderCardToBlob(blob => {
        const a = document.createElement("a");
        a.href = URL.createObjectURL(blob);
        a.download = "lead-card.png";
        a.click();
      });
    });

    document.getElementById("shareCardShareBtn").addEventListener("click", () => {
      renderCardToBlob(async blob => {
        const file = new File([blob], "lead-card.png", { type: "image/png" });
        if (navigator.canShare && navigator.canShare({ files: [file] })) {
          try {
            await navigator.share({ files: [file], title: "Lead", text: "Sharing a lead" });
          } catch (err) { /* user cancelled the share sheet */ }
          return;
        }
        try {
          await navigator.clipboard.write([new ClipboardItem({ "image/png": blob })]);
          toast("Card image copied to clipboard");
        } catch (err) {
          const a = document.createElement("a");
          a.href = URL.createObjectURL(blob);
          a.download = "lead-card.png";
          a.click();
          toast("Sharing isn't supported here — image downloaded instead", "dark");
        }
      });
    });
  }

  function renderReminders(reminders) {
    const box = document.getElementById("ldReminders");
    if (!reminders.length) {
      box.innerHTML = `<div class="empty-state py-4"><i class="bi bi-bell-slash"></i><h6 class="fs-13">No reminders yet</h6></div>`;
      return;
    }
    const sorted = [...reminders].sort((a, b) => (b.id - a.id));
    box.innerHTML = sorted.map(r => {
      const missed = isMissed(r);
      const isSilent = r.status === "silent";
      const statusLabel = r.status === "completed" ? "Completed" : isSilent ? "Silenced" : missed ? "Missed" : "Scheduled";
      const canSilence = r.status === "scheduled" || r.status === "missed";
      return `
      <div class="reminder-item ${r.status === "completed" ? "completed" : ""} ${missed ? "missed-reminder" : ""} ${isSilent ? "silent-reminder" : ""}">
        <div class="reminder-check" data-complete-id="${r.id}"><i class="bi bi-check"></i></div>
        <div class="flex-grow-1">
          <div class="reminder-title">${r.note}</div>
          <div class="reminder-meta">
            ${r.reminder_date ? `<span><i class="bi bi-calendar3 me-1"></i>${fmtDate(r.reminder_date)}</span>` : ""}
            ${r.reminder_time ? `<span>${fmtTime(r.reminder_time)}</span>` : ""}
            ${r.appointment_type ? `<span><i class="bi bi-${r.appointment_type === "whatsapp" ? "whatsapp" : "camera-video"} me-1"></i>${r.appointment_type === "whatsapp" ? "WhatsApp" : "Meeting"}</span>` : ""}
            <span class="fw-600" style="color:${missed ? "var(--danger)" : "var(--text-muted)"}">${statusLabel}</span>
          </div>
        </div>
        ${canSilence ? `<button class="btn btn-sm btn-outline-secondary" data-silence-reminder="${r.id}" title="Silence" style="width:28px;height:28px;padding:0"><i class="bi bi-volume-mute"></i></button>` : ""}
        <button class="btn btn-sm btn-outline-secondary" data-del-reminder="${r.id}" style="width:28px;height:28px;padding:0"><i class="bi bi-x"></i></button>
      </div>`;
    }).join("");

    box.querySelectorAll("[data-complete-id]").forEach(el => {
      el.addEventListener("click", async () => {
        const id = parseInt(el.dataset.completeId, 10);
        await apiFetch(CFG.routes.reminderStatus.replace("__ID__", id), {
          method: "POST", headers: csrfHeaders({ "Content-Type": "application/json" }), body: JSON.stringify({ status: "completed" }),
        });
        await loadLeads();
        const leadId = document.getElementById("remLeadId").value;
        openLeadDetail(leadId);
        if (window.GlobalReminders) window.GlobalReminders.refresh();
      });
    });
    box.querySelectorAll("[data-silence-reminder]").forEach(el => {
      el.addEventListener("click", async () => {
        const id = parseInt(el.dataset.silenceReminder, 10);
        await apiFetch(CFG.routes.reminderStatus.replace("__ID__", id), {
          method: "POST", headers: csrfHeaders({ "Content-Type": "application/json" }), body: JSON.stringify({ status: "silent" }),
        });
        toast("Reminder silenced");
        await loadLeads();
        const leadId = document.getElementById("remLeadId").value;
        openLeadDetail(leadId);
        if (window.GlobalReminders) window.GlobalReminders.refresh(); // update the bell right away
      });
    });
    box.querySelectorAll("[data-del-reminder]").forEach(el => {
      el.addEventListener("click", async () => {
        const id = parseInt(el.dataset.delReminder, 10);
        await apiFetch(CFG.routes.reminderDestroy.replace("__ID__", id), { method: "DELETE" });
        await loadLeads();
        const leadId = document.getElementById("remLeadId").value;
        openLeadDetail(leadId);
        if (window.GlobalReminders) window.GlobalReminders.refresh();
      });
    });
  }

  // Notes list — same visual language as reminders (.reminder-item). A note
  // can carry a date/time too (shown as plain info, no missed/silence logic
  // since notes never alert). Delete only — no complete/silence actions.
  function renderNotes(notes) {
    const box = document.getElementById("ldNotes");
    if (!notes.length) {
      box.innerHTML = `<div class="empty-state py-4"><i class="bi bi-sticky"></i><h6 class="fs-13">No notes yet</h6></div>`;
      return;
    }
    const sorted = [...notes].sort((a, b) => (b.id - a.id));
    box.innerHTML = sorted.map(n => `
      <div class="reminder-item">
        <div class="flex-grow-1">
          <div class="reminder-title">${n.note}</div>
          ${(n.reminder_date || n.reminder_time) ? `<div class="reminder-meta">
            ${n.reminder_date ? `<span><i class="bi bi-calendar3 me-1"></i>${fmtDate(n.reminder_date)}</span>` : ""}
            ${n.reminder_time ? `<span>${fmtTime(n.reminder_time)}</span>` : ""}
          </div>` : ""}
        </div>
        <button class="btn btn-sm btn-outline-secondary" data-del-note="${n.id}" style="width:28px;height:28px;padding:0"><i class="bi bi-x"></i></button>
      </div>`).join("");

    box.querySelectorAll("[data-del-note]").forEach(el => {
      el.addEventListener("click", async () => {
        const id = parseInt(el.dataset.delNote, 10);
        await apiFetch(CFG.routes.reminderDestroy.replace("__ID__", id), { method: "DELETE" });
        await loadLeads();
        const leadId = document.getElementById("remLeadId").value;
        openLeadDetail(leadId);
      });
    });
  }

  function initReminderForm() {
    document.getElementById("reminderForm").addEventListener("submit", async e => {
      e.preventDefault();
      const leadId = document.getElementById("remLeadId").value;
      const kind = document.querySelector('input[name="remKind"]:checked').value; // "reminder" | "note"
      const aptEl = document.querySelector('input[name="remAptType"]:checked');
      const payload = {
        lead_id: leadId,
        type: kind,
        note: document.getElementById("remNote").value.trim(),
        reminder_date: document.getElementById("remDate").value || null,
        reminder_time: document.getElementById("remTime").value || null,
        appointment_type: kind === "note" ? null : (aptEl ? aptEl.value : null),
      };
      const res = await apiFetch(CFG.routes.reminderStore, {
        method: "POST", headers: csrfHeaders({ "Content-Type": "application/json" }), body: JSON.stringify(payload),
      });
      if (!res.success) {
        toast(res.errors ? Object.values(res.errors)[0][0] : `Could not add ${kind === "note" ? "note" : "reminder"}.`, "danger");
        return;
      }
      toast(kind === "note" ? "Note added" : "Reminder added");
      await loadLeads();
      openLeadDetail(leadId);
      if (kind === "reminder" && window.GlobalReminders) window.GlobalReminders.refresh();
    });
  }

  /* ---------------- Deep link: /admin/leads?lead=ID opens that lead ----------------
     Used by the global bell/alarm popup's "View lead" action when the user
     wasn't already on this page. */

  function openLeadFromQueryString() {
    const params = new URLSearchParams(window.location.search);
    const leadId = params.get("lead");
    if (!leadId) return;
    openLeadDetail(parseInt(leadId, 10));
    // Clean the URL so a refresh doesn't reopen it and it's shareable-looking.
    params.delete("lead");
    const newUrl = window.location.pathname + (params.toString() ? `?${params}` : "");
    window.history.replaceState({}, "", newUrl);
  }

  /* ---------------- Boot ---------------- */

  document.addEventListener("DOMContentLoaded", () => {
    initFilters();
    initBulkActions();
    initAssignMultiSelect();
    initViewAsFilter();
    initLeadForm();
    initReminderForm();
    initReminderKindToggle();
    initStatusSelect();
    initShare();
    initAttachmentUpload();
    loadLeads().then(openLeadFromQueryString);
  });
})();
</script>

@endsection
