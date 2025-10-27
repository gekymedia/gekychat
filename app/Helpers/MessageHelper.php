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
}