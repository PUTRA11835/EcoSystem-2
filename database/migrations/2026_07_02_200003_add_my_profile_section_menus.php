<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $sections = [
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

    public function up(): void
    {
        // Create top-level "My Profile" group
        $parentId = DB::table('menu')->insertGetId([
            'parent_id'  => null,
            'name'       => 'My Profile',
            'slug'       => 'my-profile',
            'type'       => 'group',
            'route_name' => null,
            'icon'       => 'fa-user-circle',
            'order_seq'  => 98,
            'is_active'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (array_keys($this->sections) as $i => $key) {
            $name    = $this->sections[$key];
            $groupId = DB::table('menu')->insertGetId([
                'parent_id'  => $parentId,
                'name'       => $name,
                'slug'       => "my-profile.section.{$key}",
                'type'       => 'group',
                'route_name' => null,
                'icon'       => null,
                'order_seq'  => $i + 1,
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('menu')->insert([
                [
                    'parent_id'  => $groupId,
                    'name'       => "View {$name}",
                    'slug'       => "my-profile.section.{$key}.view",
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
                    'slug'       => "my-profile.section.{$key}.update",
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

    public function down(): void
    {
        $slugs = ['my-profile'];
        foreach (array_keys($this->sections) as $key) {
            $slugs[] = "my-profile.section.{$key}";
            $slugs[] = "my-profile.section.{$key}.view";
            $slugs[] = "my-profile.section.{$key}.update";
        }

        $ids = DB::table('menu')->whereIn('slug', $slugs)->pluck('id');
        if ($ids->isNotEmpty()) {
            DB::table('role_menu')->whereIn('menu_id', $ids)->delete();
            DB::table('menu')->whereIn('id', $ids)->delete();
        }
    }
};
