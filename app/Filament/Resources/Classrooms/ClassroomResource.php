<?php

namespace App\Filament\Resources\Classrooms;

use App\Filament\Resources\AdminResource;
use App\Filament\Resources\Classrooms\Pages\ManageClassrooms;
use App\Models\Classroom;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Les classes. Seul le nom se corrige : le code d'accès est généré par le
 * serveur et le prof vient de la création.
 *
 * La suppression passe par le modèle, donc par le crochet déjà testé de
 * Classroom : les dossiers de médias des devoirs et des cours sont effacés,
 * et la base emporte devoirs, cours, rendus et inscriptions.
 */
class ClassroomResource extends AdminResource
{
    protected static ?string $model = Classroom::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $modelLabel = 'classe';

    protected static ?string $pluralModelLabel = 'classes';

    protected static ?string $navigationLabel = 'Classes';

    protected static string|\UnitEnum|null $navigationGroup = 'Enseignement';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nom')
                ->required()
                ->maxLength(100),
            TextInput::make('join_code')
                ->label("Code d'accès")
                ->disabled()
                ->helperText('Généré par le serveur. Le prof peut le régénérer depuis l\'application.'),
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
                TextColumn::make('teacher.name')
                    ->label('Prof')
                    ->formatStateUsing(fn (Classroom $record) => trim(($record->teacher?->first_name ?? '').' '.($record->teacher?->name ?? '')))
                    ->searchable(),
                TextColumn::make('join_code')
                    ->label('Code')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('members_count')
                    ->label('Élèves')
                    ->counts('members')
                    ->sortable(),
                TextColumn::make('assignments_count')
                    ->label('Devoirs')
                    ->counts('assignments'),
                TextColumn::make('lessons_count')
                    ->label('Cours')
                    ->counts('lessons'),
                TextColumn::make('created_at')
                    ->label('Créée le')
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
            'index' => ManageClassrooms::route('/'),
        ];
    }
}
