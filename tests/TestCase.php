<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Les scénarios historiques vérifient le français. Symfony simule
        // sinon un navigateur anglophone par défaut.
        $this->withHeader('Accept-Language', 'fr');
    }
}
