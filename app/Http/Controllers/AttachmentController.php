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
            // Attachable fields are set to null initially, will be linked to message later
            'attachable_id' => null,
            'attachable_type' => null,
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
        $user = $request->user();
        
        // Check if user is the uploader
        if ($attachment->user_id === $user->id) {
            return ApiResponse::data($attachment);
        }
        
        // Check if attachment belongs to a message in a conversation/group the user is part of
        if ($attachment->attachable_type === 'App\Models\Message') {
            $message = \App\Models\Message::find($attachment->attachable_id);
            if ($message && $message->conversation_id) {
                $conversation = \App\Models\Conversation::find($message->conversation_id);
                if ($conversation && $conversation->isParticipant($user->id)) {
                    return ApiResponse::data($attachment);
                }
            }
        } elseif ($attachment->attachable_type === 'App\Models\GroupMessage') {
            $groupMessage = \App\Models\GroupMessage::find($attachment->attachable_id);
            if ($groupMessage && $groupMessage->group_id) {
                $group = \App\Models\Group::find($groupMessage->group_id);
                if ($group && $group->isMember($user)) {
                    return ApiResponse::data($attachment);
                }
            }
        }
        
        // If no permission, return 403
        abort(403, 'You do not have permission to view this attachment');
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
