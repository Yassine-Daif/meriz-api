<?php

namespace App\Actions\Comments;

use App\Models\Comment;
use App\Models\User;

/**
 * Marque un commentaire comme traité, ou le rouvre. Ces champs ne s'écrivent
 * qu'ici, et l'on garde qui a classé le point.
 */
class ResolveComment
{
    public function resolve(Comment $comment, User $user): Comment
    {
        $comment->forceFill([
            'resolved_at' => now(),
            'resolved_by' => $user->id,
        ])->save();

        return $comment;
    }

    public function reopen(Comment $comment): Comment
    {
        $comment->forceFill([
            'resolved_at' => null,
            'resolved_by' => null,
        ])->save();

        return $comment;
    }
}
