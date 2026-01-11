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
<script src="https://unpkg.com/livekit-client@latest/dist/livekit-client.umd.js"></script>
<script>
let room = null;
let remoteParticipant = null;
let localParticipant = null;

document.addEventListener('DOMContentLoaded', function() {
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
            // Load broadcast info
            const infoResponse = await fetch(`/live-broadcast/${id}/info`, {
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
            document.getElementById('broadcast-container').innerHTML = `
                <div class="alert alert-danger">
                    <h5>Error</h5>
                    <p>${error.message || 'Failed to load broadcast. Please try again.'}</p>
                </div>
            `;
        }
    }
    
    async function joinBroadcast(broadcastId, isBroadcaster, roomName) {
        try {
            // Get LiveKit token
            // Broadcasters also use the join endpoint to rejoin their own broadcast
            const endpoint = `/api/v1/live/${broadcastId}/join`;
            
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
            const wsUrl = data.websocket_url || data.data?.websocket_url || 'ws://localhost:7880';
            
            if (!token || !wsUrl) {
                throw new Error('Missing token or websocket URL');
            }
            
            // Initialize LiveKit room
            room = new LiveKitClient.Room({
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
            
            // Connect to room
            await room.connect(wsUrl, token);
            console.log('Connected to room:', room.name);
            
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
            
            const response = await fetch(`/api/v1/live/${broadcastId}/end`, {
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
