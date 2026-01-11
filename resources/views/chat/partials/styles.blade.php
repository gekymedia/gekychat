{{-- resources/views/chat/partials/styles.blade.php --}}
<style>
  :root {
    --bubble-radius: 16px;
    --reaction-bg: rgba(0, 0, 0, 0.1);
    --chat-transition: all 0.2s ease;
    /* GekyChat Brand Colors - Green & Gold */
    --geky-green: #10B981;
    --geky-green-dark: #059669;
    --geky-green-light: #34D399;
    --geky-gold: #F59E0B;
    --geky-gold-dark: #D97706;
    --geky-gold-light: #FBBF24;
    /* Legacy support - map to new colors */
    --wa-green: var(--geky-green);
    --wa-muted: #8696a0;
    --border: #e1e1e1;
    --bg: #ffffff;
    --bg-accent: #f0f2f5;
    --card: #ffffff;
    --text: #111b21;
    --input-bg: #ffffff;
    --input-border: #e1e1e1;
    --bubble-sent-bg: #D1FAE5;
    --bubble-sent-text: #065F46;
    --bubble-recv-bg: #ffffff;
    --bubble-recv-text: #111b21;
  }

  [data-theme="dark"] {
    --border: #2a3942;
    --bg: #111b21;
    --bg-accent: #202c33;
    --card: #202c33;
    --text: #e9edef;
    --input-bg: #2a3942;
    --input-border: #2a3942;
    --bubble-sent-bg: #064E3B;
    --bubble-sent-text: #A7F3D0;
    --bubble-recv-bg: #202c33;
    --bubble-recv-text: #e9edef;
    --reaction-bg: rgba(255, 255, 255, 0.1);
  }

  [data-theme="dark"] .messages-container {
    background-image: url('/images/chatbg2.jpg');
  }

  [data-theme="dark"] .messages-container::before {
    background: rgba(17, 27, 33, 0.85);
  }

  /* Layout */
  .chat-container {
    height: calc(100dvh - var(--nav-h, 60px));
    background: var(--bg);
  }

  .chat-header {
    background: var(--card);
    border-bottom: 1px solid var(--border);
    min-height: 70px;
    backdrop-filter: blur(10px);
  }

  .chat-header-name {
    font-weight: 600;
    letter-spacing: 0.2px;
  }

  /* Messages Container */
  .messages-container {
    background-image: url('/images/chatbg.jpg');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    background-attachment: local;
    display: flex;
    flex-direction: column;
    padding: 12px;
    flex: 1 1 auto;
    overflow: auto;
    min-height: 0;
    position: relative;
  }

  .messages-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.85);
    z-index: 0;
    pointer-events: none;
  }

  .messages-container > * {
    position: relative;
    z-index: 1;
  }

  .messages-loader {
    display: none;
  }

  /* Message Bubbles */
  .message {
    transition: var(--chat-transition);
    margin-bottom: 8px;
  }

  .message:hover {
    transform: translateX(2px);
  }

  .message-bubble {
    max-width: min(75%, 680px);
    padding: 12px 16px;
    border-radius: var(--bubble-radius);
    position: relative;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: var(--chat-transition);
    word-wrap: break-word;
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.95) !important;
  }

  .message-bubble.sent {
    background: rgba(209, 250, 229, 0.95) !important;
    color: var(--bubble-sent-text);
    border-top-right-radius: 6px;
    margin-left: auto;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
  }

  [data-theme="dark"] .message-bubble.sent {
    background: rgba(6, 78, 59, 0.95) !important;
  }

  .message-bubble.received {
    background: rgba(255, 255, 255, 0.95) !important;
    color: var(--bubble-recv-text, #111b21) !important;
    border-top-left-radius: 6px;
    margin-right: auto;
    border: 1px solid rgba(225, 225, 225, 0.5) !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  }
  
  [data-theme="dark"] .message-bubble.received {
    background: rgba(32, 44, 51, 0.95) !important;
    color: var(--bubble-recv-text, #e9edef) !important;
    border-color: rgba(42, 57, 66, 0.5) !important;
  }

  .message-bubble:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.18);
    transform: translateY(-1px);
  }

  .message-bubble.received:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
  }

  /* Message Content */
  .sender-name {
    font-weight: 600;
    opacity: 0.85;
    display: block;
    margin-bottom: 4px;
    font-size: 0.875rem;
    color: var(--geky-green);
  }

  .message-text {
    white-space: pre-wrap;
    word-break: break-word;
    line-height: 1.4;
    font-size: 0.9375rem;
  }

  .reply-preview {
    border-left: 3px solid var(--geky-green);
    padding-left: 12px;
    margin-bottom: 8px;
    opacity: 0.85;
    font-style: italic;
    background: rgba(0, 0, 0, 0.05);
    padding: 6px 10px;
    border-radius: 8px;
    font-size: 0.875rem;
    border: 1px solid rgba(0, 0, 0, 0.1);
  }

  [data-theme="dark"] .reply-preview {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.1);
  }

  /* Media Attachments */
  .media-img {
    max-width: 220px;
    border-radius: 12px;
    transition: var(--chat-transition);
    cursor: pointer;
    border: 1px solid var(--border);
  }

  .media-img:hover {
    transform: scale(1.02);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
  }

  .media-img.loading {
    opacity: 0.7;
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
  }

  @keyframes loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
  }

  .doc-link {
    text-decoration: none;
    padding: 8px 12px;
    border-radius: 8px;
    background: rgba(0, 0, 0, 0.05);
    transition: var(--chat-transition);
    border: 1px solid var(--border);
    color: var(--text);
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }

  .doc-link:hover {
    background: rgba(0, 0, 0, 0.1);
    text-decoration: none;
    transform: translateY(-1px);
  }

  [data-theme="dark"] .doc-link {
    background: rgba(255, 255, 255, 0.05);
  }

  [data-theme="dark"] .doc-link:hover {
    background: rgba(255, 255, 255, 0.1);
  }

  /* Message Footer */
  .message-footer {
    font-size: 0.75rem;
    opacity: 0.8;
    margin-top: 4px;
  }

  .status-indicator {
    margin-left: 8px;
  }

  /* Reactions */
  .reactions-container {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-top: 6px;
  }

  .bg-reaction {
    background: var(--reaction-bg);
    font-size: 0.75rem;
    padding: 4px 8px;
    cursor: pointer;
    transition: var(--chat-transition);
    border: 1px solid rgba(0, 0, 0, 0.1);
  }

  .bg-reaction:hover {
    transform: scale(1.1);
  }

  /* Message Actions */
  .message-actions {
    visibility: hidden;
    align-self: center;
    margin: 0 8px;
    opacity: 0;
    transition: var(--chat-transition);
  }

  .message:hover .message-actions {
    visibility: visible;
    opacity: 1;
  }

  .reaction-buttons .btn {
    padding: 4px 8px;
    border-radius: 8px;
    transition: var(--chat-transition);
    border: none;
  }

  .reaction-buttons .btn:hover {
    transform: scale(1.2);
    background: var(--reaction-bg);
  }

  /* Reply Preview Container */
  .reply-preview-container {
    background: var(--card);
    border-top: 1px solid var(--border);
    display: none;
    padding: 12px;
  }

  /* Message Input */
  .message-input-container {
    background: var(--bg);
    border-top: 1px solid var(--border);
    position: sticky;
    bottom: 0;
    z-index: 10;
    backdrop-filter: blur(10px);
  }

  .composer .form-control {
    background: var(--input-bg);
    border-color: var(--input-border);
    color: var(--text);
    border-radius: 20px;
    padding: 12px 16px;
    transition: var(--chat-transition);
    border: 1px solid var(--input-border);
  }

  .composer .form-control:focus {
    border-color: var(--geky-green);
    box-shadow: 0 0 0 0.2rem rgba(16, 185, 129, 0.25);
  }

  /* Buttons */
  .btn-ghost {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text);
    border-radius: 12px;
    transition: var(--chat-transition);
  }

  .btn-ghost:hover {
    background: rgba(255, 255, 255, 0.1);
    transform: translateY(-1px);
  }

  [data-theme="light"] .btn-ghost:hover {
    background: rgba(0, 0, 0, 0.04);
  }

  .btn-wa {
    background: linear-gradient(135deg, var(--geky-green) 0%, var(--geky-green-dark) 100%);
    border: none;
    color: #ffffff;
    font-weight: 600;
    border-radius: 12px;
    transition: var(--chat-transition);
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
  }

  .btn-wa:hover {
    background: linear-gradient(135deg, var(--geky-green-dark) 0%, var(--geky-green) 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
    color: #ffffff;
  }

  .btn-outline-wa {
    border-color: var(--geky-green);
    color: var(--geky-green);
    border-radius: 12px;
    transition: var(--chat-transition);
    background: transparent;
  }

  .btn-outline-wa:hover {
    background: var(--geky-green);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
  }

  /* Upload Progress */
  #upload-progress {
    display: none;
    height: 4px;
    background: var(--border);
  }

  .progress-bar {
    transition: width 0.3s ease;
    background: linear-gradient(90deg, var(--geky-green) 0%, var(--geky-gold) 100%);
  }

  /* Emoji Picker */
  .emoji-wrap {
    position: fixed;
    right: 16px;
    bottom: 86px;
    z-index: 1000;
    display: none;
    animation: fadeIn 0.2s ease;
  }

  @keyframes fadeIn {
    from {
      opacity: 0;
      transform: translateY(10px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  emoji-picker {
    --emoji-size: 1.2rem;
    width: 320px;
    height: 380px;
    border: 1px solid var(--border);
    border-radius: 12px;
  }

  /* Empty State */
  .empty-chat-state {
    background-image: url('/images/chatbg.jpg');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    position: relative;
  }

  .empty-chat-state::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.85);
    z-index: 0;
    pointer-events: none;
  }

  .empty-chat-state > * {
    position: relative;
    z-index: 1;
  }

  [data-theme="dark"] .empty-chat-state {
    background-image: url('/images/chatbg2.jpg');
  }

  [data-theme="dark"] .empty-chat-state::before {
    background: rgba(17, 27, 33, 0.85);
  }

  .empty-chat-icon {
    width: 80px;
    height: 80px;
    font-size: 2rem;
    color: var(--wa-muted);
    display: none;
  }

  .empty-chat-logo {
    width: 120px;
    height: 120px;
    margin: 0 auto 2rem;
    display: block;
    position: relative;
    z-index: 1;
  }

  .empty-chat-title {
    font-weight: 700;
    color: var(--text);
    position: relative;
    z-index: 1;
  }

  .empty-chat-subtitle {
    color: var(--wa-muted);
    position: relative;
    z-index: 1;
  }

  /* Drop Zone */
  .drop-hover {
    outline: 2px dashed var(--geky-green);
    outline-offset: 4px;
    border-radius: 14px;
    background: rgba(16, 185, 129, 0.05);
  }

  /* Network Banner */
  .network-banner {
    background: #dc3545;
    color: white;
    padding: 8px 16px;
    text-align: center;
    display: none;
    animation: slideDown 0.3s ease;
  }

  @keyframes slideDown {
    from {
      transform: translateY(-100%);
    }
    to {
      transform: translateY(0);
    }
  }

  /* Avatars */
  .avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.875rem;
    flex-shrink: 0;
  }

  /* .avatar-img {
    object-fit: cover;
  } */

  /* bg-avatar removed - use .avatar-placeholder class from app.css instead */

  .bg-brand {
    background: linear-gradient(135deg, var(--geky-green) 0%, var(--geky-gold) 100%);
    color: white;
  }

  /* Online List */
  #online-list {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  /* Responsive Design */
  @media (max-width: 768px) {
    /* Ensure chat container takes full width and proper layout on mobile */
    .chat-container {
      width: 100%;
      display: flex;
      flex-direction: row;
      position: relative;
    }
    
    /* Default: Show sidebar, hide chat area on /c index */
    /* #conversation-sidebar-wrapper {
      display: none !important;
      width: 100% !important;
      max-width: 100% !important;
      min-width: 100% !important;
      flex: 1 1 auto;
      position: relative;
      z-index: 1;
    } */

    /* Default: Hide chat area on /c index route */
    #chat-area {
      display: none !important;
      width: 100% !important;
      max-width: 100% !important;
      min-width: 100% !important;
      flex: 1 1 auto;
      position: relative;
      z-index: 1;
    }

    /* When chat/group/channel is active: Hide sidebar, show chat area */
    .chat-active #conversation-sidebar-wrapper {
      display: none !important;
      visibility: hidden !important;
      opacity: 0 !important;
      pointer-events: none !important;
      width: 0 !important;
      max-width: 0 !important;
      min-width: 0 !important;
      overflow: hidden !important;
    }

    .chat-active #chat-area {
      display: flex !important;
    }
    
    /* Hide back button by default on mobile */
    #back-to-conversations {
      display: none !important;
    }
    
    /* Show back button when chat is active on mobile */
    .chat-active #back-to-conversations {
      display: flex !important;
      visibility: visible !important;
    }

    .message-bubble {
      max-width: min(85%, 680px);
    }

    .emoji-wrap {
      right: 8px;
      left: 8px;
      bottom: 80px;
    }

    emoji-picker {
      width: auto;
      max-width: 100%;
    }

    .chat-header {
      padding: 12px;
    }

    .messages-container {
      padding: 8px;
    }
  }

  @media (max-width: 576px) {
    .message-bubble {
      max-width: 90%;
      padding: 6px 10px;
    }

    .composer .form-control {
      padding: 10px 14px;
    }
  }

  /* Scrollbars */
  .messages-container::-webkit-scrollbar {
    width: 6px;
  }

  .messages-container::-webkit-scrollbar-track {
    background: transparent;
  }

  .messages-container::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 10px;
  }

  .messages-container::-webkit-scrollbar-thumb:hover {
    background: var(--wa-muted);
  }

  /* Forward Modal */
  .forward-list-container {
    max-height: 400px;
    overflow-y: auto;
  }

  .forward-section {
    border-bottom: 1px solid var(--border);
  }

  .forward-section:last-child {
    border-bottom: none;
  }

  .list-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid var(--border);
    flex-shrink: 0;
  }

  .cursor-pointer {
    cursor: pointer;
  }

  /* Link Styling */
  .linkify {
    color: var(--geky-green);
    text-decoration: none;
    word-break: break-all;
  }

  .linkify:hover {
    text-decoration: underline;
  }

  /* Utility Classes */
  .text-truncate-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  .bg-card {
    background: var(--card);
  }

  .muted {
    color: var(--wa-muted);
  }

  /* Loading States */
  .skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
    border-radius: 4px;
  }

  /* Focus States */
  .btn:focus,
  .form-control:focus {
    outline: none;
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25);
  }

  /* Print Styles */
  @media print {
    .message-input-container,
    .chat-header,
    .emoji-wrap {
      display: none !important;
    }

    .messages-container {
      overflow: visible !important;
      height: auto !important;
    }
  }
</style>