<?php

namespace Tests\Feature;

use App\Models\CertificationBody;
use App\Models\User;
use App\Support\WorkspaceNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_visible_operational_menu_opens_a_real_page_in_its_own_role(): void
    {
        $this->withoutVite();
        foreach (['pelamar', 'admin', 'sertifikasi'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            if ($role === 'sertifikasi') {
                CertificationBody::factory()->active()->create(['user_id' => $user->id]);
            }
            $this->actingAs($user);
            foreach (WorkspaceNavigation::items($role, 'dashboard') as $item) {
                $this->get($item['url'])->assertOk()->assertDontSee('Halaman ini menjadi dasar')->assertDontSee('Pratinjau antarmuka.');
            }
        }
    }

    public function test_unavailable_features_are_not_advertised_and_direct_access_is_clear(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create())->get('/pelamar/dashboard')->assertSee('Bukti Kompetensi')->assertDontSee('/pelamar/pengaturan');
        $this->get('/pelamar/pengaturan')->assertNotFound()->assertSee('Halaman tidak tersedia');
        $this->get('/pelamar')->assertRedirect('/pelamar/dashboard');
    }
}
