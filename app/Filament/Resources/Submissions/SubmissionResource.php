<?php

namespace App\Filament\Resources\Submissions;

use App\Actions\Submissions\GradeSubmission;
use App\Enums\SubmissionStatus;
use App\Filament\Resources\AdminResource;
use App\Filament\Resources\Submissions\Pages\ManageSubmissions;
use App\Models\Submission;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Les rendus.
 *
 * Pas de formulaire de modification : noter et retirer une note passent par
 * GradeSubmission, qui pose l'état et la date comme en API. Le travail rendu
 * n'est pas affiché, c'est la copie d'un élève : le panneau n'en montre que
 * la taille.
 */
class SubmissionResource extends AdminResource
{
    protected static ?string $model = Submission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $modelLabel = 'rendu';

    protected static ?string $pluralModelLabel = 'rendus';

    protected static ?string $navigationLabel = 'Rendus';

    protected static string|\UnitEnum|null $navigationGroup = 'Enseignement';

    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student.name')
                    ->label('Élève')
                    ->formatStateUsing(fn (Submission $record) => trim(($record->student?->first_name ?? '').' '.($record->student?->name ?? '')))
                    ->searchable(),
                TextColumn::make('assignment.title')
                    ->label('Devoir')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('assignment.classroom.name')
                    ->label('Classe'),
                TextColumn::make('status')
                    ->label('État')
                    ->badge()
                    ->formatStateUsing(fn (SubmissionStatus $state) => $state === SubmissionStatus::Graded ? 'Noté' : 'À corriger')
                    ->color(fn (SubmissionStatus $state) => $state === SubmissionStatus::Graded ? 'success' : 'warning'),
                TextColumn::make('grade')
                    ->label('Note')
                    ->placeholder('—'),
                TextColumn::make('content')
                    ->label('Travail')
                    ->formatStateUsing(fn (?string $state) => $state === null ? '—' : number_format(strlen($state) / 1024, 1, ',', ' ').' Ko')
                    ->tooltip('Le contenu du travail ne s\'affiche pas ici.'),
                TextColumn::make('submitted_at')
                    ->label('Rendu le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('graded_at')
                    ->label('Noté le')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('État')
                    ->options(['submitted' => 'À corriger', 'graded' => 'Noté']),
                SelectFilter::make('assignment')
                    ->label('Devoir')
                    ->relationship('assignment', 'title'),
            ])
            ->recordActions([
                Action::make('grade')
                    ->label('Noter')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->schema([
                        TextInput::make('grade')
                            ->label('Note')
                            ->required()
                            ->maxLength(config('submissions.max_grade_chars')),
                        Textarea::make('feedback')
                            ->label('Retour')
                            ->rows(4)
                            ->maxLength(config('submissions.max_feedback_chars')),
                    ])
                    ->fillForm(fn (Submission $record) => [
                        'grade' => $record->grade,
                        'feedback' => $record->feedback,
                    ])
                    ->action(fn (Submission $record, array $data, GradeSubmission $grading) => $grading->grade(
                        $record,
                        $data['grade'],
                        $data['feedback'] ?? null,
                    )),
                Action::make('removeGrade')
                    ->label('Retirer la note')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Submission $record) => $record->isGraded())
                    ->action(fn (Submission $record, GradeSubmission $grading) => $grading->remove($record)),
                DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        // Le contenu est sélectionné, mais jamais affiché : la colonne n'en
        // montre que la taille.
        return parent::getEloquentQuery()->with(['student', 'assignment.classroom']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSubmissions::route('/'),
        ];
    }
}
