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
    
    // Check if we're on a chat show page (conversation/group/channel is open)
    function isChatActive() {
        const path = window.location.pathname;
        // Check if we're on /c/{slug} or /g/{slug} (not just /c or /g)
        // Also check for /c/new, /c/start, etc. - these should show chat area
        const isIndexRoute = path === '/c' || path === '/c/' || path === '/g' || path === '/g/';
        if (isIndexRoute) {
            return false; // Index route - show sidebar, hide chat area
        }
        // Check if we're on a specific conversation/group page
        return /^\/(c|g)\/[^\/]+/.test(path);
    }
    
    // Toggle chat active state
    function toggleChatActive(active) {
        const container = getChatContainer();
        if (!container) return;
        
        const backButton = document.getElementById('back-to-conversations');
        
        if (active) {
            container.classList.add('chat-active');
            // Show back button on mobile
            if (isMobile() && backButton) {
                backButton.style.display = 'flex';
            }
        } else {
            container.classList.remove('chat-active');
            // Hide back button on mobile
            if (isMobile() && backButton) {
                backButton.style.display = 'none';
            }
        }
    }
    
    // Initialize mobile chat state
    function initMobileChatState() {
        if (!isMobile()) {
            // Desktop: always show both
            const container = getChatContainer();
            if (container) {
                container.classList.remove('chat-active');
            }
            return;
        }
        
        // Mobile: check if chat is active
        const container = getChatContainer();
        if (!container) return;
        
        if (isChatActive()) {
            // Conversation/group is open - show chat area, hide sidebar
            container.classList.add('chat-active');
        } else {
            // On index route (/c) - show sidebar, hide chat area
            container.classList.remove('chat-active');
        }
    }
    
    // Handle conversation clicks in sidebar
    function setupSidebarLinks() {
        const sidebarLinks = document.querySelectorAll('#conversation-sidebar a[href*="/c/"], #conversation-sidebar a[href*="/g/"]');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                if (isMobile()) {
                    // On mobile, add chat-active class when navigating to a conversation
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
                    // Remove chat-active to show sidebar
                    toggleChatActive(false);
                    
                    // Navigate to chat index
                    const chatIndexUrl = '{{ route("chat.index") }}';
                    if (window.location.pathname !== chatIndexUrl) {
                        window.location.href = chatIndexUrl;
                    }
                } else {
                    // Desktop: just go back
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
        
        // Re-check on resize
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
})();
</script>

