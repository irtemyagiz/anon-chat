<?php

namespace App\Exceptions;

class AlreadyOwnedException extends MarketplaceException
{
    public function __construct()
    {
        parent::__construct('Bu ürünü bu lisansla zaten satın aldınız.');
    }
}
