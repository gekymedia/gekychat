@php
    $url = $file->url ?? (Storage::url($file->file_path));
    $ext = strtolower(pathinfo($file->file_path, PATHINFO_EXTENSION));
    $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp']);
    $isVideo = in_array($ext, ['mp4','mov','avi','webm']);
    $fileName = $file->original_name ?? basename($file->file_path);
    $fileSize = $file->file_size ? round($file->file_size / 1024) : null;
@endphp

@if($isImage)
    <div class="mt-2">
        <img data-src="{{ $url }}" alt="Shared image" class="img-fluid rounded media-img" 
             loading="lazy" data-bs-toggle="modal" data-bs-target="#imageModal" 
             data-image-src="{{ $url }}" width="220" height="220">
    </div>
@elseif($isVideo)
    <div class="mt-2">
        <video controls class="img-fluid rounded media-video" preload="metadata"
               data-src="{{ $url }}" style="max-width: 220px;">
            <source src="{{ $url }}" type="video/{{ $ext }}">
            Your browser does not support the video tag.
        </video>
    </div>
@else
    <div class="mt-2">
        <a href="{{ $url }}" target="_blank" class="d-inline-flex align-items-center doc-link" 
           rel="noopener noreferrer" download="{{ $fileName }}">
            <i class="bi bi-file-earmark me-1" aria-hidden="true"></i>
            <span class="text-truncate" style="max-width: 200px;">
                {{ $fileName }}
            </span>
            @if($fileSize)
                <small class="text-muted ms-2">({{ $fileSize }} KB)</small>
            @endif
        </a>
    </div>
@endif