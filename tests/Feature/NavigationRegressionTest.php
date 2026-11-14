<?php
namespace Tests\Feature;

use App\Models\{Skill, User, WeightConfiguration};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NavigationRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        WeightConfiguration::create([
            'version' => 1, 'required_weight' => 3, 'preferred_weight' => 1,
            'related_factor' => 0.5, 'threshold' => 40, 'is_active' => true,
        ]);
    }

    private function signIn(string $role): User
    {
        $user = User::create([
            'name' => 'Pengguna Uji', 'email' => $role.'@example.test',
            'password' => 'sandi-uji-aman-123', 'role' => $role,
            'email_verified_at' => now(), 'is_active' => true,
        ]);
        if ($role === 'sertifikasi') {
            DB::table('certification_bodies')->insert([
                'user_id' => $user->id, 'name' => 'Lembaga Uji',
                'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->actingAs($user);
        return $user;
    }

    private function activeLinks(string $html, string $role): array
    {
        preg_match('/<nav aria-label="Menu '.preg_quote($role, '/').'">(.*?)<\/nav>/s', $html, $match);
        $this->assertArrayHasKey(1, $match, 'Navigasi utama tidak ditemukan');
        preg_match_all('/<a\s+[^>]*?class="active"[^>]*?href="([^"]+)"|<a\s+[^>]*?href="([^"]+)"[^>]*?class="active"/s', $match[1], $links, PREG_SET_ORDER);
        return array_map(fn($m) => $m[1] ?: ($m[2] ?? ''), $links);
    }

    public function test_activity_page_selects_only_activity_menu(): void
    {
        $this->signIn('pelamar');
        $res=$this->get('/pelamar/pelatihan/aktivitas')->assertOk();
        $this->assertSame(['/pelamar/pelatihan/aktivitas'], $this->activeLinks($res->getContent(), 'pelamar'));
    }

    public function test_training_list_selects_only_training_menu(): void
    {
        $this->signIn('pelamar');
        $res=$this->get('/pelamar/pelatihan')->assertOk();
        $this->assertSame(['/pelamar/pelatihan'], $this->activeLinks($res->getContent(), 'pelamar'));
    }

    public function test_applicant_menu_displays_profile_label_and_local_icons(): void
    {
        $this->signIn('pelamar');
        $res=$this->get('/pelamar/riwayat')->assertOk();
        $res->assertSee('Profil &amp; Riwayat', false);
        $this->assertSame(['/pelamar/riwayat'], $this->activeLinks($res->getContent(), 'pelamar'));
        $this->assertFileExists(public_path('icons/house.svg'));
        $this->assertFileExists(public_path('icons/clipboard-check.svg'));
    }

    public function test_admin_skill_edit_form_compiles_without_parse_error(): void
    {
        $this->signIn('admin');
        $skill=Skill::create(['name'=>'Forklift Uji','category'=>'Logistik','is_active'=>true]);
        $this->get('/admin/skill/form?id='.$skill->id)->assertOk()->assertSee('Forklift Uji');
    }

    public function test_certification_submenu_selects_only_participant(): void
    {
        $this->signIn('sertifikasi');
        $res=$this->get('/sertifikasi/pelatihan/peserta')->assertOk();
        $this->assertSame(['/sertifikasi/pelatihan/peserta'], $this->activeLinks($res->getContent(), 'sertifikasi'));
    }
}
