<?php

namespace App\Services;

use App\Actions\Assignments\CreateAssignment;
use App\Actions\Lessons\CreateLesson;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\LessonMedium;
use App\Models\TeachingResource;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use Throwable;

class TeachingLibrary
{
    public function __construct(private readonly CreateAssignment $assignments, private readonly CreateLesson $lessons) {}

    public function capture(User $teacher, Assignment|Lesson $source, ?TeachingResource $existing = null): TeachingResource
    {
        abort_unless($source->classroom->teacher_id === $teacher->id, 404);
        abort_if($existing && $existing->user_id !== $teacher->id, 404);
        $kind = $source instanceof Assignment ? 'assignment' : 'lesson';
        if ($source instanceof Lesson) {
            try {
                $blocks = json_decode($source->blocks, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw ValidationException::withMessages(['source_id' => 'Le contenu du cours doit être une liste de blocs.']);
            }
            if (! is_array($blocks) || ! array_is_list($blocks)) {
                throw ValidationException::withMessages(['source_id' => 'Le contenu du cours doit être une liste de blocs.']);
            }
        }
        if ($existing && $existing->kind !== $kind) {
            throw ValidationException::withMessages(['source_id' => 'La catégorie de la ressource ne peut pas changer.']);
        }
        $directory = null;
        $previousDirectory = null;
        try {
            $resource = DB::transaction(function () use ($teacher, $source, $existing, $kind, &$directory, &$previousDirectory) {
                User::whereKey($teacher->id)->lockForUpdate()->firstOrFail();
                $resource = $existing ? TeachingResource::whereKey($existing->id)->lockForUpdate()->firstOrFail() : new TeachingResource;
                if (! $resource->exists && TeachingResource::visibleTo($teacher)->count() >= 200) {
                    throw ValidationException::withMessages(['source_id' => 'Nombre maximal de ressources atteint.']);
                }
                $resource->forceFill(['id' => $resource->id ?? (string) Str::ulid(), 'user_id' => $teacher->id, 'kind' => $kind]);
                $version = $resource->exists ? $resource->version + 1 : 1;
                $previousDirectory = $resource->exists ? 'teaching-library/'.$resource->id.'/'.$resource->version : null;
                $directory = 'teaching-library/'.$resource->id.'/'.$version;
                $payload = $source instanceof Assignment
                    ? $source->only(['instructions', 'type', 'due_at', 'base_content', 'solution_content', 'interface_settings'])
                    : ['blocks' => $source->blocks, 'media' => []];
                if (isset($payload['type'])) {
                    $payload['type'] = $source->type->value;
                }
                $bytes = strlen(json_encode($payload, JSON_THROW_ON_ERROR));
                $initialPayloadBytes = $bytes;
                $used = (int) TeachingResource::visibleTo($teacher)->where('id', '!=', $resource->id)->sum('storage_bytes');
                $this->checkBudget($used + $bytes);
                if ($source instanceof Assignment && $source->image_path) {
                    $target = $directory.'/image';
                    $this->checkCopyBudget($used + $bytes, config('assignments.image_disk'), $source->image_path);
                    $this->copy(config('assignments.image_disk'), $source->image_path, 'local', $target);
                    $bytes += Storage::disk('local')->size($target);
                    $payload['image'] = ['path' => $target, 'mime' => $source->image_mime, 'size' => $source->image_size];
                }
                if ($source instanceof Lesson) {
                    foreach ($source->media as $medium) {
                        $target = $directory.'/'.$medium->id;
                        $this->checkCopyBudget($used + $bytes, config('lessons.disk'), $medium->path);
                        $this->copy(config('lessons.disk'), $medium->path, 'local', $target);
                        $bytes += Storage::disk('local')->size($target);
                        $payload['media'][] = ['id' => $medium->id, 'path' => $target, 'kind' => $medium->kind->value, 'mime' => $medium->mime, 'size' => $medium->size, 'name' => $medium->name];
                    }
                }
                $bytes += strlen(json_encode($payload, JSON_THROW_ON_ERROR)) - $initialPayloadBytes;
                $this->checkBudget($used + $bytes);
                $resource->forceFill(['source_id' => $source->id, 'title' => $source->title, 'version' => $version, 'payload' => $payload, 'storage_bytes' => $bytes])->save();
                // La classe source possède déjà le contenu : ne pas le dupliquer.
                DB::table('teaching_publications')->updateOrInsert(['resource_id' => $resource->id, 'classroom_id' => $source->classroom_id], [
                    'assignment_id' => $kind === 'assignment' ? $source->id : null,
                    'lesson_id' => $kind === 'lesson' ? $source->id : null,
                    'version' => $version, 'created_at' => now(), 'updated_at' => now(),
                ]);

                return $resource;
            });
        } catch (Throwable $exception) {
            if ($directory) {
                Storage::disk('local')->deleteDirectory($directory);
            }
            throw $exception;
        }
        if ($previousDirectory) {
            Storage::disk('local')->deleteDirectory($previousDirectory);
        }

        return $resource;
    }

    /** Copies indépendantes ; une nouvelle tentative ne crée pas de doublon. */
    public function distribute(TeachingResource $resource, array $classroomIds, bool $updateExisting, bool $publish, bool $applySettings, ?string $dueAt): array
    {
        $written = [];
        $obsolete = [];
        try {
            $result = DB::transaction(function () use ($resource, $classroomIds, $updateExisting, $publish, $applySettings, $dueAt, &$written, &$obsolete) {
                $resource = TeachingResource::whereKey($resource->id)->lockForUpdate()->firstOrFail();
                $result = [];
                foreach (array_unique($classroomIds) as $id) {
                    $classroom = Classroom::where('teacher_id', $resource->user_id)->whereKey($id)->firstOrFail();
                    $publication = DB::table('teaching_publications')->where('resource_id', $resource->id)->where('classroom_id', $id)->first();
                    $target = $publication ? ($resource->kind === 'assignment' ? Assignment::find($publication->assignment_id) : Lesson::find($publication->lesson_id)) : null;
                    if ($target && (! $updateExisting || $publication->version === $resource->version)) {
                        $changed = false;
                        // Un choix ultérieur de restrictions ne doit pas être ignoré
                        // lorsque le contenu de cette version est déjà diffusé.
                        if ($updateExisting && $applySettings && $target instanceof Assignment) {
                            $settings = $resource->payload['interface_settings'] ?? null;
                            $changed = $target->interface_settings !== $settings;
                            if ($changed) {
                                $target->fill(['interface_settings' => $settings])->save();
                            }
                        }
                        $result[] = ['classroom_id' => $id, 'id' => $target->id, 'updated' => $changed];

                        continue;
                    }
                    $payload = $resource->payload;
                    $new = $target === null;
                    if ($resource->kind === 'assignment') {
                        $fields = array_intersect_key($payload, array_flip(['instructions', 'type', 'base_content', 'solution_content']));
                        $fields['title'] = $resource->title;
                        if ($new || $applySettings) {
                            $fields['interface_settings'] = $payload['interface_settings'] ?? null;
                        }
                        if ($new) {
                            $fields['due_at'] = $dueAt;
                            $target = $this->assignments->handle($classroom, $fields);
                        } else {
                            if ($target->solution_content !== ($fields['solution_content'] ?? null)) {
                                // Tout nouveau corrigé reste retenu jusqu'à une décision explicite.
                                $target->forceFill(['solution_released_at' => null]);
                            }
                            $target->fill($fields);
                        }
                        if ($target->image_path) {
                            $obsolete[] = [config('assignments.image_disk'), $target->image_path];
                        }
                        $image = $payload['image'] ?? null;
                        $imagePath = $image ? $target->imageDirectory().'/'.Str::random(40) : null;
                        if ($image) {
                            $this->copy('local', $image['path'], config('assignments.image_disk'), $imagePath);
                            $written[] = [config('assignments.image_disk'), $imagePath];
                        }
                        $target->forceFill(['image_path' => $imagePath, 'image_mime' => $image['mime'] ?? null, 'image_size' => $image['size'] ?? null])->save();
                    } else {
                        $target ??= $this->lessons->handle($classroom, ['title' => $resource->title, 'blocks' => '[]']);
                        $map = [];
                        foreach ($target->media as $medium) {
                            $obsolete[] = [config('lessons.disk'), $medium->path];
                            $medium->delete();
                        }
                        foreach ($payload['media'] as $medium) {
                            $mediaPath = $target->mediaDirectory().'/'.Str::random(40);
                            $this->copy('local', $medium['path'], config('lessons.disk'), $mediaPath);
                            $written[] = [config('lessons.disk'), $mediaPath];
                            $copy = new LessonMedium;
                            $copy->forceFill(['lesson_id' => $target->id, 'path' => $mediaPath, ...array_intersect_key($medium, array_flip(['kind', 'mime', 'size', 'name']))])->save();
                            $map[$medium['id']] = $copy->id;
                        }
                        $blocks = json_decode($payload['blocks'], true, 512, JSON_THROW_ON_ERROR);
                        foreach ($blocks as &$block) {
                            if (is_array($block) && isset($block['media_id'])) {
                                $block['media_id'] = $map[$block['media_id']] ?? '';
                            }
                        }
                        unset($block);
                        $target->fill(['title' => $resource->title, 'blocks' => json_encode($blocks, JSON_THROW_ON_ERROR)])->save();
                    }
                    // Le choix de publication ne concerne que les nouvelles copies.
                    if ($new && $publish) {
                        $target->forceFill(['status' => 'published', 'published_at' => now()])->save();
                    }
                    DB::table('teaching_publications')->updateOrInsert(['resource_id' => $resource->id, 'classroom_id' => $id], [
                        'assignment_id' => $resource->kind === 'assignment' ? $target->id : null,
                        'lesson_id' => $resource->kind === 'lesson' ? $target->id : null,
                        'version' => $resource->version, 'created_at' => $publication?->created_at ?? now(), 'updated_at' => now(),
                    ]);
                    $result[] = ['classroom_id' => $id, 'id' => $target->id, 'updated' => true];
                }

                return $result;
            });
        } catch (Throwable $exception) {
            foreach ($written as [$disk, $file]) {
                Storage::disk($disk)->delete($file);
            }
            throw $exception;
        }
        foreach ($obsolete as [$disk, $file]) {
            Storage::disk($disk)->delete($file);
        }

        return $result;
    }

    private function copy(string $sourceDisk, string $source, string $targetDisk, string $target): void
    {
        $stream = Storage::disk($sourceDisk)->readStream($source);
        if (! is_resource($stream)) {
            throw ValidationException::withMessages(['source_id' => 'Un média de la ressource est introuvable.']);
        }
        try {
            if (! Storage::disk($targetDisk)->put($target, $stream)) {
                throw ValidationException::withMessages(['source_id' => 'La copie d’un média a échoué.']);
            }
        } finally {
            fclose($stream);
        }
    }

    private function checkCopyBudget(int $used, string $disk, string $path): void
    {
        if (! Storage::disk($disk)->exists($path)) {
            throw ValidationException::withMessages(['source_id' => 'Un média de la ressource est introuvable.']);
        }
        $this->checkBudget($used + Storage::disk($disk)->size($path));
    }

    private function checkBudget(int $bytes): void
    {
        if ($bytes > 200 * 1024 * 1024) {
            throw ValidationException::withMessages(['source_id' => 'Espace de stockage de la bibliothèque atteint.']);
        }
    }
}
