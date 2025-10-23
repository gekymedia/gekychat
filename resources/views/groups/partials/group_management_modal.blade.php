{{-- resources/views/groups/partials/group_management_modal.blade.php --}}
@php
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Str;

    $groupData = $groupData ?? [
        'name' => $group->name ?? 'Group Chat',
        'description' => $group->description ?? null,
        'avatar' => $group->avatar_path ? Storage::url($group->avatar_path) : null,
        'isPrivate' => $group->is_private ?? false,
        'memberCount' => $group->members->count() ?? 0,
        'isOwner' => $group->owner_id === auth()->id(),
        'userRole' => $group->members->firstWhere('id', auth()->id())?->pivot?->role ?? 'member',
    ];
@endphp

{{-- Add CSS styles for member avatars --}}
<style>
    .member-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }

    .bg-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.875rem;
    }

    .member-item {
        border-bottom: 1px solid #f0f0f0;
        transition: background-color 0.2s ease;
    }

    .member-item:last-child {
        border-bottom: none;
    }

    .member-item:hover {
        background-color: #f8f9fa;
    }

    .member-role-badge {
        font-size: 0.7rem;
        padding: 0.2rem 0.5rem;
        border-radius: 12px;
        font-weight: 600;
    }

    .role-owner {
        background-color: #fff3cd;
        color: #856404;
        border: 1px solid #ffeaa7;
    }

    .role-admin {
        background-color: #d1ecf1;
        color: #0c5460;
        border: 1px solid #bee5eb;
    }

    .action-btn {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .action-btn.promote {
        background-color: #e8f5e8;
        color: #28a745;
        border: 1px solid #c3e6cb;
    }

    .action-btn.promote:hover {
        background-color: #d4edda;
        color: #155724;
    }

    .action-btn.remove {
        background-color: #f8d7da;
        color: #dc3545;
        border: 1px solid #f5c6cb;
    }

    .action-btn.remove:hover {
        background-color: #f1b0b7;
        color: #721c24;
    }

    .min-width-0 {
        min-width: 0;
    }

    .group-settings-avatar {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border: 3px solid #fff;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .dragover {
        background-color: #f8f9fa;
        border-color: #007bff !important;
    }

    .invite-link-container {
        display: flex;
        gap: 0.5rem;
    }

    .invite-link {
        flex: 1;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        padding: 0.5rem 0.75rem;
        background-color: #f8f9fa;
        font-size: 0.875rem;
    }

    .copy-invite-btn.copied {
        background-color: #28a745 !important;
        border-color: #28a745 !important;
    }

    .invite-stats {
        display: flex;
        gap: 1rem;
        font-size: 0.875rem;
    }

    .stat-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #6c757d;
    }
</style>

