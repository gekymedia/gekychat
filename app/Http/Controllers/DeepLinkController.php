<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DeepLinkController extends Controller
{
    /**
     * Serve Android App Links verification file
     * GET /.well-known/assetlinks.json
     */
    public function assetlinks()
    {
        // Android package name from build.gradle.kts
        $packageName = 'com.example.gekychat_mobile';
        
        // SHA256 fingerprints - these need to be updated with your actual app signing certificate fingerprints
        // You can get these using:
        // keytool -list -v -keystore your-keystore.jks -alias your-alias
        // For debug builds, use the debug keystore fingerprint
        $sha256Fingerprints = [
            // TODO: Replace with your actual SHA256 fingerprints
            // Example: 'AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99:AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99'
        ];
        
        // If no fingerprints are configured, return empty array (app links won't work until configured)
        if (empty($sha256Fingerprints)) {
            return response()->json([
                'error' => 'App Links not configured. Please add SHA256 fingerprints to DeepLinkController.'
            ], 404);
        }
        
        $assetLinks = [
            [
                'relation' => ['delegate_permission/common.handle_all_urls'],
                'target' => [
                    'namespace' => 'android_app',
                    'package_name' => $packageName,
                    'sha256_cert_fingerprints' => $sha256Fingerprints
                ]
            ]
        ];
        
        return response()->json($assetLinks)
            ->header('Content-Type', 'application/json');
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
                'error' => 'Universal Links not configured. Please add Team ID to DeepLinkController.'
            ], 404);
        }
        
        $association = [
            'applinks' => [
                'apps' => [],
                'details' => [
                    [
                        'appID' => $teamId . '.' . $appId,
                        'paths' => [
                            '/g/*',      // Group invite links
                            '/c/*',      // Conversation links
                            '/chat/*',   // Alternative conversation links
                            '/groups/join/*', // Group join links
                        ]
                    ]
                ]
            ]
        ];
        
        // Return as JSON without .json extension (iOS requirement)
        return response()->json($association)
            ->header('Content-Type', 'application/json');
    }
}
