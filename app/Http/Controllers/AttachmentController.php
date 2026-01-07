<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Jobs\CompressImage;
use App\Jobs\CompressVideo;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;

class AttachmentController extends Controller
{
    public function upload(Request $r) {
        $r->validate(['file' => 'required|file|max:25600']); // 25MB
        $f = $r->file('file');

        $path = $f->store('attachments/'.date('Y/m/d'), 'public');

        // Get compression level from request (default: medium)
        $compressionLevel = $r->input('compression_level', 'medium');

        $att = Attachment::create([
            'user_id' => $r->user()->id ?? null,
            'file_path' => $path,
            'original_name' => $f->getClientOriginalName(),
            'mime_type' => $f->getMimeType(),
            'size' => $f->getSize(),
            'compression_status' => 'pending', // MEDIA COMPRESSION: Mark as pending
            'compression_level' => $compressionLevel,
        ]);

        // MEDIA COMPRESSION: Queue compression job
        $this->queueCompression($att);

        // Generate thumbnail for images if needed (optional, can be done later)
        // The url accessor in Attachment model will handle URL generation

        return ApiResponse::data($att);
    }

    /**
     * MEDIA COMPRESSION: Get attachment details (for checking compression status)
     * GET /api/v1/attachments/{id}
     */
    public function show(Request $request, $id)
    {
        $attachment = Attachment::findOrFail($id);
        
        // TODO: Add permission check - user should only see their own attachments
        // or attachments in conversations/groups they're part of
        
        return ApiResponse::data($attachment);
    }

    /**
     * MEDIA COMPRESSION: Queue appropriate compression job based on file type
     */
    private function queueCompression(Attachment $attachment): void
    {
        if ($attachment->is_image) {
            CompressImage::dispatch($attachment);
        } elseif ($attachment->is_video) {
            CompressVideo::dispatch($attachment);
        }
        // Documents and audio files are not compressed
    }
}