{{-- Group Settings Modal --}}
<div class="modal fade" id="editGroupModal" tabindex="-1" aria-labelledby="editGroupLabel" aria-hidden="true"
    >
    <div class="modal-dialog modal-dialog-centered">
        {{-- In your group_management_modal.blade.php --}}
        <form class="modal-content" id="edit-group-form" action="{{ route('groups.update', $group->id) }}" method="POST"
            enctype="multipart/form-data" novalidate>
            @csrf
            @method('PUT')

            <div class="modal-header">
                <h2 class="modal-title h5" id="editGroupLabel">
                    Edit {{ $group->type === 'channel' ? 'Channel' : 'Group' }}
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                {{-- Avatar Upload --}}
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="position-relative">
                        <img id="groupAvatarPreview"
                            src="{{ $group->avatar_path ? Storage::url($group->avatar_path) : asset('images/group-default.png') }}"
                            class="rounded-circle group-settings-avatar" alt="Group avatar preview"
                            onerror="this.src='{{ asset('images/group-default.png') }}'">
                        <div
                            class="position-absolute bottom-0 end-0 bg-primary rounded-circle p-1 border border-2 border-white">
                            <i class="bi bi-camera text-white" style="font-size: 0.75rem;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <label for="groupAvatarInput" class="form-label mb-1 fw-medium">
                            {{ $group->type === 'channel' ? 'Channel' : 'Group' }} Avatar
                        </label>
                        <input type="file" name="avatar" id="groupAvatarInput" class="form-control"
                            accept="image/png,image/jpeg,image/webp" aria-describedby="avatarHelp">
                        <div id="avatarHelp" class="form-text">PNG, JPG or WebP. Max 2MB.</div>
                    </div>
                </div>

                {{-- Group Name --}}
                <div class="mb-3">
                    <label for="groupNameInput" class="form-label fw-medium">
                        {{ $group->type === 'channel' ? 'Channel' : 'Group' }} Name
                    </label>
                    <input type="text" name="name" id="groupNameInput" class="form-control"
                        value="{{ old('name', $group->name) }}" maxlength="64" required
                        placeholder="Enter {{ $group->type === 'channel' ? 'channel' : 'group' }} name"
                        aria-describedby="nameHelp">
                    @error('name')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                    <div id="nameHelp" class="form-text">Maximum 64 characters</div>
                </div>

                {{-- Group Description --}}
                <div class="mb-3">
                    <label for="groupDescriptionInput" class="form-label fw-medium">Description</label>
                    <textarea name="description" id="groupDescriptionInput" class="form-control" rows="3" maxlength="200"
                        placeholder="Optional {{ $group->type === 'channel' ? 'channel' : 'group' }} description"
                        aria-describedby="descriptionHelp">{{ old('description', $group->description) }}</textarea>
                    @error('description')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                    <div class="form-text d-flex justify-content-between">
                        <span id="description-counter">{{ strlen($group->description ?? '') }}</span>/200 characters
                    </div>
                </div>

                {{-- Group Type --}}
                <div class="mb-3">
                    <label class="form-label fw-medium">Type</label>
                    <div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="type" id="typeGroup" value="group"
                                {{ $group->type === 'group' ? 'checked' : '' }}>
                            <label class="form-check-label" for="typeGroup">Group</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="type" id="typeChannel"
                                value="channel" {{ $group->type === 'channel' ? 'checked' : '' }}>
                            <label class="form-check-label" for="typeChannel">Channel</label>
                        </div>
                    </div>
                    @error('type')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Privacy Setting - Only show for groups, channels are always public --}}
                @if ($group->type === 'group')
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_public_switch"
                            name="is_public" value="1" {{ $group->is_public ? 'checked' : '' }}
                            aria-describedby="privacyHelp">
                        <label class="form-check-label fw-medium" for="is_public_switch">
                            <i class="bi bi-globe me-2" aria-hidden="true"></i>
                            Public group
                        </label>
                        <div id="privacyHelp" class="form-text">
                            Public groups can be discovered and joined by anyone
                        </div>
                    </div>
                @else
                    <input type="hidden" name="is_public" value="1">
                @endif

                {{-- Member Management Section (Owner/Admin only) --}}
                @if ($group->isAdmin(auth()->id()) || $group->owner_id === auth()->id())
                    <div class="group-management-section border-top pt-3 mt-3">
                        <h3 class="h6 group-management-title">
                            <i class="bi bi-people-fill" aria-hidden="true"></i>
                            Member Management
                        </h3>

                        <div class="members-list" style="max-height: 200px; overflow-y: auto;">
                            @foreach ($group->members as $member)
                                @php
                                    $isSelf = $member->id === auth()->id();
                                    $isOwner = $group->owner_id === $member->id;
                                    $isAdmin = optional($member->pivot)->role === 'admin';
                                    $canManage =
                                        !$isSelf &&
                                        ($group->owner_id === auth()->id() ||
                                            ($group->isAdmin(auth()->id()) && !$isOwner && !$isAdmin));
                                @endphp

                                <div
                                    class="member-item d-flex align-items-center justify-content-between py-2 px-2 rounded">
                                    <div class="d-flex align-items-center gap-3 flex-grow-1">
                                        {{-- Member Avatar --}}
                                        @if ($member->avatar_path && Storage::exists($member->avatar_path))
                                            <img src="{{ Storage::url($member->avatar_path) }}" class="member-avatar"
                                                alt="{{ $member->name ?? $member->phone }} avatar"
                                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="bg-avatar text-white d-flex align-items-center justify-content-center"
                                                style="display: none;">
                                                <small>{{ Str::upper(Str::substr($member->name ?? ($member->phone ?? 'U'), 0, 1)) }}</small>
                                            </div>
                                        @else
                                            <div
                                                class="bg-avatar text-white d-flex align-items-center justify-content-center">
                                                <small>{{ Str::upper(Str::substr($member->name ?? ($member->phone ?? 'U'), 0, 1)) }}</small>
                                            </div>
                                        @endif

                                        {{-- Member Info --}}
                                        <div class="flex-grow-1 min-width-0">
                                            <div class="d-flex align-items-center gap-2">
                                                <strong class="text-truncate d-block" style="max-width: 150px;">
                                                    {{ $member->name ?? ($member->phone ?? 'Unknown User') }}
                                                </strong>
                                                @if ($isOwner)
                                                    <span class="member-role-badge role-owner"
                                                        title="Group owner">Owner</span>
                                                @elseif($isAdmin)
                                                    <span class="member-role-badge role-admin"
                                                        title="Group admin">Admin</span>
                                                @endif
                                            </div>
                                            @if ($isSelf)
                                                <small class="text-muted">You</small>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Management Actions --}}
                                    @if ($canManage)
                                        <div class="member-management-actions d-flex gap-1">
                                            @if (!$isAdmin && ($group->owner_id === auth()->id() || $group->isAdmin(auth()->id())))
                                                <button class="btn btn-sm action-btn promote" type="button"
                                                    data-member-id="{{ $member->id }}"
                                                    data-member-name="{{ $member->name ?? $member->phone }}"
                                                    title="Promote to admin"
                                                    aria-label="Promote {{ $member->name ?? $member->phone }} to admin">
                                                    <i class="bi bi-arrow-up" aria-hidden="true"></i>
                                                </button>
                                            @endif

                                            @if ($group->owner_id === auth()->id() && !$isOwner)
                                                <button class="btn btn-sm action-btn remove" type="button"
                                                    data-member-id="{{ $member->id }}"
                                                    data-member-name="{{ $member->name ?? $member->phone }}"
                                                    title="Remove from group"
                                                    aria-label="Remove {{ $member->name ?? $member->phone }} from group">
                                                    <i class="bi bi-person-dash" aria-hidden="true"></i>
                                                </button>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="edit-group-save">
                    <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"
                        style="display: none;"></span>
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Invite Link Modal --}}
{{-- In your group_management_modal.blade.php --}}
{{-- Replace the existing invite modal with this --}}
{{-- Group Details Offcanvas --}}
<div class="offcanvas offcanvas-end" tabindex="-1" id="groupDetails" aria-labelledby="groupDetailsLabel">
    <div class="offcanvas-header">
        <h2 class="offcanvas-title h5" id="groupDetailsLabel">
            <i class="bi bi-info-circle me-2" aria-hidden="true"></i>
            Group Info
        </h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        {{-- Group Header --}}
        <div class="text-center mb-4">
            <div class="position-relative d-inline-block">
                @if ($groupData['avatar'])
                    <img src="{{ $groupData['avatar'] }}" class="rounded-circle group-details-avatar mb-3"
                        alt="{{ $groupData['name'] }} group avatar"
                        onerror="this.src='{{ asset('images/group-default.png') }}'">
                @else
                    <div
                        class="group-details-avatar rounded-circle bg-brand text-white d-flex align-items-center justify-content-center mx-auto mb-3">
                        {{ $groupData['initial'] }}
                    </div>
                @endif

                @if ($groupData['isPrivate'])
                    <span
                        class="position-absolute bottom-0 end-0 bg-dark rounded-circle border border-2 border-white d-flex align-items-center justify-content-center"
                        style="width: 20px; height: 20px;" title="Private group">
                        <i class="bi bi-lock-fill text-white" style="font-size: 0.6rem;"></i>
                    </span>
                @endif
            </div>

            <h3 class="h4 mb-2">{{ $groupData['name'] }}</h3>

            @if ($groupData['description'])
                <p class="text-muted mb-3">{{ $groupData['description'] }}</p>
            @endif

            <div class="d-flex justify-content-center gap-4 text-muted small">
                <div>
                    <i class="bi bi-people me-1" aria-hidden="true"></i>
                    <span>{{ $groupData['memberCount'] }} members</span>
                </div>
                <div>
                    <i class="bi bi-calendar me-1" aria-hidden="true"></i>
                    <span>Created {{ $group->created_at->diffForHumans() }}</span>
                </div>
            </div>
        </div>

        {{-- Group Type Badge --}}
        <div class="d-flex justify-content-center mb-4">
            <span class="badge {{ $groupData['isPrivate'] ? 'bg-warning' : 'bg-info' }}">
                <i class="bi {{ $groupData['isPrivate'] ? 'bi-lock-fill' : 'bi-globe' }} me-1"
                    aria-hidden="true"></i>
                {{ $groupData['isPrivate'] ? 'Private Group' : 'Public Channel' }}
            </span>
        </div>

        {{-- Quick Actions --}}
        <div class="row g-2 mb-4">
            @if ($groupData['isOwner'] || $groupData['userRole'] === 'admin')
                <div class="col-6">
                    <button
                        class="btn btn-outline-primary w-100 d-flex align-items-center justify-content-center gap-2"
                        data-bs-toggle="modal" data-bs-target="#editGroupModal" data-bs-dismiss="offcanvas">
                        <i class="bi bi-pencil" aria-hidden="true"></i>
                        Edit
                    </button>
                </div>
            @endif
            <div class="col-6">
                <button class="btn btn-outline-success w-100 d-flex align-items-center justify-content-center gap-2"
                    data-bs-toggle="modal" data-bs-target="#group-invite-modal" data-bs-dismiss="offcanvas">
                    <i class="bi bi-link-45deg" aria-hidden="true"></i>
                    Invite
                </button>
            </div>
        </div>

        {{-- Members Section --}}
        <div class="members-section">
            <h4 class="h6 mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-people-fill" aria-hidden="true"></i>
                Members ({{ $groupData['memberCount'] }})
            </h4>

            <div class="members-list" style="max-height: 300px; overflow-y: auto;">
                @foreach ($group->members as $member)
                    @php
                        $isSelf = $member->id === auth()->id();
                        $isOwner = $group->owner_id === $member->id;
                        $isAdmin = optional($member->pivot)->role === 'admin';
                    @endphp

                    <div class="member-item d-flex align-items-center justify-content-between py-2 px-2 rounded">
                        <div class="d-flex align-items-center gap-3 flex-grow-1">
                            {{-- Member Avatar --}}
                            @if ($member->avatar_path && Storage::exists($member->avatar_path))
                                <img src="{{ Storage::url($member->avatar_path) }}" class="member-avatar"
                                    alt="{{ $member->name ?? $member->phone }} avatar"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="bg-avatar text-white d-flex align-items-center justify-content-center"
                                    style="display: none;">
                                    <small>{{ Str::upper(Str::substr($member->name ?? ($member->phone ?? 'U'), 0, 1)) }}</small>
                                </div>
                            @else
                                <div class="bg-avatar text-white d-flex align-items-center justify-content-center">
                                    <small>{{ Str::upper(Str::substr($member->name ?? ($member->phone ?? 'U'), 0, 1)) }}</small>
                                </div>
                            @endif

                            {{-- Member Info --}}
                            <div class="flex-grow-1 min-width-0">
                                <div class="d-flex align-items-center gap-2">
                                    <strong class="text-truncate d-block" style="max-width: 150px;">
                                        {{ $member->name ?? ($member->phone ?? 'Unknown User') }}
                                        @if ($isSelf)
                                            <small class="text-muted">(You)</small>
                                        @endif
                                    </strong>
                                    @if ($isOwner)
                                        <span class="member-role-badge role-owner" title="Group owner">Owner</span>
                                    @elseif($isAdmin)
                                        <span class="member-role-badge role-admin" title="Group admin">Admin</span>
                                    @endif
                                </div>
                                <small class="text-muted">
                                    {{-- FIXED: Convert string to Carbon instance --}}
                                    Joined {{ \Carbon\Carbon::parse($member->pivot->joined_at)->diffForHumans() }}
                                </small>
                            </div>
                        </div>

                        {{-- Online Status --}}
                        <div class="online-status">
                            <div class="status-indicator {{ $member->isOnline() ? 'online' : 'offline' }}"
                                title="{{ $member->isOnline() ? 'Online' : 'Offline' }}">
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Group Statistics --}}
        <div class="group-stats mt-4 pt-3 border-top">
            <h4 class="h6 mb-3">Group Statistics</h4>
            <div class="row g-3 text-center">
                <div class="col-4">
                    <div class="stat-card p-2 rounded bg-light">
                        <div class="h5 mb-1 text-primary">{{ $group->messages()->count() }}</div>
                        <small class="text-muted">Messages</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="stat-card p-2 rounded bg-light">
                        <div class="h5 mb-1 text-success">{{ $group->members()->where('role', 'admin')->count() }}
                        </div>
                        <small class="text-muted">Admins</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="stat-card p-2 rounded bg-light">
                        <div class="h5 mb-1 text-info">{{ $group->created_at->format('M Y') }}</div>
                        <small class="text-muted">Created</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .group-details-avatar {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border: 4px solid #fff;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .member-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }

    .bg-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.875rem;
    }

    .member-item {
        border-bottom: 1px solid #f0f0f0;
        transition: background-color 0.2s ease;
    }

    .member-item:last-child {
        border-bottom: none;
    }

    .member-item:hover {
        background-color: #f8f9fa;
    }

    .member-role-badge {
        font-size: 0.7rem;
        padding: 0.2rem 0.5rem;
        border-radius: 12px;
        font-weight: 600;
    }

    .role-owner {
        background-color: #fff3cd;
        color: #856404;
        border: 1px solid #ffeaa7;
    }

    .role-admin {
        background-color: #d1ecf1;
        color: #0c5460;
        border: 1px solid #bee5eb;
    }

    .status-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        transition: all 0.3s ease;
    }

    .status-indicator.online {
        background-color: #28a745;
        box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.3);
    }

    .status-indicator.offline {
        background-color: #6c757d;
        opacity: 0.5;
    }

    .stat-card {
        transition: transform 0.2s ease;
    }

    .stat-card:hover {
        transform: translateY(-2px);
    }

    .offcanvas {
        background: var(--bg);
        color: var(--text);
    }

    .offcanvas-header {
        border-bottom: 1px solid var(--border);
    }

    .offcanvas-body {
        padding: 1rem;
    }
