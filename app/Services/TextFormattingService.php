<?php

namespace App\Services;

/**
 * Service for parsing and validating WhatsApp-style text formatting
 * 
 * Supported formats:
 * - *bold* or **bold** for bold text
 * - _italic_ or __italic__ for italic text  
 * - ~strikethrough~ or ~~strikethrough~~ for strikethrough text
 * - `monospace` or ``monospace`` for monospace/code text
 */
class TextFormattingService
{
    /**
     * Validate formatting markers in text
     * Ensures markers are properly paired
     * 
     * @param string $text
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function validateFormatting(string $text): array
    {
        $errors = [];
        
        // Check for unmatched markers
        $boldPairs = self::countPairs($text, '*');
        if ($boldPairs % 2 !== 0) {
            $errors[] = 'Unmatched bold markers (*)';
        }
        
        $italicPairs = self::countPairs($text, '_');
        if ($italicPairs % 2 !== 0) {
            $errors[] = 'Unmatched italic markers (_)';
        }
        
        $strikethroughPairs = self::countPairs($text, '~');
        if ($strikethroughPairs % 2 !== 0) {
            $errors[] = 'Unmatched strikethrough markers (~)';
        }
        
        $monospacePairs = self::countPairs($text, '`');
        if ($monospacePairs % 2 !== 0) {
            $errors[] = 'Unmatched monospace markers (`)';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
    
    /**
     * Count pairs of formatting markers
     * Handles single and double markers
     * 
     * @param string $text
     * @param string $marker
     * @return int Number of pairs
     */
    private static function countPairs(string $text, string $marker): int
    {
        $escaped = preg_quote($marker, '/');
        $doublePattern = '/' . $escaped . $escaped . '/';
        
        // First, count double markers and remove them
        $doubleMatches = preg_match_all($doublePattern, $text);
        $textWithoutDouble = preg_replace($doublePattern, '', $text);
        
        // Then count remaining single markers
        // Single marker must not be part of a double marker and should be between word chars or at boundaries
        $singlePattern = '/(?<![*`_~])' . $escaped . '(?![*`_~])/';
        $singleMatches = preg_match_all($singlePattern, $textWithoutDouble);
        
        // Total markers = double*2 + single, pairs = markers / 2
        $totalMarkers = ($doubleMatches * 2) + $singleMatches;
        return (int)($totalMarkers / 2);
    }
    
    /**
     * Parse formatted text and return structured data
     * Useful for API responses or HTML rendering
     * 
     * @param string $text
     * @return array Array of text segments with formatting info
     */
    public static function parseFormatting(string $text): array
    {
        $segments = [];
        $current = '';
        $stack = []; // Track nested formatting
        
        $i = 0;
        $len = mb_strlen($text);
        
        while ($i < $len) {
            $char = mb_substr($text, $i, 1);
            $nextChar = $i + 1 < $len ? mb_substr($text, $i + 1, 1) : '';
            
            // Check for double markers first
            $isDouble = ($char === $nextChar) && in_array($char, ['*', '_', '~', '`']);
            
            if ($isDouble) {
                // Handle double marker
                $marker = $char . $nextChar;
                $formatType = self::getFormatType($marker);
                
                if (self::isClosingMarker($text, $i, $marker, $stack)) {
                    // Close formatting
                    if (!empty($current)) {
                        $segments[] = [
                            'text' => $current,
                            'formats' => array_unique($stack),
                        ];
                        $current = '';
                    }
                    array_pop($stack);
                    $i += 2;
                } else {
                    // Open formatting
                    if (!empty($current)) {
                        $segments[] = [
                            'text' => $current,
                            'formats' => array_unique($stack),
                        ];
                        $current = '';
                    }
                    $stack[] = $formatType;
                    $i += 2;
                }
            } elseif (in_array($char, ['*', '_', '~', '`'])) {
                // Handle single marker
                $formatType = self::getFormatType($char);
                
                if (self::isClosingMarker($text, $i, $char, $stack)) {
                    // Close formatting
                    if (!empty($current)) {
                        $segments[] = [
                            'text' => $current,
                            'formats' => array_unique($stack),
                        ];
                        $current = '';
                    }
                    // Remove the last occurrence of this format type
                    $key = array_search($formatType, array_reverse($stack, true));
                    if ($key !== false) {
                        unset($stack[$key]);
                        $stack = array_values($stack);
                    }
                    $i++;
                } else {
                    // Open formatting
                    if (!empty($current)) {
                        $segments[] = [
                            'text' => $current,
                            'formats' => array_unique($stack),
                        ];
                        $current = '';
                    }
                    $stack[] = $formatType;
                    $i++;
                }
            } else {
                $current .= $char;
                $i++;
            }
        }
        
        // Add remaining text
        if (!empty($current)) {
            $segments[] = [
                'text' => $current,
                'formats' => array_unique($stack),
            ];
        }
        
        return $segments;
    }
    
    /**
     * Check if a marker is a closing marker
     * 
     * @param string $text
     * @param int $pos
     * @param string $marker
     * @param array $stack
     * @return bool
     */
    private static function isClosingMarker(string $text, int $pos, string $marker, array $stack): bool
    {
        $formatType = self::getFormatType($marker);
        
        // If format is not in stack, it's opening
        if (!in_array($formatType, $stack)) {
            return false;
        }
        
        // Check if there's content before this marker
        $before = mb_substr($text, 0, $pos);
        $lastNonSpace = mb_strlen(rtrim($before));
        
        // Must have non-whitespace content before to be a closer
        return $lastNonSpace > 0;
    }
    
    /**
     * Get format type from marker
     * 
     * @param string $marker
     * @return string
     */
    private static function getFormatType(string $marker): string
    {
        $marker = trim($marker);
        switch ($marker) {
            case '*':
            case '**':
                return 'bold';
            case '_':
            case '__':
                return 'italic';
            case '~':
            case '~~':
                return 'strikethrough';
            case '`':
            case '``':
                return 'monospace';
            default:
                return 'none';
        }
    }
    
    /**
     * Convert formatted text to HTML
     * Useful for web display or email rendering
     * 
     * @param string $text
     * @return string HTML string
     */
    public static function toHtml(string $text): string
    {
        $segments = self::parseFormatting($text);
        $html = '';
        
        foreach ($segments as $segment) {
            $content = htmlspecialchars($segment['text'], ENT_QUOTES, 'UTF-8');
            
            if (empty($segment['formats'])) {
                $html .= $content;
                continue;
            }
            
            // Apply formatting tags
            $formatted = $content;
            foreach ($segment['formats'] as $format) {
                switch ($format) {
                    case 'bold':
                        $formatted = "<strong>{$formatted}</strong>";
                        break;
                    case 'italic':
                        $formatted = "<em>{$formatted}</em>";
                        break;
                    case 'strikethrough':
                        $formatted = "<s>{$formatted}</s>";
                        break;
                    case 'monospace':
                        $formatted = "<code>{$formatted}</code>";
                        break;
                }
            }
            
            $html .= $formatted;
        }
        
        return $html;
    }
    
    /**
     * Convert formatted text to plain text (remove all markers)
     * 
     * @param string $text
     * @return string
     */
    public static function toPlainText(string $text): string
    {
        // Remove all formatting markers
        $text = preg_replace('/\*\*?/', '', $text); // Remove * and **
        $text = preg_replace('/__?/', '', $text);   // Remove _ and __
        $text = preg_replace('/~~?/', '', $text);   // Remove ~ and ~~
        $text = preg_replace('/`{1,2}/', '', $text); // Remove ` and ``
        
        return $text;
    }
}
