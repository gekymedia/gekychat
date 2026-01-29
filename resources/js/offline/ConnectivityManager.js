/**
 * ConnectivityManager.js
 * 
 * Monitors network connectivity and provides reliable online/offline detection
 */

export class ConnectivityManager {
    constructor() {
        this.isOnline = navigator.onLine;
        this.listeners = new Set();
        this.qualityCheckInterval = null;
        this.connectionQuality = 'good'; // good, degraded, poor, offline
        
        this.init();
    }

    init() {
        // Listen to browser online/offline events
        window.addEventListener('online', () => this.handleOnline());
        window.addEventListener('offline', () => this.handleOffline());

        // Start periodic connectivity checks
        this.startQualityMonitoring();

        // Initial check
        this.checkConnectivity();
    }

    /**
     * Handle browser online event
     */
    handleOnline() {
        this.log('Browser reports online');
        this.checkConnectivity().then(() => {
            this.notifyListeners('online');
        });
    }

    /**
     * Handle browser offline event
     */
    handleOffline() {
        this.log('Browser reports offline');
        this.isOnline = false;
        this.connectionQuality = 'offline';
        this.notifyListeners('offline');
    }

    /**
     * Check actual connectivity by attempting a lightweight request
     */
    async checkConnectivity() {
        // Quick check: try to fetch a small resource
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 3000);

            const response = await fetch('/api/v1/health', {
                method: 'HEAD',
                signal: controller.signal,
                cache: 'no-cache'
            }).catch(() => {
                // Fallback: try to ping a known endpoint
                return fetch(window.location.origin + '/favicon.ico', {
                    method: 'HEAD',
                    signal: controller.signal,
                    cache: 'no-cache'
                });
            });

            clearTimeout(timeoutId);

            if (response.ok || response.status < 500) {
                this.isOnline = true;
                this.updateConnectionQuality('good');
                return true;
            } else {
                this.isOnline = false;
                this.connectionQuality = 'poor';
                this.notifyListeners('offline');
                return false;
            }
        } catch (error) {
            this.isOnline = false;
            this.connectionQuality = 'offline';
            this.notifyListeners('offline');
            return false;
        }
    }

    /**
     * Start monitoring connection quality
     */
    startQualityMonitoring() {
        // Check every 30 seconds
        this.qualityCheckInterval = setInterval(() => {
            if (navigator.onLine) {
                this.checkConnectivity();
            }
        }, 30000);
    }

    /**
     * Stop quality monitoring
     */
    stopQualityMonitoring() {
        if (this.qualityCheckInterval) {
            clearInterval(this.qualityCheckInterval);
            this.qualityCheckInterval = null;
        }
    }

    /**
     * Update connection quality based on recent checks
     */
    updateConnectionQuality(quality) {
        const previousQuality = this.connectionQuality;
        this.connectionQuality = quality;

        if (previousQuality !== quality) {
            this.notifyListeners('qualityChanged', quality);
        }
    }

    /**
     * Register a listener for connectivity changes
     */
    onConnectivityChange(callback) {
        this.listeners.add(callback);
        return () => this.listeners.delete(callback);
    }

    /**
     * Notify all listeners of connectivity changes
     */
    notifyListeners(event, data = null) {
        this.listeners.forEach(callback => {
            try {
                callback({ event, isOnline: this.isOnline, quality: this.connectionQuality, data });
            } catch (error) {
                console.error('Error in connectivity listener:', error);
            }
        });
    }

    /**
     * Get current connectivity status
     */
    getStatus() {
        return {
            isOnline: this.isOnline,
            quality: this.connectionQuality,
            browserOnline: navigator.onLine
        };
    }

    /**
     * Wait for online connectivity
     */
    async waitForOnline(timeout = 60000) {
        if (this.isOnline) {
            return Promise.resolve(true);
        }

        return new Promise((resolve, reject) => {
            const timeoutId = setTimeout(() => {
                unsubscribe();
                reject(new Error('Timeout waiting for online'));
            }, timeout);

            const unsubscribe = this.onConnectivityChange((status) => {
                if (status.isOnline) {
                    clearTimeout(timeoutId);
                    unsubscribe();
                    resolve(true);
                }
            });

            // Also check immediately
            this.checkConnectivity().then((online) => {
                if (online) {
                    clearTimeout(timeoutId);
                    unsubscribe();
                    resolve(true);
                }
            });
        });
    }

    /**
     * Logging helper
     */
    log(message, data = null) {
        if (window.ChatCore?.config?.debug) {
            console.log(`[ConnectivityManager] ${message}`, data);
        }
    }

    /**
     * Cleanup
     */
    destroy() {
        this.stopQualityMonitoring();
        window.removeEventListener('online', this.handleOnline);
        window.removeEventListener('offline', this.handleOffline);
        this.listeners.clear();
    }
}

// Export singleton instance
export const connectivityManager = new ConnectivityManager();
