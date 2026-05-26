<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name')) — TANDIL</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Readable layout aligned with Breeze guest (max-w-md, ~16px type) --}}
    <style>
        /* Match Breeze guest login scale: ~16px body, md card width, readable labels/buttons */
        .portal-page { min-height: 100vh; font-family: Figtree, ui-sans-serif, system-ui, sans-serif; font-size: 16px; color: #0f172a; -webkit-font-smoothing: antialiased; }
        .portal-shell {
            width: 100%;
            max-width: 36rem;
            margin-left: auto;
            margin-right: auto;
            padding: 2rem 1.5rem 2.5rem;
            box-sizing: border-box;
        }
        @media (min-width: 640px) {
            .portal-shell { padding-left: 1.5rem; padding-right: 1.5rem; }
        }
        .portal-logo-wrap {
            width: 6rem; height: 6rem; margin-left: auto; margin-right: auto;
            display: flex; align-items: center; justify-content: center;
            border-radius: 14px; background: #fff;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08);
            border: 1px solid rgba(148, 163, 184, 0.35);
            box-sizing: border-box;
        }
        .portal-logo-wrap img {
            width: 6rem; height: 6rem; object-fit: contain;
            border-radius: 12px; display: block; padding: 4px; box-sizing: border-box;
        }
        .portal-muted { color: #64748b; font-size: 16px; }
        .portal-title { font-weight: 700; color: #0f172a; margin: 0.35rem 0 0; font-size: 1.25rem; text-align: center; }
        .portal-sub { text-align: center; margin: 0.35rem 0 0; font-size: 16px; color: #64748b; line-height: 1.5; }
        .portal-brandline { text-align: center; margin-top: 0.75rem; font-size: 16px; font-weight: 600; color: #2d4a3e; }
        .portal-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 2rem 2rem 1.75rem;
            box-shadow: 0 16px 48px rgba(15, 23, 42, 0.08);
            box-sizing: border-box;
        }
        .portal-label { display: block; font-size: 16px; font-weight: 600; color: #334155; margin-bottom: 0.35rem; }
        .portal-input {
            display: block; width: 100%; box-sizing: border-box;
            padding: 0.75rem 0.875rem; font-size: 16px; line-height: 1.5;
            border: 1px solid #cbd5e1; border-radius: 10px;
            background: #f8fafc; color: #0f172a;
        }
        .portal-input:focus {
            outline: none; border-color: #2d4a3e; background: #fff;
            box-shadow: 0 0 0 3px rgba(45, 74, 62, 0.18);
        }
        .portal-input-wrap { position: relative; }
        .portal-input--password { padding-right: 3rem; }
        .portal-password-toggle {
            position: absolute; right: 0.65rem; top: 50%; transform: translateY(-50%);
            display: inline-flex; align-items: center; justify-content: center;
            width: 2.25rem; height: 2.25rem; border: none; background: transparent;
            color: #64748b; cursor: pointer; border-radius: 8px;
        }
        .portal-password-toggle:hover { color: #2d4a3e; background: #f1f5f9; }
        .portal-password-toggle:focus { outline: 2px solid #2d4a3e; outline-offset: 2px; }
        .portal-password-toggle .hidden { display: none; }
        .portal-field { margin-bottom: 1.25rem; }
        .portal-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin: 0.25rem 0 1.25rem; font-size: 16px; }
        .portal-row label { display: flex; align-items: center; gap: 10px; color: #475569; cursor: pointer; font-size: 16px; }
        .portal-row input[type="checkbox"] { width: 1.1rem; height: 1.1rem; }
        .portal-link { color: #0369a1; font-weight: 600; text-decoration: underline; text-underline-offset: 2px; font-size: 16px; }
        .portal-link:hover { color: #0c4a6e; }
        .portal-btn {
            display: block; width: 100%; box-sizing: border-box;
            padding: 0.75rem 1rem;
            background: #1a2332 !important;
            color: #fff !important;
            border: none !important;
            border-radius: 0.375rem;
            font-weight: 600; font-size: 16px;
            letter-spacing: 0.05em; text-transform: uppercase;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(26, 35, 50, 0.25);
        }
        .portal-btn:hover { background: #141b26 !important; }
        .portal-btn:focus { outline: 2px solid #2d4a3e; outline-offset: 2px; }
        .portal-divider { border: 0; border-top: 1px solid #f1f5f9; margin: 1.25rem 0 0; padding-top: 1rem; text-align: center; font-size: 16px; }
        .portal-foot { text-align: center; margin-top: 1rem; font-size: 16px; }
        .portal-foot a { color: #64748b; font-weight: 500; font-size: 16px; }
        .portal-foot a:hover { color: #2d4a3e; }
        .portal-alert { border-radius: 12px; padding: 0.75rem 1rem; font-size: 16px; margin-bottom: 1rem; line-height: 1.5; }
        .portal-page--roles { background: linear-gradient(180deg, #f5f0e8 0%, #ebe4d8 100%); }
        .portal-page--login {
            background: linear-gradient(165deg, #f8fafc 0%, #e8eef4 45%, #f1f5f9 100%);
            justify-content: center;
        }
        .portal-page--login .portal-shell { width: 100%; }
        .portal-role-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
        .portal-role-link {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 14px 16px; border-radius: 14px;
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid #d6d3cd;
            text-decoration: none; color: inherit;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
            box-sizing: border-box;
        }
        .portal-role-link:hover { border-color: #2d4a3e55; background: #fff; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06); }
        .portal-role-title { font-weight: 700; font-size: 16px; color: #0f172a; margin: 0; line-height: 1.25; }
        .portal-role-desc { font-size: 16px; color: #475569; margin: 6px 0 0; line-height: 1.5; }
        .portal-icon {
            flex-shrink: 0; width: 44px; height: 44px; border-radius: 9999px;
            border: 1px solid rgba(45, 74, 62, 0.22); background: #f4faf6;
            display: flex; align-items: center; justify-content: center; color: #2d4a3e;
        }
        .portal-err { color: #dc2626; font-size: 16px; margin: 6px 0 0; }
        .portal-site-footer {
            text-align: center;
            padding: 1.25rem 1.5rem 1.75rem;
            margin-top: auto;
        }
        .portal-site-footer__link {
            font-size: 16px;
            font-weight: 600;
            color: #2d4a3e;
            text-decoration: underline;
            text-underline-offset: 3px;
        }
        .portal-site-footer__link:hover { color: #1a3329; }
        .portal-page--roles,
        .portal-page--login {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .portal-page--roles .portal-shell,
        .portal-page--login .portal-shell {
            flex: 1;
        }
        .portal-page--login .portal-shell {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
    </style>
</head>
<body class="portal-page">
    @yield('content')
    @include('app-portal.partials.site-footer')
</body>
</html>
