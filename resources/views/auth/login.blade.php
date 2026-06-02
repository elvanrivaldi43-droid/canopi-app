<!DOCTYPE html>
<html lang="id"
      x-data="{ darkMode: localStorage.getItem('darkMode') === 'true' }"
      x-init="$watch('darkMode', val => localStorage.setItem('darkMode', val))"
      :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login — Pusat Kanopi CanopiBSD</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { font-family: 'Plus Jakarta Sans', sans-serif; box-sizing: border-box; margin: 0; padding: 0; }
        .font-display { font-family: 'Playfair Display', serif; }

        :root {
            --gold: #C9A84C;
            --gold-dark: #A8872E;
            --dark: #0F1117;
            --dark2: #161B27;
            --dark3: #1E2535;
        }

        html, body {
            height: 100%;
            background: var(--dark);
        }

        /* Background */
        .login-bg {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            position: relative;
            overflow: hidden;
            background: var(--dark);
        }

        /* Decorative circles */
        .login-bg::before {
            content: '';
            position: absolute;
            top: -200px;
            right: -200px;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(201,168,76,0.08) 0%, transparent 70%);
            pointer-events: none;
        }
        .login-bg::after {
            content: '';
            position: absolute;
            bottom: -200px;
            left: -200px;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(201,168,76,0.05) 0%, transparent 70%);
            pointer-events: none;
        }

        /* Card */
        .login-card {
            width: 100%;
            max-width: 420px;
            background: var(--dark2);
            border-radius: 24px;
            border: 1px solid rgba(201,168,76,0.15);
            padding: 40px 36px;
            position: relative;
            z-index: 1;
            box-shadow: 0 25px 50px rgba(0,0,0,0.5);
        }

        @media (max-width: 480px) {
            .login-card { padding: 32px 24px; border-radius: 20px; }
        }

        /* Logo */
        .logo-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 32px;
        }
        .logo-icon {
            width: 64px;
            height: 64px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            box-shadow: 0 8px 24px rgba(201,168,76,0.3);
        }
        .logo-title {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            font-weight: 800;
            color: var(--gold);
            margin-bottom: 4px;
        }
        .logo-sub {
            font-size: 12px;
            color: #64748B;
            letter-spacing: 0.5px;
        }

        /* Divider */
        .divider {
            height: 1px;
            background: rgba(201,168,76,0.1);
            margin: 0 0 28px 0;
        }

        /* Label */
        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #94A3B8;
            margin-bottom: 8px;
            letter-spacing: 0.3px;
        }

        /* Input */
        .form-input {
            width: 100%;
            background: var(--dark3);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 13px 16px;
            font-size: 14px !important;
            color: #E2E8F0;
            outline: none;
            transition: all 0.2s;
            -webkit-appearance: none;
        }
        .form-input:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(201,168,76,0.12);
            background: rgba(201,168,76,0.04);
        }
        .form-input::placeholder { color: #475569; }

        /* Input wrapper with icon */
        .input-wrap {
            position: relative;
            margin-bottom: 20px;
        }
        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #475569;
            pointer-events: none;
        }
        .input-wrap .form-input {
            padding-left: 44px;
        }
        .input-wrap .toggle-pass {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #475569;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
        }
        .input-wrap .toggle-pass:hover { color: var(--gold); }

        /* Checkbox */
        .checkbox-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
        }
        .checkbox-wrap input[type="checkbox"] {
            width: 18px;
            height: 18px;
            border-radius: 5px;
            accent-color: var(--gold);
            cursor: pointer;
            flex-shrink: 0;
        }
        .checkbox-wrap label {
            font-size: 13px;
            color: #94A3B8;
            cursor: pointer;
        }

        /* Button */
        .btn-login {
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            color: var(--dark);
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            letter-spacing: 0.3px;
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            box-shadow: 0 4px 16px rgba(201,168,76,0.3);
            -webkit-tap-highlight-color: transparent;
        }
        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(201,168,76,0.4);
        }
        .btn-login:active { transform: translateY(0); }
        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        /* Error */
        .error-msg {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.25);
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 12.5px;
            color: #FCA5A5;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        .error-msg svg { flex-shrink: 0; margin-top: 1px; }

        /* Input error */
        .input-error {
            font-size: 11px;
            color: #F87171;
            margin-top: -14px;
            margin-bottom: 16px;
            padding-left: 4px;
        }

        /* Footer */
        .login-footer {
            margin-top: 28px;
            text-align: center;
            font-size: 11px;
            color: #334155;
        }
        .login-footer span { color: var(--gold); }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-thumb { background: rgba(201,168,76,0.3); border-radius: 10px; }
    </style>
