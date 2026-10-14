{{--
    Tab hub sisi karyawan (ESS): "My Cash Advance" | "My Cash Advance Report".
    Karyawan yang menerima uang muka juga yang mempertanggungjawabkannya, jadi keduanya
    satu halaman bertab — bukan dua entri sidebar. Sisi Finance memakai
    components/hub-tabs.blade.php.

    Tab CAR mengikuti sakelar ESS `advance_payment_car` (Management → ESS Settings), dan
    bilah tab tidak dirender bila hanya satu tab yang boleh dilihat.
--}}
@php
    $myCaEss = \App\Http\Controllers\Management\EssSettingsController::getEssSettings();
    $hubTabs = [];

    if (!empty($myCaEss['advance_payment_ca'])) {
        $hubTabs[] = [
            'label' => 'My Cash Advance',
            'icon'  => 'hand-holding-usd',
            'route' => 'general.my-cash-advance.index',
            'is'    => 'general/my-cash-advance',
            'gate'  => 'general.my-cash-advance',
        ];
    }

    if (!empty($myCaEss['advance_payment_car'])) {
        $hubTabs[] = [
            'label' => 'My Cash Advance Report',
            'icon'  => 'file-contract',
            'route' => 'general.my-cash-advance-report.index',
            'is'    => 'general/my-cash-advance-report',
            'gate'  => 'general.my-cash-advance-report',
        ];
    }
@endphp
@include('partials.hub-tabs', ['hubTabs' => $hubTabs, 'hubHideSingle' => true])
