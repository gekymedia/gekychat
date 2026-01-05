<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\GroupMessage;
use App\Models\GroupMessageReaction;
use App\Models\Message;
use App\Models\MessageReaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReactionController extends Controller
{
    public function reactToMessage(Request $r, $messageId)
    {
        try {
            $r->validate(['emoji'=>'required|string|max:16']);
            
            $m = Message::with(['reactions.user','sender','attachments','conversation'])->findOrFail($messageId);
            
            // Check if message has a conversation (not a group message)
            if (!$m->conversation_id) {
                Log::error('Attempted to react to message without conversation_id', [
                    'message_id' => $messageId,
                    'user_id' => $r->user()->id,
                ]);
                return response()->json([
                    'message' => 'This message does not support reactions via this endpoint. Use group message reaction endpoint instead.'
                ], 400);
            }
            
            // Load conversation if not already loaded
            if (!$m->relationLoaded('conversation')) {
                $m->load('conversation');
            }
            
            // Check if user is participant in the conversation
            if (!$m->conversation || !$m->conversation->isParticipant($r->user()->id)) {
                return response()->json([
                    'message' => 'You are not a participant in this conversation.'
                ], 403);
            }

            MessageReaction::updateOrCreate(
                ['user_id'=>$r->user()->id,'message_id'=>$m->id],
                ['emoji'=>$r->emoji]
            );

            $m->load(['reactions.user']);
            return response()->json(['data'=>new MessageResource($m)]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Message not found for reaction', [
                'message_id' => $messageId,
                'user_id' => $r->user()->id ?? null,
            ]);
            return response()->json([
                'message' => 'Message not found.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to react to message: ' . $e->getMessage(), [
                'message_id' => $messageId,
                'user_id' => $r->user()->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Failed to react to message: ' . $e->getMessage()
            ], 500);
        }
    }

    public function unreactFromMessage(Request $r, $messageId)
    {
        try {
            $m = Message::with('conversation')->findOrFail($messageId);
            
            if (!$m->conversation_id || !$m->conversation || !$m->conversation->isParticipant($r->user()->id)) {
                return response()->json([
                    'message' => 'You are not a participant in this conversation.'
                ], 403);
            }
            
            MessageReaction::where(['user_id'=>$r->user()->id,'message_id'=>$m->id])->delete();
            $m->load(['reactions.user','sender','attachments']);
            return response()->json(['data'=>new MessageResource($m)]);
        } catch (\Exception $e) {
            Log::error('Failed to remove reaction from message: ' . $e->getMessage(), [
                'message_id' => $messageId,
                'user_id' => $r->user()->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Failed to remove reaction: ' . $e->getMessage()
            ], 500);
        }
    }

    public function reactToGroupMessage(Request $r, $id)
    {
        try {
            $r->validate(['emoji'=>'required|string|max:16']);
            $m = GroupMessage::with(['reactions.user','sender','attachments','group'])->findOrFail($id);
            
            if (!$m->group || !$m->group->isMember($r->user())) {
                return response()->json([
                    'message' => 'You are not a member of this group.'
                ], 403);
            }

            GroupMessageReaction::updateOrCreate(
                ['user_id'=>$r->user()->id,'group_message_id'=>$m->id],
                ['emoji'=>$r->emoji]
            );

            $m->load(['reactions.user']);
            return response()->json(['data'=>new MessageResource($m)]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to react to group message: ' . $e->getMessage(), [
                'group_message_id' => $id,
                'user_id' => $r->user()->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Failed to react to group message: ' . $e->getMessage()
            ], 500);
        }
    }

    public function unreactFromGroupMessage(Request $r, $id)
    {
        try {
            $m = GroupMessage::with(['group'])->findOrFail($id);
            
            if (!$m->group || !$m->group->isMember($r->user())) {
                return response()->json([
                    'message' => 'You are not a member of this group.'
                ], 403);
            }
            
            GroupMessageReaction::where(['user_id'=>$r->user()->id,'group_message_id'=>$m->id])->delete();
            $m->load(['reactions.user','sender','attachments']);
            return response()->json(['data'=>new MessageResource($m)]);
        } catch (\Exception $e) {
            Log::error('Failed to remove reaction from group message: ' . $e->getMessage(), [
                'group_message_id' => $id,
                'user_id' => $r->user()->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Failed to remove reaction: ' . $e->getMessage()
            ], 500);
        }
    }
}
