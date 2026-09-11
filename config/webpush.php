<?php

return [
    'vapid_public_key'  => env('VAPID_PUBLIC_KEY', ''),
    'vapid_private_key' => env('VAPID_PRIVATE_KEY', ''),
    // Apple's web push service rejects VAPID JWTs whose "sub" claim isn't a
    // mailto: or https:// URI (BadJwtToken) — app.url is often plain http://
    // in local/dev, so this needs its own dedicated value.
    'vapid_subject'     => env('VAPID_SUBJECT', 'mailto:support@eclectic.co.id'),
];
