<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MenuSeeder extends Seeder
{
    // Role IDs sesuai tabel employee_role
    const ADMIN    = 1; // EC Administrator
    const EMPLOYEE = 2; // Delivery Support User
    const INTERN   = 3; // EC User
    const HOP      = 4; // Delivery Project Head
    const HOS      = 5; // Delivery Support Head
    const HELPDESK = 6; // Delivery Support Service Helpdesk
    const RPMO     = 7; // Delivery RPMO Head
    const MANAGER  = 14; // Delivery Support Manager

    public function run(): void
    {
        $now = Carbon::now();

        // ── Struktur menu (urutan: parent harus sebelum child) ───────────────────
        // parent_slug null = root. function type = tombol/aksi dalam halaman.
        $menus = [
            // ── Home ────────────────────────────────────────────────────────────
            ['slug' => 'dashboard',                    'name' => 'Home',                    'type' => 'page',     'parent_slug' => null,          'route_name' => 'dashboard',                    'icon' => 'fa-home',              'order_seq' => 1],

            // ── Calendar ─────────────────────────────────────────────────────────
            ['slug' => 'calendar',                     'name' => 'Calendar',                'type' => 'group',    'parent_slug' => null,          'route_name' => null,                           'icon' => 'fa-calendar-alt',      'order_seq' => 2],
            ['slug' => 'calendar.events',              'name' => 'Events',                  'type' => 'page',     'parent_slug' => 'calendar',    'route_name' => 'calendar.events',              'icon' => null,                   'order_seq' => 1],
            ['slug' => 'calendar.events.create',       'name' => 'Create Event',            'type' => 'function', 'parent_slug' => 'calendar.events', 'route_name' => null,                      'icon' => null,                   'order_seq' => 1],
            ['slug' => 'calendar.timesheets',          'name' => 'Timesheets',              'type' => 'page',     'parent_slug' => 'calendar',    'route_name' => 'calendar.timesheets',          'icon' => null,                   'order_seq' => 2],
            ['slug' => 'timesheet.create',             'name' => 'Create Timesheet',        'type' => 'function', 'parent_slug' => 'calendar.timesheets', 'route_name' => null,                  'icon' => null,                   'order_seq' => 1],

            // ── Reporting ─────────────────────────────────────────────────────────
            ['slug' => 'reporting',                    'name' => 'Reporting',               'type' => 'group',    'parent_slug' => null,          'route_name' => null,                           'icon' => 'fa-chart-bar',         'order_seq' => 3],
            ['slug' => 'reporting.validation',         'name' => 'MD Validation',           'type' => 'page',     'parent_slug' => 'reporting',   'route_name' => 'reporting',                    'icon' => null,                   'order_seq' => 1],
            ['slug' => 'reporting.export-excel',       'name' => 'Export Excel',            'type' => 'function', 'parent_slug' => 'reporting.validation', 'route_name' => null,                 'icon' => null,                   'order_seq' => 1],
            ['slug' => 'reporting.close-period',       'name' => 'Close Period',            'type' => 'function', 'parent_slug' => 'reporting.validation', 'route_name' => null,                 'icon' => null,                   'order_seq' => 2],
            ['slug' => 'reporting.md-recap',           'name' => 'MD Recap',                'type' => 'page',     'parent_slug' => 'reporting',   'route_name' => 'reporting.md-recap',           'icon' => null,                   'order_seq' => 2],
            ['slug' => 'reporting.collection-outlook', 'name' => 'Collection Outlook',      'type' => 'page',     'parent_slug' => 'reporting',   'route_name' => 'reporting.collection-outlook', 'icon' => null,                   'order_seq' => 3],

            // ── Master ────────────────────────────────────────────────────────────
            ['slug' => 'master',                       'name' => 'Master',                  'type' => 'group',    'parent_slug' => null,          'route_name' => null,                           'icon' => 'fa-database',          'order_seq' => 4],
            ['slug' => 'master.employee',              'name' => 'Employee',                'type' => 'page',     'parent_slug' => 'master',      'route_name' => 'master.employee.index',        'icon' => null,                   'order_seq' => 1],
            ['slug' => 'master.employee.create',       'name' => 'Create Employee',         'type' => 'function', 'parent_slug' => 'master.employee', 'route_name' => null,                      'icon' => null,                   'order_seq' => 1],
            ['slug' => 'master.employee.action',       'name' => 'Actions (Edit/Delete)',   'type' => 'function', 'parent_slug' => 'master.employee', 'route_name' => null,                      'icon' => null,                   'order_seq' => 2],
            ['slug' => 'master.customer',              'name' => 'Customer',                'type' => 'page',     'parent_slug' => 'master',      'route_name' => 'master.customer.index',        'icon' => null,                   'order_seq' => 2],
            ['slug' => 'master.customer.create',       'name' => 'Create Customer',         'type' => 'function', 'parent_slug' => 'master.customer', 'route_name' => null,                      'icon' => null,                   'order_seq' => 1],
            ['slug' => 'master.customer.action',       'name' => 'Actions (Edit/Delete)',   'type' => 'function', 'parent_slug' => 'master.customer', 'route_name' => null,                      'icon' => null,                   'order_seq' => 2],

            // ── Finance ───────────────────────────────────────────────────────────
            ['slug' => 'financial',                    'name' => 'Finance',                 'type' => 'page',     'parent_slug' => null,          'route_name' => 'financial',                    'icon' => 'fa-dollar-sign',       'order_seq' => 5],

            // ── HR & General ──────────────────────────────────────────────────────
            ['slug' => 'general',                      'name' => 'HR & General',            'type' => 'page',     'parent_slug' => null,          'route_name' => 'general',                      'icon' => 'fa-users',             'order_seq' => 6],

            // ── Business Dev ──────────────────────────────────────────────────────
            ['slug' => 'business',                     'name' => 'Business Dev',            'type' => 'page',     'parent_slug' => null,          'route_name' => 'business',                     'icon' => 'fa-trending-up',       'order_seq' => 7],

            // ── Ticket ────────────────────────────────────────────────────────────
            ['slug' => 'tickets.inbox',                'name' => 'Ticket',                  'type' => 'page',     'parent_slug' => null,          'route_name' => 'ticket.index',                 'icon' => 'fa-ticket-alt',        'order_seq' => 8],
            ['slug' => 'ui.ticket.btn-create',         'name' => 'Create Ticket',           'type' => 'function', 'parent_slug' => 'tickets.inbox', 'route_name' => null,                        'icon' => null,                   'order_seq' => 1],
            ['slug' => 'ticket.view-all',              'name' => 'View All Tickets',        'type' => 'function', 'parent_slug' => 'tickets.inbox', 'route_name' => null,                        'icon' => null,                   'order_seq' => 2],
            ['slug' => 'ticket.view-own',              'name' => 'View Own Tickets',        'type' => 'function', 'parent_slug' => 'tickets.inbox', 'route_name' => null,                        'icon' => null,                   'order_seq' => 3],
            ['slug' => 'ticket.view-team',             'name' => 'View Team Tickets',       'type' => 'function', 'parent_slug' => 'tickets.inbox', 'route_name' => null,                        'icon' => null,                   'order_seq' => 4],
            ['slug' => 'ticket.assign-pic',            'name' => 'Assign PIC',              'type' => 'function', 'parent_slug' => 'tickets.inbox', 'route_name' => null,                        'icon' => null,                   'order_seq' => 5],
            ['slug' => 'ticket.confirm-assignment',    'name' => 'Confirm Assignment',      'type' => 'function', 'parent_slug' => 'tickets.inbox', 'route_name' => null,                        'icon' => null,                   'order_seq' => 6],
            ['slug' => 'ticket.take',                  'name' => 'Take Ticket',             'type' => 'function', 'parent_slug' => 'tickets.inbox', 'route_name' => null,                        'icon' => null,                   'order_seq' => 7],
            ['slug' => 'ui.ticket.edit-fields',        'name' => 'Edit Status/Priority/Type','type' => 'function','parent_slug' => 'tickets.inbox', 'route_name' => null,                        'icon' => null,                   'order_seq' => 9],
            ['slug' => 'ui.ticket.manage-members',     'name' => 'Manage Members',          'type' => 'function', 'parent_slug' => 'tickets.inbox', 'route_name' => null,                        'icon' => null,                   'order_seq' => 10],
            ['slug' => 'ui.ticket.internal-notes',     'name' => 'Internal Notes',          'type' => 'function', 'parent_slug' => 'tickets.inbox', 'route_name' => null,                        'icon' => null,                   'order_seq' => 11],
            ['slug' => 'ticket.assign-delivery-support', 'name' => 'Assign to Delivery Support', 'type' => 'function', 'parent_slug' => 'tickets.inbox', 'route_name' => null,                    'icon' => null,                   'order_seq' => 12],
            ['slug' => 'ticket.propose-mandays',  'name' => 'Propose Mandays',           'type' => 'function', 'parent_slug' => 'tickets.inbox', 'route_name' => null, 'icon' => null, 'order_seq' => 13],
            ['slug' => 'ticket.review-mandays',   'name' => 'Review Mandays Proposal',   'type' => 'function', 'parent_slug' => 'tickets.inbox', 'route_name' => null, 'icon' => null, 'order_seq' => 14],
            ['slug' => 'ticket.head-mandays',     'name' => 'Head Mandays & Resolution', 'type' => 'function', 'parent_slug' => 'tickets.inbox', 'route_name' => null, 'icon' => null, 'order_seq' => 15],
            ['slug' => 'ticket.view-credential',  'name' => 'View Customer Credential',  'type' => 'function', 'parent_slug' => 'tickets.inbox', 'route_name' => null, 'icon' => null, 'order_seq' => 16],
            ['slug' => 'ticket.meeting',          'name' => 'Meeting',                   'type' => 'function', 'parent_slug' => 'tickets.inbox', 'route_name' => null, 'icon' => null, 'order_seq' => 17],
            ['slug' => 'ticket.delete',           'name' => 'Delete Ticket',             'type' => 'function', 'parent_slug' => 'tickets.inbox', 'route_name' => null, 'icon' => null, 'order_seq' => 18],
            ['slug' => 'ticket.hide',             'name' => 'Hide Ticket',               'type' => 'function', 'parent_slug' => 'tickets.inbox', 'route_name' => null, 'icon' => null, 'order_seq' => 19],

            // ── Room Chat (ticket detail sidebar toggles) ───────────────────────────
            ['slug' => 'room-chat',                    'name' => 'Room Chat',               'type' => 'group',    'parent_slug' => null,          'route_name' => null,                           'icon' => 'fa-comments',          'order_seq' => 8],
            ['slug' => 'room-chat.tab-all-ticket',     'name' => 'All Ticket Button',       'type' => 'function', 'parent_slug' => 'room-chat',   'route_name' => null,                           'icon' => null,                   'order_seq' => 1],
            ['slug' => 'room-chat.tab-my-ticket',      'name' => 'My Ticket Button',        'type' => 'function', 'parent_slug' => 'room-chat',   'route_name' => null,                           'icon' => null,                   'order_seq' => 2],

            // ── My Tasks ─────────────────────────────────────────────────────────
            ['slug' => 'ticket.my-tasks',              'name' => 'My Tasks',                'type' => 'page',     'parent_slug' => null,          'route_name' => 'ticket.task',                  'icon' => 'fa-tasks',             'order_seq' => 9],

            // ── Consultant Workload ───────────────────────────────────────────────
            ['slug' => 'ticket.consultant-workload',   'name' => 'Consultant Workload',     'type' => 'page',     'parent_slug' => null,          'route_name' => 'ticket.consultant-workload',   'icon' => 'fa-chart-line',        'order_seq' => 10],

            // ── Ticket Validation ─────────────────────────────────────────────────
            ['slug' => 'tickets.staging',              'name' => 'Ticket Validation',       'type' => 'page',     'parent_slug' => null,          'route_name' => 'staging.index',                'icon' => 'fa-clipboard-check',   'order_seq' => 11],
            ['slug' => 'staging.approve',              'name' => 'Action Validate',         'type' => 'function', 'parent_slug' => 'tickets.staging', 'route_name' => null,                      'icon' => null,                   'order_seq' => 1],
            ['slug' => 'staging.reject',               'name' => 'Action Reject',           'type' => 'function', 'parent_slug' => 'tickets.staging', 'route_name' => null,                      'icon' => null,                   'order_seq' => 2],

            // ── Delivery ──────────────────────────────────────────────────────────
            ['slug' => 'delivery',                     'name' => 'Delivery',                'type' => 'group',    'parent_slug' => null,          'route_name' => null,                           'icon' => 'fa-briefcase',         'order_seq' => 11],
            ['slug' => 'delivery.project',             'name' => 'Project',                 'type' => 'page',     'parent_slug' => 'delivery',    'route_name' => 'projects.index',               'icon' => null,                   'order_seq' => 1],
            ['slug' => 'delivery-project.add-new',     'name' => 'Add New Project',         'type' => 'function', 'parent_slug' => 'delivery.project', 'route_name' => null,                     'icon' => null,                   'order_seq' => 1],
            ['slug' => 'delivery.support',             'name' => 'Support',                 'type' => 'page',     'parent_slug' => 'delivery',    'route_name' => 'delivery.support.index',       'icon' => null,                   'order_seq' => 2],
            ['slug' => 'delivery-support.add-new',     'name' => 'Add Delivery Support',    'type' => 'function', 'parent_slug' => 'delivery.support', 'route_name' => null,                     'icon' => null,                   'order_seq' => 1],
            ['slug' => 'delivery-support.edit-type',   'name' => 'Edit Field Type',         'type' => 'function', 'parent_slug' => 'delivery.support', 'route_name' => null,                     'icon' => null,                   'order_seq' => 2],

            // ── Control Center (Admin) ────────────────────────────────────────────
            ['slug' => 'control-center',               'name' => 'Control Center',          'type' => 'group',    'parent_slug' => null,          'route_name' => null,                           'icon' => 'fa-server',            'order_seq' => 12],
            ['slug' => 'control-center.overview',      'name' => 'Overview',                'type' => 'page',     'parent_slug' => 'control-center', 'route_name' => 'admin.index',              'icon' => null,                   'order_seq' => 1],
            ['slug' => 'control-center.activity-log',  'name' => 'Activity Log',            'type' => 'page',     'parent_slug' => 'control-center', 'route_name' => 'admin.activity-log',       'icon' => null,                   'order_seq' => 2],
            ['slug' => 'control-center.sessions',      'name' => 'Active Sessions',         'type' => 'page',     'parent_slug' => 'control-center', 'route_name' => 'admin.sessions',           'icon' => null,                   'order_seq' => 3],
            ['slug' => 'control-center.failed-jobs',   'name' => 'Failed Jobs',             'type' => 'page',     'parent_slug' => 'control-center', 'route_name' => 'admin.failed-jobs',        'icon' => null,                   'order_seq' => 4],
            ['slug' => 'control-center.backup',        'name' => 'Backup & Export',         'type' => 'page',     'parent_slug' => 'control-center', 'route_name' => 'admin.backup',             'icon' => null,                   'order_seq' => 5],
            ['slug' => 'control-center.sounds',        'name' => 'Notif Sounds',            'type' => 'page',     'parent_slug' => 'control-center', 'route_name' => 'admin.sounds',             'icon' => null,                   'order_seq' => 6],

            // ── SLA ───────────────────────────────────────────────────────────────
            ['slug' => 'sla',                          'name' => 'SLA',                     'type' => 'group',    'parent_slug' => null,          'route_name' => null,                           'icon' => 'fa-stopwatch',         'order_seq' => 13],
            ['slug' => 'sla.report',                   'name' => 'SLA Report',              'type' => 'page',     'parent_slug' => 'sla',         'route_name' => 'sla.report',                   'icon' => null,                   'order_seq' => 1],

            // ── RPMO ──────────────────────────────────────────────────────────────
            ['slug' => 'rpmo',                         'name' => 'RPMO',                    'type' => 'group',    'parent_slug' => null,          'route_name' => null,                           'icon' => 'fa-building',          'order_seq' => 14],
            ['slug' => 'rpmo.overview',                'name' => 'Overview',                'type' => 'page',     'parent_slug' => 'rpmo',        'route_name' => 'rpmo',                         'icon' => null,                   'order_seq' => 1],
            ['slug' => 'rpmo.periods',                 'name' => 'Period Management',       'type' => 'page',     'parent_slug' => 'rpmo',        'route_name' => 'rpmo.periods.index',           'icon' => null,                   'order_seq' => 2],

            // ── Legal ─────────────────────────────────────────────────────────────
            ['slug' => 'legal',                        'name' => 'Legal',                   'type' => 'page',     'parent_slug' => null,          'route_name' => 'legal',                        'icon' => 'fa-balance-scale',     'order_seq' => 15],

            // ── Manajemen (Admin only) ────────────────────────────────────────────
            ['slug' => 'management',                            'name' => 'Management',              'type' => 'group',    'parent_slug' => null,                   'route_name' => null,                                    'icon' => 'fa-shield-alt',  'order_seq' => 16],
            ['slug' => 'management.roles',                      'name' => 'Role',                    'type' => 'page',     'parent_slug' => 'management',           'route_name' => 'management.roles.index',                'icon' => null,             'order_seq' => 1],
            ['slug' => 'management.permissions',                'name' => 'Menu Access',             'type' => 'page',     'parent_slug' => 'management',           'route_name' => 'management.permissions.index',          'icon' => null,             'order_seq' => 2],
            ['slug' => 'management.holidays',                   'name' => 'Holidays',                'type' => 'page',     'parent_slug' => 'management',           'route_name' => 'management.holidays.index',             'icon' => null,             'order_seq' => 3],
            ['slug' => 'management.hidden-tickets',             'name' => 'Hidden Tickets',          'type' => 'page',     'parent_slug' => 'management',           'route_name' => 'management.hidden-tickets.index',       'icon' => null,             'order_seq' => 5],
            ['slug' => 'management.employee',                   'name' => 'Master Employee Settings', 'type' => 'group',   'parent_slug' => 'management',           'route_name' => null,                                    'icon' => null,             'order_seq' => 4],
            ['slug' => 'management.employee.basic-data',        'name' => 'Basic Data',              'type' => 'page',     'parent_slug' => 'management.employee',  'route_name' => 'management.employee.basic-data.index',  'icon' => null,             'order_seq' => 1],
            ['slug' => 'management.employee.address',           'name' => 'Address',                 'type' => 'page',     'parent_slug' => 'management.employee',  'route_name' => 'management.employee.address.index',     'icon' => null,             'order_seq' => 2],
            ['slug' => 'management.employee.identification',    'name' => 'Identification',          'type' => 'page',     'parent_slug' => 'management.employee',  'route_name' => 'management.employee.identification.index','icon' => null,            'order_seq' => 3],
            ['slug' => 'management.employee.family',            'name' => 'Family',                  'type' => 'page',     'parent_slug' => 'management.employee',  'route_name' => 'management.employee.family.index',      'icon' => null,             'order_seq' => 4],
            ['slug' => 'management.employee.education',         'name' => 'Education',               'type' => 'page',     'parent_slug' => 'management.employee',  'route_name' => 'management.employee.education.index',   'icon' => null,             'order_seq' => 5],
            ['slug' => 'management.employee.qualification',     'name' => 'Qualification',           'type' => 'page',     'parent_slug' => 'management.employee',  'route_name' => 'management.employee.qualification.index','icon' => null,            'order_seq' => 6],
            ['slug' => 'management.employee.contract',          'name' => 'Contract',                'type' => 'page',     'parent_slug' => 'management.employee',  'route_name' => 'management.employee.contract.index',    'icon' => null,             'order_seq' => 7],
            ['slug' => 'management.employee.bank',              'name' => 'Bank',                    'type' => 'page',     'parent_slug' => 'management.employee',  'route_name' => 'management.employee.bank.index',        'icon' => null,             'order_seq' => 8],
            ['slug' => 'management.employee.payment',           'name' => 'Payment',                 'type' => 'page',     'parent_slug' => 'management.employee',  'route_name' => 'management.employee.payment.index',     'icon' => null,             'order_seq' => 9],
            ['slug' => 'management.employee.attachment',        'name' => 'Attachment',              'type' => 'page',     'parent_slug' => 'management.employee',  'route_name' => 'management.employee.attachment.index',  'icon' => null,             'order_seq' => 10],
        ];

        // ── Upsert menus (idempotent) ────────────────────────────────────────────
        $inserted = [];
        foreach ($menus as $menu) {
            $parentId = isset($menu['parent_slug']) && $menu['parent_slug']
                ? ($inserted[$menu['parent_slug']] ?? null)
                : null;

            $existing = DB::table('menu')->where('slug', $menu['slug'])->first();

            if ($existing) {
                DB::table('menu')->where('slug', $menu['slug'])->update([
                    'parent_id'  => $parentId,
                    'name'       => $menu['name'],
                    'type'       => $menu['type'],
                    'route_name' => $menu['route_name'] ?? null,
                    'icon'       => $menu['icon'] ?? null,
                    'order_seq'  => $menu['order_seq'],
                    'is_active'  => true,
                    'updated_at' => $now,
                ]);
                $id = $existing->id;
            } else {
                $id = DB::table('menu')->insertGetId([
                    'parent_id'  => $parentId,
                    'name'       => $menu['name'],
                    'slug'       => $menu['slug'],
                    'type'       => $menu['type'],
                    'route_name' => $menu['route_name'] ?? null,
                    'icon'       => $menu['icon'] ?? null,
                    'order_seq'  => $menu['order_seq'],
                    'is_active'  => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $inserted[$menu['slug']] = $id;
        }

        // ── Permission matrix ────────────────────────────────────────────────────
        // Format: [can_view, can_create, can_edit, can_delete]
        $v    = [true,  false, false, false];
        $vc   = [true,  true,  false, false];
        $ve   = [true,  false, true,  false];
        $vce  = [true,  true,  true,  false];
        $vced = [true,  true,  true,  true];

        // slug => [roleId => permissions]  (null = tidak punya akses)
        $matrix = [
            // Home
            'dashboard'                   => [self::ADMIN=>$v,    self::EMPLOYEE=>$v,   self::INTERN=>$v,   self::HOP=>$v,    self::HOS=>$v,    self::HELPDESK=>$v,   self::RPMO=>$v],
            // Calendar
            'calendar'                    => [self::ADMIN=>$vced, self::EMPLOYEE=>$v,   self::INTERN=>$v,   self::HOP=>$v,    self::HOS=>$v,    self::HELPDESK=>$v,   self::RPMO=>$v],
            'calendar.events'             => [self::ADMIN=>$vced, self::EMPLOYEE=>$v,   self::INTERN=>$v,   self::HOP=>$v,    self::HOS=>$v,    self::HELPDESK=>$v,   self::RPMO=>$v],
            'calendar.events.create'      => [self::ADMIN=>$v],
            'calendar.timesheets'         => [self::ADMIN=>$vced, self::EMPLOYEE=>$vce, self::INTERN=>$vc,  self::HOP=>$vce,  self::HOS=>$vce],
            'timesheet.create'            => [self::ADMIN=>$v,    self::EMPLOYEE=>$v,   self::HOP=>$v,      self::HOS=>$v],
            // Reporting
            'reporting'                   => [self::ADMIN=>$v,    self::EMPLOYEE=>$v,   self::HOP=>$v,      self::HOS=>$v,    self::HELPDESK=>$v,   self::RPMO=>$v],
            'reporting.validation'        => [self::ADMIN=>$vced, self::EMPLOYEE=>$v,   self::HOP=>$v,      self::HOS=>$vce,  self::HELPDESK=>$v,   self::RPMO=>$v],
            'reporting.export-excel'      => [self::ADMIN=>$v,    self::HOS=>$v],
            'reporting.close-period'      => [self::ADMIN=>$v,    self::HOS=>$v],
            'reporting.md-recap'          => [self::ADMIN=>$vced, self::HOS=>$vce],
            'reporting.collection-outlook' => [self::ADMIN=>$v, self::HOP=>$v, self::RPMO=>$v],
            // Master
            'master'                      => [self::ADMIN=>$vced, self::EMPLOYEE=>$v,   self::HOP=>$v,      self::HOS=>$v],
            'master.employee'             => [self::ADMIN=>$vced, self::EMPLOYEE=>$ve,  self::HOP=>$v,      self::HOS=>$v],
            'master.employee.create'      => [self::ADMIN=>$v],
            'master.employee.action'      => [self::ADMIN=>$v],
            'master.customer'             => [self::ADMIN=>$vced, self::HOP=>$v,        self::HOS=>$v],
            'master.customer.create'      => [self::ADMIN=>$v],
            'master.customer.action'      => [self::ADMIN=>$v],
            // Finance, HR, Business
            'financial'                   => [self::ADMIN=>$v],
            'general'                     => [self::ADMIN=>$v],
            'business'                    => [self::ADMIN=>$v],
            // Ticket
            'tickets.inbox'               => [self::ADMIN=>$vced, self::EMPLOYEE=>$v,   self::HOP=>$v,      self::HOS=>$ve,   self::HELPDESK=>$vce, self::RPMO=>$v],
            'ui.ticket.btn-create'        => [self::ADMIN=>$v],
            'ticket.view-all'             => [self::ADMIN=>$v],
            'ticket.view-own'             => [self::EMPLOYEE=>$v],
            'ticket.view-team'            => [self::HOP=>$v,      self::HOS=>$v,        self::HELPDESK=>$v, self::RPMO=>$v],
            'ticket.assign-pic'           => [self::ADMIN=>$v,    self::HOS=>$v,        self::HELPDESK=>$v, self::RPMO=>$v, self::MANAGER=>$v],
            'ticket.confirm-assignment'   => [self::ADMIN=>$v,    self::HELPDESK=>$v,   self::RPMO=>$v],
            'ticket.take'                 => [self::EMPLOYEE=>$v],
            'ui.ticket.edit-fields'       => [self::ADMIN=>$v,    self::HOS=>$v,        self::HELPDESK=>$v, self::RPMO=>$v],
            'ui.ticket.manage-members'    => [self::ADMIN=>$v,    self::EMPLOYEE=>$v,   self::HOS=>$v,    self::HELPDESK=>$v, self::RPMO=>$v],
            'ui.ticket.internal-notes'    => [self::ADMIN=>$v,    self::HELPDESK=>$v,   self::RPMO=>$v],
            'ticket.assign-delivery-support' => [self::ADMIN=>$v, self::HOS=>$v,        self::HELPDESK=>$v, self::RPMO=>$v],
            'ticket.propose-mandays'  => [self::EMPLOYEE=>$v],
            'ticket.review-mandays'   => [self::HELPDESK=>$v, self::RPMO=>$v],
            'ticket.head-mandays'     => [self::HOS=>$v],
            'ticket.view-credential'  => [self::ADMIN=>$v, self::HOS=>$v, self::HELPDESK=>$v, self::RPMO=>$v],
            'ticket.meeting'          => [self::ADMIN=>$v, self::HOS=>$v, self::HELPDESK=>$v, self::RPMO=>$v],
            'ticket.delete'           => [self::ADMIN=>$v],
            'ticket.hide'             => [self::ADMIN=>$v, self::HOS=>$v, self::HELPDESK=>$v, self::RPMO=>$v],
            // Room Chat
            'room-chat'                   => [self::ADMIN=>$v, self::EMPLOYEE=>$v, self::HOS=>$v, self::HELPDESK=>$v, self::RPMO=>$v],
            'room-chat.tab-all-ticket'    => [self::ADMIN=>$v, self::EMPLOYEE=>$v, self::HOS=>$v, self::HELPDESK=>$v, self::RPMO=>$v],
            'room-chat.tab-my-ticket'     => [self::ADMIN=>$v, self::EMPLOYEE=>$v, self::HOS=>$v, self::HELPDESK=>$v, self::RPMO=>$v],
            // My Tasks (Delivery Support User=2, Delivery Project User=15)
            'ticket.my-tasks'             => [self::ADMIN=>$v,    self::EMPLOYEE=>$v,   15=>$v],
            // Consultant Workload
            'ticket.consultant-workload'  => [self::ADMIN=>$v,    self::HOP=>$v,        self::HOS=>$v,      self::HELPDESK=>$v, self::RPMO=>$v],
            // Ticket Validation
            'tickets.staging'             => [self::ADMIN=>$vced, self::EMPLOYEE=>$v,   self::HELPDESK=>$vced, self::RPMO=>$vce,  self::MANAGER=>$vce],
            'staging.approve'             => [self::ADMIN=>$v,    self::EMPLOYEE=>$v,   self::HELPDESK=>$v,    self::RPMO=>$v,    self::MANAGER=>$v],
            'staging.reject'              => [self::ADMIN=>$v,    self::EMPLOYEE=>$v,   self::HELPDESK=>$v,    self::RPMO=>$v,    self::MANAGER=>$v],
            // Delivery
            'delivery'                    => [self::ADMIN=>$vced, self::HOP=>$vce,      self::HOS=>$vce,    self::HELPDESK=>$v,   self::RPMO=>$vced],
            'delivery.project'            => [self::ADMIN=>$vced, self::HOP=>$vce,      self::RPMO=>$vced],
            'delivery-project.add-new'    => [self::ADMIN=>$v,    self::HOP=>$v,        self::RPMO=>$v],
            'delivery.support'            => [self::ADMIN=>$vced, self::HOS=>$vce,      self::HELPDESK=>$v, self::RPMO=>$vced],
            'delivery-support.add-new'    => [self::ADMIN=>$v,    self::HOS=>$v,        self::HELPDESK=>$v, self::RPMO=>$v],
            'delivery-support.edit-type'  => [self::ADMIN=>$v,    self::HELPDESK=>$v,   self::RPMO=>$v],
            // Control Center
            'control-center'              => [self::ADMIN=>$v],
            'control-center.overview'     => [self::ADMIN=>$v],
            'control-center.activity-log' => [self::ADMIN=>$v],
            'control-center.sessions'     => [self::ADMIN=>$v],
            'control-center.failed-jobs'  => [self::ADMIN=>$v],
            'control-center.backup'       => [self::ADMIN=>$v],
            'control-center.sounds'       => [self::ADMIN=>$v],
            // SLA
            'sla'                         => [self::ADMIN=>$v,    self::HOS=>$v,        self::HELPDESK=>$v],
            'sla.report'                  => [self::ADMIN=>$v,    self::HOS=>$v,        self::HELPDESK=>$v],
            // RPMO
            'rpmo'                        => [self::ADMIN=>$v,    self::HOP=>$v,        self::HOS=>$v,      self::RPMO=>$v],
            'rpmo.overview'               => [self::ADMIN=>$v,    self::RPMO=>$v],
            'rpmo.periods'                => [self::ADMIN=>$v,    self::HOP=>$v,        self::HOS=>$v,      self::RPMO=>$v],
            // Legal
            'legal'                       => [self::ADMIN=>$v],
            // Manajemen
            'management'                            => [self::ADMIN=>$v, self::HOS=>$v, self::HELPDESK=>$v, self::RPMO=>$v],
            'management.roles'                      => [self::ADMIN=>$vced],
            'management.permissions'                => [self::ADMIN=>$vced],
            'management.holidays'                   => [self::ADMIN=>$vced],
            'management.hidden-tickets'             => [self::ADMIN=>$v, self::HOS=>$v, self::HELPDESK=>$v, self::RPMO=>$v],
            'management.employee'                   => [self::ADMIN=>$v],
            'management.employee.basic-data'        => [self::ADMIN=>$vced],
            'management.employee.address'           => [self::ADMIN=>$vced],
            'management.employee.identification'    => [self::ADMIN=>$vced],
            'management.employee.family'            => [self::ADMIN=>$vced],
            'management.employee.education'         => [self::ADMIN=>$vced],
            'management.employee.qualification'     => [self::ADMIN=>$vced],
            'management.employee.contract'          => [self::ADMIN=>$vced],
            'management.employee.bank'              => [self::ADMIN=>$vced],
            'management.employee.payment'           => [self::ADMIN=>$vced],
            'management.employee.attachment'        => [self::ADMIN=>$vced],
        ];

        foreach ($matrix as $slug => $rolePerms) {
            $menuId = $inserted[$slug] ?? null;
            if (!$menuId) continue;

            foreach ($rolePerms as $roleId => $perms) {
                DB::table('role_menu')->updateOrInsert(
                    ['role_id' => $roleId, 'menu_id' => $menuId],
                    [
                        'can_view'   => $perms[0],
                        'can_create' => $perms[1],
                        'can_edit'   => $perms[2],
                        'can_delete' => $perms[3],
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        }
    }
}
