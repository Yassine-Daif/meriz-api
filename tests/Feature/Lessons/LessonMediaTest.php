<?php

namespace Tests\Feature\Lessons;

use App\Actions\Lessons\ManageLessonMedia;
use App\Enums\MediaKind;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LessonMediaTest extends TestCase
{
    use RefreshDatabase;

    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->for($teacher, 'teacher')->create();
        $this->lesson = Lesson::factory()->for($classroom)->create();
        Sanctum::actingAs($teacher);
    }

    private function upload(UploadedFile $file)
    {
        return $this->post("/api/lessons/{$this->lesson->id}/media", ['file' => $file], ['Accept' => 'application/json']);
    }

    /**
     * Fichiers audio minimaux mais reconnus par finfo.
     */
    private function audioFile(string $name, string $type): UploadedFile
    {
        $content = match ($type) {
            // En-tête ID3 puis une trame MPEG.
            'mp3' => "ID3\x03\x00\x00\x00\x00\x00\x00".str_repeat("\xFF\xFB\x90\x00", 200),
            'ogg' => "OggS\x00\x02".str_repeat("\x00", 20).'vorbis'.str_repeat("\x00", 400),
            'wav' => 'RIFF'.pack('V', 2048).'WAVEfmt '.pack('V', 16).pack('vvVVvv', 1, 1, 8000, 8000, 1, 8).'data'.pack('V', 2000).str_repeat("\x00", 2000),
        };

        return UploadedFile::fake()->createWithContent($name, $content);
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function acceptedFiles(): array
    {
        return [
            'png' => ['image', 'schema.png', 'image/png', 'png'],
            'jpeg' => ['image', 'photo.jpg', 'image/jpeg', 'jpg'],
            'webp' => ['image', 'vue.webp', 'image/webp', 'webp'],
            'mp3' => ['mp3', 'explication.mp3', 'audio/mpeg', 'mp3'],
            'wav' => ['wav', 'voix.wav', 'audio/x-wav', 'wav'],
        ];
    }

    #[DataProvider('acceptedFiles')]
    public function test_accepted_media(string $kind, string $name, string $mime, string $extension): void
    {
        $file = $kind === 'image'
            ? UploadedFile::fake()->image($name, 40, 30)
            : $this->audioFile($name, $kind);

        $response = $this->upload($file)->assertCreated();

        $medium = $this->lesson->media()->firstOrFail();
        $this->assertSame($mime, $medium->mime);
        $this->assertStringEndsWith('.'.$extension, $medium->path);
        $this->assertStringStartsWith($this->lesson->mediaDirectory().'/', $medium->path);
        $this->assertSame($kind === 'image' ? MediaKind::Image : MediaKind::Audio, $medium->kind);
        $response->assertJsonPath('data.kind', $medium->kind->value)
            ->assertJsonPath('data.url', url("/api/lessons/{$this->lesson->id}/media/{$medium->id}"));

        Storage::disk('local')->assertExists($medium->path);
        $this->get($response->json('data.url'))->assertOk()->assertHeader('Content-Type', $mime);
    }

    public function test_disguised_files_are_refused(): void
    {
        $refused = [
            'SVG' => UploadedFile::fake()->createWithContent('dessin.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            'GIF' => UploadedFile::fake()->image('anim.gif'),
            'HTML en .png' => UploadedFile::fake()->createWithContent('innocent.png', '<!DOCTYPE html><script>alert(1)</script>'),
            'HTML en .mp3' => UploadedFile::fake()->createWithContent('piste.mp3', '<!DOCTYPE html><script>alert(1)</script>'),
            'PHP en .png' => UploadedFile::fake()->createWithContent('shell.png', '<?php system($_GET["c"]); ?>'),
            'PHP en .mp3' => UploadedFile::fake()->createWithContent('shell.mp3', '<?php system($_GET["c"]); ?>'),
            'ZIP en .png' => UploadedFile::fake()->createWithContent('archive.png', "PK\x03\x04".str_repeat("\x00", 100)),
        ];

        foreach ($refused as $label => $file) {
            $response = $this->upload($file);

            $this->assertSame(422, $response->status(), $label);
            $this->assertArrayHasKey('file', $response->json('errors'), $label);
        }

        $this->assertDatabaseCount('lesson_media', 0);
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_extension_and_kind_come_from_the_content(): void
    {
        // Une image nommée .mp3 reste une image…
        $png = UploadedFile::fake()->image('source.png');
        $this->upload(UploadedFile::fake()->createWithContent('musique.mp3', file_get_contents($png->getRealPath())))
            ->assertCreated();

        $medium = $this->lesson->media()->firstOrFail();
        $this->assertSame(MediaKind::Image, $medium->kind);
        $this->assertSame('image/png', $medium->mime);
        $this->assertStringEndsWith('.png', $medium->path);
        // Le nom d'affichage est nettoyé et prend l'extension réelle.
        $this->assertSame('musique.png', $medium->name);

        // … et un audio nommé .png reste un audio. Le nom ne décide de rien.
        $mp3 = $this->audioFile('son.mp3', 'mp3');
        $id = $this->upload(UploadedFile::fake()->createWithContent('image.png', file_get_contents($mp3->getRealPath())))
            ->assertCreated()
            ->assertJsonPath('data.kind', 'audio')
            ->json('data.id');

        $audio = $this->lesson->media()->findOrFail($id);
        $this->assertSame('audio/mpeg', $audio->mime);
        $this->assertStringEndsWith('.mp3', $audio->path);
        $this->assertSame('image.mp3', $audio->name);
    }

    public function test_sizes_are_bounded_by_kind(): void
    {
        config(['lessons.max_image_kb' => 10, 'lessons.max_audio_kb' => 20]);

        $this->upload(UploadedFile::fake()->image('grande.png')->size(11))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);

        // L'audio profite de sa propre limite, plus large.
        $this->upload($this->audioFile('ok.mp3', 'mp3'))->assertCreated();
    }

    public function test_media_count_per_lesson_is_bounded(): void
    {
        config(['lessons.max_media_per_lesson' => 1]);

        $this->upload(UploadedFile::fake()->image('a.png'))->assertCreated();
        $this->upload(UploadedFile::fake()->image('b.png'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', ManageLessonMedia::QUOTA_MESSAGE);

        $this->assertDatabaseCount('lesson_media', 1);
    }

    public function test_removing_a_medium(): void
    {
        $id = $this->upload(UploadedFile::fake()->image('a.png'))->assertCreated()->json('data.id');
        $path = $this->lesson->media()->firstOrFail()->path;

        $this->deleteJson("/api/lessons/{$this->lesson->id}/media/{$id}")->assertNoContent();

        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseCount('lesson_media', 0);
        $this->getJson("/api/lessons/{$this->lesson->id}/media/{$id}")->assertNotFound();
    }

    public function test_audio_supports_range_requests(): void
    {
        $id = $this->upload($this->audioFile('voix.wav', 'wav'))->assertCreated()->json('data.id');
        $url = "/api/lessons/{$this->lesson->id}/media/{$id}";

        $full = $this->get($url)->assertOk();
        $this->assertSame('bytes', $full->headers->get('Accept-Ranges'));

        $partial = $this->get($url, ['Range' => 'bytes=0-99']);
        $partial->assertStatus(206);
        $this->assertStringStartsWith('bytes 0-99/', $partial->headers->get('Content-Range'));
    }

    public function test_missing_or_non_file_input_is_refused(): void
    {
        $this->postJson("/api/lessons/{$this->lesson->id}/media", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);

        $this->postJson("/api/lessons/{$this->lesson->id}/media", ['file' => 'data:image/png;base64,AAAA'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }
}
