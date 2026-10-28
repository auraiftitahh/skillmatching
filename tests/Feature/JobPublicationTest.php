<?php

namespace Tests\Feature;

use App\Models\JobVacancy;
use App\Models\User;
use App\Support\Publication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobPublicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_job_requires_active_status_valid_source_dates_and_active_requirements(): void
    {
        $job = JobVacancy::factory()->published()->create();
        $this->assertSame([$job->id], Publication::jobs()->pluck('job_vacancies.id')->all());
        $job->update(['status' => 'draft']);
        $this->assertSame(0, Publication::jobs()->count());
        $job->update(['status' => 'active', 'expires_at' => null]);
        $this->assertSame(0, Publication::jobs()->count());
        $job->update(['expires_at' => now('Asia/Jakarta')->subDay()->toDateString()]);
        $this->assertSame(0, Publication::jobs()->count());
        $job->update(['expires_at' => now('Asia/Jakarta')->toDateString()]);
        $this->assertSame(1, Publication::jobs()->count());
        $job->skills()->first()->update(['is_active' => false]);
        $this->assertSame(0, Publication::jobs()->count());
    }

    public function test_admin_cannot_publish_empty_or_expired_job_and_other_roles_cannot_change_status(): void
    {
        $job = JobVacancy::factory()->create();
        $this->actingAs(User::factory()->admin()->create())->patch(route('admin.jobs.publication', $job), ['status' => 'active'])->assertSessionHasErrors('status');
        $good = JobVacancy::factory()->published()->create();
        $this->patch(route('admin.jobs.publication', $good), ['status' => 'inactive'])->assertSessionHasNoErrors();
        $this->assertSame('inactive', $good->fresh()->status);
        $this->actingAs(User::factory()->create())->patch(route('admin.jobs.publication', $good), ['status' => 'active'])->assertForbidden();
        $good->update(['source_url' => 'javascript:alert(1)']);
        $this->assertFalse(Publication::jobs(false)->whereKey($good->id)->exists());
    }

    public function test_expiration_command_is_repeatable_and_today_is_still_valid(): void
    {
        $expired = JobVacancy::factory()->published()->create(['expires_at' => now('Asia/Jakarta')->subDay()->toDateString()]);
        $today = JobVacancy::factory()->published()->create(['expires_at' => now('Asia/Jakarta')->toDateString()]);
        $this->artisan('skillmatching:expire-jobs')->assertSuccessful();
        $this->assertSame('inactive', $expired->fresh()->status);
        $this->assertSame('active', $today->fresh()->status);
        $this->artisan('skillmatching:expire-jobs')->assertSuccessful();
        $this->assertSame(1, Publication::jobs()->count());
    }
}
