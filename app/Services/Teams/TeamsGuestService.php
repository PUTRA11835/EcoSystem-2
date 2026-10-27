<?php

namespace App\Services\Teams;

use App\Models\TeamsGuestUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Consultant eksternal di group chat tiket: undangan guest + object id-nya.
 *
 * Consultant tanpa akun kantor (Gmail, domain vendor) hanya bisa masuk Teams
 * Eclectic sebagai GUEST tenant. Alamat emailnya tidak bisa langsung dipakai di
 * `users('...')` — Graph hanya mengenal object id atau UPN, dan UPN guest
 * berbentuk `nama_gmail.com#EXT#@...`. Id itu didapat dari respons
 * POST /invitations lalu disimpan di `teams_guest_user`.
 *
 * Yang terbukti di spike 20 Okt 2026:
 *  - guest yang undangannya BELUM diterima sudah bisa dimasukkan ke group chat
 *    (POST /chats) dengan role `owner`, sama seperti anggota internal;
 *  - roster chat (GET /chats/{id}/members) mengembalikan alamat Gmail aslinya,
 *    jadi pengenalan pengirim di TeamsInboundService tetap jalan lewat email.
 *
 * Yang TIDAK berlaku (26 Okt 2026): guest yang baru dibuat DETIK itu juga. Id
 * dari Entra sudah ada, tetapi Teams belum mengenalnya selama beberapa menit,
 * dan POST /chats yang memuatnya ditolak seluruhnya. Spike 20 Okt lolos karena
 * guest-nya sudah ada sejak sebelumnya. Lihat isPropagating().
 */
class TeamsGuestService
{
    public function __construct(private readonly TeamsGraphClient $graph)
    {
    }

    public function isEnabled(): bool
    {
        return (bool) config('services.teams_sync.enabled')
            && (bool) config('services.teams_sync.guest_invite')
            && $this->graph->isConfigured();
    }

    /** Alamat di luar domain tenant — harus lewat jalur guest. */
    public function isExternal(string $email): bool
    {
        $domain = mb_strtolower(trim((string) substr(strrchr($email, '@') ?: '', 1)));

        return $domain !== '' && !in_array($domain, $this->internalDomains(), true);
    }

