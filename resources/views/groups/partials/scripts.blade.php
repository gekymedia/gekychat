<script>
document.addEventListener('DOMContentLoaded', function () {
  // ===== Constants & DOM =====
  const STORAGE_URL = "{{ Storage::url('') }}";
  const CSRF       = "{{ csrf_token() }}";
  const currentUid = Number(@json(auth()->id()));
  const groupId    = Number(@json($group->id));
  const groupName  = @json($group->name ?? 'Group');

  const chatBox           = document.getElementById('chat-box');
  const topSentinel       = document.getElementById('top-sentinel');
  const messagesContainer = document.getElementById('messages-container');
  const form              = document.getElementById('chat-form');
  const sendBtn           = document.getElementById('send-btn');
  const msgInput          = document.getElementById('message-input');
  const replyInput        = document.getElementById('reply-to-id');
  const pickerWrap        = document.getElementById('emoji-picker-wrap');
  const picker            = document.getElementById('emoji-picker');
  const emojiBtn          = document.getElementById('emoji-btn');
  const progressBar       = document.getElementById('upload-progress');
  const audio             = document.getElementById('notification-sound');
  const netBanner         = document.getElementById('net-banner');
  const netRetryIn        = document.getElementById('net-retry-in');
  const copyInviteBtn     = document.getElementById('copy-invite');

  const historyUrl = @json(route('groups.messages.history', $group));
  const typingUrl  = @json(route('groups.typing', $group));
  const pingUrl    = @json(route('ping'));

  // Forward modal bits
  const forwardDatasetsEl = document.getElementById('forward-datasets');
  const forwardModalEl    = document.getElementById('forward-modal');
  let   forwardModal      = null;
  if (forwardModalEl && window.bootstrap?.Modal) {
    forwardModal = new bootstrap.Modal(forwardModalEl);
  }
  const forwardSourceId   = document.getElementById('forward-source-id');
  const forwardConfirmBtn = document.getElementById('forward-confirm');

  // State
  let typingTimeout, typingHideTimeout;
  let resizeObserver;
  let isLoadingMessages = false;
  let hasMoreMessages   = true;
  let currentPage       = 1;

  // ===== Unified fetch helper =====
  const DEFAULT_HEADERS = {
    'Accept': 'application/json',
    'X-CSRF-TOKEN': CSRF,
    'X-Requested-With': 'XMLHttpRequest'
  };
  async function apiFetch(url, opts = {}) {
    const headers = { ...DEFAULT_HEADERS, ...(opts.headers || {}) };
    const options = { credentials: 'same-origin', ...opts, headers };
    const res = await fetch(url, options);
    const ct = res.headers.get('content-type') || '';
    let payload = null;
    try { payload = ct.includes('application/json') ? await res.json() : await res.text(); } catch (_) {}
    if (!res.ok) {
      const msg = (payload && payload.message) ? payload.message : `HTTP ${res.status}`;
      const err = new Error(msg); err.status = res.status; err.payload = payload; throw err;
    }
    return payload;
  }

  // ===== Init =====
  initChat();

  function initChat() {
    scrollToBottom({force: true});
    setupTopSentinelObserver();     // 👈 new
    setupResizeObserver();          // 👈 non-forcing
    lazyLoadImages();
    setupNetworkAwareness();
    setupForwardPicker();
    wireStaticHandlers();
    wireGlobalDelegates();          // 👈 edit-btn, etc.

    whenEchoReady(setupEchoListeners);
  }

  // ✅ Helper: run cb when Echo has been initialized by the layout bootstrap
  function whenEchoReady(cb) {
    if (window.Echo && typeof window.Echo.private === 'function') {
      cb();
    } else {
      document.addEventListener('echo:ready', () => cb(), { once: true });
      let tries = 0;
      const t = setInterval(() => {
        if (window.Echo && typeof window.Echo.private === 'function') {
          clearInterval(t); cb();
        } else if (++tries > 40) {
          clearInterval(t);
          console.warn('Echo still not ready after waiting.');
        }
      }, 50);
    }
  }

  // ===== Responsive back button =====
  document.getElementById('back-to-conversations')?.addEventListener('click', () => {
    document.querySelector('.chat-container')?.classList.remove('chat-active');
  });
  document.querySelectorAll('.conversation-item').forEach(item => {
    item.addEventListener('click', () => {
      if (window.innerWidth < 768) {
        document.querySelector('.chat-container')?.classList.add('chat-active');
      }
    });
  });

  // ===== Emoji picker =====
  emojiBtn?.addEventListener('click', (e) => {
    e.stopPropagation();
    pickerWrap.style.display = (pickerWrap.style.display === 'none' || !pickerWrap.style.display) ? 'block' : 'none';
  });
  document.addEventListener('click', (e) => {
    if (!pickerWrap.contains(e.target) && e.target !== emojiBtn) {
      pickerWrap.style.display = 'none';
    }
  });
  picker?.addEventListener('emoji-click', (ev) => {
    if (!msgInput) return;
    const cursorPos  = msgInput.selectionStart ?? msgInput.value.length;
    const textBefore = msgInput.value.substring(0, cursorPos);
    const textAfter  = msgInput.value.substring(cursorPos);
    msgInput.value   = textBefore + ev.detail.unicode + textAfter;
    msgInput.selectionStart = msgInput.selectionEnd = cursorPos + ev.detail.unicode.length;
    msgInput.focus();
  });

  // ===== Drag & drop upload =====
  const dropZone = document.getElementById('drop-zone');
  if (dropZone) {
    ['dragenter','dragover'].forEach(evt => {
      dropZone.addEventListener(evt, e => { e.preventDefault(); dropZone.classList.add('drop-hover'); }, false);
    });
    ['dragleave','drop'].forEach(evt => {
      dropZone.addEventListener(evt, e => { e.preventDefault(); dropZone.classList.remove('drop-hover'); }, false);
    });
    dropZone.addEventListener('drop', (e) => {
      if (!form) return;
      const files = e.dataTransfer?.files;
      if (files && files.length) handleFileUpload(files);
    });
  }

  function handleFileUpload(files) {
    const formData = new FormData(form);
    formData.delete('attachments[]');
    Array.from(files).forEach(file => formData.append('attachments[]', file));

    const xhr = new XMLHttpRequest();
    xhr.open('POST', form.action, true);
    xhr.withCredentials = true;
    xhr.setRequestHeader('X-CSRF-TOKEN', CSRF);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.upload.onprogress = (e) => {
      if (e.lengthComputable) {
        const percent = Math.round((e.loaded / e.total) * 100);
        progressBar.style.display = 'block';
        progressBar.querySelector('.progress-bar').style.width = percent + '%';
      }
    };
    xhr.onload = () => {
      progressBar.style.display = 'none';
      try {
        const ok = xhr.status >= 200 && xhr.status < 300;
        const response = JSON.parse(xhr.response || '{}');
        if (ok && response.message) {
          const replyId = replyInput.value;
          if (replyId && !response.message.replyTo) {
            const src = document.querySelector(`.message[data-message-id="${replyId}"] .message-text`);
            response.message.reply_to_id = Number(replyId);
            response.message.replyTo = { body: src?.textContent?.trim() || '' };
          }
          appendMessageToChat(response.message, true);
          form.reset();
          document.getElementById('reply-preview')?.style.setProperty('display','none');
          replyInput.value = '';
          scrollToBottom({smooth:true});
        } else {
          alert(response?.message || 'File upload failed');
        }
      } catch {
        alert('File upload failed');
      }
    };
    xhr.onerror = () => showOffline();
    xhr.send(formData);
  }

  // ===== Scroll helpers =====
  function scrollToBottom({smooth=false, force=false}={}) {
    if (!chatBox) return;
    const scrollHeight = chatBox.scrollHeight;
    const clientHeight = chatBox.clientHeight;
    const scrollTop    = chatBox.scrollTop;
    const distance     = scrollHeight - (clientHeight + scrollTop);
    if (force || distance < 300) {
      if (smooth) chatBox.scrollTo({ top: scrollHeight, behavior: 'smooth' });
      else chatBox.scrollTop = scrollHeight;
    }
  }

  // ===== Pagination / infinite scroll (top sentinel) =====
  function preserveScrollWhilePrepending(prependFn) {
    if (!chatBox) return prependFn();
    const before = chatBox.scrollHeight;
    prependFn();
    const after = chatBox.scrollHeight;
    chatBox.scrollTop += (after - before);
  }

  function loadMoreMessages() {
    if (isLoadingMessages || !hasMoreMessages) return;
    isLoadingMessages = true;
    document.getElementById('messages-loader')?.style.setProperty('display', 'block');
    const nextPage = currentPage + 1;

    apiFetch(`${historyUrl}?page=${nextPage}`)
      .then(data => {
        const batch = data?.messages?.data ?? [];
        if (batch.length === 0) { hasMoreMessages = false; return; }

        const fragment = document.createDocumentFragment();
        batch.slice().reverse().forEach(msg => {
          const el = createMessageElement(msg, Number(msg.sender_id) === currentUid);
          fragment.prepend(el);
        });

        preserveScrollWhilePrepending(() => {
          messagesContainer.prepend(fragment);
        });

        addMessageListenersToNewElements(messagesContainer);
        currentPage = nextPage;
        if (!data?.messages?.next_page_url) hasMoreMessages = false;
      })
      .catch(() => showOffline())
      .finally(() => {
        isLoadingMessages = false;
        document.getElementById('messages-loader')?.style.setProperty('display', 'none');
        lazyLoadImages();
      });
  }

  function setupTopSentinelObserver() {
    if (!topSentinel || !chatBox) return;
    const io = new IntersectionObserver((entries) => {
      if (entries[0]?.isIntersecting) loadMoreMessages();
    }, { root: chatBox, rootMargin: '200px 0px 0px 0px' });
    io.observe(topSentinel);
  }

  function setupResizeObserver() {
    resizeObserver = new ResizeObserver(() => scrollToBottom()); // no force
    if (chatBox) resizeObserver.observe(chatBox);
  }

  function lazyLoadImages() {
    const lazyImages = document.querySelectorAll('.media-img[data-src]:not([src])');
    const imageObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const img = entry.target;
          img.src = img.dataset.src;
          img.classList.add('loading');
          img.onload = () => { img.classList.remove('loading'); img.removeAttribute('data-src'); };
          imageObserver.unobserve(img);
        }
      });
    }, { root: chatBox, rootMargin: '200px 0px' });
    lazyImages.forEach(img => imageObserver.observe(img));
  }

  // ===== Send message =====
  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!form) return;
    sendBtn.disabled = true;
    const original = sendBtn.innerHTML;
    sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';
    try {
      const fd = new FormData(form);
      const replyId = replyInput.value;
      const data = await apiFetch(form.action, { method: 'POST', body: fd });
      if (data?.message) {
        if (replyId && !data.message.replyTo) {
          const src = document.querySelector(`.message[data-message-id="${replyId}"] .message-text`);
          data.message.reply_to_id = Number(replyId);
          data.message.replyTo = { body: src?.textContent?.trim() || '' };
        }
        appendMessageToChat(data.message, true);
        form.reset();
        document.getElementById('reply-preview')?.style.setProperty('display','none');
        replyInput.value = '';
        scrollToBottom({smooth:true});
      } else {
        alert(data?.message || 'Failed to send message.');
      }
    } catch (err) {
      showOffline();
      alert(err?.message || 'Failed to send message. You appear to be offline.');
    } finally {
      sendBtn.disabled = false;
      sendBtn.innerHTML = original;
    }
  });

  // ===== Static handlers (existing DOM) =====
  function wireStaticHandlers(){
    // Reply
    document.getElementById('cancel-reply')?.addEventListener('click', () => {
      replyInput.value = '';
      document.getElementById('reply-preview').style.display = 'none';
    });

    // Delete (form-enhanced)
    document.querySelectorAll('form.delete-form').forEach(f => {
      f.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!confirm('Delete this message?')) return;
        try {
          await apiFetch(this.action, { method: 'DELETE' });
          const wrapper = this.closest('.message');
          wrapper?.remove();
        } catch (err) {
          showOffline();
          alert(err?.message || 'Error deleting message.');
        }
      });
    });

    // Copy invite
    copyInviteBtn?.addEventListener('click', async function () {
      const invite = @json(route('groups.show', $group));
      try {
        await navigator.clipboard.writeText(invite);
        this.innerHTML = '<i class="bi bi-check2 me-1"></i> Copied';
        setTimeout(() => this.innerHTML = '<i class="bi bi-link-45deg me-1"></i> Copy invite link', 1500);
      } catch {
        prompt('Copy this link:', invite);
      }
    });
  }

  // ===== Global delegates (so new nodes also work) =====
  function wireGlobalDelegates(){
    document.addEventListener('click', async (e) => {
      // Reply (delegated)
      const replyBtn = e.target.closest('.reply-btn');
      if (replyBtn) {
        const id  = replyBtn.dataset.messageId;
        const el  = document.querySelector(`.message[data-message-id="${id}"]`);
        const txt = el?.querySelector('.message-text')?.textContent ?? '';
        replyInput.value = id;
        document.getElementById('reply-preview').style.display = 'block';
        document.querySelector('.reply-preview-content').textContent = txt.length > 60 ? txt.slice(0,60)+'…' : txt;
        msgInput?.focus();
        return;
      }

      // Delete (AJAX)
      const delBtn = e.target.closest('.delete-btn');
      if (delBtn) {
        e.preventDefault();
        const messageId = delBtn.dataset.messageId;
        if (!confirm('Delete this message?')) return;
        try {
          await apiFetch(`/groups/${groupId}/messages/${messageId}`, { method: 'DELETE' });
          document.querySelector(`.message[data-message-id="${messageId}"]`)?.remove();
        } catch {
          showOffline();
          alert('Error deleting message.');
        }
        return;
      }

      // Reactions (optimistic)
      const reactBtn = e.target.closest('.reaction-btn');
      if (reactBtn) {
        e.preventDefault();
        const url   = reactBtn.dataset.reactUrl;
        const emoji = reactBtn.dataset.reaction;
        const wrapper = reactBtn.closest('.message');
        if (!url || !emoji || !wrapper) return;
        optimisticAddReaction(wrapper.dataset.messageId, emoji);
        try {
          await apiFetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ emoji })
          });
        } catch { showOffline(); }
        return;
      }

      // Forward
      const fwdBtn = e.target.closest('.forward-btn');
      if (fwdBtn) {
        e.preventDefault();
        forwardSourceId.value = fwdBtn.dataset.messageId || '';
        forwardConfirmBtn.disabled = false;
        forwardModal?.show();
        return;
      }

      // ✏️ Edit
      const editBtn = e.target.closest('.edit-btn');
      if (editBtn) {
        e.preventDefault();
        const id  = editBtn.dataset.messageId;
        const url = editBtn.dataset.editUrl;
        const cur = editBtn.dataset.body || '';
        const updated = prompt('Edit message:', cur);
        if (updated === null) return;
        try {
          await apiFetch(url, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ body: updated })
          });
          const area = document.querySelector(`.message[data-message-id="${id}"] .message-text`);
          if (area) {
            area.innerHTML = escapeHtml(updated)
              .replace(/(https?:\/\/[^\s]+)/g, '<a class="linkify" target="_blank" href="$1">$1</a>');
          }
        } catch (err) {
          showOffline();
          alert(err?.message || 'Failed to edit message.');
        }
        return;
      }
    });
  }

  function addMessageListenersToNewElements(scope) {
    scope.querySelectorAll('.message').forEach(() => {/* delegated now; no-op */});
  }

  // ===== Optimistic reactions =====
  function optimisticAddReaction(messageId, emoji) {
    const wrapper = document.querySelector(`.message[data-message-id="${messageId}"]`);
    if (!wrapper) return;
    const container = wrapper.querySelector('.reactions-container') || (() => {
      const d = document.createElement('div');
      d.className = 'reactions-container mt-1';
      wrapper.querySelector('.message-bubble')?.appendChild(d);
      return d;
    })();
    const badge = document.createElement('span');
    badge.className = 'badge bg-reaction rounded-pill me-1';
    badge.title = 'You';
    badge.textContent = emoji;
    container.appendChild(badge);
  }

  // ===== Typing indicator =====
  msgInput?.addEventListener('input', () => {
    clearTimeout(typingTimeout);
    const indicator = document.getElementById('typing-indicator');
    if (indicator) indicator.style.display = msgInput.value ? 'block' : 'none';
    typingTimeout = setTimeout(() => {
      if (indicator) indicator.style.display = 'none';
      apiFetch(typingUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ is_typing: !!msgInput.value })
      }).catch(()=>{});
    }, 450);
  });
  // Clear typing on blur
  msgInput?.addEventListener('blur', () => {
    apiFetch(typingUrl, {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ is_typing:false })
    }).catch(()=>{});
  });

  // ===== Echo / Realtime =====
  function setupEchoListeners() {
    if (!window.Echo || typeof window.Echo.private !== 'function') {
      console.warn('Echo not ready; realtime disabled on group page.');
      return;
    }

    window.Echo.private(`group.${groupId}`)
      .listen('GroupMessageSent', (e) => {
        if (Number(e.message?.sender_id) === currentUid) return;
        appendMessageToChat(e.message);
        audio?.play().catch(() => {});
        scrollToBottom({smooth:true});
      })
      .listen('GroupMessageEdited', (e) => {
        const id = e.message?.id;
        if (!id) return;
        const wrapper = document.querySelector(`.message[data-message-id="${id}"]`);
        if (!wrapper) return;

        const area = wrapper.querySelector('.message-text');
        if (area) {
          const raw = String(e.message.display_body ?? e.message.body ?? '');
          area.innerHTML = escapeHtml(raw).replace(/(https?:\/\/[^\s]+)/g, '<a class="linkify" target="_blank" href="$1">$1</a>');
        }

        const reactWrap = wrapper.querySelector('.reactions-container');
        if (reactWrap) {
          let reactionsHTML = '';
          (e.message.reactions || []).forEach(r => {
            const emoji = r.emoji || r.reaction || '👍';
            const who   = r.user?.name || 'User';
            reactionsHTML += `<span class="badge bg-reaction rounded-pill me-1" title="${escapeHtml(who)}">${escapeHtml(emoji)}</span>`;
          });
          reactWrap.innerHTML = reactionsHTML;
        }
      })
      .listen('GroupMessageDeleted', (e) => {
        document.querySelector(`.message[data-message-id="${e.message_id}"]`)?.remove();
      })
      .listen('GroupTyping', (e) => {
        if (Number(e.user?.id) === currentUid) return;
        const indicator = document.getElementById('typing-indicator');
        if (indicator && e.is_typing) {
          indicator.style.display = 'block';
          clearTimeout(typingHideTimeout);
          typingHideTimeout = setTimeout(() => indicator.style.display = 'none', 1800);
        }
      });

    if (typeof window.Echo.join === 'function') {
      window.Echo.join(`group.${groupId}`)
        .here(users => updateOnlineList(users))
        .joining(user => addToOnlineList(user))
        .leaving(user => removeFromOnlineList(user));
    }
    console.log('[Group] Echo listeners attached on group.' + groupId);
  }

  // ===== Network awareness & reconnect banner =====
  let retryTimer=null, retryAt=null, retryDelayMs=2000;
  function showOffline() {
    if (!netBanner) return;
    netBanner.style.display = 'block';
    sendBtn?.setAttribute('disabled','disabled');
    scheduleRetry();
  }
  function showOnline() {
    if (!netBanner) return;
    netBanner.style.display = 'none';
    netRetryIn.textContent = '';
    clearTimeout(retryTimer); retryTimer=null; retryAt=null;
    retryDelayMs = 2000;
    sendBtn?.removeAttribute('disabled');
  }
  function scheduleRetry() {
    clearTimeout(retryTimer);
    retryAt = Date.now() + retryDelayMs;
    updateETA();
    retryTimer = setTimeout(async () => {
      const ok = await ping();
      if (ok) showOnline(); else {
        retryDelayMs = Math.min(retryDelayMs * 1.75, 30000);
        scheduleRetry();
      }
    }, retryDelayMs);
  }
  function updateETA(){
    if (!retryAt || !netRetryIn) return;
    const left = Math.max(0, retryAt - Date.now());
    netRetryIn.textContent = `Retry in ${Math.ceil(left/1000)}s`;
    if (left > 0) requestAnimationFrame(updateETA);
  }
  async function ping(){
    try{
      const r = await fetch(pingUrl, { credentials: 'same-origin', cache:'no-store' });
      return r.ok;
    }catch{return false;}
  }
  function setupNetworkAwareness(){
    window.addEventListener('offline', showOffline);
    window.addEventListener('online', async()=>{ (await ping()) ? showOnline() : showOffline(); });

    const echo = window.Echo;
    const pusher = echo?.connector?.pusher;
    if (pusher?.connection) {
      pusher.connection.bind('state_change', ({current})=>{
        if (current==='connected') showOnline();
        if (['connecting','unavailable','failed','disconnected'].includes(current)) showOffline();
      });
    }
    if (!navigator.onLine) showOffline();
  }

  // ===== Presence helpers =====
  function updateOnlineList(users) {
    const list = document.getElementById('online-list');
    if (!list) return;
    list.innerHTML = '';
    users.forEach(u => addToOnlineList(u));
  }
  function addToOnlineList(user) {
    if (!user || Number(user.id) === currentUid) return;
    const list = document.getElementById('online-list');
    const el = document.createElement('div');
    el.className = 'avatar rounded-circle text-white d-flex align-items-center justify-content-center';
    el.style.cssText = 'width:30px;height:30px;background:#22c55e;position:relative;';
    el.textContent = (user.name || '?').charAt(0).toUpperCase();
    el.title = user.name || 'Online';
    const dot = document.createElement('span');
    dot.style.cssText = 'position:absolute;right:-2px;bottom:-2px;width:10px;height:10px;background:#22c55e;border:2px solid var(--bg);border-radius:50%;';
    el.appendChild(dot);
    list?.appendChild(el);
  }
  function removeFromOnlineList(user) {
    const list = document.getElementById('online-list');
    if (!list) return;
    [...list.children].forEach(c => { if (c.title === (user?.name || 'Online')) c.remove(); });
  }

  // ===== DOM builders =====
  function createMessageElement(message, isOwn = false) {
    let attachmentsHTML = '';
    if (Array.isArray(message.attachments)) {
      message.attachments.forEach(file => {
        const path = String(file.file_path || '');
        const ext  = (path.split('.').pop() || '').toLowerCase();
        const isImage = ['jpg','jpeg','png','gif','webp'].includes(ext);
        const url  = STORAGE_URL + path;
        attachmentsHTML += isImage
          ? `<div class="mt-2"><img class="img-fluid rounded media-img" alt="image" loading="lazy" data-src="${url}" style="max-width:220px;"></div>`
          : `<div class="mt-2"><a href="${url}" target="_blank" class="d-inline-flex align-items-center doc-link"><i class="bi bi-file-earmark me-1"></i> ${escapeHtml(file.original_name || 'file')}</a></div>`;
      });
    }

    let replyPreviewHTML = '';
    if ((message.reply_to_id || message.replyTo) && message.replyTo) {
      const repliedText = message.replyTo.body || message.replyTo.display_body || '';
      replyPreviewHTML = `<div class="reply-preview"><small>Replying to: ${escapeHtml(String(repliedText)).slice(0, 100)}</small></div>`;
    }

    const fwdHeader = (message.forwarded_from_id || message.is_forwarded)
      ? `<div class="mb-1"><small class="muted"><i class="bi bi-forward-fill me-1"></i>Forwarded</small></div>`
      : '';

    const raw  = String(message.display_body ?? message.body ?? '');
    const text = escapeHtml(raw).replace(/(https?:\/\/[^\s]+)/g, '<a class="linkify" target="_blank" href="$1">$1</a>');

    const t = new Date(message.created_at || Date.now());
    const time = t.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

    const wrapper = document.createElement('div');
    wrapper.className = `message mb-3 d-flex ${isOwn ? 'justify-content-end' : 'justify-content-start'}`;
    wrapper.dataset.messageId = message.id;
    wrapper.dataset.fromMe    = isOwn ? '1' : '0';
    wrapper.dataset.read      = isOwn ? '1' : (message.read_at ? '1' : '0');

    let statusIcon = '<i class="bi bi-check2 muted" title="Sent"></i>';
    if (message.read_at) statusIcon = '<i class="bi bi-check2-all text-primary" title="Read"></i>';
    else if (message.delivered_at) statusIcon = '<i class="bi bi-check2-all muted" title="Delivered"></i>';

    let reactionsHTML = '';
    if (Array.isArray(message.reactions)) {
      message.reactions.forEach(r => {
        const emoji = r.emoji || r.reaction || '👍';
        const who   = r.user?.name || 'User';
        reactionsHTML += `<span class="badge bg-reaction rounded-pill me-1" title="${escapeHtml(who)}">${escapeHtml(emoji)}</span>`;
      });
    }

    wrapper.innerHTML = `
      <div class="message-bubble ${isOwn ? 'sent' : 'received'}">
        ${!isOwn && message.sender ? `<small class="sender-name">${escapeHtml(message.sender.name || message.sender.phone || '')}</small>` : ''}
        <div class="message-content">
          ${replyPreviewHTML}
          ${fwdHeader}
          <div class="message-text">${text}</div>
          ${attachmentsHTML}
        </div>
        <div class="message-footer d-flex justify-content-between align-items-center mt-1">
          <small class="muted">${time}</small>
          ${isOwn ? `<div class="status-indicator">${statusIcon}</div>` : ''}
        </div>
        ${reactionsHTML ? `<div class="reactions-container mt-1">${reactionsHTML}</div>` : '<div class="reactions-container mt-1"></div>'}
      </div>
      <div class="message-actions dropdown">
        <button class="btn btn-sm p-0" data-bs-toggle="dropdown" aria-label="Actions"><i class="bi bi-three-dots-vertical"></i></button>
        <ul class="dropdown-menu">
          <li><button class="dropdown-item reply-btn" data-message-id="${message.id}">Reply</button></li>
          <li><button class="dropdown-item forward-btn" data-message-id="${message.id}">Forward</button></li>
          ${isOwn ? `<li><button class="dropdown-item delete-btn" data-message-id="${message.id}">Delete</button></li>` : ''}
          <li><hr class="dropdown-divider"></li>
          <li>
            <div class="d-flex px-3 py-1">
              <button class="btn btn-sm reaction-btn" data-reaction="👍" data-react-url="/groups/${groupId}/messages/${message.id}/reactions">👍</button>
              <button class="btn btn-sm reaction-btn ms-1" data-reaction="❤️" data-react-url="/groups/${groupId}/messages/${message.id}/reactions">❤️</button>
              <button class="btn btn-sm reaction-btn ms-1" data-reaction="😂" data-react-url="/groups/${groupId}/messages/${message.id}/reactions">😂</button>
              <button class="btn btn-sm reaction-btn ms-1" data-reaction="😮" data-react-url="/groups/${groupId}/messages/${message.id}/reactions">😮</button>
            </div>
          </li>
        </ul>
      </div>
    `;
    return wrapper;
  }

  function appendMessageToChat(message, isOwn = false) {
    if (!messagesContainer) return;
    const el = createMessageElement(message, isOwn);
    messagesContainer.appendChild(el);
    lazyLoadImages();
  }

  // ===== Forward picker (unchanged, trimmed for brevity) =====
  function setupForwardPicker() {
    const data = safeParseJSON(forwardDatasetsEl?.textContent || '{}') || {};
    const dmListEl    = document.getElementById('fwd-dm-list');
    const grpListEl   = document.getElementById('fwd-group-list');
    const searchDMs   = document.getElementById('fwd-search-dms');
    const searchGrps  = document.getElementById('fwd-search-groups');
    const selCount    = document.getElementById('fwd-selected-count');

    if (!dmListEl || !grpListEl) return;

    let selectedDMs  = new Set();
    let selectedGrps = new Set();

    function renderList(items, container, selectedSet) {
      container.innerHTML = '';
      if (!Array.isArray(items) || items.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'text-center text-muted py-3';
        empty.textContent = 'No results';
        container.appendChild(empty);
        return;
      }
      items.forEach(it => {
        const row = document.createElement('label');
        row.className = 'list-group-item d-flex align-items-center gap-2';
        row.innerHTML = `
          ${it.avatar ? `<img src="${it.avatar}" class="list-avatar" alt="">` :
            `<div class="list-avatar d-inline-flex align-items-center justify-content-center bg-avatar text-white"><small>${escapeHtml((it.name||'?').charAt(0).toUpperCase())}</small></div>`}
          <div class="flex-grow-1">${escapeHtml(it.name || 'Unknown')}</div>
          <input type="checkbox" class="form-check-input ms-auto">
        `;
        const cb = row.querySelector('input[type="checkbox"]');
        cb.checked = selectedSet.has(it.id);
        cb.addEventListener('change', () => {
          if (cb.checked) selectedSet.add(it.id); else selectedSet.delete(it.id);
          updateCount();
          forwardConfirmBtn.disabled = (selectedDMs.size + selectedGrps.size) === 0;
        });
        row.addEventListener('click', (e) => {
          if (e.target.tagName.toLowerCase() === 'input') return;
          cb.checked = !cb.checked;
          cb.dispatchEvent(new Event('change'));
        });
        container.appendChild(row);
      });
    }

    function updateCount() { if (selCount) selCount.textContent = (selectedDMs.size + selectedGrps.size).toString(); }
    function filterItems(items, q) {
      q = (q || '').trim().toLowerCase();
      if (!q) return items;
      return items.filter(it => (it.name || '').toLowerCase().includes(q));
    }

    renderList(data.conversations || [], dmListEl, selectedDMs);
    renderList(data.groups || [],        grpListEl, selectedGrps);
    updateCount();

    searchDMs?.addEventListener('input', () => {
      renderList(filterItems(data.conversations || [], searchDMs.value), dmListEl, selectedDMs);
    });
    searchGrps?.addEventListener('input', () => {
      renderList(filterItems(data.groups || [], searchGrps.value), grpListEl, selectedGrps);
    });

    document.querySelectorAll('.forward-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        forwardSourceId.value = this.dataset.messageId || '';
        forwardConfirmBtn.disabled = (selectedDMs.size + selectedGrps.size) === 0;
        forwardModal?.show();
      });
    });

    forwardConfirmBtn?.addEventListener('click', async () => {
      const mid = forwardSourceId.value;
      if (!mid) { alert('No message selected'); return; }
      const conversation_ids = Array.from(selectedDMs);
      const group_ids        = Array.from(selectedGrps);

      try {
        if (conversation_ids.length) {
          await apiFetch('/messages/forward', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message_id: mid, conversation_ids })
          });
        }
        if (group_ids.length) {
          await apiFetch(`/groups/${groupId}/forward`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message_id: mid, group_ids })
          });
        }
        forwardModal?.hide();
      } catch (err) {
        showOffline();
        alert(err?.message || 'Failed to forward message.');
      }
    });
  }

  // ===== Utils =====
  function escapeHtml(s) { return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
  function safeParseJSON(s){ try { return JSON.parse(s); } catch { return null; } }
});
</script>
