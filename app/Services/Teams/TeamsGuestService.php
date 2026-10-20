<?php

namespace App\Services\Teams;

use App\Models\TeamsGuestUser;
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
                'sendInvitationMessage'   => true,
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
     * Rujukan user untuk Graph / konektor Teams: email untuk internal, object
     * id untuk guest. Null = guest yang tidak bisa di-resolve.
     */
    public function teamsUserRef(string $email): ?string
    {
        $email = trim($email);

        return $this->isExternal($email) ? $this->resolve($email) : $email;
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
