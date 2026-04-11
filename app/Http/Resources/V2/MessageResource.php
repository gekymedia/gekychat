<?php

namespace App\Http\Resources\V2;

use App\Http\Resources\MessageResource as V1MessageResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * V2 aliases on top of {@see V1MessageResource} so list/detail payloads stay consistent
 * while older clients can keep using `content` and Unix `timestamp`.
 */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $base = (new V1MessageResource($this->resource))->toArray($request);

        $timestamp = null;
        if (! empty($base['created_at']) && is_string($base['created_at'])) {
            $timestamp = strtotime($base['created_at']);
        } elseif ($this->resource->created_at) {
            $timestamp = $this->resource->created_at->timestamp;
        }

        return array_merge($base, [
            'content' => $base['body'] ?? '',
            'timestamp' => $timestamp,
            'is_edited' => ! empty($base['edited_at']),
        ]);
    }
}
