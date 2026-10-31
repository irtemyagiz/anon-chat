<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Referans veriler (roller, paketler, sağlayıcılar...) tüm testlerde yüklenir.
     * RefreshDatabase veritabanını süreç başına bir kez kurduğu için bu ayar
     * temel sınıfta tutulur.
     */
    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
