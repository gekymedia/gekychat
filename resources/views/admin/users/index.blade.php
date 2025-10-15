@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-6">
            <h1 class="h3 mb-0">User Management</h1>
        </div>
    </div>

    <div class="card shadow">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Conversations</th>
                            <th>Messages</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                        <tr>
                            <td>{{ $user->id }}</td>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email ?? 'N/A' }}</td>
                            <td>{{ $user->phone ?? 'N/A' }}</td>
                            <td>{{ $user->conversations_as_user_one_count + $user->conversations_as_user_two_count }}</td>
                            <td>{{ $user->messages_count }}</td>
                            <td>
                                @if($user->is_banned)
                                <span class="badge bg-danger">Banned</span>
                                @else
                                <span class="badge bg-success">Active</span>
                                @endif
                            </td>
                            <td>
                                <form action="{{ route('admin.user.toggle-ban', $user->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm {{ $user->is_banned ? 'btn-success' : 'btn-warning' }}">
                                        @if($user->is_banned)
                                        <i class="fas fa-check-circle"></i> Unban
                                        @else
                                        <i class="fas fa-ban"></i> Ban
                                        @endif
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <div class="mt-4">
                {{ $users->links() }}
            </div>
        </div>
    </div>
</div>
@endsection