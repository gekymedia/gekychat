<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageStatus;
use App\Models\User;
use App\Models\BotSetting;
use App\Events\MessageSent;
use App\Events\MessageStatusUpdated;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BotService
{
    private $botUserId;
    private $cugApiService;

    public function __construct()
    {
        $this->botUserId = User::where('phone', '0000000000')->value('id');
        // Note: Remove CugAdmissionsApiService from constructor if not yet created
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

        // Process the message and generate response using CUG guidelines
        $response = $this->generateResponse($messageText, $conversationId, $senderId);

        if ($response) {
            $this->sendBotMessage($conversationId, $response);
        }
    }

    /**
     * Generate bot response using LLM or rule-based system
     */
    private function generateResponse(string $messageText, int $conversationId, int $senderId): ?string
    {
        $input = mb_strtolower(trim($messageText));

        // Handle empty input
        if (empty($input)) {
            return "I didn't receive any message. How can I help you with CUG admissions today?";
        }

        // Try LLM first if enabled
        if (BotSetting::isLlmEnabled()) {
            $llmResponse = $this->generateLlmResponse($messageText, $conversationId);
            if ($llmResponse) {
                return $llmResponse;
            }
            // Fall back to rule-based if LLM fails
            Log::warning('LLM response failed, falling back to rule-based');
        }

        // Use rule-based responses
        return $this->generateRuleBasedResponse($input);
    }

    /**
     * Generate response using LLM (Ollama)
     */
    private function generateLlmResponse(string $messageText, int $conversationId): ?string
    {
        try {
            $provider = BotSetting::getLlmProvider();
            
            if ($provider === 'ollama') {
                return $this->generateOllamaResponse($messageText);
            }
            
            // Add other providers here (OpenAI, etc.)
            return null;
        } catch (\Exception $e) {
            Log::error('LLM generation error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate response using Ollama API
     */
    private function generateOllamaResponse(string $messageText): ?string
    {
        $config = BotSetting::getOllamaConfig();
        $apiUrl = rtrim($config['api_url'], '/') . '/api/generate';
        
        $systemPrompt = "You are GekyBot, a helpful virtual assistant for CUG (Central University Ghana) admissions. 
You help users with undergraduate and postgraduate admissions information. 
Be friendly, concise, and helpful. If you don't know something, say so politely.";

        try {
            $response = Http::timeout(30)->post($apiUrl, [
                'model' => $config['model'],
                'prompt' => $messageText,
                'system' => $systemPrompt,
                'stream' => false,
                'options' => [
                    'temperature' => $config['temperature'],
                    'num_predict' => $config['max_tokens'],
                ],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['response'] ?? null;
            }
            
            Log::warning('Ollama API error: ' . $response->status());
            return null;
        } catch (\Exception $e) {
            Log::error('Ollama API exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate response using rule-based system (original logic)
     */
    private function generateRuleBasedResponse(string $input): ?string
    {

        // Basic commands
        if (str_contains($input, 'hello') || str_contains($input, 'hi') || str_contains($input, 'hey')) {
            return "Hello there! 👋 I'm GekyBot, your virtual assistant from *Priority Admissions Office*. I can help you with CUG undergraduate and postgraduate admissions. How can I assist you today?";
        }

        if (str_contains($input, 'time')) {
            return "The current time is " . now()->format('h:i A') . " and the date is " . now()->format('F j, Y');
        }

        if (str_contains($input, 'name')) {
            return "I'm GekyBot, your friendly CUG admissions assistant from *Priority Solutions Agency*! 🤖";
        }

        if (str_contains($input, 'help')) {
            return $this->getHelpMessage();
        }

        // Admissions-related queries
        if (str_contains($input, 'postgraduate') || str_contains($input, 'masters') || str_contains($input, 'mphil') || str_contains($input, 'phd')) {
            return $this->getPostgraduateGuidelines();
        }

        if (str_contains($input, 'undergraduate') || str_contains($input, 'degree') || str_contains($input, 'bsc') || str_contains($input, 'access course')) {
            return $this->getUndergraduateGuidelines();
        }

        if (str_contains($input, 'admission') || str_contains($input, 'apply') || str_contains($input, 'form')) {
            return $this->handleAdmissionInquiry($input);
        }

        if (str_contains($input, 'option 1') || str_contains($input, 'full processing')) {
            return $this->getOption1Details();
        }

        if (str_contains($input, 'option 2') || str_contains($input, 'voucher') || str_contains($input, 'self application')) {
            return $this->getOption2Details();
        }

        if (str_contains($input, 'payment') || str_contains($input, 'pay') || str_contains($input, 'momo') || str_contains($input, 'ussd')) {
            return $this->getPaymentOptions();
        }

        if (str_contains($input, 'programme') || str_contains($input, 'course') || str_contains($input, 'program')) {
            return $this->getAvailableProgrammes($input);
        }

        if (str_contains($input, 'access course') || str_contains($input, 'access')) {
            return $this->getAccessCourseInfo();
        }

        // FAQ questions
        if ($this->isFaqQuestion($input)) {
            return $this->handleFaqQuestion($input);
        }

        // ChatGPT requests
        if (str_contains($input, 'chatgpt') || str_contains($input, 'gpt') || str_contains($input, 'ai ')) {
            return $this->handleChatGPTRequest($input);
        }

        // Default response for unrecognized messages
        return $this->handleDefaultResponse($input);
    }

    /**
     * Get postgraduate admission guidelines
     */
    private function getPostgraduateGuidelines(): string
    {
        return "🎓 *CUG POSTGRADUATE ADMISSIONS GUIDELINES*\n" .
               "_Priority Admissions Office (PSA)_\n" .
               "─────────────────────────────\n\n" .

               "📍 *PERSONAL DETAILS REQUIRED*\n" .
               "• Title (Rev, Mr., Pastor, etc)\n" .
               "• Surname\n" .
               "• First Name\n" .
               "• Middle Name\n" .
               "• Gender\n" .
               "• Date of Birth\n" .
               "• Place of Birth\n" .
               "• Region of Birth\n" .
               "• Home Town\n" .
               "• Region of Hometown\n" .
               "• Country\n" .
               "• Marital Status\n" .
               "• No of Children\n" .
               "• Religion\n" .
               "• Church you attend\n" .
               "• How do you intend to finance your education\n" .
               "• Do you have any disability\n\n" .

               "📍 *PROGRAMME DETAILS*\n" .
               "• Programme name\n" .
               "• Stream (Regular or Weekend)\n" .
               "• Campus (Sunyani-main, Accra, Tamale)\n" .
               "• Academic period (Sept25, Jan26, June26)\n" .
               "• Entry Mode (apply with a 1st Degree/2nd Degree in)\n\n" .

               "📍 *CURRENT EMPLOYMENT*\n" .
               "• Employer Name\n" .
               "• Tel no\n" .
               "• Email Address\n" .
               "• Address\n" .
               "• Date of Employment From\n" .
               "• To\n" .
               "• Current position or Title\n" .
               "• State 3 responsibility you do at your work place\n\n" .

               "📍 *APPLICANTS CONTACT*\n" .
               "• Phone number\n" .
               "• Email Address\n" .
               "• Postal Address\n" .
               "• City\n" .
               "• Residential Address\n" .
               "• Digital address\n\n" .

               "📍 *FAMILY DETAILS*\n" .
               "• Guardian Name\n" .
               "• Relation to Applicant\n" .
               "• Occupation\n" .
               "• Phone Number\n\n" .

               "📍 *ACADEMIC DETAILS*\n" .
               "• Name of Institution\n" .
               "• Country of Institution\n" .
               "• Address and Email of Institution\n" .
               "• Course offered\n" .
               "• Year, Month you started\n" .
               "• Year, Month you ended\n" .
               "• Qualification Obtained\n" .
               "• Year and Month of Award\n" .
               "• Degree Classification (eg. 1st Class)\n" .
               "• FGPA\n\n" .

               "📍 *REFERENCES* (2 required)\n" .
               "• Position/Title\n" .
               "• Organisation\n" .
               "• Tel No\n" .
               "• Email\n\n" .

               "📍 *DOCUMENTS REQUIRED*\n" .
               "• Passport picture\n" .
               "• 1st Degree Certificate and Transcript\n" .
               "• Copy of CV\n" .
               "• Statement of Purpose (we can write this for you)\n" .
               "• Research Proposal (10 pages for PhD applicants)\n" .
               "• Signature (use: https://www.signwell.com/online-signature/draw/)\n\n" .

               "Reply with:\n" .
               "• 'OPTION 1' for Full Processing (GHC 250)\n" .
               "• 'OPTION 2' for Voucher + Self Application (GHC 150)\n" .
               "• 'PROGRAMMES' to see available programmes\n" .
               "• 'PAYMENT' for payment options";
    }

    /**
     * Get undergraduate admission guidelines
     */
    private function getUndergraduateGuidelines(): string
    {
        return "🎓 *CUG UNDERGRADUATE ADMISSIONS GUIDELINES*\n" .
               "_Priority Admissions Office (PSA)_\n" .
               "─────────────────────────────\n\n" .

               "📍 *PERSONAL DETAILS REQUIRED*\n" .
               "• Title (Mr., Miss, Dr., etc)\n" .
               "• Surname\n" .
               "• First Name\n" .
               "• Middle Name\n" .
               "• Gender\n" .
               "• Date of Birth\n" .
               "• Place of Birth\n" .
               "• Region of Birth\n" .
               "• Home Town\n" .
               "• Region of Hometown\n" .
               "• Country\n" .
               "• Marital Status\n" .
               "• No. of Children\n" .
               "• Religion\n" .
               "• Church you attend\n" .
               "• Are you currently employed\n" .
               "• If yes, your occupation, facility & address of workplace\n" .
               "• How do you intend to finance your education\n\n" .

               "📍 *PROGRAM DETAILS*\n" .
               "• Programme (e.g. BSc Nursing)\n" .
               "• Regular / Weekend / Sandwich (for Diploma holders)\n" .
               "• Academic period (Sept25, Jan26, June26)\n" .
               "• Entry Mode (WASSCE, Cert, Matured, Diploma/HND)\n\n" .

               "📍 *CONTACT DETAILS*\n" .
               "• Phone Number\n" .
               "• Email Address\n" .
               "• Postal Address\n" .
               "• City\n" .
               "• Residential Address\n" .
               "• Digital Address\n\n" .

               "📍 *FAMILY DETAILS*\n" .
               "*Father*\n" .
               "• Name\n" .
               "• Dead/Alive\n" .
               "• Contact\n" .
               "• Occupation\n" .
               "• Address\n\n" .

               "*Mother*\n" .
               "• Name\n" .
               "• Dead/Alive\n" .
               "• Contact\n" .
               "• Occupation\n" .
               "• Address\n\n" .

               "*Guardian*\n" .
               "• Name\n" .
               "• Relation to Applicant\n" .
               "• Occupation\n" .
               "• Phone Number\n\n" .

               "📍 *ACADEMIC DETAILS (SHS)*\n" .
               "• Name of SHS\n" .
               "• Course Offered\n" .
               "• Year & Month Started\n" .
               "• Year & Month Ended\n" .
               "• Exam Type\n" .
               "• Index Number\n" .
               "• Exam Year (Date)\n\n" .

               "📍 *EXTRA QUALIFICATION (If any)*\n" .
               "• Name of Tertiary Institution Attended\n" .
               "• Year & Month Started\n" .
               "• Year & Month Ended\n" .
               "• Certificate Obtained\n\n" .

               "📍 *DOCUMENTS REQUIRED*\n" .
               "• Passport picture\n" .
               "• WASSCE Results / Certificate\n" .
               "• Any additional certificates (if applicable)\n" .
               "• Signature (use: https://www.signwell.com/online-signature/draw/)\n\n" .

               "Reply with:\n" .
               "• 'OPTION 1' for Full Processing (GHC 200)\n" .
               "• 'OPTION 2' for Voucher + Self Application (GHC 115)\n" .
               "• 'ACCESS COURSE' for Access Course information\n" .
               "• 'PAYMENT' for payment options";
    }

    /**
     * Get Access Course information
     */
    private function getAccessCourseInfo(): string
    {
        return "📌 *INFORMATION ABOUT CUG ACCESS COURSE*\n" .
               "─────────────────────────────\n\n" .

               "The *Catholic University of Ghana* is currently registering applicants for the *Access Course* (final session for the 2025 Academic Year).\n\n" .

               "✅ *Course Format:*\n" .
               "• Lectures: Online via Zoom\n" .
               "• Duration: 3–4 weeks\n" .
               "• Examinations: In-person at CUG Campus, Sunyani–Fiapre\n\n" .

               "💰 *Access Course Fee:* GHC 1,200\n" .
               "(Payment to CUG's GCB Bank Account)\n\n" .

               "📍 *Placement After Access Exams:*\n" .
               "• NAC → BSc. Nursing (Level 200)\n" .
               "• NAP → BSc. Public Health Nursing (Level 200)\n" .
               "• NAP → BSc. Midwifery (Level 200)\n\n" .

               "📝 *Validity:* 2 years\n" .
               "💰 *Tuition After Admission:* ≈ GHC 4,400-4,500 per semester\n\n" .

               "Reply 'UNDERGRADUATE GUIDELINES' for full requirements or 'OPTION 1' to apply.";
    }

    /**
     * Get Option 1 details (Full Processing)
     */
    private function getOption1Details(): string
    {
        return "⭐ *OPTION 1 – Full Processing by Priority Admissions Office*\n" .
               "─────────────────────────────\n\n" .

               "💰 *FEES:*\n" .
               "• Postgraduate: GHC 250\n" .
               "• Undergraduate: GHC 200\n" .
               "• Access Course: Included in course fee\n\n" .

               "✅ *WHAT WE DO FOR YOU:*\n" .
               "✔ Generate your voucher\n" .
               "✔ Fill the forms correctly without mistakes\n" .
               "✔ Submit directly to CUG Admissions (no EMS posting required)\n" .
               "✔ Print all required documents + passport picture\n" .
               "✔ You will receive Email + SMS confirmation\n" .
               "✔ Add you to the Official CUG WhatsApp Group\n" .
               "✔ Follow-up until admission is released\n" .
               "✔ Professional Statement of Purpose writing (if needed)\n\n" .

               "📝 *HOW TO PROCEED:*\n" .
               "1. Make payment using any payment option\n" .
               "2. Send payment screenshot here\n" .
               "3. Provide your details and documents\n" .
               "4. We handle everything else!\n\n" .

               "This option is simple, stress-free, and highly recommended.\n\n" .

               "Reply 'PAYMENT' for payment options or 'PROCEED' to continue.";
    }

    /**
     * Get Option 2 details (Self Application)
     */
    private function getOption2Details(): string
    {
        return "✅ *OPTION 2 – Voucher + Self Application*\n" .
               "─────────────────────────────\n\n" .

               "💰 *FEES:*\n" .
               "• Postgraduate: GHC 150\n" .
               "• Undergraduate: GHC 115\n\n" .

               "🔗 *APPLICATION PORTAL:*\n" .
               "https://cug.prioritysolutionsagency.com\n\n" .

               "📝 *WHAT YOU WILL DO:*\n" .
               "After payment, you will receive a CUG Admission Voucher Code\n\n" .
               "You will personally:\n" .
               "✔ Fill the online forms\n" .
               "✔ Upload your documents\n" .
               "✔ Print the completed forms\n" .
               "✔ Attach your certificates + transcripts\n" .
               "✔ Submit via EMS or at CUG Campus (Fiapre – Sunyani)\n\n" .

               "📝 *HOW TO PROCEED:*\n" .
               "1. Make payment using any payment option\n" .
               "2. Send payment screenshot here\n" .
               "3. Receive your voucher code\n" .
               "4. Complete application yourself\n\n" .

               "Reply 'PAYMENT' for payment options or 'PROCEED' to continue.";
    }

    /**
     * Get payment options
     */
    private function getPaymentOptions(): string
    {
        return "💰 *PAYMENT OPTIONS*\n" .
               "─────────────────────────────\n\n" .

               "You can pay using any of the options below:\n\n" .

               "1️⃣ *USSD (All Networks)* – Dial *713*0049#\n\n" .

               "2️⃣ *MTN MoMo Payment* – 0543992073\n" .
               "   Merchant ID: 670113\n\n" .

               "3️⃣ *GCB Bank*\n" .
               "   Account Name: PRIORITY ADMISSIONS OFFICE\n" .
               "   Account Number: 7251420000128\n\n" .

               "📸 *After payment, send screenshot here to confirm and begin your process.*\n\n" .

               "Reply with:\n" .
               "• 'POSTGRADUATE' for postgraduate fees\n" .
               "• 'UNDERGRADUATE' for undergraduate fees\n" .
               "• 'ACCESS COURSE' for Access Course fees";
    }

    /**
     * Get available programmes
     */
    private function getAvailableProgrammes(string $input): string
    {
        if (str_contains($input, 'postgraduate') || str_contains($input, 'masters') || str_contains($input, 'mphil')) {
            return "🎓 *POSTGRADUATE PROGRAMMES AVAILABLE*\n" .
                   "─────────────────────────────\n\n" .

                   "📌 *School of Graduate Studies (Weekend Programmes)*\n" .
                   "• MPhil and Master of Public Health\n" .
                   "• MSc Data Science\n" .
                   "• MBA (Accounting | Finance | HRM | Marketing)\n" .
                   "• MPhil Educational Psychology, Guidance & Counselling\n" .
                   "• MPhil Educational Administration & Management\n" .
                   "• Postgraduate Diploma in Education (PGDE)\n" .
                   "• PhD in Management, Education and Doctor of Business Administration\n" .
                   "• ...and Many More\n\n" .

                   "💼 *Flexible weekend lecture format for workers*\n" .
                   "🧾 *Easy admission requirements*\n" .
                   "📍 *Study in Sunyani | Accra | Tamale*\n\n" .

                   "Reply 'POSTGRADUATE GUIDELINES' for full requirements";
        }

        return "🎓 *UNDERGRADUATE PROGRAMMES AVAILABLE*\n" .
               "─────────────────────────────\n\n" .

               "We offer various undergraduate programmes including:\n" .
               "• BSc Nursing\n" .
               "• BSc Midwifery\n" .
               "• BSc Public Health Nursing\n" .
               "• And many other programmes\n\n" .

               "📍 *Streams Available:*\n" .
               "• Regular\n" .
               "• Weekend\n" .
               "• Sandwich (for Diploma holders)\n\n" .

               "Reply 'UNDERGRADUATE GUIDELINES' for full requirements or 'ACCESS COURSE' for Access Course information";
    }

    /**
     * Handle admission inquiry
     */
    private function handleAdmissionInquiry(string $input): string
    {
        return "🎓 *CUG ADMISSIONS APPLICATION*\n" .
               "─────────────────────────────\n\n" .

               "Thank you for your interest in applying to the *Catholic University of Ghana (CUG)*. Due to ongoing maintenance on the main university portal, admissions are currently being processed through the *Priority Admissions Office*.\n\n" .

               "Please choose your application type:\n\n" .

               "🎓 *POSTGRADUATE* (Masters, MPhil, PhD)\n" .
               "• Option 1: Full Processing - GHC 250\n" .
               "• Option 2: Voucher + Self Application - GHC 150\n\n" .

               "🎓 *UNDERGRADUATE* (Degree, Access Course)\n" .
               "• Option 1: Full Processing - GHC 200\n" .
               "• Option 2: Voucher + Self Application - GHC 115\n\n" .

               "Reply with:\n" .
               "• 'POSTGRADUATE' for postgraduate application\n" .
               "• 'UNDERGRADUATE' for undergraduate application\n" .
               "• 'OPTION 1' for full processing details\n" .
               "• 'OPTION 2' for self application details\n" .
               "• 'PROGRAMMES' to see available programmes";
    }

    /**
     * Get help message with available commands
     */
    private function getHelpMessage(): string
    {
        return "🆘 *HELP - AVAILABLE COMMANDS*\n" .
               "─────────────────────────────\n\n" .

               "🎓 *ADMISSIONS INFORMATION:*\n" .
               "• `postgraduate` - PG guidelines & requirements\n" .
               "• `undergraduate` - UG guidelines & requirements\n" .
               "• `programmes` - Available programmes\n" .
               "• `access course` - Access course information\n\n" .

               "📝 *APPLICATION OPTIONS:*\n" .
               "• `option 1` - Full processing details\n" .
               "• `option 2` - Self application details\n" .
               "• `payment` - Payment options\n\n" .

               "💰 *FEES:*\n" .
               "• PG Option 1: GHC 250\n" .
               "• PG Option 2: GHC 150\n" .
               "• UG Option 1: GHC 200\n" .
               "• UG Option 2: GHC 115\n" .
               "• Access Course: GHC 1,200\n\n" .

               "🔗 *APPLICATION PORTAL:*\n" .
               "https://cug.prioritysolutionsagency.com\n\n" .

               "🤖 *AI FEATURES:*\n" .
               "• `chatgpt [question]` - Ask ChatGPT anything\n" .
               "• `ai help` - Get AI assistance\n\n" .

               "Just type what you need help with!";
    }

    /**
     * Handle FAQ questions
     */
    public function handleFaqQuestion(string $question): string
    {
        // Simple FAQ system - you can replace this with API calls later
        $faqAnswers = [
            'requirements' => "Admission requirements vary by programme. Generally:\n• Completed application form\n• Academic transcripts/certificates\n• Passport photographs\n• Identification documents\n• Programme-specific requirements may apply",
            'deadline' => "Applications are accepted on a rolling basis. We recommend applying at least 2 months before your intended start date.",
            'fees' => "Fees vary by programme:\n• Undergraduate: ~GHC 4,400-4,500 per semester\n• Postgraduate: Programme-specific\n• Access Course: GHC 1,200",
            'duration' => "Programme durations:\n• Undergraduate: 4 years\n• Postgraduate: 1-2 years\n• Access Course: 3-4 weeks"
        ];

        $questionLower = strtolower($question);
        
        foreach ($faqAnswers as $keyword => $answer) {
            if (str_contains($questionLower, $keyword)) {
                return "🤔 **Your Question:** \"{$question}\"\n\n✅ **Answer:** {$answer}";
            }
        }

        return "🤔 **Your Question:** \"{$question}\"\n\n❓ **Answer:** I understand you're asking about \"{$question}\". For specific information, please contact the Priority Admissions Office directly at 0543992073 or visit our website.";
    }

    /**
     * Check if the input is likely an FAQ question
     */
    private function isFaqQuestion(string $input): bool
    {
        $faqKeywords = [
            'what', 'when', 'where', 'why', 'how', 'can i', 'do you', 'is there',
            'requirement', 'deadline', 'fee', 'cost', 'tuition', 'scholarship',
            'duration', 'length', 'start', 'begin', 'require', 'need', 'eligibility'
        ];

        foreach ($faqKeywords as $keyword) {
            if (str_contains($input, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle ChatGPT requests
     */
    private function handleChatGPTRequest(string $input): string
    {
        // Remove the trigger words to get the actual question
        $question = trim(str_replace(['chatgpt', 'ai', 'gpt'], '', $input));
        
        if (empty($question)) {
            return "I'd be happy to help with ChatGPT! Please ask me a question after the 'chatgpt' command. For example: `chatgpt what is quantum computing?`";
        }

        try {
            $response = $this->callChatGPT($question);
            return "🤖 **ChatGPT Response:**\n\n" . $response;
        } catch (\Exception $e) {
            Log::error('ChatGPT API error: ' . $e->getMessage());
            return "I'm sorry, I'm having trouble connecting to ChatGPT right now. Please try again later or ask me something else!";
        }
    }

    /**
     * Call ChatGPT API
     */
    private function callChatGPT(string $question): string
    {
        $apiKey = config('services.openai.api_key');
        
        if (!$apiKey) {
            return "ChatGPT integration is not configured. Please contact administrator.";
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant. Provide concise, helpful responses.'],
                ['role' => 'user', 'content' => $question]
            ],
            'max_tokens' => 500,
            'temperature' => 0.7,
        ]);

        if ($response->successful()) {
            return $response->json('choices.0.message.content', 'Sorry, I could not generate a response.');
        }

        throw new \Exception('ChatGPT API request failed: ' . $response->body());
    }

    /**
     * Handle default responses for unrecognized input
     */
    private function handleDefaultResponse(string $input): string
    {
        $defaultResponses = [
            "I'm not sure I understand. Type `help` to see what I can do!",
            "That's an interesting question! Try asking me about admissions, time, or type `help` for more options.",
            "I'm still learning! You can ask me about admissions, use ChatGPT, or check the time. Type `help` for all options.",
            "I'm here to help with CUG admissions, answer questions using AI, and provide general assistance. What specifically can I help you with?"
        ];

        return $defaultResponses[array_rand($defaultResponses)];
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

        // Add a small delay before broadcasting bot message to ensure user's message appears first
        // This prevents the bot reply from appearing before the user's message in the UI
        // Use usleep for a small delay (500ms)
        usleep(500000); // 500 milliseconds
        
        // Broadcast the message to all users in the conversation (not just toOthers)
        // This ensures the user receives the bot message in real-time
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