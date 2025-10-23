{{-- resources/views/groups/partials/messages.blade.php --}}
<div id="group-messages-content">
    @foreach ($group->messages as $message)
        @include('chat.shared.message', [
            'message' => $message,
            'isGroup' => true,
            'group' => $group,
            'isOwner' => $group->owner_id === auth()->id(),
            'userRole' => $group->members->firstWhere('id', auth()->id())?->pivot?->role,
            'showSenderNames' => true,
        ])
    @endforeach
</div>