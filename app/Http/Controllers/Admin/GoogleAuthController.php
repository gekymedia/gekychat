<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Google\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Google OAuth for Drive backups only (per-project refresh token in .env).
 */
class GoogleAuthController extends Controller
{
    public function start(Request $request)
    {
        $client = $this->makeClient();
        $client->setState(csrf_token());
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setIncludeGrantedScopes(true);
        $client->setScopes(config('services.google_backup_backup.scopes', [
            'https://www.googleapis.com/auth/drive.file',
        ]));

        return redirect()->away($client->createAuthUrl());
    }

    public function callback(Request $request)
    {
        $redirectRoute = (string) config('services.google_backup.backups_redirect_route', 'admin.backups.index');

        if ($request->has('error')) {
            return redirect()->route($redirectRoute)
                ->with('error', 'Google auth error: '.$request->get('error'));
        }

        $code = (string) $request->get('code');
        if ($code === '') {
            return redirect()->route($redirectRoute)
                ->with('error', 'Missing authorization code from Google.');
        }

        $guardKey = 'google_oauth_code_'.sha1($code);
        if (Cache::has($guardKey)) {
            return redirect()->route($redirectRoute)
                ->with('error', 'This authorization code was already used. Please try again.');
        }
        Cache::put($guardKey, true, 60);

        $client = $this->makeClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            return redirect()->route($redirectRoute)
                ->with('error', 'Google token exchange failed: '.($token['error_description'] ?? $token['error']));
        }

        $cacheKey = (string) config('services.google_backup.access_token_cache_key', 'google_access_token');
        $ttl = max(60, (int) ($token['expires_in'] ?? 3600) - 300);
        Cache::put($cacheKey, $token, now()->addSeconds($ttl));

        if (! empty($token['refresh_token'])) {
            $this->persistRefreshToken($token['refresh_token']);
            $msg = 'Google Drive connected for this project.';
        } else {
            $msg = 'Google connected (no new refresh token). Revoke old access at myaccount.google.com/permissions and reconnect with consent.';
        }

        return redirect()->route($redirectRoute)->with('success', $msg);
    }

    protected function makeClient(): Client
    {
        $client = new Client();
        $client->setClientId((string) config('services.google_backup.client_id'));
        $client->setClientSecret((string) config('services.google_backup.client_secret'));
        $client->setRedirectUri(config('services.google_backup.redirect'));

        return $client;
    }

    protected function persistRefreshToken(string $refreshToken): void
    {
        Storage::disk('local')->put('google/refresh_token.txt', $refreshToken);

        $envPath = base_path('.env');
        if (! is_file($envPath)) {
            throw new \RuntimeException('.env file not found');
        }

        $env = file_get_contents($envPath);
        $escapedToken = str_replace(['"', "\n", "\r"], ['\"', '', ''], $refreshToken);

        if (str_contains($env, 'GOOGLE_BACKUP_REFRESH_TOKEN=')) {
            $env = preg_replace(
                '/^GOOGLE_BACKUP_REFRESH_TOKEN=.*$/m',
                'GOOGLE_BACKUP_REFRESH_TOKEN="'.$escapedToken.'"',
                $env
            );
        } else {
            $env .= PHP_EOL.'GOOGLE_BACKUP_REFRESH_TOKEN="'.$escapedToken.'"'.PHP_EOL;
        }

        file_put_contents($envPath, $env);

        try {
            Artisan::call('config:clear');
        } catch (\Throwable $e) {
            Log::warning('config:clear after Google auth failed: '.$e->getMessage());
        }

        putenv('GOOGLE_BACKUP_REFRESH_TOKEN='.$refreshToken);
        config(['services.google_backup.refresh_token' => $refreshToken]);
    }
}
