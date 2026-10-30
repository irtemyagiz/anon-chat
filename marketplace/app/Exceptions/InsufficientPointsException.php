<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientPointsException extends RuntimeException
{
    public function __construct(public readonly int $required, public readonly int $available)
    {
        parent::__construct("Yetersiz puan bakiyesi. Gerekli: {$required}, mevcut: {$available}.");
    }

    public function shortfall(): int
    {
        return max(0, $this->required - $this->available);
    }
}
