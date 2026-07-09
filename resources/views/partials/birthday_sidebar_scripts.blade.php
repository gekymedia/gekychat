{{-- Birthday banner + celebrants modal (Telegram-style) --}}
<div class="modal fade" id="birthday-celebrants-modal" tabindex="-1" aria-labelledby="birthdayCelebrantsLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="birthdayCelebrantsLabel">Birthdays</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2 px-0" id="birthday-celebrants-body"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="birthday-sticker-modal" tabindex="-1" aria-labelledby="birthdayStickerLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="birthdayStickerLabel">Birthday stickers</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="text-muted small mb-3" id="birthday-sticker-subtitle">Pick a sticker to send</p>
                <div id="birthday-sticker-grid" class="d-grid gap-2" style="grid-template-columns: repeat(4, 1fr);"></div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const summaryUrl = @json(route('birthdays.summary'));
    const dismissUrl = @json(route('in-app-notices.dismiss'));
    const walletUrl = @json(route('sika.wallet'));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const storagePrefix = 'geky_birthday_dismissed_';
    let birthdaySummary = null;
    let pendingStickerCelebrant = null;
    const birthdayStickerEmojis = [
        '🎂', '🎉', '🥳', '🎁', '🎈', '🎊', '🍰', '🧁', '✨', '💖',
        '🎆', '🪅', '🎇', '🌟', '💐', '🥂', '🎀', '❤️', '🤗', '🙌',
        '👏', '😍', '🤩', '💯',
    ];

    document.querySelectorAll('[data-in-app-notice-dismiss]').forEach(btn => {
        btn.addEventListener('click', async function (e) {
            e.preventDefault();
            e.stopPropagation();
            const key = this.getAttribute('data-notice-key');
            const card = this.closest('.in-app-notice-card');
            try {
                await fetch(dismissUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ notice_key: key }),
                    credentials: 'same-origin',
                });
            } catch (err) { console.error(err); }
            if (card) card.remove();
            const wrap = document.querySelector('.in-app-notices');
            if (wrap && !wrap.querySelector('.in-app-notice-card')) wrap.remove();
        });
    });

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function titleWithHighlight(title) {
        const idx = (title || '').toLowerCase().indexOf('birthday');
        if (idx < 0) return escapeHtml(title);
        const before = escapeHtml(title.slice(0, idx));
        const match = escapeHtml(title.slice(idx, idx + 8));
        const after = escapeHtml(title.slice(idx + 8));
        return `${before}<span class="hl">${match}</span>${after}`;
    }

    function avatarHtml(c) {
        if (c.avatar_url) {
            return `<img src="${escapeHtml(c.avatar_url)}" alt="">`;
        }
        const initial = (c.name || '?').trim().charAt(0).toUpperCase();
        return `<span class="birthday-avatar-fallback d-inline-flex align-items-center justify-content-center bg-secondary text-white" style="width:32px;height:32px;border-radius:50%;font-size:12px;">${escapeHtml(initial)}</span>`;
    }

    function isDismissed(key) {
        try { return localStorage.getItem(storagePrefix + key) === '1'; } catch (_) { return false; }
    }

    function markDismissed(key) {
        try { localStorage.setItem(storagePrefix + key, '1'); } catch (_) {}
    }

    function renderBanner(data) {
        const banner = document.getElementById('birthday-chat-banner');
        if (!banner || !data?.show_banner || isDismissed(data.dismiss_key)) {
            banner?.classList.remove('visible');
            return;
        }
        birthdaySummary = data;
        document.getElementById('birthday-banner-title').innerHTML = titleWithHighlight(data.banner_title || '');
        document.getElementById('birthday-banner-sub').textContent = data.banner_subtitle || '';
        const avWrap = document.getElementById('birthday-banner-avatars');
        avWrap.innerHTML = '';
        const preview = (data.today || []).slice(0, 3);
        preview.forEach(c => {
            avWrap.insertAdjacentHTML('beforeend', avatarHtml(c));
        });
        banner.classList.add('visible');
    }

    function celebrantRow(c) {
        const sub = escapeHtml(c.last_seen_label || '');
        const isSelf = !!c.is_self;
        const actions = isSelf
            ? `<button type="button" class="btn btn-sm btn-outline-warning rounded-circle birthday-gift-btn" title="Send gift" data-user-id="${c.user_id}" data-name="${escapeHtml(c.name)}"><i class="bi bi-gift"></i></button>`
            : `<button type="button" class="btn btn-sm btn-outline-secondary rounded-circle birthday-sticker-btn" title="Send sticker" data-user-id="${c.user_id}" data-conversation-id="${c.conversation_id || ''}" data-name="${escapeHtml(c.name)}"><i class="bi bi-emoji-smile"></i></button>
               <button type="button" class="btn btn-sm btn-outline-warning rounded-circle birthday-gift-btn" title="Send gift" data-user-id="${c.user_id}" data-name="${escapeHtml(c.name)}"><i class="bi bi-gift"></i></button>
               <button type="button" class="btn btn-sm btn-outline-success rounded-circle birthday-wish-btn" title="Send wish" data-user-id="${c.user_id}" data-conversation-id="${c.conversation_id || ''}" data-name="${escapeHtml(c.name)}"><i class="bi bi-chat-dots"></i></button>`;
        return `<div class="celebrant-row d-flex align-items-center gap-2 px-3 py-2">
            ${avatarHtml(c)}
            <div class="flex-grow-1 min-width-0">
                <div class="fw-semibold text-truncate">${escapeHtml(c.name)}</div>
                ${sub ? `<div class="small text-muted text-truncate">${sub}</div>` : ''}
            </div>
            <div class="d-flex align-items-center gap-1 flex-shrink-0">${actions}</div>
        </div>`;
    }

    function openCelebrantsModal() {
        if (!birthdaySummary) return;
        const body = document.getElementById('birthday-celebrants-body');
        let html = '';
        if (!birthdaySummary.has_birthday_set) {
            html += `<div class="px-3 pb-2"><a href="/settings" class="text-decoration-none"><i class="bi bi-cake2 me-1"></i> Add your birthday</a></div>`;
        }
        if ((birthdaySummary.today || []).length) {
            html += `<div class="section-label">BIRTHDAY TODAY</div>`;
            birthdaySummary.today.forEach(c => { html += celebrantRow(c); });
        }
        if ((birthdaySummary.yesterday || []).length) {
            html += `<div class="section-label">BIRTHDAY YESTERDAY</div>`;
            birthdaySummary.yesterday.forEach(c => { html += celebrantRow(c); });
        }
        if (birthdaySummary.self_today) {
            html += `<div class="section-label">THIS IS YOU</div>`;
            html += celebrantRow({ ...birthdaySummary.self_today, last_seen_label: 'Treat yourself today' });
        }
        body.innerHTML = html || '<p class="text-muted px-3">No birthdays to show.</p>';
        body.querySelectorAll('.birthday-wish-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                openBirthdayChat(btn);
            });
        });
        body.querySelectorAll('.birthday-gift-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                openBirthdayGift(btn);
            });
        });
        body.querySelectorAll('.birthday-sticker-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                openBirthdayStickerPicker(btn);
            });
        });
        const modal = new bootstrap.Modal(document.getElementById('birthday-celebrants-modal'));
        modal.show();
    }

    async function openBirthdayChat(btn) {
        const name = btn.getAttribute('data-name') || 'friend';
        const conversationId = btn.getAttribute('data-conversation-id');
        const draft = `Happy birthday, ${name}! 🎂`;
        sessionStorage.setItem('geky_pending_composer_prefill', draft);
        let href = null;
        if (conversationId) {
            const item = document.querySelector(`[data-conversation-id="${conversationId}"]`);
            href = item?.getAttribute('href');
        }
        if (href) {
            window.location.href = href;
            return;
        }
        const userId = btn.getAttribute('data-user-id');
        if (userId) {
            window.location.href = `/c/start?user_id=${encodeURIComponent(userId)}`;
        }
    }

    function openBirthdayGift(btn) {
        const userId = btn.getAttribute('data-user-id');
        const name = btn.getAttribute('data-name') || 'friend';
        if (!userId) return;
        try {
            sessionStorage.setItem('geky_pending_sika_gift', JSON.stringify({
                userId: parseInt(userId, 10),
                name,
            }));
        } catch (_) {}
        window.location.href = walletUrl;
    }

    function openBirthdayStickerPicker(btn) {
        pendingStickerCelebrant = {
            userId: btn.getAttribute('data-user-id'),
            conversationId: btn.getAttribute('data-conversation-id') || '',
            name: btn.getAttribute('data-name') || 'friend',
        };
        const sub = document.getElementById('birthday-sticker-subtitle');
        if (sub) sub.textContent = `Send to ${pendingStickerCelebrant.name}`;
        const grid = document.getElementById('birthday-sticker-grid');
        if (!grid) return;
        grid.innerHTML = birthdayStickerEmojis.map(emoji =>
            `<button type="button" class="btn btn-light border birthday-sticker-pick py-2" data-emoji="${emoji}" style="font-size:1.75rem;line-height:1;">${emoji}</button>`
        ).join('');
        grid.querySelectorAll('.birthday-sticker-pick').forEach(pickBtn => {
            pickBtn.addEventListener('click', () => sendBirthdaySticker(pickBtn.getAttribute('data-emoji')));
        });
        const modalEl = document.getElementById('birthday-sticker-modal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }

    async function ensureCelebrantConversation(celebrant) {
        if (celebrant.conversationId) return celebrant.conversationId;
        if (!celebrant.userId) return null;
        const res = await fetch('/api/v1/conversations/start', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ user_id: parseInt(celebrant.userId, 10) }),
        });
        if (!res.ok) return null;
        const json = await res.json();
        return json?.data?.id ?? json?.id ?? null;
    }

    async function sendBirthdaySticker(emoji) {
        if (!pendingStickerCelebrant || !emoji) return;
        try {
            const conversationId = await ensureCelebrantConversation(pendingStickerCelebrant);
            if (!conversationId) throw new Error('No conversation');
            const res = await fetch(`/api/v1/conversations/${conversationId}/messages`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ body: emoji }),
            });
            if (!res.ok) throw new Error('Send failed');
            const stickerModal = document.getElementById('birthday-sticker-modal');
            if (stickerModal && typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getInstance(stickerModal)?.hide();
            }
            const celebrantsModal = document.getElementById('birthday-celebrants-modal');
            if (celebrantsModal && typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getInstance(celebrantsModal)?.hide();
            }
            if (typeof showToast === 'function') {
                showToast(`Birthday sticker sent to ${pendingStickerCelebrant.name}`, 'success');
            }
        } catch (e) {
            console.error(e);
            if (typeof showToast === 'function') {
                showToast('Could not send sticker', 'danger');
            }
        }
    }

    async function loadBirthdaySummary() {
        try {
            const res = await fetch(summaryUrl, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) return;
            const json = await res.json();
            renderBanner(json.data || json);
        } catch (e) {
            console.error('Birthday summary', e);
        }
    }

    document.getElementById('birthday-chat-banner')?.addEventListener('click', (e) => {
        if (e.target.closest('#birthday-banner-dismiss')) return;
        openCelebrantsModal();
    });
    document.getElementById('birthday-banner-dismiss')?.addEventListener('click', async (e) => {
        e.stopPropagation();
        if (!birthdaySummary?.dismiss_key) return;
        markDismissed(birthdaySummary.dismiss_key);
        document.getElementById('birthday-chat-banner')?.classList.remove('visible');
        try {
            await fetch(dismissUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ notice_key: birthdaySummary.dismiss_key }),
                credentials: 'same-origin',
            });
        } catch (_) {}
    });

    loadBirthdaySummary();
})();
</script>
