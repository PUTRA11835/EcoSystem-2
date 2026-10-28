<?php

/**
 * User Guide — bab (chapter) panduan pengguna. Isi panduan = berkas Markdown di resources/guide/{en,id}/*.md
 * (lihat resources/guide/_AUTHORING.md). Bab hanya mengelompokkan topik; topik yang tak boleh dilihat pengguna
 * tidak dikirim sama sekali, dan bab tanpa topik terlihat tidak tampil.
 *
 * Urutan bab = urutan di sini. `title` per bahasa; istilah menu/tombol di dalamnya tetap bahasa Inggris (sama dengan layar aplikasi).
 */
return [
    'locales' => ['en' => 'English', 'id' => 'Bahasa Indonesia'],
    'default_locale' => 'en',

    'chapters' => [
        'getting-started' => ['icon' => 'compass',    'title' => ['en' => 'Getting started',           'id' => 'Memulai']],
        'my-workspace'    => ['icon' => 'smart-home', 'title' => ['en' => 'My Workspace',              'id' => 'My Workspace']],
        'human-capital'   => ['icon' => 'address-book', 'title' => ['en' => 'Human Capital',           'id' => 'Human Capital']],
        'external'        => ['icon' => 'briefcase',  'title' => ['en' => 'External consultants',      'id' => 'Konsultan External']],
        'finance'         => ['icon' => 'wallet',     'title' => ['en' => 'Finance',                   'id' => 'Finance']],
        'work-service'    => ['icon' => 'tools',      'title' => ['en' => 'Work & Service',            'id' => 'Work & Service']],
        'business-legal'  => ['icon' => 'building-skyscraper', 'title' => ['en' => 'Business & Legal', 'id' => 'Business & Legal']],
        'reporting'       => ['icon' => 'chart-histogram', 'title' => ['en' => 'Reporting',            'id' => 'Reporting']],
        'administration'  => ['icon' => 'shield-cog', 'title' => ['en' => 'Administration',            'id' => 'Administration']],
        'reference'       => ['icon' => 'book',       'title' => ['en' => 'Workflows & reference',     'id' => 'Alur kerja & referensi']],
    ],

    // Teks tetap pada tampilan (bukan isi panduan).
    'ui' => [
        'en' => [
            'title' => 'User Guide', 'subtitle' => 'Step-by-step help for the features you can use, in the right order.',
            'toc' => 'Table of Contents', 'toc_hint' => 'Search, or pick a chapter and topic to jump straight to it.', 'expand_all' => 'Expand all', 'collapse_all' => 'Collapse all', 'chapter' => 'Chapter', 'topic_count' => 'topics', 'search_toc' => 'Search the guide…', 'no_results' => 'Nothing found. Try another word.',
            'results' => 'results', 'download_pdf' => 'Download PDF', 'updated' => 'Updated', 'language' => 'Language', 'tailored' => 'This guide is tailored to your access',
            'topics_of' => 'topics', 'on_this_page' => 'On this topic', 'back_top' => 'Back to top',
            'untranslated' => 'This topic is not available in your language yet; the English version is shown.', 'related' => 'Related topics', 'empty' => 'No topics are available for your access yet.',
            'note' => 'Note', 'tip' => 'Tip', 'important' => 'Important', 'warning' => 'Warning', 'topic_updated' => 'Topic updated', 'print' => 'Print', 'cover_for' => 'Prepared for',
        ],
        'id' => [
            'title' => 'User Guide', 'subtitle' => 'Panduan langkah demi langkah untuk fitur yang dapat Anda gunakan, sesuai urutan kerja yang benar.',
            'toc' => 'Daftar Isi', 'toc_hint' => 'Cari, atau pilih bab dan topik untuk langsung menuju ke sana.', 'expand_all' => 'Buka semua', 'collapse_all' => 'Tutup semua', 'chapter' => 'Bab', 'topic_count' => 'topik', 'search_toc' => 'Cari di panduan…', 'no_results' => 'Tidak ditemukan. Coba kata lain.',
            'results' => 'hasil', 'download_pdf' => 'Unduh PDF', 'updated' => 'Diperbarui', 'language' => 'Bahasa', 'tailored' => 'Panduan ini disesuaikan dengan akses Anda',
            'topics_of' => 'topik', 'on_this_page' => 'Di topik ini', 'back_top' => 'Kembali ke atas',
            'untranslated' => 'Topik ini belum tersedia dalam bahasa Anda; versi bahasa Inggris ditampilkan.', 'related' => 'Topik terkait', 'empty' => 'Belum ada topik yang tersedia untuk akses Anda.',
            'note' => 'Catatan', 'tip' => 'Tips', 'important' => 'Penting', 'warning' => 'Peringatan', 'topic_updated' => 'Topik diperbarui', 'print' => 'Cetak', 'cover_for' => 'Disiapkan untuk',
        ],
    ],
];
