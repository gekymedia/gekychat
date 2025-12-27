@php
    $locationData = $message->location_data ?? [];
    $latitude = $locationData['latitude'] ?? null;
    $longitude = $locationData['longitude'] ?? null;
    $address = $locationData['address'] ?? null;
    $placeName = $locationData['place_name'] ?? null;
    $sharedAt = $locationData['shared_at'] ?? $message->created_at;
@endphp

@if(!empty($locationData) && $latitude && $longitude)
<div class="location-message mt-2">
    <div class="location-card rounded border bg-light" role="article" aria-label="Shared location">
        <div class="location-preview position-relative">
            {{-- Google Maps preview --}}
            <div class="location-map-preview rounded-top position-relative" 
                 style="height: 200px; width: 100%; overflow: hidden; cursor: pointer; background: #f0f0f0;"
                 onclick="openLocationInMap({{ $latitude }}, {{ $longitude }}, '{{ addslashes($placeName ?? $address ?? 'Location') }}')"
                 title="Click to open in Google Maps">
                @php
                    $mapsApiKey = config('services.google_maps.api_key', '');
                    $staticMapUrl = $mapsApiKey 
                        ? "https://maps.googleapis.com/maps/api/staticmap?center={$latitude},{$longitude}&zoom=15&size=600x200&maptype=roadmap&markers=color:red%7C{$latitude},{$longitude}&key={$mapsApiKey}"
                        : null;
                @endphp
                @if($staticMapUrl)
                    <img 
                        src="{{ $staticMapUrl }}"
                        alt="Location map"
                        style="width: 100%; height: 100%; object-fit: cover;"
                        onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'d-flex align-items-center justify-content-center h-100 text-center\'><div><i class=\'bi bi-geo-alt-fill display-4 text-muted mb-2\'></i><p class=\'mb-0 small text-muted\'>Click to view location</p><p class=\'mb-0 text-muted\' style=\'font-size: 0.75rem;\'>{{ $latitude }}, {{ $longitude }}</p></div></div>';"
                    >
                @else
                    {{-- Fallback when no API key is configured --}}
                    <div class="d-flex align-items-center justify-content-center h-100 text-center">
                        <div>
                            <i class="bi bi-geo-alt-fill display-4 text-primary mb-2" aria-hidden="true"></i>
                            <p class="mb-0 small text-muted">Click to view location</p>
                            <p class="mb-0 text-muted" style="font-size: 0.75rem;">{{ number_format($latitude, 6) }}, {{ number_format($longitude, 6) }}</p>
                        </div>
                    </div>
                @endif
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
    background: #f0f0f0;
    transition: all 0.3s ease;
    position: relative;
}

.location-map-preview:hover {
    opacity: 0.9;
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