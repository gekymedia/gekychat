<?php

namespace App\Http\Controllers;

use App\Services\BotService;
use App\Services\CugAdmissionsApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BotController extends Controller
{
    private $botService;
    private $cugApiService;

    public function __construct(BotService $botService, CugAdmissionsApiService $cugApiService)
    {
        $this->botService = $botService;
        $this->cugApiService = $cugApiService;
    }

    /**
     * Process document upload for form filling
     */
    public function processDocument(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'conversation_id' => 'required|exists:conversations,id'
        ]);

        try {
            $file = $request->file('file');
            $filePath = $file->store('temp/documents');
            
            // Process document through CUG API
            $result = $this->cugApiService->processDocument(
                $filePath,
                $file->getClientOriginalName(),
                Auth::id()
            );

            // Clean up temp file
            Storage::delete($filePath);

            if ($result['success']) {
                // Send preview to conversation
                $this->botService->sendBotMessage(
                    $request->conversation_id,
                    $result['data']['preview'] ?? $result['message'] ?? 'Document processed successfully!'
                );
            } else {
                // Send error message to conversation
                $this->botService->sendBotMessage(
                    $request->conversation_id,
                    "❌ **Document Processing Failed**\n\n" . ($result['message'] ?? 'Unable to process document. Please try again.')
                );
            }

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Document processing error: ' . $e->getMessage());
            
            // Send error to conversation
            $this->botService->sendBotMessage(
                $request->conversation_id,
                "❌ **Error Processing Document**\n\n" . $e->getMessage()
            );

            return response()->json([
                'success' => false,
                'message' => 'Error processing document: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit application to CUG system
     */
    public function submitApplication(Request $request)
    {
        $request->validate([
            'form_data' => 'required|array',
            'conversation_id' => 'required|exists:conversations,id',
            'application_type' => 'required|string|in:undergraduate,postgraduate,access'
        ]);

        try {
            // Add application type to form data
            $formData = $request->form_data;
            $formData['application_type'] = $request->application_type;
            $formData['submitted_by_user_id'] = Auth::id();

            $result = $this->cugApiService->submitApplication($formData, Auth::id());

            if ($result['success']) {
                $applicationId = $result['data']['application_id'] ?? $result['data']['reference_number'] ?? 'Unknown';
                
                // Notify user in chat
                $this->botService->sendBotMessage(
                    $request->conversation_id,
                    "🎉 **Application Submitted Successfully!**\n\n" .
                    "Your application ID: **{$applicationId}**\n" .
                    "Type: **" . strtoupper($request->application_type) . "**\n" .
                    "You will receive a confirmation email shortly.\n\n" .
                    "You can check your application status anytime by typing 'check status'."
                );
            } else {
                // Send error message to conversation
                $this->botService->sendBotMessage(
                    $request->conversation_id,
                    "❌ **Application Submission Failed**\n\n" . ($result['message'] ?? 'Unable to submit application. Please try again.')
                );
            }

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Application submission error: ' . $e->getMessage());
            
            // Send error to conversation
            $this->botService->sendBotMessage(
                $request->conversation_id,
                "❌ **Error Submitting Application**\n\n" . $e->getMessage()
            );

            return response()->json([
                'success' => false,
                'message' => 'Error submitting application: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check application status
     */
    public function checkApplicationStatus(Request $request)
    {
        $request->validate([
            'application_id' => 'required|string',
            'conversation_id' => 'required|exists:conversations,id'
        ]);

        try {
            $result = $this->cugApiService->checkApplicationStatus($request->application_id);

            if ($result['success']) {
                $status = $result['data']['status'] ?? 'Unknown';
                $details = $result['data']['details'] ?? '';
                
                $statusMessage = "📋 **Application Status**\n\n" .
                               "Application ID: **{$request->application_id}**\n" .
                               "Status: **{$status}**\n";
                
                if (!empty($details)) {
                    $statusMessage .= "\nDetails: {$details}";
                }

                $this->botService->sendBotMessage($request->conversation_id, $statusMessage);
            } else {
                $this->botService->sendBotMessage(
                    $request->conversation_id,
                    "❌ **Status Check Failed**\n\n" . ($result['message'] ?? 'Unable to check application status.')
                );
            }

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Status check error: ' . $e->getMessage());
            
            $this->botService->sendBotMessage(
                $request->conversation_id,
                "❌ **Error Checking Status**\n\n" . $e->getMessage()
            );

            return response()->json([
                'success' => false,
                'message' => 'Error checking application status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available programmes
     */
    public function getProgrammes(Request $request)
    {
        $request->validate([
            'type' => 'sometimes|string|in:undergraduate,postgraduate,all',
            'category' => 'sometimes|string',
            'conversation_id' => 'required|exists:conversations,id'
        ]);

        try {
            $type = $request->type ?? 'all';
            $category = $request->category ?? null;

            $result = $this->cugApiService->getProgrammes($type, $category);

            if ($result['success']) {
                $programmes = $result['data']['programmes'] ?? [];
                
                if (empty($programmes)) {
                    $this->botService->sendBotMessage(
                        $request->conversation_id,
                        "📚 **Available Programmes**\n\nNo programmes found for the specified criteria."
                    );
                } else {
                    $message = "📚 **Available Programmes**\n\n";
                    
                    foreach ($programmes as $index => $programme) {
                        if ($index >= 10) { // Limit to 10 programmes
                            $message .= "\n... and more! Visit our website for complete list.";
                            break;
                        }
                        
                        $name = $programme['name'] ?? 'Unknown Programme';
                        $code = $programme['code'] ?? '';
                        $duration = $programme['duration'] ?? '';
                        
                        $message .= "• **{$name}**";
                        if ($code) $message .= " ({$code})";
                        if ($duration) $message .= " - {$duration}\n";
                        else $message .= "\n";
                    }
                    
                    $this->botService->sendBotMessage($request->conversation_id, $message);
                }
            } else {
                $this->botService->sendBotMessage(
                    $request->conversation_id,
                    "❌ **Unable to fetch programmes**\n\n" . ($result['message'] ?? 'Please try again later.')
                );
            }

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Programmes fetch error: ' . $e->getMessage());
            
            $this->botService->sendBotMessage(
                $request->conversation_id,
                "❌ **Error Fetching Programmes**\n\n" . $e->getMessage()
            );

            return response()->json([
                'success' => false,
                'message' => 'Error fetching programmes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extract applicant information from text
     */
    public function extractApplicantInfo(Request $request)
    {
        $request->validate([
            'text' => 'required|string',
            'document_type' => 'sometimes|string',
            'conversation_id' => 'required|exists:conversations,id'
        ]);

        try {
            $result = $this->cugApiService->extractApplicantInfo(
                $request->text,
                $request->document_type,
                'gekychat_bot'
            );

            if ($result['success']) {
                $extractedData = $result['data'] ?? [];
                
                $message = "🔍 **Information Extracted**\n\n";
                $message .= "I've processed your information. Here's what I found:\n\n";
                
                foreach ($extractedData as $key => $value) {
                    if (!empty($value)) {
                        $formattedKey = ucfirst(str_replace('_', ' ', $key));
                        $message .= "• **{$formattedKey}**: {$value}\n";
                    }
                }
                
                $this->botService->sendBotMessage($request->conversation_id, $message);
            } else {
                $this->botService->sendBotMessage(
                    $request->conversation_id,
                    "❌ **Information Extraction Failed**\n\n" . ($result['message'] ?? 'Unable to extract information from the provided text.')
                );
            }

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Information extraction error: ' . $e->getMessage());
            
            $this->botService->sendBotMessage(
                $request->conversation_id,
                "❌ **Error Extracting Information**\n\n" . $e->getMessage()
            );

            return response()->json([
                'success' => false,
                'message' => 'Error extracting information: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get FAQ answer from CUG AI system
     */
    public function getFaqAnswer(Request $request)
    {
        $request->validate([
            'question' => 'required|string',
            'conversation_id' => 'required|exists:conversations,id',
            'context' => 'sometimes|string|in:admissions,programmes,general'
        ]);

        try {
            $result = $this->cugApiService->getFaqAnswer(
                $request->question,
                $request->context ?? 'admissions'
            );

            if ($result['success']) {
                $answer = $result['data']['answer'] ?? $result['message'] ?? 'I found some information for you!';
                
                $this->botService->sendBotMessage(
                    $request->conversation_id,
                    "🤔 **Your Question:** {$request->question}\n\n✅ **Answer:** {$answer}"
                );
            } else {
                // Fallback to bot's internal FAQ system
                $fallbackAnswer = $this->botService->handleFaqQuestion($request->question);
                $this->botService->sendBotMessage($request->conversation_id, $fallbackAnswer);
            }

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('FAQ answer error: ' . $e->getMessage());
            
            // Fallback to bot's internal FAQ system
            $fallbackAnswer = $this->botService->handleFaqQuestion($request->question);
            $this->botService->sendBotMessage($request->conversation_id, $fallbackAnswer);

            return response()->json([
                'success' => false,
                'message' => 'Error getting FAQ answer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check CUG API health
     */
    public function healthCheck()
    {
        try {
            $health = $this->cugApiService->healthCheck();
            
            return response()->json([
                'service' => 'CUG Admissions API',
                'status' => $health['success'] ? 'healthy' : 'unhealthy',
                'response_time_ms' => $health['response_time_ms'] ?? 'N/A',
                'timestamp' => now()->toISOString(),
                'details' => $health
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'service' => 'CUG Admissions API',
                'status' => 'error',
                'message' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * Validate API configuration
     */
    public function validateConfig()
    {
        try {
            $config = $this->cugApiService->validateConfig();
            
            return response()->json([
                'service' => 'CUG Admissions API Configuration',
                'valid' => $config['valid'],
                'issues' => $config['issues'],
                'config' => $config['config'],
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'service' => 'CUG Admissions API Configuration',
                'valid' => false,
                'issues' => ['Validation error: ' . $e->getMessage()],
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }
}