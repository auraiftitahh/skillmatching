<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WeightConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WeightConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function payload(): array
    {
        return ['base_version' => 1, 'required_weight' => 4, 'preferred_weight' => 2, 'related_factor' => 0.6, 'threshold' => 45, 'reason' => 'Penyesuaian kebutuhan kompetensi.'];
    }

    public function test_publishing_creates_new_version_without_overwriting_existing_values(): void
    {
        $original = WeightConfiguration::current();
        $old = $original->getAttributes();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/bobot')->assertOk()->assertSee('Versi aktif 1');
        $this->post(route('admin.weights.store'), $this->payload())->assertSessionHasNoErrors();
        $current = WeightConfiguration::current();
        $this->assertSame(2, $current->version);
        $this->assertSame(4, $current->required_weight);
        $this->assertSame($admin->id, $current->created_by);
        $this->assertSame($old, $original->fresh()->getAttributes());
        $this->assertDatabaseCount('weight_configurations', 2);
        $this->assertSame(1, DB::table('weight_configuration_state')->count());
    }

    public function test_invalid_ranges_order_and_missing_reason_do_not_change_current_configuration(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $data = $this->payload();
        $data['required_weight'] = 1;
        $data['preferred_weight'] = 2;
        $data['related_factor'] = 1.1;
        $data['threshold'] = 101;
        $data['reason'] = '';
        $this->post(route('admin.weights.store'), $data)->assertSessionHasErrors(['required_weight', 'related_factor', 'threshold', 'reason']);
        $this->assertSame(1, WeightConfiguration::current()->version);
        $this->assertDatabaseCount('weight_configurations', 1);
    }

    public function test_stale_submission_cannot_replace_newer_configuration_and_other_roles_are_blocked(): void
    {
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.weights.store'), $this->payload())->assertSessionHasNoErrors();
        $this->post(route('admin.weights.store'), $this->payload())->assertStatus(409);
        $this->assertSame(2, WeightConfiguration::current()->version);
        $this->actingAs(User::factory()->create())->get('/admin/bobot')->assertForbidden();
        $this->post(route('admin.weights.store'), $this->payload())->assertForbidden();
    }
}
