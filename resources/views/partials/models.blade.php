
<!-- ===================== ADD LEAD MODAL ===================== -->
<div class="modal fade modal-lg" id="adduserModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="addLeadForm">
        <div class="modal-header">
          <h5 class="modal-title">Add new User</h5>
          <button type="button" class="btn-close mx-3" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fs-13 fw-600">Full name</label>
              <input type="text" class="form-control" id="alName" placeholder="e.g. Jordan Patel" required>
            </div>
            <div class="col-6">
              <label class="form-label fs-13 fw-600">Email</label>
              <input type="email" class="form-control" id="alEmail" placeholder="name@company.com" required>
            </div>
            <div class="col-6">
              <label class="form-label fs-13 fw-600">Phone</label>
              <input type="text" class="form-control" id="alPhone" placeholder="+1 555 000 0000">
            </div>
            <div class="col-6">
              <label class="form-label fs-13 fw-600">Password</label>
              <input type="email" class="form-control" id="alEmail" placeholder="name@company.com" required>
            </div>
            <div class="col-6">
              <label class="form-label fs-13 fw-600">Re-Enter password</label>
              <input type="text" class="form-control" id="alPhone" placeholder="+1 555 000 0000">
            </div>
            <div class="col-12">
              <label class="form-label fs-13 fw-600">Role</label>
              <select class="form-select" id="alSource">
                <option name="admin">Admin</option>
                <option name="super_admin">Super Admin</option>
              </select>
            </div>
           
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add User</button>
        </div>
      </form>
    </div>
  </div>
</div>