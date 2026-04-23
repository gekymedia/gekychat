@php
    $previousDate = null;
@endphp
@foreach ($messages as $message)
    @php
        $isOwnMessage = $message->sender_id === auth()->id();
        $canEdit = $isOwnMessage;
        $canDelete = $isOwnMessage;
        $currentDate = $message->created_at->startOfDay();
        $showDateDivider = $previousDate === null || !$currentDate->isSameDay($previousDate);
    @endphp

    @if($showDateDivider)
        <div class="date-divider text-center my-3" data-date="{{ $message->created_at->format('Y-m-d') }}">
            <span class="date-divider-text bg-bg px-3 py-1 rounded-pill text-muted small fw-semibold">
                {{ \App\Helpers\DateHelper::formatChatDate($message->created_at) }}
            </span>
        </div>
    @endif

    @include('chat.shared.message', [
        'message' => $message,
        'isGroup' => false,
        'conversation' => $conversation,
        'showSenderNames' => false,
        'canEdit' => $canEdit,
        'canDelete' => $canDelete,
    ])

    @php
        $previousDate = $currentDate;
    @endphp
@endforeach
