/**
 * PWA Install Prompt Handler
 * Manages the "Add to Home Screen" functionality
 */

let deferredPrompt = null;
let installButton = null;

// Initialize PWA install functionality
function initPWAInstall() {
    // Listen for beforeinstallprompt event
    window.addEventListener('beforeinstallprompt', (e) => {
        // Prevent the mini-infobar from appearing on mobile
        e.preventDefault();
        
        // Stash the event so it can be triggered later
        deferredPrompt = e;
        
        // Show install button
        showInstallButton();
        
        console.log('[PWA] Install prompt available');
    });
    
    // Listen for app installed event
    window.addEventListener('appinstalled', () => {
        console.log('[PWA] App installed successfully');
        deferredPrompt = null;
        hideInstallButton();
        
        // Track installation
        if (typeof gtag !== 'undefined') {
            gtag('event', 'pwa_install', {
                event_category: 'PWA',
                event_label: 'App Installed',
            });
        }
    });
    
    // Check if already installed
    if (window.matchMedia('(display-mode: standalone)').matches) {
        console.log('[PWA] App is running in standalone mode');
        hideInstallButton();
    }
}

// Show install button
function showInstallButton() {
    installButton = document.getElementById('pwa-install-btn');
    
    if (installButton) {
        installButton.style.display = 'block';
        installButton.addEventListener('click', handleInstallClick);
    } else {
        // Create install button if it doesn't exist
        createInstallButton();
    }
}

// Hide install button
function hideInstallButton() {
    if (installButton) {
        installButton.style.display = 'none';
    }
}

// Create install button dynamically
function createInstallButton() {
    const button = document.createElement('button');
    button.id = 'pwa-install-btn';
    button.className = 'btn btn-primary pwa-install-button';
    button.innerHTML = '<i class="bi bi-download"></i> Install App';
    button.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 1000;
        padding: 12px 24px;
        border-radius: 25px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        animation: slideInUp 0.3s ease-out;
    `;
    
    button.addEventListener('click', handleInstallClick);
    document.body.appendChild(button);
    installButton = button;
}

// Handle install button click
async function handleInstallClick() {
    if (!deferredPrompt) {
        console.log('[PWA] Install prompt not available');
        return;
    }
    
    // Show the install prompt
    deferredPrompt.prompt();
    
    // Wait for the user to respond to the prompt
    const { outcome } = await deferredPrompt.userChoice;
    
    console.log(`[PWA] User response: ${outcome}`);
    
    // Track user choice
    if (typeof gtag !== 'undefined') {
        gtag('event', 'pwa_install_prompt', {
            event_category: 'PWA',
            event_label: outcome,
        });
    }
    
    // Clear the deferredPrompt
    deferredPrompt = null;
    
    // Hide the install button
    if (outcome === 'accepted') {
        hideInstallButton();
    }
}

// Check for iOS and show custom install instructions
function checkiOSInstall() {
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    const isInStandaloneMode = ('standalone' in window.navigator) && (window.navigator.standalone);
    
    if (isIOS && !isInStandaloneMode) {
        showIOSInstallInstructions();
    }
}

// Show iOS install instructions
function showIOSInstallInstructions() {
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = 'ios-install-modal';
    modal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Install GekyChat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <p>To install GekyChat on your iOS device:</p>
                    <ol class="text-start">
                        <li>Tap the Share button <i class="bi bi-box-arrow-up"></i></li>
                        <li>Scroll down and tap "Add to Home Screen"</li>
                        <li>Tap "Add" to confirm</li>
                    </ol>
                    <img src="/images/ios-install.png" alt="iOS Install Instructions" class="img-fluid mt-3" style="max-height: 200px;">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Show modal (requires Bootstrap)
    if (typeof bootstrap !== 'undefined') {
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    }
}

// Register service worker
async function registerServiceWorker() {
    if ('serviceWorker' in navigator) {
        try {
            const registration = await navigator.serviceWorker.register('/service-worker.js', {
                scope: '/',
            });
            
            console.log('[PWA] Service Worker registered:', registration.scope);
            
            // Check for updates periodically
            setInterval(() => {
                registration.update();
            }, 60 * 60 * 1000); // Check every hour
            
            // Listen for updates
            registration.addEventListener('updatefound', () => {
                const newWorker = registration.installing;
                
                newWorker.addEventListener('statechange', () => {
                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                        // New service worker available
                        showUpdateNotification();
                    }
                });
            });
            
            return registration;
        } catch (error) {
            console.error('[PWA] Service Worker registration failed:', error);
        }
    }
}

// Show update notification
function showUpdateNotification() {
    const toast = document.createElement('div');
    toast.className = 'toast align-items-center text-white bg-primary border-0';
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                A new version of GekyChat is available!
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-footer p-2 pt-0">
            <button class="btn btn-sm btn-light w-100" onclick="updateServiceWorker()">
                Update Now
            </button>
        </div>
    `;
    
    // Add to toast container
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(container);
    }
    
    container.appendChild(toast);
    
    // Show toast (requires Bootstrap)
    if (typeof bootstrap !== 'undefined') {
        const bsToast = new bootstrap.Toast(toast, { autohide: false });
        bsToast.show();
    }
}

// Update service worker
function updateServiceWorker() {
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistration().then((registration) => {
            if (registration && registration.waiting) {
                // Tell the waiting service worker to activate
                registration.waiting.postMessage('SKIP_WAITING');
                
                // Reload the page
                window.location.reload();
            }
        });
    }
}

// Initialize on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        registerServiceWorker();
        initPWAInstall();
        checkiOSInstall();
    });
} else {
    registerServiceWorker();
    initPWAInstall();
    checkiOSInstall();
}

// Export functions for global use
window.PWA = {
    install: handleInstallClick,
    update: updateServiceWorker,
    checkiOS: checkiOSInstall,
};
