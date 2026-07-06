<!doctype html>
<html lang="en" data-theme="{{ $config->renderer()->get('theme', 'light') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="color-scheme" content="{{ $config->renderer()->get('theme', 'light') }}">
    <title>{{ $config->get('ui.title') ?? config('app.name') . ' - API Docs' }}</title>

    <script src="https://unpkg.com/@stoplight/elements@8.4.2/web-components.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/@stoplight/elements@8.4.2/styles.min.css">

    <script>
        const originalFetch = window.fetch;
        const API_TOKEN_STORAGE_KEY = 'api_token';
        const getStoredApiToken = () => window.localStorage.getItem(API_TOKEN_STORAGE_KEY)?.trim() || '';
        const setStoredApiToken = (token) => window.localStorage.setItem(API_TOKEN_STORAGE_KEY, token);
        const clearStoredApiToken = () => window.localStorage.removeItem(API_TOKEN_STORAGE_KEY);

        // intercept TryIt requests and add the XSRF-TOKEN header,
        // which is necessary for Sanctum cookie-based authentication to work correctly
        window.fetch = (url, options) => {
            const CSRF_TOKEN_COOKIE_KEY = "XSRF-TOKEN";
            const CSRF_TOKEN_HEADER_KEY = "X-XSRF-TOKEN";
            const getCookieValue = (key) => {
                const cookie = document.cookie.split(';').find((cookie) => cookie.trim().startsWith(key));
                return cookie?.split("=")[1];
            };

            const updateFetchHeaders = (
                headers,
                headerKey,
                headerValue,
            ) => {
                if (headers instanceof Headers) {
                    headers.set(headerKey, headerValue);
                } else if (Array.isArray(headers)) {
                    headers.push([headerKey, headerValue]);
                } else if (headers) {
                    headers[headerKey] = headerValue;
                }
            };

            const { headers = new Headers() } = options || {};
            const csrfToken = getCookieValue(CSRF_TOKEN_COOKIE_KEY);
            if (csrfToken) {
                updateFetchHeaders(headers, CSRF_TOKEN_HEADER_KEY, decodeURIComponent(csrfToken));
            }

            const apiToken = getStoredApiToken();
            if (apiToken) {
                updateFetchHeaders(headers, 'Authorization', `Bearer ${apiToken}`);
            }

            return originalFetch(url, {
                ...options,
                headers,
            });
        };
    </script>

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
        html, body { margin:0; height:100%; }
        body { background-color: var(--color-canvas); }
        body.scramble-docs-page {
            display: flex;
            flex-direction: column;
            overflow: hidden;
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
        #docs {
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
        /* issues about the dark theme of stoplight/mosaic-code-viewer using web component:
         * https://github.com/stoplightio/elements/issues/2188#issuecomment-1485461965
         */
        [data-theme="dark"] .token.property {
            color: rgb(128, 203, 196) !important;
        }
        [data-theme="dark"] .token.operator {
            color: rgb(255, 123, 114) !important;
        }
        [data-theme="dark"] .token.number {
            color: rgb(247, 140, 108) !important;
        }
        [data-theme="dark"] .token.string {
            color: rgb(165, 214, 255) !important;
        }
        [data-theme="dark"] .token.boolean {
            color: rgb(121, 192, 255) !important;
        }
        [data-theme="dark"] .token.punctuation {
            color: #dbdbdb !important;
        }
        [data-theme="dark"] .scramble-token-toolbar {
            background: #0f172a;
            border-bottom-color: #1e293b;
        }
        [data-theme="dark"] .scramble-token-label,
        [data-theme="dark"] .scramble-token-input,
        [data-theme="dark"] .scramble-token-button-clear {
            color: #e2e8f0;
        }
        [data-theme="dark"] .scramble-token-input {
            background: #111827;
            border-color: #334155;
        }
        [data-theme="dark"] .scramble-token-button-clear {
            background: #1e293b;
        }
        [data-theme="dark"] .scramble-token-status {
            color: #cbd5e1;
        }
    </style>
</head>
<body class="scramble-docs-page">
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
<elements-api
    id="docs"
    @foreach($config->renderer()->all(except: ['theme']) as $key => $value)
        @continue(! $value)
        {{ $key }}="{{ $value === true ? 'true' : ($value === false ? 'false' : $value) }}"
    @endforeach
/>
<script>
    (async () => {
        const docs = document.getElementById('docs');
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
        docs.apiDescriptionDocument = @json($spec);
    })();
</script>

@if($config->renderer()->get('theme', 'light') === 'system')
    <script>
        var mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

        function updateTheme(e) {
            if (e.matches) {
                window.document.documentElement.setAttribute('data-theme', 'dark');
                window.document.getElementsByName('color-scheme')[0].setAttribute('content', 'dark');
            } else {
                window.document.documentElement.setAttribute('data-theme', 'light');
                window.document.getElementsByName('color-scheme')[0].setAttribute('content', 'light');
            }
        }

        mediaQuery.addEventListener('change', updateTheme);
        updateTheme(mediaQuery);
    </script>
@endif
</body>
</html>
