<?php

namespace App\Http\Controllers;

use App\Services\HrProfile\HrProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Seksi "HR Profile" karyawan (H3.5): data HR, foto, tanda tangan.
 *
 * IZIN: di rute lewat `employee.section:hr_profile[,view]` — target diri sendiri memakai slug
 * `my-profile.section.hr_profile.*`, target orang lain memakai `employee.section.hr_profile.*`.
 * Controller ini menambahkan aturan yang tidak bisa diungkapkan slug:
 *   - peran 'self' (target == pemilik sesi) vs 'hr' menentukan field yang boleh diubah;
 *   - TANDA TANGAN hanya dapat diunggah/digambar oleh PEMILIKNYA (T9). HR hanya boleh menghapus.
 *
 * Semua aksi tulis memakai POST dan mewajibkan header X-Requested-With (AJAX): rute /api dikecualikan
 * dari CSRF token, jadi header kustom ini (tak bisa dikirim lintas-situs tanpa CORS) menjadi
 * lapisan tambahan di atas cookie SameSite.
 */
class EmployeeHrProfileController extends Controller
{
    public function __construct(private readonly HrProfileService $service)
    {
    }

    public function show(Request $request, int $employeeId): JsonResponse
    {
        $isSelf = $this->isSelf($employeeId);

        return response()->json([
            'success' => true,
            'data'    => $this->service->forEmployee($employeeId, !$isSelf) + [
                'is_self'          => $isSelf,
                'can_upload_signature' => $isSelf,
            ],
        ]);
    }

    public function save(Request $request, int $employeeId): JsonResponse
    {
        if ($denied = $this->requireAjax($request)) {
            return $denied;
        }

        $input = $request->except(['status_reason', '_token']);
        $actor = $this->isSelf($employeeId) ? 'self' : 'hr';

        try {
            $result = $this->service->save(
                $employeeId,
                is_array($input) ? $input : [],
                $actor,
                $this->actorId(),
                $actor === 'hr' ? (string) $request->input('status_reason', '') : null
            );
        } catch (\Throwable $e) {
            Log::error('HR profile: gagal menyimpan', ['employee_id' => $employeeId, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Could not save the HR profile. Please try again.'], 500);
        }

        if (!$result['ok']) {
            $forbidden = !empty($result['rejected']);
            if ($forbidden) {
                // Percobaan mengubah field terlarang dicatat (nama field saja, bukan nilainya).
                Log::warning('HR profile: field terlarang ditolak', [
                    'target' => $employeeId, 'actor' => $this->actorId(), 'fields' => $result['rejected'],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['errors']['_'] ?? 'Please correct the highlighted fields.',
                'errors'  => $result['errors'],
            ], $forbidden ? 403 : 422);
        }

        return response()->json(['success' => true, 'message' => 'HR profile saved successfully.']);
    }

    public function uploadPhoto(Request $request, int $employeeId): JsonResponse
    {
        return $this->upload($request, $employeeId, 'photo');
    }

    public function uploadSignature(Request $request, int $employeeId): JsonResponse
    {
        if (!$this->isSelf($employeeId)) {
            return response()->json(['success' => false, 'message' => 'Only the owner can add or change their own signature.'], 403);
        }

        return $this->upload($request, $employeeId, 'signature');
    }

    public function deletePhoto(Request $request, int $employeeId): JsonResponse
    {
        return $this->remove($request, $employeeId, 'photo');
    }

    public function deleteSignature(Request $request, int $employeeId): JsonResponse
    {
        return $this->remove($request, $employeeId, 'signature');
    }

    public function photo(int $employeeId): BinaryFileResponse|JsonResponse
    {
        return $this->serve($employeeId, 'photo');
    }

    public function signature(int $employeeId): BinaryFileResponse|JsonResponse
    {
        return $this->serve($employeeId, 'signature');
    }

    // ─────────────────────────────────────────────────────────────────────

    private function upload(Request $request, int $employeeId, string $kind): JsonResponse
    {
        if ($denied = $this->requireAjax($request)) {
            return $denied;
        }

        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return response()->json(['success' => false, 'message' => 'Please choose an image file to upload.'], 422);
        }

        try {
            $this->service->storeImage($employeeId, $kind, $file->getRealPath(), $this->actorId());
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('HR profile: gagal menyimpan gambar', ['employee_id' => $employeeId, 'kind' => $kind, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Could not save the image. Please try again.'], 500);
        }

        return response()->json([
            'success' => true,
            'message' => ($kind === 'photo' ? 'Photo' : 'Signature') . ' saved successfully.',
        ]);
    }

    private function remove(Request $request, int $employeeId, string $kind): JsonResponse
    {
        if ($denied = $this->requireAjax($request)) {
            return $denied;
        }

        try {
            $this->service->deleteImage($employeeId, $kind, $this->actorId());
        } catch (\Throwable $e) {
            Log::error('HR profile: gagal menghapus gambar', ['employee_id' => $employeeId, 'kind' => $kind, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Could not remove the image. Please try again.'], 500);
        }

        return response()->json(['success' => true, 'message' => ($kind === 'photo' ? 'Photo' : 'Signature') . ' removed.']);
    }

    private function serve(int $employeeId, string $kind): BinaryFileResponse|JsonResponse
    {
        $file = $this->service->imageFile($employeeId, $kind);
        if (!$file) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }

        return response()->file($file['path'], [
            'Content-Type'           => $file['mime'],
            'Content-Disposition'    => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, no-store',
        ]);
    }

    private function requireAjax(Request $request): ?JsonResponse
    {
        return $request->ajax()
            ? null
            : response()->json(['success' => false, 'message' => 'Invalid request.'], 400);
    }

    private function actorId(): int
    {
        return (int) (session('user')['id'] ?? 0);
    }

    private function isSelf(int $employeeId): bool
    {
        return $employeeId !== 0 && $employeeId === $this->actorId();
    }
}
