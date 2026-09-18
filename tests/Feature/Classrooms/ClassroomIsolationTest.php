<?php

namespace Tests\Feature\Classrooms;

use App\Models\Classroom;
use App\Models\User;
use App\Policies\ClassroomPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Preuves du cloisonnement des classes : un non-membre ne voit rien, un
 * membre ne gère rien, et l'email de connexion d'autrui ne sort jamais.
 */
class ClassroomIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $alice;

    private User $bob;

    private User $outsider;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->teacher()->withSharedProfile()->create(['email' => 'prof.secret@univ-lyon1.fr']);
        $this->alice = User::factory()->withSharedProfile()->create(['email' => 'alice.secret@gmail.com']);
        $this->bob = User::factory()->create([
            'email' => 'bob.secret@gmail.com',
            'bio' => 'Bio privée de Bob',
            'bio_shared' => false,
            'contact' => 'contact-prive-bob',
            'contact_shared' => false,
        ]);
        $this->outsider = User::factory()->create();

        $this->classroom = Classroom::factory()->for($this->teacher, 'teacher')->create(['name' => 'Terminale S1']);
        $this->classroom->members()->attach([$this->alice->id, $this->bob->id]);
    }

    public function test_student_only_lists_classrooms_they_belong_to(): void
    {
        $other = Classroom::factory()->create(['name' => 'Autre classe']);
        $other->members()->attach($this->outsider->id);

        Sanctum::actingAs($this->alice);
        $this->getJson('/api/classrooms')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$this->classroom->id])
            ->assertDontSee('Autre classe');

        Sanctum::actingAs($this->outsider);
        $this->getJson('/api/classrooms')
            ->assertJsonPath('data.*.id', [$other->id])
            ->assertDontSee('Terminale S1');
    }

    public function test_teacher_only_lists_their_own_classrooms(): void
    {
        $otherTeacherClass = Classroom::factory()->create();
        $joined = Classroom::factory()->create(['name' => 'Où le prof est élève']);
        $joined->members()->attach($this->teacher->id);

        Sanctum::actingAs($this->teacher);

        $this->getJson('/api/classrooms?role=teacher')
            ->assertJsonPath('data.*.id', [$this->classroom->id]);

        $this->getJson('/api/classrooms?role=student')
            ->assertJsonPath('data.*.id', [$joined->id]);

        $ids = $this->getJson('/api/classrooms')->json('data.*.id');
        $this->assertEqualsCanonicalizing([$this->classroom->id, $joined->id], $ids);
        $this->assertNotContains($otherTeacherClass->id, $ids);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function debugModes(): array
    {
        return ['debug désactivé' => ['0'], 'debug activé' => ['1']];
    }

    #[DataProvider('debugModes')]
    public function test_non_member_gets_the_same_404_as_a_missing_classroom(string $debug): void
    {
        config(['app.debug' => (bool) $debug]);
        Sanctum::actingAs($this->outsider);

        $targets = [
            'classe réelle' => $this->classroom->id,
            'ULID inexistant' => (string) Str::ulid(),
            'id mal formé' => 'pas-un-id',
        ];

        $routes = [
            ['GET', '/api/classrooms/%s'],
            ['PATCH', '/api/classrooms/%s'],
            ['DELETE', '/api/classrooms/%s'],
            ['POST', '/api/classrooms/%s/code'],
            ['DELETE', '/api/classrooms/%s/members/'.$this->alice->id],
            ['DELETE', '/api/classrooms/%s/membership'],
        ];

        foreach ($routes as [$method, $pattern]) {
            $bodies = [];

            foreach ($targets as $label => $id) {
                $response = $this->json($method, sprintf($pattern, $id), ['name' => 'x']);
                $this->assertSame(404, $response->status(), "{$method} {$pattern} {$label}");
                $bodies[$label] = $response->getContent();
            }

            $this->assertCount(1, array_unique($bodies), "{$method} {$pattern} : ".json_encode($bodies));
            $this->assertSame('{"message":"Ressource introuvable."}', reset($bodies));
        }

        $this->assertSame('Terminale S1', $this->classroom->fresh()->name);
        $this->assertSame(2, $this->classroom->members()->count());
    }

    public function test_another_teacher_cannot_manage_the_classroom(): void
    {
        $code = $this->classroom->join_code;
        Sanctum::actingAs(User::factory()->teacher()->create());

        $this->patchJson("/api/classrooms/{$this->classroom->id}", ['name' => 'Volée'])->assertNotFound();
        $this->postJson("/api/classrooms/{$this->classroom->id}/code")->assertNotFound();
        $this->deleteJson("/api/classrooms/{$this->classroom->id}/members/{$this->alice->id}")->assertNotFound();
        $this->deleteJson("/api/classrooms/{$this->classroom->id}")->assertNotFound();

        $fresh = $this->classroom->fresh();
        $this->assertSame('Terminale S1', $fresh->name);
        $this->assertSame($code, $fresh->join_code);
        $this->assertSame(2, $fresh->members()->count());
    }

    public function test_member_cannot_manage_the_classroom(): void
    {
        $code = $this->classroom->join_code;
        Sanctum::actingAs($this->alice);

        foreach ([
            ['PATCH', "/api/classrooms/{$this->classroom->id}", ['name' => 'Piratée']],
            ['POST', "/api/classrooms/{$this->classroom->id}/code", []],
            ['DELETE', "/api/classrooms/{$this->classroom->id}/members/{$this->bob->id}", []],
            ['DELETE', "/api/classrooms/{$this->classroom->id}", []],
        ] as [$method, $uri, $data]) {
            $this->json($method, $uri, $data)
                ->assertForbidden()
                ->assertJsonPath('message', ClassroomPolicy::OWNER_ONLY_MESSAGE);
        }

        $fresh = $this->classroom->fresh();
        $this->assertSame('Terminale S1', $fresh->name);
        $this->assertSame($code, $fresh->join_code);
        $this->assertTrue($fresh->hasMember($this->bob));
    }

    public function test_authorization_comes_before_validation(): void
    {
        // Données invalides : le refus d'accès doit primer sur la validation.
        Sanctum::actingAs($this->alice);
        $this->patchJson("/api/classrooms/{$this->classroom->id}", [])->assertForbidden();
        $this->postJson('/api/classrooms', [])->assertForbidden();

        Sanctum::actingAs($this->outsider);
        $this->patchJson("/api/classrooms/{$this->classroom->id}", [])->assertNotFound();
    }

    public function test_login_emails_never_appear_in_the_student_view(): void
    {
        Sanctum::actingAs($this->alice);

        $response = $this->getJson("/api/classrooms/{$this->classroom->id}")->assertOk();
        $body = $response->getContent();

        $this->assertStringNotContainsString('bob.secret@gmail.com', $body);
        $this->assertStringNotContainsString('prof.secret@univ-lyon1.fr', $body);
        $this->assertStringNotContainsString('"email"', $body);

        $bobEntry = collect($response->json('data.members'))->firstWhere('id', $this->bob->id);
        $this->assertSame($this->bob->name, $bobEntry['name']);
        $this->assertSame($this->bob->first_name, $bobEntry['first_name']);
    }

    public function test_login_emails_never_appear_in_the_teacher_view_nor_in_lists(): void
    {
        Sanctum::actingAs($this->teacher);
        $body = $this->getJson("/api/classrooms/{$this->classroom->id}")->assertOk()->getContent();
        $this->assertStringNotContainsString('alice.secret@gmail.com', $body);
        $this->assertStringNotContainsString('bob.secret@gmail.com', $body);
        $this->assertStringNotContainsString('"email"', $body);

        Sanctum::actingAs($this->alice);
        $list = $this->getJson('/api/classrooms')->getContent();
        $this->assertStringNotContainsString('prof.secret@univ-lyon1.fr', $list);

        $join = $this->postJson('/api/classrooms/join', ['code' => $this->classroom->join_code])->getContent();
        $this->assertStringNotContainsString('bob.secret@gmail.com', $join);
        $this->assertStringNotContainsString('prof.secret@univ-lyon1.fr', $join);
    }

    public function test_unshared_profile_fields_stay_hidden_from_classmates_and_teacher(): void
    {
        foreach ([$this->alice, $this->teacher] as $viewer) {
            Sanctum::actingAs($viewer);
            $response = $this->getJson("/api/classrooms/{$this->classroom->id}")->assertOk();

            $bobEntry = collect($response->json('data.members'))->firstWhere('id', $this->bob->id);
            $this->assertNull($bobEntry['bio']);
            $this->assertNull($bobEntry['contact']);
            $response->assertDontSee('Bio privée de Bob')->assertDontSee('contact-prive-bob');
        }
    }

    public function test_shared_profile_fields_are_visible_to_classmates(): void
    {
        Sanctum::actingAs($this->bob);
        $response = $this->getJson("/api/classrooms/{$this->classroom->id}")->assertOk();

        $aliceEntry = collect($response->json('data.members'))->firstWhere('id', $this->alice->id);
        $this->assertSame($this->alice->bio, $aliceEntry['bio']);
        $this->assertSame($this->alice->contact, $aliceEntry['contact']);
        $response->assertJsonPath('data.teacher.bio', $this->teacher->bio);

        // Aucun réglage de partage ni rôle d'autrui n'est exposé.
        $this->assertArrayNotHasKey('bio_shared', $aliceEntry);
        $this->assertArrayNotHasKey('role', $aliceEntry);
    }

    public function test_students_never_see_the_join_code(): void
    {
        Sanctum::actingAs($this->alice);

        $this->getJson("/api/classrooms/{$this->classroom->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.join_code')
            ->assertDontSee($this->classroom->join_code);

        $this->getJson('/api/classrooms')->assertDontSee($this->classroom->join_code);
        $this->postJson('/api/classrooms/join', ['code' => $this->classroom->join_code])
            ->assertJsonMissingPath('data.join_code');

        Sanctum::actingAs($this->teacher);
        $this->getJson("/api/classrooms/{$this->classroom->id}")
            ->assertJsonPath('data.join_code', $this->classroom->join_code);
    }

    public function test_owner_and_code_cannot_be_forced_by_the_client(): void
    {
        $otherTeacher = User::factory()->teacher()->create();
        Sanctum::actingAs($otherTeacher);

        $response = $this->postJson('/api/classrooms', [
            'name' => 'Ma classe',
            'teacher_id' => $this->teacher->id,
            'join_code' => 'AAAAAAAA',
            'id' => $this->classroom->id,
        ])->assertCreated();

        $created = Classroom::findOrFail($response->json('data.id'));
        $this->assertSame($otherTeacher->id, $created->teacher_id);
        $this->assertNotSame('AAAAAAAA', $created->join_code);
        $this->assertNotSame($this->classroom->id, $created->id);
        $this->assertSame('Terminale S1', $this->classroom->fresh()->name);
    }

    public function test_teacher_can_only_remove_members_of_their_own_classroom(): void
    {
        $otherClass = Classroom::factory()->create();
        $otherClass->members()->attach($this->outsider->id);

        Sanctum::actingAs($this->teacher);

        // Utilisateur qui n'est pas membre de cette classe.
        $this->deleteJson("/api/classrooms/{$this->classroom->id}/members/{$this->outsider->id}")->assertNotFound();
        // Utilisateur inexistant, id non numérique.
        $this->deleteJson("/api/classrooms/{$this->classroom->id}/members/999999")->assertNotFound();
        $this->deleteJson("/api/classrooms/{$this->classroom->id}/members/abc")->assertNotFound();
        // Classe d'un autre prof.
        $this->deleteJson("/api/classrooms/{$otherClass->id}/members/{$this->outsider->id}")->assertNotFound();

        $this->assertTrue($otherClass->hasMember($this->outsider));
    }

    public function test_every_route_requires_a_token(): void
    {
        $id = $this->classroom->id;

        $this->getJson('/api/classrooms')->assertUnauthorized();
        $this->postJson('/api/classrooms', ['name' => 'x'])->assertUnauthorized();
        $this->postJson('/api/classrooms/join', ['code' => $this->classroom->join_code])->assertUnauthorized();
        $this->getJson("/api/classrooms/{$id}")->assertUnauthorized();
        $this->patchJson("/api/classrooms/{$id}", ['name' => 'x'])->assertUnauthorized();
        $this->deleteJson("/api/classrooms/{$id}")->assertUnauthorized();
        $this->postJson("/api/classrooms/{$id}/code")->assertUnauthorized();
        $this->deleteJson("/api/classrooms/{$id}/members/{$this->alice->id}")->assertUnauthorized();
        $this->deleteJson("/api/classrooms/{$id}/membership")->assertUnauthorized();
        $this->patchJson('/api/me', ['bio' => 'x'])->assertUnauthorized();

        $this->assertSame(2, $this->classroom->members()->count());
    }
}
