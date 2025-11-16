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

    /**
     * Update the authenticated user's date of birth (month and day).
     * Allows setting either or both fields to null or valid values.
     * Example payload: { "dob_month": 5, "dob_day": 21 }
     */
    public function updateDob(Request $request)
    {
        $request->validate([
            'dob_month' => 'nullable|integer|min:1|max:12',
            'dob_day'   => 'nullable|integer|min:1|max:31',
        ]);

        $user = Auth::user();
        $user->dob_month = $request->input('dob_month');
        $user->dob_day   = $request->input('dob_day');
        $user->save();

        return response()->json([
            'success'   => true,
            'dob_month' => $user->dob_month,
            'dob_day'   => $user->dob_day,
            'message'   => 'Birthday updated successfully',
        ]);
    }
}