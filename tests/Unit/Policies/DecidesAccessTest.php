<?php

namespace Tests\Unit\Policies;

use App\Policies\Concerns\DecidesAccess;
use PHPUnit\Framework\TestCase;

/**
 * Les quatre primitives de refus sont désormais la clé de voûte du
 * cloisonnement : les sept Policies s'appuient dessus. Ce test les tient.
 *
 * Deux propriétés comptent plus que les autres :
 *
 * 1. un refus caché doit être **indiscernable** d'une ressource absente ;
 * 2. `reserve` ne doit **pas** évaluer sa fermeture quand la première
 *    branche autorise — sinon chaque autorisation ajoute une requête, et
 *    les trois tests qui comptent les requêtes tombent.
 */
class DecidesAccessTest extends TestCase
{
    private object $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new class
        {
            use DecidesAccess {
                allowOrHide as public;
                allowOrDeny as public;
                denyOrHide as public;
                reserve as public;
            }
        };
    }

    public function test_allow_or_hide_allows_or_hides(): void
    {
        $this->assertTrue($this->policy->allowOrHide(true)->allowed());

        $hidden = $this->policy->allowOrHide(false);
        $this->assertTrue($hidden->denied());
        $this->assertSame(404, $hidden->status());
    }

    public function test_a_hidden_refusal_carries_no_message(): void
    {
        // Le message est ce qui trahirait l'existence de la ressource.
        $this->assertNull($this->policy->allowOrHide(false)->message());
    }

    public function test_allow_or_deny_says_why(): void
    {
        $this->assertTrue($this->policy->allowOrDeny(true, 'parce que')->allowed());

        $denied = $this->policy->allowOrDeny(false, 'parce que');
        $this->assertTrue($denied->denied());
        $this->assertSame('parce que', $denied->message());
        $this->assertNotSame(404, $denied->status());
    }

    public function test_deny_or_hide_picks_the_right_refusal(): void
    {
        $known = $this->policy->denyOrHide(true, 'parce que');
        $this->assertSame('parce que', $known->message());
        $this->assertNotSame(404, $known->status());

        $unknown = $this->policy->denyOrHide(false, 'parce que');
        $this->assertSame(404, $unknown->status());
        $this->assertNull($unknown->message());
    }

    public function test_reserve_covers_the_three_branches(): void
    {
        // Autorisé.
        $this->assertTrue($this->policy->reserve(true, fn () => true, 'parce que')->allowed());

        // Refusé, mais il sait que ça existe : refus explicite.
        $explicit = $this->policy->reserve(false, fn () => true, 'parce que');
        $this->assertSame('parce que', $explicit->message());
        $this->assertNotSame(404, $explicit->status());

        // Refusé, et il n'en sait rien : 404 indiscernable.
        $hidden = $this->policy->reserve(false, fn () => false, 'parce que');
        $this->assertSame(404, $hidden->status());
        $this->assertNull($hidden->message());
    }

    public function test_reserve_does_not_evaluate_its_closure_when_it_allows(): void
    {
        // Le test qui protège le compte de requêtes : hasMember() et les
        // relations d'un rendu interrogent la base. Les évaluer alors que la
        // première branche suffit ajouterait une requête par autorisation.
        $called = false;

        $this->policy->reserve(true, function () use (&$called) {
            $called = true;

            return true;
        }, 'parce que');

        $this->assertFalse($called, 'reserve() a évalué sa fermeture alors que la première branche autorisait.');
    }

    public function test_reserve_evaluates_its_closure_once_when_it_refuses(): void
    {
        $calls = 0;

        $this->policy->reserve(false, function () use (&$calls) {
            $calls++;

            return true;
        }, 'parce que');

        $this->assertSame(1, $calls);
    }
}
