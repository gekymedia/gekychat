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
        // Normalize is_voicenote / shared_as_document (client may send as scalar or array; ensure boolean-like value)
        $isVoicenoteInput = $r->input('is_voicenote');
        if (is_array($isVoicenoteInput)) {
            $isVoicenoteInput = $isVoicenoteInput[0] ?? false;
        }
        $sharedAsDocInput = $r->input('shared_as_document');
        if (is_array($sharedAsDocInput)) {
            $sharedAsDocInput = $sharedAsDocInput[0] ?? false;
        }
        $r->merge([
            'is_voicenote' => $isVoicenoteInput,
            'shared_as_document' => $sharedAsDocInput,
        ]);
        $r->validate([
            'file' => 'required|file|max:25600', // 25MB
            'is_voicenote' => 'sometimes|boolean',
            'shared_as_document' => 'sometimes|boolean',
            'compression_level' => 'sometimes|string|in:none,low,medium,high',
        ]);
        $f = $r->file('file');

        $path = $f->store('attachments/'.date('Y/m/d'), 'public');

        // Get compression level from request (default: medium)
        $isVoicenote = (bool) $r->boolean('is_voicenote', false);
        $sharedAsDocument = (bool) $r->boolean('shared_as_document', false);
        $compressionLevel = $r->input('compression_level', 'medium');
        if ($isVoicenote) {
            // Voice notes should never be compressed.
            $compressionLevel = 'none';
        }

        // Prefer client-provided mime type (more reliable on mobile, especially for .m4a),
        // fallback to server guess.
        $clientMime = $f->getClientMimeType();
        $serverMime = $f->getMimeType();
        $mimeType = $clientMime ?: ($serverMime ?: 'application/octet-stream');

        $att = Attachment::create([
            'user_id' => $r->user()->id ?? null,
            'file_path' => $path,
            'original_name' => $f->getClientOriginalName(),
            'mime_type' => $mimeType,
            'size' => $f->getSize(),
            'shared_as_document' => $sharedAsDocument,
            'is_voicenote' => $isVoicenote,
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
