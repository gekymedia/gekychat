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

        $path = $f->store('uploads/'.date('Y/m/d'), 'public');

        $meta = [
            'url'   => Storage::disk('public')->url($path),
            'mime'  => $f->getMimeType(),
            'bytes' => $f->getSize(),
        ];

        if (str_starts_with($meta['mime'], 'image/')) {
            $img = Image::make($f->getRealPath())->resize(512, null, function($c){ $c->aspectRatio(); })->encode('jpg', 80);
            $thumbPath = 'thumbs/'.basename($path).'.jpg';
            Storage::disk('public')->put($thumbPath, (string)$img);
            $meta['thumbnail_url'] = Storage::disk('public')->url($thumbPath);
        }

        $att = Attachment::create([
            'type' => str_starts_with($meta['mime'], 'image/') ? 'image' : 'other',
            'url'  => $meta['url'],
            'thumbnail_url' => $meta['thumbnail_url'] ?? null,
            'mime' => $meta['mime'],
            'bytes'=> $meta['bytes'],
        ]);

        return ApiResponse::data($att);
    }
}
