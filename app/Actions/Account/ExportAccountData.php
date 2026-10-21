<?php

namespace App\Actions\Account;

use App\Models\Assignment;
use App\Models\Comment;
use App\Models\Document;
use App\Models\Lesson;
use App\Models\LessonMedium;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Rassemble tout ce qui appartient à une personne, pour qu'elle l'emporte.
 *
 * Deux règles tiennent tout le fichier :
 *
 * 1. Rien d'une autre personne. Pas de camarade, pas de nom de prof, pas
 *    d'élève dans les rendus reçus, jamais d'email de connexion. Les
 *    classes où elle est élève sortent avec leur nom seul.
 * 2. Les contenus de documents et de rendus sortent tels qu'ils sont
 *    stockés, sans réencodage : le serveur ne lit pas ce format.
 *
 * Les fichiers envoyés sont décrits, pas inclus : le JSON reste lisible et
 * léger, et chaque média porte son adresse de téléchargement.
 */
class ExportAccountData
{
    public const FORMAT_VERSION = 1;

    /**
     * @return array<string, mixed>
     */
    public function handle(User $user): array
    {
        $taughtIds = $user->taughtClassrooms()->pluck('id');

        return [
            'export' => [
                'generated_at' => Carbon::now()->toIso8601String(),
                'format_version' => self::FORMAT_VERSION,
                'note' => 'Les fichiers envoyés ne sont pas dans ce fichier : chaque média porte son adresse de téléchargement.',
            ],
            'account' => $this->account($user),
            'documents' => $this->documents($user),
            'classrooms' => $this->classrooms($user),
            'groups' => $this->groups($user),
            'submissions' => $this->submissions($user),
            'comments' => $this->comments($user),
            'assignments' => $this->assignments($taughtIds),
            'lessons' => $this->lessons($taughtIds),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function account(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'email' => $user->email,
            'role' => $user->role->value,
            'is_academic' => $user->is_academic,
            'bio' => $user->bio,
            'bio_shared' => $user->bio_shared,
            'contact' => $user->contact,
            'contact_shared' => $user->contact_shared,
            'avatar_bg' => $user->avatarBackground(),
            'avatar_fg' => $user->avatarText(),
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    /**
     * Ses documents, contenu compris : les personnels, et ceux qu'elle a
     * écrits dans l'espace d'un groupe.
     *
     * @return array<int, array<string, mixed>>
     */
    private function documents(User $user): array
    {
        return $user->documents()
            ->orderBy('created_at')
            ->get()
            ->map(fn (Document $document) => [
                'id' => $document->id,
                'name' => $document->name,
                'assignment_id' => $document->assignment_id,
                'group_id' => $document->group_id,
                'content' => $document->content,
                'created_at' => $document->created_at?->toIso8601String(),
                'updated_at' => $document->updated_at?->toIso8601String(),
            ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function classrooms(User $user): array
    {
        return [
            // Élève : le nom de la classe, et depuis quand. Rien sur les
            // autres personnes qui y sont.
            'member_of' => $user->classrooms()->orderBy('name')->get()
                ->map(fn ($classroom) => [
                    'id' => $classroom->id,
                    'name' => $classroom->name,
                    'joined_at' => $classroom->membership->created_at?->toIso8601String(),
                ])->all(),

            // Prof : ses classes. Le nombre de membres, pas leur identité.
            'taught' => $user->taughtClassrooms()->withCount('members')->orderBy('name')->get()
                ->map(fn ($classroom) => [
                    'id' => $classroom->id,
                    'name' => $classroom->name,
                    'join_code' => $classroom->join_code,
                    'members_count' => $classroom->members_count,
                    'created_at' => $classroom->created_at?->toIso8601String(),
                ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function groups(User $user): array
    {
        return [
            'created' => $user->createdGroups()->withCount('members')->orderBy('name')->get()
                ->map(fn ($group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'join_code' => $group->join_code,
                    'members_count' => $group->members_count,
                    'created_at' => $group->created_at?->toIso8601String(),
                ])->all(),

            'member_of' => $user->groups()->orderBy('name')->get()
                ->map(fn ($group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'joined_at' => $group->membership->created_at?->toIso8601String(),
                ])->all(),
        ];
    }

    /**
     * Ses rendus, avec le travail rendu, la note et le retour reçus.
     *
     * @return array<int, array<string, mixed>>
     */
    private function submissions(User $user): array
    {
        return $user->submissions()
            ->with('assignment.classroom')
            ->orderBy('submitted_at')
            ->get()
            ->map(fn ($submission) => [
                'id' => $submission->id,
                'assignment_id' => $submission->assignment_id,
                'assignment_title' => $submission->assignment?->title,
                'classroom_name' => $submission->assignment?->classroom?->name,
                'status' => $submission->status->value,
                'grade' => $submission->grade,
                'feedback' => $submission->feedback,
                'content' => $submission->content,
                'submitted_at' => $submission->submitted_at?->toIso8601String(),
                'graded_at' => $submission->graded_at?->toIso8601String(),
            ])->all();
    }

    /**
     * Les commentaires qu'elle a écrits, pas ceux des autres.
     *
     * @return array<int, array<string, mixed>>
     */
    private function comments(User $user): array
    {
        return Comment::where('user_id', $user->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn (Comment $comment) => [
                'id' => $comment->id,
                'document_id' => $comment->document_id,
                'body' => $comment->body,
                'parent_id' => $comment->parent_id,
                'drawing' => $comment->drawing,
                'position' => $comment->hasPosition()
                    ? ['x' => $comment->position_x, 'y' => $comment->position_y]
                    : null,
                'resolved' => $comment->isResolved(),
                'resolved_at' => $comment->resolved_at?->toIso8601String(),
                'created_at' => $comment->created_at?->toIso8601String(),
            ])->all();
    }

    /**
     * Prof : ses devoirs, énoncé, base et corrigé compris. L'image est
     * décrite avec son adresse.
     *
     * @param  Collection<int, string>  $classroomIds
     * @return array<int, array<string, mixed>>
     */
    private function assignments(Collection $classroomIds): array
    {
        if ($classroomIds->isEmpty()) {
            return [];
        }

        return Assignment::whereIn('classroom_id', $classroomIds)
            ->orderBy('created_at')
            ->get()
            ->map(fn (Assignment $assignment) => [
                'id' => $assignment->id,
                'classroom_id' => $assignment->classroom_id,
                'title' => $assignment->title,
                'instructions' => $assignment->instructions,
                'type' => $assignment->type->value,
                'status' => $assignment->status->value,
                'due_at' => $assignment->due_at?->toIso8601String(),
                'base_content' => $assignment->base_content,
                'solution_content' => $assignment->solution_content,
                'published_at' => $assignment->published_at?->toIso8601String(),
                'solution_released_at' => $assignment->solution_released_at?->toIso8601String(),
                'image' => $assignment->image_path === null ? null : [
                    'mime' => $assignment->image_mime,
                    'bytes' => $assignment->image_size,
                    'url' => '/api/assignments/'.$assignment->id.'/image',
                ],
                'created_at' => $assignment->created_at?->toIso8601String(),
            ])->all();
    }

    /**
     * Prof : ses cours, page de blocs comprise, et ses médias décrits.
     *
     * @param  Collection<int, string>  $classroomIds
     * @return array<int, array<string, mixed>>
     */
    private function lessons(Collection $classroomIds): array
    {
        if ($classroomIds->isEmpty()) {
            return [];
        }

        return Lesson::whereIn('classroom_id', $classroomIds)
            ->with('media')
            ->orderBy('created_at')
            ->get()
            ->map(fn (Lesson $lesson) => [
                'id' => $lesson->id,
                'classroom_id' => $lesson->classroom_id,
                'title' => $lesson->title,
                'status' => $lesson->status->value,
                'blocks' => $lesson->blocks,
                'published_at' => $lesson->published_at?->toIso8601String(),
                'media' => $lesson->media->map(fn (LessonMedium $medium) => [
                    'id' => $medium->id,
                    'kind' => $medium->kind->value,
                    'name' => $medium->name,
                    'mime' => $medium->mime,
                    'bytes' => $medium->size,
                    'url' => '/api/lessons/'.$lesson->id.'/media/'.$medium->id,
                ])->all(),
                'created_at' => $lesson->created_at?->toIso8601String(),
            ])->all();
    }
}
