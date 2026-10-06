<?php

namespace App\Models\Concerns;

use App\Models\EmployeeHrProfile;
use Illuminate\Support\Facades\Storage;

/**
 * A letter signed with its signatory's signature from the employee master data
 * (employee_hr_profile.signature_path) — offering letters and the letters of
 * the Letter Templates hub.
 *
 * Columns: signatory_employee_id, signature_path, signed_at, signed_by_employee_id.
 * The model says where its copy goes through signatureFolder().
 */
trait SignsWithMasterSignature
{
    /** The private disk the signature copied onto a letter is kept on. */
    public const SIGNATURE_DISK = 'local';

    abstract protected function signatureFolder(): string;

    public function isSigned(): bool
    {
        return $this->signed_at !== null && $this->signature_path !== null;
    }

    /** The signature image inlined for the PDF renderer, which cannot reach a private disk by URL. */
    public function signatureDataUri(): ?string
    {
        $disk = Storage::disk(self::SIGNATURE_DISK);

        if (!$this->isSigned() || !$disk->exists($this->signature_path)) {
            return null;
        }

        return 'data:' . $disk->mimeType($this->signature_path) . ';base64,' . base64_encode($disk->get($this->signature_path));
    }

    /**
     * Signs the letter with its signatory's signature from the employee master
     * data. The image is copied onto the letter, so the letter stays as it was
     * signed whatever later happens to the master data.
     */
    public function signWithMasterSignature(int $signedByEmployeeId): void
    {
        $source = EmployeeHrProfile::signaturePathOf($this->signatory_employee_id);
        if (!$source) {
            throw new \RuntimeException('The signatory has no signature in the employee master data yet.');
        }

        $extension = pathinfo($source, PATHINFO_EXTENSION) ?: 'png';
        $target = $this->signatureFolder() . '/signature-' . now()->format('YmdHis') . ".{$extension}";
        Storage::disk(self::SIGNATURE_DISK)->put($target, Storage::disk(EmployeeHrProfile::FILE_DISK)->get($source));

        $old = $this->signature_path;
        $this->forceFill([
            'signature_path'        => $target,
            'signed_at'             => now(),
            'signed_by_employee_id' => $signedByEmployeeId,
        ])->save();

        if ($old && $old !== $target) {
            Storage::disk(self::SIGNATURE_DISK)->delete($old);
        }
    }

    /** Takes the signature off — a letter that changed has to be signed again. */
    public function removeSignature(): void
    {
        if ($this->signature_path) {
            Storage::disk(self::SIGNATURE_DISK)->delete($this->signature_path);
        }

        $this->forceFill(['signature_path' => null, 'signed_at' => null, 'signed_by_employee_id' => null])->save();
    }
}
