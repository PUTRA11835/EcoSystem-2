<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $employeeSections = [
        'basic_data'     => 'Basic Data',
        'address'        => 'Address',
        'identification' => 'Identification',
        'family'         => 'Family',
        'education'      => 'Education',
        'qualification'  => 'Qualification',
        'contract'       => 'Contract',
        'bank'           => 'Bank Account',
        'payment'        => 'Basic Payment',
        'attachment'     => 'Attachment',
    ];

    private array $customerSections = [
        'basic_data'     => 'Basic Data',
        'address'        => 'Address',
        'identification' => 'Identification',
        'contact'        => 'Contact',
        'bank'           => 'Bank Account',
        'credential'     => 'Credential',
        'history'        => 'History',
        'attachment'     => 'Attachment',
    ];

    public function up(): void
    {
        // Remove old standalone profile.sections group and children
        $oldIds = DB::table('menu')
            ->where('slug', 'profile.sections')
            ->orWhere('slug', 'LIKE', 'profile.section.%')
            ->pluck('id');

        if ($oldIds->isNotEmpty()) {
            DB::table('role_menu')->whereIn('menu_id', $oldIds)->delete();
            DB::table('menu')->whereIn('id', $oldIds)->delete();
        }

        // Add section groups under Employee (master.employee)
        $employeeParentId = DB::table('menu')->where('slug', 'master.employee')->value('id');
        if ($employeeParentId) {
            foreach (array_keys($this->employeeSections) as $i => $key) {
                $name    = $this->employeeSections[$key];
                $groupId = DB::table('menu')->insertGetId([
                    'parent_id'  => $employeeParentId,
                    'name'       => $name,
                    'slug'       => "employee.section.{$key}",
                    'type'       => 'group',
                    'route_name' => null,
                    'icon'       => null,
                    'order_seq'  => 10 + $i,
                    'is_active'  => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('menu')->insert([
                    [
                        'parent_id'  => $groupId,
                        'name'       => "View {$name}",
                        'slug'       => "employee.section.{$key}.view",
                        'type'       => 'function',
                        'route_name' => null,
                        'icon'       => null,
                        'order_seq'  => 1,
                        'is_active'  => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'parent_id'  => $groupId,
                        'name'       => "Update {$name}",
                        'slug'       => "employee.section.{$key}.update",
                        'type'       => 'function',
                        'route_name' => null,
                        'icon'       => null,
                        'order_seq'  => 2,
                        'is_active'  => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ]);
            }
        }

        // Add section groups under Customer (master.customer)
        $customerParentId = DB::table('menu')->where('slug', 'master.customer')->value('id');
        if ($customerParentId) {
            foreach (array_keys($this->customerSections) as $i => $key) {
                $name    = $this->customerSections[$key];
                $groupId = DB::table('menu')->insertGetId([
                    'parent_id'  => $customerParentId,
                    'name'       => $name,
                    'slug'       => "customer.section.{$key}",
                    'type'       => 'group',
                    'route_name' => null,
                    'icon'       => null,
                    'order_seq'  => 10 + $i,
                    'is_active'  => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('menu')->insert([
                    [
                        'parent_id'  => $groupId,
                        'name'       => "View {$name}",
                        'slug'       => "customer.section.{$key}.view",
                        'type'       => 'function',
                        'route_name' => null,
                        'icon'       => null,
                        'order_seq'  => 1,
                        'is_active'  => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'parent_id'  => $groupId,
                        'name'       => "Update {$name}",
                        'slug'       => "customer.section.{$key}.update",
                        'type'       => 'function',
                        'route_name' => null,
                        'icon'       => null,
                        'order_seq'  => 2,
                        'is_active'  => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ]);
            }
        }
    }

    public function down(): void
    {
        $allSlugs = [];
        foreach (array_keys($this->employeeSections) as $key) {
            $allSlugs[] = "employee.section.{$key}";
            $allSlugs[] = "employee.section.{$key}.view";
            $allSlugs[] = "employee.section.{$key}.update";
        }
        foreach (array_keys($this->customerSections) as $key) {
            $allSlugs[] = "customer.section.{$key}";
            $allSlugs[] = "customer.section.{$key}.view";
            $allSlugs[] = "customer.section.{$key}.update";
        }

        $ids = DB::table('menu')->whereIn('slug', $allSlugs)->pluck('id');
        if ($ids->isNotEmpty()) {
            DB::table('role_menu')->whereIn('menu_id', $ids)->delete();
            DB::table('menu')->whereIn('id', $ids)->delete();
        }

        // Restore standalone profile.sections group
        $parentId = DB::table('menu')->insertGetId([
            'parent_id' => null, 'name' => 'Profile Sections',
            'slug' => 'profile.sections', 'type' => 'group',
            'route_name' => null, 'icon' => 'fa-user-lock',
            'order_seq' => 99, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach (array_keys($this->employeeSections) as $i => $key) {
            DB::table('menu')->insert([
                'parent_id' => $parentId, 'name' => $this->employeeSections[$key],
                'slug' => "profile.section.{$key}", 'type' => 'function',
                'route_name' => null, 'icon' => null, 'order_seq' => $i + 1,
                'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
};
