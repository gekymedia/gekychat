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
        
        $receivedMessageIds = collect([]);
        if ($userConversations->isNotEmpty()) {
            try {
                $receivedMessageIds = \App\Models\Message::whereIn('conversation_id', $userConversations)
                    ->where('sender_id', '!=', $user->id)
                    ->pluck('id');
            } catch (\Exception $e) {
                \Log::warning('Error fetching received messages for storage usage: ' . $e->getMessage());
            }
        }
        
        // Get attachments from groups user is in
        $userGroups = collect([]);
        try {
            $userGroups = \App\Models\Group::whereHas('members', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            })->pluck('id');
        } catch (\Exception $e) {
            \Log::warning('Error fetching user groups for storage usage: ' . $e->getMessage());
        }
        
        $groupReceivedMessageIds = collect([]);
        if ($userGroups->isNotEmpty()) {
            try {
                $groupReceivedMessageIds = \App\Models\GroupMessage::whereIn('group_id', $userGroups)
                    ->where('sender_id', '!=', $user->id)
                    ->pluck('id');
            } catch (\Exception $e) {
                \Log::warning('Error fetching group messages for storage usage: ' . $e->getMessage());
            }
        }

        $allMessageIds = $sentMessageIds->merge($receivedMessageIds)->unique();
        $allGroupMessageIds = $groupMessageIds->merge($groupReceivedMessageIds)->unique();

        // Handle empty arrays to avoid SQL errors
        if ($allMessageIds->isEmpty() && $allGroupMessageIds->isEmpty()) {
            $attachments = collect([]);
        } else {
            $attachments = Attachment::where(function ($q) use ($allMessageIds, $allGroupMessageIds) {
                if ($allMessageIds->isNotEmpty()) {
                    $q->where('attachable_type', \App\Models\Message::class)
                      ->whereIn('attachable_id', $allMessageIds);
                }
                if ($allGroupMessageIds->isNotEmpty()) {
                    if ($allMessageIds->isNotEmpty()) {
                        $q->orWhere(function($subQ) use ($allGroupMessageIds) {
                            $subQ->where('attachable_type', \App\Models\GroupMessage::class)
                                 ->whereIn('attachable_id', $allGroupMessageIds);
                        });
                    } else {
                        $q->where('attachable_type', \App\Models\GroupMessage::class)
                          ->whereIn('attachable_id', $allGroupMessageIds);
                    }
                }
            })->get();
        }

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

