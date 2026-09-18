<div class="page-content">
      <!-- ===================== LEADS ===================== -->
      <section class="page-section active" id="page-leads">
        <div class="page-header">
          <div>
            <span class="breadcrumb-eyebrow">Pipeline</span>
            <h1>Leads</h1>
            <p>Track, qualify and follow up with every lead in one place.</p>
          </div>
          <div class="page-header-actions d-flex gap-2">
            <button class="btn btn-outline-secondary"><i class="bi bi-upload me-1"></i>Import</button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLeadModal"><i class="bi bi-plus-lg me-1"></i>Add Lead</button>
          </div>
        </div>

        <!-- Filter bar -->
        <div class="filter-bar">
          <div class="filter-search">
            <i class="bi bi-search"></i>
            <input type="text" class="form-control" id="filterSearch" placeholder="Search by name, company or email">
          </div>
          <select class="form-select" id="filterStatus" style="max-width:150px">
            <option value="">All statuses</option>
            <option>New</option><option>Contacted</option><option>Qualified</option><option>Unqualified</option><option>Converted</option>
          </select>
          <select class="form-select" id="filterSource" style="max-width:150px">
            <option value="">All sources</option>
          </select>
          <select class="form-select" id="filterOwner" style="max-width:160px">
            <option value="">All owners</option>
          </select>
          <button class="btn btn-light-soft btn-sm" id="clearFiltersBtn"><i class="bi bi-x-circle me-1"></i>Clear</button>
          <div class="d-flex flex-wrap gap-2 ms-1" id="activeFilterPills"></div>
        </div>

        <!-- Bulk action bar -->
        <div class="filter-bar d-none" id="bulkBar" style="background:var(--primary-50); border-color:var(--primary-100)">
          <span class="fw-600 fs-13" style="color:var(--primary)"><span id="bulkCount">0</span> lead(s) selected</span>
          <div class="ms-auto d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary bg-white"><i class="bi bi-tag me-1"></i>Assign owner</button>
            <button class="btn btn-sm btn-outline-secondary bg-white" id="bulkDeleteBtn"><i class="bi bi-trash me-1"></i>Delete</button>
            <button class="btn btn-sm btn-light-soft" id="bulkClearBtn">Cancel</button>
          </div>
        </div>

        <div class="section-card">
          <div class="section-card-head">
            <h2>All Leads</h2>
            <span class="badge-status status-contacted" id="leadsCountBadge">0 leads</span>
          </div>
          <div class="table-wrap">
            <table class="crm-table">
              <thead>
                <tr>
                  <th><input class="form-check-input" type="checkbox" id="selectAllCheck"></th>
                  <th>Lead</th>
                  <th>Email</th>
                  <th>Phone</th>
                  <th>Status</th>
                  <th>Source</th>
                  <th>Owner</th>
                  <th>Score</th>
                  <th>Last Activity</th>
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