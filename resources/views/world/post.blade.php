@extends('layouts.app')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/world-video-player.css') }}">
<link rel="stylesheet" href="{{ asset('css/world-comments.css') }}">
@endsection

@section('content')
<div class="world-post-page">
    <div class="world-video-container">
        <video class="world-feed-video" src="{{ $post->video_url }}" playsinline></video>
    </div>
    
    <div class="world-comments-container" id="comments-container">
        <div class="comments-list"></div>
        
        <div class="reply-indicator"></div>
        
        <div class="comment-input-container">
            <textarea 
                class="comment-input" 
                placeholder="Add a comment..."
                rows="1"
            ></textarea>
            <button class="comment-submit-btn">
                <i class="material-icons">send</i>
            </button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('js/world-video-player.js') }}"></script>
<script src="{{ asset('js/world-comments.js') }}"></script>
<script>
    const commentsManager = initWorldComments({{ $post->id }}, 'comments-container');
</script>
@endsection
