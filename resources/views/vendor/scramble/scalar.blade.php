<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>{{ $config->get('ui.title') ?? config('app.name') . ' - API Docs' }}</title>
    <style>
        :root {
            --scramble-token-success: #1f9d55;
            --scramble-token-danger: #dc2626;
            --scramble-token-border: #d0d7de;
            --scramble-token-panel: #ffffff;
            --scramble-token-text: #0f172a;
            --scramble-token-muted: #475569;
            --scramble-token-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
        }
        html, body {
            margin: 0;
            min-height: 100%;
        }
        body {
            display: flex;
            flex-direction: column;
            background: #f8fafc;
        }
        .scramble-token-toolbar {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            border-bottom: 1px solid var(--scramble-token-border);
            background: var(--scramble-token-panel);
            box-shadow: var(--scramble-token-shadow);
            z-index: 10;
        }
        .scramble-token-label {
            font-size: 14px;
            font-weight: 600;
            color: var(--scramble-token-text);
            white-space: nowrap;
        }
        .scramble-token-input {
            flex: 1;
            min-width: 220px;
            padding: 10px 12px;
            border: 1px solid var(--scramble-token-border);
            border-radius: 8px;
            font-size: 14px;
            color: var(--scramble-token-text);
        }
        .scramble-token-button {
            border: 0;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .scramble-token-button-save {
            background: #0f172a;
            color: #fff;
        }
        .scramble-token-button-clear {
            background: #e2e8f0;
            color: var(--scramble-token-text);
        }
        .scramble-token-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--scramble-token-muted);
            white-space: nowrap;
        }
        .scramble-token-status-dot {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: var(--scramble-token-danger);
        }
        .scramble-token-status.saved .scramble-token-status-dot {
            background: var(--scramble-token-success);
        }
        #app {
            flex: 1;
            min-height: 0;
        }
        @media (max-width: 768px) {
            .scramble-token-toolbar {
                flex-wrap: wrap;
            }
            .scramble-token-status {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<div class="scramble-token-toolbar">
    <label class="scramble-token-label" for="scramble-api-token">Set API Token</label>
    <input
        id="scramble-api-token"
        class="scramble-token-input"
        type="text"
        placeholder="Paste bearer token"
        autocomplete="off"
        spellcheck="false"
    >
    <button id="scramble-api-token-save" class="scramble-token-button scramble-token-button-save" type="button">Save</button>
    <button id="scramble-api-token-clear" class="scramble-token-button scramble-token-button-clear" type="button">Clear Token</button>
    <div id="scramble-api-token-status" class="scramble-token-status" aria-live="polite">
        <span class="scramble-token-status-dot"></span>
        <span id="scramble-api-token-status-text">No Token</span>
    </div>
</div>
<div id="app"></div>
<script src="{{ $config->renderer()->get('cdn', 'https://cdn.jsdelivr.net/npm/@scalar/api-reference') }}"></script>

<script>
    const API_TOKEN_STORAGE_KEY = 'api_token';
    const CSRF_TOKEN_COOKIE_KEY = "XSRF-TOKEN";
    const CSRF_TOKEN_HEADER_KEY = "X-XSRF-TOKEN";
    const getStoredApiToken = () => window.localStorage.getItem(API_TOKEN_STORAGE_KEY)?.trim() || '';
    const setStoredApiToken = (token) => window.localStorage.setItem(API_TOKEN_STORAGE_KEY, token);
    const clearStoredApiToken = () => window.localStorage.removeItem(API_TOKEN_STORAGE_KEY);
    const getCookieValue = (key) => {
        const cookie = document.cookie.split(';').find((cookie) => cookie.trim().startsWith(key));
        return cookie?.split("=")[1];
    };
    const tokenInput = document.getElementById('scramble-api-token');
    const saveButton = document.getElementById('scramble-api-token-save');
    const clearButton = document.getElementById('scramble-api-token-clear');
    const statusElement = document.getElementById('scramble-api-token-status');
    const statusText = document.getElementById('scramble-api-token-status-text');

    const syncTokenUi = () => {
        const token = getStoredApiToken();
        tokenInput.value = token;
        const hasToken = token.length > 0;
        statusElement.classList.toggle('saved', hasToken);
        statusText.textContent = hasToken ? 'Token Saved' : 'No Token';
    };

    saveButton.addEventListener('click', () => {
        setStoredApiToken(tokenInput.value.trim());
        syncTokenUi();
    });

    clearButton.addEventListener('click', () => {
        clearStoredApiToken();
        syncTokenUi();
        tokenInput.focus();
    });

    tokenInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            saveButton.click();
        }
    });

    syncTokenUi();

    Scalar.createApiReference('#app', {
        content: @json($spec),
        ...@json($config->renderer()->all(except: ['cdn', 'credentials'])),
        onBeforeRequest: ({ requestBuilder }) => {
            const csrfToken = getCookieValue(CSRF_TOKEN_COOKIE_KEY);
            if (csrfToken) {
                requestBuilder.headers.set(CSRF_TOKEN_HEADER_KEY, decodeURIComponent(csrfToken));
            }

            const apiToken = getStoredApiToken();
            if (apiToken) {
                requestBuilder.headers.set('Authorization', `Bearer ${apiToken}`);
            }
        },
        customFetch: (input, init) => {
            const headers = new Headers(init?.headers || {});
            const apiToken = getStoredApiToken();
            if (apiToken) {
                headers.set('Authorization', `Bearer ${apiToken}`);
            }

            const csrfToken = getCookieValue(CSRF_TOKEN_COOKIE_KEY);
            if (csrfToken) {
                headers.set(CSRF_TOKEN_HEADER_KEY, decodeURIComponent(csrfToken));
            }

            return window.fetch(input, {
                ...init,
                headers,
                credentials: @json($config->renderer()->get('credentials', 'include')),
            });
        }
    })
</script>
</body>
</html>
