<?php

namespace App\Filament\Resources\Documents;

use App\Filament\Resources\AdminResource;
use App\Filament\Resources\Documents\Pages\ManageDocuments;
use App\Models\Document;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Les documents : schémas personnels, travaux rattachés à un devoir, et
 * documents partagés d'un groupe.
 *
 * Seul le nom se corrige. Le contenu est le travail de quelqu'un : le
 * panneau n'en montre que la taille, jamais le schéma lui-même.
 */
class DocumentResource extends AdminResource
{
    protected static ?string $model = Document::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $modelLabel = 'document';

    protected static ?string $pluralModelLabel = 'documents';

    protected static ?string $navigationLabel = 'Documents';

    protected static string|\UnitEnum|null $navigationGroup = 'Travaux';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nom')
                ->required()
                ->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Propriétaire')
                    ->formatStateUsing(fn (Document $record) => $record->user?->displayName() ?? '')
                    ->searchable(),
                TextColumn::make('kind')
                    ->label('Nature')
                    ->badge()
                    ->state(fn (Document $record) => match (true) {
                        $record->group_id !== null => 'Groupe',
                        $record->assignment_id !== null => 'Devoir',
                        default => 'Personnel',
                    }),
                TextColumn::make('assignment.title')
                    ->label('Devoir')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('group.name')
                    ->label('Groupe')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('content')
                    ->label('Taille')
                    ->formatStateUsing(fn (?string $state) => $state === null ? '—' : number_format(strlen($state) / 1024, 1, ',', ' ').' Ko')
                    ->tooltip('Le schéma ne s\'affiche pas ici.'),
                TextColumn::make('comments_count')
                    ->label('Commentaires')
                    ->counts('comments'),
                TextColumn::make('updated_at')
                    ->label('Modifié le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Filter::make('personal')
                    ->label('Personnels')
                    ->query(fn (Builder $query) => $query->whereNull('group_id')->whereNull('assignment_id')),
                Filter::make('work')
                    ->label('Travaux de devoir')
                    ->query(fn (Builder $query) => $query->whereNotNull('assignment_id')),
                Filter::make('shared')
                    ->label('Documents de groupe')
                    ->query(fn (Builder $query) => $query->whereNotNull('group_id')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'assignment', 'group']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDocuments::route('/'),
        ];
    }
}
