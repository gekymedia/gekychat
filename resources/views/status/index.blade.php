@extends('layouts.app')

@section('title', 'Status - ' . config('app.name'))

@push('styles')
<style>
    .status-ring {
        border: 3px solid #25D366;
        border-radius: 50%;
        padding: 3px;
        cursor: pointer;
        transition: transform 0.2s;
    }
    .status-ring:hover {
        transform: scale(1.05);
    }
    .status-ring.viewed {
        border-color: #8696a0;
    }
    .status-ring.no-status {
        border-color: transparent;
    }
    .status-avatar {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        object-fit: cover;
    }
    .status-avatar-sm {
        width: 40px;
        height: 40px;
    }
    
    /* Status Viewer Overlay */
    .status-viewer {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: #000;
        z-index: 9999;
        display: none;
        flex-direction: column;
    }
    .status-viewer.active {
        display: flex;
    }
    .status-progress-bar {
        display: flex;
        gap: 4px;
        padding: 12px 16px 8px;
        background: linear-gradient(rgba(0,0,0,0.6), transparent);
    }
    .status-progress-segment {
        flex: 1;
        height: 3px;
        background: rgba(255,255,255,0.3);
        border-radius: 2px;
        overflow: hidden;
    }
    .status-progress-segment .fill {
        height: 100%;
        background: white;
        width: 0%;
    }
    .status-header {
        padding: 8px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        color: white;
    }
    .status-content {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        overflow: hidden;
    }
    .status-content img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }
    .status-content video {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }
    .status-text-content {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px;
        text-align: center;
        word-break: break-word;
    }
    .status-caption {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 20px;
        background: linear-gradient(transparent, rgba(0,0,0,0.8));
        color: white;
        text-align: center;
    }
    .status-nav {
        position: absolute;
        top: 0;
        bottom: 0;
        width: 30%;
        cursor: pointer;
        z-index: 10;
    }
    .status-nav.prev { left: 0; }
    .status-nav.next { right: 0; }
    
    .my-status-add-btn {
        position: absolute;
        bottom: -4px;
        right: -4px;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
    }
    
    .status-item {
        cursor: pointer;
        padding: 12px;
        border-radius: 12px;
        transition: background 0.2s;
    }
    .status-item:hover {
        background: var(--bg-hover, rgba(0,0,0,0.05));
    }
</style>
@endpush

@section('content')
<div class="h-100 d-flex flex-column">
    <div class="chat-header border-bottom p-3">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <a href="{{ route('chat.index') }}" class="btn btn-link text-decoration-none p-0 me-3 d-md-none" title="Back to Chats">
                    <i class="bi bi-arrow-left" style="font-size: 1.5rem;"></i>
                </a>
                <div>
                    <h4 class="mb-0"><i class="bi bi-circle-fill text-success me-2" style="font-size: 12px;"></i>Status</h4>
                    <small class="text-muted">24-hour stories from your contacts</small>
                </div>
            </div>
            <button class="btn btn-wa btn-sm" id="create-status-btn">
                <i class="bi bi-plus-lg me-1"></i> Add Status
            </button>
        </div>
    </div>
    
    <div class="flex-grow-1 overflow-auto">
        <!-- My Status -->
        <div class="p-3 border-bottom">
            <div class="d-flex align-items-center status-item" id="my-status-container">
                <div class="position-relative me-3">
                    <div class="status-ring no-status" id="my-status-ring">
                        <img src="{{ auth()->user()->avatar_url ?? '/images/default-avatar.png' }}" 
                             class="status-avatar" 
                             alt="My status"
                             onerror="this.src='/images/default-avatar.png'">
                    </div>
                    <button class="btn btn-wa btn-sm rounded-circle my-status-add-btn" id="add-status-btn-small">
                        <i class="bi bi-plus"></i>
                    </button>
                </div>
                <div class="flex-grow-1">
                    <strong>My Status</strong>
                    <br>
                    <small class="text-muted" id="my-status-time">Tap to add status update</small>
                </div>
            </div>
        </div>
        
        <!-- Recent Updates -->
        <div class="p-3">
            <h6 class="text-muted mb-3 px-2">Recent Updates</h6>
            <div id="status-list">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted mt-2 mb-0">Loading statuses...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Status Viewer -->
