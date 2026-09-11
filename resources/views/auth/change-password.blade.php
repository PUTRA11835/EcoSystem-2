<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Set Password — ECoSystem</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800&display=swap" rel="stylesheet">
    <style>
        /* ── Content panel ─────────────────────────────────────────── */
        #content-panel {
            display:flex; flex-direction:column;
            background:#fff; height:100vh; overflow:hidden; position:relative;
        }
        @media (max-width:767px) { #content-panel { height:auto; min-height:calc(100vh - 62px); overflow:visible; } }

        .cp-inner {
            width:100%; max-width:400px;
            opacity:0; animation:fadeInRight .7s ease forwards; animation-delay:.3s;
        }

        /* Icon badge */
        .cp-badge {
            width:3.25rem; height:3.25rem; border-radius:1rem;
            background:linear-gradient(135deg,#fee2e2,#fef2f2);
            border:1px solid #fecaca;
            display:flex; align-items:center; justify-content:center;
            margin-bottom:1.25rem;
        }

        /* Floating label input */
        .fl-wrap { position:relative; }
        .fl-inp {
            width:100%; padding:1.375rem 2.875rem .5rem 1rem;
            border:1.5px solid #CBD5E1; border-radius:8px;
            font-family:'Plus Jakarta Sans',sans-serif; font-size:.9375rem; font-weight:500;
            color:#0F172A; background:#fff; outline:none; line-height:1.4;
            transition:border-color .2s ease, box-shadow .2s ease;
        }
        .fl-inp::placeholder { color:transparent; }
        .fl-inp:-webkit-autofill,.fl-inp:-webkit-autofill:hover,.fl-inp:-webkit-autofill:focus {
            -webkit-text-fill-color:#0F172A; -webkit-box-shadow:0 0 0 1000px #fff inset;
            font-family:'Plus Jakarta Sans',sans-serif; font-size:.9375rem; font-weight:500;
            transition:background-color 9999s ease-in-out 0s;
        }
        .fl-inp:focus { border-color:#A00000; box-shadow:0 0 0 3px rgba(160,0,0,.1); }
        .fl-label {
            position:absolute; left:1rem; top:50%; transform:translateY(-50%);
            font-size:.9rem; color:#94A3B8; pointer-events:none; white-space:nowrap;
            transition:top .18s ease, transform .18s ease, font-size .18s ease, color .18s ease, font-weight .18s ease;
        }
        .fl-inp:focus ~ .fl-label,
        .fl-inp:not(:placeholder-shown) ~ .fl-label {
            top:.625rem; transform:none; font-size:.6875rem; font-weight:600;
            color:#A00000; letter-spacing:.02em;
        }
        .fl-pwd-btn {
            position:absolute; right:0; top:0; bottom:0; width:2.875rem;
            display:flex; align-items:center; justify-content:center;
            background:none; border:none; cursor:pointer; color:#CBD5E1; border-radius:0 8px 8px 0;
            transition:color .15s ease;
        }
        .fl-pwd-btn:hover { color:#A00000; }

        /* Requirements card */
        .cp-req {
            background:#F8FAFC; border:1px solid #E2E8F0; border-radius:var(--r-xl);
            padding:.875rem 1rem; display:flex; flex-direction:column; gap:.5rem;
        }
        .cp-req-row {
            display:flex; align-items:center; gap:.5rem;
            font-size:.75rem; font-weight:500; color:#94A3B8;
            transition:color .2s ease;
        }
        .cp-req-row .tick { width:1rem; height:1rem; flex-shrink:0; transition:color .2s ease; }
        .cp-req-row.ok { color:#16A34A; }

        /* Submit button */
        .cp-btn {
            width:100%; padding:.9375rem 1rem; background:#A00000; color:#fff;
            font-family:'Plus Jakarta Sans',sans-serif; font-size:.9375rem; font-weight:600;
            border:none; border-radius:8px; cursor:pointer; position:relative; overflow:hidden;
            transition:background .2s ease, transform .15s ease;
        }
        .cp-btn:hover:not(:disabled) { background:#800000; }
        .cp-btn:active:not(:disabled) { transform:scale(.98); }
        .cp-btn:disabled { cursor:not-allowed; opacity:.55; }

        .cp-alert {
            display:flex; align-items:flex-start; gap:.625rem;
            background:#FEF2F2; border:1px solid #FECACA; border-left:3px solid #DC2626;
            border-radius:var(--r-lg); padding:.75rem .875rem; margin-bottom:1.25rem;
        }
    </style>
</head>
<body>

@include('auth.partials.brand-panel', [
    'brandHeadline' => 'Secure<br>your account.',
    'brandSub'      => 'One last step. Choose a strong password and you are ready to step into your ECoSystem workspace.',
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

    {{-- Form (centered) --}}
    <div style="flex:1; display:flex; align-items:center; justify-content:center; padding:1rem 2.5rem; position:relative; z-index:1;">
        <div class="cp-inner">

            <div class="cp-badge" style="opacity:0;animation:fadeInUp .5s ease forwards;animation-delay:.35s;">
                <svg style="width:1.5rem;height:1.5rem;color:#A00000;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>

            <div style="margin-bottom:1.5rem;">
                <h2 style="font-size:1.625rem;font-weight:800;color:#0F172A;letter-spacing:-.035em;line-height:1.2;margin-bottom:.375rem;opacity:0;animation:fadeInUp .5s ease forwards;animation-delay:.43s;">
                    Set New Password
                </h2>
                <p style="font-size:.8125rem;color:#64748B;line-height:1.6;opacity:0;animation:fadeInUp .5s ease forwards;animation-delay:.51s;">
                    Create a strong password to secure your account.
                </p>
            </div>

            {{-- Flash / validation errors --}}
            @if(session('error') || $errors->any())
                <div class="cp-alert" style="opacity:0;animation:fadeInUp .4s ease forwards;animation-delay:.55s;">
                    <svg style="width:1.1rem;height:1.1rem;color:#DC2626;flex-shrink:0;margin-top:.1rem;" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <div style="font-size:.75rem;color:#B91C1C;line-height:1.5;">
                        @if(session('error')) {{ session('error') }} @endif
                        @foreach($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                </div>
            @endif

            <form method="POST" action="/change-password"
                  style="display:flex;flex-direction:column;gap:1.125rem;opacity:0;animation:fadeInUp .5s ease forwards;animation-delay:.59s;">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                {{-- New password --}}
                <div class="fl-wrap">
                    <input type="password" id="password" name="password" placeholder=" " class="fl-inp"
                           style="padding-right:2.875rem;" autocomplete="new-password" required>
                    <label class="fl-label" for="password">New Password</label>
                    <button type="button" class="fl-pwd-btn" data-target="password" tabindex="-1" aria-label="Toggle password">
                        <svg style="width:1.0625rem;height:1.0625rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </button>
                </div>

                {{-- Confirm password --}}
                <div class="fl-wrap">
                    <input type="password" id="password_confirmation" name="password_confirmation" placeholder=" " class="fl-inp"
                           style="padding-right:2.875rem;" autocomplete="new-password" required>
                    <label class="fl-label" for="password_confirmation">Confirm Password</label>
                    <button type="button" class="fl-pwd-btn" data-target="password_confirmation" tabindex="-1" aria-label="Toggle password">
                        <svg style="width:1.0625rem;height:1.0625rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </button>
                </div>

                {{-- Requirements --}}
                <div class="cp-req">
                    <div class="cp-req-row" id="req-length">
                        <svg class="tick" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        At least 8 characters
                    </div>
                    <div class="cp-req-row" id="req-match">
                        <svg class="tick" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        Passwords match
                    </div>
                </div>

                <button type="submit" id="submitBtn" class="cp-btn" disabled>
                    Save Password &amp; Sign In
                </button>
            </form>

            <div style="margin-top:1.5rem;text-align:center;opacity:0;animation:fadeIn .6s ease forwards;animation-delay:.7s;">
                <a href="{{ route('login') }}"
                   style="display:inline-flex;align-items:center;gap:.35rem;font-size:.8125rem;color:#A00000;font-weight:600;text-decoration:none;transition:color .15s;"
                   onmouseover="this.style.color='#6B0000'" onmouseout="this.style.color='#A00000'">
                    <svg style="width:.95rem;height:.95rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back to login
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

<script>
    // Password visibility toggles
    document.querySelectorAll('.fl-pwd-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const inp = document.getElementById(btn.dataset.target);
            inp.type = inp.type === 'password' ? 'text' : 'password';
        });
    });

    // Live requirement validation
    const pw     = document.getElementById('password');
    const conf   = document.getElementById('password_confirmation');
    const reqLen = document.getElementById('req-length');
    const reqMat = document.getElementById('req-match');
    const submit = document.getElementById('submitBtn');

    function validate() {
        const lenOk   = pw.value.length >= 8;
        const matchOk = pw.value.length > 0 && pw.value === conf.value;
        reqLen.classList.toggle('ok', lenOk);
        reqMat.classList.toggle('ok', matchOk);
        submit.disabled = !(lenOk && matchOk);
    }
    pw.addEventListener('input', validate);
    conf.addEventListener('input', validate);
</script>
</body>
</html>
