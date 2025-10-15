@extends('layouts.app')

@section('content')
<div class="container" style="max-width:720px">
  <div class="wa-card p-4">
    <h4 class="mb-3">Security Settings</h4>

    @if (session('status'))
      <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
      <div class="alert alert-danger">
        <ul class="mb-0">
          @foreach ($errors->all() as $e)
            <li>{{ $e }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('settings.security.update') }}">
      @csrf

      <div class="mb-3">
        <label class="form-label">Email (optional)</label>
        <input type="email" name="email" class="form-control" value="{{ old('email', auth()->user()->email) }}" placeholder="name@example.com">
        <div class="form-text">Add an email to enable email-based security and recovery.</div>
      </div>

      <div class="mb-3">
        <label class="form-label">New Password (optional)</label>
        <input type="password" name="password" class="form-control" autocomplete="new-password" placeholder="********">
        <div class="form-text">Minimum 8 characters.</div>
      </div>

      <div class="mb-3">
        <label class="form-label">Confirm New Password</label>
        <input type="password" name="password_confirmation" class="form-control" autocomplete="new-password" placeholder="********">
      </div>

      <button type="submit" class="btn btn-wa">Save</button>
    </form>
  </div>
</div>
@endsection
