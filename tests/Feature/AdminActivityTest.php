<?php

namespace Tests\Feature;

use App\Models\AccountStatusChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_has_readable_action_actor_date_and_filters_without_raw_system_keys(): void
    {
        $this->withoutVite();
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        AccountStatusChange::create(['user_id' => $user->id, 'actor_id' => $admin->id, 'user_name' => $user->name, 'user_email' => $user->email, 'user_role' => 'pelamar', 'actor_name' => $admin->name, 'before_is_active' => true, 'after_is_active' => false, 'reason' => 'Permintaan penonaktifan untuk pengujian.']);
        $this->actingAs($admin)->get('/admin/aktivitas?type=akun')->assertOk()->assertSee('Akun dinonaktifkan')->assertSee($admin->name)->assertSee('WIB')->assertSee(route('admin.accounts.show', $user));
        $this->get('/admin/aktivitas?type=bobot')->assertOk()->assertDontSee('Permintaan penonaktifan untuk pengujian.');
        $this->actingAs($user)->get('/admin/aktivitas')->assertForbidden();
    }
}
