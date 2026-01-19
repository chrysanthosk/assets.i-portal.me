<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $portalName ?? 'i-portal' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* Chevron down/up behavior for Settings */
        .nav-item .nav-arrow { transition: transform .15s ease; }
        .nav-item.menu-open > a .chev-down { display: none; }
        .nav-item.menu-open > a .chev-up { display: inline-block; }
        .nav-item:not(.menu-open) > a .chev-down { display: inline-block; }
        .nav-item:not(.menu-open) > a .chev-up { display: none; }

        /* Better invalid states spacing */
        .invalid-feedback { display: block; }
    </style>
</head>

<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">

@php
    $portalNameValue = \App\Models\PortalSetting::where('key','portal_name')->value('value') ?? 'i-portal';
    $settingsOpen = request()->is('settings*');
@endphp

<div class="app-wrapper">

    <!-- Navbar -->
    <nav class="app-header navbar navbar-expand bg-body">
        <div class="container-fluid">

            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-lte-toggle="sidebar" href="#">
                        <i class="bi bi-list"></i>
                    </a>
                </li>
            </ul>

            <ul class="navbar-nav ms-auto align-items-center">

                <li class="nav-item me-2">
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleTheme()">
                        <span id="themeLabel">Light</span> Mode
                    </button>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">
                        <i class="bi bi-person-circle"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a href="{{ route('profile.edit') }}" class="dropdown-item">Profile</a>
                        <div class="dropdown-divider"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="dropdown-item text-danger">Logout</button>
                        </form>
                    </div>
                </li>

            </ul>
        </div>
    </nav>

    <!-- Sidebar -->
    <aside class="app-sidebar bg-body-secondary shadow">
        <div class="sidebar-brand">
            <a href="{{ route('dashboard') }}" class="brand-link">
                <span class="brand-text fw-light">{{ $portalNameValue }}</span>
            </a>
        </div>

        <div class="sidebar-wrapper">
            <nav>
                <ul class="nav nav-pills nav-sidebar flex-column" data-lte-toggle="treeview" role="menu">

                    @can('view_dashboard')
                    <li class="nav-item">
                        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <i class="nav-icon bi bi-speedometer2"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>
                    @endcan

                    <!-- SETTINGS (Expandable) -->
                    <li class="nav-item {{ $settingsOpen ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ $settingsOpen ? 'active' : '' }}">
                            <i class="nav-icon bi bi-gear"></i>
                            <p>
                                Settings
                                <span class="nav-arrow float-end">
                                    <i class="bi bi-chevron-down chev-down"></i>
                                    <i class="bi bi-chevron-up chev-up"></i>
                                </span>
                            </p>
                        </a>

                        <ul class="nav nav-treeview">

                            @can('manage_portal_settings')
                            <li class="nav-item">
                                <a href="{{ route('settings.portal.edit') }}" class="nav-link {{ request()->routeIs('settings.portal.*') ? 'active' : '' }}">
                                    <i class="nav-icon bi bi-sliders"></i>
                                    <p>Portal Settings</p>
                                </a>
                            </li>
                            @endcan

                            @can('manage_users')
                            <li class="nav-item">
                                <a href="{{ route('settings.users.index') }}" class="nav-link {{ request()->routeIs('settings.users.*') ? 'active' : '' }}">
                                    <i class="nav-icon bi bi-people"></i>
                                    <p>Users</p>
                                </a>
                            </li>
                            @endcan

                            @can('manage_permission_sets')
                            <li class="nav-item">
                                <a href="{{ route('settings.permissionSets.index') }}" class="nav-link {{ request()->routeIs('settings.permissionSets.*') ? 'active' : '' }}">
                                    <i class="nav-icon bi bi-shield-lock"></i>
                                    <p>Permission Sets</p>
                                </a>
                            </li>
                            @endcan

                            @can('manage_portal_settings')
                            <li class="nav-item">
                              <a href="{{ route('settings.smtp.edit') }}" class="nav-link {{ request()->routeIs('settings.smtp.*') ? 'active' : '' }}">
                                <i class="nav-icon bi bi-envelope-at"></i>
                                <p>SMTP Settings</p>
                              </a>
                            </li>
                            @endcan

                        </ul>
                    </li>

                </ul>
            </nav>
        </div>
    </aside>

    <!-- Main content -->
    <main class="app-main">
        <div class="container-fluid pt-3">

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            @yield('content')
        </div>
    </main>

    <!-- Footer -->
    <footer class="app-footer text-sm">
        <div class="float-end d-none d-sm-inline">
            {{ now()->year }}
        </div>
        <strong>{{ $portalNameValue }}</strong>
    </footer>

</div>

<script>
(function () {
    // Theme toggle
    const html = document.documentElement;
    function applyTheme(theme) {
        html.setAttribute('data-bs-theme', theme);
        localStorage.setItem('theme', theme);
        const label = document.getElementById('themeLabel');
        if (label) label.textContent = theme === 'dark' ? 'Light' : 'Dark';
    }
    applyTheme(localStorage.getItem('theme') || 'dark');
    window.toggleTheme = function () {
        const current = html.getAttribute('data-bs-theme');
        applyTheme(current === 'dark' ? 'light' : 'dark');
    };

    // Password strength meter (works on any page)
    // Usage: add data-password-meter="someId" to your password input.
    // And create:
    //  - <div id="someId-text"></div>
    //  - <div class="progress"><div id="someId-bar"></div></div>
    function strengthToLabel(score) {
        return ['Very weak', 'Weak', 'Fair', 'Good', 'Strong'][score] || 'Very weak';
    }
    function strengthToWidth(score) {
        return [10, 25, 50, 75, 100][score] || 10;
    }

    window.initPasswordMeters = function () {
        const inputs = document.querySelectorAll('input[data-password-meter]');
        inputs.forEach((input) => {
            const id = input.getAttribute('data-password-meter');
            const textEl = document.getElementById(id + '-text');
            const barEl = document.getElementById(id + '-bar');

            if (!textEl || !barEl) return;

            const update = () => {
                const val = input.value || '';
                if (!window.zxcvbn) {
                    textEl.textContent = val.length ? 'Strength meter not loaded' : '';
                    barEl.style.width = val.length ? '25%' : '0%';
                    barEl.className = 'progress-bar';
                    return;
                }

                if (!val.length) {
                    textEl.textContent = '';
                    barEl.style.width = '0%';
                    barEl.className = 'progress-bar';
                    return;
                }

                const r = window.zxcvbn(val);
                const score = r.score; // 0-4
                textEl.textContent = strengthToLabel(score);
                barEl.style.width = strengthToWidth(score) + '%';

                // color classes (no hard-coded colors; using bootstrap contextual classes)
                barEl.className = 'progress-bar ' + (
                    score <= 1 ? 'bg-danger' :
                    score === 2 ? 'bg-warning' :
                    score === 3 ? 'bg-info' : 'bg-success'
                );
            };

            input.addEventListener('input', update);
            update();
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        window.initPasswordMeters();
    });
})();
</script>

</body>
</html>
