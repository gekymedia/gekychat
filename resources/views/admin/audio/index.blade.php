@extends('layouts.admin')

@section('title', 'Audio Library Management')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0">Audio Library</h2>
                <a href="{{ route('audio.browse') }}" class="btn btn-wa" target="_blank">
                    <i class="bi bi-search me-1"></i> Browse Audio
                </a>
            </div>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Total Audio</h6>
                    <h3 class="mb-0">{{ number_format($stats['total']) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Active</h6>
                    <h3 class="mb-0 text-success">{{ number_format($stats['active']) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-2">CC0 (Public Domain)</h6>
                    <h3 class="mb-0 text-info">{{ number_format($stats['cc0']) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Total Usage</h6>
                    <h3 class="mb-0 text-primary">{{ number_format($stats['total_usage']) }}</h3>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.audio.index') }}" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Search audio..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="license" class="form-select">
                        <option value="">All Licenses</option>
                        <option value="CC0" {{ request('license') == 'CC0' ? 'selected' : '' }}>CC0</option>
                        <option value="Attribution" {{ request('license') == 'Attribution' ? 'selected' : '' }}>Attribution</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Audio List -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Artist</th>
                            <th>Duration</th>
                            <th>License</th>
                            <th>Usage</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($audio as $item)
                        <tr>
                            <td>{{ $item->id }}</td>
                            <td>
                                <strong>{{ $item->name }}</strong>
                                @if($item->attribution_required)
                                    <br><small class="text-warning"><i class="bi bi-info-circle"></i> Attribution required</small>
                                @endif
                            </td>
                            <td>{{ $item->freesound_username ?? 'Unknown' }}</td>
                            <td>{{ $item->formatted_duration }}</td>
                            <td>
                                <span class="badge {{ str_contains($item->license_type, 'CC0') ? 'bg-success' : 'bg-warning' }}">
                                    {{ $item->license_type }}
                                </span>
                            </td>
                            <td>{{ number_format($item->usage_count) }}</td>
                            <td>
                                <span class="badge bg-{{ $item->validation_status === 'approved' ? 'success' : ($item->validation_status === 'rejected' ? 'danger' : 'warning') }}">
                                    {{ ucfirst($item->validation_status) }}
                                </span>
                                @if($item->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary preview-audio" data-url="{{ $item->preview_url }}">
                                        <i class="bi bi-play"></i>
                                    </button>
                                    <button class="btn btn-outline-{{ $item->is_active ? 'warning' : 'success' }} toggle-status" data-id="{{ $item->id }}">
                                        <i class="bi bi-{{ $item->is_active ? 'pause' : 'play' }}"></i>
                                    </button>
                                    <button class="btn btn-outline-info view-details" data-id="{{ $item->id }}">
                                        <i class="bi bi-info-circle"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                No audio found
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="mt-3">
                {{ $audio->links() }}
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
let audioPlayer = new Audio();
let currentlyPlaying = null;

// Preview audio
document.querySelectorAll('.preview-audio').forEach(btn => {
    btn.addEventListener('click', function() {
        const url = this.dataset.url;
        
        if (currentlyPlaying === url) {
            audioPlayer.pause();
            currentlyPlaying = null;
            this.innerHTML = '<i class="bi bi-play"></i>';
        } else {
            audioPlayer.pause();
            audioPlayer.src = url;
            audioPlayer.play();
            currentlyPlaying = url;
            
            // Reset all buttons
            document.querySelectorAll('.preview-audio').forEach(b => {
                b.innerHTML = '<i class="bi bi-play"></i>';
            });
            this.innerHTML = '<i class="bi bi-stop"></i>';
            
            // Auto-stop after 10 seconds
            setTimeout(() => {
                if (currentlyPlaying === url) {
                    audioPlayer.pause();
                    currentlyPlaying = null;
                    this.innerHTML = '<i class="bi bi-play"></i>';
                }
            }, 10000);
        }
    });
});

// Toggle status
document.querySelectorAll('.toggle-status').forEach(btn => {
    btn.addEventListener('click', async function() {
        const id = this.dataset.id;
        
        try {
            const response = await fetch(`/admin/audio/${id}/toggle-status`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });
            
            if (response.ok) {
                location.reload();
            } else {
                alert('Failed to toggle status');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to toggle status');
        }
    });
});
</script>
@endpush
@endsection
