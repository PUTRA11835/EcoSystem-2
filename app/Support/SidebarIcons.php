<?php

namespace App\Support;

/**
 * Ikon sidebar satu keluarga (Tabler Icons outline, MIT) — dipilih per JUDUL menu, bukan per bentuk ikon lama,
 * sehingga menu yang berbeda tak lagi berbagi ikon sama. Dipakai oleh partials/sidebar.blade.php setelah tiap
 * seksi dirakit: <i class="fas fa-…"> di dalam .nav-icon diganti <svg class="sb-ico"><use href="#ti-…"/></svg>.
 *
 * Aman terhadap hal yang tak dikenal: judul tak terdaftar → dicoba lewat kelas Font Awesome-nya → bila tetap tak
 * ada, ikon Font Awesome ASLI dibiarkan. Jadi menu baru / grup ESS buatan admin tidak pernah kehilangan ikon.
 * File ini DIBANGKITKAN bersama partials/sidebar-icons.blade.php (daftar yang sama) — sunting keduanya lewat
 * pembangkitnya, bukan tangan.
 */
final class SidebarIcons
{
    /** judul menu (teks .nav-text) → nama ikon Tabler */
    private const BY_LABEL = [
        'Dashboard' => 'layout-dashboard',
        'My Profile' => 'user-circle',
        'My Attendance' => 'fingerprint',
        'My Leave & Permit' => 'calendar-off',
        'My Activity' => 'stack-2',
        'Overtime' => 'clock-plus',
        'My Reimbursement' => 'receipt-2',
        'Paystub' => 'file-dollar',
        'Purchase Request' => 'shopping-cart',
        'Cash Advance' => 'cash-banknote',
        'Cash Advance Report' => 'report-money',
        'My Loans' => 'building-bank',
        'My KPI' => 'target-arrow',
        'My Timesheet' => 'clock',
        'Calendar' => 'calendar-event',
        'Employee Data' => 'users',
        'Recruitment' => 'user-search',
        'Offering Letter' => 'file-certificate',
        'Onboarding' => 'user-check',
        'Attendance' => 'clock-check',
        'Leave & Permit' => 'calendar-off',
        'Overtime Management' => 'clock-plus',
        'KPI Evaluation' => 'report-analytics',
        'Letter Templates' => 'template',
        'Reimbursement Management' => 'receipt-2',
        'Purchase Request Management' => 'shopping-cart',
        'Cash Advance (CA)' => 'cash-banknote',
        'Cash Advance Report (CAR)' => 'report-money',
        'Financial' => 'coins',
        'Ticket' => 'ticket',
        'My Tasks' => 'checklist',
        'Consultant Workload' => 'users-group',
        'Ticket Validation' => 'clipboard-check',
        'Delivery' => 'truck-delivery',
        'Project' => 'layout-kanban',
        'Support' => 'headset',
        'SLA' => 'stopwatch',
        'SLA Report' => 'chart-bar',
        'SLA Config' => 'settings',
        'RPMO' => 'settings-automation',
        'Overview' => 'gauge',
        'Period Management' => 'calendar-stats',
        'AI Assistant' => 'robot',
        'AI Research' => 'report-search',
        'Business Partner' => 'building',
        'Business Dev' => 'briefcase',
        'Legal' => 'scale',
        'Project Reports' => 'presentation-analytics',
        'Support Reports' => 'file-analytics',
        'Collection Outlook' => 'cash-banknote',
        'Collection Outlook (Support)' => 'cash-banknote',
        'Consultant Assignment' => 'user-star',
        'MD Validation' => 'circle-check',
        'MD Recap' => 'table',
        'Ticketing Overview' => 'list-details',
        'Ticket by Module' => 'puzzle',
        'Log Shifting' => 'history',
        'Resolution Days' => 'calendar-time',
        'Diagram Report' => 'chart-pie',
        'Management' => 'user-shield',
        'Role' => 'id-badge-2',
        'Menu Access' => 'key',
        'Menu List' => 'sitemap',
        'ESS Settings' => 'adjustments',
        'Holidays' => 'beach',
        'Hidden Tickets' => 'eye-off',
        'Approval Workflow' => 'rubber-stamp',
        'Control Center' => 'shield-lock',
        'Activity Log' => 'activity',
        'Login Log' => 'login',
        'Audit Log' => 'file-search',
        'AI Settings' => 'cpu',
        'Active Sessions' => 'devices',
        'Failed Jobs' => 'alert-triangle',
        'Backup & Export' => 'database-export',
        'Notif Sounds' => 'volume',
        'Settings' => 'settings',
        'Employee' => 'users',
        'Basic Data' => 'id',
        'Address' => 'map-pin',
        'Identification' => 'license',
        'Family' => 'friends',
        'Education' => 'school',
        'Qualification' => 'certificate',
        'Contract' => 'file-text',
        'Bank Account' => 'building-bank',
        'Basic Payment' => 'cash',
        'Attachment' => 'paperclip',
    ];

    /** kelas Font Awesome → nama ikon Tabler (cadangan) */
    private const BY_FA = [
        'fa-layer-group' => 'stack-2',
        'fa-home' => 'home',
        'fa-user-circle' => 'user-circle',
        'fa-users' => 'users',
        'fa-user' => 'user',
        'fa-clock' => 'clock',
        'fa-calendar-alt' => 'calendar-event',
        'fa-calendar-check' => 'calendar-check',
        'fa-hand-holding-usd' => 'cash-banknote',
        'fa-file-invoice-dollar' => 'file-dollar',
        'fa-receipt' => 'receipt-2',
        'fa-shopping-cart' => 'shopping-cart',
        'fa-chart-line' => 'chart-line',
        'fa-chart-bar' => 'chart-bar',
        'fa-cog' => 'settings',
        'fa-briefcase' => 'briefcase',
        'fa-tasks' => 'checklist',
        'fa-folder' => 'folder',
        'fa-file' => 'file',
        'fa-star' => 'star',
        'fa-bell' => 'bell',
        'fa-envelope' => 'mail',
    ];

    /** Ganti ikon Font Awesome di dalam <span class="nav-icon"> pada potongan HTML sidebar. */
    public static function apply(string $html): string
    {
        $pattern = '~(<span class="nav-icon[^"]*"[^>]*>\s*)<i\s+class="[^"]*?\b(fa-[a-z0-9-]+)[^"]*"\s*></i>(\s*</span>\s*<span class="nav-text[^"]*">\s*([^<]*?)\s*</span>)?~';

        return preg_replace_callback($pattern, function (array $m): string {
            $label = isset($m[4]) ? trim(preg_replace('~\s+~', ' ', html_entity_decode($m[4]))) : '';
            $name = self::BY_LABEL[$label] ?? self::BY_FA[$m[2]] ?? null;
            if ($name === null) {
                return $m[0]; // tak dikenal: biarkan ikon asli
            }

            return $m[1] . '<svg class="sb-ico" aria-hidden="true" focusable="false"><use href="#ti-' . $name . '"/></svg>' . ($m[3] ?? '');
        }, $html) ?? $html;
    }
}
