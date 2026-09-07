<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="dark light">
    <script>
        // Apply the saved theme before first paint to avoid a flash
        try { document.documentElement.setAttribute('data-bs-theme', localStorage.getItem('theme') || 'dark'); } catch (e) {}
    </script>

    @php
        $portalNameValue = \App\Models\PortalSetting::where('key', 'portal_name')->value('value') ?? 'assets.i-portal.me';
        $routeName = optional(request()->route())->getName() ?? '';
        $titleMap = [
            'dashboard' => 'Dashboard',
            'assets.import' => 'Import title deed',
            'assets.rentals' => 'Agreements',
            'assets.tags' => 'Tags',
            'assets.create' => 'Add property',
            'assets.edit' => 'Edit property',
            'assets' => 'Properties',
            'tenants' => 'Tenants',
            'payments.unconfirmed' => 'Rent check',
            'payments' => 'Payments',
            'expenses' => 'Expenses',
            'reports' => 'Reports',
            'settings.portal' => 'Portal settings',
            'settings.smtp' => 'Email (SMTP)',
            'settings.users' => 'Users',
            'settings.permissionSets' => 'Permission sets',
            'settings.assetTypes' => 'Property types',
            'settings.ownerEntities' => 'Owner entities',
            'settings.currencies' => 'Currencies & FX',
            'audit' => 'Audit log',
            'profile' => 'Profile',
            '2fa' => 'Two-factor authentication',
        ];
        $pageTitle = trim(View::getSection('title') ?? '');
        if ($pageTitle === '') {
            foreach ($titleMap as $prefix => $label) {
                if ($routeName === $prefix || str_starts_with($routeName, $prefix.'.')) { $pageTitle = $label; break; }
            }
        }
        $pageTitle = $pageTitle ?: $portalNameValue;

        $user = auth()->user();
        $initials = $user ? strtoupper(mb_substr($user->name ?? $user->username ?? 'U', 0, 1).mb_substr($user->surname ?? '', 0, 1)) : '';
        $displayName = $user ? trim(($user->name ?? '').' '.($user->surname ?? '')) ?: $user->username : '';

        $settingsOpen = request()->is('settings*') || request()->is('assets/tags*') || request()->is('audit*');
        $canSettings = $user && (
            $user->can('manage_portal_settings') || $user->can('manage_users') || $user->can('manage_permission_sets') ||
            $user->can('manage_smtp_settings') || $user->can('manage_asset_types') || $user->can('manage_owner_entities') ||
            $user->can('manage_fx_rates') || $user->can('manage_audit_logs') || $user->can('manage_asset_tags')
        );

        // Attention counters for the bell + sidebar badge
        $unconfirmedRent = 0; $overdueRent = 0; $expiringDocs = 0;
        if ($user && $user->can('manage_rental_payments')) {
            $unconfirmedRent = \App\Models\RentalPayment::query()->awaitingConfirmation()->count();
            $overdueRent = \App\Models\RentalPayment::query()->overdue()->count();
        }
        if ($user && $user->can('manage_assets')) {
            $expiringDocs = \App\Models\AssetDocument::query()->whereNotNull('expires_at')
                ->whereDate('expires_at', '<=', now()->addDays(30)->toDateString())->count();
        }
        $attention = $unconfirmedRent + $overdueRent + $expiringDocs;
    @endphp

    <title>{{ $pageTitle }} · {{ $portalNameValue }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>
<div class="shell">

    {{-- ================= Sidebar ================= --}}
    <div class="sidebar-backdrop" data-sidebar-close></div>
    <aside class="sidebar" id="sidebar" aria-label="Main navigation">
        <div class="sidebar-brand">
            <a href="{{ route('dashboard') }}">
                <span class="brand-mark">{{ strtoupper(mb_substr($portalNameValue, 0, 1)) }}</span>
                <span class="brand-name">{{ $portalNameValue }}</span>
            </a>
            <button class="icon-btn ms-auto d-lg-none" type="button" data-sidebar-close aria-label="Close menu"><i class="bi bi-x-lg"></i></button>
        </div>

        <nav class="sidebar-nav">
            @can('view_dashboard')
                <a href="{{ route('dashboard') }}" class="sb-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="bi bi-grid-1x2"></i><span class="sb-label">Dashboard</span>
                </a>
            @endcan

            @if($user && ($user->can('manage_assets') || $user->can('manage_tenants')))
                <div class="sb-header">Portfolio</div>
                @can('manage_assets')
                    <a href="{{ route('assets.index') }}" class="sb-link {{ request()->routeIs('assets.index', 'assets.create', 'assets.edit', 'assets.show', 'assets.import.*') ? 'active' : '' }}">
                        <i class="bi bi-buildings"></i><span class="sb-label">Properties</span>
                    </a>
                @endcan
                @can('manage_tenants')
                    <a href="{{ route('tenants.index') }}" class="sb-link {{ request()->routeIs('tenants.*') ? 'active' : '' }}">
                        <i class="bi bi-people"></i><span class="sb-label">Tenants</span>
                    </a>
                @endcan
            @endif

            @if($user && ($user->can('manage_asset_rentals') || $user->can('manage_rental_payments')))
                <div class="sb-header">Rent</div>
                @can('manage_rental_payments')
                    <a href="{{ route('payments.unconfirmed') }}" class="sb-link {{ request()->routeIs('payments.unconfirmed') ? 'active' : '' }}">
                        <i class="bi bi-question-circle"></i><span class="sb-label">Rent check</span>
                        @if($unconfirmedRent)<span class="sb-badge">{{ $unconfirmedRent }}</span>@endif
                    </a>
                    <a href="{{ route('payments.index') }}" class="sb-link {{ request()->routeIs('payments.index') ? 'active' : '' }}">
                        <i class="bi bi-wallet2"></i><span class="sb-label">Payments</span>
                    </a>
                @endcan
                @can('manage_asset_rentals')
                    <a href="{{ route('assets.rentals.index') }}" class="sb-link {{ request()->routeIs('assets.rentals.*') ? 'active' : '' }}">
                        <i class="bi bi-file-earmark-text"></i><span class="sb-label">Agreements</span>
                    </a>
                @endcan
            @endif

            @if($user && ($user->can('manage_asset_expenses') || $user->can('view_reports')))
                <div class="sb-header">Finance</div>
                @can('manage_asset_expenses')
                    <a href="{{ route('expenses.index') }}" class="sb-link {{ request()->routeIs('expenses.*') ? 'active' : '' }}">
                        <i class="bi bi-receipt"></i><span class="sb-label">Expenses</span>
                    </a>
                @endcan
                @can('view_reports')
                    <a href="{{ route('reports.index') }}" class="sb-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                        <i class="bi bi-graph-up"></i><span class="sb-label">Reports</span>
                    </a>
                @endcan
            @endif

            @if($canSettings)
                <div class="sb-header">System</div>
                <button class="sb-link {{ $settingsOpen ? 'active' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#sbSettings"
                        aria-expanded="{{ $settingsOpen ? 'true' : 'false' }}" aria-controls="sbSettings">
                    <i class="bi bi-gear"></i><span class="sb-label">Settings</span><i class="bi bi-chevron-down sb-chevron"></i>
                </button>
                <ul class="sb-sub collapse {{ $settingsOpen ? 'show' : '' }}" id="sbSettings">
                    @can('manage_portal_settings')
                        <li><a href="{{ route('settings.portal.edit') }}" class="sb-link {{ request()->routeIs('settings.portal.*') ? 'active' : '' }}"><i class="bi bi-sliders"></i><span class="sb-label">Portal &amp; reminders</span></a></li>
                    @endcan
                    @can('manage_smtp_settings')
                        <li><a href="{{ route('settings.smtp.edit') }}" class="sb-link {{ request()->routeIs('settings.smtp.*') ? 'active' : '' }}"><i class="bi bi-envelope-at"></i><span class="sb-label">Email (SMTP)</span></a></li>
                    @endcan
                    @can('manage_asset_types')
                        <li><a href="{{ route('settings.assetTypes.index') }}" class="sb-link {{ request()->routeIs('settings.assetTypes.*') ? 'active' : '' }}"><i class="bi bi-ui-checks"></i><span class="sb-label">Property types</span></a></li>
                    @endcan
                    @advanced
                        @can('manage_fx_rates')
                            <li><a href="{{ route('settings.currencies.edit') }}" class="sb-link {{ request()->routeIs('settings.currencies.*') ? 'active' : '' }}"><i class="bi bi-currency-exchange"></i><span class="sb-label">Currencies &amp; FX</span></a></li>
                        @endcan
                        @can('manage_owner_entities')
                            <li><a href="{{ route('settings.ownerEntities.index') }}" class="sb-link {{ request()->routeIs('settings.ownerEntities.*') ? 'active' : '' }}"><i class="bi bi-building"></i><span class="sb-label">Owner entities</span></a></li>
                        @endcan
                        @can('manage_asset_tags')
                            <li><a href="{{ route('assets.tags.index') }}" class="sb-link {{ request()->routeIs('assets.tags.*') ? 'active' : '' }}"><i class="bi bi-tags"></i><span class="sb-label">Tags</span></a></li>
                        @endcan
                        @can('manage_users')
                            <li><a href="{{ route('settings.users.index') }}" class="sb-link {{ request()->routeIs('settings.users.*') ? 'active' : '' }}"><i class="bi bi-person-gear"></i><span class="sb-label">Users</span></a></li>
                        @endcan
                        @can('manage_permission_sets')
                            <li><a href="{{ route('settings.permissionSets.index') }}" class="sb-link {{ request()->routeIs('settings.permissionSets.*') ? 'active' : '' }}"><i class="bi bi-shield-lock"></i><span class="sb-label">Permission sets</span></a></li>
                        @endcan
                        @can('manage_audit_logs')
                            <li><a href="{{ route('audit.index') }}" class="sb-link {{ request()->routeIs('audit.*') ? 'active' : '' }}"><i class="bi bi-clipboard-data"></i><span class="sb-label">Audit log</span></a></li>
                        @endcan
                    @endadvanced
                </ul>
            @endif
        </nav>

        <div class="sidebar-foot">
            @auth
                <a href="{{ route('profile.edit') }}" class="sb-link {{ request()->routeIs('profile.*') ? 'active' : '' }}">
                    <i class="bi bi-person-circle"></i><span class="sb-label">Profile</span>
                </a>
            @endauth
            <button class="sb-link sb-collapse d-none d-lg-flex" type="button" data-sidebar-mini title="Collapse sidebar">
                <i class="bi bi-chevron-left"></i><span class="sb-label sb-collapse-label">Collapse</span>
            </button>
        </div>
    </aside>

    {{-- ================= Main ================= --}}
    <div class="shell-main">
        <header class="topbar">
            <button class="icon-btn d-lg-none" type="button" data-sidebar-toggle aria-label="Open menu"><i class="bi bi-list fs-5"></i></button>
            <h1 class="page-title">{{ $pageTitle }}</h1>

            <div class="ms-auto d-flex align-items-center gap-1">
                @auth
                    <div class="dropdown">
                        <button class="icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications" title="Needs attention">
                            <i class="bi bi-bell"></i>
                            @if($attention)<span class="dot"></span>@endif
                        </button>
                        <div class="dropdown-menu dropdown-menu-end" style="min-width: 260px;">
                            <h6 class="dropdown-header">Needs attention</h6>
                            @if(! $attention)
                                <div class="dropdown-item-text text-muted small">All clear 🎉</div>
                            @endif
                            @if($unconfirmedRent)
                                <a class="dropdown-item d-flex justify-content-between" href="{{ route('payments.unconfirmed') }}">
                                    <span><i class="bi bi-question-circle me-2 text-warning"></i>Rent awaiting your answer</span><span class="badge text-bg-warning">{{ $unconfirmedRent }}</span>
                                </a>
                            @endif
                            @if($overdueRent)
                                <a class="dropdown-item d-flex justify-content-between" href="{{ route('payments.index', ['status' => 'overdue']) }}">
                                    <span><i class="bi bi-exclamation-circle me-2 text-danger"></i>Overdue rent</span><span class="badge text-bg-danger">{{ $overdueRent }}</span>
                                </a>
                            @endif
                            @if($expiringDocs)
                                <a class="dropdown-item d-flex justify-content-between" href="{{ route('assets.index') }}">
                                    <span><i class="bi bi-file-earmark-x me-2 text-warning"></i>Documents expiring ≤30d</span><span class="badge text-bg-warning">{{ $expiringDocs }}</span>
                                </a>
                            @endif
                        </div>
                    </div>
                @endauth

                <button class="icon-btn" type="button" data-theme-toggle aria-label="Toggle theme"><i class="bi bi-sun" data-theme-icon></i></button>

                @auth
                    <div class="dropdown">
                        <button class="user-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="avatar">{{ $initials }}</span>
                            <span class="d-none d-sm-inline small fw-medium">{{ $displayName }}</span>
                            <i class="bi bi-chevron-down small text-muted d-none d-sm-inline"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <div class="dropdown-item-text small text-muted">{{ $user->email }}</div>
                            <div class="dropdown-divider"></div>
                            <a href="{{ route('profile.edit') }}" class="dropdown-item"><i class="bi bi-person me-2"></i>Profile</a>
                            <div class="dropdown-divider"></div>
                            <form method="POST" action="{{ route('logout') }}" data-no-loading>
                                @csrf
                                <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Logout</button>
                            </form>
                        </div>
                    </div>
                @endauth
            </div>
        </header>

        <main class="content">
            @if (session('success'))
                <div class="alert alert-success d-flex align-items-center gap-2"><i class="bi bi-check-circle"></i><div>{{ session('success') }}</div></div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger d-flex align-items-center gap-2"><i class="bi bi-exclamation-triangle"></i><div>{{ session('error') }}</div></div>
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
        </main>

        <footer class="shell-footer d-flex justify-content-between flex-wrap gap-2">
            <span><i class="bi bi-info-circle me-1"></i>{{ $portalNameValue }}</span>
            <span>{{ now()->year }}</span>
        </footer>
    </div>
</div>
</body>
</html>
