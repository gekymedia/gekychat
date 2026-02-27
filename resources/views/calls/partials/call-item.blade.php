@php
    $otherUser = $call->other_user;
    $duration = $call->duration;
    $durationText = null;
    if ($duration !== null && $duration > 0) {
        $durationText = $duration < 60 
            ? "{$duration}s" 
            : gmdate('i:s', $duration);
    }
@endphp
<div class="list-group-item list-group-item-action border-border bg-card call-item">
    <div class="d-flex align-items-center gap-3">
        {{-- Call Icon --}}
        <div class="flex-shrink-0">
            @if($call->type === 'video')
                <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" 
                     style="width: 48px; height: 48px;">
                    <i class="bi bi-camera-video-fill text-primary" style="font-size: 1.25rem;"></i>
                </div>
            @else
                <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" 
                     style="width: 48px; height: 48px;">
                    <i class="bi bi-telephone-fill text-success" style="font-size: 1.25rem;"></i>
                </div>
            @endif
        </div>

        {{-- Call Info --}}
        <div class="flex-grow-1 min-w-0">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <div class="d-flex align-items-center gap-2">
                    <h6 class="mb-0 fw-semibold text-text">
                        {{ $otherUser->name ?? $otherUser->phone ?? 'Unknown' }}
                    </h6>
                    @if($call->is_outgoing)
                        <i class="bi bi-arrow-up-circle text-primary" title="Outgoing call"></i>
                    @else
                        <i class="bi bi-arrow-down-circle text-success" title="Incoming call"></i>
                    @endif
                    @if($call->is_missed)
                        <span class="badge bg-danger">Missed</span>
                    @endif
                </div>
                <small class="text-muted">
                    {{ $call->created_at->format('M d, H:i') }}
                </small>
            </div>
            
            <div class="d-flex align-items-center gap-2">
                <small class="text-muted">
                    {{ $call->type === 'video' ? 'Video call' : 'Voice call' }}
                </small>
                @if($durationText)
                    <span class="text-muted">•</span>
                    <small class="text-muted">{{ $durationText }}</small>
                @elseif($call->status === 'pending')
                    <span class="badge bg-warning">Pending</span>
                @elseif($call->status === 'ongoing')
                    <span class="badge bg-info">Ongoing</span>
                @elseif($call->status === 'ended' && $call->is_missed)
                    <small class="text-muted">No answer</small>
                @endif
            </div>
        </div>
    </div>
</div>
