@extends('layouts.app')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/world-search.css') }}">
@endsection

@section('content')
<div class="world-search-page">
    <div class="world-search-container">
        <input 
            type="text" 
            id="world-search-input" 
            placeholder="Search videos, users, hashtags..."
            autofocus
        >
        <div id="world-search-suggestions"></div>
    </div>
    
    <div id="world-search-results"></div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('js/world-search.js') }}"></script>
@endsection
