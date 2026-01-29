@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-6">
            <h1 class="h3 mb-0">Message Management</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="{{ route('admin.conversations') }}" class="btn btn-secondary">
                <i class="fas fa-comments"></i> View Conversations
            </a>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.messages') }}" class="row g-3">
                <div class="col-md-10">
                    <input type="text" 
                           name="search" 
                           class="form-control" 
                           placeholder="Search messages by content..." 
                           value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
                @if(request('search'))
                <div class="col-12">
                    <a href="{{ route('admin.messages') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-times"></i> Clear Search
                    </a>
                </div>
                @endif
            </form>
        </div>
    </div>

    <div class="card shadow">
        <div class="card-body">
            @if($messages->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Content</th>
                            <th>Sender</th>
                            <th>Conversation</th>
                            <th>Sent At</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($messages as $message)
                        <tr>
                            <td>{{ $message->id }}</td>
                            <td class="text-truncate" style="max-width: 200px;" title="{{ $message->body }}">
                                {{ $message->body ?: '(No text content)' }}
                                @if($message->attachments->count())
                                <span class="badge bg-info ms-1">
                                    <i class="fas fa-paperclip"></i> {{ $message->attachments->count() }} Attachment(s)
                                </span>
                                @endif
                            </td>
                            <td>
                                @if($message->sender)
                                <a href="{{ route('admin.users.index') }}?search={{ urlencode($message->sender->name) }}" class="text-decoration-none" title="View user">
                                    {{ $message->sender->name }}
                                </a>
                                @else
                                <span class="text-muted">Deleted User</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.conversation.show', $message->conversation->id) }}" class="text-decoration-none">
                                    #{{ $message->conversation->id }}
                                </a>
                            </td>
                            <td>{{ $message->created_at->format('M d, Y H:i') }}</td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="{{ route('admin.message.show', $message->id) }}" 
                                       class="btn btn-sm btn-primary" 
                                       title="View Message">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <form action="{{ route('admin.message.delete', $message->id) }}" 
                                          method="POST" 
                                          class="d-inline"
                                          onsubmit="return confirm('Are you sure you want to delete this message? This action cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                                class="btn btn-sm btn-danger" 
                                                title="Delete Message">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <div class="mt-4">
                {{ $messages->links() }}
            </div>
            @else
            <div class="text-center py-5">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <p class="text-muted">No messages found.</p>
                @if(request('search'))
                <a href="{{ route('admin.messages') }}" class="btn btn-primary">
                    Clear Search
                </a>
                @endif
            </div>
            @endif
        </div>
    </div>
</div>
@endsection