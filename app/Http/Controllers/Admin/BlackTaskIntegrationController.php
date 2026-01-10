<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BlackTaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class BlackTaskIntegrationController extends Controller
{
    /**
     * Show BlackTask integration settings
     */
    public function index(BlackTaskService $blackTaskService)
    {
        $isConfigured = $blackTaskService->isConfigured();
        $connectionTest = null;

        if ($isConfigured) {
            $connectionTest = $blackTaskService->testConnection();
        }

        return view('admin.blacktask.index', [
            'isConfigured' => $isConfigured,
            'connectionTest' => $connectionTest,
            'blacktaskUrl' => config('services.blacktask.url'),
        ]);
    }

    /**
     * Test connection to BlackTask
     */
    public function testConnection(BlackTaskService $blackTaskService)
    {
        $result = $blackTaskService->testConnection();

        return response()->json($result);
    }

    /**
     * Update BlackTask configuration
     */
    public function updateConfig(Request $request)
    {
        $request->validate([
            'url' => 'required|url',
            'api_token' => 'required|string',
        ]);

        // Update .env file
        $this->updateEnvFile([
            'BLACKTASK_URL' => $request->url,
            'BLACKTASK_API_TOKEN' => $request->api_token,
        ]);

        // Clear config cache
        Artisan::call('config:clear');

        return response()->json([
            'success' => true,
            'message' => 'BlackTask configuration updated successfully'
        ]);
    }

    /**
     * Update .env file with new values
     */
    private function updateEnvFile(array $data)
    {
        $envPath = base_path('.env');
        $envContent = file_get_contents($envPath);

        foreach ($data as $key => $value) {
            $pattern = "/^{$key}=.*/m";
            $replacement = "{$key}={$value}";

            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
            } else {
                $envContent .= "\n{$replacement}";
            }
        }

        file_put_contents($envPath, $envContent);
    }
}