</style>
{{-- Group Invite Modal --}}
<div class="modal fade" id="group-invite-modal" tabindex="-1" aria-labelledby="groupInviteModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="groupInviteModalLabel">
                    <i class="bi bi-people-fill me-2" aria-hidden="true"></i>
                    Invite to <span id="group-invite-name">{{ $groupData['name'] }}</span>
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="invite-section border-0 p-0">
                    <p class="text-muted mb-3">Share this link to invite others to the group:</p>

                    <div class="invite-link-container mb-3">
                        <input type="text" class="invite-link form-control" id="group-invite-link-input"
                            value="{{ route('groups.show', $group) }}" readonly aria-label="Group invite link">
                        <button class="btn btn-primary copy-invite-btn" type="button" id="copy-group-invite">
                            <i class="bi bi-copy" aria-hidden="true"></i>
                        </button>
                    </div>

                    <div class="invite-stats">
                        <div class="stat-item">
                            <i class="bi bi-people" aria-hidden="true"></i>
                            <span id="group-invite-member-count">{{ $groupData['memberCount'] }} members</span>
                        </div>
                        @if ($groupData['isPrivate'])
                            <div class="stat-item">
                                <i class="bi bi-lock-fill" aria-hidden="true"></i>
                                <span>Private • Invite only</span>
                            </div>
                        @else
                            <div class="stat-item">
                                <i class="bi bi-globe" aria-hidden="true"></i>
                                <span>Public • Anyone can join</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Group invite copy functionality
        const copyGroupInviteBtn = document.getElementById('copy-group-invite');
        const groupInviteLinkInput = document.getElementById('group-invite-link-input');

        if (copyGroupInviteBtn && groupInviteLinkInput) {
            copyGroupInviteBtn.addEventListener('click', async function() {
                try {
                    await navigator.clipboard.writeText(groupInviteLinkInput.value);

                    // Visual feedback
                    const originalHtml = this.innerHTML;
                    this.innerHTML = '<i class="bi bi-check2"></i>';
                    this.classList.add('copied');

                    setTimeout(() => {
                        this.innerHTML = originalHtml;
                        this.classList.remove('copied');
                    }, 2000);

                    showToast('Group invite link copied to clipboard', 'success');
                } catch (err) {
                    // Fallback for browsers that don't support clipboard API
                    groupInviteLinkInput.select();
                    document.execCommand('copy');
                    showToast('Group invite link copied to clipboard', 'success');
                }
            });
        }
    });
