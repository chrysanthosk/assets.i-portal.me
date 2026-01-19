<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>Login</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="hold-transition login-page">
<div class="login-box">
  <div class="card card-outline card-primary">
    <div class="card-header text-center">
      <b>i-portal</b>
    </div>

    <div class="card-body">
      <p class="login-box-msg">Sign in to start your session</p>

      @if (session('status'))
        <div class="alert alert-info">{{ session('status') }}</div>
      @endif

      @if ($errors->any())
        <div class="alert alert-danger">
          <ul class="mb-0">
            @foreach ($errors->all() as $e)
              <li>{{ $e }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="input-group mb-3">
          <input type="text" name="username" class="form-control" placeholder="Username" value="{{ old('username') }}" required autofocus>
          <div class="input-group-text"><span class="bi bi-person"></span></div>
        </div>

        <div class="input-group mb-3">
          <input type="password" name="password" class="form-control" placeholder="Password" required>
          <div class="input-group-text"><span class="bi bi-lock"></span></div>
        </div>

        <div class="row align-items-center">
          <div class="col-8">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="themeBtn">
              <span id="themeLabel">Dark</span> Mode
            </button>
          </div>
          <div class="col-4">
            <button type="submit" class="btn btn-primary w-100">Sign In</button>
          </div>
        </div>
      </form>

      <!-- removed default admin message -->
    </div>
  </div>
</div>

<script>
  // AdminLTE 4 + Bootstrap 5:
  // - Bootstrap uses <html data-bs-theme="dark">
  // - AdminLTE supports body.dark-mode
  (function () {
    const html = document.documentElement;
    const body = document.body;

    function applyTheme(theme) {
      const isDark = theme === 'dark';
      html.setAttribute('data-bs-theme', isDark ? 'dark' : 'light');
      body.classList.toggle('dark-mode', isDark);
      localStorage.setItem('theme', theme);

      const label = document.getElementById('themeLabel');
      if (label) label.textContent = isDark ? 'Light' : 'Dark';
    }

    const saved = localStorage.getItem('theme') || 'light';
    applyTheme(saved);

    const btn = document.getElementById('themeBtn');
    if (btn) {
      btn.addEventListener('click', function () {
        const current = localStorage.getItem('theme') || 'light';
        applyTheme(current === 'dark' ? 'light' : 'dark');
      });
    }
  })();
</script>
</body>
</html>
