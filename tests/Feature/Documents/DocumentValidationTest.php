<?php

namespace Tests\Feature\Documents;

use App\Actions\Documents\CreateDocument;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_name_is_required_and_bounded(): void
    {
        $this->postJson('/api/documents', ['content' => '{}'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->postJson('/api/documents', ['name' => '   ', 'content' => '{}'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->postJson('/api/documents', ['name' => str_repeat('a', 256), 'content' => '{}'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_content_must_be_a_json_string(): void
    {
        $this->postJson('/api/documents', ['name' => 'Doc'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);

        $this->postJson('/api/documents', ['name' => 'Doc', 'content' => '{pas du json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);

        $this->postJson('/api/documents', ['name' => 'Doc', 'content' => ['model' => []]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);

        $this->postJson('/api/documents', ['name' => 'Doc', 'content' => 42])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_content_size_is_bounded_in_bytes(): void
    {
        config(['documents.max_content_bytes' => 100]);

        // 100 octets pile : accepté.
        $atLimit = '"'.str_repeat('a', 98).'"';
        $this->postJson('/api/documents', ['name' => 'Pile', 'content' => $atLimit])->assertCreated();

        // 101 octets : refusé.
        $overLimit = '"'.str_repeat('a', 99).'"';
        $this->postJson('/api/documents', ['name' => 'Trop', 'content' => $overLimit])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);

        // 60 caractères mais 118 octets : refusé, la limite compte bien des octets.
        $multibyte = '"'.str_repeat('é', 58).'"';
        $this->assertSame(60, mb_strlen($multibyte));
        $this->assertSame(118, strlen($multibyte));
        $this->postJson('/api/documents', ['name' => 'Accents', 'content' => $multibyte])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);

        $this->assertDatabaseCount('documents', 1);
    }

    public function test_update_is_validated_too(): void
    {
        config(['documents.max_content_bytes' => 100]);
        $document = Document::factory()->for($this->user)->create(['name' => 'Intact', 'content' => '{}']);

        $this->patchJson("/api/documents/{$document->id}", ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->patchJson("/api/documents/{$document->id}", ['content' => 'pas du json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);

        $this->patchJson("/api/documents/{$document->id}", ['content' => '"'.str_repeat('a', 200).'"'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);

        $this->assertSame('Intact', $document->fresh()->name);
        $this->assertSame('{}', $document->fresh()->content);
    }

    public function test_document_count_per_user_is_bounded(): void
    {
        config(['documents.max_per_user' => 2]);
        Document::factory()->count(2)->for($this->user)->create();
        // Les documents des autres ne comptent pas dans le quota.
        Document::factory()->count(3)->create();

        $this->postJson('/api/documents', ['name' => 'Un de trop', 'content' => '{}'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.content.0', CreateDocument::QUOTA_MESSAGE);

        $this->assertSame(2, $this->user->documents()->count());

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/documents', ['name' => 'Autre compte', 'content' => '{}'])->assertCreated();
    }
}
