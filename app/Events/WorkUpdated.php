<?php

namespace App\Events;

use App\Models\Assignment;
use App\Models\Document;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Le travail d'un élève vient de changer, sur un devoir suivi en direct.
 *
 * Diffusion immédiate (ShouldBroadcastNow) : pas de worker de file à tenir.
 * L'évènement ne transporte que l'instantané, en lecture seule : rien ici ne
 * permet d'écrire chez l'élève.
 */
class WorkUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public readonly Document $document) {}

    /**
     * Second garde-fou, après celui du canal : rien ne part si le prof n'a
     * pas ouvert le suivi sur ce devoir.
     */
    public function broadcastWhen(): bool
    {
        return $this->assignment()?->liveTrackingEnabled() === true;
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(
            "assignments.{$this->document->assignment_id}.work.{$this->document->user_id}"
        )];
    }

    public function broadcastAs(): string
    {
        return 'work.updated';
    }

    /**
     * Liste blanche : l'instantané seulement. Ni note, ni corrigé, ni email.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'document_id' => $this->document->id,
            'assignment_id' => $this->document->assignment_id,
            'student_id' => $this->document->user_id,
            'name' => $this->document->name,
            'content' => $this->document->content,
            'updated_at' => $this->document->updated_at?->toIso8601String(),
        ];
    }

    private function assignment(): ?Assignment
    {
        return $this->document->assignment_id === null
            ? null
            : $this->document->assignment()->first();
    }
}
