<?php

namespace App\Filament\Widgets;

use App\Enums\AssignmentStatus;
use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Assignments\AssignmentResource;
use App\Filament\Resources\Classrooms\ClassroomResource;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Lessons\LessonResource;
use App\Filament\Resources\Submissions\SubmissionResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Comment;
use App\Models\Document;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\Submission;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Les chiffres de la plateforme : ce que contient le logiciel.
 *
 * Pas d'audience ici — visites, sources, taux de clics relèvent d'un outil
 * d'analytics branché au déploiement, pas des données métier.
 *
 * Un count() par chiffre, aucune boucle sur des modèles.
 */
class MetierStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected int|array|null $columns = ['md' => 2, 'xl' => 4];

    protected ?string $heading = 'La plateforme en chiffres';

    protected function getStats(): array
    {
        $comptes = User::whereNull('anonymized_at')->count();
        $eleves = User::whereNull('anonymized_at')->where('role', UserRole::Student)->count();
        $profs = User::whereNull('anonymized_at')->where('role', UserRole::Teacher)->count();
        $supprimes = User::whereNotNull('anonymized_at')->count();

        $devoirs = Assignment::count();
        $publies = Assignment::where('status', AssignmentStatus::Published)->count();

        $rendus = Submission::count();
        $aCorriger = Submission::where('status', SubmissionStatus::Submitted)->count();

        return [
            Stat::make('Comptes actifs', $comptes)
                ->description($supprimes === 0 ? 'Aucun compte supprimé' : $supprimes.' compte(s) supprimé(s)')
                ->icon(Heroicon::OutlinedUsers)
                ->color('primary')
                ->url(UserResource::getUrl()),

            Stat::make('Élèves', $eleves)
                ->description('Comptes apprenants')
                ->icon(Heroicon::OutlinedAcademicCap)
                ->url(UserResource::getUrl()),

            Stat::make('Professeurs', $profs)
                ->description('Comptes enseignants')
                ->icon(Heroicon::OutlinedAcademicCap)
                ->url(UserResource::getUrl()),

            Stat::make('Classes', Classroom::count())
                ->description(Group::count().' groupe(s) d\'élèves')
                ->icon(Heroicon::OutlinedRectangleStack)
                ->url(ClassroomResource::getUrl()),

            Stat::make('Devoirs', $devoirs)
                ->description($publies.' publié(s)')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->url(AssignmentResource::getUrl()),

            Stat::make('Rendus', $rendus)
                ->description($aCorriger.' en attente de correction')
                ->icon(Heroicon::OutlinedInboxArrowDown)
                ->color($aCorriger > 0 ? 'warning' : 'success')
                ->url(SubmissionResource::getUrl()),

            Stat::make('Cours', Lesson::count())
                ->icon(Heroicon::OutlinedBookOpen)
                ->url(LessonResource::getUrl()),

            Stat::make('Documents', Document::count())
                ->description(Comment::count().' commentaire(s)')
                ->icon(Heroicon::OutlinedDocumentText)
                ->url(DocumentResource::getUrl()),
        ];
    }
}
