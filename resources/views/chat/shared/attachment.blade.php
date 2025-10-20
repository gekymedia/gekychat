{{-- resources/views/chat/shared/attachment.blade.php --}}
@php
// ADD THESE HELPER FUNCTIONS AT THE TOP OF THE FILE
if (!function_exists('formatFileSize')) {
    function formatFileSize($bytes) {
        if ($bytes == 0) return '0 Bytes';
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
}

if (!function_exists('getFileIcon')) {
    function getFileIcon($mimeType = null) {
        if (!$mimeType) return 'bi-file-earmark';
        $icons = [
            'pdf' => 'bi-file-pdf-fill', 
            'doc' => 'bi-file-word-fill', 
            'docx' => 'bi-file-word-fill',
            'xls' => 'bi-file-excel-fill', 
            'xlsx' => 'bi-file-excel-fill', 
            'ppt' => 'bi-file-ppt-fill',
            'pptx' => 'bi-file-ppt-fill', 
            'zip' => 'bi-file-zip-fill', 
            'rar' => 'bi-file-zip-fill',
            'txt' => 'bi-file-text-fill', 
            'csv' => 'bi-file-text-fill', 
            'default' => 'bi-file-earmark'
        ];
        
        if (!$mimeType) return $icons['default'];
        
        $type = strtolower($mimeType);
        
        if (str_contains($type, 'pdf')) return $icons['pdf'];
        if (str_contains($type, 'word') || str_contains($type, 'document')) return $icons['doc'];
        if (str_contains($type, 'excel') || str_contains($type, 'spreadsheet')) return $icons['xls'];
        if (str_contains($type, 'powerpoint') || str_contains($type, 'presentation')) return $icons['ppt'];
        if (str_contains($type, 'zip') || str_contains($type, 'compressed')) return $icons['zip'];
        if (str_contains($type, 'text') || str_contains($type, 'csv')) return $icons['txt'];
        
        return $icons['default'];
    }
}


  use Illuminate\Support\Facades\Storage;
  use Illuminate\Support\Str;

  // Handle both variable names: $file (old) and $attachment (new)
  $attachment = $attachment ?? $file ?? null;
  if (!$attachment) return;

  // Get file properties with fallbacks
  $filePath = $attachment->file_path ?? $attachment->path ?? null;
  $fileName = $attachment->original_name ?? $attachment->file_name ?? $attachment->name ?? 'file';
  $fileSize = $attachment->file_size ?? $attachment->size ?? null;
  $mimeType = $attachment->mime_type ?? $attachment->type ?? null;
  
  // Generate URL - handle both Storage::url and direct URLs
  if (isset($attachment->url)) {
    $fileUrl = $attachment->url;
  } elseif ($filePath && Storage::exists($filePath)) {
    $fileUrl = Storage::url($filePath);
  } else {
    $fileUrl = $filePath; // Fallback to direct path
  }
  
  // Determine file type
  $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
  $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp','bmp','svg']);
  $isVideo = in_array($ext, ['mp4','mov','avi','webm','mkv','flv']);
  $isAudio = in_array($ext, ['mp3','wav','ogg','aac','flac','m4a']);
  $isDocument = !$isImage && !$isVideo && !$isAudio;
  
  // Format file size for display
  $formattedSize = $fileSize ? formatFileSize($fileSize) : null;
@endphp

<div class="attachment-item" data-file-type="{{ $ext }}" data-file-name="{{ $fileName }}">
  @if($isImage)
    {{-- Image Attachment with Modal Support --}}
    <div class="image-attachment position-relative">
      <img src="{{ $fileUrl }}" 
           alt="{{ $fileName }}" 
           class="img-fluid rounded media-img cursor-pointer"
           loading="lazy"
           data-bs-toggle="modal" 
           data-bs-target="#imageModal"
           data-image-src="{{ $fileUrl }}"
           data-file-name="{{ $fileName }}"
           style="max-width: 300px; max-height: 300px; object-fit: cover;"
           onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
      
      {{-- Fallback for broken images --}}
      <div class="image-fallback d-none align-items-center justify-content-center bg-secondary rounded text-white p-3"
           style="max-width: 300px; height: 150px;">
        <div class="text-center">
          <i class="bi bi-image display-6 d-block mb-2"></i>
          <small>Image not available</small>
        </div>
      </div>
    </div>

  @elseif($isVideo)
    {{-- Video Attachment --}}
    <div class="video-attachment position-relative">
      <video controls 
             class="img-fluid rounded media-video"
             preload="metadata"
             style="max-width: 300px; max-height: 300px;"
             poster="{{ $attachment->thumbnail_url ?? '' }}"
             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
        <source src="{{ $fileUrl }}" type="video/{{ $ext }}">
        Your browser does not support the video tag.
      </video>
      
      {{-- Fallback for unsupported video --}}
      <div class="video-fallback d-none align-items-center justify-content-center bg-secondary rounded text-white p-3"
           style="max-width: 300px; height: 150px;">
        <div class="text-center">
          <i class="bi bi-film display-6 d-block mb-2"></i>
          <small>Video not supported</small>
          <br>
          <a href="{{ $fileUrl }}" download="{{ $fileName }}" class="btn btn-sm btn-light mt-2">
            <i class="bi bi-download me-1"></i> Download
          </a>
        </div>
      </div>
      
      {{-- Video info overlay --}}
      <div class="video-info position-absolute bottom-0 start-0 end-0 bg-dark bg-opacity-75 text-white p-2 rounded-bottom">
        <small class="d-flex justify-content-between align-items-center">
          <span class="text-truncate me-2">{{ $fileName }}</span>
          @if($formattedSize)
            <span>{{ $formattedSize }}</span>
          @endif
        </small>
      </div>
    </div>

  @elseif($isAudio)
    {{-- Audio Attachment --}}
    <div class="audio-attachment bg-light rounded p-3" style="max-width: 300px;">
      <div class="d-flex align-items-center gap-3">
        <div class="audio-icon text-primary">
          <i class="bi bi-file-earmark-music display-6"></i>
        </div>
        <div class="audio-info flex-grow-1">
          <div class="file-name fw-medium text-truncate" style="max-width: 180px;">
            {{ $fileName }}
          </div>
          @if($formattedSize)
            <div class="file-size text-muted small">{{ $formattedSize }}</div>
          @endif
        </div>
        <audio controls class="audio-player" style="width: 200px; height: 40px;">
          <source src="{{ $fileUrl }}" type="audio/{{ $ext }}">
          Your browser does not support the audio element.
        </audio>
      </div>
    </div>

  @else
    {{-- Document Attachment --}}
    <div class="document-attachment bg-light rounded p-3 d-flex align-items-center gap-3" 
         style="max-width: 300px;">
      <div class="file-icon text-primary">
        <i class="bi {{ getFileIcon($mimeType ?? $ext) }} display-6"></i>
      </div>
      <div class="file-info flex-grow-1 min-width-0">
        <div class="file-name fw-medium text-truncate" title="{{ $fileName }}">
          {{ $fileName }}
        </div>
        @if($formattedSize)
          <div class="file-size text-muted small">{{ $formattedSize }}</div>
        @endif
        <div class="file-type text-muted small text-uppercase">
          {{ strtoupper($ext) }} File
        </div>
      </div>
      <a href="{{ $fileUrl }}" 
         download="{{ $fileName }}" 
         class="btn btn-wa btn-sm flex-shrink-0"
         title="Download {{ $fileName }}">
        <i class="bi bi-download"></i>
      </a>
    </div>
  @endif
</div>

<style>
.attachment-item {
  margin: 4px 0;
}

.cursor-pointer {
  cursor: pointer;
}

.media-img:hover {
  opacity: 0.9;
  transform: scale(1.02);
  transition: all 0.2s ease;
}

.video-info, .audio-info, .file-info {
  min-width: 0;
}

.audio-player::-webkit-media-controls-panel {
  background: var(--card);
}

.audio-player::-webkit-media-controls-current-time-display,
.audio-player::-webkit-media-controls-time-remaining-display {
  color: var(--text);
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .media-img, .media-video {
    max-width: 250px !important;
    max-height: 250px !important;
  }
  
  .document-attachment, .audio-attachment {
    max-width: 280px !important;
  }
}

@media (max-width: 576px) {
  .media-img, .media-video {
    max-width: 200px !important;
    max-height: 200px !important;
  }
  
  .document-attachment, .audio-attachment {
    max-width: 260px !important;
  }
  
  .audio-player {
    width: 150px !important;
  }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Handle image loading errors
  document.querySelectorAll('.media-img').forEach(img => {
    img.addEventListener('error', function() {
      this.style.display = 'none';
      const fallback = this.nextElementSibling;
      if (fallback && fallback.classList.contains('image-fallback')) {
        fallback.classList.remove('d-none');
        fallback.classList.add('d-flex');
      }
    });
  });
  
  // Handle video loading errors
  document.querySelectorAll('.media-video').forEach(video => {
    video.addEventListener('error', function() {
      this.style.display = 'none';
      const fallback = this.nextElementSibling;
      if (fallback && fallback.classList.contains('video-fallback')) {
        fallback.classList.remove('d-none');
        fallback.classList.add('d-flex');
      }
    });
  });
  
  // Handle audio loading errors
  document.querySelectorAll('.audio-player').forEach(audio => {
    audio.addEventListener('error', function() {
      const container = this.closest('.audio-attachment');
      if (container) {
        const downloadBtn = document.createElement('a');
        downloadBtn.href = this.querySelector('source')?.src || '#';
        downloadBtn.download = this.closest('.attachment-item')?.dataset.fileName || 'audio';
        downloadBtn.className = 'btn btn-wa btn-sm ms-2';
        downloadBtn.innerHTML = '<i class="bi bi-download me-1"></i>Download';
        this.parentNode.appendChild(downloadBtn);
        this.style.display = 'none';
      }
    });
  });
});
</script>