<?php

namespace App\Services;

use App\Actions\Teacher\GrantTeacherRole;
use App\Enums\UsageCategory;
use App\Models\User;

class ActivateVerifiedTeacher
{
    public function __construct(private readonly GrantTeacherRole $grant) {}

    public function handle(User $user): void
    {
        if ($user->usage_category === UsageCategory::Teacher && $user->teacher_verified_at !== null && $user->hasVerifiedEmail()) {
            $this->grant->handle($user);
        }
    }
}
