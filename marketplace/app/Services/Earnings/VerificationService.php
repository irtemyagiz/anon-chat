<?php

namespace App\Services\Earnings;

use App\Enums\VerificationStatus;
use App\Exceptions\InvalidUploadException;
use App\Exceptions\MarketplaceException;
use App\Models\CreatorProfile;
use App\Models\CreatorVerification;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Satıcı kimlik ve vergi doğrulaması. Hakediş ödemesi yalnızca onaylı doğrulaması
 * olan creator'lara yapılır. Kimlik/vergi numarası ve adres şifreli saklanır;
 * belge özel diske rastgele adla kaydedilir.
 */
class VerificationService
{
    private const DOCUMENT_DISK = 'private';

    private const MAX_DOCUMENT_BYTES = 10 * 1024 * 1024;

    public function __construct(private readonly AuditLogger $audit) {}

    public function latest(CreatorProfile $profile): ?CreatorVerification
    {
        return $profile->verifications()->latest('id')->first();
    }

    /**
     * @param  array{legal_name: string, entity_type: string, tax_number?: ?string, tax_office?: ?string, identity_number?: ?string, country_code: string, address: string}  $data
     */
    public function submit(CreatorProfile $profile, array $data, ?UploadedFile $document): CreatorVerification
    {
        $latest = $this->latest($profile);

        if ($latest !== null && in_array($latest->status, [VerificationStatus::Pending, VerificationStatus::Approved], true)) {
            throw new MarketplaceException(__('Bekleyen veya onaylanmış bir doğrulamanız zaten var.'));
        }

        $path = $document ? $this->storeDocument($profile, $document) : null;

        return DB::transaction(function () use ($profile, $data, $path): CreatorVerification {
            $verification = $profile->verifications()->create([...$data, 'document_path' => $path]);
            $verification->forceFill(['status' => VerificationStatus::Pending])->save();

            $this->audit->record('creator.verification_submitted', $verification, actor: $profile->user);

            return $verification;
        });
    }

    public function review(CreatorVerification $verification, VerificationStatus $status, User $admin, ?string $note = null): void
    {
        if ($verification->status !== VerificationStatus::Pending) {
            throw new MarketplaceException(__('Yalnızca bekleyen doğrulamalar incelenebilir.'));
        }

        $verification->forceFill([
            'status' => $status,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ])->save();

        $this->audit->record('creator.verification_'.$status->value, $verification, ['status' => 'pending'], ['status' => $status->value], $admin, array_filter(['note' => $note]));
    }

    /**
     * Yalnızca PDF, JPG ve PNG kabul edilir; içerik dosya imzasıyla doğrulanır.
     */
    private function storeDocument(CreatorProfile $profile, UploadedFile $document): string
    {
        $head = (string) file_get_contents($document->getRealPath(), false, null, 0, 8);
        $extension = match (true) {
            str_starts_with($head, '%PDF-') => 'pdf',
            str_starts_with($head, "\xFF\xD8\xFF") => 'jpg',
            str_starts_with($head, "\x89PNG\r\n\x1A\n") => 'png',
            default => null,
        };

        if ($extension === null || $document->getSize() > self::MAX_DOCUMENT_BYTES) {
            throw new InvalidUploadException(__('Belge PDF, JPG veya PNG olmalı ve en fazla 10 MB olabilir.'));
        }

        return Storage::disk(self::DOCUMENT_DISK)->putFileAs("verifications/{$profile->id}", $document, Str::random(40).'.'.$extension);
    }
}
