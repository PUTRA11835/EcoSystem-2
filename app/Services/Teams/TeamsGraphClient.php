<?php

namespace App\Services\Teams;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Klien Microsoft Graph khusus chat Teams — token client-credentials + GET/POST.
 *
 * SENGAJA berdiri sendiri, tidak menumpang EmailController::getAccessTokenPublic().
 * Alasannya pagar isolasi (docs/teams-chat-sync-design.md §2): jalur email tidak
 * boleh punya cara baru untuk gagal gara-gara fitur ini. Kredensialnya memang
 * kredensial yang sama — tapi lewat config sendiri yang jatuh balik ke MS_*,
 * sehingga memisahkan app registration nanti cukup lewat .env.
 *
 * Yang TIDAK ada di kelas ini, dan alasannya (terbukti di spike 17 Sep 2026):
 *
 *  - Resolusi user lewat GET /users/{id} — app ini TIDAK punya User.Read.All,
 *    panggilannya 403. Identitas diambil dari roster chat; lihat design §7.
 *  - Kirim pesan (POST /chats/{id}/messages) — tidak bisa app-only sama sekali,
 *    hanya tersedia untuk Teamwork.Migrate.All. Arah keluar lewat Power Automate.
 */
class TeamsGraphClient
{
    /**
     * Token di-cache sedikit lebih pendek dari masa berlakunya, supaya tidak ada
     * request yang berangkat membawa token yang kedaluwarsa di tengah jalan.
     */
    private const TOKEN_EXPIRY_MARGIN = 120;

    public function isConfigured(): bool
    {
        return $this->tenantId() !== '' && $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    // ─── Konfigurasi ─────────────────────────────────────────────────────────

    private function tenantId(): string
    {
        return trim((string) config('services.microsoft_graph_teams.tenant_id'));
    }

    private function clientId(): string
    {
        return trim((string) config('services.microsoft_graph_teams.client_id'));
    }

    private function clientSecret(): string
    {
        return trim((string) config('services.microsoft_graph_teams.client_secret'));
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.microsoft_graph_teams.base_url'), '/');
    }

    private function timeout(): int
    {
        return max(5, (int) config('services.teams_sync.timeout', 15));
    }

    // ─── Token ───────────────────────────────────────────────────────────────

    /**
     * Cache key mengandung client_id supaya rotasi kredensial atau pemindahan ke
     * app registration terpisah tidak memakai token app lama yang masih tersimpan.
     */
    private function tokenCacheKey(): string
    {
        return 'teams_graph_token_' . sha1($this->tenantId() . '|' . $this->clientId());
    }

    public function accessToken(): string
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Kredensial Microsoft Graph untuk Teams belum dikonfigurasi.');
        }

        $cached = Cache::get($this->tokenCacheKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()
            ->timeout($this->timeout())
            ->post("https://login.microsoftonline.com/{$this->tenantId()}/oauth2/v2.0/token", [
                'grant_type'    => 'client_credentials',
                'client_id'     => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'scope'         => 'https://graph.microsoft.com/.default',
            ]);

        if (!$response->successful()) {
            // Body-nya ikut dibawa apa adanya: inilah yang membedakan secret
            // kedaluwarsa (AADSTS7000222) dari permission yang dicabut, dan
            // tanpa itu diagnosanya jadi tebak-tebakan.
            throw new RuntimeException(
                'Gagal memperoleh Graph access token untuk Teams: ' . $response->body()
            );
        }

        $token     = (string) $response->json('access_token');
        $expiresIn = (int) $response->json('expires_in', 3600);

        if ($token === '') {
            throw new RuntimeException('Graph mengembalikan access token kosong untuk Teams.');
        }

        Cache::put($this->tokenCacheKey(), $token, max(60, $expiresIn - self::TOKEN_EXPIRY_MARGIN));

        return $token;
    }

    /** Buang token tersimpan — dipakai saat Graph menjawab 401. */
    public function forgetToken(): void
    {
        Cache::forget($this->tokenCacheKey());
    }

    // ─── Panggilan ───────────────────────────────────────────────────────────

    /**
     * GET Graph. $query dirakit oleh pemanggil sebagai pasangan kunci-nilai
     * mentah ('$filter' => "...") — Http::get() yang meng-encode-nya.
     *
     * @throws TeamsGraphException kalau Graph membalas non-2xx.
     */
    public function get(string $path, array $query = []): array
    {
        return $this->send('GET', $path, $query);
    }

    /** @throws TeamsGraphException */
    public function post(string $path, array $payload): array
    {
        return $this->send('POST', $path, [], $payload);
    }

    /**
     * Ambil byte mentah (mis. hostedContents/{id}/$value).
     *
     * Dikembalikan sebagai Response, bukan string, karena MIME-nya HANYA ada di
     * header Content-Type respons — di daftar hostedContents, contentBytes dan
     * contentType keduanya null (terbukti di spike). Pemanggil butuh header itu.
     *
     * Belum dipakai di fase teks-saja; disediakan supaya fase lampiran tidak
     * perlu membongkar kelas ini lagi.
     *
     * @throws TeamsGraphException
     */
    public function getRaw(string $path): Response
    {
        $response = Http::withToken($this->accessToken())
            ->timeout($this->timeout())
            ->get($this->url($path));

        if ($response->status() === 401) {
            $this->forgetToken();
            $response = Http::withToken($this->accessToken())
                ->timeout($this->timeout())
                ->get($this->url($path));
        }

        if (!$response->successful()) {
            throw TeamsGraphException::fromResponse('GET', $path, $response);
        }

        return $response;
    }

    /**
     * Satu putaran request, dengan satu kali coba ulang khusus untuk 401.
     *
     * 401 di sini hampir selalu berarti token cache sudah tidak berlaku (secret
     * dirotasi, atau token dicabut) — bukan permission yang salah, yang muncul
     * sebagai 403. Membuang cache lalu mencoba sekali lagi menyembuhkannya tanpa
     * menunggu TTL habis. Selain 401, tidak ada retry: command penjadwal akan
     * datang lagi semenit kemudian.
     *
     * @throws TeamsGraphException
     */
    private function send(string $method, string $path, array $query = [], ?array $payload = null): array
    {
        $response = $this->dispatch($method, $path, $query, $payload);

        if ($response->status() === 401) {
            $this->forgetToken();
            $response = $this->dispatch($method, $path, $query, $payload);
        }

        if (!$response->successful()) {
            throw TeamsGraphException::fromResponse($method, $path, $response);
        }

        return $response->json() ?? [];
    }

    private function dispatch(string $method, string $path, array $query, ?array $payload): Response
    {
        $request = Http::withToken($this->accessToken())
            ->acceptJson()
            ->timeout($this->timeout());

        return $method === 'POST'
            ? $request->post($this->url($path), $payload ?? [])
            : $request->get($this->url($path), $query);
    }

    /**
     * chat_id mengandung ':' dan '@' ("19:xxx@thread.v2") yang harus ter-encode
     * saat jadi segmen path. Pemanggil merakit path lewat chatPath() di bawah,
     * jadi encoding-nya cuma ditulis sekali.
     */
    private function url(string $path): string
    {
        return $this->baseUrl() . '/' . ltrim($path, '/');
    }

    /** Path aman untuk sebuah chat, mis. chatPath($id, 'messages'). */
    public static function chatPath(string $chatId, string $suffix = ''): string
    {
        $path = 'chats/' . rawurlencode($chatId);

        return $suffix === '' ? $path : $path . '/' . ltrim($suffix, '/');
    }
}
