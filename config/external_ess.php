<?php

/**
 * Menu self-service (ESS) untuk konsultan eksternal.
 *
 * Setiap karyawan aktif otomatis mendapat menu self-service karyawan (presensi, lembur, reimbursement, paystub, …) — itu tak relevan
 * bagi konsultan kontrak. Untuk karyawan berjenis External (employee_basic_data.employee_type = 'External'):
 *
 *   hidden_nav              kunci item ESS di sidebar (EssSettingsController::ESS_ITEMS) yang disembunyikan
 *   blocked_default_slugs   slug yang BIASANYA otomatis diberikan ke semua karyawan tetapi tidak untuk konsultan; hak yang
 *                           diberikan EKSPLISIT lewat role (Menu Access) tetap berlaku
 *
 * Karyawan Internal tidak terpengaruh sama sekali. Sengaja TIDAK disembunyikan: My Profile, My Leave & Permit, My KPI,
 * My Timesheet (timesheet internal dipakai konsultan Delivery untuk tiket/proyek) dan Calendar — ubah daftar di bawah bila perlu.
 */
return [
    'hidden_nav' => [
        'my_attendance', 'overtime', 'expense_reimbursement', 'paystub', 'purchase_request',
        'advance_payment_ca', 'advance_payment_car', 'loans', 'my_letter_requests',
    ],

    'blocked_default_slugs' => [
        'general.my-attendance', 'general.my-overtime', 'general.my-reimbursement', 'general.my-purchase-request',
    ],
];
