<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-data" content='@json(session("user"))'>
    <title>@yield('title', 'Dashboard') - EcoSystem</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    @php
        $preferences = session('user_preferences', [
            'theme' => 'light',
            'primary_color' => '#991b1b',
            'sidebar_style' => 'gradient',
            'font_size' => 'medium',
            'compact_mode' => false,
            'show_animations' => true,
        ]);
        
        // Convert hex to RGB for Tailwind
        $primaryColor = $preferences['primary_color'];
        $rgb = sscanf($primaryColor, "#%02x%02x%02x");
        $primaryRgb = implode(', ', $rgb);
        
        // Calculate darker shade
        $darkR = max(0, $rgb[0] - 40);
        $darkG = max(0, $rgb[1] - 40);
        $darkB = max(0, $rgb[2] - 40);
        $primaryDarkRgb = "$darkR, $darkG, $darkB";
        
        // Font sizes
        $fontSizes = [
            'small' => '14px',
            'medium' => '16px',
            'large' => '18px'
        ];
        $baseFontSize = $fontSizes[$preferences['font_size']];
        
        // Theme colors
        $bgColor = $preferences['theme'] === 'dark' ? '#111827' : '#f9fafb';
        $textColor = $preferences['theme'] === 'dark' ? '#f9fafb' : '#111827';
        $cardBg = $preferences['theme'] === 'dark' ? '#1f2937' : '#ffffff';
        
        // Get user from session
        $user = session('user', []);
        $userRoleId   = $user['role']['id']   ?? 1;
        $userRoleName = $user['role']['name'] ?? '';

        // $permSlugs dan $can di-share oleh ShareMenuPermissions middleware
        $permSlugs = $permSlugs ?? [];
        $can       = $can       ?? fn(string $slug) => in_array($slug, $permSlugs);

        // Backward compat variables (dipakai di beberapa tempat lain di view ini)
        $showAllMenus     = $can('management');
        $showMasterMenu   = $can('master');
        $showRpmoMenu     = $can('rpmo');
        $showSlaMenu      = $can('sla');
        $showLimitedMenus = false; // tidak dipakai lagi
        $canManageSla     = $can('sla.config');
    @endphp
    
    <style>
        * { 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; 
        }
        
        :root {
            --primary-color: {{ $primaryColor }};
            --primary-rgb: {{ $primaryRgb }};
            --primary-dark-rgb: {{ $primaryDarkRgb }};
            --font-size-base: {{ $baseFontSize }};
            --bg-color: {{ $bgColor }};
            --text-color: {{ $textColor }};
            --card-bg: {{ $cardBg }};
            --scrollbar-track: {{ $preferences['theme'] === 'dark' ? '#111827' : '#f1f1f1' }};
        }
        
        body {
            font-size: var(--font-size-base);
            background-color: var(--bg-color) !important;
            color: var(--text-color) !important;
        }
        
        .sidebar-transition { 
            transition: all {{ $preferences['show_animations'] ? '0.3s' : '0s' }} cubic-bezier(0.4, 0, 0.2, 1); 
        }
        
        .primary-bg {
            background-color: var(--primary-color) !important;
        }
        
        .primary-text {
            color: var(--primary-color) !important;
        }
        
        .primary-border {
            border-color: var(--primary-color) !important;
        }
        
        .primary-hover:hover {
            background-color: var(--primary-color) !important;
        }
        
        .primary-gradient {
            background: linear-gradient(135deg, 
                rgb(var(--primary-dark-rgb)), 
                rgb(var(--primary-rgb))) !important;
        }
        
        .primary-solid {
            background-color: rgb(var(--primary-rgb)) !important;
        }
        
        /* Smooth scrollbar */
        ::-webkit-scrollbar {
            width: 4px;
            height: 4px;
        }

        ::-webkit-scrollbar-track {
            background: var(--scrollbar-track, #f1f1f1);
            border-radius: 10px;
        }

        /* Firefox: tipis + thumb = accent, track = theme */
        * { scrollbar-width: thin; scrollbar-color: rgb(var(--primary-rgb)) var(--scrollbar-track, #f1f1f1); }

        /* Thumb mengikuti warna sidebar/accent (var --primary-*), bukan merah tetap.
           Gradient meniru sidebar `.primary-gradient`; ganti warna Accent di Settings
           akan otomatis mengubah warna scrollbar. Border = warna track (light/dark). */
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, rgb(var(--primary-dark-rgb)), rgb(var(--primary-rgb)));
            border-radius: 10px;
            border: 1px solid var(--scrollbar-track, #f1f1f1);
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgb(var(--primary-rgb));
        }
        
        /* Navbar animation */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-10px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .nav-link {
            animation: slideIn 0.3s ease-out;
        }
        
        /* Hover effects */
        .nav-link:hover {
            transform: translateX(4px);
        }
        
        .nav-link.active {
            box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
        }
        
        @if($preferences['compact_mode'])
        .p-6 { padding: 1rem !important; }
        .p-8 { padding: 1.5rem !important; }
        .gap-6 { gap: 1rem !important; }
        .space-y-6 > * + * { margin-top: 1rem !important; }
        @endif
        
        @if($preferences['theme'] === 'dark')
        /* ── Dark mode ────────────────────────────────────────────────────────
           Palet permukaan (surface) berlapis agar tidak "belang":
             page   #0b1120  — latar terjauh (bg-gray-50)
             raised #1f2937  — kartu / panel (bg-white)
             sunken #111827  — area tenggelam (bg-gray-100/200)
           Warna teks, border, divider, hover, placeholder, shadow, dan latar
           bertint merah muda semuanya dipetakan ulang. Elemen ber-brand
           (primary-*, gradient sidebar) sengaja TIDAK diubah. */

        /* Surfaces */
        .bg-white            { background-color: #1f2937 !important; }
        .bg-gray-50          { background-color: #0b1120 !important; }
        .bg-gray-100,
        .bg-gray-200         { background-color: #111827 !important; }
        .bg-gray-800,
        .bg-gray-900         { background-color: #030712 !important; }
        /* Latar semi-transparan yang umum dipakai untuk header/kartu lembut */
        .bg-gray-50\/50,
        .bg-gray-50\/60,
        .bg-white\/10        { background-color: rgba(255,255,255,.04) !important; }

        /* Latar bertint merah (badge/active state ringan) → merah gelap lembut */
        .bg-red-50,
        .bg-red-50\/50,
        .bg-red-100          { background-color: rgba(153,27,27,.22) !important; }

        /* Pastel -50/-100 lain (Quick Navigation, badge status/role, chip ikon)
           → tint gelap sesuai hue agar tak menyilaukan di atas latar gelap. */
        .bg-blue-50    { background-color: rgba(59,130,246,.14) !important; }
        .bg-sky-50     { background-color: rgba(14,165,233,.14) !important; }
        .bg-indigo-50  { background-color: rgba(99,102,241,.14) !important; }
        .bg-green-50   { background-color: rgba(34,197,94,.14) !important; }
        .bg-emerald-50 { background-color: rgba(16,185,129,.14) !important; }
        .bg-teal-50    { background-color: rgba(20,184,166,.14) !important; }
        .bg-amber-50   { background-color: rgba(245,158,11,.14) !important; }
        .bg-yellow-50  { background-color: rgba(234,179,8,.14) !important; }
        .bg-orange-50  { background-color: rgba(249,115,22,.14) !important; }
        .bg-purple-50  { background-color: rgba(168,85,247,.14) !important; }
        .bg-pink-50    { background-color: rgba(236,72,153,.14) !important; }
        .bg-blue-100   { background-color: rgba(59,130,246,.20) !important; }
        .bg-sky-100    { background-color: rgba(14,165,233,.20) !important; }
        .bg-indigo-100 { background-color: rgba(99,102,241,.20) !important; }
        .bg-green-100  { background-color: rgba(34,197,94,.20) !important; }
        .bg-emerald-100{ background-color: rgba(16,185,129,.20) !important; }
        .bg-amber-100  { background-color: rgba(245,158,11,.20) !important; }
        .bg-yellow-100 { background-color: rgba(234,179,8,.20) !important; }
        .bg-orange-100 { background-color: rgba(249,115,22,.20) !important; }
        .bg-purple-100 { background-color: rgba(168,85,247,.20) !important; }
        /* Shade -200 (mis. chip WEIGHT % `bg-purple-200 text-purple-800` di tabel
           Planning) → tint gelap agar teks (yg dicerahkan) terbaca. */
        .bg-blue-200   { background-color: rgba(59,130,246,.26) !important; }
        .bg-sky-200    { background-color: rgba(14,165,233,.26) !important; }
        .bg-indigo-200 { background-color: rgba(99,102,241,.26) !important; }
        .bg-green-200  { background-color: rgba(34,197,94,.26) !important; }
        .bg-emerald-200{ background-color: rgba(16,185,129,.26) !important; }
        .bg-teal-200   { background-color: rgba(20,184,166,.26) !important; }
        .bg-amber-200  { background-color: rgba(245,158,11,.26) !important; }
        .bg-yellow-200 { background-color: rgba(234,179,8,.26) !important; }
        .bg-orange-200 { background-color: rgba(249,115,22,.26) !important; }
        .bg-purple-200 { background-color: rgba(168,85,247,.26) !important; }
        .bg-pink-200   { background-color: rgba(236,72,153,.26) !important; }
        .bg-red-200    { background-color: rgba(153,27,27,.30) !important; }

        /* Varian OPACITY pastel (mis. `bg-orange-50/40`, `bg-blue-50/40` di kolom
           Plan Cost) — class berbeda dari `bg-orange-50`, ditangkap via atribut. */
        [class*="bg-blue-50/"]    { background-color: rgba(59,130,246,.15) !important; }
        [class*="bg-sky-50/"]     { background-color: rgba(14,165,233,.15) !important; }
        [class*="bg-indigo-50/"]  { background-color: rgba(99,102,241,.15) !important; }
        [class*="bg-green-50/"]   { background-color: rgba(34,197,94,.15) !important; }
        [class*="bg-emerald-50/"] { background-color: rgba(16,185,129,.15) !important; }
        [class*="bg-orange-50/"]  { background-color: rgba(249,115,22,.15) !important; }
        [class*="bg-amber-50/"]   { background-color: rgba(245,158,11,.15) !important; }
        [class*="bg-yellow-50/"]  { background-color: rgba(234,179,8,.15) !important; }
        [class*="bg-purple-50/"]  { background-color: rgba(168,85,247,.15) !important; }
        [class*="bg-red-50/"]     { background-color: rgba(153,27,27,.20) !important; }

        /* Teks warna gelap (badge `-700/-800`) → dicerahkan agar terbaca di gelap.
           Ikon `-600` sudah cukup jenuh, dibiarkan. */
        .text-blue-700,   .text-blue-800   { color: #93c5fd !important; }
        .text-sky-700,    .text-sky-800    { color: #7dd3fc !important; }
        .text-indigo-700, .text-indigo-800 { color: #a5b4fc !important; }
        .text-green-700,  .text-green-800  { color: #86efac !important; }
        .text-emerald-700,.text-emerald-800{ color: #6ee7b7 !important; }
        .text-teal-600,   .text-teal-700,   .text-teal-800   { color: #5eead4 !important; }
        .text-cyan-700,   .text-cyan-800   { color: #67e8f9 !important; }
        .text-amber-700,  .text-amber-800  { color: #fcd34d !important; }
        .text-yellow-700, .text-yellow-800 { color: #fde047 !important; }
        .text-orange-700, .text-orange-800 { color: #fdba74 !important; }
        .text-purple-700, .text-purple-800 { color: #d8b4fe !important; }
        .text-red-700,    .text-red-800    { color: #fca5a5 !important; }
        /* Shade -900 (teks paling gelap, dipakai di chip `bg-*-200 text-*-900`) */
        .text-blue-900   { color: #93c5fd !important; }
        .text-sky-900    { color: #7dd3fc !important; }
        .text-indigo-900 { color: #a5b4fc !important; }
        .text-green-900  { color: #86efac !important; }
        .text-emerald-900{ color: #6ee7b7 !important; }
        .text-teal-900   { color: #5eead4 !important; }
        .text-amber-900  { color: #fcd34d !important; }
        .text-yellow-900 { color: #fde047 !important; }
        .text-orange-900 { color: #fdba74 !important; }
        .text-purple-900 { color: #d8b4fe !important; }
        .text-red-900    { color: #fca5a5 !important; }

        /* Aksen merah brand (.primary-text = var(--primary-color) #991b1b) terlalu
           gelap di latar gelap → dicerahkan. `body` agar menang atas aturan halaman
           `.primary-text{color:var(--primary-color)!important}`. */
        body .primary-text { color: #f87171 !important; }

        /* Badge SOLID terang (mis. `bg-yellow-400 text-gray-900` = badge count di
           sidebar) — latarnya tetap terang, jadi teks HARUS tetap gelap; jangan
           ikut dicerahkan seperti `.text-gray-900` biasa. Compound (0,2,0) menang. */
        .bg-yellow-300.text-gray-900, .bg-yellow-400.text-gray-900, .bg-yellow-500.text-gray-900,
        .bg-amber-300.text-gray-900,  .bg-amber-400.text-gray-900,  .bg-amber-500.text-gray-900,
        .bg-lime-400.text-gray-900,   .bg-green-400.text-gray-900,   .bg-orange-400.text-gray-900,
        .bg-yellow-400.text-gray-800, .bg-amber-400.text-gray-800 { color: #111827 !important; }

        /* Text */
        .text-gray-900,
        .text-black          { color: #f3f4f6 !important; }
        .text-gray-800       { color: #e5e7eb !important; }
        .text-gray-700       { color: #d1d5db !important; }
        .text-gray-600       { color: #cbd1d9 !important; }
        .text-gray-500       { color: #9ca3af !important; }
        .text-gray-400       { color: #8b93a1 !important; }
        .text-gray-300       { color: #6b7280 !important; }

        /* Borders & dividers */
        .border-gray-100     { border-color: #1f2937 !important; }
        .border-gray-200     { border-color: #374151 !important; }
        .border-gray-300     { border-color: #4b5563 !important; }
        .divide-gray-100 > :not([hidden]) ~ :not([hidden]) { border-color: #1f2937 !important; }
        .divide-gray-200 > :not([hidden]) ~ :not([hidden]) { border-color: #374151 !important; }

        /* ── Hover states ──────────────────────────────────────────────────
           Samakan SEMUA hover latar terang ke #374151 (seperti Master Employee),
           termasuk varian opacity (mis. `hover:bg-gray-50/80`) & shade lain.
           Selektor atribut menangkap semua varian; `!important` menang atas
           utilitas hover Tailwind (yang non-important). */
        [class*="hover:bg-gray-"]:hover,
        [class*="hover:bg-slate-"]:hover,
        [class*="hover:bg-zinc-"]:hover,
        [class*="hover:bg-neutral-"]:hover,
        [class*="hover:bg-stone-"]:hover,
        [class*="hover:bg-white"]:hover { background-color: #374151 !important; }

        /* Hover pastel berwarna (khusus shade -50/-100 via ~= agar TIDAK kena
           tombol solid -500/-600/-700) → tint gelap sesuai hue, tetap subtle. */
        [class~="hover:bg-blue-50"]:hover,   [class~="hover:bg-blue-100"]:hover   { background-color: rgba(59,130,246,.22) !important; }
        [class~="hover:bg-sky-50"]:hover,    [class~="hover:bg-sky-100"]:hover    { background-color: rgba(14,165,233,.22) !important; }
        [class~="hover:bg-indigo-50"]:hover, [class~="hover:bg-indigo-100"]:hover { background-color: rgba(99,102,241,.22) !important; }
        [class~="hover:bg-green-50"]:hover,  [class~="hover:bg-green-100"]:hover  { background-color: rgba(34,197,94,.22) !important; }
        [class~="hover:bg-emerald-50"]:hover,[class~="hover:bg-emerald-100"]:hover{ background-color: rgba(16,185,129,.22) !important; }
        [class~="hover:bg-teal-50"]:hover,   [class~="hover:bg-teal-100"]:hover   { background-color: rgba(20,184,166,.22) !important; }
        [class~="hover:bg-amber-50"]:hover,  [class~="hover:bg-amber-100"]:hover  { background-color: rgba(245,158,11,.22) !important; }
        [class~="hover:bg-yellow-50"]:hover, [class~="hover:bg-yellow-100"]:hover { background-color: rgba(234,179,8,.22) !important; }
        [class~="hover:bg-orange-50"]:hover, [class~="hover:bg-orange-100"]:hover { background-color: rgba(249,115,22,.22) !important; }
        [class~="hover:bg-purple-50"]:hover, [class~="hover:bg-purple-100"]:hover { background-color: rgba(168,85,247,.22) !important; }
        [class~="hover:bg-pink-50"]:hover,   [class~="hover:bg-pink-100"]:hover   { background-color: rgba(236,72,153,.22) !important; }
        [class~="hover:bg-red-50"]:hover,    [class~="hover:bg-red-100"]:hover    { background-color: rgba(153,27,27,.30) !important; }

        /* Chip ikon dengan group-hover (mis. Quick Navigation) — samakan pola */
        .group:hover [class*="group-hover:bg-gray-"],
        .group:hover [class*="group-hover:bg-slate-"] { background-color: #374151 !important; }
        .group:hover [class~="group-hover:bg-blue-100"]    { background-color: rgba(59,130,246,.26) !important; }
        .group:hover [class~="group-hover:bg-sky-100"]     { background-color: rgba(14,165,233,.26) !important; }
        .group:hover [class~="group-hover:bg-indigo-100"]  { background-color: rgba(99,102,241,.26) !important; }
        .group:hover [class~="group-hover:bg-green-100"]   { background-color: rgba(34,197,94,.26) !important; }
        .group:hover [class~="group-hover:bg-emerald-100"] { background-color: rgba(16,185,129,.26) !important; }
        .group:hover [class~="group-hover:bg-amber-100"]   { background-color: rgba(245,158,11,.26) !important; }
        .group:hover [class~="group-hover:bg-purple-100"]  { background-color: rgba(168,85,247,.26) !important; }
        .group:hover [class~="group-hover:bg-red-100"]     { background-color: rgba(153,27,27,.34) !important; }

        /* Sidebar = permukaan ber-ACCENT (bukan gelap). Nav pakai overlay putih
           transparan (active `bg-white/opacity-15`, hover `hover:bg-white/opacity-10`).
           Kembalikan overlay itu agar tidak diubah jadi abu/gelap oleh aturan generik
           di atas — `#sidebar` (ID) menang spesifisitas. */
        #sidebar .bg-white { background-color: rgba(255,255,255,0.15) !important; }
        #sidebar [class*="hover:bg-white"]:hover { background-color: rgba(255,255,255,0.10) !important; }

        /* Form controls */
        input, select, textarea {
            background-color: #374151 !important;
            color: #f9fafb !important;
            border-color: #4b5563 !important;
        }
        input:read-only,
        input:disabled, select:disabled, textarea:disabled {
            background-color: #1f2937 !important;
            color: #9ca3af !important;
        }
        input::placeholder, textarea::placeholder { color: #6b7280 !important; }

        /* Shadow lebih dalam agar kartu tetap terbaca di atas latar gelap */
        .shadow-sm, .shadow, .shadow-md, .shadow-lg, .shadow-xl, .shadow-2xl {
            box-shadow: 0 4px 16px rgba(0,0,0,.45) !important;
        }

        /* Scrollbar: warna thumb & track sudah theme-aware via var --primary-*
           dan --scrollbar-track (didefinisikan di :root), jadi otomatis mengikuti
           sidebar/accent + tema. Tidak perlu override di sini. */

        /* ── Komponen custom yang memakai putih hardcoded ────────────────────
           Elemen berikut TIDAK memakai class `bg-white` (mereka distyle lewat
           CSS mentah / JS), jadi override class di atas tak menjangkaunya.
           Dipetakan ulang di sini agar ikut tema. */

        /* Native <select> option list */
        option { background-color: #1f2937 !important; color: #e5e7eb !important; }

        /* Enhanced <select> — public/js/select-enhance.js (.se-*) */
        .se-btn            { background: #374151 !important; border-color: #4b5563 !important; color: #e5e7eb !important; }
        .se-btn:hover      { border-color: #6b7280 !important; }
        .se-btn[disabled]  { background: #1f2937 !important; }
        .se-label          { color: #e5e7eb !important; }
        .se-label.is-placeholder { color: #6b7280 !important; }
        .se-panel          { background: #1f2937 !important; border-color: #374151 !important;
                             box-shadow: 0 20px 40px rgba(0,0,0,.55) !important; }
        .se-item           { color: #d1d5db !important; }
        .se-item:hover,
        .se-item.is-active { background: #374151 !important; color: #f9fafb !important; }
        .se-item.is-disabled { color: #4b5563 !important; }
        .se-search-head    { background: #1f2937 !important; border-color: #374151 !important; }

        /* Custom dropdown — public/js/custom-dropdown.js (.custom-dd-*) */
        .custom-dd-panel   { background: #1f2937 !important; border-color: #374151 !important; }
        .custom-dd-btn     { background: #374151 !important; border-color: #4b5563 !important; color: #e5e7eb !important; }
        /* Filter kolom di HEADER tabel juga memakai `.custom-dd-btn`, tapi di sana
           harus TRANSPARAN mengikuti latar header (bg-gray-50 → #0b1120) — jika
           tidak, sel header berdropdown (Customer…Type) tampak lebih terang &
           belang dibanding sel header lain. Hover tetap ditangani utilitas
           `hover:bg-gray-*`. */
        thead .custom-dd-btn { background: transparent !important; }
        .custom-dd-item    { color: #d1d5db !important; }
        .custom-dd-item:hover,
        .custom-dd-item.is-active,
        .custom-dd-item.selected { background: #374151 !important; color: #f9fafb !important; }

        /* Reporting → MD Recap (baris grup/anak pakai #f9fafb / #fff mentah) */
        .recap-emp-row td       { background: #111827 !important; }
        .recap-emp-row:hover td { background: #0b1120 !important; }
        .recap-sub-row td       { background: #1f2937 !important; }
        .recap-sub-row:hover td { background: #374151 !important; }

        /* Reporting → Collection Outlook (.co-*) */
        .co-select { background: #374151 !important; border-color: #4b5563 !important; color: #e5e7eb !important; }
        .co-th     { background: #111827 !important; color: #9ca3af !important;
                     border-bottom-color: #374151 !important; border-right-color: #374151 !important; }
        .co-td     { border-bottom-color: #374151 !important; border-right-color: #374151 !important; color: #d1d5db !important; }
        thead .co-sticky-cust, thead .co-sticky-proj, thead .co-sticky-top { background: #0b1120 !important; }
        tbody .co-sticky-cust, tbody .co-sticky-proj, tbody .co-sticky-top { background: #111827 !important; }
        tbody tr:hover .co-sticky-cust,
        tbody tr:hover .co-sticky-proj,
        tbody tr:hover .co-sticky-top { background: #1f2937 !important; }
        .co-amount-btn:hover { background: #374151 !important; }
        /* Nilai amount di sel — dicerahkan agar terbaca di latar gelap */
        .co-open   { color: #cbd5e1 !important; }
        .co-paid   { color: #4ade80 !important; }
        .co-delay  { color: #fbbf24 !important; }
        .co-paid:hover  { background: rgba(34,197,94,.18) !important; }
        .co-delay:hover { background: rgba(245,158,11,.18) !important; }

        /* Delivery → Project Detail: sticky section nav + tab hover/active
           (#sectionNav putih translucent; .section-tab hover #f9fafb). */
        #sectionNav { background: rgba(17,24,39,.92) !important; border-bottom-color: #374151 !important; }
        .section-tab:hover { background-color: #374151 !important; color: #e5e7eb !important; }
        .section-tab.active { color: #f87171 !important; }

        /* Delivery → Phase Management: redupkan gradient pekat (header indigo, bar
           amber, bar biru) agar tidak menyilaukan. Scoped ke `.phase-mgmt-page`
           supaya halaman lain tak terpengaruh. */
        .phase-mgmt-page .from-blue-600.to-indigo-600  { background-image: linear-gradient(to right, #21375a, #2b295c) !important; }
        .phase-mgmt-page .from-amber-400.to-orange-500 { background-image: linear-gradient(to right, #a16207, #b45309) !important; }
        .phase-mgmt-page .from-blue-500.to-indigo-600  { background-image: linear-gradient(to right, #1e40af, #3730a3) !important; }
        /* Track bar */
        .phase-mgmt-page .bg-amber-100 { background-color: rgba(245,158,11,.16) !important; }
        .phase-mgmt-page .bg-gray-200  { background-color: #374151 !important; }

        /* Delivery → Gantt chart: banyak warna terang hardcoded (panel putih, header,
           sel hari putih, kolom weekend cream #fef3c7, border terang). Dipetakan
           ulang agar menyatu dengan tema gelap. Bar tugas (warna dinamis) & garis
           "Today" merah dibiarkan. */
        .gantt-wrapper { background-color: #1f2937 !important; }
        .gantt-sidebar { background-color: #111827 !important; border-right-color: #374151 !important; }
        .gantt-sidebar-header,
        .gantt-timeline-header { background-color: #111827 !important; border-bottom-color: #374151 !important; }
        .gantt-header-col { color: #cbd5e1 !important; }
        .gantt-week-cell { background-color: #111827 !important; border-right-color: #374151 !important; color: #9ca3af !important; }
        .gantt-week-row { border-bottom-color: #374151 !important; }
        .gantt-day-cell { background-color: #1f2937 !important; border-right-color: #374151 !important; color: #cbd5e1 !important; }
        .gantt-day-cell.weekend,
        .gantt-grid-cell.weekend { background-color: rgba(245,158,11,.10) !important; }
        .gantt-day-cell.today { background-color: rgba(59,130,246,.22) !important; color: #93c5fd !important; }
        .gantt-phase-row,
        .gantt-timeline-row.gantt-phase-bg { background-color: #111827 !important; border-bottom-color: #374151 !important; }
        .gantt-activity-row { border-bottom-color: #374151 !important; }
        .gantt-activity-row:hover { background-color: #374151 !important; }
        .gantt-grid-cell { border-right-color: #374151 !important; }
        .gantt-toggle-button:hover { background-color: rgba(255,255,255,.08) !important; }
        .gantt-timeline::-webkit-scrollbar-track,
        .gantt-sidebar-body::-webkit-scrollbar-track { background: #111827 !important; }
        .gantt-timeline::-webkit-scrollbar-thumb,
        .gantt-sidebar-body::-webkit-scrollbar-thumb { background: #4b5563 !important; }

        /* Baris tabel ber-gradasi TERANG (mis. Project Planning phase/group rows,
           header banner from-*-50/100) → diratakan jadi permukaan gelap. Gradasi
           gelap/pekat (from-blue-600, from-*-500, dsb.) sengaja dibiarkan. */
        .from-gray-50, .from-gray-100, .from-blue-50, .from-indigo-50, .from-purple-50,
        .from-indigo-100, .from-purple-100, .from-amber-50, .from-orange-50 {
            --tw-gradient-from: #1f2937 !important;
            --tw-gradient-to:   #1f2937 !important;
            --tw-gradient-stops: #1f2937, #1f2937 !important;
        }
        .to-gray-50, .to-gray-100, .to-blue-50, .to-indigo-50, .to-purple-50,
        .to-indigo-100, .to-purple-100, .to-orange-50 {
            --tw-gradient-to: #1f2937 !important;
            --tw-gradient-stops: var(--tw-gradient-from), #1f2937 !important;
        }
        .hover\:from-purple-100:hover, .hover\:to-indigo-100:hover {
            --tw-gradient-from: #374151 !important;
            --tw-gradient-to:   #374151 !important;
            --tw-gradient-stops: #374151, #374151 !important;
        }

        /* Master → Employee/Customer list: hover baris (#fef2f2 pink !important) */
        body .employee-row:hover, body .customer-row:hover { background-color: #374151 !important; }

        /* Master → Employee/Customer detail: field read-only (.profile-readonly).
           Prefiks `body` menaikkan spesifisitas agar menang atas aturan halaman
           yang memakai !important dengan spesifisitas sama (CSS halaman dimuat
           belakangan lewat stack styles di head). */
        body .profile-readonly input,
        body .profile-readonly textarea,
        body .profile-readonly select,
        body .profile-readonly .se-btn {
            background: #111827 !important; color: #9ca3af !important; border-color: #374151 !important;
        }

        /* Ticket list (Support Tickets) — toggle aktif, stat-card aktif, baris unread */
        #btnViewAll.active, #btnViewMy.active, #btnViewAllHd.active, #btnViewUnassigned.active {
            background: #374151 !important; color: #f9fafb !important;
        }
        body .stat-card.active-filter {
            background: rgba(220,38,38,.16) !important;
            border-top-color: #7f1d1d !important; border-right-color: #7f1d1d !important; border-bottom-color: #7f1d1d !important;
        }
        #ticketsListBody tr:hover { background: #374151 !important; }
        /* Dua kolom sticky (Last Update & Ticket#) memakai background PUTIH inline
           (style="background:#ffffff") pada tiap baris — timpa jadi gelap. Baris
           unread punya selector lebih spesifik di bawah sehingga tetap menang.
           PENTING: kolom sticky di-FREEZE dan mengambang di atas kolom lain yang
           di-scroll horizontal, jadi latarnya WAJIB OPAQUE — jika translucent
           (rgba), konten kolom di belakang akan tembus. Tint unread karena itu
           dipakai sebagai warna SOLID hasil blend di atas permukaan #1f2937,
           bukan rgba. */
        #ticketsListBody tr td:first-child,
        #ticketsListBody tr td:nth-child(2) { background: #1f2937 !important; }
        #ticketsListBody tr:hover td:first-child,
        #ticketsListBody tr:hover td:nth-child(2) { background: #374151 !important; }
        #ticketsListBody tr.ticket-unread-customer,
        #ticketsListBody tr.ticket-unread-customer td:first-child,
        #ticketsListBody tr.ticket-unread-customer td:nth-child(2) { background: #233550 !important; }
        #ticketsListBody tr.ticket-unread-customer:hover,
        #ticketsListBody tr.ticket-unread-customer:hover td:first-child,
        #ticketsListBody tr.ticket-unread-customer:hover td:nth-child(2) { background: #253d61 !important; }
        #ticketsListBody tr.ticket-unread-internal,
        #ticketsListBody tr.ticket-unread-internal td:first-child,
        #ticketsListBody tr.ticket-unread-internal td:nth-child(2) { background: #393b35 !important; }
        #ticketsListBody tr.ticket-unread-internal:hover,
        #ticketsListBody tr.ticket-unread-internal:hover td:first-child,
        #ticketsListBody tr.ticket-unread-internal:hover td:nth-child(2) { background: #4b4733 !important; }

        /* Calendar → Timesheet: stat cards (Total/Draft/Submitted/Approved/Rejected).
           Semua pakai `bg-white`, tapi ditegaskan lewat ID (spesifisitas tertinggi)
           agar dijamin gelap meski ada override lain. */
        #cardAll, #cardDraft, #cardSubmitted, #cardApproved, #cardRejected { background-color: #1f2937 !important; }

        /* SLA → SLA Report: kolom sticky putih (.sc #ffffff), group header band
           (.grp-info/resp/res), badge SLA (.sla-badge-*) — semua warna mentah. */
        .sla-table tbody .sc                     { background: #1f2937 !important; }
        .sla-table tbody tr:hover .sc            { background: #374151 !important; }
        .sla-table tbody tr.row-pending .sc      { background: rgba(245,158,11,.12) !important; }
        .sla-table tbody tr.row-pending:hover .sc{ background: rgba(245,158,11,.20) !important; }
        .grp-info { background: #111827 !important; }
        .grp-resp { background: rgba(59,130,246,.10) !important; }
        .grp-res  { background: rgba(34,197,94,.10) !important; }
        .sla-badge-met      { background: rgba(34,197,94,.18) !important; color: #86efac !important; }
        .sla-badge-breached { background: rgba(153,27,27,.30) !important; color: #fca5a5 !important; }
        .sla-badge-pending  { background: rgba(59,130,246,.18) !important; color: #93c5fd !important; }
        .sla-badge-paused   { background: rgba(245,158,11,.18) !important; color: #fcd34d !important; }
        .sla-badge-pv       { background: #374151 !important; color: #cbd5e1 !important; }
        .sla-check-none     { color: #6b7280 !important; }
        /* Header sticky tabel SLA + tabel kedua (bg-gray-50/80) */
        .sla-table thead th          { background-color: #111827 !important; }
        .sla-table thead .grp-resp   { background: rgba(59,130,246,.10) !important; }
        .sla-table thead .grp-res    { background: rgba(34,197,94,.10) !important; }
        .bg-gray-50\/70, .bg-gray-50\/80, .bg-gray-50\/90 { background-color: #111827 !important; }
        @endif
        
        /* ── Global Form Input Reset ─────────────────────────────────────────
           Tailwind v4 preflight sets border-width:0 and no padding on all
           elements. Restore a consistent, comfortable appearance site-wide.
           Using :where() keeps specificity at (0,0,0) so any Tailwind utility
           class or inline style can still override without needing !important. */
        :where(input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):not([type="file"]):not([type="range"])),
        :where(select),
        :where(textarea) {
            border-width: 1px;
            border-style: solid;
            padding: 0.5rem 0.75rem;   /* py-2 px-3 — comfortable touch target */
            line-height: 1.5rem;
            border-radius: 0.375rem;   /* rounded-md */
        }
        :where(textarea) {
            padding: 0.625rem 0.75rem; /* slightly taller for multiline */
            line-height: 1.625rem;
        }

        /* primary-focus — consistent focus ring matching brand colour */
        .primary-focus:focus,
        .primary-focus:focus-visible {
            outline: none;
            border-color: rgb(var(--primary-rgb));
            box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15);
        }

        /* ── Responsive form grids ───────────────────────────────────────────
           Master data section forms use a dense `grid-cols-6` layout for
           desktop. Add `form-grid` alongside it: below the lg breakpoint the
           column count steps down and every field spans a single cell, so the
           mixed col-span-1/2/3/4/6 children never overflow or misalign.
           At >= 1024px the original grid-cols-6 + col-span-* rules apply. */
        @media (max-width: 1023px) {
            .form-grid > * { grid-column: auto !important; }
        }
        @media (min-width: 768px) and (max-width: 1023px) {
            .form-grid { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; }
        }
        @media (min-width: 480px) and (max-width: 767px) {
            .form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
        }
        @media (max-width: 479px) {
            .form-grid { grid-template-columns: minmax(0, 1fr) !important; }
        }

        /* Card hover effect */
        .card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
        }
    </style>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '{{ $primaryColor }}',
                        'primary-dark': 'rgb({{ $primaryDarkRgb }})',
                    }
                }
            }
        }
    </script>
    <style>
        #toast-container {
            position: fixed; top: 1.5rem; right: 1.5rem; z-index: 9999;
            display: flex; flex-direction: column; gap: 0.75rem;
            max-width: 22rem; width: 100%; pointer-events: none;
        }
        .toast {
            pointer-events: all; border-radius: 0.875rem;
            padding: 1rem 1rem 0 1rem; display: flex; flex-direction: column;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15); overflow: hidden;
            transform: translateX(110%); opacity: 0;
            transition: transform 0.4s cubic-bezier(0.34,1.56,0.64,1), opacity 0.3s ease;
        }
        .toast.show { transform: translateX(0); opacity: 1; }
        .toast.hide { transform: translateX(110%); opacity: 0; transition: transform 0.35s ease-in, opacity 0.3s ease-in; }
        .toast-body { display: flex; align-items: flex-start; gap: 0.75rem; padding-bottom: 0.875rem; }
        .toast-icon { flex-shrink: 0; width: 2rem; height: 2rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .toast-content { flex: 1; min-width: 0; }
        .toast-title { font-size: 0.8125rem; font-weight: 700; line-height: 1.2; }
        .toast-message { font-size: 0.8125rem; margin-top: 0.2rem; line-height: 1.4; }
        .toast-close { flex-shrink: 0; background: none; border: none; cursor: pointer; padding: 0.1rem; border-radius: 0.375rem; opacity: 0.5; transition: opacity 0.2s; line-height: 1; }
        .toast-close:hover { opacity: 1; }
        .toast-progress { height: 3px; border-radius: 0 0 0.875rem 0.875rem; margin: 0 -1rem; transform-origin: left; animation: toast-progress-shrink linear forwards; }
        @keyframes toast-progress-shrink { from { transform: scaleX(1); } to { transform: scaleX(0); } }
        .toast-success { background: #f0fdf4; border: 1.5px solid #86efac; }
        .toast-success .toast-icon { background: #dcfce7; }
        .toast-success .toast-icon svg { color: #16a34a; }
        .toast-success .toast-title { color: #14532d; }
        .toast-success .toast-message { color: #15803d; }
        .toast-success .toast-close { color: #14532d; }
        .toast-success .toast-progress { background: #22c55e; }
        .toast-error { background: #fff1f1; border: 1.5px solid #fca5a5; }
        .toast-error .toast-icon { background: #fee2e2; }
        .toast-error .toast-icon svg { color: #dc2626; }
        .toast-error .toast-title { color: #991b1b; }
        .toast-error .toast-message { color: #b91c1c; }
        .toast-error .toast-close { color: #991b1b; }
        .toast-error .toast-progress { background: #ef4444; }
        .toast-warning { background: #fffbeb; border: 1.5px solid #fcd34d; }
        .toast-warning .toast-icon { background: #fef9c3; }
        .toast-warning .toast-icon svg { color: #d97706; }
        .toast-warning .toast-title { color: #78350f; }
        .toast-warning .toast-message { color: #92400e; }
        .toast-warning .toast-close { color: #78350f; }
        .toast-warning .toast-progress { background: #f59e0b; }
        .toast-info { background: #eff6ff; border: 1.5px solid #93c5fd; }
        .toast-info .toast-icon { background: #dbeafe; }
        .toast-info .toast-icon svg { color: #2563eb; }
        .toast-info .toast-title { color: #1e3a8a; }
        .toast-info .toast-message { color: #1d4ed8; }
        .toast-info .toast-close { color: #1e3a8a; }
        .toast-info .toast-progress { background: #3b82f6; }

        /* Modal backdrop blur — applied automatically when overlay is visible */
        .modal-blur-active {
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }
    </style>
    @stack('styles')
</head>
<body class="text-gray-900 min-h-screen" style="background-color: var(--bg-color);">
    <div id="toast-container"></div>
    <div class="flex min-h-screen">
        
        <!-- Mobile sidebar backdrop (only visible when drawer is open on < lg) -->
        <div id="sidebarOverlay" onclick="closeSidebar()" class="fixed inset-0 z-40 hidden lg:hidden" style="background-color: rgba(0,0,0,0.5);"></div>

        <!-- Sidebar - Modern Design -->
        <aside id="sidebar" class="sidebar-transition fixed inset-y-0 left-0 h-screen overflow-y-auto {{ $preferences['sidebar_style'] === 'gradient' ? 'primary-gradient' : 'primary-solid' }} text-white shadow-2xl z-50 w-64 -translate-x-full lg:translate-x-0">
            <!-- Logo Section -->
            <div class="sidebar-logo p-5 pb-2 flex items-center justify-center">
                    <div class="w-full rounded-xl p-3 backdrop-blur-sm">
                        <img src="/images/eclectic_logo_nobg.png" alt="EcoSystem Logo" class="w-full h-auto"/>
                    </div>
            </div>

            <!-- Navigation Menu -->
            @hasSection('sidebar-nav')
                @yield('sidebar-nav')
            @else
            <nav class="py-6 px-4">
                <!-- HOME - Visible to all roles -->
                <div class="mb-2">
                    <a href="{{ route('dashboard') }}" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('dashboard') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-home"></i>
                        </span>
                        <span class="nav-text font-medium">Home</span>
                    </a>
                </div>
                
                @if($can('calendar'))
                <!-- CALENDAR Dropdown -->
                <div class="mb-2">
                    <button onclick="toggleCalendarDropdown()" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl w-full text-left {{ Request::is('calendar*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-calendar-alt"></i>
                        </span>
                        <span class="nav-text flex-1 font-medium">Calendar</span>
                        <i class="fas fa-chevron-down text-xs nav-text transition-transform" id="calendarChevron"></i>
                    </button>
                    <div id="calendarDropdown" class="nav-text {{ Request::is('calendar*') ? '' : 'hidden' }} mt-2 ml-4 space-y-1">
                        @if($can('calendar.events'))
                        <a href="{{ route('calendar.events') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('calendar/events*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-calendar-check text-xs"></i>
                            </span>
                            <span class="nav-text text-sm">Events</span>
                        </a>
                        @endif
                        @if($can('calendar.timesheets'))
                        <a href="{{ route('calendar.timesheets') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('calendar/timesheets*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-clock text-xs"></i>
                            </span>
                            <span class="nav-text text-sm">Timesheets</span>
                        </a>
                        @endif
                    </div>
                </div>
                @endif
                
                @if($can('reporting'))
                <!-- REPORTING Dropdown -->
                <div class="mb-2">
                    <button onclick="toggleReportingDropdown()" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl w-full text-left {{ Request::is('reporting*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-chart-line"></i>
                        </span>
                        <span class="nav-text flex-1 font-medium">Reporting</span>
                        <i class="fas fa-chevron-down text-xs nav-text transition-transform" id="reportingChevron"></i>
                    </button>
                    <div id="reportingDropdown" class="nav-text {{ Request::is('reporting*') ? '' : 'hidden' }} mt-2 ml-4 space-y-1">
                        @if($can('reporting.validation'))
                        <a href="{{ route('reporting') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('reporting') && !Request::is('reporting/md-recap*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-check-circle text-xs"></i>
                            </span>
                            <span class="nav-text text-sm">MD Validation</span>
                        </a>
                        @endif
                        @if($can('reporting.md-recap'))
                        <a href="{{ route('reporting.md-recap') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('reporting/md-recap*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-table text-xs"></i>
                            </span>
                            <span class="nav-text text-sm">MD Recap</span>
                        </a>
                        @endif
                        @if($can('reporting.collection-outlook'))
                        <a href="{{ route('reporting.collection-outlook') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ (Request::is('reporting/collection-outlook') || Request::is('reporting/collection-outlook/*')) ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-hand-holding-usd text-xs"></i>
                            </span>
                            <span class="nav-text text-sm">Collection Outlook</span>
                        </a>
                        @endif
                        @if($can('reporting.collection-outlook-support'))
                        <a href="{{ route('reporting.collection-outlook-support') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('reporting/collection-outlook-support*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-hand-holding-usd text-xs"></i>
                            </span>
                            <span class="nav-text text-sm">Collection Outlook (Support)</span>
                        </a>
                        @endif
                        @if($can('reporting.ticketing-overview'))
                        <a href="{{ route('reporting.ticketing-overview') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('reporting/ticketing-overview*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-headset text-xs"></i>
                            </span>
                            <span class="nav-text text-sm">Ticketing Overview</span>
                        </a>
                        @endif
                        @if($can('reporting.ticket-by-module'))
                        <a href="{{ route('reporting.ticket-by-module') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('reporting/ticket-by-module*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-puzzle-piece text-xs"></i>
                            </span>
                            <span class="nav-text text-sm">Ticket by Modul</span>
                        </a>
                        @endif
                        @if($can('reporting.log-shifting'))
                        <a href="{{ route('reporting.log-shifting') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('reporting/log-shifting*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-clock text-xs"></i>
                            </span>
                            <span class="nav-text text-sm">Log Shifting</span>
                        </a>
                        @endif
                        @if($can('reporting.resolution-days'))
                        <a href="{{ route('reporting.resolution-days') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('reporting/resolution-days*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-hourglass-half text-xs"></i>
                            </span>
                            <span class="nav-text text-sm">Resolution Days</span>
                        </a>
                        @endif
                    </div>
                </div>
                @endif

                @if($can('master'))
                <!-- MASTER Dropdown -->
                <div class="mb-2">
                    <button onclick="toggleMasterDropdown()" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl w-full text-left {{ Request::is('master*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-database"></i>
                        </span>
                        <span class="nav-text flex-1 font-medium">Master</span>
                        <i class="fas fa-chevron-down text-xs nav-text transition-transform" id="masterChevron"></i>
                    </button>
                    <div id="masterDropdown" class="nav-text {{ Request::is('master*') ? '' : 'hidden' }} mt-2 ml-4 space-y-1">
                        @if($can('master.employee'))
                        <a href="{{ route('master.employee.index') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('master/employee*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-users text-xs"></i>
                            </span>
                            <span class="nav-text text-sm">Employee</span>
                        </a>
                        @endif
                        @if($can('master.customer'))
                        <a href="{{ route('master.customer.index') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('master/customer*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-user-tie text-xs"></i>
                            </span>
                            <span class="nav-text text-sm">Customer</span>
                        </a>
                        @endif
                    </div>
                </div>
                @endif

                @if($can('financial'))
                <!-- FINANCIAL -->
                <div class="mb-2">
                    <a href="#" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('financial') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-coins"></i>
                        </span>
                        <span class="nav-text font-medium">Financial</span>
                    </a>
                </div>
                @endif

                @if($can('general'))
                <!-- HR & GENERAL -->
                <div class="mb-2">
                    <a href="#" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('general') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-users-cog"></i>
                        </span>
                        <span class="nav-text font-medium">HR & General</span>
                    </a>
                </div>
                @endif

                @if($can('business'))
                <!-- BUSINESS DEV -->
                <div class="mb-2">
                    <a href="#" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('business') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-briefcase"></i>
                        </span>
                        <span class="nav-text font-medium">Business Dev</span>
                    </a>
                </div>
                @endif

                @if($can('tickets.inbox'))
                <!-- TICKET -->
                <div class="mb-2">
                    @php
                        $ticketActive = Request::is('ticket') || (Request::is('ticket/*') && !Request::is('ticket/task*') && !Request::is('ticket/consultant-workload*'));
                    @endphp
                    <a href="{{ route('ticket.index') }}" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ $ticketActive ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-ticket-alt"></i>
                        </span>
                        <span class="nav-text font-medium">Ticket</span>
                    </a>
                </div>
                @endif

                @if($can('ticket.my-tasks'))
                <!-- MY TASKS -->
                <div class="mb-2">
                    <a href="{{ route('ticket.task') }}" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('ticket/task*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-tasks"></i>
                        </span>
                        <span class="nav-text font-medium">My Tasks</span>
                    </a>
                </div>
                @endif

                @if($can('ticket.consultant-workload'))
                <!-- CONSULTANT WORKLOAD -->
                <div class="mb-2">
                    <a href="{{ route('ticket.consultant-workload') }}" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('ticket/consultant-workload*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-users-cog"></i>
                        </span>
                        <span class="nav-text font-medium">Consultant Workload</span>
                    </a>
                </div>
                @endif

                @if($can('tickets.staging'))
                <!-- TICKET VALIDATION -->
                <div class="mb-2">
                    <a href="{{ route('staging.index') }}" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('staging-tickets*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-clipboard-check"></i>
                        </span>
                        <span class="nav-text font-medium flex-1">Ticket Validation</span>
                        @php
                            $unvalidatedCount = \App\Models\StagingTicket::where('status', 'unvalidated')->count();
                        @endphp
                        <span id="sidebarValidationBadge"
                              class="nav-text bg-yellow-400 text-gray-900 text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[20px] text-center {{ $unvalidatedCount > 0 ? '' : 'hidden' }}">
                            {{ $unvalidatedCount > 99 ? '99+' : $unvalidatedCount }}
                        </span>
                    </a>
                </div>
                @endif


                @if($can('delivery'))
                <!-- DELIVERY Dropdown -->
                <div class="mb-2">
                    <button onclick="toggleDeliveryDropdown()" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl w-full text-left {{ Request::is('project*') || Request::is('planning*') || Request::is('issues*') || Request::is('delivery/support*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-truck"></i>
                        </span>
                        <span class="nav-text flex-1 font-medium">Delivery</span>
                        <i class="fas fa-chevron-down text-xs nav-text transition-transform" id="deliveryChevron"></i>
                    </button>
                    <div id="deliveryDropdown" class="nav-text {{ Request::is('project*') || Request::is('planning*') || Request::is('issues*') || Request::is('delivery/support*') ? '' : 'hidden' }} mt-2 ml-4 space-y-1">
                        @if($can('delivery.project'))
                        <a href="{{ route('projects.index') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('project*') || Request::is('planning*') || Request::is('issues*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-project-diagram text-xs"></i>
                            </span>
                            <span class="nav-text text-sm">Project</span>
                        </a>
                        @endif
                        @if($can('delivery.support'))
                        <a href="{{ route('delivery.support.index') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('delivery/support*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-headset text-xs"></i>
                            </span>
                            <span class="nav-text text-sm">Support</span>
                        </a>
                        @endif
                    </div>
                </div>
                @endif

                @if($can('control-center'))
                <!-- CONTROL CENTER -->
                @php $adminOpen = Request::is('admin*'); @endphp
                <div class="mb-2">
                    <button onclick="toggleAdminDropdown()" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl w-full text-left {{ $adminOpen ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-shield-alt"></i>
                        </span>
                        <span class="nav-text flex-1 font-medium">Control Center</span>
                        <i class="fas fa-chevron-down text-xs nav-text transition-transform {{ $adminOpen ? 'rotate-180' : '' }}" id="adminChevron"></i>
                    </button>
                    <div id="adminDropdown" class="nav-text {{ $adminOpen ? '' : 'hidden' }} mt-1 ml-4 space-y-1">
                        @if($can('control-center.overview'))
                        <a href="{{ route('admin.index') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('admin') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center"><i class="fas fa-th-large text-xs"></i></span>
                            <span class="nav-text text-sm">Overview</span>
                        </a>
                        @endif
                        @if($can('control-center.activity-log'))
                        <a href="{{ route('admin.activity-log') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('admin/activity-log*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center"><i class="fas fa-history text-xs"></i></span>
                            <span class="nav-text text-sm">Activity Log</span>
                        </a>
                        @endif
                        @if($can('control-center.login-log'))
                        <a href="{{ route('admin.login-log') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('admin/login-log*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center"><i class="fas fa-sign-in-alt text-xs"></i></span>
                            <span class="nav-text text-sm">Login Log</span>
                        </a>
                        @endif
                        @if($can('control-center.sessions'))
                        <a href="{{ route('admin.sessions') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('admin/sessions*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center"><i class="fas fa-users text-xs"></i></span>
                            <span class="nav-text text-sm">Active Sessions</span>
                        </a>
                        @endif
                        @if($can('control-center.failed-jobs'))
                        <a href="{{ route('admin.failed-jobs') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('admin/failed-jobs*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center"><i class="fas fa-exclamation-triangle text-xs"></i></span>
                            <span class="nav-text text-sm">Failed Jobs</span>
                        </a>
                        @endif
                        @if($can('control-center.backup'))
                        <a href="{{ route('admin.backup') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('admin/backup*') || Request::is('admin/export*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center"><i class="fas fa-database text-xs"></i></span>
                            <span class="nav-text text-sm">Backup & Export</span>
                        </a>
                        @endif
                        @if($can('control-center.sounds'))
                        <a href="{{ route('admin.sounds') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('admin/sounds*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center"><i class="fas fa-music text-xs"></i></span>
                            <span class="nav-text text-sm">Notif Sounds</span>
                        </a>
                        @endif
                    </div>
                </div>
                @endif

                @if($showSlaMenu || $canManageSla)
                <!-- SLA Dropdown -->
                @php $slaDropdownOpen = Request::is('sla*'); @endphp
                <div class="mb-2">
                    <button onclick="toggleSlaDropdown()" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl w-full text-left {{ $slaDropdownOpen ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-stopwatch"></i>
                        </span>
                        <span class="nav-text flex-1 font-medium">SLA</span>
                        <i class="fas fa-chevron-down text-xs nav-text transition-transform {{ $slaDropdownOpen ? 'rotate-180' : '' }}" id="slaChevron"></i>
                    </button>
                    <div id="slaDropdown" class="nav-text {{ $slaDropdownOpen ? '' : 'hidden' }} mt-2 ml-4 space-y-1">
                        @if($showSlaMenu)
                        <a href="{{ route('sla.report') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('sla/report*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-chart-bar text-xs"></i>
                            </span>
                            <span class="nav-text text-sm">SLA Report</span>
                        </a>
                        @endif
                        @if($canManageSla)
                        <a href="{{ route('sla.config') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('sla/config*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-cog text-xs"></i>
                            </span>
                            <span class="nav-text text-sm">SLA Config</span>
                        </a>
                        @endif
                    </div>
                </div>
                @endif

                @if($showRpmoMenu)
                <!-- RPMO -->
                @php $rpmoDropdownOpen = Request::is('rpmo*'); @endphp
                <div class="mb-2">
                    <button onclick="toggleRpmoDropdown()" class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-xl {{ $rpmoDropdownOpen ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all" style="background:none;border:none;cursor:pointer;text-align:left;">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-cogs"></i>
                        </span>
                        <span class="nav-text font-medium flex-1">RPMO</span>
                        <span id="rpmoChevron" class="nav-text transition-transform duration-200 {{ $rpmoDropdownOpen ? 'rotate-180' : '' }}">
                            <i class="fas fa-chevron-down text-xs"></i>
                        </span>
                    </button>

                    <div id="rpmoSubmenu" class="{{ $rpmoDropdownOpen ? '' : 'hidden' }} pl-4 mt-1 space-y-1">
                        @if($can('rpmo.overview'))
                        <a href="{{ route('rpmo') }}" class="nav-link flex items-center gap-3 px-4 py-2 rounded-xl {{ Request::is('rpmo') && !Request::is('rpmo/*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all text-sm">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-tachometer-alt"></i>
                            </span>
                            <span class="nav-text">Overview</span>
                        </a>
                        @endif
                        @if($can('rpmo.periods'))
                        <a href="{{ route('rpmo.periods.index') }}" class="nav-link flex items-center gap-3 px-4 py-2 rounded-xl {{ Request::is('rpmo/periods*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all text-sm">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-calendar-alt"></i>
                            </span>
                            <span class="nav-text">Period Management</span>
                        </a>
                        @endif
                    </div>
                </div>
                @endif

                @if($can('legal'))
                <!-- LEGAL -->
                <div class="mb-2">
                    <a href="#" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('legal') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-balance-scale"></i>
                        </span>
                        <span class="nav-text font-medium">Legal</span>
                    </a>
                </div>
                @endif

                @if($can('management'))
                <!-- MANAJEMEN -->
                <div class="mb-2">
                    <button onclick="toggleManajemenDropdown()" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl w-full text-left {{ Request::is('management*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-shield-alt"></i>
                        </span>
                        <span class="nav-text flex-1 font-medium">Management</span>
                        <i class="fas fa-chevron-down text-xs nav-text transition-transform" id="manajemenChevron"></i>
                    </button>
                    <div id="manajemenDropdown" class="nav-text {{ Request::is('management*') ? '' : 'hidden' }} mt-2 ml-4 space-y-1">
                        @if($can('management.roles'))
                        <a href="{{ route('management.roles.index') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('management/roles*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-user-tag text-xs"></i>
                            </span>
                            <span class="nav-text text-sm">Role</span>
                        </a>
                        @endif
                        @if($can('management.permissions'))
                        <a href="{{ route('management.permissions.index') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('management/permissions*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-key text-xs"></i>
                            </span>
                            <span class="nav-text text-sm">Menu Access</span>
                        </a>
                        @endif
                        @if($can('management.holidays'))
                        <a href="{{ route('management.holidays.index') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('management/holidays*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-calendar-day text-xs"></i>
                            </span>
                            <span class="nav-text text-sm">Holidays</span>
                        </a>
                        @endif
                        @if($can('management.hidden-tickets'))
                        <a href="{{ route('management.hidden-tickets.index') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('management/hidden-tickets*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-eye-slash text-xs"></i>
                            </span>
                            <span class="nav-text text-sm">Hidden Tickets</span>
                        </a>
                        @endif
                        @if($can('management.employee'))
                        <div class="mt-1">
                            <button onclick="toggleMasterMgmtDropdown()" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg w-full text-left {{ Request::is('management/employee*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-users text-xs"></i>
                                </span>
                                <span class="nav-text text-sm flex-1">Employee</span>
                                <i class="fas fa-chevron-down text-xs nav-text transition-transform {{ Request::is('management/employee*') ? 'rotate-180' : '' }}" id="masterMgmtChevron"></i>
                            </button>
                            <div id="masterMgmtDropdown" class="nav-text {{ Request::is('management/employee*') ? '' : 'hidden' }} mt-1 ml-4 space-y-1">
                                @if($can('management.employee.basic-data'))
                                <a href="{{ route('management.employee.basic-data.index') }}" class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('management/employee/basic-data*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                    <span class="w-3 h-3 flex items-center justify-center"><i class="fas fa-id-card text-xs"></i></span>
                                    <span class="nav-text text-xs">Basic Data</span>
                                </a>
                                @endif
                                @if($can('management.employee.address'))
                                <a href="{{ route('management.employee.address.index') }}" class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('management/employee/address*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                    <span class="w-3 h-3 flex items-center justify-center"><i class="fas fa-map-marker-alt text-xs"></i></span>
                                    <span class="nav-text text-xs">Address</span>
                                </a>
                                @endif
                                @if($can('management.employee.identification'))
                                <a href="{{ route('management.employee.identification.index') }}" class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('management/employee/identification*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                    <span class="w-3 h-3 flex items-center justify-center"><i class="fas fa-fingerprint text-xs"></i></span>
                                    <span class="nav-text text-xs">Identification</span>
                                </a>
                                @endif
                                @if($can('management.employee.family'))
                                <a href="{{ route('management.employee.family.index') }}" class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('management/employee/family*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                    <span class="w-3 h-3 flex items-center justify-center"><i class="fas fa-users text-xs"></i></span>
                                    <span class="nav-text text-xs">Family</span>
                                </a>
                                @endif
                                @if($can('management.employee.education'))
                                <a href="{{ route('management.employee.education.index') }}" class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('management/employee/education*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                    <span class="w-3 h-3 flex items-center justify-center"><i class="fas fa-graduation-cap text-xs"></i></span>
                                    <span class="nav-text text-xs">Education</span>
                                </a>
                                @endif
                                @if($can('management.employee.qualification'))
                                <a href="{{ route('management.employee.qualification.index') }}" class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('management/employee/qualification*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                    <span class="w-3 h-3 flex items-center justify-center"><i class="fas fa-certificate text-xs"></i></span>
                                    <span class="nav-text text-xs">Qualification</span>
                                </a>
                                @endif
                                @if($can('management.employee.contract'))
                                <a href="{{ route('management.employee.contract.index') }}" class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('management/employee/contract*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                    <span class="w-3 h-3 flex items-center justify-center"><i class="fas fa-file-contract text-xs"></i></span>
                                    <span class="nav-text text-xs">Contract</span>
                                </a>
                                @endif
                                @if($can('management.employee.bank'))
                                <a href="{{ route('management.employee.bank.index') }}" class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('management/employee/bank*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                    <span class="w-3 h-3 flex items-center justify-center"><i class="fas fa-university text-xs"></i></span>
                                    <span class="nav-text text-xs">Bank Account</span>
                                </a>
                                @endif
                                @if($can('management.employee.payment'))
                                <a href="{{ route('management.employee.payment.index') }}" class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('management/employee/payment*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                    <span class="w-3 h-3 flex items-center justify-center"><i class="fas fa-money-bill text-xs"></i></span>
                                    <span class="nav-text text-xs">Basic Payment</span>
                                </a>
                                @endif
                                @if($can('management.employee.attachment'))
                                <a href="{{ route('management.employee.attachment.index') }}" class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('management/employee/attachment*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                    <span class="w-3 h-3 flex items-center justify-center"><i class="fas fa-paperclip text-xs"></i></span>
                                    <span class="nav-text text-xs">Attachment</span>
                                </a>
                                @endif
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
                @endif

                <!-- Divider -->
                <div class="my-6 border-t border-white border-opacity-10"></div>
                
                <!-- SETTINGS - Visible to all roles -->
                <div class="mb-2">
                    <a href="{{ route('settings.index') }}" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('settings*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-cog"></i>
                        </span>
                        <span class="nav-text font-medium">Settings</span>
                    </a>
                </div>
            </nav>
            @endif
        </aside>

        <!-- Main Content -->
        <main id="mainContent" class="sidebar-transition flex-1 ml-0 lg:ml-64 min-w-0">
            <!-- Header - Modern Design -->
            <header class="sticky top-0 z-40 shadow-sm border-b border-gray-100" style="background-color: var(--card-bg);">
                <div class="px-4 sm:px-6 py-3 sm:py-4 flex justify-between items-center gap-3">
                    <div class="flex items-center gap-3 sm:gap-4 min-w-0">
                        <button onclick="toggleSidebar()" class="flex-shrink-0 w-10 h-10 flex items-center justify-center border-2 rounded-xl hover:bg-opacity-10 primary-hover primary-border transition-all" style="border-color: var(--primary-color); color: var(--text-color);">
                            <i class="fas fa-bars"></i>
                        </button>
                        <div class="min-w-0">
                            <h1 class="text-base sm:text-xl font-bold mb-0.5 truncate" style="color: var(--text-color);">@yield('page-title', 'Dashboard')</h1>
                            <p class="text-xs text-gray-500 truncate">@yield('page-subtitle', 'Welcome back')</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 sm:gap-4 flex-shrink-0">
                        <!-- Search Bar -->
                        @yield('page-actions')
                        <!-- Notification Bell -->
                        <div class="flex items-center gap-2">
                            <button id="soundToggleBtn" onclick="toggleNotifSound()"
                                title="Disable notification sound"
                                class="w-10 h-10 flex items-center justify-center border-2 border-gray-200 rounded-xl hover:border-red-800 hover:bg-red-50 transition-all text-red-700 hover:text-red-800">
                                <i id="soundToggleIcon" class="fas fa-volume-up text-sm"></i>
                            </button>
                        <div class="relative" id="bellWrapper">
                            <button id="bellBtn" onclick="toggleBellDropdown()"
                                class="relative w-10 h-10 flex items-center justify-center border-2 border-gray-200 rounded-xl hover:border-red-800 hover:bg-red-50 transition-all text-gray-600 hover:text-red-800">
                                <i class="fas fa-bell"></i>
                                <span id="bellBadge" class="hidden absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 bg-red-600 rounded-full border-2 border-white text-white text-[10px] font-bold flex items-center justify-center leading-none"></span>
                            </button>

                            <!-- Notification Dropdown -->
                            <div id="bellDropdown" class="hidden absolute top-full right-0 mt-2 w-80 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 overflow-hidden">
                                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                                    <span class="text-sm font-semibold text-gray-800">Notifications</span>
                                    <div class="flex gap-2">
                                        <button onclick="markAllNotificationsRead()" class="text-xs text-red-700 hover:underline font-medium">Mark all read</button>
                                        <a href="{{ route('notifications.index') }}" class="text-xs text-gray-500 hover:underline">View all</a>
                                    </div>
                                </div>
                                <div id="bellNotifList" class="max-h-80 overflow-y-auto divide-y divide-gray-50">
                                    <div class="px-4 py-6 text-center text-xs text-gray-400">Loading...</div>
                                </div>
                            </div>
                        </div>
                        </div>{{-- end flex gap-2 sound+bell wrapper --}}

                        <!-- User Menu -->
                        <div class="relative">
                            <button onclick="toggleUserDropdown()" class="flex items-center gap-3 px-4 py-2.5 border-2 border-gray-200 rounded-xl hover:bg-gray-50 hover:border-red-800 transition-all">
                                <div class="w-10 h-10 rounded-xl primary-gradient text-white flex items-center justify-center font-bold text-sm shadow-md">
                                    @if(isset($user['type']) && $user['type'] === 'customer')
                                        {{ strtoupper(substr($user['company_name'] ?? 'C', 0, 2)) }}
                                    @else
                                        {{ strtoupper(substr($user['name'] ?? 'U', 0, 2)) }}
                                    @endif
                                </div>
                                <div class="text-left hidden xl:block">
                                    <div class="text-sm font-bold text-gray-900">
                                        @if(isset($user['type']) && $user['type'] === 'customer')
                                            {{ $user['company_name'] ?? 'Customer' }}
                                        @else
                                            {{ $user['name'] ?? 'User' }}
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ $user['role']['name'] ?? 'User' }}
                                    </div>
                                </div>
                                <i class="fas fa-chevron-down text-gray-500 text-xs"></i>
                            </button>

                            <div id="userDropdown" class="hidden absolute top-full right-0 mt-2 w-64 bg-white rounded-xl shadow-2xl border-2 border-gray-100 p-2 z-50">
                                <!-- User Info -->
                                <a href="{{ route('profile.my') }}" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-50 text-gray-900 text-sm transition-all font-medium">
                                    <i class="fas fa-user w-5 text-center text-gray-500"></i>
                                    <span>My Profile</span>
                                </a>
                                <a href="{{ route('settings.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-50 text-gray-900 text-sm transition-all font-medium">
                                    <i class="fas fa-cog w-5 text-center text-gray-500"></i>
                                    <span>Settings</span>
                                </a>
                                <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-50 text-gray-900 text-sm transition-all font-medium">
                                    <i class="fas fa-question-circle w-5 text-center text-gray-500"></i>
                                    <span>Help & Support</span>
                                </a>
                                <hr class="my-2 border-gray-200">
                                <button type="button" onclick="handleLogout(this)"
                                    class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-red-50 text-red-600 text-sm w-full text-left transition-all font-medium">
                                    <i class="fas fa-sign-out-alt w-5 text-center"></i>
                                    <span>Sign Out</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content Area -->
            <div class="overflow-x-hidden">
                <div class="@yield('content-class', 'p-6')">
                    @yield('content')
                </div>
            </div>
        </main>
    </div>

    <script>
        function handleLogout(el) {
            if (el) el.style.pointerEvents = 'none';
            fetch('/api/auth/logout', { method: 'POST' })
                .finally(() => { window.location.href = '/auth/login'; });
        }
    </script>

    <script>
        var isCalendarDropdownOpen = {{ Request::is('calendar*') ? 'true' : 'false' }};

        function toggleCalendarDropdown() {

            var dropdown = document.getElementById('calendarDropdown');
            isCalendarDropdownOpen = !isCalendarDropdownOpen;
            dropdown.classList.toggle('hidden', !isCalendarDropdownOpen);
        }
    </script>

    <script>
        var isCollapsed = false;
        var isMasterDropdownOpen = {{ Request::is('master*') ? 'true' : 'false' }};
        var isDeliveryDropdownOpen = {{ Request::is('project*') || Request::is('support*') ? 'true' : 'false' }};
        var isReportingDropdownOpen = {{ Request::is('reporting*') ? 'true' : 'false' }};
        
        // Desktop = docked sidebar (>= Tailwind lg breakpoint 1024px).
        // Below that we treat the sidebar as a slide-in drawer with a backdrop.
        function isDesktopViewport() { return window.innerWidth >= 1024; }

        function openSidebar() {
            var sidebar = document.getElementById('sidebar');
            var overlay = document.getElementById('sidebarOverlay');
            // Guarantee full width even if the sidebar was collapsed on desktop first.
            sidebar.classList.remove('w-0', 'overflow-hidden', '-translate-x-full');
            sidebar.classList.add('w-64', 'translate-x-0');
            if (overlay) overlay.classList.remove('hidden');
            document.body.style.overflow = 'hidden'; // lock scroll behind drawer
        }

        function closeSidebar() {
            var sidebar = document.getElementById('sidebar');
            var overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.add('-translate-x-full');
            sidebar.classList.remove('translate-x-0');
            if (overlay) overlay.classList.add('hidden');
            document.body.style.overflow = '';
        }

        function toggleSidebar() {
            var sidebar     = document.getElementById('sidebar');
            var mainContent = document.getElementById('mainContent');

            if (isDesktopViewport()) {
                // Desktop: collapse/expand the docked sidebar and reflow content.
                isCollapsed = !isCollapsed;
                if (isCollapsed) {
                    sidebar.classList.remove('w-64');
                    sidebar.classList.add('w-0', 'overflow-hidden');
                    mainContent.classList.remove('lg:ml-64');
                    mainContent.classList.add('lg:ml-0');
                } else {
                    sidebar.classList.remove('w-0', 'overflow-hidden');
                    sidebar.classList.add('w-64');
                    mainContent.classList.remove('lg:ml-0');
                    mainContent.classList.add('lg:ml-64');
                }
            } else {
                // Mobile/tablet: open or close the drawer.
                var isOpen = sidebar.classList.contains('translate-x-0');
                if (isOpen) closeSidebar(); else openSidebar();
            }
        }

        // When resizing up to desktop, drop any mobile drawer state so the
        // docked sidebar and scroll lock are always in a clean state.
        window.addEventListener('resize', function () {
            if (isDesktopViewport()) {
                var overlay = document.getElementById('sidebarOverlay');
                if (overlay) overlay.classList.add('hidden');
                document.body.style.overflow = '';
            }
        });

        // Close the drawer with the Escape key on mobile.
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !isDesktopViewport()) closeSidebar();
        });

        function toggleMasterDropdown() {
            isMasterDropdownOpen = !isMasterDropdownOpen;
            document.getElementById('masterDropdown').classList.toggle('hidden', !isMasterDropdownOpen);
        }

        function toggleReportingDropdown() {
            isReportingDropdownOpen = !isReportingDropdownOpen;
            document.getElementById('reportingDropdown').classList.toggle('hidden', !isReportingDropdownOpen);
        }

        function toggleDeliveryDropdown() {
            isDeliveryDropdownOpen = !isDeliveryDropdownOpen;
            document.getElementById('deliveryDropdown').classList.toggle('hidden', !isDeliveryDropdownOpen);
        }

        let isRpmoDropdownOpen = {{ Request::is('rpmo*') ? 'true' : 'false' }};
        function toggleRpmoDropdown() {
            isRpmoDropdownOpen = !isRpmoDropdownOpen;
            const submenu = document.getElementById('rpmoSubmenu');
            const chevron = document.getElementById('rpmoChevron');
            if (submenu) submenu.classList.toggle('hidden', !isRpmoDropdownOpen);
            if (chevron) chevron.classList.toggle('rotate-180', isRpmoDropdownOpen);
        }

        function toggleAdminDropdown() {
            const submenu = document.getElementById('adminDropdown');
            const chevron = document.getElementById('adminChevron');
            if (!submenu) return;
            const isOpen = !submenu.classList.contains('hidden');
            submenu.classList.toggle('hidden', isOpen);
            if (chevron) chevron.classList.toggle('rotate-180', !isOpen);
        }

        function toggleSlaDropdown() {
            const submenu = document.getElementById('slaDropdown');
            const chevron = document.getElementById('slaChevron');
            if (!submenu) return;
            const isOpen = !submenu.classList.contains('hidden');
            submenu.classList.toggle('hidden', isOpen);
            if (chevron) chevron.classList.toggle('rotate-180', !isOpen);
        }

        function toggleManajemenDropdown() {
            const submenu = document.getElementById('manajemenDropdown');
            const chevron = document.getElementById('manajemenChevron');
            if (!submenu) return;
            const isOpen = !submenu.classList.contains('hidden');
            submenu.classList.toggle('hidden', isOpen);
            if (chevron) chevron.classList.toggle('rotate-180', !isOpen);
        }

        function toggleMasterMgmtDropdown() {
            const submenu = document.getElementById('masterMgmtDropdown');
            const chevron = document.getElementById('masterMgmtChevron');
            if (!submenu) return;
            const isOpen = !submenu.classList.contains('hidden');
            submenu.classList.toggle('hidden', isOpen);
            if (chevron) chevron.classList.toggle('rotate-180', !isOpen);
        }

        function toggleUserDropdown() {
            document.getElementById('userDropdown').classList.toggle('hidden');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            var userMenu = event.target.closest('button[onclick="toggleUserDropdown()"]');
            var dropdown = document.getElementById('userDropdown');
            if (!userMenu && !dropdown.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });
        
        // Smooth scroll behavior
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                if (!href || href === '#' || !href.startsWith('#')) return;
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    </script>
    <script>
        const _toastIcons = {
            success: `<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>`,
            error:   `<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>`,
            warning: `<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>`,
            info:    `<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>`,
        };
        const _toastLabels = {
            success: 'Success',
            error:   'Error',
            warning: 'Warning',
            info:    'Information',
        };

        function showToast(message, type, duration = 4000) {
            type = type ?? 'info';
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <div class="toast-body">
                    <div class="toast-icon">${_toastIcons[type] ?? _toastIcons.info}</div>
                    <div class="toast-content">
                        <p class="toast-title">${_toastLabels[type] ?? 'Info'}</p>
                        <p class="toast-message">${message}</p>
                    </div>
                    <button class="toast-close" aria-label="Close">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="toast-progress" style="animation-duration: ${duration}ms;"></div>
            `;
            container.appendChild(toast);
            requestAnimationFrame(() => requestAnimationFrame(() => toast.classList.add('show')));
            function dismiss() {
                toast.classList.remove('show');
                toast.classList.add('hide');
                toast.addEventListener('transitionend', () => toast.remove(), { once: true });
            }
            const timer = setTimeout(dismiss, duration);
            toast.querySelector('.toast-close').addEventListener('click', () => { clearTimeout(timer); dismiss(); });
        }

        function showNotification(message, type = 'info') {
            showToast(message, type);
        }

        function showToastClose(btn) {
            const toast = btn.closest('.toast-close');
            if (toast) toast.click();
        }

        // ── Flash messages dari redirect (misal: akses ditolak middleware) ──
        @if(session('error'))
        document.addEventListener('DOMContentLoaded', function() {
            showToast(@json(session('error')), 'error', 6000);
        });
        @endif
        @if(session('success'))
        document.addEventListener('DOMContentLoaded', function() {
            showToast(@json(session('success')), 'success', 4000);
        });
        @endif
        @if(session('warning'))
        document.addEventListener('DOMContentLoaded', function() {
            showToast(@json(session('warning')), 'warning', 5000);
        });
        @endif

        // â”€â”€ Modal backdrop blur (auto-applied, no per-modal changes needed) â”€â”€
        (function() {
            function _syncModalBlur() {
                document.querySelectorAll('.fixed.inset-0').forEach(function(el) {
                    var isOverlay = !el.classList.contains('hidden')
                        && el.id !== 'toast-container'
                        && !el.classList.contains('action-dropdown');
                    el.classList.toggle('modal-blur-active', isOverlay);
                });
            }
            new MutationObserver(_syncModalBlur).observe(document.body, {
                subtree: true, attributes: true, attributeFilter: ['class', 'style']
            });
        })();
    </script>
    @stack('scripts')

    {{-- ==================== GLOBAL SELECT ENHANCER ====================
         Auto-styles every native <select> across the app dengan UI bergaya
         custom-dd (button + panel + chevron animasi). <select> asli tetap di
         DOM (visually hidden) sehingga form submission, kode legacy yang
         membaca .value, dan listener change/onchange tetap berfungsi.
         Opt-out per element: tambahkan atribut `data-no-enhance`. --}}
    @php
        $selectEnhancePath = public_path('js/select-enhance.js');
        $selectEnhanceVer  = file_exists($selectEnhancePath) ? filemtime($selectEnhancePath) : time();
    @endphp
    <script src="/js/select-enhance.js?v={{ $selectEnhanceVer }}"></script>

    <!-- ==================== NOTIFICATION BELL JS ==================== -->
    <script>
    (function () {
        var bellOpen = false;
        var csrf = document.querySelector('meta[name=”csrf-token”]')?.getAttribute('content') || '';

        /* ---- toggle dropdown ---- */
        function toggleBellDropdown() {
            bellOpen = !bellOpen;
            var dropdown = document.getElementById('bellDropdown');
            if (bellOpen) {
                dropdown.classList.remove('hidden');
                loadBellNotifications();
            } else {
                dropdown.classList.add('hidden');
            }
        }

        document.addEventListener('click', function (e) {
            var wrapper = document.getElementById('bellWrapper');
            if (wrapper && !wrapper.contains(e.target)) {
                var dropdown = document.getElementById('bellDropdown');
                if (dropdown) dropdown.classList.add('hidden');
                bellOpen = false;
            }
        });

        /* ---- notification sound (HTML Audio — works without immediate user gesture) ---- */
        var _soundEnabled     = localStorage.getItem('notif_sound_enabled') !== 'false';
        var _lastUnreadCount  = null;
        var _lastNonMsgCount  = null;
        var _lastMessageCount = null;
        var _pageTitle        = document.title;

        // Use <audio> element — simpler and respects Chrome's per-origin user activation,
        // meaning it works even when triggered by push/postMessage without a direct click.
        var _defaultSoundFile = 'mixkit-software-interface-back-2575.wav';
        var _audioTicket  = new Audio('/sounds/' + _defaultSoundFile);
        var _audioChat    = new Audio('/sounds/' + _defaultSoundFile);
        var _audioStaging = new Audio('/sounds/' + _defaultSoundFile);
        _audioTicket.preload  = 'auto';
        _audioChat.preload    = 'auto';
        _audioStaging.preload = 'auto';

        // ── Browser autoplay unlock ──────────────────────────────────────────
        // Browsers block audio.play() until the user has interacted with the page
        // (click, keydown, touchstart). We silently warm-up both audio elements on
        // the first interaction so every subsequent play() call succeeds immediately.
        var _audioUnlocked = false;
        function _unlockAudio() {
            if (_audioUnlocked) return;
            _audioUnlocked = true;
            [_audioTicket, _audioChat, _audioStaging].forEach(function (el) {
                var prev = el.volume;
                el.volume = 0;
                el.play().then(function () {
                    el.pause();
                    el.currentTime = 0;
                    el.volume = prev;
                }).catch(function () {
                    el.volume = prev;
                });
            });
            document.removeEventListener('click',      _unlockAudio);
            document.removeEventListener('keydown',    _unlockAudio);
            document.removeEventListener('touchstart', _unlockAudio);
        }
        document.addEventListener('click',      _unlockAudio);
        document.addEventListener('keydown',    _unlockAudio);
        document.addEventListener('touchstart', _unlockAudio);

        function _getSoundFile(key) {
            var filename = localStorage.getItem(key)
                || localStorage.getItem('notif_sound')  // legacy single-key fallback
                || _defaultSoundFile;
            return filename.includes('.') ? filename : _defaultSoundFile;
        }

        function _playAudioEl(audioEl, key) {
            if (!_soundEnabled) return;
            var file     = _getSoundFile(key);
            var expected = location.origin + '/sounds/' + file;
            if (audioEl.src !== expected) {
                audioEl.src = '/sounds/' + file;
                audioEl.load();
            }
            audioEl.currentTime = 0;
            audioEl.play().catch(function () {});
        }

        function playTicketSound()  { _playAudioEl(_audioTicket,  'notif_sound_ticket'); }
        function playChatSound()    { _playAudioEl(_audioChat,    'notif_sound_chat'); }
        function playStagingSound() { _playAudioEl(_audioStaging, 'notif_sound_staging'); }
        function playNotifSound()   { playTicketSound(); }

        function _applySoundUi() {
            var btn  = document.getElementById('soundToggleBtn');
            var icon = document.getElementById('soundToggleIcon');
            if (_soundEnabled) {
                if (icon) icon.className = 'fas fa-volume-up text-sm';
                if (btn)  { btn.classList.remove('text-gray-400'); btn.classList.add('text-red-700'); btn.title = 'Disable notification sound'; }
            } else {
                if (icon) icon.className = 'fas fa-volume-mute text-sm';
                if (btn)  { btn.classList.remove('text-red-700'); btn.classList.add('text-gray-400'); btn.title = 'Enable notification sound'; }
            }
        }

        function toggleNotifSound() {
            _soundEnabled = !_soundEnabled;
            localStorage.setItem('notif_sound_enabled', _soundEnabled ? 'true' : 'false');
            if (_soundEnabled) {
                // Play immediately as audio unlock + confirmation for user
                playNotifSound();
                if ('Notification' in window && Notification.permission === 'default') {
                    Notification.requestPermission();
                }
            }
            _applySoundUi();
        }

        // Sync button UI to persisted state on page load
        _applySoundUi();

        /* ---- browser (OS) notification ---- */
        function showOsNotification(title, body, url) {
            if (!('Notification' in window) || Notification.permission !== 'granted') return;
            var n = new Notification(title, {
                body: body,
                icon: '/images/logo_nobg.png',
                tag:  'ecosystem-notif-' + Date.now(),
            });
            n.onclick = function () {
                window.focus();
                if (url) window.location.href = url;
                n.close();
            };
        }

        /* ---- tab title badge ---- */
        function updateTabTitle(count) {
            document.title = count > 0 ? '(' + count + ') ' + _pageTitle : _pageTitle;
        }

        /* ---- handle new notifications (sound + OS notif) ---- */
        function handleNewNotifications() {
            // Bunyi hanya jika tab aktif dan bukan di halaman ticket show
            // (halaman ticket punya message polling sendiri yang handle sound)
            var onTicketPage = /^\/ticket\/\d+/.test(window.location.pathname);
            if (!document.hidden && !onTicketPage) {
                playTicketSound();
            }
            // OS notification — hanya saat tab background/minimize agar tidak mengganggu saat user sedang aktif
            if (document.hidden && 'Notification' in window && Notification.permission === 'granted') {
                fetch('/api/notifications?limit=1', { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.data && data.data.length > 0) {
                            var n = data.data[0];
                            var metaParts = [];
                            if (n.ticket_number) metaParts.push(n.ticket_number);
                            if (n.customer_name) metaParts.push(n.customer_name);
                            var body = (metaParts.length ? metaParts.join(' · ') + '\n' : '') + (n.preview || '');
                            showOsNotification(
                                getTitle(n),
                                body,
                                n.link || (n.ticket_id ? '/ticket/' + n.ticket_id : '/notifications')
                            );
                        }
                    })
                    .catch(function () {});
            }
        }

        /* ---- fetch immediately when tab becomes visible again ---- */
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) fetchUnreadCount();
        });

        window.toggleNotifSound = toggleNotifSound;

        /* ---- staging new-email cross-tab notification ---- */
        // Fires when user is on a page OTHER than staging and a new email arrives.
        // BroadcastChannel does not fire in the originating tab, so no double-play
        // from that side. localStorage storage event also only fires in OTHER tabs.
        function _handleStagingNewEmail(count) {
            playStagingSound();
            // OS notification hanya saat tab ini tidak terlihat user
            if (document.hidden) {
                showOsNotification(
                    'Email Baru · Ticket Validation',
                    count + ' email baru menunggu validasi',
                    '/staging'
                );
            }
        }
        // BroadcastChannel: primary mechanism (all modern browsers)
        var _stagingBcOk = false;
        try {
            var _stagingBc = new BroadcastChannel('ecosystem-staging');
            _stagingBc.onmessage = function (e) {
                if (e.data && e.data.type === 'new-staging-email') {
                    _handleStagingNewEmail(e.data.count);
                }
            };
            _stagingBcOk = true;
        } catch (_e) {}
        // localStorage fallback: ONLY when BroadcastChannel is not available
        // (prevents double-play since both would otherwise fire on the same tab)
        if (!_stagingBcOk) {
            window.addEventListener('storage', function (e) {
                if (e.key !== '_eco_staging_evt' || !e.newValue) return;
                try {
                    var d = JSON.parse(e.newValue);
                    if (d.type === 'new-staging-email' && Date.now() - d.ts < 5000) {
                        _handleStagingNewEmail(d.count);
                    }
                } catch (_ex) {}
            });
        }

        /* ---- badge count ---- */
        function fetchUnreadCount() {
            fetch('/api/notifications/unread-count', { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var badge = document.getElementById('bellBadge');
                    if (!badge) return;

                    // Badge now includes chat/message types (ticket replies, internal notes)
                    // alongside every other notification type.
                    var count = data.count || 0;
                    if (count > 0) {
                        badge.textContent = count > 99 ? '99+' : count;
                        badge.classList.remove('hidden');
                    } else {
                        badge.classList.add('hidden');
                    }
                    updateTabTitle(count);

                    // Split the delta so message-type increases play the chat sound while
                    // everything else keeps using the ticket sound + OS notification.
                    var msgCount    = data.message_sound_count || 0;
                    var nonMsgCount = count - msgCount;

                    if (_lastNonMsgCount !== null && nonMsgCount > _lastNonMsgCount) {
                        handleNewNotifications();
                    }
                    _lastNonMsgCount = nonMsgCount;

                    if (_lastMessageCount !== null && msgCount > _lastMessageCount) {
                        var onTicketPage = /^\/ticket\/\d+/.test(window.location.pathname);
                        if (!onTicketPage) { playChatSound(); }
                    }
                    _lastMessageCount = msgCount;

                    _lastUnreadCount = count;
                })
                .catch(function () {});
        }

        /* ---- icon config per type ---- */
        var TYPE_CFG = {
            timesheet_submitted:          { bg: '#f3e8ff', color: '#7c3aed', fa: 'fa-file-alt' },
            late_exception_submitted:     { bg: '#fef9c3', color: '#ca8a04', fa: 'fa-user-clock' },
            late_exception_pending_rpmo:  { bg: '#dbeafe', color: '#2563eb', fa: 'fa-user-clock' },
            late_exception_head_approved: { bg: '#dcfce7', color: '#16a34a', fa: 'fa-check-circle' },
            late_exception_head_rejected: { bg: '#fee2e2', color: '#dc2626', fa: 'fa-times-circle' },
            late_exception_approved:      { bg: '#dcfce7', color: '#16a34a', fa: 'fa-unlock' },
            late_exception_rejected:      { bg: '#fee2e2', color: '#dc2626', fa: 'fa-ban' },
            customer_mandays_canceled:    { bg: '#ffedd5', color: '#ea580c', fa: 'fa-times-circle' },
            customer_mandays_proposed:    { bg: '#dbeafe', color: '#2563eb', fa: 'fa-file-invoice' },
            resolution_days_proposed:     { bg: '#e0e7ff', color: '#4f46e5', fa: 'fa-users' },
            contract_end_reminder:        { bg: '#fef9c3', color: '#ca8a04', fa: 'fa-file-contract' },
            top_invoice_reminder:         { bg: '#dbeafe', color: '#2563eb', fa: 'fa-file-invoice-dollar' },
            ticket_member_added:          { bg: '#dcfce7', color: '#16a34a', fa: 'fa-user-plus' },
            ticket_member_removed:        { bg: '#fee2e2', color: '#dc2626', fa: 'fa-user-minus' },
            ticket_member_reactivated:    { bg: '#dbeafe', color: '#2563eb', fa: 'fa-user-check' },
            ticket_internal_note:         { bg: '#fef9c3', color: '#ca8a04', fa: 'fa-sticky-note' },
            ticket_reply:                 { bg: '#dbeafe', color: '#2563eb', fa: 'fa-reply' },
            customer_email_reply:         { bg: '#dcfce7', color: '#16a34a', fa: 'fa-envelope' }
        };
        var DEFAULT_CFG = { bg: '#fee2e2', color: '#b91c1c', fa: 'fa-at' };

        function getTitle(n) {
            switch (n.type) {
                case 'timesheet_submitted':          return (n.from_name || 'Consultant') + ' submitted a timesheet';
                case 'late_exception_submitted':     return 'Late Access Request from ' + (n.from_name || 'Employee');
                case 'late_exception_pending_rpmo':  return 'Late Access Request needs your review';
                case 'late_exception_head_approved': return 'Late Access Request approved by Head';
                case 'late_exception_head_rejected': return 'Late Access Request rejected by Head';
                case 'late_exception_approved':      return 'Late Access Request approved by RPMO';
                case 'late_exception_rejected':      return 'Late Access Request rejected by RPMO';
                case 'customer_mandays_canceled':    return 'Customer Mandays Proposal canceled';
                case 'customer_mandays_proposed':    return 'Customer Mandays — needs review';
                case 'resolution_days_proposed':     return 'Resolution Days — needs review';
                case 'contract_end_reminder':        return 'Contract deadline reminder';
                case 'top_invoice_reminder':         return 'Invoice submission due';
                case 'ticket_member_added':       return (n.from_name || 'Someone') + ' added you to a ticket';
                case 'ticket_member_removed':     return (n.from_name || 'Someone') + ' removed a member from a ticket';
                case 'ticket_member_reactivated': return (n.from_name || 'Someone') + ' re-added a member to a ticket';
                case 'ticket_internal_note':      return (n.from_name || 'Someone') + ' added an internal note';
                case 'ticket_reply':              return (n.from_name || 'Someone') + ' replied to a ticket';
                case 'customer_email_reply':      return (n.from_name || 'Customer') + ' replied via email';
                default: return (n.from_name || 'Someone') + ' mentioned you';
            }
        }

        function getUrl(n) {
            var base = n.link
                ? n.link
                : (n.type === 'timesheet_submitted'
                    ? '/calendar/timesheets'
                    : (n.ticket_id ? '/ticket/' + n.ticket_id : '/notifications'));
            if (n.message_id && base.indexOf('#') === -1) {
                base += '#msg-' + n.message_id;
            }
            return base;
        }

        /* ---- build one notification item using createElement (no Tailwind dependency) ---- */
        function buildItem(n) {
            var isUnread = !n.is_read;
            var cfg = TYPE_CFG[n.type] || DEFAULT_CFG;

            /* outer <a> */
            var a = document.createElement('a');
            a.href = getUrl(n);
            a.style.display         = 'flex';
            a.style.alignItems      = 'flex-start';
            a.style.gap             = '12px';
            a.style.padding         = '10px 16px';
            a.style.textDecoration  = 'none';
            a.style.borderBottom    = '1px solid #f3f4f6';
            a.style.transition      = 'background 0.15s';
            a.style.background      = isUnread ? '#fff1f2' : '#ffffff';
            a.addEventListener('mouseover', function () { a.style.background = isUnread ? '#ffe4e6' : '#f9fafb'; });
            a.addEventListener('mouseout',  function () { a.style.background = isUnread ? '#fff1f2' : '#ffffff'; });
            a.addEventListener('click', function () { markNotifReadBell(n.id); });

            /* icon circle */
            var circle = document.createElement('div');
            circle.style.width          = '32px';
            circle.style.height         = '32px';
            circle.style.borderRadius   = '50%';
            circle.style.background     = cfg.bg;
            circle.style.display        = 'flex';
            circle.style.alignItems     = 'center';
            circle.style.justifyContent = 'center';
            circle.style.flexShrink     = '0';
            circle.style.marginTop      = '2px';

            var ico = document.createElement('i');
            ico.className   = 'fas ' + cfg.fa;
            ico.style.color    = cfg.color;
            ico.style.fontSize = '12px';
            circle.appendChild(ico);

            /* text container */
            var textBox = document.createElement('div');
            textBox.style.flex     = '1';
            textBox.style.minWidth = '0';
            textBox.style.overflow = 'hidden';

            var pTitle = document.createElement('p');
            pTitle.style.margin        = '0';
            pTitle.style.fontSize      = '12px';
            pTitle.style.fontWeight    = '600';
            pTitle.style.color         = '#111827';
            pTitle.style.overflow      = 'hidden';
            pTitle.style.whiteSpace    = 'nowrap';
            pTitle.style.textOverflow  = 'ellipsis';
            pTitle.textContent = getTitle(n);

            if (n.ticket_number || n.customer_name) {
                var pMeta = document.createElement('p');
                pMeta.style.margin       = '2px 0 0';
                pMeta.style.fontSize     = '11px';
                pMeta.style.fontWeight   = '500';
                pMeta.style.color        = '#374151';
                pMeta.style.overflow     = 'hidden';
                pMeta.style.whiteSpace   = 'nowrap';
                pMeta.style.textOverflow = 'ellipsis';
                var parts = [];
                if (n.ticket_number) parts.push(n.ticket_number);
                if (n.customer_name) parts.push(n.customer_name);
                pMeta.textContent = parts.join(' · ');
                textBox.appendChild(pMeta);
            }

            var pPreview = document.createElement('p');
            pPreview.style.margin       = '2px 0 0';
            pPreview.style.fontSize     = '11px';
            pPreview.style.color        = '#6b7280';
            pPreview.style.overflow     = 'hidden';
            pPreview.style.whiteSpace   = 'nowrap';
            pPreview.style.textOverflow = 'ellipsis';
            pPreview.textContent = n.preview || '';

            var pTime = document.createElement('p');
            pTime.style.margin    = '3px 0 0';
            pTime.style.fontSize  = '10px';
            pTime.style.color     = '#9ca3af';
            pTime.textContent = n.created_at || '';

            textBox.appendChild(pTitle);
            textBox.appendChild(pPreview);
            textBox.appendChild(pTime);

            /* unread dot */
            a.appendChild(circle);
            a.appendChild(textBox);
            if (isUnread) {
                var dot = document.createElement('span');
                dot.style.width        = '8px';
                dot.style.height       = '8px';
                dot.style.background   = '#ef4444';
                dot.style.borderRadius = '50%';
                dot.style.flexShrink   = '0';
                dot.style.marginTop    = '4px';
                dot.style.display      = 'block';
                a.appendChild(dot);
            }

            return a;
        }

        function setListMessage(list, msg) {
            list.innerHTML = '';
            var d = document.createElement('div');
            d.style.padding    = '24px 16px';
            d.style.textAlign  = 'center';
            d.style.fontSize   = '12px';
            d.style.color      = '#9ca3af';
            d.textContent = msg;
            list.appendChild(d);
        }

        /* ---- load notifications into bell dropdown ---- */
        function loadBellNotifications() {
            var list = document.getElementById('bellNotifList');
            if (!list) return;
            setListMessage(list, 'Loading...');
            fetch('/api/notifications?limit=10', { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    list.innerHTML = '';
                    if (!data.success || !data.data || !data.data.length) {
                        setListMessage(list, 'No notifications');
                        return;
                    }
                    data.data.forEach(function (n) { list.appendChild(buildItem(n)); });
                })
                .catch(function () { setListMessage(list, 'Failed to load'); });
        }

        /* ---- fire-and-forget mark-as-read (called on click, navigation proceeds normally) ----
           Marks this notification read; the backend also marks every other unread
           notification for the same ticket read in one go, so the badge can drop by more
           than 1 — re-fetch the real count instead of guessing with a client-side decrement.
           Still visible (as read) on the full /notifications page. */
        function markNotifReadBell(id) {
            fetch('/api/notifications/' + id + '/read', {
                method: 'PUT',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf }
            }).then(function () {
                fetchUnreadCount();
            }).catch(function () {});
        }

        /* ---- mark all read (does NOT delete — read items remain on the full page) ---- */
        function markAllNotificationsRead() {
            var list = document.getElementById('bellNotifList');
            if (list) setListMessage(list, 'No notifications');
            var badge = document.getElementById('bellBadge');
            if (badge) badge.classList.add('hidden');
            fetch('/api/notifications/read-all', { method: 'PUT', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': csrf } })
                .catch(function () {});
        }

        /* ---- expose globals ---- */
        window.toggleBellDropdown       = toggleBellDropdown;
        window.markAllNotificationsRead = markAllNotificationsRead;
        window.fetchUnreadCount         = fetchUnreadCount;
        window.playNotifSound           = playNotifSound;
        window.playTicketSound          = playTicketSound;
        window.playChatSound            = playChatSound;
        window.playStagingSound         = playStagingSound;

        /* ---- start ---- */
        fetchUnreadCount();
        setInterval(fetchUnreadCount, 15000);
    })();
    </script>

    <!-- ==================== WEB PUSH SERVICE WORKER ==================== -->
    <script>
    (function () {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

        var VAPID_PUBLIC_KEY = '{{ config("webpush.vapid_public_key") }}';

        function urlBase64ToUint8Array(base64String) {
            var padding = '='.repeat((4 - base64String.length % 4) % 4);
            var base64  = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            var raw     = window.atob(base64);
            var output  = new Uint8Array(raw.length);
            for (var i = 0; i < raw.length; ++i) output[i] = raw.charCodeAt(i);
            return output;
        }

        function subscribeToPush(registration) {
            registration.pushManager.getSubscription().then(function (existing) {
                if (existing) {
                    sendSubscriptionToServer(existing);
                    return;
                }
                registration.pushManager.subscribe({
                    userVisibleOnly:      true,
                    applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY),
                }).then(function (sub) {
                    sendSubscriptionToServer(sub);
                }).catch(function (err) {
                    console.warn('[WebPush] Subscribe failed:', err);
                });
            });
        }

        function sendSubscriptionToServer(sub) {
            var json = sub.toJSON();
            var csrf = document.querySelector('meta[name="csrf-token"]');
            fetch('/api/push/subscribe', {
                method:      'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type':  'application/json',
                    'X-CSRF-TOKEN':  csrf ? csrf.content : '',
                    'Accept':        'application/json',
                },
                body: JSON.stringify({
                    endpoint:    json.endpoint,
                    keys: {
                        p256dh: json.keys.p256dh,
                        auth:   json.keys.auth,
                    },
                }),
            }).catch(function () {});
        }

        /* Register Service Worker */
        navigator.serviceWorker.register('/sw.js').then(function (registration) {
            /* Listen for push messages forwarded from SW (to play custom sound) */
            navigator.serviceWorker.addEventListener('message', function (event) {
                if (event.data && event.data.type === 'PUSH_RECEIVED') {
                    var payload = event.data.payload || {};
                    var msgTypes = ['ticket_reply', 'ticket_internal_note'];
                    /* Chat/message types use chat sound; others use ticket/alert sound. */
                    if (msgTypes.includes(payload.type)) {
                        if (typeof window.playChatSound === 'function') window.playChatSound();
                    } else {
                        if (typeof window.playTicketSound === 'function') window.playTicketSound();
                    }
                    if (typeof window.fetchUnreadCount === 'function') {
                        window.fetchUnreadCount();
                    }
                }
            });

            /* Subscribe to push once notification permission is granted */
            if (Notification.permission === 'granted') {
                subscribeToPush(registration);
            }
        }).catch(function (err) {
            console.warn('[SW] Registration failed:', err);
        });

        function unsubscribeFromPush() {
            navigator.serviceWorker.ready.then(function (registration) {
                registration.pushManager.getSubscription().then(function (sub) {
                    if (!sub) return;
                    var endpoint = sub.endpoint;
                    sub.unsubscribe().then(function () {
                        var csrf = document.querySelector('meta[name="csrf-token"]');
                        fetch('/api/push/unsubscribe', {
                            method:      'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf ? csrf.content : '',
                                'Accept':       'application/json',
                            },
                            body: JSON.stringify({ endpoint: endpoint }),
                        }).catch(function () {});
                    });
                });
            });
        }

        /* Wrap toggleNotifSound: subscribe on enable, unsubscribe on disable */
        var _origToggle = window.toggleNotifSound;
        window.toggleNotifSound = function () {
            if (typeof _origToggle === 'function') _origToggle();
            /* Read the new state from localStorage (set by _origToggle) */
            var nowEnabled = localStorage.getItem('notif_sound_enabled') !== 'false';
            if (nowEnabled) {
                if (Notification.permission === 'granted') {
                    navigator.serviceWorker.ready.then(subscribeToPush);
                } else if (Notification.permission === 'default') {
                    Notification.requestPermission().then(function (perm) {
                        if (perm === 'granted') {
                            navigator.serviceWorker.ready.then(subscribeToPush);
                        }
                    });
                }
            } else {
                unsubscribeFromPush();
            }
        };
    })();
    </script>

    @if(!config('app.debug'))
    <script>
        (function () {
            var noop = function () {};
            console.log   = noop;
            console.debug = noop;
            console.info  = noop;
        })();
    </script>
    @endif

    {{-- Global confirm modal — replaces browser native confirm() everywhere.
         Usage: if (await showConfirm('msg', 'title', 'danger')) { ... } --}}
    @include('partials.confirm-modal')
</body>
</html>
