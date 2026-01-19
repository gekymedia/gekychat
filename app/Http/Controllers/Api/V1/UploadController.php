<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Jobs\CompressImage;
use App\Jobs\CompressVideo;
use Illuminate\Http\Request;

/**
 * MEDIA COMPRESSION: Note - This controller uses the /upload endpoint
 * Compression jobs are automatically queued after upload
 */

class UploadController extends Controller
{
    public function store(Request $r)
    {
        $r->validate([
            'files'=>'required|array|min:1|max:10',
            'files.*'=>'file|mimes:jpg,jpeg,png,gif,webp,pdf,zip,doc,docx,mp4,mp3,mov,wav,m4a|max:10240',
            'compression_level' => 'nullable|in:low,medium,high,none', // MEDIA COMPRESSION: User preference (none = skip compression)
        ]);

        // Get compression level from request (default: medium)
        $compressionLevel = $r->input('compression_level', 'medium');

        $out = [];
        foreach ($r->file('files') as $file) {
            $path = $file->store('attachments', 'public');
            $att = Attachment::create([
                'user_id' => $r->user()->id,
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'compression_status' => 'pending', // MEDIA COMPRESSION: Mark as pending
                'compression_level' => $compressionLevel,
            ]);

            // MEDIA COMPRESSION: Queue compression job
            $this->queueCompression($att);

            $out[] = [
                'id'=>$att->id,
                'url'=>$att->url,
                'mime_type'=>$att->mime_type,
                'is_image'=>$att->is_image,
                'is_video'=>$att->is_video,
                'is_document'=>$att->is_document,
                'compression_status'=>$att->compression_status, // TODO: Frontend should poll for completion
            ];
        }
        return response()->json(['data'=>$out], 201);
    }

    /**
     * MEDIA COMPRESSION: Queue appropriate compression job based on file type
     * Only compresses if compression_level is not 'none'
     */
    private function queueCompression(Attachment $attachment): void
    {
        // Don't compress if compression_level is 'none' (e.g., for voice messages/audio files)
        if ($attachment->compression_level === 'none') {
            return;
        }
        
        if ($attachment->is_image) {
            CompressImage::dispatch($attachment);
        } elseif ($attachment->is_video) {
            CompressVideo::dispatch($attachment);
        }
        // Documents and audio files are not compressed
    }
}
