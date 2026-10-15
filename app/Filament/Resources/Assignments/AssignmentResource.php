<?php

namespace App\Filament\Resources\Assignments;

use App\Actions\Assignments\ChangeAssignmentState;
use App\Enums\AssignmentStatus;
use App\Enums\AssignmentType;
use App\Filament\Resources\AdminResource;
use App\Filament\Resources\Assignments\Pages\ManageAssignments;
use App\Models\Assignment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Les devoirs.
 *
 * Le formulaire ne touche qu'au texte. Publier, livrer le corrigé et ouvrir
 * le suivi en direct sont des actions, et elles appellent
 * ChangeAssignmentState : les dates de publication et de libération restent
 * posées par la même règle que dans l'application, jamais à la main.
 *
 * La base et le corrigé ne sont pas affichés : ce sont des contenus de
 * travail, le panneau n'en montre que la taille.
 */
class AssignmentResource extends AdminResource
{
    protected static ?string $model = Assignment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'devoir';

    protected static ?string $pluralModelLabel = 'devoirs';

    protected static ?string $navigationLabel = 'Devoirs';

    protected static string|\UnitEnum|null $navigationGroup = 'Enseignement';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label('Titre')
                ->required()
                ->maxLength(200),
            Textarea::make('instructions')
                ->label('Consigne')
                ->rows(6)
                ->required(),
            Select::make('type')
                ->label('Type')
                ->options(['exercise' => 'Exercice', 'exam' => 'Évaluation'])
                ->required(),
            DateTimePicker::make('due_at')
                ->label('Date limite')
                ->seconds(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Titre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('classroom.name')
                    ->label('Classe')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (AssignmentType $state) => $state === AssignmentType::Exam ? 'Évaluation' : 'Exercice'),
                TextColumn::make('status')
                    ->label('État')
                    ->badge()
                    ->formatStateUsing(fn (AssignmentStatus $state) => $state === AssignmentStatus::Published ? 'Publié' : 'Brouillon')
                    ->color(fn (AssignmentStatus $state) => $state === AssignmentStatus::Published ? 'success' : 'warning'),
                TextColumn::make('due_at')
                    ->label('Échéance')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('submissions_count')
                    ->label('Rendus')
                    ->counts('submissions'),
                IconColumn::make('solution_released_at')
                    ->label('Corrigé livré')
                    ->boolean(),
                IconColumn::make('live_tracking')
                    ->label('Suivi direct')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('État')
                    ->options(['draft' => 'Brouillon', 'published' => 'Publié']),
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(['exercise' => 'Exercice', 'exam' => 'Évaluation']),
                SelectFilter::make('classroom')
                    ->label('Classe')
                    ->relationship('classroom', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    self::stateAction('publish', 'Publier', fn (Assignment $a) => ! $a->isPublished()),
                    self::stateAction('unpublish', 'Repasser en brouillon', fn (Assignment $a) => $a->isPublished()),
                    self::stateAction('releaseSolution', 'Livrer le corrigé', fn (Assignment $a) => ! $a->solutionReleased()),
                    self::stateAction('withholdSolution', 'Retirer le corrigé', fn (Assignment $a) => $a->solutionReleased()),
                    self::stateAction('enableLiveTracking', 'Ouvrir le suivi en direct', fn (Assignment $a) => ! $a->liveTrackingEnabled()),
                    self::stateAction('disableLiveTracking', 'Fermer le suivi en direct', fn (Assignment $a) => $a->liveTrackingEnabled()),
                ])->label('États'),
                DeleteAction::make(),
            ]);
    }

    /**
     * Chaque changement d'état réutilise l'action du serveur, pour que les
     * dates qui vont avec soient posées de la même façon qu'en API.
     *
     * @param  callable(Assignment): bool  $visible
     */
    private static function stateAction(string $method, string $label, callable $visible): Action
    {
        return Action::make($method)
            ->label($label)
            ->requiresConfirmation()
            ->visible(fn (Assignment $record) => $visible($record))
            ->action(fn (Assignment $record, ChangeAssignmentState $state) => $state->{$method}($record));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAssignments::route('/'),
        ];
    }
}
