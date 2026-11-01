<?php

namespace Database\Seeders;

use App\Models\CertificationBody;
use App\Models\JobTaxonomy;
use App\Models\JobVacancy;
use App\Models\Skill;
use App\Models\Training;
use App\Models\TrainingSubmission;
use App\Models\User;
use App\Services\ApplicantSkills;
use App\Services\TrainingReview;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoScenarioSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('skillmatching.seed.demo_enabled') || (app()->isProduction() && ! config('skillmatching.seed.allow_demo_in_production'))) {
            return;
        }
        $password = (string) config('skillmatching.seed.demo_password');
        if (strlen($password) < 12) {
            throw new RuntimeException('Isi DEMO_PASSWORD minimal 12 karakter.');
        }
        DB::transaction(function () use ($password): void {
            $admin = User::where('email', config('skillmatching.seed.owner_email'))->where('role', 'admin')->firstOrFail();
            $body = CertificationBody::whereHas('user', fn ($q) => $q->where('email', 'kontak@logistiknusantara.id'))->firstOrFail();
            if ($body->status !== 'active' || ! $body->user->is_active || ! $body->user->hasVerifiedEmail()) {
                return;
            }
            $definitions = [
                ['Pengelolaan Stok Dasar', 'Pengelolaan Stok', 'Logistik', 2, 'online', 8, 2, 0],
                ['Forklift Dasar', 'Operasi Forklift', 'Logistik', 2, 'offline', 16, 4, 350000],
                ['Spreadsheet Dasar', 'Spreadsheet', 'Administrasi', 2, 'online', 12, 3, 0],
                ['Keselamatan Kerja Gudang', 'K3', 'Produksi / Logistik', 1, 'hybrid', 8, 4, 150000],
                ['Spreadsheet Lanjutan', 'Spreadsheet', 'Administrasi', 4, 'online', 24, 6, 250000],
                ['Pengantar Spreadsheet', 'Spreadsheet', 'Administrasi', 1, 'online', 4, 1, 0],
                ['Komunikasi Tim', 'Komunikasi Tim', 'Lintas Bidang', 2, 'hybrid', 8, 2, 50000],
            ];
            $courses = [];
            foreach ($definitions as [$title,$skill,$category,$level,$mode,$hours,$weekly,$cost]) {
                $training = Training::where('certification_body_id', $body->id)->where('title', $title)->first();
                if (! $training) {
                    $submission = TrainingSubmission::create(['certification_body_id' => $body->id, 'status' => 'pending', 'submitted_at' => now(), 'payload' => ['title' => $title, 'description' => 'Contoh pelatihan untuk latihan alur Skill Matching. Pembelajaran berlangsung di luar platform.', 'mode' => $mode, 'duration_hours' => $hours, 'weekly_hours' => $weekly, 'cost' => $cost, 'source_url' => 'https://example.org/pelatihan', 'outcomes' => [['skill_id' => 'new', 'new_name' => $skill, 'category' => $category, 'description' => 'Kemampuan untuk konteks studi kasus latihan.', 'target_level' => $level]]]]);
                    app(TrainingReview::class)->decide($submission->id, $admin, ['decision' => 'approved', 'review_note' => 'Informasi dan pemetaan keterampilan contoh telah diperiksa.']);
                    $training = Training::findOrFail($submission->fresh()->training_id);
                    if ($title === 'Spreadsheet Lanjutan' && isset($courses['Spreadsheet Dasar'])) {
                        DB::table('training_prerequisites')->insert(['training_id' => $training->id, 'prerequisite_id' => $courses['Spreadsheet Dasar']->id]);
                    }
                }$courses[$title] = $training;
            }
            $skills = Skill::whereIn('name', ['Pengelolaan Stok', 'Operasi Forklift', 'Spreadsheet', 'K3', 'Komunikasi Tim'])->pluck('id', 'name');
            if ($skills->count() !== 5) {
                return;
            }
            foreach ([
                ['Raka Pratama', 'raka@example.com', [$skills['Pengelolaan Stok'] => 3, $skills['Spreadsheet'] => 1, $skills['K3'] => 2]],
                ['Profil Parsial', 'parsial@example.test', [$skills['Spreadsheet'] => 1]],
                ['Profil Mahir', 'mahir@example.test', [$skills['Pengelolaan Stok'] => 4, $skills['Operasi Forklift'] => 4, $skills['Spreadsheet'] => 4, $skills['K3'] => 4]],
                ['Profil Skill Terkait', 'relasi@example.test', [$skills['Komunikasi Tim'] => 2]],
                ['Profil Digital', 'digital@example.test', [$skills['Spreadsheet'] => 3]],
            ] as [$name,$email,$levels]) {
                $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => $password, 'role' => 'pelamar', 'is_active' => true, 'email_verified_at' => now()]);
                if ($user->wasRecentlyCreated) {
                    app(ApplicantSkills::class)->saveSelfReported($user, $levels);
                }
            }
            $warehouse = JobVacancy::firstOrCreate(['title' => 'Staf Gudang', 'company' => 'PT Logistik Nusantara'], ['location' => 'Bogor', 'description' => 'Contoh posisi tujuan untuk studi kasus latihan; bukan penawaran kerja nyata. Kebutuhan: stok, forklift, spreadsheet, dan keselamatan kerja.', 'employment_type' => 'contract', 'source_url' => 'https://example.org/lowongan', 'source_name' => 'Studi kasus latihan', 'fetched_at' => today(), 'expires_at' => today()->addMonths(3), 'status' => 'active']);
            if ($warehouse->wasRecentlyCreated) {
                foreach ([['Pengelolaan Stok', 2, 'required', 3], ['Operasi Forklift', 2, 'required', 3], ['Spreadsheet', 2, 'required', 3], ['K3', 1, 'preferred', 1]] as [$skill,$level,$importance,$weight]) {
                    $warehouse->skills()->attach($skills[$skill], ['minimum_level' => $level, 'importance' => $importance, 'weight' => $weight, 'is_override' => false]);
                }
            }
            $inventory = JobVacancy::firstOrCreate(['title' => 'Admin Inventori', 'company' => 'PT Manufaktur Indonesia'], ['location' => 'Jakarta', 'description' => 'Contoh posisi tujuan untuk latihan perbandingan kemampuan spreadsheet. Bukan lowongan nyata.', 'employment_type' => 'permanent', 'source_url' => 'https://example.org/inventori', 'source_name' => 'Studi kasus latihan', 'fetched_at' => today(), 'expires_at' => today()->addMonths(3), 'status' => 'active']);
            if ($inventory->wasRecentlyCreated) {
                $inventory->skills()->attach($skills['Spreadsheet'], ['minimum_level' => 2, 'importance' => 'required', 'weight' => 3, 'is_override' => false]);
            }
            $occupation = JobTaxonomy::firstOrCreate(['normalized_name' => Skill::normalize('Staf Gudang')], ['name' => 'Staf Gudang', 'description' => 'Pemetaan contoh konteks pekerjaan pergudangan.', 'is_active' => true]);
            if ($occupation->wasRecentlyCreated) {
                $occupation->skills()->sync([$skills['Pengelolaan Stok'], $skills['Operasi Forklift'], $skills['K3']]);
            }
            DB::table('skill_relations')->insertOrIgnore(['skill_id' => $skills['Pengelolaan Stok'], 'related_skill_id' => $skills['Komunikasi Tim'], 'reason' => 'Hubungan contoh terbatas untuk mendemonstrasikan dukungan skill terkait.', 'created_at' => now(), 'updated_at' => now()]);
            $draft = TrainingSubmission::firstOrCreate(['certification_body_id' => $body->id, 'status' => 'draft', 'training_id' => null], ['payload' => ['title' => 'Rencana Pelatihan Baru']]);
        }, 3);
    }
}
