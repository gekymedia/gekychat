/**
 * Web call manager — LiveKit only (no P2P WebRTC).
 * Handles incoming ring UI, start/join redirects, and signaling side-effects.
 */
function getCallUrl(key, sessionId = null) {
    const urls = window.__GekyChatCallUrls || {};
    if (sessionId != null) {
        const template = urls[`${key}Template`];
        if (template) return template.replace(':session', sessionId);
        if (key === 'decline') return `/calls/${sessionId}/decline`;
        if (key === 'leave') return `/calls/${sessionId}/leave`;
        if (key === 'end') return `/calls/${sessionId}/end`;
        if (key === 'signal') return `/calls/${sessionId}/signal`;
        if (key === 'status') return `/calls/${sessionId}/status`;
        return `/calls/${sessionId}/${key}`;
    }
    const base = urls[key];
    if (base) return base;
    switch (key) {
        case 'start':
            return '/calls/start';
        default:
            return null;
    }
}

function liveKitRoomUrl(sessionId, type = 'video') {
    return `/calls/group/${sessionId}?type=${type || 'video'}`;
}

export class CallManager {
    constructor() {
        this.currentCall = null;
        this.isCaller = false;
        this.callType = 'voice';
        this.callUserName = null;
        this.callUserAvatar = null;

        this._audioContext = null;
        this._ringbackGain = null;
        this._ringbackOscillators = [];
        this._ringbackInterval = null;
        this._ringtoneGain = null;
        this._ringtoneOscillators = [];
        this._ringtoneInterval = null;

        this.init();
    }

    init() {
        this.setupUI();
        this.setupEchoListeners();

        const autoStart = window.__autoStartCall;
        if (autoStart?.sessionId && !this.currentCall) {
            window.__autoStartCall = null;
            setTimeout(
                () => this.joinBySessionId(autoStart.sessionId, autoStart.type || 'video'),
                150,
            );
        }
    }

    setupUI() {
        const groupVoiceCallBtn = document.getElementById('group-voice-call-btn');
        if (groupVoiceCallBtn) {
            groupVoiceCallBtn.addEventListener('click', () => {
                const groupId = groupVoiceCallBtn.dataset.groupId;
                if (groupId) this.startGroupCall(groupId, 'voice');
            });
        }

        const groupVideoCallBtn = document.getElementById('group-video-call-btn');
        if (groupVideoCallBtn) {
            groupVideoCallBtn.addEventListener('click', () => {
                const groupId = groupVideoCallBtn.dataset.groupId;
                if (groupId) this.startGroupCall(groupId, 'video');
            });
        }

        document.getElementById('call-accept-btn')?.addEventListener('click', () => this.acceptCall());
        document.getElementById('call-decline-btn')?.addEventListener('click', () => this.declineCall());
        document.getElementById('call-end-btn')?.addEventListener('click', () => this.endCall());
        document.getElementById('call-end-minimized-btn')?.addEventListener('click', () => this.endCall());
    }

    setupEchoListeners() {
        const currentUserId = window.APP?.userId || window.currentUserId;
        if (!currentUserId || !window.Echo) {
            console.warn('Call signaling unavailable (Echo or user id missing)');
            return;
        }

        window.Echo.private(`user.${currentUserId}`).listen('.CallInvite', (event) => {
            this.handleCallSignal({
                payload: JSON.stringify({
                    session_id: event.session_id || event.call_id,
                    type: event.type,
                    caller: event.caller,
                    action: 'invite',
                }),
            });
        });

        window.Echo.private(`call.${currentUserId}`).listen('.CallSignal', (event) => {
            this.handleCallSignal(event);
        });

        window.handleIncomingCallInvite = (event) => {
            this.handleCallSignal({
                payload: JSON.stringify({
                    session_id: event.session_id || event.call_id,
                    type: event.type,
                    caller: event.caller,
                    action: 'invite',
                }),
            });
        };
    }

    async startCall(userId, type) {
        await this._startSession({ callee_id: parseInt(userId, 10), type }, type);
    }

