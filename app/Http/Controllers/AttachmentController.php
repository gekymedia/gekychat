<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
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

        $att = Attachment::create([
            'user_id' => $r->user()->id ?? null,
            'file_path' => $path,
            'original_name' => $f->getClientOriginalName(),
            'mime_type' => $f->getMimeType(),
            'size' => $f->getSize(),
        ]);

        // Generate thumbnail for images if needed (optional, can be done later)
        // The url accessor in Attachment model will handle URL generation

        return ApiResponse::data($att);
    }
}
