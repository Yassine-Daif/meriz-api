<?php

namespace App\Filament\Resources\Comments;

use App\Actions\Comments\ResolveComment;
use App\Filament\Resources\AdminResource;
use App\Filament\Resources\Comments\Pages\ManageComments;
use App\Models\Comment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Les commentaires de correction. Le texte se corrige, par exemple pour
 * retirer un propos déplacé ; classer ou rouvrir passe par ResolveComment,
 * la même action que l'application.
 */
class CommentResource extends AdminResource
{
    protected static ?string $model = Comment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $modelLabel = 'commentaire';

    protected static ?string $pluralModelLabel = 'commentaires';

    protected static ?string $navigationLabel = 'Commentaires';

    protected static string|\UnitEnum|null $navigationGroup = 'Travaux';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('body')
                ->label('Texte')
                ->rows(5)
                ->required()
                ->maxLength(config('comments.max_body_chars')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('body')
                    ->label('Texte')
                    ->searchable()
                    ->wrap()
                    ->limit(80),
                TextColumn::make('author.name')
                    ->label('Auteur')
                    ->formatStateUsing(fn (Comment $record) => $record->author?->displayName() ?? '')
                    ->searchable(),
                TextColumn::make('document.name')
                    ->label('Travail')
                    ->searchable(),
                IconColumn::make('position_x')
                    ->label('Posé')
                    ->boolean()
                    ->tooltip('Un commentaire posé est rattaché à un point du schéma.'),
                IconColumn::make('resolved_at')
                    ->label('Résolu')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Écrit le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('resolved')
                    ->label('Résolus')
                    ->query(fn (Builder $query) => $query->whereNotNull('resolved_at')),
                Filter::make('open')
                    ->label('Ouverts')
                    ->query(fn (Builder $query) => $query->whereNull('resolved_at')),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('resolve')
                    ->label('Marquer résolu')
                    ->visible(fn (Comment $record) => ! $record->isResolved())
                    ->action(fn (Comment $record, ResolveComment $resolution) => $resolution->resolve(
                        $record,
                        auth()->user(),
                    )),
                Action::make('reopen')
                    ->label('Rouvrir')
                    ->visible(fn (Comment $record) => $record->isResolved())
                    ->action(fn (Comment $record, ResolveComment $resolution) => $resolution->reopen($record)),
                DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['author', 'document']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageComments::route('/'),
        ];
    }
}
