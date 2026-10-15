<?php

namespace App\Filament\Resources\Lessons;

use App\Actions\Lessons\ChangeLessonState;
use App\Enums\LessonStatus;
use App\Filament\Resources\AdminResource;
use App\Filament\Resources\Lessons\Pages\ManageLessons;
use App\Models\Lesson;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Les cours. Le titre se corrige ; la page de blocs non, c'est un contenu.
 *
 * Publier passe par ChangeLessonState, la même règle que l'application, et
 * supprimer passe par le modèle, dont le crochet efface les médias.
 */
class LessonResource extends AdminResource
{
    protected static ?string $model = Lesson::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $modelLabel = 'cours';

    protected static ?string $pluralModelLabel = 'cours';

    protected static ?string $navigationLabel = 'Cours';

    protected static string|\UnitEnum|null $navigationGroup = 'Enseignement';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label('Titre')
                ->required()
                ->maxLength(200),
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
                TextColumn::make('status')
                    ->label('État')
                    ->badge()
                    ->formatStateUsing(fn (LessonStatus $state) => $state === LessonStatus::Published ? 'Publié' : 'Brouillon')
                    ->color(fn (LessonStatus $state) => $state === LessonStatus::Published ? 'success' : 'warning'),
                TextColumn::make('media_count')
                    ->label('Médias')
                    ->counts('media'),
                TextColumn::make('published_at')
                    ->label('Publié le')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable(),
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
                SelectFilter::make('classroom')
                    ->label('Classe')
                    ->relationship('classroom', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('publish')
                    ->label('Publier')
                    ->requiresConfirmation()
                    ->visible(fn (Lesson $record) => ! $record->isPublished())
                    ->action(fn (Lesson $record, ChangeLessonState $state) => $state->publish($record)),
                Action::make('unpublish')
                    ->label('Repasser en brouillon')
                    ->requiresConfirmation()
                    ->visible(fn (Lesson $record) => $record->isPublished())
                    ->action(fn (Lesson $record, ChangeLessonState $state) => $state->unpublish($record)),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLessons::route('/'),
        ];
    }
}
