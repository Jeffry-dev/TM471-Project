<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>API Playground</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #0b1020;
            --panel: #111a33;
            --text: #e8ecff;
            --muted: #a9b3d6;
            --border: rgba(255,255,255,0.12);
            --accent: #7c9cff;
            --danger: #ff6b6b;
            --success: #4ade80;
        }
        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji";
            background: radial-gradient(1200px 800px at 15% 10%, rgba(124, 156, 255, 0.20), transparent 60%),
                        radial-gradient(900px 700px at 90% 15%, rgba(74, 222, 128, 0.12), transparent 55%),
                        var(--bg);
            color: var(--text);
        }
        a { color: var(--accent); }
        .wrap { max-width: 1080px; margin: 0 auto; padding: 28px 18px 56px; }
        h1 { font-size: 22px; margin: 0 0 6px; }
        p { margin: 0 0 14px; color: var(--muted); }
        .grid { display: grid; grid-template-columns: 360px 1fr; gap: 14px; }
        .card {
            background: rgba(17,26,51,0.85);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.25);
        }
        .card h2 { font-size: 14px; margin: 0 0 10px; color: #dbe3ff; letter-spacing: 0.2px; }
        label { display: block; font-size: 12px; color: var(--muted); margin: 10px 0 6px; }
        input, select, textarea {
            width: 100%;
            box-sizing: border-box;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: rgba(0,0,0,0.25);
            color: var(--text);
            padding: 10px 10px;
            outline: none;
        }
        textarea { min-height: 180px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-size: 12px; }
        .row { display: grid; grid-template-columns: 120px 1fr; gap: 10px; }
        .row3 { display: grid; grid-template-columns: 120px 1fr 1fr; gap: 10px; }
        .btns { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
        button {
            border-radius: 10px;
            border: 1px solid var(--border);
            background: rgba(124,156,255,0.18);
            color: var(--text);
            padding: 9px 11px;
            cursor: pointer;
        }
        button.primary { background: rgba(124,156,255,0.30); border-color: rgba(124,156,255,0.45); }
        button.ghost { background: rgba(0,0,0,0.10); }
        button.danger { background: rgba(255,107,107,0.20); border-color: rgba(255,107,107,0.35); }
        button:disabled { opacity: 0.55; cursor: not-allowed; }
        .quick button { width: 100%; text-align: left; }
        .muted { color: var(--muted); }
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: var(--muted);
            border: 1px dashed var(--border);
            padding: 6px 10px;
            border-radius: 999px;
        }
        pre {
            margin: 0;
            white-space: pre-wrap;
            word-break: break-word;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 12px;
            background: rgba(0,0,0,0.28);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px;
            min-height: 220px;
        }
        .status {
            font-size: 12px;
            margin-top: 10px;
            color: var(--muted);
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .status .ok { color: var(--success); }
        .status .bad { color: var(--danger); }
        .foot { margin-top: 14px; font-size: 12px; color: var(--muted); }
        @media (max-width: 980px) {
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <h1>API Playground</h1>
    <p>
        Interactive docs page for quickly testing your endpoints.
        <span class="pill">Base URL: <span id="baseUrl"></span></span>
    </p>
    <p class="muted" style="margin-top:-4px;">
        Security: this page ships with <strong>examples only</strong> (no real credentials). Don’t paste production tokens here.
    </p>

    <div class="grid">
        <div class="card">
            <h2>Quick actions</h2>
            <p class="muted">Click an endpoint to prefill the request builder.</p>

            <div class="quick btns" style="flex-direction: column; align-items: stretch;">
                <button class="ghost" data-fill="login">POST /auth/login (get token)</button>
                <button class="ghost" data-fill="me">GET /auth/me</button>
                <button class="ghost" data-fill="menuIndex">GET /menu</button>
                <button class="ghost" data-fill="menuIndexPaginated">GET /menu?perPage=25&page=1</button>
                <button class="ghost" data-fill="menuShow">GET /menu/{id}</button>
                <button class="ghost" data-fill="menuCreate">POST /menu (admin)</button>
                <button class="ghost" data-fill="menuUpdate">PATCH /menu/{id} (admin)</button>
                <button class="ghost" data-fill="menuDelete">DELETE /menu/{id} (admin)</button>
                <button class="ghost" data-fill="categoriesIndex">GET /categories</button>
                <button class="ghost" data-fill="categoriesShow">GET /categories/{id}</button>
                <button class="ghost" data-fill="categoriesCreate">POST /categories (admin)</button>
                <button class="ghost" data-fill="categoriesUpdate">PATCH /categories/{id} (admin)</button>
                <button class="ghost" data-fill="categoriesDelete">DELETE /categories/{id} (admin)</button>
                <button class="ghost" data-fill="contact">POST /contact-messages</button>
            </div>

            <div class="foot">
                <div><strong>Auth note:</strong> Admin endpoints require <code>Authorization: Bearer &lt;token&gt;</code>.</div>
                <div style="margin-top:6px;">Tip: Use the login button first, then click “Save token”.</div>
            </div>
        </div>

        <div class="card">
            <h2>Request builder</h2>

            <label for="token">Bearer token (Sanctum)</label>
            <div class="row3">
                <input id="token" placeholder="Paste token here" />
                <button id="saveToken" class="primary">Save token</button>
                <button id="clearToken" class="danger">Clear</button>
            </div>
            <div style="margin-top: 10px;">
                <label style="display: inline-flex; align-items: center; gap: 8px; margin: 0;">
                    <input id="useAuth" type="checkbox" style="width:auto;" checked>
                    <span>Send Authorization header</span>
                </label>
            </div>

            <label>Request</label>
            <div class="row">
                <select id="method">
                    <option>GET</option>
                    <option>POST</option>
                    <option>PATCH</option>
                    <option>DELETE</option>
                </select>
                <input id="path" placeholder="/menu" />
            </div>

            <label for="body">JSON body (optional)</label>
            <textarea id="body" spellcheck="false" placeholder='{ "example": true }'></textarea>

            <div class="btns">
                <button id="send" class="primary">Send</button>
                <button id="clear" class="ghost">Clear body</button>
            </div>

            <div class="status" id="status"></div>

            <label>Response</label>
            <pre id="response">// Response will show here</pre>
        </div>
    </div>
</div>

<script>
(function () {
    const baseUrl = window.location.origin;
    document.getElementById('baseUrl').textContent = baseUrl;

    const els = {
        token: document.getElementById('token'),
        saveToken: document.getElementById('saveToken'),
        clearToken: document.getElementById('clearToken'),
        useAuth: document.getElementById('useAuth'),
        method: document.getElementById('method'),
        path: document.getElementById('path'),
        body: document.getElementById('body'),
        send: document.getElementById('send'),
        clear: document.getElementById('clear'),
        response: document.getElementById('response'),
        status: document.getElementById('status'),
    };

    const STORAGE_KEY = 'apiPlaygroundToken';
    els.token.value = localStorage.getItem(STORAGE_KEY) || '';

    function setStatus(parts) {
        els.status.innerHTML = '';
        for (const p of parts) {
            const span = document.createElement('span');
            span.textContent = p.text;
            if (p.kind === 'ok') span.className = 'ok';
            if (p.kind === 'bad') span.className = 'bad';
            els.status.appendChild(span);
        }
    }

    function pretty(text) {
        try {
            return JSON.stringify(JSON.parse(text), null, 2);
        } catch {
            return text;
        }
    }

    async function sendRequest() {
        const method = els.method.value;
        const path = els.path.value.trim() || '/';

        const headers = {
            'Accept': 'application/json',
        };

        const useAuth = els.useAuth.checked;
        const token = els.token.value.trim();
        if (useAuth && token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        const bodyRaw = els.body.value.trim();
        let body = undefined;

        if (method !== 'GET' && method !== 'DELETE' && bodyRaw.length > 0) {
            headers['Content-Type'] = 'application/json';
            body = bodyRaw;
        }

        setStatus([{ text: `Sending ${method} ${path}...` }]);
        els.response.textContent = '';

        const started = Date.now();

        let res;
        let text;
        try {
            res = await fetch(path, { method, headers, body });
            text = await res.text();
        } catch (e) {
            setStatus([{ text: 'Network error', kind: 'bad' }, { text: String(e) }]);
            els.response.textContent = String(e);
            return;
        }

        const ms = Date.now() - started;
        const ok = res.ok;

        setStatus([
            { text: `HTTP ${res.status} ${res.statusText}`, kind: ok ? 'ok' : 'bad' },
            { text: `${ms}ms` },
            { text: useAuth ? 'auth: on' : 'auth: off' },
        ]);

        els.response.textContent = pretty(text);

        // If this was a login response, try to auto-detect accessToken.
        if (path === '/auth/login' && ok) {
            try {
                const json = JSON.parse(text);
                if (json && typeof json.accessToken === 'string' && json.accessToken.length > 0) {
                    els.token.value = json.accessToken;
                }
            } catch {
                // ignore
            }
        }
    }

    function fillPreset(name) {
        const presets = {
            login: {
                method: 'POST',
                path: '/auth/login',
                // Example only. Replace with your real credentials.
                body: JSON.stringify({ email: '', password: '' }, null, 2),
                useAuth: false,
            },
            me: { method: 'GET', path: '/auth/me', body: '', useAuth: true },
            menuIndex: { method: 'GET', path: '/menu', body: '', useAuth: false },
            menuIndexPaginated: { method: 'GET', path: '/menu?perPage=25&page=1', body: '', useAuth: false },
            menuShow: { method: 'GET', path: '/menu/{id}', body: '', useAuth: false },
            menuCreate: {
                method: 'POST',
                path: '/menu',
                body: JSON.stringify({
                    name: 'Sample item',
                    description: 'Optional description',
                    imageUrl: null,
                    price: 9.99,
                    category: 'Starters',
                    isAvailable: true
                }, null, 2),
                useAuth: true,
            },
            menuUpdate: {
                method: 'PATCH',
                path: '/menu/{id}',
                body: JSON.stringify({
                    description: 'Updated description',
                    isAvailable: true
                }, null, 2),
                useAuth: true,
            },
            menuDelete: { method: 'DELETE', path: '/menu/{id}', body: '', useAuth: true },
            categoriesIndex: { method: 'GET', path: '/categories', body: '', useAuth: false },
            categoriesShow: { method: 'GET', path: '/categories/{id}', body: '', useAuth: false },
            categoriesCreate: {
                method: 'POST',
                path: '/categories',
                body: JSON.stringify({ name: 'New category', description: 'Optional' }, null, 2),
                useAuth: true,
            },
            categoriesUpdate: {
                method: 'PATCH',
                path: '/categories/{id}',
                body: JSON.stringify({ description: 'Updated description' }, null, 2),
                useAuth: true,
            },
            categoriesDelete: { method: 'DELETE', path: '/categories/{id}', body: '', useAuth: true },
            contact: {
                method: 'POST',
                path: '/contact-messages',
                body: JSON.stringify({
                    name: 'Jane Doe',
                    email: 'jane@example.com',
                    phone: '555-555-5555',
                    message: 'Hello! I have a question.',
                    mode: 'contact',
                    date: null,
                    occasion: null,
                    seating: null,
                    preferredContact: 'email'
                }, null, 2),
                useAuth: false,
            },
        };

        const p = presets[name];
        if (!p) return;

        els.method.value = p.method;
        els.path.value = p.path;
        els.body.value = p.body;
        els.useAuth.checked = !!p.useAuth;

        setStatus([{ text: `Prefilled: ${p.method} ${p.path}` }]);
        els.response.textContent = '// Response will show here';
    }

    els.saveToken.addEventListener('click', function () {
        localStorage.setItem(STORAGE_KEY, els.token.value.trim());
        setStatus([{ text: 'Token saved to localStorage', kind: 'ok' }]);
    });

    els.clearToken.addEventListener('click', function () {
        els.token.value = '';
        localStorage.removeItem(STORAGE_KEY);
        setStatus([{ text: 'Token cleared', kind: 'ok' }]);
    });

    els.send.addEventListener('click', function () {
        sendRequest();
    });

    els.clear.addEventListener('click', function () {
        els.body.value = '';
    });

    document.querySelectorAll('[data-fill]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            fillPreset(btn.getAttribute('data-fill'));
        });
    });

    // Default view
    fillPreset('menuIndex');
})();
</script>
</body>
</html>