</script>

<style>
    .invite-link-container {
        display: flex;
        gap: 0.5rem;
    }

    .invite-link {
        flex: 1;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        padding: 0.5rem 0.75rem;
        background-color: #f8f9fa;
        font-size: 0.875rem;
    }

    .copy-invite-btn.copied {
        background-color: #28a745 !important;
        border-color: #28a745 !important;
    }

    .invite-stats {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        font-size: 0.875rem;
    }

    .stat-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #6c757d;
    }
</style>

{{-- Leave Group Confirmation Modal --}}
<div class="modal fade" id="leaveGroupModal" tabindex="-1" aria-labelledby="leaveGroupModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="leaveGroupModalLabel">Leave Group</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center py-3">
                    <i class="bi bi-exclamation-triangle display-4 text-warning mb-3" aria-hidden="true"></i>
                    <h3 class="h6 mb-2">Are you sure you want to leave "{{ $groupData['name'] }}"?</h3>
                    <p class="text-muted mb-0">
                        You will no longer receive messages from this group and will need to be re-invited to join
                        again.
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="{{ route('groups.leave', $group) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-danger">Leave Group</button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- JavaScript for Group Management --}}
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const editGroupModal = document.getElementById('editGroupModal');
        const editGroupForm = document.getElementById('edit-group-form');
        const groupAvatarInput = document.getElementById('groupAvatarInput');
        const groupAvatarPreview = document.getElementById('groupAvatarPreview');
        const groupDescriptionInput = document.getElementById('groupDescriptionInput');
        const descriptionCounter = document.getElementById('description-counter');

        console.log('Group management script loaded');
        console.log('Avatar input:', groupAvatarInput);
        console.log('Avatar preview:', groupAvatarPreview);

        // Initialize group management functionality
        if (editGroupModal) {
            initializeGroupManagement();
        }

        function initializeGroupManagement() {
            console.log('Initializing group management...');
            setupAvatarUpload();
            setupFormValidation();
            setupMemberActions();
        }

        function setupAvatarUpload() {
            console.log('Setting up avatar upload...');

            if (groupAvatarInput && groupAvatarPreview) {
                console.log('Avatar elements found, setting up event listener');

                groupAvatarInput.addEventListener('change', function(e) {
                    console.log('File input changed');
                    const file = e.target.files[0];
                    console.log('Selected file:', file);

                    if (file) {
                        if (!validateImageFile(file)) {
                            this.value = '';
                            showToast('Please select a valid image file (PNG, JPG, WebP) under 2MB',
                                'error');
                            return;
                        }

                        const reader = new FileReader();
                        reader.onload = function(e) {
                            console.log('File loaded, updating preview');
                            groupAvatarPreview.src = e.target.result;
                            groupAvatarPreview.style.display = 'block';
                        };
                        reader.onerror = function(e) {
                            console.error('Error reading file:', e);
                            showToast('Error reading image file', 'error');
                        };
                        reader.readAsDataURL(file);
                    } else {
                        console.log('No file selected');
                    }
                });

                // Add click event to the avatar area for easier selection
                const avatarArea = groupAvatarPreview.parentElement;
                if (avatarArea) {
                    avatarArea.addEventListener('click', function(e) {
                        if (e.target === avatarArea || e.target === groupAvatarPreview) {
                            groupAvatarInput.click();
                        }
                    });

                    // Drag and drop for avatar
                    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                        avatarArea.addEventListener(eventName, preventDefaults, false);
                    });

                    ['dragenter', 'dragover'].forEach(eventName => {
                        avatarArea.addEventListener(eventName, highlight, false);
                    });

                    ['dragleave', 'drop'].forEach(eventName => {
                        avatarArea.addEventListener(eventName, unhighlight, false);
                    });

                    avatarArea.addEventListener('drop', handleDrop, false);
                }
            } else {
                console.error('Avatar input or preview element not found');
            }

            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            function highlight() {
                this.classList.add('dragover');
            }

            function unhighlight() {
                this.classList.remove('dragover');
            }

            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                if (files.length > 0) {
                    groupAvatarInput.files = files;
                    groupAvatarInput.dispatchEvent(new Event('change'));
                }
            }
        }

        function validateImageFile(file) {
            const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
            const maxSize = 2 * 1024 * 1024; // 2MB

            if (!validTypes.includes(file.type)) {
                console.error('Invalid file type:', file.type);
                return false;
            }

            if (file.size > maxSize) {
                console.error('File too large:', file.size);
                return false;
            }

            return true;
        }

        function setupFormValidation() {
            // Character counter for description
            if (groupDescriptionInput && descriptionCounter) {
                updateCounter(groupDescriptionInput.value.length);
                groupDescriptionInput.addEventListener('input', function() {
                    updateCounter(this.value.length);
                });

                function updateCounter(length) {
                    descriptionCounter.textContent = length;
                    if (length > 180) {
                        descriptionCounter.classList.add('text-warning');
                    } else {
                        descriptionCounter.classList.remove('text-warning');
                    }
                }
            }
        }

        // Form submission - FIXED to prevent page reload
        // Form submission - FIXED modal closing
        if (editGroupForm) {
            editGroupForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                console.log('Form submission started');

                const submitBtn = this.querySelector('#edit-group-save');
                const spinner = submitBtn.querySelector('.spinner-border');

                submitBtn.disabled = true;
                spinner.style.display = 'inline-block';

                try {
                    const formData = new FormData(this);

                    // Debug: log form data
                    console.log('Form data being sent:');
                    for (let [key, value] of formData.entries()) {
                        if (key !== 'avatar') {
                            console.log(key + ': ' + value);
                        } else {
                            console.log(key + ': [file]');
                        }
                    }

                    const response = await fetch(this.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector(
                                'meta[name="csrf-token"]').getAttribute('content')
                        },
                    });

                    console.log('Response status:', response.status);

                    // Get the response text first
                    const responseText = await response.text();
                    console.log('Raw response:', responseText);

                    let result;
                    try {
                        result = JSON.parse(responseText);
                    } catch (parseError) {
                        console.error('Failed to parse JSON response:', parseError);
                        throw new Error('Invalid server response');
                    }

                    if (response.ok && result.success) {
                        showToast(
                            '{{ $group->type === 'channel' ? 'Channel' : 'Group' }} updated successfully',
                            'success');

                        // FIXED: Close modal using jQuery/bootstrap method that actually works
                        closeModalProperly();

                        // Update the UI without full page reload
                        updateUIWithoutReload(result.group);

                    } else {
                        // Handle validation errors
                        if (result.errors) {
                            const errorMessages = Object.values(result.errors).flat().join(', ');
                            throw new Error(errorMessages);
                        }
                        throw new Error(result.message ||
                            'Failed to update {{ $group->type === 'channel' ? 'channel' : 'group' }}'
                            );
                    }

                } catch (error) {
                    console.error('Update error:', error);
                    showToast(error.message ||
                        'Failed to update {{ $group->type === 'channel' ? 'channel' : 'group' }}. Please try again.',
                        'error');
                } finally {
                    submitBtn.disabled = false;
                    spinner.style.display = 'none';
                }
            });
        }

        // FIXED: Proper modal closing function
        function closeModalProperly() {
            const modalElement = document.getElementById('editGroupModal');
            if (modalElement) {
                // Method 1: Use Bootstrap's hide method directly
                const modal = bootstrap.Modal.getInstance(modalElement);
                if (modal) {
                    modal.hide();
                } else {
                    // Method 2: If instance doesn't exist, create one and hide it
                    const newModal = new bootstrap.Modal(modalElement);
                    newModal.hide();
                }

                // Method 3: Fallback - manually hide the modal
                setTimeout(() => {
                    modalElement.classList.remove('show');
                    modalElement.style.display = 'none';
                    document.body.classList.remove('modal-open');
                    const backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) {
                        backdrop.remove();
                    }
                }, 100);
            }
        }
        // Function to update UI without page reload
        function updateUIWithoutReload(updatedGroup) {
            console.log('Updating UI with new group data:', updatedGroup);

            // Update group header
            const groupHeader = document.querySelector('.group-header');
            if (groupHeader) {
                // Update group name in header
                const groupNameElement = groupHeader.querySelector('.group-name');
                if (groupNameElement) {
                    groupNameElement.textContent = updatedGroup.name;
                }

                // Update group avatar in header
                const headerAvatar = groupHeader.querySelector('.group-avatar');
                if (headerAvatar && updatedGroup.avatar_path) {
                    headerAvatar.src = "{{ Storage::url('') }}" + updatedGroup.avatar_path;
                    headerAvatar.onerror = function() {
                        this.src = '{{ asset('images/group-default.png') }}';
                    };
                }
            }

            // Update group details in offcanvas
            const groupDetails = document.getElementById('groupDetails');
            if (groupDetails) {
                // Update group name
                const detailsName = groupDetails.querySelector('.offcanvas-title');
                if (detailsName) {
                    detailsName.textContent = updatedGroup.name;
                }

                // Update group avatar
                const detailsAvatar = groupDetails.querySelector('.group-details-avatar');
                if (detailsAvatar && updatedGroup.avatar_path) {
                    detailsAvatar.src = "{{ Storage::url('') }}" + updatedGroup.avatar_path;
                    detailsAvatar.onerror = function() {
                        this.src = '{{ asset('images/group-default.png') }}';
                    };
                }

                // Update group description
                const detailsDescription = groupDetails.querySelector('.group-description');
                if (detailsDescription) {
                    detailsDescription.textContent = updatedGroup.description || '';
                }
            }

            // Update any other UI elements that show group info
            document.title = updatedGroup.name + ' - Group Chat';

            console.log('UI updated successfully');
        }

        function setupMemberActions() {
            // Delegated event handling for member actions
            editGroupModal.addEventListener('click', async function(e) {
                const promoteBtn = e.target.closest('.promote');
                const removeBtn = e.target.closest('.remove');

                if (promoteBtn) {
                    e.preventDefault();
                    await handleMemberPromotion(promoteBtn);
                }

                if (removeBtn) {
                    e.preventDefault();
                    await handleMemberRemoval(removeBtn);
                }
            });
        }

        async function handleMemberPromotion(button) {
            const memberId = button.dataset.memberId;
            const memberName = button.dataset.memberName;

            if (!confirm(`Promote ${memberName} to admin?`)) return;

            try {
                const response = await fetch(`/g/{{ $group->id }}/members/${memberId}/promote`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const result = await response.json();

                if (result.success) {
                    showToast(`${memberName} promoted to admin`, 'success');
                    // FIXED: Use the proper modal closing method
                    closeModalProperly();
                    // Reopen modal after a short delay to show updated roles
                    setTimeout(() => {
                        const modal = new bootstrap.Modal(document.getElementById(
                        'editGroupModal'));
                        modal.show();
                    }, 500);
                } else {
                    throw new Error(result.message || 'Failed to promote member');
                }
            } catch (error) {
                console.error('Promotion error:', error);
                showToast(`Failed to promote member: ${error.message}`, 'error');
            }
        }

        async function handleMemberRemoval(button) {
            const memberId = button.dataset.memberId;
            const memberName = button.dataset.memberName;

            if (!confirm(`Remove ${memberName} from the group?`)) return;

            try {
                const response = await fetch(`/g/{{ $group->id }}/members/${memberId}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const result = await response.json();

                if (result.success) {
                    showToast(`${memberName} removed from group`, 'success');
                    // FIXED: Use the proper modal closing method
                    closeModalProperly();
                    // Reopen modal after a short delay to show updated member list
                    setTimeout(() => {
                        const modal = new bootstrap.Modal(document.getElementById(
                        'editGroupModal'));
                        modal.show();
                    }, 500);
                } else {
                    throw new Error(result.message || 'Failed to remove member');
                }
            } catch (error) {
                console.error('Removal error:', error);
                showToast(`Failed to remove member: ${error.message}`, 'error');
            }
        }
        // Public API for group management
        window.groupManagement = {
            showEditModal: () => {
                const modal = new bootstrap.Modal(editGroupModal);
                modal.show();
            },
            showInviteModal: () => {
                const modal = new bootstrap.Modal(document.getElementById('group-invite-modal'));
                modal.show();
            },
            showLeaveModal: () => {
                const modal = new bootstrap.Modal(document.getElementById('leaveGroupModal'));
                modal.show();
            }
        };
    });

    // Global helper function
    function showToast(message, type = 'info') {
        // Use your existing toast implementation or create a simple one
        const toastContainer = document.getElementById('toast-container') || createToastContainer();

        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-bg-${type === 'error' ? 'danger' : type} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');

        toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;

        toastContainer.appendChild(toast);

        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();

        // Remove toast after it's hidden
        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    }

    function createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
        return container;
    }

   // FIXED: Better Bootstrap modal management
document.addEventListener('DOMContentLoaded', function() {
    let modalInstances = new Map();
    
    function initializeBootstrapComponents() {
        // Initialize all modal elements
        const modalElements = document.querySelectorAll('.modal');
        modalElements.forEach(element => {
            try {
                // Store the modal instance for later use
                const modal = new bootstrap.Modal(element);
                modalInstances.set(element.id, modal);
            } catch (error) {
                console.warn('Modal initialization error:', error);
            }
        });

        // Initialize all offcanvas elements
        const offcanvasElements = document.querySelectorAll('.offcanvas');
        offcanvasElements.forEach(element => {
            try {
                new bootstrap.Offcanvas(element);
            } catch (error) {
                console.warn('Offcanvas initialization error:', error);
            }
        });
    }

    // Global function to get modal instance
    window.getModalInstance = function(modalId) {
        return modalInstances.get(modalId);
    };

    // Global function to close modal
    window.closeModal = function(modalId) {
        const modal = modalInstances.get(modalId);
        if (modal) {
            modal.hide();
        } else {
            // Fallback
            const modalElement = document.getElementById(modalId);
            if (modalElement) {
                const fallbackModal = new bootstrap.Modal(modalElement);
                fallbackModal.hide();
            }
        }
    };

    // Re-initialize when DOM changes
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length) {
                initializeBootstrapComponents();
            }
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });

    // Initial initialization
    initializeBootstrapComponents();
});
</script>
