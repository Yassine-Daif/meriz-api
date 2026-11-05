<?php

namespace Tests\Feature\Documents;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentCreationRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_burst_does_not_block_saving_or_other_accounts_on_same_ip(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        Sanctum::actingAs($first);
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/documents', ['name' => 'Document', 'content' => '{}'])->assertCreated();
            $ids[] = $response->json('data.id');
        }
        $this->postJson('/api/documents', ['name' => 'Trop vite', 'content' => '{}'])
            ->assertTooManyRequests()->assertHeader('Retry-After');
        $this->patchJson('/api/documents/'.$ids[0], ['content' => '{"saved":true}'])->assertOk();
        Sanctum::actingAs($second);
        $this->postJson('/api/documents', ['name' => 'Autre élève', 'content' => '{}'])->assertCreated();
        $this->assertSame(5, Document::where('user_id', $first->id)->count());
    }
}
