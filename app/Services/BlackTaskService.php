<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * BlackTask Integration Service
 * 
 * Integrates GekyChat AI Assistant with BlackTask todo list management
 * Uses phone number to identify users across both platforms
 */
class BlackTaskService
{
    private string $baseUrl;
    private string $apiToken;
    private int $timeout = 30;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.blacktask.url', 'https://blacktask.com'), '/');
        $this->apiToken = config('services.blacktask.api_token', '');
    }

    /**
     * Get or create BlackTask user by phone number
     * 
     * @param string $phone User's phone number
     * @return array|null User data or null if failed
     */
    public function getUserByPhone(string $phone): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiToken,
                    'Accept' => 'application/json',
                ])
                ->get("{$this->baseUrl}/api/users/by-phone", [
                    'phone' => $phone
                ]);

            if ($response->successful()) {
                return $response->json('data');
            }

            Log::warning('BlackTask user lookup failed', [
                'phone' => $phone,
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('BlackTask user lookup error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create a new task in BlackTask
     * 
     * @param string $phone User's phone number
     * @param array $taskData Task details
     * @return array Result with success status and task data
     */
    public function createTask(string $phone, array $taskData): array
    {
        try {
            // Get user's BlackTask token
            $userToken = $this->getUserToken($phone);
            if (!$userToken) {
                return [
                    'success' => false,
                    'message' => 'BlackTask account not found. Please register at ' . $this->baseUrl
                ];
            }

            // Parse natural language date if present
            $taskData = $this->parseTaskDate($taskData);

            // Validate and set defaults
            $taskData['title'] = $taskData['title'] ?? $taskData['task'] ?? 'New Task';
            $taskData['task_date'] = $taskData['task_date'] ?? today()->toDateString();
            $taskData['priority'] = $taskData['priority'] ?? 1; // Medium priority
            
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $userToken,
                    'Accept' => 'application/json',
                ])
                ->post("{$this->baseUrl}/api/tasks", $taskData);

            if ($response->successful()) {
                $task = $response->json('task');
                return [
                    'success' => true,
                    'message' => 'Task created successfully',
                    'task' => $task
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to create task: ' . ($response->json('message') ?? 'Unknown error')
            ];

        } catch (\Exception $e) {
            Log::error('BlackTask create task error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error creating task: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get user's tasks from BlackTask
     * 
     * @param string $phone User's phone number
     * @param array $filters Optional filters (date, category, priority, status)
     * @return array Result with success status and tasks
     */
    public function getTasks(string $phone, array $filters = []): array
    {
        try {
            $userToken = $this->getUserToken($phone);
            if (!$userToken) {
                return [
                    'success' => false,
                    'message' => 'BlackTask account not found'
                ];
            }

            // Build query parameters
            $params = [];
            if (isset($filters['start'])) {
                $params['start'] = $filters['start'];
            }
            if (isset($filters['end'])) {
                $params['end'] = $filters['end'];
            }
            if (!isset($params['start']) && !isset($params['end'])) {
                // Default to today's tasks
                $params['start'] = today()->toDateString();
                $params['end'] = today()->toDateString();
            }

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $userToken,
                    'Accept' => 'application/json',
                ])
                ->get("{$this->baseUrl}/api/tasks", $params);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'tasks' => $response->json()
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to fetch tasks'
            ];

        } catch (\Exception $e) {
            Log::error('BlackTask get tasks error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error fetching tasks: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update a task in BlackTask
     * 
     * @param string $phone User's phone number
     * @param int $taskId Task ID
     * @param array $updates Task updates
     * @return array Result with success status
     */
    public function updateTask(string $phone, int $taskId, array $updates): array
    {
        try {
            $userToken = $this->getUserToken($phone);
            if (!$userToken) {
                return [
                    'success' => false,
                    'message' => 'BlackTask account not found'
                ];
            }

            // Parse natural language date if present
            $updates = $this->parseTaskDate($updates);

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $userToken,
                    'Accept' => 'application/json',
                ])
                ->patch("{$this->baseUrl}/api/tasks/{$taskId}", $updates);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Task updated successfully',
                    'task' => $response->json('task')
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to update task: ' . ($response->json('message') ?? 'Unknown error')
            ];

        } catch (\Exception $e) {
            Log::error('BlackTask update task error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error updating task: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Mark task as complete
     * 
     * @param string $phone User's phone number
     * @param int $taskId Task ID
     * @return array Result with success status
     */
    public function completeTask(string $phone, int $taskId): array
    {
        return $this->updateTask($phone, $taskId, ['is_done' => true]);
    }

    /**
     * Delete a task from BlackTask
     * 
     * @param string $phone User's phone number
     * @param int $taskId Task ID
     * @return array Result with success status
     */
    public function deleteTask(string $phone, int $taskId): array
    {
        try {
            $userToken = $this->getUserToken($phone);
            if (!$userToken) {
                return [
                    'success' => false,
                    'message' => 'BlackTask account not found'
                ];
            }

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $userToken,
                    'Accept' => 'application/json',
                ])
                ->delete("{$this->baseUrl}/api/tasks/{$taskId}");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Task deleted successfully'
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to delete task'
            ];

        } catch (\Exception $e) {
            Log::error('BlackTask delete task error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error deleting task: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get task statistics
     * 
     * @param string $phone User's phone number
     * @return array Result with success status and statistics
     */
    public function getStatistics(string $phone): array
    {
        try {
            $userToken = $this->getUserToken($phone);
            if (!$userToken) {
                return [
                    'success' => false,
                    'message' => 'BlackTask account not found'
                ];
            }

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $userToken,
                    'Accept' => 'application/json',
                ])
                ->get("{$this->baseUrl}/api/tasks/statistics");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'statistics' => $response->json()
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to fetch statistics'
            ];

        } catch (\Exception $e) {
            Log::error('BlackTask get statistics error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error fetching statistics: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get user's API token from BlackTask
     * Cached for 1 hour to reduce API calls
     * 
     * @param string $phone User's phone number
     * @return string|null API token or null if not found
     */
    private function getUserToken(string $phone): ?string
    {
        $cacheKey = "blacktask_token_{$phone}";
        
        return Cache::remember($cacheKey, 3600, function () use ($phone) {
            $user = $this->getUserByPhone($phone);
            return $user['api_token'] ?? null;
        });
    }

    /**
     * Parse natural language date expressions
     * 
     * @param array $taskData Task data
     * @return array Task data with parsed date
     */
    private function parseTaskDate(array $taskData): array
    {
        $title = $taskData['title'] ?? $taskData['task'] ?? '';
        
        // Common date patterns
        $patterns = [
            '/\b(today|tod)\b/i' => today(),
            '/\b(tomorrow|tmrw|tmr)\b/i' => today()->addDay(),
            '/\b(next week)\b/i' => today()->addWeek(),
            '/\b(next month)\b/i' => today()->addMonth(),
            '/\b(monday|mon)\b/i' => Carbon::parse('next monday'),
            '/\b(tuesday|tue)\b/i' => Carbon::parse('next tuesday'),
            '/\b(wednesday|wed)\b/i' => Carbon::parse('next wednesday'),
            '/\b(thursday|thu)\b/i' => Carbon::parse('next thursday'),
            '/\b(friday|fri)\b/i' => Carbon::parse('next friday'),
            '/\b(saturday|sat)\b/i' => Carbon::parse('next saturday'),
            '/\b(sunday|sun)\b/i' => Carbon::parse('next sunday'),
        ];

        foreach ($patterns as $pattern => $date) {
            if (preg_match($pattern, $title)) {
                $taskData['task_date'] = $date->toDateString();
                // Remove date keywords from title
                $taskData['title'] = trim(preg_replace($pattern, '', $title));
                break;
            }
        }

        // Try to parse explicit dates (YYYY-MM-DD, DD/MM/YYYY, etc.)
        if (!isset($taskData['task_date']) && preg_match('/\b\d{4}-\d{2}-\d{2}\b/', $title, $matches)) {
            $taskData['task_date'] = $matches[0];
            $taskData['title'] = trim(str_replace($matches[0], '', $title));
        }

        return $taskData;
    }

    /**
     * Parse priority from task text
     * 
     * @param string $text Task text
     * @return int Priority level (0=low, 1=medium, 2=high)
     */
    public function parsePriority(string $text): int
    {
        $text = strtolower($text);
        
        if (preg_match('/\b(urgent|important|high|critical|asap)\b/', $text)) {
            return 2; // High priority
        }
        
        if (preg_match('/\b(low|minor|later)\b/', $text)) {
            return 0; // Low priority
        }
        
        return 1; // Medium priority (default)
    }

    /**
     * Format tasks for display in chat
     * 
     * @param array $tasks Array of tasks
     * @return string Formatted task list
     */
    public function formatTasksForChat(array $tasks): string
    {
        if (empty($tasks)) {
            return "📋 You have no tasks for this period.";
        }

        $message = "📋 **Your Tasks**\n\n";
        
        foreach ($tasks as $index => $task) {
            $checkbox = $task['extendedProps']['is_done'] ?? false ? '✅' : '⬜';
            $title = $task['title'] ?? 'Untitled';
            $priority = $task['extendedProps']['priority'] ?? 1;
            $category = $task['extendedProps']['category'] ?? '';
            
            $priorityIcon = match($priority) {
                2 => '🔴',
                0 => '🟢',
                default => '🟡'
            };
            
            $message .= "{$checkbox} {$priorityIcon} **{$title}**";
            if ($category) {
                $message .= " ({$category})";
            }
            $message .= "\n";
            
            if ($index >= 9) { // Limit to 10 tasks
                $remaining = count($tasks) - 10;
                if ($remaining > 0) {
                    $message .= "\n... and {$remaining} more tasks";
                }
                break;
            }
        }
        
        return $message;
    }

    /**
     * Check if BlackTask integration is configured
     * 
     * @return bool True if configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->baseUrl) && !empty($this->apiToken);
    }

    /**
     * Test connection to BlackTask API
     * 
     * @return array Result with success status
     */
    public function testConnection(): array
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiToken,
                    'Accept' => 'application/json',
                ])
                ->get("{$this->baseUrl}/api/user");

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful() 
                    ? 'BlackTask connection successful' 
                    : 'BlackTask connection failed'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage()
            ];
        }
    }
}
