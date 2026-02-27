@extends('layouts.app')

@section('title', 'Channels - ' . config('app.name', 'GekyChat'))

@php
    // Set sidebar variables (same as chat.index)
    $convShowBase = '/c/';
    $groupShowBase = '/g/';
@endphp

@section('content')
    {{-- General channels view (no specific channel selected) --}}
    <div class="d-flex flex-column h-100">
        {{-- Mobile header with back button --}}
        <div class="chat-header border-bottom p-3 d-md-none">
            <div class="d-flex align-items-center">
                <a href="{{ route('chat.index') }}" class="btn btn-link text-decoration-none p-0 me-3" title="Back to Chats">
                    <i class="bi bi-arrow-left" style="font-size: 1.5rem;"></i>
                </a>
                <div>
                    <h4 class="mb-0"><i class="bi bi-broadcast-tower me-2"></i>Channels</h4>
                    <small class="text-muted">Select a channel to view messages</small>
                </div>
            </div>
        </div>
        
        <div class="d-flex flex-column align-items-center justify-content-center flex-grow-1 empty-chat-state" role="main">
            <div class="text-center p-4 max-w-400">
                <div
                    class="avatar bg-card mb-4 mx-auto rounded-circle d-flex align-items-center justify-content-center empty-chat-icon">
                    <i class="bi bi-broadcast-tower" aria-hidden="true"></i>
                </div>
                <h1 class="h4 empty-chat-title mb-3">Channels</h1>
                <p class="muted mb-4 empty-chat-subtitle">
                    Select a channel from the sidebar to view messages
                </p>
            </div>
        </div>
    </div>

    {{-- Shared Modals --}}
    @include('chat.shared.forward_modal', ['context' => 'direct'])
    @include('chat.shared.image_modal')

    {{-- Forward Data --}}
    @php
        $conversationsData = [];
        foreach ($conversations ?? [] as $conversationItem) {
            $otherUser = $conversationItem->members->where('id', '!=', auth()->id())->first();
            $conversationsData[] = [
                'id' => $conversationItem->id,
                'name' => $otherUser->name ?? 'Unknown',
                'phone' => $otherUser->phone ?? '',
                'avatar' => $otherUser->avatar_url ?? '',
                'type' => 'conversation',
                'subtitle' => 'Direct chat',
            ];
        }

        $groupsData = [];
        foreach ($groups ?? [] as $group) {
            $groupsData[] = [
                'id' => $group->id,
                'title' => $group->name,
                'name' => $group->name,
                'avatar' => $group->avatar_url ?? '',
                'type' => 'group',
                'subtitle' => $group->members->count() . ' members',
            ];
        }
        
        // Add channels to forward groups
        foreach ($channels ?? [] as $channel) {
            $groupsData[] = [
                'id' => $channel->id,
                'title' => $channel->name,
                'name' => $channel->name,
                'avatar' => $channel->avatar_url ?? '',
                'type' => 'channel',
                'subtitle' => $channel->members->count() . ' ' . ($channel->members->count() === 1 ? 'follower' : 'followers'),
            ];
        }
    @endphp

    <script type="application/json" id="forward-datasets">
    {
        "conversations": @json($conversationsData),
        "groups": @json($groupsData)
    }
    </script>
@endsection
