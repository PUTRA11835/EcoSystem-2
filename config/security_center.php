<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Brute-force lockout thresholds
    |--------------------------------------------------------------------------
    |
    | Failed attempts are counted from the existing login_activity table
    | (status = 'failed'), so no separate counter storage is needed. Account
    | lockout is keyed by auth_users.id; IP lockout by the request IP.
    |
    */
    'brute_force' => [
        'account' => [
            'max_attempts'    => 5,
            'window_minutes'  => 15,
            'lockout_minutes' => 30,
        ],
        'ip' => [
            'max_attempts'    => 15,
            'window_minutes'  => 15,
            'lockout_minutes' => 60,
        ],

        // Credential stuffing / password spray: many DISTINCT accounts failing
        // from one IP. Checked separately from the raw-attempt IP threshold
        // above because a spray can stay well under that volume (e.g. 5
        // accounts tried once each = 5 attempts, far below max_attempts=15)
        // while still being a clear coordinated-attack signal.
        'credential_stuffing' => [
            'distinct_accounts_threshold' => 5,
            'window_minutes'              => 15,
            'lockout_minutes'             => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Attack-pattern detection (WAF-style logging)
    |--------------------------------------------------------------------------
    |
    | 'mode' => 'log'   : matches are written to security_events, request proceeds normally.
    | 'mode' => 'block' : matches are also rejected with a 400 response.
    | Ships as 'log' - see Security Center plan for the false-positive rationale
    | (ticket text/pasted logs can legitimately resemble attack payloads).
    |
    */
    'waf' => [
        'mode' => 'log',

        // Only these things are scanned: query params, route params, the URI
        // path, and POST fields whose name is in `fields` below. Free-text
        // fields (ticket descriptions, messages, AI chat) are never scanned.
        'fields' => [
            'email', 'username', 'search', 'q', 'keyword', 'filter',
            'sort', 'slug', 'redirect', 'url', 'filename', 'name',
        ],

        // Subset of `fields` above checked for open-redirect: an absolute
        // URL whose host differs from the app's own host. Handled as
        // dedicated logic (not a static regex) since it needs the current
        // request's host to compare against.
        'redirect_fields' => ['redirect', 'url', 'next', 'return_to', 'callback', 'continue'],

        // Fields where a bare IP/hostname is a meaningful signal (SSRF probe
        // via a server-side fetch target). Restricting ssrf.loopback_host and
        // ssrf.private_ip to these avoids flagging a helpdesk agent typing
        // "192.168.1.1" into a plain search/keyword box while troubleshooting
        // a network ticket - a false positive this app would hit often.
        // Everything else in `patterns` below applies to all scanned fields.
        'url_shaped_fields' => ['redirect', 'url', 'next', 'return_to', 'callback', 'continue', 'filename'],

        // category => [pattern_name => config key holding the field allowlist].
        // A pattern not listed here applies to every scanned field. NOTE:
        // must be a genuinely nested array (not a flat 'category.pattern'
        // string key) - config()'s dot-notation lookup traverses nesting,
        // it does not match against literal dots in a key.
        'scoped_patterns' => [
            'ssrf' => [
                'loopback_host' => 'url_shaped_fields',
                'private_ip'    => 'url_shaped_fields',
            ],
        ],

        // How many notifications per IP+event_type within this window
        // (debounce so a sustained probe doesn't spam admins).
        'notify_debounce_minutes' => 10,

        'patterns' => [
            'sqli' => [
                'union_select'   => '/\bUNION\b.{0,40}\bSELECT\b/i',
                'or_true'        => '/(\'|"|\bOR\b)\s*(\d+|\'\w*\')\s*=\s*(\d+|\'\w*\')/i',
                'sql_comment'    => '/(--|#|\/\*).{0,3}$/',
                'stacked_query'  => '/;\s*(DROP|DELETE|INSERT|UPDATE)\b/i',
                'sleep_benchmark'=> '/\b(SLEEP|BENCHMARK)\s*\(/i',
                'sql_keywords'   => '/\b(SELECT\b.{0,40}\bFROM|INSERT\s+INTO|DROP\s+TABLE|ALTER\s+TABLE)\b/i',
            ],
            'xss' => [
                'script_tag'     => '/<script\b[^>]*>/i',
                'on_event_attr'  => '/\bon(error|load|click|mouseover|focus)\s*=/i',
                'js_protocol'    => '/javascript\s*:/i',
                'iframe_tag'     => '/<iframe\b/i',
                'svg_onload'     => '/<svg\b[^>]*onload/i',
            ],
            'path_traversal' => [
                'dot_dot_slash'  => '/\.\.(\/|\\\\)/',
                'encoded_traversal' => '/(%2e%2e|%252e%252e)(%2f|%5c)/i',
                'null_byte'      => '/%00/',
            ],
            'command_injection' => [
                'shell_metachar'  => '/[;&|`]\s*(rm|wget|curl|nc|ncat|bash|sh|cat|chmod|chown|python|perl|powershell)\b/i',
                'command_subst'   => '/\$\([^)]+\)|`[^`]+`/',
                'recon_command'   => '/(^|[;&|]\s*)(whoami|uname\s+-a|id;|\/etc\/passwd|ipconfig|net\s+user)\b/i',
                'redirect_to_shell' => '/\|\s*(bash|sh|powershell|cmd)\b/i',
            ],
            'ssrf' => [
                'cloud_metadata'  => '/169\.254\.169\.254|metadata\.google\.internal/i',
                'loopback_host'   => '/\b(127\.0\.0\.1|0\.0\.0\.0|localhost)\b/i',
                'private_ip'      => '/\b(10\.\d{1,3}\.\d{1,3}\.\d{1,3}|172\.(1[6-9]|2\d|3[01])\.\d{1,3}\.\d{1,3}|192\.168\.\d{1,3}\.\d{1,3})\b/',
                'internal_scheme' => '/\b(gopher|dict|ftp|file):\/\//i',
            ],
            'lfi_rfi' => [
                'php_wrapper'     => '/\b(php|data|expect|zip|phar):\/\//i',
                'sensitive_file'  => '/\/etc\/(passwd|shadow|hosts)\b|\bwin\.ini\b|\bboot\.ini\b/i',
                'traversal_deep'  => '/(\.\.(\/|\\\\)){2,}/',
            ],
            'php_object_injection' => [
                // Classic PHP serialize() object marker: O:<len>:"<class>":<props>:{
                // Extremely unlikely to appear in legitimate input of any kind.
                'serialized_object' => '/O:\d+:"[^"]{1,200}":\d+:\{/',
                'serialized_array'  => '/a:\d+:\{(s|i):/',
            ],
        ],

        // Known scanner/pentest-tool User-Agent substrings. Unlike everything
        // else here, this checks a header, not a field value - see
        // DetectAttackPatterns::checkScannerUserAgent(). Kept to unambiguous
        // tool names only (no bare "curl"/"python-requests"/empty-UA) since
        // this app receives legitimate automated calls (health checks,
        // Power Automate, MS Graph webhooks) that would otherwise false-positive.
        'scanner_user_agents' => [
            'sqlmap', 'nikto', 'nmap', 'nessus', 'acunetix', 'w3af',
            'dirbuster', 'gobuster', 'wpscan', 'masscan', 'zgrab',
            'nuclei', 'metasploit', 'havij', 'netsparker', 'burpsuite',
            'openvas', 'skipfish',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Response-pattern detection (path scanning, ID enumeration, mass reads)
    |--------------------------------------------------------------------------
    |
    | Unlike the WAF checks above (request-time, payload-based), these need
    | the RESPONSE status code, so they're checked in a separate middleware
    | (DetectAccessPatterns) that wraps the whole request/response cycle.
    | All counters are rolling windows kept in cache (no new tables).
    |
    */
    'access_patterns' => [
        // True 404s (no route matched at all) from one IP - classic
        // directory/endpoint scanning (looking for /.env, /wp-admin, backups, etc).
        'path_scan' => [
            'threshold'       => 20,
            'window_minutes'  => 10,
        ],

        // 403/404 on a route with a numeric ID parameter (e.g. /tickets/{id}),
        // from one IP - trying many IDs to find one that's accessible.
        'id_enumeration' => [
            'threshold'       => 15,
            'window_minutes'  => 10,
        ],

        // One AUTHENTICATED employee successfully (200) opening many DISTINCT
        // resource-detail records in a short time - insider data scraping via
        // normal page views, as opposed to the explicit "Export" button
        // (already covered by mass_export in `anomaly` below).
        'mass_data_access' => [
            'distinct_ids_threshold' => 40,
            'window_minutes'         => 15,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachment upload scanning
    |--------------------------------------------------------------------------
    |
    | Log-only, like everything else here - a support ticket can legitimately
    | carry a .sh/.log/.sql file as troubleshooting evidence, so this flags
    | for review rather than rejecting the upload.
    |
    */
    'upload' => [
        'dangerous_extensions' => [
            'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'pht',
            'exe', 'com', 'scr', 'msi', 'dll',
            'sh', 'bash', 'bat', 'cmd', 'ps1', 'vbs', 'vbe', 'wsf',
            'jsp', 'jspx', 'asp', 'aspx', 'cgi', 'jar',
        ],

        // extension => acceptable server-detected MIME types (via PHP fileinfo,
        // not the client-supplied Content-Type, which is trivially spoofed).
        'mime_signatures' => [
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
            'gif'  => ['image/gif'],
            'pdf'  => ['application/pdf'],
        ],

        // Extensions that look like documents/images - used to spot a
        // double-extension disguise like "invoice.pdf.php".
        'safe_looking_extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'csv'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Anomaly detection (scheduled job) thresholds
    |--------------------------------------------------------------------------
    */
    'anomaly' => [
        'mass_export' => [
            'row_count_threshold'  => 1000,
            'exports_per_day_threshold' => 3,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Two-factor authentication (TOTP)
    |--------------------------------------------------------------------------
    */
    'two_factor' => [
        'challenge_ttl_minutes' => 5,
        'max_verify_attempts'   => 5,
        'lockout_minutes'       => 30,   // reuses auth_users.locked_until, same column brute-force already uses
        'recovery_codes_count'  => 8,
        // RoleId::EC_ADMINISTRATOR - checked against session role_ids; 2FA is
        // mandatory for these roles, optional (self-enrolled) for everyone else.
        'enforce_for_role_ids'  => [1],
    ],

];
