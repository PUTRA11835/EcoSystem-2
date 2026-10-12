<?php

use Illuminate\Database\Migrations\Migration;
use App\Support\MenuRegistrar;

/**
 * Slug izin khusus untuk fitur Contact Groups (shared ticket visibility),
 * terpisah dari `customer.section.contact.update` supaya bisa di-grant
 * independen — siapa yang boleh edit data contact belum tentu boleh
 * mengatur grup mereka. Baru → admin-only (aturan baku MenuRegistrar),
 * role lain diberikan manual lewat Control Center → Menu Access.
 */
return new class extends Migration
{
    public function up(): void
    {
        MenuRegistrar::register('customer.section.contact', [
            'customer.section.contact.group' => 'Manage Contact Groups',
        ], 3);
    }

    public function down(): void
    {
        MenuRegistrar::remove(['customer.section.contact.group']);
    }
};
