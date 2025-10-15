@extends('layouts.app')

@section('content')
<div class="container">
    <h4 class="mb-4">🛡️ Admin - Messages</h4>

    @foreach($messages as $message)
        <div class="border p-3 mb-3 rounded">
            <div class="d-flex justify-content-between">
                <div>
                    <strong>{{ $message->sender->phone }} (Conversation #{{ $message->conversation_id }}):</strong>
                    <p class="mb-1">{{ $message->body }}</p>
                    <small class="text-muted">{{ $message->created_at->format('M d, Y h:i A') }}</small>
                </div>
                <form action="{{ route('admin.message.delete', $message->id) }}" method="POST">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-danger">Delete</button>
                </form>
            </div>
        </div>
    @endforeach

    {{ $messages->links() }}
</div>
@endsection