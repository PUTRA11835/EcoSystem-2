<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * CRUD ringan untuk Customer Group struktural + data untuk halaman grouping.
 */
class CustomerGroupController extends Controller
{
    /**
     * GET /api/customer-groups
     * Daftar grup untuk dropdown (id, name, code, jumlah anggota).
     */
    public function index(Request $request)
    {
        try {
            $query = CustomerGroup::query()->withCount('customers');

            if ($request->boolean('active_only')) {
                $query->where('is_active', true);
            }

            $groups = $query->orderBy('name')->get()->map(fn ($g) => [
                'id'              => $g->id,
                'name'            => $g->name,
                'code'            => $g->code,
                'is_active'       => $g->is_active,
                'customers_count' => $g->customers_count,
            ]);

            return response()->json(['success' => true, 'data' => $groups]);
        } catch (\Exception $e) {
            Log::error('CustomerGroupController@index error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to load customer groups'], 500);
        }
    }

    /**
     * POST /api/customer-groups
     * Buat grup baru (dipakai juga untuk "create inline" dari modal customer).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'        => ['required', 'string', 'max:255', 'unique:customer_groups,name'],
            'code'        => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [
            'name.unique' => 'A customer group with this name already exists.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $group = CustomerGroup::create([
                'name'        => trim($request->name),
                'code'        => $request->code ?: strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $request->name), 0, 10)) ?: null,
                'description' => $request->description,
                'is_active'   => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Customer group created successfully',
                'data'    => [
                    'id'   => $group->id,
                    'name' => $group->name,
                    'code' => $group->code,
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('CustomerGroupController@store error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to create customer group'], 500);
        }
    }

    /**
     * PUT /api/customer-groups/{id}
     */
    public function update(Request $request, $id)
    {
        $group = CustomerGroup::find($id);
        if (!$group) {
            return response()->json(['success' => false, 'message' => 'Customer group not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'        => ['required', 'string', 'max:255', 'unique:customer_groups,name,' . $id],
            'code'        => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $group->update($request->only(['name', 'code', 'description', 'is_active']));

            // Mirror perubahan nama ke kolom teks lama pada semua anggota
            if ($group->wasChanged('name')) {
                \DB::table('customer_basic_data')
                    ->whereIn('customer_id', $group->customers()->pluck('customer_id'))
                    ->update(['customer_group' => $group->name]);
            }

            return response()->json(['success' => true, 'message' => 'Customer group updated successfully']);
        } catch (\Exception $e) {
            Log::error('CustomerGroupController@update error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to update customer group'], 500);
        }
    }

    /**
     * DELETE /api/customer-groups/{id}
     * FK di customer di-set null otomatis (nullOnDelete).
     */
    public function destroy($id)
    {
        $group = CustomerGroup::find($id);
        if (!$group) {
            return response()->json(['success' => false, 'message' => 'Customer group not found'], 404);
        }

        try {
            // Bersihkan mirror teks pada anggota sebelum grup dihapus
            \DB::table('customer_basic_data')
                ->whereIn('customer_id', $group->customers()->pluck('customer_id'))
                ->update(['customer_group' => null]);

            $group->delete();

            return response()->json(['success' => true, 'message' => 'Customer group deleted successfully']);
        } catch (\Exception $e) {
            Log::error('CustomerGroupController@destroy error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to delete customer group'], 500);
        }
    }

    /**
     * GET /api/customer-groups/available-customers
     * Customer yang belum tergabung di grup mana pun (customer_group_id null),
     * untuk dropdown "tambah anggota". Menjamin aturan 1 customer = 0–1 grup.
     */
    public function availableCustomers()
    {
        try {
            $customers = Customer::with('basicData')
                ->customers() // Customer Group hanya untuk business partner bertipe Customer
                ->whereNull('customer_group_id')
                ->get()
                ->map(fn ($c) => [
                    'id'    => $c->customer_id,
                    'code'  => $c->customer_code,
                    'name'  => $c->basicData->name_1 ?? $c->customer_code,
                    'email' => $c->email,
                ])
                ->sortBy(fn ($c) => strtolower($c['name']))
                ->values();

            return response()->json(['success' => true, 'data' => $customers]);
        } catch (\Exception $e) {
            Log::error('CustomerGroupController@availableCustomers error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to load available customers'], 500);
        }
    }

    /**
     * POST /api/customer-groups/{id}/members
     * Tambah satu customer ke grup. Ditolak jika customer sudah punya grup lain
     * (aturan: 1 customer = 0–1 grup).
     */
    public function addMember(Request $request, $id)
    {
        $group = CustomerGroup::find($id);
        if (!$group) {
            return response()->json(['success' => false, 'message' => 'Customer group not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'customer_id' => ['required', 'integer'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $customer = Customer::find($request->customer_id);
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Customer not found'], 404);
        }

        // Grup ini murni pengelompokan sisi customer — vendor tidak boleh masuk.
        if (($customer->type ?? Customer::TYPE_CUSTOMER) !== Customer::TYPE_CUSTOMER) {
            return response()->json([
                'success' => false,
                'message' => 'Only business partners with type Customer can be added to a customer group.',
            ], 422);
        }

        if ($customer->customer_group_id && (int) $customer->customer_group_id !== (int) $id) {
            return response()->json([
                'success' => false,
                'message' => 'This customer already belongs to another group. Remove it from that group first.',
            ], 422);
        }

        try {
            $customer->customer_group_id = $group->id;
            $customer->save();
            $customer->syncGroupNameToBasicData();

            return response()->json(['success' => true, 'message' => 'Customer added to group successfully']);
        } catch (\Exception $e) {
            Log::error('CustomerGroupController@addMember error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to add customer to group'], 500);
        }
    }

    /**
     * DELETE /api/customer-groups/{id}/members/{customerId}
     * Lepas customer dari grup (customer_group_id → null). Customer tetap ada.
     */
    public function removeMember($id, $customerId)
    {
        $group = CustomerGroup::find($id);
        if (!$group) {
            return response()->json(['success' => false, 'message' => 'Customer group not found'], 404);
        }

        $customer = Customer::where('customer_id', $customerId)
            ->where('customer_group_id', $id)
            ->first();
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Customer is not a member of this group'], 404);
        }

        try {
            $customer->customer_group_id = null;
            $customer->save();
            $customer->syncGroupNameToBasicData();

            return response()->json(['success' => true, 'message' => 'Customer removed from group successfully']);
        } catch (\Exception $e) {
            Log::error('CustomerGroupController@removeMember error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to remove customer from group'], 500);
        }
    }

    /**
     * GET /api/customer-groups/grouping-data
     * Grup + anggotanya untuk halaman Customer Grouping.
     */
    public function groupingData()
    {
        try {
            $groups = CustomerGroup::with(['customers.basicData'])
                ->withCount('customers')
                ->orderBy('name')
                ->get()
                ->map(fn ($g) => [
                    'id'        => $g->id,
                    'name'      => $g->name,
                    'code'      => $g->code,
                    'is_active' => $g->is_active,
                    'members'   => $g->customers->map(fn ($c) => [
                        'id'     => $c->customer_id,
                        'code'   => $c->customer_code,
                        'email'  => $c->email,
                        'name'   => $c->basicData->name_1 ?? $c->customer_code,
                        'status' => $c->is_active ? 'active' : 'inactive',
                    ])->values(),
                ]);

            return response()->json(['success' => true, 'data' => $groups]);
        } catch (\Exception $e) {
            Log::error('CustomerGroupController@groupingData error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to load customer group data'], 500);
        }
    }
}
