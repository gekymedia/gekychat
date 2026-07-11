{{-- Product analytics for web chat (sessions + screen time) --}}
<script>
(function () {
    if (!@json(auth()->check())) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const storageKey = 'geky_pa_session_uuid';
    let sessionUuid = localStorage.getItem(storageKey);
    if (!sessionUuid) {
        sessionUuid = (crypto.randomUUID && crypto.randomUUID()) || String(Date.now());
        localStorage.setItem(storageKey, sessionUuid);
    }

    let currentFeature = 'chats';
    let featureOpenedAt = Date.now();
    const queue = [];
    let analyticsEnabled = true;
    let heartbeatTimer = null;
    let flushTimer = null;

    function disableAnalytics() {
        if (!analyticsEnabled) return;
        analyticsEnabled = false;
        if (heartbeatTimer) clearInterval(heartbeatTimer);
        if (flushTimer) clearInterval(flushTimer);
    }

    function api(path, body) {
        if (!analyticsEnabled) return Promise.resolve(null);

        return fetch('/api/v1' + path, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        }).then((response) => {
            if (response.status === 401 || response.status === 403) {
                disableAnalytics();
            }
            return response;
        }).catch(() => {});
    }

    function flush() {
        if (!analyticsEnabled || !queue.length) return;
        const events = queue.splice(0, queue.length);
        api('/analytics/events', { session_uuid: sessionUuid, platform: 'web', events });
    }

    function trackFeature(feature) {
        if (!analyticsEnabled) return;

        const now = Date.now();
        if (currentFeature && featureOpenedAt) {
            const seconds = Math.round((now - featureOpenedAt) / 1000);
            if (seconds > 0) {
                queue.push({
                    event_name: 'screen_view',
                    feature_key: currentFeature,
                    properties: { duration_seconds: seconds, ended: true },
                    occurred_at: new Date().toISOString(),
                });
            }
        }
        currentFeature = feature;
        featureOpenedAt = now;
        queue.push({
            event_name: 'screen_view',
            feature_key: feature,
            occurred_at: new Date().toISOString(),
        });
        flush();
    }

    api('/analytics/session/start', {
        session_uuid: sessionUuid,
        platform: 'web',
        device_type: 'web',
    });

    heartbeatTimer = setInterval(() => api('/analytics/session/heartbeat', { session_uuid: sessionUuid }), 60000);
    flushTimer = setInterval(flush, 30000);

    document.addEventListener('visibilitychange', () => {
        if (!analyticsEnabled) return;

        if (document.visibilityState === 'hidden') {
            trackFeature(currentFeature);
            api('/analytics/session/end', { session_uuid: sessionUuid });
        } else {
            api('/analytics/session/start', { session_uuid: sessionUuid, platform: 'web' });
            featureOpenedAt = Date.now();
        }
    });

    window.gekyProductAnalytics = { trackFeature };
    trackFeature('chats');
})();
</script>
