<?php

namespace App\Console\Commands;

use App\Models\SecurityEvent;
use App\Services\LoginSecurityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Reactive health check for the 3 external credentials this app depends on:
 * MS Graph (email sending), Anthropic API key, OpenAI API key. All 3 can
 * silently die (expired secret, revoked/rotated key) with zero admin-facing
 * signal today - confirmed ProcessEmailInbox::handle() swallows a Graph auth
 * failure internally and still returns Command::SUCCESS, so Schedule
 * Monitor's existing consecutive-failure alerting never sees it.
 *
 * Reactive only (not a proactive expiry-date lookup): querying an Azure AD
 * app registration's actual secret-expiry date needs an Application.Read.All
 * permission this app's registration doesn't currently have. Confirming a
 * fresh token/API call still succeeds is what's available with the access
 * already granted - see the project plan this was built from for the full
 * rationale.
 */
class CheckIntegrationCredentials extends Command
{
    protected $signature = 'schedule-monitor:check-integration-health';

    protected $description = 'Reactively check MS Graph / Anthropic / OpenAI credentials are still valid.';

    // Don't re-alert every 6h run for a single sustained outage.
    private const NOTIFY_DEBOUNCE_MINUTES = 360;

    public function handle(): int
    {
        $results = [
            $this->checkMsGraph(),
            $this->checkAnthropic(),
            $this->checkOpenAi(),
        ];

        $this->table(
            ['Integration', 'Status', 'Detail'],
            array_map(fn ($r) => [$r['name'], $r['status'], $r['detail']], $results)
        );

        foreach ($results as $result) {
            if ($result['status'] === 'unhealthy') {
                $this->recordUnhealthy($result);
            }
        }

        Log::info('CheckIntegrationCredentials completed', ['results' => $results]);

        return self::SUCCESS;
    }

    private function checkMsGraph(): array
    {
        $tenantId     = config('services.microsoft_graph.tenant_id');
        $clientId     = config('services.microsoft_graph.client_id');
        $clientSecret = config('services.microsoft_graph.client_secret');

        if (!$tenantId || !$clientId || !$clientSecret) {
            return ['name' => 'MS Graph', 'status' => 'not_configured', 'detail' => 'tenant_id/client_id/client_secret missing from config.'];
        }

        try {
            $response = Http::asForm()->timeout(10)->post("https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token", [
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'scope'         => 'https://graph.microsoft.com/.default',
                'grant_type'    => 'client_credentials',
            ]);

            if ($response->successful() && $response->json('access_token')) {
                return ['name' => 'MS Graph', 'status' => 'healthy', 'detail' => 'Token obtained successfully.'];
            }

            $body   = (string) $response->body();
            $reason = match (true) {
                str_contains($body, 'AADSTS7000222') => 'Client secret has expired.',
                str_contains($body, 'AADSTS7000215') => 'Invalid client secret.',
                default => "HTTP {$response->status()}: " . Str::limit($body, 200),
            };

            return ['name' => 'MS Graph', 'status' => 'unhealthy', 'detail' => $reason];
        } catch (\Throwable $e) {
            return ['name' => 'MS Graph', 'status' => 'unhealthy', 'detail' => 'Request failed: ' . $e->getMessage()];
        }
    }

    private function checkAnthropic(): array
    {
        $apiKey = config('services.anthropic.api_key');

        if (!$apiKey) {
            return ['name' => 'Anthropic', 'status' => 'not_configured', 'detail' => 'ANTHROPIC_API_KEY not set.'];
        }

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])->timeout(10)->get('https://api.anthropic.com/v1/models');

            if ($response->successful()) {
                return ['name' => 'Anthropic', 'status' => 'healthy', 'detail' => 'API key valid.'];
            }

            return ['name' => 'Anthropic', 'status' => 'unhealthy', 'detail' => "HTTP {$response->status()}: " . Str::limit((string) $response->body(), 200)];
        } catch (\Throwable $e) {
            return ['name' => 'Anthropic', 'status' => 'unhealthy', 'detail' => 'Request failed: ' . $e->getMessage()];
        }
    }

    private function checkOpenAi(): array
    {
        $apiKey = config('services.openai.api_key');

        if (!$apiKey) {
            return ['name' => 'OpenAI', 'status' => 'not_configured', 'detail' => 'OPENAI_API_KEY not set.'];
        }

        try {
            $response = Http::withToken($apiKey)->timeout(10)->get('https://api.openai.com/v1/models');

            if ($response->successful()) {
                return ['name' => 'OpenAI', 'status' => 'healthy', 'detail' => 'API key valid.'];
            }

            return ['name' => 'OpenAI', 'status' => 'unhealthy', 'detail' => "HTTP {$response->status()}: " . Str::limit((string) $response->body(), 200)];
        } catch (\Throwable $e) {
            return ['name' => 'OpenAI', 'status' => 'unhealthy', 'detail' => 'Request failed: ' . $e->getMessage()];
        }
    }

    private function recordUnhealthy(array $result): void
    {
        $debounceKey = 'integration_health_notif_' . Str::slug($result['name']);

        $event = SecurityEvent::record([
            'event_type'  => 'integration_credential_unhealthy',
            'severity'    => 'high',
            'module'      => 'Integration',
            'status'      => 'open',
            'title'       => "{$result['name']} credential unhealthy",
            'description' => "{$result['name']} health check failed: {$result['detail']}",
            'payload'     => $result,
        ]);

        if ($event && Cache::add($debounceKey, true, now()->addMinutes(self::NOTIFY_DEBOUNCE_MINUTES))) {
            app(LoginSecurityService::class)->notifyAdmins($event, "{$result['name']} credential appears unhealthy: {$result['detail']}");
        }
    }
}
