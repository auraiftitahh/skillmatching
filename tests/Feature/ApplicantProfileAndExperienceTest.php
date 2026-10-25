<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserExperience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApplicantProfileAndExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
    }

    private function experienceData(array $overrides = []): array
    {
        return array_merge(['type' => 'work', 'organization' => 'PT Contoh Pengalaman', 'title' => 'Staf Gudang', 'start_date' => '2023-01-01', 'end_date' => '2024-12-31', 'is_current' => false, 'description' => 'Mengelola pencatatan persediaan.'], $overrides);
    }

    public function test_profile_updates_only_the_logged_in_applicants_safe_fields(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $password = $user->password;
        $this->actingAs($user)->get('/pelamar/profil?user_id='.$other->id)->assertOk()->assertSee($user->email)->assertDontSee($other->email);
        $this->patch('/pelamar/profil', ['name' => 'Nama Baru', 'phone' => '+62 812-3456-7890', 'domicile' => 'Bogor', 'employment_status' => 'laid_off', 'user_id' => $other->id, 'email' => 'changed@example.test', 'password' => 'AnotherPassword123!', 'role' => 'admin', 'is_active' => false, 'email_verified_at' => null])->assertRedirect(route('applicant.profile.edit'));
        $this->assertSame('Nama Baru', $user->fresh()->name);
        $this->assertSame('Bogor', $user->fresh()->domicile);
        $this->assertSame('laid_off', $user->fresh()->employment_status);
        $this->assertSame($password, $user->fresh()->password);
        $this->assertSame('pelamar', $user->fresh()->role);
        $this->assertTrue($user->fresh()->is_active);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertNotSame('changed@example.test', $user->fresh()->email);
        $this->assertNotSame('Nama Baru', $other->fresh()->name);
        $this->get('/pelamar/profil')->assertOk()->assertSee('Nama Baru')->assertSee('Bogor');
        Notification::assertNothingSent();
    }

    public function test_optional_profile_fields_can_be_cleared_and_invalid_values_do_not_save(): void
    {
        $user = User::factory()->create(['phone' => '081234567890', 'domicile' => 'Bogor', 'employment_status' => 'working']);
        $this->actingAs($user)->patch('/pelamar/profil', ['name' => 'Tetap Sama', 'phone' => '', 'domicile' => '', 'employment_status' => ''])->assertRedirect();
        $this->assertNull($user->fresh()->phone);
        $this->assertNull($user->fresh()->domicile);
        $this->assertNull($user->fresh()->employment_status);
        $this->patch('/pelamar/profil', ['name' => '', 'phone' => 'not-a-number', 'employment_status' => 'admin'])->assertSessionHasErrors(['name', 'phone', 'employment_status']);
        $this->assertSame('Tetap Sama', $user->fresh()->name);
    }

    public static function otherRoles(): array
    {
        return [['admin'], ['sertifikasi']];
    }

    #[DataProvider('otherRoles')]
    public function test_other_roles_cannot_read_or_write_applicant_profiles_or_experiences(string $role): void
    {
        $record = UserExperience::factory()->create();
        $this->actingAs(User::factory()->create(['role' => $role]));
        foreach (['/pelamar/profil', '/pelamar/riwayat', '/pelamar/riwayat/tambah', '/pelamar/riwayat/'.$record->id.'/ubah'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->patch('/pelamar/profil', ['name' => 'Not Allowed'])->assertForbidden();
        $this->post('/pelamar/riwayat', $this->experienceData())->assertForbidden();
        $this->put('/pelamar/riwayat/'.$record->id, $this->experienceData())->assertForbidden();
        $this->delete('/pelamar/riwayat/'.$record->id)->assertForbidden();
    }

    public function test_guests_and_ineligible_accounts_cannot_modify_profiles(): void
    {
        $this->get('/pelamar/profil')->assertRedirect(route('login'));
        $this->post('/pelamar/riwayat', $this->experienceData())->assertRedirect(route('login'));
        $user = User::factory()->inactive()->create();
        $this->actingAs($user)->patch('/pelamar/profil', ['name' => 'Not Saved'])->assertRedirect(route('login'));
        $this->assertNotSame('Not Saved', $user->fresh()->name);
    }

    public static function experienceTypes(): array
    {
        return [['work'], ['education'], ['organization']];
    }

    #[DataProvider('experienceTypes')]
    public function test_applicants_can_create_each_experience_type_without_assigning_another_owner(string $type): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user)->post('/pelamar/riwayat', $this->experienceData(['type' => $type, 'user_id' => $other->id, 'job_taxonomy_id' => 999]))->assertRedirect();
        $record = UserExperience::firstOrFail();
        $this->assertSame($user->id, $record->user_id);
        $this->assertSame($type, $record->type);
        $this->assertSame($user->id, $record->user->id);
        $this->assertSame(1, $user->experiences()->count());
        $this->assertFalse(Schema::hasTable('skills'));
        $this->get('/pelamar/riwayat')->assertOk()->assertSee('Staf Gudang')->assertSee('01 Januari 2023')->assertSee('31 Desember 2024');
    }

    public function test_editing_and_deleting_are_scoped_to_the_owner(): void
    {
        $user = User::factory()->create();
        $own = UserExperience::factory()->create(['user_id' => $user->id]);
        $other = UserExperience::factory()->create(['title' => 'Pengalaman Rahasia Orang Lain']);
        $this->actingAs($user)->get('/pelamar/riwayat')->assertOk()->assertDontSee('Pengalaman Rahasia Orang Lain');
        $this->get('/pelamar/riwayat/'.$other->id.'/ubah')->assertNotFound();
        $this->put('/pelamar/riwayat/'.$other->id, $this->experienceData())->assertNotFound();
        $this->delete('/pelamar/riwayat/'.$other->id)->assertNotFound();
        $this->get('/pelamar/riwayat/'.$own->id.'/ubah')->assertOk();
        $this->put('/pelamar/riwayat/'.$own->id, $this->experienceData(['title' => 'Posisi Diperbarui']))->assertRedirect();
        $this->assertSame('Posisi Diperbarui', $own->fresh()->title);
        $this->delete('/pelamar/riwayat/'.$own->id)->assertRedirect();
        $this->assertDatabaseMissing('user_experiences', ['id' => $own->id]);
        $this->assertDatabaseHas('user_experiences', ['id' => $other->id]);
    }

    public function test_invalid_dates_and_incomplete_required_fields_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/pelamar/riwayat', $this->experienceData(['end_date' => '2022-12-31']))->assertSessionHasErrors('end_date');
        $future = now('Asia/Jakarta')->addDay()->toDateString();
        $this->post('/pelamar/riwayat', $this->experienceData(['start_date' => $future, 'end_date' => null]))->assertSessionHasErrors('start_date');
        $this->post('/pelamar/riwayat', $this->experienceData(['end_date' => $future]))->assertSessionHasErrors('end_date');
        $this->post('/pelamar/riwayat', $this->experienceData(['start_date' => null]))->assertSessionHasErrors('start_date');
        $this->post('/pelamar/riwayat', $this->experienceData(['organization' => '', 'title' => '', 'type' => 'invalid']))->assertSessionHasErrors(['organization', 'title', 'type']);
        $this->post('/pelamar/riwayat', $this->experienceData(['description' => str_repeat('a', 2001)]))->assertSessionHasErrors('description');
        $this->assertDatabaseCount('user_experiences', 0);
    }

    public function test_ongoing_experiences_hide_and_clear_end_dates_and_can_be_completed_later(): void
    {
        $record = UserExperience::factory()->create();
        $this->actingAs($record->user)->put('/pelamar/riwayat/'.$record->id, $this->experienceData(['is_current' => true, 'end_date' => '2022-01-01']))->assertRedirect();
        $this->assertTrue($record->fresh()->is_current);
        $this->assertNull($record->fresh()->end_date);
        $this->get('/pelamar/riwayat/'.$record->id.'/ubah')->assertOk()->assertSee('data-experience-end  hidden', false);
        $this->put('/pelamar/riwayat/'.$record->id, $this->experienceData(['is_current' => false]))->assertRedirect();
        $this->assertFalse($record->fresh()->is_current);
        $this->assertSame('2024-12-31', $record->fresh()->end_date->toDateString());
    }

    public function test_dates_and_optional_description_can_be_left_empty(): void
    {
        $this->actingAs(User::factory()->create())->post('/pelamar/riwayat', $this->experienceData(['start_date' => null, 'end_date' => null, 'description' => null]))->assertRedirect();
        $this->assertNull(UserExperience::firstOrFail()->start_date);
        $this->assertNull(UserExperience::firstOrFail()->end_date);
    }

    public function test_history_filtering_search_pagination_and_empty_states_are_scoped(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/pelamar/riwayat')->assertOk()->assertSee('Belum ada riwayat pengalaman');
        UserExperience::factory()->count(12)->create(['user_id' => $user->id, 'type' => 'education']);
        $target = UserExperience::factory()->create(['user_id' => $user->id, 'type' => 'work', 'title' => 'Pengalaman 100%']);
        UserExperience::factory()->create(['title' => 'Rahasia Lain']);
        $this->get('/pelamar/riwayat?type=education&page=2')->assertOk()->assertViewHas('experiences', fn ($items) => $items->total() === 12 && $items->count() === 2);
        $this->get('/pelamar/riwayat?type=education&page=99')->assertRedirect(route('applicant.experiences.index', ['type' => 'education', 'page' => 2]));
        $this->get('/pelamar/riwayat?q=%25')->assertOk()->assertViewHas('experiences', fn ($items) => $items->total() === 1 && $items->first()->id === $target->id);
        $this->get('/pelamar/riwayat?q=TidakAda')->assertOk()->assertSee('Tidak ada riwayat yang sesuai');
        $this->getJson('/pelamar/riwayat?type=invalid')->assertUnprocessable();
    }

    public function test_description_is_escaped_and_the_page_has_explicit_delete_confirmation(): void
    {
        $record = UserExperience::factory()->create(['description' => '<script>alert(1)</script>']);
        $this->actingAs($record->user)->get('/pelamar/riwayat')->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertSee('Ya, hapus riwayat')->assertSee('name="_token"', false);
    }

    public function test_validation_preserves_safe_fields_even_when_an_input_is_an_array(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->from('/pelamar/profil')->patch('/pelamar/profil', ['name' => ['invalid'], 'domicile' => 'Bogor'])->assertSessionHasErrors('name');
        $this->get('/pelamar/profil')->assertOk()->assertSee('Bogor');
        $this->from('/pelamar/riwayat/tambah')->post('/pelamar/riwayat', $this->experienceData(['organization' => ['invalid']]))->assertSessionHasErrors('organization');
        $this->get('/pelamar/riwayat/tambah')->assertOk()->assertSee('Staf Gudang');
    }

    public function test_account_deletion_removes_only_its_own_experiences(): void
    {
        $own = UserExperience::factory()->create();
        $other = UserExperience::factory()->create();
        $own->user->delete();
        $this->assertDatabaseMissing('user_experiences', ['id' => $own->id]);
        $this->assertDatabaseHas('user_experiences', ['id' => $other->id]);
    }
}
