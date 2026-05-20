(function () {
    'use strict';

    const config = window.__REQUEST_DEBUG__;
    const STORAGE_KEY = 'rd_pending_trace';
    const COOKIE_NAME = 'rd_trace';
    const MIN_RESOURCE_MS = 80;

    if (!config?.reportUrl) {
        return;
    }

    function setTraceCookie(traceId) {
        document.cookie = `${COOKIE_NAME}=${traceId};path=/;max-age=120;SameSite=Lax`;
    }

    function startTrace(href) {
        const traceId = crypto.randomUUID();
        const payload = {
            trace_id: traceId,
            clicked_at: new Date().toISOString(),
            clicked_epoch_ms: Date.now(),
            href,
        };

        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
        } catch (e) {
            return;
        }

        setTraceCookie(traceId);
    }

    function shouldTrackAnchor(anchor) {
        if (!anchor?.href) {
            return false;
        }

        if (anchor.target === '_blank' || anchor.hasAttribute('download')) {
            return false;
        }

        const url = new URL(anchor.href, window.location.origin);

        if (url.origin !== window.location.origin) {
            return false;
        }

        if (url.pathname.startsWith('/assets/')) {
            return false;
        }

        const path = url.pathname;
        if (path === window.location.pathname && url.search === window.location.search) {
            return false;
        }

        return true;
    }

    document.addEventListener(
        'click',
        (event) => {
            const anchor = event.target.closest('a[href]');
            if (!shouldTrackAnchor(anchor)) {
                return;
            }

            startTrace(anchor.href);
        },
        true,
    );

    function pathsMatch(pendingHref, currentPath) {
        try {
            const pendingPath = new URL(pendingHref, window.location.origin).pathname;
            return pendingPath === currentPath;
        } catch (e) {
            return false;
        }
    }

    function collectSlowResources() {
        return performance
            .getEntriesByType('resource')
            .filter((entry) => entry.duration >= MIN_RESOURCE_MS)
            .sort((a, b) => b.duration - a.duration)
            .slice(0, 8)
            .map((entry) => ({
                name: entry.name,
                duration_ms: Math.round(entry.duration * 100) / 100,
                initiator_type: entry.initiatorType || null,
            }));
    }

    function buildReport(pending) {
        const nav = performance.getEntriesByType('navigation')[0];
        if (!nav || nav.type === 'back_forward') {
            return null;
        }

        const fetchStartEpoch = performance.timeOrigin + nav.fetchStart;
        const responseStartEpoch = performance.timeOrigin + nav.responseStart;
        const responseEndEpoch = performance.timeOrigin + nav.responseEnd;
        const domEpoch = performance.timeOrigin + nav.domContentLoadedEventEnd;
        const loadEpoch = performance.timeOrigin + nav.loadEventEnd;
        const pageLoadedEpoch = Date.now();

        const durations = {
            click_to_fetch: Math.round(Math.max(0, fetchStartEpoch - pending.clicked_epoch_ms)),
            fetch_to_response_start: Math.round(nav.responseStart - nav.fetchStart),
            response_start_to_end: Math.round(nav.responseEnd - nav.responseStart),
            response_end_to_dom: Math.round(nav.domContentLoadedEventEnd - nav.responseEnd),
            dom_to_load: Math.round(nav.loadEventEnd - nav.domContentLoadedEventEnd),
            click_to_load: Math.round(pageLoadedEpoch - pending.clicked_epoch_ms),
        };

        return {
            trace_id: pending.trace_id,
            clicked_at: pending.clicked_at,
            clicked_epoch_ms: pending.clicked_epoch_ms,
            page_loaded_at: new Date(pageLoadedEpoch).toISOString(),
            page_loaded_epoch_ms: pageLoadedEpoch,
            path: window.location.pathname,
            href: pending.href,
            navigation_type: nav.type,
            durations_ms: durations,
            timestamps: {
                fetch_start: new Date(fetchStartEpoch).toISOString(),
                response_start: new Date(responseStartEpoch).toISOString(),
                response_end: new Date(responseEndEpoch).toISOString(),
                dom_content_loaded: new Date(domEpoch).toISOString(),
                load_event_end: new Date(loadEpoch).toISOString(),
            },
            slow_resources: collectSlowResources(),
        };
    }

    function sendReport(report) {
        const body = JSON.stringify(report);
        const headers = {
            'Content-Type': 'application/json',
            Accept: 'application/json',
        };

        if (config.csrf) {
            headers['X-CSRF-TOKEN'] = config.csrf;
        }

        fetch(config.reportUrl, {
            method: 'POST',
            headers,
            body,
            credentials: 'same-origin',
            keepalive: true,
        }).catch(() => {});
    }

    function flushPendingTrace() {
        let raw;
        try {
            raw = sessionStorage.getItem(STORAGE_KEY);
        } catch (e) {
            return;
        }

        if (!raw) {
            return;
        }

        let pending;
        try {
            pending = JSON.parse(raw);
        } catch (e) {
            sessionStorage.removeItem(STORAGE_KEY);
            return;
        }

        if (!pathsMatch(pending.href, window.location.pathname)) {
            return;
        }

        const report = buildReport(pending);
        sessionStorage.removeItem(STORAGE_KEY);

        if (!report) {
            return;
        }

        sendReport(report);
    }

    if (document.readyState === 'complete') {
        flushPendingTrace();
    } else {
        window.addEventListener('load', flushPendingTrace, { once: true });
    }
})();
