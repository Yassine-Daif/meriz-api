<?php

namespace Tests\Feature\Security;

use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResponseHeadersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, array<int, string>>
     */
    private function expectedHeaders(): array
    {
        return [
            ['X-Content-Type-Options', 'nosniff'],
            ['Referrer-Policy', 'no-referrer'],
            ['X-Frame-Options', 'DENY'],
        ];
    }

    private function assertHardened(TestResponse $response): void
    {
        foreach ($this->expectedHeaders() as [$header, $value]) {
            $this->assertSame($value, $response->headers->get($header), "Entête manquant : {$header}");
        }

        $this->assertStringContainsString(
            "default-src 'none'",
            (string) $response->headers->get('Content-Security-Policy'),
        );
    }

    public function test_a_successful_response_carries_the_security_headers(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->assertHardened($this->getJson('/api/me')->assertOk());
    }

    public function test_a_public_response_carries_them_too(): void
    {
        $this->assertHardened($this->getJson('/api/health')->assertOk());
    }

    public function test_an_unauthenticated_response_carries_them(): void
    {
        $this->assertHardened($this->getJson('/api/me')->assertUnauthorized());
    }

    public function test_a_not_found_response_carries_them(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->assertHardened(
            $this->getJson('/api/documents/01hzzzzzzzzzzzzzzzzzzzzzzz')->assertNotFound(),
        );
    }

    public function test_a_validation_error_carries_them(): void
    {
        $this->assertHardened($this->postJson('/api/auth/login', [])->assertStatus(422));
    }

    public function test_a_media_route_keeps_its_own_headers(): void
    {
        Storage::fake('local');

        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->for($teacher, 'teacher')->create();
        $lesson = Lesson::factory()->for($classroom)->published()->create();

        Sanctum::actingAs($teacher);

        $mediumId = $this->post("/api/lessons/{$lesson->id}/media", [
            'file' => UploadedFile::fake()->image('schema.png', 20, 20),
        ])->assertCreated()->json('data.id');

        $response = $this->get("/api/lessons/{$lesson->id}/media/{$mediumId}")->assertOk();

        // La route de fichier pose sa propre CSP et son propre cache : le
        // middleware ne doit rien écraser.
        $this->assertSame("default-src 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
    }

    public function test_the_rate_limit_really_bites(): void
    {
        // La sonde est plafonnée à 60 par minute : au 61e appel, 429.
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/health')->assertOk();
        }

        $this->getJson('/api/health')->assertStatus(429);
    }

    public function test_an_authenticated_account_has_a_general_ceiling(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Le plafond général est à 300 par minute : on vérifie que le
        // compteur est bien branché, sans monter jusqu'au refus.
        $response = $this->getJson('/api/me')->assertOk();

        $this->assertSame('300', $response->headers->get('X-RateLimit-Limit'));
        $this->assertSame('299', $response->headers->get('X-RateLimit-Remaining'));
    }
}
