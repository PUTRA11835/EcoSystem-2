<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $type === 'reset' ? 'Email Sent' : 'Check Your Email' }} - ECoSystem</title>
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
        @keyframes iconPop {
            0%   { opacity:0; transform:scale(.6); }
            70%  { transform:scale(1.08); }
            100% { opacity:1; transform:scale(1); }
        }
        @keyframes iconRing {
            0%,100% { transform:scale(1); opacity:.15; }
            50%     { transform:scale(1.18); opacity:.28; }
        }
        @keyframes checkDraw {
            from { stroke-dashoffset: 60; }
            to   { stroke-dashoffset: 0;  }
        }

        /* ── Layout ────────────────────────────────────────────────── */
        #deco-panel  { display:none; }
        #right-panel { width:100%; }

        @media (min-width:768px) {
            #deco-panel  { display:flex !important; width:50%; }
            #right-panel { width:50%; }
        }
        @media (min-width:1280px) {
            #deco-panel  { width:55%; }
            #right-panel { width:45%; }
        }

        /* ── Left panel content classes ────────────────────────────── */
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
        .dp-features { display:flex; flex-direction:column; gap:.6875rem; margin-bottom:1.75rem; }
        .dp-feature {
            display:flex; align-items:flex-start; gap:.875rem;
            padding:.6rem .75rem; border-radius:var(--r-xl);
            border:1px solid rgba(255,255,255,.08);
            background:rgba(255,255,255,.06); backdrop-filter:blur(4px);
            cursor:default;
            transition:transform .25s ease, background .25s ease, border-color .25s ease;
            opacity:0;
        }
        .dp-feature:hover {
            transform:translateX(5px);
            background:rgba(255,255,255,.11);
            border-color:rgba(255,255,255,.15);
        }
        .dp-feature:nth-child(1) { animation:fadeInUp .6s ease forwards; animation-delay:.35s; }
        .dp-feature:nth-child(2) { animation:fadeInUp .6s ease forwards; animation-delay:.45s; }
        .dp-feature:nth-child(3) { animation:fadeInUp .6s ease forwards; animation-delay:.55s; }
        .dp-icon {
            width:2.25rem; height:2.25rem; border-radius:var(--r-lg); flex-shrink:0;
            background:rgba(255,255,255,.13); border:1px solid rgba(255,255,255,.18);
            display:flex; align-items:center; justify-content:center; margin-top:.05rem;
        }
        .dp-notice {
            display:inline-flex; align-items:flex-start; gap:.5rem;
            background:rgba(0,0,0,.2); border:1px solid rgba(255,255,255,.08);
            border-radius:var(--r-lg); padding:.625rem .875rem; max-width:440px;
            opacity:0; animation:fadeInUp .6s ease forwards; animation-delay:.65s;
        }

        /* ── Right panel ────────────────────────────────────────────── */
        .rp-inner {
            width:100%; max-width:400px;
            opacity:0; animation:fadeInRight .7s ease forwards; animation-delay:.3s;
        }

        /* ── Email icon ─────────────────────────────────────────────── */
        .email-icon-wrap {
            position:relative; display:inline-flex;
            align-items:center; justify-content:center;
            margin-bottom:1.25rem;
        }
        .email-icon-ring {
            position:absolute; width:80px; height:80px; border-radius:50%;
            border:1px solid rgba(160,0,0,.14);
            animation:iconRing 3.5s ease-in-out infinite;
        }
        .email-icon-ring2 {
            position:absolute; width:100px; height:100px; border-radius:50%;
            border:1px solid rgba(160,0,0,.07);
            animation:iconRing 3.5s ease-in-out infinite; animation-delay:-1s;
        }
        .email-icon-bg {
            width:60px; height:60px; border-radius:50%;
            background:linear-gradient(145deg,#fff5f5,#fee2e2);
            border:1.5px solid rgba(160,0,0,.12);
            box-shadow:0 6px 18px rgba(160,0,0,.1), 0 2px 5px rgba(160,0,0,.07);
            display:flex; align-items:center; justify-content:center;
            animation:iconPop .55s cubic-bezier(.34,1.56,.64,1) forwards;
            animation-delay:.4s; opacity:0;
        }

        /* ── Divider ────────────────────────────────────────────────── */
        .rp-divider {
            width:100%; height:1px;
            background:linear-gradient(90deg, transparent, #E2E8F0 20%, #E2E8F0 80%, transparent);
            margin:1rem 0;
        }

        /* ── Tips card ──────────────────────────────────────────────── */
        .tips-card {
            background:#FAFAFA;
            border:1.5px solid #E2E8F0;
            border-radius:var(--r-xl);
            padding:.875rem 1rem;
            text-align:left;
        }
        .tips-row {
            display:flex; align-items:flex-start; gap:.625rem;
            padding:.45rem .5rem; border-radius:var(--r-lg);
            transition:background .15s ease;
        }
        .tips-row:hover { background:rgba(160,0,0,.03); }
        .tips-icon-wrap {
            width:28px; height:28px; border-radius:8px; flex-shrink:0;
            display:flex; align-items:center; justify-content:center;
        }

        /* ── Back link ──────────────────────────────────────────────── */
        .back-link {
            display:inline-flex; align-items:center; gap:.375rem;
            font-size:.8125rem; color:#A00000; font-weight:600;
            text-decoration:none; padding:.5rem .875rem;
            border:1.5px solid rgba(160,0,0,.2); border-radius:9999px;
            transition:background .15s ease, border-color .15s ease, gap .15s ease;
        }
        .back-link:hover {
            background:rgba(160,0,0,.04); border-color:rgba(160,0,0,.35); gap:.5rem;
        }

        /* ── Mobile ─────────────────────────────────────────────────── */
        .mobile-strip { display:none; }
        @media (max-width:767px) {
            body { flex-direction:column; overflow-y:auto; height:auto; }
            html { height:auto; }
            .mobile-strip {
                display:flex; align-items:center; justify-content:center; gap:.75rem;
                background:linear-gradient(135deg, #1A0000, #6B0000);
                padding:1.25rem 1.5rem;
            }
            #right-panel { width:100%; height:auto; min-height:calc(100vh - 62px); }
            #right-panel > div:nth-child(3) { padding:1rem 1.5rem 0; }
            .rp-inner { max-width:100%; }
        }
    </style>
</head>
<body>

{{-- Mobile header strip --}}
<div class="mobile-strip">
    <img src="/images/eclectic_logo_nobg.png" alt="ECoSystem"
         style="height:1.875rem;width:auto;filter:brightness(0) invert(1);">
    <span style="font-size:.8125rem;font-weight:600;color:rgba(255,255,255,.85);letter-spacing:.05em;">ECoSystem</span>
</div>

{{-- ════════════════════════════════════════════════════════════
     LEFT PANEL — identical to login page
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

        <div class="dp-badge">
            <svg style="width:.75rem;height:.75rem;color:rgba(255,255,255,.65);flex-shrink:0;"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            <span style="font-size:.6rem;font-weight:700;color:rgba(255,255,255,.82);letter-spacing:.12em;text-transform:uppercase;">
                ECoSystem &nbsp;&middot;&nbsp; Powered by Eclectic Consulting
            </span>
        </div>

        <h1 class="dp-headline">
            Everything<br>Eclectic<br>runs here.
        </h1>

        <p class="dp-sub">
            One intelligent workspace for every team, ticket, and timeline.
            Built exclusively for Eclectic Consulting.
        </p>

        <div class="dp-features">
            <div class="dp-feature">
                <div class="dp-icon">
                    <svg style="width:1rem;height:1rem;" fill="none" stroke="#fff" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                </div>
                <div>
                    <p style="font-size:.8125rem;color:#fff;font-weight:700;line-height:1.3;">Ticket and Support</p>
                    <p style="font-size:.6875rem;color:rgba(255,255,255,.6);margin-top:.2rem;line-height:1.5;">
                        Resolve every request. Full team visibility, zero missed tickets.
                    </p>
                </div>
            </div>
            <div class="dp-feature">
                <div class="dp-icon">
                    <svg style="width:1rem;height:1rem;" fill="none" stroke="#fff" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p style="font-size:.8125rem;color:#fff;font-weight:700;line-height:1.3;">Timesheet and Manday Tracking</p>
                    <p style="font-size:.6875rem;color:rgba(255,255,255,.6);margin-top:.2rem;line-height:1.5;">
                        Track time, monitor workloads, ship projects on schedule.
                    </p>
                </div>
            </div>
            <div class="dp-feature">
                <div class="dp-icon">
                    <svg style="width:1rem;height:1rem;" fill="none" stroke="#fff" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <div>
                    <p style="font-size:.8125rem;color:#fff;font-weight:700;line-height:1.3;">Reporting and Analytics</p>
                    <p style="font-size:.6875rem;color:rgba(255,255,255,.6);margin-top:.2rem;line-height:1.5;">
                        Live dashboards. Instant decisions. Leadership always in the loop.
                    </p>
                </div>
            </div>
        </div>

        <div class="dp-notice">
            <svg style="width:.8rem;height:.8rem;color:rgba(255,255,255,.4);flex-shrink:0;margin-top:.15rem;"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            <span style="font-size:.6875rem;color:rgba(255,255,255,.4);line-height:1.6;">
                Authorized personnel only. Need access? Contact your IT Administrator.
            </span>
        </div>
    </div>
</div>

{{-- ════════════════════════════════════════════════════════════
     RIGHT PANEL
════════════════════════════════════════════════════════════ --}}
<div id="right-panel"
     style="display:flex; flex-direction:column;
            background:#fff; height:100vh; overflow:hidden; position:relative;">

    {{-- Echo rings --}}
    <div style="position:absolute;bottom:-140px;right:-140px;width:420px;height:420px;border-radius:50%;
                border:1px solid rgba(160,0,0,.05);pointer-events:none;z-index:0;"></div>
    <div style="position:absolute;bottom:20%;left:-60px;width:200px;height:200px;border-radius:50%;
                border:1px solid rgba(160,0,0,.04);pointer-events:none;z-index:0;"></div>

    {{-- Row 1: Logo bar --}}
    <div style="flex-shrink:0; display:flex; justify-content:flex-end; align-items:center;
                padding:1.125rem 2.25rem; position:relative; z-index:1;">
        <img src="/images/eclectic_logo_nobg.png" alt="Eclectic Consulting"
             style="height:2.625rem; width:auto; display:block;
                    opacity:0; animation:fadeIn .5s ease forwards; animation-delay:.2s;">
    </div>

    {{-- Row 2: Content (centered) --}}
    <div style="flex:1; min-height:0; display:flex; align-items:center; justify-content:center;
                padding:.75rem 2.5rem; overflow:hidden; position:relative; z-index:1;">

        <div class="rp-inner" style="text-align:center;">

            {{-- ── Icon ──────────────────────────────────────────────── --}}
            <div class="email-icon-wrap">
                <div class="email-icon-ring2"></div>
                <div class="email-icon-ring"></div>
                <div class="email-icon-bg">
                    @if($type === 'reset')
                        <svg style="width:2rem;height:2rem;color:#A00000;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"
                                  d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    @else
                        <svg style="width:2rem;height:2rem;color:#A00000;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    @endif
                </div>
            </div>

            {{-- ── Heading ─────────────────────────────────────────────── --}}
            <div style="margin-bottom:1rem;
                        opacity:0;animation:fadeInUp .5s ease forwards;animation-delay:.5s;">
                <p style="font-size:.75rem;font-weight:600;color:#A00000;letter-spacing:.08em;
                           text-transform:uppercase;margin-bottom:.5rem;">
                    @if($type === 'reset') Password Reset @else Account Activation @endif
                </p>
                <h2 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.75rem;font-weight:800;
                           color:#0F172A;letter-spacing:-.04em;line-height:1.15;margin-bottom:.5rem;">
                    @if($type === 'reset') Email Sent @else Check Your Email @endif
                </h2>
                <p style="font-size:.875rem;color:#64748B;line-height:1.7;">
                    @if($type === 'reset') We sent a password reset link to @else We sent an activation email to @endif
                </p>
            </div>

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

            {{-- ── Instruction text ────────────────────────────────────── --}}
            <p style="font-size:.8125rem;color:#94A3B8;line-height:1.8;margin-bottom:0;
                      opacity:0;animation:fadeInUp .5s ease forwards;animation-delay:.64s;">
                Open the email and click&nbsp;<strong style="color:#475569;font-weight:600;">
                    @if($type === 'reset') "Reset My Password" @else "Set My Password" @endif
                </strong>&nbsp;to
                @if($type === 'reset') set a new password. @else activate your account. @endif
                <br>
                <span style="display:inline-flex;align-items:center;gap:.3rem;margin-top:.25rem;">
                    <svg style="width:.75rem;height:.75rem;color:#CBD5E1;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Link expires in <strong style="color:#64748B;">30 minutes</strong>
                </span>
            </p>

            {{-- ── Divider ─────────────────────────────────────────────── --}}
            <div class="rp-divider" style="opacity:0;animation:fadeIn .4s ease forwards;animation-delay:.7s;"></div>

            {{-- ── Tips card ───────────────────────────────────────────── --}}
            <div class="tips-card" style="opacity:0;animation:fadeInUp .5s ease forwards;animation-delay:.72s;">

                {{-- Card header --}}
                <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.5rem;
                             padding-bottom:.5rem;border-bottom:1px solid #E2E8F0;">
                    <div style="width:24px;height:24px;border-radius:7px;background:#FEF9C3;
                                border:1px solid #FDE68A;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg style="width:.7rem;height:.7rem;color:#D97706;" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <span style="font-size:.75rem;font-weight:700;color:#374151;letter-spacing:.01em;">
                        Didn't receive the email?
                    </span>
                </div>

                {{-- Tip row 1 --}}
                <div class="tips-row">
                    <div class="tips-icon-wrap" style="background:#EFF6FF;border:1px solid #BFDBFE;">
                        <svg style="width:.75rem;height:.75rem;color:#2563EB;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 11H5m0 0l7-7m-7 7l7 7"/>
                        </svg>
                    </div>
                    <div style="flex:1;">
                        <p style="font-size:.8125rem;color:#374151;line-height:1.5;font-weight:500;">
                            Check your <strong style="color:#1D4ED8;">Spam / Junk</strong> folder
                        </p>
                    </div>
                </div>

                {{-- Tip row 2 --}}
                <div class="tips-row">
                    <div class="tips-icon-wrap" style="background:#F0FDF4;border:1px solid #BBF7D0;">
                        <svg style="width:.75rem;height:.75rem;color:#16A34A;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div style="flex:1;">
                        <p style="font-size:.8125rem;color:#374151;line-height:1.5;font-weight:500;">
                            Make sure the email address is correct
                        </p>
                    </div>
                </div>

                {{-- Tip row 3 --}}
                @if($type === 'reset')
                <div class="tips-row">
                    <div class="tips-icon-wrap" style="background:#FEF2F2;border:1px solid #FECACA;">
                        <svg style="width:.75rem;height:.75rem;color:#DC2626;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                    </div>
                    <div style="flex:1;">
                        <a href="{{ route('password-setup.forgot') }}"
                           style="font-size:.8125rem;color:#A00000;font-weight:600;line-height:1.5;
                                  text-decoration:underline;text-underline-offset:2px;display:block;">
                            Resend password reset link
                        </a>
                    </div>
                </div>
                @else
                <div class="tips-row">
                    <div class="tips-icon-wrap" style="background:#FFF7ED;border:1px solid #FED7AA;">
                        <svg style="width:.75rem;height:.75rem;color:#EA580C;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </div>
                    <div style="flex:1;">
                        <p style="font-size:.8125rem;color:#374151;line-height:1.5;font-weight:500;">
                            Contact the helpdesk team
                        </p>
                    </div>
                </div>
                @endif
            </div>

            {{-- ── Back to login ───────────────────────────────────────── --}}
            <div style="margin-top:1rem;opacity:0;animation:fadeInUp .5s ease forwards;animation-delay:.8s;">
                <a href="{{ route('login') }}" class="back-link">
                    <svg style="width:.8125rem;height:.8125rem;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                              d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to login page
                </a>
            </div>

        </div>
    </div>

    {{-- Row 3: Copyright bar --}}
    <div style="flex-shrink:0; text-align:center; padding:.875rem 2rem 1rem; position:relative; z-index:1;
                opacity:0; animation:fadeIn .6s ease forwards; animation-delay:.85s;">
        <p style="font-size:.6875rem;color:#CBD5E1;line-height:1.5;">
            &copy; {{ date('Y') }} ECoSystem by Eclectic Consulting. All rights reserved.
        </p>
    </div>
</div>

</body>
</html>
