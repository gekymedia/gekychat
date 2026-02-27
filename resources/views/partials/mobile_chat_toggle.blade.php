{{-- Mobile Chat Toggle Script --}}
<script>
(function() {
    'use strict';
    
    // Check if we're on mobile
    function isMobile() {
        return window.innerWidth <= 768;
    }
    
    // Get chat container
    function getChatContainer() {
        return document.getElementById('chat-container') || document.querySelector('.d-flex.h-100');
    }
    
    // Determine page type based on URL
    function getPageType() {
        const path = window.location.pathname;
        
        // Full page views - these should hide sidebar on mobile
        const fullPagePatterns = [
            /^\/contacts/,
            /^\/settings/,
            /^\/calls/,
            /^\/world-feed/,
            /^\/ai-chat/,
            /^\/live-broadcast/,
            /^\/broadcast-lists/,
            /^\/status/,
            /^\/sika/,
            /^\/email-chat/,
            /^\/channels$/  // Just /channels, not /channels/{id}
        ];
        
        for (const pattern of fullPagePatterns) {
            if (pattern.test(path)) {
                return 'full-page';
            }
        }
        
        // Chat conversation view - specific chat/group open
        if (/^\/(c|g)\/[^\/]+/.test(path)) {
            return 'chat-conversation';
        }
        
        // Chat list view - index pages
        if (path === '/c' || path === '/c/' || path === '/g' || path === '/g/' || path === '/') {
            return 'chat-list';
        }
        
        // Default to full-page for unknown routes
        return 'full-page';
    }
    
    // Update container classes based on page type
    function updateContainerClasses() {
        const container = getChatContainer();
        if (!container) return;
        
        const pageType = getPageType();
        
        // Remove all page type classes
        container.classList.remove('full-page-view', 'chat-conversation-view', 'chat-list-view', 'chat-active');
        
        // Add appropriate class
        switch (pageType) {
            case 'full-page':
                container.classList.add('full-page-view');
                break;
            case 'chat-conversation':
                container.classList.add('chat-conversation-view');
                if (isMobile()) {
                    container.classList.add('chat-active');
                }
                break;
            case 'chat-list':
                container.classList.add('chat-list-view');
                break;
        }
    }
    
    // Toggle chat active state (for backward compatibility)
    function toggleChatActive(active) {
        const container = getChatContainer();
        if (!container) return;
        
        const backButton = document.getElementById('back-to-conversations');
        
        if (active) {
            container.classList.add('chat-active');
            container.classList.remove('chat-list-view');
            container.classList.add('chat-conversation-view');
            if (isMobile() && backButton) {
                backButton.style.display = 'flex';
            }
        } else {
            container.classList.remove('chat-active');
            container.classList.remove('chat-conversation-view');
            container.classList.add('chat-list-view');
            if (isMobile() && backButton) {
                backButton.style.display = 'none';
            }
        }
    }
    
    // Initialize mobile chat state
    function initMobileChatState() {
        updateContainerClasses();
        
        // Handle back button visibility
        const container = getChatContainer();
        const backButton = document.getElementById('back-to-conversations');
        
        if (isMobile() && backButton) {
            const pageType = getPageType();
            backButton.style.display = (pageType === 'chat-conversation') ? 'flex' : 'none';
        }
    }
    
    // Handle conversation clicks in sidebar
    function setupSidebarLinks() {
        const sidebarLinks = document.querySelectorAll('#conversation-sidebar a[href*="/c/"], #conversation-sidebar a[href*="/g/"]');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                if (isMobile()) {
                    setTimeout(() => {
                        toggleChatActive(true);
                    }, 100);
                }
            });
        });
    }
    
    // Setup back button functionality
    function setupBackButton() {
        const backButton = document.getElementById('back-to-conversations');
        if (backButton) {
            backButton.addEventListener('click', function(e) {
                e.preventDefault();
                
                if (isMobile()) {
                    toggleChatActive(false);
                    const chatIndexUrl = '{{ route("chat.index") }}';
                    if (window.location.pathname !== chatIndexUrl) {
                        window.location.href = chatIndexUrl;
                    }
                } else {
                    window.history.back();
                }
            });
        }
    }
    
    // Handle window resize
    function handleResize() {
        initMobileChatState();
    }
    
    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        initMobileChatState();
        setupSidebarLinks();
        setupBackButton();
        
        // Re-check on resize with debounce
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(handleResize, 250);
        });
        
        // Re-check when navigating (for SPA-like behavior)
        window.addEventListener('popstate', function() {
            setTimeout(initMobileChatState, 100);
        });
    });
    
    // Also check after page load (in case DOMContentLoaded already fired)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMobileChatState);
    } else {
        initMobileChatState();
    }
    
    // Expose for external use
    window.GekyChatMobile = {
        isMobile: isMobile,
        getPageType: getPageType,
        toggleChatActive: toggleChatActive,
        updateContainerClasses: updateContainerClasses
    };
})();
</script>

