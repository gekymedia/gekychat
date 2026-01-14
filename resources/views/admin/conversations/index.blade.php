@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-6">
            <h1 class="h3 mb-0">Conversation Management</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="{{ route('admin.messages') }}" class="btn btn-secondary">
                View Messages
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
                            <th>Participants</th>
                            <th>Messages</th>
                            <th>Started</th>
                            <th>Last Message</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($conversations as $conversation)
                        <tr>
                            <td>{{ $conversation->id }}</td>
                            <td>
                                <div class="d-flex">
                                    <div class="me-2">
                                        <span class="badge bg-primary">
                                            {{ $conversation->userOne->name }}
                                        </span>
                                    </div>
                                    <div>
                                        <span class="badge bg-secondary">
                                            {{ $conversation->userTwo->name }}
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $conversation->messages->count() }}</td>
                            <td>{{ $conversation->created_at->format('M d, Y') }}</td>
                            <td>
                                @if($conversation->messages->last())
                                {{ $conversation->messages->last()->created_at->diffForHumans() }}
                                @else
                                No messages
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.conversation.show', $conversation->id) }}" class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <form action="{{ route('admin.conversation.delete', $conversation->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this conversation and all messages?')">
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
                {{ $conversations->links() }}
            </div>
        </div>
    </div>
</div>
@endsection