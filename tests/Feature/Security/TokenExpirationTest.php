<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * Un jeton doit toujours finir par expirer. Un réglage raté en production,
 * vide ou à zéro, ne doit pas les rendre éternels en silence.
 */
class TokenExpirationTest extends TestCase
{
    private const DEFAULT_MINUTES = 60 * 24 * 30;

    /**
     * Relit le fichier de configuration avec une valeur d'environnement
     * donnée, puis remet l'environnement en état.
     */
    private function expirationFor(?string $value): mixed
    {
        $previous = $_ENV['SANCTUM_TOKEN_EXPIRATION'] ?? null;

        try {
            if ($value === null) {
                unset($_ENV['SANCTUM_TOKEN_EXPIRATION'], $_SERVER['SANCTUM_TOKEN_EXPIRATION']);
            } else {
                $_ENV['SANCTUM_TOKEN_EXPIRATION'] = $value;
                $_SERVER['SANCTUM_TOKEN_EXPIRATION'] = $value;
            }

            return (require config_path('sanctum.php'))['expiration'];
        } finally {
            unset($_ENV['SANCTUM_TOKEN_EXPIRATION'], $_SERVER['SANCTUM_TOKEN_EXPIRATION']);

            if ($previous !== null) {
                $_ENV['SANCTUM_TOKEN_EXPIRATION'] = $previous;
                $_SERVER['SANCTUM_TOKEN_EXPIRATION'] = $previous;
            }
        }
    }

    public function test_tokens_expire_by_default(): void
    {
        $this->assertSame(self::DEFAULT_MINUTES, $this->expirationFor(null));
    }

    public function test_a_blank_setting_does_not_make_tokens_eternal(): void
    {
        $this->assertSame(self::DEFAULT_MINUTES, $this->expirationFor(''));
    }

    public function test_a_zero_setting_does_not_make_tokens_eternal(): void
    {
        $this->assertSame(self::DEFAULT_MINUTES, $this->expirationFor('0'));
    }

    public function test_a_negative_setting_does_not_make_tokens_eternal(): void
    {
        $this->assertSame(self::DEFAULT_MINUTES, $this->expirationFor('-1'));
    }

    public function test_a_positive_setting_is_honoured(): void
    {
        $this->assertSame(120, $this->expirationFor('120'));
    }

    public function test_the_running_configuration_expires_tokens(): void
    {
        $expiration = config('sanctum.expiration');

        $this->assertIsInt($expiration);
        $this->assertGreaterThan(0, $expiration);
    }
}