</head>
<body>
<div class="login-bg">
    <div class="login-card">

        {{-- Logo --}}
        <div class="logo-wrap">
            <div class="logo-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" style="width:32px;height:32px;">
                    <path d="M11.47 3.84a.75.75 0 011.06 0l8.69 8.69a.75.75 0 101.06-1.06l-8.689-8.69a2.25 2.25 0 00-3.182 0l-8.69 8.69a.75.75 0 001.061 1.06l8.69-8.69z"/>
                    <path d="M12 5.432l8.159 8.159c.03.03.06.058.091.086v6.198c0 1.035-.84 1.875-1.875 1.875H15a.75.75 0 01-.75-.75v-4.5a.75.75 0 00-.75-.75h-3a.75.75 0 00-.75.75V21a.75.75 0 01-.75.75H5.625a1.875 1.875 0 01-1.875-1.875v-6.198a2.29 2.29 0 00.091-.086L12 5.43z"/>
                </svg>
            </div>
            <div class="logo-title font-display">Pusat Kanopi</div>
            <div class="logo-sub">CanopiBSD Management System</div>
        </div>

        <div class="divider"></div>

        {{-- Session Error --}}
        @if ($errors->any())
        <div class="error-msg">
            <svg xmlns="http://www.w3.org/2000/svg" style="width:16px;height:16px;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
            </svg>
            <span>Email atau password salah. Silakan coba lagi.</span>
        </div>
        @endif

        @if (session('status'))
        <div style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.25);border-radius:10px;padding:12px 14px;font-size:12.5px;color:#6EE7B7;margin-bottom:20px;">
            {{ session('status') }}
        </div>
        @endif

        {{-- Form --}}
        <form method="POST" action="{{ route('login') }}" x-data="{ showPass: false, loading: false }" @submit="loading = true">
            @csrf

            {{-- Email --}}
            <label class="form-label" for="email">Email</label>
            <div class="input-wrap">
                <span class="input-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" style="width:16px;height:16px;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                    </svg>
                </span>
                <input
                    id="email"
                    name="email"
                    type="email"
                    class="form-input"
                    placeholder="email@kanopibsd.co.id"
                    value="{{ old('email') }}"
                    required
                    autocomplete="email"
                    autofocus>
            </div>
            @error('email')
                <div class="input-error">{{ $message }}</div>
            @enderror

            {{-- Password --}}
            <label class="form-label" for="password">Password</label>
            <div class="input-wrap">
                <span class="input-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" style="width:16px;height:16px;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                    </svg>
                </span>
                <input
                    id="password"
                    name="password"
                    :type="showPass ? 'text' : 'password'"
                    class="form-input"
                    placeholder="••••••••"
                    required
                    autocomplete="current-password">
                <button type="button" class="toggle-pass" @click="showPass = !showPass">
                    <svg x-show="!showPass" xmlns="http://www.w3.org/2000/svg" style="width:16px;height:16px;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <svg x-show="showPass" xmlns="http://www.w3.org/2000/svg" style="width:16px;height:16px;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                    </svg>
                </button>
            </div>
            @error('password')
                <div class="input-error">{{ $message }}</div>
            @enderror

            {{-- Remember Me --}}
            <div class="checkbox-wrap">
                <input type="checkbox" id="remember" name="remember" {{ old('remember') ? 'checked' : '' }}>
                <label for="remember">Ingat saya di perangkat ini</label>
            </div>

            {{-- Submit --}}
            <button type="submit" class="btn-login" :disabled="loading">
                <span x-show="!loading">Masuk ke Sistem</span>
                <span x-show="loading" style="display:flex;align-items:center;justify-content:center;gap:8px;">
                    <svg style="width:16px;height:16px;animation:spin 1s linear infinite;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle style="opacity:0.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path style="opacity:0.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Memproses...
                </span>
            </button>
        </form>

        {{-- Footer --}}
        <div class="login-footer">
            © 2026 <span>Pusat Kanopi BSD</span> · CanopiBSD v2.0
        </div>

    </div>
</div>

<style>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>
</body>
</html>
