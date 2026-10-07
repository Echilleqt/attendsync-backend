<aside class="sidebar" id="admin-sidebar" aria-label="Main navigation">
    <a class="sidebar-brand" href="{{ route('admin.dashboard') }}">
        <span class="brand-mark" aria-hidden="true">
            <img src="{{ asset('images/attendsync.png') }}?v=20261003-circle" alt="">
        </span>
        <span class="brand-copy">
            <span class="brand">ATTENDSYNC</span>
            <span class="brand-subtitle">Attendance management</span>
        </span>
    </a>

    <div class="sidebar-school">
        <span class="sidebar-school-label">WORKSPACE</span>
        <strong>Bansud Central School</strong>
        <span>Administrator portal</span>
    </div>

    <nav class="sidebar-nav">
        <p class="menu-title">Overview</p>
        <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/></svg></span>
            <span>Dashboard</span>
        </a>

        <p class="menu-title">People &amp; attendance</p>
        <a href="{{ route('admin.employees.index') }}" class="nav-link {{ request()->routeIs('admin.employees.*') ? 'active' : '' }}">
            <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 19a5.5 5.5 0 0 1 11 0M16 5.5a3 3 0 0 1 0 5.8M17 14a4.8 4.8 0 0 1 3.5 4.6"/></svg></span>
            <span>Employees</span>
        </a>
        <a href="{{ route('admin.attendance.index') }}" class="nav-link {{ request()->routeIs('admin.attendance.*') ? 'active' : '' }}">
            <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3.2 2"/></svg></span>
            <span>Attendance</span>
        </a>
        <a href="{{ route('admin.attendance-correction-requests.index') }}" class="nav-link {{ request()->routeIs('admin.attendance-correction-requests.*') ? 'active' : '' }}">
            <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M7 3.8h7l4 4V20H7a2 2 0 0 1-2-2V5.8a2 2 0 0 1 2-2Z"/><path d="M14 4v4h4M9 12h6M9 15.5h6"/></svg></span>
            <span>Attendance requests</span>
        </a>
        <a href="{{ route('admin.reports.index') }}" class="nav-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">
            <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M4 19.5V13M10 19.5V5M16 19.5V9M22 19.5V3"/></svg></span>
            <span>Reports</span>
        </a>

        <p class="menu-title">Administration</p>
        <a href="{{ route('admin.settings.index') }}" class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
            <span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="12" r="3"/><path d="m19.3 13.7 1.1.9-1.5 2.6-1.4-.5a7.6 7.6 0 0 1-1.6.9l-.3 1.5h-3l-.3-1.5a7.6 7.6 0 0 1-1.6-.9l-1.4.5-1.5-2.6 1.1-.9a7.2 7.2 0 0 1 0-1.8l-1.1-.9 1.5-2.6 1.4.5a7.6 7.6 0 0 1 1.6-.9l.3-1.5h3l.3 1.5a7.6 7.6 0 0 1 1.6.9l1.4-.5 1.5 2.6-1.1.9a7.2 7.2 0 0 1 0 1.8Z"/></svg></span>
            <span>Settings</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <span class="profile-icon">{{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}</span>
            <span class="sidebar-user-copy">
                <strong>{{ auth()->user()->name ?? 'Administrator' }}</strong>
                <span>Administrator</span>
            </span>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="logout-button">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M10 5H5v14h5M14 8l4 4-4 4M8 12h10"/></svg>
                <span>Sign out</span>
            </button>
        </form>
    </div>
</aside>
