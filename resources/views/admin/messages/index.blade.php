@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-6">
            <h1 class="h3 mb-0">Message Management</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="{{ route('admin.conversations') }}" class="btn btn-secondary">
                View Conversations
            </a>
        </div>
    </div>

    <div class="card shadow">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Content</th>
                            <th>Sender</th>
                            <th>Conversation</th>
                            <th>Sent At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($messages as $message)
                        <tr>
                            <td>{{ $message->id }}</td>
                            <td class="text-truncate" style="max-width: 200px;">
                                {{ $message->body }}
                                @if($message->attachments->count())
                                <span class="badge bg-info">Attachment</span>
                                @endif
                            </td>
                            <td>
                                <a href="#">
                                    {{ $message->sender->name ?? 'Deleted User' }}
                                </a>
                            </td>
                            <td>#{{ $message->conversation->id }}</td>
                            <td>{{ $message->created_at->format('M d, Y H:i') }}</td>
                            <td>
                                <a href="{{ route('admin.message.show', $message->id) }}" class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <form action="{{ route('admin.message.delete', $message->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this message?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <div class="mt-4">
                {{ $messages->links() }}
            </div>
        </div>
    </div>
</div>
@endsection