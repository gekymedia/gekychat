<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Attachment;

class StorageUsageController extends Controller
{
    /**
     * Get storage usage breakdown.
     * GET /storage-usage
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Get all attachments from user's sent messages and received messages
        $sentMessageIds = \App\Models\Message::where('sender_id', $user->id)->pluck('id');
        $groupMessageIds = \App\Models\GroupMessage::where('sender_id', $user->id)->pluck('id');
        
        // Get attachments from conversations user is in
        $userConversations = \App\Models\Conversation::where(function ($q) use ($user) {
            $q->where('user_one_id', $user->id)->orWhere('user_two_id', $user->id);
        })->pluck('id');
        
        $receivedMessageIds = \App\Models\Message::whereIn('conversation_id', $userConversations)
            ->where('sender_id', '!=', $user->id)
            ->pluck('id');
        
        // Get attachments from groups user is in
        $userGroups = \App\Models\Group::whereHas('members', function ($q) use ($user) {
            $q->where('users.id', $user->id);
        })->pluck('id');
        
        $groupReceivedMessageIds = \App\Models\GroupMessage::whereIn('group_id', $userGroups)
            ->where('sender_id', '!=', $user->id)
            ->pluck('id');

        $allMessageIds = $sentMessageIds->merge($receivedMessageIds)->unique();
        $allGroupMessageIds = $groupMessageIds->merge($groupReceivedMessageIds)->unique();

        $attachments = Attachment::where(function ($q) use ($allMessageIds, $allGroupMessageIds) {
            $q->whereIn('message_id', $allMessageIds)
              ->orWhereIn('group_message_id', $allGroupMessageIds);
        })->get();

        $breakdown = [
            'photos' => [
                'count' => 0,
                'size' => 0,
            ],
            'videos' => [
                'count' => 0,
                'size' => 0,
            ],
            'audio' => [
                'count' => 0,
                'size' => 0,
            ],
            'documents' => [
                'count' => 0,
                'size' => 0,
            ],
            'total' => [
                'count' => $attachments->count(),
                'size' => 0,
            ],
        ];

        foreach ($attachments as $attachment) {
            $size = $attachment->size ?? 0;
            $breakdown['total']['size'] += $size;

            if ($attachment->is_image) {
                $breakdown['photos']['count']++;
                $breakdown['photos']['size'] += $size;
            } elseif ($attachment->is_video) {
                $breakdown['videos']['count']++;
                $breakdown['videos']['size'] += $size;
            } elseif ($attachment->is_audio) {
                $breakdown['audio']['count']++;
                $breakdown['audio']['size'] += $size;
            } else {
                $breakdown['documents']['count']++;
                $breakdown['documents']['size'] += $size;
            }
        }

        // Format sizes
        foreach ($breakdown as $key => $data) {
            $breakdown[$key]['size_formatted'] = $this->formatBytes($data['size']);
        }

        return response()->json([
            'data' => $breakdown,
        ]);
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

