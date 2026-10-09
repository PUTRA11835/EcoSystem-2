<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * General Affairs → Inventory & Assets: two registers, because they are different things.
 *
 * inventory_items  office inventory & consumables — a STOCK quantity per line (stationery, pantry, cleaning ...)
 *   quantity / min_stock   units in stock now / the level at or below which the line is "Low stock"
 *   unit_price             rupiah per unit; the stock value is unit_price × quantity
 *   status_override        empty = the status follows the stock (out of stock · low stock · in stock);
 *                          otherwise the value forced by hand: in_stock · low_stock · out_of_stock · inactive
 *                          (inactive lines leave the Overview totals)
 *
 * inventory_assets  company assets — ONE row per tracked unit (laptop, monitor, vehicle ...)
 *   assignee_employee_id   who has it now, if anyone
 *   purchase_price         rupiah paid for this unit
 *   condition              good · fair · damaged
 *   status                 available · in_use · maintenance · disposed (disposed rows leave the Overview totals)
 *
 * inventory_options  the dropdown lists the two forms offer (Inventory & Assets → Settings)
 *   field        which dropdown: item_category · item_unit · asset_category · asset_condition · asset_status · location
 *   value        what the records store. Category / unit / location: the name itself (a rename updates the records).
 *                Condition / status: a fixed key (good, in_use ...) so the app's rules keep working when the label changes
 *   label        what people see
 *   tone         badge colour of a condition / status (gray · green · amber · red · blue)
 *   is_system    built-in rows the app's rules rely on: renamed and re-ordered freely, never deleted or switched off
 *   is_active    off = no longer offered in the form; records that already use it keep it
 *
 * Both registers keep an optional photo on the private disk (path only); it is served through a menu-guarded route.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('category', 60)->index();
            $table->string('unit', 20)->default('pcs');
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('min_stock')->default(0);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->string('location', 120)->nullable();
            $table->string('status_override', 12)->nullable();
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_options', function (Blueprint $table) {
            $table->id();
            $table->string('field', 30);
            $table->string('value', 60);
            $table->string('label', 60);
            $table->string('tone', 10)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['field', 'value']);
        });

        $this->seedOptions();

        Schema::create('inventory_assets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('category', 60)->index();
            $table->string('brand', 100)->nullable();
            $table->string('serial_number', 100)->nullable()->index();
            $table->unsignedBigInteger('assignee_employee_id')->nullable()->index();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 15, 2)->default(0);
            $table->string('condition', 12)->default('good')->index();
            $table->string('status', 12)->default('available')->index();
            $table->string('location', 120)->nullable();
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('assignee_employee_id')->references('employee_id')->on('employee')->nullOnDelete();
        });
    }

    /** Starting lists, all editable in Settings; only the condition / status keys below are built in. */
    private function seedOptions(): void
    {
        $rows = [];
        $add = function (string $field, string $value, string $label, ?string $tone = null, bool $system = false) use (&$rows) {
            $order = count(array_filter($rows, fn ($r) => $r['field'] === $field)) + 1;
            $rows[] = ['field' => $field, 'value' => $value, 'label' => $label, 'tone' => $tone, 'sort_order' => $order,
                'is_active' => true, 'is_system' => $system, 'created_at' => now(), 'updated_at' => now()];
        };

        foreach (['Stationery', 'Pantry', 'Cleaning', 'Printing'] as $name) {
            $add('item_category', $name, $name);
        }
        foreach (['pcs', 'box', 'pack', 'ream', 'set', 'bottle'] as $name) {
            $add('item_unit', $name, $name);
        }
        foreach (['IT Equipment', 'Furniture', 'Office Equipment', 'Appliance', 'Vehicle'] as $name) {
            $add('asset_category', $name, $name);
        }
        $add('asset_condition', 'good', 'Good', 'green', true);
        $add('asset_condition', 'fair', 'Fair', 'amber', true);
        $add('asset_condition', 'damaged', 'Damaged', 'red', true);
        $add('asset_status', 'available', 'Available', 'green', true);
        $add('asset_status', 'in_use', 'In use', 'blue', true);
        $add('asset_status', 'maintenance', 'Under maintenance', 'amber', true);
        $add('asset_status', 'disposed', 'Disposed', 'gray', true);

        DB::table('inventory_options')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_options');
        Schema::dropIfExists('inventory_assets');
        Schema::dropIfExists('inventory_items');
    }
};
