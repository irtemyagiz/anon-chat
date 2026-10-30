<?php

namespace App\Enums;

enum WalletTransactionType: string
{
    case TopUp = 'top_up';
    case Purchase = 'purchase';
    case PurchaseRefund = 'purchase_refund';
    case TopUpRefund = 'top_up_refund';
    case Chargeback = 'chargeback';
    case AdminAdjustment = 'admin_adjustment';
    case Promotion = 'promotion';
    case Reversal = 'reversal';

    /**
     * Kullanıcıya gösterilecek Türkçe etiket.
     */
    public function label(): string
    {
        return match ($this) {
            self::TopUp => 'Puan yükleme',
            self::Purchase => 'Ürün satın alma',
            self::PurchaseRefund => 'Satın alma iadesi',
            self::TopUpRefund => 'Yükleme iadesi',
            self::Chargeback => 'Ödeme itirazı',
            self::AdminAdjustment => 'Yönetici düzeltmesi',
            self::Promotion => 'Promosyon puanı',
            self::Reversal => 'Hatalı işlem geri alma',
        };
    }

    /**
     * Bu işlem türünün kullanıcı bakiyesini eksiye düşürmesine izin verilir mi?
     *
     * Ödeme itirazı (chargeback) sağlayıcı tarafından zorla uygulandığından
     * kullanıcı puanlarını harcamış olsa bile kaydedilmelidir.
     */
    public function allowsNegativeBalance(): bool
    {
        return $this === self::Chargeback;
    }
}
