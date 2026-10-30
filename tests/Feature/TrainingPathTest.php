<?php

namespace Tests\Feature;

use App\Models\Training;
use App\Models\User;
use App\Services\TrainingPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TrainingPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_prerequisites_are_ordered_once_and_unavailable_nodes_block_path(): void
    {
        $a = Training::factory()->published()->create();
        $b = Training::factory()->published()->create();
        $c = Training::factory()->published()->create();
        $user = User::factory()->create();
        DB::table('training_prerequisites')->insert([['training_id' => $c->id, 'prerequisite_id' => $b->id], ['training_id' => $b->id, 'prerequisite_id' => $a->id], ['training_id' => $c->id, 'prerequisite_id' => $a->id]]);
        $path = app(TrainingPath::class)->forTraining($user, $c);
        $this->assertSame([$a->id, $b->id, $c->id], array_column($path['steps'], 'id'));
        $this->assertFalse($path['blocked']);
        $a->update(['status' => 'inactive']);
        $this->assertTrue(app(TrainingPath::class)->forTraining($user, $c)['blocked']);
        $this->assertDatabaseCount('training_enrollments', 0);
    }

    public function test_weekly_preference_filters_catalog_and_invalid_limit_is_rejected(): void
    {
        $this->withoutVite();
        $small = Training::factory()->published()->create(['weekly_hours' => 2]);
        $large = Training::factory()->published()->create(['weekly_hours' => 20]);
        $this->actingAs(User::factory()->create())->get('/pelamar/pelatihan?weekly_hours=3')->assertOk()->assertSee($small->title)->assertDontSee($large->title);
        $this->get('/pelamar/pelatihan?weekly_hours=0')->assertSessionHasErrors('weekly_hours');
    }
}
