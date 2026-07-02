<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Reset Password — ECoSystem</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800&display=swap" rel="stylesheet">
    <style>
        /* ── Variables ─────────────────────────────────────────────── */
        :root {
            --red-deepest: #1A0000;
            --red-dark:    #6B0000;
            --red-mid:     #8B0000;
            --red-brand:   #A00000;
            --red-bright:  #B91C1C;
            --red-accent:  #CC2200;
            --off-white:   #F7F5F3;
            --gray-100:    #F0EDEA;
            --gray-200:    #E0DDD9;
            --gray-300:    #C4C1BD;
            --gray-400:    #9C9894;
            --gray-500:    #6B6864;
            --gray-700:    #3D3B39;
            --gray-900:    #1A1917;
            --shadow-card: 0 2px 4px rgba(0,0,0,.04), 0 8px 24px rgba(0,0,0,.07), 0 24px 56px rgba(0,0,0,.06);
            --shadow-btn:  0 4px 14px rgba(160,0,0,.4);
            --shadow-btn-h:0 8px 24px rgba(160,0,0,.55);
            --r-lg:   0.75rem;
            --r-xl:   1rem;
            --r-2xl:  1.25rem;
            --r-card: 1.375rem;
        }

        /* ── Reset ─────────────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { height: 100%; overflow: hidden; }
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            display: flex;
            height: 100vh;
            overflow: hidden;
            color: var(--gray-900);
        }

        /* ── Keyframes ─────────────────────────────────────────────── */
        @keyframes fadeInUp {
            from { opacity:0; transform:translateY(22px); }
            to   { opacity:1; transform:translateY(0);    }
        }
        @keyframes fadeInRight {
            from { opacity:0; transform:translateX(28px); }
            to   { opacity:1; transform:translateX(0);    }
        }
        @keyframes fadeIn {
            from { opacity:0; } to { opacity:1; }
        }
        @keyframes orbFloat1 {
            0%,100% { transform:translate(0,0) scale(1); }
            33%     { transform:translate(22px,-28px) scale(1.05); }
            66%     { transform:translate(-14px,16px) scale(0.97); }
        }
        @keyframes orbFloat2 {
            0%,100% { transform:translate(0,0) scale(1); }
            40%     { transform:translate(-24px,20px) scale(1.06); }
            75%     { transform:translate(10px,-18px) scale(0.96); }
        }
        @keyframes orbFloat3 {
            0%,100% { transform:translate(0,0) scale(1); }
            50%     { transform:translate(16px,-22px) scale(1.04); }
        }
        @keyframes ringPulse {
            0%,100% { transform:scale(1);    opacity:.09; }
            50%     { transform:scale(1.06); opacity:.18; }
        }
        @keyframes ringPulse2 {
            0%,100% { transform:scale(1);    opacity:.06; }
            50%     { transform:scale(1.08); opacity:.14; }
        }
        @keyframes pillDrift1 {
            0%,100% { transform:rotate(-42deg) translate(0,0); }
            30%     { transform:rotate(-41.2deg) translate(8px,-14px); }
            60%     { transform:rotate(-42.8deg) translate(-5px,-22px); }
        }
        @keyframes pillDrift2 {
            0%,100% { transform:rotate(-42deg) translate(0,0); }
            35%     { transform:rotate(-43.2deg) translate(-6px,-16px); }
            70%     { transform:rotate(-41deg) translate(5px,-10px); }
        }
        @keyframes pillDrift3 {
            0%,100% { transform:rotate(-42deg) translate(0,0); }
            45%     { transform:rotate(-42.5deg) translate(4px,-18px); }
        }
        @keyframes pillTop {
            0%,100% { transform:rotate(-18deg) translate(0,0); }
            35%     { transform:rotate(-17deg) translate(7px,-13px); }
            70%     { transform:rotate(-19deg) translate(-5px,8px); }
        }
        @keyframes diamondFloat {
            0%,100% { transform:rotate(45deg) translate(0,0); }
            30%     { transform:rotate(46.5deg) translate(5px,-8px); }
            65%     { transform:rotate(43.5deg) translate(-4px,9px); }
        }
        @keyframes dotDrift {
            0%,100% { transform:translate(0,0);      opacity:.35; }
            33%     { transform:translate(5px,-8px);  opacity:.55; }
            66%     { transform:translate(-4px,5px); opacity:.4;  }
        }
        @keyframes dotDrift2 {
            0%,100% { transform:translate(0,0);       opacity:.25; }
            40%     { transform:translate(-6px,-5px); opacity:.45; }
            75%     { transform:translate(4px,7px);   opacity:.3;  }
        }
        @keyframes shimmerLine {
            0%,100% { opacity:.05; } 50% { opacity:.15; }
        }
        @keyframes spin { to { transform:rotate(360deg); } }
        @keyframes progress-shrink { from{transform:scaleX(1)} to{transform:scaleX(0)} }

        /* ── Layout ────────────────────────────────────────────────── */
        #deco-panel  { display:none; }
        #form-panel  { width:100%; }

        @media (min-width:768px) {
            #deco-panel  { display:flex !important; width:50%; }
            #form-panel  { width:50%; }
        }
        @media (min-width:1280px) {
            #deco-panel  { width:55%; }
            #form-panel  { width:45%; }
        }

        /* ── Left panel ─────────────────────────────────────────────── */
        .dp-badge {
            display:inline-flex; align-items:center; gap:.5rem;
            background:rgba(255,255,255,.1); backdrop-filter:blur(8px);
            border:1px solid rgba(255,255,255,.16); border-radius:9999px;
            padding:.3rem 1rem; margin-bottom:1.75rem;
            opacity:0; animation:fadeInUp .6s ease forwards; animation-delay:.05s;
        }
        .dp-headline {
            font-family:'Plus Jakarta Sans',sans-serif;
            font-size:clamp(2.125rem,3.2vw,3rem);
            font-weight:800; color:#fff;
            line-height:1.12; letter-spacing:-.04em;
            margin-bottom:1rem; max-width:440px;
            opacity:0; animation:fadeInUp .6s ease forwards; animation-delay:.15s;
        }
        .dp-sub {
            font-size:.9rem; color:rgba(255,255,255,.65);
            line-height:1.8; max-width:400px; margin-bottom:1.875rem;
            opacity:0; animation:fadeInUp .6s ease forwards; animation-delay:.25s;
        }
        .dp-steps { display:flex; flex-direction:column; gap:.6875rem; margin-bottom:1.75rem; }
        .dp-step {
            display:flex; align-items:flex-start; gap:.875rem;
            padding:.6rem .75rem; border-radius:var(--r-xl);
            border:1px solid rgba(255,255,255,.08);
            background:rgba(255,255,255,.06); backdrop-filter:blur(4px);
            cursor:default;
            transition:transform .25s ease, background .25s ease, border-color .25s ease;
            opacity:0;
        }
        .dp-step:hover {
            transform:translateX(5px);
            background:rgba(255,255,255,.11);
            border-color:rgba(255,255,255,.15);
        }
        .dp-step:nth-child(1) { animation:fadeInUp .6s ease forwards; animation-delay:.35s; }
        .dp-step:nth-child(2) { animation:fadeInUp .6s ease forwards; animation-delay:.45s; }
        .dp-step:nth-child(3) { animation:fadeInUp .6s ease forwards; animation-delay:.55s; }
        .dp-icon {
            width:2.25rem; height:2.25rem; border-radius:var(--r-lg); flex-shrink:0;
            background:rgba(255,255,255,.13); border:1px solid rgba(255,255,255,.18);
            display:flex; align-items:center; justify-content:center; margin-top:.05rem;
        }
        .dp-step-num {
            width:2.25rem; height:2.25rem; border-radius:50%; flex-shrink:0;
            background:rgba(255,255,255,.13); border:1px solid rgba(255,255,255,.18);
            display:flex; align-items:center; justify-content:center; margin-top:.05rem;
            font-size:.6875rem; font-weight:800; color:#fff;
        }
        .dp-notice {
            display:inline-flex; align-items:flex-start; gap:.5rem;
            background:rgba(0,0,0,.2); border:1px solid rgba(255,255,255,.08);
            border-radius:var(--r-lg); padding:.625rem .875rem; max-width:440px;
            opacity:0; animation:fadeInUp .6s ease forwards; animation-delay:.65s;
        }

        /* ── Right panel / form ─────────────────────────────────────── */
        .fp-inner {
            width:100%; max-width:400px;
            opacity:0; animation:fadeInRight .7s ease forwards; animation-delay:.3s;
        }

        /* Floating label wrapper */
        .fl-wrap { position:relative; }

        .fl-inp {
            width:100%;
            padding:1.375rem 2.875rem .5rem 1rem;
            border:1.5px solid #CBD5E1;
            border-radius:8px;
            font-family:'Plus Jakarta Sans',sans-serif;
            font-size:.9375rem; font-weight:500;
            color:#0F172A; background:#fff;
            outline:none; line-height:1.4;
            transition:border-color .2s ease, box-shadow .2s ease;
        }
        .fl-inp::placeholder { color:transparent; }
        .fl-inp:-webkit-autofill,
        .fl-inp:-webkit-autofill:hover,
        .fl-inp:-webkit-autofill:focus {
            -webkit-text-fill-color:#0F172A;
            -webkit-box-shadow:0 0 0 1000px #fff inset;
            font-family:'Plus Jakarta Sans',sans-serif;
            font-size:.9375rem; font-weight:500;
            transition:background-color 9999s ease-in-out 0s;
        }
        .fl-inp:focus {
            border-color:#A00000;
            box-shadow:0 0 0 3px rgba(160,0,0,.1);
        }
        .fl-inp.is-error {
            border-color:#DC2626;
            box-shadow:0 0 0 3px rgba(220,38,38,.1);
        }

        /* Floating label */
        .fl-label {
            position:absolute; left:1rem; top:50%;
            transform:translateY(-50%);
            font-size:.9rem; color:#94A3B8;
            pointer-events:none; white-space:nowrap;
            font-family:'Plus Jakarta Sans',sans-serif;
            transition:top .18s ease, transform .18s ease,
                       font-size .18s ease, color .18s ease, font-weight .18s ease;
        }
        .fl-inp:focus ~ .fl-label,
        .fl-inp:not(:placeholder-shown) ~ .fl-label {
            top:.625rem; transform:none;
            font-size:.6875rem; font-weight:600;
            color:#A00000; letter-spacing:.02em;
        }
        .fl-inp.is-error ~ .fl-label { color:#DC2626; }

        /* Right-side icon */
        .fl-r-icon {
            position:absolute; right:.875rem; top:50%; transform:translateY(-50%);
            width:1.125rem; height:1.125rem;
            color:#CBD5E1; pointer-events:none;
            display:flex; align-items:center;
            transition:color .2s ease;
        }
        .fl-wrap:focus-within .fl-r-icon { color:#A00000; }

        /* Error message */
        .l-err { font-size:.6875rem; color:#DC2626; display:none; margin-top:.3rem; padding-left:.25rem; }

        /* Submit button */
        .fp-btn {
            width:100%; padding:.9375rem 1rem;
            background:#A00000;
            color:#fff; font-family:'Plus Jakarta Sans',sans-serif;
            font-size:.9375rem; font-weight:600;
            border:none; border-radius:8px; cursor:pointer;
            position:relative; overflow:hidden;
            transition:background .2s ease, transform .15s ease;
        }
        .fp-btn:hover:not(:disabled) { background:#800000; }
        .fp-btn:active:not(:disabled) { transform:scale(.98); }
        .fp-btn:disabled { cursor:not-allowed; opacity:.85; }

        /* ── Toast ─────────────────────────────────────────────────── */
        #toast-container {
            position:fixed; top:1.5rem; right:1.5rem; z-index:9999;
            display:flex; flex-direction:column; gap:.75rem;
            max-width:22rem; width:100%; pointer-events:none;
        }
        .toast {
            pointer-events:all; border-radius:.875rem;
            padding:1rem 1rem 0 1rem; display:flex; flex-direction:column;
            box-shadow:0 10px 30px rgba(0,0,0,.15); overflow:hidden;
            transform:translateX(110%); opacity:0;
            transition:transform .4s cubic-bezier(.34,1.56,.64,1), opacity .3s ease;
        }
        .toast.show { transform:translateX(0); opacity:1; }
        .toast.hide { transform:translateX(110%); opacity:0; transition:transform .35s ease-in, opacity .3s ease-in; }
        .toast-body    { display:flex; align-items:flex-start; gap:.75rem; padding-bottom:.875rem; }
        .toast-icon    { flex-shrink:0; width:2rem; height:2rem; border-radius:50%; display:flex; align-items:center; justify-content:center; }
        .toast-content { flex:1; min-width:0; }
        .toast-title   { font-size:.8125rem; font-weight:700; line-height:1.2; }
        .toast-msg     { font-size:.8125rem; margin-top:.2rem; line-height:1.4; }
        .toast-close   { flex-shrink:0; background:none; border:none; cursor:pointer; padding:.1rem; border-radius:.375rem; opacity:.5; transition:opacity .2s; }
        .toast-close:hover { opacity:1; }
        .toast-progress { height:3px; border-radius:0 0 .875rem .875rem; margin:0 -1rem; transform-origin:left; animation:progress-shrink linear forwards; }
        .toast-error   { background:#fff1f1; border:1.5px solid #fca5a5; }
        .toast-error .toast-icon { background:#fee2e2; } .toast-error .toast-icon svg { color:#dc2626; }
        .toast-error .toast-title { color:#991b1b; } .toast-error .toast-msg { color:#b91c1c; }
        .toast-error .toast-close { color:#991b1b; } .toast-error .toast-progress { background:#ef4444; }
        .toast-success { background:#f0fdf4; border:1.5px solid #86efac; }
        .toast-success .toast-icon { background:#dcfce7; } .toast-success .toast-icon svg { color:#16a34a; }
        .toast-success .toast-title { color:#14532d; } .toast-success .toast-msg { color:#15803d; }
        .toast-success .toast-close { color:#14532d; } .toast-success .toast-progress { background:#22c55e; }
        .toast-warning { background:#fffbeb; border:1.5px solid #fcd34d; }
        .toast-warning .toast-icon { background:#fef9c3; } .toast-warning .toast-icon svg { color:#d97706; }
        .toast-warning .toast-title { color:#78350f; } .toast-warning .toast-msg { color:#92400e; }
        .toast-warning .toast-close { color:#78350f; } .toast-warning .toast-progress { background:#f59e0b; }
        .toast-info { background:#eff6ff; border:1.5px solid #93c5fd; }
        .toast-info .toast-icon { background:#dbeafe; } .toast-info .toast-icon svg { color:#2563eb; }
        .toast-info .toast-title { color:#1e3a8a; } .toast-info .toast-msg { color:#1d4ed8; }
        .toast-info .toast-close { color:#1e3a8a; } .toast-info .toast-progress { background:#3b82f6; }

        /* ── Mobile ─────────────────────────────────────────────────── */
        .mobile-strip { display:none; }
        @media (max-width:767px) {
            html { height:auto; overflow:auto; }
            body { flex-direction:column; overflow-y:auto; height:auto; min-height:100vh; }
            .mobile-strip {
                display:flex; align-items:center; justify-content:center; gap:.75rem;
                background:linear-gradient(135deg, #1A0000, #6B0000);
                padding:1.25rem 1.5rem;
            }
            /* The panel carries inline height:100vh/overflow:hidden — beat it with
               !important so the whole form (incl. submit) can scroll into view on
               short screens (phone landscape / on-screen keyboard). */
            #form-panel { width:100%; height:auto !important; min-height:calc(100vh - 62px); overflow:visible !important; }
            #form-panel > div:nth-child(3) { padding:1rem 1.5rem 0; }
            .fp-inner { max-width:100%; }
        }
    </style>
</head>
<body>

<div id="toast-container"></div>

{{-- Mobile header strip --}}
<div class="mobile-strip">
    <img src="/images/eclectic_logo_nobg.png" alt="ECoSystem"
         style="height:1.875rem;width:auto;filter:brightness(0) invert(1);">
    <span style="font-size:.8125rem;font-weight:600;color:rgba(255,255,255,.85);letter-spacing:.05em;">ECoSystem</span>
</div>

{{-- ════════════════════════════════════════════════════════════
     LEFT PANEL
════════════════════════════════════════════════════════════ --}}
<div id="deco-panel"
     style="position:relative; overflow:hidden; flex-direction:column;
            align-items:flex-start; justify-content:center; padding:0 4.75rem;
            height:100vh;
            background:linear-gradient(155deg,#1A0000 0%,#3D0000 18%,#6B0000 40%,#8B0000 62%,#A00000 80%,#B91C1C 100%);">

    {{-- Orbs --}}
    <div style="position:absolute;top:-80px;right:-60px;width:360px;height:360px;border-radius:50%;
                background:radial-gradient(circle,rgba(204,34,0,.18) 0%,transparent 68%);
                pointer-events:none;animation:orbFloat1 9s ease-in-out infinite;"></div>
    <div style="position:absolute;bottom:-100px;left:-60px;width:280px;height:280px;border-radius:50%;
                background:radial-gradient(circle,rgba(255,68,0,.1) 0%,transparent 68%);
                pointer-events:none;animation:orbFloat2 11s ease-in-out infinite;animation-delay:-2s;"></div>
    <div style="position:absolute;top:28%;right:4%;width:240px;height:240px;border-radius:50%;
                background:radial-gradient(circle,rgba(192,57,43,.12) 0%,transparent 70%);
                pointer-events:none;animation:orbFloat3 13s ease-in-out infinite;animation-delay:-4s;"></div>

    {{-- Rings top-right --}}
    <div style="position:absolute;top:-150px;right:-150px;width:520px;height:520px;border-radius:50%;
                border:1px solid rgba(255,255,255,.07);pointer-events:none;
                animation:ringPulse 9s ease-in-out infinite;"></div>
    <div style="position:absolute;top:-90px;right:-90px;width:360px;height:360px;border-radius:50%;
                border:1px solid rgba(255,255,255,.09);pointer-events:none;
                animation:ringPulse 9s ease-in-out infinite;animation-delay:-3s;"></div>

    {{-- Rings bottom-left --}}
    <div style="position:absolute;bottom:-160px;left:-160px;width:480px;height:480px;border-radius:50%;
                border:1px solid rgba(255,255,255,.06);pointer-events:none;
                animation:ringPulse2 12s ease-in-out infinite;"></div>
    <div style="position:absolute;bottom:-100px;left:-100px;width:320px;height:320px;border-radius:50%;
                border:1px solid rgba(255,255,255,.08);pointer-events:none;
                animation:ringPulse 10s ease-in-out infinite;animation-delay:-5s;"></div>

    {{-- Mid-right ring --}}
    <div style="position:absolute;top:42%;right:4%;width:130px;height:130px;border-radius:50%;
                border:1px solid rgba(255,255,255,.07);pointer-events:none;
                animation:ringPulse2 14s ease-in-out infinite;animation-delay:-6s;"></div>

    {{-- Diamonds --}}
    <div style="position:absolute;top:19%;right:17%;width:44px;height:44px;border-radius:7px;
                background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.13);
                pointer-events:none;animation:diamondFloat 9s ease-in-out infinite;"></div>
    <div style="position:absolute;top:46%;right:23%;width:28px;height:28px;border-radius:5px;
                background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);
                pointer-events:none;animation:diamondFloat 12s ease-in-out infinite;animation-delay:-5s;"></div>
    <div style="position:absolute;bottom:24%;left:36%;width:20px;height:20px;border-radius:4px;
                background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);
                pointer-events:none;animation:diamondFloat 8s ease-in-out infinite;animation-delay:-2.5s;"></div>

    {{-- Floating pills upper/mid --}}
    <div style="position:absolute;top:8%;right:3%;width:210px;height:54px;border-radius:9999px;
                background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);
                backdrop-filter:blur(4px);pointer-events:none;
                animation:pillTop 9s cubic-bezier(.45,.05,.55,.95) infinite;"></div>
    <div style="position:absolute;top:60%;right:2%;width:140px;height:38px;border-radius:9999px;
                background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);
                backdrop-filter:blur(3px);pointer-events:none;
                animation:pillTop 11s cubic-bezier(.45,.05,.55,.95) infinite;animation-delay:-4s;"></div>

    {{-- Dot clusters --}}
    <div style="position:absolute;top:13%;right:22%;pointer-events:none;">
        <div style="width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,.35);position:absolute;animation:dotDrift 6s ease-in-out infinite;"></div>
        <div style="width:4px;height:4px;border-radius:50%;background:rgba(255,255,255,.25);position:absolute;top:18px;left:20px;animation:dotDrift2 7s ease-in-out infinite;animation-delay:-.8s;"></div>
        <div style="width:5px;height:5px;border-radius:50%;background:rgba(255,255,255,.2);position:absolute;top:38px;left:6px;animation:dotDrift 8s ease-in-out infinite;animation-delay:-1.6s;"></div>
    </div>
    <div style="position:absolute;bottom:28%;right:27%;pointer-events:none;">
        <div style="width:5px;height:5px;border-radius:50%;background:rgba(255,255,255,.28);position:absolute;animation:dotDrift 8s ease-in-out infinite;"></div>
        <div style="width:3px;height:3px;border-radius:50%;background:rgba(255,255,255,.18);position:absolute;top:14px;left:18px;animation:dotDrift2 9s ease-in-out infinite;animation-delay:-1.5s;"></div>
        <div style="width:4px;height:4px;border-radius:50%;background:rgba(255,255,255,.15);position:absolute;top:-8px;left:30px;animation:dotDrift 7s ease-in-out infinite;animation-delay:-3s;"></div>
    </div>
    <div style="position:absolute;top:50%;left:5%;pointer-events:none;">
        <div style="width:5px;height:5px;border-radius:50%;background:rgba(255,255,255,.2);position:absolute;animation:dotDrift2 9s ease-in-out infinite;"></div>
        <div style="width:3px;height:3px;border-radius:50%;background:rgba(255,255,255,.15);position:absolute;top:14px;left:16px;animation:dotDrift 7s ease-in-out infinite;animation-delay:-2s;"></div>
    </div>

    {{-- Shimmer lines lower-left --}}
    <div style="position:absolute;bottom:19%;left:3%;pointer-events:none;display:flex;flex-direction:column;gap:7px;">
        <div style="width:60px;height:2px;border-radius:9999px;background:rgba(255,255,255,.09);animation:shimmerLine 5s ease-in-out infinite;"></div>
        <div style="width:40px;height:1.5px;border-radius:9999px;background:rgba(255,255,255,.07);animation:shimmerLine 6.5s ease-in-out infinite;animation-delay:-2s;"></div>
        <div style="width:26px;height:1px;border-radius:9999px;background:rgba(255,255,255,.05);animation:shimmerLine 8s ease-in-out infinite;animation-delay:-1s;"></div>
    </div>

    {{-- Glass pills — bottom-right cluster --}}
    <div style="position:absolute;bottom:-18px;right:-45px;pointer-events:none;">
        <div style="position:absolute;width:300px;height:74px;border-radius:9999px;
                    background:rgba(255,255,255,.09);border:1px solid rgba(255,255,255,.14);
                    backdrop-filter:blur(6px);bottom:0;right:0;
                    animation:pillDrift1 7s cubic-bezier(.45,.05,.55,.95) infinite;"></div>
        <div style="position:absolute;width:248px;height:62px;border-radius:9999px;
                    background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);
                    backdrop-filter:blur(4px);bottom:68px;right:46px;
                    animation:pillDrift2 8.5s cubic-bezier(.45,.05,.55,.95) infinite;animation-delay:-1.8s;"></div>
        <div style="position:absolute;width:198px;height:52px;border-radius:9999px;
                    background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);
                    backdrop-filter:blur(3px);bottom:128px;right:88px;
                    animation:pillDrift3 7.5s cubic-bezier(.45,.05,.55,.95) infinite;animation-delay:-3.5s;"></div>
        <div style="position:absolute;width:155px;height:42px;border-radius:9999px;
                    background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);
                    bottom:186px;right:126px;
                    animation:pillDrift1 9s cubic-bezier(.45,.05,.55,.95) infinite;animation-delay:-5s;"></div>
    </div>

    {{-- ── Content ──────────────────────────────────────────────── --}}
    <div style="position:relative;z-index:10;max-width:480px;">

        {{-- Badge --}}
        <div class="dp-badge">
            <svg style="width:.75rem;height:.75rem;color:rgba(255,255,255,.65);flex-shrink:0;"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            <span style="font-size:.6rem;font-weight:700;color:rgba(255,255,255,.82);letter-spacing:.12em;text-transform:uppercase;">
                ECoSystem &nbsp;&middot;&nbsp; Account Recovery
            </span>
        </div>

        {{-- Headline --}}
        <h1 class="dp-headline">
            Recover<br>your account<br>securely.
        </h1>

        {{-- Subheading --}}
        <p class="dp-sub">
            No worries — it happens. We'll send you a secure reset link
            straight to your registered email address.
        </p>

        {{-- Steps --}}
        <div class="dp-steps">
            <div class="dp-step">
                <div class="dp-step-num">1</div>
                <div>
                    <p style="font-size:.8125rem;color:#fff;font-weight:700;line-height:1.3;">Enter your email</p>
                    <p style="font-size:.6875rem;color:rgba(255,255,255,.6);margin-top:.2rem;line-height:1.5;">
                        Use the email address linked to your ECoSystem account.
                    </p>
                </div>
            </div>

            <div class="dp-step">
                <div class="dp-step-num">2</div>
                <div>
                    <p style="font-size:.8125rem;color:#fff;font-weight:700;line-height:1.3;">Check your inbox</p>
                    <p style="font-size:.6875rem;color:rgba(255,255,255,.6);margin-top:.2rem;line-height:1.5;">
                        A password reset link will arrive within a few minutes.
                    </p>
                </div>
            </div>

            <div class="dp-step">
                <div class="dp-step-num">3</div>
                <div>
                    <p style="font-size:.8125rem;color:#fff;font-weight:700;line-height:1.3;">Set a new password</p>
                    <p style="font-size:.6875rem;color:rgba(255,255,255,.6);margin-top:.2rem;line-height:1.5;">
                        Follow the link and create a strong new password.
                    </p>
                </div>
            </div>
        </div>

        {{-- Notice --}}
        <div class="dp-notice">
            <svg style="width:.8rem;height:.8rem;color:rgba(255,255,255,.4);flex-shrink:0;margin-top:.15rem;"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span style="font-size:.6875rem;color:rgba(255,255,255,.4);line-height:1.6;">
                Didn't get the email? Check your spam folder or contact your IT Administrator.
            </span>
        </div>
    </div>
</div>

{{-- ════════════════════════════════════════════════════════════
     RIGHT PANEL
════════════════════════════════════════════════════════════ --}}
<div id="form-panel"
     style="display:flex; flex-direction:column;
            background:#fff; height:100vh; overflow:hidden; position:relative;">

    {{-- Subtle echo rings --}}
    <div style="position:absolute;bottom:-140px;right:-140px;width:420px;height:420px;border-radius:50%;
                border:1px solid rgba(160,0,0,.05);pointer-events:none;z-index:0;"></div>
    <div style="position:absolute;bottom:20%;left:-60px;width:200px;height:200px;border-radius:50%;
                border:1px solid rgba(160,0,0,.04);pointer-events:none;z-index:0;"></div>

    {{-- Row 1: Logo bar --}}
    <div style="flex-shrink:0; display:flex; justify-content:flex-end; align-items:center;
                padding:1.625rem 2.25rem; position:relative; z-index:1;">
        <img src="/images/eclectic_logo_nobg.png" alt="Eclectic Consulting"
             style="height:3rem; width:auto; display:block;
                    opacity:0; animation:fadeIn .5s ease forwards; animation-delay:.2s;">
    </div>

    {{-- Row 2: Form centered --}}
    <div style="flex:1; display:flex; align-items:center; justify-content:center;
                padding:1rem 2.5rem 0; position:relative; z-index:1;">

        <div class="fp-inner">

            {{-- Heading --}}
            <div style="margin-bottom:1.875rem;">
                <p style="font-size:.8125rem;color:#64748B;font-weight:400;margin-bottom:.25rem;
                          opacity:0;animation:fadeInUp .5s ease forwards;animation-delay:.35s;">
                    Account Recovery
                </p>
                <h2 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.75rem;font-weight:800;
                           color:#0F172A;letter-spacing:-.035em;line-height:1.2;
                           opacity:0;animation:fadeInUp .5s ease forwards;animation-delay:.43s;">
                    Reset your password
                </h2>
                <p style="font-size:.875rem;color:#64748B;margin-top:.5rem;line-height:1.6;
                          opacity:0;animation:fadeInUp .5s ease forwards;animation-delay:.51s;">
                    Enter your registered email and we'll send you a link to get back in.
                </p>
            </div>

            @if(session('success'))
                <script>window._flashSuccess = @json(session('success'));</script>
            @endif
            @if(session('error'))
                <script>window._flashError = @json(session('error'));</script>
            @endif
            @if($errors->any())
                <script>window._flashError = @json($errors->first());</script>
            @endif

            {{-- Form --}}
            <form id="forgotForm" method="POST" action="/forgot-password"
                  style="display:flex;flex-direction:column;gap:1.125rem;
                         opacity:0;animation:fadeInUp .5s ease forwards;animation-delay:.59s;">
                @csrf

                {{-- Email --}}
                <div>
                    <div class="fl-wrap">
                        <input type="email" id="email" name="email"
                               value="{{ old('email') }}"
                               placeholder=" " class="fl-inp {{ $errors->has('email') ? 'is-error' : '' }}"
                               autocomplete="email" required autofocus>
                        <label class="fl-label" for="email">Registered Email</label>
                        <span class="fl-r-icon" aria-hidden="true">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:100%;height:100%;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </span>
                    </div>
                    <span class="l-err" id="emailErr"
                          @if($errors->has('email')) style="display:block;" @endif>
                        {{ $errors->first('email') ?: 'Please enter a valid email address.' }}
                    </span>
                </div>

                {{-- Submit --}}
                <button type="submit" id="submitBtn" class="fp-btn" aria-label="Send password reset link">
                    <span class="submit-text" style="display:flex;align-items:center;justify-content:center;gap:.5rem;">
                        <svg style="width:1rem;height:1rem;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        Send Reset Link
                    </span>
                    <div class="submit-loader"
                         style="display:none;position:absolute;inset:0;align-items:center;justify-content:center;gap:.5rem;">
                        <div style="width:1.125rem;height:1.125rem;border:2px solid rgba(255,255,255,.35);
                                    border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;"></div>
                        <span style="font-size:.875rem;font-weight:600;">Sending…</span>
                    </div>
                </button>

                {{-- Back to login --}}
                <p style="text-align:center;font-size:.8125rem;color:#64748B;margin-top:.125rem;">
                    Remember your password?
                    <a href="{{ route('login') }}"
                       style="color:#A00000;font-weight:600;text-decoration:none;transition:color .15s;"
                       onmouseover="this.style.color='#6B0000'"
                       onmouseout="this.style.color='#A00000'">Back to Sign In</a>
                </p>
            </form>
        </div>
    </div>

    {{-- Row 3: Copyright bar --}}
    <div style="flex-shrink:0; text-align:center; padding:1.25rem 2rem 1.5rem; position:relative; z-index:1;
                opacity:0; animation:fadeIn .6s ease forwards; animation-delay:.85s;">
        <p style="font-size:.6875rem;color:#CBD5E1;line-height:1.5;">
            &copy; {{ date('Y') }} ECoSystem by Eclectic Consulting. All rights reserved.
        </p>
    </div>
</div>

<script>
    // ── Toast ────────────────────────────────────────────────────
    const TOAST_DUR = 5000;
    const _icons = {
        success:`<svg style="width:1rem;height:1rem" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>`,
        error:  `<svg style="width:1rem;height:1rem" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>`,
        warning:`<svg style="width:1rem;height:1rem" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>`,
        info:   `<svg style="width:1rem;height:1rem" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>`,
    };
    const _labels = { success:'Success', error:'Error', warning:'Warning', info:'Information' };

    function showToast(type, message, dur = TOAST_DUR) {
        const c  = document.getElementById('toast-container');
        const t  = document.createElement('div'); t.className = `toast toast-${type}`;
        const b  = document.createElement('div'); b.className = 'toast-body';
        const ic = document.createElement('div'); ic.className = 'toast-icon'; ic.innerHTML = _icons[type];
        const co = document.createElement('div'); co.className = 'toast-content';
        const ti = document.createElement('p');   ti.className = 'toast-title';   ti.textContent = _labels[type];
        const ms = document.createElement('p');   ms.className = 'toast-msg';     ms.textContent = message;
        const cl = document.createElement('button'); cl.className = 'toast-close'; cl.setAttribute('aria-label','Close');
        cl.innerHTML = `<svg style="width:1rem;height:1rem" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>`;
        co.append(ti, ms); b.append(ic, co, cl);
        const pr = document.createElement('div'); pr.className = 'toast-progress'; pr.style.animationDuration = `${dur}ms`;
        t.append(b, pr); c.appendChild(t);
        requestAnimationFrame(() => requestAnimationFrame(() => t.classList.add('show')));
        function dismiss() {
            t.classList.replace('show','hide');
            t.addEventListener('transitionend', () => t.remove(), { once:true });
        }
        const timer = setTimeout(dismiss, dur);
        cl.addEventListener('click', () => { clearTimeout(timer); dismiss(); });
    }
    const showError   = m => showToast('error',   m);
    const showSuccess = m => showToast('success', m);

    // ── Form loading state ───────────────────────────────────────
    const form    = document.getElementById('forgotForm');
    const btn     = document.getElementById('submitBtn');
    const emailEl = document.getElementById('email');

    function setLoading(on) {
        btn.disabled = on;
        btn.querySelector('.submit-text').style.opacity   = on ? '0' : '1';
        btn.querySelector('.submit-loader').style.display = on ? 'flex' : 'none';
    }

    form.addEventListener('submit', function () {
        const email = emailEl.value.trim();
        if (!email) {
            emailEl.classList.add('is-error');
            document.getElementById('emailErr').style.display = 'block';
            return;
        }
        setLoading(true);
    });

    emailEl.addEventListener('input', () => {
        emailEl.classList.remove('is-error');
        document.getElementById('emailErr').style.display = 'none';
    });

    window.addEventListener('load', () => {
        if (window._flashSuccess) showSuccess(window._flashSuccess);
        if (window._flashError)   showError(window._flashError);
    });
</script>
</body>
</html>
