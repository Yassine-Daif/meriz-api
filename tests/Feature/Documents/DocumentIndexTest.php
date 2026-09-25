<?php

namespace Tests\Feature\Documents;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_list_is_sorted_from_most_recent_to_oldest(): void
    {
        $old = Document::factory()->for($this->user)->create(['name' => 'Ancien']);
        $this->travel(1)->hour();
        $recent = Document::factory()->for($this->user)->create(['name' => 'Récent']);
        $this->travel(1)->hour();
        $newest = Document::factory()->for($this->user)->create(['name' => 'Tout neuf']);

        $this->getJson('/api/documents')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$newest->id, $recent->id, $old->id]);
    }

    public function test_updating_a_document_moves_it_to_the_top(): void
    {
        $first = Document::factory()->for($this->user)->create();
        $this->travel(1)->hour();
        $second = Document::factory()->for($this->user)->create();
        $this->travel(1)->hour();

        $this->patchJson("/api/documents/{$first->id}", ['name' => 'Modifié'])->assertOk();

        $this->getJson('/api/documents')
            ->assertJsonPath('data.*.id', [$first->id, $second->id]);
    }

    public function test_list_does_not_include_content(): void
    {
        Document::factory()->for($this->user)->create(['content' => '{"lourd":"'.str_repeat('x', 1000).'"}']);

        $response = $this->getJson('/api/documents')->assertOk();

        $this->assertSame(
            ['id', 'name', 'assignment_id', 'created_at', 'updated_at'],
            array_keys($response->json('data.0')),
        );
        $response->assertDontSee('lourd');
    }

    public function test_list_is_paginated(): void
    {
        Document::factory()->count(5)->for($this->user)->create();

        $this->getJson('/api/documents?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonStructure(['data', 'links' => ['next'], 'meta']);

        $this->getJson('/api/documents?per_page=2&page=3')
            ->assertJsonCount(1, 'data');
    }

    public function test_empty_list(): void
    {
        $this->getJson('/api/documents')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }

    public function test_page_size_is_bounded(): void
    {
        $this->getJson('/api/documents?per_page=500')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);

        $this->getJson('/api/documents?per_page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }
}
