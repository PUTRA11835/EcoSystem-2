<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\AuthUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CustomerContactController extends Controller
{
    /**
     * Get all contacts for a customer
     */
    public function index($customerId)
    {
        try {
            Log::info('=== API: FETCHING CUSTOMER CONTACTS ===', [
                'customer_id' => $customerId
            ]);

            $contacts = DB::table('customer_contact as cc')
                ->leftJoin('auth_users as au', 'au.contact_id', '=', 'cc.contact_id')
                ->leftJoin('customer_contact_group_members as gm', 'gm.contact_id', '=', 'cc.contact_id')
                ->leftJoin('customer_contact_groups as g', 'g.group_id', '=', 'gm.group_id')
                ->where('cc.customer_id', $customerId)
                ->orderBy('cc.contact_id', 'desc')
                ->select(
                    'cc.contact_id',
                    'cc.customer_id',
                    'cc.title',
                    'cc.full_name',
                    'cc.nick_name',
                    'cc.position',
                    'cc.department',
                    'cc.language',
                    'cc.cell_phone_country',
                    'cc.cell_phone',
                    'cc.telephone_country',
                    'cc.telephone',
                    'cc.telephone_extension',
                    'cc.fax_country',
                    'cc.fax',
                    'cc.fax_extension',
                    'cc.email_personal',
                    'cc.email_work',
                    'cc.website',
                    'cc.preferred_communication',
                    'cc.entry_date',
                    'cc.valid_from',
                    'cc.valid_to',
                    'cc.created_at',
                    'cc.updated_at',
                    'au.id as auth_user_id',
                    'au.email as login_email',
                    'au.is_active as login_active',
                    'au.is_already_cp as login_setup_done',
                    'au.can_view_all_tickets',
                    'au.last_login_at',
                    'g.group_id',
                    'g.name as group_name'
                )
                ->get();

            Log::info('=== API: CUSTOMER CONTACTS FETCHED SUCCESSFULLY ===', [
                'count' => $contacts->count()
            ]);

            return response()->json([
                'success' => true,
                'data' => $contacts
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR FETCHING CUSTOMER CONTACTS ===', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch contacts'
            ], 500);
        }
    }

    /**
     * Get single contact
     */
    public function show($customerId, $contactId)
    {
        try {
            Log::info('=== API: FETCHING CUSTOMER CONTACT ===', [
                'customer_id' => $customerId,
                'contact_id' => $contactId
            ]);

            $contact = DB::table('customer_contact')
                ->where('customer_id', $customerId)
                ->where('contact_id', $contactId)
                ->first();

            if (!$contact) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contact not found'
                ], 404);
            }

            Log::info('=== API: CUSTOMER CONTACT FETCHED SUCCESSFULLY ===');

            return response()->json([
                'success' => true,
                'data' => $contact
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR FETCHING CUSTOMER CONTACT ===', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch contact'
            ], 500);
        }
    }

    /**
     * Store new contact
     */
    public function store(Request $request, $customerId)
    {
        Log::info('=== API: CREATING CUSTOMER CONTACT ===', [
            'customer_id' => $customerId,
            'data' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'title' => 'nullable|string|max:255',
            'nick_name' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'language' => 'nullable|string|max:100',
            'cell_phone_country' => 'nullable|string|max:20',
            'cell_phone' => 'nullable|string|max:20',
            'telephone_country' => 'nullable|string|max:20',
            'telephone' => 'nullable|string|max:50',
            'telephone_extension' => 'nullable|string|max:20',
            'fax_country' => 'nullable|string|max:20',
            'fax' => 'nullable|string|max:50',
            'fax_extension' => 'nullable|string|max:20',
            'email_personal' => 'nullable|email|max:255',
            'email_work' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'preferred_communication' => 'nullable|string|max:100',
            'entry_date' => 'nullable|date',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if customer exists
            $customerExists = DB::table('customer')->where('customer_id', $customerId)->exists();
            if (!$customerExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
            }

            $contactData = [
                'customer_id' => $customerId,
                'title' => $request->title,
                'full_name' => $request->full_name,
                'nick_name' => $request->nick_name,
                'position' => $request->position,
                'department' => $request->department,
                'language' => $request->language,
                'cell_phone_country' => $request->cell_phone_country,
                'cell_phone' => $request->cell_phone,
                'telephone_country' => $request->telephone_country,
                'telephone' => $request->telephone,
                'telephone_extension' => $request->telephone_extension,
                'fax_country' => $request->fax_country,
                'fax' => $request->fax,
                'fax_extension' => $request->fax_extension,
                'email_personal' => $request->email_personal,
                'email_work' => $request->email_work,
                'website' => $request->website,
                'preferred_communication' => $request->preferred_communication,
                'entry_date' => $request->entry_date,
                'valid_from' => $request->valid_from,
                'valid_to' => $request->valid_to,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            Log::info('=== API: PREPARED DATA FOR INSERT ===', ['data' => $contactData]);

            $contactId = DB::table('customer_contact')->insertGetId($contactData);

            AuditLog::recordAction(
                module: 'Customer', // matches CustomerContact's own $auditModule so these rows group together
                auditableType: 'CustomerContact',
                auditableId: $contactId,
                event: 'created',
                recordLabel: $request->full_name,
                description: "added Customer Contact: {$request->full_name} — Customer #{$customerId}",
                old: null,
                new: $contactData,
            );

            Log::info('=== API: CUSTOMER CONTACT CREATED SUCCESSFULLY ===', [
                'contact_id' => $contactId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Contact created successfully',
                'data' => ['contact_id' => $contactId]
            ], 201);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR CREATING CUSTOMER CONTACT ===', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create contact'
            ], 500);
        }
    }

    /**
     * Update contact
     */
    public function update(Request $request, $customerId, $contactId)
    {
        Log::info('=== API: UPDATING CUSTOMER CONTACT ===', [
            'customer_id' => $customerId,
            'contact_id' => $contactId,
            'data' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'title' => 'nullable|string|max:255',
            'nick_name' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'language' => 'nullable|string|max:100',
            'cell_phone_country' => 'nullable|string|max:20',
            'cell_phone' => 'nullable|string|max:20',
            'telephone_country' => 'nullable|string|max:20',
            'telephone' => 'nullable|string|max:50',
            'telephone_extension' => 'nullable|string|max:20',
            'fax_country' => 'nullable|string|max:20',
            'fax' => 'nullable|string|max:50',
            'fax_extension' => 'nullable|string|max:20',
            'email_personal' => 'nullable|email|max:255',
            'email_work' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'preferred_communication' => 'nullable|string|max:100',
            'entry_date' => 'nullable|date',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Snapshot before update — needed for the audit log entry below.
            $existingContact = DB::table('customer_contact')
                ->where('customer_id', $customerId)
                ->where('contact_id', $contactId)
                ->first();

            $updateData = [
                'title' => $request->title,
                'full_name' => $request->full_name,
                'nick_name' => $request->nick_name,
                'position' => $request->position,
                'department' => $request->department,
                'language' => $request->language,
                'cell_phone_country' => $request->cell_phone_country,
                'cell_phone' => $request->cell_phone,
                'telephone_country' => $request->telephone_country,
                'telephone' => $request->telephone,
                'telephone_extension' => $request->telephone_extension,
                'fax_country' => $request->fax_country,
                'fax' => $request->fax,
                'fax_extension' => $request->fax_extension,
                'email_personal' => $request->email_personal,
                'email_work' => $request->email_work,
                'website' => $request->website,
                'preferred_communication' => $request->preferred_communication,
                'entry_date' => $request->entry_date,
                'valid_from' => $request->valid_from,
                'valid_to' => $request->valid_to,
                'updated_at' => now(),
            ];

            $updated = DB::table('customer_contact')
                ->where('customer_id', $customerId)
                ->where('contact_id', $contactId)
                ->update($updateData);

            if ($updated === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contact not found'
                ], 404);
            }

            AuditLog::recordAction(
                module: 'Customer', // matches CustomerContact's own $auditModule so these rows group together
                auditableType: 'CustomerContact',
                auditableId: $contactId,
                event: 'updated',
                recordLabel: $request->full_name,
                description: "updated Customer Contact: {$request->full_name} — Customer #{$customerId}",
                old: (array) $existingContact,
                new: $updateData,
            );

            Log::info('=== API: CUSTOMER CONTACT UPDATED SUCCESSFULLY ===');

            return response()->json([
                'success' => true,
                'message' => 'Contact updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR UPDATING CUSTOMER CONTACT ===', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update contact'
            ], 500);
        }
    }

    /**
     * Delete contact
     */
    public function destroy($customerId, $contactId)
    {
        Log::info('=== API: DELETING CUSTOMER CONTACT ===', [
            'customer_id' => $customerId,
            'contact_id' => $contactId
        ]);

        try {
            // Snapshot before delete — needed for the audit log entry below.
            $existingContact = DB::table('customer_contact')
                ->where('customer_id', $customerId)
                ->where('contact_id', $contactId)
                ->first();

            // Explicitly remove any linked login account first. Don't rely
            // solely on the auth_users.contact_id ON DELETE CASCADE FK —
            // it has been observed not to fire in production, leaving an
            // orphaned auth_users row still bound to this customer.
            DB::table('auth_users')
                ->where('contact_id', $contactId)
                ->delete();

            $deleted = DB::table('customer_contact')
                ->where('customer_id', $customerId)
                ->where('contact_id', $contactId)
                ->delete();

            if ($deleted === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contact not found'
                ], 404);
            }

            $label = $existingContact->full_name ?? "Contact #{$contactId}";

            AuditLog::recordAction(
                module: 'Customer', // matches CustomerContact's own $auditModule so these rows group together
                auditableType: 'CustomerContact',
                auditableId: $contactId,
                event: 'deleted',
                recordLabel: $label,
                description: "deleted Customer Contact: {$label} — Customer #{$customerId}",
                old: (array) $existingContact,
                new: null,
            );

            Log::info('=== API: CUSTOMER CONTACT DELETED SUCCESSFULLY ===');

            return response()->json([
                'success' => true,
                'message' => 'Contact deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR DELETING CUSTOMER CONTACT ===', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete contact'
            ], 500);
        }
    }

    /**
     * Grant Jarvies login access to a contact person.
     * Creates an auth_users entry with is_already_cp=false,
     * then immediately sends a password-setup email.
     */
    public function createLogin(Request $request, $customerId, $contactId)
    {
        $contact = DB::table('customer_contact')
            ->where('customer_id', $customerId)
            ->where('contact_id', $contactId)
            ->first();

        if (!$contact) {
            return response()->json(['success' => false, 'message' => 'Contact not found'], 404);
        }

        $existing = DB::table('auth_users')->where('contact_id', $contactId)->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'This contact already has a login account'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'login_email' => 'required|email|unique:auth_users,email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $customer = DB::table('customer')->where('customer_id', $customerId)->first();

            // Jarvies adalah portal sisi customer — contact person milik business
            // partner bertipe Vendor tidak boleh diberi akses login.
            if ($customer && ($customer->type ?? \App\Models\Customer::TYPE_CUSTOMER) !== \App\Models\Customer::TYPE_CUSTOMER) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Login access is only available for business partners with type Customer.',
                ], 422);
            }

            $customerCode = $customer->customer_code ?? 'CP';
            $namePart = Str::slug($contact->full_name ?? $contactId, '');
            $username = strtolower($customerCode . '_' . $namePart);

            // Ensure username is unique
            $base = $username;
            $i = 1;
            while (DB::table('auth_users')->where('username', $username)->exists()) {
                $username = $base . $i++;
            }

            $authUserId = DB::table('auth_users')->insertGetId([
                'employee_id'   => null,
                'customer_id'   => $customerId,
                'contact_id'    => $contactId,
                'username'      => $username,
                'email'         => $request->login_email,
                'phone'         => $contact->cell_phone ?: null,
                'password'      => Hash::make(Str::random(32)),
                'is_active'     => true,
                'is_already_cp' => false,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            // Send password-setup email immediately
            $authUser = AuthUser::find($authUserId);
            PasswordSetupController::generateAndSendToken($authUser);

            DB::commit();

            Log::info('=== API: CONTACT LOGIN CREATED ===', [
                'customer_id' => $customerId,
                'contact_id'  => $contactId,
                'email'       => $request->login_email,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Login access granted. A password setup email has been sent to ' . $request->login_email,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('=== API: ERROR CREATING CONTACT LOGIN ===', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create login'
            ], 500);
        }
    }

    /**
     * Toggle can_view_all_tickets for a contact's auth_users record.
     */
    public function toggleViewAllTickets($customerId, $contactId)
    {
        $authUser = DB::table('auth_users')->where('contact_id', $contactId)->first();

        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'This contact does not have a Jarvies login account.',
            ], 404);
        }

        $newValue = !((bool) $authUser->can_view_all_tickets);

        DB::table('auth_users')
            ->where('contact_id', $contactId)
            ->update(['can_view_all_tickets' => $newValue, 'updated_at' => now()]);

        // Admin sees every company ticket already, so a contact group would be
        // redundant for them — and grouping is only meant for Member-level
        // contacts. Promoting to Admin drops them out of whatever group they're in.
        $removedFromGroup = false;
        if ($newValue) {
            $removedFromGroup = DB::table('customer_contact_group_members')
                ->where('contact_id', $contactId)
                ->delete() > 0;
        }

        Log::info('=== API: TOGGLE CAN_VIEW_ALL_TICKETS ===', [
            'customer_id'        => $customerId,
            'contact_id'         => $contactId,
            'new_value'          => $newValue,
            'removed_from_group' => $removedFromGroup,
        ]);

        $message = $newValue
            ? 'This contact can now view all company tickets.'
            : 'This contact can now only view tickets they submitted.';
        if ($removedFromGroup) {
            $message .= ' They were also removed from their contact group.';
        }

        return response()->json([
            'success'              => true,
            'can_view_all_tickets' => $newValue,
            'removed_from_group'   => $removedFromGroup,
            'message'              => $message,
        ]);
    }

    /**
     * List all contact groups for a customer, with their members.
     */
    public function groups($customerId)
    {
        $groups = DB::table('customer_contact_groups')
            ->where('customer_id', $customerId)
            ->orderBy('name')
            ->get();

        $memberRows = DB::table('customer_contact_group_members as gm')
            ->join('customer_contact as cc', 'cc.contact_id', '=', 'gm.contact_id')
            ->whereIn('gm.group_id', $groups->pluck('group_id'))
            ->select('gm.group_id', 'gm.contact_id', 'cc.full_name', 'cc.email_work')
            ->get()
            ->groupBy('group_id');

        $data = $groups->map(function ($group) use ($memberRows) {
            $group->members = ($memberRows->get($group->group_id) ?? collect())->values();
            return $group;
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Create a new contact group for a customer.
     */
    public function createGroup(Request $request, $customerId)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $customerExists = DB::table('customer')->where('customer_id', $customerId)->exists();
        if (!$customerExists) {
            return response()->json(['success' => false, 'message' => 'Customer not found'], 404);
        }

        $groupId = DB::table('customer_contact_groups')->insertGetId([
            'customer_id' => $customerId,
            'name'        => $request->name,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        AuditLog::recordAction(
            module: 'Customer',
            auditableType: 'CustomerContactGroup',
            auditableId: $groupId,
            event: 'created',
            recordLabel: $request->name,
            description: "created Contact Group: {$request->name} — Customer #{$customerId}",
            old: null,
            new: ['group_id' => $groupId, 'customer_id' => $customerId, 'name' => $request->name],
        );

        return response()->json([
            'success' => true,
            'message' => 'Contact group created successfully',
            'data'    => ['group_id' => $groupId],
        ], 201);
    }

    /**
     * Rename a contact group.
     */
    public function renameGroup(Request $request, $customerId, $groupId)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $group = DB::table('customer_contact_groups')
            ->where('customer_id', $customerId)
            ->where('group_id', $groupId)
            ->first();

        if (!$group) {
            return response()->json(['success' => false, 'message' => 'Group not found'], 404);
        }

        DB::table('customer_contact_groups')
            ->where('group_id', $groupId)
            ->update(['name' => $request->name, 'updated_at' => now()]);

        AuditLog::recordAction(
            module: 'Customer',
            auditableType: 'CustomerContactGroup',
            auditableId: $groupId,
            event: 'updated',
            recordLabel: $request->name,
            description: "renamed Contact Group #{$groupId} to: {$request->name} — Customer #{$customerId}",
            old: ['name' => $group->name],
            new: ['name' => $request->name],
        );

        return response()->json(['success' => true, 'message' => 'Contact group renamed successfully']);
    }

    /**
     * Delete a contact group. Members are simply detached (their own
     * contact/login records are untouched), not deleted.
     */
    public function deleteGroup($customerId, $groupId)
    {
        $group = DB::table('customer_contact_groups')
            ->where('customer_id', $customerId)
            ->where('group_id', $groupId)
            ->first();

        if (!$group) {
            return response()->json(['success' => false, 'message' => 'Group not found'], 404);
        }

        DB::table('customer_contact_groups')->where('group_id', $groupId)->delete();

        AuditLog::recordAction(
            module: 'Customer',
            auditableType: 'CustomerContactGroup',
            auditableId: $groupId,
            event: 'deleted',
            recordLabel: $group->name,
            description: "deleted Contact Group: {$group->name} — Customer #{$customerId}",
            old: (array) $group,
            new: null,
        );

        return response()->json(['success' => true, 'message' => 'Contact group deleted successfully']);
    }

    /**
     * Add a contact to a group.
     * Rejected if the contact belongs to a different customer, is an Admin
     * (can_view_all_tickets = true — Admin already sees everything company-wide,
     * so grouping would be meaningless for them), or already belongs to a group.
     */
    public function addGroupMember(Request $request, $customerId, $groupId)
    {
        $validator = Validator::make($request->all(), [
            'contact_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $group = DB::table('customer_contact_groups')
            ->where('customer_id', $customerId)
            ->where('group_id', $groupId)
            ->first();

        if (!$group) {
            return response()->json(['success' => false, 'message' => 'Group not found'], 404);
        }

        $contactId = $request->contact_id;

        $contact = DB::table('customer_contact')
            ->where('customer_id', $customerId)
            ->where('contact_id', $contactId)
            ->first();

        if (!$contact) {
            return response()->json([
                'success' => false,
                'message' => 'Contact not found for this customer',
            ], 404);
        }

        $authUser = DB::table('auth_users')->where('contact_id', $contactId)->first();
        if ($authUser && (bool) $authUser->can_view_all_tickets) {
            return response()->json([
                'success' => false,
                'message' => 'Admin contacts already see every company ticket and cannot be added to a group.',
            ], 422);
        }

        $existingGroupId = DB::table('customer_contact_group_members')
            ->where('contact_id', $contactId)
            ->value('group_id');

        if ($existingGroupId) {
            return response()->json([
                'success' => false,
                'message' => $existingGroupId == $groupId
                    ? 'This contact is already in this group.'
                    : 'This contact already belongs to another group. Remove them from it first.',
            ], 422);
        }

        DB::table('customer_contact_group_members')->insert([
            'group_id'   => $groupId,
            'contact_id' => $contactId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AuditLog::recordAction(
            module: 'Customer',
            auditableType: 'CustomerContactGroup',
            auditableId: $groupId,
            event: 'updated',
            recordLabel: $group->name,
            description: "added {$contact->full_name} to Contact Group: {$group->name} — Customer #{$customerId}",
            old: null,
            new: ['contact_id' => $contactId, 'full_name' => $contact->full_name],
        );

        return response()->json(['success' => true, 'message' => 'Contact added to group successfully']);
    }

    /**
     * Remove a contact from a group.
     */
    public function removeGroupMember($customerId, $groupId, $contactId)
    {
        $group = DB::table('customer_contact_groups')
            ->where('customer_id', $customerId)
            ->where('group_id', $groupId)
            ->first();

        if (!$group) {
            return response()->json(['success' => false, 'message' => 'Group not found'], 404);
        }

        $deleted = DB::table('customer_contact_group_members')
            ->where('group_id', $groupId)
            ->where('contact_id', $contactId)
            ->delete();

        if ($deleted === 0) {
            return response()->json(['success' => false, 'message' => 'This contact is not in the group'], 404);
        }

        $contact = DB::table('customer_contact')->where('contact_id', $contactId)->first();

        AuditLog::recordAction(
            module: 'Customer',
            auditableType: 'CustomerContactGroup',
            auditableId: $groupId,
            event: 'updated',
            recordLabel: $group->name,
            description: 'removed ' . ($contact->full_name ?? "Contact #{$contactId}") . " from Contact Group: {$group->name} — Customer #{$customerId}",
            old: ['contact_id' => $contactId],
            new: null,
        );

        return response()->json(['success' => true, 'message' => 'Contact removed from group successfully']);
    }

    /**
     * Revoke Jarvies login access from a contact person.
     * Deletes the auth_users entry linked to this contact.
     */
    public function revokeLogin($customerId, $contactId)
    {
        $contact = DB::table('customer_contact')
            ->where('customer_id', $customerId)
            ->where('contact_id', $contactId)
            ->first();

        if (!$contact) {
            return response()->json(['success' => false, 'message' => 'Contact not found'], 404);
        }

        $authUser = DB::table('auth_users')->where('contact_id', $contactId)->first();
        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'No login account found for this contact'
            ], 404);
        }

        DB::table('auth_users')->where('contact_id', $contactId)->delete();

        Log::info('=== API: CONTACT LOGIN REVOKED ===', [
            'customer_id' => $customerId,
            'contact_id'  => $contactId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Login access has been revoked successfully',
        ]);
    }
}