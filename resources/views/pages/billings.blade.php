@extends('layouts.app')
@section('title', 'Billing | Dalal Adda')

@section('content')

<style>
  /* ---- Bill type badges (reuse .badge-status base look, same pattern as leads) ---- */
  .badge-status.bt-invoice   { background: var(--success-bg); color: var(--success); }
  .badge-status.bt-quotation { background: var(--info-bg);    color: var(--info); }
  .badge-status.bt-pi        { background: var(--warning-bg); color: var(--warning); }

  /* ---- Source toggle / bill-type radios (same look as leads' apt-type-group) ---- */
  .apt-type-group { display: flex; gap: 10px; }
  .apt-type-option { flex: 1; }
  .apt-type-option input { display: none; }
  .apt-type-option label {
    display: flex; align-items: center; justify-content: center; gap: 7px;
    border: 1.5px solid var(--border); border-radius: 10px; padding: 9px; font-size: 13px; font-weight: 600;
    color: var(--text-muted); cursor: pointer; background: #FBFBFE;
  }
  .apt-type-option input:checked + label { border-color: var(--primary); color: var(--primary); background: var(--primary-50); }

  /* ---- Line item cards (product rows inside the bill form) ---- */
  .item-row { border: 1.5px solid var(--border); border-radius: 12px; padding: 12px; margin-bottom: 10px; background: #FBFBFE; }
  .item-row .item-meta { font-size: 11px; color: var(--text-muted); }
  .item-row .item-meta.over-stock { color: var(--danger); font-weight: 700; }
  .item-row .item-line-total { font-size: 14px; }
  .tax-chip {
    display: inline-flex; align-items: center; gap: 5px; background: var(--primary-50); color: var(--primary);
    font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 20px;
  }
  .tax-chip button { border: none; background: none; color: inherit; padding: 0; line-height: 1; font-size: 13px; }

  /* ---- Totals summary box ---- */
  .totals-box { background: var(--primary-50); border-radius: 12px; padding: 14px 16px; }
  .totals-box .t-row { display: flex; justify-content: space-between; font-size: 13px; padding: 3px 0; }
  .totals-box .t-row.grand { font-size: 16px; font-weight: 800; color: var(--primary); border-top: 1px dashed var(--border); margin-top: 6px; padding-top: 8px; }

  /* ---- Bank / header manage lists (inside their modals) ---- */
  .manage-list { max-height: 190px; overflow-y: auto; border-top: 1px solid var(--border); margin-top: 14px; padding-top: 10px; }
  .manage-list-row { display: flex; align-items: center; gap: 10px; padding: 8px 4px; border-radius: 8px; }
  .manage-list-row:hover { background: var(--primary-50); }
  .manage-list-row .mlr-name { font-size: 13px; font-weight: 700; color: var(--text); }
  .manage-list-row .mlr-sub { font-size: 11.5px; color: var(--text-muted); }
  .manage-list-row .mlr-actions { margin-left: auto; display: flex; gap: 4px; }
  .manage-list-row .mlr-actions button { background: none; border: none; color: var(--text-muted); padding: 4px 6px; }
  .manage-list-row .mlr-actions button:hover { color: var(--primary); }
  .manage-list-row .mlr-actions button.danger:hover { color: var(--danger); }
  .default-pill { background: var(--gold-bg); color: var(--gold); font-size: 10px; font-weight: 800; padding: 2px 7px; border-radius: 10px; margin-left: 6px; }

  /* ---- Searchable single-select (lead picker, product picker) — same
     pattern as the "Assign to" search control on the Leads page ---- */
  .ms-control { position: relative; }
  .ms-toggle {
    width: 100%; text-align: left; background: #FBFBFE; border: 1.5px solid var(--border);
    border-radius: 10px; padding: 10px 13px; font-size: 13.5px; color: var(--text);
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
  }
  .ms-toggle .chips { display: flex; flex-wrap: wrap; gap: 5px; overflow: hidden; }
  .ms-toggle .chip { background: var(--primary-50); color: var(--primary); font-size: 12px; font-weight: 700; padding: 3px 9px; border-radius: 20px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .ms-toggle .placeholder { color: #A7ABC2; }
  .ms-panel {
    display: none; position: absolute; top: calc(100% + 6px); left: 0; right: 0; z-index: 50;
    background: #fff; border: 1px solid var(--border); border-radius: 12px; box-shadow: var(--shadow-lg);
    padding: 10px; max-height: 260px; overflow-y: auto;
  }
  .ms-panel.show { display: block; }
  .ms-panel input.ms-search { margin-bottom: 8px; }
  .ms-option { display: flex; align-items: center; gap: 9px; padding: 7px 6px; border-radius: 7px; font-size: 13px; cursor: pointer; }
  .ms-option:hover { background: var(--primary-50); }

  /* Product picker specific sizing — narrower toggle used inside the item row */
  .item-product-control .ms-toggle { padding: 9px 11px; font-size: 13px; }
  .item-product-control .ms-panel { width: 340px; }
  .ms-option .msop-meta { font-size: 11px; color: var(--text-muted); margin-top: 1px; }
  .ms-option .msop-meta.msop-out { color: var(--danger); font-weight: 600; }

  /* ---- Bill detail offcanvas ---- */
  .bd-item-row { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 11px 16px; border-bottom: 1px solid var(--border); }
  .bd-item-row:last-child { border-bottom: none; }
  .bd-item-row .bd-item-name { font-weight: 600; font-size: 13px; color: var(--text); }
  .bd-item-row .bd-item-meta { font-size: 11.5px; color: var(--text-muted); margin-top: 1px; }
  .bd-item-row .bd-item-total { font-weight: 700; font-size: 13px; white-space: nowrap; }

.bill-modal-dialog{
    max-width:95vw;
    width:95vw;
    height:95vh;
    margin:2.5vh auto;
}

.bill-modal-dialog .modal-content{
    height:100%;
    max-height:100%;
    overflow:hidden;
}

/* The header/body/footer live INSIDE #billForm, not as direct children of
   .modal-content — so the form itself has to be the flex column, or the
   flex rules below never reach them. */
.bill-modal-dialog .modal-content > form{
    height:100%;
    display:flex;
    flex-direction:column;
    min-height:0;
    overflow:hidden;
}

.bill-modal-dialog .modal-body{
    flex:1 1 auto;
    min-height:0;
    overflow-y:auto !important;
}

.bill-modal-dialog .modal-header,
.bill-modal-dialog .modal-footer{
    flex:0 0 auto;
}
</style>

<div class="page-content">
  <section class="page-section active" id="page-billing">

    <div class="page-header">
      <div>
        <span class="breadcrumb-eyebrow">Finance</span>
        <h1>Billing</h1>
        <p>Generate invoices, quotations &amp; proforma invoices, and keep stock in sync.</p>
      </div>
      <div class="page-header-actions d-flex gap-2">
        <button class="btn btn-primary" id="openAddBillBtn">
          <i class="bi bi-plus-lg me-1"></i>Generate Bill
        </button>
      </div>
    </div>

    <!-- Stat cards -->
    <div class="row g-3 mb-3">
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div><div class="stat-label">Total Bills</div><div class="stat-value" id="statTotalBills">0</div></div>
          <div class="stat-icon" style="background:var(--primary-50); color:var(--primary)"><i class="bi bi-receipt"></i></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div><div class="stat-label">Invoices</div><div class="stat-value" id="statInvoices">0</div></div>
          <div class="stat-icon" style="background:var(--success-bg); color:var(--success)"><i class="bi bi-file-earmark-check"></i></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div><div class="stat-label">Quotations / PI</div><div class="stat-value" id="statQuotes">0</div></div>
          <div class="stat-icon" style="background:var(--info-bg); color:var(--info)"><i class="bi bi-file-earmark-text"></i></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div><div class="stat-label">Invoiced Revenue</div><div class="stat-value" id="statRevenue">₹0</div></div>
          <div class="stat-icon" style="background:var(--gold-bg); color:var(--gold)"><i class="bi bi-cash-stack"></i></div>
        </div>
      </div>
    </div>

    <!-- Filter bar -->
    <div class="filter-bar">
      <div class="filter-search">
        <i class="bi bi-search"></i>
        <input type="text" class="form-control" id="filterSearch" placeholder="Search by bill number, customer or phone...">
      </div>
      <select class="form-select" id="filterBillType" style="max-width:160px">
        <option value="">All Types</option>
        @foreach($billTypes as $key => $label)
          <option value="{{ $key }}">{{ $label }}</option>
        @endforeach
      </select>
      <div class="d-flex align-items-center gap-1">
        <input type="date" class="form-control" id="filterDateFrom" style="max-width:150px" title="Billed from">
        <span class="text-muted-2 fs-12">to</span>
        <input type="date" class="form-control" id="filterDateTo" style="max-width:150px" title="Billed to">
      </div>
      <button class="btn btn-outline-secondary btn-sm" id="clearFiltersBtn">Clear</button>
      @if(auth('admin')->user()->isSuperAdmin())
      <div class="ms-auto d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" id="openBankModalBtn"><i class="bi bi-bank me-1"></i>Add Bank</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="openHeaderModalBtn"><i class="bi bi-card-heading me-1"></i>Billing Header</button>
      </div>
      @endif
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
              <th>Bill #</th>
              <th>Type</th>
              <th>Customer</th>
              <th>Created by</th>
              <th>Billing Date</th>
              <th>Valid Till</th>
              <th>Amount</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="billsTbody"></tbody>
        </table>
      </div>
      <div class="crm-pagination">
        <span class="page-info" id="pageInfo"></span>
        <div class="d-flex gap-1" id="pageButtons"></div>
      </div>
    </div>
  </section>
</div>

<!-- ===================== ADD / EDIT BILL MODAL ===================== -->
<div class="modal fade" id="billModal" tabindex="-1">
  <div class="modal-dialog modal-xl bill-modal-dialog">
    <div class="modal-content h-100">
      <form id="billForm">
        <input type="hidden" id="billId">
        <div class="modal-header">
          <h5 class="modal-title" id="billModalTitle">Generate new bill</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">

          <!-- Bill type -->
          <div class="apt-type-group mb-3" id="billTypeGroup" style="max-width:480px">
            @foreach($billTypes as $key => $label)
              <div class="apt-type-option">
                <input type="radio" name="billType" id="billType_{{ $key }}" value="{{ $key }}" {{ $key === 'invoice' ? 'checked' : '' }}>
                <label for="billType_{{ $key }}">{{ $label }}</label>
              </div>
            @endforeach
          </div>

          <div class="row g-3 mb-3">
            <div class="col-4">
              <label class="form-label">Bill number</label>
              <input type="text" class="form-control" id="billNumberPreview">
              <div class="fs-11 text-muted-2 mt-1">Last: <span id="lastBillNumberText">—</span></div>
            </div>
            <div class="col-4">
              <label class="form-label">Billing date</label>
              <input type="date" class="form-control" id="billDate" required>
            </div>
            <div class="col-4">
              <label class="form-label">Valid till</label>
              <input type="date" class="form-control" id="billValidTill">
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-12">
              <label class="form-label">Billing header (company profile shown on the PDF)</label>
              <select class="form-select" id="billHeaderId">
                <option value="">— none selected —</option>
              </select>
            </div>
          </div>

          <hr>

          <!-- Customer -->
          <h6 class="fs-13 fw-700 text-uppercase text-muted-2 mb-2">Customer</h6>
     

        <div class="apt-type-group mb-2" style="max-width:480px">
            <div class="apt-type-option">
              <input type="radio" name="custSource" id="custSrcLead" value="lead" checked>
              <label for="custSrcLead"><i class="bi bi-person-check me-1"></i>From Lead</label>
            </div>
            <div class="apt-type-option">
              <input type="radio" name="custSource" id="custSrcBill" value="bill">
              <label for="custSrcBill"><i class="bi bi-receipt me-1"></i>From Bill</label>
            </div>
            <div class="apt-type-option">
              <input type="radio" name="custSource" id="custSrcNew" value="new">
              <label for="custSrcNew"><i class="bi bi-person-plus me-1"></i>New Customer</label>
            </div>
          </div>

          <div class="mb-3" id="leadPickWrap">
            <div class="ms-control" id="leadPickControl">
              <button type="button" class="ms-toggle" id="leadPickToggle">
                <span class="chips" id="leadPickLabel"><span class="placeholder">Search a lead by name or phone...</span></span>
                <i class="bi bi-chevron-down"></i>
              </button>
              <div class="ms-panel" id="leadPickPanel">
                <input type="text" class="form-control ms-search" id="leadPickSearch" placeholder="Search leads...">
                <div id="leadPickOptions"></div>
              </div>
            </div>
            <input type="hidden" id="leadPickerValue">
          </div>

          <div class="mb-3" id="billPickWrap">
            <div class="ms-control" id="billPickControl">
              <button type="button" class="ms-toggle" id="billPickToggle">
                <span class="chips" id="billPickLabel"><span class="placeholder">Search a bill by customer or bill number...</span></span>
                <i class="bi bi-chevron-down"></i>
              </button>
              <div class="ms-panel" id="billPickPanel">
                <input type="text" class="form-control ms-search" id="billPickSearch" placeholder="Search bills...">
                <div id="billPickOptions"></div>
              </div>
            </div>
            <input type="hidden" id="billPickerValue">
          </div>

          <div class="row g-3">
            <div class="col-6"><label class="form-label">Customer name</label><input type="text" class="form-control" id="custName" required></div>
            <div class="col-6"><label class="form-label">Company</label><input type="text" class="form-control" id="custCompany"></div>
            <div class="col-4"><label class="form-label">GST number</label><input type="text" class="form-control" id="custGst"></div>
            <div class="col-4"><label class="form-label">Phone</label><input type="text" class="form-control" id="custPhone" required></div>
            <div class="col-4"><label class="form-label">Email</label><input type="email" class="form-control" id="custEmail"></div>
          </div>

          <div class="row g-3 mt-1">
            <div class="col-6">
              <label class="form-label">Billing address</label>
              <textarea class="form-control" id="custBillingAddress" rows="2"></textarea>
            </div>
            <div class="col-6">
              <div class="d-flex justify-content-between align-items-center">
                <label class="form-label mb-0">Shipping address</label>
                <div class="form-check">
                  <input type="checkbox" class="form-check-input" id="shipSameCheck" checked>
                  <label class="form-check-label fs-12" for="shipSameCheck">Same as billing</label>
                </div>
              </div>
              <textarea class="form-control" id="custShippingAddress" rows="2" disabled></textarea>
            </div>
          </div>

          <hr>

          <!-- Product information -->
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fs-13 fw-700 text-uppercase text-muted-2 mb-0">Product information</h6>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="addItemRowBtn"><i class="bi bi-plus-lg me-1"></i>Add product</button>
          </div>
          <div id="itemRows"></div>

          <hr>

          <!-- Courier -->
          <h6 class="fs-13 fw-700 text-uppercase text-muted-2 mb-2">Courier information</h6>
          <div class="row g-3 mb-3">
            <div class="col-3"><label class="form-label">Courier name</label><input type="text" class="form-control" id="courierName"></div>
            <div class="col-3"><label class="form-label">Price</label><input type="number" class="form-control" id="courierPrice" min="0" step="0.01" value="0"></div>
            <div class="col-3">
              <label class="form-label">GST type</label>
              <select class="form-select" id="courierTaxType">
                <option value="">— none —</option>
                @foreach($taxTypes as $t)
                  <option value="{{ $t }}">{{ $t }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-3"><label class="form-label">GST %</label><input type="number" class="form-control" id="courierTaxPercent" min="0" max="100" step="0.01" value="0"></div>
          </div>

          <hr>

          <div class="row g-3">
            <div class="col-6">
              <label class="form-label">Bank details on invoice</label>
              <select class="form-select" id="billBankId">
                <option value="">— none selected —</option>
              </select>
            </div>
            <div class="col-6">
              <div class="totals-box">
                <div class="t-row"><span>Subtotal</span><span id="sumSubtotal">₹0.00</span></div>
                <div class="t-row"><span>Total tax</span><span id="sumTax">₹0.00</span></div>
                <div class="t-row grand"><span>Grand total</span><span id="sumGrand">₹0.00</span></div>
              </div>
            </div>
          </div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="billSubmitBtn">Generate bill</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===================== ADD BANK MODAL ===================== -->
<div class="modal fade" id="bankModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="bankForm">
        <input type="hidden" id="bankId">
        <div class="modal-header">
          <h5 class="modal-title" id="bankModalTitle">Add bank</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12"><label class="form-label">Bank name</label><input type="text" class="form-control" id="bankName" required></div>
            <div class="col-12"><label class="form-label">Account holder name</label><input type="text" class="form-control" id="bankHolder" required></div>
            <div class="col-6"><label class="form-label">Account number</label><input type="text" class="form-control" id="bankAccNumber" required></div>
            <div class="col-6"><label class="form-label">Re-enter account number</label><input type="text" class="form-control" id="bankAccNumberConfirm" required></div>
            <div class="col-6"><label class="form-label">IFSC code</label><input type="text" class="form-control" id="bankIfsc" required></div>
            <div class="col-6"><label class="form-label">Branch</label><input type="text" class="form-control" id="bankBranch"></div>
            <div class="col-12"><label class="form-label">QR code (optional)</label><input type="file" class="form-control" id="bankQr" accept="image/*"></div>
          </div>
          <div class="d-flex justify-content-end mt-2">
            <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="bankCancelEditBtn">Cancel edit</button>
          </div>
          <div class="manage-list" id="bankManageList"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary" id="bankSubmitBtn">Add bank</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===================== ADD BILLING HEADER MODAL ===================== -->
<div class="modal fade" id="headerModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="headerForm">
        <input type="hidden" id="headerId">
        <div class="modal-header">
          <h5 class="modal-title" id="headerModalTitle">Add billing header</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12"><label class="form-label">Company name</label><input type="text" class="form-control" id="headerCompanyName" required></div>
            <div class="col-6"><label class="form-label">GSTIN</label><input type="text" class="form-control" id="headerGstin"></div>
            <div class="col-6"><label class="form-label">Phone</label><input type="text" class="form-control" id="headerPhone"></div>
            <div class="col-12"><label class="form-label">Address</label><textarea class="form-control" id="headerAddress" rows="2"></textarea></div>
            <div class="col-12"><label class="form-label">Logo (optional)</label><input type="file" class="form-control" id="headerLogo" accept="image/*"></div>
            <div class="col-12">
              <div class="form-check">
                <input type="checkbox" class="form-check-input" id="headerIsDefault">
                <label class="form-check-label fs-13" for="headerIsDefault">Use as default header</label>
              </div>
            </div>
          </div>
          <div class="d-flex justify-content-end mt-2">
            <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="headerCancelEditBtn">Cancel edit</button>
          </div>
          <div class="manage-list" id="headerManageList"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary" id="headerSubmitBtn">Add header</button>
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
        <h5 class="modal-title">Delete bill(s)?</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0">This will permanently delete the selected bill(s). If they were invoices, any deducted stock will be restored. This can't be undone.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirmDeleteBtn">Delete</button>
      </div>
    </div>
  </div>
</div>

<!-- ===================== BILL DETAIL OFFCANVAS ===================== -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="billDetailOffcanvas" style="width:460px">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title">Bill details</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body">
    <div class="d-flex align-items-center gap-2 mb-3">
      <div id="bdType"></div>
      <div class="fw-700 ms-auto" id="bdNumber" style="font-size:16px"></div>
    </div>

    <div class="card-flat p-3 mb-3">
      <div class="d-flex justify-content-between mb-2"><span class="text-muted-2 fs-13">Customer</span><span class="fw-600 fs-13 text-end" id="bdCustomer">—</span></div>
      <div class="d-flex justify-content-between mb-2"><span class="text-muted-2 fs-13">Company</span><span class="fw-600 fs-13 text-end" id="bdCompany">—</span></div>
      <div class="d-flex justify-content-between mb-2"><span class="text-muted-2 fs-13">Created by</span><span class="fw-600 fs-13 text-end" id="bdCreator">—</span></div>
      <div class="d-flex justify-content-between mb-2"><span class="text-muted-2 fs-13">Phone</span><span class="fw-600 fs-13" id="bdPhone">—</span></div>
      <div class="d-flex justify-content-between mb-2"><span class="text-muted-2 fs-13">GSTIN</span><span class="fw-600 fs-13" id="bdGst">—</span></div>
      <div class="d-flex justify-content-between mb-2"><span class="text-muted-2 fs-13">Billing date</span><span class="fw-600 fs-13" id="bdDate">—</span></div>
      <div class="d-flex justify-content-between"><span class="text-muted-2 fs-13">Valid till</span><span class="fw-600 fs-13" id="bdValidTill">—</span></div>
    </div>

    <h6 class="fs-13 fw-700 text-uppercase text-muted-2 mb-2">Items</h6>
    <div class="section-card mb-3" id="bdItems"></div>

    <div class="totals-box mb-3">
      <div class="t-row"><span>Subtotal</span><span id="bdSubtotal">₹0.00</span></div>
      <div class="t-row"><span>Total tax</span><span id="bdTax">₹0.00</span></div>
      <div class="t-row grand"><span>Grand total</span><span id="bdGrand">₹0.00</span></div>
    </div>

    <div class="d-flex gap-2">
      <button type="button" class="btn btn-primary flex-fill" id="bdDownloadBtn"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</button>
      <button type="button" class="btn btn-outline-secondary flex-fill" id="bdEditBtn"><i class="bi bi-pencil me-1"></i>Edit bill</button>
    </div>
  </div>
</div>

<script>
  window.BILLING_PAGE_DATA = {
    leads: @json($leads),
    products: @json($products),
    banks: @json($banks),
    billingHeaders: @json($billingHeaders),
    taxTypes: @json($taxTypes),
    billTypes: @json($billTypes),
    lastNumbers: @json($lastNumbers),
    nextNumbers: @json($nextNumbers),
    csrfToken: '{{ csrf_token() }}',
    routes: {
      data: '{{ route('billing.data') }}',
      store: '{{ route('billing.store') }}',
      show: '{{ url('admin/billing') }}/__ID__',
      update: '{{ url('admin/billing') }}/__ID__',
      destroy: '{{ url('admin/billing') }}/__ID__',
      bulkDelete: '{{ route('billing.bulkDestroy') }}',
      pdf: '{{ url('admin/billing') }}/__ID__/pdf',
      bankStore: '{{ route('billing.banks.store') }}',
      bankUpdate: '{{ url('admin/billing/banks') }}/__ID__',
      bankDestroy: '{{ url('admin/billing/banks') }}/__ID__',
      headerStore: '{{ route('billing.headers.store') }}',
      headerUpdate: '{{ url('admin/billing/headers') }}/__ID__',
      headerDestroy: '{{ url('admin/billing/headers') }}/__ID__',
    }
  };

(function () {
  "use strict";

  const CFG = window.BILLING_PAGE_DATA;
  const CSRF = CFG.csrfToken;

  let bills = [];
  let banks = CFG.banks.slice();
  let headers = CFG.billingHeaders.slice();
  let nextNumbers = Object.assign({}, CFG.nextNumbers);
  let lastNumbers = Object.assign({}, CFG.lastNumbers);

  let activeFilters = { search: "", billType: "", dateFrom: "", dateTo: "" };
  let currentPage = 1;
  const PAGE_SIZE = 8;
  let selectedIds = new Set();

  let itemsState = [];
  let itemRowSeq = 0;
let editingBankId = null;
  let editingHeaderId = null;
  let selectedLeadId = null;
  let selectedSourceBillId = null;

  /* ---------------- Small helpers ---------------- */

  function escapeHtml(str) {
    return String(str ?? "").replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  }

  const AVATAR_COLORS = ["#4338CA", "#0891B2", "#B45309", "#16A34A", "#DC2626", "#6D28D9", "#0D9488"];
  function initials(name) {
    return (name || "?").trim().split(/\s+/).slice(0, 2).map(p => p[0]).join("").toUpperCase();
  }
  function colorFor(name) {
    let hash = 0;
    for (let i = 0; i < (name || "").length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash);
    return AVATAR_COLORS[Math.abs(hash) % AVATAR_COLORS.length];
  }

  function fmtMoney(n) {
    n = Number(n) || 0;
    return "₹" + n.toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function fmtDate(d) {
    if (!d) return "—";
    const dt = new Date(d);
    if (isNaN(dt)) return d;
    return dt.toLocaleDateString(undefined, { day: "numeric", month: "short", year: "numeric" });
  }

  function toast(message, variant) {
    let wrap = document.getElementById("blToastWrap");
    if (!wrap) {
      wrap = document.createElement("div");
      wrap.id = "blToastWrap";
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

  // Hardened fetch wrapper: session-expiry AND any non-2xx response now
  // throw instead of silently resolving, so callers actually find out
  // when something went wrong (403 from a policy check, 500 from a bug,
  // etc.) instead of just rendering an empty table.
  async function apiFetch(url, options) {
    const res = await fetch(url, Object.assign({ headers: csrfHeaders({ "Accept": "application/json" }) }, options));
    if (res.status === 401 || res.status === 419) {
      toast("Your session expired, please log in again.", "danger");
      throw new Error("unauthenticated");
    }
    if (!res.ok) {
      let body = null;
      try { body = await res.json(); } catch (e) { /* non-JSON error body */ }
      console.error("apiFetch error", res.status, url, body);
      throw new Error((body && (body.message || firstErrorMessage(body))) || `Request failed (${res.status})`);
    }
    return res.json();
  }

  async function apiFetchForm(url, formData) {
    const res = await fetch(url, { method: "POST", headers: csrfHeaders({ "Accept": "application/json" }), body: formData });
    if (res.status === 401 || res.status === 419) {
      toast("Your session expired, please log in again.", "danger");
      throw new Error("unauthenticated");
    }
    if (!res.ok) {
      let body = null;
      try { body = await res.json(); } catch (e) {}
      console.error("apiFetchForm error", res.status, url, body);
      throw new Error((body && (body.message || firstErrorMessage(body))) || `Request failed (${res.status})`);
    }
    return res.json();
  }

  function firstErrorMessage(res) {
    return res && res.errors ? Object.values(res.errors)[0][0] : null;
  }

  function firstError(res) {
    return res.errors ? Object.values(res.errors)[0][0] : "Something went wrong.";
  }

  function prefixFor(type) {
    return type === "invoice" ? "INV" : type === "quotation" ? "QUO" : type === "pi" ? "PI" : "BILL";
  }

  function bumpNumber(type, actualNumber) {
    lastNumbers[type] = actualNumber;
    const m = actualNumber.match(/(\d+)$/);
    const seq = m ? parseInt(m[1], 10) : 0;
    nextNumbers[type] = prefixFor(type) + "-" + String(seq + 1).padStart(4, "0");
  }

  /* ---------------- Data fetch ---------------- */

  async function loadBills() {
    try {
      const data = await apiFetch(CFG.routes.data);
      if (!data.success) {
        toast(data.message || "Could not load bills.", "danger");
        return;
      }
      bills = data.bills;
      renderAll();
    } catch (err) {
      // Previously this failed silently, leaving the table permanently
      // empty with no clue why. Now the person sees a toast and the real
      // error (auth/permission/server) lands in the console.
      toast(err.message || "Could not load bills.", "danger");
      console.error("loadBills failed:", err);
    }
  }

  /* ---------------- Filtering ---------------- */

  function getFilteredBills() {
    return bills.filter(b => {
      const term = activeFilters.search.toLowerCase();
      const matchesSearch = !term || (b.bill_number + b.customer_name + (b.phone || "")).toLowerCase().includes(term);
      const matchesType = !activeFilters.billType || b.bill_type === activeFilters.billType;
      const billDate = (b.billing_date || "").slice(0, 10);
      const matchesFrom = !activeFilters.dateFrom || (billDate && billDate >= activeFilters.dateFrom);
      const matchesTo = !activeFilters.dateTo || (billDate && billDate <= activeFilters.dateTo);
      return matchesSearch && matchesType && matchesFrom && matchesTo;
    });
  }

  /* ---------------- Rendering: stats + table ---------------- */

  function renderAll() {
    renderStats();
    renderTable();
  }

  function renderStats() {
    document.getElementById("statTotalBills").textContent = bills.length;
    document.getElementById("statInvoices").textContent = bills.filter(b => b.bill_type === "invoice").length;
    document.getElementById("statQuotes").textContent = bills.filter(b => b.bill_type === "quotation" || b.bill_type === "pi").length;
    const revenue = bills.filter(b => b.bill_type === "invoice").reduce((sum, b) => sum + Number(b.grand_total || 0), 0);
    document.getElementById("statRevenue").textContent = fmtMoney(revenue);
  }

  function typeBadge(type) {
    const label = CFG.billTypes[type] || type;
    return `<span class="badge-status bt-${type}">${label}</span>`;
  }

  function creatorName(b) {
    return b.creator && b.creator.name ? b.creator.name : "—";
  }

  function renderTable() {
    const filtered = getFilteredBills();
    const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
    currentPage = Math.min(currentPage, totalPages);
    const start = (currentPage - 1) * PAGE_SIZE;
    const pageItems = filtered.slice(start, start + PAGE_SIZE);

    const tbody = document.getElementById("billsTbody");

    if (pageItems.length === 0) {
      tbody.innerHTML = `<tr><td colspan="9"><div class="empty-state">
        <i class="bi bi-receipt-cutoff"></i><h6>No bills found</h6>
        <p class="fs-13 mb-0">Try adjusting filters or generate a new bill.</p></div></td></tr>`;
    } else {
      tbody.innerHTML = pageItems.map(b => `
        <tr data-id="${b.id}">
          <td onclick="event.stopPropagation()"><input class="form-check-input row-check" type="checkbox" data-id="${b.id}" ${selectedIds.has(b.id) ? "checked" : ""}></td>
          <td class="fw-700">${escapeHtml(b.bill_number)}</td>
          <td>${typeBadge(b.bill_type)}</td>
          <td>
            <div class="lead-name">${escapeHtml(b.customer_name)}</div>
            <div class="lead-company text-muted-2 fs-12">${escapeHtml(b.company_name || "")}</div>
          </td>
          <td class="text-muted-2">${escapeHtml(creatorName(b))}</td>
          <td class="text-muted-2">${fmtDate(b.billing_date)}</td>
          <td class="text-muted-2">${fmtDate(b.valid_till)}</td>
          <td class="fw-700">${fmtMoney(b.grand_total)}</td>
          <td onclick="event.stopPropagation()">
            <div class="dropdown row-actions">
              <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item view-bill-action" href="#" data-id="${b.id}"><i class="bi bi-eye me-2"></i>View details</a></li>
                <li><a class="dropdown-item pdf-bill-action" href="#" data-id="${b.id}"><i class="bi bi-file-earmark-pdf me-2"></i>Download PDF</a></li>
                <li><a class="dropdown-item edit-bill-action" href="#" data-id="${b.id}"><i class="bi bi-pencil me-2"></i>Edit bill</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger delete-bill-action" href="#" data-id="${b.id}"><i class="bi bi-trash me-2"></i>Delete</a></li>
              </ul>
            </div>
          </td>
        </tr>`).join("");
    }

    renderActivePills();
    renderPagination(filtered.length, totalPages);
    bindRowEvents();
    updateBulkBar();
  }

  function renderActivePills() {
    const wrap = document.getElementById("activeFilterPills");
    wrap.innerHTML = "";
    if (activeFilters.billType) {
      wrap.insertAdjacentHTML("beforeend", `<span class="filter-pill">Type: ${CFG.billTypes[activeFilters.billType]} <button type="button" data-clear="billType"><i class="bi bi-x"></i></button></span>`);
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
          activeFilters.dateFrom = ""; activeFilters.dateTo = "";
          document.getElementById("filterDateFrom").value = "";
          document.getElementById("filterDateTo").value = "";
        } else {
          activeFilters.billType = "";
          document.getElementById("filterBillType").value = "";
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
    document.querySelectorAll("#billsTbody tr[data-id]").forEach(row => {
      row.addEventListener("click", () => openBillDetail(parseInt(row.dataset.id, 10)));
    });
    document.querySelectorAll(".row-check").forEach(cb => {
      cb.addEventListener("change", () => {
        const id = parseInt(cb.dataset.id, 10);
        if (cb.checked) selectedIds.add(id); else selectedIds.delete(id);
        updateBulkBar();
      });
    });
    document.querySelectorAll(".view-bill-action").forEach(a => a.addEventListener("click", e => { e.preventDefault(); openBillDetail(parseInt(a.dataset.id, 10)); }));
    document.querySelectorAll(".pdf-bill-action").forEach(a => a.addEventListener("click", e => {
      e.preventDefault();
      window.open(CFG.routes.pdf.replace("__ID__", a.dataset.id), "_blank");
    }));
    document.querySelectorAll(".edit-bill-action").forEach(a => a.addEventListener("click", e => { e.preventDefault(); openBillModal(parseInt(a.dataset.id, 10)); }));
    document.querySelectorAll(".delete-bill-action").forEach(a => {
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

  /* ---------------- Filters / bulk wiring ---------------- */

  function initFilters() {
    document.getElementById("filterSearch").addEventListener("input", e => { activeFilters.search = e.target.value; currentPage = 1; renderTable(); });
    document.getElementById("filterBillType").addEventListener("change", e => { activeFilters.billType = e.target.value; currentPage = 1; renderTable(); });
    document.getElementById("filterDateFrom").addEventListener("change", e => { activeFilters.dateFrom = e.target.value; currentPage = 1; renderTable(); });
    document.getElementById("filterDateTo").addEventListener("change", e => { activeFilters.dateTo = e.target.value; currentPage = 1; renderTable(); });
    document.getElementById("clearFiltersBtn").addEventListener("click", () => {
      activeFilters = { search: "", billType: "", dateFrom: "", dateTo: "" };
      ["filterSearch", "filterBillType", "filterDateFrom", "filterDateTo"].forEach(id => document.getElementById(id).value = "");
      currentPage = 1;
      renderTable();
    });
  }

  /* ---------------- Bill detail offcanvas ---------------- */

  async function openBillDetail(id) {
    let res;
    try {
      res = await apiFetch(CFG.routes.show.replace("__ID__", id));
    } catch (err) {
      toast(err.message || "Could not load bill.", "danger");
      return;
    }
    if (!res.success) return;
    const bill = res.bill;

    document.getElementById("bdType").innerHTML = typeBadge(bill.bill_type);
    document.getElementById("bdNumber").textContent = bill.bill_number;
    document.getElementById("bdCustomer").textContent = bill.customer_name;
    document.getElementById("bdCompany").textContent = bill.company_name || "—";
    document.getElementById("bdCreator").textContent = creatorName(bill);
    document.getElementById("bdPhone").textContent = bill.phone || "—";
    document.getElementById("bdGst").textContent = bill.gst_number || "—";
    document.getElementById("bdDate").textContent = fmtDate(bill.billing_date);
    document.getElementById("bdValidTill").textContent = bill.valid_till ? fmtDate(bill.valid_till) : "—";

    const itemRowsHtml = (bill.items || []).map(it => {
      const taxLabel = (it.taxes || []).map(t => `${t.type} ${t.percent}%`).join(" + ");
      const meta = `${it.quantity} ${it.unit || ""} × ${fmtMoney(it.price)}${taxLabel ? " · " + taxLabel : ""}`;
      return `<div class="bd-item-row">
        <div>
          <div class="bd-item-name">${escapeHtml(it.product_name)}</div>
          <div class="bd-item-meta">${escapeHtml(meta)}</div>
        </div>
        <div class="bd-item-total">${fmtMoney(it.total)}</div>
      </div>`;
    });

    if (bill.courier_name || Number(bill.courier_price) > 0) {
      const courierTaxLabel = bill.courier_tax_type && bill.courier_tax_percent ? `${bill.courier_tax_type} ${bill.courier_tax_percent}%` : "";
      const courierTotal = Number(bill.courier_price || 0) + Number(bill.courier_tax_amount || 0);
      itemRowsHtml.push(`<div class="bd-item-row">
        <div>
          <div class="bd-item-name">${escapeHtml(bill.courier_name || "Courier")}</div>
          <div class="bd-item-meta">${escapeHtml("Courier" + (courierTaxLabel ? " · " + courierTaxLabel : ""))}</div>
        </div>
        <div class="bd-item-total">${fmtMoney(courierTotal)}</div>
      </div>`);
    }

    document.getElementById("bdItems").innerHTML = itemRowsHtml.join("") ||
      `<div class="empty-state py-3"><i class="bi bi-box-seam"></i><h6 class="fs-13">No items</h6></div>`;

    document.getElementById("bdSubtotal").textContent = fmtMoney(bill.subtotal);
    document.getElementById("bdTax").textContent = fmtMoney(bill.total_tax);
    document.getElementById("bdGrand").textContent = fmtMoney(bill.grand_total);

    document.getElementById("bdDownloadBtn").onclick = () => {
      window.open(CFG.routes.pdf.replace("__ID__", bill.id), "_blank");
    };
    document.getElementById("bdEditBtn").onclick = () => {
      bootstrap.Offcanvas.getOrCreateInstance(document.getElementById("billDetailOffcanvas")).hide();
      openBillModal(bill.id);
    };

    bootstrap.Offcanvas.getOrCreateInstance(document.getElementById("billDetailOffcanvas")).show();
  }

  function initBulkActions() {
    document.getElementById("bulkClearBtn").addEventListener("click", () => { selectedIds.clear(); renderTable(); });
    document.getElementById("bulkDeleteBtn").addEventListener("click", () => {
      bootstrap.Modal.getOrCreateInstance(document.getElementById("deleteConfirmModal")).show();
    });
    document.getElementById("confirmDeleteBtn").addEventListener("click", async () => {
      const ids = Array.from(selectedIds);
      if (!ids.length) return;
      try {
        const res = ids.length === 1
          ? await apiFetch(CFG.routes.destroy.replace("__ID__", ids[0]), { method: "DELETE" })
          : await apiFetch(CFG.routes.bulkDelete, { method: "POST", headers: csrfHeaders({ "Content-Type": "application/json" }), body: JSON.stringify({ ids }) });
        if (res.success) {
          bills = bills.filter(b => !ids.includes(b.id));
          selectedIds.clear();
          renderAll();
          toast(res.message || "Deleted", "dark");
        }
      } catch (err) {
        toast(err.message || "Could not delete.", "danger");
      }
      bootstrap.Modal.getOrCreateInstance(document.getElementById("deleteConfirmModal")).hide();
    });
  }

  /* ---------------- Lead / product labels ---------------- */

  function leadLabel(l) { return `${l.customer_name} — ${l.phone || l.whatsapp || ""}`; }

  function populateSelectOptions(selectEl, list, valueKey, labelFn, placeholder) {
    selectEl.innerHTML = `<option value="">${placeholder}</option>` + list.map(item => `<option value="${item[valueKey]}">${escapeHtml(labelFn(item))}</option>`).join("");
  }

  /* ---------------- Lead picker (searchable single-select dropdown) ---------------- */

  function renderLeadPickOptions(term) {
    const box = document.getElementById("leadPickOptions");
    term = (term || "").toLowerCase();
    const list = CFG.leads.filter(l => leadLabel(l).toLowerCase().includes(term));
    box.innerHTML = list.length ? list.map(l => `
      <div class="ms-option" data-lead-id="${l.id}">
        <div class="avatar avatar-sm" style="background:${colorFor(l.customer_name)}">${initials(l.customer_name)}</div>
        <div>
          <div class="fw-600" style="font-size:13px">${escapeHtml(l.customer_name)}</div>
          <div class="fs-11 text-muted-2">${escapeHtml(l.phone || l.whatsapp || "")}</div>
        </div>
      </div>`).join("") : `<div class="fs-13 text-muted-2 p-2">No leads found</div>`;

    box.querySelectorAll("[data-lead-id]").forEach(opt => {
      opt.addEventListener("click", () => selectLead(parseInt(opt.dataset.leadId, 10)));
    });
  }

  function selectLead(id) {
    const lead = CFG.leads.find(l => l.id === id);
    if (!lead) return;
    selectedLeadId = id;
    document.getElementById("leadPickerValue").value = id;
    document.getElementById("leadPickLabel").innerHTML = `<span class="chip">${escapeHtml(leadLabel(lead))}</span>`;
    document.getElementById("leadPickPanel").classList.remove("show");
    applyLeadToForm(lead);
  }

  function clearLeadPicker() {
    selectedLeadId = null;
    document.getElementById("leadPickerValue").value = "";
    document.getElementById("leadPickLabel").innerHTML = `<span class="placeholder">Search a lead by name or phone...</span>`;
  }

  function applyLeadToForm(lead) {
    document.getElementById("custName").value = lead.customer_name || "";
    document.getElementById("custCompany").value = lead.company_name || "";
    document.getElementById("custGst").value = lead.gst_number || "";
    document.getElementById("custPhone").value = lead.phone || lead.whatsapp || "";
    document.getElementById("custEmail").value = lead.email || "";
    document.getElementById("custBillingAddress").value = lead.billing_address || "";
    if (document.getElementById("shipSameCheck").checked) {
      document.getElementById("custShippingAddress").value = lead.billing_address || "";
    } else {
      document.getElementById("custShippingAddress").value = lead.shipping_address || "";
    }
  }

function initLeadPicker() {
    const toggle = document.getElementById("leadPickToggle");
    const panel = document.getElementById("leadPickPanel");
    toggle.addEventListener("click", () => {
      panel.classList.toggle("show");
      if (panel.classList.contains("show")) {
        document.getElementById("leadPickSearch").value = "";
        renderLeadPickOptions("");
        document.getElementById("leadPickSearch").focus();
      }
    });
    document.getElementById("leadPickSearch").addEventListener("input", e => renderLeadPickOptions(e.target.value));
    document.addEventListener("click", e => {
      if (!document.getElementById("leadPickControl").contains(e.target)) panel.classList.remove("show");
    });
  }

  /* ---------------- Bill picker (searchable, "From Bill" source) ----------------
     Same ms-control/ms-toggle/ms-panel pattern as the lead picker above,
     just sourced from the already-loaded `bills` array (loadBills()) —
     which billing.data already scopes to "your own bills" for a regular
     admin, so this list is naturally "bills I've made" with no extra
     backend call. */

  function billSourceLabel(b) {
    return `${b.customer_name} — ${b.bill_number} (${fmtDate(b.billing_date)})`;
  }

  function renderBillPickOptions(term) {
    const box = document.getElementById("billPickOptions");
    term = (term || "").toLowerCase();
    const list = bills.filter(b => (b.customer_name + " " + b.bill_number + " " + (b.phone || "")).toLowerCase().includes(term));
    box.innerHTML = list.length ? list.map(b => `
      <div class="ms-option" data-bill-id="${b.id}">
        <div class="avatar avatar-sm" style="background:${colorFor(b.customer_name)}">${initials(b.customer_name)}</div>
        <div>
          <div class="fw-600" style="font-size:13px">${escapeHtml(b.customer_name)}</div>
          <div class="fs-11 text-muted-2">${escapeHtml(b.bill_number)} · ${fmtDate(b.billing_date)}</div>
        </div>
      </div>`).join("") : `<div class="fs-13 text-muted-2 p-2">No bills found</div>`;

    box.querySelectorAll("[data-bill-id]").forEach(opt => {
      opt.addEventListener("click", () => selectBillSource(parseInt(opt.dataset.billId, 10)));
    });
  }

  function selectBillSource(id) {
    const bill = bills.find(b => b.id === id);
    if (!bill) return;
    selectedSourceBillId = id;
    document.getElementById("billPickerValue").value = id;
    document.getElementById("billPickLabel").innerHTML = `<span class="chip">${escapeHtml(billSourceLabel(bill))}</span>`;
    document.getElementById("billPickPanel").classList.remove("show");
    applyBillToForm(bill);
  }

  function clearBillPicker() {
    selectedSourceBillId = null;
    document.getElementById("billPickerValue").value = "";
    document.getElementById("billPickLabel").innerHTML = `<span class="placeholder">Search a bill by customer or bill number...</span>`;
  }
  function clearCustomerFields() {
  document.getElementById("custName").value = "";
  document.getElementById("custCompany").value = "";
  document.getElementById("custGst").value = "";
  document.getElementById("custPhone").value = "";
  document.getElementById("custEmail").value = "";
  document.getElementById("custBillingAddress").value = "";
  document.getElementById("shipSameCheck").checked = true;
  const shipField = document.getElementById("custShippingAddress");
  shipField.value = "";
  shipField.disabled = true;
}

  function applyBillToForm(bill) {
    document.getElementById("custName").value = bill.customer_name || "";
    document.getElementById("custCompany").value = bill.company_name || "";
    document.getElementById("custGst").value = bill.gst_number || "";
    document.getElementById("custPhone").value = bill.phone || "";
    document.getElementById("custEmail").value = bill.email || "";
    document.getElementById("custBillingAddress").value = bill.billing_address || "";
    if (document.getElementById("shipSameCheck").checked) {
      document.getElementById("custShippingAddress").value = bill.billing_address || "";
    } else {
      document.getElementById("custShippingAddress").value = bill.shipping_address || "";
    }

    // If that old bill was tied to a lead you can still see, keep the new
    // bill linked to the same lead (same behaviour as picking it via
    // "From Lead") — otherwise it's treated like a plain new customer.
    const matchedLead = bill.lead_id ? CFG.leads.find(l => l.id === bill.lead_id) : null;
    selectedLeadId = matchedLead ? matchedLead.id : null;
  }

  function initBillPicker() {
    const toggle = document.getElementById("billPickToggle");
    const panel = document.getElementById("billPickPanel");
    toggle.addEventListener("click", () => {
      panel.classList.toggle("show");
      if (panel.classList.contains("show")) {
        document.getElementById("billPickSearch").value = "";
        renderBillPickOptions("");
        document.getElementById("billPickSearch").focus();
      }
    });
    document.getElementById("billPickSearch").addEventListener("input", e => renderBillPickOptions(e.target.value));
    document.addEventListener("click", e => {
      if (!document.getElementById("billPickControl").contains(e.target)) panel.classList.remove("show");
    });
  }

  /* ---------------- Bill modal: customer source ---------------- */

  /* ---------------- Bill modal: customer source ---------------- */

/* ---------------- Bill modal: customer source ---------------- */

function initCustomerSource() {
    document.querySelectorAll('input[name="custSource"]').forEach(r => {
      r.addEventListener("change", () => {
        const isLead = document.getElementById("custSrcLead").checked;
        const isBill = document.getElementById("custSrcBill").checked;
        document.getElementById("leadPickWrap").style.display = isLead ? "block" : "none";
        document.getElementById("billPickWrap").style.display = isBill ? "block" : "none";
        if (!isLead) clearLeadPicker();
        if (!isBill) clearBillPicker();
        if (!isLead && !isBill) {
          selectedLeadId = null; // "New Customer" — no lead link
          clearCustomerFields();
        }
      });
    });

    document.getElementById("shipSameCheck").addEventListener("change", e => {
      const shipField = document.getElementById("custShippingAddress");
      shipField.disabled = e.target.checked;
      if (e.target.checked) shipField.value = document.getElementById("custBillingAddress").value;
    });
    document.getElementById("custBillingAddress").addEventListener("input", e => {
      if (document.getElementById("shipSameCheck").checked) {
        document.getElementById("custShippingAddress").value = e.target.value;
      }
    });
  }

  /* ---------------- Bill type -> bill number preview ---------------- */

  function initBillTypeGroup() {
    document.querySelectorAll('input[name="billType"]').forEach(r => {
      r.addEventListener("change", () => {
        document.getElementById("billNumberPreview").value = nextNumbers[r.value] || "";
        document.getElementById("lastBillNumberText").textContent = lastNumbers[r.value] || "—";
        recalcTotals(); // over-stock highlighting only applies to invoice
      });
    });
  }

  function currentBillType() {
    return document.querySelector('input[name="billType"]:checked').value;
  }

  /* ---------------- Line items ---------------- */

  function newItemRow() {
    return { rowId: ++itemRowSeq, product_id: null, product_name: "", hsn_sku: "", unit: "", stock: null, price: 0, quantity: 1, taxes: [] };
  }

  // Options list markup for the product-search dropdown, filtered by name /
  // SKU / HSN. Each option shows stock and last price up front so picking a
  // product and seeing what it'll cost/whether it's in stock is one step.
  function productOptionsHtml(term) {
    term = (term || "").trim().toLowerCase();
    const list = CFG.products.filter(p => {
      if (!term) return true;
      return (p.name || "").toLowerCase().includes(term)
        || (p.sku || "").toLowerCase().includes(term)
        || (p.hsn || "").toLowerCase().includes(term);
    }).slice(0, 50);

    if (!list.length) return `<div class="fs-13 text-muted-2 p-2">No products found</div>`;

    return list.map(p => {
      const outOfStock = Number(p.stock) <= 0;
      return `
      <div class="ms-option" data-product-id="${p.id}">
        <div>
          <div class="fw-600" style="font-size:13px">${escapeHtml(p.name)}</div>
          <div class="msop-meta ${outOfStock ? "msop-out" : ""}">${escapeHtml(p.sku || p.hsn || "")}${p.sku || p.hsn ? " · " : ""}Stock: ${p.stock ?? 0} ${escapeHtml(p.unit || "")} · ${fmtMoney(p.last_price || 0)}</div>
        </div>
      </div>`;
    }).join("");
  }

  function renderItemRows() {
    const box = document.getElementById("itemRows");
    if (!itemsState.length) {
      box.innerHTML = `<div class="empty-state py-3"><i class="bi bi-box-seam"></i><h6 class="fs-13">No products added yet</h6></div>`;
      return;
    }
    box.innerHTML = itemsState.map(row => {
      const taxChips = row.taxes.map((t, i) => `<span class="tax-chip">${t.type} ${t.percent}% <button type="button" class="tax-chip-remove" data-row="${row.rowId}" data-idx="${i}">&times;</button></span>`).join("");
      const overStock = currentBillType() === "invoice" && row.stock !== null && row.quantity > row.stock;
      return `
      <div class="item-row" data-row-id="${row.rowId}">
        <div class="row g-2 align-items-end">
          <div class="col-3">
            <label class="fs-11 text-muted-2">Product</label>
            <div class="ms-control item-product-control">
              <button type="button" class="ms-toggle item-product-toggle">
                <span class="chips">${row.product_name ? `<span class="chip">${escapeHtml(row.product_name)}</span>` : `<span class="placeholder">Search product...</span>`}</span>
                <i class="bi bi-chevron-down"></i>
              </button>
              <div class="ms-panel item-product-panel">
                <input type="text" class="form-control ms-search item-product-search" placeholder="Search by name, SKU or HSN...">
                <div class="item-product-options">${productOptionsHtml("")}</div>
              </div>
            </div>
            <div class="item-meta ${overStock ? "over-stock" : ""}">HSN/SKU: ${escapeHtml(row.hsn_sku || "—")} · Stock: ${row.stock === null ? "—" : row.stock + " " + (row.unit || "")}</div>
          </div>
          <div class="col-2"><label class="fs-11 text-muted-2">Price</label><input type="number" class="form-control item-price" min="0" step="0.01" value="${row.price}"></div>
          <div class="col-1"><label class="fs-11 text-muted-2">Qty</label><input type="number" class="form-control item-qty" min="0.01" step="0.01" value="${row.quantity}"></div>
          <div class="col-1"><label class="fs-11 text-muted-2">Unit</label><input type="text" class="form-control item-unit" value="${escapeHtml(row.unit || "")}" readonly></div>
          <div class="col-3">
            <label class="fs-11 text-muted-2">Taxes</label>
            <div class="d-flex gap-1">
              <select class="form-select form-select-sm item-tax-type">${CFG.taxTypes.map(t => `<option value="${t}">${t}</option>`).join("")}</select>
              <input type="number" class="form-control form-control-sm item-tax-percent" placeholder="%" min="0" max="100" step="0.01" style="width:70px">
              <button type="button" class="btn btn-sm btn-outline-secondary item-tax-add"><i class="bi bi-plus"></i></button>
            </div>
            <div class="d-flex flex-wrap gap-1 mt-1">${taxChips}</div>
          </div>
          <div class="col-2 text-end">
            <div class="fw-700 item-line-total">₹0.00</div>
            <button type="button" class="btn btn-sm text-danger item-remove" title="Remove"><i class="bi bi-trash"></i></button>
          </div>
        </div>
      </div>`;
    }).join("");
    recalcTotals();
  }

  function rowById(id) { return itemsState.find(r => r.rowId === id); }

  function closeAllProductPanels() {
    document.querySelectorAll("#itemRows .item-product-panel.show").forEach(p => p.classList.remove("show"));
  }

  function initItemRowEvents() {
    document.getElementById("addItemRowBtn").addEventListener("click", () => {
      itemsState.push(newItemRow());
      renderItemRows();
    });

    document.getElementById("itemRows").addEventListener("change", e => {
      const rowEl = e.target.closest(".item-row");
      if (!rowEl) return;
      const row = rowById(parseInt(rowEl.dataset.rowId, 10));
      if (!row) return;

      if (e.target.classList.contains("item-price")) { row.price = parseFloat(e.target.value) || 0; recalcTotals(); }
      if (e.target.classList.contains("item-qty")) { row.quantity = parseFloat(e.target.value) || 0; renderItemRows(); }
    });

    document.getElementById("itemRows").addEventListener("input", e => {
      // Filter the product dropdown as the user types.
      if (e.target.classList.contains("item-product-search")) {
        const panel = e.target.closest(".item-product-panel");
        panel.querySelector(".item-product-options").innerHTML = productOptionsHtml(e.target.value);
        return;
      }

      const rowEl = e.target.closest(".item-row");
      if (!rowEl) return;
      const row = rowById(parseInt(rowEl.dataset.rowId, 10));
      if (!row) return;
      if (e.target.classList.contains("item-price")) { row.price = parseFloat(e.target.value) || 0; recalcTotals(); }
      if (e.target.classList.contains("item-qty")) { row.quantity = parseFloat(e.target.value) || 0; recalcTotals(); }
    });

    document.getElementById("itemRows").addEventListener("click", e => {
      const rowEl = e.target.closest(".item-row");
      if (!rowEl) return;
      const row = rowById(parseInt(rowEl.dataset.rowId, 10));

      // Open/close the product-search dropdown for this row.
      if (e.target.closest(".item-product-toggle")) {
        const panel = rowEl.querySelector(".item-product-panel");
        const wasOpen = panel.classList.contains("show");
        closeAllProductPanels();
        if (!wasOpen) {
          panel.classList.add("show");
          const searchInput = panel.querySelector(".item-product-search");
          searchInput.value = "";
          panel.querySelector(".item-product-options").innerHTML = productOptionsHtml("");
          searchInput.focus();
        }
        return;
      }

      // Pick a product from the dropdown — auto-fills price (still editable
      // in the field next to it) and stock/unit, then closes the panel.
      const optionEl = e.target.closest(".ms-option[data-product-id]");
      if (optionEl && row) {
        const product = CFG.products.find(p => p.id === parseInt(optionEl.dataset.productId, 10));
        if (product) {
          row.product_id = product.id;
          row.product_name = product.name;
          row.hsn_sku = product.hsn || product.sku || "";
          row.unit = product.unit || "";
          row.stock = product.stock;
          row.price = product.last_price || 0;
          renderItemRows();
        }
        return;
      }

      if (e.target.closest(".item-remove")) {
        const rowId = parseInt(rowEl.dataset.rowId, 10);
        itemsState = itemsState.filter(r => r.rowId !== rowId);
        renderItemRows();
        return;
      }
      if (e.target.closest(".item-tax-add")) {
        const typeSel = rowEl.querySelector(".item-tax-type");
        const pctInput = rowEl.querySelector(".item-tax-percent");
        const percent = parseFloat(pctInput.value);
        if (!percent || percent <= 0) { toast("Enter a valid tax %", "danger"); return; }
        row.taxes.push({ type: typeSel.value, percent });
        renderItemRows();
        return;
      }
      if (e.target.closest(".tax-chip-remove")) {
        const btn = e.target.closest(".tax-chip-remove");
        const btnRow = rowById(parseInt(btn.dataset.row, 10));
        btnRow.taxes.splice(parseInt(btn.dataset.idx, 10), 1);
        renderItemRows();
      }
    });

    // Close any open product dropdown when clicking outside of it.
    document.addEventListener("click", e => {
      if (!e.target.closest(".item-product-control")) closeAllProductPanels();
    });
  }

  function recalcTotals() {
    let subtotal = 0, totalTax = 0;
    document.querySelectorAll("#itemRows .item-row").forEach(rowEl => {
      const row = rowById(parseInt(rowEl.dataset.rowId, 10));
      if (!row) return;
      const taxable = round2(row.price * row.quantity);
      const taxAmt = round2(row.taxes.reduce((s, t) => s + taxable * (t.percent / 100), 0));
      const total = taxable + taxAmt;
      subtotal += taxable;
      totalTax += taxAmt;
      const lineTotalEl = rowEl.querySelector(".item-line-total");
      if (lineTotalEl) lineTotalEl.textContent = fmtMoney(total);

      const overStock = currentBillType() === "invoice" && row.stock !== null && row.quantity > row.stock;
      const metaEl = rowEl.querySelector(".item-meta");
      if (metaEl) metaEl.classList.toggle("over-stock", overStock);
    });

    const courierPrice = parseFloat(document.getElementById("courierPrice").value) || 0;
    const courierPct = parseFloat(document.getElementById("courierTaxPercent").value) || 0;
    const courierTax = round2(courierPrice * (courierPct / 100));
    subtotal += courierPrice;
    totalTax += courierTax;

    document.getElementById("sumSubtotal").textContent = fmtMoney(subtotal);
    document.getElementById("sumTax").textContent = fmtMoney(totalTax);
    document.getElementById("sumGrand").textContent = fmtMoney(subtotal + totalTax);
  }

  function round2(n) { return Math.round((n + Number.EPSILON) * 100) / 100; }

  function initCourierEvents() {
    ["courierPrice", "courierTaxPercent"].forEach(id => {
      document.getElementById(id).addEventListener("input", recalcTotals);
    });
    document.getElementById("courierTaxType").addEventListener("change", recalcTotals);
  }

  /* ---------------- Add / Edit bill modal ---------------- */

  function resetBillForm() {
    document.getElementById("billForm").reset();
    document.getElementById("billId").value = "";
    document.querySelector('input[name="billType"][value="invoice"]').checked = true;
    document.getElementById("billNumberPreview").value = nextNumbers.invoice || "";
    document.getElementById("lastBillNumberText").textContent = lastNumbers.invoice || "—";
    document.getElementById("billDate").value = new Date().toISOString().slice(0, 10);
    document.getElementById("custSrcLead").checked = true;
    document.getElementById("leadPickWrap").style.display = "block";
    document.getElementById("billPickWrap").style.display = "none";
    clearLeadPicker();
    clearBillPicker();
    document.getElementById("shipSameCheck").checked = true;
    document.getElementById("custShippingAddress").disabled = true;
    itemsState = [newItemRow()];
    populateSelectOptions(document.getElementById("billHeaderId"), headers, "id", h => h.company_name + (h.is_default ? " (default)" : ""), "— none selected —");
    populateSelectOptions(document.getElementById("billBankId"), banks, "id", b => `${b.bank_name} — ${b.account_number}`, "— none selected —");
    const defaultHeader = headers.find(h => h.is_default);
    if (defaultHeader) document.getElementById("billHeaderId").value = defaultHeader.id;
    renderItemRows();
  }

  async function openBillModal(id) {
    resetBillForm();

    if (id) {
      let res;
      try {
        res = await apiFetch(CFG.routes.show.replace("__ID__", id));
      } catch (err) {
        toast(err.message || "Could not load bill.", "danger");
        return;
      }
      if (!res.success) return;
      const bill = res.bill;

      document.getElementById("billModalTitle").textContent = "Edit bill";
      document.getElementById("billSubmitBtn").textContent = "Save changes";
      document.getElementById("billId").value = bill.id;
      document.querySelector(`input[name="billType"][value="${bill.bill_type}"]`).checked = true;
      document.getElementById("billNumberPreview").value = bill.bill_number;
      document.getElementById("lastBillNumberText").textContent = lastNumbers[bill.bill_type] || "—";
      document.getElementById("billDate").value = (bill.billing_date || "").slice(0, 10);
      document.getElementById("billValidTill").value = (bill.valid_till || "").slice(0, 10);
      document.getElementById("billHeaderId").value = bill.billing_header_id || "";
      document.getElementById("billBankId").value = bill.bank_id || "";

      if (bill.lead_id) {
        document.getElementById("custSrcLead").checked = true;
        document.getElementById("leadPickWrap").style.display = "block";
        const lead = CFG.leads.find(l => l.id === bill.lead_id);
        if (lead) selectLead(lead.id); else clearLeadPicker();
      } else {
        document.getElementById("custSrcNew").checked = true;
        document.getElementById("leadPickWrap").style.display = "none";
        clearLeadPicker();
      }
      document.getElementById("custName").value = bill.customer_name || "";
      document.getElementById("custCompany").value = bill.company_name || "";
      document.getElementById("custGst").value = bill.gst_number || "";
      document.getElementById("custPhone").value = bill.phone || "";
      document.getElementById("custEmail").value = bill.email || "";
      document.getElementById("custBillingAddress").value = bill.billing_address || "";
      document.getElementById("shipSameCheck").checked = !!bill.ship_same_as_billing;
      document.getElementById("custShippingAddress").disabled = !!bill.ship_same_as_billing;
      document.getElementById("custShippingAddress").value = bill.shipping_address || "";

      document.getElementById("courierName").value = bill.courier_name || "";
      document.getElementById("courierPrice").value = bill.courier_price || 0;
      document.getElementById("courierTaxType").value = bill.courier_tax_type || "";
      document.getElementById("courierTaxPercent").value = bill.courier_tax_percent || 0;

      itemsState = (bill.items || []).map(it => ({
        rowId: ++itemRowSeq,
        product_id: it.product_id,
        product_name: it.product_name,
        hsn_sku: it.hsn_sku,
        unit: it.unit,
        stock: it.product ? it.product.total_quantity ?? null : null,
        price: it.price,
        quantity: it.quantity,
        taxes: (it.taxes || []).map(t => ({ type: t.type, percent: t.percent })),
      }));
      if (!itemsState.length) itemsState = [newItemRow()];
      renderItemRows();
    } else {
      document.getElementById("billModalTitle").textContent = "Generate new bill";
      document.getElementById("billSubmitBtn").textContent = "Generate bill";
    }

    bootstrap.Modal.getOrCreateInstance(document.getElementById("billModal")).show();
  }

  function initBillForm() {
    document.getElementById("openAddBillBtn").addEventListener("click", () => openBillModal(null));

    document.getElementById("billForm").addEventListener("submit", async e => {
      e.preventDefault();

      const validItems = itemsState.filter(r => r.product_name && r.price >= 0 && r.quantity > 0);
      if (!validItems.length) { toast("Add at least one product line.", "danger"); return; }
      // NEW: bill number ab manual field hai — empty nahi ja sakta
const billNumber = document.getElementById("billNumberPreview").value.trim();
if (!billNumber) { toast("Bill number is required.", "danger"); return; }
      const shipSame = document.getElementById("shipSameCheck").checked;
      const isLeadSource = document.getElementById("custSrcLead").checked;
      const isBillSource = document.getElementById("custSrcBill").checked;
      const lead = (isLeadSource || isBillSource) && selectedLeadId ? CFG.leads.find(l => l.id === selectedLeadId) : null;
      const payload = {
        bill_number: billNumber,   // NEW
        bill_type: currentBillType(),
        lead_id: lead ? lead.id : null,
        customer_name: document.getElementById("custName").value.trim(),
        company_name: document.getElementById("custCompany").value.trim(),
        gst_number: document.getElementById("custGst").value.trim(),
        phone: document.getElementById("custPhone").value.trim(),
        email: document.getElementById("custEmail").value.trim(),
        billing_address: document.getElementById("custBillingAddress").value.trim(),
        shipping_address: document.getElementById("custShippingAddress").value.trim(),
        ship_same_as_billing: shipSame,
        billing_date: document.getElementById("billDate").value,
        valid_till: document.getElementById("billValidTill").value || null,
        billing_header_id: document.getElementById("billHeaderId").value || null,
        bank_id: document.getElementById("billBankId").value || null,
        courier_name: document.getElementById("courierName").value.trim(),
        courier_price: parseFloat(document.getElementById("courierPrice").value) || 0,
        courier_tax_type: document.getElementById("courierTaxType").value || null,
        courier_tax_percent: parseFloat(document.getElementById("courierTaxPercent").value) || 0,
        items: validItems.map(r => ({
          product_id: r.product_id,
          product_name: r.product_name,
          hsn_sku: r.hsn_sku,
          unit: r.unit,
          price: r.price,
          quantity: r.quantity,
          taxes: r.taxes,
        })),
      };

      const id = document.getElementById("billId").value;
      const url = id ? CFG.routes.update.replace("__ID__", id) : CFG.routes.store;

      let res;
      try {
        res = await apiFetch(url, {
          method: id ? "PUT" : "POST",
          headers: csrfHeaders({ "Content-Type": "application/json" }),
          body: JSON.stringify(payload),
        });
      } catch (err) {
        toast(err.message || "Could not save bill.", "danger");
        return;
      }

      if (!res.success) { toast(firstError(res), "danger"); return; }

      if (!id) bumpNumber(res.bill.bill_type, res.bill.bill_number);
      await loadBills();
      bootstrap.Modal.getOrCreateInstance(document.getElementById("billModal")).hide();
      toast(res.message);
    });
  }

  /* ---------------- Bank modal ---------------- */

  function renderBankList() {
    const box = document.getElementById("bankManageList");
    if (!banks.length) { box.innerHTML = `<div class="fs-12 text-muted-2 text-center py-2">No banks added yet.</div>`; return; }
    box.innerHTML = banks.map(b => `
      <div class="manage-list-row">
        <div>
          <div class="mlr-name">${escapeHtml(b.bank_name)}</div>
          <div class="mlr-sub">${escapeHtml(b.account_holder_name)} · ****${escapeHtml(String(b.account_number).slice(-4))}</div>
        </div>
        <div class="mlr-actions">
          <button type="button" class="bank-edit-btn" data-id="${b.id}" title="Edit"><i class="bi bi-pencil"></i></button>
          <button type="button" class="bank-del-btn danger" data-id="${b.id}" title="Delete"><i class="bi bi-trash"></i></button>
        </div>
      </div>`).join("");

    box.querySelectorAll(".bank-edit-btn").forEach(btn => btn.addEventListener("click", () => loadBankIntoForm(parseInt(btn.dataset.id, 10))));
    box.querySelectorAll(".bank-del-btn").forEach(btn => btn.addEventListener("click", async () => {
      if (!confirm("Delete this bank?")) return;
      try {
        const res = await apiFetch(CFG.routes.bankDestroy.replace("__ID__", btn.dataset.id), { method: "DELETE" });
        if (res.success) {
          banks = banks.filter(b => b.id !== parseInt(btn.dataset.id, 10));
          renderBankList();
          toast(res.message, "dark");
        }
      } catch (err) {
        toast(err.message || "Could not delete bank.", "danger");
      }
    }));
  }

  function loadBankIntoForm(id) {
    const bank = banks.find(b => b.id === id);
    if (!bank) return;
    editingBankId = id;
    document.getElementById("bankId").value = id;
    document.getElementById("bankName").value = bank.bank_name;
    document.getElementById("bankHolder").value = bank.account_holder_name;
    document.getElementById("bankAccNumber").value = bank.account_number;
    document.getElementById("bankAccNumberConfirm").value = "";
    document.getElementById("bankIfsc").value = bank.ifsc_code;
    document.getElementById("bankBranch").value = bank.branch || "";
    document.getElementById("bankModalTitle").textContent = "Edit bank";
    document.getElementById("bankSubmitBtn").textContent = "Save changes";
    document.getElementById("bankCancelEditBtn").classList.remove("d-none");
  }

  function resetBankForm() {
    editingBankId = null;
    document.getElementById("bankForm").reset();
    document.getElementById("bankId").value = "";
    document.getElementById("bankModalTitle").textContent = "Add bank";
    document.getElementById("bankSubmitBtn").textContent = "Add bank";
    document.getElementById("bankCancelEditBtn").classList.add("d-none");
  }

  // FIX: openBankModalBtn only exists in the DOM for super admins
  // (guarded by an isSuperAdmin check in the blade template).
  // For a normal admin the button isn't rendered, so getElementById()
  // returns null and calling .addEventListener() on it crashed the whole
  // script — which is why nothing else on the page worked either.
  // Guard every optional-button lookup with a null check before attaching
  // its listener; the rest of the modal (form submit, manage list) still
  // works fine if it's ever opened programmatically.
  function initBankModal() {
    const openBtn = document.getElementById("openBankModalBtn");
    if (openBtn) {
      openBtn.addEventListener("click", () => {
        resetBankForm();
        renderBankList();
        bootstrap.Modal.getOrCreateInstance(document.getElementById("bankModal")).show();
      });
    }
    document.getElementById("bankCancelEditBtn").addEventListener("click", resetBankForm);

    document.getElementById("bankForm").addEventListener("submit", async e => {
      e.preventDefault();
      const fd = new FormData();
      fd.append("bank_name", document.getElementById("bankName").value.trim());
      fd.append("account_holder_name", document.getElementById("bankHolder").value.trim());
      fd.append("account_number", document.getElementById("bankAccNumber").value.trim());
      fd.append("account_number_confirmation", document.getElementById("bankAccNumberConfirm").value.trim());
      fd.append("ifsc_code", document.getElementById("bankIfsc").value.trim());
      fd.append("branch", document.getElementById("bankBranch").value.trim());
      const qrFile = document.getElementById("bankQr").files[0];
      if (qrFile) fd.append("qr_code", qrFile);

      let url = CFG.routes.bankStore;
      if (editingBankId) { fd.append("_method", "PUT"); url = CFG.routes.bankUpdate.replace("__ID__", editingBankId); }

      let res;
      try {
        res = await apiFetchForm(url, fd);
      } catch (err) {
        toast(err.message || "Could not save bank.", "danger");
        return;
      }
      if (!res.success) { toast(firstError(res), "danger"); return; }

      if (editingBankId) {
        banks = banks.map(b => b.id === res.bank.id ? res.bank : b);
      } else {
        banks.push(res.bank);
      }
      resetBankForm();
      renderBankList();
      populateSelectOptions(document.getElementById("billBankId"), banks, "id", b => `${b.bank_name} — ${b.account_number}`, "— none selected —");
      toast(res.message);
    });
  }

  /* ---------------- Billing header modal ---------------- */

  function renderHeaderList() {
    const box = document.getElementById("headerManageList");
    if (!headers.length) { box.innerHTML = `<div class="fs-12 text-muted-2 text-center py-2">No billing headers added yet.</div>`; return; }
    box.innerHTML = headers.map(h => `
      <div class="manage-list-row">
        <div>
          <div class="mlr-name">${escapeHtml(h.company_name)} ${h.is_default ? '<span class="default-pill">DEFAULT</span>' : ""}</div>
          <div class="mlr-sub">${escapeHtml(h.gstin || "No GSTIN")} · ${escapeHtml(h.phone || "")}</div>
        </div>
        <div class="mlr-actions">
          <button type="button" class="header-edit-btn" data-id="${h.id}" title="Edit"><i class="bi bi-pencil"></i></button>
          <button type="button" class="header-del-btn danger" data-id="${h.id}" title="Delete"><i class="bi bi-trash"></i></button>
        </div>
      </div>`).join("");

    box.querySelectorAll(".header-edit-btn").forEach(btn => btn.addEventListener("click", () => loadHeaderIntoForm(parseInt(btn.dataset.id, 10))));
    box.querySelectorAll(".header-del-btn").forEach(btn => btn.addEventListener("click", async () => {
      if (!confirm("Delete this billing header?")) return;
      try {
        const res = await apiFetch(CFG.routes.headerDestroy.replace("__ID__", btn.dataset.id), { method: "DELETE" });
        if (res.success) {
          headers = headers.filter(h => h.id !== parseInt(btn.dataset.id, 10));
          renderHeaderList();
          toast(res.message, "dark");
        }
      } catch (err) {
        toast(err.message || "Could not delete header.", "danger");
      }
    }));
  }

  function loadHeaderIntoForm(id) {
    const header = headers.find(h => h.id === id);
    if (!header) return;
    editingHeaderId = id;
    document.getElementById("headerId").value = id;
    document.getElementById("headerCompanyName").value = header.company_name;
    document.getElementById("headerGstin").value = header.gstin || "";
    document.getElementById("headerPhone").value = header.phone || "";
    document.getElementById("headerAddress").value = header.address || "";
    document.getElementById("headerIsDefault").checked = !!header.is_default;
    document.getElementById("headerModalTitle").textContent = "Edit billing header";
    document.getElementById("headerSubmitBtn").textContent = "Save changes";
    document.getElementById("headerCancelEditBtn").classList.remove("d-none");
  }

  function resetHeaderForm() {
    editingHeaderId = null;
    document.getElementById("headerForm").reset();
    document.getElementById("headerId").value = "";
    document.getElementById("headerModalTitle").textContent = "Add billing header";
    document.getElementById("headerSubmitBtn").textContent = "Add header";
    document.getElementById("headerCancelEditBtn").classList.add("d-none");
  }

  // FIX: same reasoning as initBankModal() above — openHeaderModalBtn is
  // also gated by the isSuperAdmin check and won't exist for a
  // normal admin, so guard it with a null check too.
  function initHeaderModal() {
    const openBtn = document.getElementById("openHeaderModalBtn");
    if (openBtn) {
      openBtn.addEventListener("click", () => {
        resetHeaderForm();
        renderHeaderList();
        bootstrap.Modal.getOrCreateInstance(document.getElementById("headerModal")).show();
      });
    }
    document.getElementById("headerCancelEditBtn").addEventListener("click", resetHeaderForm);

    document.getElementById("headerForm").addEventListener("submit", async e => {
      e.preventDefault();
      const fd = new FormData();
      fd.append("company_name", document.getElementById("headerCompanyName").value.trim());
      fd.append("gstin", document.getElementById("headerGstin").value.trim());
      fd.append("phone", document.getElementById("headerPhone").value.trim());
      fd.append("address", document.getElementById("headerAddress").value.trim());
      fd.append("is_default", document.getElementById("headerIsDefault").checked ? "1" : "0");
      const logoFile = document.getElementById("headerLogo").files[0];
      if (logoFile) fd.append("logo", logoFile);

      let url = CFG.routes.headerStore;
      if (editingHeaderId) { fd.append("_method", "PUT"); url = CFG.routes.headerUpdate.replace("__ID__", editingHeaderId); }

      let res;
      try {
        res = await apiFetchForm(url, fd);
      } catch (err) {
        toast(err.message || "Could not save billing header.", "danger");
        return;
      }
      if (!res.success) { toast(firstError(res), "danger"); return; }

      if (res.header.is_default) headers = headers.map(h => ({ ...h, is_default: false }));
      if (editingHeaderId) {
        headers = headers.map(h => h.id === res.header.id ? res.header : h);
      } else {
        headers.push(res.header);
      }
      resetHeaderForm();
      renderHeaderList();
      populateSelectOptions(document.getElementById("billHeaderId"), headers, "id", h => h.company_name + (h.is_default ? " (default)" : ""), "— none selected —");
      toast(res.message);
    });
  }

  /* ---------------- Boot ---------------- */

  document.addEventListener("DOMContentLoaded", () => {
   initLeadPicker();
    initBillPicker();
    initFilters();
    initBulkActions();
    initCustomerSource();
    initBillTypeGroup();
    initItemRowEvents();
    initCourierEvents();
    initBillForm();
    initBankModal();
    initHeaderModal();
    loadBills();
  });
})();
</script>

@endsection