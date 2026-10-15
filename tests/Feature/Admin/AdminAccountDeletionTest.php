<?php

namespace Tests\Feature\Admin;

use App\Enums\SubmissionStatus;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Comment;
use App\Models\Document;
use App\Models\Submission;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Supprimer un compte depuis le panneau doit produire exactement le même
 * état que DELETE /api/me : une seule règle dans tout le serveur.
 */
class AdminAccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_anonymizes_like_the_api_does(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->for($teacher, 'teacher')->create();

        $alice = User::factory()->create([
            'name' => 'Martin',
            'first_name' => 'Alice',
            'email' => 'alice@example.com',
            'bio' => 'Première année',
        ]);
        $classroom->members()->attach($alice->id);

        $assignment = Assignment::factory()->for($classroom)->published()->create();
        $submission = Submission::factory()->for($assignment)->for($alice, 'student')
            ->graded('16/20', 'Cardinalités justes.')->create();

        $work = Document::factory()->for($alice)->forAssignment($assignment)->create();
        $comment = Comment::factory()->for($work)->for($teacher, 'author')->create(['body' => 'À revoir.']);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageUsers::class)
            ->callAction(TestAction::make('deleteAccount')->table($alice))
            ->assertHasNoActionErrors();

        $alice->refresh();

        // Identité effacée.
        $this->assertSame(User::DELETED_NAME, $alice->name);
        $this->assertNull($alice->first_name);
        $this->assertNull($alice->bio);
        $this->assertSame('supprime-'.$alice->id.'@compte.invalid', $alice->email);
        $this->assertNotNull($alice->anonymized_at);

        // Documents personnels et inscriptions partis.
        $this->assertNull(Document::find($work->id));
        $this->assertSame(0, $alice->classrooms()->count());

        // Le rendu noté reste chez le prof, intact.
        $submission->refresh();
        $this->assertSame('16/20', $submission->grade);
        $this->assertSame(SubmissionStatus::Graded, $submission->status);

        // Le commentaire du prof suit son document : celui-ci était le
        // travail personnel d'Alice, donc il part avec lui.
        $this->assertNull(Comment::find($comment->id));
    }

    public function test_the_admin_cannot_delete_an_account_in_base(): void
    {
        $alice = User::factory()->create();
        $assignment = Assignment::factory()->published()->create();
        Submission::factory()->for($assignment)->for($alice, 'student')->graded()->create();

        $this->actingAs(User::factory()->admin()->create());

        // La suppression ordinaire de Filament n'est pas proposée : elle
        // emporterait le rendu noté en cascade.
        Livewire::test(ManageUsers::class)
            ->assertActionDoesNotExist(TestAction::make('delete')->table($alice));

        $this->assertSame(1, Submission::count());
    }

    public function test_an_already_deleted_account_offers_no_action(): void
    {
        $alice = User::factory()->create();
        $alice->forceFill(['anonymized_at' => now()])->save();

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageUsers::class)
            ->assertActionHidden(TestAction::make('deleteAccount')->table($alice))
            ->assertActionHidden(TestAction::make('edit')->table($alice));
    }

    public function test_only_an_admin_reaches_that_page(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/users')->assertForbidden();
    }
}
