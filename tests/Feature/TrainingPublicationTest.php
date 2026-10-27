<?php

namespace Tests\Feature;

use App\Models\Training;
use App\Models\User;
use App\Support\Publication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingPublicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_only_approved_active_training_with_eligible_body_and_active_outcomes_is_public(): void
    {
        $good = Training::factory()->published()->create();
        $plain = Training::factory()->create();
        $this->assertSame([$good->id], Publication::trainings()->pluck('trainings.id')->all());
        $good->body->user->update(['email_verified_at' => null]);
        $this->assertSame(0, Publication::trainings()->count());
        $good->body->user->update(['email_verified_at' => now()]);
        $good->skills()->first()->update(['is_active' => false]);
        $this->assertSame(0, Publication::trainings()->count());
        $this->assertDatabaseHas('trainings', ['id' => $plain->id]);
    }

    public function test_prerequisites_prevent_self_links_and_transitive_cycles(): void
    {
        [$a,$b,$c] = Training::factory()->published()->count(3)->create()->all();
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('admin.trainings.publication', $a))->assertOk();
        $this->put(route('admin.trainings.prerequisites', $a), ['prerequisites' => [$b->id]])->assertSessionHasNoErrors();
        $this->put(route('admin.trainings.prerequisites', $b), ['prerequisites' => [$c->id]])->assertSessionHasNoErrors();
        $this->put(route('admin.trainings.prerequisites', $c), ['prerequisites' => [$a->id]])->assertSessionHasErrors('prerequisites');
        $this->put(route('admin.trainings.prerequisites', $a), ['prerequisites' => [$a->id]])->assertSessionHasErrors('prerequisites');
        $this->assertDatabaseHas('training_prerequisites', ['training_id' => $a->id, 'prerequisite_id' => $b->id]);
        $this->assertDatabaseCount('training_prerequisites', 2);
    }

    public function test_publication_cannot_bypass_approval_and_other_roles_cannot_change_prerequisites(): void
    {
        $good = Training::factory()->published()->create();
        $plain = Training::factory()->create(['status' => 'inactive']);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->patch(route('admin.trainings.status', $plain), ['status' => 'active'])->assertSessionHasErrors('status');
        $this->patch(route('admin.trainings.status', $good), ['status' => 'inactive'])->assertSessionHasNoErrors();
        $this->assertSame(0, Publication::trainings()->count());
        $this->patch(route('admin.trainings.status', $good), ['status' => 'active'])->assertSessionHasNoErrors();
        $this->assertSame(1, Publication::trainings()->count());
        $this->actingAs(User::factory()->create())->put(route('admin.trainings.prerequisites',$good), ['prerequisites' => []])->assertForbidden();
    }
}
