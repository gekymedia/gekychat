<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppVersionDeployTest extends TestCase
{
    use RefreshDatabase;

    public function test_deploy_hook_updates_android_latest_version_with_valid_token(): void
    {
        config(['app_versions.deploy_token' => 'deploy-secret']);

        $response = $this->patchJson('/api/v1/app/version/latest', [
            'platform' => 'android',
            'latest_version' => '1.0.1+101',
        ], [
            'Authorization' => 'Bearer deploy-secret',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.platform', 'android')
            ->assertJsonPath('data.latest_version', '1.0.1+101');

        $this->assertSame(
            '1.0.1+101',
            SystemSetting::getValue('app_version_android_latest')
        );
    }

    public function test_deploy_hook_rejects_missing_or_invalid_token(): void
    {
        config(['app_versions.deploy_token' => 'deploy-secret']);

        $this->patchJson('/api/v1/app/version/latest', [
            'platform' => 'android',
            'latest_version' => '1.0.1+101',
        ])->assertUnauthorized();

        $this->patchJson('/api/v1/app/version/latest', [
            'platform' => 'android',
            'latest_version' => '1.0.1+101',
        ], [
            'Authorization' => 'Bearer wrong',
        ])->assertUnauthorized();
    }
}
