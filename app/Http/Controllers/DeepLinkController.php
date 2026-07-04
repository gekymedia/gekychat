<?php

namespace App\Http\Controllers;

class DeepLinkController extends Controller
{
    /**
     * Serve Android App Links + credential sharing verification file.
     * GET /.well-known/assetlinks.json
     *
     * Required for:
     * - App Links (open chat.gekychat.com URLs in the app)
     * - Credential sharing (passkeys / saved passwords between site and app)
     *
     * @see https://developers.google.com/identity/smartlock-passwords/android/associate-apps-and-sites
     */
    public function assetlinks()
    {
        $assetLinks = [
            [
                'relation' => [
                    'delegate_permission/common.handle_all_urls',
                ],
                'target' => [
                    'namespace' => 'android_app',
                    'package_name' => 'com.example.gekychat_mobile',
                    'sha256_cert_fingerprints' => [
                        '71:C4:10:D4:0C:C5:E8:3D:7D:55:36:A9:AD:11:40:8E:D4:B8:3A:3C:B9:49:76:58:D8:B4:8B:F9:25:33:3B:42',
                    ],
                ],
            ],
            [
                'relation' => [
                    'delegate_permission/common.handle_all_urls',
                    'delegate_permission/common.get_login_creds',
                ],
                'target' => [
                    'namespace' => 'android_app',
                    'package_name' => 'com.gekychat.app',
                    'sha256_cert_fingerprints' => [
                        '3F:52:5A:98:2D:29:CA:55:90:4A:CA:60:C4:7F:3D:A1:58:13:50:89:0A:E7:6F:46:17:45:66:E6:30:2F:24:FA',
                    ],
                ],
            ],
        ];

        return response()->json($assetLinks, 200, [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ->header('Content-Type', 'application/json')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Serve iOS Universal Links verification file
     * GET /.well-known/apple-app-site-association
     */
    public function appleAppSiteAssociation()
    {
        // iOS bundle identifier from Info.plist
        $appId = 'com.example.gekychatMobile';

        // Team ID - this needs to be your Apple Developer Team ID
        // You can find it in your Apple Developer account or Xcode
        $teamId = 'YOUR_TEAM_ID'; // TODO: Replace with your actual Team ID

        // If team ID is not configured, return empty (universal links won't work until configured)
        if ($teamId === 'YOUR_TEAM_ID') {
            return response()->json([
                'error' => 'Universal Links not configured. Please add Team ID to DeepLinkController.',
            ], 404);
        }

        $association = [
            'applinks' => [
                'apps' => [],
                'details' => [
                    [
                        'appID' => $teamId.'.'.$appId,
                        'paths' => [
                            '/g/*',      // Group invite links
                            '/c/*',      // Conversation links
                            '/chat/*',   // Alternative conversation links
                            '/groups/join/*', // Group join links
                        ],
                    ],
                ],
            ],
        ];

        // Return as JSON without .json extension (iOS requirement)
        return response()->json($association)
            ->header('Content-Type', 'application/json');
    }
}
