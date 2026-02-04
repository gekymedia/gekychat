{{-- resources/views/groups/partials/styles.blade.php --}}
<style>
  /* ===== GROUP CHAT SPECIFIC STYLES ===== */
  :root {
    --group-accent: #6366f1;
    --group-admin: #8b5cf6;
    --group-owner: #dc2626;
    --member-online: #10b981;
    --member-offline: #6b7280;
    --moderation-warning: #f59e0b;
    --group-border: rgba(99, 102, 241, 0.2);
  }

  [data-theme="dark"] {
    --group-accent: #818cf8;
    --group-admin: #a78bfa;
    --group-owner: #ef4444;
    --group-border: rgba(129, 140, 248, 0.3);
  }

  /* ===== GROUP HEADER ENHANCEMENTS ===== */
  .group-header {
    background: linear-gradient(135deg, var(--card) 0%, color-mix(in srgb, var(--group-accent) 5%, var(--card)) 100%);
    border-bottom: 2px solid var(--group-border);
    backdrop-filter: blur(10px);
  }

  .group-header-name {
    font-weight: 600;
    letter-spacing: 0.2px;
    color: var(--text);
  }

  .group-privacy-badge {
    font-size: 0.75rem;
    padding: 2px 8px;
    border-radius: 12px;
    background: var(--group-border);
    color: var(--group-accent);
    border: 1px solid var(--group-border);
  }

  /* Fix muted text to use theme-aware colors */
  .muted {
    color: var(--wa-muted) !important;
  }

  /* Fix text-muted to use theme-aware colors */
  .text-muted {
    color: var(--wa-muted) !important;
  }

  /* Fix channel badge to use theme-aware colors */
  .badge[style*="background-color: var(--bg-accent)"] {
    background-color: var(--bg-accent) !important;
    color: var(--text) !important;
  }

  /* ===== MEMBER LIST & ONLINE STATUS ===== */
  .members-list {
    max-height: 400px;
    overflow-y: auto;
  }

  .member-item {
    transition: all 0.2s ease;
    border-radius: 8px;
    padding: 8px 12px;
    margin-bottom: 4px;
  }

  .member-item:hover {
    background: rgba(99, 102, 241, 0.05);
    transform: translateX(4px);
  }

  [data-theme="dark"] .member-item:hover {
    background: rgba(99, 102, 241, 0.1);
  }

  .member-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--border);
    transition: all 0.3s ease;
  }

  .member-avatar.online {
    border-color: var(--member-online);
    box-shadow: 0 0 0 2px var(--member-online);
  }

  .member-avatar.offline {
    opacity: 0.7;
    border-color: var(--member-offline);
  }

  .member-role-badge {
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 6px;
    font-weight: 600;
  }

  .role-owner {
    background: linear-gradient(135deg, var(--group-owner) 0%, #ef4444 100%);
    color: white;
  }

  .role-admin {
    background: linear-gradient(135deg, var(--group-admin) 0%, #8b5cf6 100%);
    color: white;
  }

  .role-member {
    background: var(--reaction-bg);
    color: var(--text);
  }

  /* ===== GROUP MESSAGE BUBBLES ===== */
  .group-message-bubble {
    position: relative;
    max-width: min(75%, 680px);
    margin-bottom: 8px;
  }

  .group-message-bubble.sent {
    margin-left: auto;
  }

  .group-message-bubble.received {
    margin-right: auto;
  }

  .group-message-bubble.received::before {
    content: attr(data-sender);
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--group-accent);
    margin-bottom: 2px;
    opacity: 0.9;
  }

  .group-message-bubble.received.admin::before {
    color: var(--group-admin);
  }

  .group-message-bubble.received.owner::before {
    color: var(--group-owner);
  }

  /* ===== GROUP MANAGEMENT STYLES ===== */
  .group-management-section {
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 16px;
    background: var(--card);
  }

  .group-management-title {
    font-weight: 600;
    color: var(--group-accent);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .member-management-actions {
    display: flex;
    gap: 8px;
    opacity: 0;
    transition: opacity 0.2s ease;
  }

  .member-item:hover .member-management-actions {
    opacity: 1;
  }

  .action-btn {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border);
    background: var(--card);
    color: var(--text);
    transition: all 0.2s ease;
    font-size: 0.8rem;
  }

  .action-btn:hover {
    transform: scale(1.1);
    background: var(--group-accent);
    color: white;
    border-color: var(--group-accent);
  }

  .action-btn.promote:hover {
    background: var(--group-admin);
    border-color: var(--group-admin);
  }

  .action-btn.remove:hover {
    background: var(--group-owner);
    border-color: var(--group-owner);
  }

  /* ===== GROUP SETTINGS MODAL ===== */
  .group-settings-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--group-border);
    transition: all 0.3s ease;
  }

  .group-settings-avatar:hover {
    border-color: var(--group-accent);
    transform: scale(1.05);
  }

  .avatar-upload-area {
    border: 2px dashed var(--border);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
    background: var(--card);
  }

  .avatar-upload-area:hover {
    border-color: var(--group-accent);
    background: color-mix(in srgb, var(--group-accent) 5%, var(--card));
  }

  .avatar-upload-area.dragover {
    border-color: var(--group-accent);
    background: color-mix(in srgb, var(--group-accent) 10%, var(--card));
    transform: scale(1.02);
  }

  .privacy-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border-radius: 8px;
    background: var(--reaction-bg);
    margin-top: 8px;
  }

  .privacy-indicator.private {
    background: color-mix(in srgb, var(--group-owner) 10%, transparent);
    color: var(--group-owner);
  }

  .privacy-indicator.public {
    background: color-mix(in srgb, var(--wa-green) 10%, transparent);
    color: var(--wa-green);
  }

  /* ===== GROUP INVITE SYSTEM ===== */
  .invite-section {
    background: linear-gradient(135deg, var(--card) 0%, color-mix(in srgb, var(--group-accent) 3%, var(--card)) 100%);
    border: 1px solid var(--group-border);
    border-radius: 12px;
    padding: 16px;
    margin: 16px 0;
  }

  .invite-link-container {
    display: flex;
    gap: 8px;
    margin-bottom: 12px;
  }

  .invite-link {
    flex: 1;
    background: var(--input-bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 8px 12px;
    font-family: monospace;
    font-size: 0.875rem;
    color: var(--text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .copy-invite-btn {
    background: var(--group-accent);
    color: white;
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    font-weight: 600;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .copy-invite-btn:hover {
    background: color-mix(in srgb, var(--group-accent) 80%, black);
    transform: translateY(-1px);
  }

  .copy-invite-btn.copied {
    background: var(--wa-green);
  }

  .invite-stats {
    display: flex;
    gap: 16px;
    font-size: 0.875rem;
    color: var(--wa-muted);
  }

  .stat-item {
    display: flex;
    align-items: center;
    gap: 4px;
  }

  /* ===== GROUP TYPING INDICATORS ===== */
  .group-typing-indicator {
    display: none;
    padding: 8px 16px;
    background: var(--reaction-bg);
    border-radius: 18px;
    margin: 8px 0;
    max-width: fit-content;
    font-size: 0.875rem;
    color: var(--wa-muted);
  }

  .group-typing-users {
    font-weight: 600;
    color: var(--group-accent);
  }

  .typing-dots {
    display: inline-flex;
    gap: 2px;
    margin-left: 4px;
  }

  .typing-dots .dot {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: var(--wa-muted);
    animation: typing-bounce 1.4s infinite ease-in-out;
  }

  .typing-dots .dot:nth-child(1) { animation-delay: -0.32s; }
  .typing-dots .dot:nth-child(2) { animation-delay: -0.16s; }
  .typing-dots .dot:nth-child(3) { animation-delay: 0s; }

  @keyframes typing-bounce {
    0%, 80%, 100% {
      transform: scale(0.8);
      opacity: 0.5;
    }
    40% {
      transform: scale(1);
      opacity: 1;
    }
  }

  /* ===== GROUP REACTIONS ENHANCEMENT ===== */
  .group-reaction-summary {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 4px;
  }

  .reaction-with-count {
    display: flex;
    align-items: center;
    gap: 2px;
    padding: 2px 6px;
    border-radius: 12px;
    background: var(--reaction-bg);
    border: 1px solid var(--border);
    font-size: 0.75rem;
    transition: all 0.2s ease;
  }

  .reaction-with-count:hover {
    background: color-mix(in srgb, var(--group-accent) 10%, var(--reaction-bg));
    border-color: var(--group-accent);
    transform: scale(1.05);
  }

  .reaction-count {
    font-weight: 600;
    color: var(--text);
    font-size: 0.7rem;
  }

  /* ===== GROUP MODERATION STYLES ===== */
  .moderation-warning {
    background: color-mix(in srgb, var(--moderation-warning) 10%, transparent);
    border: 1px solid color-mix(in srgb, var(--moderation-warning) 30%, transparent);
    border-radius: 8px;
    padding: 12px;
    margin: 8px 0;
  }

  .moderation-actions {
    display: flex;
    gap: 8px;
    margin-top: 8px;
  }

  .moderation-btn {
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    border: 1px solid var(--border);
    background: var(--card);
    transition: all 0.2s ease;
  }

  .moderation-btn.warn {
    color: var(--moderation-warning);
    border-color: var(--moderation-warning);
  }

  .moderation-btn.warn:hover {
    background: var(--moderation-warning);
    color: white;
  }

  .moderation-btn.remove {
    color: var(--group-owner);
    border-color: var(--group-owner);
  }

  .moderation-btn.remove:hover {
    background: var(--group-owner);
    color: white;
  }

  /* ===== GROUP ACTIVITY INDICATORS ===== */
  .group-activity {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: var(--reaction-bg);
    border-radius: 8px;
    margin: 8px 0;
    font-size: 0.875rem;
  }

  .activity-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--member-online);
    animation: pulse 2s infinite;
  }

  @keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
  }

  .activity-text {
    color: var(--wa-muted);
  }

  .activity-count {
    font-weight: 600;
    color: var(--group-accent);
  }

  /* ===== RESPONSIVE GROUP STYLES ===== */
  @media (max-width: 768px) {
    .group-header {
      padding: 12px;
    }

    .members-list {
      max-height: 300px;
    }

    .member-item {
      padding: 6px 8px;
    }

    .member-avatar {
      width: 32px;
      height: 32px;
    }

    .group-management-section {
      padding: 12px;
    }

    .invite-link-container {
      flex-direction: column;
    }

    .invite-link {
      font-size: 0.8rem;
    }

    .group-message-bubble {
      max-width: 85%;
    }

    .member-management-actions {
      opacity: 1; /* Always show on mobile */
    }
  }

  @media (max-width: 576px) {
    .group-settings-avatar {
      width: 60px;
      height: 60px;
    }

    .avatar-upload-area {
      padding: 16px;
    }

    .moderation-actions {
      flex-direction: column;
    }

    .group-typing-indicator {
      font-size: 0.8rem;
    }

    .reaction-with-count {
      font-size: 0.7rem;
    }
  }

  /* ===== GROUP SCROLLBAR STYLING ===== */
  .members-list::-webkit-scrollbar,
  .group-management-section::-webkit-scrollbar {
    width: 6px;
  }

  .members-list::-webkit-scrollbar-track,
  .group-management-section::-webkit-scrollbar-track {
    background: transparent;
  }

  .members-list::-webkit-scrollbar-thumb,
  .group-management-section::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 3px;
  }

  .members-list::-webkit-scrollbar-thumb:hover,
  .group-management-section::-webkit-scrollbar-thumb:hover {
    background: var(--wa-muted);
  }

  /* ===== GROUP ANIMATIONS ===== */
  @keyframes groupMessageSlideIn {
    from {
      opacity: 0;
      transform: translateY(10px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .group-message-bubble {
    animation: groupMessageSlideIn 0.3s ease-out;
  }

  @keyframes memberJoin {
    0% {
      opacity: 0;
      transform: scale(0.8);
    }
    50% {
      transform: scale(1.05);
    }
    100% {
      opacity: 1;
      transform: scale(1);
    }
  }

  .member-item.new-member {
    animation: memberJoin 0.5s ease-out;
  }

  /* ===== GROUP LOADING STATES ===== */
  .group-loading-skeleton {
    background: linear-gradient(90deg, var(--border) 25%, var(--reaction-bg) 50%, var(--border) 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
    border-radius: 8px;
  }

  @keyframes loading {
    0% {
      background-position: 200% 0;
    }
    100% {
      background-position: -200% 0;
    }
  }

  .group-avatar-skeleton {
    width: 40px;
    height: 40px;
    border-radius: 50%;
  }

  .group-name-skeleton {
    height: 16px;
    width: 120px;
    margin-bottom: 4px;
  }

  .group-description-skeleton {
    height: 12px;
    width: 200px;
  }

  /* ===== GROUP EMPTY STATES ===== */
  .group-empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--wa-muted);
  }

  .group-empty-icon {
    font-size: 3rem;
    margin-bottom: 16px;
    opacity: 0.5;
  }

  .group-empty-title {
    font-size: 1.25rem;
    margin-bottom: 8px;
    color: var(--text);
  }

  .group-empty-description {
    margin-bottom: 20px;
    line-height: 1.5;
  }

  /* ===== GROUP SECURITY INDICATORS ===== */
  .security-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 600;
    background: var(--reaction-bg);
    color: var(--text);
  }

  .security-badge.encrypted {
    background: color-mix(in srgb, var(--wa-green) 10%, transparent);
    color: var(--wa-green);
  }

  .security-badge.private {
    background: color-mix(in srgb, var(--group-owner) 10%, transparent);
    color: var(--group-owner);
  }

  /* ===== GROUP NOTIFICATION STYLES ===== */
  .group-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1060;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 12px 16px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    max-width: 300px;
    animation: slideInRight 0.3s ease-out;
  }

  @keyframes slideInRight {
    from {
      opacity: 0;
      transform: translateX(100%);
    }
    to {
      opacity: 1;
      transform: translateX(0);
    }
  }

  .notification-group {
    font-weight: 600;
    color: var(--group-accent);
  }

  /* ===== PRINT STYLES FOR GROUP CHAT ===== */
  @media print {
    .group-management-section,
    .member-management-actions,
    .message-actions {
      display: none !important;
    }

    .group-message-bubble {
      max-width: 100% !important;
      break-inside: avoid;
    }
  }
