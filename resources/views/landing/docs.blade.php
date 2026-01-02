@extends('layouts.public')

@section('title', 'Documentation - GekyChat')

@section('content')
<div class="container py-5">
    <div class="row">
        <div class="col-12">
            <h1 class="text-center mb-5">GekyChat Documentation</h1>
            <p class="text-center text-muted mb-5">Coming soon - API and integration documentation</p>
            <div class="text-center">
                <a href="{{ route('landing.index') }}" class="btn btn-wa">Back to Home</a>
            </div>
        </div>
    </div>
</div>
@endsection

