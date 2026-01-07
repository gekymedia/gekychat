@extends('layouts.app')

@section('title', 'Live Broadcast - ' . config('app.name', 'GekyChat'))

{{-- Sidebar data is loaded by controller --}}

@section('content')
<div class="h-100 d-flex flex-column">
    <div class="chat-header border-bottom p-3">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <h4 class="mb-0">Live Broadcasts</h4>
                <small class="text-muted">Start or join live broadcasts</small>
            </div>
            <button class="btn btn-wa btn-sm" id="start-broadcast-btn">
                <i class="bi bi-camera-video-fill me-1"></i> Go Live
            </button>
        </div>
    </div>
    
    <div class="flex-grow-1 overflow-auto p-4">
        <!-- Active Broadcasts -->
        <div id="active-broadcasts" class="mb-4">
            <h5 class="mb-3">Active Broadcasts</h5>
            <div id="broadcasts-list" class="row g-3">
                <div class="text-center py-5 w-100">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-muted mt-2">Loading broadcasts...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Start Broadcast Modal -->
<div class="modal fade" id="startBroadcastModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Start Live Broadcast</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="start-broadcast-form">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" required maxlength="100" placeholder="What are you broadcasting about?">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" maxlength="500" placeholder="Optional description"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-wa">Start Broadcast</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    async function loadBroadcasts() {
        try {
            const response = await fetch('/api/v1/live-broadcasts?status=live', {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            
            if (!response.ok) throw new Error('Failed to load broadcasts');
            
            const data = await response.json();
            const broadcasts = data.data || [];
            renderBroadcasts(broadcasts);
        } catch (error) {
            console.error('Error loading broadcasts:', error);
            document.getElementById('broadcasts-list').innerHTML = 
                '<div class="col-12"><div class="alert alert-warning">No active broadcasts</div></div>';
        }
    }
    
    function renderBroadcasts(broadcasts) {
        const container = document.getElementById('broadcasts-list');
        
        if (broadcasts.length === 0) {
            container.innerHTML = '<div class="col-12"><div class="alert alert-info">No active broadcasts. Be the first to go live!</div></div>';
            return;
        }
        
        container.innerHTML = broadcasts.map(broadcast => `
            <div class="col-md-6 col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">${broadcast.title || 'Untitled Broadcast'}</h6>
                        <p class="card-text small text-muted">${broadcast.description || ''}</p>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="bi bi-eye"></i> ${broadcast.viewers_count || 0} viewers
                            </small>
                            <a href="/live/${broadcast.id}" class="btn btn-sm btn-wa">Watch</a>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    }
    
    document.getElementById('start-broadcast-btn')?.addEventListener('click', () => {
        const modal = new bootstrap.Modal(document.getElementById('startBroadcastModal'));
        modal.show();
    });
    
    document.getElementById('start-broadcast-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        
        try {
            const response = await fetch('/api/v1/live-broadcasts', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: formData
            });
            
            if (response.ok) {
                const data = await response.json();
                bootstrap.Modal.getInstance(document.getElementById('startBroadcastModal')).hide();
                // Redirect to broadcast page or show success
                window.location.href = `/live/${data.data.id}`;
            } else {
                alert('Failed to start broadcast');
            }
        } catch (error) {
            console.error('Error starting broadcast:', error);
            alert('Failed to start broadcast');
        }
    });
    
    loadBroadcasts();
    // Refresh every 10 seconds
    setInterval(loadBroadcasts, 10000);
});
</script>
@endpush
@endsection
