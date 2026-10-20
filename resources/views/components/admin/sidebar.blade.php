<aside class="sidebar" id="admin-sidebar" aria-label="Main navigation">
    <div class="sidebar-header-row">
        <a class="sidebar-brand" href="{{ route('admin.dashboard') }}" title="ATTENDSYNC Admin Portal">
            <span class="brand-mark" aria-hidden="true">
                <img src="{{ asset('images/attendsync.png') }}?v=20261003-circle" alt="ATTENDSYNC Logo">
            </span>
            <span class="brand-copy">
                <span class="brand">ATTENDSYNC</span>
                <span class="brand-subtitle">Attendance System</span>
            </span>
        </a>
        <button type="button" class="sidebar-close-btn" data-sidebar-close aria-label="Close navigation menu">
            <x-lucide-x class="w-5 h-5" aria-hidden="true" />
        </button>
    </div>

    <div class="sidebar-school">
        <span class="sidebar-school-label">WORKSPACE</span>
        <strong>Bansud Central School</strong>
        <span>Administrator Portal</span>
    </div>

    <nav class="sidebar-nav">
        <p class="menu-title">Overview</p>
        <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" title="Dashboard">
            <x-lucide-layout-dashboard class="nav-icon" aria-hidden="true" />
            <span>Dashboard</span>
        </a>

        <p class="menu-title">People &amp; Attendance</p>
        <a href="{{ route('admin.employees.index') }}" class="nav-link {{ request()->routeIs('admin.employees.*') ? 'active' : '' }}" title="Employees">
            <x-lucide-users class="nav-icon" aria-hidden="true" />
            <span>Employees</span>
        </a>
        <a href="{{ route('admin.departments.index') }}" class="nav-link {{ request()->routeIs('admin.departments.*') ? 'active' : '' }}" title="Departments">
            <x-lucide-building-2 class="nav-icon" aria-hidden="true" />
            <span>Departments</span>
        </a>
        <a href="{{ route('admin.positions.index') }}" class="nav-link {{ request()->routeIs('admin.positions.*') ? 'active' : '' }}" title="Positions">
            <x-lucide-briefcase class="nav-icon" aria-hidden="true" />
            <span>Positions</span>
        </a>
        <a href="{{ route('admin.schedules.index') }}" class="nav-link {{ request()->routeIs('admin.schedules.*') ? 'active' : '' }}" title="Schedules">
            <x-lucide-calendar-clock class="nav-icon" aria-hidden="true" />
            <span>Schedules</span>
        </a>
        <a href="{{ route('admin.devices.index') }}" class="nav-link {{ request()->routeIs('admin.devices.*') ? 'active' : '' }}" title="Devices">
            <x-lucide-smartphone class="nav-icon" aria-hidden="true" />
            <span>Devices</span>
        </a>
        <a href="{{ route('admin.attendance.index') }}" class="nav-link {{ request()->routeIs('admin.attendance.*') ? 'active' : '' }}" title="Attendance">
            <x-lucide-clipboard-check class="nav-icon" aria-hidden="true" />
            <span>Attendance</span>
        </a>
        <a href="{{ route('admin.attendance-correction-requests.index') }}" class="nav-link {{ request()->routeIs('admin.attendance-correction-requests.*') ? 'active' : '' }}" title="Attendance Requests">
            <x-lucide-file-pen-line class="nav-icon" aria-hidden="true" />
            <span>Attendance Requests</span>
        </a>
        <a href="{{ route('admin.reports.index') }}" class="nav-link {{ request()->routeIs('admin.reports.index') ? 'active' : '' }}" title="Reports">
            <x-lucide-chart-column class="nav-icon" aria-hidden="true" />
            <span>Reports</span>
        </a>
        <a href="{{ route('admin.reports.dtr') }}" class="nav-link {{ request()->routeIs('admin.reports.dtr*') ? 'active' : '' }}" title="CSC Form 48 (DTR)">
            <x-lucide-file-spreadsheet class="nav-icon" aria-hidden="true" />
            <span>CSC Form 48 (DTR)</span>
        </a>

        <p class="menu-title">Administration</p>
        <a href="{{ route('admin.settings.index') }}" class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" title="Settings">
            <x-lucide-settings class="nav-icon" aria-hidden="true" />
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
            <button type="submit" class="logout-button" title="Sign out of administrative session">
                <x-lucide-log-out class="nav-icon logout-icon" />
                <span>Sign out</span>
            </button>
        </form>
    </div>
</aside>