</style>
{{-- Enhanced base chat styles with group support --}}
<style>
  /* ===== ENHANCED BASE STYLES WITH GROUP SUPPORT ===== */
  :root {
    /* Enhanced color system */
    --bubble-radius: 16px;
    --reaction-bg: rgba(0, 0, 0, 0.08);
    --chat-transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    
    /* Group-specific colors */
    --group-accent: #6366f1;
    --group-admin: #8b5cf6;
    --group-owner: #dc2626;
    --member-online: #10b981;
  }

  [data-theme="dark"] {
    --reaction-bg: rgba(255, 255, 255, 0.12);
    --group-accent: #818cf8;
    --group-admin: #a78bfa;
    --group-owner: #ef4444;
  }

  /* ===== ENHANCED MESSAGE BUBBLES ===== */
  .message-bubble {
    max-width: min(75%, 680px);
    padding: 12px 16px;
    border-radius: var(--bubble-radius);
    position: relative;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: var(--chat-transition);
    backdrop-filter: blur(10px);
    word-wrap: break-word;
  }

  .message-bubble.sent {
    background: var(--bubble-sent-bg);
    color: var(--bubble-sent-text);
    border-top-right-radius: 6px;
    margin-left: auto;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
  }

  .message-bubble.received {
    background: var(--bubble-recv-bg);
    color: var(--bubble-recv-text);
    border-top-left-radius: 6px;
    margin-right: auto;
    border: 1px solid var(--border);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  }

  .message-bubble:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.18);
    transform: translateY(-1px);
  }

  .message-bubble.received:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
  }

  /* Group-specific sender styling */
  .message-bubble.received[data-sender-role="admin"] {
    border-left: 3px solid var(--group-admin);
  }

  .message-bubble.received[data-sender-role="owner"] {
    border-left: 3px solid var(--group-owner);
  }

  .sender-name {
    font-weight: 600;
    opacity: 0.9;
    display: block;
    margin-bottom: 4px;
    font-size: 0.875rem;
  }

  .sender-name.admin {
    color: var(--group-admin);
  }

  .sender-name.owner {
    color: var(--group-owner);
  }

  /* ===== ENHANCED REACTIONS ===== */
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
    position: relative;
  }

  .bg-reaction:hover {
    transform: scale(1.1);
    background: color-mix(in srgb, var(--group-accent) 15%, var(--reaction-bg));
  }

  .bg-reaction.own-reaction {
    background: color-mix(in srgb, var(--group-accent) 20%, var(--reaction-bg));
    border-color: var(--group-accent);
  }

  /* ===== ENHANCED MESSAGE ACTIONS ===== */
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

  .reaction-buttons {
    padding: 4px 8px;
  }

  .reaction-btn {
    padding: 4px 8px;
    border-radius: 8px;
    transition: var(--chat-transition);
    border: none;
    background: transparent;
    font-size: 1.1rem;
  }

  .reaction-btn:hover {
    transform: scale(1.2);
    background: var(--reaction-bg);
  }

  /* ===== ENHANCED TYPING INDICATOR ===== */
  .typing-indicator {
    display: none;
    padding: 8px 16px;
    background: var(--reaction-bg);
    border-radius: 18px;
    margin: 8px 0;
    max-width: fit-content;
    font-size: 0.875rem;
    color: var(--wa-muted);
    animation: pulse-gentle 2s infinite;
  }

  @keyframes pulse-gentle {
    0%, 100% { opacity: 0.7; }
    50% { opacity: 1; }
  }

  /* ===== ENHANCED UPLOAD PROGRESS ===== */
  #upload-progress {
    display: none;
    height: 4px;
    background: var(--border);
    border-radius: 2px;
    overflow: hidden;
  }

  .progress-bar {
    transition: width 0.3s ease;
    background: linear-gradient(90deg, var(--group-accent), var(--wa-green));
    border-radius: 2px;
  }

  /* ===== ENHANCED NETWORK BANNER ===== */
  .network-banner {
    background: linear-gradient(135deg, #dc2626, #b91c1c);
    color: white;
    padding: 12px 0;
    text-align: center;
    display: none;
    animation: slideDown 0.3s ease;
    position: relative;
    z-index: 1050;
  }

  @keyframes slideDown {
    from {
      transform: translateY(-100%);
      opacity: 0;
    }
    to {
      transform: translateY(0);
      opacity: 1;
    }
  }

  /* ===== ENHANCED RESPONSIVE DESIGN ===== */
  @media (max-width: 768px) {
    .message-bubble {
      max-width: min(85%, 680px);
      padding: 10px 14px;
    }

    .sender-name {
      font-size: 0.8rem;
    }

    .reactions-container {
      gap: 3px;
    }

    .bg-reaction {
      font-size: 0.7rem;
      padding: 3px 6px;
    }

    .message-actions {
      margin: 0 4px;
    }
  }

  @media (max-width: 576px) {
    .message-bubble {
      max-width: 90%;
      padding: 8px 12px;
    }

    .typing-indicator {
      font-size: 0.8rem;
      padding: 6px 12px;
    }
  }

  /* ===== ENHANCED ACCESSIBILITY ===== */
  @media (prefers-reduced-motion: reduce) {
    .message-bubble,
    .bg-reaction,
    .message-actions,
    .reaction-btn {
      transition: none;
      animation: none;
    }
  }

  /* High contrast mode support */
  @media (prefers-contrast: high) {
    .message-bubble {
      border: 2px solid var(--text);
    }

    .bg-reaction {
      border: 2px solid var(--text);
    }
  }

  /* ===== ENHANCED FOCUS MANAGEMENT ===== */
  .message-actions .dropdown-toggle:focus,
  .reaction-btn:focus,
  .reply-btn:focus,
  .forward-btn:focus {
    outline: 2px solid var(--group-accent);
    outline-offset: 2px;
  }

  /* ===== ENHANCED LOADING STATES ===== */
  .messages-loader {
    display: none;
    text-align: center;
    padding: 20px;
  }

  .messages-loader .spinner-border {
    width: 2rem;
    height: 2rem;
    border-width: 0.2em;
  }

  /* ===== ENHANCED EMPTY STATES ===== */
  .empty-chat-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    text-align: center;
    padding: 40px 20px;
  }

  .empty-chat-icon {
    width: 80px;
    height: 80px;
    font-size: 2rem;
    color: var(--wa-muted);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--card);
    border-radius: 50%;
  }

  .empty-chat-title {
    font-weight: 700;
    color: var(--text);
    margin-bottom: 8px;
  }

  .empty-chat-subtitle {
    color: var(--wa-muted);
    line-height: 1.5;
    max-width: 400px;
  }
