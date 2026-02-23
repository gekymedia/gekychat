@php
    use Illuminate\Support\Facades\DB;

    $isGroupMessage = $isGroup ?? false;
    $pollRow = $isGroupMessage
        ? DB::table('message_polls')->where('group_message_id', $message->id)->first()
        : DB::table('message_polls')->where('message_id', $message->id)->first();

    if (!$pollRow) {
        $pollRow = null;
    }
@endphp

@if($pollRow)
@php
    $options = DB::table('message_poll_options')
        ->where('poll_id', $pollRow->id)
        ->orderBy('sort_order')
        ->get();

    $totalVotes = DB::table('message_poll_votes')->where('poll_id', $pollRow->id)->count();
    $myVotes = auth()->check()
        ? DB::table('message_poll_votes')
            ->where('poll_id', $pollRow->id)
            ->where('user_id', auth()->id())
            ->pluck('option_id')
            ->toArray()
        : [];

    $optionsWithCounts = $options->map(function ($opt) use ($pollRow, $totalVotes, $myVotes) {
        $voteCount = DB::table('message_poll_votes')->where('option_id', $opt->id)->count();
        $percentage = $totalVotes > 0 ? round(($voteCount / $totalVotes) * 100) : 0;
        return (object)[
            'id' => $opt->id,
            'text' => $opt->text,
            'vote_count' => $voteCount,
            'percentage' => $percentage,
            'is_voted' => in_array($opt->id, $myVotes),
        ];
    });

    $isClosed = $pollRow->closes_at && \Carbon\Carbon::parse($pollRow->closes_at)->isPast();
    $pollId = $pollRow->id;
    $voteEndpoint = '/api/v1/polls/' . $pollId . '/vote';
@endphp
<div class="poll-message mt-2" data-poll-id="{{ $pollId }}" data-allow-multiple="{{ $pollRow->allow_multiple ? '1' : '0' }}" data-vote-endpoint="{{ $voteEndpoint }}">
    <div class="poll-card rounded border bg-light p-3" role="article" aria-label="Poll: {{ $pollRow->question }}">
        <div class="d-flex align-items-center gap-2 mb-2">
            <i class="bi bi-bar-chart-fill text-primary" style="font-size: 1.25rem;" aria-hidden="true"></i>
            <h6 class="mb-0 fw-semibold text-dark flex-grow-1">{{ $pollRow->question }}</h6>
        </div>
        @if($pollRow->allow_multiple)
            <small class="text-muted d-block mb-2"><i class="bi bi-check2-square me-1"></i>Multiple answers allowed</small>
        @endif
        @if($pollRow->is_anonymous)
            <small class="text-muted d-block mb-2"><i class="bi bi-incognito me-1"></i>Anonymous poll</small>
        @endif

        <div class="poll-options">
            @foreach($optionsWithCounts as $opt)
                <div class="poll-option-item mb-2 position-relative" data-option-id="{{ $opt->id }}">
                    @if($isClosed || auth()->guest())
                        <div class="d-flex justify-content-between align-items-center small">
                            <span class="text-dark">{{ $opt->text }}</span>
                            <span class="text-muted">{{ $opt->vote_count }} vote{{ $opt->vote_count !== 1 ? 's' : '' }} ({{ $opt->percentage }}%)</span>
                        </div>
                        @if($totalVotes > 0)
                            <div class="progress mt-1" style="height: 6px;">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $opt->percentage }}%;" aria-valuenow="{{ $opt->percentage }}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        @endif
                    @else
                        <button type="button"
                                class="poll-option-btn btn btn-outline-secondary btn-sm w-100 text-start d-flex justify-content-between align-items-center {{ $opt->is_voted ? 'active' : '' }}"
                                data-option-id="{{ $opt->id }}">
                            <span>{{ $opt->text }}</span>
                            @if($opt->is_voted)
                                <i class="bi bi-check-circle-fill text-primary"></i>
                            @endif
                        </button>
                        @if($totalVotes > 0)
                            <div class="progress mt-1" style="height: 4px;">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $opt->percentage }}%;"></div>
                            </div>
                            <small class="text-muted">{{ $opt->vote_count }} vote{{ $opt->vote_count !== 1 ? 's' : '' }}</small>
                        @endif
                    @endif
                </div>
            @endforeach
        </div>

        <small class="text-muted d-block mt-2">{{ $totalVotes }} total vote{{ $totalVotes !== 1 ? 's' : '' }}</small>
        @if($isClosed)
            <small class="text-muted d-block"><i class="bi bi-lock me-1"></i>Poll closed</small>
        @endif
    </div>
</div>

@if(auth()->check() && !$isClosed)
<script>
(function() {
    const card = document.querySelector('.poll-message[data-poll-id="{{ $pollId }}"]');
    if (!card || window.pollVoteBound{{ $pollId }}) return;
    window.pollVoteBound{{ $pollId }} = true;
    const voteEndpoint = card.dataset.voteEndpoint;
    const allowMultiple = card.dataset.allowMultiple === '1';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    card.querySelectorAll('.poll-option-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const optionId = parseInt(this.dataset.optionId, 10);
            let optionIds = [optionId];
            if (allowMultiple) {
                const alreadyVoted = Array.from(card.querySelectorAll('.poll-option-btn.active')).map(b => parseInt(b.dataset.optionId, 10));
                if (alreadyVoted.includes(optionId)) {
                    optionIds = alreadyVoted.filter(id => id !== optionId);
                } else {
                    optionIds = [...alreadyVoted, optionId];
                }
            }
            fetch(voteEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(allowMultiple && optionIds.length > 1 ? { option_ids: optionIds } : { option_id: optionIds[0] })
            })
            .then(r => r.json())
            .then(() => { window.location.reload(); })
            .catch(() => { if (typeof showToast === 'function') showToast('Failed to vote', 'error'); });
        });
    });
})();
</script>
@endif

<style>
.poll-card {
    max-width: 320px;
    border: 1px solid var(--border-color, #dee2e6) !important;
}
.poll-option-btn.active {
    background: var(--bs-primary-bg-subtle);
    border-color: var(--bs-primary);
    color: var(--bs-primary);
}
[data-theme="dark"] .poll-card {
    background: var(--bs-dark-bg-subtle) !important;
    border-color: #444 !important;
}
[data-theme="dark"] .poll-card h6 { color: var(--bs-light); }
</style>
@endif
