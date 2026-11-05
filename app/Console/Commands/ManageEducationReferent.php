<?php

namespace App\Console\Commands;

use App\Models\EducationInstitution;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Réservé à l'opérateur après vérification du mandat par l'établissement. */
class ManageEducationReferent extends Command
{
    protected $signature = 'meriz:education-referent {email} {institution : Identifiant de l’établissement} {--revoke}';

    protected $description = 'Autoriser ou retirer un référent dont le mandat institutionnel a été vérifié';

    public function handle(): int
    {
        $user = User::where('email', mb_strtolower(trim($this->argument('email'))))->first();
        $institution = EducationInstitution::find($this->argument('institution'));
        if (! $user || ! $institution || $user->isAnonymized() || ! $user->hasVerifiedEmail()) {
            $this->error('Compte vérifié et établissement existant requis.');

            return self::FAILURE;
        }
        $query = DB::table('education_referents')->where('user_id', $user->id)->where('institution_id', $institution->id);
        if ($this->option('revoke')) {
            $query->delete();
            $this->info('Mandat retiré. Les invitations non utilisées de ce référent seront refusées.');
        } else {
            $this->warn('Le mandat de cette personne doit avoir été vérifié auprès de l’établissement, indépendamment de son adresse email.');
            if (! $this->confirm('Le mandat est-il vérifié ?', false)) {
                return self::FAILURE;
            }
            DB::table('education_referents')->updateOrInsert(['user_id' => $user->id, 'institution_id' => $institution->id], ['created_at' => now(), 'updated_at' => now()]);
            $this->info('Référent autorisé.');
        }

        return self::SUCCESS;
    }
}
