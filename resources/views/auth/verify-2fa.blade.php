{{--
    Simple two‑factor authentication (2FA) verification page.
    When a user has enabled 2FA, they are redirected here to enter
    the 6‑digit code sent to their email or phone. The form posts
    back to the `verify.2fa` route defined in your routes/web.php.
--}}

@extends('layouts.app')

@section('title', 'Two‑Factor Verification')

@section('content')
<div class="container py-5" style="max-width: 480px;">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-card border-bottom border-border">
            <h1 class="h5 mb-0">Verify Two‑Factor Code</h1>
        </div>
        <div class="card-body bg-bg">
            {{-- Session status --}}
            @if(session('status'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('status') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            {{-- Display validation errors --}}
            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <p class="text-muted mb-4">Enter the 6‑digit verification code sent to your email address.</p>

            <form method="POST" action="{{ route('verify.2fa') }}">
                @csrf
                <div class="mb-3">
                    <label for="two_factor_code" class="form-label">Verification Code</label>
                    <input type="text" id="two_factor_code" name="two_factor_code" class="form-control" maxlength="6" pattern="\d{6}" required autofocus>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <button type="submit" class="btn btn-wa">Verify</button>
                    <a href="{{ route('resend.2fa') }}" class="btn btn-link p-0">Resend Code</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection