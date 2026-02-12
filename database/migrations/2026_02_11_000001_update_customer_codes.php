<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Updates existing customers with generated customer_codes based on their search_term_1
     */
    public function up(): void
    {
        // Get all customers without customer_code
        $customers = DB::table('customer')
            ->whereNull('customer_code')
            ->orWhere('customer_code', '')
            ->get();

        foreach ($customers as $customer) {
            // Get the basic data to get search_term_1
            $basicData = DB::table('customer_basic_data')
                ->where('customer_id', $customer->customer_id)
                ->first();

            if ($basicData && !empty($basicData->search_term_1)) {
                // Generate customer_code from search_term_1 (first 4 letters)
                $customerCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $basicData->search_term_1), 0, 4));
            } else {
                // Fallback: use CUST + customer_id
                $customerCode = 'CUS' . $customer->customer_id;
            }

            // Ensure uniqueness
            $originalCode = $customerCode;
            $counter = 1;
            while (DB::table('customer')
                ->where('customer_code', $customerCode)
                ->where('customer_id', '!=', $customer->customer_id)
                ->exists()) {
                $customerCode = substr($originalCode, 0, 3) . $counter;
                $counter++;
            }

            // Update the customer
            DB::table('customer')
                ->where('customer_id', $customer->customer_id)
                ->update(['customer_code' => $customerCode]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optionally clear customer_codes
        // DB::table('customer')->update(['customer_code' => null]);
    }
};
