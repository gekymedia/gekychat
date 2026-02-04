@extends('layouts.app')

@section('title', 'Watch Live Broadcast - ' . config('app.name', 'GekyChat'))

@section('content')
<div class="h-100 d-flex flex-column">
    <div class="chat-header border-bottom p-3">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('live-broadcast.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
                <div>
                    <h4 class="mb-0">Live Broadcast</h4>
                    <small class="text-muted" id="broadcast-title">Loading...</small>
                </div>
            </div>
            <button class="btn btn-danger btn-sm" id="end-broadcast-btn" style="display: none;">
                <i class="bi bi-stop-circle me-1"></i> End Broadcast
            </button>
        </div>
    </div>
    
    <div class="flex-grow-1 overflow-auto p-4">
        <div id="broadcast-container" class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3 text-muted">Loading broadcast...</p>
        </div>
    </div>
</div>

@push('styles')
<style>
    #broadcast-video-container {
        position: relative;
        width: 100%;
        max-width: 1200px;
        margin: 0 auto;
        background: #000;
        border-radius: 8px;
        overflow: hidden;
    }
    
    #broadcast-video {
        width: 100%;
        height: auto;
        display: block;
        max-height: 80vh;
        object-fit: contain;
    }
    
    .broadcast-controls {
        position: absolute;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        display: flex;
        gap: 10px;
        z-index: 10;
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
    
    .drag-overlay.active {
        display: flex;
    }
    
    .drag-overlay-content {
        text-align: center;
        color: white;
        font-size: 24px;
    }
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
                console.warn('LiveKit WebSocket URL is not configured or using default. LiveKit server may not be accessible.');
                // Show warning but continue - user will see connection error if server is not available
            }
            
            // Validate WebSocket URL format
            if (!wsUrl.startsWith('ws://') && !wsUrl.startsWith('wss://')) {
                console.warn('Invalid WebSocket URL format, prepending ws://');
                wsUrl = 'ws://' + wsUrl;
            }
            
            // Initialize LiveKit room
            const LiveKit = getLiveKitClient();
            room = new LiveKit.Room({
                adaptiveStream: true,
                dynacast: true,
            });
            
            // Set up event listeners
            room.on('participantConnected', participant => {
                console.log('Participant connected:', participant.identity);
                if (!isBroadcaster) {
                    remoteParticipant = participant;
                    setupRemoteVideo(participant);
                }
            });
            
            room.on('trackSubscribed', (track, publication, participant) => {
                console.log('Track subscribed:', track.kind);
                if (track.kind === 'video') {
                    attachVideoTrack(track, participant.identity !== room.localParticipant?.identity);
                } else if (track.kind === 'audio') {
                    attachAudioTrack(track);
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
                } else {
                    errorMessage += connectError.message || 'Unknown error occurred.';
                }
                
                throw new Error(errorMessage);
            }
            
            // If broadcaster, enable camera and microphone
            if (isBroadcaster && room.localParticipant) {
                localParticipant = room.localParticipant;
                await localParticipant.setCameraEnabled(true);
                await localParticipant.setMicrophoneEnabled(true);
                
                // Get local video track
                room.localParticipant.videoTrackPublications.forEach((publication) => {
                    if (publication.track) {
                        attachVideoTrack(publication.track, false);
                    }
                });
                
                // Show end broadcast button
                document.getElementById('end-broadcast-btn').style.display = 'block';
                document.getElementById('end-broadcast-btn').addEventListener('click', () => {
                    endBroadcast(broadcastId);
                });
            }
            
            // Render UI
            renderBroadcastUI(isBroadcaster);
            
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
        
        const videoElement = document.getElementById('broadcast-video') || document.createElement('video');
        videoElement.id = 'broadcast-video';
        videoElement.autoplay = true;
        videoElement.playsInline = true;
        videoElement.muted = !isRemote; // Mute local video to avoid feedback
        
        track.attach(videoElement);
        
        if (!document.getElementById('broadcast-video')) {
            container.appendChild(videoElement);
        }
    }
    
    function attachAudioTrack(track) {
        const audioElement = document.createElement('audio');
        audioElement.autoplay = true;
        track.attach(audioElement);
        document.body.appendChild(audioElement);
    }
    
    function setupRemoteVideo(participant) {
        participant.videoTrackPublications.forEach((publication) => {
            if (publication.track) {
                attachVideoTrack(publication.track, true);
            }
        });
    }
    
    function renderBroadcastUI(isBroadcaster) {
        const container = document.getElementById('broadcast-container');
        container.innerHTML = `
            <div id="broadcast-video-container">
                <video id="broadcast-video" autoplay playsInline muted></video>
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
