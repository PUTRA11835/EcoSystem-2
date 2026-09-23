<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Two-Factor Verification - ECoSystem</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800&display=swap" rel="stylesheet">
    <style>
        :root {
            --red-brand: #A00000;
            --red-dark:  #6B0000;
            --gray-900:  #0F172A;
            --gray-500:  #64748B;
            --gray-300:  #CBD5E1;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #F7F5F3;
            display: flex; align-items: center; justify-content: center;
            min-height: 100vh; padding: 1.5rem; color: var(--gray-900);
        }
        .card {
            width: 100%; max-width: 26rem; background: #fff;
            border-radius: 1.375rem; padding: 2.25rem 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,.04), 0 8px 24px rgba(0,0,0,.07), 0 24px 56px rgba(0,0,0,.06);
        }
        .icon-badge {
            width: 3rem; height: 3rem; border-radius: 9999px;
            background: #fff1f1; display: flex; align-items: center; justify-content: center;
            margin-bottom: 1.25rem;
        }
        .icon-badge svg { width: 1.5rem; height: 1.5rem; color: var(--red-brand); }
        h1 { font-size: 1.5rem; font-weight: 800; letter-spacing: -.03em; margin-bottom: .375rem; }
        p.sub { font-size: .875rem; color: var(--gray-500); margin-bottom: 1.75rem; line-height: 1.5; }
        .code-inp {
            width: 100%; padding: .875rem 1rem; border: 1.5px solid var(--gray-300);
            border-radius: 8px; font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.5rem; font-weight: 700; letter-spacing: .35em; text-align: center;
            color: var(--gray-900); outline: none; transition: border-color .2s, box-shadow .2s;
        }
        .code-inp.recovery-mode { font-size: 1rem; letter-spacing: .05em; text-transform: uppercase; }
        .code-inp:focus { border-color: var(--red-brand); box-shadow: 0 0 0 3px rgba(160,0,0,.1); }
        .code-inp.is-error { border-color: #DC2626; }
        .l-err { font-size: .75rem; color: #DC2626; display: none; margin-top: .5rem; }
        .toggle-link {
            display: inline-block; margin-top: .875rem; font-size: .8125rem;
            color: var(--red-brand); font-weight: 500; text-decoration: none; cursor: pointer;
            background: none; border: none; padding: 0;
        }
        .toggle-link:hover { text-decoration: underline; }
        .submit-btn {
            width: 100%; margin-top: 1.5rem; padding: .9375rem 1rem;
            background: var(--red-brand); color: #fff; font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: .9375rem; font-weight: 600; border: none; border-radius: 8px;
            cursor: pointer; position: relative; overflow: hidden; transition: background .15s;
        }
        .submit-btn:hover { background: var(--red-dark); }
        .submit-btn:disabled { opacity: .7; cursor: not-allowed; }
        .submit-loader { display: none; position: absolute; inset: 0; align-items: center; justify-content: center; gap: .5rem; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .back-link { display: block; text-align: center; margin-top: 1.25rem; font-size: .8125rem; color: var(--gray-500); text-decoration: none; }
        .back-link:hover { text-decoration: underline; }

        /* ── Toast (same implementation as the login page) ──────────── */
        #toast-container { position: fixed; top: 1.5rem; right: 1.5rem; z-index: 9999; display: flex; flex-direction: column; gap: .75rem; max-width: 22rem; width: 100%; pointer-events: none; }
        .toast { pointer-events: all; border-radius: .875rem; padding: 1rem 1rem 0 1rem; display: flex; flex-direction: column; box-shadow: 0 10px 30px rgba(0,0,0,.15); overflow: hidden; transform: translateX(110%); opacity: 0; transition: transform .4s cubic-bezier(.34,1.56,.64,1), opacity .3s ease; }
        .toast.show { transform: translateX(0); opacity: 1; }
        .toast.hide { transform: translateX(110%); opacity: 0; }
        .toast-body { display: flex; align-items: flex-start; gap: .75rem; padding-bottom: .875rem; }
        .toast-icon { flex-shrink: 0; width: 2rem; height: 2rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .toast-content { flex: 1; min-width: 0; }
        .toast-title { font-size: .8125rem; font-weight: 700; line-height: 1.2; }
        .toast-msg { font-size: .8125rem; margin-top: .2rem; line-height: 1.4; }
        .toast-close { flex-shrink: 0; background: none; border: none; cursor: pointer; padding: .1rem; border-radius: .375rem; opacity: .5; transition: opacity .2s; }
        .toast-close:hover { opacity: 1; }
        .toast-progress { height: 3px; border-radius: 0 0 .875rem .875rem; margin: 0 -1rem; transform-origin: left; animation: progress-shrink linear forwards; }
        @keyframes progress-shrink { from { transform: scaleX(1); } to { transform: scaleX(0); } }
        .toast-error { background: #fff1f1; border: 1.5px solid #fca5a5; }
        .toast-error .toast-icon { background: #fee2e2; } .toast-error .toast-icon svg { color: #dc2626; }
        .toast-error .toast-title { color: #991b1b; } .toast-error .toast-msg { color: #b91c1c; }
        .toast-error .toast-close { color: #991b1b; } .toast-error .toast-progress { background: #ef4444; }
        .toast-success { background: #f0fdf4; border: 1.5px solid #86efac; }
        .toast-success .toast-icon { background: #dcfce7; } .toast-success .toast-icon svg { color: #16a34a; }
        .toast-success .toast-title { color: #14532d; } .toast-success .toast-msg { color: #15803d; }
        .toast-success .toast-close { color: #14532d; } .toast-success .toast-progress { background: #22c55e; }
    </style>
</head>
<body>

<div id="toast-container"></div>

<div class="card">
    <div class="icon-badge">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12.75L11.25 15 15 9.75M21 12c0 4.556-3.04 8.408-7.2 9.632-.53.155-1.07-.19-1.07-.744V19.5m8.27-7.5a9.75 9.75 0 00-.75-3.75L12 3 4.75 8.25A9.75 9.75 0 004 12c0 4.556 3.04 8.408 7.2 9.632.53.155 1.07-.19 1.07-.744V19.5" /></svg>
    </div>
    <h1>Two-Factor Verification</h1>
    <p class="sub" id="subText">Enter the 6-digit code from your authenticator app.</p>

    <form id="challengeForm">
        <input type="text" id="code" name="code" class="code-inp" inputmode="numeric" maxlength="6"
               placeholder="000000" autocomplete="one-time-code" autofocus required>
        <span class="l-err" id="codeErr">Please enter your code.</span>

        <button type="button" class="toggle-link" id="toggleMode">Use a recovery code instead</button>

        <button type="submit" id="submitBtn" class="submit-btn">
            <span class="submit-text">Verify</span>
            <div class="submit-loader">
                <div style="width:1.125rem;height:1.125rem;border:2px solid rgba(255,255,255,.35);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;"></div>
                <span style="font-size:.875rem;font-weight:600;">Verifying...</span>
            </div>
        </button>
    </form>

    <a href="{{ route('login') }}" class="back-link">&larr; Back to login</a>
</div>

<script>
    // ── Resolve the challenge token from the URL, or bounce to login ──────
    const params = new URLSearchParams(window.location.search);
    const twoFactorToken = params.get('token');
    if (!twoFactorToken) {
        window.location.href = '{{ route("login") }}';
    }

    // ── Toast (identical implementation to the login page) ────────────────
    const TOAST_DUR = 5000;
    const _icons = {
        success: `<svg style="width:1rem;height:1rem" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>`,
        error: `<svg style="width:1rem;height:1rem" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>`,
    };
    const _labels = { success: 'Success', error: 'Error' };

    function showToast(type, message, dur = TOAST_DUR) {
        const c = document.getElementById('toast-container');
        const t = document.createElement('div'); t.className = `toast toast-${type}`;
        const b = document.createElement('div'); b.className = 'toast-body';
        const ic = document.createElement('div'); ic.className = 'toast-icon'; ic.innerHTML = _icons[type];
        const co = document.createElement('div'); co.className = 'toast-content';
        const ti = document.createElement('p'); ti.className = 'toast-title'; ti.textContent = _labels[type];
        const ms = document.createElement('p'); ms.className = 'toast-msg'; ms.textContent = message;
        const cl = document.createElement('button'); cl.className = 'toast-close'; cl.setAttribute('aria-label', 'Close');
        cl.innerHTML = `<svg style="width:1rem;height:1rem" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>`;
        co.append(ti, ms); b.append(ic, co, cl);
        const pr = document.createElement('div'); pr.className = 'toast-progress'; pr.style.animationDuration = `${dur}ms`;
        t.append(b, pr); c.appendChild(t);
        requestAnimationFrame(() => requestAnimationFrame(() => t.classList.add('show')));
        function dismiss() {
            t.classList.replace('show', 'hide');
            setTimeout(() => t.remove(), 400);
        }
        cl.addEventListener('click', dismiss);
        setTimeout(dismiss, dur);
    }
    const showError = m => showToast('error', m);
    const showSuccess = m => showToast('success', m);

    // ── Recovery-code toggle ────────────────────────────────────────────
    const codeEl = document.getElementById('code');
    const toggleBtn = document.getElementById('toggleMode');
    const subText = document.getElementById('subText');
    let recoveryMode = false;

    toggleBtn.addEventListener('click', function () {
        recoveryMode = !recoveryMode;
        codeEl.classList.toggle('recovery-mode', recoveryMode);
        codeEl.value = '';
        if (recoveryMode) {
            codeEl.setAttribute('placeholder', 'XXXX-XXXX');
            codeEl.removeAttribute('maxlength');
            codeEl.removeAttribute('inputmode');
            subText.textContent = 'Enter one of your 8-character recovery codes.';
            toggleBtn.textContent = 'Use an authenticator code instead';
        } else {
            codeEl.setAttribute('placeholder', '000000');
            codeEl.setAttribute('maxlength', '6');
            codeEl.setAttribute('inputmode', 'numeric');
            subText.textContent = 'Enter the 6-digit code from your authenticator app.';
            toggleBtn.textContent = 'Use a recovery code instead';
        }
        codeEl.focus();
    });

    // ── Submit ──────────────────────────────────────────────────────────
    const form = document.getElementById('challengeForm');
    const btn = document.getElementById('submitBtn');

    function setLoading(on) {
        btn.disabled = on;
        btn.querySelector('.submit-text').style.opacity = on ? '0' : '1';
        btn.querySelector('.submit-loader').style.display = on ? 'flex' : 'none';
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const code = codeEl.value.trim();
        codeEl.classList.remove('is-error');
        document.getElementById('codeErr').style.display = 'none';

        if (!code) {
            codeEl.classList.add('is-error');
            document.getElementById('codeErr').style.display = 'block';
            return;
        }

        setLoading(true);
        try {
            const res = await fetch('/api/auth/2fa/verify', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ two_factor_token: twoFactorToken, code }),
            });
            const data = await res.json();

            if (res.ok && data.success) {
                localStorage.setItem('api_token', data.data.token);
                localStorage.setItem('user_data', JSON.stringify(data.data.user));
                showSuccess('Verified! Redirecting…');
                setTimeout(() => window.location.href = '/dashboard', 800);
            } else {
                showError(data.message || 'Invalid code. Please try again.');
                setLoading(false);
            }
        } catch {
            showError('An error occurred. Please try again.');
            setLoading(false);
        }
    });
</script>

</body>
</html>
