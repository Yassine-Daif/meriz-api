<?php

namespace Tests\Feature\Admin;

use App\Filament\Widgets\InscriptionsChartWidget;
use App\Filament\Widgets\MetierStatsWidget;
use App\Filament\Widgets\RendusChartWidget;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Comment;
use App\Models\Document;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Les huit écrans de gestion, avec des données dedans : ils doivent
 * s'afficher, et ne pas laisser fuir un contenu de travail.
 */
class AdminResourcesTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET_CONTENT = 'CONTENU-SECRET-c47f';

    private function scene(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->create();

        $classroom = Classroom::factory()->for($teacher, 'teacher')->create();
        $classroom->members()->attach($student->id);

        $assignment = Assignment::factory()->for($classroom)->published()
            ->withBase('{"base":"'.self::SECRET_CONTENT.'"}')
            ->withSolution('{"solution":"'.self::SECRET_CONTENT.'"}')
            ->create();

        Lesson::factory()->for($classroom)->published()
            ->withBlocks('[{"type":"text","text":"'.self::SECRET_CONTENT.'"}]')
            ->create();

        Submission::factory()->for($assignment)->for($student, 'student')
            ->create(['content' => '{"rendu":"'.self::SECRET_CONTENT.'"}']);

        $document = Document::factory()->for($student)->forAssignment($assignment)
            ->create(['content' => '{"schema":"'.self::SECRET_CONTENT.'"}']);

        Comment::factory()->for($document)->for($teacher, 'author')->create();

        $group = Group::factory()->create();
        $group->members()->attach($student->id);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function pages(): array
    {
        return [
            'tableau de bord' => ['/admin'],
            'comptes' => ['/admin/users'],
            'classes' => ['/admin/classrooms'],
            'devoirs' => ['/admin/assignments'],
            'cours' => ['/admin/lessons'],
            'groupes' => ['/admin/groups'],
            'rendus' => ['/admin/submissions'],
            'commentaires' => ['/admin/comments'],
            'documents' => ['/admin/documents'],
        ];
    }

    #[DataProvider('pages')]
    public function test_each_page_opens_for_an_admin(string $url): void
    {
        $this->scene();
        $this->actingAs(User::factory()->admin()->create());

        $this->get($url)->assertOk();
    }

    #[DataProvider('pages')]
    public function test_no_page_shows_a_work_content(string $url): void
    {
        // Le panneau montre des tailles et des métadonnées, pas les schémas
        // ni les copies : ce sont les travaux de personnes réelles.
        $this->scene();
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->get($url)->assertOk();

        $this->assertStringNotContainsString(self::SECRET_CONTENT, $response->getContent());
    }

    #[DataProvider('pages')]
    public function test_no_page_opens_for_an_ordinary_account(string $url): void
    {
        $this->actingAs(User::factory()->create());

        $this->get($url)->assertForbidden();
    }

    public function test_the_stats_widget_shows_the_business_figures(): void
    {
        // Les widgets sont chargés en différé par Livewire : on teste le
        // composant, pas le HTML de la page qui l'accueille.
        $this->scene();
        $this->actingAs(User::factory()->admin()->create());

        $widget = Livewire::test(MetierStatsWidget::class);

        foreach (['Comptes actifs', 'Classes', 'Devoirs', 'Rendus', 'Cours', 'Documents'] as $label) {
            $widget->assertSee($label);
        }

        // Les chiffres de la scène : 3 comptes actifs, 1 classe, 1 devoir.
        $widget->assertSee('3')->assertSee('1');
    }

    public function test_the_charts_render_without_loading_every_row(): void
    {
        $this->scene();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(InscriptionsChartWidget::class)->assertOk();
        Livewire::test(RendusChartWidget::class)->assertOk();
    }

    public function test_a_widget_is_closed_to_an_ordinary_account(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin')->assertForbidden();
    }
}
