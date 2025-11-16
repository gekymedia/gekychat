<div class="message-actions dropdown">
    <button class="btn btn-sm btn-ghost" data-bs-toggle="dropdown" 
            aria-expanded="false" aria-label="Message actions">
        <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
    </button>
    <ul class="dropdown-menu">
        <li>
            <button class="dropdown-item d-flex align-items-center gap-2 reply-btn" 
                    data-message-id="{{ $messageId }}">
                <i class="bi bi-reply" aria-hidden="true"></i>
                <span>Reply</span>
            </button>
        </li>
        {{-- Reply privately is only available in group chats and not for your own messages. --}}
        @if(($isGroup ?? false) && !($isOwn ?? false))
            <li>
                <a class="dropdown-item d-flex align-items-center gap-2 reply-private-link"
                   href="{{ route('groups.messages.reply-private', ['group' => $group->id ?? ($group ?? null), 'message' => $messageId]) }}">
                    <i class="bi bi-person-lines-fill" aria-hidden="true"></i>
                    <span>Reply privately</span>
                </a>
            </li>
        @endif
        <li>
            <button class="dropdown-item d-flex align-items-center gap-2 forward-btn" 
                    data-message-id="{{ $messageId }}">
                <i class="bi bi-forward" aria-hidden="true"></i>
                <span>Forward</span>
            </button>
        </li>
        
        @if($canEdit)
            <li>
                <button class="dropdown-item d-flex align-items-center gap-2 edit-btn"
                        data-message-id="{{ $messageId }}"
                        data-body="{{ e($body) }}"
                        data-edit-url="{{ $editUrl }}">
                    <i class="bi bi-pencil" aria-hidden="true"></i>
                    <span>Edit</span>
                </button>
            </li>
        @endif
        
        @if($canDelete)
            <li>
                <button class="dropdown-item d-flex align-items-center gap-2 text-danger delete-btn" 
                        data-message-id="{{ $messageId }}"
                        data-delete-url="{{ $deleteUrl }}">
                    <i class="bi bi-trash" aria-hidden="true"></i>
                    <span>Delete</span>
                </button>
            </li>
        @endif
        
        <li><hr class="dropdown-divider"></li>
        <li>
            <div class="d-flex px-3 py-1 reaction-buttons">
                <button class="btn btn-sm reaction-btn" data-reaction="👍" data-react-url="{{ $reactUrl }}">👍</button>
                <button class="btn btn-sm reaction-btn ms-1" data-reaction="❤️" data-react-url="{{ $reactUrl }}">❤️</button>
                <button class="btn btn-sm reaction-btn ms-1" data-reaction="😂" data-react-url="{{ $reactUrl }}">😂</button>
                <button class="btn btn-sm reaction-btn ms-1" data-reaction="😮" data-react-url="{{ $reactUrl }}">😮</button>
            </div>
        </li>
    </ul>
</div>