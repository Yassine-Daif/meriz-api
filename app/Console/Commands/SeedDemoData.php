<?php

namespace App\Console\Commands;

use App\Models\Classroom;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

/**
 * Remplit la base de développement avec un jeu de données de test.
 *
 * Réservée au développement : en production, elle demande confirmation,
 * comme les commandes de migration.
 */
class SeedDemoData extends Command
{
    use ConfirmableTrait;

    protected $signature = 'meriz:demo-data {--force : Exécuter même en production}';

    protected $description = 'Remplit la base de développement : un prof de test, des classes et des élèves';

    public function handle(DemoDataSeeder $seeder): int
    {
        if (! $this->confirmToProceed('Cette commande remplit la base avec des données de test.')) {
            return self::FAILURE;
        }

        $this->components->info('Création des données de test…');

        $seeder->setCommand($this)->run();

        $teacher = User::firstWhere('email', DemoDataSeeder::TEACHER_EMAIL);

        $this->newLine();
        $this->components->twoColumnDetail('<fg=green>Prof de test</>', $teacher->first_name.' '.$teacher->name);
        $this->components->twoColumnDetail('Email', DemoDataSeeder::TEACHER_EMAIL);
        $this->components->twoColumnDetail('Mot de passe', DemoDataSeeder::TEACHER_PASSWORD);
        $this->newLine();

        $this->table(
            ['Classe', 'Code', 'Élèves'],
            Classroom::where('teacher_id', $teacher->id)
                ->withCount('members')
                ->orderBy('name')
                ->get()
                ->map(fn (Classroom $c) => [$c->name, $c->join_code, $c->members_count])
                ->all(),
        );

        $this->components->twoColumnDetail(
            'Élèves de test',
            User::where('email', 'like', '%@'.DemoDataSeeder::DOMAIN)->count() - 1 .' comptes, mot de passe '.DemoDataSeeder::STUDENT_PASSWORD,
        );
        $this->components->info('Relancer la commande remplace ces données, sans toucher aux autres comptes.');

        return self::SUCCESS;
    }
}
