{{--
    Minimal contact creation form used when a number is not found or when
    manually adding a contact. The form posts to the contacts.store route.
    You can expand this page to include additional fields as required.
--}}

@extends('layouts.app')

@section('title', 'Add Contact')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-card border-bottom border-border">
                    <h1 class="h5 mb-0">New Contact</h1>
                </div>
                <div class="card-body bg-bg">
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

                    <form method="POST" action="{{ route('contacts.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" id="name" name="name" class="form-control" value="{{ old('name') }}" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-control" value="{{ old('phone') }}" required>
                        </div>
                        <div class="mb-3">
                            <label for="note" class="form-label">Note (optional)</label>
                            <textarea id="note" name="note" class="form-control" rows="3">{{ old('note') }}</textarea>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="favorite" name="favorite" value="1" {{ old('favorite') ? 'checked' : '' }}>
                            <label class="form-check-label" for="favorite">Mark as favourite</label>
                        </div>
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('contacts.index') }}" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-wa">Save Contact</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection