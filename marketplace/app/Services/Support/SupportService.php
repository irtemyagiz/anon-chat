<?php

namespace App\Services\Support;

use App\Exceptions\InvalidUploadException;
use App\Exceptions\MarketplaceException;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketRepliedNotification;
use App\Services\Notifications\Notifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Destek talepleri. Ek dosyalar (PDF/JPG/PNG) özel diske rastgele adla kaydedilir.
 */
class SupportService
{
    private const MAX_OPEN_TICKETS = 10;

    public function __construct(private readonly Notifier $notifier) {}

    public function open(User $user, string $subject, string $category, string $body, ?int $pointPurchaseId = null, ?UploadedFile $attachment = null): SupportTicket
    {
        if (SupportTicket::query()->where('user_id', $user->id)->where('status', '!=', 'closed')->count() >= self::MAX_OPEN_TICKETS) {
            throw new MarketplaceException(__('Aynı anda en fazla :count açık talebin olabilir.', ['count' => self::MAX_OPEN_TICKETS]));
        }

        $ticket = DB::transaction(function () use ($user, $subject, $category, $body, $pointPurchaseId, $attachment): SupportTicket {
            $ticket = new SupportTicket(['subject' => $subject, 'category' => $category, 'priority' => in_array($category, ['payment', 'refund', 'copyright'], true) ? 'high' : 'normal']);
            $ticket->user_id = $user->id;
            $ticket->point_purchase_id = $pointPurchaseId;
            $ticket->last_reply_at = now();
            $ticket->save();

            $this->addMessage($ticket, $user, $body, false, $attachment);

            return $ticket;
        });

        $this->notifier->staff('support.manage', __('Yeni destek talebi'), __(':user: :subject', ['user' => $user->email, 'subject' => $subject]), route('admin.support.show', $ticket));

        return $ticket;
    }

    public function reply(SupportTicket $ticket, User $author, string $body, bool $asStaff, ?UploadedFile $attachment = null): SupportMessage
    {
        if ($ticket->status === 'closed' && ! $asStaff) {
            throw new MarketplaceException(__('Kapatılmış talebe yanıt verilemez. Yeni bir talep oluşturabilirsin.'));
        }

        $message = DB::transaction(function () use ($ticket, $author, $body, $asStaff, $attachment): SupportMessage {
            $message = $this->addMessage($ticket, $author, $body, $asStaff, $attachment);

            $ticket->forceFill([
                'status' => $asStaff ? 'answered' : 'awaiting_staff',
                'last_reply_at' => now(),
                'assigned_to' => $asStaff ? ($ticket->assigned_to ?? $author->id) : $ticket->assigned_to,
            ])->save();

            return $message;
        });

        if ($asStaff) {
            $this->notifier->send($ticket->user, new SupportTicketRepliedNotification($ticket->uuid, $ticket->subject));
        }

        return $message;
    }

    public function close(SupportTicket $ticket): void
    {
        $ticket->forceFill(['status' => 'closed'])->save();
    }

    private function addMessage(SupportTicket $ticket, User $author, string $body, bool $asStaff, ?UploadedFile $attachment): SupportMessage
    {
        $message = new SupportMessage(['body' => trim($body)]);
        $message->support_ticket_id = $ticket->id;
        $message->user_id = $author->id;
        $message->is_staff_reply = $asStaff;
        $message->attachment_path = $attachment ? $this->storeAttachment($ticket, $attachment) : null;
        $message->save();

        return $message;
    }

    private function storeAttachment(SupportTicket $ticket, UploadedFile $file): string
    {
        $head = (string) file_get_contents($file->getRealPath(), false, null, 0, 8);
        $extension = match (true) {
            str_starts_with($head, '%PDF-') => 'pdf',
            str_starts_with($head, "\xFF\xD8\xFF") => 'jpg',
            str_starts_with($head, "\x89PNG\r\n\x1A\n") => 'png',
            default => null,
        };

        if ($extension === null || $file->getSize() > 10 * 1024 * 1024) {
            throw new InvalidUploadException(__('Ek dosya PDF, JPG veya PNG olmalı ve en fazla 10 MB olabilir.'));
        }

        return Storage::disk('private')->putFileAs("support/{$ticket->uuid}", $file, Str::random(40).'.'.$extension);
    }
}
