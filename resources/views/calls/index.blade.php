{{-- calls/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Call Logs - ' . config('app.name', 'GekyChat'))

@php
    // Set sidebar variables (same as chat.index)
    $convShowBase = '/c/';
    $groupShowBase = '/g/';
    $totalCalls = $calls->total();
    $perPage = $calls->perPage();
    $hasMorePages = $calls->hasMorePages();
@endphp

@section('content')
<div class="container-fluid h-100">
    <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-card border-bottom border-border py-3">
            <div class="d-flex align-items-center">
                <a href="{{ route('chat.index') }}" class="btn btn-link text-decoration-none p-0 me-3 d-md-none" title="Back to Chats">
                    <i class="bi bi-arrow-left" style="font-size: 1.5rem;"></i>
                </a>
                <div>
                    <h1 class="h4 mb-0 fw-bold text-text">Call Logs</h1>
                    <p class="text-muted mb-0">View your call history</p>
                </div>
            </div>
        </div>

        <div class="card-body bg-bg p-0" id="calls-container" style="max-height: calc(100vh - 200px); overflow-y: auto;">
            @if($calls->count() > 0)
                <div class="list-group list-group-flush" id="calls-list">
                    @foreach($calls as $call)
                        @include('calls.partials.call-item', ['call' => $call])
                    @endforeach
                </div>

                {{-- Load More / Show Less Controls --}}
                @if($totalCalls > $perPage)
                <div class="calls-pagination-controls py-3 px-3 bg-card border-top border-border">
                    <div class="d-flex flex-column align-items-center gap-2">
                        {{-- Load More Button --}}
                        @if($hasMorePages)
                        <button type="button" 
                                class="btn btn-outline-secondary btn-sm w-100" 
                                id="load-more-calls"
                                data-page="2"
                                data-loading="false">
                            <i class="bi bi-chevron-down me-2"></i>
                            <span class="btn-text">Load More</span>
                            <span class="spinner-border spinner-border-sm ms-2 d-none" role="status"></span>
                        </button>
                        @endif
                        
                        {{-- Show Less Button (hidden initially) --}}
                        <button type="button" 
                                class="btn btn-outline-secondary btn-sm w-100 d-none" 
                                id="show-less-calls">
                            <i class="bi bi-chevron-up me-2"></i>
                            <span class="btn-text">Show Less</span>
                        </button>
                        
                        {{-- Count indicator --}}
                        <small class="text-muted" id="calls-count-indicator">
                            Showing <span id="shown-count">{{ $calls->count() }}</span> of {{ $totalCalls }} calls
                        </small>
                    </div>
                </div>
                @endif
            @else
                {{-- Empty State --}}
                <div class="text-center py-5">
                    <div class="empty-state-icon mb-4">
                        <i class="bi bi-telephone-x display-1 text-muted"></i>
                    </div>
                    <h4 class="text-muted mb-3">No call history</h4>
                    <p class="text-muted mb-0">Your call logs will appear here</p>
                </div>
            @endif
        </div>
    </div>
</div>

<style>
.calls-pagination-controls {
    position: sticky;
    bottom: 0;
    z-index: 10;
}

.calls-pagination-controls .btn {
    max-width: 300px;
    transition: all 0.2s ease;
}

.calls-pagination-controls .btn:hover {
    transform: translateY(-1px);
}

.calls-pagination-controls .btn i {
    transition: transform 0.2s ease;
}

.calls-pagination-controls .btn:hover i {
    transform: translateY(2px);
}

#show-less-calls:hover i {
    transform: translateY(-2px) !important;
}

@media (max-width: 768px) {
    .calls-pagination-controls .btn {
        max-width: 100%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const callsList = document.getElementById('calls-list');
    const loadMoreBtn = document.getElementById('load-more-calls');
    const showLessBtn = document.getElementById('show-less-calls');
    const shownCountEl = document.getElementById('shown-count');
    const totalCalls = {{ $totalCalls }};
    const perPage = {{ $perPage }};
    let initialCallsHtml = callsList ? callsList.innerHTML : '';
    let currentPage = 1;
    let allLoadedCalls = [];
    
    // Store initial calls
    if (callsList) {
        allLoadedCalls = Array.from(callsList.children);
    }
    
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', async function() {
            if (this.dataset.loading === 'true') return;
            
            const nextPage = parseInt(this.dataset.page);
            this.dataset.loading = 'true';
            
            // Show loading state
            const btnText = this.querySelector('.btn-text');
            const spinner = this.querySelector('.spinner-border');
            btnText.textContent = 'Loading...';
            spinner.classList.remove('d-none');
            this.disabled = true;
            
            try {
                const response = await fetch(`{{ route('calls.index') }}?page=${nextPage}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (!response.ok) throw new Error('Failed to load calls');
                
                const data = await response.json();
                
                if (data.html && data.html.trim()) {
                    // Append new calls
                    callsList.insertAdjacentHTML('beforeend', data.html);
                    
                    // Update stored calls
                    allLoadedCalls = Array.from(callsList.children);
                    
                    // Update page counter
                    this.dataset.page = nextPage + 1;
                    currentPage = nextPage;
                    
                    // Update shown count
                    if (shownCountEl) {
                        shownCountEl.textContent = allLoadedCalls.length;
                    }
                    
                    // Show "Show Less" button if we've loaded more than initial
                    if (allLoadedCalls.length > perPage && showLessBtn) {
                        showLessBtn.classList.remove('d-none');
                    }
                    
                    // Hide load more if no more pages
                    if (!data.hasMore) {
                        this.classList.add('d-none');
                    }
                }
            } catch (error) {
                console.error('Error loading more calls:', error);
                alert('Failed to load more calls. Please try again.');
            } finally {
                // Reset button state
                btnText.textContent = 'Load More';
                spinner.classList.add('d-none');
                this.disabled = false;
                this.dataset.loading = 'false';
            }
        });
    }
    
    if (showLessBtn) {
        showLessBtn.addEventListener('click', function() {
            if (!callsList) return;
            
            // Keep only the first page of calls
            const children = Array.from(callsList.children);
            children.forEach((child, index) => {
                if (index >= perPage) {
                    child.remove();
                }
            });
            
            // Update stored calls
            allLoadedCalls = Array.from(callsList.children);
            
            // Update shown count
            if (shownCountEl) {
                shownCountEl.textContent = allLoadedCalls.length;
            }
            
            // Reset load more button
            if (loadMoreBtn) {
                loadMoreBtn.dataset.page = '2';
                loadMoreBtn.classList.remove('d-none');
            }
            
            // Hide show less button
            this.classList.add('d-none');
            
            // Scroll to top of calls list
            const container = document.getElementById('calls-container');
            if (container) {
                container.scrollTo({ top: 0, behavior: 'smooth' });
            }
            
            currentPage = 1;
        });
    }
});
</script>
@endsection
