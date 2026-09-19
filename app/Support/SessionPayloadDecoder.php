<?php

namespace App\Support;

/**
 * Decodes the `user` array this app stores via session()->put('user', ...)
 * out of a raw `sessions.payload` blob.
 *
 * sessions.user_id can't be used for this: the app never calls Laravel's
 * Auth::login(), so Illuminate\Session\DatabaseSessionHandler resets that
 * column to NULL on every request (it writes Guard::class->id(), which is
 * always null here). The real identity only lives inside the payload blob.
 *
 * Shared by AdminSessionController (force-logout by session id) and
 * SecurityCenterController (force-logout by employee id).
 */
class SessionPayloadDecoder
{
    public static function decode(?string $payload): ?array
    {
        if (!$payload) {
            return null;
        }

        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            return null;
        }

        $data = @unserialize($decoded, ['allowed_classes' => false]);

        return (is_array($data) && is_array($data['user'] ?? null)) ? $data['user'] : null;
    }
}
