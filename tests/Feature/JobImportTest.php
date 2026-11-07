<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class JobImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_previews_mixed_csv_without_writing_to_catalog(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $file = UploadedFile::fake()->createWithContent('lowongan.csv', <<<'CSV'
title,company,location,description,employment_type,source_url,fetched_at
Operator,PT Demo,Bogor,Posisi operator,contract,https://example.test/operator,2025-01-01
Operator,PT Demo,Bogor,Posisi duplikat,contract,https://example.test/operator,2025-01-01
Analis,PT Demo,Bogor,Posisi analis,contract,,2025-01-01
CSV);

        $this->actingAs($admin)->post(route('admin.jobs.import.preview'), ['file' => $file])
            ->assertRedirect(route('admin.jobs.import.show'));
        $this->assertDatabaseCount('job_vacancies', 0);
        $this->get(route('admin.jobs.import.show'))->assertOk()->assertSee('Siap disimpan')->assertSee('Judul dan perusahaan sudah ada.');
    }
}