</style>

  /* ===== LINKIFY STYLES ===== */
  .linkify,
  .email-link,
  .phone-link {
    color: #0066cc;
    text-decoration: underline;
    cursor: pointer;
    word-break: break-word;
  }

  [data-theme="dark"] .linkify,
  [data-theme="dark"] .email-link,
  [data-theme="dark"] .phone-link {
    color: #4d94ff;
  }

  .linkify:hover,
  .email-link:hover,
  .phone-link:hover {
    opacity: 0.8;
    text-decoration: underline;
  }

  .phone-link {
    font-weight: 500;
  }

  #phone-action-menu {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    border: 1px solid var(--border);
    background: var(--card);
    border-radius: 8px;
    padding: 8px 0;
    min-width: 200px;
  }

  [data-theme="dark"] #phone-action-menu {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
  }

  #phone-action-menu .dropdown-item {
    padding: 10px 16px;
    color: var(--text);
    transition: all 0.2s ease;
  }

  #phone-action-menu .dropdown-item:hover {
    background: var(--hover-bg, rgba(0, 0, 0, 0.05));
  }

  [data-theme="dark"] #phone-action-menu .dropdown-item:hover {
    background: var(--hover-bg, rgba(255, 255, 255, 0.1));
  }

  #phone-action-menu .dropdown-item i {
    color: var(--group-accent);
  }
</style>