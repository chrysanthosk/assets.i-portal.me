<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $portalName ?? 'assets.i-portal.me' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        .invalid-feedback { display: block; }
        .form-control.is-invalid,
        .form-select.is-invalid { border-color: var(--bs-danger); }

        /* Make parent menu items clickable + show pointer */
        .nav-sidebar .nav-link { cursor: pointer; }

        /* Chevron rotation */
        .nav-link .chev {
            transition: transform .15s ease;
            display: inline-block;
        }
        .nav-item.menu-open > .nav-link .chev {
            transform: rotate(180deg); /* down -> up */
        }
    </style>
</head>

<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">

@php
$portalNameValue = \App\Models\PortalSetting::where('key','portal_name')->value('value') ?? 'assets.i-portal.me';

$settingsOpen = request()->is('settings*');
$assetsOpen   = request()->is('assets*');

$canAssets = auth()->check() && (
auth()->user()->can('manage_assets') ||
auth()->user()->can('manage_asset_rentals') ||
auth()->user()->can('manage_asset_tags')
);

$canSettings = auth()->check() && (
auth()->user()->can('manage_portal_settings') ||
auth()->user()->can('manage_users') ||
auth()->user()->can('manage_permission_sets') ||
auth()->user()->can('manage_smtp_settings') ||
auth()->user()->can('manage_asset_types') ||
auth()->user()->can('manage_owner_entities') ||
auth()->user()->can('manage_audit_logs')
);
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
                    <button class="btn btn-sm btn-outline-secondary" type="button" onclick="toggleTheme()">
                        <span id="themeLabel">Light</span> Mode
                    </button>
                </li>

                @if(auth()->check())
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
                @endif
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
                <ul class="nav nav-pills nav-sidebar flex-column"
                    data-lte-toggle="treeview"
                    role="menu"
                    data-accordion="false">

                    @can('view_dashboard')
                    <li class="nav-item">
                        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <i class="nav-icon bi bi-speedometer2"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>
                    @endcan

                    <!-- ASSETS (Expandable) -->
                    @if($canAssets)
                    <li class="nav-item {{ $assetsOpen ? 'menu-open' : '' }}">
                        <a class="nav-link {{ $assetsOpen ? 'active' : '' }}" href="#">
                            <i class="nav-icon bi bi-buildings"></i>
                            <p>
                                Assets
                                <i class="bi bi-chevron-down float-end chev"></i>
                            </p>
                        </a>

                        <ul class="nav nav-treeview">

                            @can('manage_assets')
                            <li class="nav-item">
                                <a href="{{ route('assets.index') }}"
                                   class="nav-link {{ request()->routeIs('assets.index') || request()->routeIs('assets.create') || request()->routeIs('assets.edit') || request()->routeIs('assets.show') ? 'active' : '' }}">
                                    <i class="nav-icon bi bi-list-ul"></i>
                                    <p>Assets List</p>
                                </a>
                            </li>
                            @endcan

                            @can('manage_asset_rentals')
                            <li class="nav-item">
                                <a href="{{ route('assets.rentals.index') }}"
                                   class="nav-link {{ request()->routeIs('assets.rentals.*') ? 'active' : '' }}">
                                    <i class="nav-icon bi bi-cash-coin"></i>
                                    <p>Rental Income</p>
                                </a>
                            </li>
                            @endcan

                            @can('manage_asset_tags')
                            <li class="nav-item">
                                <a href="{{ route('assets.tags.index') }}"
                                   class="nav-link {{ request()->routeIs('assets.tags.*') ? 'active' : '' }}">
                                    <i class="nav-icon bi bi-tags"></i>
                                    <p>Tags</p>
                                </a>
                            </li>
                            @endcan

                        </ul>
                    </li>
                    @endif

                    <!-- SETTINGS (Expandable) - only show if user has any settings permission -->
                    @if($canSettings)
                    <li class="nav-item {{ $settingsOpen ? 'menu-open' : '' }}">
                        <a class="nav-link {{ $settingsOpen ? 'active' : '' }}" href="#">
                            <i class="nav-icon bi bi-gear"></i>
                            <p>
                                Settings
                                <i class="bi bi-chevron-down float-end chev"></i>
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

                            @can('manage_smtp_settings')
                            <li class="nav-item">
                                <a href="{{ route('settings.smtp.edit') }}" class="nav-link {{ request()->routeIs('settings.smtp.*') ? 'active' : '' }}">
                                    <i class="nav-icon bi bi-envelope-at"></i>
                                    <p>SMTP Settings</p>
                                </a>
                            </li>
                            @endcan

                            @can('manage_asset_types')
                            <li class="nav-item">
                                <a href="{{ route('settings.assetTypes.index') }}" class="nav-link {{ request()->routeIs('settings.assetTypes.*') ? 'active' : '' }}">
                                    <i class="nav-icon bi bi-ui-checks"></i>
                                    <p>Asset Types</p>
                                </a>
                            </li>
                            @endcan

                            @can('manage_owner_entities')
                            <li class="nav-item">
                                <a href="{{ route('settings.ownerEntities.index') }}" class="nav-link {{ request()->routeIs('settings.ownerEntities.*') ? 'active' : '' }}">
                                    <i class="nav-icon bi bi-building"></i>
                                    <p>Owner Entities</p>
                                </a>
                            </li>
                            @endcan

                            @can('manage_audit_logs')
                            <li class="nav-item">
                                <a href="{{ route('audit.index') }}"
                                   class="nav-link {{ request()->routeIs('audit.*') ? 'active' : '' }}">
                                    <i class="nav-icon bi bi-clipboard-data"></i>
                                    <p>Audit Logs</p>
                                </a>
                            </li>
                            @endcan

                        </ul>
                    </li>
                    @endif

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

            @if ($errors->any())
            <div class="alert alert-danger">
                <div class="fw-semibold mb-1">Please fix the errors below</div>
                <ul class="mb-0">
                    @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
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

                    if (!val.length) {
                        textEl.textContent = '';
                        barEl.style.width = '0%';
                        barEl.className = 'progress-bar';
                        return;
                    }

                    if (!window.zxcvbn) {
                        textEl.textContent = 'Strength meter not loaded';
                        barEl.style.width = '25%';
                        barEl.className = 'progress-bar bg-warning';
                        return;
                    }

                    const r = window.zxcvbn(val);
                    const score = r.score;
                    textEl.textContent = strengthToLabel(score);
                    barEl.style.width = strengthToWidth(score) + '%';
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
