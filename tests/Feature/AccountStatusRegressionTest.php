<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountStatusRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function makeApplicant(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Pelamar Uji',
            'email' => 'pelamar-status@example.test',
            'password' => 'password-untuk-test-123',
            'role' => 'pelamar',
            'email_verified_at' => now(),
        ], $overrides));
    }

    public function test_user_created_without_explicit_active_flag_matches_database_default(): void
    {
        $user = $this->makeApplicant();
        // Periksa model sebelum refresh: di sinilah dua tes v1.1.1 sebelumnya gagal.
        $this->assertTrue($user->is_active);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => true]);
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_active_applicant_gets_forbidden_when_visiting_admin_area(): void
    {
        $user = $this->makeApplicant();
        $this->actingAs($user)->get('/admin/dashboard')->assertForbidden();
    }

    public function test_explicitly_disabled_user_is_not_reactivated_by_model_default(): void
    {
        $user = $this->makeApplicant(['is_active' => false]);
        $this->assertFalse($user->is_active);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => false]);

        $this->actingAs($user)->get('/pelamar/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_account_disabled_after_creation_cannot_access_next_request(): void
    {
        $user = $this->makeApplicant();
        $user->update(['is_active' => false]);
        $this->actingAs($user->fresh())->get('/pelamar/dashboard')
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
