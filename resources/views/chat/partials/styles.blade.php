{{-- resources/views/chat/partials/styles.blade.php --}}
<style>
  :root {
    --bubble-radius: 16px;
    --reaction-bg: rgba(0, 0, 0, 0.1);
    --chat-transition: all 0.2s ease;
    --wa-green: #25d366;
    --wa-muted: #8696a0;
    --border: #e1e1e1;
    --bg: #ffffff;
    --bg-accent: #f0f2f5;
    --card: #ffffff;
    --text: #111b21;
    --input-bg: #ffffff;
    --input-border: #e1e1e1;
    --bubble-sent-bg: #d9fdd3;
    --bubble-sent-text: #111b21;
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
    --bubble-sent-bg: #005c4b;
    --bubble-sent-text: #e9edef;
    --bubble-recv-bg: #202c33;
    --bubble-recv-text: #e9edef;
    --reaction-bg: rgba(255, 255, 255, 0.1);
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
    background: radial-gradient(1000px 600px at 10% -10%, var(--bg-accent) 0, var(--bg) 60%), var(--bg);
    display: flex;
    flex-direction: column;
    padding: 12px;
    flex: 1 1 auto;
    overflow: auto;
    min-height: 0;
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
    padding: 8px 12px;
    border-radius: var(--bubble-radius);
    position: relative;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    transition: var(--chat-transition);
    word-wrap: break-word;
  }

  .message-bubble.sent {
    background: var(--bubble-sent-bg);
    color: var(--bubble-sent-text);
    border-top-right-radius: 6px;
    margin-left: auto;
  }

  .message-bubble.received {
    background: var(--bubble-recv-bg);
    color: var(--bubble-recv-text);
    border-top-left-radius: 6px;
    margin-right: auto;
    border: 1px solid var(--border);
  }

  .message-bubble:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
  }

  /* Message Content */
  .sender-name {
    font-weight: 600;
    opacity: 0.85;
    display: block;
    margin-bottom: 4px;
    font-size: 0.875rem;
    color: var(--wa-green);
  }

  .message-text {
    white-space: pre-wrap;
    word-break: break-word;
    line-height: 1.4;
    font-size: 0.9375rem;
  }

  .reply-preview {
    border-left: 3px solid var(--wa-green);
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
    border-color: var(--wa-green);
    box-shadow: 0 0 0 0.2rem rgba(37, 211, 102, 0.25);
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
    background: var(--wa-green);
    border: none;
    color: #062a1f;
    font-weight: 600;
    border-radius: 12px;
    transition: var(--chat-transition);
  }

  .btn-wa:hover {
    filter: brightness(1.05);
    transform: translateY(-1px);
    color: #062a1f;
  }

  .btn-outline-wa {
    border-color: var(--wa-green);
    color: var(--wa-green);
    border-radius: 12px;
    transition: var(--chat-transition);
  }

  .btn-outline-wa:hover {
    background: var(--wa-green);
    color: #062a1f;
  }

  /* Upload Progress */
  #upload-progress {
    display: none;
    height: 4px;
    background: var(--border);
  }

  .progress-bar {
    transition: width 0.3s ease;
    background: var(--wa-green);
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
    background: var(--bg);
  }

  .empty-chat-icon {
    width: 80px;
    height: 80px;
    font-size: 2rem;
    color: var(--wa-muted);
  }

  .empty-chat-title {
    font-weight: 700;
    color: var(--text);
  }

  .empty-chat-subtitle {
    color: var(--wa-muted);
  }

  /* Drop Zone */
  .drop-hover {
    outline: 2px dashed var(--wa-green);
    outline-offset: 4px;
    border-radius: 14px;
    background: rgba(37, 211, 102, 0.05);
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

  .avatar-img {
    object-fit: cover;
  }

  .bg-avatar {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  }

  .bg-brand {
    background: var(--wa-green);
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
    #conversation-sidebar {
      display: block;
    }

    #chat-area {
      display: none;
    }

    .chat-active #conversation-sidebar {
      display: none;
    }

    .chat-active #chat-area {
      display: flex;
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
    color: var(--wa-green);
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
    box-shadow: 0 0 0 2px rgba(37, 211, 102, 0.25);
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