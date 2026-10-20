<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#10243d">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'ATTENDSYNC Admin')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="{{ asset('images/attendsync.png') }}?v=20261003">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=20261003-circle">
</head>

<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <div class="dashboard-wrapper">

        @include('components.admin.sidebar')
        <button type="button" class="sidebar-overlay" data-sidebar-overlay aria-label="Close navigation menu" tabindex="-1"></button>

        <main class="main-content" id="main-content">
            @include('components.admin.topbar')

            <div class="page-content">
                @yield('content')
            </div>
        </main>

    </div>

    @include('components.admin.custom-modal')

    <script src="{{ asset('js/admin.js') }}" defer></script>
</body>
</html>