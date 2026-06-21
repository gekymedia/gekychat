@extends('layouts.app')

@section('title', ($pageTitle ?? 'Call') . ' - ' . config('app.name', 'GekyChat'))

@section('content')
<div class="group-call-page vh-100 d-flex flex-column bg-dark">
    <div class="d-flex align-items-center justify-content-between p-3 border-bottom border-secondary">
        <a href="{{ route('chat.index') }}" class="btn btn-sm btn-outline-light" id="back-link">
            <i class="bi bi-arrow-left me-1"></i> Leave
        </a>
        <span class="text-white fw-bold text-center flex-grow-1 px-2" id="call-header-title">{{ $headerTitle ?? 'Call' }}</span>
        <span class="text-white-50 small text-end" id="room-name" style="min-width: 6rem;">{{ $subtitle ?? '—' }}</span>
    </div>
    <div id="group-call-container" class="flex-grow-1 d-flex align-items-center justify-content-center p-3 position-relative min-h-0">
        @if(!empty($ended))
            <div class="text-center text-white">
                <p class="mb-2"><i class="bi bi-telephone-x display-4 text-secondary"></i></p>
                <h5 class="text-white">This call has ended</h5>
                <p class="text-white-50 small mb-3">{{ $endedHint ?? 'You can start a new call from your chats.' }}</p>
                <a href="{{ route('chat.index') }}" class="btn btn-outline-light">Back to chats</a>
            </div>
        @else
            <div class="text-center text-white" id="group-call-connecting">
                <div class="spinner-border" role="status"></div>
                <p class="mt-3 mb-1 fw-semibold">{{ $headerTitle ?? 'Call' }}</p>
                @if(!empty($subtitle))
                    <p class="text-white-50 small mb-2">{{ $subtitle }}</p>
                @endif
                <p class="mt-1 mb-0 text-white-50">
                    @if(!empty($isGroupCall))
                        Connecting to group call…
                    @elseif(($callType ?? 'voice') === 'video')
                        Connecting video call…
                    @else
                        Connecting voice call…
                    @endif
                </p>
            </div>
        @endif
    </div>
    <div class="p-3 border-top border-secondary d-flex justify-content-center gap-3" id="group-call-controls" style="{{ !empty($ended) ? 'display:none!important' : '' }}">
        <button type="button" class="btn btn-lg btn-outline-light rounded-circle p-3" id="btn-mute" title="Mute">
            <i class="bi bi-mic-fill"></i>
        </button>
        <button type="button" class="btn btn-lg btn-danger rounded-circle p-3" id="btn-hangup" title="End call">
            <i class="bi bi-telephone-fill"></i>
        </button>
        @if(!empty($isGroupCall) || ($callType ?? 'voice') === 'video')
        <button type="button" class="btn btn-lg btn-outline-light rounded-circle p-3" id="btn-video" title="Video">
            <i class="bi bi-camera-video-fill"></i>
        </button>
        @endif
    </div>
</div>

