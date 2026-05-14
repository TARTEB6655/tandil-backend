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
    {{-- Works even when Vite/Tailwind is not built: narrow column + visible button --}}
    <style>
        .portal-page { min-height: 100vh; font-family: Figtree, ui-sans-serif, system-ui, sans-serif; font-size: 15px; color: #0f172a; -webkit-font-smoothing: antialiased; }
        .portal-shell {
            width: 100%;
            max-width: 360px;
            margin-left: auto;
            margin-right: auto;
            padding: 1.5rem 1rem 2rem;
            box-sizing: border-box;
        }
        @media (min-width: 400px) {
            .portal-shell { padding-left: 1.25rem; padding-right: 1.25rem; }
        }
        .portal-logo-wrap {
            width: 70px; height: 70px; margin-left: auto; margin-right: auto;
            display: flex; align-items: center; justify-content: center;
            border-radius: 14px; background: #fff;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08);
            border: 1px solid rgba(148, 163, 184, 0.35);
            box-sizing: border-box;
        }
        .portal-logo-wrap img {
            width: 70px; height: 70px; object-fit: contain;
            border-radius: 12px; display: block; padding: 4px; box-sizing: border-box;
        }
        .portal-muted { color: #64748b; font-size: 13px; }
        .portal-title { font-weight: 700; color: #0f172a; margin: 0.35rem 0 0; font-size: 1.125rem; text-align: center; }
        .portal-sub { text-align: center; margin: 0.35rem 0 0; font-size: 13px; color: #64748b; line-height: 1.45; }
        .portal-brandline { text-align: center; margin-top: 0.75rem; font-size: 13px; font-weight: 600; color: #2d4a3e; }
        .portal-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.35rem 1.25rem 1.25rem;
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.06);
            box-sizing: border-box;
        }
        .portal-label { display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 0.2rem; }
        .portal-input {
            display: block; width: 100%; box-sizing: border-box;
            padding: 0.65rem 0.75rem; font-size: 15px;
            border: 1px solid #cbd5e1; border-radius: 10px;
            background: #f1f5f9; color: #0f172a;
        }
        .portal-input:focus {
            outline: none; border-color: #2d4a3e; background: #fff;
            box-shadow: 0 0 0 3px rgba(45, 74, 62, 0.18);
        }
        .portal-field { margin-bottom: 1rem; }
        .portal-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin: 0.25rem 0 1rem; font-size: 13px; }
        .portal-row label { display: flex; align-items: center; gap: 8px; color: #475569; cursor: pointer; }
        .portal-link { color: #0369a1; font-weight: 600; text-decoration: underline; text-underline-offset: 2px; }
        .portal-link:hover { color: #0c4a6e; }
        .portal-btn {
            display: block; width: 100%; box-sizing: border-box;
            padding: 0.8rem 1rem;
            background: #1a2332 !important;
            color: #fff !important;
            border: none !important;
            border-radius: 10px;
            font-weight: 700; font-size: 13px;
            letter-spacing: 0.06em; text-transform: uppercase;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(26, 35, 50, 0.25);
        }
        .portal-btn:hover { background: #141b26 !important; }
        .portal-btn:focus { outline: 2px solid #2d4a3e; outline-offset: 2px; }
        .portal-divider { border: 0; border-top: 1px solid #f1f5f9; margin: 1.25rem 0 0; padding-top: 1rem; text-align: center; }
        .portal-foot { text-align: center; margin-top: 1rem; font-size: 13px; }
        .portal-foot a { color: #64748b; font-weight: 500; }
        .portal-foot a:hover { color: #2d4a3e; }
        .portal-alert { border-radius: 12px; padding: 0.75rem 1rem; font-size: 13px; margin-bottom: 1rem; line-height: 1.45; }
        .portal-page--roles { background: linear-gradient(180deg, #f5f0e8 0%, #ebe4d8 100%); }
        .portal-page--login { background: #f1f5f9; }
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
        .portal-role-title { font-weight: 700; font-size: 15px; color: #0f172a; margin: 0; line-height: 1.25; }
        .portal-role-desc { font-size: 13px; color: #475569; margin: 6px 0 0; line-height: 1.45; }
        .portal-icon {
            flex-shrink: 0; width: 44px; height: 44px; border-radius: 9999px;
            border: 1px solid rgba(45, 74, 62, 0.22); background: #f4faf6;
            display: flex; align-items: center; justify-content: center; color: #2d4a3e;
        }
        .portal-err { color: #dc2626; font-size: 13px; margin: 6px 0 0; }
    </style>
</head>
<body class="portal-page">
    @yield('content')
</body>
</html>
