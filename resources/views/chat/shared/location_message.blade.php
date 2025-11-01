@php
    $locationData = $message->location_data ?? [];
    $latitude = $locationData['latitude'] ?? null;
    $longitude = $locationData['longitude'] ?? null;
    $address = $locationData['address'] ?? null;
    $placeName = $locationData['place_name'] ?? null;
    $sharedAt = $locationData['shared_at'] ?? $message->created_at;
@endphp

@if($latitude && $longitude)
<div class="location-message mt-2">
    <div class="location-card rounded border bg-light" role="article" aria-label="Shared location">
        <div class="location-preview position-relative">
            {{-- Static map preview --}}
            <div class="location-map-preview bg-secondary rounded-top d-flex align-items-center justify-content-center text-white" 
                 style="height: 120px; cursor: pointer;"
                 onclick="openLocationInMap({{ $latitude }}, {{ $longitude }}, '{{ addslashes($placeName ?? $address ?? 'Location') }}')">
                <div class="text-center">
                    <i class="bi bi-geo-alt-fill display-6 mb-2" aria-hidden="true"></i>
                    <p class="mb-0 small">View Location</p>
                </div>
            </div>
            
            {{-- Location details --}}
            <div class="location-content p-3">
                @if($placeName)
                    <h6 class="mb-1 fw-semibold text-dark">{{ $placeName }}</h6>
                @endif
                
                @if($address)
                    <p class="mb-1 text-muted small lh-sm">{{ $address }}</p>
                @endif
                
                <small class="text-muted">
                    <i class="bi bi-clock me-1" aria-hidden="true"></i>
                    Shared {{ $sharedAt instanceof \Carbon\Carbon ? $sharedAt->diffForHumans() : \Carbon\Carbon::parse($sharedAt)->diffForHumans() }}
                </small>
                
                <div class="location-actions mt-2 d-flex gap-2">
                    <button class="btn btn-sm btn-outline-primary flex-fill" 
                            onclick="openLocationInMap({{ $latitude }}, {{ $longitude }}, '{{ addslashes($placeName ?? $address ?? 'Location') }}')">
                        <i class="bi bi-map me-1" aria-hidden="true"></i>Open Map
                    </button>
                    
                    <button class="btn btn-sm btn-outline-secondary" 
                            onclick="copyLocationLink({{ $latitude }}, {{ $longitude }})"
                            title="Copy location link">
                        <i class="bi bi-link-45deg" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openLocationInMap(lat, lng, title) {
    const mapsUrl = `https://www.google.com/maps?q=${lat},${lng}&z=15`;
    window.open(mapsUrl, '_blank', 'noopener,noreferrer');
}

function copyLocationLink(lat, lng) {
    const mapsUrl = `https://www.google.com/maps?q=${lat},${lng}&z=15`;
    navigator.clipboard.writeText(mapsUrl).then(() => {
        showToast('Location link copied to clipboard');
    }).catch(() => {
        showToast('Failed to copy location link');
    });
}
</script>

<style>
.location-card {
    transition: all 0.3s ease;
    max-width: 300px;
    border: 1px solid var(--border-color, #dee2e6) !important;
}

.location-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.location-map-preview {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    transition: all 0.3s ease;
}

.location-map-preview:hover {
    filter: brightness(1.1);
}

.location-content h6 {
    color: var(--bs-body-color);
    line-height: 1.3;
}

[data-theme="dark"] .location-card {
    background: var(--bs-dark-bg-subtle) !important;
    border-color: #444 !important;
}

[data-theme="dark"] .location-content h6 {
    color: var(--bs-light);
}
</style>
@endif