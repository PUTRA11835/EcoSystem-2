<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class EmployeeAttachmentController extends Controller
{
    /**
     * Get all attachments for an employee
     */
    public function index($employeeId)
    {
        try {
            $attachments = DB::table('employee_attachment')
                ->where('employee_id', $employeeId)
                ->orderBy('attachment_id', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $attachments
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR FETCHING EMPLOYEE ATTACHMENTS ===', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attachments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload a new attachment
     */
    public function store(Request $request, $employeeId)
    {
        $validator = Validator::make($request->all(), [
            'document_type'  => 'required|string|max:100',
            'document_title' => 'required|string|max:255',
            'description'    => 'nullable|string',
            'file'           => 'required|file|max:10240|mimes:pdf,doc,docx,jpg,jpeg,png,xlsx,xls',
        ], [
            'document_type.required'  => 'Document type is required',
            'document_title.required' => 'Document title is required',
            'file.required'           => 'Please select a file to upload',
            'file.max'                => 'File size must not exceed 10MB',
            'file.mimes'              => 'Allowed file types: PDF, DOC, DOCX, JPG, PNG, XLSX, XLS',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $employeeExists = DB::table('employee')->where('employee_id', $employeeId)->exists();
            if (!$employeeExists) {
                return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
            }

            $file     = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('employee_attachments/' . $employeeId, $fileName, 'public');

            $attachmentId = DB::table('employee_attachment')->insertGetId([
                'employee_id'    => $employeeId,
                'document_type'  => $request->document_type,
                'document_title' => $request->document_title,
                'description'    => $request->description,
                'file_name'      => $fileName,
                'file_path'      => $filePath,
                'file_size'      => $file->getSize(),
                'mime_type'      => $file->getMimeType(),
                'uploaded_by'    => auth()->user()->username ?? 'system',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            Log::info('=== API: EMPLOYEE ATTACHMENT CREATED ===', ['attachment_id' => $attachmentId]);

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'data'    => ['attachment_id' => $attachmentId]
            ], 201);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR CREATING EMPLOYEE ATTACHMENT ===', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an attachment
     */
    public function destroy($employeeId, $attachmentId)
    {
        try {
            $attachment = DB::table('employee_attachment')
                ->where('employee_id', $employeeId)
                ->where('attachment_id', $attachmentId)
                ->first();

            if (!$attachment) {
                return response()->json(['success' => false, 'message' => 'Attachment not found'], 404);
            }

            // Delete the physical file
            if ($attachment->file_path && Storage::disk('public')->exists($attachment->file_path)) {
                Storage::disk('public')->delete($attachment->file_path);
            }

            DB::table('employee_attachment')
                ->where('attachment_id', $attachmentId)
                ->delete();

            Log::info('=== API: EMPLOYEE ATTACHMENT DELETED ===', ['attachment_id' => $attachmentId]);

            return response()->json([
                'success' => true,
                'message' => 'Attachment deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR DELETING EMPLOYEE ATTACHMENT ===', [
                'employee_id'  => $employeeId,
                'attachment_id' => $attachmentId,
                'error'        => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete attachment: ' . $e->getMessage()
            ], 500);
        }
    }
}
