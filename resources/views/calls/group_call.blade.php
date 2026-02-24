@extends('layouts.app')

@section('title', 'Group Call - ' . config('app.name', 'GekyChat'))

@section('content')
<div class="group-call-page vh-100 d-flex flex-column bg-dark">
    <div class="d-flex align-items-center justify-content-between p-3 border-bottom border-secondary">
        <a href="{{ route('groups.index') }}" class="btn btn-sm btn-outline-light" id="back-link">
            <i class="bi bi-arrow-left me-1"></i> Leave
        </a>
        <span class="text-white fw-bold">Group call</span>
        <span class="text-white-50 small" id="room-name">—</span>
    </div>
    <div id="group-call-container" class="flex-grow-1 d-flex align-items-center justify-content-center p-3 position-relative min-h-0">
        <div class="text-center text-white">
            <div class="spinner-border" role="status"></div>
            <p class="mt-3 mb-0">Connecting...</p>
        </div>
    </div>
    <div class="p-3 border-top border-secondary d-flex justify-content-center gap-3">
        <button type="button" class="btn btn-lg btn-outline-light rounded-circle p-3" id="btn-mute" title="Mute">
            <i class="bi bi-mic-fill"></i>
        </button>
        <button type="button" class="btn btn-lg btn-danger rounded-circle p-3" id="btn-hangup" title="End call">
            <i class="bi bi-telephone-fill"></i>
        </button>
        <button type="button" class="btn btn-lg btn-outline-light rounded-circle p-3" id="btn-video" title="Video">
            <i class="bi bi-camera-video-fill"></i>
        </button>
    </div>
</div>

@push('scripts')
<script src="https://unpkg.com/livekit-client@1.15.13/dist/livekit-client.umd.min.js" onerror="this.onerror=null;this.src='https://cdn.jsdelivr.net/npm/livekit-client@1.15.13/dist/livekit-client.umd.min.js';"></script>
<script>
(function() {
    const SESSION_ID = {{ (int) $sessionId }};
    const urlParams = new URLSearchParams(window.location.search);
    const isVideo = (urlParams.get('type') || 'video') === 'video';

    let room = null;
    let muted = false;
    let videoOff = false;

    function waitForLiveKit() {
        return new Promise((resolve, reject) => {
            if (typeof window.LivekitClient !== 'undefined') { window.LiveKit = window.LivekitClient; resolve(); return; }
            if (typeof window.LiveKit !== 'undefined') { resolve(); return; }
            let attempts = 0;
            const t = setInterval(() => {
                attempts++;
                if (typeof window.LivekitClient !== 'undefined') { clearInterval(t); window.LiveKit = window.LivekitClient; resolve(); }
                else if (typeof window.LiveKit !== 'undefined') { clearInterval(t); resolve(); }
                else if (attempts >= 100) { clearInterval(t); reject(new Error('LiveKit failed to load')); }
            }, 100);
        });
    }

    function renderGrid() {
        const container = document.getElementById('group-call-container');
        if (!container || !room) return;
        const participants = [room.localParticipant, ...Array.from(room.remoteParticipants.values())].filter(Boolean);
        container.innerHTML = '<div class="row g-2 w-100 justify-content-center" id="participant-grid"></div>';
        const grid = document.getElementById('participant-grid');
        participants.forEach(p => {
            const vid = document.createElement('video');
            vid.autoplay = true;
            vid.playsInline = true;
            vid.muted = p === room.localParticipant;
            vid.className = 'rounded bg-secondary';
            vid.style.objectFit = 'cover';
            vid.style.width = '100%';
            vid.style.maxWidth = participants.length <= 2 ? '400px' : '200px';
            vid.style.height = 'auto';
            const pub = p.videoTrackPublications && (Array.from(p.videoTrackPublications.values())[0]);
            if (pub && pub.track) pub.track.attach(vid);
            else { vid.style.background = '#333'; vid.appendChild(document.createTextNode(p.name || 'No video')); }
            const wrap = document.createElement('div');
            wrap.className = 'col-auto position-relative';
            wrap.innerHTML = '<span class="position-absolute bottom-0 start-0 m-1 badge bg-dark">' + (p === room.localParticipant ? 'You' : (p.name || p.identity)) + '</span>';
            wrap.insertBefore(vid, wrap.firstChild);
            grid.appendChild(wrap);
        });
    }

    function attachTrack(track, participant) {
        const grid = document.getElementById('participant-grid');
        if (!grid) return;
        const vid = document.createElement('video');
        vid.autoplay = true;
        vid.playsInline = true;
        vid.muted = participant === room.localParticipant;
        vid.className = 'rounded bg-secondary';
        vid.style.objectFit = 'cover';
        vid.style.maxWidth = '200px';
        track.attach(vid);
        const wrap = document.createElement('div');
        wrap.className = 'col-auto';
        wrap.innerHTML = '<span class="badge bg-dark">' + (participant.name || participant.identity) + '</span>';
        wrap.insertBefore(vid, wrap.firstChild);
        grid.appendChild(wrap);
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
            await room.connect(wsUrl, data.token, { audio: true, video: isVideo });
            document.getElementById('room-name').textContent = data.room || '';
            renderGrid();
            // Notify backend so the caller (e.g. phone) can stop ringback and join the same LiveKit room
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                await fetch('/calls/group/' + SESSION_ID + '/joined', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {})
                    }
                });
            } catch (e) { /* non-fatal */ }
            const local = room.localParticipant;
            document.getElementById('btn-mute').onclick = async () => {
                muted = !muted;
                await local.setMicrophoneEnabled(!muted);
                document.getElementById('btn-mute').querySelector('i').className = muted ? 'bi bi-mic-mute-fill' : 'bi bi-mic-fill';
            };
            document.getElementById('btn-video').onclick = async () => {
                videoOff = !videoOff;
                await local.setCameraEnabled(!videoOff);
                document.getElementById('btn-video').querySelector('i').className = videoOff ? 'bi bi-camera-video-off-fill' : 'bi bi-camera-video-fill';
            };
            document.getElementById('btn-hangup').onclick = async () => {
                await room.disconnect();
                window.history.back();
            };
        } catch (e) {
            container.innerHTML = '<div class="alert alert-danger"><p>' + (e.message || e) + '</p><a href="' + (document.referrer || '{{ route("groups.index") }}') + '" class="btn btn-primary">Back</a></div>';
        }
    }
    document.addEventListener('DOMContentLoaded', run);
})();
</script>
@endpush
@endsection
