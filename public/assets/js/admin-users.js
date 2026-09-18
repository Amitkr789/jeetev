$(function () {
  const BASE = '/admin/users';
  let currentPage = 1;
  let perPage = 10;
  let selectedIds = new Set();

  // CSRF setup for all AJAX calls
  $.ajaxSetup({
    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
  });

  loadUsers();

  // ---------- FETCH ----------
  function loadUsers(page = 1) {
    currentPage = page;
    $('#leadsTbody').html('<tr><td colspan="8" class="text-center py-4">Loading...</td></tr>');

    $.ajax({
      url: `${BASE}/list`,
      method: 'GET',
      data: { page: page, per_page: perPage },
      success: function (res) {
        renderTable(res.data);
        renderPagination(res.meta);
        $('#leadsCountBadge').text(res.meta.total + ' users');
      },
      error: function () {
        $('#leadsTbody').html('<tr><td colspan="8" class="text-center text-danger py-4">Failed to load users.</td></tr>');
      }
    });
  }

  function renderTable(users) {
    if (!users.length) {
      $('#leadsTbody').html('<tr><td colspan="8" class="text-center py-4">No users found.</td></tr>');
      return;
    }
    let rows = users.map(u => `
      <tr data-id="${u.id}">
        <td><input type="checkbox" class="form-check-input row-check" value="${u.id}"></td>
        <td>${escapeHtml(u.name)}</td>
        <td>${escapeHtml(u.email)}</td>
        <td>${u.phone ?? '-'}</td>
        <td><span class="badge-status">${u.role === 'super_admin' ? 'Super Admin' : 'Admin'}</span></td>
        <td>
          <div class="form-check form-switch m-0">
            <input class="form-check-input status-toggle" type="checkbox" data-id="${u.id}" ${u.status === 'active' ? 'checked' : ''}>
          </div>
        </td>
        <td>${new Date(u.created_at).toLocaleDateString()}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-light-soft edit-btn" data-id="${u.id}"><i class="bi bi-pencil"></i></button>
          <button class="btn btn-sm btn-light-soft text-danger delete-btn" data-id="${u.id}"><i class="bi bi-trash"></i></button>
        </td>
      </tr>
    `).join('');
    $('#leadsTbody').html(rows);
  }

  function renderPagination(meta) {
    $('#pageInfo').text(`Page ${meta.current_page} of ${meta.last_page} · ${meta.total} total`);
    let btns = '';
    for (let i = 1; i <= meta.last_page; i++) {
      btns += `<button class="btn btn-sm ${i === meta.current_page ? 'btn-primary' : 'btn-outline-secondary'} page-btn" data-page="${i}">${i}</button>`;
    }
    $('#pageButtons').html(btns);
  }

  $(document).on('click', '.page-btn', function () {
    loadUsers($(this).data('page'));
  });

  // ---------- ADD ----------
  $('#openAddUserBtn').on('click', function () {
    resetForm();
    $('#userModalTitle').text('Add new User');
    $('#saveUserBtn').text('Add User');
    $('#editPassHint').hide();
    $('#alPassword, #alConfirmPassword').attr('required', true);
  });

  // ---------- EDIT (open modal, prefill) ----------
  $(document).on('click', '.edit-btn', function () {
    const id = $(this).data('id');
    $.get(`${BASE}/${id}`, function (res) {
      const u = res.data;
      resetForm();
      $('#alId').val(u.id);
      $('#alName').val(u.name);
      $('#alEmail').val(u.email);
      $('#alPhone').val(u.phone);
      $('#alRole').val(u.role);
      $('#userModalTitle').text('Edit User');
      $('#saveUserBtn').text('Update User');
      $('#editPassHint').show();
      $('#alPassword, #alConfirmPassword').removeAttr('required');
      new bootstrap.Modal('#adduserModal').show();
    }).fail(() => alert('Could not load user.'));
  });

  // ---------- ADD/UPDATE SUBMIT ----------
  $('#addLeadForm').on('submit', function (e) {
    e.preventDefault();
    clearErrors();

    const id = $('#alId').val();
    const payload = {
      name: $('#alName').val(),
      email: $('#alEmail').val(),
      phone: $('#alPhone').val(),
      password: $('#alPassword').val(),
      confirm_password: $('#alConfirmPassword').val(),
      role: $('#alRole').val(),
    };

    if (payload.password !== payload.confirm_password) {
      $('#err_password').text('Passwords do not match.');
      return;
    }

    const url = id ? `${BASE}/${id}` : BASE;
    const method = id ? 'PUT' : 'POST';

    $('#saveUserBtn').prop('disabled', true).text('Saving...');

    $.ajax({
      url, method, data: payload,
      success: function (res) {
        bootstrap.Modal.getInstance(document.getElementById('adduserModal')).hide();
        loadUsers(currentPage);
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
        $('#saveUserBtn').prop('disabled', false).text(id ? 'Update User' : 'Add User');
      }
    });
  });

  // ---------- TOGGLE STATUS ----------
  $(document).on('change', '.status-toggle', function () {
    const $toggle = $(this);
    const id = $toggle.data('id');
    $toggle.prop('disabled', true);

    $.ajax({
      url: `${BASE}/${id}/toggle-status`,
      method: 'PATCH',
      success: function (res) {
        $toggle.prop('checked', res.status === 'active');
      },
      error: function (xhr) {
        $toggle.prop('checked', !$toggle.is(':checked')); // revert
        alert(xhr.responseJSON?.message || 'Failed to update status.');
      },
      complete: function () {
        $toggle.prop('disabled', false);
      }
    });
  });

  // ---------- SINGLE DELETE ----------
  $(document).on('click', '.delete-btn', function () {
    const id = $(this).data('id');
    if (!confirm('Delete this user? This cannot be undone.')) return;

    $.ajax({
      url: `${BASE}/${id}`,
      method: 'DELETE',
      success: () => loadUsers(currentPage),
      error: (xhr) => alert(xhr.responseJSON?.message || 'Delete failed.')
    });
  });

  // ---------- MULTISELECT ----------
  $('#selectAllCheck').on('change', function () {
    const checked = $(this).is(':checked');
    $('.row-check').prop('checked', checked);
    selectedIds.clear();
    if (checked) $('.row-check').each(function () { selectedIds.add($(this).val()); });
    updateBulkBar();
  });

  $(document).on('change', '.row-check', function () {
    const val = $(this).val();
    this.checked ? selectedIds.add(val) : selectedIds.delete(val);
    updateBulkBar();
  });

  function updateBulkBar() {
    $('#bulkCount').text(selectedIds.size);
    $('#bulkBar').toggleClass('d-none', selectedIds.size === 0);
  }

  $('#bulkClearBtn').on('click', function () {
    selectedIds.clear();
    $('.row-check, #selectAllCheck').prop('checked', false);
    updateBulkBar();
  });

  $('#bulkDeleteBtn').on('click', function () {
    if (!selectedIds.size) return;
    if (!confirm(`Delete ${selectedIds.size} selected user(s)?`)) return;

    $.ajax({
      url: `${BASE}/bulk-delete`,
      method: 'POST',
      data: { ids: Array.from(selectedIds) },
      success: function () {
        selectedIds.clear();
        updateBulkBar();
        loadUsers(1);
      },
      error: (xhr) => alert(xhr.responseJSON?.message || 'Bulk delete failed.')
    });
  });

  // ---------- HELPERS ----------
  function resetForm() {
    $('#addLeadForm')[0].reset();
    $('#alId').val('');
    clearErrors();
  }

  function clearErrors() {
    $('.invalid-feedback').text('');
  }

  function escapeHtml(str) {
    return $('<div>').text(str ?? '').html();
  }
});