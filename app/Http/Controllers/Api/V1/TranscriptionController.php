<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TranscriptionController extends Controller
{
    /**
     * Transcribe an audio file using OpenAI Whisper API.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function transcribe(Request $request)
    {
        $request->validate([
            'audio' => 'required|file|mimes:mp3,mp4,mpeg,mpga,m4a,wav,webm,ogg,flac|max:25600', // 25MB max (Whisper limit)
            'language' => 'nullable|string|max:10', // Optional language hint (e.g., 'en', 'es', 'fr')
        ]);

        $apiKey = config('services.openai.api_key') ?? env('OPENAI_API_KEY');
        
        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Transcription service is not configured',
            ], 503);
        }

        try {
            $audioFile = $request->file('audio');
            $tempPath = $audioFile->getRealPath();
            $originalName = $audioFile->getClientOriginalName();
            $mimeType = $audioFile->getMimeType();

            // Prepare multipart form data for OpenAI Whisper API
            $multipart = [
                [
                    'name' => 'file',
                    'contents' => fopen($tempPath, 'r'),
                    'filename' => $originalName,
                ],
                [
                    'name' => 'model',
                    'contents' => 'whisper-1',
                ],
                [
                    'name' => 'response_format',
                    'contents' => 'verbose_json', // Get detailed response with timestamps
                ],
            ];

            // Add language hint if provided
            if ($request->has('language') && !empty($request->language)) {
                $multipart[] = [
                    'name' => 'language',
                    'contents' => $request->language,
                ];
            }

            // Call OpenAI Whisper API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
            ])->timeout(120)->asMultipart()->post('https://api.openai.com/v1/audio/transcriptions', $multipart);

            if ($response->successful()) {
                $data = $response->json();
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'text' => $data['text'] ?? '',
                        'language' => $data['language'] ?? null,
                        'duration' => $data['duration'] ?? null,
                        'segments' => $data['segments'] ?? [], // Word-level timestamps if available
                    ],
                ]);
            }

            // Handle API errors
            $errorBody = $response->json();
            Log::error('OpenAI Whisper API error', [
                'status' => $response->status(),
                'error' => $errorBody,
            ]);

            return response()->json([
                'success' => false,
                'message' => $errorBody['error']['message'] ?? 'Transcription failed',
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Transcription error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during transcription',
            ], 500);
        }
    }

    /**
     * Transcribe audio from a URL (e.g., for already uploaded voice messages).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function transcribeUrl(Request $request)
    {
        $request->validate([
            'url' => 'required|url',
            'language' => 'nullable|string|max:10',
        ]);

        $apiKey = config('services.openai.api_key') ?? env('OPENAI_API_KEY');
        
        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Transcription service is not configured',
            ], 503);
        }

        try {
            // Download the audio file temporarily
            $audioContent = Http::timeout(60)->get($request->url);
            
            if (!$audioContent->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not download audio file',
                ], 400);
            }

            // Save to temp file
            $tempPath = storage_path('app/temp/' . uniqid('audio_') . '.mp3');
            $tempDir = dirname($tempPath);
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            file_put_contents($tempPath, $audioContent->body());

            try {
                // Prepare multipart form data
                $multipart = [
                    [
                        'name' => 'file',
                        'contents' => fopen($tempPath, 'r'),
                        'filename' => 'audio.mp3',
                    ],
                    [
                        'name' => 'model',
                        'contents' => 'whisper-1',
                    ],
                    [
                        'name' => 'response_format',
                        'contents' => 'verbose_json',
                    ],
                ];

                if ($request->has('language') && !empty($request->language)) {
                    $multipart[] = [
                        'name' => 'language',
                        'contents' => $request->language,
                    ];
                }

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                ])->timeout(120)->asMultipart()->post('https://api.openai.com/v1/audio/transcriptions', $multipart);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'text' => $data['text'] ?? '',
                            'language' => $data['language'] ?? null,
                            'duration' => $data['duration'] ?? null,
                            'segments' => $data['segments'] ?? [],
                        ],
                    ]);
                }

                $errorBody = $response->json();
                Log::error('OpenAI Whisper API error (URL)', [
                    'status' => $response->status(),
                    'error' => $errorBody,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $errorBody['error']['message'] ?? 'Transcription failed',
                ], $response->status());

            } finally {
                // Clean up temp file
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }
            }

        } catch (\Exception $e) {
            Log::error('Transcription URL error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during transcription',
            ], 500);
        }
    }
}
