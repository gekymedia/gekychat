<?php

namespace App\Support;

class ApiResponse {
    public static function data($data, array $meta = [], int $status = 200) {
        // Always force { data, meta } envelope
        return response()->json(['data' => $data, 'meta' => (object) $meta], $status);
    }
}
