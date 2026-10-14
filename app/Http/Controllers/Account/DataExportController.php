<?php

namespace App\Http\Controllers\Account;

use App\Actions\Account\ExportAccountData;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Emporter ses données.
 *
 * Aucun paramètre : le compte exporté est celui du jeton, toujours. Le
 * fichier est du JSON mis en forme, pour être lisible tel quel.
 */
class DataExportController extends Controller
{
    public function show(Request $request, ExportAccountData $export): StreamedResponse
    {
        $user = $request->user();
        $payload = $export->handle($user);

        $filename = 'meriz-donnees-'.$user->id.'-'.Carbon::now()->format('Y-m-d').'.json';

        return response()->streamDownload(
            function () use ($payload) {
                echo json_encode(
                    $payload,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                );
            },
            $filename,
            ['Content-Type' => 'application/json'],
        );
    }
}
