@extends('layouts.app')

@section('title', 'Audio Library - ' . config('app.name'))

@section('content')
<div class="h-100 d-flex flex-column">
    <!-- Header -->
    <div class="chat-header border-bottom p-3">
        <div class="d-flex align-items-center justify-content-between">
            <h4 class="mb-0">Audio Library</h4>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" id="trending-tab-btn">
                    <i class="bi bi-fire me-1"></i> Trending
                </button>
                <button class="btn btn-sm btn-wa active" id="search-tab-btn">
                    <i class="bi bi-search me-1"></i> Search
                </button>
            </div>
        </div>
    </div>
    
    <!-- Search Bar -->
    <div class="p-3 border-bottom" id="search-section">
        <div class="input-group">
            <input type="text" class="form-control" id="audio-search-input" placeholder="Search for sounds...">
            <button class="btn btn-wa" id="search-btn">
                <i class="bi bi-search"></i>
            </button>
        </div>
    </div>
    
    <!-- Audio List -->
    <div class="flex-grow-1 overflow-auto p-3" id="audio-list-container">
        <div class="text-center py-5 text-muted">
            <i class="bi bi-music-note-beamed" style="font-size: 48px;"></i>
            <p class="mt-3">Search for sounds or browse trending</p>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentTab = 'search';
    let currentlyPlayingAudio = null;
    let audioElement = new Audio();
    
    const searchInput = document.getElementById('audio-search-input');
    const searchBtn = document.getElementById('search-btn');
    const audioListContainer = document.getElementById('audio-list-container');
    const searchTabBtn = document.getElementById('search-tab-btn');
    const trendingTabBtn = document.getElementById('trending-tab-btn');
    const searchSection = document.getElementById('search-section');
    
    // Tab switching
    searchTabBtn.addEventListener('click', () => {
        currentTab = 'search';
        searchTabBtn.classList.add('active');
        trendingTabBtn.classList.remove('active');
        searchSection.style.display = 'block';
        audioListContainer.innerHTML = `
            <div class="text-center py-5 text-muted">
                <i class="bi bi-music-note-beamed" style="font-size: 48px;"></i>
                <p class="mt-3">Search for sounds to add to your videos</p>
            </div>
        `;
    });
    
    trendingTabBtn.addEventListener('click', () => {
        currentTab = 'trending';
        trendingTabBtn.classList.add('active');
        searchTabBtn.classList.remove('active');
        searchSection.style.display = 'none';
        loadTrending();
    });
    
    // Search
    searchBtn.addEventListener('click', performSearch);
    searchInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') performSearch();
    });
    
    async function performSearch() {
        const query = searchInput.value.trim();
        if (!query) return;
        
        audioListContainer.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Searching...</span>
                </div>
            </div>
        `;
        
        try {
            const response = await fetch(`/audio/search?q=${encodeURIComponent(query)}&max_duration=120`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) throw new Error('Search failed');
            
            const data = await response.json();
            renderAudioList(data.data);
        } catch (error) {
            console.error('Search error:', error);
            audioListContainer.innerHTML = `
                <div class="alert alert-danger">
                    Failed to search audio. Please try again.
                </div>
            `;
        }
    }
    
    async function loadTrending() {
        audioListContainer.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `;
        
        try {
            const response = await fetch('/audio/trending', {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) throw new Error('Failed to load trending');
            
            const data = await response.json();
            renderAudioList({ cached: data.data, freesound: [] });
        } catch (error) {
            console.error('Trending error:', error);
            audioListContainer.innerHTML = `
                <div class="alert alert-danger">
                    Failed to load trending audio. Please try again.
                </div>
            `;
        }
    }
    
    function renderAudioList(data) {
        const cached = data.cached || [];
        const freesound = data.freesound || [];
        const allAudio = [...cached, ...freesound];
        
        if (allAudio.length === 0) {
            audioListContainer.innerHTML = `
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-music-note-beamed" style="font-size: 48px;"></i>
                    <p class="mt-3">No sounds found</p>
                </div>
            `;
            return;
        }
        
        audioListContainer.innerHTML = '';
        
        allAudio.forEach(audio => {
            const card = document.createElement('div');
            card.className = 'card mb-3';
            
            const duration = audio.duration || 0;
            const minutes = Math.floor(duration / 60);
            const seconds = Math.floor(duration % 60);
            const durationText = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            const license = audio.license_type || audio.license || 'Unknown';
            const isCC0 = license.includes('CC0');
            const attributionRequired = audio.attribution_required || false;
            
            card.innerHTML = `
                <div class="card-body">
                    <div class="d-flex align-items-start">
                        <div class="flex-shrink-0 me-3">
                            <div class="rounded-circle bg-wa d-flex align-items-center justify-content-center" 
                                 style="width: 48px; height: 48px;">
                                <i class="bi bi-music-note text-white"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1">${escapeHtml(audio.name || 'Unknown')}</h6>
                            <small class="text-muted">
                                by ${escapeHtml(audio.freesound_username || audio.username || 'Unknown')} • ${durationText}
                            </small>
                            <div class="mt-2">
                                <span class="badge ${isCC0 ? 'bg-success' : 'bg-warning'} me-2">
                                    ${isCC0 ? 'CC0' : 'CC BY'}
                                </span>
                                ${attributionRequired ? '<i class="bi bi-info-circle text-warning" title="Attribution required"></i>' : ''}
                            </div>
                            ${attributionRequired ? `
                                <div class="alert alert-warning mt-2 mb-0 py-1 px-2 small">
                                    <i class="bi bi-info-circle me-1"></i>
                                    ${escapeHtml(audio.attribution_text || '')}
                                </div>
                            ` : ''}
                        </div>
                        <div class="flex-shrink-0 ms-3">
                            <button class="btn btn-sm btn-outline-wa me-2 preview-btn" data-audio-id="${audio.id}" data-preview-url="${escapeHtml(audio.preview_url || '')}">
                                <i class="bi bi-play-circle"></i> Preview
                            </button>
                            <button class="btn btn-sm btn-wa select-btn" data-audio='${JSON.stringify(audio)}'>
                                <i class="bi bi-check-circle"></i> Select
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            audioListContainer.appendChild(card);
        });
        
        // Attach event listeners
        document.querySelectorAll('.preview-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const audioId = parseInt(this.dataset.audioId);
                const previewUrl = this.dataset.previewUrl;
                playPreview(audioId, previewUrl, this);
            });
        });
        
        document.querySelectorAll('.select-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const audio = JSON.parse(this.dataset.audio);
                selectAudio(audio);
            });
        });
    }
    
    function playPreview(audioId, previewUrl, btnElement) {
        // If already playing this audio, stop it
        if (currentlyPlayingAudio === audioId) {
            audioElement.pause();
            currentlyPlayingAudio = null;
            btnElement.innerHTML = '<i class="bi bi-play-circle"></i> Preview';
            return;
        }
        
        // Stop any currently playing audio
        audioElement.pause();
        
        // Reset all preview buttons
        document.querySelectorAll('.preview-btn').forEach(btn => {
            btn.innerHTML = '<i class="bi bi-play-circle"></i> Preview';
        });
        
        // Play new audio
        audioElement.src = previewUrl;
        audioElement.play();
        currentlyPlayingAudio = audioId;
        btnElement.innerHTML = '<i class="bi bi-stop-circle"></i> Stop';
        
        // Auto-stop after 10 seconds
        setTimeout(() => {
            if (currentlyPlayingAudio === audioId) {
                audioElement.pause();
                currentlyPlayingAudio = null;
                btnElement.innerHTML = '<i class="bi bi-play-circle"></i> Preview';
            }
        }, 10000);
        
        // Listen for completion
        audioElement.onended = () => {
            currentlyPlayingAudio = null;
            btnElement.innerHTML = '<i class="bi bi-play-circle"></i> Preview';
        };
    }
    
    function selectAudio(audio) {
        // Store selected audio in sessionStorage for use in create post page
        sessionStorage.setItem('selectedAudio', JSON.stringify(audio));
        
        // Also use localStorage as backup (for same-window communication)
        localStorage.setItem('selectedAudio', JSON.stringify(audio));
        
        // Trigger custom event
        window.dispatchEvent(new CustomEvent('audioSelected', { detail: audio }));
        
        // Show success message
        showToast('Audio selected: ' + audio.name, 'success');
        
        // Close modal or navigate back
        if (window.opener) {
            // If opened as popup, trigger event in parent
            window.opener.postMessage({ type: 'audioSelected', audio: audio }, '*');
            window.close();
        } else {
            // If same window, just close or navigate
            setTimeout(() => {
                window.close();
            }, 1000);
        }
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} position-fixed top-0 start-50 translate-middle-x mt-3`;
        toast.style.zIndex = '9999';
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }
});
</script>
@endpush
@endsection
