<?php

namespace App\Filament\Resources\Users;

use App\Actions\Account\DeleteAccount;
use App\Enums\UserRole;
use App\Filament\Resources\AdminResource;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Les comptes.
 *
 * Deux choses à ne jamais faire ici, et le code les empêche :
 *
 * 1. Donner le droit d'administrer. is_admin est affiché mais désactivé :
 *    il ne se pose qu'en ligne de commande, par meriz:admin, pour qu'un
 *    admin ne puisse pas en fabriquer un autre d'un clic.
 * 2. Supprimer en base. La suppression ordinaire emporterait les rendus
 *    notés et les commentaires en cascade. L'action appelle DeleteAccount,
 *    la règle déjà en place, qui anonymise et garde ce que d'autres
 *    utilisent encore.
 */
class UserResource extends AdminResource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'compte';

    protected static ?string $pluralModelLabel = 'comptes';

    protected static ?string $navigationLabel = 'Comptes';

    protected static string|\UnitEnum|null $navigationGroup = 'Communauté';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'email';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('first_name')
                ->label('Prénom')
                ->maxLength(100),
            TextInput::make('name')
                ->label('Nom')
                ->required()
                ->maxLength(100),
            TextInput::make('email')
                ->label('Adresse email')
                ->disabled()
                ->helperText("L'adresse ne se change pas depuis l'administration : elle sert à se connecter et elle est vérifiée."),
            TextInput::make('role')
                ->label('Rôle')
                ->disabled()
                ->formatStateUsing(fn (?UserRole $state) => $state === UserRole::Teacher ? 'Prof' : 'Élève')
                ->helperText('Le mode prof s\'active par l\'application, sur une adresse scolaire vérifiée.'),
            Toggle::make('is_academic')
                ->label('Adresse scolaire reconnue'),
            Toggle::make('is_admin')
                ->label('Administrateur')
                ->disabled()
                ->helperText('Se donne et se retire par php artisan meriz:admin.'),
            Textarea::make('bio')
                ->label('Présentation')
                ->rows(3)
                ->maxLength(280),
            Toggle::make('bio_shared')->label('Présentation partagée'),
            TextInput::make('contact')
                ->label('Contact')
                ->maxLength(255),
            Toggle::make('contact_shared')->label('Contact partagé'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->formatStateUsing(fn (User $record) => $record->displayName())
                    ->searchable(['name', 'first_name'])
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('role')
                    ->label('Rôle')
                    ->badge()
                    ->formatStateUsing(fn (UserRole $state) => $state === UserRole::Teacher ? 'Prof' : 'Élève')
                    ->color(fn (UserRole $state) => $state === UserRole::Teacher ? 'success' : 'gray'),
                IconColumn::make('is_academic')
                    ->label('Scolaire')
                    ->boolean(),
                IconColumn::make('email_verified_at')
                    ->label('Vérifié')
                    ->boolean(),
                IconColumn::make('is_admin')
                    ->label('Admin')
                    ->boolean(),
                TextColumn::make('anonymized_at')
                    ->label('Supprimé le')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Inscrit le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('role')
                    ->label('Rôle')
                    ->options(['student' => 'Élève', 'teacher' => 'Prof']),
                TernaryFilter::make('is_admin')->label('Administrateur'),
                TernaryFilter::make('is_academic')->label('Adresse scolaire'),
                Filter::make('anonymized')
                    ->label('Comptes supprimés')
                    ->query(fn (Builder $query) => $query->whereNotNull('anonymized_at')),
                Filter::make('unverified')
                    ->label('Email non vérifié')
                    ->query(fn (Builder $query) => $query->whereNull('email_verified_at')),
            ])
            ->recordActions([
                EditAction::make()
                    ->hidden(fn (User $record) => $record->isAnonymized()),
                self::deleteAccountAction(),
            ]);
    }

    /**
     * Supprimer un compte, avec la règle du serveur, pas celle de Filament.
     */
    private static function deleteAccountAction(): Action
    {
        return Action::make('deleteAccount')
            ->label('Supprimer le compte')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Supprimer ce compte définitivement')
            ->modalDescription(
                "L'identité, les documents personnels et les médias sont effacés. "
                .'Un rendu déjà noté et les commentaires restent, anonymisés, sous « Compte supprimé ». '
                .'Cette action est irréversible.'
            )
            ->modalSubmitActionLabel('Supprimer définitivement')
            ->hidden(fn (User $record) => $record->isAnonymized())
            ->action(function (User $record, DeleteAccount $delete) {
                $delete->handle($record);

                Notification::make()
                    ->title('Compte supprimé')
                    ->body('Les données identifiantes sont effacées. Ce qui subsiste est anonymisé.')
                    ->success()
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
