<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BroadcastList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BroadcastListController extends Controller
{
    /**
     * Get all broadcast lists for the authenticated user.
     * GET /broadcast-lists
     */
    public function index(Request $request)
    {
        $lists = $request->user()
            ->broadcastLists()
            ->with(['recipients:id,name,phone,avatar_path'])
            ->latest()
            ->get();

        $data = $lists->map(function ($list) {
            return [
                'id' => $list->id,
                'name' => $list->name,
                'description' => $list->description,
                'recipient_count' => $list->recipients()->count(),
                'recipients' => $list->recipients->map(function ($recipient) {
                    return [
                        'id' => $recipient->id,
                        'name' => $recipient->name,
                        'phone' => $recipient->phone,
                        'avatar_url' => $recipient->avatar_path 
                            ? asset('storage/' . $recipient->avatar_path) 
                            : null,
                    ];
                }),
                'created_at' => $list->created_at->toIso8601String(),
                'updated_at' => $list->updated_at->toIso8601String(),
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Create a new broadcast list.
     * POST /broadcast-lists
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'exists:users,id',
        ]);

        return DB::transaction(function () use ($request, $data) {
            $list = BroadcastList::create([
                'user_id' => $request->user()->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            $list->recipients()->attach($data['recipients']);

            $list->load(['recipients:id,name,phone,avatar_path']);

            return response()->json([
                'data' => [
                    'id' => $list->id,
                    'name' => $list->name,
                    'description' => $list->description,
                    'recipient_count' => $list->recipients->count(),
                    'recipients' => $list->recipients->map(function ($recipient) {
                        return [
                            'id' => $recipient->id,
                            'name' => $recipient->name,
                            'phone' => $recipient->phone,
                            'avatar_url' => $recipient->avatar_path 
                                ? asset('storage/' . $recipient->avatar_path) 
                                : null,
                        ];
                    }),
                    'created_at' => $list->created_at->toIso8601String(),
                    'updated_at' => $list->updated_at->toIso8601String(),
                ],
            ], 201);
        });
    }

    /**
     * Get a specific broadcast list.
     * GET /broadcast-lists/{id}
     */
    public function show(Request $request, $id)
    {
        $list = $request->user()
            ->broadcastLists()
            ->with(['recipients:id,name,phone,avatar_path'])
            ->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $list->id,
                'name' => $list->name,
                'description' => $list->description,
                'recipient_count' => $list->recipients->count(),
                'recipients' => $list->recipients->map(function ($recipient) {
                    return [
                        'id' => $recipient->id,
                        'name' => $recipient->name,
                        'phone' => $recipient->phone,
                        'avatar_url' => $recipient->avatar_path 
                            ? asset('storage/' . $recipient->avatar_path) 
                            : null,
                    ];
                }),
                'created_at' => $list->created_at->toIso8601String(),
                'updated_at' => $list->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update a broadcast list.
     * PUT /broadcast-lists/{id}
     */
    public function update(Request $request, $id)
    {
        $list = $request->user()->broadcastLists()->findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
            'recipients' => 'sometimes|array|min:1',
            'recipients.*' => 'exists:users,id',
        ]);

        return DB::transaction(function () use ($list, $data) {
            if (isset($data['name'])) {
                $list->name = $data['name'];
            }
            if (isset($data['description'])) {
                $list->description = $data['description'];
            }
            $list->save();

            if (isset($data['recipients'])) {
                $list->recipients()->sync($data['recipients']);
            }

            $list->load(['recipients:id,name,phone,avatar_path']);

            return response()->json([
                'data' => [
                    'id' => $list->id,
                    'name' => $list->name,
                    'description' => $list->description,
                    'recipient_count' => $list->recipients->count(),
                    'recipients' => $list->recipients->map(function ($recipient) {
                        return [
                            'id' => $recipient->id,
                            'name' => $recipient->name,
                            'phone' => $recipient->phone,
                            'avatar_url' => $recipient->avatar_path 
                                ? asset('storage/' . $recipient->avatar_path) 
                                : null,
                        ];
                    }),
                    'created_at' => $list->created_at->toIso8601String(),
                    'updated_at' => $list->updated_at->toIso8601String(),
                ],
            ]);
        });
    }

    /**
     * Delete a broadcast list.
     * DELETE /broadcast-lists/{id}
     */
    public function destroy(Request $request, $id)
    {
        $list = $request->user()->broadcastLists()->findOrFail($id);
        $list->delete();

        return response()->json(['message' => 'Broadcast list deleted']);
    }

    /**
     * Send a message to all recipients in a broadcast list.
     * POST /broadcast-lists/{id}/send
     */
    public function sendMessage(Request $request, $id)
    {
        $list = $request->user()->broadcastLists()->with('recipients')->findOrFail($id);

        $data = $request->validate([
            'body' => 'nullable|string',
            'attachments' => 'nullable|array',
            'attachments.*' => 'integer|exists:attachments,id',
        ]);

        $sender = $request->user();
        $recipients = $list->recipients;
        $sentMessages = [];

        foreach ($recipients as $recipient) {
            // Find or create conversation with each recipient
            $conversation = \App\Models\Conversation::findOrCreateDirect($sender->id, $recipient->id);

            // Create message
            $message = \App\Models\Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'body' => $data['body'] ?? null,
            ]);

            // Attach attachments if provided
            if (!empty($data['attachments'])) {
                $message->attachments()->attach($data['attachments']);
            }

            $sentMessages[] = [
                'recipient_id' => $recipient->id,
                'recipient_name' => $recipient->name,
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
            ];
        }

        return response()->json([
            'message' => 'Message sent to ' . count($sentMessages) . ' recipients',
            'sent_messages' => $sentMessages,
        ]);
    }
}

