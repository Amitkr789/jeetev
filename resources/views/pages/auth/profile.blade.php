@extends('layouts.app')
@section('title', 'Dalal Adda | My Profile')
@section('content')
<div class="page-content">
  <section class="page-section active" id="page-profile">
    <div class="page-header">
      <div>
        <span class="breadcrumb-eyebrow">Account</span>
        <h1>My Profile</h1>
        <p>Update your personal details and manage your account password.</p>
      </div>
    </div>

    <div class="row g-4">
      <!-- Profile info card -->
      <div class="col-lg-6">
        <div class="section-card">
          <div class="section-card-head">
            <h2>Profile Details</h2>
          </div>
          <form id="profileForm" class="p-3">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label fs-13 fw-600">Full name</label>
                <input type="text" class="form-control" id="pfName" value="{{ $admin->name }}" required>
                <div class="invalid-feedback d-block text-danger fs-12" id="err_name"></div>
              </div>
              <div class="col-12">
                <label class="form-label fs-13 fw-600">Email</label>
                <input type="email" class="form-control" id="pfEmail" value="{{ $admin->email }}" required>
                <div class="invalid-feedback d-block text-danger fs-12" id="err_email"></div>
              </div>
              <div class="col-12">
                <label class="form-label fs-13 fw-600">Phone</label>
                <input type="text" class="form-control" id="pfPhone" value="{{ $admin->phone }}">
                <div class="invalid-feedback d-block text-danger fs-12" id="err_phone"></div>
              </div>
              <div class="col-12">
                <label class="form-label fs-13 fw-600">Role</label>
                <input type="text" class="form-control" value="{{ $admin->role === 'super_admin' ? 'Super Admin' : 'Admin' }}" disabled>
              </div>
            </div>
            <div class="mt-4 text-end">
              <button type="submit" class="btn btn-primary" id="saveProfileBtn">Save Changes</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Change password card -->
      <div class="col-lg-6">
        <div class="section-card">
          <div class="section-card-head">
            <h2>Change Password</h2>
          </div>
          <form id="passwordForm" class="p-3">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label fs-13 fw-600">Current password</label>
                <input type="password" class="form-control" id="pfCurrentPassword" required>
                <div class="invalid-feedback d-block text-danger fs-12" id="err_current_password"></div>
              </div>
              <div class="col-12">
                <label class="form-label fs-13 fw-600">New password</label>
                <input type="password" class="form-control" id="pfPassword" placeholder="Min 6 characters" required>
                <div class="invalid-feedback d-block text-danger fs-12" id="err_password"></div>
              </div>
              <div class="col-12">
                <label class="form-label fs-13 fw-600">Re-enter new password</label>
                <input type="password" class="form-control" id="pfConfirmPassword" required>
              </div>
            </div>
            <div class="mt-4 text-end">
              <button type="submit" class="btn btn-primary" id="savePasswordBtn">Update Password</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </section>
</div>

<script>
  $(function () {
    $.ajaxSetup({
      headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
    });

    // ---------- UPDATE PROFILE ----------
    $('#profileForm').on('submit', function (e) {
      e.preventDefault();
      clearErrors();

      const payload = {
        name:  $('#pfName').val(),
        email: $('#pfEmail').val(),
        phone: $('#pfPhone').val(),
      };

      $('#saveProfileBtn').prop('disabled', true).text('Saving...');

      $.ajax({
        url: '/admin/profile',
        method: 'PUT',
        data: payload,
        success: function (res) {
          alert(res.message || 'Profile updated.');
        },
        error: function (xhr) {
          if (xhr.status === 422) {
            const errors = xhr.responseJSON.errors;
            Object.keys(errors).forEach(field => {
              $(`#err_${field}`).text(errors[field][0]);
            });
          } else {
            alert(xhr.responseJSON?.message || 'Something went wrong.');
          }
        },
        complete: function () {
          $('#saveProfileBtn').prop('disabled', false).text('Save Changes');
        }
      });
    });

    // ---------- CHANGE PASSWORD ----------
    $('#passwordForm').on('submit', function (e) {
      e.preventDefault();
      clearErrors();

      const password = $('#pfPassword').val();
      const confirm  = $('#pfConfirmPassword').val();

      if (password !== confirm) {
        $('#err_password').text('Passwords do not match.');
        return;
      }

      const payload = {
        current_password: $('#pfCurrentPassword').val(),
        password: password,
        confirm_password: confirm,
      };

      $('#savePasswordBtn').prop('disabled', true).text('Updating...');

      $.ajax({
        url: '/admin/profile/password',
        method: 'PUT',
        data: payload,
        success: function (res) {
          alert(res.message || 'Password changed.');
          $('#passwordForm')[0].reset();
        },
        error: function (xhr) {
          if (xhr.status === 422) {
            const errors = xhr.responseJSON.errors;
            Object.keys(errors).forEach(field => {
              $(`#err_${field}`).text(errors[field][0]);
            });
          } else {
            alert(xhr.responseJSON?.message || 'Something went wrong.');
          }
        },
        complete: function () {
          $('#savePasswordBtn').prop('disabled', false).text('Update Password');
        }
      });
    });

    function clearErrors() {
      $('.invalid-feedback').text('');
    }
  });
</script>
@endsection