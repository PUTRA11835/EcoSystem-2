<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CustomerAttachmentController extends Controller
{
    /**
     * Get all attachments for a customer
     */
    public function index($customerId)
    {
        try {
            Log::info('=== API: FETCHING CUSTOMER ATTACHMENTS ===', [
                'customer_id' => $customerId
            ]);

            $attachments = DB::table('customer_attachment')
                ->where('customer_id', $customerId)
                ->orderBy('attachment_id', 'desc')
                ->get();

            Log::info('=== API: CUSTOMER ATTACHMENTS FETCHED SUCCESSFULLY ===', [
                'count' => $attachments->count()
            ]);

            return response()->json([
                'success' => true,
                'data' => $attachments
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR FETCHING CUSTOMER ATTACHMENTS ===', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attachments'
            ], 500);
        }
    }

    /**
     * Get single attachment
     */
    public function show($customerId, $attachmentId)
    {
        try {
            Log::info('=== API: FETCHING CUSTOMER ATTACHMENT ===', [
                'customer_id' => $customerId,
                'attachment_id' => $attachmentId
            ]);

            $attachment = DB::table('customer_attachment')
                ->where('customer_id', $customerId)
                ->where('attachment_id', $attachmentId)
                ->first();

            if (!$attachment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attachment not found'
                ], 404);
            }

            Log::info('=== API: CUSTOMER ATTACHMENT FETCHED SUCCESSFULLY ===');

            return response()->json([
                'success' => true,
                'data' => $attachment
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR FETCHING CUSTOMER ATTACHMENT ===', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attachment'
            ], 500);
        }
    }

    /**
     * Store new attachment
     */
    public function store(Request $request, $customerId)
    {
        Log::info('=== API: CREATING CUSTOMER ATTACHMENT ===', [
            'customer_id' => $customerId,
            'data' => $request->except('file')
        ]);

        $validator = Validator::make($request->all(), [
            'document_type' => 'required|string|max:100',
            'document_title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'file' => 'required|file|max:10240|mimes:pdf,doc,docx,jpg,jpeg,png,xlsx,xls',
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

            // Handle file upload
            $file      = $request->file('file');
            $extension = $file->getClientOriginalExtension();
            $fileName  = Str::uuid() . ($extension ? '.' . $extension : '');
            $filePath  = $file->storeAs('customer_attachments/' . $customerId, $fileName, 'public');
            
            $attachmentData = [
                'customer_id' => $customerId,
                'document_type' => $request->document_type,
                'document_title' => $request->document_title,
                'description' => $request->description,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $filePath,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'uploaded_by' => session('user.eci') ?? session('user.name') ?? 'system',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $attachmentId = DB::table('customer_attachment')->insertGetId($attachmentData);

            AuditLog::recordAction(
                module: 'Customer', // matches CustomerAttachment's own $auditModule so these rows group together
                auditableType: 'CustomerAttachment',
                auditableId: $attachmentId,
                event: 'created',
                recordLabel: $request->document_title,
                description: "added Customer Attachment: {$request->document_title} — Customer #{$customerId}",
                old: null,
                new: $attachmentData,
            );

            Log::info('=== API: CUSTOMER ATTACHMENT CREATED SUCCESSFULLY ===', [
                'attachment_id' => $attachmentId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Attachment uploaded successfully',
                'data' => ['attachment_id' => $attachmentId]
            ], 201);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR CREATING CUSTOMER ATTACHMENT ===', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload attachment'
            ], 500);
        }
    }

    /**
     * Update attachment metadata
     */
    public function update(Request $request, $customerId, $attachmentId)
    {
        Log::info('=== API: UPDATING CUSTOMER ATTACHMENT ===', [
            'customer_id' => $customerId,
            'attachment_id' => $attachmentId,
            'data' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            'document_type' => 'nullable|string|max:100',
            'document_title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
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
            $existingAttachment = DB::table('customer_attachment')
                ->where('customer_id', $customerId)
                ->where('attachment_id', $attachmentId)
                ->first();

            $updateData = [
                'document_type' => $request->document_type,
                'document_title' => $request->document_title,
                'description' => $request->description,
                'updated_at' => now(),
            ];

            $updated = DB::table('customer_attachment')
                ->where('customer_id', $customerId)
                ->where('attachment_id', $attachmentId)
                ->update($updateData);

            if ($updated === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attachment not found'
                ], 404);
            }

            $label = $request->document_title ?: ($existingAttachment->document_title ?? "Attachment #{$attachmentId}");

            AuditLog::recordAction(
                module: 'Customer', // matches CustomerAttachment's own $auditModule so these rows group together
                auditableType: 'CustomerAttachment',
                auditableId: $attachmentId,
                event: 'updated',
                recordLabel: $label,
                description: "updated Customer Attachment: {$label} — Customer #{$customerId}",
                old: (array) $existingAttachment,
                new: $updateData,
            );

            Log::info('=== API: CUSTOMER ATTACHMENT UPDATED SUCCESSFULLY ===');

            return response()->json([
                'success' => true,
                'message' => 'Attachment updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR UPDATING CUSTOMER ATTACHMENT ===', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update attachment'
            ], 500);
        }
    }

    /**
     * Delete attachment
     */
    public function destroy($customerId, $attachmentId)
    {
        Log::info('=== API: DELETING CUSTOMER ATTACHMENT ===', [
            'customer_id' => $customerId,
            'attachment_id' => $attachmentId
        ]);

        try {
            // Get attachment to delete file
            $attachment = DB::table('customer_attachment')
                ->where('customer_id', $customerId)
                ->where('attachment_id', $attachmentId)
                ->first();

            if (!$attachment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attachment not found'
                ], 404);
            }

            // Delete file from storage
            if ($attachment->file_path && Storage::disk('public')->exists($attachment->file_path)) {
                Storage::disk('public')->delete($attachment->file_path);
            }

            // Delete record from database
            DB::table('customer_attachment')
                ->where('customer_id', $customerId)
                ->where('attachment_id', $attachmentId)
                ->delete();

            $label = $attachment->document_title ?? "Attachment #{$attachmentId}";

            AuditLog::recordAction(
                module: 'Customer', // matches CustomerAttachment's own $auditModule so these rows group together
                auditableType: 'CustomerAttachment',
                auditableId: $attachmentId,
                event: 'deleted',
                recordLabel: $label,
                description: "deleted Customer Attachment: {$label} — Customer #{$customerId}",
                old: (array) $attachment,
                new: null,
            );

            Log::info('=== API: CUSTOMER ATTACHMENT DELETED SUCCESSFULLY ===');

            return response()->json([
                'success' => true,
                'message' => 'Attachment deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR DELETING CUSTOMER ATTACHMENT ===', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete attachment'
            ], 500);
        }
    }

    /**
     * Download attachment
     */
    public function download($customerId, $attachmentId)
    {
        Log::info('=== API: DOWNLOADING CUSTOMER ATTACHMENT ===', [
            'customer_id' => $customerId,
            'attachment_id' => $attachmentId
        ]);

        try {
            $attachment = DB::table('customer_attachment')
                ->where('customer_id', $customerId)
                ->where('attachment_id', $attachmentId)
                ->first();

            if (!$attachment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attachment not found'
                ], 404);
            }

            if (!Storage::disk('public')->exists($attachment->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found on server'
                ], 404);
            }

            return Storage::disk('public')->download($attachment->file_path, $attachment->file_name);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR DOWNLOADING CUSTOMER ATTACHMENT ===', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to download attachment'
            ], 500);
        }
    }
}