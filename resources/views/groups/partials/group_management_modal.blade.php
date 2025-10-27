
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

{{-- ===== GROUP MANAGEMENT STYLES ===== --}}
<style>
    /* ===== CORE COMPONENT STYLES ===== */
    /* Avatar Styles - Theme Aware */
    .member-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
        border: 2px solid var(--border);
    }

    .bg-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        /* background: linear-gradient(135deg, var(--wa-green) 0%, var(--wa-deep) 100%); */
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.875rem;
        color: white;
        border: 2px solid var(--border);
    }

    .group-settings-avatar {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border: 3px solid var(--card);
        border-radius: 50%;
        box-shadow: var(--wa-shadow);
    }

    .group-details-avatar {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border: 4px solid var(--card);
        border-radius: 50%;
        box-shadow: var(--wa-shadow);
    }

    /* ===== MEMBER LIST STYLES ===== */
    .member-item {
        border-bottom: 1px solid var(--border);
        transition: background-color 0.2s ease;
        padding: 0.75rem;
        margin: 0 -0.75rem;
    }

    .member-item:last-child {
        border-bottom: none;
    }

    .member-item:hover {
        background-color: var(--bg-accent);
    }

    /* Role Badges - Theme Aware */
    .member-role-badge {
        font-size: 0.7rem;
        padding: 0.2rem 0.5rem;
        border-radius: 12px;
        font-weight: 600;
    }

    .role-owner {
        background-color: color-mix(in srgb, var(--wa-green) 20%, transparent);
        color: color-mix(in srgb, var(--wa-green) 80%, black);
        border: 1px solid color-mix(in srgb, var(--wa-green) 40%, transparent);
    }

    .role-admin {
        background-color: color-mix(in srgb, #007bff 20%, transparent);
        color: color-mix(in srgb, #007bff 80%, black);
        border: 1px solid color-mix(in srgb, #007bff 40%, transparent);
    }

    /* ===== ACTION BUTTONS ===== */
    .action-btn {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        border: 1px solid var(--border);
        background: var(--card);
    }

    .action-btn.promote {
        color: var(--wa-green);
    }

    .action-btn.promote:hover {
        background-color: color-mix(in srgb, var(--wa-green) 15%, transparent);
        border-color: var(--wa-green);
    }

    .action-btn.remove {
        color: #dc3545;
    }

    .action-btn.remove:hover {
        background-color: color-mix(in srgb, #dc3545 15%, transparent);
        border-color: #dc3545;
    }

    /* ===== LAYOUT UTILITIES ===== */
    .min-width-0 {
        min-width: 0;
    }

    .flex-grow-1 {
        flex-grow: 1;
    }

    /* ===== DRAG & DROP STYLES ===== */
    .dragover {
        background-color: var(--bg-accent);
        border-color: var(--wa-green) !important;
    }

    /* ===== INVITE LINK STYLES ===== */
    .invite-link-container {
        display: flex;
        gap: 0.5rem;
    }

    .invite-link {
        flex: 1;
        border: 1px solid var(--border);
        border-radius: 0.375rem;
        padding: 0.5rem 0.75rem;
        background-color: var(--input-bg);
        font-size: 0.875rem;
        color: var(--text);
    }

    .copy-invite-btn.copied {
        background-color: var(--wa-green) !important;
        border-color: var(--wa-green) !important;
        color: #062a1f;
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
        color: var(--wa-muted);
    }

    /* ===== STATUS INDICATORS ===== */
    .status-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        transition: all 0.3s ease;
    }

    .status-indicator.online {
        background-color: var(--wa-green);
        box-shadow: 0 0 0 2px color-mix(in srgb, var(--wa-green) 30%, transparent);
    }

    .status-indicator.offline {
        background-color: var(--wa-muted);
        opacity: 0.5;
    }

    /* ===== STAT CARDS ===== */
    .stat-card {
        transition: transform 0.2s ease;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 0.5rem;
    }

    .stat-card:hover {
        transform: translateY(-2px);
    }

    /* ===== MODAL & OFFCANVAS THEME SUPPORT ===== */
    .modal-content {
        background: var(--card);
        color: var(--text);
        border: 1px solid var(--border);
    }

    .modal-header {
        border-bottom: 1px solid var(--border);
    }

    .modal-footer {
        border-top: 1px solid var(--border);
    }

    .offcanvas {
        background: var(--bg);
        color: var(--text);
    }

    .offcanvas-header {
        border-bottom: 1px solid var(--border);
    }

    .btn-close {
        filter: invert(var(--invert, 0));
    }

    /* ===== FORM STYLES ===== */
    .form-control, .form-select {
        background: var(--input-bg);
        color: var(--text);
        border: 1px solid var(--input-border);
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--wa-green);
        box-shadow: 0 0 0 0.2rem color-mix(in srgb, var(--wa-green) 25%, transparent);
    }

    .form-text {
        color: var(--wa-muted);
    }

    /* ===== MANAGEMENT SECTIONS ===== */
    .group-management-section {
        border-top: 1px solid var(--border);
    }

    .members-section {
        border-top: 1px solid var(--border);
        padding-top: 1rem;
    }

    .group-stats {
        border-top: 1px solid var(--border);
    }

    /* ===== RESPONSIVE ADJUSTMENTS ===== */
    @media (max-width: 768px) {
        .group-settings-avatar {
            width: 60px;
            height: 60px;
        }
        
        .group-details-avatar {
            width: 80px;
            height: 80px;
        }
        
        .invite-stats {
            flex-direction: column;
            gap: 0.5rem;
        }
    }
</style>

{{-- ===== GROUP MANAGEMENT MODALS ===== --}}

{{-- Edit Group Modal --}}
<div class="modal fade" id="editGroupModal" tabindex="-1" aria-labelledby="editGroupLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
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
                {{-- Avatar Upload Section --}}
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="position-relative">
                        <img id="groupAvatarPreview"
                            src="{{ $group->avatar_path ? Storage::url($group->avatar_path) : asset('images/group-default.png') }}"
                            class="group-settings-avatar" alt="Group avatar preview"
                            onerror="this.src='{{ asset('images/group-default.png') }}'">
                        <div class="position-absolute bottom-0 end-0 bg-primary rounded-circle p-1 border border-2 border-white">
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

                                <div class="member-item d-flex align-items-center justify-content-between rounded">
                                    <div class="d-flex align-items-center gap-3 flex-grow-1">
                                        {{-- Member Avatar --}}
                                        @if ($member->avatar_path && Storage::exists($member->avatar_path))
                                            <img src="{{ Storage::url($member->avatar_path) }}" class="member-avatar"
                                                alt="{{ $member->name ?? $member->phone }} avatar"
                                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="bg-avatar d-none">
                                                {{ Str::upper(Str::substr($member->name ?? ($member->phone ?? 'U'), 0, 1)) }}
                                            </div>
                                        @else
                                            <div class="bg-avatar">
                                                {{ Str::upper(Str::substr($member->name ?? ($member->phone ?? 'U'), 0, 1)) }}
                                            </div>
                                        @endif

                                        {{-- Member Info --}}
                                        <div class="flex-grow-1 min-width-0">
                                            <div class="d-flex align-items-center gap-2">
                                                <strong class="text-truncate" style="max-width: 150px;">
                                                    {{ $member->name ?? ($member->phone ?? 'Unknown User') }}
                                                </strong>
                                                @if ($isOwner)
                                                    <span class="member-role-badge role-owner">Owner</span>
                                                @elseif($isAdmin)
                                                    <span class="member-role-badge role-admin">Admin</span>
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
                                                    title="Promote to admin">
                                                    <i class="bi bi-arrow-up" aria-hidden="true"></i>
                                                </button>
                                            @endif

                                            @if ($group->owner_id === auth()->id() && !$isOwner)
                                                <button class="btn btn-sm action-btn remove" type="button"
                                                    data-member-id="{{ $member->id }}"
                                                    data-member-name="{{ $member->name ?? $member->phone }}"
                                                    title="Remove from group">
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
                    <img src="{{ $groupData['avatar'] }}" class="group-details-avatar mb-3"
                        alt="{{ $groupData['name'] }} group avatar"
                        onerror="this.src='{{ asset('images/group-default.png') }}'">
                @else
                    <div class="group-details-avatar bg-brand text-white d-flex align-items-center justify-content-center mx-auto mb-3">
                        {{ Str::upper(Str::substr($groupData['name'], 0, 1)) }}
                    </div>
                @endif

                @if ($groupData['isPrivate'])
                    <span class="position-absolute bottom-0 end-0 bg-dark rounded-circle border border-2 border-white d-flex align-items-center justify-content-center"
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

        {{-- Quick Actions --}}
        <div class="row g-2 mb-4">
            @if ($groupData['isOwner'] || $groupData['userRole'] === 'admin')
                <div class="col-6">
                    <button class="btn btn-outline-primary w-100 d-flex align-items-center justify-content-center gap-2"
                        data-bs-toggle="modal" data-bs-target="#editGroupModal" data-bs-dismiss="offcanvas">
                        <i class="bi bi-pencil" aria-hidden="true"></i>
                        Edit
                    </button>
                </div>
            @endif
            <div class="col-6">
               {{-- Enhanced Invite Section --}}
<div class="invite-management-section border-top pt-3 mt-3">
    <h4 class="h6 mb-3 d-flex align-items-center gap-2">
        <i class="bi bi-link-45deg" aria-hidden="true"></i>
        Invite Link
    </h4>

    @if($group->type === 'channel' && $group->is_public)
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            Public channels are discoverable and don't need invite links.
        </div>
    @else
        <div class="invite-link-container mb-3">
            <input type="text" class="invite-link form-control" id="group-invite-link-input"
                   value="{{ $group->invite_code ? $group->getInviteLink() : '' }}" 
                   readonly aria-label="Group invite link"
                   placeholder="Generate an invite link to share...">
            <button class="btn btn-primary copy-invite-btn" type="button" id="copy-invite-btn">
                <i class="bi bi-copy" aria-hidden="true"></i>
            </button>
        </div>

        <div class="d-flex gap-2 mb-3">
            <button class="btn btn-success btn-sm" id="generate-invite-btn"
                    style="{{ $group->invite_code ? 'display: none;' : '' }}">
                <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>
                Generate Link
            </button>
            
            <button class="btn btn-outline-danger btn-sm" id="revoke-invite-btn"
                    style="{{ !$group->invite_code ? 'display: none;' : '' }}">
                <i class="bi bi-x-circle me-1" aria-hidden="true"></i>
                Revoke Link
            </button>
        </div>

        {{-- Share Options --}}
        <div id="share-invite-section" style="{{ !$group->invite_code ? 'display: none;' : '' }}">
            <label class="form-label fw-medium">Share via:</label>
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-outline-primary btn-sm share-invite-btn" data-method="whatsapp">
                    <i class="bi bi-whatsapp" aria-hidden="true"></i>
                    WhatsApp
                </button>
                <button class="btn btn-outline-info btn-sm share-invite-btn" data-method="telegram">
                    <i class="bi bi-telegram" aria-hidden="true"></i>
                    Telegram
                </button>
                <button class="btn btn-outline-secondary btn-sm share-invite-btn" data-method="copy">
                    <i class="bi bi-clipboard" aria-hidden="true"></i>
                    Copy Link
                </button>
            </div>
        </div>

        <div class="invite-stats mt-3">
            <div class="stat-item">
                <i class="bi bi-people" aria-hidden="true"></i>
                <span>{{ $group->members->count() }} members</span>
            </div>
            @if($group->is_public)
                <div class="stat-item">
                    <i class="bi bi-globe" aria-hidden="true"></i>
                    <span>Public • Anyone can join</span>
                </div>
            @else
                <div class="stat-item">
                    <i class="bi bi-lock-fill" aria-hidden="true"></i>
                    <span>Private • Invite only</span>
                </div>
            @endif
        </div>
    @endif
</div>
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

                    <div class="member-item d-flex align-items-center justify-content-between rounded">
                        <div class="d-flex align-items-center gap-3 flex-grow-1">
                            {{-- Member Avatar --}}
                            @if ($member->avatar_path && Storage::exists($member->avatar_path))
                                <img src="{{ Storage::url($member->avatar_path) }}" class="member-avatar"
                                    alt="{{ $member->name ?? $member->phone }} avatar"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="bg-avatar d-none">
                                    {{ Str::upper(Str::substr($member->name ?? ($member->phone ?? 'U'), 0, 1)) }}
                                </div>
                            @else
                                <div class="bg-avatar">
                                    {{ Str::upper(Str::substr($member->name ?? ($member->phone ?? 'U'), 0, 1)) }}
                                </div>
                            @endif

                            {{-- Member Info --}}
                            <div class="flex-grow-1 min-width-0">
                                <div class="d-flex align-items-center gap-2">
                                    <strong class="text-truncate" style="max-width: 150px;">
                                        {{ $member->name ?? ($member->phone ?? 'Unknown User') }}
                                        @if ($isSelf)
                                            <small class="text-muted">(You)</small>
                                        @endif
                                    </strong>
                                    @if ($isOwner)
                                        <span class="member-role-badge role-owner">Owner</span>
                                    @elseif($isAdmin)
                                        <span class="member-role-badge role-admin">Admin</span>
                                    @endif
                                </div>
                                <small class="text-muted">
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
    </div>
</div>

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
                <div class="invite-section">
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

{{-- ===== GROUP MANAGEMENT JAVASCRIPT ===== --}}

<script>
    // Enhanced invite management
class GroupInviteManager {
    constructor(groupId) {
        this.groupId = groupId;
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.loadInviteInfo();
    }

    setupEventListeners() {
        // Generate invite link
        document.getElementById('generate-invite-btn')?.addEventListener('click', () => {
            this.generateInviteLink();
        });

        // Copy invite link
        document.getElementById('copy-invite-btn')?.addEventListener('click', () => {
            this.copyInviteLink();
        });

        // Share via different methods
        document.querySelectorAll('.share-invite-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const method = e.target.dataset.method;
                this.shareInvite(method);
            });
        });

        // Revoke invite link
        document.getElementById('revoke-invite-btn')?.addEventListener('click', () => {
            this.revokeInviteLink();
        });
    }

    async loadInviteInfo() {
        try {
            const response = await fetch(`/groups/${this.groupId}/invite-info`);
            const data = await response.json();
            
            if (data.success) {
                this.updateUI(data);
            }
        } catch (error) {
            console.error('Failed to load invite info:', error);
        }
    }

    async generateInviteLink() {
        try {
            const response = await fetch(`/groups/${this.groupId}/generate-invite`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const data = await response.json();

            if (data.success) {
                this.updateUI(data);
                showToast('Invite link generated successfully', 'success');
            } else {
                showToast(data.message, 'error');
            }
        } catch (error) {
            console.error('Failed to generate invite:', error);
            showToast('Failed to generate invite link', 'error');
        }
    }

    async copyInviteLink() {
        const inviteLinkInput = document.getElementById('group-invite-link-input');
        if (!inviteLinkInput || !inviteLinkInput.value) {
            showToast('No invite link available', 'error');
            return;
        }

        try {
            await navigator.clipboard.writeText(inviteLinkInput.value);
            showToast('Invite link copied to clipboard', 'success');
            
            // Visual feedback
            const copyBtn = document.getElementById('copy-invite-btn');
            const originalHtml = copyBtn.innerHTML;
            copyBtn.innerHTML = '<i class="bi bi-check2"></i>';
            copyBtn.classList.add('copied');
            
            setTimeout(() => {
                copyBtn.innerHTML = originalHtml;
                copyBtn.classList.remove('copied');
            }, 2000);
        } catch (err) {
            // Fallback
            inviteLinkInput.select();
            document.execCommand('copy');
            showToast('Invite link copied to clipboard', 'success');
        }
    }

    async shareInvite(method) {
        try {
            const response = await fetch(`/groups/${this.groupId}/share-invite`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ method })
            });

            const data = await response.json();

            if (data.success) {
                if (method === 'copy') {
                    showToast(data.message, 'success');
                } else if (data.share_url) {
                    window.open(data.share_url, '_blank');
                }
            } else {
                showToast(data.message, 'error');
            }
        } catch (error) {
            console.error('Failed to share invite:', error);
            showToast('Failed to share invite', 'error');
        }
    }

    async revokeInviteLink() {
        if (!confirm('Are you sure you want to revoke this invite link? Existing links will no longer work.')) {
            return;
        }

        try {
            const response = await fetch(`/groups/${this.groupId}/revoke-invite`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const data = await response.json();

            if (data.success) {
                this.updateUI({ has_invite_code: false, invite_link: null, invite_code: null });
                showToast('Invite link revoked successfully', 'success');
            } else {
                showToast(data.message, 'error');
            }
        } catch (error) {
            console.error('Failed to revoke invite:', error);
            showToast('Failed to revoke invite link', 'error');
        }
    }

    updateUI(data) {
        const inviteLinkInput = document.getElementById('group-invite-link-input');
        const generateBtn = document.getElementById('generate-invite-btn');
        const revokeBtn = document.getElementById('revoke-invite-btn');
        const shareSection = document.getElementById('share-invite-section');

        if (inviteLinkInput) {
            inviteLinkInput.value = data.invite_link || '';
        }

        if (generateBtn) {
            generateBtn.style.display = data.has_invite_code ? 'none' : 'block';
        }

        if (revokeBtn) {
            revokeBtn.style.display = data.has_invite_code ? 'block' : 'none';
        }

        if (shareSection) {
            shareSection.style.display = data.has_invite_code ? 'block' : 'none';
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    const groupId = document.querySelector('[data-group-id]')?.dataset.groupId;
    if (groupId) {
        window.groupInviteManager = new GroupInviteManager(groupId);
    }
});
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
