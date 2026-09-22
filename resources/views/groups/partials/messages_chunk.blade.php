@php
    $isOwner = $isOwner ?? ($group->owner_id === auth()->id());
    $userRole = $userRole ?? ($group->members->firstWhere('id', auth()->id())?->pivot?->role);
@endphp
@foreach ($messages as $message)
    @include('chat.shared.message', [
        'message' => $message,
        'isGroup' => true,
        'group' => $group,
        'isOwner' => $isOwner,
        'userRole' => $userRole,
        'showSenderNames' => true,
    ])
@endforeach
