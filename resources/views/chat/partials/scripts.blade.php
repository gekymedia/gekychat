{{-- resources/views/chat/partials/scripts.blade.php --}}
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // ==== Constants & DOM ====
        let messageIntersectionObserver = null;
        const STORAGE_URL = "{{ Storage::url('') }}"; // e.g. /storage/
        const CSRF = "{{ csrf_token() }}";
        const currentUserId = "{{auth()->id()}}";
        const chatBox = document.getElementById('chat-box');
        const messagesContainer = document.getElementById('messages-container');
        const form = document.getElementById('chat-form');
        const sendBtn = document.getElementById('send-btn');
        const msgInput = document.getElementById('message-input');
        const replyInput = document.getElementById('reply-to');
        const pickerWrap = document.getElementById('emoji-picker-wrap');
        const picker = document.getElementById('emoji-picker');
        const emojiBtn = document.getElementById('emoji-btn');
        const securityBtn = document.getElementById('security-btn');
        const progressBar = document.getElementById('upload-progress');
        const audio = document.getElementById('notification-sound');
        const netBanner = document.getElementById('net-banner');
        const netRetryIn = document.getElementById('net-retry-in');

        const securityModalEl = document.getElementById('security-modal');
        const securityModal = (window.bootstrap && securityModalEl) ? new window.bootstrap.Modal(securityModalEl) : null;

        // Forward modal elements (multi-select)
        const forwardModalEl = document.getElementById('forward-modal');
        const forwardModal = (window.bootstrap && forwardModalEl) ? new window.bootstrap.Modal(forwardModalEl) : null;
        const forwardSourceId = document.getElementById('forward-source-id');
        const fwdSearchDMs = document.getElementById('fwd-search-dms');
        const fwdSearchGroups = document.getElementById('fwd-search-groups');
        const fwdDMList = document.getElementById('fwd-dm-list');
        const fwdGroupList = document.getElementById('fwd-group-list');
        const fwdSelectedCount = document.getElementById('fwd-selected-count');
        const forwardConfirm = document.getElementById('forward-confirm');
        const datasetEl = document.getElementById('forward-datasets');

        let typingHideTimeout;
        let resizeObserver;
        let isLoadingMessages = false;
        let hasMoreMessages = true;
        let currentPage = 1;
        let toMarkRead = new Set();
        let conversationId = "{{ $conversation->id ?? '' }}";
        // ===== Forward (multi-select) =====
        const selectedDMs = new Set();
        const selectedGroups = new Set();
        let dmData = [];
        let groupData = [];
        // ==== Unified fetch helper (cookies + CSRF + AJAX header) ====
        const DEFAULT_HEADERS = {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': CSRF,
            'X-Requested-With': 'XMLHttpRequest'
        };
        async function apiFetch(url, opts = {}) {
            const headers = {
                ...DEFAULT_HEADERS,
                ...(opts.headers || {})
            };
            const options = {
                credentials: 'same-origin',
                ...opts,
                headers
            };
            const res = await fetch(url, options);
            const ct = res.headers.get('content-type') || '';
            let payload = null;
            if (ct.includes('application/json')) {
                payload = await res.json().catch(() => null);
            } else if (res.status !== 204) {
                payload = await res.text().catch(() => null);
            }
            if (!res.ok) {
                const msg = (payload && payload.message) ? payload.message : `HTTP ${res.status}`;
                const err = new Error(msg);
                err.status = res.status;
                err.payload = payload;
                throw err;
            }
            return payload;
        }

        // ==== Init ====
        initChat();

        function initChat() {
            scrollToBottom({
                force: true
            });
            setupIntersectionObserver();
            setupResizeObserver();
            lazyLoadImages();
            if (conversationId) setupEchoListeners();
            setupNetworkAwareness();
            initForwardPicker();
            wireStaticActions(); // reply/forward/delete/reaction for existing DOM
        }

        // ==== Responsive back button ====
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

        // ==== Emoji picker ====
        const togglePicker = (e) => {
            e?.stopPropagation?.();
            const visible = pickerWrap && pickerWrap.style.display === 'block';
            if (pickerWrap) pickerWrap.style.display = visible ? 'none' : 'block';
        };
        emojiBtn?.addEventListener('click', togglePicker);
        document.addEventListener('click', (e) => {
            if (pickerWrap && !pickerWrap.contains(e.target) && e.target !== emojiBtn) {
                pickerWrap.style.display = 'none';
            }
        });
        picker?.addEventListener('emoji-click', (ev) => {
            if (!msgInput) return;
            const cursorPos = msgInput.selectionStart ?? msgInput.value.length;
            const textBefore = msgInput.value.substring(0, cursorPos);
            const textAfter = msgInput.value.substring(cursorPos);
            msgInput.value = textBefore + ev.detail.unicode + textAfter;
            msgInput.selectionStart = msgInput.selectionEnd = cursorPos + ev.detail.unicode.length;
            msgInput.focus();
        });

        // ==== Security modal ====
        securityBtn?.addEventListener('click', () => securityModal?.show());
        document.getElementById('apply-security')?.addEventListener('click', () => {
            const isEncrypted = document.getElementById('encrypt-toggle').checked;
            const expiresIn = document.getElementById('expiration-select').value;
            document.getElementById('is-encrypted').value = isEncrypted ? '1' : '0';
            document.getElementById('expires-in').value = (expiresIn === '0') ? '' : expiresIn;
            securityModal?.hide();
            if (isEncrypted || (expiresIn !== '0')) {
                securityBtn.innerHTML = '<i class="bi bi-shield-lock-fill"></i>';
                securityBtn.classList.add('text-primary');
            } else {
                securityBtn.innerHTML = '<i class="bi bi-shield-lock"></i>';
                securityBtn.classList.remove('text-primary');
            }
        });

        // ==== Drag & drop upload ====
        const dropZone = document.getElementById('drop-zone');
        if (dropZone && form) {
            ['dragenter', 'dragover'].forEach(evt => {
                dropZone.addEventListener(evt, e => {
                    e.preventDefault();
                    dropZone.classList.add('drop-hover');
                }, false);
            });
            ['dragleave', 'drop'].forEach(evt => {
                dropZone.addEventListener(evt, e => {
                    e.preventDefault();
                    dropZone.classList.remove('drop-hover');
                }, false);
            });
            dropZone.addEventListener('drop', (e) => {
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
                if (e.lengthComputable && progressBar) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    progressBar.style.display = 'block';
                    progressBar.querySelector('.progress-bar').style.width = percent + '%';
                }
            };
            xhr.onload = () => {
                if (progressBar) progressBar.style.display = 'none';
                try {
                    const ok = xhr.status >= 200 && xhr.status < 300;
                    const response = JSON.parse(xhr.response || '{}');
                    if (ok && response.message) {
                        appendMessageToChat(response.message, true);
                        form.reset();
                        const rp = document.getElementById('reply-preview');
                        if (rp) rp.style.display = 'none';
                        replyInput.value = '';
                        scrollToBottom({
                            smooth: true
                        });
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

        // ==== Scroll helpers ====
        function scrollToBottom({
            smooth = false,
            force = false
        } = {}) {
            if (!chatBox) return;
            const scrollHeight = chatBox.scrollHeight;
            const clientHeight = chatBox.clientHeight;
            const scrollTop = chatBox.scrollTop;
            const distanceToBottom = scrollHeight - (clientHeight + scrollTop);
            if (force || distanceToBottom < 300) {
                if (smooth) chatBox.scrollTo({
                    top: scrollHeight,
                    behavior: 'smooth'
                });
                else chatBox.scrollTop = scrollHeight;
            }
        }

        // ==== Pagination / infinite scroll ====
        function loadMoreMessages() {
            if (isLoadingMessages || !hasMoreMessages || !conversationId) return;
            isLoadingMessages = true;
            const loader = document.getElementById('messages-loader');
            if (loader) loader.style.display = 'block';
            const nextPage = currentPage + 1;
            apiFetch(`/chat/${conversationId}/history?page=${nextPage}`)
                .then(data => {
                    const batch = data?.messages?.data ?? [];
                    if (batch.length === 0) {
                        hasMoreMessages = false;
                        return;
                    }
                    const fragment = document.createDocumentFragment();
                    batch.slice().reverse().forEach(msg => {
                        const el = createMessageElement(msg, msg.sender_id === currentUserId);
                        fragment.prepend(el);
                    });
                    messagesContainer.prepend(fragment);
                    addMessageListenersToNewElements(fragment);
                    currentPage = nextPage;
                    if (!data?.messages?.next_page_url) hasMoreMessages = false;
                })
                .catch(() => showOffline())
                .finally(() => {
                    isLoadingMessages = false;
                    if (loader) loader.style.display = 'none';
                    lazyLoadImages();
                });
        }

        //   let messageIntersectionObserver = null;
        function setupIntersectionObserver() {
            if (!chatBox) return;
            messageIntersectionObserver = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        const el = entry.target;
                        // Load older when we are near the top
                        if (el.classList.contains('message') && chatBox.scrollTop < 500 && entry.boundingClientRect.top < chatBox.clientHeight) {
                            loadMoreMessages();
                        }
                        // Mark read as they appear
                        if (el.dataset.fromMe === '0' && el.dataset.read === '0') {
                            toMarkRead.add(parseInt(el.dataset.messageId));
                            el.dataset.read = '1';
                            readDebounce();
                        }
                    }
                });
            }, {
                root: chatBox,
                rootMargin: '0px 0px 100px 0px',
                threshold: 0.1
            });
            document.querySelectorAll('.message').forEach(m => messageIntersectionObserver.observe(m));
        }

        function observeMessage(el) {
            messageIntersectionObserver?.observe(el);
        }

        function setupResizeObserver() {
            resizeObserver = new ResizeObserver(() => scrollToBottom({
                force: true
            }));
            if (chatBox) resizeObserver.observe(chatBox);
        }

        function lazyLoadImages() {
            if (!chatBox) return;
            const lazyImages = document.querySelectorAll('.media-img[data-src]:not([src])');
            const imageObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.add('loading');
                        img.onload = () => {
                            img.classList.remove('loading');
                            img.removeAttribute('data-src');
                        };
                        imageObserver.unobserve(img);
                    }
                });
            }, {
                root: chatBox,
                rootMargin: '200px 0px'
            });
            lazyImages.forEach(img => imageObserver.observe(img));
        }

        // ==== Send message ====
        form?.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!form) return;
            sendBtn.disabled = true;
            const original = sendBtn.innerHTML;
            sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';
            try {
                const data = await apiFetch(form.action, {
                    method: 'POST',
                    body: new FormData(form)
                });
                if (data?.message) {
                    appendMessageToChat(data.message, true);
                    form.reset();
                    const rp = document.getElementById('reply-preview');
                    if (rp) rp.style.display = 'none';
                    replyInput.value = '';
                    document.getElementById('is-encrypted').value = '0';
                    document.getElementById('expires-in').value = '';
                    if (securityBtn) {
                        securityBtn.innerHTML = '<i class="bi bi-shield-lock"></i>';
                        securityBtn.classList.remove('text-primary');
                    }
                    scrollToBottom({
                        smooth: true
                    });
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

        // ==== Static actions on already-rendered DOM (reply/forward/delete/react) ====
        function wireStaticActions() {
            document.querySelectorAll('.message').forEach(addMessageListeners);
        }

        // ==== Reply (static buttons already wired by wireStaticActions) ====
        document.getElementById('cancel-reply')?.addEventListener('click', () => {
            replyInput.value = '';
            const rp = document.getElementById('reply-preview');
            if (rp) rp.style.display = 'none';
        });

        // ==== Reactions (static) ====
        // Note: dynamic messages wired in addMessageListeners()



        function initForwardPicker() {
            if (!datasetEl) return;
            try {
                const data = JSON.parse(datasetEl.textContent || '{}');
                dmData = Array.isArray(data.conversations) ? data.conversations : [];
                groupData = Array.isArray(data.groups) ? data.groups : [];
            } catch {}

            renderFwdList('dm', dmData);
            renderFwdList('group', groupData);

            fwdSearchDMs?.addEventListener('input', debounce(() => {
                const q = (fwdSearchDMs.value || '').toLowerCase();
                const filtered = !q ? dmData : dmData.filter(x => (x.name || '').toLowerCase().includes(q));
                renderFwdList('dm', filtered);
            }, 200));

            fwdSearchGroups?.addEventListener('input', debounce(() => {
                const q = (fwdSearchGroups.value || '').toLowerCase();
                const filtered = !q ? groupData : groupData.filter(x => (x.name || '').toLowerCase().includes(q));
                renderFwdList('group', filtered);
            }, 200));

            // Open modal from any message's "Forward" action
            document.querySelectorAll('.forward-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    forwardSourceId.value = this.dataset.messageId || '';
                    // Reset selections every time we open
                    selectedDMs.clear();
                    selectedGroups.clear();
                    updateFwdSelectedCount();
                    clearChecks();
                    if (forwardConfirm) forwardConfirm.disabled = true;
                    forwardModal?.show();
                });
            });

            forwardConfirm?.addEventListener('click', async () => {
                const message_id = forwardSourceId.value;
                if (!message_id) {
                    alert('No message selected.');
                    return;
                }
                if (selectedDMs.size === 0 && selectedGroups.size === 0) {
                    return;
                }

                // ChatController@forwardToTargets expects { message_id, targets:[{type,id},...] }
                const targets = [
                    ...Array.from(selectedDMs).map(id => ({
                        type: 'conversation',
                        id
                    })),
                    ...Array.from(selectedGroups).map(id => ({
                        type: 'group',
                        id
                    }))
                ];

                try {
                    const data = await apiFetch("{{ route('chat.forward.targets') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            message_id,
                            targets
                        })
                    });
                    forwardModal?.hide();

                    const forwarded = Array.isArray(data?.results?.conversations) ?
                        data.results.conversations : [];
                    forwarded.forEach(m => {
                        if (String(m.conversation_id) === String(conversationId)) {
                            appendMessageToChat(m, true);
                        }
                    });

                } catch (e) {
                    showOffline();
                    alert(e?.message || 'Failed to forward message.');
                }
            });
        }

        function renderFwdList(type, items) {
            const container = type === 'dm' ? fwdDMList : fwdGroupList;
            const selectedSet = type === 'dm' ? selectedDMs : selectedGroups;
            if (!container) return;
            container.innerHTML = '';

            if (!items.length) {
                const empty = document.createElement('div');
                empty.className = 'text-center text-muted py-3';
                empty.textContent = 'No results';
                container.appendChild(empty);
                return;
            }

            items.forEach(item => {
                const row = document.createElement('label');
                row.className = 'list-group-item d-flex align-items-center gap-3';

                const cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.className = 'form-check-input';
                cb.style.marginTop = '0';
                cb.checked = selectedSet.has(item.id);

                cb.addEventListener('change', () => {
                    if (cb.checked) selectedSet.add(item.id);
                    else selectedSet.delete(item.id);
                    updateFwdSelectedCount();
                });

                let avatarEl;
                if (item.avatar) {
                    avatarEl = document.createElement('img');
                    avatarEl.className = 'list-avatar';
                    avatarEl.alt = '';
                    avatarEl.src = item.avatar;
                    avatarEl.onerror = () => {
                        avatarEl.replaceWith(makeInitialBadge(item.name));
                    };
                } else {
                    avatarEl = makeInitialBadge(item.name);
                }

                const title = document.createElement('div');
                title.className = 'flex-grow-1';
                title.textContent = item.name || 'Unknown';

                row.appendChild(cb);
                row.appendChild(avatarEl);
                row.appendChild(title);
                container.appendChild(row);

                // Clicking row toggles checkbox
                row.addEventListener('click', (e) => {
                    if (e.target.tagName.toLowerCase() === 'input') return;
                    cb.checked = !cb.checked;
                    cb.dispatchEvent(new Event('change'));
                });
            });

            updateFwdSelectedCount();
        }

        function clearChecks() {
            fwdDMList?.querySelectorAll('input[type="checkbox"]').forEach(c => c.checked = false);
            fwdGroupList?.querySelectorAll('input[type="checkbox"]').forEach(c => c.checked = false);
        }

        function updateFwdSelectedCount() {
            const total = selectedDMs.size + selectedGroups.size;
            if (fwdSelectedCount) fwdSelectedCount.textContent = String(total);
            if (forwardConfirm) forwardConfirm.disabled = total === 0;
        }

        function makeInitialBadge(name = '') {
            const d = document.createElement('div');
            d.className = 'list-avatar d-flex align-items-center justify-content-center bg-avatar text-white';
            d.textContent = (String(name || '?').charAt(0) || '?').toUpperCase();
            return d;
        }

        // ==== Clear chat (for-me) ====
        const clearBtn = document.getElementById('clear-chat-btn');
        @if(isset($conversation))
        clearBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            if (!confirm('Clear this chat for you?')) return;
            apiFetch("{{ route('chat.clear', $conversation->id) }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            }).then(() => {
                if (messagesContainer) messagesContainer.innerHTML = '';
            }).catch(showOffline);
        });
        @endif

        // ==== Typing indicator (local + server) ====
        let typingNotifyTimeout;
        msgInput?.addEventListener('input', () => {
            const indicator = document.getElementById('typing-indicator');
            if (indicator) indicator.style.display = msgInput.value ? 'block' : 'none';

            clearTimeout(typingNotifyTimeout);
            typingNotifyTimeout = setTimeout(() => {
                if (!conversationId) return;
                apiFetch("{{ route('chat.typing') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        conversation_id: conversationId,
                        is_typing: !!msgInput.value
                    })
                }).catch(() => {});
            }, 450);
        });

        // ==== Delete / React / Reply helper (attach to one message DOM) ====
        function addMessageListeners(el) {
            // Observe for read on new items
            observeMessage(el);

            // Reply
            el.querySelector('.reply-btn')?.addEventListener('click', (e) => {
                e.preventDefault();
                const id = e.currentTarget.dataset.messageId;
                const msgEl = document.querySelector(`.message[data-message-id="${id}"]`);
                const txt = msgEl?.querySelector('.message-text')?.textContent ?? '';
                replyInput.value = id;
                const rp = document.getElementById('reply-preview');
                if (rp) rp.style.display = 'block';
                const cont = document.querySelector('.reply-preview-content');
                if (cont) cont.textContent = txt.length > 60 ? txt.slice(0, 60) + '…' : txt;
                msgInput?.focus();
            });

            // Delete
            el.querySelector('.delete-btn')?.addEventListener('click', async (e) => {
                e.preventDefault();
                const id = e.currentTarget.dataset.messageId;
                if (!confirm('Delete this message?')) return;
                try {
                    await apiFetch(`/messages/${id}`, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json'
                        }
                    });
                    document.querySelector(`.message[data-message-id="${id}"]`)?.remove();
                } catch {
                    showOffline();
                    alert('Error deleting message.');
                }
            });

            // Reactions
            el.querySelectorAll('.reaction-btn').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    const messageId = el.dataset.messageId;
                    const reaction = e.currentTarget.dataset.reaction;
                    if (!messageId || !reaction) return;
                    try {
                        await apiFetch('/messages/react', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                message_id: messageId,
                                reaction
                            })
                        });
                    } catch {
                        showOffline();
                    }
                });
            });

            // Forward (safety)
            el.querySelector('.forward-btn')?.addEventListener('click', (e) => {
                e.preventDefault();
                if (!forwardSourceId) return;
                forwardSourceId.value = el.dataset.messageId || '';
                forwardModal?.show();
            });
        }

        function addMessageListenersToNewElements(fragment) {
            fragment.querySelectorAll('.message').forEach(el => addMessageListeners(el));
        }

        // ==== Mark read (debounced batch) ====
        const readDebounce = debounce(() => {
            if (toMarkRead.size === 0 || !conversationId) return;
            const ids = Array.from(toMarkRead);
            toMarkRead.clear();
            apiFetch("{{ route('chat.read') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    conversation_id: conversationId,
                    message_ids: ids
                })
            }).catch(() => {});
        }, 600);

        // ==== Echo / Realtime ====
        function setupEchoListeners() {
            if (!window.Echo || typeof window.Echo.private !== 'function') return;

            window.Echo.private(`chat.${conversationId}`)
                .listen('MessageSent', (e) => {
                    if (!e?.message) return;
                    if (e.message.sender_id === currentUserId) return;
                    if (e.message.is_encrypted) e.message.display_body = '[Encrypted message]';
                    appendMessageToChat(e.message);
                    audio?.play?.().catch(() => {});
                    const el = document.querySelector(`.message[data-message-id="${e.message.id}"]`);
                    if (el && el.dataset.fromMe === '0') {
                        toMarkRead.add(parseInt(e.message.id));
                        readDebounce();
                    }
                    scrollToBottom({
                        smooth: true
                    });
                })
                .listen('UserTyping', (e) => {
                    if (e?.user_id === currentUserId) return;
                    const indicator = document.getElementById('typing-indicator');
                    if (indicator) {
                        indicator.style.display = 'block';
                        clearTimeout(typingHideTimeout);
                        typingHideTimeout = setTimeout(() => indicator.style.display = 'none', 1800);
                    }
                })
                .listen('MessageRead', (e) => {
                    (e?.message_ids || []).forEach(id => {
                        const el = document.querySelector(`.message[data-message-id="${id}"] .status-indicator`);
                        if (el) el.innerHTML = '<i class="bi bi-check2-all text-primary" title="Read"></i>';
                    });
                })
                .listen('MessageStatusUpdated', (e) => {
                    const statusEl = document.querySelector(`.message[data-message-id="${e?.message_id}"] .status-indicator`);
                    if (!statusEl) return;
                    if (e?.status === 'delivered') statusEl.innerHTML = '<i class="bi bi-check2-all muted" title="Delivered"></i>';
                    else if (e?.status === 'read') statusEl.innerHTML = '<i class="bi bi-check2-all text-primary" title="Read"></i>';
                })
                .listen('MessageDeleted', (e) => {
                    document.querySelector(`.message[data-message-id="${e?.message_id}"]`)?.remove();
                })
                .listen('ChatCleared', (e) => {
                    if (String(e?.conversation_id) === String(conversationId) && messagesContainer) {
                        messagesContainer.innerHTML = '';
                    }
                });

            // Optional presence channel UI
            if (typeof window.Echo.join === 'function') {
                window.Echo.join('chat.presence')
                    .here(users => updateOnlineList(users))
                    .joining(user => addToOnlineList(user))
                    .leaving(user => removeFromOnlineList(user));
            }
        }

        // ==== Network awareness & reconnect banner ====
        let retryTimer = null,
            retryAt = null,
            retryDelayMs = 2000;

        function showOffline() {
            if (!netBanner) return;
            netBanner.style.display = 'block';
            sendBtn?.setAttribute('disabled', 'disabled');
            scheduleRetry();
        }

        function showOnline() {
            if (!netBanner) return;
            netBanner.style.display = 'none';
            if (netRetryIn) netRetryIn.textContent = '';
            clearTimeout(retryTimer);
            retryTimer = null;
            retryAt = null;
            retryDelayMs = 2000;
            sendBtn?.removeAttribute('disabled');
        }

        function scheduleRetry() {
            clearTimeout(retryTimer);
            retryAt = Date.now() + retryDelayMs;
            updateETA();
            retryTimer = setTimeout(async () => {
                const ok = await ping();
                if (ok) showOnline();
                else {
                    retryDelayMs = Math.min(retryDelayMs * 1.75, 30000);
                    scheduleRetry();
                }
            }, retryDelayMs);
        }

        function updateETA() {
            if (!retryAt || !netRetryIn) return;
            const left = Math.max(0, retryAt - Date.now());
            netRetryIn.textContent = `Retry in ${Math.ceil(left/1000)}s`;
            if (left > 0) requestAnimationFrame(updateETA);
        }
        async function ping() {
            try {
                const res = await fetch("{{ route('ping') }}", {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store'
                });
                return res.ok;
            } catch {
                return false;
            }
        }

        function setupNetworkAwareness() {
            window.addEventListener('offline', showOffline);
            window.addEventListener('online', async () => {
                (await ping()) ? showOnline(): showOffline();
            });
            const echo = window.Echo;
            const pusher = echo?.connector?.pusher;
            if (pusher?.connection) {
                pusher.connection.bind('state_change', ({
                    current
                }) => {
                    if (current === 'connected') showOnline();
                    if (['connecting', 'unavailable', 'failed', 'disconnected'].includes(current)) showOffline();
                });
            }
            if (!navigator.onLine) showOffline();
        }

        // ==== DOM builders ====
        function createMessageElement(message, isOwn = false) {
            let attachmentsHTML = '';
            if (Array.isArray(message.attachments)) {
                message.attachments.forEach(file => {
                    const path = String(file.file_path || '');
                    const ext = path.split('.').pop()?.toLowerCase() || '';
                    const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext);
                    const url = STORAGE_URL + path;
                    attachmentsHTML += isImage ?
                        `<img class="img-fluid rounded media-img" alt="image" loading="lazy" data-src="${url}" style="max-width:220px;">` :
                        `<div class="mt-2"><a href="${url}" target="_blank" class="d-inline-flex align-items-center doc-link"><i class="bi bi-file-earmark me-1"></i> ${escapeHtml(file.original_name || 'file')}</a></div>`;
                });
            }

            let replyPreviewHTML = '';
            const replyObj = message.replyTo || message.reply_to;
            if (replyObj) {
                const repliedText = replyObj.display_body || replyObj.body || '';
                replyPreviewHTML = `<div class="reply-preview"><small>Replying to: ${escapeHtml(String(repliedText)).slice(0, 100)}</small></div>`;
            }

            let text;
            if (message.is_encrypted && message.sender_id !== currentUserId) {
                text = '<i class="bi bi-lock-fill me-1"></i> Encrypted message';
            } else {
                const raw = String(message.display_body ?? message.body ?? '');
                text = escapeHtml(raw).replace(/(https?:\/\/[^\s]+)/g, '<a class="linkify" target="_blank" href="$1">$1</a>');
            }

            const t = new Date(message.created_at || Date.now());
            const time = t.toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit'
            });

            const wrapper = document.createElement('div');
            wrapper.className = `message mb-3 d-flex ${isOwn ? 'justify-content-end' : 'justify-content-start'}`;
            wrapper.dataset.messageId = message.id;
            wrapper.dataset.fromMe = isOwn ? '1' : '0';
            wrapper.dataset.read = isOwn ? '1' : (message.read_at ? '1' : '0');

            let statusIcon = '<i class="bi bi-check2 muted" title="Sent"></i>';
            if (message.read_at) statusIcon = '<i class="bi bi-check2-all text-primary" title="Read"></i>';
            else if (message.delivered_at) statusIcon = '<i class="bi bi-check2-all muted" title="Delivered"></i>';

            let reactionsHTML = '';
            if (Array.isArray(message.reactions)) {
                message.reactions.forEach(r => {
                    reactionsHTML += `<span class="badge bg-reaction rounded-pill me-1" title="${escapeHtml(r.user?.name || 'User')}">${escapeHtml(r.reaction)}</span>`;
                });
            }

            const fwdHeader = (message.is_forwarded || message.forwarded_from_id) ? `<div class="mb-1"><small class="muted"><i class="bi bi-forward-fill me-1"></i>Forwarded</small></div>` : '';

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
        ${reactionsHTML ? `<div class="reactions-container mt-1">${reactionsHTML}</div>` : ''}
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
              <button class="btn btn-sm reaction-btn" data-reaction="👍">👍</button>
              <button class="btn btn-sm reaction-btn ms-1" data-reaction="❤️">❤️</button>
              <button class="btn btn-sm reaction-btn ms-1" data-reaction="😂">😂</button>
              <button class="btn btn-sm reaction-btn ms-1" data-reaction="😮">😮</button>
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
            addMessageListeners(el);
            lazyLoadImages();
        }

        // ==== Presence helpers (optional UI) ====
        function updateOnlineList(users) {
            const list = document.getElementById('online-list');
            if (!list) return;
            list.innerHTML = '';
            (users || []).forEach(u => addToOnlineList(u));
        }

        function addToOnlineList(user) {
            if (!user || user.id === currentUserId) return;
            const list = document.getElementById('online-list');
            if (!list) return;
            const el = document.createElement('div');
            el.className = 'avatar rounded-circle text-white d-flex align-items-center justify-content-center';
            el.style.cssText = 'width:30px;height:30px;background:#22c55e;position:relative;';
            el.textContent = (user.name || '?').charAt(0).toUpperCase();
            el.title = user.name || 'Online';
            const dot = document.createElement('span');
            dot.style.cssText = 'position:absolute;right:-2px;bottom:-2px;width:10px;height:10px;background:#22c55e;border:2px solid var(--bg);border-radius:50%;';
            el.appendChild(dot);
            list.appendChild(el);
        }

        function removeFromOnlineList(user) {
            const list = document.getElementById('online-list');
            if (!list) return;
            [...list.children].forEach(c => {
                if (c.title === (user?.name || 'Online')) c.remove();
            });
        }

        // ==== Utils ====
        function escapeHtml(s) {
            return String(s).replace(/[&<>"']/g, (m) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            } [m]));
        }

        function debounce(fn, ms) {
            let t;
            return (...a) => {
                clearTimeout(t);
                t = setTimeout(() => fn(...a), ms);
            };
        }
    });
</script>