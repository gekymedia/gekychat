<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\User;
use App\Models\Group;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContactSearchService
{
    public function search(int $userId, string $query, array $filters = [], int $limit = 20)
    {
        if (empty($query)) {
            return $this->getDefaultSuggestions($userId, $limit);
        }
        
        $searchTerm = '%' . preg_replace('/\s+/', '%', $query) . '%';
        $phoneDigits = preg_replace('/\D+/', '', $query);
        
        $results = [];
        
        // Apply filters
        if (empty($filters) || in_array('contacts', $filters)) {
            $results['contacts'] = $this->searchContacts($userId, $searchTerm, $phoneDigits, $limit);
        }
        
        if (empty($filters) || in_array('users', $filters)) {
            $results['users'] = $this->searchUsers($userId, $searchTerm, $phoneDigits, $limit);
        }
        
        if (empty($filters) || in_array('groups', $filters)) {
            $results['groups'] = $this->searchGroups($userId, $searchTerm, $limit);
        }
        
        if (empty($filters) || in_array('messages', $filters)) {
            $results['messages'] = $this->searchMessages($userId, $query, $limit);
        }
        
        // Always search conversations (one-on-one chats) regardless of filters
        $results['conversations'] = $this->searchConversations($userId, $searchTerm, $phoneDigits, $limit);
        
        // Always include phone number suggestion if query looks like a phone number
        if ($this->looksLikePhoneNumber($query)) {
            $results['phone_suggestion'] = $this->getPhoneSuggestion($userId, $query);
        }
        
        $scoredResults = $this->scoreAndMergeResults($results, $query, $userId);
        
        return $this->applyFinalFilters($scoredResults, $filters, $limit);
    }
    
    private function getDefaultSuggestions(int $userId, int $limit): array
    {
        return [
            'recent_chats' => $this->getRecentChats($userId, $limit),
            'frequent_contacts' => $this->getFrequentContacts($userId, 10),
            'unread_chats' => $this->getUnreadChats($userId, 10),
        ];
    }
    
    private function searchContacts(int $userId, string $searchTerm, string $phoneDigits, int $limit): array
    {
        return Contact::where('user_id', $userId)
            ->where(function ($q) use ($searchTerm, $phoneDigits) {
                $q->where('display_name', 'LIKE', $searchTerm)
                  ->orWhere('phone', 'LIKE', $searchTerm)
                  ->orWhere('normalized_phone', 'LIKE', '%' . $phoneDigits . '%');
            })
            ->with('contactUser')
            ->limit($limit)
            ->get()
            ->map(function ($contact) {
                return [
                    'type' => 'contact',
                    'id' => 'contact_' . $contact->id,
                    'contact' => $contact,
                    'user' => $contact->contactUser,
                    'display_name' => $contact->display_name,
                    'phone' => $contact->phone,
                    'is_registered' => !is_null($contact->contact_user_id),
                    'avatar_url' => $contact->contactUser->avatar_url ?? null,
                ];
            })->toArray();
    }
    
    private function searchUsers(int $userId, string $searchTerm, string $phoneDigits, int $limit): array
    {
        return User::where('id', '!=', $userId)
            ->whereNotNull('phone_verified_at')
            ->where(function ($q) use ($searchTerm, $phoneDigits) {
                $q->where('name', 'LIKE', $searchTerm)
                  ->orWhere('email', 'LIKE', $searchTerm)
                  ->orWhere('phone', 'LIKE', '%' . $phoneDigits . '%')
                  ->orWhere('slug', 'LIKE', $searchTerm);
            })
            ->limit($limit)
            ->get()
            ->map(function ($user) use ($userId) {
                $isContact = Contact::where('user_id', $userId)
                    ->where('contact_user_id', $user->id)
                    ->exists();
                    
                return [
                    'type' => 'user',
                    'id' => 'user_' . $user->id,
                    'user' => $user,
                    'display_name' => $user->name,
                    'phone' => $user->phone,
                    'is_contact' => $isContact,
                    'avatar_url' => $user->avatar_url,
                    'slug' => $user->slug,
                ];
            })->toArray();
    }
    
    private function searchGroups(int $userId, string $searchTerm, int $limit): array
    {
        return Group::where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', $searchTerm)
                  ->orWhere('description', 'LIKE', $searchTerm)
                  ->orWhere('slug', 'LIKE', $searchTerm);
            })
            ->where(function ($q) use ($userId) {
                $q->whereHas('members', fn($m) => $m->where('user_id', $userId))
                  ->orWhere('is_public', true);
            })
            ->withCount('members')
            ->with(['members' => fn($q) => $q->where('user_id', $userId)->withPivot('role')])
            ->limit($limit)
            ->get()
            ->map(function ($group) {
                return [
                    'type' => 'group',
                    'id' => 'group_' . $group->id,
                    'group' => $group,
                    'display_name' => $group->name,
                    'member_count' => $group->members_count,
                    'is_member' => $group->members->isNotEmpty(),
                    'avatar_url' => $group->avatar_url,
                    'slug' => $group->slug,
                ];
            })->toArray();
    }
    
    private function searchConversations(int $userId, string $searchTerm, string $phoneDigits, int $limit): array
    {
        return Conversation::forUser($userId)
            ->where(function ($q) use ($searchTerm, $phoneDigits, $userId) {
                // Search by other member's name or phone
                $q->whereHas('members', function ($memberQuery) use ($searchTerm, $phoneDigits, $userId) {
                    $memberQuery->where('user_id', '!=', $userId)
                        ->whereHas('user', function ($userQuery) use ($searchTerm, $phoneDigits) {
                            $userQuery->where(function ($uq) use ($searchTerm, $phoneDigits) {
                                $uq->where('name', 'LIKE', $searchTerm);
                                if (!empty($phoneDigits)) {
                                    $uq->orWhere('phone', 'LIKE', '%' . $phoneDigits . '%');
                                }
                            });
                        });
                })
                // Or search by message content in the conversation
                ->orWhereHas('messages', function ($msgQuery) use ($searchTerm) {
                    $msgQuery->where('body', 'LIKE', $searchTerm);
                });
            })
            ->with(['latestMessage', 'members' => function ($q) use ($userId) {
                $q->where('user_id', '!=', $userId);
            }])
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($conversation) use ($userId) {
                $otherUser = $conversation->members->firstWhere('id', '!=', $userId);
                
                return [
                    'type' => 'conversation',
                    'id' => 'conversation_' . $conversation->id,
                    'conversation' => $conversation,
                    'display_name' => $conversation->title,
                    'phone' => $otherUser?->phone ?? null,
                    'avatar_url' => $conversation->avatar_url,
                    'last_message' => $conversation->latestMessage?->body,
                    'timestamp' => $conversation->updated_at,
                    'conversation_slug' => $conversation->slug,
                    'unread_count' => $conversation->unreadCountFor($userId),
                ];
            })->toArray();
    }
    
    private function searchMessages(int $userId, string $query, int $limit): array
    {
        return Message::where(function ($q) use ($query) {
                $q->where('body', 'LIKE', '%' . $query . '%')
                  ->orWhereHas('attachments', function ($a) use ($query) {
                      $a->where('original_name', 'LIKE', '%' . $query . '%');
                  });
            })
            ->whereHas('conversation.members', fn($m) => $m->where('user_id', $userId))
            ->with(['conversation', 'sender'])
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($message) use ($userId, $query) {
                $conversation = $message->conversation;
                $snippet = $this->createMessageSnippet($message, $query);
                
                return [
                    'type' => 'message',
                    'id' => 'message_' . $message->id,
                    'message' => $message,
                    'conversation' => $conversation,
                    'sender' => $message->sender,
                    'display_name' => $conversation->title,
                    'snippet' => $snippet,
                    'avatar_url' => $conversation->avatar_url,
                    'timestamp' => $message->created_at,
                    'conversation_slug' => $conversation->slug,
                ];
            })->toArray();
    }
    
    private function getPhoneSuggestion(int $userId, string $phone): array
    {
        $normalizedPhone = Contact::normalizePhone($phone);
        
        return [
            'type' => 'phone_suggestion',
            'id' => 'phone_' . $normalizedPhone,
            'phone' => $normalizedPhone,
            'display_name' => "Message $normalizedPhone",
            'description' => 'Start a new conversation',
            'is_registered' => User::where('phone', $normalizedPhone)->exists(),
            'avatar_url' => null,
        ];
    }
    
    private function createMessageSnippet(Message $message, string $query): string
    {
        $body = $message->body;
        $query = strtolower($query);
        
        // Find position of query in message
        $pos = stripos($body, $query);
        
        if ($pos === false) {
            // Check attachments
            if ($message->attachments->isNotEmpty()) {
                return "File: " . $message->attachments->first()->original_name;
            }
            return Str::limit($body, 50);
        }
        
        // Create snippet with highlighted context
        $start = max(0, $pos - 20);
        $snippet = substr($body, $start, 60);
        
        if ($start > 0) {
            $snippet = '...' . $snippet;
        }
        
        if ($start + 60 < strlen($body)) {
            $snippet = $snippet . '...';
        }
        
        return $snippet;
    }
    
    private function scoreAndMergeResults(array $results, string $query, int $userId): array
    {
        $allResults = [];
        $processedKeys = [];
        
        foreach ($results as $category => $items) {
            foreach ($items as $item) {
                $item['score'] = $this->calculateRelevanceScore($item, $query, $userId);
                
                // Deduplicate by phone/user ID
                $key = $this->getDeduplicationKey($item);
                if (!in_array($key, $processedKeys)) {
                    $allResults[] = $item;
                    $processedKeys[] = $key;
                }
            }
        }
        
        return $allResults;
    }
    
    private function calculateRelevanceScore(array $item, string $query, int $userId): float
    {
        $score = 0;
        $query = strtolower(trim($query));
        
        switch ($item['type']) {
            case 'contact':
                $score += 100;
                if (strtolower($item['display_name']) === $query) $score += 50;
                if ($item['is_registered']) $score += 30;
                break;
                
            case 'user':
                $score += 90;
                if (strtolower($item['user']->slug) === $query) $score += 70;
                if (strtolower($item['display_name']) === $query) $score += 50;
                if ($item['is_contact']) $score += 20;
                break;
                
            case 'conversation':
                $score += 85;
                if ($item['unread_count'] > 0) $score += 25;
                if (strtolower($item['display_name']) === $query) $score += 40;
                // Boost recent conversations
                $daysAgo = $item['timestamp']->diffInDays(now());
                $score += max(0, 20 - $daysAgo);
                break;
                
            case 'group':
                $score += 80;
                if ($item['is_member']) $score += 30;
                if (strtolower($item['display_name']) === $query) $score += 40;
                break;
                
            case 'message':
                $score += 70;
                // Boost recent messages
                $daysAgo = $item['timestamp']->diffInDays(now());
                $score += max(0, 30 - $daysAgo);
                break;
                
            case 'phone_suggestion':
                $score += 60;
                break;
        }
        
        return $score;
    }
    
    private function getDeduplicationKey(array $item): string
    {
        switch ($item['type']) {
            case 'contact':
            case 'user':
                return 'user_' . ($item['user']['id'] ?? $item['phone'] ?? $item['id']);
            case 'conversation':
                return 'conversation_' . $item['conversation']['id'];
            case 'group':
                return 'group_' . $item['group']['id'];
            case 'message':
                return 'message_' . $item['message']['id'];
            case 'phone_suggestion':
                return 'phone_' . $item['phone'];
            default:
                return $item['type'] . '_' . $item['id'];
        }
    }
    
    private function applyFinalFilters(array $results, array $filters, int $limit): array
    {
        $filtered = collect($results);
        
        // Apply unread filter
        if (in_array('unread', $filters)) {
            $filtered = $filtered->filter(function ($item) {
                return in_array($item['type'], ['contact', 'user', 'group']) && 
                       $this->hasUnreadMessages($item);
            });
        }
        
        return $filtered->sortByDesc('score')
            ->take($limit)
            ->values()
            ->toArray();
    }
    
    private function hasUnreadMessages(array $item): bool
    {
        // Implement unread check based on item type
        // This would check conversation unread counts
        return false; // Placeholder
    }
    
    private function looksLikePhoneNumber(string $query): bool
    {
        $digits = preg_replace('/\D+/', '', $query);
        return strlen($digits) >= 9 && strlen($digits) <= 15;
    }
    
    private function getRecentChats(int $userId, int $limit): array
    {
        return Conversation::forUser($userId)
            ->with(['latestMessage', 'members'])
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($conversation) use ($userId) {
                return [
                    'type' => 'recent_chat',
                    'conversation' => $conversation,
                    'display_name' => $conversation->title,
                    'avatar_url' => $conversation->avatar_url,
                    'unread_count' => $conversation->unread_count,
                    'last_message' => $conversation->latestMessage?->body,
                    'timestamp' => $conversation->updated_at,
                ];
            })->toArray();
    }
    
    private function getFrequentContacts(int $userId, int $limit): array
    {
        // Implement frequent contacts logic
        return [];
    }
    
    private function getUnreadChats(int $userId, int $limit): array
    {
        return Conversation::forUser($userId)
            ->with(['latestMessage', 'members'])
            ->get()
            ->filter(fn($conv) => $conv->unread_count > 0)
            ->sortByDesc('updated_at')
            ->take($limit)
            ->map(function ($conversation) {
                return [
                    'type' => 'unread_chat',
                    'conversation' => $conversation,
                    'display_name' => $conversation->title,
                    'avatar_url' => $conversation->avatar_url,
                    'unread_count' => $conversation->unread_count,
                    'last_message' => $conversation->latestMessage?->body,
                ];
            })->toArray();
    }
}