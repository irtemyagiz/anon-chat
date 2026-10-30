<?php

namespace App\Services\Payments;

use RuntimeException;

class InvalidWebhookSignatureException extends RuntimeException {}
