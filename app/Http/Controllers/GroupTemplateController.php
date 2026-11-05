<?php

namespace App\Http\Controllers;

use App\Actions\Groups\CreateGroupDocument;
use App\Http\Requests\Groups\DistributeGroupTemplateRequest;
use App\Models\Document;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class GroupTemplateController extends Controller
{
    public function store(DistributeGroupTemplateRequest $request, Document $document, CreateGroupDocument $create)
    {
        $result = DB::transaction(function () use ($request, $document, $create) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $ids = $request->validated('group_ids');
            sort($ids);
            $prior = DB::table('group_template_copies')->where('user_id', $request->user()->id)->where('request_key', $request->validated('request_key'))->orderBy('group_id')->get();
            if ($prior->isNotEmpty() && ($prior->pluck('group_id')->all() !== $ids || $prior->contains(fn ($row) => $row->source_id !== $document->id))) {
                throw ValidationException::withMessages(['request_key' => 'Cette demande correspond à une autre sélection. Rouvrez la diffusion pour en créer une nouvelle.']);
            }
            $result = [];
            foreach ($ids as $id) {
                $key = ['user_id' => $request->user()->id, 'group_id' => $id, 'request_key' => $request->validated('request_key')];
                $existing = DB::table('group_template_copies')->where($key)->first();
                if ($existing && $existing->source_id !== $document->id) {
                    throw ValidationException::withMessages(['request_key' => 'Cette demande correspond à un autre document.']);
                }
                // Une copie supprimée volontairement n'est pas recréée par une relance.
                if ($existing) {
                    $result[] = ['group_id' => $id, 'document_id' => $existing->document_id];

                    continue;
                }
                $group = Group::visibleTo($request->user())->whereKey($id)->firstOrFail();
                Gate::authorize('createDocument', $group);
                $copy = $create->handle($group, $request->user(), ['name' => $document->name, 'content' => $document->content]);
                DB::table('group_template_copies')->insert([...$key, 'source_id' => $document->id, 'document_id' => $copy->id, 'created_at' => now(), 'updated_at' => now()]);
                $result[] = ['group_id' => $id, 'document_id' => $copy->id];
            }

            return $result;
        });

        return response()->json(['data' => $result]);
    }
}
