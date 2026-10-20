<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | ATTENDSYNC</title>
    <link rel="icon" type="image/png" href="{{ asset('images/attendsync.png') }}?v=20261003">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <style>
        :root {
            --navy: #14264F;
            --navy-light: #1b356d;
            --primary-blue: #2457D6;
            --primary-blue-dark: #1b44ab;
            --page-bg: #F3F6FB;
            --text-dark: #0F172A;
            --text-muted: #64748B;
            --border: #E2E8F0;
            --white: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            background: var(--page-bg);
            color: var(--text-dark);
        }

        .login-wrapper {
            min-height: 100vh;
            display: flex;
        }

        /* =========================================
           LEFT BRANDING SIDE
        ========================================= */

        .login-brand-side {
            position: relative;
            width: 52%;
            min-height: 100vh;
            padding: 42px 60px;

            display: flex;
            flex-direction: column;
            justify-content: space-between;

            overflow: hidden;
            background: var(--navy);
            color: var(--white);
        }

        .login-brand-side::before {
            content: "";
            position: absolute;
            width: 430px;
            height: 430px;
            border-radius: 50%;
            background: rgba(49, 91, 255, 0.12);
            top: -190px;
            right: -160px;
        }

        .login-brand-side::after {
            content: "";
            position: absolute;
            width: 360px;
            height: 360px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.035);
            bottom: -190px;
            left: -150px;
        }

        .brand-logo {
            position: relative;
            z-index: 2;

            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        /* =========================================
           ATTENDSYNC LOGO
        ========================================= */

        .brand-icon {
            position: relative;
            width: 68px;
            height: 68px;
            flex-shrink: 0;
            display: inline-block;
            overflow: hidden;
            border-radius: 50%;
            background: #ffffff;
        }

        .brand-icon img {
            position: absolute;
            top: -19px;
            left: -31px;
            width: 131px;
            height: 131px;
            max-width: none;
            object-fit: contain;
        }

        .brand-wordmark {
            color: #ffffff;
            font-family: 'Poppins', sans-serif;
            font-size: 25px;
            font-weight: 800;
            letter-spacing: 0.8px;
        }

        /* =========================================
           LEFT CONTENT
        ========================================= */

        .brand-content {
            position: relative;
            z-index: 2;
            max-width: 570px;
            margin: auto 0;
        }

        .brand-content h1 {
            margin-bottom: 22px;

            font-family: 'Poppins', sans-serif;
            font-size: clamp(35px, 4vw, 58px);
            line-height: 1.15;
            font-weight: 800;
        }

        .brand-content h1 span {
            color: #8fa8ff;
        }

        .brand-content p {
            max-width: 500px;

            color: #c5d0e5;
            font-size: 15px;
            line-height: 1.9;
        }

        .brand-features {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;

            margin-top: 34px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 8px;

            color: #e7edfa;
            font-size: 12px;
            font-weight: 600;
        }

        .feature-check {
            width: 22px;
            height: 22px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 50%;
            background: rgba(49, 91, 255, 0.25);
            color: #8fa8ff;
        }

        .brand-footer {
            position: relative;
            z-index: 2;

            color: #8796b4;
            font-size: 11px;
        }

        /* =========================================
           RIGHT LOGIN SIDE
        ========================================= */

        .login-form-side {
            width: 48%;
            min-height: 100vh;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 45px 55px;
            background: #ffffff;
        }

        .login-card {
            width: 100%;
            max-width: 430px;
        }

        .mobile-brand {
            display: none;
        }

        .form-heading {
            margin-bottom: 32px;
        }

        .form-heading h2 {
            margin-bottom: 10px;

            font-family: 'Poppins', sans-serif;
            font-size: 32px;
            font-weight: 800;
            color: var(--text-dark);
        }

        .form-heading p {
            color: var(--text-muted);
            font-size: 13px;
            line-height: 1.7;
        }

        .alert-error {
            padding: 13px 15px;
            margin-bottom: 22px;

            border: 1px solid #fecaca;
            border-radius: 10px;

            background: #fff1f2;
            color: #b91c1c;

            font-size: 13px;
            line-height: 1.6;
        }

        .form-group {
            margin-bottom: 21px;
        }

        .form-group label {
            display: block;
            margin-bottom: 9px;

            color: #34435d;
            font-size: 12px;
            font-weight: 700;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            top: 50%;
            left: 16px;

            transform: translateY(-50%);

            color: #9aa8bc;
            font-size: 16px;
            pointer-events: none;
        }

        .form-control {
            width: 100%;
            height: 53px;

            padding: 0 17px 0 47px;

            border: 1px solid var(--border);
            border-radius: 11px;
            outline: none;

            background: #f9fbfe;
            color: var(--text-dark);

            font-family: 'Inter', sans-serif;
            font-size: 13px;

            transition: 0.25s ease;
        }

        .form-control:focus {
            border-color: var(--primary-blue);
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(49, 91, 255, 0.10);
        }

        .password-input {
            padding-right: 48px;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 16px;

            transform: translateY(-50%);

            border: none;
            background: transparent;
            color: #8c98aa;

            cursor: pointer;
            font-size: 16px;
        }

        .form-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;

            margin: 5px 0 25px;
        }

        .remember-option {
            display: flex;
            align-items: center;
            gap: 8px;

            color: #788397;
            font-size: 12px;
        }

        .remember-option input {
            accent-color: var(--primary-blue);
        }

        .forgot-link {
            color: var(--primary-blue);
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
        }

        .forgot-link:hover {
            text-decoration: underline;
        }

        .login-button {
            width: 100%;
            height: 54px;

            border: none;
            border-radius: 10px;

            background: var(--primary-blue);
            color: #ffffff;

            font-family: 'Inter', sans-serif;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.3px;

            cursor: pointer;

            box-shadow: 0 8px 18px rgba(49, 91, 255, 0.20);
            transition: 0.25s ease;
        }

        .login-button:hover {
            background: var(--primary-blue-dark);
            transform: translateY(-2px);
            box-shadow: 0 12px 22px rgba(49, 91, 255, 0.28);
        }

        .login-button:active {
            transform: translateY(0);
        }

        .security-note {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;

            margin-top: 22px;

            color: #8d98a8;
            font-size: 11px;
            text-align: center;
        }

        .form-bottom {
            margin-top: 27px;

            color: #9aa3b1;
            font-size: 11px;
            line-height: 1.7;
            text-align: center;
        }

        .form-bottom strong {
            color: #526174;
        }

        /* =========================================
           TABLET
        ========================================= */

        @media (max-width: 1050px) {
            .login-brand-side {
                width: 48%;
                padding: 40px 35px;
            }

            .login-form-side {
                width: 52%;
                padding: 35px 30px;
            }

            .brand-content h1 {
                font-size: 39px;
            }

            .brand-content p {
                font-size: 14px;
            }

        }

        /* =========================================
           MOBILE
        ========================================= */

        @media (max-width: 700px) {
            .login-wrapper {
                display: block;
            }

            .login-brand-side {
                display: none;
            }

            .login-form-side {
                width: 100%;
                min-height: 100vh;

                align-items: flex-start;
                padding: 35px 22px;

                background: var(--navy);
            }

            .login-card {
                max-width: 480px;
                margin: auto;
                padding: 28px 22px;

                border-radius: 16px;
                background: #ffffff;
            }

            .mobile-brand {
                display: flex;
                align-items: center;
                justify-content: center;
                flex-wrap: wrap;
                gap: 13px;

                margin-bottom: 24px;
                text-align: center;
            }

            .mobile-brand .brand-icon {
                width: 56px;
                height: 56px;
            }

            .mobile-brand .brand-icon img {
                top: -16px;
                left: -26px;
                width: 108px;
                height: 108px;
            }

            .mobile-brand .brand-wordmark {
                font-size: 23px;
            }

            .form-heading h2 {
                font-size: 28px;
            }

            .form-heading p {
                font-size: 12px;
            }
        }

        @media (max-width: 380px) {
            .login-form-side {
                padding: 28px 16px;
            }

            .login-card {
                padding: 25px 18px;
            }

            .form-heading h2 {
                font-size: 25px;
            }

            .form-options {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

    <div class="login-wrapper">

        <!-- LEFT BRANDING -->
        <section class="login-brand-side">

            <div class="brand-logo">

                <span class="brand-icon" aria-hidden="true"><img src="{{ asset('images/attendsync.png') }}?v=20261003-circle" alt=""></span>
                <span class="brand-wordmark">ATTENDSYNC</span>

            </div>

            <div class="brand-content">

                <h1>
                    Smarter Attendance.<br>
                    <span>Better Management.</span>
                </h1>

                <p>
                    A secure biometric attendance system designed to help
                    administrators monitor employee attendance, manage records,
                    and improve daily school operations.
                </p>

                <div class="brand-features">

                    <div class="feature-item">
                        <x-lucide-circle-check class="w-4 h-4 text-emerald-400" aria-hidden="true" />
                        <span>Secure access</span>
                    </div>

                    <div class="feature-item">
                        <x-lucide-circle-check class="w-4 h-4 text-emerald-400" aria-hidden="true" />
                        <span>Real-time records</span>
                    </div>

                    <div class="feature-item">
                        <x-lucide-circle-check class="w-4 h-4 text-emerald-400" aria-hidden="true" />
                        <span>Easy monitoring</span>
                    </div>

                </div>

            </div>

            <div class="brand-footer">
                © {{ date('Y') }} ATTENDSYNC. All rights reserved.
            </div>

        </section>

        <!-- RIGHT LOGIN FORM -->
        <section class="login-form-side">

            <div class="login-card">

                <!-- MOBILE BRANDING -->
                <div class="mobile-brand">

                    <span class="brand-icon" aria-hidden="true"><img src="{{ asset('images/attendsync.png') }}?v=20261003-circle" alt=""></span>
                    <span class="brand-wordmark">ATTENDSYNC</span>

                </div>

                <div class="form-heading">

                    <h2>Welcome Back!</h2>

                    <p>
                        Sign in to manage your attendance.
                    </p>

                </div>

                @if ($errors->any())
                    <div class="alert-error">

                        <strong>Login failed.</strong><br>

                        @foreach ($errors->all() as $error)
                            {{ $error }}<br>
                        @endforeach

                    </div>
                @endif

                <form method="POST" action="{{ route('login.submit') }}">
                    @csrf

                    <div class="form-group">

                        <label for="email">
                            Email Address
                        </label>

                        <div class="input-wrapper">

                            <span class="input-icon">
                                <x-lucide-mail class="w-4 h-4 text-slate-400" aria-hidden="true" />
                            </span>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                class="form-control"
                                placeholder="Enter your email address"
                                value="{{ old('email') }}"
                                required
                                autofocus
                            >

                        </div>

                    </div>

                    <div class="form-group">

                        <label for="password">
                            Password
                        </label>

                        <div class="input-wrapper">

                            <span class="input-icon">
                                <x-lucide-lock class="w-4 h-4 text-slate-400" aria-hidden="true" />
                            </span>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-control password-input"
                                placeholder="Enter your password"
                                required
                            >

                            <button
                                type="button"
                                class="password-toggle"
                                onclick="togglePassword()"
                                aria-label="Show or hide password"
                            >
                                <span id="pwdEyeShow"><x-lucide-eye class="w-4 h-4" aria-hidden="true" /></span>
                                <span id="pwdEyeHide" style="display:none;"><x-lucide-eye-off class="w-4 h-4" aria-hidden="true" /></span>
                            </button>

                        </div>

                    </div>

                    <div class="form-options">

                        <label class="remember-option">
                            <input type="checkbox" name="remember">
                            Remember me
                        </label>

                        <a href="#" class="forgot-link">
                            Forgot password?
                        </a>

                    </div>

                    <button type="submit" class="login-button">
                        LOGIN
                    </button>

                </form>

                <div class="security-note">
                    <x-lucide-shield-check class="w-4 h-4 text-blue-600 inline-block align-middle mr-1" aria-hidden="true" />
                    <span>Your account information is protected</span>
                </div>

                <div class="form-bottom">
                    Authorized personnel only.<br>
                    <strong>ATTENDSYNC Attendance System</strong>
                </div>

            </div>

        </section>

    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const showIcon = document.getElementById('pwdEyeShow');
            const hideIcon = document.getElementById('pwdEyeHide');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                showIcon.style.display = 'none';
                hideIcon.style.display = 'inline-flex';
            } else {
                passwordInput.type = 'password';
                showIcon.style.display = 'inline-flex';
                hideIcon.style.display = 'none';
            }
        }
    </script>

</body>
</html>