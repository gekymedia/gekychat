<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LinkPreviewService
{
    public function getPreview(string $url): ?array
    {
        // Check if this is a gekychat group invite link
        $gekychatPreview = $this->getGekychatGroupPreview($url);
        if ($gekychatPreview) {
            return $gekychatPreview;
        }
        
        $cacheKey = 'link_preview_' . md5($url);
        
        return Cache::remember($cacheKey, 3600, function () use ($url) {
            try {
                // Validate URL
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    return null;
                }

                // Make request with proper headers and timeout
                $response = Http::timeout(10)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (compatible; LinkPreviewBot/1.0)',
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                        'Accept-Language' => 'en-US,en;q=0.5',
                    ])
                    ->get($url);

                if (!$response->successful()) {
                    Log::warning("Link preview HTTP error: " . $response->status());
                    return $this->getFallbackPreview($url);
                }

                $html = $response->body();
                
                return $this->parseHtmlForPreview($html, $url);

            } catch (\Exception $e) {
                Log::error('Link preview error for ' . $url . ': ' . $e->getMessage());
                return $this->getFallbackPreview($url);
            }
        });
    }

    private function getGekychatGroupPreview(string $url): ?array
    {
        // Parse URL to check if it's a gekychat group invite link
        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'] ?? '';
        $path = $parsedUrl['path'] ?? '';
        
        // Check if URL contains gekychat domain or localhost (for development)
        $isGekychatUrl = str_contains($host, 'gekychat') || 
                        str_contains($host, 'localhost') || 
                        str_contains($host, '127.0.0.1');
        
        if (!$isGekychatUrl) {
            return null;
        }
        
        // Check if path matches group invite pattern: /groups/join/{code} or /invite/{code}
        $inviteCode = null;
        // Use a more robust pattern that handles special characters properly
        // Match alphanumeric and common safe characters for invite codes
        try {
            if (preg_match('~/groups/join/([a-zA-Z0-9_-]+)~', $path, $matches)) {
                $inviteCode = $matches[1];
            } elseif (preg_match('~/invite/([a-zA-Z0-9_-]+)~', $path, $matches)) {
                $inviteCode = $matches[1];
            }
        } catch (\Exception $e) {
            // If regex fails, try a simpler approach
            Log::warning('Regex error in getGekychatGroupPreview: ' . $e->getMessage());
            // Fallback: extract code manually
            if (str_contains($path, '/groups/join/')) {
                $parts = explode('/groups/join/', $path);
                if (isset($parts[1])) {
                    $inviteCode = explode('/', $parts[1])[0];
                    $inviteCode = explode('?', $inviteCode)[0];
                    $inviteCode = explode('#', $inviteCode)[0];
                }
            } elseif (str_contains($path, '/invite/')) {
                $parts = explode('/invite/', $path);
                if (isset($parts[1])) {
                    $inviteCode = explode('/', $parts[1])[0];
                    $inviteCode = explode('?', $inviteCode)[0];
                    $inviteCode = explode('#', $inviteCode)[0];
                }
            }
        }
        
        if (!$inviteCode) {
            return null;
        }
        
        // Find the group by invite code
        $group = \App\Models\Group::where('invite_code', $inviteCode)->first();
        
        if (!$group) {
            return null;
        }
        
        // Build preview with group info
        $avatarUrl = $group->avatar_path 
            ? \App\Helpers\UrlHelper::secureStorageUrl($group->avatar_path)
            : \App\Helpers\UrlHelper::secureAsset('images/group-default.png');
        
        return [
            'url' => $url,
            'title' => $group->name,
            'description' => $group->description ?? 'Join this ' . ($group->type === 'channel' ? 'channel' : 'group') . ' on GekyChat',
            'image' => $avatarUrl,
            'site_name' => 'GekyChat',
        ];
    }

    private function parseHtmlForPreview(string $html, string $url): array
    {
        $metaTags = $this->parseMetaTags($html);
        
        $preview = [
            'url' => $url,
            'title' => $this->extractTitle($html, $metaTags) ?? $this->getDomainFromUrl($url),
            'description' => $this->extractDescription($metaTags) ?? 'Visit this website to learn more.',
            'image' => $this->extractImage($metaTags, $url),
            'site_name' => $this->extractSiteName($metaTags, $url) ?? $this->getDomainFromUrl($url),
        ];

        return $preview;
    }

    private function getFallbackPreview(string $url): array
    {
        return [
            'url' => $url,
            'title' => $this->getDomainFromUrl($url),
            'description' => 'Unable to fetch preview. Click to visit the website.',
            'image' => null,
            'site_name' => $this->getDomainFromUrl($url),
        ];
    }

    private function getDomainFromUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        return $host ? str_replace('www.', '', $host) : 'Website';
    }

    private function parseMetaTags(string $html): array
    {
        $metaTags = [];
        $pattern = '/<meta[^>]+(property|name)=["\']([^"\']+)["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i';
        
        preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $property = strtolower($match[2]);
            $content = html_entity_decode($match[3], ENT_QUOTES, 'UTF-8');
            $metaTags[$property] = $content;
        }
        
        return $metaTags;
    }

    private function extractTitle(string $html, array $metaTags): ?string
    {
        // Try Open Graph title first
        if (!empty($metaTags['og:title'])) {
            return $metaTags['og:title'];
        }
        
        // Try Twitter title
        if (!empty($metaTags['twitter:title'])) {
            return $metaTags['twitter:title'];
        }
        
        // Fall back to document title
        preg_match('/<title[^>]*>(.*?)<\/title>/i', $html, $titleMatches);
        if (!empty($titleMatches[1])) {
            return trim($titleMatches[1]);
        }
        
        return null;
    }

    private function extractDescription(array $metaTags): ?string
    {
        // Try Open Graph description
        if (!empty($metaTags['og:description'])) {
            return $metaTags['og:description'];
        }
        
        // Try Twitter description
        if (!empty($metaTags['twitter:description'])) {
            return $metaTags['twitter:description'];
        }
        
        // Try standard description
        if (!empty($metaTags['description'])) {
            return $metaTags['description'];
        }
        
        return null;
    }

    private function extractImage(array $metaTags, string $baseUrl): ?string
    {
        // Try Open Graph image
        if (!empty($metaTags['og:image'])) {
            return $this->makeAbsoluteUrl($metaTags['og:image'], $baseUrl);
        }
        
        // Try Twitter image
        if (!empty($metaTags['twitter:image'])) {
            return $this->makeAbsoluteUrl($metaTags['twitter:image'], $baseUrl);
        }
        
        // Try Twitter image:src (older format)
        if (!empty($metaTags['twitter:image:src'])) {
            return $this->makeAbsoluteUrl($metaTags['twitter:image:src'], $baseUrl);
        }
        
        return null;
    }

    private function extractSiteName(array $metaTags, string $url): ?string
    {
        if (!empty($metaTags['og:site_name'])) {
            return $metaTags['og:site_name'];
        }
        
        // Extract from Twitter card
        if (!empty($metaTags['twitter:site'])) {
            return $metaTags['twitter:site'];
        }
        
        return null;
    }

    private function makeAbsoluteUrl(string $url, string $baseUrl): string
    {
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }
        
        $base = parse_url($baseUrl);
        $path = ltrim($url, '/');
        
        return $base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '') . '/' . $path;
    }
}