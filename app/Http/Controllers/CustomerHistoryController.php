<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerHistoryController extends Controller
{
    /**
     * Get history records for a customer with pagination and filtering
     */
    public function index(Request $request, $customerId)
    {
        try {
            Log::info('=== API: FETCHING CUSTOMER HISTORY ===', [
                'customer_id' => $customerId,
                'filters' => $request->all()
            ]);

            $query = DB::table('customer_history')
                ->where('customer_id', $customerId);

            // Apply filter by action type if provided
            if ($request->has('action_type') && $request->action_type !== 'all') {
                $query->where('action', $request->action_type);
            }

            // Apply filter by section if provided
            if ($request->has('section') && $request->section) {
                $query->where('section', $request->section);
            }

            // Apply date range filter if provided
            if ($request->has('date_from') && $request->date_from) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to') && $request->date_to) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Get total count before pagination
            $totalRecords = $query->count();

            // Apply pagination
            $perPage = max(1, min((int) $request->get('per_page', 200), 500));
            $page = $request->get('page', 1);
            $offset = ($page - 1) * $perPage;

            $history = $query->orderBy('created_at', 'desc')
                ->offset($offset)
                ->limit($perPage)
                ->get();

            Log::info('=== API: CUSTOMER HISTORY FETCHED SUCCESSFULLY ===', [
                'count' => $history->count(),
                'total' => $totalRecords
            ]);

            return response()->json([
                'success' => true,
                'data' => $history,
                'pagination' => [
                    'total' => $totalRecords,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => ceil($totalRecords / $perPage),
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $totalRecords)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR FETCHING CUSTOMER HISTORY ===', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch history'
            ], 500);
        }
    }

    /**
     * Create a history record
     */
    public function store(Request $request, $customerId)
    {
        Log::info('=== API: CREATING CUSTOMER HISTORY RECORD ===', [
            'customer_id' => $customerId,
            'data' => $request->all()
        ]);

        try {
            // Check if customer exists
            $customerExists = DB::table('customer')->where('customer_id', $customerId)->exists();
            if (!$customerExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
            }

            $historyData = [
                'customer_id' => $customerId,
                'action' => $request->action ?? 'update',
                'description' => $request->description ?? '',
                'section' => $request->section ?? 'general',
                'user_id' => session('user.id') ?? null,
                'user_name' => session('user.eci') ?? session('user.name') ?? 'system',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $historyId = DB::table('customer_history')->insertGetId($historyData);

            $label = "History #{$historyId}" . ($historyData['action'] ? " ({$historyData['action']})" : '');

            AuditLog::recordAction(
                module: 'Customer', // matches CustomerHistory's own $auditModule so these rows group together
                auditableType: 'CustomerHistory',
                auditableId: $historyId,
                event: 'created',
                recordLabel: $label,
                description: "added Customer History: {$label} — Customer #{$customerId}",
                old: null,
                new: $historyData,
            );

            Log::info('=== API: CUSTOMER HISTORY RECORD CREATED SUCCESSFULLY ===', [
                'history_id' => $historyId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'History record created successfully',
                'data' => ['history_id' => $historyId]
            ], 201);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR CREATING CUSTOMER HISTORY RECORD ===', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create history record'
            ], 500);
        }
    }

    /**
     * Get history statistics
     */
    public function statistics($customerId)
    {
        try {
            Log::info('=== API: FETCHING CUSTOMER HISTORY STATISTICS ===', [
                'customer_id' => $customerId
            ]);

            $stats = DB::table('customer_history')
                ->where('customer_id', $customerId)
                ->select(
                    'action',
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy('action')
                ->get();

            $totalRecords = DB::table('customer_history')
                ->where('customer_id', $customerId)
                ->count();

            $recentActivity = DB::table('customer_history')
                ->where('customer_id', $customerId)
                ->orderBy('created_at', 'desc')
                ->first();

            Log::info('=== API: CUSTOMER HISTORY STATISTICS FETCHED SUCCESSFULLY ===');

            return response()->json([
                'success' => true,
                'data' => [
                    'total_records' => $totalRecords,
                    'by_action' => $stats,
                    'most_recent_activity' => $recentActivity
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR FETCHING CUSTOMER HISTORY STATISTICS ===', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics'
            ], 500);
        }
    }

    /**
     * Delete old history records (cleanup utility)
     */
    public function cleanup($customerId, Request $request)
    {
        Log::info('=== API: CLEANING UP CUSTOMER HISTORY ===', [
            'customer_id' => $customerId,
            'days' => $request->get('days', 365)
        ]);

        try {
            $days = $request->get('days', 365); // Default: delete records older than 1 year
            $cutoff = now()->subDays($days);
            $deletedCount = DB::table('customer_history')
                ->where('customer_id', $customerId)
                ->where('created_at', '<', $cutoff)
                ->delete();

            // One summary row per cleanup run rather than one per deleted
            // history record — individual rows were already bulk-deleted
            // above so there's nothing left to snapshot per-row anyway.
            AuditLog::recordAction(
                module: 'Customer', // matches CustomerHistory's own $auditModule so these rows group together
                auditableType: 'CustomerHistory',
                auditableId: $customerId,
                event: 'deleted',
                recordLabel: "Cleanup ({$deletedCount} record(s))",
                description: "deleted {$deletedCount} old Customer History record(s) older than {$days} days — Customer #{$customerId}",
                old: null,
                new: [
                    'deleted_count' => $deletedCount,
                    'older_than_days' => $days,
                    'cutoff' => $cutoff->toDateTimeString(),
                ],
            );

            Log::info('=== API: CUSTOMER HISTORY CLEANED UP SUCCESSFULLY ===', [
                'deleted_count' => $deletedCount
            ]);

            return response()->json([
                'success' => true,
                'message' => "Deleted {$deletedCount} old history records",
                'deleted_count' => $deletedCount
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR CLEANING UP CUSTOMER HISTORY ===', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cleanup history'
            ], 500);
        }
    }
}