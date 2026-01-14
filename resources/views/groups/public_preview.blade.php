@extends('layouts.public')

@section('title', $group['name'] . ' - GekyChat')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body text-center p-5">
                    <!-- Group Avatar -->
                    <div class="mb-4">
                        @if($group['avatar_url'])
                            <img src="{{ $group['avatar_url'] }}" alt="{{ $group['name'] }}" 
                                 class="rounded-circle" style="width: 120px; height: 120px; object-fit: cover;">
                        @else
                            <div class="rounded-circle bg-primary d-inline-flex align-items-center justify-content-center" 
                                 style="width: 120px; height: 120px;">
                                <span class="text-white fs-1">{{ substr($group['name'], 0, 1) }}</span>
                            </div>
                        @endif
                    </div>
                    
                    <!-- Group Name -->
                    <h2 class="mb-2">{{ $group['name'] }}</h2>
                    
                    @if($group['is_verified'])
                        <p class="text-muted mb-3">
                            <i class="bi bi-check-circle-fill text-primary"></i> Verified
                        </p>
                    @endif
                    
                    <!-- Group Info -->
                    <div class="mb-4">
                        <p class="text-muted mb-2">
                            <i class="bi bi-people"></i> 
                            {{ $group['member_count'] }} {{ $group['member_count'] == 1 ? 'member' : 'members' }}
                        </p>
                        @if($group['description'])
                            <p class="text-muted">{{ $group['description'] }}</p>
                        @endif
                    </div>
                    
                    <!-- Open in Desktop App Prompt -->
                    <div class="border-top pt-4 mt-4">
                        <p class="text-muted mb-3">Open this group in GekyChat Desktop?</p>
                        <div class="d-flex gap-2 justify-content-center">
                            <button id="open-desktop-btn" class="btn btn-primary">
                                <i class="bi bi-box-arrow-up-right me-2"></i> Open in GekyChat
                            </button>
                            <a href="{{ $webUrl }}" class="btn btn-outline-secondary" target="_blank">
                                <i class="bi bi-globe me-2"></i> Open in Web
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const openBtn = document.getElementById('open-desktop-btn');
    const webUrl = '{{ $webUrl }}';
    
    // Create deep link URL
    const deepLinkUrl = 'gekychat://web?url=' + encodeURIComponent('https://web.gekychat.com' + webUrl.replace('https://web.gekychat.com', ''));
    
    openBtn.addEventListener('click', function() {
        // Try to open desktop app
        window.location.href = deepLinkUrl;
        
        // Fallback: if desktop app doesn't open, redirect to web after a delay
        setTimeout(function() {
            window.location.href = webUrl;
        }, 2000);
    });
});
</script>
@endsection
