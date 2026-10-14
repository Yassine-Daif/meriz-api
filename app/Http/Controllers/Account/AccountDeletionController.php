<?php

namespace App\Http\Controllers\Account;

use App\Actions\Account\DeleteAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\DeleteAccountRequest;
use Illuminate\Http\Response;

/**
 * Supprimer son compte, et seulement le sien : il vient du jeton. La
 * confirmation par mot de passe est vérifiée dans la requête, donc avant
 * que quoi que ce soit ne soit effacé.
 */
class AccountDeletionController extends Controller
{
    public function destroy(DeleteAccountRequest $request, DeleteAccount $delete): Response
    {
        $delete->handle($request->user());

        return response()->noContent();
    }
}
