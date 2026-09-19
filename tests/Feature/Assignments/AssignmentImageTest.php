<?php

namespace Tests\Feature\Assignments;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AssignmentImageTest extends TestCase
{
    use RefreshDatabase;

    private Assignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->for($teacher, 'teacher')->create();
        $this->assignment = Assignment::factory()->for($classroom)->create();
        Sanctum::actingAs($teacher);
    }

    private function upload(UploadedFile $file)
    {
        return $this->post("/api/assignments/{$this->assignment->id}/image", ['image' => $file], ['Accept' => 'application/json']);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function acceptedImages(): array
    {
        return [
            'png' => ['schema.png', 'image/png', 'png'],
            'jpeg' => ['photo.jpg', 'image/jpeg', 'jpg'],
            'webp' => ['image.webp', 'image/webp', 'webp'],
        ];
    }

    #[DataProvider('acceptedImages')]
    public function test_accepted_image_types(string $name, string $mime, string $extension): void
    {
        $this->upload(UploadedFile::fake()->image($name, 40, 30))
            ->assertOk()
            ->assertJsonPath('data.has_image', true)
            ->assertJsonPath('data.image_url', url("/api/assignments/{$this->assignment->id}/image"));

        $fresh = $this->assignment->fresh();
        $this->assertSame($mime, $fresh->image_mime);
        $this->assertStringEndsWith('.'.$extension, $fresh->image_path);
        $this->assertStringStartsWith("assignments/{$fresh->classroom_id}/{$fresh->id}/", $fresh->image_path);
        Storage::disk('local')->assertExists($fresh->image_path);

        $this->get("/api/assignments/{$this->assignment->id}/image")
            ->assertOk()
            ->assertHeader('Content-Type', $mime);
    }

    public function test_svg_is_refused(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';

        $this->upload(UploadedFile::fake()->createWithContent('dessin.svg', $svg))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);

        $this->assertNull($this->assignment->fresh()->image_path);
    }

    public function test_html_disguised_as_png_is_refused(): void
    {
        $html = '<!DOCTYPE html><html><body><script>alert(document.cookie)</script></body></html>';

        $this->upload(UploadedFile::fake()->createWithContent('innocent.png', $html))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);

        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_gif_is_refused(): void
    {
        $this->upload(UploadedFile::fake()->image('anim.gif'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);
    }

    public function test_image_size_is_bounded(): void
    {
        config(['assignments.max_image_kb' => 10]);

        $this->upload(UploadedFile::fake()->image('grande.png')->size(11))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);
    }

    public function test_missing_or_non_file_image_is_refused(): void
    {
        $this->postJson("/api/assignments/{$this->assignment->id}/image", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);

        $this->postJson("/api/assignments/{$this->assignment->id}/image", ['image' => 'data:image/png;base64,AAAA'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);
    }

    public function test_replacing_an_image_deletes_the_previous_one(): void
    {
        $this->upload(UploadedFile::fake()->image('a.png'))->assertOk();
        $first = $this->assignment->fresh()->image_path;

        $this->upload(UploadedFile::fake()->image('b.jpg'))->assertOk();
        $second = $this->assignment->fresh()->image_path;

        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);
    }

    public function test_extension_comes_from_the_content_not_the_name(): void
    {
        $png = UploadedFile::fake()->image('source.png');
        $renamed = UploadedFile::fake()->createWithContent('photo.jpg', file_get_contents($png->getRealPath()));

        $this->upload($renamed)->assertOk();

        $fresh = $this->assignment->fresh();
        $this->assertSame('image/png', $fresh->image_mime);
        $this->assertStringEndsWith('.png', $fresh->image_path);
        $this->assertStringNotContainsString('photo', $fresh->image_path);
    }

    public function test_removing_the_image(): void
    {
        $this->upload(UploadedFile::fake()->image('a.png'))->assertOk();
        $path = $this->assignment->fresh()->image_path;

        $this->deleteJson("/api/assignments/{$this->assignment->id}/image")
            ->assertOk()
            ->assertJsonPath('data.has_image', false)
            ->assertJsonMissingPath('data.image_url');

        Storage::disk('local')->assertMissing($path);
        $this->getJson("/api/assignments/{$this->assignment->id}/image")->assertNotFound();
    }
}
