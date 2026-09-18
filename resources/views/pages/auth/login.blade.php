<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Sign in · LeadFlow CRM</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="{{ asset('assets/css/style.css') }}" rel="stylesheet">
</head>
<body>

<div class="login-shell">

  <aside class="login-aside">
    <div class="login-brand">
      <div class="mark">LF</div>
      <div class="name">LeadFlow CRM</div>
    </div>

    <div class="login-pitch">
      <h2>Turn more leads into closed deals.</h2>
      <p>One place to capture, qualify, and follow up with every lead — before your competitors do.</p>

      <ul class="login-feature-list">
        <li><span class="check"><i class="bi bi-check-lg"></i></span> Smart filters &amp; lead scoring</li>
        <li><span class="check"><i class="bi bi-check-lg"></i></span> Automatic follow-up reminders</li>
        <li><span class="check"><i class="bi bi-check-lg"></i></span> Full activity timeline per lead</li>
        <li><span class="check"><i class="bi bi-check-lg"></i></span> Team-wide pipeline visibility</li>
      </ul>
    </div>

    <p class="login-quote">"LeadFlow cut our response time from a day to under an hour." — Ops Lead, Orbit Logistics</p>
  </aside>

  <main class="login-main">
    <div class="login-form-wrap">
      <h1>Welcome back</h1>
      <p class="sub">Sign in to your workspace to keep your pipeline moving.</p>

      <form id="loginForm" data-action="{{ url('/admin/login') }}" novalidate>
        <div class="mb-3">
          <label class="form-label" for="email">Work email</label>
          <input type="email" class="form-control" id="email" placeholder="you@company.com" required>
          <div class="invalid-feedback d-block text-danger fs-12" id="err_email"></div>
        </div>

        <div class="mb-2">
          <label class="form-label" for="password">Password</label>
          <div class="pwd-wrap">
            <input type="password" class="form-control" id="password" placeholder="Enter your password" required>
            <button type="button" class="toggle-pwd" id="togglePwd" aria-label="Show password"><i class="bi bi-eye"></i></button>
          </div>
          <div class="invalid-feedback d-block text-danger fs-12" id="err_password"></div>
        </div>

        <div class="d-flex align-items-center justify-content-between mb-3 mt-2">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="rememberMe">
            <label class="form-check-label fs-13" for="rememberMe">Remember me</label>
          </div>
        </div>

        <button type="submit" class="btn btn-primary w-100" id="loginBtn">
          <span id="loginBtnText">Sign in</span>
        </button>
      </form>

      <p class="text-center fs-13 text-muted-2 mt-4 mb-0">Access sirf authorized admins ke liye hai.</p>
    </div>
  </main>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastStack" style="z-index:1080"></div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  $(function () {
  $.ajaxSetup({
    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
  });

  // Show/hide password
  $('#togglePwd').on('click', function () {
    const $pwd = $('#password');
    const $icon = $(this).find('i');
    if ($pwd.attr('type') === 'password') {
      $pwd.attr('type', 'text');
      $icon.attr('class', 'bi bi-eye-slash');
    } else {
      $pwd.attr('type', 'password');
      $icon.attr('class', 'bi bi-eye');
    }
  });

  // Submit login
  $('#loginForm').on('submit', function (e) {
    e.preventDefault();
    clearErrors();

    const $btn = $('#loginBtn');
    const $txt = $('#loginBtnText');
    $btn.prop('disabled', true);
    $txt.html('<span class="spinner-border spinner-border-sm me-2"></span>Signing in…');

    $.ajax({
      url: $('#loginForm').data('action'),
      method: 'POST',
      data: {
        email: $('#email').val(),
        password: $('#password').val(),
        remember: $('#rememberMe').is(':checked') ? 1 : 0,
      },
      success: function (res) {
        showToast('success', res.message || 'Login successful.');
        setTimeout(() => { window.location.href = res.redirect; }, 500);
      },
      error: function (xhr) {
        if (xhr.status === 422) {
          const errors = xhr.responseJSON.errors;
          Object.keys(errors).forEach(field => $(`#err_${field}`).text(errors[field][0]));
        } else {
          showToast('error', xhr.responseJSON?.message || 'Login failed. Please try again.');
        }
      },
      complete: function () {
        $btn.prop('disabled', false);
        $txt.text('Sign in');
      }
    });
  });

  function clearErrors() {
    $('.invalid-feedback').text('');
  }

  function showToast(type, msg) {
    const bg = type === 'success' ? 'text-bg-success' : 'text-bg-danger';
    const $toast = $(`
      <div class="toast align-items-center ${bg} border-0" role="alert">
        <div class="d-flex">
          <div class="toast-body">${msg}</div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
      </div>
    `);
    $('#toastStack').append($toast);
    new bootstrap.Toast($toast[0], { delay: 3000 }).show();
  }
});
</script>
</body>
</html>