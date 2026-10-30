<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Kullanıcıya gösterilebilir iş kuralı ihlalleri (ör. minimum yükleme altı,
 * yayında olmayan ürün, doğrulanmamış satıcı).
 */
class MarketplaceException extends RuntimeException {}
