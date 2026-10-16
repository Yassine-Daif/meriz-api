<?php

namespace App\Filament\Resources\Groups;

use App\Filament\Resources\AdminResource;
use App\Filament\Resources\Groups\Pages\ManageGroups;
use App\Models\Group;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Les groupes d'élèves. Le nom se corrige ; le code est généré par le
 * serveur et le créateur vient de la création.
 *
 * Supprimer un groupe emporte ses membres et ses documents partagés, par
 * les cascades de la base, comme quand son créateur le supprime.
 */
class GroupResource extends AdminResource
{
    protected static ?string $model = Group::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $modelLabel = 'groupe';

    protected static ?string $pluralModelLabel = 'groupes';

    protected static ?string $navigationLabel = 'Groupes';

    protected static string|\UnitEnum|null $navigationGroup = 'Communauté';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nom')
                ->required()
                ->maxLength(100),
            TextInput::make('join_code')
                ->label("Code d'accès")
                ->disabled(),
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
                TextColumn::make('creator.name')
                    ->label('Créateur')
                    ->formatStateUsing(fn (Group $record) => $record->creator?->displayName() ?? '')
                    ->searchable(),
                TextColumn::make('join_code')
                    ->label('Code')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('members_count')
                    ->label('Membres')
                    ->counts('members')
                    ->sortable(),
                TextColumn::make('documents_count')
                    ->label('Documents')
                    ->counts('documents'),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageGroups::route('/'),
        ];
    }
}
