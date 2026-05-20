(function () {
    'use strict';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const originalFetch = window.fetch ? window.fetch.bind(window) : null;
    const FETCH_TIMEOUT_MS = 30000;
    const reportedErrors = new Set();

    function releaseStuckUi() {
        document.dispatchEvent(new Event('submit-guard:reset'));

        ['preloader', 'status'].forEach((id) => {
            const element = document.getElementById(id);

            if (!element) {
                return;
            }

            element.style.display = 'none';
            element.setAttribute('aria-hidden', 'true');
        });
    }

    function normalizedErrorPayload(error, fallbackMessage) {
        const message = (error?.message || String(error || fallbackMessage || 'Erro JavaScript')).slice(0, 500);

        return {
            message,
            source: (error?.filename || error?.source || '').slice(0, 500),
            lineno: Number(error?.lineno || 0),
            colno: Number(error?.colno || 0),
            stack: (error?.error?.stack || error?.reason?.stack || error?.stack || '').slice(0, 3000),
            url: window.location.href,
        };
    }

    function reportClientError(payload) {
        if (!csrfToken || !originalFetch) {
            return;
        }

        const signature = `${payload.message}|${payload.source}|${payload.lineno}|${payload.colno}`;

        if (reportedErrors.has(signature)) {
            return;
        }

        reportedErrors.add(signature);

        originalFetch('/client-errors', {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        }).catch(() => {
            // Logging must never block the user interaction that triggered the error.
        });
    }

    if (originalFetch && window.AbortController) {
        window.fetch = function resilientFetch(input, init = {}) {
            if (init.signal || init.keepalive) {
                return originalFetch(input, init);
            }

            const controller = new AbortController();
            const timeout = window.setTimeout(() => {
                controller.abort();
            }, FETCH_TIMEOUT_MS);

            return originalFetch(input, {
                ...init,
                signal: controller.signal,
            }).catch((error) => {
                if (error?.name === 'AbortError') {
                    releaseStuckUi();
                    reportClientError({
                        message: 'Requisição AJAX excedeu o tempo limite',
                        source: typeof input === 'string' ? input : input?.url || '',
                        lineno: 0,
                        colno: 0,
                        stack: '',
                        url: window.location.href,
                    });
                }

                throw error;
            }).finally(() => {
                window.clearTimeout(timeout);
            });
        };
    }

    window.addEventListener('error', (event) => {
        releaseStuckUi();
        reportClientError(normalizedErrorPayload(event, 'Erro JavaScript'));
    });

    window.addEventListener('unhandledrejection', (event) => {
        releaseStuckUi();
        reportClientError(normalizedErrorPayload(event.reason, 'Promise rejeitada sem tratamento'));
    });

    window.addEventListener('submit-guard:timeout', () => {
        releaseStuckUi();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', releaseStuckUi, { once: true });
    } else {
        releaseStuckUi();
    }

    window.addEventListener('pageshow', releaseStuckUi);
    window.setTimeout(releaseStuckUi, 5000);
})();