    /**
     * Object id guest untuk sebuah email; undang dulu kalau belum pernah.
     *
     * Null untuk semua kegagalan: pemanggil membuang satu anggota itu saja,
     * bukan menggagalkan seluruh group chat.
     *
     * Tidak ada pemeriksaan "sudah jadi guest lewat jalan lain" (mis. diundang
     * manual dari Teams): itu butuh User.Read.All. POST /invitations untuk
     * orang yang sudah guest tetap mengembalikan id yang sama — hanya email
     * undangannya terkirim sekali lagi, sekali seumur baris tabel ini.
     */
    public function resolve(string $email): ?string
    {
        $key = mb_strtolower(trim($email));

        if ($key === '' || !filter_var($key, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $known = TeamsGuestUser::where('email', $key)->value('aad_object_id');

        if (is_string($known) && $known !== '') {
            return $known;
        }

        if (!$this->isEnabled()) {
            return null;
        }

        try {
            $invite = $this->graph->post('invitations', [
                'invitedUserEmailAddress' => $key,
                'inviteRedirectUrl'       => (string) config('services.teams_sync.guest_redirect_url'),
                // Email bawaan Microsoft masuk Spam Gmail — lihat sendChatInvite().
                'sendInvitationMessage'   => !$this->customInvite(),
            ]);
        } catch (TeamsGraphException $e) {
            // 403 di sini hampir selalu berarti User.Invite.All belum di-consent
            // atau token lama masih tersimpan (php artisan cache:clear).
            Log::warning('TeamsGuest: Graph menolak undangan guest', [
                'email'  => $key,
                'status' => $e->status,
                'code'   => $e->graphCode,
                'body'   => mb_substr((string) $e->body, 0, 1000),
            ]);

            return null;
        }

        $id = $invite['invitedUser']['id'] ?? null;

        if (!is_string($id) || $id === '') {
            Log::warning('TeamsGuest: undangan sukses tanpa object id', [
                'email'    => $key,
                'response' => $invite,
            ]);

            return null;
        }

        TeamsGuestUser::updateOrCreate(['email' => $key], [
            'aad_object_id' => $id,
            'invite_status' => $invite['status'] ?? null,
            'redeem_url'    => $invite['inviteRedeemUrl'] ?? null,
            'invited_at'    => now(),
        ]);

        Log::info('TeamsGuest: guest diundang', [
            'email'  => $key,
            'id'     => $id,
            'status' => $invite['status'] ?? null,
        ]);

        return $id;
    }

    /**
     * Guest yang undangannya masih terlalu baru untuk dikenal Teams.
     *
     * Ukurannya invited_at baris kita, bukan umur guest di Entra (membacanya
     * butuh User.Read.All). Akibatnya guest yang sebenarnya sudah lama ada
     * tetapi baru pertama kali tercatat di sini ikut dianggap baru — itu hanya
     * membuatnya masuk grup beberapa menit lebih lambat, tidak menggagalkan apa
     * pun.
     */
    public function isPropagating(string $email): bool
    {
        $minutes = (int) config('services.teams_sync.guest_propagation_minutes', 15);

        if ($minutes <= 0 || !$this->isExternal($email)) {
            return false;
        }

        $invitedAt = TeamsGuestUser::where('email', mb_strtolower(trim($email)))->value('invited_at');

        return $invitedAt !== null
            && Carbon::parse($invitedAt)->gt(now()->subMinutes($minutes));
    }

    /**
     * Rujukan user untuk Graph / konektor Teams: email untuk internal, object
     * id untuk guest. Null = guest yang tidak bisa di-resolve.
     */
    public function teamsUserRef(string $email): ?string
    {
        $email = trim($email);

        return $this->isExternal($email) ? $this->resolve($email) : $email;
    }

    /**
     * Beri tahu guest bahwa ia sudah ada di group chat tiket, lewat email dari
     * mailbox support.
     *
     * Menggantikan email undangan bawaan Microsoft, karena dua hal:
     *  1. Email itu masuk Spam Gmail (26 Okt 2026). Ia dikirim infrastruktur
     *     Microsoft atas nama eclectic.co.id, sementara DMARC domain ini
     *     quarantine dan DKIM-nya belum aktif. Email dari mailbox support lewat
     *     Exchange lolos SPF yang selaras — dan alamat itu juga yang sudah
     *     dipakai korespondensi tiket.
     *  2. Tujuannya hanya halaman Teams web, tanpa pilihan membuka aplikasi
     *     desktop. Tautan chat (/l/chat/...) melewati halaman peluncur Teams
     *     yang menawarkan "Open Teams app", dan langsung berpindah ke tenant
     *     Eclectic — di aplikasi desktop, guest sering masih berada di akun
     *     pribadinya sendiri sehingga grupnya tampak "tidak ada".
     *
     * Email pertama ke seorang guest memuat langkah menerima undangan
     * (redeem_url); berikutnya cukup tautan chat. Kegagalan hanya dicatat.
     */
    public function sendChatInvite(string $email, string $chatId, string $topic): bool
    {
        if (!$this->customInvite() || !$this->isExternal($email)) {
            return false;
        }

        $key    = mb_strtolower(trim($email));
        $guest  = TeamsGuestUser::where('email', $key)->first();
        $sender = (string) config('services.microsoft_graph.sender_email');

        if ($sender === '') {
            Log::warning('TeamsGuest: email undangan chat tidak dikirim, MS_SENDER_EMAIL kosong', ['email' => $key]);

            return false;
        }

        $redeemUrl = $guest && !$guest->onboarded_at ? $guest->redeem_url : null;

        try {
            $this->graph->post('users/' . rawurlencode($sender) . '/sendMail', [
                'message' => [
                    'subject'      => "Group chat Microsoft Teams: {$topic}",
                    'body'         => ['contentType' => 'HTML', 'content' => self::chatInviteHtml($topic, self::chatDeepLink($chatId), $redeemUrl)],
                    'toRecipients' => [['emailAddress' => ['address' => $key]]],
                ],
                'saveToSentItems' => true,
            ]);
        } catch (TeamsGraphException $e) {
            Log::warning('TeamsGuest: gagal mengirim email undangan chat', [
                'email'  => $key,
                'status' => $e->status,
                'code'   => $e->graphCode,
                'body'   => mb_substr((string) $e->body, 0, 1000),
            ]);

            return false;
        }

        if ($guest && !$guest->onboarded_at) {
            $guest->forceFill(['onboarded_at' => now()])->save();
        }

        Log::info('TeamsGuest: email undangan chat terkirim', [
            'email'        => $key,
            'chat_id'      => $chatId,
            'with_redeem'  => $redeemUrl !== null,
        ]);

        return true;
    }

    /**
     * Tautan langsung ke group chat, bentuknya sama dengan `webUrl` chat di
     * Graph. tenantId wajib: tanpa itu Teams membuka tenant asal guest (akun
     * pribadinya), dan chat tiket tidak ditemukan di sana.
     */
    public static function chatDeepLink(string $chatId, ?string $tenant = null): string
    {
        $tenant ??= (string) config('services.microsoft_graph_teams.tenant_id');

        return 'https://teams.microsoft.com/l/chat/' . rawurlencode($chatId) . '/0'
            . ($tenant !== '' ? '?tenantId=' . rawurlencode($tenant) : '');
    }

    /**
     * Isi email: teks polos tanpa gambar, satu tujuan per tombol — bentuk yang
     * paling kecil peluangnya dianggap spam.
     */
    public static function chatInviteHtml(string $topic, string $chatUrl, ?string $redeemUrl): string
    {
        $topic   = e($topic);
        $chatUrl = e($chatUrl);
        $redeem  = $redeemUrl === null ? null : e($redeemUrl);
        $button  ='background:#991b1b;color:#fff;padding:10px 22px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;';

        $steps = $redeem === null
            ? <<<HTML
<p style="margin:20px 0;"><a href="{$chatUrl}" style="{$button}">Buka group chat di Teams</a></p>
HTML
            : <<<HTML
<p><strong>Langkah 1 — terima undangan (cukup sekali):</strong></p>
<p style="margin:12px 0 20px;"><a href="{$redeem}" style="{$button}">Terima undangan Eclectic Company</a></p>
<p><strong>Langkah 2 — buka group chat tiket:</strong></p>
<p style="margin:12px 0 20px;"><a href="{$chatUrl}" style="{$button}">Buka group chat di Teams</a></p>
<p style="color:#555;">Login dengan alamat email ini. Jika diminta, masukkan kode verifikasi yang dikirim Microsoft ke email Anda.</p>
HTML;

        return <<<HTML
<p>Halo,</p>
<p>Anda ditambahkan sebagai tamu (guest) ke group chat Microsoft Teams milik Eclectic Company untuk tiket:</p>
<p><strong>{$topic}</strong></p>
{$steps}
<p style="color:#555;">Saat tautan dibuka, pilih <em>Continue on this browser</em> (disarankan) atau <em>Open Teams app</em>.</p>
<p style="color:#555;">Jika aplikasi desktop menampilkan pesan <em>"We can't take you to that message because it's in a chat you're not in"</em>, berarti aplikasi sedang memakai akun Anda yang lain (akun pribadi atau akun kantor/kampus). Klik foto profil di kanan atas, pilih organisasi <strong>Eclectic Company (Guest)</strong>, lalu buka tautan ini lagi. Jika organisasi itu tidak muncul, gunakan Teams di browser.</p>
<br>
<p>Salam,<br><strong>Eclectic Support</strong></p>
HTML;
    }

    private function customInvite(): bool
    {
        return (bool) config('services.teams_sync.guest_custom_invite', true);
    }

    /** @return list<string> huruf kecil */
    private function internalDomains(): array
    {
        return array_values(array_filter(array_map(
            static fn ($d) => mb_strtolower(trim($d)),
            explode(',', (string) config('services.teams_sync.internal_domains'))
        )));
    }
}
