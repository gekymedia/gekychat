<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageStatus;
use App\Models\User;
use App\Models\BotSetting;
use App\Services\FeatureFlagService;
use App\Services\BlackTaskService;
use App\Events\MessageSent;
use App\Events\MessageStatusUpdated;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class BotService
{
    private $botUserId;
    private $cugApiService;

    public function __construct()
    {
        $this->botUserId = User::where('phone', '0000000000')->value('id');
    }

    /**
     * Handle bot replies for direct messages
     */
    public function handleDirectMessage(int $conversationId, string $messageText, int $senderId): void
    {
        if (!$this->botUserId) {
            Log::warning('Bot user not found');
            return;
        }

        $conversation = Conversation::with('members')->find($conversationId);
        if (!$conversation || !$conversation->isParticipant($this->botUserId)) {
            return;
        }

        // Process the message and generate response using OpenAI as primary
        $response = $this->generateResponse($messageText, $conversationId, $senderId);

        if ($response) {
            $this->sendBotMessage($conversationId, $response);
        }
    }

    /**
     * Generate bot response - OpenAI is the PRIMARY responder
     * 
     * OpenAI handles ALL queries with contextual knowledge:
     * - General questions → OpenAI with general assistant prompt
     * - Admission queries → OpenAI with CUG admission knowledge injected
     * - Task queries → OpenAI with BlackTask API integration
     * 
     * No more rule-based responses - everything is AI-powered!
     */
    private function generateResponse(string $messageText, int $conversationId, int $senderId): ?string
    {
        $input = trim($messageText);

        // Handle empty input
        if (empty($input)) {
            return "I didn't receive any message. How can I help you today?";
        }

        // Get user
        $user = User::find($senderId);

        // Check rate limits
        if (!$this->checkRateLimit($user)) {
            return "You've reached your AI usage limit for today. Please try again tomorrow or upgrade your plan for more AI interactions.";
        }

        // Check if OpenAI API key is configured
        $openAiKey = config('services.openai.api_key') ?: env('OPENAI_API_KEY');
        
        if (empty($openAiKey)) {
            Log::warning('OpenAI API key not configured, falling back to basic response');
            return "I'm currently unable to process your request. Please try again later or contact support.";
        }

        // Detect query type for context injection
        $queryType = $this->detectQueryType($input);
        
        // Get conversation context
        $conversationContext = $this->getConversationContext($conversationId, 5);
        
        // Generate response using OpenAI with appropriate context
        $response = $this->generateOpenAiResponse($input, $queryType, $conversationContext, $user);
        
        if ($response) {
            $response = $this->applySafetyFilters($response);
            $this->trackUsage($user, 'openai');
            return $response;
        }

        // Fallback if OpenAI fails
        Log::error('OpenAI response failed');
        return "I'm having trouble processing your request right now. Please try again in a moment.";
    }

    /**
     * Detect the type of query for context injection
     * Returns: 'general', 'admission', 'task', or 'mixed'
     */
    private function detectQueryType(string $input): string
    {
        $inputLower = mb_strtolower($input);
        
        // Task-related keywords
        $taskKeywords = [
            'todo', 'task', 'remind', 'reminder', 'blacktask',
            'add task', 'create task', 'new task', 'my tasks',
            'show tasks', 'list tasks', 'complete task', 'done task',
            'delete task', 'remove task', 'task stats', 'pending tasks'
        ];
        
        // Admission-related keywords
        $admissionKeywords = [
            'admission', 'admissions', 'apply', 'application',
            'postgraduate', 'undergraduate', 'masters', 'mphil', 'phd',
            'degree', 'bsc', 'msc', 'mba', 'programme', 'program',
            'course', 'nursing', 'midwifery', 'public health',
            'access course', 'cug', 'catholic university', 'fiapre',
            'sunyani', 'priority admissions', 'voucher', 'form',
            'option 1', 'option 2', 'full processing', 'self application',
            'tuition', 'fee', 'fees', 'scholarship', 'deadline',
            'requirement', 'requirements', 'document', 'documents'
        ];
        
        $isTask = false;
        $isAdmission = false;
        
        foreach ($taskKeywords as $keyword) {
            if (str_contains($inputLower, $keyword)) {
                $isTask = true;
                break;
            }
        }
        
        foreach ($admissionKeywords as $keyword) {
            if (str_contains($inputLower, $keyword)) {
                $isAdmission = true;
                break;
            }
        }
        
        if ($isTask && $isAdmission) {
            return 'mixed';
        } elseif ($isTask) {
            return 'task';
        } elseif ($isAdmission) {
            return 'admission';
        }
        
        return 'general';
    }

    /**
     * Generate response using OpenAI with contextual knowledge injection
     */
    private function generateOpenAiResponse(string $messageText, string $queryType, array $conversationContext, User $user): ?string
    {
        try {
            $openAiKey = config('services.openai.api_key') ?: env('OPENAI_API_KEY');
            
            // Build system prompt based on query type
            $systemPrompt = $this->buildSystemPrompt($queryType, $user);
            
            // Build messages array
            $messages = [
                ['role' => 'system', 'content' => $systemPrompt]
            ];
            
            // Add conversation context
            foreach ($conversationContext as $ctx) {
                $messages[] = [
                    'role' => $ctx['sender'] === 'Bot' ? 'assistant' : 'user',
                    'content' => $ctx['message'],
                ];
            }
            
            // Add current message
            $messages[] = ['role' => 'user', 'content' => $messageText];
            
            // If task query, check if we need to execute task actions
            if ($queryType === 'task' || $queryType === 'mixed') {
                $taskAction = $this->detectTaskAction($messageText, $user);
                if ($taskAction) {
                    // Execute task action and include result in context
                    $taskResult = $this->executeTaskAction($taskAction, $user);
                    $messages[] = [
                        'role' => 'system',
                        'content' => "Task action result: " . json_encode($taskResult)
                    ];
                }
            }
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $openAiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini', // Cost-effective and capable
                'messages' => $messages,
                'max_tokens' => 1000,
                'temperature' => 0.7,
                'presence_penalty' => 0.1,
                'frequency_penalty' => 0.1,
            ]);

            if ($response->successful()) {
                return $response->json('choices.0.message.content');
            }
            
            Log::error('OpenAI API error: ' . $response->status() . ' - ' . $response->body());
            return null;
            
        } catch (\Exception $e) {
            Log::error('OpenAI API exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Build system prompt based on query type
     * Injects relevant knowledge for admission or task queries
     */
    private function buildSystemPrompt(string $queryType, User $user): string
    {
        $basePrompt = "You are GekyChat AI, a friendly and helpful virtual assistant. You are conversational, empathetic, and provide clear, concise responses. You use emojis sparingly to add warmth. You are knowledgeable about many topics and always aim to be helpful.";
        
        switch ($queryType) {
            case 'admission':
                return $basePrompt . "\n\n" . $this->getAdmissionKnowledge();
                
            case 'task':
                return $basePrompt . "\n\n" . $this->getTaskKnowledge($user);
                
            case 'mixed':
                return $basePrompt . "\n\n" . $this->getAdmissionKnowledge() . "\n\n" . $this->getTaskKnowledge($user);
                
            default:
                return $basePrompt . "\n\n" . 
                    "You can help with:\n" .
                    "1. General questions and conversations\n" .
                    "2. CUG (Catholic University of Ghana) admissions - if the user asks about admissions, programmes, or applications\n" .
                    "3. Task management via BlackTask - if the user wants to manage their todo list\n" .
                    "4. Any other assistance the user needs\n\n" .
                    "Be helpful and proactive. If you detect the user might benefit from admission info or task management, you can mention these capabilities.";
        }
    }

    /**
     * Get CUG Admission knowledge for context injection
     */
    private function getAdmissionKnowledge(): string
    {
        return <<<EOT
## CUG ADMISSIONS KNOWLEDGE BASE

You are an expert on Catholic University of Ghana (CUG) admissions, processed through Priority Admissions Office (PSA).

### APPLICATION OPTIONS:

**OPTION 1 - Full Processing (Recommended):**
- Postgraduate: GHC 250
- Undergraduate: GHC 200
- What's included: Voucher generation, form filling, direct submission to CUG, document printing, email/SMS confirmation, WhatsApp group access, follow-up until admission, Statement of Purpose writing

**OPTION 2 - Voucher + Self Application:**
- Postgraduate: GHC 150
- Undergraduate: GHC 115
- Application Portal: https://cug.prioritysolutionsagency.com
- User fills forms, uploads documents, prints, and submits via EMS or at CUG Campus

### PAYMENT OPTIONS:
1. USSD (All Networks): Dial *713*0049#
2. MTN MoMo: 0543992073 (Merchant ID: 670113)
3. GCB Bank: Account Name: PRIORITY ADMISSIONS OFFICE, Account Number: 7251420000128

### POSTGRADUATE PROGRAMMES:
- MPhil and Master of Public Health
- MSc Data Science
- MBA (Accounting, Finance, HRM, Marketing)
- MPhil Educational Psychology, Guidance & Counselling
- MPhil Educational Administration & Management
- Postgraduate Diploma in Education (PGDE)
- PhD in Management, Education
- Doctor of Business Administration

### UNDERGRADUATE PROGRAMMES:
- BSc Nursing
- BSc Midwifery
- BSc Public Health Nursing
- Various other degree programmes
- Streams: Regular, Weekend, Sandwich (for Diploma holders)

### ACCESS COURSE:
- Fee: GHC 1,200
- Format: Online via Zoom (3-4 weeks), Exams at CUG Campus Sunyani-Fiapre
- Leads to: BSc Nursing, BSc Public Health Nursing, BSc Midwifery (Level 200)
- Validity: 2 years
- Tuition after admission: ~GHC 4,400-4,500 per semester

### REQUIRED DOCUMENTS:
**Postgraduate:**
- Passport picture
- 1st Degree Certificate and Transcript
- CV
- Statement of Purpose
- Research Proposal (10 pages for PhD)
- Signature (via signwell.com)

**Undergraduate:**
- Passport picture
- WASSCE Results/Certificate
- Additional certificates (if applicable)
- Signature

### CAMPUSES:
- Sunyani-Fiapre (Main)
- Accra
- Tamale

### ACADEMIC PERIODS:
- September 2025
- January 2026
- June 2026

When users ask about admissions, provide helpful, accurate information based on this knowledge. Guide them through the process step by step.
EOT;
    }

    /**
     * Get BlackTask knowledge for context injection
     */
    private function getTaskKnowledge(User $user): string
    {
        $phone = $user->phone ?? 'not set';
        $blackTaskUrl = config('services.blacktask.url', 'https://blacktask.app');
        
        return <<<EOT
## BLACKTASK TODO LIST INTEGRATION

You can help users manage their tasks through BlackTask integration.

### USER INFO:
- Phone: {$phone}
- BlackTask URL: {$blackTaskUrl}

### AVAILABLE ACTIONS:
When users want to manage tasks, you can help them with:

1. **Add Task**: Create a new task
   - Ask for: task title, optional due date, optional priority
   - Priority levels: High (urgent/important), Medium (default), Low (minor/later)
   
2. **List Tasks**: Show their pending tasks
   - Can filter by: all, today, overdue, completed
   
3. **Complete Task**: Mark a task as done
   - Need: task ID or task title to identify
   
4. **Delete Task**: Remove a task
   - Need: task ID or task title to identify
   
5. **Task Statistics**: Show completion stats

### NATURAL LANGUAGE SUPPORT:
Users can use natural language like:
- "Remind me to call John tomorrow"
- "Add task: Buy groceries"
- "What are my tasks for today?"
- "Mark task 5 as done"
- "Delete the grocery task"

### RESPONSE FORMAT:
When showing tasks, format them nicely with:
- ✅ for completed
- ⏳ for pending
- 🔴 for high priority
- 🟡 for medium priority
- 🟢 for low priority
- 📅 for due dates

If the user doesn't have a BlackTask account, guide them to register at {$blackTaskUrl} using their phone number ({$phone}).

Be helpful and proactive in task management. Confirm actions before executing them.
EOT;
    }

    /**
     * Detect if user wants to perform a task action
     */
    private function detectTaskAction(string $input, User $user): ?array
    {
        $inputLower = mb_strtolower($input);
        
        // Add task patterns
        if (preg_match('/(add|create|new|remind|set)\s*(task|todo|reminder)?[:\s]+(.+)/i', $input, $matches)) {
            return [
                'action' => 'add',
                'title' => trim($matches[3] ?? $matches[0]),
                'phone' => $user->phone
            ];
        }
        
        // List tasks patterns
        if (preg_match('/(show|list|get|what are|view)\s*(my)?\s*(task|todo|pending)/i', $inputLower)) {
            return [
                'action' => 'list',
                'phone' => $user->phone
            ];
        }
        
        // Complete task patterns
        if (preg_match('/(complete|done|finish|mark.*done)\s*(task|todo)?\s*#?(\d+)/i', $input, $matches)) {
            return [
                'action' => 'complete',
                'task_id' => (int)$matches[3],
                'phone' => $user->phone
            ];
        }
        
        // Delete task patterns
        if (preg_match('/(delete|remove|cancel)\s*(task|todo)?\s*#?(\d+)/i', $input, $matches)) {
            return [
                'action' => 'delete',
                'task_id' => (int)$matches[3],
                'phone' => $user->phone
            ];
        }
        
        // Stats patterns
        if (preg_match('/(task|todo)\s*(stat|statistic|summary|overview)/i', $inputLower)) {
            return [
                'action' => 'stats',
                'phone' => $user->phone
            ];
        }
        
        return null;
    }

    /**
     * Execute task action via BlackTask API
     */
    private function executeTaskAction(array $action, User $user): array
    {
        $blackTaskService = app(BlackTaskService::class);
        
        if (!$blackTaskService->isConfigured()) {
            return [
                'success' => false,
                'message' => 'BlackTask integration is not configured'
            ];
        }
        
        $phone = $action['phone'] ?? $user->phone;
        
        if (!$phone) {
            return [
                'success' => false,
                'message' => 'User phone number not available'
            ];
        }
        
        switch ($action['action']) {
            case 'add':
                $priority = $blackTaskService->parsePriority($action['title'] ?? '');
                return $blackTaskService->createTask($phone, [
                    'title' => $action['title'],
                    'priority' => $priority
                ]);
                
            case 'list':
                $result = $blackTaskService->getTasks($phone);
                if ($result['success']) {
                    $result['formatted'] = $blackTaskService->formatTasksForChat($result['tasks']);
                }
                return $result;
                
            case 'complete':
                return $blackTaskService->completeTask($phone, $action['task_id']);
                
            case 'delete':
                return $blackTaskService->deleteTask($phone, $action['task_id']);
                
            case 'stats':
                return $blackTaskService->getStatistics($phone);
                
            default:
                return [
                    'success' => false,
                    'message' => 'Unknown action'
                ];
        }
    }

    /**
     * Get conversation context (recent messages)
     */
    private function getConversationContext(int $conversationId, int $limit = 5): array
    {
        $messages = Message::where('conversation_id', $conversationId)
            ->with('sender:id,name')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse();

        $context = [];
        foreach ($messages as $message) {
            $isBot = $message->sender_id === $this->botUserId;
            $context[] = [
                'sender' => $isBot ? 'Bot' : 'User',
                'message' => $message->body,
            ];
        }

        return $context;
    }

    /**
     * Apply safety filters to AI responses
     */
    private function applySafetyFilters(string $response): string
    {
        // List of potentially problematic patterns
        $unsafePatterns = [
            '/\b(kill|murder|suicide|bomb|terrorist|hack|exploit)\b/i',
            '/\b(fuck|shit|damn|bitch|asshole)\b/i',
        ];

        foreach ($unsafePatterns as $pattern) {
            if (preg_match($pattern, $response)) {
                Log::warning('Unsafe content detected in AI response, filtering...');
                return "I'm sorry, I cannot provide that type of information. Is there something else I can help you with?";
            }
        }

        // Limit response length
        $maxLength = 2000;
        if (strlen($response) > $maxLength) {
            $response = substr($response, 0, $maxLength) . '...';
        }

        return $response;
    }

    /**
     * Check if user has exceeded AI rate limit
     */
    private function checkRateLimit(User $user): bool
    {
        $settings = json_decode($user->settings ?? '{}', true);
        $plan = $settings['plan'] ?? 'free';
        
        $dailyLimit = match($plan) {
            'free' => 50,      // Increased for better UX
            'premium' => 200,
            'pro' => 500,
            'enterprise' => 1000,
            default => 50,
        };
        
        $lastUsedAt = $user->ai_last_used_at;
        if ($lastUsedAt && is_string($lastUsedAt)) {
            $lastUsedAt = Carbon::parse($lastUsedAt);
        }
        
        if (!$lastUsedAt || $lastUsedAt->lt(Carbon::today())) {
            $user->ai_usage_count = 0;
            $user->save();
        }
        
        return $user->ai_usage_count < $dailyLimit;
    }

    /**
     * Track AI usage for a user
     */
    private function trackUsage(User $user, string $type): void
    {
        $lastUsedAt = $user->ai_last_used_at;
        if ($lastUsedAt && is_string($lastUsedAt)) {
            $lastUsedAt = Carbon::parse($lastUsedAt);
        }
        
        if (!$lastUsedAt || $lastUsedAt->lt(Carbon::today())) {
            $user->ai_usage_count = 0;
        }
        
        $user->ai_usage_count += 1;
        $user->ai_last_used_at = now();
        $user->save();
        
        Log::info('AI usage tracked', [
            'user_id' => $user->id,
            'type' => $type,
            'count' => $user->ai_usage_count,
        ]);
    }

    /**
     * Send message as bot
     */
    public function sendBotMessage(int $conversationId, string $response): void
    {
        $botMessage = Message::create([
            'conversation_id' => $conversationId,
            'sender_id'       => $this->botUserId,
            'body'            => $response,
        ]);

        MessageStatus::create([
            'message_id' => $botMessage->id,
            'user_id'    => $this->botUserId,
            'status'     => MessageStatus::STATUS_SENT,
            'updated_at' => now(),
        ]);

        $botMessage->load('sender');

        // Small delay to ensure user's message appears first
        usleep(500000);
        
        broadcast(new MessageSent($botMessage));
        broadcast(new MessageStatusUpdated(
            $botMessage->id,
            MessageStatus::STATUS_SENT,
            $conversationId
        ));
    }

    /**
     * Call CUG API (for future integration)
     */
    public function callCugApi(string $endpoint, array $data = []): array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'X-API-Key' => config('services.cug_admissions.api_key', ''),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post("https://cug.prioritysolutionsagency.com/api/v1/bot/{$endpoint}", $data);

            if ($response->successful()) {
                return $response->json();
            }

            return [
                'success' => false,
                'message' => 'API request failed',
                'status' => $response->status()
            ];

        } catch (\Exception $e) {
            Log::error("CUG API call failed: {$endpoint} - " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Service temporarily unavailable'
            ];
        }
    }
}
