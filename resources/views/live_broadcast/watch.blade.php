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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const broadcastId = {{ $broadcastId ?? 'null' }};
    
    if (!broadcastId) {
        document.getElementById('broadcast-container').innerHTML = `
            <div class="alert alert-danger">
                Invalid broadcast ID
            </div>
        `;
        return;
    }
    
    // TODO: Integrate LiveKit SDK for actual video streaming
    // For now, show a placeholder
    loadBroadcastInfo(broadcastId);
    
    async function loadBroadcastInfo(id) {
        try {
            const response = await fetch(`/live-broadcast/${id}/info`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                if (response.status === 404) {
                    throw new Error('Broadcast not found');
                }
                throw new Error('Failed to load broadcast');
            }
            
            const data = await response.json();
            const broadcast = data.data || data;
            
            document.getElementById('broadcast-title').textContent = broadcast.title || 'Untitled Broadcast';
            
            // Check if broadcast is still live
            if (broadcast.status !== 'live') {
                document.getElementById('broadcast-container').innerHTML = `
                    <div class="alert alert-warning">
                        <h5>${escapeHtml(broadcast.title || 'Untitled Broadcast')}</h5>
                        <p>This broadcast has ended.</p>
                        <p class="mb-0">It ended at: ${new Date(broadcast.ended_at).toLocaleString()}</p>
                    </div>
                `;
                return;
            }
            
            document.getElementById('broadcast-container').innerHTML = `
                <div class="alert alert-info">
                    <h5>${escapeHtml(broadcast.title || 'Untitled Broadcast')}</h5>
                    ${broadcast.description ? `<p>${escapeHtml(broadcast.description)}</p>` : ''}
                    <p>Live broadcast streaming will be integrated here using LiveKit SDK.</p>
                    <p class="mb-0">Viewers: ${broadcast.viewers_count || 0}</p>
                </div>
            `;
        } catch (error) {
            console.error('Error loading broadcast:', error);
            document.getElementById('broadcast-container').innerHTML = `
                <div class="alert alert-danger">
                    ${error.message || 'Failed to load broadcast. Please try again.'}
                </div>
            `;
        }
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>
@endpush
@endsection
