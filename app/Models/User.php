<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
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
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_academic' => 'boolean',
            'bio_shared' => 'boolean',
            'contact_shared' => 'boolean',
        ];
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

    public function isTeacher(): bool
    {
        return $this->role === UserRole::Teacher;
    }
}