    async startGroupCall(groupId, type) {
        await this._startSession({ group_id: parseInt(groupId, 10), type }, type, true);
    }

    async _startSession(body, type, isGroup = false) {
        try {
            this.callType = type;
            this.isCaller = true;

            const header = document.querySelector(isGroup ? '.group-header' : '.chat-header');
            let displayName = isGroup ? 'Group' : 'User';
            let displayAvatar = null;

            if (header) {
                const nameEl = header.querySelector(
                    isGroup ? '.group-header-name' : '.chat-header-name, h5, h6',
                );
                if (nameEl?.textContent?.trim()) displayName = nameEl.textContent.trim();
                const avatarEl = header.querySelector('.avatar-img');
                if (avatarEl?.src) displayAvatar = avatarEl.src;
            }

            this.callUserName = displayName;
            this.callUserAvatar = displayAvatar;

            try {
                await this.requestMediaPermissions(type === 'video');
            } catch (error) {
                alert(error.message || 'Microphone permission is required to place a call.');
                return;
            }

            this.showCallUI(displayName, displayAvatar, 'calling');
            this.playRingback();

            const response = await fetch(getCallUrl('start'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify(body),
            });

            if (response.status === 401) {
                this.stopRingback();
                const data = await response.json().catch(() => ({}));
                alert((data.message || 'Session expired.') + ' Please log in again.');
                window.location.href = '/login';
                this.hideCallUI();
                return;
            }

            const data = await response.json();
            if (data.status !== 'success' || !data.session_id) {
                throw new Error(data.message || 'Failed to start call');
            }

            this.currentCall = { sessionId: data.session_id, type };
            this.stopRingback();
            this.hideCallUI();
            window.location.href = liveKitRoomUrl(data.session_id, type);
        } catch (error) {
            console.error('Error starting call:', error);
            this.stopRingback();
            alert('Failed to start call: ' + error.message);
            this.dismissIncomingCall();
        }
    }

    async requestMediaPermissions(requireVideo = false) {
        const stream = await navigator.mediaDevices.getUserMedia({
            audio: true,
            video: requireVideo,
        });
        stream.getTracks().forEach((track) => track.stop());
    }

    async handleCallSignal(event) {
        let payload = event?.payload;
        if (typeof payload === 'string') {
            try {
                payload = JSON.parse(payload);
            } catch (error) {
                console.warn('Invalid call signal payload', error);
                return;
            }
        }
        if (!payload || typeof payload !== 'object') return;

        const currentUserId = window.APP?.userId || window.currentUserId;

        if (payload.action === 'invite') {
            if (payload.caller?.id == currentUserId) return;
            if (this.currentCall) return;

            const callerName = payload.caller?.name || 'User';
            const callerAvatar = payload.caller?.avatar || null;

            this.currentCall = {
                sessionId: payload.session_id,
                callerId: payload.caller?.id,
                type: payload.type,
            };
            this.callType = payload.type || 'voice';
            this.isCaller = false;
            this.callUserName = callerName;
            this.callUserAvatar = callerAvatar;

            this.showCallUI(callerName, callerAvatar, 'incoming');
            this.playRingtone();
            this.showCallNotification(callerName, payload.type, callerAvatar);
            return;
        }

        if (payload.action === 'cancel') {
            this.dismissIncomingCall();
            return;
        }

        if (payload.action === 'declined') {
            this.stopRingback();
            this.stopRingtone();
            if (this.isCaller) {
                this.updateCallStatus('declined');
                setTimeout(() => this.dismissIncomingCall('Call declined'), 1500);
            } else {
                this.dismissIncomingCall();
            }
            return;
        }

        if (payload.action === 'busy') {
            this.stopRingback();
            this.stopRingtone();
            if (this.isCaller) alert('User is busy on another call.');
            this.dismissIncomingCall();
            return;
        }

        if (payload.action === 'callee-ringing' && this.isCaller) {
            this.updateCallStatus('ringing');
            return;
        }

        if (payload.type === 'livekit-joined' && this.isCaller) {
            this.stopRingback();
            const sid = this.currentCall?.sessionId;
            if (sid && !window.location.pathname.includes('/calls/group/')) {
                window.location.href = liveKitRoomUrl(sid, this.callType || 'voice');
            }
            return;
        }

        if (payload.action === 'ended') {
            this.dismissIncomingCall();
        }
    }

