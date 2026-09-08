<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>try { document.documentElement.setAttribute('data-bs-theme', localStorage.getItem('theme') || 'dark'); } catch (e) {}</script>
    @php $portalNameValue = \App\Models\PortalSetting::name(); @endphp
    <title>{{ $title ?? $portalNameValue }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="auth-page">
    <div class="auth-card">
        <div class="d-flex align-items-center justify-content-center gap-2 mb-4">
            <span style="width:36px;height:36px;border-radius:9px;display:grid;place-items:center;background:linear-gradient(135deg,var(--brand),#22d3ee);color:#fff;font-weight:700;">{{ strtoupper(mb_substr($portalNameValue, 0, 1)) }}</span>
            <span class="fs-5 fw-semibold">{{ $portalNameValue }}</span>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')

        <div class="text-center mt-3">
            <button type="button" class="btn btn-link btn-sm text-muted" data-theme-toggle><i class="bi bi-sun me-1" data-theme-icon></i> Switch theme</button>
        </div>
    </div>
</div>
</body>
</html>
