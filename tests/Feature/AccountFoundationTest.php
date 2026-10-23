<?php

namespace Tests\Feature;

use App\Models\CertificationBody;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class AccountFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'skillmatching.seed.owner_email' => 'admin@example.test',
            'skillmatching.seed.owner_password' => 'OwnerTesting123!',
            'skillmatching.seed.demo_enabled' => true,
            'skillmatching.seed.demo_password' => 'DemoTesting123!',
            'skillmatching.seed.allow_demo_in_production' => false,
        ]);
        Mail::fake();
        Notification::fake();
    }

    public function test_new_accounts_default_to_active_applicants_with_hashed_hidden_passwords(): void
    {
        $user = User::create(['name' => 'Pelamar', 'email' => 'pelamar@example.test', 'password' => 'PasswordTesting123!']);
        $this->assertSame(User::ROLE_APPLICANT, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertSame(User::ROLE_APPLICANT, $user->fresh()->role);
        $this->assertTrue(Hash::check('PasswordTesting123!', $user->password));
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertArrayNotHasKey('remember_token', $user->toArray());
    }

    public function test_seed_creates_three_roles_and_independent_email_and_institution_states_without_sending_mail(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('users', 9);
        $this->assertDatabaseCount('certification_bodies', 5);
        $this->assertDatabaseHas('users', ['email' => 'admin@example.test', 'role' => 'admin']);
        $this->assertDatabaseHas('users', ['email' => 'nonaktif@example.test', 'is_active' => false]);
        $this->assertFalse(User::where('email', 'belum.verifikasi@example.test')->firstOrFail()->hasVerifiedEmail());
        $institution = User::where('email', 'lembaga.unverified@example.test')->firstOrFail();
        $this->assertFalse($institution->hasVerifiedEmail());
        $this->assertSame(CertificationBody::STATUS_ACTIVE, $institution->certificationBody->status);
        $this->assertSame($institution->id, $institution->certificationBody->user->id);
        $this->assertSame(CertificationBody::STATUS_PENDING, User::where('email', 'lembaga.pending@example.test')->firstOrFail()->certificationBody->status);
        $this->assertTrue(Hash::check('DemoTesting123!', $institution->password));
        Mail::assertNothingOutgoing();
        Notification::assertNothingSent();
    }

    public function test_repeated_seeding_preserves_existing_account_password_status_and_institution_details(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'kontak@logistiknusantara.id')->firstOrFail();
        $user->update(['name' => 'Nama Diubah', 'password' => 'ChangedTesting123!', 'is_active' => false]);
        $user->certificationBody->update(['name' => 'Lembaga Diubah', 'status' => CertificationBody::STATUS_DISABLED]);
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('users', 9);
        $this->assertDatabaseCount('certification_bodies', 5);
        $this->assertSame('Nama Diubah', $user->fresh()->name);
        $this->assertFalse($user->fresh()->is_active);
        $this->assertTrue(Hash::check('ChangedTesting123!', $user->fresh()->password));
        $this->assertSame('Lembaga Diubah', $user->certificationBody->fresh()->name);
        $this->assertSame(CertificationBody::STATUS_DISABLED, $user->certificationBody->fresh()->status);
    }

    public function test_demo_accounts_can_be_disabled(): void
    {
        config(['skillmatching.seed.demo_enabled' => false]);
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('certification_bodies', 0);
    }

    public function test_production_does_not_seed_demo_accounts_without_explicit_permission(): void
    {
        $this->app->instance('env', 'production');
        $this->app->make(AccountSeeder::class)->run();
        $this->assertDatabaseCount('users', 1);
        config(['skillmatching.seed.allow_demo_in_production' => true]);
        $this->app->make(AccountSeeder::class)->run();
        $this->assertDatabaseCount('users', 9);
    }

    public function test_missing_demo_password_fails_before_any_account_is_created(): void
    {
        config(['skillmatching.seed.demo_password' => '']);
        try {
            $this->seed(DatabaseSeeder::class);
            $this->fail('Seeder should reject missing demo credentials.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('DEMO_PASSWORD', $exception->getMessage());
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_existing_non_admin_is_not_promoted_by_owner_configuration(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.test']);
        try {
            $this->seed(DatabaseSeeder::class);
            $this->fail('Seeder should reject an occupied owner email.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('non-Admin', $exception->getMessage());
        }
        $this->assertSame(User::ROLE_APPLICANT, $user->fresh()->role);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_role_migration_preserves_existing_accounts(): void
    {
        $user = User::factory()->create(['name' => 'Akun Lama']);
        $migration = require database_path('migrations/2026_10_25_135217_add_role_and_active_status_to_users_table.php');
        $migration->down();
        $migration->up();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Akun Lama',
            'role' => User::ROLE_APPLICANT,
            'is_active' => true,
        ]);
    }

    public function test_conflicting_demo_institution_email_rolls_back_all_new_seed_accounts(): void
    {
        $user = User::factory()->create(['email' => 'kontak@logistiknusantara.id']);
        try {
            $this->seed(DatabaseSeeder::class);
            $this->fail('Conflicting demo role should abort the transaction.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('role lain', $exception->getMessage());
        }
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('certification_bodies', 0);
        $this->assertSame(User::ROLE_APPLICANT, $user->fresh()->role);
    }

    public function test_each_user_can_only_have_one_institution_profile(): void
    {
        $institution = CertificationBody::factory()->create();
        $this->expectException(QueryException::class);
        CertificationBody::factory()->create(['user_id' => $institution->user_id]);
    }

    public function test_deleting_an_institution_account_removes_its_profile(): void
    {
        $institution = CertificationBody::factory()->create();
        $institution->user->delete();
        $this->assertDatabaseMissing('certification_bodies', ['id' => $institution->id]);
    }
}
