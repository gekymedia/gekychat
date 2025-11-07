@extends('layouts.admin')

@section('content')
<div class="container mt-4">
    <h1 class="mb-4">Banned Users</h1>
    @if($bannedUsers->isEmpty())
        <p>No banned users.</p>
    @else
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Banned Since</th>
                    <th>Ban Expires</th>
                </tr>
            </thead>
            <tbody>
                @foreach($bannedUsers as $user)
                <tr>
                    <td>{{ $user->id }}</td>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->phone }}</td>
                    <td>{{ $user->updated_at->format('Y-m-d') }}</td>
                    <td>{{ optional($user->banned_until)->format('Y-m-d H:i') ?? 'Indefinite' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">Back to Dashboard</a>
</div>
@endsection