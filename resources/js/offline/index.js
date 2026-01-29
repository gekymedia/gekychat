/**
 * Offline Module Entry Point
 * 
 * Exports all offline functionality for easy importing
 */

export { OfflineStorage, offlineStorage } from './OfflineStorage.js';
export { ConnectivityManager, connectivityManager } from './ConnectivityManager.js';
export { SyncManager } from './SyncManager.js';
export { OfflineChatCore } from './OfflineChatCore.js';
export { OfflineUI } from './OfflineUI.js';

// Make available globally for blade templates
if (typeof window !== 'undefined') {
    import('./OfflineStorage.js').then(module => {
        window.OfflineStorage = module.OfflineStorage;
        window.offlineStorage = module.offlineStorage;
    });
    
    import('./ConnectivityManager.js').then(module => {
        window.ConnectivityManager = module.ConnectivityManager;
        window.connectivityManager = module.connectivityManager;
    });
    
    import('./SyncManager.js').then(module => {
        window.SyncManager = module.SyncManager;
    });
    
    import('./OfflineChatCore.js').then(module => {
        window.OfflineChatCore = module.OfflineChatCore;
    });
    
    import('./OfflineUI.js').then(module => {
        window.OfflineUI = module.OfflineUI;
    });
}
