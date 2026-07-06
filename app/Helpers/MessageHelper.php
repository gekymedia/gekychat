<?php
// app/Helpers/MessageHelper.php

namespace App\Helpers;

class MessageHelper
{
    public static function normalizePhoneNumber($phone)
    {
        $cleaned = preg_replace('/[^\d+]/', '', $phone);
        
        if (str_starts_with($cleaned, '233')) {
            return '+' . $cleaned;
        } elseif (str_starts_with($cleaned, '0')) {
            return '+233' . substr($cleaned, 1);
        } elseif (strlen($cleaned) === 9 && !str_starts_with($cleaned, '0')) {
            return '+233' . $cleaned;
        }
        
        return $cleaned;
    }

    public static function applyMarkdownFormatting($content)
    {
        // Bold: **text** or __text__
        $content = preg_replace('/(\*\*|__)(.*?)\1/', '<strong>$2</strong>', $content);
        
        // Italic: *text* or _text_
        $content = preg_replace('/(\*|_)(.*?)\1/', '<em>$2</em>', $content);
        
        // Strikethrough: ~text~
        $content = preg_replace('/~(.*?)~/', '<del>$1</del>', $content);
        
        // Monospace: ```text```
        $content = preg_replace('/```(.*?)```/', '<code>$1</code>', $content);
        
        return $content;
    }

    /**
     * Fast body HTML for async messages-panel loads (avoids heavy per-message Blade).
     */
    public static function formatPanelBody(?string $body, bool $isEncrypted = false, bool $isOwn = false): string
    {
        $body = trim((string) $body);
        if ($body === '') {
            return '';
        }

        if ($isEncrypted && ! $isOwn) {
            return '<i class="bi bi-lock-fill me-1" aria-hidden="true"></i><span>Encrypted message</span>';
        }

        if (preg_match('/^https?:\/\/[^\s]+\.(gif|webp)(\?[^\s]*)?$/i', $body)
            || preg_match('/^https?:\/\/(media\d*\.giphy\.com|i\.giphy\.com)/i', $body)) {
            return '<img src="' . e($body) . '" class="img-fluid rounded gif-message" alt="GIF" loading="lazy" style="max-width:300px;max-height:300px;">';
        }

        $escaped = e($body);
        $html = preg_replace(
            '/(https?:\/\/[^\s<]+)/',
            '<a href="$1" target="_blank" class="linkify" rel="noopener noreferrer">$1</a>',
            nl2br($escaped)
        );

        return self::applyMarkdownFormatting($html ?? $escaped);
    }
}