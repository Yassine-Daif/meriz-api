<?php

namespace App\Actions\Auth;

use App\Models\User;

class IssueToken
{
    public const DEFAULT_DEVICE_NAME = 'meriz';

    public function handle(User $user, ?string $deviceName = null): string
    {
        return $user->createToken($deviceName ?: self::DEFAULT_DEVICE_NAME)->plainTextToken;
    }
}
