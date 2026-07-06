@php
    $previousDate = null;
@endphp
@foreach ($messages as $message)
    @php
        $currentDate = $message->created_at->startOfDay();
        $showDateDivider = $previousDate === null || !$currentDate->isSameDay($previousDate);
    @endphp

    @if ($showDateDivider)
        <div class="date-divider text-center my-3" data-date="{{ $message->created_at->format('Y-m-d') }}">
            <span class="date-divider-text bg-bg px-3 py-1 rounded-pill text-muted small fw-semibold">
                {{ \App\Helpers\DateHelper::formatChatDate($message->created_at) }}
            </span>
        </div>
    @endif

    @include('chat.shared.message_panel', [
        'message' => $message,
        'conversation' => $conversation,
    ])

    @php
        $previousDate = $currentDate;
    @endphp
@endforeach
