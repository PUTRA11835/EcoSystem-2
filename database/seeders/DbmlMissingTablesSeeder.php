<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DbmlMissingTablesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Ensure base records exist for FK dependencies.
        $employeeRoleId = DB::table('employee_role')->value('id');
        if (!$employeeRoleId) {
            $employeeRoleId = DB::table('employee_role')->insertGetId([
                'name' => 'Seeder Role',
                'description' => 'Seeder role for FK dependencies',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $employeeId = DB::table('employee')->value('employee_id');
        if (!$employeeId) {
            $employeeId = DB::table('employee')->insertGetId([
                'role_id' => $employeeRoleId,
                'eci' => 'SEEDER_EMP_1',
                'password' => bcrypt('password'),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], 'employee_id');
        }
        $employeeId2 = DB::table('employee')
            ->where('employee_id', '!=', $employeeId)
            ->value('employee_id');
        if (!$employeeId2) {
            $employeeId2 = DB::table('employee')->insertGetId([
                'role_id' => $employeeRoleId,
                'eci' => 'SEEDER_EMP_2',
                'password' => bcrypt('password'),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], 'employee_id');
        }

        $customerId = DB::table('customer')->value('customer_id');
        if (!$customerId) {
            $customerId = DB::table('customer')->insertGetId([
                'customer_code' => 'SEEDER_CUST_1',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], 'customer_id');
        }

        $ticketId = DB::table('ticket')->value('ticket_id');
        if (!$ticketId) {
            $ticketId = DB::table('ticket')->insertGetId([
                'ticket_number' => '2602-SEED-0001',
                'customer_id' => $customerId,
                'employee_id' => $employeeId,
                'description' => 'Seeder ticket',
                'ticket_priority' => 'Medium',
                'status' => 'open',
                'jarvies_status' => 'in process',
                'created_at' => $now,
                'updated_at' => $now,
            ], 'ticket_id');
        }

        // ------------------------------------------------------------------
        // auth_users (2 rows)
        // ------------------------------------------------------------------
        if (DB::table('auth_users')->count() < 2) {
            DB::table('auth_users')->insert([
                [
                    'user_type' => 'employee',
                    'employee_id' => $employeeId,
                    'customer_id' => null,
                    'username' => 'seed_employee',
                    'email' => 'seed_employee@example.com',
                    'phone' => '0811111111',
                    'password' => bcrypt('password'),
                    'last_login_at' => null,
                    'is_active' => true,
                    'email_verified_at' => null,
                    'phone_verified_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'user_type' => 'customer',
                    'employee_id' => null,
                    'customer_id' => $customerId,
                    'username' => 'seed_customer',
                    'email' => 'seed_customer@example.com',
                    'phone' => '0822222222',
                    'password' => bcrypt('password'),
                    'last_login_at' => null,
                    'is_active' => true,
                    'email_verified_at' => null,
                    'phone_verified_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }

        // ------------------------------------------------------------------
        // employee_role (2 rows)
        // ------------------------------------------------------------------
        if (DB::table('employee_role')->count() < 2) {
            DB::table('employee_role')->insert([
                [
                    'name' => 'Seeder Role A',
                    'description' => 'Seeder role assignment A',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'Seeder Role B',
                    'description' => 'Seeder role assignment B',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }

        // ------------------------------------------------------------------
        // employee_history (2 rows)
        // ------------------------------------------------------------------
        if (DB::table('employee_history')->count() < 2) {
            DB::table('employee_history')->insert([
                [
                    'employee_id' => $employeeId,
                    'action' => 'created',
                    'description' => 'Seeder history A',
                    'performed_by' => $employeeId,
                    'performed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'employee_id' => $employeeId,
                    'action' => 'updated',
                    'description' => 'Seeder history B',
                    'performed_by' => $employeeId,
                    'performed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }

        // ------------------------------------------------------------------
        // ticket_message (2 rows)
        // ------------------------------------------------------------------
        $messageIds = DB::table('ticket_message')->pluck('id')->all();
        if (count($messageIds) < 2) {
            DB::table('ticket_message')->insert([
                [
                    'ticket_id' => $ticketId,
                    'sender_type' => 'customer',
                    'sender_id' => $customerId,
                    'sender_email' => 'seed_customer@example.com',
                    'sender_name' => 'Seed Customer',
                    'message' => 'Seeder message A',
                    'is_internal_note' => false,
                    'channel' => 'web',
                    'email_message_id' => null,
                    'email_in_reply_to' => null,
                    'is_read_by_customer' => true,
                    'is_read_by_agent' => false,
                    'read_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'ticket_id' => $ticketId,
                    'sender_type' => 'employee',
                    'sender_id' => $employeeId,
                    'sender_email' => 'seed_employee@example.com',
                    'sender_name' => 'Seed Employee',
                    'message' => 'Seeder message B',
                    'is_internal_note' => false,
                    'channel' => 'web',
                    'email_message_id' => null,
                    'email_in_reply_to' => null,
                    'is_read_by_customer' => false,
                    'is_read_by_agent' => true,
                    'read_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
            $messageIds = DB::table('ticket_message')->pluck('id')->all();
        }

        // ------------------------------------------------------------------
        // ticket_attachment (2 rows)
        // ------------------------------------------------------------------
        if (DB::table('ticket_attachment')->count() < 2) {
            $messageIdA = $messageIds[0] ?? null;
            $messageIdB = $messageIds[1] ?? $messageIdA;
            DB::table('ticket_attachment')->insert([
                [
                    'ticket_id' => $ticketId,
                    'message_id' => $messageIdA,
                    'uploaded_by_type' => 'customer',
                    'uploaded_by_id' => $customerId,
                    'attachment_type' => 'link',
                    'link_url' => 'https://example.com/file-a',
                    'link_title' => 'Seeder Attachment A',
                    'description' => 'Seeder attachment A',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'ticket_id' => $ticketId,
                    'message_id' => $messageIdB,
                    'uploaded_by_type' => 'employee',
                    'uploaded_by_id' => $employeeId,
                    'attachment_type' => 'link',
                    'link_url' => 'https://example.com/file-b',
                    'link_title' => 'Seeder Attachment B',
                    'description' => 'Seeder attachment B',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }

        // ------------------------------------------------------------------
        // ticket_account (2 rows)
        // ------------------------------------------------------------------
        if (DB::table('ticket_account')->count() < 2) {
            DB::table('ticket_account')->insert([
                [
                    'ticket_id' => $ticketId,
                    'account_type' => 'customer',
                    'account_email' => 'seed_customer@example.com',
                    'can_view' => true,
                    'can_reply' => true,
                    'joined_at' => $now,
                    'last_seen_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'ticket_id' => $ticketId,
                    'account_type' => 'employee',
                    'account_email' => 'seed_employee@example.com',
                    'can_view' => true,
                    'can_reply' => true,
                    'joined_at' => $now,
                    'last_seen_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }

        // ------------------------------------------------------------------
        // ticket_team (2 rows)
        // ------------------------------------------------------------------
        if (DB::table('ticket_team')->count() < 2) {
            DB::table('ticket_team')->insert([
                [
                    'ticket_id' => $ticketId,
                    'employee_id' => $employeeId,
                    'joined_at' => $now,
                    'left_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'ticket_id' => $ticketId,
                    'employee_id' => $employeeId2,
                    'joined_at' => $now,
                    'left_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }

        // ------------------------------------------------------------------
        // customer_mandays_detail (2 rows)
        // ------------------------------------------------------------------
        $customerMandaysId = DB::table('customer_mandays')->value('id');
        if (!$customerMandaysId) {
            $customerMandaysId = DB::table('customer_mandays')->insertGetId([
                'ticket_id' => $ticketId,
                'version' => 1,
                'proposed_by_agent_id' => $employeeId,
                'proposed_at' => $now,
                'submitted_to_customer_at' => null,
                'status' => 'draft',
                'customer_response_at' => null,
                'customer_notes' => null,
                'total_mandays' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        if (DB::table('customer_mandays_detail')->count() < 2) {
            DB::table('customer_mandays_detail')->insert([
                [
                    'customer_mandays_id' => $customerMandaysId,
                    'module' => 'Module A',
                    'mandays' => 1.5,
                    'notes' => 'Seeder CMD A',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'customer_mandays_id' => $customerMandaysId,
                    'module' => 'Module B',
                    'mandays' => 2.0,
                    'notes' => 'Seeder CMD B',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }

        // ------------------------------------------------------------------
        // consultant_mandays_detail (2 rows)
        // ------------------------------------------------------------------
        $consultantMandaysId = DB::table('consultant_mandays')->value('id');
        if (!$consultantMandaysId) {
            $consultantMandaysId = DB::table('consultant_mandays')->insertGetId([
                'ticket_id' => $ticketId,
                'proposed_by_agent_id' => $employeeId,
                'proposed_at' => $now,
                'last_edited_at' => null,
                'status' => 'draft',
                'approved_by_head_id' => null,
                'approved_at' => null,
                'rejection_reason' => null,
                'total_mandays' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        if (DB::table('consultant_mandays_detail')->count() < 2) {
            DB::table('consultant_mandays_detail')->insert([
                [
                    'consultant_mandays_id' => $consultantMandaysId,
                    'employee_id' => $employeeId,
                    'module' => 'Module X',
                    'mandays' => 1.0,
                    'notes' => 'Seeder CIMD A',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'consultant_mandays_id' => $consultantMandaysId,
                    'employee_id' => $employeeId,
                    'module' => 'Module Y',
                    'mandays' => 2.5,
                    'notes' => 'Seeder CIMD B',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }

        // ------------------------------------------------------------------
        // ticket_rating (2 rows)
        // ------------------------------------------------------------------
        if (DB::table('ticket_rating')->count() < 2) {
            DB::table('ticket_rating')->insert([
                [
                    'ticket_id' => $ticketId,
                    'customer_id' => $customerId,
                    'rating' => 5,
                    'comment' => 'Seeder rating A',
                    'created_at' => $now,
                ],
                [
                    'ticket_id' => $ticketId,
                    'customer_id' => $customerId,
                    'rating' => 4,
                    'comment' => 'Seeder rating B',
                    'created_at' => $now,
                ],
            ]);
        }
    }
}
