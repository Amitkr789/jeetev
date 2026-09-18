<!-- ===================== SIDEBAR ===================== -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="mark text-light">JE</div>
      <div class="name">JEETVOLTEV<small class="mt-1">CRM WORKSPACE</small></div>
    </div>

    <div class="sidebar-search">
      <i class="bi bi-search"></i>
      <input type="text" id="sidebarMenuSearch" class="form-control form-control-sm rounded-3 text-dark" placeholder="Quick search...">
    </div>

    <nav class="sidebar-nav">
      <div class="sidebar-section-label">Workspace</div>
      <a href="/admin" class="nav-link" data-page="dashboard" data-title="Dashboard"><i class="bi bi-grid-1x2"></i>Dashboard</a>
      <a href="/admin/leads" class="nav-link" data-page="leads" data-title="Leads"><i class="bi bi-person-lines-fill"></i>Lead
        <!-- <span class="badge-count" id="navLeadCount">16</span> -->

      </a>

      <!-- <a href="contacts.html" class="nav-link" data-page="contacts" data-title="Contacts"><i class="bi bi-people"></i>Contacts</a> -->
      @if(auth('admin')->user()->isSuperAdmin())
      <a href="/admin/meta-leads" class="nav-link" data-page="reminders" data-title="Reminders &amp; Tasks"><i class="bi bi-receipt"></i>Meta Leads</a>
      <a href="/admin/inventory" class="nav-link" data-page="deals" data-title="Deals"><i class="bi bi-currency-exchange"></i>Inventory</a>

      @endif
      <a href="/admin/chat" class="nav-link" data-page="settings" data-title="Settings"><i class="bi bi-chat"></i> Chat Room</a>
      <a href="/admin/reminders" class="nav-link" data-page="reminders" data-title="Reminders &amp; Tasks"><i class="bi bi-bell"></i>Reminders</a>

         <a href="/admin/tasks" class="nav-link" data-page="reminders" data-title="Reminders &amp; Tasks"><i class="bi bi-receipt"></i>Task</a>
          <a href="/admin/billing" class="nav-link" data-page="reminders" data-title="Reminders &amp; Tasks"><i class="bi bi-receipt"></i>Billings</a>


      @if(auth('admin')->user()->isSuperAdmin())
      <div class="sidebar-section-label">Manage AI</div>
      <a href="/admin/knowledge-base" class="nav-link" data-page="reports" data-title="Reports"><i class="bi bi-bar-chart-line"></i>Knowledge Base</a>
      <a href="/admin/chatbot-settings" class="nav-link" data-page="reports" data-title="Reports"><i class="bi bi-gear"></i>AI settings</a>
      @endif

    </nav>

    <div class="sidebar-footer">
      <a href="#" class="sidebar-user">
        <div class="avatar avatar-sm" style="background:#6366F1">{{ strtoupper(collect(explode(' ', Auth::guard('admin')->user()->name))->map(fn($word) => substr($word, 0, 1))->join('')) }}</div>
        <div class="info">
          <div class="uname">{{Auth::guard('admin')->user()->name}}</div>
          <div class="urole">{{Auth::guard('admin')->user()->role}}</div>
        </div>
      </a>
    </div>
  </aside>
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
  <!-- ===================== MAIN ===================== -->
  <div class="main-col">

    <!-- Topbar -->
    <header class="topbar">
      <button class="btn-hamburger" id="hamburgerBtn"><i class="bi bi-list fs-5"></i></button>

      <div class="topbar-search">
        <i class="bi bi-search"></i>
        <input type="text" class="form-control" placeholder="Search leads, contacts, deals...">
      </div>

      <div class="topbar-actions">
        <!-- Notification bell: now a real dropdown fed by the global reminder poll -->
        <div class="dropdown">
  <button class="icon-btn" id="taskBellBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" title="Task notifications">
    <i class="bi bi-list-check fs-5"></i>
    <span class="notif-badge" id="taskBellCount" style="display:none"></span>
  </button>
  <div class="dropdown-menu dropdown-menu-end notif-dropdown" id="taskBellList" style="width:320px">
    <div class="notif-empty">No task notifications</div>
  </div>
