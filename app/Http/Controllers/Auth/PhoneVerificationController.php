<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\BotContact;
use App\Services\SmsServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PhoneVerificationController extends Controller
{
    protected SmsServiceInterface $smsService;
    protected int $maxAttempts = 3;
    protected int $decayMinutes = 5;

    public function __construct(SmsServiceInterface $smsService)
    {
        $this->smsService = $smsService;
    }

    public function show()
    {
        return view('auth.phone-login');
    }

    public function sendOtp(Request $request)
    {
        // More flexible phone validation to allow bot numbers
        $request->validate([
            'phone' => 'required|string|max:20'
        ]);

        // Normalize phone number (remove non-digits)
        $phone = preg_replace('/\D+/', '', $request->phone);

        // Check if this is a bot number
        $bot = BotContact::getByPhone($phone);
        if ($bot) {
            // Bot number - don't send SMS, just store bot info in session
            session([
                'phone' => $phone,
                'is_bot' => true,
                'bot_id' => $bot->id,
            ]);

            return redirect()->route('verify.otp')->with([
                'status' => 'Please enter the 6-digit bot code',
                'is_bot' => true,
            ]);
        }

        // Validate phone format for regular users (Ghanaian format)
        if (!preg_match('/^0[0-9]{9}$/', $phone)) {
            throw ValidationException::withMessages([
                'phone' => 'Please enter a valid phone number (10 digits starting with 0)'
            ]);
        }

        $throttleKey = 'otp:' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, $this->maxAttempts)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'phone' => "Too many attempts. Please try again in {$seconds} seconds."
            ]);
        }

        RateLimiter::hit($throttleKey, $this->decayMinutes * 60);

        $user = User::firstOrCreate(
            ['phone' => $phone],
            [
                'name' => 'User_' . substr($phone, -4),
                'email' => 'user_' . $phone . '@example.com',
                'password' => bcrypt(uniqid()) // Temporary password
            ]
        );

        $otp = rand(100000, 999999);
        $user->update([
            'otp_code' => $otp,
            'otp_expires_at' => Carbon::now()->addMinutes(5),
        ]);

        // Send OTP via SMS
        $message = "Your OTP code is: {$otp}. Valid for 5 minutes.";
        $smsResponse = $this->smsService->sendSms($phone, $message);

        if (!$smsResponse['success']) {
            return back()->withErrors([
                'phone' => 'Failed to send OTP. Please try again later.'
            ]);
        }

        session([
            'otp_user_id' => $user->id,
            'phone' => $phone,
            'is_bot' => false,
            'resend_time' => Carbon::now()->addSeconds(30)->timestamp
        ]);

        return redirect()->route('verify.otp')->with([
            'status' => 'OTP sent to your phone number',
            'sms_balance' => $smsResponse['balance']
        ]);
    }

    public function showOtpForm()
    {
        if (!session('phone')) {
            return redirect()->route('login');
        }

        return view('auth.verify-otp', [
            'phone' => session('phone'),
            'resend_time' => session('resend_time'),
            'is_bot' => session('is_bot', false),
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp_code' => 'required|digits:6' // Match the form field name
        ]);

        $phone = session('phone');
        $isBot = session('is_bot', false);

        if ($isBot) {
            // Bot login - verify bot code
            $botId = session('bot_id');
            $bot = BotContact::find($botId);
            
            if (!$bot || !$bot->verifyCode($request->otp_code)) {
                return back()->withErrors(['otp_code' => 'Invalid bot code.']);
            }

            // Get or create user for bot
            $user = $bot->getOrCreateUser();

            // Mark phone as verified (bots don't need SMS verification)
            if (!$user->phone_verified_at) {
                $user->markPhoneAsVerified();
            }
        } else {
            // Regular user - verify OTP
            $user = User::where('phone', $phone)
                ->where('otp_code', $request->otp_code)
                ->where('otp_expires_at', '>', Carbon::now())
                ->first();

            if (!$user) {
                return back()->withErrors(['otp_code' => 'Invalid or expired OTP code.']);
            }

            // Clear OTP and mark as verified
            $user->update([
                'otp_code' => null,
                'otp_expires_at' => null,
                'phone_verified_at' => Carbon::now()
            ]);
        }

        // Log in the user
        Auth::login($user, $request->remember ?? false);

        // Check if 2FA is enabled (user must enter their PIN)
        if ($user->requiresTwoFactor()) {
            // Clear session data
            session()->forget(['otp_user_id', 'phone', 'resend_time', 'is_bot', 'bot_id']);
            
            // Redirect to 2FA PIN verification
            return redirect()->route('verify.2fa')
                ->with('status', 'Please enter your two-factor authentication PIN.');
        }

        // Seed default contacts and conversations for first-time users
        try {
            $this->seedDefaultContactsFor($user);
        } catch (\Throwable $e) {
            \Log::error('Failed to seed default contacts', ['error' => $e->getMessage()]);
        }

        // Flag first login to optionally show Google contacts modal
        if ($user->contacts()->count() <= 3) {
            session(['show_google_contact_modal' => true]);
        }

        // Clear session data
        session()->forget(['otp_user_id', 'phone', 'resend_time', 'is_bot', 'bot_id']);

        // Redirect to chat
        return redirect()->route('chat.index')->with('success', 'Login successful!');
    }

    /**
     * Seed the default contacts (GekyBot, Admin, and self-chat) for a newly verified user.
     * Contacts are only inserted if they don't already exist for this user to avoid duplicates.
     * Additionally ensures a DM conversation exists for each seeded contact.
     *
     * @param \App\Models\User $user
     * @return void
     */
    protected function seedDefaultContactsFor(User $user): void
    {
        // Only seed if the user has no contacts yet or very few (to avoid overriding manual imports)
        // You can adjust this heuristic as needed
        $existingCount = $user->contacts()->count();
        if ($existingCount > 0) {
            return;
        }

        $defaultPhones = [
            '0000000000',   // GekyBot
            '0248229540',   // Admin (Emmanuel Gyabaa Yeboah)
        ];

        // Ensure self chat (saved messages) conversation exists
        \App\Models\Conversation::findOrCreateSavedMessages($user->id);

        foreach ($defaultPhones as $phone) {
            /** @var \App\Models\User|null $target */
            $target = User::where('phone', $phone)->first();
            if (!$target) {
                continue;
            }

            // Create contact if not exists
            $existing = \App\Models\Contact::where('user_id', $user->id)
                ->where('contact_user_id', $target->id)
                ->first();
            if (!$existing) {
                \App\Models\Contact::create([
                    'user_id'        => $user->id,
                    'contact_user_id' => $target->id,
                    'display_name'   => $target->name ?? $target->phone,
                    'phone'          => $target->phone,
                    'normalized_phone' => \App\Models\Contact::normalizePhone($target->phone),
                    'source'         => 'seeded',
                    'is_deleted'     => false,
                ]);
            }

            // Ensure conversation exists
            \App\Models\Conversation::findOrCreateDirect($user->id, $target->id);
        }
    }

    public function resendOtp(Request $request)
    {
        // Don't allow resend for bot logins
        if (session('is_bot', false)) {
            return back()->withErrors([
                'otp_code' => 'Bot codes cannot be resent. Please use the code from the admin panel.'
            ]);
        }

        if (!session('otp_user_id')) {
            return redirect()->route('login');
        }

        $user = User::find(session('otp_user_id'));
        if (!$user) {
            return redirect()->route('login');
        }

        $otp = rand(100000, 999999);
        $user->update([
            'otp_code' => $otp,
            'otp_expires_at' => Carbon::now()->addMinutes(5),
        ]);

        $message = "Your new OTP code is: {$otp}. Valid for 5 minutes.";
        $smsResponse = $this->smsService->sendSms($user->phone, $message);

        if (!$smsResponse['success']) {
            return back()->withErrors([
                'otp_code' => 'Failed to resend OTP. Please try again.'
            ]);
        }

        // Update resend time in session
        session(['resend_time' => Carbon::now()->addSeconds(30)->timestamp]);

        return back()->with([
            'status' => 'New OTP sent to your phone',
            'sms_balance' => $smsResponse['balance']
        ]);
    }
}