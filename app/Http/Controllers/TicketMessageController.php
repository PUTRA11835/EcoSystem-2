<?php

namespace App\Http\Controllers;

use App\Http\Controllers\EmailController;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TicketMessageController extends Controller
{
    /**
     * Get messages for a ticket
     */
    public function index($ticketId)
    {
        try {
            $sessionUser = session('user');

            if (!$sessionUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $ticket = Ticket::findOrFail($ticketId);

            // Check access based on role
            $roleId = $sessionUser['role']['id'];

            // Customer can only view their own ticket messages
            if ($roleId == 3 && $ticket->customer_id != $sessionUser['id']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view this ticket'
                ], 403);
            }

            // Build query for messages
            $query = TicketMessage::where('ticket_id', $ticketId)
                ->orderBy('created_at', 'asc');

            // Customer cannot see internal notes
            if ($roleId == 3) {
                $query->where('is_internal_note', false);
            }

            $messages = $query->get()->map(function ($message) {
                return [
                    'id' => $message->id,
                    'ticket_id' => $message->ticket_id,
                    'sender_type' => $message->sender_type,
                    'sender_id' => $message->sender_id,
                    'sender_name' => $message->sender_name,
                    'sender_email' => $message->sender_email,
                    'message_body' => $message->message,
                    'message_type' => $message->is_internal_note ? 'internal_note' : 'reply',
                    'channel' => $message->channel ?? 'web',
                    'is_read_by_customer' => $message->is_read_by_customer,
                    'is_read_by_agent' => $message->is_read_by_agent,
                    'created_at' => $message->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $messages
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching ticket messages:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch messages',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new message (reply or internal note)
     */
    public function store(Request $request, $ticketId)
    {
        $validator = Validator::make($request->all(), [
            'message_body' => 'required|string',
            'message_type' => 'required|in:reply,internal_note',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $sessionUser = session('user');

            if (!$sessionUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $ticket = Ticket::findOrFail($ticketId);
            $roleId = $sessionUser['role']['id'];

            // Customer can only reply to their own tickets
            if ($roleId == 3 && $ticket->customer_id != $sessionUser['id']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to reply to this ticket'
                ], 403);
            }

            // Customer cannot create internal notes
            if ($roleId == 3 && $request->message_type === 'internal_note') {
                return response()->json([
                    'success' => false,
                    'message' => 'Customers cannot create internal notes'
                ], 403);
            }

            // Determine sender type and info
            $senderType = $roleId == 3 ? 'customer' : 'employee';
            $senderId = $sessionUser['id'];
            $senderName = $sessionUser['name'] ?? $sessionUser['email'] ?? 'Unknown';

            // Create the message
            $message = TicketMessage::create([
                'ticket_id' => $ticketId,
                'sender_type' => $senderType,
                'sender_id' => $senderId,
                'sender_name' => $senderName,
                'message' => $request->message_body,
                'is_internal_note' => $request->message_type === 'internal_note',
                'channel' => 'web',
                'is_read_by_customer' => $senderType === 'customer',
                'is_read_by_agent' => $senderType === 'employee',
            ]);

            // Update timestamps only — status diubah manual oleh helpdesk via dropdown
            if ($request->message_type === 'reply') {
                if ($senderType === 'employee') {
                    $ticket->update(['last_agent_reply_at' => now(), 'last_message_at' => now()]);
                } else {
                    $ticket->update(['last_customer_reply_at' => now(), 'last_message_at' => now()]);
                }
            }

            // Auto-kirim email jika tiket berasal dari email dan ini adalah reply dari employee
            if (
                $request->message_type === 'reply'
                && $senderType === 'employee'
                && $ticket->channel === 'email'
                && !empty($ticket->email_thread_id)
            ) {
                $this->sendEmailReply($ticket, $message);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $message->id,
                    'ticket_id' => $message->ticket_id,
                    'sender_type' => $message->sender_type,
                    'sender_name' => $message->sender_name,
                    'message_body' => $message->message,
                    'message_type' => $message->is_internal_note ? 'internal_note' : 'reply',
                    'created_at' => $message->created_at,
                ],
                'message' => 'Message sent successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending ticket message:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send message',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Kirim email balasan ke customer untuk tiket yang berasal dari email
     */
    private function sendEmailReply(Ticket $ticket, TicketMessage $message): void
    {
        try {
            // Dapatkan email customer
            $customerEmail = null;
            if ($ticket->customer_id) {
                $customer = Customer::find($ticket->customer_id);
                $customerEmail = $customer?->email;
            }

            // Fallback: cari dari pesan pertama tiket (sender email dari email asal)
            if (!$customerEmail) {
                $firstMessage = TicketMessage::where('ticket_id', $ticket->ticket_id)
                    ->where('channel', 'email')
                    ->orderBy('created_at', 'asc')
                    ->first();
                $customerEmail = $firstMessage?->sender_email;
            }

            if (!$customerEmail) {
                Log::warning('TicketMessageController@sendEmailReply: no customer email found', [
                    'ticket_id' => $ticket->ticket_id,
                ]);
                return;
            }

            // Bangun subject dari deskripsi tiket
            $subject = 'Ticket #' . $ticket->ticket_number . ': ' . ($ticket->description ? substr($ticket->description, 0, 80) : 'Update');

            // In-Reply-To = internetMessageId dari pesan email terakhir di tiket ini
            // (bukan email_thread_id yang isinya conversationId dari Graph)
            $lastEmailMsg = TicketMessage::where('ticket_id', $ticket->ticket_id)
                ->where('channel', 'email')
                ->whereNotNull('email_message_id')
                ->orderBy('created_at', 'desc')
                ->first();

            $inReplyTo = $lastEmailMsg?->email_message_id;

            $emailController = new EmailController();
            $emailController->sendTicketReply($customerEmail, $subject, $message->message, $inReplyTo);

        } catch (\Exception $e) {
            Log::error('TicketMessageController@sendEmailReply: failed to send email', [
                'ticket_id' => $ticket->ticket_id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mark all messages as read
     */
    public function markAllRead($ticketId)
    {
        try {
            $sessionUser = session('user');

            if (!$sessionUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $ticket = Ticket::findOrFail($ticketId);
            $roleId = $sessionUser['role']['id'];

            // Customer can only mark their own ticket messages as read
            if ($roleId == 3 && $ticket->customer_id != $sessionUser['id']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            // Mark messages as read based on user type
            if ($roleId == 3) {
                // Customer marks messages as read by customer
                TicketMessage::where('ticket_id', $ticketId)
                    ->where('sender_type', 'employee')
                    ->where('is_read_by_customer', false)
                    ->update([
                        'is_read_by_customer' => true,
                        'read_at' => now()
                    ]);
            } else {
                // Employee/Admin marks messages as read by agent
                TicketMessage::where('ticket_id', $ticketId)
                    ->where('sender_type', 'customer')
                    ->where('is_read_by_agent', false)
                    ->update([
                        'is_read_by_agent' => true,
                        'read_at' => now()
                    ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Messages marked as read'
            ]);
        } catch (\Exception $e) {
            Log::error('Error marking messages as read:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark messages as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
