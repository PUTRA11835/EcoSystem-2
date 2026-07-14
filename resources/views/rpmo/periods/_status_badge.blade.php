@php
$isDark = (session('user_preferences')['theme'] ?? 'light') === 'dark';
// Teks dicerahkan di dark mode agar tetap kontras di atas latar rgba tipis.
$cfg = match($status ?? 'not_open') {
    'open'     => ['background:rgba(34,197,94,.15);color:' . ($isDark ? '#86efac' : '#15803d') . ';border:1px solid rgba(34,197,94,.3)', 'Open'],
    'closed'   => ['background:rgba(107,114,128,.15);color:' . ($isDark ? '#cbd5e1' : '#4b5563') . ';border:1px solid rgba(107,114,128,.3)', 'Closed'],
    default    => ['background:rgba(234,179,8,.15);color:' . ($isDark ? '#fcd34d' : '#a16207') . ';border:1px solid rgba(234,179,8,.3)', 'Not Open'],
};
@endphp
<span style="{{ $cfg[0] }};display:inline-flex;align-items:center;padding:2px 10px;border-radius:9999px;font-size:11px;font-weight:600;white-space:nowrap;">
    {{ $cfg[1] }}
</span>
