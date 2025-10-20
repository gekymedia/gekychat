<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Carbon\Carbon;

class GekyChatUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Your user phone number
        $yourPhone = '0248229540';
        
        // Find or create your user account
        $yourUser = User::where('phone', $yourPhone)->first();
        
        if (!$yourUser) {
            $yourUser = User::create([
                'name' => 'Geky User',
                'phone' => $yourPhone,
                'email' => 'gekyuser@example.com',
                'password' => Hash::make('password123'),
                'phone_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $this->command->info("Created your user account with phone: {$yourPhone}");
        } else {
            $this->command->info("Found existing user account with phone: {$yourPhone}");
        }

        // Ghana phone numbers with realistic prefixes
        $ghanaPhoneNumbers = [
            '0240000001', '0240000002', '0240000003', '0240000004', '0240000005',
            '0240000006', '0240000007', '0240000008', '0240000009', '0240000010',
            '0240000011', '0240000012', '0240000013', '0240000014', '0240000015',
            '0240000016', '0240000017', '0240000018', '0240000019', '0240000020',
        ];

        // Ghanaian names for more realism
        $ghanaianFirstNames = [
            'Kwame', 'Ama', 'Kofi', 'Abena', 'Yaw', 'Akua', 'Kwasi', 'Adwoa', 'Kwabena', 'Akosua',
            'Yaa', 'Esi', 'Kojo', 'Afua', 'Kwaku', 'Ama', 'Yaw', 'Akos', 'Kweku', 'Adjoa'
        ];

        $ghanaianLastNames = [
            'Mensah', 'Appiah', 'Darko', 'Osei', 'Boateng', 'Agyei', 'Asare', 'Owusu', 'Amoako', 'Ansah',
            'Sarpong', 'Quaye', 'Tweneboah', 'Gyamfi', 'Adu', 'Opoku', 'Danso', 'Frimpong', 'Asante', 'Baffour'
        ];

        $createdUsers = [];
        
        $this->command->info("Creating 20 users for GekyChat...");

        foreach ($ghanaPhoneNumbers as $index => $phone) {
            // Check if user already exists
            $existingUser = User::where('phone', $phone)->first();
            if ($existingUser) {
                $this->command->info("User already exists: {$existingUser->name} ({$phone})");
                $createdUsers[] = $existingUser;
                continue;
            }

            $firstName = $ghanaianFirstNames[$index];
            $lastName = $ghanaianLastNames[$index];
            
            $user = User::create([
                'name' => "{$firstName} {$lastName}",
                'phone' => $phone,
                'email' => Str::slug($firstName . '.' . $lastName) . '@example.com',
                'password' => Hash::make('password123'),
                'phone_verified_at' => now(),
                'created_at' => now()->subDays(rand(1, 365)),
                'updated_at' => now(),
            ]);

            $createdUsers[] = $user;
            $this->command->info("Created user: {$user->name} ({$user->phone})");
        }

        $this->command->info("\nAdding 10 users as contacts to your account...");

        // Add first 10 users as contacts
        $contactsToAdd = array_slice($createdUsers, 0, 10);
        
        foreach ($contactsToAdd as $index => $contactUser) {
            // Check if contact already exists
            $existingContact = Contact::where('user_id', $yourUser->id)
                ->where('contact_user_id', $contactUser->id)
                ->first();

            if ($existingContact) {
                $this->command->info("Contact already exists: {$existingContact->display_name}");
                continue;
            }

            $displayName = $contactUser->name;
            
            $contact = Contact::create([
                'user_id' => $yourUser->id,
                'contact_user_id' => $contactUser->id,
                'display_name' => $displayName,
                'phone' => $contactUser->phone,
                'normalized_phone' => Contact::normalizePhone($contactUser->phone),
                'source' => 'seeder',
                'is_favorite' => $index < 3,
                'created_at' => now()->subDays(rand(1, 30)),
                'updated_at' => now(),
            ]);

            $favoriteStatus = $contact->is_favorite ? ' (FAVORITE)' : '';
            $this->command->info("Added contact: {$contact->display_name}{$favoriteStatus}");
        }

        // Create some conversations between you and your contacts USING THE MODELS
        $this->command->info("\nCreating some conversations...");
        
        $conversationContacts = array_slice($contactsToAdd, 0, 5); // Create convos with first 5 contacts
        
        foreach ($conversationContacts as $contactUser) {
            $this->createConversationWithMessages($yourUser, $contactUser);
        }

        $this->command->info("\nSeeder completed successfully!");
        $this->command->info("Your account: {$yourUser->name} ({$yourUser->phone})");
        $this->command->info("Total users processed: " . count($createdUsers));
        $this->command->info("Contacts added to your account: " . count($contactsToAdd));
        $this->command->info("Conversations created: " . count($conversationContacts));
    }

    /**
     * Create a conversation between two users using the proper Models
     */
    private function createConversationWithMessages(User $user1, User $user2): void
    {
        try {
            // Use your Conversation model's method to find or create conversation
            $conversation = Conversation::findOrCreateDirect($user1->id, $user2->id, $user1->id);
            
            $this->command->info("Created conversation between {$user1->name} and {$user2->name}");

            // Create some sample messages using the Message model
            $sampleMessages = [
                "Hello {$user2->name}! 👋",
                "How are you doing today?",
                "Did you see the latest updates?",
                "Let's catch up soon!",
                "Thanks for your help with the project",
                "Are you available for a call tomorrow?",
                "Happy weekend! 🎉",
                "Check out this new feature I found",
                "Looking forward to our meeting",
                "Take care! 😊"
            ];

            $messageCount = rand(3, 8);
            $messageDates = [];
            
            // Generate random message dates within the last 30 days
            for ($i = 0; $i < $messageCount; $i++) {
                $messageDates[] = now()->subDays(rand(0, 30))->subHours(rand(0, 23))->subMinutes(rand(0, 59));
            }
            
            sort($messageDates); // Sort dates chronologically

            foreach ($messageDates as $index => $messageDate) {
                $sender = $index % 2 === 0 ? $user1 : $user2; // Alternate senders
                $message = $sampleMessages[array_rand($sampleMessages)];
                
                // Create message using the Message model (matches your database)
                Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $sender->id,
                    'body' => $message,
                    'type' => 'text',
                    'created_at' => $messageDate,
                    'updated_at' => $messageDate,
                ]);
            }

            $this->command->info("Added {$messageCount} messages to conversation");

        } catch (\Exception $e) {
            $this->command->error("Failed to create conversation between {$user1->name} and {$user2->name}: " . $e->getMessage());
        }
    }
}