</div>
        <div class="dropdown">
          <button class="icon-btn" id="notifBellBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" title="Notifications">
            <i class="bi bi-bell fs-5"></i>
            <span class="notif-badge" id="notifBellCount" style="display:none"></span>
          </button>
          <div class="dropdown-menu dropdown-menu-end notif-dropdown" id="notifBellList" style="width:320px">
            <div class="notif-empty">No reminders due right now</div>
          </div>
        </div>
        <button class="icon-btn d-none d-md-flex" data-bs-toggle="tooltip" title="Help"><i class="bi bi-question-circle fs-5"></i></button>
        <div class="dropdown">
          <a href="#" class="topbar-profile" data-bs-toggle="dropdown">
            <div class="avatar avatar-sm" style="background:#6366F1">{{ strtoupper(collect(explode(' ', Auth::guard('admin')->user()->name))->map(fn($word) => substr($word, 0, 1))->join('')) }}</div>
            <i class="bi bi-chevron-down fs-12 d-none d-sm-inline" style="color:var(--text-muted)"></i>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><h6 class="dropdown-header">{{ Auth::guard('admin')->user()->name }}</h6></li>
            <li><a class="dropdown-item" href="{{route('admin.profile')}}"><i class="bi bi-person me-2"></i>My profile</a></li>
            @if(auth('admin')->user()->isSuperAdmin())
            <li><a class="dropdown-item" href="{{route('admin.register')}}"><i class="bi bi-gear me-2"></i>Settings</a></li>
            @endif
            <li><hr class="dropdown-divider"></li>
            <li><form id="logoutForm" method="POST" action="{{ route('admin.logout') }}" class="d-none">@csrf</form>
<a class="dropdown-item" href="{{route('admin.logout')}}" id="logoutBtn"><i class="bi bi-box-arrow-right me-2"></i>Sign out</a></li>
          </ul>
        </div>
      </div>
    </header>

    <style>
      /* Global reminder alarm popup — lives here (not on the leads page) because
         the modal can now appear on any page. */
      .alarm-card { border: 1px solid var(--border); border-radius: 12px; padding: 12px 14px; margin-bottom: 10px; }
      .alarm-card:last-child { margin-bottom: 0; }
      .alarm-card .alarm-lead-name { font-weight: 700; font-size: 14px; }
      .alarm-card .alarm-note { font-size: 12.5px; color: var(--text-muted); margin: 3px 0 10px; }
      .alarm-card .alarm-actions { display: flex; gap: 8px; }
      .alarm-card .alarm-actions .btn { flex: 1; padding: 7px 10px; font-size: 12.5px; }

      /* Topbar notification bell */
      .icon-btn { position: relative; }
      .notif-badge {
        position: absolute; top: 2px; right: 2px; min-width: 16px; height: 16px; padding: 0 4px;
        background: var(--danger); color: #fff; border-radius: 20px; font-size: 10px; font-weight: 700;
        display: flex; align-items: center; justify-content: center; line-height: 1;
      }
      .notif-dropdown { padding: 6px; max-height: 360px; overflow-y: auto; }
      .notif-dropdown .notif-item { display: flex; flex-direction: column; gap: 2px; padding: 9px 10px; border-radius: 9px; }
      .notif-dropdown .notif-item:hover { background: var(--primary-50); }
      .notif-dropdown .notif-item .notif-name { font-weight: 700; font-size: 13px; }
      .notif-dropdown .notif-item .notif-note { font-size: 12px; color: var(--text-muted); }
      .notif-dropdown .notif-item .notif-meta { font-size: 11px; color: var(--danger); font-weight: 600; }
      .notif-dropdown .notif-item .notif-actions { display: flex; gap: 6px; margin-top: 4px; }
      .notif-dropdown .notif-item .notif-actions .btn { font-size: 11.5px; padding: 3px 9px; border-radius: 7px; }
      .notif-dropdown .notif-empty { padding: 20px 10px; text-align: center; font-size: 12.5px; color: var(--text-muted); }
    </style>

    <script>
      // Global reminder config — read by /js/global-reminders.js on every page.
      window.GLOBAL_REMINDER_CONFIG = {
        csrfToken: '{{ csrf_token() }}',
        routes: {
          reminderCheck: '{{ route('leads.reminders.check') }}',
          reminderStatus: '{{ url('admin/leads/reminders') }}/__ID__/status',
          leadsPage: '{{ url('admin/leads') }}',
        }
      };

    </script><script>

  window.TASK_NOTIF_CONFIG = {
    csrfToken: '{{ csrf_token() }}',
    routes: {
      check: '{{ route('tasks.notifications.check') }}',
      read:  '{{ route('tasks.notifications.read') }}',
      tasksPage: '{{ url('admin/tasks') }}',
    }
  };
</script>


