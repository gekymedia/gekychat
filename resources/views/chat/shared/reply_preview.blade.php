{{-- resources/views/chat/shared/reply_preview.blade.php --}}
@php
  $context = $context ?? 'direct'; // 'direct' or 'group'
  $isGroup = $context === 'group';
@endphp

<div class="reply-preview-container border-top bg-card" id="reply-preview" style="display: none;" 
     data-context="{{ $context }}" aria-live="polite" role="region" aria-label="Reply preview">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center py-2">
      {{-- Reply Info --}}
      <div class="flex-grow-1 min-width-0">
        <div class="d-flex align-items-center gap-2 mb-1">
          <small class="muted d-flex align-items-center gap-1">
            <i class="bi bi-reply-fill text-primary" aria-hidden="true"></i>
            <span>Replying to</span>
            @if($isGroup)
              <span class="reply-sender-name text-muted" id="reply-sender-name"></span>
            @endif
          </small>
        </div>
        <div class="reply-preview-content text-truncate text-break" 
             style="max-height: 3em; line-height: 1.5; overflow: hidden;"
             aria-live="polite"
             aria-label="Message being replied to">
          {{-- Preview content will be inserted here by JavaScript --}}
        </div>
      </div>

      {{-- Cancel Reply Button --}}
      <button class="btn btn-sm btn-outline-danger ms-3 flex-shrink-0" id="cancel-reply" 
              aria-label="Cancel reply" title="Cancel reply"
              data-bs-toggle="tooltip">
        <i class="bi bi-x-lg" aria-hidden="true"></i>
      </button>
    </div>
  </div>
</div>

{{-- Inline Styles for Reply Preview --}}
<style>
.reply-preview-container {
  background: var(--card);
  border-top: 1px solid var(--border);
  transition: all 0.2s ease;
  backdrop-filter: blur(10px);
}

.reply-preview-container.showing {
  animation: slideUp 0.2s ease-out;
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.reply-preview-content {
  color: var(--text);
  font-size: 0.875rem;
  word-wrap: break-word;
}

#cancel-reply {
  border-radius: 8px;
  transition: all 0.2s ease;
}

#cancel-reply:hover {
  transform: scale(1.05);
  background: var(--bs-danger);
  color: white;
}

.reply-sender-name {
  font-weight: 500;
  font-size: 0.8rem;
}

/* Group-specific styling */
[data-context="group"] .reply-preview-container {
  border-top-color: var(--group-border);
}

[data-context="group"] .reply-sender-name {
  color: var(--group-accent);
}

/* Responsive design */
@media (max-width: 768px) {
  .reply-preview-container {
    padding-left: 8px;
    padding-right: 8px;
  }
  
  .reply-preview-content {
    font-size: 0.8rem;
    max-height: 2.5em;
  }
}

/* High contrast mode support */
@media (prefers-contrast: high) {
  .reply-preview-container {
    border-top-width: 2px;
  }
  
  #cancel-reply {
    border-width: 2px;
  }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
  .reply-preview-container {
    transition: none;
    animation: none;
  }
  
  #cancel-reply:hover {
    transform: none;
  }
}
</style>

{{-- JavaScript Integration --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
  const replyPreview = document.getElementById('reply-preview');
  const cancelReplyBtn = document.getElementById('cancel-reply');
  const replyPreviewContent = replyPreview?.querySelector('.reply-preview-content');
  const replySenderName = replyPreview?.querySelector('.reply-sender-name');
  
  // Global function to show reply preview
  window.showReplyPreview = function(messageText, senderName = null, messageId = null) {
    if (!replyPreview || !replyPreviewContent) return;
    
    console.log('Showing reply preview:', { messageText, senderName, messageId });
    
    // Set the preview content
    const previewText = messageText.length > 120 ? messageText.slice(0, 120) + '…' : messageText;
    replyPreviewContent.textContent = previewText;
    replyPreviewContent.setAttribute('aria-label', `Replying to: ${previewText}`);
    
    // Set sender name for group chats
    if (senderName && replySenderName) {
      replySenderName.textContent = senderName;
    }
    
    // Store message ID if provided
    if (messageId) {
      replyPreview.dataset.replyToId = messageId;
    }
    
    // Show the preview
    replyPreview.style.display = 'block';
    replyPreview.classList.add('showing');
    
    // Focus on message input if it exists
    const messageInput = document.getElementById('message-input');
    if (messageInput) {
      setTimeout(() => messageInput.focus(), 100);
    }
  };
  
  // Global function to hide reply preview
  window.hideReplyPreview = function() {
    if (!replyPreview) return;
    
    console.log('Hiding reply preview');
    
    replyPreview.style.display = 'none';
    replyPreview.classList.remove('showing');
    replyPreviewContent.textContent = '';
    replyPreviewContent.removeAttribute('aria-label');
    
    if (replySenderName) {
      replySenderName.textContent = '';
    }
    
    // Clear stored message ID
    delete replyPreview.dataset.replyToId;
    
    // Clear reply input if it exists
    const replyInput = document.getElementById('reply-to') || document.getElementById('reply-to-id');
    if (replyInput) {
      replyInput.value = '';
    }
  };
  
  // Cancel reply button event listener
  if (cancelReplyBtn) {
    cancelReplyBtn.addEventListener('click', function(e) {
      e.preventDefault();
      window.hideReplyPreview();
    });
  }
  
  // Escape key to cancel reply
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && replyPreview?.style.display === 'block') {
      window.hideReplyPreview();
    }
  });
  
  // Handle reply button clicks from message actions
  document.addEventListener('click', function(e) {
    if (e.target.closest('.reply-btn')) {
      const replyBtn = e.target.closest('.reply-btn');
      const messageId = replyBtn.dataset.messageId;
      const messageElement = replyBtn.closest('.message');
      
      if (!messageElement) return;
      
      // Find the message text content
      const messageTextElement = messageElement.querySelector('.message-text');
      let messageText = '';
      
      if (messageTextElement) {
        // Get text content without HTML tags
        messageText = messageTextElement.textContent || messageTextElement.innerText || '';
      }
      
      // Find sender name for group chats
      let senderName = null;
      if (replyPreview?.dataset.context === 'group') {
        const senderNameElement = messageElement.querySelector('.sender-name');
        if (senderNameElement) {
          senderName = senderNameElement.textContent || '';
        }
      }
      
      console.log('Reply button clicked:', { messageId, messageText, senderName });
      
      // Show the reply preview
      window.showReplyPreview(messageText, senderName, messageId);
      
      // Close the dropdown menu if it's open
      const dropdown = replyBtn.closest('.dropdown-menu');
      const dropdownInstance = bootstrap.Dropdown.getInstance(dropdown?.parentElement);
      if (dropdownInstance) {
        dropdownInstance.hide();
      }
    }
  });
  
  // Initialize tooltips
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });
});
</script>