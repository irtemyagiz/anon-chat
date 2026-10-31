<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tüm platform bildirimlerinin temeli: her bildirim site içinde (database)
 * saklanır; kullanıcının o kategori için e-posta tercihi açıksa e-posta da gönderilir.
 * Kuyrukta ve veritabanı transaction'ı tamamlandıktan sonra gönderilir.
 */
abstract class MarketplaceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Bildirim kategorileri ve varsayılan e-posta tercihi.
     *
     * @var array<string, array{label: string, mail: bool}>
     */
    public const CATEGORIES = [
        'purchases' => ['label' => 'Satın alma ve puan yükleme', 'mail' => true],
        'sales' => ['label' => 'Satışlarım (creator)', 'mail' => true],
        'moderation' => ['label' => 'Ürün ve mağaza onayları', 'mail' => true],
        'payouts' => ['label' => 'Hakediş ödemeleri', 'mail' => true],
        'community' => ['label' => 'Yorum, değerlendirme ve takipçi', 'mail' => false],
        'creator_updates' => ['label' => 'Takip ettiğim tasarımcılar ve satın aldığım modellerin güncellemeleri', 'mail' => false],
        'support' => ['label' => 'Destek talepleri', 'mail' => true],
        'admin' => ['label' => 'Yönetici uyarıları', 'mail' => true],
    ];

    public function __construct()
    {
        $this->afterCommit();
    }

    abstract public function category(): string;

    abstract public function title(): string;

    abstract public function body(): string;

    abstract public function url(): ?string;

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return self::wantsMail($notifiable, $this->category()) ? ['database', 'mail'] : ['database'];
    }

    public static function wantsMail(User $user, string $category): bool
    {
        $setting = $user->settings()->where('key', "notify.mail.{$category}")->value('value');

        return $setting === null ? (self::CATEGORIES[$category]['mail'] ?? false) : $setting === '1';
    }

    public function toMail(User $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->title().' · '.config('app.name'))
            ->greeting(__('Merhaba :name,', ['name' => $notifiable->first_name]))
            ->line($this->body());

        if ($this->url() !== null) {
            $message->action(__('Görüntüle'), $this->url());
        }

        return $message->salutation(config('app.name'))
            ->line(__('Bildirim tercihlerini hesap ayarlarından değiştirebilirsin.'));
    }

    /**
     * @return array{category: string, title: string, body: string, url: ?string}
     */
    public function toArray(User $notifiable): array
    {
        return [
            'category' => $this->category(),
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->url(),
        ];
    }
}
