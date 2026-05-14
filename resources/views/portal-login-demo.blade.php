<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TANDIL — Portal login (demo)</title>
    <style>
        :root { --bg:#f5f0e8; --card:#ebe4d8; --text:#1a1a1a; --accent:#2d4a3e; --muted:#5c5c5c; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: system-ui, -apple-system, Segoe UI, sans-serif; background:var(--bg); color:var(--text); min-height:100vh; padding:1.25rem; }
        .wrap { max-width: 420px; margin: 0 auto; }
        h1 { font-size: 1.35rem; text-align: center; margin: 0 0 0.35rem; }
        .sub { text-align: center; color: var(--muted); font-size: 0.85rem; margin-bottom: 1.25rem; }
        .logo { text-align:center; font-weight:700; letter-spacing:0.08em; margin-bottom:1rem; color:var(--accent); }
        .portal { display:block; width:100%; text-align:left; padding:0.9rem 1rem; margin-bottom:0.65rem; border:none; border-radius:12px;
            background:var(--card); color:var(--text); cursor:pointer; box-shadow:0 1px 2px rgba(0,0,0,.06); border:1px solid rgba(0,0,0,.04); }
        .portal:hover { filter: brightness(0.98); }
        .portal strong { display:block; font-size:0.95rem; }
        .portal span { display:block; font-size:0.72rem; color:var(--muted); margin-top:0.25rem; line-height:1.35; }
        .portal.selected { outline: 2px solid var(--accent); }
        label { display:block; font-size:0.78rem; color:var(--muted); margin:0.5rem 0 0.2rem; }
        input { width:100%; padding:0.65rem 0.75rem; border-radius:8px; border:1px solid #ccc; background:#fff; }
        button[type=submit] { width:100%; margin-top:1rem; padding:0.75rem; border-radius:10px; border:none; background:var(--accent); color:#fff; font-weight:600; cursor:pointer; }
        button[type=submit]:disabled { opacity:0.55; cursor:not-allowed; }
        pre { background:#1e1e1e; color:#d4d4d4; padding:0.75rem; border-radius:8px; font-size:0.7rem; overflow:auto; max-height:220px; margin-top:1rem; white-space:pre-wrap; word-break:break-all; }
        .hint { font-size:0.75rem; color:var(--muted); margin-top:0.75rem; line-height:1.4; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="logo">TANDIL</div>
        <h1>Choose your role (portal)</h1>
        <p class="sub">Same <code>portal</code> value the mobile app should send to <code>POST /api/auth/login</code>.</p>

        <form id="f">
            <input type="hidden" name="portal" id="portal" value="client" required>
            <button type="button" class="portal selected" data-portal="client"><strong>Client</strong><span>Subscribe, shop, wallet — portal <code>client</code></span></button>
            <button type="button" class="portal" data-portal="technician"><strong>Worker (technician)</strong><span>Field work — portal <code>technician</code></span></button>
            <button type="button" class="portal" data-portal="supervisor"><strong>Supervisor</strong><span>Team lead — portal <code>supervisor</code></span></button>
            <button type="button" class="portal" data-portal="area_manager"><strong>Area manager</strong><span>Region oversight — portal <code>area_manager</code></span></button>
            <button type="button" class="portal" data-portal="hr"><strong>HR</strong><span>Employees — portal <code>hr</code></span></button>
            <button type="button" class="portal" data-portal="admin"><strong>Admin</strong><span>Back office — portal <code>admin</code></span></button>

            <label for="email">Email</label>
            <input id="email" name="email" type="email" autocomplete="username" required>
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>

            <button type="submit" id="go">Sign in</button>
        </form>
        <p class="hint">Open your browser devtools Network tab to inspect the JSON. A token is returned only when email/password are valid <em>and</em> the selected portal matches the user’s Spatie role (when roles exist).</p>
        <pre id="out">Response will appear here.</pre>
    </div>
    <script>
        const API_LOGIN = @json($apiLoginUrl ?? url('/api/auth/login'));
        const portalInput = document.getElementById('portal');
        document.querySelectorAll('.portal[data-portal]').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.portal[data-portal]').forEach(b => b.classList.remove('selected'));
                btn.classList.add('selected');
                portalInput.value = btn.dataset.portal;
            });
        });
        document.getElementById('f').addEventListener('submit', async (e) => {
            e.preventDefault();
            const go = document.getElementById('go');
            const out = document.getElementById('out');
            go.disabled = true;
            out.textContent = 'Loading…';
            try {
                const body = {
                    email: document.getElementById('email').value.trim(),
                    password: document.getElementById('password').value,
                    portal: portalInput.value,
                };
                const res = await fetch(API_LOGIN, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(body),
                });
                const text = await res.text();
                let pretty = text;
                try { pretty = JSON.stringify(JSON.parse(text), null, 2); } catch (_) {}
                out.textContent = res.status + ' ' + res.statusText + '\n\n' + pretty;
            } catch (err) {
                out.textContent = String(err);
            } finally {
                go.disabled = false;
            }
        });
    </script>
</body>
</html>
