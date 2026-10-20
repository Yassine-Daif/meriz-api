<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\TrafficReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TrafficReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_traffic_is_admin_only(): void
    {
        $this->get('/admin/traffic')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create())->get('/admin/traffic')->assertForbidden();
    }

    public function test_admin_can_open_an_empty_report_without_a_server_dependency(): void
    {
        config(['traffic.directory' => storage_path('framework/testing/no-traffic')]);
        $this->actingAs(User::factory()->admin()->create())->get('/admin/traffic')
            ->assertOk()->assertSee('Collecte en préparation')->assertSee('Fréquentation');
    }

    public function test_report_content_is_escaped_and_path_is_allowlisted(): void
    {
        $directory = storage_path('framework/testing/traffic-'.uniqid());
        File::ensureDirectoryExists($directory);
        config(['traffic.directory' => $directory]);
        try {
            $report = [
                'generated_at' => now()->toIso8601String(), 'start_date' => '20/Oct/2026', 'end_date' => '20/Oct/2026',
                'summary' => ['visitors' => 2, 'requests' => 5, 'pages' => 3, 'errors' => 0],
                'daily' => [['label' => '20/10/2026', 'hits' => 5, 'visitors' => 2]],
                'pages' => [['label' => '<script>alert(1)</script>', 'hits' => 3]],
                'browsers' => [], 'os' => [], 'countries' => [], 'referrers' => [], 'statuses' => [],
            ];
            File::put($directory.'/site-7.json', json_encode($report));
            $this->actingAs(User::factory()->admin()->create())->get('/admin/traffic')->assertOk()
                ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
                ->assertDontSee('<script>alert(1)</script>', false);
            $this->expectException(HttpException::class);
            app(TrafficReport::class)->read('../../.env', 7);
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
