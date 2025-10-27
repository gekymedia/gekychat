<?php

namespace App\Services;

use App\Models\User;
use App\Models\GoogleContact;
use App\Models\Contact;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleContactsService
{
    public function syncContacts(User $user)
    {
        if (!$user->hasGoogleAccess()) {
            throw new \Exception('User does not have Google access');
        }

        try {
            // Refresh token if needed
            $accessToken = $this->refreshTokenIfNeeded($user);
            
            // Fetch contacts from Google
            $googleContacts = $this->fetchGoogleContacts($accessToken);
            
            // Process and sync contacts
            $this->processGoogleContacts($user, $googleContacts);
            
            $user->updateLastSyncTime();
            
            return [
                'success' => true,
                'synced_count' => count($googleContacts),
                'message' => 'Contacts synced successfully'
            ];
            
        } catch (\Exception $e) {
            Log::error('Google Contacts sync failed: ' . $e->getMessage());
            throw $e;
        }
    }

    protected function refreshTokenIfNeeded(User $user)
    {
        // For now, return the access token
        // In production, implement token refresh logic
        return $user->google_access_token;
    }

    protected function fetchGoogleContacts($accessToken)
    {
        $response = Http::withToken($accessToken)
            ->get('https://people.googleapis.com/v1/people/me/connections', [
                'personFields' => 'names,phoneNumbers,emailAddresses,photos',
                'pageSize' => 2000,
            ]);

        if ($response->successful()) {
            return $response->json('connections', []);
        }

        throw new \Exception('Failed to fetch Google contacts: ' . $response->body());
    }

    protected function processGoogleContacts(User $user, $googleContacts)
    {
        $processedPhones = [];

        foreach ($googleContacts as $googleContact) {
            $contactData = $this->extractContactData($googleContact);
            
            if ($contactData && $contactData['phone']) {
                $processedPhones[] = $contactData['phone'];
                
                // Update or create Google contact record
                $googleContactRecord = GoogleContact::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'google_contact_id' => $googleContact['resourceName'],
                    ],
                    array_merge($contactData, [
                        'last_synced_at' => now(),
                        'is_deleted_in_google' => false,
                    ])
                );

                // Sync to local contacts if not already deleted in GekyChat
                $this->syncToLocalContacts($user, $googleContactRecord);
            }
        }

        // Mark contacts as deleted in Google if they're no longer in the sync
        $this->markMissingContactsAsDeleted($user, $processedPhones);
    }

    protected function extractContactData($googleContact)
    {
        $name = $googleContact['names'][0]['displayName'] ?? null;
        $phone = null;
        $email = null;
        $photoUrl = null;

        // Extract phone number
        if (isset($googleContact['phoneNumbers'][0]['value'])) {
            $phone = $this->normalizePhone($googleContact['phoneNumbers'][0]['value']);
        }

        // Extract email
        if (isset($googleContact['emailAddresses'][0]['value'])) {
            $email = $googleContact['emailAddresses'][0]['value'];
        }

        // Extract photo
        if (isset($googleContact['photos'][0]['url'])) {
            $photoUrl = $googleContact['photos'][0]['url'];
        }

        if (!$phone) {
            return null;
        }

        return [
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'photo_url' => $photoUrl,
        ];
    }

    protected function normalizePhone($phone)
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Handle Ghanaian numbers
        if (str_starts_with($phone, '233') && strlen($phone) === 12) {
            return '0' . substr($phone, 3);
        }
        
        // Ensure it starts with 0 and has 10 digits
        if (str_starts_with($phone, '0') && strlen($phone) === 10) {
            return $phone;
        }
        
        return null;
    }

    protected function syncToLocalContacts(User $user, GoogleContact $googleContact)
    {
        // Normalize the phone number for the contacts table
        $normalizedPhone = Contact::normalizePhone($googleContact->phone);

        // Check if contact already exists and is not deleted in GekyChat
        $existingContact = Contact::where('user_id', $user->id)
            ->where('normalized_phone', $normalizedPhone)
            ->where('is_deleted', false)
            ->first();

        if (!$existingContact) {
            // Create new contact in GekyChat with normalized_phone
            Contact::create([
                'user_id' => $user->id,
                'display_name' => $googleContact->name ?? $googleContact->phone,
                'phone' => $googleContact->phone,
                'normalized_phone' => $normalizedPhone, // Add this line
                'email' => $googleContact->email,
                'source' => 'google_sync',
                'google_contact_id' => $googleContact->id,
                'is_favorite' => false,
            ]);
        } elseif ($existingContact->isFromGoogleSync()) {
            // Update existing Google-synced contact
            $existingContact->update([
                'display_name' => $googleContact->name ?? $googleContact->phone,
                'email' => $googleContact->email,
                'normalized_phone' => $normalizedPhone, // Update normalized phone too
            ]);
        }
        // If it's a manual contact, don't overwrite it
    }

    protected function markMissingContactsAsDeleted(User $user, $currentPhones)
    {
        GoogleContact::where('user_id', $user->id)
            ->whereNotIn('phone', $currentPhones)
            ->update(['is_deleted_in_google' => true]);
            
        // Note: We don't delete local contacts here - they remain in GekyChat
    }

    public function getSyncStatus(User $user)
    {
        $totalGoogleContacts = GoogleContact::where('user_id', $user->id)->count();
        $activeGoogleContacts = GoogleContact::where('user_id', $user->id)
            ->where('is_deleted_in_google', false)
            ->count();
        $localContactsFromGoogle = Contact::where('user_id', $user->id)
            ->where('source', 'google_sync')
            ->where('is_deleted', false)
            ->count();

        return [
            'google_sync_enabled' => $user->google_sync_enabled,
            'last_sync' => $user->last_google_sync_at,
            'total_google_contacts' => $totalGoogleContacts,
            'active_google_contacts' => $activeGoogleContacts,
            'local_contacts_from_google' => $localContactsFromGoogle,
            'has_google_access' => $user->hasGoogleAccess(),
        ];
    }
}