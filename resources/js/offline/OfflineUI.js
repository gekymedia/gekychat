/**
 * OfflineUI.js
 * 
 * UI components for offline status indicators
 * Can be integrated into existing chat UI
 */

export class OfflineUI {
    constructor(containerSelector = '.chat-header') {
        this.container = document.querySelector(containerSelector);
        this.statusIndicator = null;
        this.pendingBadge = null;
        
        this.init();
    }

    init() {
        // Create status indicator
        this.createStatusIndicator();
        
        // Listen to connection status changes
        document.addEventListener('connectionStatusChanged', (e) => {
            this.updateConnectionStatus(e.detail);
        });

        // Listen to pending messages count changes
        document.addEventListener('pendingMessagesCountChanged', (e) => {
            this.updatePendingCount(e.detail.count);
        });
    }

    /**
     * Create connection status indicator
     */
    createStatusIndicator() {
        if (!this.container) {
            console.warn('Container not found for offline status indicator');
            return;
        }

        // Create indicator element
        this.statusIndicator = document.createElement('div');
        this.statusIndicator.className = 'offline-status-indicator';
        this.statusIndicator.innerHTML = `
            <span class="status-icon">
                <i class="bi bi-wifi"></i>
            </span>
            <span class="status-text">Online</span>
        `;

        // Add styles
        this.statusIndicator.style.cssText = `
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
            background: rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        `;

        // Insert into container
        this.container.appendChild(this.statusIndicator);

        // Initial status
        this.updateConnectionStatus({ isOnline: navigator.onLine, quality: 'good' });
    }

    /**
     * Update connection status display
     */
    updateConnectionStatus(status) {
        if (!this.statusIndicator) return;

        const icon = this.statusIndicator.querySelector('.status-icon i');
        const text = this.statusIndicator.querySelector('.status-text');

        if (status.isOnline) {
            // Online
            icon.className = 'bi bi-wifi';
            text.textContent = 'Online';
            this.statusIndicator.style.background = 'rgba(34, 197, 94, 0.1)';
            this.statusIndicator.style.color = '#22c55e';
        } else {
            // Offline
            icon.className = 'bi bi-wifi-off';
            text.textContent = 'Offline';
            this.statusIndicator.style.background = 'rgba(239, 68, 68, 0.1)';
            this.statusIndicator.style.color = '#ef4444';
        }

        // Quality indicator
        if (status.quality === 'poor') {
            icon.className = 'bi bi-wifi-1';
            text.textContent = 'Poor Connection';
        } else if (status.quality === 'degraded') {
            icon.className = 'bi bi-wifi-2';
            text.textContent = 'Weak Connection';
        }
    }

    /**
     * Update pending messages count badge
     */
    updatePendingCount(count) {
        if (count === 0) {
            if (this.pendingBadge) {
                this.pendingBadge.remove();
                this.pendingBadge = null;
            }
            return;
        }

        // Create or update badge
        if (!this.pendingBadge) {
            this.pendingBadge = document.createElement('div');
            this.pendingBadge.className = 'pending-messages-badge';
            this.pendingBadge.style.cssText = `
                position: fixed;
                bottom: 1rem;
                right: 1rem;
                background: #3b82f6;
                color: white;
                padding: 0.5rem 1rem;
                border-radius: 0.5rem;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                z-index: 1000;
                display: flex;
                align-items: center;
                gap: 0.5rem;
                cursor: pointer;
                transition: all 0.3s ease;
            `;
            this.pendingBadge.innerHTML = `
                <i class="bi bi-clock"></i>
                <span class="pending-count">${count}</span>
                <span>pending</span>
            `;
            document.body.appendChild(this.pendingBadge);

            // Click to sync
            this.pendingBadge.addEventListener('click', () => {
                document.dispatchEvent(new CustomEvent('forceSync'));
            });
        } else {
            const countElement = this.pendingBadge.querySelector('.pending-count');
            if (countElement) {
                countElement.textContent = count;
            }
        }
    }

    /**
     * Show sync progress indicator
     */
    showSyncProgress() {
        if (this.syncProgress) return;

        this.syncProgress = document.createElement('div');
        this.syncProgress.className = 'sync-progress';
        this.syncProgress.style.cssText = `
            position: fixed;
            top: 1rem;
            right: 1rem;
            background: white;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        `;
        this.syncProgress.innerHTML = `
            <div class="spinner-border spinner-border-sm" role="status">
                <span class="visually-hidden">Syncing...</span>
            </div>
            <span>Syncing messages...</span>
        `;
        document.body.appendChild(this.syncProgress);
    }

    /**
     * Hide sync progress indicator
     */
    hideSyncProgress() {
        if (this.syncProgress) {
            this.syncProgress.remove();
            this.syncProgress = null;
        }
    }

    /**
     * Show toast notification
     */
    showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.style.cssText = `
            position: fixed;
            bottom: 1rem;
            left: 50%;
            transform: translateX(-50%);
            background: ${type === 'error' ? '#ef4444' : type === 'success' ? '#22c55e' : '#3b82f6'};
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 1001;
            animation: slideUp 0.3s ease;
        `;
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'slideDown 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    /**
     * Cleanup
     */
    destroy() {
        if (this.statusIndicator) {
            this.statusIndicator.remove();
        }
        if (this.pendingBadge) {
            this.pendingBadge.remove();
        }
        if (this.syncProgress) {
            this.syncProgress.remove();
        }
    }
}

// Add CSS animations
if (!document.getElementById('offline-ui-styles')) {
    const style = document.createElement('style');
    style.id = 'offline-ui-styles';
    style.textContent = `
        @keyframes slideUp {
            from {
                transform: translateX(-50%) translateY(100%);
                opacity: 0;
            }
            to {
                transform: translateX(-50%) translateY(0);
                opacity: 1;
            }
        }
        
        @keyframes slideDown {
            from {
                transform: translateX(-50%) translateY(0);
                opacity: 1;
            }
            to {
                transform: translateX(-50%) translateY(100%);
                opacity: 0;
            }
        }
        
        .pending-messages-badge:hover {
            background: #2563eb !important;
            transform: scale(1.05);
        }
    `;
    document.head.appendChild(style);
}
