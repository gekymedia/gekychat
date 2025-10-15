<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function store(Request $r)
    {
        $r->validate([
            'files'=>'required|array|min:1|max:10',
            'files.*'=>'file|mimes:jpg,jpeg,png,gif,webp,pdf,zip,doc,docx,mp4,mp3,mov,wav|max:10240'
        ]);

        $out = [];
        foreach ($r->file('files') as $file) {
            $path = $file->store('attachments', 'public');
            $att = Attachment::create([
                'user_id' => $r->user()->id,
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
            $out[] = [
                'id'=>$att->id,
                'url'=>$att->url,
                'mime_type'=>$att->mime_type,
                'is_image'=>$att->is_image,
                'is_video'=>$att->is_video,
                'is_document'=>$att->is_document,
            ];
        }
        return response()->json(['data'=>$out], 201);
    }
}
