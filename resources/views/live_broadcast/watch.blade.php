@extends('layouts.app')

@section('title', 'Watch Live Broadcast - ' . config('app.name', 'GekyChat'))

@section('content')
<div class="broadcast-watch-page h-100 d-flex flex-column">
    <div class="broadcast-watch-header border-bottom px-3 py-2 d-flex align-items-center justify-content-between flex-shrink-0">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('live-broadcast.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
            <div>
                <h5 class="mb-0 d-flex align-items-center gap-2">
                    <span class="badge bg-danger rounded-pill live-pulse">LIVE</span>
                    <span id="broadcast-title">Loading...</span>
                </h5>
            </div>
        </div>
        <button class="btn btn-danger btn-sm" id="end-broadcast-btn" style="display: none;">
            <i class="bi bi-stop-circle me-1"></i> End Broadcast
        </button>
    </div>
    
    <div class="broadcast-watch-main flex-grow-1 d-flex flex-column min-h-0 position-relative">
        <div id="broadcast-container" class="broadcast-container-inner flex-grow-1 d-flex align-items-center justify-content-center text-center p-4">
            <div>
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-3 text-muted mb-0">Connecting to broadcast...</p>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .broadcast-watch-page { min-height: 100%; height: 100%; }
    .broadcast-watch-header { min-height: 52px; flex-shrink: 0; }
    .broadcast-watch-main { min-height: 60vh; }
    
    .broadcast-container-inner {
        position: relative;
        background: #000;
        min-height: 280px;
    }
    
    #broadcast-video-container {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        background: #000;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    #broadcast-video-container .broadcast-video-wrap {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    #broadcast-video,
    .broadcast-video-wrap video {
        width: 100%;
        height: 100%;
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        display: block;
    }
    
    .broadcast-placeholder {
        color: rgba(255,255,255,0.6);
        padding: 2rem;
    }
    
    .broadcast-placeholder .icon-placeholder {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    
    .broadcast-controls {
        position: absolute;
        bottom: 24px;
        left: 50%;
        transform: translateX(-50%);
        display: flex;
        gap: 12px;
        z-index: 20;
    }
    
    .broadcast-controls .btn {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }
    
    .live-pulse {
        animation: live-pulse 1.5s ease-in-out infinite;
    }
    @keyframes live-pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    
    .drag-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        z-index: 10000;
        display: none;
        align-items: center;
        justify-content: center;
        border: 4px dashed #007bff;
        pointer-events: none;
    }
    .drag-overlay.active { display: flex; }
    .drag-overlay-content { text-align: center; color: white; font-size: 24px; }
</style>
@endpush

@push('scripts')
{{-- Load LiveKit client with fallback CDNs --}}
<script>
    // Try loading from primary CDN (unpkg)
    (function() {
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/livekit-client@1.15.13/dist/livekit-client.umd.min.js';
        
        script.onload = function() {
            console.log('✅ LiveKit loaded from unpkg.com');
        };
        
        script.onerror = function() {
            console.warn('⚠️ Failed to load from unpkg.com, trying jsDelivr...');
            
            // Fallback to jsDelivr
            const fallbackScript = document.createElement('script');
            fallbackScript.src = 'https://cdn.jsdelivr.net/npm/livekit-client@1.15.13/dist/livekit-client.umd.min.js';
            
            fallbackScript.onload = function() {
                console.log('✅ LiveKit loaded from jsDelivr');
            };
            
            fallbackScript.onerror = function() {
                console.error('❌ Failed to load LiveKit from both CDNs');
            };
            
            document.head.appendChild(fallbackScript);
        };
        
        document.head.appendChild(script);
    })();
</script>
<script>
let room = null;
let remoteParticipant = null;
let localParticipant = null;

// Wait for LiveKit client to load
function waitForLiveKit() {
    return new Promise((resolve, reject) => {
        // Check if LiveKit is already loaded
        // LiveKit UMD build can be exposed as window.LivekitClient or window.LiveKit
        if (typeof window.LivekitClient !== 'undefined') {
            console.log('LiveKit found as window.LivekitClient');
            window.LiveKit = window.LivekitClient; // Normalize to LiveKit
            resolve();
            return;
        }
        
        if (typeof window.LiveKit !== 'undefined') {
            console.log('LiveKit found as window.LiveKit');
            resolve();
            return;
        }
        
        // Wait for script to load
        let attempts = 0;
        const maxAttempts = 100; // 10 seconds max wait
        
        const checkInterval = setInterval(() => {
            attempts++;
            
            // Check multiple possible global names
            if (typeof window.LivekitClient !== 'undefined') {
                clearInterval(checkInterval);
                console.log('LiveKit loaded as LivekitClient after', attempts * 100, 'ms');
                window.LiveKit = window.LivekitClient; // Normalize
                resolve();
            } else if (typeof window.LiveKit !== 'undefined') {
                clearInterval(checkInterval);
                console.log('LiveKit loaded as LiveKit after', attempts * 100, 'ms');
                resolve();
            } else if (attempts >= maxAttempts) {
                clearInterval(checkInterval);
                const availableGlobals = Object.keys(window).filter(k => 
                    k.toLowerCase().includes('live') || k.toLowerCase().includes('kit')
                );
                console.error('Available globals containing "live" or "kit":', availableGlobals);
                console.error('Full window object keys (first 50):', Object.keys(window).slice(0, 50));
                
                reject(new Error('LiveKit client library failed to load after 10 seconds. This may be due to a network issue or browser extension blocking the CDN. Please check the browser console for errors.'));
            }
        }, 100);
    });
}

// Get LiveKit client
function getLiveKitClient() {
    // Try normalized LiveKit first
    if (typeof window.LiveKit !== 'undefined') {
        return window.LiveKit;
    }
    // Fallback to LivekitClient
    if (typeof window.LivekitClient !== 'undefined') {
        window.LiveKit = window.LivekitClient;
        return window.LiveKit;
    }
    throw new Error('LiveKit client library not found. The script may have failed to load. Please refresh the page.');
}

document.addEventListener('DOMContentLoaded', async function() {
    // Wait for LiveKit to load
    try {
        await waitForLiveKit();
        console.log('LiveKit client ready');
    } catch (error) {
        console.error('Failed to load LiveKit client:', error);
        document.getElementById('broadcast-container').innerHTML = `
            <div class="alert alert-danger">
                <h5>Error Loading LiveKit Client</h5>
                <p>${error.message}</p>
                <p>Please refresh the page or check your internet connection.</p>
                <p><small>If the problem persists, check the browser console for more details.</small></p>
            </div>
        `;
        return;
    }
    const broadcastId = {{ $broadcastId ?? 'null' }};
    const isBroadcaster = {{ ($isBroadcaster ?? false) ? 'true' : 'false' }};
    
    if (!broadcastId) {
        document.getElementById('broadcast-container').innerHTML = `
            <div class="alert alert-danger">
                Invalid broadcast ID
            </div>
        `;
        return;
    }
    
    loadBroadcastInfo(broadcastId, isBroadcaster);
    
    async function loadBroadcastInfo(id, isBroadcaster) {
        try {
            // Load broadcast info (use slug if available, fallback to ID)
            const broadcastSlug = {!! isset($broadcastSlug) ? json_encode($broadcastSlug) : json_encode('id') !!};
            const infoResponse = await fetch(`/live-broadcast/${broadcastSlug}/info`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            if (!infoResponse.ok) {
                throw new Error('Failed to load broadcast info');
            }
            
            const infoData = await infoResponse.json();
            const broadcast = infoData.data || infoData;
            
            document.getElementById('broadcast-title').textContent = broadcast.title || 'Untitled Broadcast';
            
            if (broadcast.status !== 'live') {
                document.getElementById('broadcast-container').innerHTML = `
                    <div class="alert alert-warning">
                        <h5>${escapeHtml(broadcast.title || 'Untitled Broadcast')}</h5>
                        <p>This broadcast has ended.</p>
                        ${broadcast.ended_at ? `<p class="mb-0">It ended at: ${new Date(broadcast.ended_at).toLocaleString()}</p>` : ''}
                    </div>
                `;
                return;
            }
            
            // Join the broadcast
            await joinBroadcast(id, isBroadcaster, broadcast.room_name);
            
        } catch (error) {
            console.error('Error loading broadcast:', error);
            const container = document.getElementById('broadcast-container');
            if (container) {
                let errorMessage = error.message || 'Failed to load broadcast. Please try again.';
                let configGuidance = '';
                
                if (errorMessage.includes('LiveKit') || errorMessage.includes('server')) {
                    configGuidance = `
                        <p class="small text-muted mb-0 mt-2">
                            <strong>Note:</strong> If you see "LiveKit server not found", please ensure:
                            <ul class="mb-0 mt-2">
                                <li>LiveKit server is running and accessible</li>
                                <li>LIVEKIT_URL is configured in your .env file</li>
                                <li>LIVEKIT_API_KEY and LIVEKIT_API_SECRET are set</li>
                            </ul>
                        </p>
                    `;
                }
                
                container.innerHTML = `
                    <div class="alert alert-danger">
                        <h5>Error</h5>
                        <p>${errorMessage}</p>
                        ${configGuidance}
                        <button class="btn btn-primary mt-3" onclick="location.reload()">Retry</button>
                    </div>
                `;
            }
        }
    }
    
    async function joinBroadcast(broadcastId, isBroadcaster, roomName) {
        try {
            // Get LiveKit token
            // Broadcasters also use the join endpoint to rejoin their own broadcast
            // Use slug if available, fallback to ID for backward compatibility
            const broadcastSlug = {!! isset($broadcastSlug) ? json_encode($broadcastSlug) : json_encode($broadcastId) !!};
            const endpoint = `/live-broadcast/${broadcastSlug}/join`;
            
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Failed to join broadcast');
            }
            
            const data = await response.json();
            const token = data.token || data.data?.token;
            let wsUrl = data.websocket_url || data.data?.websocket_url || 'ws://localhost:7880';
            
            if (!token) {
                throw new Error('Missing LiveKit token. Please check server configuration.');
            }
            
            if (!wsUrl || wsUrl === 'ws://localhost:7880') {
                console.warn('LiveKit WebSocket URL is not configured or using default. Set LIVEKIT_URL in server .env to your LiveKit server (e.g. wss://livekit.yourdomain.com).');
            }
            
            if (!wsUrl.startsWith('ws://') && !wsUrl.startsWith('wss://')) {
                console.warn('Invalid WebSocket URL format, prepending ws://');
                wsUrl = 'ws://' + wsUrl;
            }
            
            // Upgrade ws:// -> wss:// when the page is served over HTTPS (Mixed Content is blocked).
            const wasUpgraded = window.location.protocol === 'https:' && wsUrl.startsWith('ws://');
            if (wasUpgraded) {
                wsUrl = 'wss://' + wsUrl.slice(5);
                console.debug('LiveKit: upgraded URL to wss for HTTPS page. Final URL:', wsUrl);
            }
            console.debug('LiveKit: connecting to', wsUrl, '(room:', data.room_name || data.data?.room_name + ')');
            
            // Initialize LiveKit room
            const LiveKit = getLiveKitClient();
            room = new LiveKit.Room({
                adaptiveStream: true,
                dynacast: true,
            });
            
            // Set up event listeners (room-level)
            room.on('participantConnected', participant => {
                console.log('Participant connected:', participant.identity);
                if (!isBroadcaster) {
                    remoteParticipant = participant;
                    setupRemoteVideo(participant);
                    subscribeToParticipantTracks(participant);
                }
            });
            
            room.on('trackSubscribed', (track, publication, participant) => {
                console.log('Track subscribed:', track.kind, 'from', participant.identity);
                if (track.kind === 'video') {
                    attachVideoTrack(track, participant.identity !== room.localParticipant?.identity);
                } else if (track.kind === 'audio') {
                    attachAudioTrack(track);
                }
            });
            
            // When a remote participant publishes a track, attach if track is already set (else trackSubscribed will fire)
            room.on('trackPublished', (publication, participant) => {
                if (!participant || isBroadcaster || participant.identity === room.localParticipant?.identity) return;
                if (publication && publication.kind === 'video' && publication.track) {
                    console.log('Track published (video) from', participant.identity);
                    attachVideoTrack(publication.track, true);
                }
            });
            
            room.on('trackUnsubscribed', (track, publication, participant) => {
                console.log('Track unsubscribed:', track.kind);
                track.detach();
            });
            
            room.on('disconnected', () => {
                console.log('Disconnected from room');
                document.getElementById('broadcast-container').innerHTML = `
                    <div class="alert alert-info">
                        <p>Disconnected from broadcast</p>
                    </div>
                `;
            });
            
            // Connect to room with error handling
            try {
                await room.connect(wsUrl, token);
                console.log('Connected to room:', room.name);
            } catch (connectError) {
                console.error('Failed to connect to LiveKit room:', connectError);
                let errorMessage = 'Failed to connect to LiveKit server. ';
                
                if (connectError.message && connectError.message.includes('404')) {
                    errorMessage += 'LiveKit server not found. Please check server configuration.';
                } else if (connectError.message && connectError.message.includes('timeout')) {
                    errorMessage += 'Connection timeout. LiveKit server may be unreachable.';
                } else if (connectError.message && connectError.message.includes('ECONNREFUSED')) {
                    errorMessage += 'Cannot connect to LiveKit server. Please verify the server is running and the URL is correct.';
                } else if (connectError.message && (connectError.message.includes('closed') || connectError.message.includes('Websocket'))) {
                    errorMessage += 'WebSocket was closed. Ensure LIVEKIT_URL in server .env is the public LiveKit URL (e.g. wss://livekit.yourdomain.com), not localhost, and the LiveKit server is running and reachable.';
                } else {
                    errorMessage += connectError.message || 'Unknown error occurred.';
                }
                
                throw new Error(errorMessage);
            }
            
            // Render UI first so #broadcast-video-container and #broadcast-video exist before we attach any tracks
            renderBroadcastUI(isBroadcaster);
            
            // If broadcaster, enable camera and microphone then attach tracks (container already exists)
            if (isBroadcaster && room.localParticipant) {
                localParticipant = room.localParticipant;
                
                // Attach local video when it becomes available (async after setCameraEnabled)
                const attachLocalVideo = (publication) => {
                    if (publication?.track && publication.kind === 'video') {
                        attachVideoTrack(publication.track, false);
                    }
                };
                room.localParticipant.on('localTrackPublished', (publication) => attachLocalVideo(publication));
                
                await localParticipant.setCameraEnabled(true);
                await localParticipant.setMicrophoneEnabled(true);
                
                // Attach if track is already available
                const localVideoPubs = room.localParticipant?.videoTrackPublications;
                const localPubsList = localVideoPubs == null ? [] : (Array.isArray(localVideoPubs) ? localVideoPubs : (typeof localVideoPubs.values === 'function' ? Array.from(localVideoPubs.values()) : []));
                localPubsList.forEach((publication) => attachLocalVideo(publication));
                
                document.getElementById('end-broadcast-btn').style.display = 'block';
                document.getElementById('end-broadcast-btn').addEventListener('click', () => {
                    endBroadcast(broadcastId);
                });
            }
            
            // For viewers: attach video from any participant already in the room (e.g. broadcaster on mobile)
            const remotes = room.remoteParticipants;
            const remoteList = remotes == null ? [] : (Array.isArray(remotes) ? remotes : (typeof remotes.values === 'function' ? Array.from(remotes.values()) : (typeof remotes.forEach === 'function' ? (() => { const a = []; remotes.forEach(p => a.push(p)); return a; })() : [])));
            if (Array.isArray(remoteList)) {
                remoteList.forEach((participant) => {
                    if (participant) {
                        setupRemoteVideo(participant);
                        subscribeToParticipantTracks(participant);
                    }
                });
            }
            
        } catch (error) {
            console.error('Error joining broadcast:', error);
            
            // Show user-friendly error message
            const container = document.getElementById('broadcast-container');
            if (container) {
                let errorMessage = error.message || 'An error occurred while joining the broadcast. Please try again.';
                let configGuidance = '';
                
                if (errorMessage.includes('LiveKit') || errorMessage.includes('server') || errorMessage.includes('404')) {
                    configGuidance = `
                        <p class="small text-muted mb-0 mt-2">
                            <strong>Note:</strong> If you see "LiveKit server not found", please ensure:
                            <ul class="mb-0 mt-2">
                                <li>LiveKit server is running and accessible</li>
                                <li>LIVEKIT_URL is configured in your .env file</li>
                                <li>LIVEKIT_API_KEY and LIVEKIT_API_SECRET are set</li>
                            </ul>
                        </p>
                    `;
                }
                
                container.innerHTML = `
                    <div class="alert alert-danger">
                        <h5>Failed to Join Broadcast</h5>
                        <p>${errorMessage}</p>
                        ${configGuidance}
                        <button class="btn btn-primary mt-3" onclick="location.reload()">Retry</button>
                    </div>
                `;
            }
            
            throw error;
        }
    }
    
    function attachVideoTrack(track, isRemote = true) {
        const container = document.getElementById('broadcast-video-container');
        if (!container) return;
        
        const wrap = container.querySelector('.broadcast-video-wrap');
        const videoElement = document.getElementById('broadcast-video') || document.createElement('video');
        videoElement.id = 'broadcast-video';
        videoElement.autoplay = true;
        videoElement.playsInline = true;
        videoElement.muted = !isRemote; // Mute local video to avoid feedback
        
        track.attach(videoElement);
        
        if (!videoElement.parentNode && wrap) {
            wrap.appendChild(videoElement);
        } else if (!videoElement.parentNode) {
            container.appendChild(videoElement);
        }
        videoElement.style.display = 'block';
        const placeholder = document.getElementById('broadcast-placeholder');
        if (placeholder) placeholder.style.display = 'none';
    }
    
    function attachAudioTrack(track) {
        const audioElement = document.createElement('audio');
        audioElement.autoplay = true;
        track.attach(audioElement);
        document.body.appendChild(audioElement);
    }
    
    function setupRemoteVideo(participant) {
        if (!participant) return;
        const pubs = participant.videoTrackPublications;
        const list = pubs == null ? [] : (Array.isArray(pubs) ? pubs : (typeof pubs.values === 'function' ? Array.from(pubs.values()) : []));
        if (!Array.isArray(list)) return;
        list.forEach((publication) => {
            if (publication?.track) {
                attachVideoTrack(publication.track, true);
            }
        });
    }
    
    // Subscribe to track events on a remote participant (so we attach when mobile broadcaster's video arrives)
    function subscribeToParticipantTracks(participant) {
        if (!participant || !room) return;
        const isRemote = participant.identity !== room.localParticipant?.identity;
        if (!isRemote) return;
        if (typeof participant.on === 'function') {
            participant.on('trackSubscribed', (track, publication) => {
                if (track && track.kind === 'video') {
                    console.log('Remote participant trackSubscribed (video):', participant.identity);
                    attachVideoTrack(track, true);
                } else if (track && track.kind === 'audio') {
                    attachAudioTrack(track);
                }
            });
            participant.on('trackPublished', (publication) => {
                if (publication?.track && publication.kind === 'video') {
                    console.log('Remote participant trackPublished (video):', participant.identity);
                    attachVideoTrack(publication.track, true);
                }
            });
        }
    }
    
    function renderBroadcastUI(isBroadcaster) {
        const container = document.getElementById('broadcast-container');
        container.classList.add('position-relative');
        container.innerHTML = `
            <div id="broadcast-video-container">
                <div class="broadcast-video-wrap">
                    <div id="broadcast-placeholder" class="broadcast-placeholder position-absolute top-0 start-0 end-0 bottom-0 d-flex flex-column align-items-center justify-content-center">
                        <span class="icon-placeholder">${isBroadcaster ? '📷' : '⏳'}</span>
                        <p class="mb-0">${isBroadcaster ? 'Starting camera...' : 'Waiting for video...'}</p>
                    </div>
                    <video id="broadcast-video" autoplay playsInline muted style="display: none;"></video>
                </div>
                ${isBroadcaster ? `
                    <div class="broadcast-controls">
                        <button class="btn btn-danger btn-sm" id="toggle-camera-btn">
                            <i class="bi bi-camera-video"></i>
                        </button>
                        <button class="btn btn-danger btn-sm" id="toggle-mic-btn">
                            <i class="bi bi-mic"></i>
                        </button>
                    </div>
                ` : ''}
            </div>
        `;
        
        if (isBroadcaster) {
            // Setup control buttons
            document.getElementById('toggle-camera-btn').addEventListener('click', async () => {
                if (localParticipant) {
                    const enabled = !localParticipant.isCameraEnabled();
                    await localParticipant.setCameraEnabled(enabled);
                    document.getElementById('toggle-camera-btn').innerHTML = 
                        enabled ? '<i class="bi bi-camera-video"></i>' : '<i class="bi bi-camera-video-off"></i>';
                }
            });
            
            document.getElementById('toggle-mic-btn').addEventListener('click', async () => {
                if (localParticipant) {
                    const enabled = !localParticipant.isMicrophoneEnabled();
                    await localParticipant.setMicrophoneEnabled(enabled);
                    document.getElementById('toggle-mic-btn').innerHTML = 
                        enabled ? '<i class="bi bi-mic"></i>' : '<i class="bi bi-mic-mute"></i>';
                }
            });
        }
    }
    
    async function endBroadcast(broadcastId) {
        if (!confirm('Are you sure you want to end this broadcast?')) {
            return;
        }
        
        try {
            if (room) {
                await room.disconnect();
            }
            
            // Use slug if available, fallback to ID
            const broadcastSlug = {!! isset($broadcastSlug) ? json_encode($broadcastSlug) : json_encode($broadcastId) !!};
            const response = await fetch(`/live-broadcast/${broadcastSlug}/end`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            if (response.ok) {
                window.location.href = '/live-broadcast';
            } else {
                alert('Failed to end broadcast');
            }
        } catch (error) {
            console.error('Error ending broadcast:', error);
            alert('Failed to end broadcast');
        }
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Cleanup on page unload
    window.addEventListener('beforeunload', () => {
        if (room) {
            room.disconnect();
        }
    });
});
</script>
@endpush
@endsection
