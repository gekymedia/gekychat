/**
 * TikTok-style video player with draggable progress bar for World Feed
 */
class WorldVideoPlayer {
    constructor(videoElement) {
        this.video = videoElement;
        this.container = videoElement.parentElement;
        this.isDragging = false;
        this.init();
    }

    init() {
        this.createControls();
        this.attachEventListeners();
    }

    createControls() {
        // Progress bar container
        const progressContainer = document.createElement('div');
        progressContainer.className = 'world-video-progress-container';
        progressContainer.innerHTML = `
            <div class="world-video-progress-bar">
                <div class="world-video-progress-filled"></div>
                <div class="world-video-progress-handle"></div>
            </div>
            <div class="world-video-time-labels">
                <span class="world-video-time-current">0:00</span>
                <span class="world-video-time-total">0:00</span>
            </div>
        `;
        
        this.container.appendChild(progressContainer);
        
        this.progressBar = progressContainer.querySelector('.world-video-progress-bar');
        this.progressFilled = progressContainer.querySelector('.world-video-progress-filled');
        this.progressHandle = progressContainer.querySelector('.world-video-progress-handle');
        this.timeCurrent = progressContainer.querySelector('.world-video-time-current');
        this.timeTotal = progressContainer.querySelector('.world-video-time-total');
    }

    attachEventListeners() {
        // Update progress as video plays
        this.video.addEventListener('timeupdate', () => this.updateProgress());
        this.video.addEventListener('loadedmetadata', () => this.updateTotalTime());
        
        // Progress bar interactions
        this.progressBar.addEventListener('mousedown', (e) => this.startDrag(e));
        this.progressBar.addEventListener('touchstart', (e) => this.startDrag(e));
        
        document.addEventListener('mousemove', (e) => this.drag(e));
        document.addEventListener('touchmove', (e) => this.drag(e));
        
        document.addEventListener('mouseup', () => this.endDrag());
        document.addEventListener('touchend', () => this.endDrag());
        
        // Click to seek
        this.progressBar.addEventListener('click', (e) => this.seek(e));
    }

    startDrag(e) {
        this.isDragging = true;
        this.progressHandle.classList.add('dragging');
        this.drag(e);
    }

    drag(e) {
        if (!this.isDragging) return;
        
        e.preventDefault();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const rect = this.progressBar.getBoundingClientRect();
        const pos = (clientX - rect.left) / rect.width;
        const clampedPos = Math.max(0, Math.min(1, pos));
        
        this.progressFilled.style.width = `${clampedPos * 100}%`;
        this.progressHandle.style.left = `${clampedPos * 100}%`;
        
        // Update time label
        const newTime = clampedPos * this.video.duration;
        this.timeCurrent.textContent = this.formatTime(newTime);
    }

    endDrag() {
        if (!this.isDragging) return;
        
        this.isDragging = false;
        this.progressHandle.classList.remove('dragging');
        
        // Seek to new position
        const pos = parseFloat(this.progressFilled.style.width) / 100;
        this.video.currentTime = pos * this.video.duration;
    }

    seek(e) {
        if (this.isDragging) return;
        
        const rect = this.progressBar.getBoundingClientRect();
        const pos = (e.clientX - rect.left) / rect.width;
        const clampedPos = Math.max(0, Math.min(1, pos));
        
        this.video.currentTime = clampedPos * this.video.duration;
    }

    updateProgress() {
        if (this.isDragging) return;
        
        const progress = (this.video.currentTime / this.video.duration) * 100;
        this.progressFilled.style.width = `${progress}%`;
        this.progressHandle.style.left = `${progress}%`;
        this.timeCurrent.textContent = this.formatTime(this.video.currentTime);
    }

    updateTotalTime() {
        this.timeTotal.textContent = this.formatTime(this.video.duration);
    }

    formatTime(seconds) {
        if (isNaN(seconds)) return '0:00';
        
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }
}

// Auto-initialize all world feed videos
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.world-feed-video').forEach(video => {
        new WorldVideoPlayer(video);
    });
});
