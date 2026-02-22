/**
 * CallManager - Handles WebRTC voice and video calls
 * Includes Picture-in-Picture support and call state persistence
 * Uses window.__GekyChatCallUrls (from Blade route names) so web always hits session-auth routes.
 * Fallback to same-origin /calls/* so we never hit API Sanctum and get "Unauthenticated".
 */
function getCallUrl(key, sessionId = null) {
    const urls = window.__GekyChatCallUrls || {};
    if (sessionId != null) {
        const template = urls[`${key}Template`];
        if (template) return template.replace(':session', sessionId);
        return `/calls/${sessionId}/${key}`;
    }
    const base = urls[key];
    if (base) return base;
    // Fallback: use same-origin web routes (session auth), never /api/v1 (Sanctum)
    switch (key) {
        case 'config': return '/calls/config';
        case 'start': return '/calls/start';
        default: return null;
    }
}

export class CallManager {
    constructor() {
        this.currentCall = null;
        this.localStream = null;
        this.remoteStream = null;
        this.peerConnection = null;
        this.isCaller = false;
        this.callType = 'voice'; // 'voice' or 'video'
        this.isMinimized = false;
        this.callUserName = null;
        this.callUserAvatar = null;
        /** When callee receives an offer before clicking Answer, store it and apply in acceptCall() */
        this.pendingOffer = null;

        // WebRTC configuration (will be loaded from backend)
        this.rtcConfig = {
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' }
            ]
        };
        
        this.init();
        
        // Load TURN config from backend (async, non-blocking)
        this.loadTurnConfig();
        
