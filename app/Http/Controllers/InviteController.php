<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\LiveBroadcast;
use App\Models\WorldFeedPost;
use App\Models\Group;

class InviteController extends Controller
{
    /**
     * Show invite page (WhatsApp-style)
     * Handles /wf/{code} for world feed posts and group invites.
     * Public route (no auth) so shared links show Open Graph previews.
     */
    public function show($code)
    {
        // Try to find a world feed post
        $post = WorldFeedPost::where('share_code', $code)->with('creator:id,name,username')->first();
        
        // Try to find a group invite
        $group = Group::where('invite_code', $code)->first();
        
        if (!$post && !$group) {
            abort(404, 'Invite link not found');
        }
        
        $type = $post ? 'post' : 'group';
        $item = $post ?? $group;
        
        // Determine deep link and web URL
        $deepLink = null;
        $webUrl = null;
        $ogTitle = null;
        $ogDescription = null;
        $ogImage = null;
        $canonicalUrl = null;
        
        if ($type === 'post') {
            $deepLink = "gekychat://world-feed/post/{$code}";
            $webUrl = route('world-feed.index');
            $canonicalUrl = url("/wf/{$code}");
            $creatorName = $post->creator ? ($post->creator->name ?: $post->creator->username) : 'Someone';
            $ogTitle = config('app.name', 'GekyChat') . ' · ' . $creatorName;
            $ogDescription = $post->caption
                ? \Illuminate\Support\Str::limit(strip_tags($post->caption), 160)
                : "Watch this post by {$creatorName} on " . config('app.name', 'GekyChat');
            // Absolute URL for thumbnail so crawlers can fetch it (required for link previews)
            $thumbPath = $post->thumbnail_url ?: $post->media_url;
            if ($thumbPath) {
                if (\Illuminate\Support\Str::startsWith($thumbPath, ['http://', 'https://'])) {
                    $ogImage = $thumbPath;
                } else {
                    $path = ltrim($thumbPath, '/');
                    $ogImage = Storage::disk('public')->exists($path)
                        ? asset('storage/' . $path)
                        : null;
                }
            }
        } else {
            $canonicalUrl = url("/wf/{$code}");
            $webUrl = route('world-feed.index');
            $deepLink = url("/groups/join/{$code}");
            $ogTitle = config('app.name', 'GekyChat') . ' · Join group';
            $ogDescription = "You've been invited to join a group on " . config('app.name', 'GekyChat');
        }

        $ctaLabel = $type === 'post' ? 'Watch' : 'Join';
        
        return view('invite.show', [
            'type' => $type,
            'item' => $item,
            'code' => $code,
            'deepLink' => $deepLink,
            'webUrl' => $webUrl,
            'ogTitle' => $ogTitle,
            'ogDescription' => $ogDescription,
            'ogImage' => $ogImage,
            'canonicalUrl' => $canonicalUrl,
            'ctaLabel' => $ctaLabel,
        ]);
    }

    /**
     * Public landing page for shared live links: https://chat.gekychat.com/live/{id}
     * (Open Graph + deep link gekychat://live/{id} for the mobile app.)
     */
    public function showLive(string $id)
    {
        if (! ctype_digit($id)) {
            abort(404);
        }

        $broadcast = LiveBroadcast::query()
            ->whereKey((int) $id)
            ->with('broadcaster:id,name,username,avatar_path')
            ->first();

        if (! $broadcast) {
            abort(404, 'Live not found');
        }

        $isLive = $broadcast->status === 'live';
        $broadcaster = $broadcast->broadcaster;
        $creatorName = $broadcaster
            ? ($broadcaster->name ?: ($broadcaster->username ?? 'Someone'))
            : 'Someone';

        $deepLink = "gekychat://live/{$broadcast->id}";
        $canonicalUrl = url("/live/{$broadcast->id}");
        $webUrl = route('world-feed.index');

        if ($isLive) {
            $ogTitle = config('app.name', 'GekyChat').' · LIVE · '.$creatorName;
            $ogDescription = $broadcast->title
                ? \Illuminate\Support\Str::limit(strip_tags($broadcast->title), 160)
                : "{$creatorName} is live on ".config('app.name', 'GekyChat').'. Tap to watch.';
        } else {
            $ogTitle = config('app.name', 'GekyChat').' · Live ended';
            $ogDescription = 'This broadcast is no longer live. Open the app for more from '.$creatorName.'.';
        }

        $ogImage = $broadcaster ? $broadcaster->avatar_url : null;

        return view('invite.live', [
            'broadcast' => $broadcast,
            'isLive' => $isLive,
            'creatorName' => $creatorName,
            'deepLink' => $deepLink,
            'webUrl' => $webUrl,
            'ogTitle' => $ogTitle,
            'ogDescription' => $ogDescription,
            'ogImage' => $ogImage,
            'canonicalUrl' => $canonicalUrl,
        ]);
    }
}
