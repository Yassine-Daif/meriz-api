<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Notifications\ResetPasswordLink;
use App\Notifications\VerifyEmailAddress;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Fillable ne contient que les champs du profil. email, password, role,
 * is_academic et is_admin ne s'écrivent que par forceFill, dans les actions
 * et les commandes dédiées.
 */
#[Fillable(['name', 'first_name', 'bio', 'bio_shared', 'contact', 'contact_shared', 'avatar_bg', 'avatar_fg'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Nom porté par un compte supprimé par son titulaire. Ce qui subsiste
     * de lui ailleurs, un rendu noté, un commentaire dans un fil, s'affiche
     * sous ce nom.
     */
    public const DELETED_NAME = 'Compte supprimé';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'student',
        'is_academic' => false,
        'is_admin' => false,
        'bio_shared' => false,
        'contact_shared' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'anonymized_at' => 'datetime',
            'is_admin' => 'boolean',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_academic' => 'boolean',
            'bio_shared' => 'boolean',
            'contact_shared' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Group, $this>
     */
    public function createdGroups(): HasMany
    {
        return $this->hasMany(Group::class, 'creator_id');
    }

    /**
     * @return BelongsToMany<Group, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'work_group_user', 'user_id', 'group_id')
            ->as('membership')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Submission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    /**
     * @return HasMany<Classroom, $this>
     */
    public function taughtClassrooms(): HasMany
    {
        return $this->hasMany(Classroom::class, 'teacher_id');
    }

    /**
     * @return BelongsToMany<Classroom, $this>
     */
    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class)
            ->as('membership')
            ->withTimestamps();
    }

    /**
     * Le nom tel qu'on l'affiche : prénom puis nom.
     *
     * Les deux champs restent séparés dans les API Resources, parce que
     * l'application en fait ce qu'elle veut. Cette méthode est pour les
     * endroits qui ont besoin d'une seule chaîne : la zone
     * d'administration, les commandes.
     */
    public function displayName(): string
    {
        return trim($this->first_name.' '.$this->name);
    }

    /**
     * Présentation visible par autrui : seulement si remplie et partagée.
     */
    public function sharedBio(): ?string
    {
        return $this->bio_shared && filled($this->bio) ? $this->bio : null;
    }

    /**
     * Contact visible par autrui : seulement si rempli et partagé.
     */
    public function sharedContact(): ?string
    {
        return $this->contact_shared && filled($this->contact) ? $this->contact : null;
    }

    /**
     * Couleurs de la pastille d'initiales : le choix de la personne, ou la
     * valeur par défaut. Jamais nulles vers le client.
     */
    public function avatarBackground(): string
    {
        return $this->avatar_bg ?: config('profile.avatar.default_background');
    }

    public function avatarText(): string
    {
        return $this->avatar_fg ?: config('profile.avatar.default_text');
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * L'email de confirmation d'adresse, en français. Le lien signé et son
     * expiration restent fabriqués par Laravel.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify((new VerifyEmailAddress)->locale(app()->getLocale()));
    }

    /**
     * L'email de réinitialisation, en français. Le jeton reste fabriqué et
     * haché par Laravel, seul le texte et l'adresse du lien changent.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify((new ResetPasswordLink($token))->locale(app()->getLocale()));
    }

    public function isTeacher(): bool
    {
        return $this->role === UserRole::Teacher;
    }

    /**
     * Compte supprimé par son titulaire : vidé de son identité, et éteint.
     * Il ne peut plus se connecter.
     */
    public function isAnonymized(): bool
    {
        return $this->anonymized_at !== null;
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    /**
     * Seule porte de la zone d'administration.
     *
     * is_admin ne s'écrit qu'en ligne de commande, par meriz:admin : il est
     * absent de Fillable et de toutes les API Resources. Un compte supprimé
     * par son titulaire n'entre pas, même s'il avait le droit.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin() && ! $this->isAnonymized();
    }
}