    acceptCall() {
        if (!this.currentCall?.sessionId) return;
        this.stopRingtone();
        this.hideCallUI();
        window.location.href = liveKitRoomUrl(
            this.currentCall.sessionId,
            this.callType || 'voice',
        );
    }

    async declineCall() {
        this.stopRingtone();
        if (this.currentCall?.sessionId) {
            try {
                await fetch(getCallUrl('decline', this.currentCall.sessionId), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
            } catch (error) {
                console.error('Error declining call:', error);
            }
        }
        this.dismissIncomingCall();
    }

    async endCall() {
        try {
            if (this.currentCall?.sessionId) {
                const url = this.isCaller
                    ? getCallUrl('end', this.currentCall.sessionId)
                    : getCallUrl('decline', this.currentCall.sessionId);
                await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    credentials: 'same-origin',
                });
            }
        } catch (error) {
            console.error('Error ending call:', error);
        } finally {
            this.dismissIncomingCall();
        }
    }

    dismissIncomingCall(message) {
        this.stopRingtone();
        this.stopRingback();
        if (message) console.log(message);
        this.currentCall = null;
        this.isCaller = false;
        this.callUserName = null;
        this.callUserAvatar = null;
        this.hideCallUI();
    }

    joinBySessionId(sessionId, type) {
        if (this.currentCall) return;
        this.dismissIncomingCall();
        window.location.href = liveKitRoomUrl(sessionId, type || 'video');
    }

    showCallUI(userName, userAvatar, status) {
        const callModal = document.getElementById('call-modal');
        if (!callModal) return;

        this.callUserName = userName;
        this.callUserAvatar = userAvatar;

        const userNameEl = document.getElementById('call-user-name');
        const largeUserNameEl = document.getElementById('call-large-user-name');
        if (userNameEl) userNameEl.textContent = userName;
        if (largeUserNameEl) largeUserNameEl.textContent = userName;
        this.setCallAvatar(userAvatar, userName);
        this.updateCallStatus(status);

        const incomingCallUI = document.getElementById('incoming-call-ui');
        if (incomingCallUI) {
            incomingCallUI.style.display = status === 'incoming' ? 'block' : 'none';
        }

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
        document.getElementById('incoming-call-ui')?.style &&
            (document.getElementById('incoming-call-ui').style.display = 'none');
        document.body.style.overflow = '';
    }

    updateCallStatus(status) {
        const statusEl = document.getElementById('call-status');
        if (!statusEl) return;
        const labels = {
            calling: 'Calling…',
            ringing: 'Ringing…',
            connecting: 'Connecting…',
            connected: 'Connected',
            incoming: 'Incoming call',
            declined: 'Declined',
        };
        statusEl.textContent = labels[status] || status;
    }

    setCallAvatar(avatarUrl, userName) {
        const initial = userName ? userName[0].toUpperCase() : 'U';
        const pairs = [
            ['call-avatar-placeholder', 'call-avatar-img'],
            ['call-large-avatar-placeholder', 'call-large-avatar-img'],
        ];
        pairs.forEach(([placeholderId, imgId]) => {
            const placeholder = document.getElementById(placeholderId);
            const img = document.getElementById(imgId);
            if (placeholder) placeholder.textContent = initial;
            if (!img) return;
            if (avatarUrl) {
                img.src = avatarUrl;
                img.style.display = 'block';
                if (placeholder) placeholder.style.display = 'none';
            } else {
                img.style.display = 'none';
                if (placeholder) placeholder.style.display = 'flex';
            }
        });
    }

    _ensureAudioContext() {
        if (this._audioContext) return this._audioContext;
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return null;
        this._audioContext = new Ctx();
        return this._audioContext;
    }

    playRingback() {
        this.stopRingback();
        const ctx = this._ensureAudioContext();
        if (!ctx) return;
        try {
            const gain = ctx.createGain();
            gain.gain.value = 0;
            gain.connect(ctx.destination);
            const osc1 = ctx.createOscillator();
            const osc2 = ctx.createOscillator();
            osc1.frequency.value = 440;
            osc2.frequency.value = 480;
            osc1.connect(gain);
            osc2.connect(gain);
            osc1.start(0);
            osc2.start(0);
            this._ringbackGain = gain;
            this._ringbackOscillators = [osc1, osc2];
            let phase = 0;
            this._ringbackInterval = setInterval(() => {
                if (!this._ringbackGain) return;
                this._ringbackGain.gain.setTargetAtTime(phase === 0 ? 0.15 : 0, ctx.currentTime, 0.02);
                phase = phase === 0 ? 1 : 0;
            }, 1000);
        } catch (error) {
            console.warn('Ringback audio failed:', error);
        }
    }

    stopRingback() {
        if (this._ringbackInterval) {
            clearInterval(this._ringbackInterval);
            this._ringbackInterval = null;
        }
        this._ringbackOscillators.forEach((o) => {
            try {
                o.stop();
            } catch (_) {}
        });
        this._ringbackOscillators = [];
        if (this._ringbackGain) {
            try {
                this._ringbackGain.disconnect();
            } catch (_) {}
            this._ringbackGain = null;
        }
    }

    playRingtone() {
        this.stopRingtone();
        const ctx = this._ensureAudioContext();
        if (!ctx) return;
        try {
            const gain = ctx.createGain();
            gain.gain.value = 0;
            gain.connect(ctx.destination);
            const osc1 = ctx.createOscillator();
            const osc2 = ctx.createOscillator();
            osc1.frequency.value = 480;
            osc2.frequency.value = 620;
            osc1.connect(gain);
            osc2.connect(gain);
            osc1.start(0);
            osc2.start(0);
            this._ringtoneGain = gain;
            this._ringtoneOscillators = [osc1, osc2];
            let phase = 0;
            this._ringtoneInterval = setInterval(() => {
                if (!this._ringtoneGain) return;
                this._ringtoneGain.gain.setTargetAtTime(phase === 0 ? 0.2 : 0, ctx.currentTime, 0.02);
                phase = phase === 0 ? 1 : 0;
            }, 1200);
        } catch (error) {
            console.warn('Ringtone audio failed:', error);
        }
    }

    stopRingtone() {
        if (this._ringtoneInterval) {
            clearInterval(this._ringtoneInterval);
            this._ringtoneInterval = null;
        }
        this._ringtoneOscillators.forEach((o) => {
            try {
                o.stop();
            } catch (_) {}
        });
        this._ringtoneOscillators = [];
        if (this._ringtoneGain) {
            try {
                this._ringtoneGain.disconnect();
            } catch (_) {}
            this._ringtoneGain = null;
        }
    }

    showCallNotification(callerName, callType, callerAvatar = null) {
        if (!document.hidden && document.hasFocus()) return;
        if (!window.Notification) return;
        if (Notification.permission === 'default') {
            Notification.requestPermission().then((permission) => {
                if (permission === 'granted') {
                    this.showCallNotification(callerName, callType, callerAvatar);
                }
            });
            return;
        }
        if (Notification.permission !== 'granted') return;
        try {
            const notification = new Notification(
                `Incoming ${callType === 'video' ? 'video' : 'voice'} call`,
                {
                    body: callerName,
                    icon: callerAvatar || undefined,
                    tag: 'gekychat-incoming-call',
                    requireInteraction: true,
                },
            );
            notification.onclick = () => {
                window.focus();
                notification.close();
            };
        } catch (error) {
            console.warn('Call notification failed:', error);
        }
    }
}

if (typeof window !== 'undefined') {
    window.CallManager = CallManager;
}
