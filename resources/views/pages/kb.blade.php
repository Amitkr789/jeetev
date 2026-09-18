@extends('layouts.app')
@section('title', 'Knowledge Base | Dalal Adda')

@section('content')

<style>
  /* ---- Status badges ---- */
  .badge-status.kb-draft            { background: var(--warning-bg); color: var(--warning); }
  .badge-status.kb-pending_approval { background: var(--info-bg);    color: var(--info); }
  .badge-status.kb-published        { background: var(--success-bg); color: var(--success); }
  .badge-status.kb-archived         { background: var(--danger-bg);  color: var(--danger); }

  .crm-table tbody tr[data-kb-status="draft"]            { background: var(--warning-bg); }
  .crm-table tbody tr[data-kb-status="pending_approval"] { background: var(--info-bg); }
  .crm-table tbody tr[data-kb-status="published"]        { background: #fff; }
  .crm-table tbody tr[data-kb-status="archived"]         { background: var(--danger-bg); opacity: .7; }
  .crm-table tbody tr[data-kb-status]:hover              { filter: brightness(0.97); cursor: pointer; }

  /* ---- Type badge (pill) ---- */
  .badge-type {
    display: inline-flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 700;
    padding: 3px 9px; border-radius: 20px; background: var(--primary-50); color: var(--primary);
    text-transform: uppercase; letter-spacing: .3px; white-space: nowrap;
  }

  /* ---- Bill/lead-type style radio group (type picker) ---- */
  .apt-type-group { display: flex; gap: 8px; flex-wrap: wrap; }
  .apt-type-option { flex: 1 1 auto; min-width: 100px; }
  .apt-type-option input { display: none; }
  .apt-type-option label {
    display: flex; align-items: center; justify-content: center; gap: 6px;
    border: 1.5px solid var(--border); border-radius: 10px; padding: 9px; font-size: 12.5px; font-weight: 600;
    color: var(--text-muted); cursor: pointer; background: #FBFBFE; text-align: center;
  }
  .apt-type-option input:checked + label { border-color: var(--primary); color: var(--primary); background: var(--primary-50); }

  /* ---- Searchable single-select (product picker) — same pattern used elsewhere ---- */
  .ms-control { position: relative; }
  .ms-toggle {
    width: 100%; text-align: left; background: #FBFBFE; border: 1.5px solid var(--border);
    border-radius: 10px; padding: 10px 13px; font-size: 13.5px; color: var(--text);
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
  }
  .ms-toggle .chips { display: flex; flex-wrap: wrap; gap: 5px; }
  .ms-toggle .chip { background: var(--primary-50); color: var(--primary); font-size: 12px; font-weight: 700; padding: 3px 9px; border-radius: 20px; }
  .ms-toggle .placeholder { color: #A7ABC2; }
  .ms-panel {
    display: none; position: absolute; top: calc(100% + 6px); left: 0; right: 0; z-index: 50;
    background: #fff; border: 1px solid var(--border); border-radius: 12px; box-shadow: var(--shadow-lg);
    padding: 10px; max-height: 240px; overflow-y: auto;
  }
  .ms-panel.show { display: block; }
  .ms-panel input.ms-search { margin-bottom: 8px; }
  .ms-option { display: flex; align-items: center; justify-content: space-between; gap: 9px; padding: 8px 7px; border-radius: 7px; font-size: 13px; cursor: pointer; }
  .ms-option:hover { background: var(--primary-50); }
  .ms-option .ms-option-meta { font-size: 11px; color: var(--text-muted); }

  /* ---- Tag chip input ---- */
  .tag-chip-input {
    display: flex; flex-wrap: wrap; gap: 6px; border: 1.5px solid var(--border); border-radius: 10px;
    padding: 8px 10px; background: #FBFBFE; align-items: center;
  }
  .tag-chip-input input { border: none; outline: none; flex: 1; min-width: 120px; font-size: 13px; background: transparent; }
  .tag-chip {
    background: var(--primary-50); color: var(--primary); font-size: 11.5px; font-weight: 700;
    padding: 3px 9px; border-radius: 20px; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap;
  }
  .tag-chip button { border: none; background: none; color: inherit; padding: 0; line-height: 1; font-size: 13px; }

  /* ---- Switch-style row (AI ready toggle) ---- */
  .switch-row { display: flex; align-items: center; justify-content: space-between; border: 1.5px solid var(--border); border-radius: 10px; padding: 10px 13px; }
  .switch-row .form-switch { margin: 0; }

  /* ---- Media cards ---- */
  .media-card { display: flex; align-items: center; gap: 10px; padding: 9px 11px; border: 1px solid var(--border); border-radius: 10px; margin-bottom: 8px; }
  .media-card .media-icon { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: var(--primary-50); color: var(--primary); font-size: 16px; flex-shrink: 0; }
  .media-card .media-name { font-size: 12.5px; font-weight: 600; color: var(--text); word-break: break-all; }
  .media-card .media-meta { font-size: 11px; color: var(--text-muted); }
  .media-card .media-actions { margin-left: auto; display: flex; gap: 2px; flex-shrink: 0; }
  .media-card .media-actions a, .media-card .media-actions button { border: none; background: none; color: var(--text-muted); padding: 5px 7px; }
  .media-card .media-actions button:hover { color: var(--danger); }
  .media-card .media-actions a:hover { color: var(--primary); }

  /* ---- Version history ---- */
  .version-item { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 9px 4px; border-bottom: 1px dashed var(--border); font-size: 12.5px; }
  .version-item:last-child { border-bottom: none; }
  .version-item .v-num { font-weight: 700; color: var(--text); }
  .version-item .v-meta { color: var(--text-muted); font-size: 11.5px; }

  /* ---- Related-articles-for-this-product list ---- */
  .related-article-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 7px 8px; border-radius: 8px; cursor: pointer; font-size: 12.5px; }
  .related-article-row:hover { background: var(--primary-50); }
  .related-article-row .ra-title { font-weight: 600; color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

  /* ---- AI context preview ---- */
  .ai-context-box {
    background: #0F172A; color: #E2E8F0; border-radius: 10px; padding: 12px 14px;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11.5px;
    white-space: pre-wrap; max-height: 220px; overflow-y: auto; line-height: 1.5;
  }

  /* ---- Category manage list ---- */
  .manage-list { max-height: 220px; overflow-y: auto; border-top: 1px solid var(--border); margin-top: 14px; padding-top: 10px; }
  .manage-list-row { display: flex; align-items: center; gap: 10px; padding: 8px 4px; border-radius: 8px; }
  .manage-list-row:hover { background: var(--primary-50); }
  .manage-list-row .mlr-name { font-size: 13px; font-weight: 700; color: var(--text); }
  .manage-list-row .mlr-sub { font-size: 11.5px; color: var(--text-muted); }
  .manage-list-row .mlr-actions { margin-left: auto; display: flex; gap: 4px; }
  .manage-list-row .mlr-actions button { background: none; border: none; color: var(--text-muted); padding: 4px 6px; }
  .manage-list-row .mlr-actions button:hover { color: var(--primary); }
  .manage-list-row .mlr-actions button.danger:hover { color: var(--danger); }
  .inactive-pill { background: var(--danger-bg); color: var(--danger); font-size: 10px; font-weight: 800; padding: 2px 7px; border-radius: 10px; margin-left: 6px; }

  .kb-modal-dialog .modal-content { max-height: 90vh; }
  .kb-modal-dialog .modal-body { overflow-y: auto; }
</style>

<div class="page-content">
  <section class="page-section active" id="page-kb">

    <div class="page-header">
      <div>
        <span class="breadcrumb-eyebrow">Support</span>
        <h1>Knowledge Base</h1>
        <p>FAQs, policies, pricing, products &amp; services — the single source your AI chatbot will answer from.</p>
      </div>
      <div class="page-header-actions d-flex gap-2">
        <button class="btn btn-outline-secondary" id="openCategoryModalBtn"><i class="bi bi-folder me-1"></i>Categories</button>
        <button class="btn btn-outline-secondary" id="regenerateCatalogBtn" title="Rebuild the 'Our Product Categories' FAQ from the live product list"><i class="bi bi-arrow-repeat me-1"></i>Regenerate Catalog</button>
        <button class="btn btn-primary" id="openAddArticleBtn"><i class="bi bi-plus-lg me-1"></i>Add Article</button>
      </div>
    </div>

    <!-- Stat cards -->
    <div class="row g-3 mb-3">
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div><div class="stat-label">Total Articles</div><div class="stat-value" id="statTotal">0</div></div>
          <div class="stat-icon" style="background:var(--primary-50); color:var(--primary)"><i class="bi bi-journal-text"></i></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div><div class="stat-label">Published</div><div class="stat-value" id="statPublished">0</div></div>
          <div class="stat-icon" style="background:var(--success-bg); color:var(--success)"><i class="bi bi-check-circle"></i></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div><div class="stat-label">Pending Approval</div><div class="stat-value" id="statPending">0</div></div>
          <div class="stat-icon" style="background:var(--info-bg); color:var(--info)"><i class="bi bi-hourglass-split"></i></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div><div class="stat-label">Drafts</div><div class="stat-value" id="statDraft">0</div></div>
          <div class="stat-icon" style="background:var(--warning-bg); color:var(--warning)"><i class="bi bi-pencil-square"></i></div>
        </div>
      </div>
    </div>

    <!-- Type tabs -->
    <ul class="nav lead-tabs mb-3" id="kbTypeTabs">
      <li class="nav-item"><a class="nav-link active" data-type="" href="#">All</a></li>
      <li class="nav-item"><a class="nav-link" data-type="faq" href="#">FAQ</a></li>
      <li class="nav-item"><a class="nav-link" data-type="policy" href="#">Policy</a></li>
      <li class="nav-item"><a class="nav-link" data-type="pricing" href="#">Pricing</a></li>
      <li class="nav-item"><a class="nav-link" data-type="product" href="#">Product</a></li>
      <li class="nav-item"><a class="nav-link" data-type="service" href="#">Service</a></li>
    </ul>

    <!-- Filter bar -->
    <div class="filter-bar">
      <div class="filter-search">
        <i class="bi bi-search"></i>
        <input type="text" class="form-control" id="filterSearch" placeholder="Search title, question, answer or tag...">
      </div>
      <select class="form-select" id="filterCategory" style="max-width:180px">
        <option value="">All Categories</option>
        @foreach($categories as $c)
          <option value="{{ $c->id }}">{{ $c->name }}</option>
        @endforeach
      </select>
      <select class="form-select" id="filterProduct" style="max-width:200px">
        <option value="">All Products</option>
        @foreach($products as $p)
          <option value="{{ $p['id'] }}">{{ $p['name'] }}</option>
        @endforeach
      </select>
      <select class="form-select" id="filterStatus" style="max-width:170px">
        <option value="">All Statuses</option>
        <option value="draft">Draft</option>
        <option value="pending_approval">Pending Approval</option>
        <option value="published">Published</option>
        <option value="archived">Archived</option>
      </select>
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
              <th>Title</th>
              <th>Type</th>
              <th>Category</th>
              <th>Product</th>
              <th>Tags</th>
              <th>Status</th>
              <th>Ver.</th>
              <th>Updated</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="kbTbody"></tbody>
        </table>
      </div>
      <div class="crm-pagination">
        <span class="page-info" id="pageInfo"></span>
        <div class="d-flex gap-1" id="pageButtons"></div>
      </div>
    </div>
  </section>
</div>

<!-- ===================== ADD / EDIT ARTICLE MODAL ===================== -->
<div class="modal fade" id="articleModal" tabindex="-1">
  <div class="modal-dialog modal-xl bill-modal-dialog">
    <div class="modal-content">
      <form id="articleForm">
        <input type="hidden" id="articleId">
        <div class="modal-header">
          <h5 class="modal-title" id="articleModalTitle">Add article</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">

          <label class="form-label">Type</label>
          <div class="apt-type-group mb-3" id="typeGroup">
            <div class="apt-type-option">
              <input type="radio" name="articleType" id="type_faq" value="faq" checked>
              <label for="type_faq"><i class="bi bi-question-circle me-1"></i>FAQ</label>
            </div>
            <div class="apt-type-option">
              <input type="radio" name="articleType" id="type_policy" value="policy">
              <label for="type_policy"><i class="bi bi-shield-check me-1"></i>Policy</label>
            </div>
            <div class="apt-type-option">
              <input type="radio" name="articleType" id="type_pricing" value="pricing">
              <label for="type_pricing"><i class="bi bi-tag me-1"></i>Pricing</label>
            </div>
            <div class="apt-type-option">
              <input type="radio" name="articleType" id="type_product" value="product">
              <label for="type_product"><i class="bi bi-box-seam me-1"></i>Product</label>
            </div>
            <div class="apt-type-option">
              <input type="radio" name="articleType" id="type_service" value="service">
              <label for="type_service"><i class="bi bi-gear me-1"></i>Service</label>
            </div>
          </div>

          <div class="row g-3 mb-1">
            <div class="col-6">
              <label class="form-label">Category</label>
              <select class="form-select" id="articleCategoryId">
                <option value="">— none —</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label">Title</label>
              <input type="text" class="form-control" id="articleTitle" required placeholder="Short internal title">
            </div>
          </div>

          <div class="mb-3" id="questionWrap">
            <label class="form-label">Question</label>
            <input type="text" class="form-control" id="articleQuestion" placeholder="What the customer actually asks">
          </div>

          <div class="mb-3">
            <label class="form-label">Answer</label>
            <textarea class="form-control" id="articleAnswer" rows="6" required placeholder="The full answer — this is what gets shown to the customer and fed to the AI"></textarea>
            <div class="mb-3" id="detailedDescWrap" style="display:none">
  <div class="d-flex justify-content-between align-items-center mb-1">
    <label class="form-label mb-0">Detailed description</label>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="useProductDescBtn">
      <i class="bi bi-magic me-1"></i>Use product's description
    </button>
  </div>
  <textarea class="form-control" id="articleDetailedDescription" rows="4"
    placeholder="Full spec / detailed overview — feeds the AI as extra context, separate from the short answer above"></textarea>
</div>
        </div>

          <div class="mb-3" id="productPickWrap" style="display:none">
            <label class="form-label">Linked product (catalog)</label>
            <div class="ms-control" id="productPickControl">
              <button type="button" class="ms-toggle" id="productPickToggle">
                <span class="chips" id="productPickLabel"><span class="placeholder">Search a product from inventory...</span></span>
                <i class="bi bi-chevron-down"></i>
              </button>
              <div class="ms-panel" id="productPickPanel">
                <input type="text" class="form-control ms-search" id="productPickSearch" placeholder="Search products...">
                <div id="productPickOptions"></div>
              </div>
            </div>
            <div class="fs-12 text-muted-2 mt-1" id="productLiveInfo"></div>
            <div class="mb-1" id="productPreviewBox" style="display:none">
  <div class="fs-12 text-muted-2 mb-1" id="productPreviewDesc"></div>
  <div class="d-flex gap-2 flex-wrap" id="productPreviewImages"></div>
</div>
          </div>

          <div class="row g-3 mb-3" id="priceOverrideWrap" style="display:none">
            <div class="col-6">
              <label class="form-label" id="priceOverrideLabel">Price</label>
              <input type="number" class="form-control" id="articlePriceOverride" min="0" step="0.01" placeholder="0.00">
              <div class="fs-11 text-muted-2 mt-1" id="priceOverrideHint"></div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Tags</label>
            <div class="tag-chip-input" id="tagChipInput">
              <input type="text" id="tagInputField" placeholder="Type a tag and press Enter...">
            </div>
          </div>

          <div class="row g-3">
            <div class="col-7">
              <div class="switch-row">
                <div>
                  <div class="fw-600 fs-13">AI Ready</div>
                  <div class="fs-11 text-muted-2">Allow the chatbot to use this article once published</div>
                </div>
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="articleAiReady" checked>
                </div>
              </div>
            </div>
            <div class="col-5">
              <label class="form-label">AI priority</label>
              <input type="number" class="form-control" id="articlePriority" min="0" step="1" value="0">
            </div>
          </div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="articleSubmitBtn">Create article</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===================== ARTICLE DETAIL OFFCANVAS ===================== -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="articleDetailOffcanvas" style="width:480px">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title">Article details</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body">

    <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
      <div id="adType"></div>
      <div id="adStatus"></div>
      <div class="ms-auto fs-12 text-muted-2">v<span id="adVersion">1</span></div>
    </div>
    <h5 class="mb-1" id="adTitle" style="font-size:16px"></h5>
    <div class="fs-12 text-muted-2 mb-3" id="adCategory"></div>

    <div class="card-flat p-3 mb-3" id="adQuestionWrap" style="display:none">
      <div class="fs-11 text-uppercase fw-700 text-muted-2 mb-1">Question</div>
      <div class="fs-13" id="adQuestion"></div>
    </div>

    <div class="card-flat p-3 mb-3">
      <div class="fs-11 text-uppercase fw-700 text-muted-2 mb-1">Answer</div>
      <div class="fs-13" id="adAnswer" style="white-space:pre-wrap"></div>
    </div>

    <div class="card-flat p-3 mb-3" id="adProductWrap" style="display:none">
      <div class="fs-11 text-uppercase fw-700 text-muted-2 mb-2">Catalog link</div>
      <div class="d-flex justify-content-between mb-1"><span class="text-muted-2 fs-13">Product</span><span class="fw-600 fs-13" id="adProductName">—</span></div>
      <div class="d-flex justify-content-between mb-1"><span class="text-muted-2 fs-13">Live price</span><span class="fw-600 fs-13" id="adLivePrice">—</span></div>
      <div class="d-flex justify-content-between"><span class="text-muted-2 fs-13">Live stock</span><span class="fw-600 fs-13" id="adLiveStock">—</span></div>
    </div>

    <div class="mb-3 d-none" id="adRelatedWrap">
      <div class="fs-11 text-uppercase fw-700 text-muted-2 mb-2">Other articles for this product</div>
      <div id="adRelatedList"></div>
    </div>

    <div class="mb-3" id="adTagsWrap">
      <div class="fs-11 text-uppercase fw-700 text-muted-2 mb-2">Tags</div>
      <div class="d-flex flex-wrap gap-1" id="adTags"></div>
    </div>

    <div class="card-flat p-3 mb-3">
      <div class="d-flex justify-content-between mb-1"><span class="text-muted-2 fs-13">Created by</span><span class="fw-600 fs-13" id="adCreator">—</span></div>
      <div class="d-flex justify-content-between mb-1"><span class="text-muted-2 fs-13">Approved by</span><span class="fw-600 fs-13" id="adApprover">—</span></div>
      <div class="d-flex justify-content-between"><span class="text-muted-2 fs-13">AI ready</span><span class="fw-600 fs-13" id="adAiReady">—</span></div>
    </div>

    <!-- Attachments -->
    <div class="mb-4">
     <div class="d-flex justify-content-between align-items-center mb-2">
  <h6 class="fs-13 fw-700 text-uppercase text-muted-2 mb-0">Attachments</h6>
  <div class="d-flex gap-2 align-items-center">
    <select class="form-select form-select-sm" id="mediaPurposeSelect" style="width:auto">
      <option value="general">General</option>
      <option value="catalog">Catalog / Brochure</option>
    </select>
    <label class="btn btn-sm btn-outline-secondary mb-0" for="mediaUploadInput"><i class="bi bi-upload me-1"></i>Upload</label>
    <input type="file" id="mediaUploadInput" accept="image/*,.pdf" class="d-none">
  </div>
</div>
      <div id="adMediaList"><div class="fs-12 text-muted-2">No files attached.</div></div>
    </div>

    <!-- AI context preview -->
    <div class="mb-4">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="fs-13 fw-700 text-uppercase text-muted-2 mb-0">AI context preview</h6>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="copyAiContextBtn"><i class="bi bi-clipboard me-1"></i>Copy</button>
      </div>
      <div class="ai-context-box" id="adAiContext"></div>
    </div>

    <!-- Version history -->
    <div class="mb-4">
      <h6 class="fs-13 fw-700 text-uppercase text-muted-2 mb-2">Version history</h6>
      <div class="card-flat p-2" id="adVersions"></div>
    </div>

    <!-- Workflow actions -->
    <div class="d-flex flex-wrap gap-2" id="adActions"></div>

  </div>
</div>

<!-- ===================== CATEGORY MANAGE MODAL ===================== -->
<div class="modal fade" id="categoryModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="categoryForm">
        <input type="hidden" id="categoryId">
        <div class="modal-header">
          <h5 class="modal-title" id="categoryModalTitle">Add category</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12"><label class="form-label">Name</label><input type="text" class="form-control" id="categoryName" required></div>
            <div class="col-8">
              <label class="form-label">Parent category</label>
              <select class="form-select" id="categoryParentId"><option value="">— top level —</option></select>
            </div>
            <div class="col-4">
              <label class="form-label">Status</label>
              <select class="form-select" id="categoryStatus">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
            <div class="col-12"><label class="form-label">Icon <span class="text-muted-2">(bootstrap-icons class, optional)</span></label><input type="text" class="form-control" id="categoryIcon" placeholder="bi-tag"></div>
          </div>
          <div class="d-flex justify-content-end mt-2">
            <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="categoryCancelEditBtn">Cancel edit</button>
          </div>
          <div class="manage-list" id="categoryManageList"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary" id="categorySubmitBtn">Add category</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===================== DELETE CONFIRM MODAL ===================== -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-content-compact">
    <div class="modal-content modal-content-danger">
      <div class="modal-header">
        <h5 class="modal-title">Delete article(s)?</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0">This will permanently delete the selected article(s) and any attached files. This can't be undone.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirmDeleteBtn">Delete</button>
      </div>
    </div>
  </div>
</div>

<script>
  window.KB_PAGE_DATA = {
    categories: @json($categories),
    tags: @json($tags),
    products: @json($products),
    canApprove: @json($canApprove),
    csrfToken: '{{ csrf_token() }}',
    routes: {
      data: '{{ route('kb.data') }}',
      show: '{{ url('admin/knowledge-base') }}/__ID__',
      store: '{{ route('kb.store') }}',
      update: '{{ url('admin/knowledge-base') }}/__ID__',
      destroy: '{{ url('admin/knowledge-base') }}/__ID__',
      bulkDelete: '{{ route('kb.bulkDestroy') }}',
      submit: '{{ url('admin/knowledge-base') }}/__ID__/submit',
      approve: '{{ url('admin/knowledge-base') }}/__ID__/approve',
      reject: '{{ url('admin/knowledge-base') }}/__ID__/reject',
      archive: '{{ url('admin/knowledge-base') }}/__ID__/archive',
      restoreVersion: '{{ url('admin/knowledge-base') }}/__ID__/restore-version',
      mediaUpload: '{{ url('admin/knowledge-base') }}/__ID__/media',
      mediaDestroy: '{{ url('admin/knowledge-base/media') }}/__ID__',
      categoryStore: '{{ route('kb.categories.store') }}',
      categoryUpdate: '{{ url('admin/knowledge-base/categories') }}/__ID__',
      categoryDestroy: '{{ url('admin/knowledge-base/categories') }}/__ID__',
      regenerateCatalog: '{{ route('kb.regenerateCatalog') }}',
    }
  };

(function () {
  "use strict";

  const CFG = window.KB_PAGE_DATA;
  const CSRF = CFG.csrfToken;

  const TYPE_LABELS = { faq: 'FAQ', policy: 'Policy', pricing: 'Pricing', product: 'Product', service: 'Service' };
  const TYPE_ICONS = { faq: 'bi-question-circle', policy: 'bi-shield-check', pricing: 'bi-tag', product: 'bi-box-seam', service: 'bi-gear' };
  const STATUS_LABELS = { draft: 'Draft', pending_approval: 'Pending Approval', published: 'Published', archived: 'Archived' };

  let articles = [];
  let categories = CFG.categories.slice();
  let activeType = "";
  let activeFilters = { search: "", category: "", product: "", status: "" };
  let currentPage = 1;
  const PAGE_SIZE = 8;
  let selectedIds = new Set();

  let selectedProductId = null;
  let tagsState = [];
  let currentDetailId = null;
  let editingCategoryId = null;

  /* ---------------- Helpers ---------------- */

  function escapeHtml(str) {
    return String(str ?? "").replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  }
  function fmtMoney(n) {
    if (n === null || n === undefined) return "—";
    return "₹" + Number(n).toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  function fmtDateTime(d) {
    if (!d) return "—";
    const dt = new Date(d.replace(" ", "T"));
    if (isNaN(dt)) return d;
    return dt.toLocaleString(undefined, { day: "numeric", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit" });
  }
  function fmtBytes(n) {
    if (!n) return "";
    if (n < 1024) return n + " B";
    if (n < 1024 * 1024) return (n / 1024).toFixed(1) + " KB";
    return (n / (1024 * 1024)).toFixed(1) + " MB";
  }
  function toast(message, variant) {
    let wrap = document.getElementById("kbToastWrap");
    if (!wrap) {
      wrap = document.createElement("div");
      wrap.id = "kbToastWrap";
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
  async function apiFetchForm(url, formData, method) {
    const res = await fetch(url, { method: method || "POST", headers: csrfHeaders({ "Accept": "application/json" }), body: formData });
    if (res.status === 401 || res.status === 419) { toast("Session expired, please log in again.", "danger"); throw new Error("unauthenticated"); }
    return res.json();
  }
  function firstError(res) { return res.errors ? Object.values(res.errors)[0][0] : (res.message || "Something went wrong."); }

  function typeBadge(type) {
    return `<span class="badge-type"><i class="bi ${TYPE_ICONS[type] || 'bi-file-text'}"></i> ${TYPE_LABELS[type] || type}</span>`;
  }
  function statusBadge(status) {
    return `<span class="badge-status kb-${status}">${STATUS_LABELS[status] || status}</span>`;
  }

  /* ---------------- Data fetch ---------------- */

  async function loadArticles() {
    const data = await apiFetch(CFG.routes.data);
    if (!data.success) return;
    articles = data.articles;
    renderAll();
  }

  function getFiltered() {
    return articles.filter(a => {
      const term = activeFilters.search.toLowerCase();
      const haystack = ((a.title || "") + (a.question || "") + (a.answer || "") + (a.tags || []).join(" ")).toLowerCase();
      const matchesSearch = !term || haystack.includes(term);
      const matchesType = !activeType || a.type === activeType;
      const matchesCategory = !activeFilters.category || String(a.category_id) === String(activeFilters.category);
      const matchesProduct = !activeFilters.product || String(a.product_id) === String(activeFilters.product);
      const matchesStatus = !activeFilters.status || a.status === activeFilters.status;
      return matchesSearch && matchesType && matchesCategory && matchesProduct && matchesStatus;
    });
  }

  function renderAll() { renderStats(); renderTable(); }

  function renderStats() {
    document.getElementById("statTotal").textContent = articles.length;
    document.getElementById("statPublished").textContent = articles.filter(a => a.status === "published").length;
    document.getElementById("statPending").textContent = articles.filter(a => a.status === "pending_approval").length;
    document.getElementById("statDraft").textContent = articles.filter(a => a.status === "draft").length;
  }

  function renderTable() {
    const filtered = getFiltered();
    const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
    currentPage = Math.min(currentPage, totalPages);
    const start = (currentPage - 1) * PAGE_SIZE;
    const pageItems = filtered.slice(start, start + PAGE_SIZE);
    const tbody = document.getElementById("kbTbody");

    if (pageItems.length === 0) {
      tbody.innerHTML = `<tr><td colspan="10"><div class="empty-state">
        <i class="bi bi-journal-text"></i><h6>No articles found</h6>
        <p class="fs-13 mb-0">Try adjusting filters, or add a new article.</p></div></td></tr>`;
    } else {
      tbody.innerHTML = pageItems.map(a => `
        <tr data-id="${a.id}" data-kb-status="${a.status}">
          <td onclick="event.stopPropagation()"><input class="form-check-input row-check" type="checkbox" data-id="${a.id}" ${selectedIds.has(a.id) ? "checked" : ""}></td>
          <td>
            <div class="lead-name">${escapeHtml(a.title)}</div>
            <div class="lead-company text-muted-2 fs-12">${escapeHtml(a.question || "")}</div>
          </td>
          <td>${typeBadge(a.type)}</td>
          <td class="text-muted-2">${escapeHtml(a.category_name || "—")}</td>
          <td class="text-muted-2">${a.type === 'product' ? escapeHtml(a.product_name || "—") : "—"}</td>
          <td>${(a.tags || []).slice(0, 3).map(t => `<span class="tag-chip">${escapeHtml(t)}</span>`).join(" ") || "—"}</td>
          <td>${statusBadge(a.status)}</td>
          <td class="text-muted-2">v${a.version}</td>
          <td class="text-muted-2">${fmtDateTime(a.updated_at)}</td>
          <td onclick="event.stopPropagation()">
            <div class="dropdown row-actions">
              <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item view-kb-action" href="#" data-id="${a.id}"><i class="bi bi-eye me-2"></i>View details</a></li>
                <li><a class="dropdown-item edit-kb-action" href="#" data-id="${a.id}"><i class="bi bi-pencil me-2"></i>Edit</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger delete-kb-action" href="#" data-id="${a.id}"><i class="bi bi-trash me-2"></i>Delete</a></li>
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
    document.querySelectorAll("#kbTbody tr[data-id]").forEach(row => {
      row.addEventListener("click", () => openDetail(parseInt(row.dataset.id, 10)));
    });
    document.querySelectorAll(".row-check").forEach(cb => {
      cb.addEventListener("change", () => {
        const id = parseInt(cb.dataset.id, 10);
        if (cb.checked) selectedIds.add(id); else selectedIds.delete(id);
        updateBulkBar();
      });
    });
    document.querySelectorAll(".view-kb-action").forEach(a => a.addEventListener("click", e => { e.preventDefault(); openDetail(parseInt(a.dataset.id, 10)); }));
    document.querySelectorAll(".edit-kb-action").forEach(a => a.addEventListener("click", e => { e.preventDefault(); openArticleModal(parseInt(a.dataset.id, 10)); }));
    document.querySelectorAll(".delete-kb-action").forEach(a => {
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

  /* ---------------- Filters / tabs / bulk ---------------- */

  function initFilters() {
    document.getElementById("filterSearch").addEventListener("input", e => { activeFilters.search = e.target.value; currentPage = 1; renderTable(); });
    document.getElementById("filterCategory").addEventListener("change", e => { activeFilters.category = e.target.value; currentPage = 1; renderTable(); });
    document.getElementById("filterProduct").addEventListener("change", e => { activeFilters.product = e.target.value; currentPage = 1; renderTable(); });
    document.getElementById("filterStatus").addEventListener("change", e => { activeFilters.status = e.target.value; currentPage = 1; renderTable(); });
    document.getElementById("clearFiltersBtn").addEventListener("click", () => {
      activeFilters = { search: "", category: "", product: "", status: "" };
      ["filterSearch", "filterCategory", "filterProduct", "filterStatus"].forEach(id => document.getElementById(id).value = "");
      currentPage = 1;
      renderTable();
    });
    document.querySelectorAll("#kbTypeTabs .nav-link").forEach(tab => {
      tab.addEventListener("click", e => {
        e.preventDefault();
        document.querySelectorAll("#kbTypeTabs .nav-link").forEach(t => t.classList.remove("active"));
        tab.classList.add("active");
        activeType = tab.dataset.type;
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
        articles = articles.filter(a => !ids.includes(a.id));
        selectedIds.clear();
        renderAll();
        toast(res.message || "Deleted", "dark");
      }
      bootstrap.Modal.getOrCreateInstance(document.getElementById("deleteConfirmModal")).hide();
    });
  }

  /* ---------------- Product picker (ms-control) ---------------- */

  function productLabel(p) { return `${p.name}${p.sku ? " — " + p.sku : ""}`; }

  function renderProductOptions(term) {
    const box = document.getElementById("productPickOptions");
    term = (term || "").toLowerCase();
    const list = CFG.products.filter(p => productLabel(p).toLowerCase().includes(term));
    box.innerHTML = list.length ? list.map(p => `
      <div class="ms-option" data-product-id="${p.id}">
        <span>${escapeHtml(productLabel(p))}</span>
        <span class="ms-option-meta">Stock: ${p.stock ?? "—"}</span>
      </div>`).join("") : `<div class="fs-13 text-muted-2 p-2">No products found</div>`;
    box.querySelectorAll("[data-product-id]").forEach(opt => {
      opt.addEventListener("click", () => selectProduct(parseInt(opt.dataset.productId, 10)));
    });
  }

  function selectProduct(id) {
  const product = CFG.products.find(p => p.id === id);
  if (!product) return;
  selectedProductId = id;
  document.getElementById("productPickLabel").innerHTML = `<span class="chip">${escapeHtml(productLabel(product))}</span>`;
  document.getElementById("productPickPanel").classList.remove("show");
  document.getElementById("productLiveInfo").innerHTML =
    `Live from inventory — Stock: <strong>${product.stock ?? "—"} ${escapeHtml(product.unit || "")}</strong> · Last price: <strong>${product.last_price ? fmtMoney(product.last_price) : "—"}</strong>`;

  // NEW: description + image preview, read-only reference from the catalog
  const box = document.getElementById("productPreviewBox");
  document.getElementById("productPreviewDesc").textContent = product.description || "No description on file for this product.";
  document.getElementById("productPreviewImages").innerHTML = (product.images || [])
    .slice(0, 6)
    .map(url => `<img src="${url}" style="width:52px;height:52px;object-fit:cover;border-radius:8px;border:1px solid var(--border)">`)
    .join("") || `<span class="fs-11 text-muted-2">No images uploaded for this product yet.</span>`;
  box.style.display = "block";
}

function clearProductPicker() {
  selectedProductId = null;
  document.getElementById("productPickLabel").innerHTML = `<span class="placeholder">Search a product from inventory...</span>`;
  document.getElementById("productLiveInfo").innerHTML = "";
  document.getElementById("productPreviewBox").style.display = "none";   // NEW
}

document.getElementById("useProductDescBtn").addEventListener("click", () => {
  if (!selectedProductId) { toast("Pehle ek product select karo.", "danger"); return; }
  const product = CFG.products.find(p => p.id === selectedProductId);
  const field = document.getElementById("articleDetailedDescription");
  if (product?.description) field.value = product.description;
});

  function initProductPicker() {
    const toggle = document.getElementById("productPickToggle");
    const panel = document.getElementById("productPickPanel");
    toggle.addEventListener("click", () => {
      panel.classList.toggle("show");
      if (panel.classList.contains("show")) {
        document.getElementById("productPickSearch").value = "";
        renderProductOptions("");
        document.getElementById("productPickSearch").focus();
      }
    });
    document.getElementById("productPickSearch").addEventListener("input", e => renderProductOptions(e.target.value));
    document.addEventListener("click", e => {
      if (!document.getElementById("productPickControl").contains(e.target)) panel.classList.remove("show");
    });
  }

  /* ---------------- Tag chip input ---------------- */

  function renderTagChips() {
    const box = document.getElementById("tagChipInput");
    const input = document.getElementById("tagInputField");
    box.querySelectorAll(".tag-chip").forEach(el => el.remove());
    tagsState.forEach((tag, idx) => {
      const chip = document.createElement("span");
      chip.className = "tag-chip";
      chip.innerHTML = `${escapeHtml(tag)} <button type="button" data-idx="${idx}">&times;</button>`;
      box.insertBefore(chip, input);
    });
    box.querySelectorAll(".tag-chip button").forEach(btn => {
      btn.addEventListener("click", () => { tagsState.splice(parseInt(btn.dataset.idx, 10), 1); renderTagChips(); });
    });
  }

  function initTagInput() {
    const input = document.getElementById("tagInputField");
    input.addEventListener("keydown", e => {
      if (e.key === "Enter" || e.key === ",") {
        e.preventDefault();
        const val = input.value.trim().replace(/,$/, "");
        if (val && !tagsState.includes(val)) { tagsState.push(val); renderTagChips(); }
        input.value = "";
      } else if (e.key === "Backspace" && !input.value && tagsState.length) {
        tagsState.pop();
        renderTagChips();
      }
    });
  }

  /* ---------------- Type -> conditional fields ---------------- */

  function currentType() { return document.querySelector('input[name="articleType"]:checked').value; }

  function applyTypeVisibility() {
    const type = currentType();

    // Question field: useful anywhere a customer might type a specific
    // phrased query — not just plain FAQs. For product/service articles
    // this is what tells apart "Warranty" vs "Returns" vs "Delivery" FAQs
    // that are all linked to the same product.
  const showDetailed = type === "product" || type === "service";
  document.getElementById("detailedDescWrap").style.display = showDetailed ? "block" : "none";

    const questionField = document.getElementById("articleQuestion");
    const titleField = document.getElementById("articleTitle");
    if (type === "product") {
      questionField.placeholder = "e.g. Warranty kitni hai is product ki?";
      titleField.placeholder = "e.g. Electric Scooter X100 — Warranty";
    } else if (type === "service") {
      questionField.placeholder = "e.g. Service me kitna time lagta hai?";
      titleField.placeholder = "Short internal title";
    } else if (type === "faq") {
      questionField.placeholder = "What the customer actually asks";
      titleField.placeholder = "Short internal title";
    } else {
      titleField.placeholder = "Short internal title";
    }

    document.getElementById("productPickWrap").style.display = type === "product" ? "block" : "none";
    const showPrice = type === "product" || type === "pricing";
    document.getElementById("priceOverrideWrap").style.display = showPrice ? "block" : "none";
    if (type === "product") {
      document.getElementById("priceOverrideLabel").textContent = "Price override (optional)";
      document.getElementById("priceOverrideHint").textContent = "Leave blank to always use the latest inventory price automatically.";
    } else if (type === "pricing") {
      document.getElementById("priceOverrideLabel").textContent = "Price";
      document.getElementById("priceOverrideHint").textContent = "";
    }
  }

  function initTypeGroup() {
    document.querySelectorAll('input[name="articleType"]').forEach(r => r.addEventListener("change", applyTypeVisibility));
  }

  /* ---------------- Add / Edit article modal ---------------- */

  function populateCategorySelect(selectEl) {
    selectEl.innerHTML = `<option value="">— none —</option>` + categories.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join("");
  }

  function resetArticleForm() {
    document.getElementById("articleForm").reset();
    document.getElementById("articleId").value = "";
    document.querySelector('input[name="articleType"][value="faq"]').checked = true;
    populateCategorySelect(document.getElementById("articleCategoryId"));
    clearProductPicker();
    tagsState = [];
    renderTagChips();
    document.getElementById("articleAiReady").checked = true;
    document.getElementById("articlePriority").value = 0;
    applyTypeVisibility();
  }

  async function openArticleModal(id) {
    resetArticleForm();

    if (id) {
      const res = await apiFetch(CFG.routes.show.replace("__ID__", id));
      if (!res.success) return;
      const a = res.article;

      document.getElementById("articleModalTitle").textContent = "Edit article";
      document.getElementById("articleSubmitBtn").textContent = "Save changes";
      document.getElementById("articleId").value = a.id;
      document.querySelector(`input[name="articleType"][value="${a.type}"]`).checked = true;
      document.getElementById("articleCategoryId").value = a.category_id || "";
      document.getElementById("articleTitle").value = a.title || "";
      document.getElementById("articleQuestion").value = a.question || "";
      document.getElementById("articleAnswer").value = a.answer || "";
      document.getElementById("articlePriceOverride").value = a.price_override ?? "";
      document.getElementById("articleAiReady").checked = !!a.ai_ready;
      document.getElementById("articlePriority").value = a.priority || 0;
      tagsState = (a.tags || []).slice();
      renderTagChips();
      if (a.type === "product" && a.product_id) selectProduct(a.product_id);
      applyTypeVisibility();
    } else {
      document.getElementById("articleModalTitle").textContent = "Add article";
      document.getElementById("articleSubmitBtn").textContent = "Create article";
    }

    bootstrap.Modal.getOrCreateInstance(document.getElementById("articleModal")).show();
  }

  function initArticleForm() {
    document.getElementById("openAddArticleBtn").addEventListener("click", () => openArticleModal(null));

    document.getElementById("articleForm").addEventListener("submit", async e => {
      e.preventDefault();
      const type = currentType();

      if (type === "product" && !selectedProductId) {
        toast("Pick a product to link this article to.", "danger");
        return;
      }

   const payload = {
  category_id: document.getElementById("articleCategoryId").value || null,
  type: type,
  title: document.getElementById("articleTitle").value.trim(),
  question: document.getElementById("articleQuestion").value.trim() || null,
  answer: document.getElementById("articleAnswer").value.trim(),
  detailed_description: document.getElementById("articleDetailedDescription").value.trim() || null, // NEW
  product_id: type === "product" ? selectedProductId : null,
  price_override: document.getElementById("articlePriceOverride").value || null,
  ai_ready: document.getElementById("articleAiReady").checked,
  priority: parseInt(document.getElementById("articlePriority").value, 10) || 0,
  tags: tagsState,
};

      const id = document.getElementById("articleId").value;
      const url = id ? CFG.routes.update.replace("__ID__", id) : CFG.routes.store;
      const res = await apiFetch(url, {
        method: id ? "PUT" : "POST",
        headers: csrfHeaders({ "Content-Type": "application/json" }),
        body: JSON.stringify(payload),
      });

      if (!res.success) { toast(firstError(res), "danger"); return; }

      await loadArticles();
      bootstrap.Modal.getOrCreateInstance(document.getElementById("articleModal")).hide();
      toast(res.message);

      if (currentDetailId === res.article.id) openDetail(res.article.id);
    });
  }

  /* ---------------- Detail offcanvas ---------------- */

  function renderMediaList(media) {
    const box = document.getElementById("adMediaList");
    if (!media || !media.length) { box.innerHTML = `<div class="fs-12 text-muted-2">No files attached.</div>`; return; }
    box.innerHTML = media.map(m => `
      <div class="media-card" data-media-id="${m.id}">
    <div class="media-icon"><i class="bi ${m.type === 'pdf' ? 'bi-file-earmark-pdf' : 'bi-file-earmark-image'}"></i></div>
    <div>
      <div class="media-name">${escapeHtml(m.file_name)} ${m.purpose === 'catalog' ? '<span class="badge-type" style="margin-left:6px">Catalog</span>' : ''}</div>
      <div class="media-meta">${fmtBytes(m.file_size)}</div>
    </div>
        <div class="media-actions">
          <a href="${m.url}" target="_blank" title="Open"><i class="bi bi-box-arrow-up-right"></i></a>
          <button type="button" class="media-del-btn" data-id="${m.id}" title="Remove"><i class="bi bi-trash"></i></button>
        </div>
      </div>`).join("");
    box.querySelectorAll(".media-del-btn").forEach(btn => {
      btn.addEventListener("click", async () => {
        if (!confirm("Remove this file?")) return;
        const res = await apiFetch(CFG.routes.mediaDestroy.replace("__ID__", btn.dataset.id), { method: "DELETE" });
        if (res.success) { toast(res.message, "dark"); openDetail(currentDetailId); }
      });
    });
  }

  function renderVersions(versions) {
    const box = document.getElementById("adVersions");
    if (!versions || !versions.length) { box.innerHTML = `<div class="fs-12 text-muted-2 p-2">No history yet.</div>`; return; }
    box.innerHTML = versions.map((v, idx) => `
      <div class="version-item">
        <div>
          <span class="v-num">v${v.version_number}</span>
          <span class="v-meta"> · ${escapeHtml(v.edited_by || "—")} · ${fmtDateTime(v.created_at)}</span>
        </div>
        ${idx === 0 ? `<span class="fs-11 text-muted-2">current</span>` : `<button type="button" class="btn btn-sm btn-outline-secondary restore-version-btn" data-version-id="${v.id}">Restore</button>`}
      </div>`).join("");
    box.querySelectorAll(".restore-version-btn").forEach(btn => {
      btn.addEventListener("click", async () => {
        if (!confirm("Restore this version? The current content will be saved to history first.")) return;
        const res = await apiFetch(CFG.routes.restoreVersion.replace("__ID__", currentDetailId), {
          method: "POST", headers: csrfHeaders({ "Content-Type": "application/json" }), body: JSON.stringify({ version_id: btn.dataset.versionId }),
        });
        if (res.success) { toast(res.message, "dark"); await loadArticles(); openDetail(currentDetailId); }
      });
    });
  }

  function renderRelatedProductArticles(a) {
    const wrap = document.getElementById("adRelatedWrap");
    const box = document.getElementById("adRelatedList");

    if (a.type !== "product" || !a.product_id) {
      wrap.classList.add("d-none");
      box.innerHTML = "";
      return;
    }

    // All other articles sharing the same linked product — i.e. the rest
    // of this product's FAQ set (Warranty, Returns, Delivery, etc.) — so
    // an admin editing one FAQ can see and jump to its siblings.
    const siblings = articles.filter(x => x.type === "product" && x.product_id === a.product_id && x.id !== a.id);

    if (!siblings.length) {
      wrap.classList.add("d-none");
      box.innerHTML = "";
      return;
    }

    wrap.classList.remove("d-none");
    box.innerHTML = siblings.map(s => `
      <div class="related-article-row" data-id="${s.id}">
        <span class="ra-title">${escapeHtml(s.title)}</span>
        ${statusBadge(s.status)}
      </div>`).join("");
    box.querySelectorAll(".related-article-row").forEach(row => {
      row.addEventListener("click", () => openDetail(parseInt(row.dataset.id, 10)));
    });
  }

  function renderActions(a) {
    const box = document.getElementById("adActions");
    const buttons = [];

    if (a.status === "draft") {
      buttons.push(`<button class="btn btn-sm btn-primary" data-action="submit"><i class="bi bi-send me-1"></i>Submit for approval</button>`);
    }
    if (a.status === "pending_approval" && CFG.canApprove) {
      buttons.push(`<button class="btn btn-sm" style="background:var(--success-bg);color:var(--success)" data-action="approve"><i class="bi bi-check-lg me-1"></i>Approve &amp; publish</button>`);
      buttons.push(`<button class="btn btn-sm" style="background:var(--danger-bg);color:var(--danger)" data-action="reject"><i class="bi bi-x-lg me-1"></i>Reject</button>`);
    }
    if (a.status === "published") {
      buttons.push(`<button class="btn btn-sm btn-outline-secondary" data-action="archive"><i class="bi bi-archive me-1"></i>Archive</button>`);
    }
    buttons.push(`<button class="btn btn-sm btn-outline-secondary" data-action="edit"><i class="bi bi-pencil me-1"></i>Edit</button>`);
    buttons.push(`<button class="btn btn-sm ms-auto" style="background:var(--danger-bg);color:var(--danger)" data-action="delete"><i class="bi bi-trash me-1"></i>Delete</button>`);

    box.innerHTML = buttons.join("");

    box.querySelector('[data-action="submit"]')?.addEventListener("click", () => runWorkflow(CFG.routes.submit));
    box.querySelector('[data-action="approve"]')?.addEventListener("click", () => runWorkflow(CFG.routes.approve));
    box.querySelector('[data-action="reject"]')?.addEventListener("click", () => runWorkflow(CFG.routes.reject));
    box.querySelector('[data-action="archive"]')?.addEventListener("click", () => runWorkflow(CFG.routes.archive));
    box.querySelector('[data-action="edit"]')?.addEventListener("click", () => {
      bootstrap.Offcanvas.getInstance(document.getElementById("articleDetailOffcanvas"))?.hide();
      openArticleModal(a.id);
    });
    box.querySelector('[data-action="delete"]')?.addEventListener("click", () => {
      selectedIds = new Set([a.id]);
      bootstrap.Offcanvas.getInstance(document.getElementById("articleDetailOffcanvas"))?.hide();
      bootstrap.Modal.getOrCreateInstance(document.getElementById("deleteConfirmModal")).show();
    });
  }

  async function runWorkflow(routeTemplate) {
    const res = await apiFetch(routeTemplate.replace("__ID__", currentDetailId), { method: "POST" });
    if (!res.success) { toast(res.message || "Could not update status.", "danger"); return; }
    toast(res.message, "dark");
    await loadArticles();
    openDetail(currentDetailId);
  }

  async function openDetail(id) {
    const res = await apiFetch(CFG.routes.show.replace("__ID__", id));
    if (!res.success) return;
    const a = res.article;
    currentDetailId = a.id;

    document.getElementById("adType").innerHTML = typeBadge(a.type);
    document.getElementById("adStatus").innerHTML = statusBadge(a.status);
    document.getElementById("adVersion").textContent = a.version;
    document.getElementById("adTitle").textContent = a.title;
    document.getElementById("adCategory").textContent = a.category_name || "Uncategorised";

    if (a.question) {
      document.getElementById("adQuestionWrap").style.display = "block";
      document.getElementById("adQuestion").textContent = a.question;
    } else {
      document.getElementById("adQuestionWrap").style.display = "none";
    }
    document.getElementById("adAnswer").textContent = a.answer;

    if (a.type === "product") {
      document.getElementById("adProductWrap").style.display = "block";
      document.getElementById("adProductName").textContent = a.product_name || "—";
      document.getElementById("adLivePrice").textContent = a.live_price !== null ? fmtMoney(a.live_price) : "—";
      document.getElementById("adLiveStock").textContent = a.live_stock !== null ? a.live_stock : "—";
    } else {
      document.getElementById("adProductWrap").style.display = "none";
    }

    document.getElementById("adTags").innerHTML = (a.tags || []).map(t => `<span class="tag-chip">${escapeHtml(t)}</span>`).join("") || `<span class="fs-12 text-muted-2">No tags</span>`;
    document.getElementById("adCreator").textContent = a.creator_name || "—";
    document.getElementById("adApprover").textContent = a.approver_name || "—";
    document.getElementById("adAiReady").innerHTML = a.ai_ready ? `<span class="text-success">Yes</span>` : `<span class="text-muted-2">No</span>`;

    renderMediaList(a.media);
    document.getElementById("adAiContext").textContent = a.ai_context || "";
    renderVersions(a.versions);
    renderActions(a);
    renderRelatedProductArticles(a);

    bootstrap.Offcanvas.getOrCreateInstance(document.getElementById("articleDetailOffcanvas")).show();
  }

  function initDetailPanel() {
    document.getElementById("copyAiContextBtn").addEventListener("click", () => {
      const text = document.getElementById("adAiContext").textContent;
      navigator.clipboard?.writeText(text);
      toast("AI context copied", "dark");
    });

    document.getElementById("mediaUploadInput").addEventListener("change", async e => {
      const file = e.target.files[0];
      if (!file || !currentDetailId) return;
      const fd = new FormData();
      fd.append("file", file);
      const res = await apiFetchForm(CFG.routes.mediaUpload.replace("__ID__", currentDetailId), fd);
      e.target.value = "";
      if (!res.success) { toast(firstError(res), "danger"); return; }
      toast("File uploaded.", "dark");
      openDetail(currentDetailId);
    });
  }

  /* ---------------- Regenerate catalog overview ---------------- */

  function initRegenerateCatalog() {
    document.getElementById("regenerateCatalogBtn").addEventListener("click", async () => {
      if (!confirm("Rebuild the 'Our Product Categories' FAQ from the current product list? It publishes immediately, no approval step.")) return;
      const res = await apiFetch(CFG.routes.regenerateCatalog, { method: "POST" });
      if (!res.success) { toast(res.message || "Could not regenerate catalog.", "danger"); return; }
      await loadArticles();
      toast(res.message, "dark");
    });
  }

  /* ---------------- Category modal ---------------- */

  function renderCategoryList() {
    const box = document.getElementById("categoryManageList");
    if (!categories.length) { box.innerHTML = `<div class="fs-12 text-muted-2 text-center py-2">No categories yet.</div>`; return; }
    box.innerHTML = categories.map(c => `
      <div class="manage-list-row">
        <div>
          <div class="mlr-name">${escapeHtml(c.name)} ${c.status === "inactive" ? '<span class="inactive-pill">INACTIVE</span>' : ""}</div>
          <div class="mlr-sub">${c.parent_id ? "Sub-category" : "Top level"}</div>
        </div>
        <div class="mlr-actions">
          <button type="button" class="cat-edit-btn" data-id="${c.id}" title="Edit"><i class="bi bi-pencil"></i></button>
          <button type="button" class="cat-del-btn danger" data-id="${c.id}" title="Delete"><i class="bi bi-trash"></i></button>
        </div>
      </div>`).join("");

    box.querySelectorAll(".cat-edit-btn").forEach(btn => btn.addEventListener("click", () => loadCategoryIntoForm(parseInt(btn.dataset.id, 10))));
    box.querySelectorAll(".cat-del-btn").forEach(btn => btn.addEventListener("click", async () => {
      if (!confirm("Delete this category? Articles in it will become uncategorised.")) return;
      const res = await apiFetch(CFG.routes.categoryDestroy.replace("__ID__", btn.dataset.id), { method: "DELETE" });
      if (res.success) {
        categories = categories.filter(c => c.id !== parseInt(btn.dataset.id, 10));
        renderCategoryList();
        refreshCategorySelects();
        toast(res.message, "dark");
      }
    }));
  }
document.getElementById("mediaUploadInput").addEventListener("change", async e => {
  const file = e.target.files[0];
  if (!file || !currentDetailId) return;
  const fd = new FormData();
  fd.append("file", file);
  fd.append("purpose", document.getElementById("mediaPurposeSelect").value);   // NEW
  const res = await apiFetchForm(CFG.routes.mediaUpload.replace("__ID__", currentDetailId), fd);
  e.target.value = "";
  if (!res.success) { toast(firstError(res), "danger"); return; }
  toast("File uploaded.", "dark");
  openDetail(currentDetailId);
});
  function refreshCategorySelects() {
    const filterSel = document.getElementById("filterCategory");
    const current = filterSel.value;
    filterSel.innerHTML = `<option value="">All Categories</option>` + categories.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join("");
    filterSel.value = current;
    document.getElementById("categoryParentId").innerHTML = `<option value="">— top level —</option>` + categories.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join("");
  }

  function loadCategoryIntoForm(id) {
    const category = categories.find(c => c.id === id);
    if (!category) return;
    editingCategoryId = id;
    document.getElementById("categoryId").value = id;
    document.getElementById("categoryName").value = category.name;
    document.getElementById("categoryParentId").value = category.parent_id || "";
    document.getElementById("categoryStatus").value = category.status;
    document.getElementById("categoryIcon").value = category.icon || "";
    document.getElementById("categoryModalTitle").textContent = "Edit category";
    document.getElementById("categorySubmitBtn").textContent = "Save changes";
    document.getElementById("categoryCancelEditBtn").classList.remove("d-none");
  }

  function resetCategoryForm() {
    editingCategoryId = null;
    document.getElementById("categoryForm").reset();
    document.getElementById("categoryId").value = "";
    document.getElementById("categoryModalTitle").textContent = "Add category";
    document.getElementById("categorySubmitBtn").textContent = "Add category";
    document.getElementById("categoryCancelEditBtn").classList.add("d-none");
  }

  function initCategoryModal() {
    document.getElementById("openCategoryModalBtn").addEventListener("click", () => {
      resetCategoryForm();
      refreshCategorySelects();
      renderCategoryList();
      bootstrap.Modal.getOrCreateInstance(document.getElementById("categoryModal")).show();
    });
    document.getElementById("categoryCancelEditBtn").addEventListener("click", resetCategoryForm);

    document.getElementById("categoryForm").addEventListener("submit", async e => {
      e.preventDefault();
      const payload = {
        name: document.getElementById("categoryName").value.trim(),
        parent_id: document.getElementById("categoryParentId").value || null,
        status: document.getElementById("categoryStatus").value,
        icon: document.getElementById("categoryIcon").value.trim() || null,
      };

      let url = CFG.routes.categoryStore;
      let method = "POST";
      if (editingCategoryId) { url = CFG.routes.categoryUpdate.replace("__ID__", editingCategoryId); method = "PUT"; }

      const res = await apiFetch(url, { method, headers: csrfHeaders({ "Content-Type": "application/json" }), body: JSON.stringify(payload) });
      if (!res.success) { toast(firstError(res), "danger"); return; }

      if (editingCategoryId) {
        categories = categories.map(c => c.id === res.category.id ? res.category : c);
      } else {
        categories.push(res.category);
      }
      resetCategoryForm();
      renderCategoryList();
      refreshCategorySelects();
      toast(res.message);
    });
  }

  /* ---------------- Boot ---------------- */

  document.addEventListener("DOMContentLoaded", () => {
    initFilters();
    initBulkActions();
    initProductPicker();
    initTagInput();
    initTypeGroup();
    initArticleForm();
    initDetailPanel();
    initCategoryModal();
    initRegenerateCatalog();
    applyTypeVisibility();
    loadArticles();
  });
})();
</script>

@endsection
