<?php

namespace Tests\Feature;

use App\Models\Training;
use App\Models\TrainingSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_counts_public_training_and_pending_work_separately(): void
    {
        $this->withoutVite();
        Training::factory()->published()->create();
        Training::factory()->create();
        TrainingSubmission::factory()->create(['status' => 'pending']);
        $this->actingAs(User::factory()->admin()->create())->get('/admin/dashboard')->assertOk()->assertViewHas('publishedTrainings', 1)->assertViewHas('pendingTrainings', 1)->assertSee('Tinjau pelatihan');
        $this->actingAs(User::factory()->create())->get('/admin/dashboard')->assertForbidden();
    }
}
