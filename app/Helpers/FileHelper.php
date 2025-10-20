<?php
// app/Helpers/FileHelper.php

namespace App\Helpers;

class FileHelper
{
    public static function formatFileSize($bytes)
    {
        if ($bytes == 0) return '0 Bytes';
        
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));
        
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }

    public static function getFileIcon($mimeType = null)
    {
        if (!$mimeType) return 'bi-file-earmark';
        
        $icons = [
            'pdf' => 'bi-file-pdf-fill',
            'doc' => 'bi-file-word-fill',
            'docx' => 'bi-file-word-fill',
            'xls' => 'bi-file-excel-fill',
            'xlsx' => 'bi-file-excel-fill',
            'ppt' => 'bi-file-ppt-fill',
            'pptx' => 'bi-file-ppt-fill',
            'zip' => 'bi-file-zip-fill',
            'rar' => 'bi-file-zip-fill',
            'txt' => 'bi-file-text-fill',
            'csv' => 'bi-file-text-fill',
            'default' => 'bi-file-earmark'
        ];
        
        $type = strtolower($mimeType);
        
        if (str_contains($type, 'pdf')) return $icons['pdf'];
        if (str_contains($type, 'word') || str_contains($type, 'document')) return $icons['doc'];
        if (str_contains($type, 'excel') || str_contains($type, 'spreadsheet')) return $icons['xls'];
        if (str_contains($type, 'powerpoint') || str_contains($type, 'presentation')) return $icons['ppt'];
        if (str_contains($type, 'zip') || str_contains($type, 'compressed')) return $icons['zip'];
        if (str_contains($type, 'text') || str_contains($type, 'csv')) return $icons['txt'];
        
        return $icons['default'];
    }
}
?>