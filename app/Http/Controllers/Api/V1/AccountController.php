<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    /**
     * Delete user account.
     * DELETE /account
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'error' => 'Invalid password',
            ], 400);
        }

        return DB::transaction(function () use ($user) {
            // Delete all user tokens
            $user->tokens()->delete();

            // Delete user data
            $user->delete();

            return response()->json([
                'message' => 'Account deleted successfully',
            ]);
        });
    }
}

