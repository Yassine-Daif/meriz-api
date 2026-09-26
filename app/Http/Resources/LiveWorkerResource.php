<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Une ligne du tableau de suivi : où en est cet élève, sans son contenu.
 *
 * L'élève est rendu en profil public, donc jamais d'email de connexion.
 *
 * @mixin User
 */
class LiveWorkerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'student' => new PublicProfileResource($this->resource),
            'has_started' => $this->work_id !== null,
            'last_activity_at' => $this->toIso($this->work_updated_at),
            'last_observed_at' => $this->toIso($this->work_observed_at),
            'submission_status' => $this->submission_status,
        ];
    }

    private function toIso(?string $value): ?string
    {
        return $value === null ? null : Carbon::parse($value)->toIso8601String();
    }
}
