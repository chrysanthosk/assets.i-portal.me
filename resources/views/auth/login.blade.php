<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <script>try { document.documentElement.setAttribute('data-bs-theme', localStorage.getItem('theme') || 'dark'); } catch (e) {}</script>
  @php $portalNameValue = \App\Models\PortalSetting::name(); @endphp
  <title>Sign in · {{ $portalNameValue }}</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <div class="d-flex align-items-center justify-content-center gap-2 mb-4">
      <span class="brand-mark" style="width:36px;height:36px;border-radius:9px;display:grid;place-items:center;background:linear-gradient(135deg,var(--brand),#22d3ee);color:#fff;font-weight:700;">{{ strtoupper(mb_substr($portalNameValue, 0, 1)) }}</span>
      <span class="fs-5 fw-semibold">{{ $portalNameValue }}</span>
    </div>

    <div class="card">
      <div class="card-body p-4">
        <h1 class="fs-5 mb-1">Welcome back</h1>
        <p class="text-muted small mb-4">Sign in to your portal.</p>

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
          <div class="mb-3">
            <label class="form-label" for="username">Username</label>
            <input type="text" id="username" name="username" class="form-control form-control-lg fs-6" value="{{ old('username') }}" required autofocus autocomplete="username">
          </div>
          <div class="mb-4">
            <label class="form-label" for="password">Password</label>
            <input type="password" id="password" name="password" class="form-control form-control-lg fs-6" required autocomplete="current-password">
          </div>
          <button type="submit" class="btn btn-primary w-100 btn-lg fs-6">Sign in</button>
        </form>
      </div>
    </div>

    <div class="text-center mt-3">
      <button type="button" class="btn btn-link btn-sm text-muted" data-theme-toggle><i class="bi bi-sun me-1" data-theme-icon></i> Switch theme</button>
    </div>
  </div>
</div>
</body>
</html>
