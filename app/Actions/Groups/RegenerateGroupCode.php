<?php

namespace App\Actions\Groups;

use App\Models\Group;
use App\Services\JoinCodeGenerator;

/**
 * Remplace le code d'un groupe. L'ancien cesse aussitôt de fonctionner.
 */
class RegenerateGroupCode
{
    public function __construct(private readonly JoinCodeGenerator $codes) {}

    public function handle(Group $group): Group
    {
        $group->forceFill(['join_code' => $this->codes->generate(Group::class)])->save();

        return $group;
    }
}
