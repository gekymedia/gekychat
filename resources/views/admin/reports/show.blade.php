@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-6">
            <h1 class="h3 mb-0">Message Details</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="{{ route('admin.messages') }}" class="btn btn-secondary">
                Back to Messages
            </a>
        </div>
    </div>

    <div class="card shadow">
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-6">
                    <h5>Message Information</h5>
                    <p class="text-muted">ID: {{ $message->id }}</p>
                </div>
                <div class="col-md-6 text-end">
                    <span class="badge bg-secondary">
                        {{ $message->created_at->format('M d, Y H:i') }}
                    </span>
                </div>
            </div>

            <div class="mb-4">
                <h6>Content</h6>
                <div class="card bg-light p-3">
                    {{ $message->body }}
                </div>
            </div>

            @if($message->reply_to)
            <div class="mb-4">
                <h6>Reply To</h6>
                <div class="card bg-light p-3">
                    {{ $message->replyTo->body ?? 'Original message deleted' }}
                </div>
            </div>
            @endif

            @if($message->attachments->count())
            <div class="mb-4">
                <h6>Attachments</h6>
                <div class="row">
                    @foreach($message->attachments as $attachment)
                    <div class="col-md-3 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <a href="{{ Storage::url($attachment->file_path) }}" target="_blank" class="d-block text-center">
                                    <i class="fas fa-file fa-3x mb-2"></i>
                                    <p class="mb-0">{{ $attachment->original_name }}</p>
                                </a>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6>Sender Information</h6>
                        </div>
                        <div class="card-body">
                            <p>Name: {{ $message->sender->name ?? 'Deleted User' }}</p>
                            <p>Email: {{ $message->sender->email ?? 'N/A' }}</p>
                            <p>Phone: {{ $message->sender->phone ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6>Conversation Information</h6>
                        </div>
                        <div class="card-body">
                            <p>Conversation ID: {{ $message->conversation->id }}</p>
                            <p>Started: {{ $message->conversation->created_at->format('M d, Y') }}</p>
                            <p>Total Messages: {{ $message->conversation->messages->count() }}</p>
                            <a href="#" class="btn btn-sm btn-primary">
                                View Conversation
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-end">
                <form action="{{ route('admin.message.delete', $message->id) }}" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Delete this message?')">
                        <i class="fas fa-trash"></i> Delete Message
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection