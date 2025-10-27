<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Services\GoogleContactsService;

class GoogleAuthController extends Controller
{
    protected $googleService;

    public function __construct(GoogleContactsService $googleService)
    {
        $this->googleService = $googleService;
    }

    public function redirect()
    {
        $state = Str::random(40);
        session(['oauth_state' => $state]);

        $query = http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => route('google.auth.callback'),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/contacts.readonly',
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ]);

        return redirect('https://accounts.google.com/o/oauth2/v2/auth?' . $query);
    }

    public function callback(Request $request)
    {
        if ($request->state !== session('oauth_state')) {
            return redirect()->route('contacts.index')->withErrors(['google' => 'Invalid state parameter']);
        }

        try {
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'code' => $request->code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => route('google.auth.callback'),
            ]);

            if ($response->successful()) {
                $tokens = $response->json();
                
                $user = Auth::user();
                $user->update([
                    'google_access_token' => $tokens['access_token'],
                    'google_refresh_token' => $tokens['refresh_token'] ?? null,
                    'google_sync_enabled' => true,
                ]);

                // Perform initial sync
                $syncResult = $this->googleService->syncContacts($user);

                return redirect()->route('contacts.index')
                    ->with('success', 'Google account connected and contacts synced successfully!')
                    ->with('sync_result', $syncResult);
            }
        } catch (\Exception $e) {
            return redirect()->route('contacts.index')
                ->withErrors(['google' => 'Failed to connect Google account: ' . $e->getMessage()]);
        }

        return redirect()->route('contacts.index')
            ->withErrors(['google' => 'Failed to connect Google account']);
    }

    public function sync(Request $request)
    {
        try {
            $user = Auth::user();
            $result = $this->googleService->syncContacts($user);
            
            return redirect()->route('contacts.index')
                ->with('success', $result['message'])
                ->with('sync_result', $result);
                
        } catch (\Exception $e) {
            return redirect()->route('contacts.index')
                ->withErrors(['google' => 'Sync failed: ' . $e->getMessage()]);
        }
    }

    public function disconnect(Request $request)
    {
        $user = Auth::user();
        $user->update([
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_sync_enabled' => false,
        ]);

        // Optionally: Keep GoogleContact records but mark them as disconnected
        // Or delete them: $user->googleContacts()->delete();

        return redirect()->route('contacts.index')
            ->with('success', 'Google account disconnected successfully');
    }

    public function status()
    {
        $user = Auth::user();
        $status = $this->googleService->getSyncStatus($user);
        
        return response()->json($status);
    }
}