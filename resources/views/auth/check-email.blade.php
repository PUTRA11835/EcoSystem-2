<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Your Email — ECoSystem</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800&display=swap" rel="stylesheet">
    <style>
        #content-panel {
            display:flex; flex-direction:column;
            background:#fff; height:100vh; overflow:hidden; position:relative;
        }
        @media (max-width:767px) { #content-panel { overflow:visible; } }

        .ce-inner {
            width:100%; max-width:400px;
            opacity:0; animation:fadeInRight .7s ease forwards; animation-delay:.3s;
        }

        /* Animated mail badge */
        .ce-badge {
            position:relative; width:4rem; height:4rem; border-radius:1.25rem;
            background:linear-gradient(135deg,#fee2e2,#fef2f2); border:1px solid #fecaca;
            display:flex; align-items:center; justify-content:center; margin-bottom:1.5rem;
        }
        .ce-badge::after {
            content:''; position:absolute; inset:-6px; border-radius:1.5rem;
            border:2px solid rgba(160,0,0,.12); animation:ringPulseBadge 2.4s ease-in-out infinite;
        }
        @keyframes ringPulseBadge { 0%,100%{transform:scale(1);opacity:.6} 50%{transform:scale(1.12);opacity:0} }

        .ce-tips {
            background:#FFFBEB; border:1px solid #FDE68A; border-radius:var(--r-xl);
            padding:.9375rem 1rem; text-align:left;
        }
        .ce-tips li { font-size:.75rem; color:#92400E; line-height:1.6; }

        .ce-email-chip {
            display:inline-block; background:#F8FAFC; border:1px solid #E2E8F0;
            border-radius:9999px; padding:.25rem .75rem; font-weight:700; color:#334155;
            font-size:.8125rem; margin-top:.25rem; word-break:break-all;
        }
    </style>
</head>
<body>

@include('auth.partials.brand-panel', [
    'brandHeadline' => 'Almost<br>there.',
    'brandSub'      => 'We just need to confirm it is really you. Check your inbox to finish setting up your secure access.',
])

{{-- ════════════════ RIGHT CONTENT PANEL ════════════════ --}}
<div id="content-panel">

    {{-- Echo rings --}}
    <div style="position:absolute;bottom:-140px;right:-140px;width:420px;height:420px;border-radius:50%;border:1px solid rgba(160,0,0,.05);pointer-events:none;z-index:0;"></div>
    <div style="position:absolute;bottom:20%;left:-60px;width:200px;height:200px;border-radius:50%;border:1px solid rgba(160,0,0,.04);pointer-events:none;z-index:0;"></div>

    {{-- Logo bar --}}
    <div style="flex-shrink:0; display:flex; justify-content:flex-end; align-items:center; padding:1.625rem 2.25rem; position:relative; z-index:1;">
        <img src="/images/eclectic_logo_nobg.png" alt="Eclectic Consulting"
             style="height:3rem; width:auto; display:block; opacity:0; animation:fadeIn .5s ease forwards; animation-delay:.2s;">
    </div>

    {{-- Content (centered) --}}
    <div style="flex:1; display:flex; align-items:center; justify-content:center; padding:1rem 2.5rem; position:relative; z-index:1;">
        <div class="ce-inner">

            <div class="ce-badge" style="opacity:0;animation:fadeInUp .5s ease forwards;animation-delay:.35s;">
                <svg style="width:1.875rem;height:1.875rem;color:#A00000;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
            @if($type === 'reset')
                <h2 style="font-size:1.625rem;font-weight:800;color:#0F172A;letter-spacing:-.035em;line-height:1.2;margin-bottom:.625rem;opacity:0;animation:fadeInUp .5s ease forwards;animation-delay:.43s;">
                    Email Sent
                </h2>
                <p style="font-size:.875rem;color:#475569;line-height:1.7;margin-bottom:.375rem;opacity:0;animation:fadeInUp .5s ease forwards;animation-delay:.51s;">
                    We have sent a password reset link to
                </p>
            @else
                <h2 style="font-size:1.625rem;font-weight:800;color:#0F172A;letter-spacing:-.035em;line-height:1.2;margin-bottom:.625rem;opacity:0;animation:fadeInUp .5s ease forwards;animation-delay:.43s;">
                    Check Your Email
                </h2>
                <p style="font-size:.875rem;color:#475569;line-height:1.7;margin-bottom:.375rem;opacity:0;animation:fadeInUp .5s ease forwards;animation-delay:.51s;">
                    We have sent an activation email to
                </p>
            @endif

            {{-- ── Email pill ──────────────────────────────────────────── --}}
            @if($email)
            <div style="margin-bottom:1rem;
                        opacity:0;animation:fadeInUp .5s ease forwards;animation-delay:.58s;">
                <div style="display:inline-flex;align-items:center;gap:.625rem;
                            background:#FEF2F2;
                            border:1.5px solid rgba(160,0,0,.18);
                            border-radius:12px;padding:.625rem 1.125rem;
                            box-shadow:0 2px 8px rgba(160,0,0,.07);">
                    <div style="width:28px;height:28px;border-radius:8px;
                                background:rgba(160,0,0,.1);
                                display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg style="width:.875rem;height:.875rem;color:#A00000;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <span style="font-size:.9375rem;font-weight:700;color:#A00000;letter-spacing:.01em;">
                        {{ $email }}
                    </span>
                </div>
            </div>
            @endif

            {{-- Fallback teks saat email tidak diketahui (pill di atas tidak tampil) --}}
            @if(!$email)
            <div style="opacity:0;animation:fadeInUp .5s ease forwards;animation-delay:.55s;margin-bottom:1rem;">
                <span style="font-size:.875rem;color:#64748B;font-style:italic;">
                    {{ $type === 'reset' ? 'the email address you entered' : 'the email address registered to your account' }}
                </span>
            </div>
            @endif

            <p style="font-size:.8125rem;color:#64748B;line-height:1.7;margin-bottom:1.5rem;opacity:0;animation:fadeInUp .5s ease forwards;animation-delay:.59s;">
                @if($type === 'reset')
                    Open the email and click the <strong style="color:#334155;">"Reset My Password"</strong> button to set a new password. The link is valid for <strong style="color:#334155;">24 hours</strong>.
                @else
                    Open the email and click the <strong style="color:#334155;">"Set My Password"</strong> button to set a new password before you can log in. The link is valid for <strong style="color:#334155;">24 hours</strong>.
                @endif
            </p>

            {{-- Tips --}}
            <div class="ce-tips" style="opacity:0;animation:fadeInUp .5s ease forwards;animation-delay:.65s;">
                <p style="display:flex;align-items:center;gap:.4rem;font-size:.75rem;font-weight:700;color:#B45309;margin-bottom:.5rem;">
                    <svg style="width:.95rem;height:.95rem;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Didn't receive the email?
                </p>
                <ul style="list-style:disc;padding-left:1.1rem;display:flex;flex-direction:column;gap:.25rem;">
                    <li>Check your <strong>Spam / Junk</strong> folder</li>
                    <li>Make sure the email address you used is correct</li>
                    @if($type === 'reset')
                        <li><a href="{{ route('password-setup.forgot') }}" style="color:#B45309;text-decoration:underline;font-weight:700;">Resend password reset link</a></li>
                    @else
                        <li>Contact the helpdesk team if you still do not receive the email</li>
                    @endif
                </ul>
            </div>

            <div style="margin-top:1.75rem;text-align:center;opacity:0;animation:fadeIn .6s ease forwards;animation-delay:.75s;">
                <a href="{{ route('login') }}"
                   style="display:inline-flex;align-items:center;gap:.35rem;font-size:.8125rem;color:#A00000;font-weight:600;text-decoration:none;transition:color .15s;"
                   onmouseover="this.style.color='#6B0000'" onmouseout="this.style.color='#A00000'">
                    <svg style="width:.95rem;height:.95rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back to login page
                </a>
            </div>

        </div>
    </div>

    {{-- Copyright --}}
    <div style="flex-shrink:0; text-align:center; padding:1.25rem 2rem 1.5rem; position:relative; z-index:1; opacity:0; animation:fadeIn .6s ease forwards; animation-delay:.85s;">
        <p style="font-size:.6875rem;color:#CBD5E1;line-height:1.5;">
            &copy; {{ date('Y') }} ECoSystem by Eclectic Consulting. All rights reserved.
        </p>
    </div>
</div>

</body>
</html>
