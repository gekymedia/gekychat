<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AutoReplyRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * AUTO-REPLY: Controller for managing auto-reply rules
 * 
 * Allows users to create, read, update, and delete auto-reply rules
 */
class AutoReplyController extends Controller
{
    /**
     * Get all auto-reply rules for the authenticated user
     * GET /api/v1/auto-replies
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $rules = AutoReplyRule::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $rules,
        ]);
    }

    /**
     * Get a specific auto-reply rule
     * GET /api/v1/auto-replies/{id}
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        
        $rule = AutoReplyRule::where('user_id', $user->id)
            ->findOrFail($id);

        return response()->json([
            'data' => $rule,
        ]);
    }

    /**
     * Create a new auto-reply rule
     * POST /api/v1/auto-replies
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'keyword' => ['required', 'string', 'max:255'],
            'reply_text' => ['required', 'string', 'max:1000'],
            'delay_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        $rule = AutoReplyRule::create([
            'user_id' => $user->id,
            'keyword' => $request->keyword,
            'reply_text' => $request->reply_text,
            'delay_seconds' => $request->delay_seconds,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'message' => 'Auto-reply rule created',
            'data' => $rule,
        ], 201);
    }

    /**
     * Update an auto-reply rule
     * PUT /api/v1/auto-replies/{id}
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        
        $rule = AutoReplyRule::where('user_id', $user->id)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'keyword' => ['sometimes', 'required', 'string', 'max:255'],
            'reply_text' => ['sometimes', 'required', 'string', 'max:1000'],
            'delay_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $rule->update($request->only(['keyword', 'reply_text', 'delay_seconds', 'is_active']));

        return response()->json([
            'message' => 'Auto-reply rule updated',
            'data' => $rule,
        ]);
    }

    /**
     * Delete an auto-reply rule
     * DELETE /api/v1/auto-replies/{id}
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        
        $rule = AutoReplyRule::where('user_id', $user->id)
            ->findOrFail($id);

        $rule->delete();

        return response()->json([
            'message' => 'Auto-reply rule deleted',
        ]);
    }
}
