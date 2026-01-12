# GekyChat AI Setup Guide

This guide explains how to set up OpenAI integration for GekyChat AI and the birthday reminder feature.

## Prerequisites

1. An OpenAI API key from https://platform.openai.com/api-keys
2. Users must have contacts saved with birthday information (dob_month and dob_day)

## Setup Instructions

### 1. Install Dependencies

The OpenAI PHP client is already installed via Composer:
```bash
composer require openai-php/client
```

### 2. Configure OpenAI API Key

Add your OpenAI API key to your `.env` file:

```env
OPENAI_API_KEY=sk-your-api-key-here
```

The key is automatically read from `config/services.php` which maps to `OPENAI_API_KEY` environment variable.

### 3. How It Works

#### AI Chat Integration

- When a user chats with **GekyBot** (phone: `0000000000`), the bot will use OpenAI to generate responses
- The bot uses conversation context (last 5 messages) for better responses
- If OpenAI API key is not configured, it falls back to rule-based responses or Ollama (if configured)
- Priority: OpenAI > Ollama > Rule-based

#### Birthday Reminder Feature

- A scheduled job runs **daily at 8:00 AM** to check for birthdays
- The job finds all contacts where:
  - The contact is saved in a user's contact list
  - The contact_user has `dob_month` and `dob_day` set
  - Today's month and day match the contact's birthday
- For each match, GekyBot sends a personalized birthday reminder message to the contact owner
- The reminder message is generated using OpenAI (if configured) or a fallback message
- Duplicate reminders are prevented (only one per day per contact)

### 4. Testing

#### Test AI Chat

1. Start a conversation with GekyBot (phone: `0000000000`)
2. Send any message
3. The bot should respond using OpenAI if the API key is configured

#### Test Birthday Reminders

1. Set up a test contact with birthday information:
   ```php
   // In tinker or a seeder
   $user = User::find(1); // Your user ID
   $contactUser = User::find(2); // Another user
   $contactUser->update([
       'dob_month' => now()->month,
       'dob_day' => now()->day,
   ]);
   $contact = Contact::create([
       'user_id' => $user->id,
       'contact_user_id' => $contactUser->id,
       'display_name' => 'Test Contact',
       'phone' => $contactUser->phone,
       'normalized_phone' => Contact::normalizePhone($contactUser->phone),
   ]);
   ```

2. Run the job manually:
   ```bash
   php artisan queue:work
   # Or dispatch the job directly:
   php artisan tinker
   >>> \App\Jobs\SendBirthdayReminders::dispatch();
   ```

### 5. Scheduled Job

The birthday reminder job is scheduled in `routes/console.php`:

```php
Schedule::job(new \App\Jobs\SendBirthdayReminders)->dailyAt('08:00');
```

Make sure your Laravel scheduler is running:

```bash
# Add to crontab
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

### 6. Files Created/Modified

- **New Files:**
  - `app/Services/OpenAiService.php` - OpenAI integration service
  - `app/Jobs/SendBirthdayReminders.php` - Birthday reminder job

- **Modified Files:**
  - `app/Services/BotService.php` - Updated to use OpenAI when available
  - `config/services.php` - Added OpenAI configuration
  - `routes/console.php` - Added scheduled job

### 7. Troubleshooting

#### AI Not Responding

1. Check if `OPENAI_API_KEY` is set in `.env`
2. Check logs: `storage/logs/laravel.log`
3. Verify the API key is valid and has credits

#### Birthday Reminders Not Sending

1. Check if contacts have `dob_month` and `dob_day` set
2. Verify the scheduled job is running: `php artisan schedule:list`
3. Check logs for errors
4. Ensure GekyBot user exists (phone: `0000000000`)

#### Duplicate Reminders

The job checks for existing reminders sent today to prevent duplicates. If you need to test again, you can manually delete the reminder message from the conversation.

## Notes

- The OpenAI integration uses `gpt-4o-mini` model for cost efficiency
- Birthday reminders are sent only once per day per contact
- The job processes all users with matching contacts in a single run
- Rate limiting and error handling are built into the service
