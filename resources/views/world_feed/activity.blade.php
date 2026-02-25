@extends('layouts.app')

@section('title', 'Activity - ' . config('app.name', 'GekyChat'))

@push('styles')
<style>
    #activity-container { max-width: 614px; margin: 0 auto; padding: 16px 0; }
    .activity-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 12px 16px;
        border-bottom: 1px solid var(--border, #eee);
        text-decoration: none;
        color: inherit;
        transition: background 0.15s ease;
    }
    .activity-item:hover { background: var(--bg-hover, #f8f9fa); }
    [data-theme="dark"] .activity-item:hover { background: rgba(255,255,255,0.06); }
    .activity-item .avatar {
        width: 44px; height: 44px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }
    .activity-item .avatar-placeholder {
        width: 44px; height: 44px;
        border-radius: 50%;
        background: var(--border, #ddd);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        color: #666;
        flex-shrink: 0;
    }
    .activity-body { flex: 1; min-width: 0; }
    .activity-item .actor-name { font-weight: 600; }
    .activity-item .summary { color: var(--text-muted, #666); }
    .activity-item .time { font-size: 0.8rem; color: var(--text-muted, #999); margin-top: 2px; }
    .activity-item .badge-live {
        background: #e74c3c;
        color: #fff;
        font-size: 0.7rem;
        padding: 2px 6px;
        border-radius: 4px;
        font-weight: 600;
    }
    .activity-thumb {
        width: 48px; height: 48px;
        border-radius: 8px;
        object-fit: cover;
        flex-shrink: 0;
    }
</style>
@endpush

@section('content')
<div class="h-100 d-flex flex-column">
    <div class="chat-header border-bottom p-3" style="position: sticky; top: 0; z-index: 100;">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <h4 class="mb-0">Activity</h4>
                <small class="text-muted">Likes, comments, follows and live</small>
            </div>
            <div>
                <button class="btn btn-outline-secondary btn-sm" id="mark-all-read-btn" style="display: none;">
                    <i class="bi bi-check-all me-1"></i> Mark all read
                </button>
            </div>
        </div>
    </div>

    <div class="flex-grow-1 overflow-auto" id="activity-scroll">
        <div id="activity-loader" class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="text-muted mt-2">Loading activity...</p>
        </div>
        <div id="activity-list" class="list-group list-group-flush" style="display: none;"></div>
        <div id="activity-empty" class="text-center py-5" style="display: none;">
            <i class="bi bi-notifications display-4 text-muted mb-3"></i>
            <h5 class="mb-2">No activity yet</h5>
            <p class="text-muted">Likes, comments, follows and live will show here.</p>
        </div>
        <div id="activity-load-more" class="text-center py-3" style="display: none;">
            <button class="btn btn-outline-primary btn-sm" id="load-more-btn">Load more</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentPage = 1;
    let lastPage = 1;
    let loading = false;

    const listEl = document.getElementById('activity-list');
    const loaderEl = document.getElementById('activity-loader');
    const emptyEl = document.getElementById('activity-empty');
    const loadMoreWrap = document.getElementById('activity-load-more');
    const loadMoreBtn = document.getElementById('load-more-btn');
    const markAllReadBtn = document.getElementById('mark-all-read-btn');

    function formatTimeAgo(iso) {
        try {
            const d = new Date(iso);
            const now = new Date();
            const s = Math.floor((now - d) / 1000);
            if (s < 60) return 'now';
            if (s < 3600) return Math.floor(s / 60) + 'm';
            if (s < 86400) return Math.floor(s / 3600) + 'h';
            if (s < 604800) return Math.floor(s / 86400) + 'd';
            if (s < 2592000) return Math.floor(s / 604800) + 'w';
            return Math.floor(s / 2592000) + 'mo';
        } catch (e) { return ''; }
    }

    function getActivityUrl(item) {
        if (item.broadcast_id && item.broadcast_slug) return '/live-broadcast/' + item.broadcast_slug;
        if (item.post_id) return '/world-feed#post-' + item.post_id;
        if (item.actor && item.actor.id) return '/world-feed?creator=' + item.actor.id;
        return '#';
    }

    function renderItem(item) {
        const actor = item.actor || {};
        const name = actor.name || 'Someone';
        const avatarUrl = actor.avatar_url || '';
        const summary = item.summary || '';
        const timeStr = formatTimeAgo(item.created_at);
        const isLive = item.type === 'live_started';
        const thumbUrl = item.post_thumbnail_url || '';
        const url = getActivityUrl(item);

        const div = document.createElement('a');
        div.className = 'activity-item list-group-item list-group-item-action';
        div.href = url;
        div.setAttribute('data-activity-id', item.id);

        let avatarHtml = '';
        if (avatarUrl) {
            avatarHtml = '<img class="avatar" src="' + escapeHtml(avatarUrl) + '" alt="">';
        } else {
            avatarHtml = '<div class="avatar-placeholder">' + (name.charAt(0) || '?').toUpperCase() + '</div>';
        }

        let trailHtml = '';
        if (isLive) {
            trailHtml = '<span class="badge-live">LIVE</span>';
        } else if (thumbUrl) {
            trailHtml = '<img class="activity-thumb" src="' + escapeHtml(thumbUrl) + '" alt="">';
        }

        div.innerHTML = '<div class="d-flex align-items-start w-100 gap-2">' +
            avatarHtml +
            '<div class="activity-body">' +
                '<div class="summary"><span class="actor-name">' + escapeHtml(name) + '</span> ' + escapeHtml(summary) + '</div>' +
                (timeStr ? '<div class="time">' + timeStr + '</div>' : '') +
            '</div>' +
            (trailHtml ? '<div class="flex-shrink-0">' + trailHtml + '</div>' : '') +
            '</div>';
        return div;
    }

    function escapeHtml(s) {
        const div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    async function loadActivity(page, append) {
        if (loading) return;
        loading = true;
        if (page === 1) {
            loaderEl.style.display = 'block';
            emptyEl.style.display = 'none';
            if (!append) listEl.innerHTML = '';
        }
        loadMoreBtn.disabled = true;

        try {
            const res = await fetch('/world-feed/activity/data?page=' + page + '&per_page=20', {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Failed to load');

            const items = data.data || [];
            const pagination = data.pagination || {};
            currentPage = pagination.current_page || page;
            lastPage = pagination.last_page || 1;

            loaderEl.style.display = 'none';
            listEl.style.display = 'block';

            items.forEach(item => listEl.appendChild(renderItem(item)));

            if (items.length === 0 && page === 1) {
                emptyEl.style.display = 'block';
            }
            if (currentPage < lastPage) {
                loadMoreWrap.style.display = 'block';
                loadMoreBtn.onclick = () => loadActivity(currentPage + 1, true);
            } else {
                loadMoreWrap.style.display = 'none';
            }
            if ((data.unread_count || 0) > 0) markAllReadBtn.style.display = 'inline-block';
        } catch (e) {
            loaderEl.innerHTML = '<div class="alert alert-danger">' + escapeHtml(e.message || 'Error loading activity') + '</div>';
        } finally {
            loading = false;
            loadMoreBtn.disabled = false;
        }
    }

    markAllReadBtn.addEventListener('click', async function() {
        try {
            const res = await fetch('/world-feed/activity/read', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ all: true })
            });
            if (res.ok) {
                markAllReadBtn.style.display = 'none';
            }
        } catch (e) {}
    });

    loadActivity(1);
});
</script>
@endpush
@endsection