<div class="status-viewer" id="status-viewer">
    <div class="status-progress-bar" id="status-progress"></div>
    <div class="status-header">
        <div class="d-flex align-items-center">
            <img id="viewer-avatar" src="" class="rounded-circle me-3 status-avatar-sm" onerror="this.src='/images/default-avatar.png'">
            <div>
                <strong id="viewer-name"></strong>
                <br>
                <small id="viewer-time" class="opacity-75"></small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-link text-white p-1" id="pause-status" title="Pause">
                <i class="bi bi-pause-fill" style="font-size: 24px;"></i>
            </button>
            <button class="btn btn-link text-white p-1" id="close-viewer" title="Close">
                <i class="bi bi-x-lg" style="font-size: 24px;"></i>
            </button>
        </div>
    </div>
    <div class="status-content" id="status-content">
        <div class="status-nav prev" id="nav-prev"></div>
        <div class="status-nav next" id="nav-next"></div>
    </div>
</div>

<!-- Create Status Modal -->
<div class="modal fade" id="createStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Create Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="create-status-form" enctype="multipart/form-data">
                <div class="modal-body">
                    <!-- Status Type Tabs -->
                    <ul class="nav nav-pills nav-fill mb-3" id="status-type-tabs">
                        <li class="nav-item">
                            <button class="nav-link active" type="button" data-type="text">
                                <i class="bi bi-fonts me-1"></i> Text
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" type="button" data-type="media">
                                <i class="bi bi-image me-1"></i> Photo/Video
                            </button>
                        </li>
                    </ul>
                    
                    <!-- Text Status -->
                    <div id="text-status-form">
                        <div class="mb-3">
                            <textarea name="content" class="form-control" rows="4" maxlength="500" placeholder="What's on your mind?" style="font-size: 18px;"></textarea>
                            <small class="text-muted"><span id="text-count">0</span>/500</small>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small">Background Color</label>
                                <input type="color" name="background_color" class="form-control form-control-color w-100" value="#128C7E">
                            </div>
                            <div class="col-6">
                                <label class="form-label small">Text Color</label>
                                <input type="color" name="text_color" class="form-control form-control-color w-100" value="#FFFFFF">
                            </div>
                        </div>
                        <!-- Preview -->
                        <div class="mb-3">
                            <label class="form-label small">Preview</label>
                            <div id="text-preview" class="rounded p-4 text-center" style="min-height: 150px; background: #128C7E; color: #FFFFFF; font-size: 18px; display: flex; align-items: center; justify-content: center;">
                                Your status preview
                            </div>
                        </div>
                    </div>
                    
                    <!-- Media Status -->
                    <div id="media-status-form" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">Photo or Video</label>
                            <input type="file" name="media" class="form-control" accept="image/*,video/*">
                            <small class="text-muted">Max 10MB. Supported: JPG, PNG, GIF, MP4, WebM</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Caption (optional)</label>
                            <textarea name="caption" class="form-control" rows="3" maxlength="200" placeholder="Add a caption..."></textarea>
                        </div>
                        <div id="media-preview" class="text-center" style="display: none;">
                            <img id="media-preview-img" src="" class="img-fluid rounded" style="max-height: 300px; display: none;">
                            <video id="media-preview-video" src="" class="img-fluid rounded" style="max-height: 300px; display: none;" controls></video>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-wa">
                        <i class="bi bi-send me-1"></i> Post Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let allStatusGroups = [];
    let currentGroupIndex = 0;
    let currentStatusIndex = 0;
    let statusTimer = null;
    let isPaused = false;
    let statusDuration = 5000;
    let currentStatusType = 'text';
    
    loadStatuses();
    loadMyStatuses();
    
    // Create status buttons
    ['create-status-btn', 'add-status-btn-small'].forEach(id => {
        document.getElementById(id)?.addEventListener('click', (e) => {
            e.stopPropagation();
            const modal = new bootstrap.Modal(document.getElementById('createStatusModal'));
            modal.show();
        });
    });
    
    // My status click - view own statuses
    document.getElementById('my-status-container')?.addEventListener('click', async function(e) {
        if (e.target.closest('#add-status-btn-small')) return;
        
        const myStatusRing = document.getElementById('my-status-ring');
        if (!myStatusRing.classList.contains('no-status')) {
            // Has statuses, view them
            try {
                const response = await fetch(`/status/user/{{ auth()->id() }}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    credentials: 'same-origin'
                });
                const data = await response.json();
                if (data.success && data.updates && data.updates.length > 0) {
                    const myGroup = {
                        user: {
                            id: {{ auth()->id() }},
                            name: 'My Status',
                            avatar_url: '{{ auth()->user()->avatar_url ?? "/images/default-avatar.png" }}'
                        },
                        statuses: data.updates
                    };
                    viewStatuses([myGroup], 0);
                }
            } catch (error) {
                console.error('Error loading my statuses:', error);
            }
        } else {
            // No statuses, open create modal
            const modal = new bootstrap.Modal(document.getElementById('createStatusModal'));
            modal.show();
        }
    });
    
    // Status type tabs
    document.querySelectorAll('#status-type-tabs .nav-link').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('#status-type-tabs .nav-link').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            currentStatusType = this.dataset.type;
            
            document.getElementById('text-status-form').style.display = currentStatusType === 'text' ? 'block' : 'none';
            document.getElementById('media-status-form').style.display = currentStatusType === 'media' ? 'block' : 'none';
        });
    });
    
    // Text preview
    const contentTextarea = document.querySelector('#text-status-form textarea[name="content"]');
    const bgColorInput = document.querySelector('input[name="background_color"]');
    const textColorInput = document.querySelector('input[name="text_color"]');
    const textPreview = document.getElementById('text-preview');
    const textCount = document.getElementById('text-count');
    
    function updateTextPreview() {
        const text = contentTextarea.value || 'Your status preview';
        textPreview.textContent = text;
        textPreview.style.background = bgColorInput.value;
        textPreview.style.color = textColorInput.value;
        textCount.textContent = contentTextarea.value.length;
    }
    
    contentTextarea?.addEventListener('input', updateTextPreview);
    bgColorInput?.addEventListener('input', updateTextPreview);
    textColorInput?.addEventListener('input', updateTextPreview);
    
    // Media preview
    const mediaInput = document.querySelector('input[name="media"]');
    mediaInput?.addEventListener('change', function() {
        const file = this.files[0];
        const previewContainer = document.getElementById('media-preview');
        const imgPreview = document.getElementById('media-preview-img');
        const videoPreview = document.getElementById('media-preview-video');
        
        if (file) {
            const url = URL.createObjectURL(file);
            if (file.type.startsWith('image/')) {
                imgPreview.src = url;
                imgPreview.style.display = 'block';
                videoPreview.style.display = 'none';
            } else if (file.type.startsWith('video/')) {
                videoPreview.src = url;
                videoPreview.style.display = 'block';
                imgPreview.style.display = 'none';
            }
            previewContainer.style.display = 'block';
        } else {
            previewContainer.style.display = 'none';
        }
    });
    
    // Create status form
    document.getElementById('create-status-form')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        // Validate
        if (currentStatusType === 'text' && !formData.get('content')?.trim()) {
            alert('Please enter some text for your status');
            return;
        }
        if (currentStatusType === 'media' && !formData.get('media')?.size) {
            alert('Please select a photo or video');
            return;
        }
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Posting...';
        
        try {
            const response = await fetch('/status', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                credentials: 'same-origin',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('createStatusModal')).hide();
                this.reset();
                updateTextPreview();
                document.getElementById('media-preview').style.display = 'none';
                loadMyStatuses();
                showToast('Status posted!', 'success');
            } else {
                alert(data.message || 'Failed to post status');
            }
        } catch (error) {
            console.error('Error posting status:', error);
            alert('Failed to post status');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
    
    // Viewer controls
    document.getElementById('close-viewer')?.addEventListener('click', closeViewer);
    document.getElementById('nav-prev')?.addEventListener('click', () => navigateStatus(-1));
    document.getElementById('nav-next')?.addEventListener('click', () => navigateStatus(1));
    document.getElementById('pause-status')?.addEventListener('click', togglePause);
    
    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (!document.getElementById('status-viewer').classList.contains('active')) return;
        
        if (e.key === 'ArrowLeft') navigateStatus(-1);
        else if (e.key === 'ArrowRight') navigateStatus(1);
        else if (e.key === 'Escape') closeViewer();
        else if (e.key === ' ') { e.preventDefault(); togglePause(); }
    });
    
    async function loadStatuses() {
        try {
            const response = await fetch('/status', {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                credentials: 'same-origin'
            });
            
            const data = await response.json();
            allStatusGroups = data.statuses || [];
            
            renderStatusList();
        } catch (error) {
            console.error('Error loading statuses:', error);
            document.getElementById('status-list').innerHTML = `
                <div class="text-center py-5">
                    <i class="bi bi-exclamation-triangle text-warning" style="font-size: 48px;"></i>
                    <p class="text-muted mt-3">Failed to load statuses</p>
                    <button class="btn btn-outline-primary btn-sm" onclick="location.reload()">Retry</button>
                </div>
            `;
        }
    }
    
    async function loadMyStatuses() {
        try {
            const response = await fetch(`/status/user/{{ auth()->id() }}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                credentials: 'same-origin'
            });
            
            const data = await response.json();
            const myStatuses = data.updates || [];
            
            const myStatusRing = document.getElementById('my-status-ring');
            const myStatusTime = document.getElementById('my-status-time');
            
            if (myStatuses.length > 0) {
                myStatusRing.classList.remove('no-status');
                myStatusRing.classList.add('status-ring');
                const latest = myStatuses[myStatuses.length - 1];
                myStatusTime.textContent = getTimeAgo(new Date(latest.created_at));
            } else {
                myStatusRing.classList.add('no-status');
                myStatusRing.classList.remove('viewed');
                myStatusTime.textContent = 'Tap to add status update';
            }
        } catch (error) {
            console.error('Error loading my statuses:', error);
        }
    }
    
    function renderStatusList() {
        const list = document.getElementById('status-list');
        
        if (allStatusGroups.length === 0) {
            list.innerHTML = `
                <div class="text-center py-5">
                    <i class="bi bi-circle text-muted" style="font-size: 48px;"></i>
                    <p class="text-muted mt-3 mb-0">No status updates from your contacts</p>
                    <p class="text-muted small">Status updates disappear after 24 hours</p>
                </div>
            `;
            return;
        }
        
        list.innerHTML = allStatusGroups.map((group, index) => {
            const hasUnviewed = group.unviewed_count > 0;
            const user = group.user;
            const latestStatus = group.statuses[0];
            
            return `
                <div class="d-flex align-items-center status-item" data-index="${index}">
                    <div class="status-ring ${hasUnviewed ? '' : 'viewed'} me-3">
                        <img src="${escapeHtml(user.avatar_url || '/images/default-avatar.png')}" 
                             class="status-avatar" 
                             alt="${escapeHtml(user.name)}"
                             onerror="this.src='/images/default-avatar.png'">
                    </div>
                    <div class="flex-grow-1">
                        <strong>${escapeHtml(user.name)}</strong>
                        <br>
                        <small class="text-muted">${latestStatus.time_ago || getTimeAgo(new Date(latestStatus.created_at))}</small>
                    </div>
                    ${hasUnviewed ? `<span class="badge bg-success rounded-pill">${group.unviewed_count}</span>` : ''}
                </div>
            `;
        }).join('');
        
        // Attach click handlers
        document.querySelectorAll('.status-item[data-index]').forEach(item => {
            item.addEventListener('click', function() {
                const index = parseInt(this.dataset.index);
                viewStatuses(allStatusGroups, index);
            });
        });
    }
    
    function viewStatuses(groups, groupIndex) {
        allStatusGroups = groups;
        currentGroupIndex = groupIndex;
        currentStatusIndex = 0;
        isPaused = false;
        
        const viewer = document.getElementById('status-viewer');
        viewer.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        showCurrentStatus();
    }
    
    function showCurrentStatus() {
        const group = allStatusGroups[currentGroupIndex];
        if (!group) { closeViewer(); return; }
        
        const status = group.statuses[currentStatusIndex];
        if (!status) { closeViewer(); return; }
        
        // Update header
        document.getElementById('viewer-avatar').src = group.user.avatar_url || '/images/default-avatar.png';
        document.getElementById('viewer-name').textContent = group.user.name || 'Unknown';
        document.getElementById('viewer-time').textContent = status.time_ago || getTimeAgo(new Date(status.created_at));
        
        // Update progress bar
        const progressBar = document.getElementById('status-progress');
        progressBar.innerHTML = group.statuses.map((_, i) => `
            <div class="status-progress-segment">
                <div class="fill" style="width: ${i < currentStatusIndex ? '100%' : '0%'}"></div>
            </div>
        `).join('');
        
        // Show content
        const content = document.getElementById('status-content');
        const navPrev = content.querySelector('.status-nav.prev');
        const navNext = content.querySelector('.status-nav.next');
        
        // Clear previous content (keep nav elements)
        Array.from(content.children).forEach(child => {
            if (!child.classList.contains('status-nav')) {
                child.remove();
            }
        });
        
        if (status.type === 'text' || (!status.media_url && status.text)) {
            const textDiv = document.createElement('div');
            textDiv.className = 'status-text-content';
            textDiv.style.background = status.background_color || '#128C7E';
            textDiv.style.color = status.text_color || '#FFFFFF';
            textDiv.style.fontSize = (status.font_size || 24) + 'px';
            textDiv.textContent = status.text || status.content || '';
            content.insertBefore(textDiv, navPrev);
        } else if (status.type === 'video' || (status.media_url && status.media_url.match(/\.(mp4|webm|mov)$/i))) {
            const video = document.createElement('video');
            video.src = status.media_url.startsWith('/') ? status.media_url : '/storage/' + status.media_url;
            video.autoplay = true;
            video.playsInline = true;
            video.muted = false;
            video.onended = () => navigateStatus(1);
            content.insertBefore(video, navPrev);
            
            if (status.text || status.content) {
                const caption = document.createElement('div');
                caption.className = 'status-caption';
                caption.textContent = status.text || status.content;
                content.appendChild(caption);
            }
        } else {
            const img = document.createElement('img');
            img.src = status.media_url.startsWith('/') ? status.media_url : '/storage/' + status.media_url;
            img.onerror = () => { img.src = '/images/placeholder.png'; };
            content.insertBefore(img, navPrev);
            
            if (status.text || status.content) {
                const caption = document.createElement('div');
                caption.className = 'status-caption';
                caption.textContent = status.text || status.content;
                content.appendChild(caption);
            }
        }
        
        // Mark as viewed
        if (status.id && !status.viewed) {
            markStatusViewed(status.id);
        }
        
        // Start timer (not for videos - they use onended)
        if (status.type !== 'video') {
            startStatusTimer();
        }
    }
    
    function startStatusTimer() {
        if (statusTimer) clearInterval(statusTimer);
        if (isPaused) return;
        
        const startTime = Date.now();
        const progressFill = document.querySelectorAll('.status-progress-segment')[currentStatusIndex]?.querySelector('.fill');
        
        statusTimer = setInterval(() => {
            if (isPaused) return;
            
            const elapsed = Date.now() - startTime;
            const progress = Math.min((elapsed / statusDuration) * 100, 100);
            
            if (progressFill) {
                progressFill.style.width = progress + '%';
                progressFill.style.transition = 'none';
            }
            
            if (elapsed >= statusDuration) {
                clearInterval(statusTimer);
                navigateStatus(1);
            }
        }, 50);
    }
    
    function navigateStatus(direction) {
        if (statusTimer) clearInterval(statusTimer);
        
        const group = allStatusGroups[currentGroupIndex];
        if (!group) { closeViewer(); return; }
        
        if (direction > 0) {
            if (currentStatusIndex < group.statuses.length - 1) {
                currentStatusIndex++;
            } else if (currentGroupIndex < allStatusGroups.length - 1) {
                currentGroupIndex++;
                currentStatusIndex = 0;
            } else {
                closeViewer();
                return;
            }
        } else {
            if (currentStatusIndex > 0) {
                currentStatusIndex--;
            } else if (currentGroupIndex > 0) {
                currentGroupIndex--;
                const prevGroup = allStatusGroups[currentGroupIndex];
                currentStatusIndex = prevGroup.statuses.length - 1;
            }
        }
        
        showCurrentStatus();
    }
    
    function togglePause() {
        isPaused = !isPaused;
        const pauseBtn = document.getElementById('pause-status');
        pauseBtn.innerHTML = isPaused 
            ? '<i class="bi bi-play-fill" style="font-size: 24px;"></i>'
            : '<i class="bi bi-pause-fill" style="font-size: 24px;"></i>';
        
        if (!isPaused) {
            startStatusTimer();
        }
        
        // Pause/play video if present
        const video = document.querySelector('#status-content video');
        if (video) {
            isPaused ? video.pause() : video.play();
        }
    }
    
    function closeViewer() {
        if (statusTimer) clearInterval(statusTimer);
        document.getElementById('status-viewer').classList.remove('active');
        document.body.style.overflow = '';
        loadStatuses(); // Refresh to update viewed status
    }
    
    async function markStatusViewed(statusId) {
        try {
            await fetch(`/status/${statusId}/view`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                credentials: 'same-origin'
            });
        } catch (error) {
            console.error('Error marking status as viewed:', error);
        }
    }
    
    function getTimeAgo(date) {
        const seconds = Math.floor((new Date() - date) / 1000);
        if (seconds < 60) return 'Just now';
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return `${minutes}m ago`;
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `${hours}h ago`;
        return 'Yesterday';
    }
    
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} position-fixed shadow-lg`;
        toast.style.cssText = 'bottom: 20px; left: 50%; transform: translateX(-50%); z-index: 99999; min-width: 250px;';
        toast.innerHTML = `<i class="bi bi-check-circle me-2"></i>${escapeHtml(message)}`;
        document.body.appendChild(toast);
        setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 3000);
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>
@endpush
@endsection
