<?php

namespace App\Actions\Assignments;

use App\Actions\Documents\CreateDocument;
use App\Models\Assignment;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Commencer un devoir : donne à l'élève son document de travail, rattaché au
 * devoir.
 *
 * Avec une base, on la copie. Sans base, on crée un document vide, mais
 * déjà rattaché : c'est ce rattachement qui rend l'élève visible dans le
 * suivi du prof et observable dès son premier enregistrement.
 *
 * Le rattachement est toujours écrit par le serveur, jamais par le client.
 * Un seul document de travail par élève et par devoir : recommencer renvoie
 * le même.
 */
class StartAssignmentWork
{
    /** Contenu neutre d'une page blanche, remplacé au premier enregistrement. */
    public const EMPTY_CONTENT = '{}';

    public function __construct(private readonly CreateDocument $createDocument) {}

    /**
     * @return array{document: Document, created: bool}
     *
     * @throws ValidationException si le quota de documents est atteint
     */
    public function handle(User $user, Assignment $assignment): array
    {
        return DB::transaction(function () use ($user, $assignment) {
            $existing = Document::query()
                ->where('assignment_id', $assignment->id)
                ->where('user_id', $user->id)
                ->orderByDesc('updated_at')
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return ['document' => $existing, 'created' => false];
            }

            // Seule la base est lue. Le corrigé n'est jamais touché ici.
            $document = $this->createDocument->handle($user, [
                'name' => Str::limit($assignment->title, 255, ''),
                'content' => $assignment->hasBase() ? $assignment->base_content : self::EMPTY_CONTENT,
            ], $assignment->id);

            return ['document' => $document, 'created' => true];
        });
    }
}
