<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'file_path',
        'original_name',
        'mime_type',
        'shared_as_document', // Flag to indicate if file was shared as document (WhatsApp-style)
        'is_voicenote', // Flag to indicate if this is a voice note/message
        'size',
        'attachable_id',
        'attachable_type',
        // MEDIA COMPRESSION fields
        'compression_status',
        'compressed_file_path',
        'thumbnail_path',
        'original_size',
        'compressed_size',
        'compression_level',
        'compression_error',
    ];

    protected $casts = [
        'size' => 'integer',
        'original_size' => 'integer',
        'compressed_size' => 'integer',
        'shared_as_document' => 'boolean',
        'is_voicenote' => 'boolean',
    ];

    protected $appends = [
        'url',
        'is_image',
        'is_video',
        'is_audio',
        'is_document',
        // Note: is_voicenote and shared_as_document are database columns (not accessors)
        // They're automatically included in JSON via $casts
        // MEDIA COMPRESSION: Add compression URLs
        'compressed_url',
        'thumbnail_url',
    ];

    /* =========================
     * Relationships
     * =========================*/
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /* =========================
     * Accessors
     * =========================*/

    /**
     * Public URL that works for local 'public' disk and cloud disks.
     * Assumes you store with: store('attachments', 'public')
     */
    public function getUrlAttribute(): string
    {
        if (!$this->file_path) return '';
        // Prefer the same disk you used for storing (public)
        try {
            return \App\Helpers\UrlHelper::secureStorageUrl($this->file_path, 'public');
        } catch (\Throwable $e) {
            // Fallback for legacy code paths
            return \App\Helpers\UrlHelper::secureAsset('storage/' . ltrim($this->file_path, '/'));
        }
    }

    /**
     * MEDIA COMPRESSION: Get compressed file URL (if available)
     */
    public function getCompressedUrlAttribute(): ?string
    {
        if (!$this->compressed_file_path) return null;
        try {
            return \App\Helpers\UrlHelper::secureStorageUrl($this->compressed_file_path, 'public');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * MEDIA COMPRESSION: Get thumbnail URL (if available)
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        if (!$this->thumbnail_path) return null;
        try {
            return \App\Helpers\UrlHelper::secureStorageUrl($this->thumbnail_path, 'public');
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getIsImageAttribute(): bool
    {
        // If explicitly marked as shared as document, it's NOT an image (it's a document)
        // This allows images shared as documents to be displayed as documents (WhatsApp-style)
        if ($this->shared_as_document) {
            return false;
        }
        
        $mime = (string) $this->mime_type;
        if ($mime === '') return $this->guessFromExtension(['jpg','jpeg','png','gif','webp','svg']);
        return Str::startsWith($mime, 'image/');
    }

    public function getIsVideoAttribute(): bool
    {
        // If explicitly marked as shared as document, it's NOT a video (it's a document)
        // This allows videos shared as documents to be displayed as documents (WhatsApp-style)
        if ($this->shared_as_document) {
            return false;
        }
        
        $mime = (string) $this->mime_type;
        if ($mime === '') return $this->guessFromExtension(['mp4','mov','webm','mkv','avi']);
        
        // Check if it's audio first (audio files can have video/mp4 MIME type but should be treated as audio)
        // M4A files can have MIME type video/mp4 or audio/mp4, but they're audio files
        $extension = strtolower(pathinfo($this->original_name ?? '', PATHINFO_EXTENSION));
        if (in_array($extension, ['m4a', 'aac', 'mp3', 'wav', 'ogg', 'flac'])) {
            return false; // It's audio, not video
        }
        
        return Str::startsWith($mime, 'video/');
    }

    public function getIsAudioAttribute(): bool
    {
        // If explicitly marked as shared as document, it's NOT audio (it's a document)
        // This allows audio files shared as documents to be displayed as documents
        if ($this->shared_as_document) {
            return false;
        }
        
        $mime = (string) $this->mime_type;
        if ($mime === '') return $this->guessFromExtension(['mp3','wav','aac','m4a','ogg','flac']);
        
        // Check extension first (more reliable for m4a files which can have video/mp4 MIME type)
        $extension = strtolower(pathinfo($this->original_name ?? '', PATHINFO_EXTENSION));
        if (in_array($extension, ['m4a', 'aac', 'mp3', 'wav', 'ogg', 'flac'])) {
            return true; // It's definitely audio
        }
        
        return Str::startsWith($mime, 'audio/');
    }

    public function getIsDocumentAttribute(): bool
    {
        // If explicitly marked as shared as document (WhatsApp-style), treat as document
        // This allows images shared as documents to be displayed as documents
        if ($this->shared_as_document) {
            return true;
        }
        
        $mime = (string) $this->mime_type;

        // Common doc types
        $docMimes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'text/csv',
        ];

        if ($mime === '') {
            return $this->guessFromExtension(['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','csv']);
        }

        return in_array($mime, $docMimes, true)
            || Str::startsWith($mime, 'text/'); // allow other text/* like text/markdown
    }

    /* =========================
     * Helpers
     * =========================*/

    public function humanReadableSize(int $precision = 1): string
    {
        $size = (int) ($this->size ?? 0);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        if ($size <= 0) return '0 B';
        $power = (int) floor(log($size, 1024));
        $power = max(0, min($power, count($units) - 1));
        return round($size / (1024 ** $power), $precision) . ' ' . $units[$power];
    }

    public function icon(): string
    {
        $mime = (string) $this->mime_type;

        return match (true) {
            $this->is_image                         => 'bi-image',
            $this->is_video                         => 'bi-film',
            $this->is_audio                         => 'bi-music-note-beamed',
            $mime === 'application/pdf'             => 'bi-file-earmark-pdf',
            Str::contains($mime, 'word')            => 'bi-file-earmark-word',
            Str::contains($mime, 'excel')           => 'bi-file-earmark-excel',
            Str::contains($mime, 'powerpoint')      => 'bi-file-earmark-ppt',
            Str::contains($mime, ['zip','compressed','gzip']) => 'bi-file-earmark-zip',
            default                                 => 'bi-file-earmark',
        };
    }

    /**
     * Lightweight extension guesser to support legacy rows with empty mime_type.
     */
    protected function guessFromExtension(array $exts): bool
    {
        $ext = strtolower(pathinfo((string)$this->file_path, PATHINFO_EXTENSION));
        return $ext !== '' && in_array($ext, $exts, true);
    }

    /* =========================
     * Model Events
     * =========================*/

    protected static function booted(): void
    {
        static::deleting(function (Attachment $a) {
            // Best effort file cleanup; ignore if already gone.
            if ($a->file_path) {
                try {
                    Storage::disk('public')->delete($a->file_path);
                } catch (\Throwable $e) {
                    // swallow
                }
            }
        });
    }
}
