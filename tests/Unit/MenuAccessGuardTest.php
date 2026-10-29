<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Izin halaman dan fungsi harus lewat Control Center > Menu Access (ChecksMenuAccess::menuAllowed),
 * bukan perbandingan RoleId di controller. Daftar role di kode tidak terlihat administrator, sehingga
 * sebuah menu bisa tampil di sidebar tetapi halamannya tetap menolak (403).
 *
 * Tes ini menjaga controller yang sudah dipindahkan agar tidak kembali memakai pengecekan role.
 */
class MenuAccessGuardTest extends TestCase
{
    private const CONVERTED = [
        'ActivityLogController',
        'AuditLogController',
        'AdminSessionController',
        'AdminJobController',
        'ScheduleMonitorController',
        'SecurityCenterController',
        'PeriodManagementController',
        'ReportingController',
    ];

    /** @dataProvider converted */
    public function test_converted_controllers_do_not_compare_role_ids_for_access(string $controller): void
    {
        $code = (string) file_get_contents(dirname(__DIR__, 2) . "/app/Http/Controllers/{$controller}.php");

        $this->assertStringContainsString('ChecksMenuAccess', $code, "$controller harus memakai ChecksMenuAccess");
        // Menyebut role administrator hanya boleh untuk menyaring penerima notifikasi, bukan untuk memutuskan izin.
        $this->assertDoesNotMatchRegularExpression('/(===|!==)\s*RoleId::EC_ADMINISTRATOR|RoleId::EC_ADMINISTRATOR->value\s*(===|!==)|\[\s*RoleId::EC_ADMINISTRATOR/', $code, "$controller membandingkan role administrator");
        $this->assertStringNotContainsString('hasAnyRole(', $code, "$controller memakai hasAnyRole untuk izin");
        $this->assertDoesNotMatchRegularExpression('/role\.id\'\)\s*(===|!==)/', $code, "$controller membandingkan role utama sesi");
    }

    public static function converted(): array
    {
        return array_map(fn ($c) => [$c], self::CONVERTED);
    }

    public function test_ticket_validation_access_comes_from_its_menu(): void
    {
        $code = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Http/Controllers/StagingTicketController.php');

        $this->assertStringContainsString("menuAllowed('tickets.staging')", $code);
        $this->assertStringNotContainsString('RoleId::STAGING_GROUP', $code);
    }

    public function test_every_menu_permission_used_by_period_management_is_registered_in_the_seeder(): void
    {
        $seeder = (string) file_get_contents(dirname(__DIR__, 2) . '/database/seeders/MenuSeeder.php');

        foreach (['rpmo.periods.manage-global', 'rpmo.periods.manage-project', 'rpmo.periods.manage-support', 'reporting.validation.view-all'] as $slug) {
            $this->assertStringContainsString("'slug' => '$slug'", $seeder, "$slug belum ada di MenuSeeder");
        }
    }
}