        // Restore active call on page load (if any)
        this.restoreCallState();
    }
    
    init() {
        this.setupUI();
        this.setupEchoListeners();
        
        // Save call state before page unload
        window.addEventListener('beforeunload', () => {
            this.saveCallState();
        });
    }
    
    setupUI() {
        // Voice call button (direct chat)
        const voiceCallBtn = document.getElementById('voice-call-btn');
        if (voiceCallBtn) {
            voiceCallBtn.addEventListener('click', () => {
                const userId = voiceCallBtn.dataset.userId;
                if (userId) {
                    this.startCall(userId, 'voice');
                }
            });
        }
        
        // Video call button (direct chat)
        const videoCallBtn = document.getElementById('video-call-btn');
        if (videoCallBtn) {
            videoCallBtn.addEventListener('click', () => {
                const userId = videoCallBtn.dataset.userId;
                if (userId) {
                    this.startCall(userId, 'video');
                }
            });
        }
        
        // Group voice call button
        const groupVoiceCallBtn = document.getElementById('group-voice-call-btn');
        if (groupVoiceCallBtn) {
            groupVoiceCallBtn.addEventListener('click', () => {
                const groupId = groupVoiceCallBtn.dataset.groupId;
                if (groupId) {
                    this.startGroupCall(groupId, 'voice');
                }
            });
        }
        
        // Group video call button
        const groupVideoCallBtn = document.getElementById('group-video-call-btn');
        if (groupVideoCallBtn) {
            groupVideoCallBtn.addEventListener('click', () => {
                const groupId = groupVideoCallBtn.dataset.groupId;
                if (groupId) {
                    this.startGroupCall(groupId, 'video');
                }
            });
        }
        
        // Call control buttons
        const muteBtn = document.getElementById('call-mute-btn');
        if (muteBtn) {
            muteBtn.addEventListener('click', () => this.toggleMute());
        }
        
        const videoToggleBtn = document.getElementById('call-video-toggle-btn');
        if (videoToggleBtn) {
            videoToggleBtn.addEventListener('click', () => this.toggleVideo());
        }
        
        const endCallBtn = document.getElementById('call-end-btn');
        if (endCallBtn) {
            endCallBtn.addEventListener('click', () => this.endCall());
        }
        
        // Incoming call buttons
        const acceptBtn = document.getElementById('call-accept-btn');
        if (acceptBtn) {
            acceptBtn.addEventListener('click', () => this.acceptCall());
        }
        
        const declineBtn = document.getElementById('call-decline-btn');
        if (declineBtn) {
            declineBtn.addEventListener('click', () => this.declineCall());
        }
        
        const minimizeBtn = document.getElementById('call-minimize-btn');
        if (minimizeBtn) {
            minimizeBtn.addEventListener('click', () => this.minimizeCall());
        }
        
        // Picture-in-Picture button
        const pipBtn = document.getElementById('call-pip-btn');
        if (pipBtn) {
            pipBtn.addEventListener('click', () => this.enterPictureInPicture());
        }
        
        // Minimized call bar buttons
        const maximizeBtn = document.getElementById('call-maximize-btn');
        if (maximizeBtn) {
            maximizeBtn.addEventListener('click', () => this.maximizeCall());
        }
        
        const endMinimizedBtn = document.getElementById('call-end-minimized-btn');
        if (endMinimizedBtn) {
            endMinimizedBtn.addEventListener('click', () => this.endCall());
        }
    }
    
    async loadTurnConfig() {
        try {
            const response = await fetch(getCallUrl('config'), {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                console.warn('Failed to load TURN config, using STUN only');
                return;
            }
            
            const data = await response.json();
            if (data.status === 'success' && data.config) {
                const turnConfig = data.config;
                
                // Add TURN servers if available
                if (turnConfig.turn && Array.isArray(turnConfig.turn) && turnConfig.turn.length > 0) {
                    const turnServers = turnConfig.turn.map(server => ({
                        urls: server.urls,
                        username: server.username || undefined,
                        credential: server.credential || undefined
                    }));
                    
                    // Add TURN servers to existing STUN servers
                    this.rtcConfig.iceServers = [
                        ...this.rtcConfig.iceServers,
                        ...turnServers
                    ];
                    
                    console.log('TURN servers loaded:', turnServers.length);
                }
            }
        } catch (error) {
            console.warn('Failed to load TURN config, using STUN only:', error);
        }
    }
    
    setupEchoListeners() {
        if (!window.Echo) {
            console.warn('Echo not available for call signaling');
            return;
        }
        
        const currentUserId = window.APP?.userId || window.currentUserId;
        if (!currentUserId) {
            console.warn('User ID not available for call signaling');
            return;
        }
        
        // Listen for incoming calls on user's private channel
        // The CallSignal event broadcasts to call.{callee_id} for direct calls
        window.Echo.private(`call.${currentUserId}`)
            .listen('.CallSignal', (event) => {
                this.handleCallSignal(event);
            });
    }
    
    async startCall(userId, type) {
        try {
            this.callType = type;
            this.isCaller = true;
            
            // Get user info from header
            const header = document.querySelector('.chat-header');
            let userData = {};
            let userName = 'User';
            let userAvatar = null;
            
            if (header && header.dataset.userData) {
                try {
                    const userDataStr = header.dataset.userData.trim();
                    if (userDataStr && userDataStr !== '{}' && userDataStr !== '') {
                        userData = JSON.parse(userDataStr);
                        userName = userData.name || 'User';
                        userAvatar = userData.avatar || null;
                    }
                } catch (e) {
                    console.warn('Failed to parse userData from header:', e);
                    // Fallback: try to get name from header text
                    const nameElement = header.querySelector('.chat-header-name, h5, h6');
                    if (nameElement) {
                        userName = nameElement.textContent.trim() || 'User';
                    }
                }
            }
            
            // Store user info for minimized bar
            this.callUserName = userName;
            this.callUserAvatar = userAvatar;
            
            // Request permissions before showing UI
            try {
                await this.requestMediaPermissions(type === 'video');
            } catch (error) {
                console.error('Media permission error:', error);
                alert(`Unable to access ${error.message.includes('video') ? 'camera' : 'microphone'}. Please enable permissions in your browser settings.`);
                return;
            }
            
            // Show call UI
            this.showCallUI(userName, userAvatar, 'calling');
            
            // Create call session (use session-auth URL from Blade so web never hits API Sanctum)
            const response = await fetch(getCallUrl('start'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    callee_id: parseInt(userId),
                    type: type
                })
            });
            
            if (response.status === 401) {
                const data = await response.json().catch(() => ({}));
                const msg = data.message || 'Session expired or not logged in.';
                alert(msg + ' Please log in again to place calls.');
                if (typeof window.location !== 'undefined') window.location.href = '/login';
                this.hideCallUI();
                return;
            }
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                throw new Error(`Server returned non-JSON response: ${text.substring(0, 100)}`);
            }
            const data = await response.json();
            if (data.status === 'success') {
                this.currentCall = {
                    sessionId: data.session_id,
                    userId: userId,
                    type: type
                };
                
                // Save call state for persistence
                this.saveCallState();
                
                // Start WebRTC
                await this.initiateWebRTC();
            } else {
                throw new Error(data.message || 'Failed to start call');
            }
        } catch (error) {
            console.error('Error starting call:', error);
            alert('Failed to start call: ' + error.message);
            this.hideCallUI();
        }
    }
    
    /**
     * Start a group call
     */
    async startGroupCall(groupId, type) {
        try {
            this.callType = type;
            this.isCaller = true;
            
            // Get group info from header
            const header = document.querySelector('.group-header');
            let groupName = 'Group';
            let groupAvatar = null;
            
            if (header) {
                const nameElement = header.querySelector('.group-header-name');
                if (nameElement) {
                    groupName = nameElement.textContent.trim() || 'Group';
                }
                
                const avatarElement = header.querySelector('.avatar-img');
                if (avatarElement) {
                    groupAvatar = avatarElement.src;
                }
            }
            
            // Store group info
            this.callUserName = groupName;
            this.callUserAvatar = groupAvatar;
            
            // Request permissions
            try {
                await this.requestMediaPermissions(type === 'video');
            } catch (error) {
                console.error('Media permission error:', error);
                alert(`Unable to access ${error.message.includes('video') ? 'camera' : 'microphone'}. Please enable permissions in your browser settings.`);
                return;
            }
            
            // Show call UI
            this.showCallUI(groupName, groupAvatar, 'calling');
            
            // Create call session (use session-auth URL from Blade so web never hits API Sanctum)
            const response = await fetch(getCallUrl('start'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    group_id: parseInt(groupId),
                    type: type
                })
            });
            
            if (response.status === 401) {
                const data = await response.json().catch(() => ({}));
                const msg = data.message || 'Session expired or not logged in.';
                alert(msg + ' Please log in again to place calls.');
                if (typeof window.location !== 'undefined') window.location.href = '/login';
                this.hideCallUI();
                return;
            }
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                throw new Error(`Server returned non-JSON response: ${text.substring(0, 100)}`);
            }
            const data = await response.json();
            if (data.status === 'success') {
                this.currentCall = {
                    sessionId: data.session_id,
                    groupId: groupId,
                    type: type
                };
                
                // Save call state for persistence
                this.saveCallState();
                
                // For group calls, the call link will be in the message
                // Users can click the link to join
                console.log('Group call started. Call link:', data.call_link);
                
                // Start WebRTC (group calls may need different handling)
                await this.initiateWebRTC();
            } else {
                throw new Error(data.message || 'Failed to start group call');
            }
        } catch (error) {
            console.error('Error starting group call:', error);
            alert('Failed to start group call: ' + error.message);
            this.hideCallUI();
        }
    }
    
    /**
     * Request media permissions (microphone and optionally video)
     * @param {boolean} requireVideo - Whether video permission is required
     * @returns {Promise<void>}
     */
    async requestMediaPermissions(requireVideo = false) {
        const constraints = {
            audio: true,
            video: requireVideo
        };
        
        try {
            // Try to get media stream to request permissions
            const stream = await navigator.mediaDevices.getUserMedia(constraints);
            // Stop the stream immediately - we just wanted to request permission
            stream.getTracks().forEach(track => track.stop());
            return;
        } catch (error) {
            if (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError') {
                throw new Error('Permission denied. Please allow access to ' + (requireVideo ? 'camera and microphone' : 'microphone') + ' in your browser settings.');
            } else if (error.name === 'NotFoundError' || error.name === 'DevicesNotFoundError') {
                throw new Error('No ' + (requireVideo ? 'camera or microphone' : 'microphone') + ' found. Please connect a device and try again.');
            } else {
                throw new Error('Failed to access media devices: ' + error.message);
            }
        }
    }

    async initiateWebRTC() {
        try {
            // Get user media (permissions already requested in startCall)
            const constraints = {
                audio: true,
                video: this.callType === 'video'
            };
            
            this.localStream = await navigator.mediaDevices.getUserMedia(constraints);
            
            // Set up local video
            if (this.callType === 'video') {
                const localVideo = document.getElementById('local-video');
                if (localVideo) {
                    localVideo.srcObject = this.localStream;
                    localVideo.style.display = 'block';
                }
            }
            
            // Create peer connection
            this.peerConnection = new RTCPeerConnection(this.rtcConfig);
            
            // Add local stream tracks
            this.localStream.getTracks().forEach(track => {
                this.peerConnection.addTrack(track, this.localStream);
            });
            
            // Handle remote stream
            this.peerConnection.ontrack = (event) => {
                const remoteVideo = document.getElementById('remote-video');
                if (remoteVideo) {
                    remoteVideo.srcObject = event.streams[0];
                    remoteVideo.style.display = 'block';
                    document.getElementById('call-video-placeholder').style.display = 'none';
                }
            };
            
            // Handle ICE candidates
            this.peerConnection.onicecandidate = (event) => {
                if (event.candidate) {
                    this.sendSignal({
                        type: 'ice-candidate',
                        candidate: event.candidate
                    });
                }
            };
            
            // Create and send offer only if caller
            if (this.isCaller) {
                const offer = await this.peerConnection.createOffer();
                await this.peerConnection.setLocalDescription(offer);
                
                this.sendSignal({
                    type: 'offer',
                    sdp: offer
                });
            }
            
        } catch (error) {
            console.error('Error initiating WebRTC:', error);
            alert('Failed to access camera/microphone: ' + error.message);
            this.endCall();
        }
    }
    
    async handleCallSignal(event) {
        // The event structure from Laravel broadcasting:
        // event.payload contains the JSON string we sent
        // event also contains call_id, caller_id, etc. from broadcastWith()
        let payload;
        
        if (event.payload) {
            if (typeof event.payload === 'string') {
                try {
                    payload = JSON.parse(event.payload);
                } catch (e) {
                    console.error('Error parsing payload:', e);
                    return;
                }
            } else {
                payload = event.payload;
            }
        } else {
            // Fallback: try event directly
            payload = event;
        }
        
        if (!payload) {
            console.warn('No payload in call signal event');
            return;
        }
        
        if (payload.action === 'invite') {
            // Incoming call - ignore if we're the caller (we initiated this call)
            const currentUserId = window.APP?.userId || window.currentUserId;
            if (payload.caller?.id == currentUserId) {
                // This is our own invite, ignore it
                return;
            }
            
            // Incoming call
            if (this.currentCall) {
                // Already in a call, decline
                return;
            }
            
            this.currentCall = {
                sessionId: payload.session_id,
                callerId: payload.caller?.id,
                type: payload.type,
                callerName: payload.caller?.name || 'User',
                callerAvatar: payload.caller?.avatar || null
            };
            
            this.callType = payload.type;
            this.isCaller = false;
            
            // Store user info for minimized bar
            const callerName = payload.caller?.name || 'User';
            const callerAvatar = payload.caller?.avatar || null;
            this.callUserName = callerName;
            this.callUserAvatar = callerAvatar;
            
            // Show incoming call UI
            this.showCallUI(callerName, callerAvatar, 'incoming');
            
            // Play ringtone (optional)
            this.playRingtone();
            
            // Show browser notification if page is not active
            this.showCallNotification(callerName, payload.type, callerAvatar);
            
        } else if (payload.action === 'ended') {
            // Call ended
            this.endCall();
            
        } else if (payload.type === 'offer' && !this.isCaller) {
            // Received offer: if we already have peerConnection (user clicked Answer), handle it; otherwise queue for acceptCall
            if (this.peerConnection) {
                await this.handleOffer(payload);
            } else {
                this.pendingOffer = payload;
            }
        } else if (payload.type === 'answer' && this.isCaller) {
            // Received answer
            await this.handleAnswer(payload);
            
        } else if (payload.type === 'ice-candidate') {
            // Received ICE candidate
            await this.handleIceCandidate(payload);
        }
        
        // Also check if payload is nested in event structure
        if (event.payload && typeof event.payload === 'object' && event.payload.type) {
            const nestedPayload = event.payload;
            if (nestedPayload.type === 'offer' && !this.isCaller) {
                if (this.peerConnection) {
                    await this.handleOffer(nestedPayload);
                } else {
                    this.pendingOffer = nestedPayload;
                }
            } else if (nestedPayload.type === 'answer' && this.isCaller) {
                await this.handleAnswer(nestedPayload);
            } else if (nestedPayload.type === 'ice-candidate') {
                await this.handleIceCandidate(nestedPayload);
            }
        }
    }
    
    async acceptCall() {
        try {
            this.hideIncomingCallUI();
            this.updateCallStatus('connecting');
            
            // Request permissions before getting media
            try {
                await this.requestMediaPermissions(this.callType === 'video');
            } catch (error) {
                console.error('Media permission error:', error);
                alert(`Unable to access ${error.message.includes('video') ? 'camera' : 'microphone'}. Please enable permissions in your browser settings.`);
                this.declineCall();
                return;
            }
            
            // Get user media (permissions already requested)
            const constraints = {
                audio: true,
                video: this.callType === 'video'
            };
            
            this.localStream = await navigator.mediaDevices.getUserMedia(constraints);
            
            // Set up local video
            if (this.callType === 'video') {
                const localVideo = document.getElementById('local-video');
                if (localVideo) {
                    localVideo.srcObject = this.localStream;
                    localVideo.style.display = 'block';
                }
            }
            
            // Create peer connection
            this.peerConnection = new RTCPeerConnection(this.rtcConfig);
            
            // Add local stream tracks
            this.localStream.getTracks().forEach(track => {
                this.peerConnection.addTrack(track, this.localStream);
            });
            
            // Handle remote stream
            this.peerConnection.ontrack = (event) => {
                const remoteVideo = document.getElementById('remote-video');
                if (remoteVideo) {
                    remoteVideo.srcObject = event.streams[0];
                    remoteVideo.style.display = 'block';
                    document.getElementById('call-video-placeholder').style.display = 'none';
                }
                this.updateCallStatus('connected');
            };
            
            // Handle ICE candidates
            this.peerConnection.onicecandidate = (event) => {
                if (event.candidate) {
                    this.sendSignal({
                        type: 'ice-candidate',
                        candidate: event.candidate
                    });
                }
            };

            // Apply pending offer if we received it before user clicked Answer
            if (this.pendingOffer) {
                await this.handleOffer(this.pendingOffer);
                this.pendingOffer = null;
            }
        } catch (error) {
            console.error('Error accepting call:', error);
            alert('Failed to accept call: ' + error.message);
            this.endCall();
        }
    }

    async handleOffer(payload) {
        try {
            await this.peerConnection.setRemoteDescription(new RTCSessionDescription({
                type: 'offer',
                sdp: payload.sdp
            }));
            
            // Create and send answer
            const answer = await this.peerConnection.createAnswer();
            await this.peerConnection.setLocalDescription(answer);
            
            this.sendSignal({
                type: 'answer',
                sdp: answer
            });
            
        } catch (error) {
            console.error('Error handling offer:', error);
            this.endCall();
        }
    }
    
    async handleAnswer(payload) {
        try {
            await this.peerConnection.setRemoteDescription(new RTCSessionDescription({
                type: 'answer',
                sdp: payload.sdp
            }));
            
            this.updateCallStatus('connected');
            
        } catch (error) {
            console.error('Error handling answer:', error);
            this.endCall();
        }
    }
    
    async handleIceCandidate(payload) {
        try {
            if (this.peerConnection && payload.candidate) {
                await this.peerConnection.addIceCandidate(new RTCIceCandidate(payload.candidate));
            }
        } catch (error) {
            console.error('Error handling ICE candidate:', error);
        }
    }
    
    async sendSignal(signalData) {
        if (!this.currentCall?.sessionId) return;
        
        try {
            await fetch(getCallUrl('signal', this.currentCall.sessionId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    payload: JSON.stringify(signalData)
                })
            });
        } catch (error) {
            console.error('Error sending signal:', error);
        }
    }
    
    async declineCall() {
        if (this.currentCall) {
            await this.endCall();
        }
    }
    
    async endCall() {
        try {
            // Stop local stream
            if (this.localStream) {
                this.localStream.getTracks().forEach(track => track.stop());
                this.localStream = null;
            }
            
            // Close peer connection
            if (this.peerConnection) {
                this.peerConnection.close();
                this.peerConnection = null;
            }
            
            // End call session
            if (this.currentCall?.sessionId) {
                await fetch(getCallUrl('end', this.currentCall.sessionId), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin'
                });
            }
            
        } catch (error) {
            console.error('Error ending call:', error);
        } finally {
            // Clear persisted call state
            this.clearCallState();
            
            this.hideCallUI();
            this.currentCall = null;
            this.isCaller = false;
            this.isMinimized = false;
            this.callUserName = null;
            this.callUserAvatar = null;
            this.pendingOffer = null;
            this.stopRingtone();
        }
    }
    
    /**
     * Save call state to localStorage for persistence across page refreshes
     */
    saveCallState() {
        if (!this.currentCall) {
            this.clearCallState();
            return;
        }
        
        try {
            const callState = {
                sessionId: this.currentCall.sessionId,
                userId: this.currentCall.userId,
                groupId: this.currentCall.groupId,
                type: this.callType,
                isCaller: this.isCaller,
                isMinimized: this.isMinimized,
                callUserName: this.callUserName,
                callUserAvatar: this.callUserAvatar,
                timestamp: Date.now()
            };
            
            localStorage.setItem('active_call_state', JSON.stringify(callState));
            console.log('Call state saved for persistence');
        } catch (error) {
            console.error('Failed to save call state:', error);
        }
    }
    
    /**
     * Restore call state from localStorage after page refresh
     */
    async restoreCallState() {
        try {
            const savedState = localStorage.getItem('active_call_state');
            if (!savedState) {
                return; // No saved call state
            }
            
            const callState = JSON.parse(savedState);
            
            // Check if call state is recent (within last 5 minutes)
            const fiveMinutesAgo = Date.now() - (5 * 60 * 1000);
            if (callState.timestamp < fiveMinutesAgo) {
                console.log('Saved call state is too old, clearing...');
                this.clearCallState();
                return;
            }
            
            // Verify call is still active on server
            const response = await fetch(getCallUrl('status', callState.sessionId), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                this.clearCallState();
                return;
            }
            
            const data = await response.json();
            if (data.status === 'success' && data.call_status === 'active') {
                // Restore call state
                this.currentCall = {
                    sessionId: callState.sessionId,
                    userId: callState.userId,
                    groupId: callState.groupId,
                    type: callState.type
                };
                
                this.callType = callState.type;
                this.isCaller = callState.isCaller;
                this.isMinimized = callState.isMinimized;
                this.callUserName = callState.callUserName;
                this.callUserAvatar = callState.callUserAvatar;
                
                // Show call UI
                if (this.isMinimized) {
                    this.showCallUI(this.callUserName, this.callUserAvatar, 'active');
                    this.minimizeCall();
                } else {
                    this.showCallUI(this.callUserName, this.callUserAvatar, 'active');
                }
                
                // Attempt to restore WebRTC connection (might fail, but worth trying)
                try {
                    await this.requestMediaPermissions(this.callType === 'video');
                    await this.initiateWebRTC();
                } catch (error) {
                    console.warn('Failed to restore WebRTC connection:', error);
                    // Show message to user
                    alert('Call connection lost. Please hang up and call again.');
                }
                
                console.log('Call state restored successfully');
            } else {
                // Call no longer active on server
                this.clearCallState();
            }
            
        } catch (error) {
            console.error('Failed to restore call state:', error);
            this.clearCallState();
        }
    }
    
    /**
     * Clear persisted call state
     */
    clearCallState() {
        try {
            localStorage.removeItem('active_call_state');
        } catch (error) {
            console.error('Failed to clear call state:', error);
        }
    }
    
    toggleMute() {
        if (this.localStream) {
            const audioTracks = this.localStream.getAudioTracks();
            audioTracks.forEach(track => {
                track.enabled = !track.enabled;
            });
            
            const muteBtn = document.getElementById('call-mute-btn');
            if (muteBtn) {
                muteBtn.classList.toggle('active', !audioTracks[0]?.enabled);
                muteBtn.innerHTML = audioTracks[0]?.enabled 
                    ? '<i class="bi bi-mic"></i>'
                    : '<i class="bi bi-mic-mute"></i>';
            }
        }
    }
    
    async toggleVideo() {
        if (!this.localStream || !this.peerConnection) {
            console.warn('Cannot toggle video: no active stream or peer connection');
            return;
        }
        
        const videoTracks = this.localStream.getVideoTracks();
        const hasVideoTracks = videoTracks.length > 0;
        const isVideoEnabled = hasVideoTracks && videoTracks[0]?.enabled;
        
        if (hasVideoTracks && isVideoEnabled) {
            // Turn video OFF (disable existing tracks)
            videoTracks.forEach(track => {
                track.enabled = false;
            });
            
            this.updateVideoToggleUI(false);
            
        } else if (hasVideoTracks && !isVideoEnabled) {
            // Turn video ON (enable existing tracks)
            videoTracks.forEach(track => {
                track.enabled = true;
            });
            
            this.updateVideoToggleUI(true);
            
        } else {
            // No video tracks - need to add video (upgrade from voice to video)
            try {
                // Request video permission
                await this.requestMediaPermissions(true);
                
                // Get video stream
                const videoStream = await navigator.mediaDevices.getUserMedia({ video: true });
                const newVideoTrack = videoStream.getVideoTracks()[0];
                
                // Add video track to existing stream
                this.localStream.addTrack(newVideoTrack);
                
                // Add track to peer connection
                this.peerConnection.addTrack(newVideoTrack, this.localStream);
                
                // Update UI
                const localVideo = document.getElementById('local-video');
                if (localVideo) {
                    localVideo.srcObject = this.localStream;
                    localVideo.style.display = 'block';
                }
                
                this.updateVideoToggleUI(true);
                
                // Send renegotiation signal (either side can initiate)
                await this.renegotiatePeerConnection();
                
            } catch (error) {
                console.error('Failed to enable video:', error);
                alert('Failed to enable video. Please check your camera permissions.');
            }
        }
    }
    
    /**
     * Update video toggle button UI
     */
    updateVideoToggleUI(enabled) {
        const videoToggleBtn = document.getElementById('call-video-toggle-btn');
        const localVideo = document.getElementById('local-video');
        
        if (videoToggleBtn) {
            videoToggleBtn.classList.toggle('active', !enabled);
            videoToggleBtn.innerHTML = enabled
                ? '<i class="bi bi-camera-video"></i>'
                : '<i class="bi bi-camera-video-off"></i>';
        }
        
        if (localVideo) {
            const videoTracks = this.localStream?.getVideoTracks() || [];
            localVideo.style.display = (enabled && videoTracks.length > 0) ? 'block' : 'none';
        }
    }
    
    /**
     * Renegotiate peer connection (needed when adding video track)
     */
    async renegotiatePeerConnection() {
        try {
            if (this.isCaller) {
                // Caller creates and sends new offer
                const offer = await this.peerConnection.createOffer();
                await this.peerConnection.setLocalDescription(offer);
                
                await this.sendSignal({
                    type: 'offer',
                    sdp: offer.sdp
                });
            } else {
                // Callee creates and sends new answer
                // First, we might need to handle if there's a pending offer
                // For now, just create an answer if we already have a remote description
                if (this.peerConnection.remoteDescription) {
                    const answer = await this.peerConnection.createAnswer();
                    await this.peerConnection.setLocalDescription(answer);
                    
                    await this.sendSignal({
                        type: 'answer',
                        sdp: answer.sdp
                    });
                } else {
                    // If no remote description, we can't create answer yet
                    // The other party will need to send an offer first
                    console.warn('Cannot renegotiate: no remote description');
                }
            }
        } catch (error) {
            console.error('Error renegotiating peer connection:', error);
        }
    }
    
    minimizeCall() {
        if (!this.currentCall) return;
        
        this.isMinimized = true;
        
        // Hide full call modal
        const callModal = document.getElementById('call-modal');
        if (callModal) {
            callModal.style.display = 'none';
        }
        
        // Show minimized bar
        const minimizedBar = document.getElementById('call-minimized-bar');
        if (minimizedBar) {
            minimizedBar.style.display = 'block';
            
            // Update minimized bar content
            this.updateMinimizedBar();
        }
    }
    
    maximizeCall() {
        if (!this.currentCall) return;
        
        this.isMinimized = false;
        
        // Hide minimized bar
        const minimizedBar = document.getElementById('call-minimized-bar');
        if (minimizedBar) {
            minimizedBar.style.display = 'none';
        }
        
        // Show full call modal
        const callModal = document.getElementById('call-modal');
        if (callModal) {
            callModal.style.display = 'flex';
            callModal.classList.add('show');
        }
    }
    
    /**
     * Enter Picture-in-Picture mode (browser feature for video calls)
     */
    async enterPictureInPicture() {
        // Only available for video calls
        if (this.callType !== 'video') {
            console.warn('PiP is only available for video calls');
            return;
        }
        
        const remoteVideo = document.getElementById('remote-video');
        
        // Check if PiP is supported
        if (!document.pictureInPictureEnabled) {
            console.warn('Picture-in-Picture is not supported in this browser');
            alert('Picture-in-Picture is not supported in your browser. Try using Chrome, Edge, or Safari.');
            return;
        }
        
        if (!remoteVideo) {
            console.warn('Remote video element not found');
            return;
        }
        
        try {
            // Check if already in PiP mode
            if (document.pictureInPictureElement) {
                await document.exitPictureInPicture();
                console.log('Exited Picture-in-Picture mode');
            } else {
                // Enter PiP mode
                await remoteVideo.requestPictureInPicture();
                console.log('Entered Picture-in-Picture mode');
                
                // Update UI when PiP mode changes
                remoteVideo.addEventListener('enterpictureinpicture', () => {
                    console.log('Video entered Picture-in-Picture mode');
                    this.updatePipButtonUI(true);
                    
                    // Optionally minimize the call modal
                    this.minimizeCall();
                }, { once: true });
                
                remoteVideo.addEventListener('leavepictureinpicture', () => {
                    console.log('Video left Picture-in-Picture mode');
                    this.updatePipButtonUI(false);
                }, { once: true });
            }
        } catch (error) {
            console.error('Failed to toggle Picture-in-Picture:', error);
            alert('Failed to enable Picture-in-Picture mode. Make sure the video is playing.');
        }
    }
    
    /**
     * Update Picture-in-Picture button UI
     */
    updatePipButtonUI(inPipMode) {
        const pipBtn = document.getElementById('call-pip-btn');
        if (pipBtn) {
            pipBtn.classList.toggle('active', inPipMode);
            pipBtn.title = inPipMode ? 'Exit Picture-in-Picture' : 'Enter Picture-in-Picture';
        }
    }
    
    /**
     * Update minimized call bar content
     */
    updateMinimizedBar() {
        const minimizedName = document.getElementById('call-minimized-name');
        const minimizedStatus = document.getElementById('call-minimized-status');
        const minimizedAvatarPlaceholder = document.getElementById('call-minimized-avatar-placeholder');
        const minimizedAvatarImg = document.getElementById('call-minimized-avatar-img');
        
        if (minimizedName && this.callUserName) {
            minimizedName.textContent = this.callUserName;
        }
        
        if (minimizedStatus) {
            const statusEl = document.getElementById('call-status');
            minimizedStatus.textContent = statusEl ? statusEl.textContent : 'In call';
        }
        
        // Update avatar
        if (this.callUserAvatar) {
            if (minimizedAvatarImg) {
                minimizedAvatarImg.src = this.callUserAvatar;
                minimizedAvatarImg.style.display = 'block';
            }
            if (minimizedAvatarPlaceholder) {
                minimizedAvatarPlaceholder.style.display = 'none';
            }
        } else {
            if (minimizedAvatarImg) {
                minimizedAvatarImg.style.display = 'none';
            }
            if (minimizedAvatarPlaceholder && this.callUserName) {
                minimizedAvatarPlaceholder.textContent = this.callUserName[0].toUpperCase();
                minimizedAvatarPlaceholder.style.display = 'flex';
            }
        }
    }
    
    showCallUI(userName, userAvatar, status) {
        const callModal = document.getElementById('call-modal');
        if (!callModal) return;
        
        // Store user info for minimized bar
        this.callUserName = userName;
        this.callUserAvatar = userAvatar;
        
        // Set user info
        const userNameEl = document.getElementById('call-user-name');
        const largeUserNameEl = document.getElementById('call-large-user-name');
        if (userNameEl) userNameEl.textContent = userName;
        if (largeUserNameEl) largeUserNameEl.textContent = userName;
        
        // Set avatar
        this.setCallAvatar(userAvatar, userName);
        
        // Update status
        this.updateCallStatus(status);
        
        // Hide minimized bar when showing full UI
        const minimizedBar = document.getElementById('call-minimized-bar');
        if (minimizedBar) {
            minimizedBar.style.display = 'none';
        }
        this.isMinimized = false;
        
        // Show/hide incoming call UI
        const incomingCallUI = document.getElementById('incoming-call-ui');
        if (incomingCallUI) {
            incomingCallUI.style.display = status === 'incoming' ? 'block' : 'none';
        }
        
        // Show modal
        callModal.style.display = 'flex';
        callModal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    
    hideCallUI() {
        const callModal = document.getElementById('call-modal');
        if (callModal) {
            callModal.style.display = 'none';
            callModal.classList.remove('show');
        }
        
        // Hide minimized bar
        const minimizedBar = document.getElementById('call-minimized-bar');
        if (minimizedBar) {
            minimizedBar.style.display = 'none';
        }
        this.isMinimized = false;
        
        // Hide videos
        const remoteVideo = document.getElementById('remote-video');
        const localVideo = document.getElementById('local-video');
        const videoPlaceholder = document.getElementById('call-video-placeholder');
        
        if (remoteVideo) {
            remoteVideo.srcObject = null;
            remoteVideo.style.display = 'none';
        }
        if (localVideo) {
            localVideo.srcObject = null;
            localVideo.style.display = 'none';
        }
        if (videoPlaceholder) {
            videoPlaceholder.style.display = 'block';
        }
        
        document.body.style.overflow = '';
    }
    
    hideIncomingCallUI() {
        const incomingCallUI = document.getElementById('incoming-call-ui');
        if (incomingCallUI) {
            incomingCallUI.style.display = 'none';
        }
    }
    
    updateCallStatus(status) {
        const statusEl = document.getElementById('call-status');
        if (!statusEl) return;
        
        const statusText = {
            'calling': 'Calling...',
            'ringing': 'Ringing...',
            'connecting': 'Connecting...',
            'connected': 'Connected',
            'incoming': 'Incoming call'
        };
        
        statusEl.textContent = statusText[status] || status;
        
        // Update minimized bar status if minimized
        if (this.isMinimized) {
            const minimizedStatus = document.getElementById('call-minimized-status');
            if (minimizedStatus) {
                minimizedStatus.textContent = statusText[status] || status;
            }
        }
    }
    
    setCallAvatar(avatarUrl, userName) {
        const initial = userName ? userName[0].toUpperCase() : 'U';
        
        // Small avatar
        const avatarPlaceholder = document.getElementById('call-avatar-placeholder');
        const avatarImg = document.getElementById('call-avatar-img');
        if (avatarPlaceholder) avatarPlaceholder.textContent = initial;
        if (avatarImg) {
            if (avatarUrl) {
                avatarImg.src = avatarUrl;
                avatarImg.style.display = 'block';
                if (avatarPlaceholder) avatarPlaceholder.style.display = 'none';
            } else {
                avatarImg.style.display = 'none';
                if (avatarPlaceholder) avatarPlaceholder.style.display = 'flex';
            }
        }
        
        // Large avatar
        const largeAvatarPlaceholder = document.getElementById('call-large-avatar-placeholder');
        const largeAvatarImg = document.getElementById('call-large-avatar-img');
        if (largeAvatarPlaceholder) largeAvatarPlaceholder.textContent = initial;
        if (largeAvatarImg) {
            if (avatarUrl) {
                largeAvatarImg.src = avatarUrl;
                largeAvatarImg.style.display = 'block';
                if (largeAvatarPlaceholder) largeAvatarPlaceholder.style.display = 'none';
            } else {
                largeAvatarImg.style.display = 'none';
                if (largeAvatarPlaceholder) largeAvatarPlaceholder.style.display = 'flex';
            }
        }
    }
    
    playRingtone() {
        // Implement ringtone playback if needed
        console.log('Playing ringtone');
    }
    
    stopRingtone() {
        // Stop ringtone if needed
        console.log('Stopping ringtone');
    }
    
    /**
     * Show browser notification for incoming call
     * Similar to message notifications, but for calls
     */
    showCallNotification(callerName, callType, callerAvatar = null) {
        // Only show notification if page is not active (similar to message notifications)
        const isActive = !document.hidden && document.hasFocus();
        if (isActive) {
            // Page is active, call UI is already visible, no need for notification
            return;
        }
        
        // Check if notifications are enabled (check the same state used for messages)
        // You can also check Notification.permission directly
        if (!window.Notification) {
            console.log('[Call Notification] Browser notifications not supported');
            return;
        }
        
        // Request permission if not already granted/denied
        if (Notification.permission === 'default') {
            Notification.requestPermission().then(permission => {
                if (permission === 'granted') {
                    // Retry showing notification after permission is granted
                    this.showCallNotification(callerName, callType, callerAvatar);
                }
            }).catch(() => {});
            return;
        }
        
        if (Notification.permission !== 'granted') {
            // Permission denied or not granted
            return;
        }
        
        // Prepare notification content
        const callTypeText = callType === 'video' ? 'Video call' : 'Voice call';
        const title = callerName || 'Incoming call';
        const body = `Incoming ${callTypeText.toLowerCase()}`;
        
        // Try to get caller's avatar (from parameter, event, or DOM)
        let icon = '/icons/icon-192x192.png'; // Default icon
        try {
            // First try the callerAvatar parameter
            if (callerAvatar && typeof callerAvatar === 'string' && callerAvatar.length > 0) {
                icon = callerAvatar;
            } else {
                // Fallback: Try to get caller avatar from the call UI if it exists
                const callerAvatarImg = document.getElementById('call-avatar-img') || 
                                       document.getElementById('call-large-avatar-img');
                if (callerAvatarImg && callerAvatarImg.src && !callerAvatarImg.src.includes('data:image')) {
                    icon = callerAvatarImg.src;
                }
            }
        } catch (e) {
            // Ignore errors, use default icon
        }
        
        try {
            // Create and show notification
            const notification = new Notification(title, {
                body: body,
                icon: icon,
                badge: '/icons/icon-32x32.png',
                tag: `call-${this.currentCall?.sessionId || Date.now()}`, // Tag prevents duplicates
                requireInteraction: false, // Allow notification to auto-dismiss
                silent: false // Allow notification sound
            });
            
            // Auto-close notification after 10 seconds (longer than messages since calls are more important)
            setTimeout(() => {
                notification.close();
            }, 10000);
            
            // Handle notification click - focus the window/tab
            notification.onclick = function() {
                window.focus();
                // Optionally, you could scroll to or highlight the call UI here
                this.close();
            };
            
        } catch (error) {
            console.warn('[Call Notification] Failed to show browser notification:', error);
        }
    }
}

// Initialize CallManager globally
if (typeof window !== 'undefined') {
    window.CallManager = CallManager;
}
