<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\User;
use App\Services\JoinCodeGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Jeu de données de développement : un prof connu, trois classes et des
 * élèves aux noms français, avec des profils et des couleurs variés.
 *
 * Tous les comptes créés portent le domaine DOMAIN. Le nettoyage ne touche
 * que ces comptes : les comptes d'essai existants ne sont jamais supprimés.
 */
class DemoDataSeeder extends Seeder
{
    public const DOMAIN = 'demo.meriz.test';

    public const TEACHER_EMAIL = 'prof@'.self::DOMAIN;

    public const TEACHER_PASSWORD = 'motdepasse-demo';

    public const STUDENT_PASSWORD = 'motdepasse-eleve';

    /** Tirage fixe : deux lancements donnent les mêmes personnes. */
    private const RANDOM_SEED = 20260920;

    private const CLASSROOM_NAMES = ['Terminale NSI', 'Première SNT', 'BUT1 Informatique'];

    /** Le Faker français n'a pas de texte français : on écrit les phrases. */
    private const BIOS = [
        'Je révise surtout le soir, en général vers 20 h.',
        'Passionnée de bases de données depuis la seconde.',
        'Je préfère travailler en binôme sur les MCD.',
        'Délégué de classe. N\'hésitez pas à me demander les consignes.',
        'J\'aime bien relire les schémas des autres avant de rendre.',
        'Je fais du basket le mercredi, donc peu dispo ce jour-là.',
        'Plutôt à l\'aise sur les jointures, moins sur les cardinalités.',
        'Je prends des notes propres, je peux les partager.',
        'Alternance en entreprise le jeudi et le vendredi.',
        'Je débute, mais je pose beaucoup de questions.',
    ];

    private const CONTACTS = [
        'discord: %s',
        'Salle d\'étude, le midi',
        '%s@exemple.fr',
        'Groupe de la classe sur Discord',
    ];

    public function run(): void
    {
        $this->purge();

        // Tout le tirage passe par Faker, donc par cette graine : deux
        // lancements donnent exactement les mêmes personnes et effectifs.
        fake('fr_FR')->seed(self::RANDOM_SEED);

        $teacher = $this->createTeacher();
        $classrooms = $this->createClassrooms($teacher);
        $this->fillClassrooms($classrooms);
    }

    /**
     * Supprime uniquement les données de test précédentes.
     */
    public function purge(): void
    {
        User::where('email', 'like', '%@'.self::DOMAIN)->get()->each(function (User $user) {
            // Par le modèle : l'événement de suppression efface aussi les
            // images des devoirs de la classe.
            $user->taughtClassrooms->each->delete();
            $user->documents()->delete();
            $user->tokens()->delete();
            $user->delete();
        });
    }

    private function createTeacher(): User
    {
        $teacher = new User(['name' => 'Bernard', 'first_name' => 'Claire']);

        $teacher->forceFill([
            'email' => self::TEACHER_EMAIL,
            'password' => Hash::make(self::TEACHER_PASSWORD),
            'email_verified_at' => now(),
            'role' => UserRole::Teacher,
            'is_academic' => true,
            'bio' => 'Prof de NSI. Disponible le mardi après-midi.',
            'bio_shared' => true,
            'contact' => 'Permanence salle B12',
            'contact_shared' => true,
            'avatar_bg' => '#1e1b4b',
            'avatar_fg' => '#e0e7ff',
        ])->save();

        return $teacher;
    }

    /**
     * @return list<Classroom>
     */
    private function createClassrooms(User $teacher): array
    {
        $codes = app(JoinCodeGenerator::class);

        return collect(self::CLASSROOM_NAMES)
            ->map(function (string $name) use ($teacher, $codes) {
                $classroom = new Classroom(['name' => $name]);
                $classroom->forceFill([
                    'teacher_id' => $teacher->id,
                    'join_code' => $codes->generate(),
                ])->save();

                return $classroom;
            })
            ->all();
    }

    /**
     * Entre 8 et 15 élèves par classe, plus trois élèves partagés entre
     * plusieurs classes.
     *
     * @param  list<Classroom>  $classrooms
     */
    private function fillClassrooms(array $classrooms): void
    {
        foreach ($classrooms as $index => $classroom) {
            $students = collect(range(1, fake('fr_FR')->numberBetween(8, 15)))
                ->map(fn (int $position) => $this->createStudent($position + $index * 100));

            $classroom->members()->attach($students->pluck('id'));
        }

        // Multi-classe : trois élèves inscrits dans deux ou trois classes.
        foreach (range(1, 3) as $position) {
            $student = $this->createStudent(900 + $position);
            $extra = array_slice($classrooms, 0, $position === 3 ? 3 : 2);

            foreach ($extra as $classroom) {
                $classroom->members()->attach($student->id);
            }
        }
    }

    private function createStudent(int $position): User
    {
        $faker = fake('fr_FR');
        $name = $faker->lastName();
        $firstName = $faker->firstName();

        $student = new User(['name' => $name, 'first_name' => $firstName]);
        $student->forceFill([
            'email' => $this->email($firstName, $name, $position),
            'password' => Hash::make(self::STUDENT_PASSWORD),
            'email_verified_at' => now(),
            'role' => UserRole::Student,
            'is_academic' => false,
            ...$this->profile($position),
            ...$this->avatar($position),
        ])->save();

        return $student;
    }

    private function email(string $firstName, string $name, int $position): string
    {
        $handle = Str::slug($firstName.'-'.$name, '.');

        return $handle.'.'.$position.'@'.self::DOMAIN;
    }

    /**
     * Profils variés : un tiers partage, un tiers remplit sans partager,
     * un tiers laisse vide.
     *
     * @return array<string, mixed>
     */
    private function profile(int $position): array
    {
        $handle = Str::slug(fake('fr_FR')->firstName().'.'.fake('fr_FR')->lastName(), '_');
        $bio = self::BIOS[$position % count(self::BIOS)];
        $contact = sprintf(self::CONTACTS[$position % count(self::CONTACTS)], $handle);

        return match ($position % 3) {
            0 => [
                'bio' => $bio,
                'bio_shared' => true,
                'contact' => $contact,
                'contact_shared' => true,
            ],
            1 => [
                'bio' => $bio,
                'bio_shared' => false,
                'contact' => $contact,
                // Un seul des deux champs partagé, une fois sur deux.
                'contact_shared' => $position % 2 === 0,
            ],
            default => [],
        };
    }

    /**
     * Couleurs variées, et un élève sur cinq sans couleur choisie, pour voir
     * les valeurs par défaut.
     *
     * @return array<string, mixed>
     */
    private function avatar(int $position): array
    {
        if ($position % 5 === 0) {
            return [];
        }

        $palette = config('profile.avatar.palette');
        [$background, $text] = $palette[$position % count($palette)];

        return ['avatar_bg' => $background, 'avatar_fg' => $text];
    }
}