@push('scripts')
@if(empty($ended))
{{-- Load LiveKit client the same way as live broadcast: dynamic inject with CDN fallback so it works when opening the dedicated call link --}}
<script>
(function() {
    const script = document.createElement('script');
    script.src = 'https://unpkg.com/livekit-client@1.15.13/dist/livekit-client.umd.min.js';
    script.onload = function() { console.log('LiveKit loaded from unpkg'); };
    script.onerror = function() {
        const fallback = document.createElement('script');
        fallback.src = 'https://cdn.jsdelivr.net/npm/livekit-client@1.15.13/dist/livekit-client.umd.min.js';
        fallback.onload = function() { console.log('LiveKit loaded from jsDelivr'); };
        fallback.onerror = function() { console.error('LiveKit failed from both CDNs'); };
        document.head.appendChild(fallback);
    };
    document.head.appendChild(script);
})();
</script>
<script>
(function() {
    const SESSION_ID = {{ (int) $sessionId }};
    const IS_GROUP_CALL = {{ !empty($isGroupCall) ? 'true' : 'false' }};
    const USER_ID = Number(window.APP?.userId) || null;
    const urlParams = new URLSearchParams(window.location.search);
    const isVideo = (urlParams.get('type') || 'video') === 'video';

    let room = null;
    let muted = false;
    let videoOff = false;
    let callEnded = false;
    let statusPollTimer = null;

    function parseSignalPayload(raw) {
        if (!raw) return null;
        if (typeof raw === 'string') {
            try { return JSON.parse(raw); } catch (_) { return null; }
        }
        return typeof raw === 'object' ? raw : null;
    }

    function showRemoteCallEnded(message) {
        if (callEnded) return;
        callEnded = true;
        if (statusPollTimer) {
            clearInterval(statusPollTimer);
            statusPollTimer = null;
        }
        const container = document.getElementById('group-call-container');
        const controls = document.getElementById('group-call-controls');
        if (controls) controls.style.display = 'none';
        if (container) {
            container.innerHTML = '<div class="text-center text-white"><p class="mb-2"><i class="bi bi-telephone-x display-4 text-secondary"></i></p>'
                + '<h5 class="text-white">' + (message || 'Call ended') + '</h5>'
                + '<p class="text-white-50 small mb-3">Returning to chats…</p></div>';
        }
        const backHref = document.getElementById('back-link')?.href || '{{ route("chat.index") }}';
        const disconnect = room ? room.disconnect().catch(function() {}) : Promise.resolve();
        disconnect.finally(function() {
            setTimeout(function() { window.location.href = backHref; }, 1200);
        });
    }

    function handleRemoteCallSignal(payload) {
        if (!payload || callEnded) return;
        const sid = parseInt(payload.session_id, 10);
        if (sid !== SESSION_ID) return;
        const action = payload.action || '';
        const reason = payload.reason || '';
        if (action === 'declined' || (action === 'ended' && reason === 'declined')) {
            showRemoteCallEnded('Call declined');
        } else if (action === 'ended' || action === 'cancel') {
            showRemoteCallEnded('Call ended');
        } else if (action === 'busy') {
            showRemoteCallEnded('User is busy on another call.');
        }
    }

    function setupCallSignalListener() {
        if (typeof Echo === 'undefined' || !USER_ID) return;
        Echo.private('call.' + USER_ID).listen('.CallSignal', function(e) {
            handleRemoteCallSignal(parseSignalPayload(e.payload));
        });
    }

    function startCallStatusPoll() {
        if (statusPollTimer) return;
        statusPollTimer = setInterval(async function() {
            if (callEnded) return;
            try {
                const resp = await fetch('/calls/' + SESSION_ID + '/status', {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!resp.ok) return;
                const data = await resp.json();
                if (data.call_status === 'ended') {
                    showRemoteCallEnded('Call ended');
                }
            } catch (_) {}
        }, 3000);
    }

    function waitForLiveKit() {
        return new Promise((resolve, reject) => {
            if (typeof window.LivekitClient !== 'undefined') { window.LiveKit = window.LivekitClient; resolve(); return; }
            if (typeof window.LiveKit !== 'undefined') { resolve(); return; }
            let attempts = 0;
            const maxAttempts = 150;
            const t = setInterval(() => {
                attempts++;
                if (typeof window.LivekitClient !== 'undefined') { clearInterval(t); window.LiveKit = window.LivekitClient; resolve(); }
                else if (typeof window.LiveKit !== 'undefined') { clearInterval(t); resolve(); }
                else if (attempts >= maxAttempts) {
                    clearInterval(t);
                    reject(new Error('LiveKit client failed to load after 15 seconds. Check your connection or try refreshing. If you use an ad blocker, try disabling it for this site.'));
                }
            }, 100);
        });
    }

    function getVideoPublication(participant) {
        if (!participant || !participant.videoTrackPublications) return null;
        var map = participant.videoTrackPublications;
        if (typeof map.values !== 'function') return null;
        var pubs = Array.from(map.values());
        for (var i = 0; i < pubs.length; i++) {
            var pub = pubs[i];
            if (pub && (pub.kind === 'video' || (pub.track && pub.track.kind === 'video'))) return pub;
        }
        return pubs[0] || null;
    }

    function renderGrid() {
        const container = document.getElementById('group-call-container');
        if (!container || !room) return;
        const local = room.localParticipant;
        const remotes = (room.remoteParticipants && typeof room.remoteParticipants.values === 'function')
            ? Array.from(room.remoteParticipants.values()) : [];
        const participants = [local, ...remotes].filter(Boolean);
        container.innerHTML = '<div class="row g-2 w-100 justify-content-center" id="participant-grid"></div>';
        const grid = document.getElementById('participant-grid');
        participants.forEach(p => {
            const vid = document.createElement('video');
            vid.autoplay = true;
            vid.playsInline = true;
            vid.muted = p === local;
            vid.className = 'rounded bg-secondary';
            vid.style.objectFit = 'cover';
            vid.style.width = '100%';
            vid.style.maxWidth = participants.length <= 2 ? '400px' : '200px';
            vid.style.height = 'auto';
            const pub = getVideoPublication(p);
            if (pub && pub.track && typeof pub.track.attach === 'function') {
                pub.track.attach(vid);
            } else if (pub && pub.track && pub.track.mediaStreamTrack) {
                vid.srcObject = new MediaStream([pub.track.mediaStreamTrack]);
            } else if (pub && pub.mediaStreamTrack) {
                vid.srcObject = new MediaStream([pub.mediaStreamTrack]);
            } else {
                vid.style.background = '#333';
                vid.appendChild(document.createTextNode(p === local ? 'You (camera starting…)' : (p.name || p.identity || 'No video')));
            }
            const wrap = document.createElement('div');
            wrap.className = 'col-auto position-relative';
            wrap.innerHTML = '<span class="position-absolute bottom-0 start-0 m-1 badge bg-dark">' + (p === local ? 'You' : (p.name || p.identity || 'Participant')) + '</span>';
            wrap.insertBefore(vid, wrap.firstChild);
            grid.appendChild(wrap);
        });
    }

    function attachTrack(track, participant) {
        const grid = document.getElementById('participant-grid');
        if (!grid || !room) return;
        const vid = document.createElement('video');
        vid.autoplay = true;
        vid.playsInline = true;
        vid.muted = participant === (room.localParticipant || null);
        vid.className = 'rounded bg-secondary';
        vid.style.objectFit = 'cover';
        vid.style.maxWidth = '200px';
        track.attach(vid);
        const wrap = document.createElement('div');
        wrap.className = 'col-auto';
        wrap.innerHTML = '<span class="badge bg-dark">' + (participant.name || participant.identity || 'Participant') + '</span>';
        wrap.insertBefore(vid, wrap.firstChild);
        grid.appendChild(wrap);
    }

    function csrfHeaders() {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        return {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {})
        };
    }

    async function notifyServerCallEnded() {
        const remotes = room && room.remoteParticipants && typeof room.remoteParticipants.values === 'function'
            ? Array.from(room.remoteParticipants.values())
            : [];
        const useLeave = IS_GROUP_CALL && remotes.length > 0;
        const path = useLeave
            ? '/calls/' + SESSION_ID + '/leave'
            : '/calls/' + SESSION_ID + '/end';
        try {
            await fetch(path, {
                method: 'POST',
                credentials: 'same-origin',
                headers: csrfHeaders(),
            });
        } catch (e) {
            console.warn('Server call end/leave failed:', e);
        }
    }

    async function run() {
        const container = document.getElementById('group-call-container');
        try {
            await waitForLiveKit();
            const resp = await fetch('/calls/group/' + SESSION_ID + '/livekit-token', { credentials: 'same-origin' });
            if (!resp.ok) {
                const err = await resp.json().catch(() => ({}));
                throw new Error(err.error || 'Failed to get token');
            }
            const data = await resp.json();
            let wsUrl = data.url || 'wss://localhost:7880';
            if (window.location.protocol === 'https:' && wsUrl.startsWith('ws://')) wsUrl = 'wss://' + wsUrl.slice(5);
            const LiveKit = window.LiveKit;
            room = new LiveKit.Room({ adaptiveStream: true, dynacast: true });
            room.on('participantConnected', () => renderGrid());
            room.on('trackSubscribed', (track, pub, participant) => attachTrack(track, participant));
            room.on('participantDisconnected', () => renderGrid());
            room.on('disconnected', () => { window.location.href = document.getElementById('back-link').href; });
            // Re-render when local participant publishes a track (e.g. camera) so your own video appears
            room.on('trackPublished', (publication, participant) => {
                if (participant === room.localParticipant) renderGrid();
            });
            await room.connect(wsUrl, data.token);
            const roomNameEl = document.getElementById('room-name');
            if (roomNameEl && IS_GROUP_CALL && data.room) {
                roomNameEl.textContent = data.room;
            }
            // Enable mic and camera so the browser prompts and we publish tracks (same pattern as live broadcast)
            const localParticipant = room.localParticipant;
            if (localParticipant) {
                await localParticipant.setMicrophoneEnabled(true);
                await localParticipant.setCameraEnabled(isVideo);
            }
            renderGrid();
            // Local video often appears asynchronously; re-render several times so your own video shows in the grid
            [200, 500, 1000, 1500].forEach(function(ms) { setTimeout(renderGrid, ms); });
            // Notify backend so the caller (e.g. phone) can stop ringback and join the same LiveKit room
            try {
                await fetch('/calls/group/' + SESSION_ID + '/joined', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: csrfHeaders(),
                });
            } catch (e) { /* non-fatal */ }
            const local = room.localParticipant;
            document.getElementById('btn-mute').onclick = async () => {
                if (!local) return;
                muted = !muted;
                await local.setMicrophoneEnabled(!muted);
                const icon = document.getElementById('btn-mute').querySelector('i');
                if (icon) icon.className = muted ? 'bi bi-mic-mute-fill' : 'bi bi-mic-fill';
            };
            const btnVideo = document.getElementById('btn-video');
            if (btnVideo) {
                btnVideo.onclick = async () => {
                    if (!local) return;
                    videoOff = !videoOff;
                    await local.setCameraEnabled(!videoOff);
                    const icon = btnVideo.querySelector('i');
                    if (icon) icon.className = videoOff ? 'bi bi-camera-video-off-fill' : 'bi bi-camera-video-fill';
                };
            }
            document.getElementById('btn-hangup').onclick = async () => {
                try {
                    await notifyServerCallEnded();
                } finally {
                    if (room) {
                        try { await room.disconnect(); } catch (_) {}
                    }
                    window.location.href = document.getElementById('back-link').href;
                }
            };
            document.getElementById('back-link').addEventListener('click', async (e) => {
                if (!room) return;
                e.preventDefault();
                try {
                    await notifyServerCallEnded();
                } finally {
                    try { await room.disconnect(); } catch (_) {}
                    window.location.href = e.currentTarget.href;
                }
            });
        } catch (e) {
            const backUrl = document.referrer || '{{ route("chat.index") }}';
            const isLiveKitLoadError = (e.message || '').indexOf('LiveKit') !== -1;
            container.innerHTML = '<div class="alert alert-danger mx-3"><h5 class="alert-heading">' + (isLiveKitLoadError ? 'Could not load call' : 'Call error') + '</h5><p class="mb-2">' + (e.message || e) + '</p><p class="mb-0 small text-muted">Try refreshing the page or use the link from your notification/chat again.</p><a href="' + backUrl + '" class="btn btn-primary mt-3">Leave</a></div>';
        }
    }
    document.addEventListener('DOMContentLoaded', function() {
        setupCallSignalListener();
        startCallStatusPoll();
        run();
    });
})();
</script>
@endif
@endpush
@endsection
