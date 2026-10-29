<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WeightConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class WeightHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_history_exposes_old_and_current_versions_without_mutating_old_configuration(): void
    {
        $old = WeightConfiguration::current();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.weights.store'), ['base_version' => 1, 'required_weight' => 5, 'preferred_weight' => 2, 'related_factor' => 0.25, 'threshold' => 60, 'reason' => 'Penyesuaian bobot untuk kebutuhan baru.'])->assertSessionHasNoErrors();
        $this->get(route('admin.weights.history'))->assertOk()->assertSee('Lihat versi 1')->assertSee('Lihat versi 2')->assertSee($admin->name);
        $this->get(route('admin.weights.show', $old))->assertOk()->assertSee('Konfigurasi awal sistem.')->assertSee('Versi riwayat');
        $this->assertSame(3, $old->fresh()->required_weight);
        $this->assertSame(0.5, $old->fresh()->related_factor);
        $this->assertSame(5, WeightConfiguration::current()->required_weight);
    }

    public function test_published_configuration_cannot_be_overwritten_or_deleted_through_model(): void
    {
        $configuration = WeightConfiguration::current();
        try {
            $configuration->update(['required_weight' => 5]);
            $this->fail('Update should be rejected.');
        } catch (LogicException $error) {
            $this->assertStringContainsString('tidak dapat ditimpa', $error->getMessage());
        }
        $this->assertSame(3, $configuration->fresh()->required_weight);
        try {
            $configuration->fresh()->delete();
            $this->fail('Delete should be rejected.');
        } catch (LogicException $error) {
            $this->assertStringContainsString('tidak dapat dihapus', $error->getMessage());
        }
        $this->assertDatabaseCount('weight_configurations', 1);
    }

    public function test_private_history_is_not_available_to_applicant(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.weights.history'))->assertForbidden();
        $this->get(route('admin.weights.show', WeightConfiguration::current()))->assertForbidden();
    }
}
