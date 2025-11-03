{{--
    View shown when a user attempts to initiate a chat with a phone number
    that does not belong to any registered user. It provides options to
    return to contacts or to add the number as a new contact. The
    controller supplies `$phone` and optionally uses `config('app.name')` for
    display. Feel free to customize this page with invitations or other
    onboarding prompts.
--}}

@extends('layouts.app')

@section('title', 'Unknown Number')

@section('content')
<div class="container py-5" style="max-width: 600px;">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-card border-bottom border-border">
            <h1 class="h5 mb-0">Unknown Contact</h1>
        </div>
        <div class="card-body bg-bg">
            <p class="mb-4">The phone number <strong>{{ $phone }}</strong> is not associated with an existing user on {{ config('app.name', 'GekyChat') }}.</p>
            <p class="text-muted">You can invite this person to join or save their details in your contacts for future reference.</p>
            <div class="d-flex gap-3 flex-wrap">
                {{-- Back to contacts list --}}
                <a href="{{ route('contacts.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back to Contacts
                </a>
                {{-- Add to contacts form --}}
                <a href="{{ route('contacts.create') }}" class="btn btn-wa">
                    <i class="bi bi-person-plus me-1"></i> Add to Contacts
                </a>
            </div>
        </div>
    </div>
</div>
@endsection