<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#10243d">
    <title>Employee Portal | ATTENDSYNC</title>
    <link rel="icon" type="image/png" href="{{ asset('images/attendsync.png') }}?v=20261003">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=admin-redesign-7">
</head>
<body class="employee-portal-page">
    <main class="employee-portal">
        <header class="employee-portal-header">
            <a class="employee-portal-brand" href="{{ route('employee.dashboard') }}" aria-label="ATTENDSYNC Employee Portal">
                <span class="employee-portal-logo">
                    <img src="{{ asset('images/attendsync.png') }}?v=20261003-circle" alt="">
                </span>
                <span class="employee-portal-brand-copy">
                    <strong>ATTENDSYNC</strong>
                    <span>Employee Portal</span>
                </span>
            </a>
            <span class="employee-portal-school">Bansud Central School</span>
        </header>

        <section class="employee-portal-card" aria-labelledby="employee-portal-title">
            <span class="employee-portal-eyebrow">EMPLOYEE WORKSPACE</span>
            <div class="employee-portal-avatar" aria-hidden="true">
                {{ strtoupper(substr(auth()->user()->name ?? 'E', 0, 1)) }}
            </div>
            <h1 id="employee-portal-title">Welcome, {{ auth()->user()->name }}!</h1>
            <p>You are signed in to the ATTENDSYNC employee portal.</p>
            <div class="employee-portal-status">
                <x-lucide-shield-check aria-hidden="true" />
                <span>Secure session active</span>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="employee-portal-logout">
                    <x-lucide-log-out aria-hidden="true" />
                    <span>Sign out</span>
                </button>
            </form>
        </section>

        <footer class="employee-portal-footer">ATTENDSYNC · Bansud Central School</footer>
    </main>
</body>
</html>