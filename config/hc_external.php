<?php

/**
 * Penandatangan dokumen External (hasil UAT 26 Okt 2026). Diidentifikasi lewat ECI (stabil antar-instalasi), bukan ID baris.
 *
 *   contract  kontrak konsultan (External → External Contract): Pak Arbin (K19002) atau Pak Titus (S14027); bawaan Arbin
 *   letters   Assignment Letter, Employment Certificate, Reference Letter: Pak Arbin
 *
 * Orang yang disebut HARUS juga terdaftar di Letter Templates → Settings → Signers (migrasi 2026_10_26_100001 menambahkannya).
 * Bila tak seorang pun dari daftar itu memenuhi syarat (belum jadi penandatangan / tak ditemukan), pembatasan dilewati dan semua
 * penandatangan terdaftar tetap bisa dipilih — supaya pembuatan dokumen tidak pernah terkunci oleh konfigurasi ini.
 */
return [
    'signers' => [
        'contract' => ['eci' => ['K19002', 'S14027'], 'default' => 'K19002'],
        'letters'  => ['eci' => ['K19002'],            'default' => 'K19002'],
    ],
];
