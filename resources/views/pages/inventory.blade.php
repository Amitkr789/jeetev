@extends('layouts.app')
@section('title', 'Inventory | Dalal Adda')

@section('content')

<style>
  /* ---- Searchable single-select "product / dealer" control ----
     Same ms-control pattern used on the leads/reminders pages. */
  .ms-control { position: relative; }
  .ms-row { display: flex; gap: 8px; align-items: flex-start; }
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
  .ms-option { display: flex; flex-direction: column; gap: 1px; padding: 7px 8px; border-radius: 7px; font-size: 13px; cursor: pointer; }
  .ms-option:hover { background: var(--primary-50); }
  .ms-option .ms-option-sub { font-size: 11.5px; color: var(--text-muted); }
  .ms-quick-add {
    flex-shrink: 0; width: 42px; height: 42px; border-radius: 10px; border: 1.5px dashed var(--border);
    background: #FBFBFE; color: var(--primary); font-size: 18px; display: flex; align-items: center;
    justify-content: center; cursor: pointer;
  }
  .ms-quick-add:hover { background: var(--primary-50); border-color: var(--primary); }

  .crm-table tbody tr[data-no-dealer="1"] { border-left: 3px solid var(--border); }
  .crm-table tbody tr[data-no-dealer="0"] { border-left: 3px solid var(--info); }
  #entriesTbody tr:hover { filter: brightness(0.97); cursor: pointer; }

  .qty-pill { font-weight: 700; }
  .qty-pill.negative { color: var(--danger); }
  .total-pill { font-weight: 700; color: var(--success); }
  .total-pill.negative { color: var(--danger); }

  /* ---- Stock movement direction (dealer purchase vs sold-via-bill) ---- */
  .movement-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
  .movement-badge.in { background: var(--success-bg); color: var(--success); }
  .movement-badge.out { background: var(--danger-bg); color: var(--danger); }

  /* ---- Products tab ---- */
  .crm-table tbody tr[data-out-of-stock="1"] { background: var(--danger-bg); }
  #productsTbody tr:hover { filter: brightness(0.97); cursor: pointer; }
  #soldTbody tr:hover { filter: brightness(0.97); cursor: pointer; }

  .product-thumb { border-radius: 8px; object-fit: cover; display: block; }
  .product-thumb-placeholder {
    border-radius: 8px; background: var(--primary-50); color: var(--primary);
    display: flex; align-items: center; justify-content: center;
  }

  .stock-badge { display: inline-block; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; }
  .stock-badge.in-stock { background: var(--success-bg); color: var(--success); }
  .stock-badge.out-of-stock { background: var(--danger-bg); color: var(--danger); }

  /* ---- Image gallery grid — used both in the Add/Edit Product modal
     (add/remove) and the product detail offcanvas (read-only display) ---- */
  .pd-gallery { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
  .pd-gallery-item {
    position: relative; aspect-ratio: 1 / 1; border-radius: 10px; overflow: hidden;
    border: 1px solid var(--border); background: var(--bg);
  }
  .pd-gallery-item img { width: 100%; height: 100%; object-fit: cover; display: block; }
  .pd-gallery-remove {
    position: absolute; top: 4px; right: 4px; width: 22px; height: 22px; border-radius: 50%; border: none;
    background: rgba(0,0,0,.55); color: #fff; display: flex; align-items: center; justify-content: center;
    font-size: 13px; cursor: pointer; line-height: 1;
  }
  .pd-gallery-remove:hover { background: var(--danger); }
</style>

<div class="page-content">
  <section class="page-section active" id="page-inventory">

    <div class="page-header">
      <div>
        <span class="breadcrumb-eyebrow">Stock</span>
        <h1>Inventory</h1>
        <p>Track every product, dealer and stock entry in one place.</p>
      </div>
      <div class="page-header-actions d-flex gap-2">
        <button class="btn btn-primary" id="openAddEntryBtn">
          <i class="bi bi-plus-lg me-1"></i>Add Stock Entry
        </button>
        <button class="btn btn-primary d-none" id="openAddProductTabBtn">
          <i class="bi bi-plus-lg me-1"></i>Add Product
        </button>
      </div>
    </div>

    <!-- Stat cards -->
    <div class="row g-3 mb-3">
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div>
            <div class="stat-label">Total Products</div>
            <div class="stat-value" id="statProducts">0</div>
          </div>
          <div class="stat-icon" style="background:var(--primary-50); color:var(--primary)"><i class="bi bi-box-seam"></i></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div>
            <div class="stat-label">Total Dealers</div>
            <div class="stat-value" id="statDealers">0</div>
          </div>
          <div class="stat-icon" style="background:var(--info-bg); color:var(--info)"><i class="bi bi-truck"></i></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div>
            <div class="stat-label">Stock Entries</div>
            <div class="stat-value" id="statEntries">0</div>
          </div>
          <div class="stat-icon" style="background:var(--warning-bg); color:var(--warning)"><i class="bi bi-clipboard-data"></i></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div>
            <div class="stat-label">Net Stock Value</div>
            <div class="stat-value" id="statValue">₹0</div>
          </div>
          <div class="stat-icon" style="background:var(--success-bg); color:var(--success)"><i class="bi bi-currency-rupee"></i></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div>
            <div class="stat-label">Out of Stock</div>
            <div class="stat-value" id="statOutOfStock">0</div>
          </div>
          <div class="stat-icon" style="background:var(--danger-bg); color:var(--danger)"><i class="bi bi-exclamation-triangle"></i></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div>
            <div class="stat-label">Sold Entries</div>
            <div class="stat-value" id="statSold">0</div>
          </div>
          <div class="stat-icon" style="background:var(--danger-bg); color:var(--danger)"><i class="bi bi-bag-check"></i></div>
        </div>
      </div>
    </div>

    <!-- Section tabs -->
    <ul class="nav lead-tabs mb-3" id="invTabs">
      <li class="nav-item"><a class="nav-link active" data-tab="entries" href="#">Stock Entries</a></li>
      <li class="nav-item"><a class="nav-link" data-tab="products" href="#">Products</a></li>
      <li class="nav-item"><a class="nav-link" data-tab="sold" href="#">Sold Products</a></li>
    </ul>

    <!-- ===================== STOCK ENTRIES VIEW ===================== -->
    <div id="entriesView">

      <!-- Filter bar -->
      <div class="filter-bar">
        <div class="filter-search">
          <i class="bi bi-search"></i>
          <input type="text" class="form-control" id="filterSearch" placeholder="Search by product, SKU, model or dealer...">
        </div>
        <select class="form-select" id="filterProduct" style="max-width:200px">
          <option value="">All Products</option>
          @foreach($products as $p)
            <option value="{{ $p->id }}">{{ $p->name }}</option>
          @endforeach
        </select>
        <select class="form-select" id="filterDealer" style="max-width:200px">
          <option value="">All Dealers</option>
          @foreach($dealers as $d)
            <option value="{{ $d->id }}">{{ $d->name }}</option>
          @endforeach
        </select>
        <div class="d-flex align-items-center gap-1">
          <input type="date" class="form-control" id="filterDateFrom" style="max-width:150px" title="From">
          <span class="text-muted-2 fs-12">to</span>
          <input type="date" class="form-control" id="filterDateTo" style="max-width:150px" title="To">
        </div>
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
                <th>Product</th>
                <th>Model</th>
                <th>Dealer</th>
                <th>Type</th>
                <th>Quantity</th>
                <th>Price</th>
                <th>Total</th>
                <th>Date</th>
                <th>Added by</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="entriesTbody"></tbody>
          </table>
        </div>
        <div class="crm-pagination">
          <span class="page-info" id="pageInfo"></span>
          <div class="d-flex gap-1" id="pageButtons"></div>
        </div>
      </div>
    </div>

    <!-- ===================== PRODUCTS VIEW ===================== -->
    <div id="productsView" class="d-none">

      <div class="filter-bar">
        <div class="filter-search">
          <i class="bi bi-search"></i>
          <input type="text" class="form-control" id="productFilterSearch" placeholder="Search by product, SKU or model...">
        </div>
        <select class="form-select" id="productFilterStatus" style="max-width:170px">
          <option value="">All Stock Status</option>
          <option value="in">In Stock</option>
          <option value="out">Out of Stock</option>
        </select>
        <button class="btn btn-outline-secondary btn-sm" id="productClearFiltersBtn">Clear</button>
      </div>

      <div id="productActivePills" class="d-flex flex-wrap gap-2 mb-2"></div>

      <div class="section-card">
        <div class="table-wrap">
          <table class="crm-table">
            <thead>
              <tr>
                <th style="width:56px"></th>
                <th>Product</th>
                <th>Model</th>
                <th>Total Stock</th>
                <th>Status</th>
                <th>Added by</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="productsTbody"></tbody>
          </table>
        </div>
        <div class="crm-pagination">
          <span class="page-info" id="productPageInfo"></span>
          <div class="d-flex gap-1" id="productPageButtons"></div>
        </div>
      </div>
    </div>

    <!-- ===================== SOLD PRODUCTS VIEW =====================
         Read-only view of every stock movement that came out of a bill
         (type = "out"). These rows can never be added/edited/deleted from
         here — only by editing/deleting the bill itself (BillingManageController
         handles the actual stock deduction via deductStock()/restoreStock()). -->
    <div id="soldView" class="d-none">

      <div class="filter-bar">
        <div class="filter-search">
          <i class="bi bi-search"></i>
          <input type="text" class="form-control" id="soldFilterSearch" placeholder="Search by product, SKU, model or bill...">
        </div>
        <select class="form-select" id="soldFilterProduct" style="max-width:200px">
          <option value="">All Products</option>
          @foreach($products as $p)
            <option value="{{ $p->id }}">{{ $p->name }}</option>
          @endforeach
        </select>
        <div class="d-flex align-items-center gap-1">
          <input type="date" class="form-control" id="soldFilterDateFrom" style="max-width:150px" title="From">
          <span class="text-muted-2 fs-12">to</span>
          <input type="date" class="form-control" id="soldFilterDateTo" style="max-width:150px" title="To">
        </div>
        <button class="btn btn-outline-secondary btn-sm" id="soldClearFiltersBtn">Clear</button>
      </div>

      <div id="soldActivePills" class="d-flex flex-wrap gap-2 mb-2"></div>

      <div class="section-card">
        <div class="table-wrap">
          <table class="crm-table">
            <thead>
              <tr>
                <th>Product</th>
                <th>Model</th>
                <th>Quantity Sold</th>
                <th>Price</th>
                <th>Total</th>
                <th>Sold via</th>
                <th>Date</th>
                <th>Sold by</th>
              </tr>
            </thead>
            <tbody id="soldTbody"></tbody>
          </table>
        </div>
        <div class="crm-pagination">
          <span class="page-info" id="soldPageInfo"></span>
          <div class="d-flex gap-1" id="soldPageButtons"></div>
        </div>
      </div>
    </div>

  </section>
</div>

<!-- ===================== ADD / EDIT STOCK ENTRY MODAL =====================
     Only ever used for "in" (dealer purchase) entries — bill-generated
     "out" entries are read-only and can only be changed by editing the
     bill itself (see entryDetailOffcanvas below). -->
<div class="modal fade" id="entryModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form id="entryForm">
        <input type="hidden" id="entryId">
        <input type="hidden" id="entryProductId">
        <input type="hidden" id="entryDealerId">
        <div class="modal-header">
          <h5 class="modal-title" id="entryModalTitle">Add stock entry</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">

            <div class="col-12">
              <label class="form-label">Product</label>
              <div class="ms-row">
                <div class="ms-control flex-grow-1" id="entryProductMsControl">
                  <button type="button" class="ms-toggle" id="entryProductMsToggle">
                    <span class="chips" id="entryProductMsChips"><span class="placeholder">Select a product...</span></span>
                    <i class="bi bi-chevron-down"></i>
                  </button>
                  <div class="ms-panel" id="entryProductMsPanel">
                    <input type="text" class="form-control ms-search" id="entryProductMsSearch" placeholder="Search by name, SKU or model...">
                    <div id="entryProductMsOptions"></div>
                  </div>
                </div>
                <div class="ms-quick-add" id="openAddProductBtn" title="Add new product"><i class="bi bi-plus-lg"></i></div>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label">Dealer <span class="text-muted-2 fw-400">(optional)</span></label>
              <div class="ms-row">
                <div class="ms-control flex-grow-1" id="entryDealerMsControl">
                  <button type="button" class="ms-toggle" id="entryDealerMsToggle">
                    <span class="chips" id="entryDealerMsChips"><span class="placeholder">Select a dealer...</span></span>
                    <i class="bi bi-chevron-down"></i>
                  </button>
                  <div class="ms-panel" id="entryDealerMsPanel">
                    <input type="text" class="form-control ms-search" id="entryDealerMsSearch" placeholder="Search by name or company...">
                    <div id="entryDealerMsOptions"></div>
                  </div>
                </div>
                <div class="ms-quick-add" id="openAddDealerBtn" title="Add new dealer"><i class="bi bi-plus-lg"></i></div>
              </div>
            </div>

            <div class="col-4">
              <label class="form-label">Quantity</label>
              <input type="number" step="0.01" min="0.01" class="form-control" id="entryQuantity" placeholder="e.g. 50" required>
            </div>
            <div class="col-4">
              <label class="form-label">Unit</label>
              <select class="form-select" id="entryUnit">
                @foreach($units as $u)
                  <option value="{{ $u }}">{{ ucfirst($u) }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-4">
              <label class="form-label">Price <span class="text-muted-2 fw-400">(per unit)</span></label>
              <input type="number" step="0.01" min="0" class="form-control" id="entryPrice" placeholder="e.g. 250" required>
            </div>

            <div class="col-6">
              <label class="form-label">Date</label>
              <input type="date" class="form-control" id="entryDate">
            </div>

            <div class="col-12">
              <label class="form-label">Description <span class="text-muted-2 fw-400">(notes for this entry — batch, condition, etc.)</span></label>
              <textarea class="form-control" id="entryDescription" rows="2" placeholder="Optional notes..."></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="entrySubmitBtn">Add entry</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===================== ADD / EDIT PRODUCT MODAL ====================
     Doubles as the quick-add popup (from the "+" next to the product
     picker on the stock-entry modal) and the full product editor (from
     the Products tab). Images are managed right here: existing images can
     be removed instantly; new ones upload immediately when editing, or
     get queued and uploaded right after creation when adding a brand new
     product (since it doesn't have an id to attach images to yet). -->
<div class="modal fade" id="addProductModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form id="addProductForm">
        <input type="hidden" id="editProductId">
        <div class="modal-header">
          <h5 class="modal-title" id="addProductModalTitle">Add new product</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Product name</label>
              <input type="text" class="form-control" id="addProductName" placeholder="e.g. LED Bulb 9W" required>
            </div>
            <div class="col-6">
              <label class="form-label">SKU</label>
              <input type="text" class="form-control" id="addProductSku" placeholder="e.g. LED-9W-001" required>
            </div>
            <div class="col-6">
              <label class="form-label">Model <span class="text-muted-2 fw-400">(optional)</span></label>
              <input type="text" class="form-control" id="addProductModel" placeholder="e.g. LB-9-COOL">
            </div>
            <div class="col-6">
              <label class="form-label">Default unit</label>
              <select class="form-select" id="addProductUnit">
                @foreach($units as $u)
                  <option value="{{ $u }}">{{ ucfirst($u) }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Description <span class="text-muted-2 fw-400">(optional)</span></label>
              <textarea class="form-control" id="addProductDescription" rows="2" placeholder="Optional product notes..."></textarea>
            </div>

            <div class="col-12">
              <label class="form-label">Images</label>
              <div class="pd-gallery mb-2" id="apGallery"></div>
              <button type="button" class="btn btn-outline-secondary btn-sm" id="apAddImagesBtn">
                <i class="bi bi-image me-1"></i>Add images
              </button>
              <input type="file" id="apImageInput" accept="image/*" multiple hidden>
              <div class="fs-11 text-muted-2 mt-1" id="apImagesHint"></div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="addProductSubmitBtn">Add product</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===================== QUICK-ADD DEALER MODAL ===================== -->
<div class="modal fade" id="addDealerModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="addDealerForm">
        <div class="modal-header">
          <h5 class="modal-title">Add new dealer</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Dealer name</label>
              <input type="text" class="form-control" id="addDealerName" placeholder="e.g. Suresh Traders" required>
            </div>
            <div class="col-6">
              <label class="form-label">Company <span class="text-muted-2 fw-400">(optional)</span></label>
              <input type="text" class="form-control" id="addDealerCompany" placeholder="e.g. Suresh Electricals Pvt Ltd">
            </div>
            <div class="col-6">
              <label class="form-label">Phone <span class="text-muted-2 fw-400">(optional)</span></label>
              <input type="text" class="form-control" id="addDealerPhone" placeholder="+91 98765 43210">
            </div>
            <div class="col-6">
              <label class="form-label">Email <span class="text-muted-2 fw-400">(optional)</span></label>
              <input type="email" class="form-control" id="addDealerEmail" placeholder="dealer@example.com">
            </div>
            <div class="col-12">
              <label class="form-label">Address <span class="text-muted-2 fw-400">(optional)</span></label>
              <textarea class="form-control" id="addDealerAddress" rows="2" placeholder="Optional address..."></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add dealer</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===================== PRODUCT DETAIL OFFCANVAS =====================
     View-only + edit/delete shortcuts — image and field edits all happen
     through the Add/Edit Product modal (pdEditBtn opens it). -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="productDetailOffcanvas" style="width:440px">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title">Product details</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body">
    <div class="d-flex align-items-center gap-3 mb-3">
      <div id="pdThumb"></div>
      <div>
        <div class="fw-600 fs-15" id="pdName" style="font-size:16px"></div>
        <div class="text-muted-2 fs-13" id="pdSku"></div>
      </div>
      <div class="d-flex align-items-center gap-1 ms-auto">
        <button type="button" class="pin-btn fs-5" id="pdEditBtn" title="Edit product"><i class="bi bi-pencil-fill"></i></button>
        <button type="button" class="pin-btn fs-5" id="pdDeleteBtn" title="Delete product"><i class="bi bi-trash-fill"></i></button>
      </div>
    </div>

    <div class="mb-3">
      <span class="stock-badge" id="pdStockBadge"></span>
    </div>

    <div class="card-flat p-3 mb-3">
      <div class="d-flex justify-content-between mb-2"><span class="text-muted-2 fs-13">Model</span><span class="fw-600 fs-13" id="pdModel">—</span></div>
      <div class="d-flex justify-content-between mb-2"><span class="text-muted-2 fs-13">Unit</span><span class="fw-600 fs-13" id="pdUnit">—</span></div>
      <div class="d-flex justify-content-between mb-2"><span class="text-muted-2 fs-13">Total stock</span><span class="fw-600 fs-13" id="pdTotalStock">—</span></div>
      <div class="d-flex justify-content-between"><span class="text-muted-2 fs-13">Added by</span><span class="fw-600 fs-13" id="pdCreatedBy">—</span></div>
    </div>

    <div class="mb-4">
      <label class="form-label fs-11 text-uppercase text-muted-2 fw-700 mb-1">Description</label>
      <p class="fs-13 mb-0" id="pdDescription">—</p>
    </div>

    <h6 class="fs-13 fw-700 text-uppercase text-muted-2 mb-2">Images</h6>
    <div class="pd-gallery mb-4" id="pdGallery"></div>

    <h6 class="fs-13 fw-700 text-uppercase text-muted-2 mb-2">Stock entries</h6>
    <div class="section-card" id="pdEntries"></div>
  </div>
</div>

<!-- ===================== STOCK ENTRY DETAIL OFFCANVAS =====================
     Mirrors the lead/product detail panels: click a Stock Entries row to
     view everything about that entry. Bill-generated ("Sold") entries are
     read-only here — edit/delete are hidden and a note points to Billing
     instead, since that's the only place those entries can safely change. -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="entryDetailOffcanvas" style="width:440px">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title">Stock entry details</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body">
    <div class="d-flex align-items-center gap-3 mb-3">
      <div id="edThumb"></div>
      <div>
        <div class="fw-600 fs-15" id="edProductName" style="font-size:16px"></div>
        <div class="text-muted-2 fs-13" id="edProductSku"></div>
      </div>
      <div class="d-flex align-items-center gap-1 ms-auto" id="edActionButtons">
        <button type="button" class="pin-btn fs-5" id="edEditBtn" title="Edit entry"><i class="bi bi-pencil-fill"></i></button>
        <button type="button" class="pin-btn fs-5" id="edDeleteBtn" title="Delete entry"><i class="bi bi-trash-fill"></i></button>
      </div>
    </div>

    <div class="mb-3">
      <span class="movement-badge" id="edTypeBadge"></span>
    </div>

    <div class="card-flat p-3 mb-3">
      <div class="d-flex justify-content-between mb-2"><span class="text-muted-2 fs-13">Dealer</span><span class="fw-600 fs-13" id="edDealer">—</span></div>
      <div class="d-flex justify-content-between mb-2"><span class="text-muted-2 fs-13">Quantity</span><span class="fw-600 fs-13" id="edQuantity">—</span></div>
      <div class="d-flex justify-content-between mb-2"><span class="text-muted-2 fs-13">Price per unit</span><span class="fw-600 fs-13" id="edPrice">—</span></div>
      <div class="d-flex justify-content-between mb-2"><span class="text-muted-2 fs-13">Total value</span><span class="fw-600 fs-13" id="edTotal">—</span></div>
      <div class="d-flex justify-content-between mb-2"><span class="text-muted-2 fs-13">Date</span><span class="fw-600 fs-13" id="edDate">—</span></div>
      <div class="d-flex justify-content-between"><span class="text-muted-2 fs-13">Added by</span><span class="fw-600 fs-13" id="edCreatedBy">—</span></div>
    </div>

    <div class="mb-3">
      <label class="form-label fs-11 text-uppercase text-muted-2 fw-700 mb-1">Description</label>
      <p class="fs-13 mb-0" id="edDescription">—</p>
    </div>

    <div class="fs-12 text-muted-2 mb-3 d-none card-flat p-2" id="edLockedNote">
      <i class="bi bi-info-circle me-1"></i>This entry was generated automatically from a bill and can only be changed by editing that bill.
    </div>

    <a href="#" class="fs-13 fw-600 d-none" id="edViewProductLink">View this product's full details <i class="bi bi-arrow-right"></i></a>
  </div>
</div>

<!-- ===================== DELETE CONFIRM MODAL (stock entries) ===================== -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-content-compact">
    <div class="modal-content modal-content-danger">
      <div class="modal-header">
        <h5 class="modal-title">Delete stock entrie(s)?</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0">This will permanently delete the selected stock entrie(s). This can't be undone.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirmDeleteBtn">Delete</button>
      </div>
    </div>
  </div>
</div>

<!-- ===================== DELETE CONFIRM MODAL (product) ===================== -->
<div class="modal fade" id="deleteProductConfirmModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-content-compact">
    <div class="modal-content modal-content-danger">
      <div class="modal-header">
        <h5 class="modal-title">Delete product?</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0">This will permanently delete the product and its images. Products with stock entries still linked to them can't be deleted until those entries are removed or reassigned.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirmDeleteProductBtn">Delete</button>
      </div>
    </div>
  </div>
</div>

<script>
  window.INVENTORY_PAGE_DATA = {
    products: @json($products),
    dealers: @json($dealers),
    csrfToken: '{{ csrf_token() }}',
    routes: {
      data: '{{ route('inventory.data') }}',
      store: '{{ route('inventory.store') }}',
      update: '{{ url('admin/inventory') }}/__ID__',
      destroy: '{{ url('admin/inventory') }}/__ID__',
      bulkDelete: '{{ route('inventory.bulkDestroy') }}',
      productStore: '{{ route('inventory.products.store') }}',
      dealerStore: '{{ route('inventory.dealers.store') }}',
      productsData: '{{ route('inventory.products.data') }}',
      productShow: '{{ url('admin/inventory/products') }}/__ID__',
      productUpdate: '{{ url('admin/inventory/products') }}/__ID__',
      productDestroy: '{{ url('admin/inventory/products') }}/__ID__',
      productImagesStore: '{{ url('admin/inventory/products') }}/__ID__/images',
      productImageDestroy: '{{ url('admin/inventory/products/images') }}/__ID__',
    }
  };

  /* ==========================================================================
   Inventory page logic (talks to InventoryManageController via fetch/AJAX)

   Three tabs share this file:
   - "Stock Entries": every inventory movement, both dealer purchases
     ("in", added manually here) and bill-driven sales ("out", created
     automatically by BillingManageController — read-only from this page).
   - "Products": product gallery/edit/delete + the product detail panel.
   - "Sold Products": read-only slice of the same entries, filtered to
     type === "out" only — i.e. everything that has been deducted from
     stock because it was sold via a bill.

   Stock Entries and Products tabs get their own detail offcanvas,
   mirroring the lead detail pattern from leads.blade: click a row to see
   everything about it. Sold Products reuses the Stock Entries detail
   offcanvas since a "sold" row is just an entry with type = "out".
   ========================================================================== */

(function () {
  "use strict";

  const CFG = window.INVENTORY_PAGE_DATA;
  const CSRF = CFG.csrfToken;

  let entries = [];
  // Single source of truth for products — used both by the product picker
  // on the stock-entry modal and by the Products tab table, so stock
  // totals / images never drift out of sync between the two.
  let productList = [...CFG.products];
  let dealers = [...CFG.dealers];

  let activeFilters = { search: "", productId: "", dealerId: "", dateFrom: "", dateTo: "" };
  let currentPage = 1;
  const PAGE_SIZE = 10;
  let selectedIds = new Set();
  let currentDetailEntryId = null;

  let activeTab = "entries";
  let productFilters = { search: "", status: "" };
  let productPage = 1;
  const PRODUCT_PAGE_SIZE = 10;
  let currentDetailProductId = null;
  let productModalMode = "quickAdd"; // "quickAdd" | "edit"
  let pendingDeleteProductId = null;

  let soldFilters = { search: "", productId: "", dateFrom: "", dateTo: "" };
  let soldPage = 1;
  const SOLD_PAGE_SIZE = 10;

  // Add/Edit Product modal's image state: existing images (when editing,
  // removable instantly) and newly-picked files queued locally (when
  // adding a brand new product that doesn't have an id yet).
  let editProductExistingImages = [];
  let addProductQueuedFiles = [];

  // Which field ("product" or "dealer") the currently-open quick-add
  // modal should feed its result back into, once saved.
  let selectedEntryProductId = null;
  let selectedEntryDealerId = null;

  /* ---------------- Small helpers ---------------- */

  function fmtDate(d) {
    if (!d) return "—";
    const dt = new Date(d);
    if (isNaN(dt)) return d;
    return dt.toLocaleDateString(undefined, { day: "numeric", month: "short", year: "numeric" });
  }

  function fmtMoney(n) {
    return "₹" + Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function fmtQty(n) {
    const num = Number(n) || 0;
    return num % 1 === 0 ? String(num) : num.toFixed(2);
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

  // Separate from apiFetch because file uploads must NOT carry a
  // Content-Type header — the browser sets the multipart boundary itself.
  async function apiUpload(url, formData) {
    const res = await fetch(url, {
      method: "POST",
      headers: { "X-CSRF-TOKEN": CSRF, "X-Requested-With": "XMLHttpRequest", "Accept": "application/json" },
      body: formData,
    });
    if (res.status === 401 || res.status === 419) {
      toast("Your session expired, please log in again.", "danger");
      throw new Error("unauthenticated");
    }
    return res.json();
  }

  function firstError(res, fallback) {
    return res.errors ? Object.values(res.errors)[0][0] : fallback;
  }

  function isOutOfStock(p) {
    return !p.total_quantity || Number(p.total_quantity) <= 0;
  }

  /**
   * Net Stock Value = current stock, valued at what it *cost* to buy —
   * never at the price it was sold for. Selling above or below cost is a
   * profit/loss question, not a stock-valuation question, so "out"
   * (sold) entries only reduce quantity here; they never subtract their
   * sale price from the value. Cost is a weighted average of every "in"
   * (dealer purchase) entry logged for that product.
   */
  function computeNetStockValue() {
    const costByProduct = {}; // product_id -> { qty, cost }
    entries.forEach(e => {
      if (e.type !== "in" || !e.product) return;
      const pid = e.product.id;
      if (!costByProduct[pid]) costByProduct[pid] = { qty: 0, cost: 0 };
      costByProduct[pid].qty += Number(e.quantity) || 0;
      costByProduct[pid].cost += (Number(e.quantity) || 0) * (Number(e.price) || 0);
    });

    return productList.reduce((sum, p) => {
      const currentQty = Number(p.total_quantity) || 0;
      if (currentQty <= 0) return sum;
      const info = costByProduct[p.id];
      const avgCost = info && info.qty > 0 ? info.cost / info.qty : 0;
      return sum + currentQty * avgCost;
    }, 0);
  }

  function stockBadgeHtml(p) {
    return isOutOfStock(p)
      ? `<span class="stock-badge out-of-stock">Out of stock</span>`
      : `<span class="stock-badge in-stock">In stock</span>`;
  }

  function applyStockBadge(el, p) {
    const out = isOutOfStock(p);
    el.className = "stock-badge " + (out ? "out-of-stock" : "in-stock");
    el.textContent = out ? "Out of stock" : "In stock";
  }

  function productThumbHtml(p, size) {
    const dim = size || 36;
    const img = (p.images && p.images.length) ? p.images[0].url : null;
    if (img) {
      return `<img src="${img}" class="product-thumb" style="width:${dim}px;height:${dim}px">`;
    }
    return `<div class="product-thumb-placeholder" style="width:${dim}px;height:${dim}px;font-size:${dim > 40 ? 18 : 14}px"><i class="bi bi-box-seam"></i></div>`;
  }

  /* ---------------- Data fetch: stock entries ---------------- */

  async function loadEntries() {
    const res = await apiFetch(CFG.routes.data);
    if (!res.success) return;
    entries = res.items;
    renderAll();
  }

  /* ---------------- Data fetch: products ---------------- */

  async function loadProducts() {
    const res = await apiFetch(CFG.routes.productsData);
    if (!res.success) return;
    productList = res.products;
    renderProductStats();
    if (activeTab === "products") renderProductsTable();
  }

  /* ---------------- Filtering: stock entries ---------------- */

  function getFilteredEntries() {
    // Stock Entries tab now only shows dealer purchases ("in"). Bill-driven
    // sales ("out") live exclusively in the Sold Products tab (see
    // getFilteredSoldEntries below) so the same movement never shows up
    // twice across tabs.
    return entries.filter(e => e.type === "in").filter(e => {
      const term = activeFilters.search.toLowerCase();
      const haystack = `${e.product ? e.product.name + " " + e.product.sku + " " + (e.product.model || "") : ""} ${e.dealer ? e.dealer.name : ""}`.toLowerCase();
      const matchesSearch = !term || haystack.includes(term);
      const matchesProduct = !activeFilters.productId || (e.product && e.product.id == activeFilters.productId);
      const matchesDealer = !activeFilters.dealerId || (e.dealer && e.dealer.id == activeFilters.dealerId);

      const entryDate = (e.entry_date || "").slice(0, 10);
      const matchesDateFrom = !activeFilters.dateFrom || (entryDate && entryDate >= activeFilters.dateFrom);
      const matchesDateTo = !activeFilters.dateTo || (entryDate && entryDate <= activeFilters.dateTo);

      return matchesSearch && matchesProduct && matchesDealer && matchesDateFrom && matchesDateTo;
    });
  }

  /* ---------------- Rendering: stock entries ---------------- */

  function renderAll() {
    renderStats();
    renderTable();
    if (activeTab === "sold") renderSoldTable();
  }

  function renderStats() {
    document.getElementById("statProducts").textContent = productList.length;
    document.getElementById("statDealers").textContent = dealers.length;
    document.getElementById("statEntries").textContent = entries.filter(e => e.type === "in").length;
    document.getElementById("statValue").textContent = fmtMoney(computeNetStockValue());
    document.getElementById("statSold").textContent = entries.filter(e => e.type === "out").length;
  }

  function renderTable() {
    const filtered = getFilteredEntries();
    const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
    currentPage = Math.min(currentPage, totalPages);
    const start = (currentPage - 1) * PAGE_SIZE;
    const pageItems = filtered.slice(start, start + PAGE_SIZE);

    const tbody = document.getElementById("entriesTbody");

    if (pageItems.length === 0) {
      tbody.innerHTML = `<tr><td colspan="11"><div class="empty-state">
        <i class="bi bi-inbox"></i><h6>No stock entries found</h6>
        <p class="fs-13 mb-0">Try adjusting filters or add a new entry.</p></div></td></tr>`;
    } else {
      tbody.innerHTML = pageItems.map(e => {
        const isOut = e.type === "out";
        const sign = isOut ? "−" : "";
        const total = (Number(e.quantity) || 0) * (Number(e.price) || 0);
        const typeBadge = isOut
          ? `<span class="movement-badge out">Sold</span>`
          : `<span class="movement-badge in">Purchase</span>`;
        const actionItems = isOut
          ? `<li><a class="dropdown-item view-entry-action" href="#" data-id="${e.id}"><i class="bi bi-eye me-2"></i>View details</a></li>`
          : `<li><a class="dropdown-item view-entry-action" href="#" data-id="${e.id}"><i class="bi bi-eye me-2"></i>View details</a></li>
             <li><a class="dropdown-item edit-entry-action" href="#" data-id="${e.id}"><i class="bi bi-pencil me-2"></i>Edit entry</a></li>
             <li><hr class="dropdown-divider"></li>
             <li><a class="dropdown-item text-danger delete-entry-action" href="#" data-id="${e.id}"><i class="bi bi-trash me-2"></i>Delete</a></li>`;

        return `
        <tr data-id="${e.id}" data-no-dealer="${e.dealer ? 1 : 0}">
          <td onclick="event.stopPropagation()">${isOut ? "" : `<input class="form-check-input row-check" type="checkbox" data-id="${e.id}" ${selectedIds.has(e.id) ? "checked" : ""}>`}</td>
          <td>
            <div class="lead-name">${e.product ? e.product.name : "—"}</div>
            <div class="lead-company">${e.product ? "SKU: " + e.product.sku : ""}</div>
          </td>
          <td class="text-muted-2">${e.product && e.product.model ? e.product.model : "—"}</td>
          <td class="text-muted-2">${e.dealer ? e.dealer.name : "—"}</td>
          <td>${typeBadge}</td>
          <td><span class="qty-pill ${isOut ? "negative" : ""}">${sign}${e.quantity}</span> <span class="text-muted-2 fs-12">${e.unit || ""}</span></td>
          <td class="text-muted-2">${fmtMoney(e.price)}</td>
          <td><span class="total-pill ${isOut ? "negative" : ""}">${sign}${fmtMoney(total)}</span></td>
          <td class="text-muted-2">${fmtDate(e.entry_date)}</td>
          <td class="text-muted-2">${e.creator ? e.creator.name : "—"}</td>
          <td onclick="event.stopPropagation()">
            <div class="dropdown row-actions">
              <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
              <ul class="dropdown-menu dropdown-menu-end">
                ${actionItems}
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
    if (activeFilters.productId) {
      const p = productList.find(p => p.id == activeFilters.productId);
      wrap.insertAdjacentHTML("beforeend", `<span class="filter-pill">Product: ${p ? p.name : activeFilters.productId} <button type="button" data-clear="productId"><i class="bi bi-x"></i></button></span>`);
    }
    if (activeFilters.dealerId) {
      const d = dealers.find(d => d.id == activeFilters.dealerId);
      wrap.insertAdjacentHTML("beforeend", `<span class="filter-pill">Dealer: ${d ? d.name : activeFilters.dealerId} <button type="button" data-clear="dealerId"><i class="bi bi-x"></i></button></span>`);
    }
    if (activeFilters.dateFrom || activeFilters.dateTo) {
      const label = activeFilters.dateFrom && activeFilters.dateTo
        ? `${fmtDate(activeFilters.dateFrom)} – ${fmtDate(activeFilters.dateTo)}`
        : activeFilters.dateFrom ? `From ${fmtDate(activeFilters.dateFrom)}` : `Until ${fmtDate(activeFilters.dateTo)}`;
      wrap.insertAdjacentHTML("beforeend", `<span class="filter-pill">Date: ${label} <button type="button" data-clear="date"><i class="bi bi-x"></i></button></span>`);
    }
    wrap.querySelectorAll("[data-clear]").forEach(btn => {
      btn.addEventListener("click", () => {
        if (btn.dataset.clear === "date") {
          activeFilters.dateFrom = "";
          activeFilters.dateTo = "";
          document.getElementById("filterDateFrom").value = "";
          document.getElementById("filterDateTo").value = "";
        } else {
          activeFilters[btn.dataset.clear] = "";
          const el = document.getElementById(btn.dataset.clear === "productId" ? "filterProduct" : "filterDealer");
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
    document.querySelectorAll("#entriesTbody tr[data-id]").forEach(row => {
      row.addEventListener("click", () => openEntryDetail(parseInt(row.dataset.id, 10)));
    });
    document.querySelectorAll(".row-check").forEach(cb => {
      cb.addEventListener("change", () => {
        const id = parseInt(cb.dataset.id, 10);
        if (cb.checked) selectedIds.add(id); else selectedIds.delete(id);
        updateBulkBar();
      });
    });
    document.querySelectorAll(".view-entry-action").forEach(a => a.addEventListener("click", e => { e.preventDefault(); openEntryDetail(parseInt(a.dataset.id, 10)); }));
    document.querySelectorAll(".edit-entry-action").forEach(a => a.addEventListener("click", e => { e.preventDefault(); openEntryModal(parseInt(a.dataset.id, 10)); }));
    document.querySelectorAll(".delete-entry-action").forEach(a => {
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

  /* ---------------- Filters wiring: stock entries ---------------- */

  function initFilters() {
    document.getElementById("filterSearch").addEventListener("input", e => { activeFilters.search = e.target.value; currentPage = 1; renderTable(); });
    document.getElementById("filterProduct").addEventListener("change", e => { activeFilters.productId = e.target.value; currentPage = 1; renderTable(); });
    document.getElementById("filterDealer").addEventListener("change", e => { activeFilters.dealerId = e.target.value; currentPage = 1; renderTable(); });
    document.getElementById("filterDateFrom").addEventListener("change", e => { activeFilters.dateFrom = e.target.value; currentPage = 1; renderTable(); });
    document.getElementById("filterDateTo").addEventListener("change", e => { activeFilters.dateTo = e.target.value; currentPage = 1; renderTable(); });
    document.getElementById("clearFiltersBtn").addEventListener("click", () => {
      activeFilters = { search: "", productId: "", dealerId: "", dateFrom: "", dateTo: "" };
      ["filterSearch", "filterProduct", "filterDealer", "filterDateFrom", "filterDateTo"].forEach(id => document.getElementById(id).value = "");
      currentPage = 1;
      renderTable();
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

      bootstrap.Modal.getOrCreateInstance(document.getElementById("deleteConfirmModal")).hide();

      if (!res.success) {
        toast(res.message || "Could not delete.", "danger");
        return;
      }

      selectedIds.clear();
      await loadEntries(); // reload rather than splice locally — bulk delete may have skipped bill-generated rows
      toast(res.message || "Deleted", "dark");
      loadProducts(); // stock totals for affected products just changed
    });
  }

  /* ---------------- Generic searchable select (product / dealer) ---------------- */

  function initSearchSelect(opts) {
    const toggle = document.getElementById(opts.toggleId);
    const panel = document.getElementById(opts.panelId);
    const searchInput = document.getElementById(opts.searchId);
    const optionsBox = document.getElementById(opts.optionsId);

    function renderOptions(term) {
      const list = opts.getList(term || "");
      optionsBox.innerHTML = list.map((item, idx) => `<label class="ms-option" data-idx="${idx}">${opts.renderOptionHtml(item)}</label>`).join("")
        || `<div class="fs-13 text-muted-2 p-2">No results found</div>`;
      optionsBox.querySelectorAll("[data-idx]").forEach(el => {
        el.addEventListener("click", () => {
          opts.onSelect(list[parseInt(el.dataset.idx, 10)]);
          panel.classList.remove("show");
        });
      });
    }

    toggle.addEventListener("click", () => {
      panel.classList.toggle("show");
      if (panel.classList.contains("show")) {
        searchInput.value = "";
        renderOptions("");
        searchInput.focus();
      }
    });
    searchInput.addEventListener("input", e => renderOptions(e.target.value));
    document.addEventListener("click", e => {
      if (!toggle.closest(".ms-control").contains(e.target)) panel.classList.remove("show");
    });

    return { refresh: () => renderOptions(searchInput.value) };
  }

  function renderEntryProductChip(product) {
    const chips = document.getElementById("entryProductMsChips");
    chips.innerHTML = product
      ? `<span class="chip">${product.name} — ${product.sku}</span>`
      : `<span class="placeholder">Select a product...</span>`;
    document.getElementById("entryProductId").value = product ? product.id : "";
    selectedEntryProductId = product ? product.id : null;
    if (product && product.unit) {
      document.getElementById("entryUnit").value = product.unit;
    }
  }

  function renderEntryDealerChip(dealer) {
    const chips = document.getElementById("entryDealerMsChips");
    chips.innerHTML = dealer
      ? `<span class="chip">${dealer.name}${dealer.company_name ? " — " + dealer.company_name : ""}</span>`
      : `<span class="placeholder">Select a dealer...</span>`;
    document.getElementById("entryDealerId").value = dealer ? dealer.id : "";
    selectedEntryDealerId = dealer ? dealer.id : null;
  }

  function initEntrySearchSelects() {
    initSearchSelect({
      toggleId: "entryProductMsToggle", panelId: "entryProductMsPanel",
      searchId: "entryProductMsSearch", optionsId: "entryProductMsOptions",
      getList: term => {
        term = term.toLowerCase();
        return productList.filter(p => `${p.name} ${p.sku} ${p.model || ""}`.toLowerCase().includes(term));
      },
      renderOptionHtml: p => `<span>${p.name}</span><span class="ms-option-sub">SKU: ${p.sku}${p.model ? " · " + p.model : ""}${isOutOfStock(p) ? ` · <span style="color:var(--danger)">Out of stock</span>` : ""}</span>`,
      onSelect: renderEntryProductChip,
    });

    initSearchSelect({
      toggleId: "entryDealerMsToggle", panelId: "entryDealerMsPanel",
      searchId: "entryDealerMsSearch", optionsId: "entryDealerMsOptions",
      getList: term => {
        term = term.toLowerCase();
        return dealers.filter(d => `${d.name} ${d.company_name || ""}`.toLowerCase().includes(term));
      },
      renderOptionHtml: d => `<span>${d.name}</span>${d.company_name ? `<span class="ms-option-sub">${d.company_name}</span>` : ""}`,
      onSelect: renderEntryDealerChip,
    });
  }

  /* ---------------- Add / Edit stock entry modal ---------------- */

  function openEntryModal(id) {
    const form = document.getElementById("entryForm");
    form.reset();
    document.getElementById("entryId").value = "";
    renderEntryProductChip(null);
    renderEntryDealerChip(null);

    if (id) {
      const entry = entries.find(e => e.id === id);
      if (entry && entry.type === "out") {
        toast("This entry was generated from a bill — edit the bill instead.", "danger");
        return;
      }
      document.getElementById("entryModalTitle").textContent = "Edit stock entry";
      document.getElementById("entrySubmitBtn").textContent = "Save changes";
      document.getElementById("entryId").value = entry.id;
      if (entry.product) renderEntryProductChip(entry.product);
      if (entry.dealer) renderEntryDealerChip(entry.dealer);
      document.getElementById("entryQuantity").value = entry.quantity;
      document.getElementById("entryUnit").value = entry.unit;
      document.getElementById("entryPrice").value = entry.price;
      document.getElementById("entryDate").value = entry.entry_date ? entry.entry_date.slice(0, 10) : "";
      document.getElementById("entryDescription").value = entry.description || "";
    } else {
      document.getElementById("entryModalTitle").textContent = "Add stock entry";
      document.getElementById("entrySubmitBtn").textContent = "Add entry";
      document.getElementById("entryDate").value = new Date().toISOString().slice(0, 10);
    }

    bootstrap.Modal.getOrCreateInstance(document.getElementById("entryModal")).show();
  }

  function initEntryForm() {
    document.getElementById("openAddEntryBtn").addEventListener("click", () => openEntryModal(null));

    document.getElementById("entryForm").addEventListener("submit", async e => {
      e.preventDefault();
      if (!selectedEntryProductId) {
        toast("Please select a product first.", "danger");
        return;
      }
      const id = document.getElementById("entryId").value;
      const payload = {
        product_id: selectedEntryProductId,
        dealer_id: selectedEntryDealerId,
        quantity: document.getElementById("entryQuantity").value,
        unit: document.getElementById("entryUnit").value,
        price: document.getElementById("entryPrice").value,
        entry_date: document.getElementById("entryDate").value || null,
        description: document.getElementById("entryDescription").value.trim(),
      };

      const url = id ? CFG.routes.update.replace("__ID__", id) : CFG.routes.store;
      const res = await apiFetch(url, {
        method: id ? "PUT" : "POST",
        headers: csrfHeaders({ "Content-Type": "application/json" }),
        body: JSON.stringify(payload),
      });

      if (!res.success) {
        toast(res.message || firstError(res, "Something went wrong."), "danger");
        return;
      }

      if (id) {
        entries = entries.map(e => (e.id == id ? res.item : e));
      } else {
        entries.unshift(res.item);
      }
      renderAll();
      bootstrap.Modal.getOrCreateInstance(document.getElementById("entryModal")).hide();
      toast(res.message);
      loadProducts(); // stock totals for the affected product just changed
    });
  }

  /* ---------------- Stock entry detail offcanvas ---------------- */

  function openEntryDetail(id) {
    const e = entries.find(en => en.id === id);
    if (!e) return;
    currentDetailEntryId = id;

    const isOut = e.type === "out";
    const total = (Number(e.quantity) || 0) * (Number(e.price) || 0);
    const sign = isOut ? "−" : "";

    document.getElementById("edThumb").innerHTML = e.product
      ? productThumbHtml(e.product, 52)
      : `<div class="product-thumb-placeholder" style="width:52px;height:52px"><i class="bi bi-box-seam"></i></div>`;
    document.getElementById("edProductName").textContent = e.product ? e.product.name : "—";
    document.getElementById("edProductSku").textContent = e.product ? "SKU: " + e.product.sku : "";
    document.getElementById("edDealer").textContent = e.dealer ? e.dealer.name : (isOut ? "—" : "No dealer");
    document.getElementById("edQuantity").textContent = sign + fmtQty(e.quantity) + " " + (e.unit || "");
    document.getElementById("edPrice").textContent = fmtMoney(e.price);
    document.getElementById("edTotal").textContent = sign + fmtMoney(total);
    document.getElementById("edDate").textContent = fmtDate(e.entry_date);
    document.getElementById("edCreatedBy").textContent = e.creator ? e.creator.name : "—";
    document.getElementById("edDescription").textContent = e.description || "No description added.";

    const badge = document.getElementById("edTypeBadge");
    badge.className = "movement-badge " + (isOut ? "out" : "in");
    badge.textContent = isOut ? "Sold" : "Purchase";

    document.getElementById("edActionButtons").classList.toggle("d-none", isOut);
    document.getElementById("edLockedNote").classList.toggle("d-none", !isOut);

    document.getElementById("edEditBtn").onclick = () => {
      bootstrap.Offcanvas.getInstance(document.getElementById("entryDetailOffcanvas"))?.hide();
      openEntryModal(e.id);
    };
    document.getElementById("edDeleteBtn").onclick = () => {
      bootstrap.Offcanvas.getInstance(document.getElementById("entryDetailOffcanvas"))?.hide();
      selectedIds = new Set([e.id]);
      bootstrap.Modal.getOrCreateInstance(document.getElementById("deleteConfirmModal")).show();
    };

    const link = document.getElementById("edViewProductLink");
    if (e.product) {
      link.classList.remove("d-none");
      link.onclick = ev => {
        ev.preventDefault();
        bootstrap.Offcanvas.getInstance(document.getElementById("entryDetailOffcanvas"))?.hide();
        openProductDetail(e.product.id);
      };
    } else {
      link.classList.add("d-none");
    }

    bootstrap.Offcanvas.getOrCreateInstance(document.getElementById("entryDetailOffcanvas")).show();
  }

  /* ================================================================
     PRODUCTS TAB
     ================================================================ */

  function renderProductStats() {
    document.getElementById("statProducts").textContent = productList.length;
    document.getElementById("statOutOfStock").textContent = productList.filter(isOutOfStock).length;
    document.getElementById("statValue").textContent = fmtMoney(computeNetStockValue());
  }

  function getFilteredProducts() {
    return productList.filter(p => {
      const term = productFilters.search.toLowerCase();
      const haystack = `${p.name} ${p.sku} ${p.model || ""}`.toLowerCase();
      const matchesSearch = !term || haystack.includes(term);
      const matchesStatus = !productFilters.status
        || (productFilters.status === "out" && isOutOfStock(p))
        || (productFilters.status === "in" && !isOutOfStock(p));
      return matchesSearch && matchesStatus;
    });
  }

  function renderProductsTable() {
    const filtered = getFilteredProducts();
    const totalPages = Math.max(1, Math.ceil(filtered.length / PRODUCT_PAGE_SIZE));
    productPage = Math.min(productPage, totalPages);
    const start = (productPage - 1) * PRODUCT_PAGE_SIZE;
    const pageItems = filtered.slice(start, start + PRODUCT_PAGE_SIZE);

    const tbody = document.getElementById("productsTbody");

    if (pageItems.length === 0) {
      tbody.innerHTML = `<tr><td colspan="7"><div class="empty-state">
        <i class="bi bi-box-seam"></i><h6>No products found</h6>
        <p class="fs-13 mb-0">Try adjusting filters or add a new product.</p></div></td></tr>`;
    } else {
      tbody.innerHTML = pageItems.map(p => `
        <tr data-id="${p.id}" data-out-of-stock="${isOutOfStock(p) ? 1 : 0}">
          <td>${productThumbHtml(p)}</td>
          <td>
            <div class="lead-name">${p.name}</div>
            <div class="lead-company">SKU: ${p.sku}</div>
          </td>
          <td class="text-muted-2">${p.model || "—"}</td>
          <td><span class="qty-pill">${fmtQty(p.total_quantity)}</span> <span class="text-muted-2 fs-12">${p.unit || ""}</span></td>
          <td>${stockBadgeHtml(p)}</td>
          <td class="text-muted-2">${p.creator ? p.creator.name : "—"}</td>
          <td onclick="event.stopPropagation()">
            <div class="dropdown row-actions">
              <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item view-product-action" href="#" data-id="${p.id}"><i class="bi bi-eye me-2"></i>View details</a></li>
                <li><a class="dropdown-item edit-product-action" href="#" data-id="${p.id}"><i class="bi bi-pencil me-2"></i>Edit product</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger delete-product-action" href="#" data-id="${p.id}"><i class="bi bi-trash me-2"></i>Delete</a></li>
              </ul>
            </div>
          </td>
        </tr>`).join("");
    }

    renderProductPills();
    renderProductPagination(filtered.length, totalPages);
    bindProductRowEvents();
  }

  function renderProductPills() {
    const wrap = document.getElementById("productActivePills");
    wrap.innerHTML = "";
    if (productFilters.status) {
      const label = productFilters.status === "out" ? "Out of stock" : "In stock";
      wrap.insertAdjacentHTML("beforeend", `<span class="filter-pill">Status: ${label} <button type="button" data-pclear="status"><i class="bi bi-x"></i></button></span>`);
    }
    wrap.querySelectorAll("[data-pclear]").forEach(btn => {
      btn.addEventListener("click", () => {
        productFilters[btn.dataset.pclear] = "";
        document.getElementById("productFilterStatus").value = "";
        productPage = 1;
        renderProductsTable();
      });
    });
  }

  function renderProductPagination(total, totalPages) {
    const start = total === 0 ? 0 : (productPage - 1) * PRODUCT_PAGE_SIZE + 1;
    const end = Math.min(productPage * PRODUCT_PAGE_SIZE, total);
    document.getElementById("productPageInfo").textContent = `Showing ${start}–${end} of ${total}`;

    const nav = document.getElementById("productPageButtons");
    let html = `<button class="btn-page" ${productPage === 1 ? "disabled" : ""} data-ppage="prev"><i class="bi bi-chevron-left"></i></button>`;
    for (let i = 1; i <= totalPages; i++) html += `<button class="btn-page ${i === productPage ? "active" : ""}" data-ppage="${i}">${i}</button>`;
    html += `<button class="btn-page" ${productPage === totalPages ? "disabled" : ""} data-ppage="next"><i class="bi bi-chevron-right"></i></button>`;
    nav.innerHTML = html;
    nav.querySelectorAll("[data-ppage]").forEach(btn => {
      btn.addEventListener("click", () => {
        if (btn.dataset.ppage === "prev") productPage--;
        else if (btn.dataset.ppage === "next") productPage++;
        else productPage = parseInt(btn.dataset.ppage, 10);
        renderProductsTable();
      });
    });
  }

  function bindProductRowEvents() {
    document.querySelectorAll("#productsTbody tr[data-id]").forEach(row => {
      row.addEventListener("click", () => openProductDetail(parseInt(row.dataset.id, 10)));
    });
    document.querySelectorAll(".view-product-action").forEach(a => a.addEventListener("click", e => { e.preventDefault(); openProductDetail(parseInt(a.dataset.id, 10)); }));
    document.querySelectorAll(".edit-product-action").forEach(a => a.addEventListener("click", e => { e.preventDefault(); openProductModal(parseInt(a.dataset.id, 10)); }));
    document.querySelectorAll(".delete-product-action").forEach(a => {
      a.addEventListener("click", e => { e.preventDefault(); confirmDeleteProduct(parseInt(a.dataset.id, 10)); });
    });
  }

  function initProductFilters() {
    document.getElementById("productFilterSearch").addEventListener("input", e => { productFilters.search = e.target.value; productPage = 1; renderProductsTable(); });
    document.getElementById("productFilterStatus").addEventListener("change", e => { productFilters.status = e.target.value; productPage = 1; renderProductsTable(); });
    document.getElementById("productClearFiltersBtn").addEventListener("click", () => {
      productFilters = { search: "", status: "" };
      document.getElementById("productFilterSearch").value = "";
      document.getElementById("productFilterStatus").value = "";
      productPage = 1;
      renderProductsTable();
    });
  }

  /* ================================================================
     SOLD PRODUCTS TAB — read-only slice of `entries` where type === "out".
     No separate fetch: reuses whatever loadEntries() already pulled in,
     so it always matches the Stock Entries tab exactly (same source of
     truth, just filtered + re-paginated for this view).
     ================================================================ */

  function getFilteredSoldEntries() {
    return entries.filter(e => e.type === "out").filter(e => {
      const term = soldFilters.search.toLowerCase();
      const haystack = `${e.product ? e.product.name + " " + e.product.sku + " " + (e.product.model || "") : ""} ${e.description || ""}`.toLowerCase();
      const matchesSearch = !term || haystack.includes(term);
      const matchesProduct = !soldFilters.productId || (e.product && e.product.id == soldFilters.productId);

      const entryDate = (e.entry_date || "").slice(0, 10);
      const matchesDateFrom = !soldFilters.dateFrom || (entryDate && entryDate >= soldFilters.dateFrom);
      const matchesDateTo = !soldFilters.dateTo || (entryDate && entryDate <= soldFilters.dateTo);

      return matchesSearch && matchesProduct && matchesDateFrom && matchesDateTo;
    });
  }

  function renderSoldTable() {
    const filtered = getFilteredSoldEntries();
    const totalPages = Math.max(1, Math.ceil(filtered.length / SOLD_PAGE_SIZE));
    soldPage = Math.min(soldPage, totalPages);
    const start = (soldPage - 1) * SOLD_PAGE_SIZE;
    const pageItems = filtered.slice(start, start + SOLD_PAGE_SIZE);

    const tbody = document.getElementById("soldTbody");

    if (pageItems.length === 0) {
      tbody.innerHTML = `<tr><td colspan="8"><div class="empty-state">
        <i class="bi bi-bag-check"></i><h6>No sold products found</h6>
        <p class="fs-13 mb-0">Products sold via a bill will show up here automatically.</p></div></td></tr>`;
    } else {
      tbody.innerHTML = pageItems.map(e => {
        const total = (Number(e.quantity) || 0) * (Number(e.price) || 0);
        return `
        <tr data-id="${e.id}">
          <td>
            <div class="lead-name">${e.product ? e.product.name : "—"}</div>
            <div class="lead-company">${e.product ? "SKU: " + e.product.sku : ""}</div>
          </td>
          <td class="text-muted-2">${e.product && e.product.model ? e.product.model : "—"}</td>
          <td><span class="qty-pill negative">−${fmtQty(e.quantity)}</span> <span class="text-muted-2 fs-12">${e.unit || ""}</span></td>
          <td class="text-muted-2">${fmtMoney(e.price)}</td>
          <td><span class="total-pill negative">−${fmtMoney(total)}</span></td>
          <td class="text-muted-2">${e.description || "—"}</td>
          <td class="text-muted-2">${fmtDate(e.entry_date)}</td>
          <td class="text-muted-2">${e.creator ? e.creator.name : "—"}</td>
        </tr>`;
      }).join("");
    }

    renderSoldPills();
    renderSoldPagination(filtered.length, totalPages);
    bindSoldRowEvents();
  }

  function renderSoldPills() {
    const wrap = document.getElementById("soldActivePills");
    wrap.innerHTML = "";
    if (soldFilters.productId) {
      const p = productList.find(p => p.id == soldFilters.productId);
      wrap.insertAdjacentHTML("beforeend", `<span class="filter-pill">Product: ${p ? p.name : soldFilters.productId} <button type="button" data-sclear="productId"><i class="bi bi-x"></i></button></span>`);
    }
    if (soldFilters.dateFrom || soldFilters.dateTo) {
      const label = soldFilters.dateFrom && soldFilters.dateTo
        ? `${fmtDate(soldFilters.dateFrom)} – ${fmtDate(soldFilters.dateTo)}`
        : soldFilters.dateFrom ? `From ${fmtDate(soldFilters.dateFrom)}` : `Until ${fmtDate(soldFilters.dateTo)}`;
      wrap.insertAdjacentHTML("beforeend", `<span class="filter-pill">Date: ${label} <button type="button" data-sclear="date"><i class="bi bi-x"></i></button></span>`);
    }
    wrap.querySelectorAll("[data-sclear]").forEach(btn => {
      btn.addEventListener("click", () => {
        if (btn.dataset.sclear === "date") {
          soldFilters.dateFrom = "";
          soldFilters.dateTo = "";
          document.getElementById("soldFilterDateFrom").value = "";
          document.getElementById("soldFilterDateTo").value = "";
        } else {
          soldFilters[btn.dataset.sclear] = "";
          document.getElementById("soldFilterProduct").value = "";
        }
        soldPage = 1;
        renderSoldTable();
      });
    });
  }

  function renderSoldPagination(total, totalPages) {
    const start = total === 0 ? 0 : (soldPage - 1) * SOLD_PAGE_SIZE + 1;
    const end = Math.min(soldPage * SOLD_PAGE_SIZE, total);
    document.getElementById("soldPageInfo").textContent = `Showing ${start}–${end} of ${total}`;

    const nav = document.getElementById("soldPageButtons");
    let html = `<button class="btn-page" ${soldPage === 1 ? "disabled" : ""} data-spage="prev"><i class="bi bi-chevron-left"></i></button>`;
    for (let i = 1; i <= totalPages; i++) html += `<button class="btn-page ${i === soldPage ? "active" : ""}" data-spage="${i}">${i}</button>`;
    html += `<button class="btn-page" ${soldPage === totalPages ? "disabled" : ""} data-spage="next"><i class="bi bi-chevron-right"></i></button>`;
    nav.innerHTML = html;
    nav.querySelectorAll("[data-spage]").forEach(btn => {
      btn.addEventListener("click", () => {
        if (btn.dataset.spage === "prev") soldPage--;
        else if (btn.dataset.spage === "next") soldPage++;
        else soldPage = parseInt(btn.dataset.spage, 10);
        renderSoldTable();
      });
    });
  }

  function bindSoldRowEvents() {
    // Reuses the same read-only entry-detail offcanvas as the Stock
    // Entries tab (it already knows how to render "Sold" rows).
    document.querySelectorAll("#soldTbody tr[data-id]").forEach(row => {
      row.addEventListener("click", () => openEntryDetail(parseInt(row.dataset.id, 10)));
    });
  }

  function initSoldFilters() {
    document.getElementById("soldFilterSearch").addEventListener("input", e => { soldFilters.search = e.target.value; soldPage = 1; renderSoldTable(); });
    document.getElementById("soldFilterProduct").addEventListener("change", e => { soldFilters.productId = e.target.value; soldPage = 1; renderSoldTable(); });
    document.getElementById("soldFilterDateFrom").addEventListener("change", e => { soldFilters.dateFrom = e.target.value; soldPage = 1; renderSoldTable(); });
    document.getElementById("soldFilterDateTo").addEventListener("change", e => { soldFilters.dateTo = e.target.value; soldPage = 1; renderSoldTable(); });
    document.getElementById("soldClearFiltersBtn").addEventListener("click", () => {
      soldFilters = { search: "", productId: "", dateFrom: "", dateTo: "" };
      ["soldFilterSearch", "soldFilterProduct", "soldFilterDateFrom", "soldFilterDateTo"].forEach(id => document.getElementById(id).value = "");
      soldPage = 1;
      renderSoldTable();
    });
  }

  /* ---------------- Tabs ---------------- */

  function initTabs() {
    document.querySelectorAll("#invTabs .nav-link").forEach(tab => {
      tab.addEventListener("click", e => {
        e.preventDefault();
        document.querySelectorAll("#invTabs .nav-link").forEach(t => t.classList.remove("active"));
        tab.classList.add("active");
        activeTab = tab.dataset.tab;
        document.getElementById("entriesView").classList.toggle("d-none", activeTab !== "entries");
        document.getElementById("productsView").classList.toggle("d-none", activeTab !== "products");
        document.getElementById("soldView").classList.toggle("d-none", activeTab !== "sold");
        document.getElementById("openAddEntryBtn").classList.toggle("d-none", activeTab !== "entries");
        document.getElementById("openAddProductTabBtn").classList.toggle("d-none", activeTab !== "products");
        if (activeTab === "products") renderProductsTable();
        if (activeTab === "sold") renderSoldTable();
      });
    });
  }

  /* ---------------- Add / Edit product modal (incl. images) ---------------- */

  function renderApGallery() {
    const box = document.getElementById("apGallery");

    const existingHtml = editProductExistingImages.map(img => `
      <div class="pd-gallery-item">
        <img src="${img.url}" alt="">
        <button type="button" class="pd-gallery-remove" data-existing-image-id="${img.id}" title="Remove image"><i class="bi bi-x"></i></button>
      </div>`).join("");

    const queuedHtml = addProductQueuedFiles.map((f, idx) => `
      <div class="pd-gallery-item">
        <img src="${f.previewUrl}" alt="">
        <button type="button" class="pd-gallery-remove" data-queued-idx="${idx}" title="Remove"><i class="bi bi-x"></i></button>
      </div>`).join("");

    const combined = existingHtml + queuedHtml;
    box.innerHTML = combined || `<div class="fs-13 text-muted-2">No images added yet.</div>`;

    box.querySelectorAll("[data-existing-image-id]").forEach(btn => {
      btn.addEventListener("click", async () => {
        const imageId = parseInt(btn.dataset.existingImageId, 10);
        const res = await apiFetch(CFG.routes.productImageDestroy.replace("__ID__", imageId), { method: "DELETE" });
        if (!res.success) {
          toast(res.message || "Could not remove image.", "danger");
          return;
        }
        editProductExistingImages = editProductExistingImages.filter(img => img.id !== imageId);
        const editId = document.getElementById("editProductId").value;
        if (editId) {
          productList = productList.map(p => (p.id == editId ? Object.assign({}, p, { images: editProductExistingImages }) : p));
          if (activeTab === "products") renderProductsTable();
        }
        renderApGallery();
        toast("Image removed");
      });
    });

    box.querySelectorAll("[data-queued-idx]").forEach(btn => {
      btn.addEventListener("click", () => {
        const idx = parseInt(btn.dataset.queuedIdx, 10);
        URL.revokeObjectURL(addProductQueuedFiles[idx].previewUrl);
        addProductQueuedFiles.splice(idx, 1);
        renderApGallery();
      });
    });
  }

  function initApImages() {
    const addBtn = document.getElementById("apAddImagesBtn");
    const input = document.getElementById("apImageInput");

    addBtn.addEventListener("click", () => input.click());

    input.addEventListener("change", async () => {
      if (!input.files.length) return;
      const editId = document.getElementById("editProductId").value;

      if (editId) {
        // Editing an existing product — upload immediately.
        const formData = new FormData();
        Array.from(input.files).forEach(f => formData.append("images[]", f));
        const res = await apiUpload(CFG.routes.productImagesStore.replace("__ID__", editId), formData);
        if (!res.success) {
          toast(firstError(res, "Could not upload image(s)."), "danger");
        } else {
          editProductExistingImages = editProductExistingImages.concat(res.images);
          productList = productList.map(p => (p.id == editId ? Object.assign({}, p, { images: editProductExistingImages }) : p));
          if (activeTab === "products") renderProductsTable();
          renderApGallery();
          toast(res.message || "Image(s) uploaded.");
        }
      } else {
        // Brand new product, not saved yet — queue locally, upload right
        // after creation succeeds (see initProductForm).
        Array.from(input.files).forEach(f => {
          addProductQueuedFiles.push({ file: f, previewUrl: URL.createObjectURL(f) });
        });
        renderApGallery();
      }
      input.value = "";
    });
  }

  function openProductModal(id) {
    const form = document.getElementById("addProductForm");
    form.reset();
    document.getElementById("editProductId").value = "";

    addProductQueuedFiles.forEach(f => URL.revokeObjectURL(f.previewUrl));
    addProductQueuedFiles = [];
    editProductExistingImages = [];

    const modalTitle = document.getElementById("addProductModalTitle");
    const submitBtn = document.getElementById("addProductSubmitBtn");
    const hint = document.getElementById("apImagesHint");

    if (id) {
      const p = productList.find(pr => pr.id === id);
      productModalMode = "edit";
      document.getElementById("editProductId").value = id;
      modalTitle.textContent = "Edit product";
      submitBtn.textContent = "Save changes";
      hint.textContent = "";
      if (p) {
        document.getElementById("addProductName").value = p.name;
        document.getElementById("addProductSku").value = p.sku;
        document.getElementById("addProductModel").value = p.model || "";
        document.getElementById("addProductUnit").value = p.unit;
        document.getElementById("addProductDescription").value = p.description || "";
        editProductExistingImages = (p.images || []).slice();
      }
    } else {
      productModalMode = "quickAdd";
      modalTitle.textContent = "Add new product";
      submitBtn.textContent = "Add product";
      hint.textContent = "Images upload right after you save the product.";
    }

    renderApGallery();
    bootstrap.Modal.getOrCreateInstance(document.getElementById("addProductModal")).show();
  }

  function initAddEditProductButtons() {
    document.getElementById("openAddProductBtn").addEventListener("click", () => openProductModal(null));
    document.getElementById("openAddProductTabBtn").addEventListener("click", () => openProductModal(null));
  }

  function initProductForm() {
    document.getElementById("addProductForm").addEventListener("submit", async e => {
      e.preventDefault();
      const payload = {
        name: document.getElementById("addProductName").value.trim(),
        sku: document.getElementById("addProductSku").value.trim(),
        model: document.getElementById("addProductModel").value.trim(),
        unit: document.getElementById("addProductUnit").value,
        description: document.getElementById("addProductDescription").value.trim(),
      };
      const editId = document.getElementById("editProductId").value;

      const url = editId ? CFG.routes.productUpdate.replace("__ID__", editId) : CFG.routes.productStore;
      const res = await apiFetch(url, {
        method: editId ? "PUT" : "POST",
        headers: csrfHeaders({ "Content-Type": "application/json" }),
        body: JSON.stringify(payload),
      });

      if (!res.success) {
        toast(firstError(res, "Could not save product."), "danger");
        return;
      }

      if (editId) {
        const updated = res.product;
        updated.images = editProductExistingImages;
        productList = productList.map(p => (p.id == editId ? Object.assign({}, p, updated) : p));
        const opt = document.querySelector(`#filterProduct option[value="${editId}"]`);
        if (opt) opt.textContent = updated.name;
        const soldOpt = document.querySelector(`#soldFilterProduct option[value="${editId}"]`);
        if (soldOpt) soldOpt.textContent = updated.name;
        if (activeTab === "products") renderProductsTable();
        if (currentDetailProductId == editId) openProductDetail(parseInt(editId, 10));
        toast("Product updated.");
        bootstrap.Modal.getOrCreateInstance(document.getElementById("addProductModal")).hide();
        return;
      }

      const created = res.product;
      created.total_quantity = 0;
      created.is_out_of_stock = true;
      created.images = [];

      if (addProductQueuedFiles.length) {
        const formData = new FormData();
        addProductQueuedFiles.forEach(f => formData.append("images[]", f.file));
        const imgRes = await apiUpload(CFG.routes.productImagesStore.replace("__ID__", created.id), formData);
        if (imgRes.success) {
          created.images = imgRes.images;
        } else {
          toast(firstError(imgRes, "Product saved, but images failed to upload."), "danger");
        }
        addProductQueuedFiles.forEach(f => URL.revokeObjectURL(f.previewUrl));
        addProductQueuedFiles = [];
      }

      productList.unshift(created);
      document.getElementById("filterProduct").insertAdjacentHTML("beforeend", `<option value="${created.id}">${created.name}</option>`);
      document.getElementById("soldFilterProduct").insertAdjacentHTML("beforeend", `<option value="${created.id}">${created.name}</option>`);
      renderProductStats();
      if (activeTab === "products") renderProductsTable();
      // If opened from the stock-entry modal's quick-add "+", also select
      // it there right away (entryModal stays "show" behind this stacked
      // modal, so this only fires in that context).
      if (productModalMode === "quickAdd" && document.getElementById("entryModal").classList.contains("show")) {
        renderEntryProductChip(created);
      }
      toast("Product added.");
      bootstrap.Modal.getOrCreateInstance(document.getElementById("addProductModal")).hide();
    });
  }

  /* ---------------- Delete product ---------------- */

  function confirmDeleteProduct(id) {
    pendingDeleteProductId = id;
    bootstrap.Modal.getOrCreateInstance(document.getElementById("deleteProductConfirmModal")).show();
  }

  function initProductDelete() {
    document.getElementById("confirmDeleteProductBtn").addEventListener("click", async () => {
      if (!pendingDeleteProductId) return;
      const res = await apiFetch(CFG.routes.productDestroy.replace("__ID__", pendingDeleteProductId), { method: "DELETE" });
      bootstrap.Modal.getOrCreateInstance(document.getElementById("deleteProductConfirmModal")).hide();

      if (!res.success) {
        toast(res.message || "Could not delete product.", "danger");
        pendingDeleteProductId = null;
        return;
      }

      productList = productList.filter(p => p.id !== pendingDeleteProductId);
      const opt = document.querySelector(`#filterProduct option[value="${pendingDeleteProductId}"]`);
      if (opt) opt.remove();
      const soldOpt = document.querySelector(`#soldFilterProduct option[value="${pendingDeleteProductId}"]`);
      if (soldOpt) soldOpt.remove();
      renderProductStats();
      if (activeTab === "products") renderProductsTable();

      const offc = bootstrap.Offcanvas.getInstance(document.getElementById("productDetailOffcanvas"));
      if (offc && currentDetailProductId === pendingDeleteProductId) offc.hide();

      toast(res.message || "Product deleted.", "dark");
      pendingDeleteProductId = null;
    });
  }

  /* ---------------- Product detail offcanvas ---------------- */

  async function openProductDetail(id) {
    const res = await apiFetch(CFG.routes.productShow.replace("__ID__", id));
    if (!res.success) return;
    const p = res.product;
    currentDetailProductId = p.id;

    document.getElementById("pdThumb").innerHTML = productThumbHtml(p, 52);
    document.getElementById("pdName").textContent = p.name;
    document.getElementById("pdSku").textContent = "SKU: " + p.sku;
    document.getElementById("pdModel").textContent = p.model || "—";
    document.getElementById("pdUnit").textContent = p.unit || "—";
    document.getElementById("pdTotalStock").textContent = fmtQty(p.total_quantity) + (p.unit ? " " + p.unit : "");
    document.getElementById("pdCreatedBy").textContent = p.creator ? p.creator.name : "—";
    document.getElementById("pdDescription").textContent = p.description || "No description added.";
    applyStockBadge(document.getElementById("pdStockBadge"), p);

    renderGallery(p.images || []);
    renderProductEntries(res.entries || []);

    document.getElementById("pdEditBtn").onclick = () => openProductModal(p.id);
    document.getElementById("pdDeleteBtn").onclick = () => confirmDeleteProduct(p.id);

    bootstrap.Offcanvas.getOrCreateInstance(document.getElementById("productDetailOffcanvas")).show();
  }

  function renderGallery(images) {
    const box = document.getElementById("pdGallery");
    if (!images.length) {
      box.innerHTML = `<div class="fs-13 text-muted-2 mb-2">No images added yet — add some from the edit screen.</div>`;
      return;
    }
    box.innerHTML = images.map(img => `
      <div class="pd-gallery-item">
        <img src="${img.url}" alt="">
      </div>`).join("");
  }

  function renderProductEntries(entries) {
    const box = document.getElementById("pdEntries");
    if (!entries.length) {
      box.innerHTML = `<div class="empty-state py-4"><i class="bi bi-clipboard-data"></i><h6 class="fs-13">No stock entries yet</h6></div>`;
      return;
    }
    box.innerHTML = entries.map(e => {
      const isOut = e.type === "out";
      const total = (Number(e.quantity) || 0) * (Number(e.price) || 0);
      const sign = isOut ? "−" : "";
      return `
      <div class="reminder-item">
        <div class="flex-grow-1">
          <div class="reminder-title">${isOut ? "Sold" : (e.dealer ? e.dealer.name : "No dealer")} · ${sign}${fmtQty(e.quantity)} ${e.unit || ""}</div>
          <div class="reminder-meta">
            <span><i class="bi bi-calendar3 me-1"></i>${fmtDate(e.entry_date)}</span>
            <span>${fmtMoney(e.price)} / unit</span>
            <span class="fw-600" style="color:${isOut ? "var(--danger)" : "var(--success)"}">${sign}${fmtMoney(total)}</span>
          </div>
        </div>
      </div>`;
    }).join("");
  }

  /* ---------------- Quick-add dealer ---------------- */

  function initQuickAddDealer() {
    document.getElementById("openAddDealerBtn").addEventListener("click", () => {
      document.getElementById("addDealerForm").reset();
      bootstrap.Modal.getOrCreateInstance(document.getElementById("addDealerModal")).show();
    });

    document.getElementById("addDealerForm").addEventListener("submit", async e => {
      e.preventDefault();
      const payload = {
        name: document.getElementById("addDealerName").value.trim(),
        company_name: document.getElementById("addDealerCompany").value.trim(),
        phone: document.getElementById("addDealerPhone").value.trim(),
        email: document.getElementById("addDealerEmail").value.trim(),
        address: document.getElementById("addDealerAddress").value.trim(),
      };
      const res = await apiFetch(CFG.routes.dealerStore, {
        method: "POST", headers: csrfHeaders({ "Content-Type": "application/json" }), body: JSON.stringify(payload),
      });
      if (!res.success) {
        toast(firstError(res, "Could not add dealer."), "danger");
        return;
      }
      dealers.push(res.dealer);
      document.getElementById("filterDealer").insertAdjacentHTML("beforeend", `<option value="${res.dealer.id}">${res.dealer.name}</option>`);
      renderEntryDealerChip(res.dealer);
      bootstrap.Modal.getOrCreateInstance(document.getElementById("addDealerModal")).hide();
      renderStats();
      toast("Dealer added — selected for this entry.");
    });
  }

  /* ---------------- Boot ---------------- */

  document.addEventListener("DOMContentLoaded", () => {
    initFilters();
    initBulkActions();
    initEntrySearchSelects();
    initEntryForm();
    initAddEditProductButtons();
    initProductForm();
    initApImages();
    initQuickAddDealer();
    initTabs();
    initProductFilters();
    initSoldFilters();
    initProductDelete();
    loadEntries();
    loadProducts();
  });
})();
</script>

@endsection