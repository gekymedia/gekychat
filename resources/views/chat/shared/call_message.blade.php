@php
    $callData = $message->call_data ?? [];
    $callType = $callData['type'] ?? 'voice'; // 'voice' or 'video'
    $duration = $callData['duration'] ?? null;
    $endedAt = $callData['ended_at'] ?? $message->created_at;
    $isOwn = (int) $message->sender_id === (int) auth()->id();
    $isMissed = $callData['missed'] ?? false;
    $callStatus = $callData['status'] ?? 'ended'; // 'calling', 'ongoing', 'ended'
    $callLink = $callData['call_link'] ?? null;
    $isActive = in_array($callStatus, ['calling', 'ongoing']);
@endphp

<div class="call-message mt-2">
    <div class="call-card rounded border bg-light d-flex align-items-center p-3 {{ $callLink ? 'call-card-clickable' : '' }}" 
         role="article" 
         aria-label="{{ ucfirst($callType) }} call"
         @if($callLink) onclick="event.preventDefault(); if(typeof joinCallFromMessage === 'function') { joinCallFromMessage('{{ $callLink }}'); } else { window.location.href = '{{ $callLink }}'; } return false;" style="cursor: pointer;" @endif>
        <div class="call-icon me-3">
            @if($callType === 'video')
                <i class="bi bi-camera-video-fill text-primary" style="font-size: 1.5rem;" aria-hidden="true"></i>
            @else
                <i class="bi bi-telephone-fill text-primary" style="font-size: 1.5rem;" aria-hidden="true"></i>
            @endif
        </div>
        
        <div class="call-details flex-grow-1">
            <div class="d-flex align-items-center justify-content-between">
                <div class="flex-grow-1">
                    <div class="fw-semibold text-dark mb-1">
                        @if($isMissed)
                            Missed {{ $callType === 'video' ? 'video call' : 'voice call' }}
                        @elseif($isActive)
                            {{ $callType === 'video' ? 'Video call' : 'Voice call' }} - Join now
                        @else
                            {{ $callType === 'video' ? 'Video call' : 'Voice call' }}
                        @endif
                    </div>
                    @if($duration !== null && $duration > 0)
                        <small class="text-muted">
                            @if($duration < 60)
                                {{ $duration }}s
                            @else
                                {{ gmdate('i:s', $duration) }}
                            @endif
                        </small>
                    @else
                        <small class="text-muted">
                            {{ $endedAt instanceof \Carbon\Carbon ? $endedAt->format('H:i') : \Carbon\Carbon::parse($endedAt)->format('H:i') }}
                        </small>
                    @endif
                    
                    {{-- Call Link Button --}}
                    @if($callLink)
                        <div class="mt-2">
                            @if($isActive)
                                <a href="{{ $callLink }}" 
                                   class="btn btn-sm btn-primary d-inline-flex align-items-center gap-2 join-call-link" 
                                   onclick="event.preventDefault(); if(typeof joinCallFromMessage === 'function') { joinCallFromMessage('{{ $callLink }}'); } else { window.location.href = '{{ $callLink }}'; } return false;">
                                    <i class="bi bi-telephone-fill"></i>
                                    <span>Join Call</span>
                                </a>
                            @else
                                <a href="{{ $callLink }}" 
                                   class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-2 join-call-link" 
                                   onclick="event.preventDefault(); if(typeof joinCallFromMessage === 'function') { joinCallFromMessage('{{ $callLink }}'); } else { window.location.href = '{{ $callLink }}'; } return false;">
                                    <i class="bi bi-info-circle"></i>
                                    <span>View Call</span>
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
                
                @if($isMissed)
                    <div class="call-status-icon">
                        <i class="bi bi-x-circle-fill text-danger" style="font-size: 1.2rem;" aria-hidden="true" title="Missed call"></i>
                    </div>
                @elseif($isActive)
                    <div class="call-status-icon">
                        <i class="bi bi-circle-fill text-success" style="font-size: 0.8rem; animation: pulse 2s infinite;" aria-hidden="true" title="Active call"></i>
                    </div>
                @elseif($isOwn)
                    <div class="call-status-icon">
                        <i class="bi bi-check-circle-fill text-primary" style="font-size: 1.2rem;" aria-hidden="true" title="Outgoing call"></i>
                    </div>
                @else
                    <div class="call-status-icon">
                        <i class="bi bi-arrow-down-circle-fill text-success" style="font-size: 1.2rem;" aria-hidden="true" title="Incoming call"></i>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<style>
.call-card {
    max-width: 320px;
    transition: all 0.3s ease;
    border: 1px solid var(--border-color, #dee2e6) !important;
}

.call-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.call-card-clickable {
    transition: all 0.3s ease;
}

.call-card-clickable:hover {
    background-color: var(--bs-primary-bg-subtle, #e7f1ff) !important;
    border-color: var(--bs-primary, #0d6efd) !important;
}

.call-icon {
    flex-shrink: 0;
}

.call-details {
    min-width: 0;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

[data-theme="dark"] .call-card {
    background: var(--bs-dark-bg-subtle) !important;
    border-color: #444 !important;
}

[data-theme="dark"] .call-details .fw-semibold {
    color: var(--bs-light);
}
</style>
