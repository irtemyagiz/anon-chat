<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Kritik işlemler için değiştirilemez denetim kaydı oluşturur
 * (puan düzeltmesi, komisyon/kur değişikliği, iade, hesap engelleme...).
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>  $context
     */
    public function record(
        string $event,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $actor = null,
        array $context = [],
    ): AuditLog {
        $request = app()->runningInConsole() ? null : request();

        return AuditLog::forceCreate([
            'actor_id' => $actor?->id ?? auth()->id(),
            'event' => $event,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'context' => $context ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }
}
