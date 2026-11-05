<?php

namespace App\Actions\Documents;

use App\Models\Document;
use App\Models\Group;
use App\Models\User;
use App\Services\DocumentStorageBudget;
use Illuminate\Support\Facades\DB;

class UpdateDocument
{
    public function __construct(private readonly DocumentStorageBudget $budget) {}

    public function handle(Document $document, array $data): Document
    {
        return DB::transaction(function () use ($document, $data) {
            $owner = $document->group_id !== null
                ? Group::whereKey($document->group_id)->lockForUpdate()->firstOrFail()
                : User::whereKey($document->user_id)->lockForUpdate()->firstOrFail();
            $document->refresh();
            if (array_key_exists('content', $data)) {
                $this->budget->check($owner, $data['content'], $document);
            }
            $document->update($data);

            return $document;
        });
    }
}
