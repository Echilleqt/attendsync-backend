<header class="topbar">
    <button
        type="button"
        class="sidebar-toggle"
        data-sidebar-toggle
        aria-label="Open navigation menu"
        aria-expanded="false"
        aria-controls="admin-sidebar"
    >
        <x-lucide-menu class="toggle-open-icon" aria-hidden="true" />
        <x-lucide-x class="toggle-close-icon" aria-hidden="true" />
    </button>
    <div class="topbar-heading">
        <span class="topbar-context">ATTENDANCE MANAGEMENT / ADMIN</span>
        <h1 class="page-title">
            @yield('page_title', 'Admin Dashboard')
        </h1>
        <p class="page-description">
            @yield('page_description', 'Manage employees and monitor attendance records.')
        </p>
    </div>

    <div class="topbar-meta">
        <div class="topbar-date">
            <x-lucide-calendar class="topbar-date-icon" aria-hidden="true" />
            <div>
                <span class="topbar-date-label">Today</span>
                <strong>{{ now()->timezone(config('app.timezone'))->format('D, M j, Y') }}</strong>
            </div>
        </div>
    </div>
</header>