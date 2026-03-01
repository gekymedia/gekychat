<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageStatus;
use App\Models\User;
use App\Models\BotContact;
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
    private $cugApiService;

    public function __construct()
    {
        // No longer storing a single bot user ID - we now support multiple bots
    }

    /**
     * Handle bot replies for direct messages
     * Routes to the appropriate bot based on which bot the user is chatting with
     */
    public function handleDirectMessage(int $conversationId, string $messageText, int $senderId): void
    {
        $conversation = Conversation::with('members')->find($conversationId);
        if (!$conversation) {
            return;
        }

        // Find which bot is in this conversation
        $botUser = null;
        $botContact = null;
        
        foreach ($conversation->members as $member) {
            $bot = BotContact::getByPhone($member->phone ?? '');
            if ($bot && $bot->is_active) {
                $botUser = $member;
                $botContact = $bot;
                break;
            }
        }

        if (!$botUser || !$botContact) {
            Log::debug('No active bot found in conversation', ['conversation_id' => $conversationId]);
            return;
        }

        // Route to appropriate handler based on bot type
        $response = match ($botContact->bot_type) {
            BotContact::TYPE_GENERAL => $this->handleGeneralBot($messageText, $conversationId, $senderId),
            BotContact::TYPE_ADMISSIONS => $this->handleAdmissionsBot($messageText, $conversationId, $senderId),
            BotContact::TYPE_TASKS => $this->handleTasksBot($messageText, $conversationId, $senderId),
            default => $this->handleGeneralBot($messageText, $conversationId, $senderId),
        };

        if ($response) {
            $this->sendBotMessage($conversationId, $response, $botUser->id);
        }
    }

    /**
     * Handle GekyChat AI (General Assistant)
     * A friendly, general-purpose AI assistant
     */
    private function handleGeneralBot(string $messageText, int $conversationId, int $senderId): ?string
    {
        $input = trim($messageText);
        if (empty($input)) {
            return "I didn't receive any message. How can I help you today?";
        }

        $user = User::find($senderId);
        if (!$this->checkRateLimit($user)) {
            return "You've reached your AI usage limit for today. Please try again tomorrow or upgrade your plan for more AI interactions.";
        }

        $openAiKey = config('services.openai.api_key') ?: env('OPENAI_API_KEY');
        if (empty($openAiKey)) {
            Log::warning('OpenAI API key not configured');
            return "I'm currently unable to process your request. Please try again later.";
        }

        $conversationContext = $this->getConversationContext($conversationId, 5);
        
        $systemPrompt = <<<EOT
You are GekyChat AI, a friendly and helpful virtual assistant. You are conversational, empathetic, and provide clear, concise responses. You use emojis sparingly to add warmth.

You can help with:
1. General questions and conversations
2. Information and explanations on various topics
3. Writing assistance and suggestions
4. Problem-solving and brainstorming

If users ask about:
- CUG admissions, applications, or university programmes → Suggest they chat with "CUG Admissions" bot for specialized help
- Task management, todos, or reminders → Suggest they chat with "BlackTask" bot for task management

Be helpful, friendly, and conversational!
EOT;

        $response = $this->callOpenAI($systemPrompt, $conversationContext, $input);
        
        if ($response) {
            $response = $this->applySafetyFilters($response);
            $this->trackUsage($user, 'general');
            return $response;
        }

        return "I'm having trouble processing your request right now. Please try again in a moment.";
    }

    /**
     * Handle CUG Admissions Bot
     * Specialized for Catholic University of Ghana admission queries
     */
    private function handleAdmissionsBot(string $messageText, int $conversationId, int $senderId): ?string
    {
        $input = trim($messageText);
        if (empty($input)) {
            return "Hello! I'm the CUG Admissions assistant. How can I help you with your admission to Catholic University of Ghana today?";
        }

        $user = User::find($senderId);
        if (!$this->checkRateLimit($user)) {
            return "You've reached your AI usage limit for today. Please try again tomorrow.";
        }

        $openAiKey = config('services.openai.api_key') ?: env('OPENAI_API_KEY');
        if (empty($openAiKey)) {
            return "I'm currently unable to process your request. Please try again later or contact Priority Admissions Office directly.";
        }

        $conversationContext = $this->getConversationContext($conversationId, 5);
        
        $systemPrompt = $this->getAdmissionsSystemPrompt();

        $response = $this->callOpenAI($systemPrompt, $conversationContext, $input);
        
        if ($response) {
            $response = $this->applySafetyFilters($response);
            $this->trackUsage($user, 'admissions');
            return $response;
        }

        return "I'm having trouble processing your request. Please try again or contact Priority Admissions Office at 0543992073.";
    }

    /**
     * Handle BlackTask Bot
     * Specialized for task/todo management
     */
    private function handleTasksBot(string $messageText, int $conversationId, int $senderId): ?string
    {
        $input = trim($messageText);
        if (empty($input)) {
            return "Hello! I'm BlackTask, your personal task manager. You can:\n\n" .
                   "📝 **Add task**: \"Add task: Buy groceries tomorrow\"\n" .
                   "📋 **List tasks**: \"Show my tasks\" or \"What's on my list?\"\n" .
                   "✅ **Complete task**: \"Complete task #1\" or \"Done with task 1\"\n" .
                   "🗑️ **Delete task**: \"Delete task #1\"\n" .
                   "📊 **Statistics**: \"Show my task stats\"\n\n" .
                   "What would you like to do?";
        }

        $user = User::find($senderId);
        if (!$this->checkRateLimit($user)) {
            return "You've reached your AI usage limit for today. Please try again tomorrow.";
        }

        $openAiKey = config('services.openai.api_key') ?: env('OPENAI_API_KEY');
        
        // First, try to detect and execute task actions
        $taskAction = $this->detectTaskAction($input, $user);
        $taskResult = null;
        
        if ($taskAction) {
            $taskResult = $this->executeTaskAction($taskAction, $user);
        }

        // If no OpenAI key, return task result directly
        if (empty($openAiKey)) {
            if ($taskResult) {
                return $this->formatTaskResult($taskResult);
            }
            return "I can help you manage tasks! Try commands like:\n- Add task: [your task]\n- Show my tasks\n- Complete task #[number]";
        }

        $conversationContext = $this->getConversationContext($conversationId, 5);
        
        $systemPrompt = $this->getTasksSystemPrompt($user, $taskResult);

        $response = $this->callOpenAI($systemPrompt, $conversationContext, $input);
        
        if ($response) {
            $response = $this->applySafetyFilters($response);
            $this->trackUsage($user, 'tasks');
            return $response;
        }

        // Fallback to formatted task result
        if ($taskResult) {
            return $this->formatTaskResult($taskResult);
        }

        return "I'm having trouble right now. Please try again in a moment.";
    }

    /**
     * Get the CUG Admissions system prompt with full knowledge base
     */
    private function getAdmissionsSystemPrompt(): string
    {
        return <<<EOT
You are the CUG Admissions Assistant, an expert on Catholic University of Ghana (CUG) admissions, processed through Priority Admissions Office (PSA).

Your role is to help prospective students with:
- Programme information
- Application process
- Fees and payment
- Requirements and documents
- Deadlines and academic periods

## APPLICATION OPTIONS:

**OPTION 1 - Full Processing (Recommended):**
- Postgraduate: GHC 250
- Undergraduate: GHC 200
- What's included: Voucher generation, form filling, direct submission to CUG, document printing, email/SMS confirmation, WhatsApp group access, follow-up until admission, Statement of Purpose writing

**OPTION 2 - Voucher + Self Application:**
- Postgraduate: GHC 150
- Undergraduate: GHC 115
- Application Portal: https://cug.prioritysolutionsagency.com
- User fills forms, uploads documents, prints, and submits via EMS or at CUG Campus

## PAYMENT OPTIONS:
1. USSD (All Networks): Dial *713*0049#
2. MTN MoMo: 0543992073 (Merchant ID: 670113)
3. GCB Bank: Account Name: PRIORITY ADMISSIONS OFFICE, Account Number: 7251420000128

## POSTGRADUATE PROGRAMMES:
- MPhil and Master of Public Health
- MSc Data Science
- MBA (Accounting, Finance, HRM, Marketing)
- MPhil Educational Psychology, Guidance & Counselling
- MPhil Educational Administration & Management
- Postgraduate Diploma in Education (PGDE)
- PhD in Management, Education
- Doctor of Business Administration

## UNDERGRADUATE PROGRAMMES:
- BSc Nursing
- BSc Midwifery
- BSc Public Health Nursing
- Various other degree programmes
- Streams: Regular, Weekend, Sandwich (for Diploma holders)

## ACCESS COURSE:
- Fee: GHC 1,200
- Format: Online via Zoom (3-4 weeks), Exams at CUG Campus Sunyani-Fiapre
- Leads to: BSc Nursing, BSc Public Health Nursing, BSc Midwifery (Level 200)
- Validity: 2 years
- Tuition after admission: ~GHC 4,400-4,500 per semester

## REQUIRED DOCUMENTS:
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

## CAMPUSES:
- Sunyani-Fiapre (Main)
- Accra
- Tamale

## ACADEMIC PERIODS:
- September 2025
- January 2026
- June 2026

## CONTACT:
- Phone: 0543992073
- WhatsApp: 0543992073

Be helpful, accurate, and guide users step by step. If they're ready to apply, explain the process clearly. Use emojis sparingly for warmth.
EOT;
    }

    /**
     * Get the BlackTask system prompt
     */
    private function getTasksSystemPrompt(User $user, ?array $taskResult = null): string
    {
        $phone = $user->phone ?? 'not set';
        $blackTaskUrl = config('services.blacktask.url', 'https://blacktask.app');
        
        $prompt = <<<EOT
You are BlackTask, a friendly and efficient personal task manager assistant.

## USER INFO:
- Name: {$user->name}
- Phone: {$phone}

## YOUR CAPABILITIES:
1. **Add Task**: Create new tasks with optional due dates and priorities
2. **List Tasks**: Show pending, completed, or all tasks
3. **Complete Task**: Mark tasks as done
4. **Delete Task**: Remove tasks
5. **Statistics**: Show task completion stats

## NATURAL LANGUAGE UNDERSTANDING:
Understand requests like:
- "Remind me to call John tomorrow" → Add task with tomorrow's date
- "Add task: Buy groceries" → Create new task
- "What's on my list?" → List pending tasks
- "Done with task 1" → Complete task #1
- "How am I doing?" → Show statistics

## RESPONSE STYLE:
- Be concise and action-oriented
- Use emojis for visual clarity:
  - ✅ Completed tasks
  - ⏳ Pending tasks
  - 🔴 High priority
  - 🟡 Medium priority
  - 🟢 Low priority
  - 📅 Due dates
- Confirm actions clearly
- Suggest next steps when appropriate

## PRIORITY LEVELS:
- High (urgent, important, ASAP, critical)
- Medium (default)
- Low (minor, later, whenever)
EOT;

        // Add task result context if available
        if ($taskResult) {
            $prompt .= "\n\n## TASK ACTION RESULT:\n" . json_encode($taskResult, JSON_PRETTY_PRINT);
            $prompt .= "\n\nUse this result to provide a natural, conversational response about what was done.";
        }

        return $prompt;
    }

    /**
     * Call OpenAI API
     */
    private function callOpenAI(string $systemPrompt, array $conversationContext, string $userMessage): ?string
    {
        try {
            $openAiKey = config('services.openai.api_key') ?: env('OPENAI_API_KEY');
            
            $messages = [
                ['role' => 'system', 'content' => $systemPrompt]
            ];
            
            foreach ($conversationContext as $ctx) {
                $messages[] = [
                    'role' => $ctx['sender'] === 'Bot' ? 'assistant' : 'user',
                    'content' => $ctx['message'],
                ];
            }
            
            $messages[] = ['role' => 'user', 'content' => $userMessage];
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $openAiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
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
        if (preg_match('/(show|list|get|what are|view|what\'s on)\s*(my)?\s*(task|todo|pending|list)/i', $inputLower)) {
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
        if (preg_match('/(task|todo)?\s*(stat|statistic|summary|overview|how am i doing)/i', $inputLower)) {
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
     * Format task result for display when OpenAI is not available
     */
    private function formatTaskResult(array $result): string
    {
        if (!$result['success']) {
            return "❌ " . ($result['message'] ?? 'Action failed');
        }

        if (isset($result['formatted'])) {
            return $result['formatted'];
        }

        if (isset($result['task'])) {
            return "✅ Task created: " . ($result['task']['title'] ?? 'New task');
        }

        if (isset($result['statistics'])) {
            $stats = $result['statistics'];
            return "📊 **Your Task Statistics**\n\n" .
                   "Total: " . ($stats['total'] ?? 0) . "\n" .
                   "Completed: " . ($stats['completed'] ?? 0) . "\n" .
                   "Pending: " . ($stats['pending'] ?? 0);
        }

        return "✅ " . ($result['message'] ?? 'Action completed');
    }

    /**
     * Get conversation context (recent messages)
     */
    private function getConversationContext(int $conversationId, int $limit = 5): array
    {
        // Get the bot user IDs to identify bot messages
        $botPhones = BotContact::where('is_active', true)->pluck('bot_number')->toArray();
        $botUserIds = User::whereIn('phone', $botPhones)->pluck('id')->toArray();

        $messages = Message::where('conversation_id', $conversationId)
            ->with('sender:id,name,phone')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse();

        $context = [];
        foreach ($messages as $message) {
            $isBot = in_array($message->sender_id, $botUserIds);
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
            'free' => 50,
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
            'bot_type' => $type,
            'count' => $user->ai_usage_count,
        ]);
    }

    /**
     * Send message as bot
     */
    public function sendBotMessage(int $conversationId, string $response, int $botUserId): void
    {
        $botMessage = Message::create([
            'conversation_id' => $conversationId,
            'sender_id'       => $botUserId,
            'body'            => $response,
        ]);

        MessageStatus::create([
            'message_id' => $botMessage->id,
            'user_id'    => $botUserId,
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
     * Get bot user by type
     */
    public static function getBotUserByType(string $type): ?User
    {
        $bot = BotContact::where('bot_type', $type)
            ->where('is_active', true)
            ->first();
            
        return $bot?->user();
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
