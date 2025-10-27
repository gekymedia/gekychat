<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function updateAbout(Request $request)
    {
        $request->validate([
            'about' => 'nullable|string|max:140'
        ]);

        $user = Auth::user();
        $user->update(['about' => $request->about]);

        return response()->json([
            'success' => true,
            'about' => $user->about_text,
            'message' => 'About updated successfully'
        ]);
    }
}