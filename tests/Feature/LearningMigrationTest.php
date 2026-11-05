<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LearningMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_document_sizes_are_initialized_without_changing_content(): void
    {
        $document = Document::factory()->create(['content' => '{"nom":"Élodie"}']);
        $migration = require database_path('migrations/2026_11_05_000004_add_document_storage_size.php');
        $migration->down();
        $migration->up();
        $row = DB::table('documents')->find($document->id);
        $this->assertSame('{"nom":"Élodie"}', $row->content);
        $this->assertSame(strlen($row->content), $row->content_bytes);
    }

    public function test_old_accounts_keep_their_role_and_receive_no_forced_interface(): void
    {
        $teacher = User::factory()->teacher()->create();
        $migration = require database_path('migrations/2026_11_05_000001_add_learning_preferences_to_users.php');
        $migration->down();
        $migration->up();
        $row = DB::table('users')->find($teacher->id);
        $this->assertSame('teacher', $row->role);
        $this->assertNull($row->interface_mode);
        $this->assertNull($row->usage_category);
    }
}
