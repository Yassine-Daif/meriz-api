<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Notifications\VerifyEmailAddress;
use Database\Factories\UserFactory;
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
 * Fillable ne contient que les champs du profil. email, password, role et
 * is_academic ne s'écrivent que par forceFill, dans les actions dédiées.
 */
#[Fillable(['name', 'first_name', 'bio', 'bio_shared', 'contact', 'contact_shared', 'avatar_bg', 'avatar_fg'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
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
        $this->notify(new VerifyEmailAddress);
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
}
