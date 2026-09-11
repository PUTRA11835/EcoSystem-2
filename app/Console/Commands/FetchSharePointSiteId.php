<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FetchSharePointSiteId extends Command
{
    protected $signature   = 'sharepoint:sites {--host= : SharePoint hostname, e.g. myecosystem.sharepoint.com}';
    protected $description = 'Fetch SharePoint site ID(s) using configured MS Graph credentials';

    public function handle(): int
    {
        $tenantId     = config('services.microsoft_graph.tenant_id');
        $clientId     = config('services.microsoft_graph.client_id');
        $clientSecret = config('services.microsoft_graph.client_secret');
        $baseUrl      = rtrim(config('services.microsoft_graph.base_url', 'https://graph.microsoft.com/v1.0'), '/');

        $this->info('Mendapatkan access token...');

        $tokenRes = Http::asForm()->post(
            "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
            [
                'grant_type'    => 'client_credentials',
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'scope'         => 'https://graph.microsoft.com/.default',
            ]
        );

        if (!$tokenRes->successful()) {
            $this->error('Gagal mendapatkan token: ' . $tokenRes->body());
            return 1;
        }

        $token = $tokenRes->json('access_token');
        $this->info('Token berhasil didapat.');

        $host = $this->option('host');

        if ($host) {
            // Cari root site dari host spesifik
            $this->info("Mencari site di {$host}...");
            $res = Http::withToken($token)->get("{$baseUrl}/sites/{$host}");

            if (!$res->successful()) {
                $this->error('Gagal: ' . $res->body());
                $this->line('Coba tanpa --host untuk list semua site.');
                return 1;
            }

            $site = $res->json();
            $this->showSite($site);
        } else {
            // List semua site yang bisa diakses
            $this->info('Mencari semua SharePoint site yang bisa diakses...');
            $res = Http::withToken($token)->get("{$baseUrl}/sites", ['search' => '*']);

            if (!$res->successful()) {
                $this->error('Gagal list sites: ' . $res->body());
                $this->line('');
                $this->line('Coba akses root site dengan --host:');
                $this->line('  php artisan sharepoint:sites --host=NAMAORG.sharepoint.com');
                return 1;
            }

            $sites = $res->json('value', []);

            if (empty($sites)) {
                $this->warn('Tidak ada site yang ditemukan. Pastikan app memiliki Sites.Read.All permission.');
                $this->line('');
                $this->line('Coba akses root site langsung:');
                $this->line('  php artisan sharepoint:sites --host=NAMAORG.sharepoint.com');
                return 1;
            }

            $this->info('Ditemukan ' . count($sites) . ' site:');
            $this->line('');

            foreach ($sites as $site) {
                $this->showSite($site);
            }
        }

        $this->line('');
        $this->line('Salin Site ID di atas ke .env:');
        $this->line('  SHAREPOINT_SITE_ID=<site-id>');
        $this->line('Lalu jalankan: php artisan config:clear');

        return 0;
    }

    private function showSite(array $site): void
    {
        $this->line('────────────────────────────────────────');
        $this->line('  Nama    : ' . ($site['displayName'] ?? $site['name'] ?? '-'));
        $this->line('  URL     : ' . ($site['webUrl'] ?? '-'));
        $this->line('  Site ID : <fg=green>' . ($site['id'] ?? '-') . '</>');
        $this->line('');
    }
}
