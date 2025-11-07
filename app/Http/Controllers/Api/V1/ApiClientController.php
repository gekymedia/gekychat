<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use Illuminate\Http\Request;

/**
 * API controller to manage API client subscriptions. Users can create
 * subscriptions with callback URLs and selected features. Admins can
 * view all subscriptions and approve or revoke them.
 */
class ApiClientController extends Controller
{
    /**
     * List API subscriptions. If the user is an admin, list all; otherwise
     * list only the authenticated user's subscriptions.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->is_admin) {
            $clients = ApiClient::with('user:id,name,phone')->get();
        } else {
            $clients = $user->apiClients()->get();
        }
        return response()->json(['data' => $clients]);
    }

    /**
     * Create a new API subscription. Only authenticated users can create their
     * own subscription.
     */
    public function store(Request $request)
    {
        $request->validate([
            'callback_url' => 'required|url',
            'features'     => 'nullable|array',
        ]);
        $client = ApiClient::create([
            'user_id'      => $request->user()->id,
            'callback_url' => $request->callback_url,
            'features'     => $request->features,
            'status'       => 'pending',
        ]);
        return response()->json(['data' => $client], 201);
    }

    /**
     * Update subscription status. Only admin can update (approve/revoke).
     */
    public function update(Request $request, $clientId)
    {
        $client = ApiClient::findOrFail($clientId);
        if (!$request->user()->is_admin) {
            abort(403);
        }
        $request->validate([
            'status'   => 'required|string|in:pending,approved,revoked',
            'features' => 'nullable|array',
        ]);
        $client->update([
            'status'   => $request->status,
            'features' => $request->features ?? $client->features,
        ]);
        return response()->json(['data' => $client]);
    }

    /**
     * Delete a subscription. Admin or owner can delete.
     */
    public function destroy(Request $request, $clientId)
    {
        $client = ApiClient::findOrFail($clientId);
        if (!$request->user()->is_admin && $client->user_id !== $request->user()->id) {
            abort(403);
        }
        $client->delete();
        return response()->json(['message' => 'Deleted']);
    }
}