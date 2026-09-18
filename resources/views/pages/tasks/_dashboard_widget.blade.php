{{--
  Include this in your main dashboard view, e.g.:
  @include('admin.tasks._dashboard_widget')

  It calls the same tasks.data endpoint the Tasks page uses, so a super
  admin sees org-wide numbers and a regular admin automatically sees only
  their own — no extra backend work needed.
--}}

<div class="section-card p-3 mb-3" id="taskDashboardWidget">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-700 mb-0">{{ Auth::guard('admin')->user()->isSuperAdmin() ? 'Team Tasks' : 'My Tasks' }}</h6>
    <a href="{{ url('admin/tasks') }}" class="fs-13 fw-600" style="color:var(--primary)">View all <i class="bi bi-arrow-right"></i></a>
  </div>
  <div class="row g-2 text-center">
    <div class="col-3">
      <div class="fs-20 fw-800" id="tdwPending">—</div>
      <div class="fs-11 text-muted-2">Pending</div>
    </div>
    <div class="col-3">
      <div class="fs-20 fw-800" style="color:var(--warning)" id="tdwInProgress">—</div>
      <div class="fs-11 text-muted-2">In Progress</div>
    </div>
    <div class="col-3">
      <div class="fs-20 fw-800" style="color:var(--success)" id="tdwCompleted">—</div>
      <div class="fs-11 text-muted-2">Completed</div>
    </div>
    <div class="col-3">
      <div class="fs-20 fw-800" style="color:var(--danger)" id="tdwOverdue">—</div>
      <div class="fs-11 text-muted-2">Overdue</div>
    </div>
  </div>
</div>

<script>
  (function () {
    fetch('{{ route('tasks.data') }}', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => r.json())
      .then(data => {
        if (!data.success) return;
        document.getElementById('tdwPending').textContent = data.stats.pending;
        document.getElementById('tdwInProgress').textContent = data.stats.in_progress;
        document.getElementById('tdwCompleted').textContent = data.stats.completed;
        document.getElementById('tdwOverdue').textContent = data.stats.overdue;
      })
      .catch(() => {});
  })();
</script>
