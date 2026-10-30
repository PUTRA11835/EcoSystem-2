-- ============================================================================
-- SQL UNTUK phpMyAdmin (database aplikasi di cPanel) — 28 Okt 2026
-- User Guide Editor (Control Center > User Guide Editor).
-- CADANGKAN dulu (Export). Jalankan BLOK demi BLOK, periksa hasil tiap blok. Aman dijalankan ulang (tiap blok memeriksa dulu).
-- Memerlukan MySQL 5.7+ / MariaDB 10.2+ (kolom bertipe JSON). Bila nanti Anda menjalankan migrasi Laravel,
-- migrasi 2026_10_28_000001/000002 dilewati karena sudah tercatat di BLOK 5.
-- Tidak ada data panduan yang diubah: isi bawaan tetap berasal dari berkas resources/guide.
-- ============================================================================

-- ── BLOK 1: tabel penyimpan hasil sunting (kosong pada awalnya) ──
CREATE TABLE IF NOT EXISTS `user_guide_topics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `locale` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
  `chapter` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT '100',
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `requires` json DEFAULT NULL,
  `body` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_on` date DEFAULT NULL,
  `base_hash` char(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `edited_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_guide_topics_key_locale_unique` (`key`,`locale`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── BLOK 2: menu "User Guide Editor" di bawah Control Center ──
INSERT INTO menu (parent_id, name, slug, type, route_name, icon, order_seq, is_active, created_at, updated_at)
SELECT p.id, 'User Guide Editor', 'control-center.user-guide', 'page', 'admin.user-guide.index', 'fa-book', 12, 1, NOW(), NOW()
FROM menu p
WHERE p.slug = 'control-center'
  AND NOT EXISTS (SELECT 1 FROM menu x WHERE x.slug = 'control-center.user-guide');

-- ── BLOK 3: menu baru aktif HANYA untuk EC Administrator (role lain diberi lewat Control Center > Menu Access) ──
INSERT INTO role_menu (role_id, menu_id, can_view, can_create, can_edit, can_delete, created_at, updated_at)
SELECT r.id, m.id, 1, 0, 0, 0, NOW(), NOW()
FROM employee_role r
JOIN menu m ON m.slug = 'control-center.user-guide'
WHERE r.name = 'EC Administrator'
  AND NOT EXISTS (SELECT 1 FROM role_menu x WHERE x.role_id = r.id AND x.menu_id = m.id);

-- ── BLOK 4: buang cache izin (disimpan di tabel cache selama 60 menit) agar menu langsung muncul ──
DELETE FROM cache WHERE `key` LIKE '%perm_slugs_%' OR `key` LIKE '%perm_matrix_%';

-- ── BLOK 5: catat migrasi supaya Laravel tidak mengulanginya ──
INSERT INTO migrations (migration, batch)
SELECT '2026_10_28_000001_create_user_guide_topics_table', (SELECT COALESCE(MAX(b.batch), 0) + 1 FROM (SELECT batch FROM migrations) b)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_10_28_000001_create_user_guide_topics_table');
INSERT INTO migrations (migration, batch)
SELECT '2026_10_28_000002_add_user_guide_editor_menu', (SELECT COALESCE(MAX(b.batch), 0) FROM (SELECT batch FROM migrations) b)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_10_28_000002_add_user_guide_editor_menu');

-- ── PEMERIKSAAN (hanya membaca) ──
-- Harus: 1 baris menu (parent = Control Center), 1 baris role_menu (EC Administrator, can_view = 1), tabel kosong, 2 baris migrasi.
SELECT m.id, m.slug, m.name, p.slug AS parent_slug, m.route_name FROM menu m LEFT JOIN menu p ON p.id = m.parent_id WHERE m.slug = 'control-center.user-guide';
SELECT r.name, rm.can_view FROM role_menu rm JOIN employee_role r ON r.id = rm.role_id JOIN menu m ON m.id = rm.menu_id WHERE m.slug = 'control-center.user-guide';
SELECT COUNT(*) AS jumlah_baris_sunting FROM user_guide_topics;
SELECT migration, batch FROM migrations WHERE migration LIKE '2026_10_28_%';
